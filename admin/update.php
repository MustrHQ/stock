<?php
/** Updates & backups — install a new release from a zip, and roll back if needed. */
require __DIR__.'/inc.php';
require_once MUSTR_ROOT.'/inc/updater.php';
require_once MUSTR_ROOT.'/inc/schema.php';

$dir = upd_dir();

/* ---------- download a backup ---------- */
if (isset($_GET['download'])) {
    $f = $dir.'/'.basename($_GET['download']);
    if (!preg_match('/^(files-.+\.zip|db-.+\.sql\.gz)$/', basename($f)) || !is_file($f)) { http_response_code(404); exit('Backup not found.'); }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($f).'"');
    header('Content-Length: '.filesize($f));
    readfile($f); exit;
}

/* ---------- second half of an update: new code is now live, so run its database upgrade ---------- */
if (isset($_GET['migrate'])) {
    try {
        mustr_migrate(db());
        flash('Updated to version '.MUSTR_VERSION.'. The database is up to date.');
    } catch (Throwable $e) {
        flash('Files were updated, but the database upgrade failed: '.$e->getMessage().
              ' Your data is untouched. Restore the backup below if the app is not working.', 'err');
    }
    redirect('update.php');
}

$preview = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    @set_time_limit(300);

    /* step 1: upload and inspect */
    if ($action === 'inspect') {
        $f = $_FILES['release'] ?? null;
        $errs = [UPLOAD_ERR_INI_SIZE => 'The zip is bigger than this server allows ('.ini_get('upload_max_filesize').'). Raise upload_max_filesize in cPanel → Select PHP Version → Options.',
                 UPLOAD_ERR_FORM_SIZE => 'The zip is too big.', UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Try again.',
                 UPLOAD_ERR_NO_FILE => 'Choose a release zip first.'];
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) { flash($errs[$f['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'The upload failed.', 'err'); redirect('update.php'); }
        if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'zip') { flash('That is not a .zip file.', 'err'); redirect('update.php'); }
        $token = bin2hex(random_bytes(8));
        $dest  = $dir.'/incoming-'.$token.'.zip';
        if (!move_uploaded_file($f['tmp_name'], $dest)) { flash('Could not save the upload. Check that the backups folder is writable.', 'err'); redirect('update.php'); }
        try {
            $preview = upd_inspect($dest);
            $preview['token'] = $token;
            $preview['file']  = $f['name'];
            $_SESSION['upd_token'] = $token;
            $_SESSION['upd_dbbackup'] = !empty($_POST['dbbackup']);
        } catch (Throwable $e) {
            @unlink($dest);
            flash($e->getMessage(), 'err');
            redirect('update.php');
        }
    }

    /* step 2: back up, then install */
    if ($action === 'apply') {
        $token = $_POST['token'] ?? '';
        if (!preg_match('/^[a-f0-9]{16}$/', $token) || $token !== ($_SESSION['upd_token'] ?? '')) {
            flash('That update has expired. Upload the zip again.', 'err'); redirect('update.php');
        }
        $zip = $dir.'/incoming-'.$token.'.zip';
        try {
            $info = upd_inspect($zip);
            $fb = upd_backup_files('before-'.$info['version']);
            $db = !empty($_SESSION['upd_dbbackup']) ? upd_backup_db() : null;
            $n  = upd_apply($info['files'], $fb);
            @unlink($zip);
            unset($_SESSION['upd_token']);
            flash($n.' files installed. Backups saved as '.$fb.($db ? ' and '.$db : '').'.');
            redirect('update.php?migrate=1');            // run the NEW code's database upgrade
        } catch (Throwable $e) {
            flash($e->getMessage(), 'err'); redirect('update.php');
        }
    }

    if ($action === 'cancel') {
        $token = $_POST['token'] ?? '';
        if (preg_match('/^[a-f0-9]{16}$/', $token)) @unlink($dir.'/incoming-'.$token.'.zip');
        unset($_SESSION['upd_token']);
        flash('Update cancelled. Nothing was changed.');
        redirect('update.php');
    }

    /* roll back to a file backup */
    if ($action === 'restore') {
        $name = basename($_POST['name'] ?? '');
        if (!preg_match('/^files-.+\.zip$/', $name) || !is_file($dir.'/'.$name)) { flash('Backup not found.', 'err'); redirect('update.php'); }
        try {
            $info = upd_inspect($dir.'/'.$name);
            $fb = upd_backup_files('before-restore');
            upd_apply($info['files'], $fb);
            flash('Restored version '.$info['version'].' from '.$name.'. What was running before is saved as '.$fb.'.');
            redirect('update.php?migrate=1');
        } catch (Throwable $e) { flash($e->getMessage(), 'err'); redirect('update.php'); }
    }

    if ($action === 'backup_now') {
        try {
            $fb = upd_backup_files('manual');
            $db = upd_backup_db();
            flash('Backups saved: '.$fb.' and '.$db.'.');
        } catch (Throwable $e) { flash($e->getMessage(), 'err'); }
        redirect('update.php');
    }

    if ($action === 'delete') {
        $name = basename($_POST['name'] ?? '');
        if (preg_match('/^(files-.+\.zip|db-.+\.sql\.gz)$/', $name)) @unlink($dir.'/'.$name);
        flash('Backup deleted.');
        redirect('update.php');
    }
}

// tidy away abandoned uploads older than a day
foreach (glob($dir.'/incoming-*.zip') ?: [] as $old) if (filemtime($old) < time() - 86400) @unlink($old);

try { $dbVersion = (string) col("SELECT v FROM meta WHERE k='db_version'"); } catch (PDOException $e) { $dbVersion = ''; }
$maxUpload = min(upd_ini_bytes(ini_get('upload_max_filesize')), upd_ini_bytes(ini_get('post_max_size')));
$backups   = upd_backups();
$writable  = is_writable(MUSTR_ROOT) && is_writable($dir);
admin_header('Updates & backups', 'update');
?>
<div class="page-head">
  <div><h1 class="admin-title">Updates &amp; backups</h1>
    <p class="lede" style="margin:0">Install a new release without FTP. Your settings, the install lock and every backup are
      left alone, and the app is backed up before anything is replaced.</p></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="backup_now">
    <button class="btn"><?= icon('save', 16) ?>Back up now</button></form>
</div>

<div class="kpis">
  <div class="kpi"><div class="l">Running version</div><div class="v"><?= h(MUSTR_VERSION) ?></div>
    <div class="h">Database at <?= h($dbVersion ?: 'unknown') ?><?= $dbVersion && $dbVersion !== MUSTR_VERSION ? ' — run the upgrade below' : '' ?></div></div>
  <div class="kpi"><div class="l">Largest upload allowed</div><div class="v"><?= h(upd_bytes($maxUpload)) ?></div>
    <div class="h">Set by your host's PHP settings</div></div>
  <div class="kpi"><div class="l">Zip support</div>
    <div class="v <?= upd_can_zip() || upd_can_phar() ? 'pos' : 'neg' ?>"><?= upd_can_zip() ? 'Ready' : (upd_can_phar() ? 'Ready' : 'Missing') ?></div>
    <div class="h"><?= upd_can_zip() ? 'PHP zip extension' : (upd_can_phar() ? 'Using Phar — slower, works fine' : 'Ask your host to enable zip') ?></div></div>
  <div class="kpi"><div class="l">Folder permissions</div>
    <div class="v <?= $writable ? 'pos' : 'neg' ?>"><?= $writable ? 'Writable' : 'Read-only' ?></div>
    <div class="h"><?= $writable ? 'The updater can replace files' : 'Fix permissions, or update by FTP' ?></div></div>
</div>

<?php if ($preview): ?>
<section class="card" style="border-color:var(--brand)">
  <div class="rep-head"><div><h2><?= icon('upload', 17) ?> Ready to install version <?= h($preview['version']) ?></h2>
    <div class="wk">From <?= h($preview['file']) ?>. You are running <?= h(MUSTR_VERSION) ?>.</div></div></div>
  <div class="pad">
    <?php if (version_compare($preview['version'], MUSTR_VERSION, '<')): ?>
      <div class="msg warn"><?= icon('alert', 16) ?><span>This is an <strong>older</strong> version than the one running. Only continue if you mean to go back.</span></div>
    <?php elseif (version_compare($preview['version'], MUSTR_VERSION, '==')): ?>
      <div class="msg warn"><?= icon('info', 16) ?><span>This is the same version you are running. Installing it again repairs any damaged files.</span></div>
    <?php endif; ?>
    <div class="kpis" style="margin-bottom:12px">
      <div class="kpi"><div class="l">New files</div><div class="v"><?= (int)$preview['new'] ?></div></div>
      <div class="kpi"><div class="l">Changed files</div><div class="v"><?= (int)$preview['changed'] ?></div></div>
      <div class="kpi"><div class="l">Unchanged</div><div class="v"><?= (int)$preview['same'] ?></div></div>
      <div class="kpi"><div class="l">Kept as they are</div><div class="v"><?= count(array_filter($preview['skipped'], fn($w) => strpos($w, 'kept') === 0)) ?></div>
        <div class="h">config.php, installer, backups</div></div>
    </div>
    <?php if ($preview['missing']): ?>
      <p class="lede"><?= count($preview['missing']) ?> file<?= count($preview['missing']) === 1 ? '' : 's' ?> on the server
        <?= count($preview['missing']) === 1 ? 'is' : 'are' ?> not in this release and will be left in place:
        <?= h(implode(', ', array_slice($preview['missing'], 0, 6))) ?><?= count($preview['missing']) > 6 ? '…' : '' ?></p>
    <?php endif; ?>
    <p class="lede">Before installing, the current files<?= !empty($_SESSION['upd_dbbackup']) ? ' and the database' : '' ?> will be
      backed up. If a file cannot be written, the backup is put straight back.</p>
    <div class="btn-row">
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="apply">
        <input type="hidden" name="token" value="<?= h($preview['token']) ?>">
        <button class="btn primary"><?= icon('check', 16) ?>Back up and install <?= h($preview['version']) ?></button></form>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="cancel">
        <input type="hidden" name="token" value="<?= h($preview['token']) ?>">
        <button class="btn">Cancel</button></form>
    </div>
  </div>
</section>
<?php else: ?>
<section class="card pad">
  <h2 class="card-title">Install a release</h2>
  <p class="lede">Upload the release zip exactly as downloaded. You will see what changes before anything happens.</p>
  <form method="post" enctype="multipart/form-data" class="inline">
    <?= csrf_field() ?><input type="hidden" name="action" value="inspect">
    <div style="flex:2 1 280px"><label for="release">Release zip</label>
      <input id="release" type="file" name="release" accept=".zip,application/zip" required
             style="height:auto;padding:8px"></div>
    <div class="days"><label><input type="checkbox" name="dbbackup" value="1" checked> Back up the database too</label></div>
    <div><button class="btn primary" <?= $writable ? '' : 'disabled' ?>><?= icon('upload', 16) ?>Check release</button></div>
  </form>
</section>
<?php endif; ?>

<?php if (!$dbVersion || version_compare($dbVersion, MUSTR_VERSION, '<')): ?>
<section class="card pad">
  <h2 class="card-title">Database upgrade waiting</h2>
  <p class="lede">The files are on <?= h(MUSTR_VERSION) ?> but the database was last upgraded at <?= h($dbVersion ?: 'an earlier version') ?> —
    usually because the files were copied up by FTP. Run the upgrade to add anything new. Existing data is not changed.</p>
  <a class="btn primary" href="update.php?migrate=1"><?= icon('refresh', 16) ?>Upgrade database</a>
</section>
<?php endif; ?>

<h2 class="section-title">Backups</h2>
<p class="lede">Kept in the <code>backups</code> folder, which the web cannot read directly. Download the ones you care about —
  a copy on the same server will not help if the server itself fails. Database backups restore through phpMyAdmin → Import.</p>
<div class="card scroll">
  <table>
    <thead><tr><th>Backup</th><th>Type</th><th>Taken</th><th class="num">Size</th><th></th></tr></thead>
    <tbody>
      <?php if (!$backups): ?><tr><td colspan="5" class="empty">No backups yet. One is made automatically before every update.</td></tr><?php endif; ?>
      <?php foreach ($backups as $b): ?>
        <tr>
          <td><code><?= h($b['name']) ?></code></td>
          <td><span class="tag <?= $b['kind'] === 'Database' ? 'blue' : '' ?>"><?= h($b['kind']) ?></span></td>
          <td><?= h(date('d/m/Y H:i', $b['time'])) ?></td>
          <td class="num"><?= h(upd_bytes($b['size'])) ?></td>
          <td class="num"><div class="btn-row" style="justify-content:flex-end;flex-wrap:nowrap">
            <a class="btn sm" href="update.php?download=<?= urlencode($b['name']) ?>" title="Download"><?= icon('download', 15) ?></a>
            <?php if ($b['kind'] === 'Files'): ?>
              <form method="post" class="inline-form" onsubmit="return confirm('Put this version back? What is running now is backed up first.')">
                <?= csrf_field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="name" value="<?= h($b['name']) ?>">
                <button class="btn sm"><?= icon('refresh', 15) ?>Restore</button></form>
            <?php endif; ?>
            <form method="post" class="inline-form" onsubmit="return confirm('Delete this backup for good?')">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="name" value="<?= h($b['name']) ?>">
              <button class="btn sm danger" title="Delete"><?= icon('trash', 15) ?></button></form>
          </div></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_footer();
