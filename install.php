<?php
/**
 * MustrHQ Stock — installer.
 *
 * A four-step setup wizard: requirements, database, shop and admin account, done.
 * It writes config.php for you. Delete this file once you have finished.
 */

if (!function_exists('h')) { function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/version.php';
require_once __DIR__.'/inc/schema.php';
require_once __DIR__.'/inc/demo.php';
require_once __DIR__.'/inc/icons.php';

$ROOT      = __DIR__;
$hasConfig = file_exists($ROOT.'/config.php');
if ($hasConfig) require_once $ROOT.'/config.php';

function wiz_pdo($host, $name, $user, $pass) {
    return new PDO('mysql:host='.$host.';dbname='.$name.';charset=utf8mb4', $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
function wiz_config_text($v) {
    $tpl = file_get_contents(__DIR__.'/config-sample.php');
    $map = [
        "'database_name_here'" => var_export($v['name'], true),
        "'username_here'"      => var_export($v['user'], true),
        "'password_here'"      => var_export($v['pass'], true),
        "'localhost'"          => var_export($v['host'], true),
        "'MustrHQ Stock'"      => var_export($v['app'] ?? 'MustrHQ Stock', true),
        "'Europe/London'"      => var_export($v['tz'] ?? 'Europe/London', true),
        "'£'"                  => var_export($v['ccy'] ?? '£', true),
    ];
    return str_replace(array_keys($map), array_values($map), $tpl);
}
function wiz_installed() {
    if (!defined('DB_NAME')) return false;
    try {
        $pdo = wiz_pdo(DB_HOST, DB_NAME, DB_USER, DB_PASS);
        $n = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        return $n > 0;
    } catch (Throwable $e) { return false; }
}

$step    = (int)($_GET['step'] ?? 1);
$errors  = [];
$notices = [];
$manual  = null;
$done    = false;

/* ---------------- already installed ---------------- */
/* Once installed, the wizard is locked for good. There is deliberately no override. */
$locked    = file_exists($ROOT.'/install.lock');
$installed = $locked || wiz_installed();
if ($installed && !$locked) @file_put_contents($ROOT.'/install.lock', 'Installed '.date('c')."\n");

/** Only a signed-in admin may run the upgrade on a live install. */
function wiz_is_admin() {
    if (empty($_SESSION['uid']) || !defined('DB_NAME')) return false;
    try {
        $s = wiz_pdo(DB_HOST, DB_NAME, DB_USER, DB_PASS)->prepare("SELECT role FROM users WHERE id=? AND active=1");
        $s->execute([$_SESSION['uid']]);
        return $s->fetchColumn() === 'admin';
    } catch (Throwable $e) { return false; }
}

if ($installed) {
    if (($_POST['action'] ?? '') === 'upgrade' && !wiz_is_admin()) {
        $errors[] = 'Sign in as an admin first, then come back to run the upgrade.';
    } elseif (($_POST['action'] ?? '') === 'upgrade') {
        try {
            mustr_migrate(wiz_pdo(DB_HOST, DB_NAME, DB_USER, DB_PASS));
            $notices[] = 'Database is up to date. Ordering tables are ready.';
        } catch (Throwable $e) { $errors[] = $e->getMessage(); }
    }
    $step = 0;
}

/* ---------------- step 2: database ---------------- */
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $v = [
        'host' => trim($_POST['host'] ?? 'localhost'),
        'name' => trim($_POST['name'] ?? ''),
        'user' => trim($_POST['user'] ?? ''),
        'pass' => (string)($_POST['pass'] ?? ''),
    ];
    if ($v['name'] === '' || $v['user'] === '') $errors[] = 'Database name and user are both needed.';
    if (!$errors) {
        try { wiz_pdo($v['host'], $v['name'], $v['user'], $v['pass']); }
        catch (Throwable $e) {
            $errors[] = 'Could not connect: '.$e->getMessage().
                ' — check the database name, user and password in cPanel, and that the user is added to the database.';
        }
    }
    if (!$errors) {
        $_SESSION['wiz_db'] = $v;
        $text = wiz_config_text($v);
        if (@file_put_contents($ROOT.'/config.php', $text) !== false) {
            @chmod($ROOT.'/config.php', 0640);
            header('Location: install.php?step=3'); exit;
        }
        $manual = $text;   // folder not writable — let them paste it in
    }
}
if ($step === 3 && ($_GET['written'] ?? '') === '1' && !file_exists($ROOT.'/config.php')) {
    $errors[] = 'config.php still is not there. Create it in the same folder as install.php, then reload this page.';
    $step = 2;
}

/* ---------------- step 3: shop + admin ---------------- */
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = $_SESSION['wiz_db'] ?? null;
    if (!$db && defined('DB_NAME')) $db = ['host' => DB_HOST, 'name' => DB_NAME, 'user' => DB_USER, 'pass' => DB_PASS];

    $f = [
        'app'   => trim($_POST['app'] ?? 'MustrHQ Stock'),
        'tz'    => trim($_POST['tz'] ?? 'Europe/London'),
        'ccy'   => trim($_POST['ccy'] ?? '£'),
        'scode' => trim($_POST['shop_code'] ?? ''),
        'sname' => trim($_POST['shop_name'] ?? ''),
        'saddr' => trim($_POST['shop_address'] ?? ''),
        'uname' => trim($_POST['admin_name'] ?? ''),
        'email' => trim($_POST['admin_email'] ?? ''),
        'pass'  => (string)($_POST['admin_pass'] ?? ''),
        'demo'  => !empty($_POST['demo']),
    ];
    if ($f['scode'] === '' || $f['sname'] === '') $errors[] = 'Give your first shop a code and a name.';
    if ($f['uname'] === '' || !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter your name and a valid email address.';
    if (strlen($f['pass']) < 8) $errors[] = 'Choose a password of at least 8 characters.';
    if (!in_array($f['tz'], timezone_identifiers_list(), true)) $errors[] = 'That time zone is not one PHP knows about.';

    if (!$errors && $db) {
        try {
            // keep the app settings in config.php
            @file_put_contents($ROOT.'/config.php', wiz_config_text($db + ['app' => $f['app'], 'tz' => $f['tz'], 'ccy' => $f['ccy']]));

            $pdo = wiz_pdo($db['host'], $db['name'], $db['user'], $db['pass']);
            mustr_migrate($pdo);

            $ins = function ($sql, $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s; };
            $cnt = fn($t) => (int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();

            if (!$cnt('shops'))
                $ins("INSERT INTO shops (code,name,address) VALUES (?,?,?)", [$f['scode'], $f['sname'], $f['saddr']]);
            $shopId = (int)$pdo->query("SELECT id FROM shops ORDER BY id LIMIT 1")->fetchColumn();

            if (!$cnt('units'))
                foreach (default_units() as $u) $ins("INSERT INTO units (name) VALUES (?)", [$u]);

            if ($f['demo']) demo_seed($pdo);

            if (!$cnt('users')) {
                $ins("INSERT INTO users (name,email,pass_hash,role,shop_id,created_at) VALUES (?,?,?,'admin',?,NOW())",
                     [$f['uname'], $f['email'], password_hash($f['pass'], PASSWORD_DEFAULT), $shopId]);
            } else {
                $errors[] = 'There are already users in this database. Sign in with an existing account instead.';
            }
            if (!$errors) {
                unset($_SESSION['wiz_db']); $done = true; $step = 4;
                @file_put_contents($ROOT.'/install.lock', 'Installed '.date('c')."\n");
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
    $POSTED = $f;
}

$STEPS = [1 => 'Before you start', 2 => 'Database', 3 => 'Your shop', 4 => 'Done'];
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install – MustrHQ Stock</title><link rel="stylesheet" href="assets/app.css?v=<?= h(MUSTR_VERSION) ?>"></head>
<body class="wiz-body">
<header class="wiz-top">
  <div class="brand"><span class="brand-box"><img src="assets/brand/logo-mark.svg" alt="" width="26" height="26"></span>
    <span class="brand-name">MustrHQ Stock<small>Set-up</small></span></div>
</header>
<div class="wiz">
  <div class="wiz-head">
    <h1>Set up your stock system</h1>
    <p>About five minutes. There are no files to edit by hand.</p>
  </div>

  <?php if ($step >= 1 && $step <= 4): ?>
  <ol class="wiz-steps">
    <?php foreach ($STEPS as $n => $label): ?>
      <li class="<?= $n < $step ? 'past' : ($n === $step ? 'now' : '') ?>"><span><?= $n ?></span><?= h($label) ?></li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>

  <div class="wiz-card">
  <?php foreach ($errors as $e): ?><div class="msg err" role="alert"><?= icon('alert', 16) ?><span><?= h($e) ?></span></div><?php endforeach; ?>
  <?php foreach ($notices as $n): ?><div class="msg ok" role="status"><?= icon('check', 16) ?><span><?= h($n) ?></span></div><?php endforeach; ?>

  <?php if ($step === 0): /* ---------- already installed ---------- */ ?>
    <h2>MustrHQ Stock is already installed</h2>
    <p>Nothing to do here. If you have just pulled a newer version of the code, run the database
       upgrade — it adds anything missing and leaves your data alone.</p>
    <form method="post" class="wiz-actions">
      <button class="btn primary" name="action" value="upgrade">Upgrade database</button>
      <a class="btn" href="login.php">Sign in</a>
    </form>
    <p class="msg warn" style="margin-top:18px">Delete <code>install.php</code> from the server when you are finished with it.</p>

  <?php elseif ($manual !== null): /* ---------- cannot write config.php ---------- */ ?>
    <h2>Create config.php yourself</h2>
    <p>The folder is not writable, so the installer could not save the file. Create a file called
       <code>config.php</code> next to <code>install.php</code>, paste this in, and save it.</p>
    <textarea rows="16" readonly onclick="this.select()"><?= h($manual) ?></textarea>
    <div class="wiz-actions"><a class="btn primary" href="install.php?step=3&written=1">I have created the file</a></div>

  <?php elseif ($step === 1): /* ---------- requirements ---------- */
    $checks = [
      ['PHP 7.4 or newer', version_compare(PHP_VERSION, '7.4', '>='), 'Running PHP '.PHP_VERSION],
      ['PDO MySQL extension', extension_loaded('pdo_mysql'), 'Turn it on in cPanel → Select PHP Version'],
      ['Sessions', session_status() === PHP_SESSION_ACTIVE, 'Needed to keep you signed in'],
      ['Folder is writable', is_writable($ROOT), 'Otherwise you will paste config.php in by hand — that is fine too'],
    ];
    $blocking = !$checks[0][1] || !$checks[1][1];
  ?>
    <h2>Before you start</h2>
    <p>Have your database details to hand. In cPanel: <strong>MySQL Databases</strong> → create a database,
       create a user, then add the user to the database with all privileges.</p>
    <ul class="checks">
      <?php foreach ($checks as $c): ?>
        <li class="<?= $c[1] ? 'yes' : 'no' ?>"><?= icon($c[1] ? 'check' : 'x', 18) ?><div><strong><?= h($c[0]) ?></strong><span><?= h($c[2]) ?></span></div></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($blocking): ?>
      <div class="msg err">Sort the red items above before carrying on.</div>
    <?php else: ?>
      <div class="wiz-actions"><a class="btn primary" href="install.php?step=2">Continue<?= icon('right', 16) ?></a></div>
    <?php endif; ?>

  <?php elseif ($step === 2): /* ---------- database ---------- */
    $v = $_POST + ($_SESSION['wiz_db'] ?? []);
  ?>
    <h2>Database details</h2>
    <p>These come from cPanel. The name and user usually start with your account prefix,
       something like <code>myaccount_stock</code>.</p>
    <form method="post" action="install.php?step=2">
      <div class="grid g2">
        <div><label for="name">Database name</label>
          <input id="name" name="name" required value="<?= h($v['name'] ?? '') ?>" autofocus></div>
        <div><label for="user">Database user</label>
          <input id="user" name="user" required value="<?= h($v['user'] ?? '') ?>"></div>
        <div><label for="pass">Database password</label>
          <input id="pass" type="password" name="pass" value="<?= h($v['pass'] ?? '') ?>"></div>
        <div><label for="host">Database host</label>
          <input id="host" name="host" value="<?= h($v['host'] ?? 'localhost') ?>"></div>
      </div>
      <div class="wiz-actions"><button class="btn primary">Test connection and continue</button></div>
    </form>

  <?php elseif ($step === 3): /* ---------- shop + admin ---------- */
    $p = $POSTED ?? [];
  ?>
    <h2>Your shop and your account</h2>
    <p>One shop to start with — you can add more later in the admin panel.</p>
    <form method="post" action="install.php?step=3">
      <div class="grid g2">
        <div><label for="app">What to call the app</label>
          <input id="app" name="app" value="<?= h($p['app'] ?? 'MustrHQ Stock') ?>"></div>
        <div><label for="ccy">Currency symbol</label>
          <input id="ccy" name="ccy" value="<?= h($p['ccy'] ?? '£') ?>"></div>
        <div><label for="tz">Time zone</label>
          <select id="tz" name="tz">
            <?php foreach (timezone_identifiers_list() as $z): ?>
              <option <?= ($p['tz'] ?? 'Europe/London') === $z ? 'selected' : '' ?>><?= h($z) ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>
      <h3>First shop</h3>
      <div class="grid g3">
        <div><label for="shop_code">Shop code</label>
          <input id="shop_code" name="shop_code" required placeholder="001" value="<?= h($p['scode'] ?? '') ?>"></div>
        <div><label for="shop_name">Shop name</label>
          <input id="shop_name" name="shop_name" required placeholder="High Street" value="<?= h($p['sname'] ?? '') ?>"></div>
        <div><label for="shop_address">Address</label>
          <input id="shop_address" name="shop_address" placeholder="1 Example Road" value="<?= h($p['saddr'] ?? '') ?>"></div>
      </div>
      <h3>Admin account</h3>
      <div class="grid g3">
        <div><label for="admin_name">Your name</label>
          <input id="admin_name" name="admin_name" required value="<?= h($p['uname'] ?? '') ?>"></div>
        <div><label for="admin_email">Email</label>
          <input id="admin_email" type="email" name="admin_email" required value="<?= h($p['email'] ?? '') ?>"></div>
        <div><label for="admin_pass">Password</label>
          <input id="admin_pass" type="password" name="admin_pass" required minlength="8" autocomplete="new-password"></div>
      </div>
      <div class="days" style="margin-top:18px">
        <label><input type="checkbox" name="demo" value="1" <?= !empty($p['demo']) ? 'checked' : '' ?>>
          Add demo data to try things out — a fictional takeaway menu, suppliers and barcodes. You can remove it later in one click.</label>
      </div>
      <div class="wiz-actions"><button class="btn primary">Install</button></div>
    </form>

  <?php elseif ($step === 4 && $done): /* ---------- done ---------- */ ?>
    <h2>That's it</h2>
    <p>The database is set up and your admin account is ready. Sign in with the email and password
       you just chose.</p>
    <p class="msg warn"><strong>One last thing:</strong> delete <code>install.php</code> from the server.
       Anyone who finds it on a half-configured site can cause mischief.</p>
    <div class="wiz-actions"><a class="btn primary" href="login.php">Sign in<?= icon('right', 16) ?></a></div>

  <?php else: ?>
    <h2>Start again</h2>
    <p>That step is out of sequence.</p>
    <a class="btn primary" href="install.php?step=1">Back to the beginning</a>
  <?php endif; ?>
  </div>
  <p class="wiz-foot">MustrHQ Stock is free software under the GNU AGPL-3.0.</p>
</div>
</body></html>
