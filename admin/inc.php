<?php
require_once __DIR__.'/../lib.php';
require_admin();

function admin_header($title, $page) {
    $B = base_url();
    $shop = current_shop();
    $groups = [
        ''                 => [['index', 'Dashboard', 'index.php', 'home']],
        'Catalogue'        => [['articles', 'Articles', 'articles.php', 'box'],
                               ['suppliers', 'Suppliers', 'suppliers.php', 'truck'],
                               ['par', 'Par levels', 'par-levels.php', 'cart'],
                               ['barcodes', 'Barcodes', 'barcodes.php', 'scan'],
                               ['schedule', 'Count schedule', 'schedule.php', 'calendar'],
                               ['taxonomy', 'Categories and units', 'taxonomy.php', 'count']],
        'Shops and people' => [['shops', 'Shops', 'shops.php', 'store'],
                               ['users', 'Users', 'users.php', 'user'],
                               ['sales', 'Sales figures', 'sales.php', 'chart']],
        'System'           => [['update', 'Updates and backups', 'update.php', 'refresh']],
    ];
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#01216C"><?= app_head_tags() ?>
<title><?= h($title) ?> – Stock admin</title>
<link rel="stylesheet" href="<?= h($B) ?>assets/app.css?v=<?= h(MUSTR_VERSION) ?>"></head><body>
<div class="admin">
<aside class="side">
  <a class="brand" href="index.php">
    <span class="brand-box"><img src="<?= h($B) ?>assets/brand/logo-mark.svg" alt="" width="26" height="26"></span>
    <span class="brand-name"><?= h($shop['name'] ?? APP_NAME) ?><small>Stock admin</small></span>
  </a>
  <nav class="side-nav" aria-label="Admin sections">
  <?php foreach ($groups as $g => $items): ?>
    <?php if ($g !== ''): ?><div class="grp"><?= h($g) ?></div><?php endif; ?>
    <?php foreach ($items as [$k, $label, $href, $ic]): ?>
      <a class="nav" href="<?= h($href) ?>"<?= $k === $page ? ' aria-current="page"' : '' ?>><?= icon($ic, 20) ?><span><?= h($label) ?></span></a>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <div class="grp">Shop screens</div>
  <a class="nav" href="<?= h($B) ?>index.php"><?= icon('store', 20) ?><span>Open the shop</span></a>
  <form method="post" action="<?= h($B) ?>logout.php"><?= csrf_field() ?><button class="nav" type="submit"><?= icon('logout', 20) ?><span>Sign out</span></button></form>
  </nav>
  <div class="side-foot">MustrHQ Stock <?= h(MUSTR_VERSION) ?><br><a href="<?= h(APP_SOURCE_URL) ?>" rel="noopener">Source code</a> (AGPL-3.0)</div>
</aside>
<div class="admin-main"><main class="wrap">
<?php foreach (flash() as $f): ?>
  <div class="msg <?= h($f[0]) ?>" role="status"><?= icon($f[0] === 'ok' ? 'check' : 'alert', 16) ?><span><?= h($f[1]) ?></span></div>
<?php endforeach;
}

function admin_footer() { echo '</main></div></div><script src="'.h(base_url()).'assets/app.js"></script></body></html>'; }
