<?php
require_once __DIR__.'/lib.php';
require_once __DIR__.'/inc/schema.php';
if (user()) redirect('index.php');

const MAX_TRIES = 5;       // failed attempts allowed...
const WINDOW_MIN = 15;     // ...in this many minutes, per email and per address

$err = '';
$expired = !empty($_SESSION['expired']); unset($_SESSION['expired']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    db()->exec(mustr_tables()['login_attempts']);
    $email = strtolower(trim($_POST['email'] ?? ''));
    $ip    = substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
    $since = date('Y-m-d H:i:s', time() - WINDOW_MIN * 60);
    q("DELETE FROM login_attempts WHERE attempted_at < ?", [date('Y-m-d H:i:s', time() - 86400)]);

    $byEmail = (int) col("SELECT COUNT(*) FROM login_attempts WHERE email=? AND attempted_at>=?", [$email, $since]);
    $byIp    = (int) col("SELECT COUNT(*) FROM login_attempts WHERE ip=? AND attempted_at>=?", [$ip, $since]);

    if ($byEmail >= MAX_TRIES || $byIp >= MAX_TRIES * 4) {
        $err = 'Too many failed attempts. Wait '.WINDOW_MIN.' minutes, or ask an admin to reset your password.';
    } else {
        $u = one("SELECT * FROM users WHERE email=? AND active=1", [$email]);
        // verify against a dummy hash when there is no such user, so timing does not reveal who has an account
        $hash = $u['pass_hash'] ?? '$2y$10$Z/il6ofJkGjCFMv/eZana.mt6iQqQWiupuaJnb5yrlPKxuXkLqtyu';
        if (password_verify($_POST['password'] ?? '', $hash) && $u) {
            q("DELETE FROM login_attempts WHERE email=?", [$email]);
            if (password_needs_rehash($u['pass_hash'], PASSWORD_DEFAULT))
                q("UPDATE users SET pass_hash=? WHERE id=?", [password_hash($_POST['password'], PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            $_SESSION = ['uid' => $u['id'], 'shop_id' => $u['shop_id'], 'seen' => time()];
            redirect('index.php');
        }
        q("INSERT INTO login_attempts (email,ip,attempted_at) VALUES (?,?,NOW())", [$email, $ip]);
        $left = MAX_TRIES - $byEmail - 1;
        $err = 'That email and password do not match an account.'.($left > 0 && $left <= 2 ? " $left attempt".($left === 1 ? '' : 's')." left before a short lock-out." : '');
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#0f1a2c"><?= app_head_tags() ?>
<meta name="robots" content="noindex,nofollow">
<title>Sign in · <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/app.css"></head>
<body class="login-body">
<form class="login-card" method="post" autocomplete="on">
  <div class="brand"><span class="brand-mark">M</span>MustrHQ <small>Stock</small></div>
  <h1>Sign in</h1>
  <p class="lede">Count stock, record waste and place orders for your shop.</p>
  <?php if ($expired && !$err): ?><div class="msg warn" role="status"><?= icon('info', 16) ?><span>You were signed out after a while with no activity. Sign in again to carry on.</span></div><?php endif; ?>
  <?php if ($err): ?><div class="msg err" role="alert"><?= icon('alert', 16) ?><span><?= h($err) ?></span></div><?php endif; ?>
  <?= csrf_field() ?>
  <div class="field"><label for="email">Email</label>
    <input id="email" type="email" name="email" required autofocus autocomplete="username"
           value="<?= h($_POST['email'] ?? '') ?>"></div>
  <div class="field"><label for="password">Password</label>
    <input id="password" type="password" name="password" required autocomplete="current-password"></div>
  <button class="btn primary" type="submit">Sign in</button>
  <p class="hint">Forgotten your password? Ask your shop's admin to reset it.</p>
  <p class="hint" style="margin-top:8px"><?= h(APP_NAME) ?> <?= h(MUSTR_VERSION) ?> · <a href="<?= h(APP_SOURCE_URL) ?>" rel="noopener">Source code</a> · AGPL-3.0</p>
</form><script src="assets/app.js"></script></body></html>
