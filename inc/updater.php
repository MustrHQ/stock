<?php
/**
 * Updater engine — used only by admin/update.php.
 *
 * A release zip is read into memory, checked, and written file by file. Nothing in the zip is
 * executed before it is installed: its version is read from version.php as text.
 * Works with PHP's zip extension, or falls back to PharData on hosts without it.
 */

const UPD_KEEP     = ['config.php', 'install.lock', 'install.php'];      // never overwritten
const UPD_KEEP_DIR = ['backups/', 'uploads/', '.git/'];
const UPD_EXT      = ['php','css','js','json','md','txt','html','png','jpg','jpeg','gif','svg','webp','ico',
                      'woff','woff2','webmanifest','sql'];
const UPD_NAMED    = ['.htaccess', '.gitignore', 'LICENSE'];

function upd_dir() {
    $d = MUSTR_ROOT.'/backups';
    if (!is_dir($d)) @mkdir($d, 0750, true);
    if (!file_exists($d.'/.htaccess')) @file_put_contents($d.'/.htaccess', "Require all denied\n");
    if (!file_exists($d.'/index.php')) @file_put_contents($d.'/index.php', "<?php http_response_code(404);\n");
    return $d;
}

function upd_can_zip()  { return class_exists('ZipArchive'); }
function upd_can_phar() { return class_exists('PharData'); }

/** name => bytes for every file in a zip. */
function upd_read_zip($path) {
    $out = [];
    if (upd_can_zip()) {
        $z = new ZipArchive();
        if ($z->open($path) !== true) throw new RuntimeException('That file is not a readable zip.');
        for ($i = 0; $i < $z->numFiles; $i++) {
            $name = $z->getNameIndex($i);
            if (substr($name, -1) === '/') continue;
            $out[$name] = $z->getFromIndex($i);
        }
        $z->close();
        return $out;
    }
    if (upd_can_phar()) {
        try { $p = new PharData($path); }
        catch (Throwable $e) { throw new RuntimeException('That file is not a readable zip.'); }
        $prefix = 'phar://'.realpath($path).'/';
        foreach (new RecursiveIteratorIterator($p) as $f) {
            $name = substr(str_replace('\\', '/', $f->getPathname()), strlen($prefix));
            $out[$name] = file_get_contents($f->getPathname());
        }
        return $out;
    }
    throw new RuntimeException('This server has neither the zip extension nor Phar, so it cannot open zip files. Ask your host to enable "zip".');
}

/** Write a zip from name => bytes. */
function upd_write_zip($path, array $files) {
    if (upd_can_zip()) {
        $z = new ZipArchive();
        if ($z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not create the backup file.');
        foreach ($files as $name => $bytes) $z->addFromString($name, $bytes);
        $z->close();
        return;
    }
    $p = new PharData($path, 0, null, Phar::ZIP);
    foreach ($files as $name => $bytes) $p->addFromString($name, $bytes);
}

/** Is this relative path safe to write, and is it ours to write? Returns a reason when not. */
function upd_path_problem($rel) {
    if ($rel === '' || strpos($rel, "\0") !== false) return 'empty name';
    if (strpos($rel, '\\') !== false || $rel[0] === '/' || preg_match('#^[A-Za-z]:#', $rel)) return 'absolute path';
    foreach (explode('/', $rel) as $seg) if ($seg === '..' || $seg === '.') return 'path climbs out of the app folder';
    $base = basename($rel);
    $ext  = strtolower(pathinfo($base, PATHINFO_EXTENSION));
    if (!in_array($base, UPD_NAMED, true) && !in_array($ext, UPD_EXT, true)) return 'file type not allowed (.'.$ext.')';
    return null;
}
function upd_is_kept($rel) {
    if (in_array($rel, UPD_KEEP, true)) return true;
    foreach (UPD_KEEP_DIR as $d) if (strpos($rel, $d) === 0) return true;
    return false;
}

/**
 * Check an uploaded release. Returns [
 *   version, app, files (rel => bytes to write), skipped (rel => reason), new, changed, same, missing
 * ] or throws with a plain explanation.
 */
function upd_inspect($zipPath) {
    $raw = upd_read_zip($zipPath);
    if (!$raw) throw new RuntimeException('The zip is empty.');

    // Find the app folder inside the zip: the one holding version.php and lib.php.
    $prefix = null;
    foreach (array_keys($raw) as $n) {
        if (preg_match('#^(.*?)version\.php$#', $n, $m) && isset($raw[$m[1].'lib.php'])) {
            if ($prefix === null || strlen($m[1]) < strlen($prefix)) $prefix = $m[1];
        }
    }
    if ($prefix === null) throw new RuntimeException('This is not a MustrHQ Stock release — there is no version.php next to lib.php.');
    if (substr_count(trim($prefix, '/'), '/') > 0) throw new RuntimeException('The app folder is buried too deep inside this zip.');

    $vsrc = $raw[$prefix.'version.php'];
    preg_match("#define\\('MUSTR_APP',\\s*'([^']+)'\\)#", $vsrc, $am);
    preg_match("#define\\('MUSTR_VERSION',\\s*'([0-9][0-9A-Za-z\\.\\-]*)'\\)#", $vsrc, $vm);
    if (($am[1] ?? '') !== MUSTR_APP) throw new RuntimeException('This zip is for a different app ('.h($am[1] ?? 'unknown').'), not MustrHQ Stock.');
    if (empty($vm[1])) throw new RuntimeException('The release has no readable version number.');

    $files = []; $skipped = []; $new = $changed = $same = 0;
    foreach ($raw as $name => $bytes) {
        if ($prefix !== '' && strpos($name, $prefix) !== 0) { $skipped[$name] = 'outside the app folder'; continue; }
        $rel = substr($name, strlen($prefix));
        if ($rel === '' || strpos($rel, '__MACOSX/') === 0 || basename($rel) === '.DS_Store') continue;
        if ($why = upd_path_problem($rel)) {
            if ($why !== null && strpos($why, 'file type') !== 0) {
                throw new RuntimeException('Refused: the zip contains an unsafe path ('.$rel.' — '.$why.'). It may have been tampered with.');
            }
            $skipped[$rel] = $why; continue;
        }
        if (upd_is_kept($rel)) { $skipped[$rel] = 'kept — your copy is never replaced'; continue; }
        $files[$rel] = $bytes;
        $cur = MUSTR_ROOT.'/'.$rel;
        if (!file_exists($cur)) $new++;
        elseif (hash_file('sha1', $cur) !== sha1($bytes)) $changed++;
        else $same++;
    }
    $missing = [];
    foreach (upd_current_files() as $rel => $_) if (!isset($files[$rel]) && !upd_is_kept($rel)) $missing[] = $rel;

    return ['app' => $am[1], 'version' => $vm[1], 'files' => $files, 'skipped' => $skipped,
            'new' => $new, 'changed' => $changed, 'same' => $same, 'missing' => $missing];
}

/** rel => absolute path for every file that belongs to the running app. */
function upd_current_files() {
    $out = [];
    $root = realpath(MUSTR_ROOT);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
        foreach (UPD_KEEP_DIR as $d) if (strpos($rel, $d) === 0) continue 2;
        if ($rel === 'config.php' || $rel === 'install.lock') continue;
        $out[$rel] = $f->getPathname();
    }
    return $out;
}

