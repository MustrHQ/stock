<?php
require_once __DIR__.'/../lib.php';
require_login();
$SHOP  = current_shop();
$TITLE = $TITLE ?? APP_NAME;
$B     = base_url();
$OOS_STRIP = ($SHOP && empty($HIDE_OOS)) ? out_of_stock_alerts($SHOP['id']) : [];

/* Which top-level section this page belongs to. */
$here = basename($_SERVER['SCRIPT_NAME']);
$sections = [
    ['Today',    'index.php',         'home',   ['index.php']],
    ['Count',    'stock-count.php',   'count',  ['stock-count.php']],
    ['Waste',    'product-waste.php', 'trash',  ['product-waste.php', 'ingredient-waste.php', 'damaged-stock.php']],
    ['Orders',   'orders.php',        'cart',   ['orders.php', 'order.php']],
    ['Goods in', 'deliveries.php',    'truck',  ['deliveries.php']],
    ['Stock',    'lookup.php',        'box',    ['lookup.php']],
    ['Loss',     'reports.php',       'chart',  ['reports.php']],
];
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#01216C"><?= app_head_tags() ?>
<title><?= h($TITLE) ?> – <?= h($SHOP['name'] ?? APP_NAME) ?></title>
<link rel="stylesheet" href="<?= h($B) ?>assets/app.css?v=<?= h(MUSTR_VERSION) ?>">
</head><body>
<header class="topbar">
  <a class="brand" href="<?= h($B) ?>index.php">
    <span class="brand-box"><img src="<?= h($B) ?>assets/brand/logo-mark.svg" alt="" width="26" height="26"></span>
    <span class="brand-name"><?= h($SHOP['name'] ?? APP_NAME) ?><small>Stock</small></span>
  </a>
  <nav class="mainnav" aria-label="Sections">
    <?php foreach ($sections as $s): $on = in_array($here, $s[3], true); ?>
      <a href="<?= h($B.$s[1]) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= icon($s[2], 18) ?><span><?= h($s[0]) ?></span></a>
    <?php endforeach; ?>
  </nav>
  <div class="topbar-right">
    <?php if (is_admin()): ?><a class="top-link" href="<?= h($B) ?>admin/index.php" title="Admin"><?= icon('settings', 18) ?><span class="lbl">Admin</span></a><?php endif; ?>
    <?= logout_button() ?>
  </div>
</header>
<?php if ($OOS_STRIP): ?>
<div class="alert-strip" role="status">
  <?= icon('alert', 16) ?>
  <span><?= count($OOS_STRIP) ?> <?= count($OOS_STRIP) === 1 ? 'item has' : 'items have' ?> run out and <?= count($OOS_STRIP) === 1 ? 'is' : 'are' ?> not on an order.</span>
  <a href="<?= h($B) ?>orders.php#out">Order now</a>
</div>
<?php endif; ?>
<main class="wrap">
<?php foreach (flash() as $f): ?>
  <div class="msg <?= h($f[0]) ?>" role="status"><?= icon($f[0] === 'ok' ? 'check' : 'alert', 16) ?><span><?= h($f[1]) ?></span></div>
<?php endforeach; ?>
