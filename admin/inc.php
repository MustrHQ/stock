<?php
require_once __DIR__.'/../lib.php';
require_admin();

function admin_header($title, $page) {
    $B = base_url();
    $tabs = [
        'index'     => ['Dashboard',          'index.php'],
        'articles'  => ['Articles',           'articles.php'],
        'suppliers' => ['Suppliers',          'suppliers.php'],
        'par'       => ['Par levels',         'par-levels.php'],
        'barcodes'  => ['Barcodes',           'barcodes.php'],
        'schedule'  => ['Count schedule',     'schedule.php'],
        'taxonomy'  => ['Categories & units', 'taxonomy.php'],
        'shops'     => ['Shops',              'shops.php'],
        'users'     => ['Users',              'users.php'],
        'sales'     => ['Sales & NSEV',       'sales.php'],
        'update'    => ['Updates & backups',  'update.php'],
    ];
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0f1a2c"><?= app_head_tags() ?>
<title><?= h($title) ?> · Admin · <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= h($B) ?>assets/app.css"></head><body>
<header class="topbar">
  <a class="brand" href="index.php"><span class="brand-mark">M</span>MustrHQ <small>Stock</small></a>
  <span class="admin-badge">ADMIN</span>
  <div class="crumb"><strong><?= h($title) ?></strong></div>
  <div class="spacer"></div>
  <a class="top-link" href="<?= h($B) ?>index.php" title="Back to the shop view"><?= icon('store', 17) ?><span class="lbl">Shop view</span></a>
  <?= logout_button() ?>
</header>
<nav class="subnav" aria-label="Admin sections">
  <?php foreach ($tabs as $k => $t): ?>
    <a class="<?= $k === $page ? 'on' : '' ?>" href="<?= h($t[1]) ?>"<?= $k === $page ? ' aria-current="page"' : '' ?>><?= h($t[0]) ?></a>
  <?php endforeach; ?>
</nav>
<main class="wrap">
<?php foreach (flash() as $f): ?>
  <div class="msg <?= h($f[0]) ?>" role="status"><?= icon($f[0] === 'ok' ? 'check' : 'alert', 16) ?><span><?= h($f[1]) ?></span></div>
<?php endforeach;
}

function admin_footer() { echo '</main><script src="'.h(base_url()).'assets/app.js"></script></body></html>'; }
