<?php
/**
 * Loads settings and the small helper layer. Everything else requires lib.php,
 * which requires this file.
 */

if (!defined('MUSTR_ROOT')) define('MUSTR_ROOT', __DIR__);
require_once MUSTR_ROOT.'/version.php';

if (file_exists(MUSTR_ROOT.'/config.php')) {
    require_once MUSTR_ROOT.'/config.php';
} elseif (!defined('DB_NAME')) {
    // Nothing configured yet — send the visitor to the installer.
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    if (substr($dir, -6) === '/admin') $dir = substr($dir, 0, -6);
    header('Location: '.($dir === '' ? '' : $dir).'/install.php');
    exit;
}

if (!defined('APP_NAME')) define('APP_NAME', 'MustrHQ Stock');
if (!defined('APP_TZ'))   define('APP_TZ', 'Europe/London');
if (!defined('APP_CCY'))  define('APP_CCY', '£');
if (!defined('APP_SOURCE_URL')) define('APP_SOURCE_URL', 'https://github.com/MustrHQ/stock');

date_default_timezone_set(APP_TZ);
if (!defined('APP_IDLE_MINUTES')) define('APP_IDLE_MINUTES', 240);   // sign out after 4 idle hours

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $https, 'path' => '/']);
    session_start();
}

/* Security headers — sent from PHP so they work on any host, not just Apache. */
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; "
         . "script-src 'self' 'unsafe-inline'; font-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'");
    if ($https) header('Strict-Transport-Security: max-age=31536000');
}

if (!function_exists('db')) {
    function db() {
        static $pdo;
        if (!$pdo) {
            $pdo = new PDO(
                'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                 PDO::ATTR_EMULATE_PREPARES => false]
            );
            // keep MySQL's NOW() and CURDATE() on the same clock as PHP (handles BST/GMT)
            $pdo->exec("SET time_zone = '".date('P')."'");
        }
        return $pdo;
    }
}
if (!function_exists('q'))   { function q($sql, $p = [])   { $s = db()->prepare($sql); $s->execute($p); return $s; } }
if (!function_exists('one')) { function one($sql, $p = []) { $r = q($sql, $p)->fetch(); return $r === false ? null : $r; } }
if (!function_exists('all')) { function all($sql, $p = []) { return q($sql, $p)->fetchAll(); } }
if (!function_exists('col')) { function col($sql, $p = []) { $r = q($sql, $p)->fetch(PDO::FETCH_NUM); return $r ? $r[0] : null; } }

/** Unicode-safe length and trim that work whether or not the host has mbstring. */
if (!function_exists('str_len')) {
    function str_len($s) { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : preg_match_all('/./us', (string)$s); }
}
if (!function_exists('str_cut')) {
    function str_cut($s, $n) {
        if (function_exists('mb_substr')) return mb_substr($s, 0, $n, 'UTF-8');
        preg_match('/^.{0,'.(int)$n.'}/us', (string)$s, $m); return $m[0] ?? '';
    }
}
if (!function_exists('h'))     { function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('money')) { function money($n) { $n = round((float)$n, 2); return ($n < 0 ? "\u{2212}" : '').APP_CCY.number_format(abs($n), 2); } }
if (!function_exists('pct'))   { function pct($n) { $n = round((float)$n, 1); return ($n < 0 ? "\u{2212}" : '').number_format(abs($n), 1).'%'; } }
if (!function_exists('redirect')) { function redirect($u) { header('Location: '.$u); exit; } }

if (!function_exists('csrf')) {
    function csrf() {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf'];
    }
}
if (!function_exists('csrf_field')) { function csrf_field() { return '<input type="hidden" name="csrf" value="'.h(csrf()).'">'; } }
if (!function_exists('csrf_check')) {
    function csrf_check() {
        if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
            http_response_code(400); exit('Your session expired. Go back, reload the page and try again.');
        }
    }
}
if (!function_exists('flash')) {
    function flash($msg = null, $type = 'ok') {
        if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return; }
        $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f;
    }
}