/** Zip up the app as it is now. Returns the backup file name. */
function upd_backup_files($label) {
    $files = [];
    foreach (upd_current_files() as $rel => $abs) $files['mustr-stock/'.$rel] = file_get_contents($abs);
    $name = 'files-'.preg_replace('/[^0-9A-Za-z\.\-]/', '', MUSTR_VERSION).'-'.date('Ymd-His').($label ? '-'.$label : '').'.zip';
    upd_write_zip(upd_dir().'/'.$name, $files);
    return $name;
}

/** Plain SQL dump of the app's tables, gzipped. Returns the file name. */
function upd_backup_db() {
    $name = 'db-'.date('Ymd-His').'.sql.gz';
    $gz = gzopen(upd_dir().'/'.$name, 'w6');
    if (!$gz) throw new RuntimeException('Could not write the database backup.');
    gzwrite($gz, "-- MustrHQ Stock ".MUSTR_VERSION." database backup, ".date('c')."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    foreach (db()->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $create = db()->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
        gzwrite($gz, "DROP TABLE IF EXISTS `$t`;\n$create;\n");
        $st = db()->query("SELECT * FROM `$t`");
        $batch = [];
        while ($row = $st->fetch(PDO::FETCH_NUM)) {
            $batch[] = '('.implode(',', array_map(fn($v) => $v === null ? 'NULL' : db()->quote($v), $row)).')';
            if (count($batch) === 200) { gzwrite($gz, "INSERT INTO `$t` VALUES ".implode(",\n", $batch).";\n"); $batch = []; }
        }
        if ($batch) gzwrite($gz, "INSERT INTO `$t` VALUES ".implode(",\n", $batch).";\n");
        gzwrite($gz, "\n");
    }
    gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
    gzclose($gz);
    return $name;
}

/**
 * Write the files. Each is written beside its target and renamed into place, so a request
 * that lands mid-update never reads half a file. On any failure the backup is put back.
 */
function upd_apply(array $files, $backupName) {
    $written = [];
    try {
        foreach ($files as $rel => $bytes) {
            $dest = MUSTR_ROOT.'/'.$rel;
            $dir  = dirname($dest);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new RuntimeException('Could not create folder '.$rel);
            $tmp = $dest.'.upd-'.bin2hex(random_bytes(3));
            if (@file_put_contents($tmp, $bytes) === false) throw new RuntimeException('Could not write '.$rel.' — check folder permissions.');
            if (!@rename($tmp, $dest)) { @unlink($tmp); throw new RuntimeException('Could not replace '.$rel); }
            $written[] = $rel;
        }
    } catch (Throwable $e) {
        if ($backupName) upd_restore_quiet($backupName);
        throw new RuntimeException($e->getMessage().' Nothing was changed — the previous version has been put back.');
    }
    if (function_exists('opcache_reset')) @opcache_reset();
    return count($written);
}

function upd_restore_quiet($backupName) {
    try {
        $info = upd_inspect(upd_dir().'/'.basename($backupName));
        foreach ($info['files'] as $rel => $bytes) @file_put_contents(MUSTR_ROOT.'/'.$rel, $bytes);
        if (function_exists('opcache_reset')) @opcache_reset();
    } catch (Throwable $e) { /* best effort */ }
}

function upd_backups() {
    $out = [];
    foreach (glob(upd_dir().'/{files-*.zip,db-*.sql.gz}', GLOB_BRACE) ?: [] as $f) {
        $out[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f),
                  'kind' => strpos(basename($f), 'db-') === 0 ? 'Database' : 'Files'];
    }
    usort($out, fn($a, $b) => $b['time'] <=> $a['time']);
    return $out;
}

function upd_bytes($n) {
    foreach (['B','KB','MB','GB'] as $u) { if ($n < 1024) return round($n, $u === 'B' ? 0 : 1).' '.$u; $n /= 1024; }
    return round($n, 1).' TB';
}
function upd_ini_bytes($v) {
    $v = trim((string)$v); $n = (float)$v;
    switch (strtolower(substr($v, -1))) { case 'g': $n *= 1024; case 'm': $n *= 1024; case 'k': $n *= 1024; }
    return (int)$n;
}
