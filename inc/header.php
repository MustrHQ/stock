<?php
require_once __DIR__.'/../lib.php';
require_login();
$SHOP  = current_shop();
$TITLE = $TITLE ?? APP_NAME;
$B     = base_url();
$OOS_STRIP = ($SHOP && empty($HIDE_OOS)) ? out_of_stock_alerts($SHOP['id']) : [];
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0f1a2c"><?= app_head_tags() ?>
<title><?= h($TITLE) ?> · <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= h($B) ?>assets/app.css">
</head><body>
<header class="topbar">
  <a class="brand" href="<?= h($B) ?>index.php"><span class="brand-mark">M</span>MustrHQ <small>Stock</small></a>
  <div class="crumb">
    <?php if ($TITLE !== 'Home'): ?><a href="<?= h($B) ?>index.php">Home</a><?= icon('right', 14) ?><?php endif; ?>
    <strong><?= h($TITLE) ?></strong>
  </div>
  <div class="spacer"></div>
  <?php if ($SHOP): ?>
    <div class="shop-pill" title="<?= h(shop_label($SHOP)) ?>"><?= icon('store', 15) ?><span><?= h($SHOP['code'].' '.$SHOP['name']) ?></span></div>
  <?php endif; ?>
  <?php if (is_admin()): ?>
    <a class="top-link" href="<?= h($B) ?>admin/index.php" title="Admin panel"><?= icon('settings', 17) ?><span class="lbl">Admin</span></a>
  <?php endif; ?>
  <?= logout_button() ?>
</header>
<?php if ($OOS_STRIP): ?>
<div class="alert-strip" role="status">
  <?= icon('alert', 16) ?>
  <span><strong><?= count($OOS_STRIP) ?> <?= count($OOS_STRIP) === 1 ? 'item is' : 'items are' ?> out of stock</strong>
    and not on order.</span>
  <a href="<?= h($B) ?>orders.php#out">Review and order</a>
</div>
<?php endif; ?>
<main class="wrap">
<?php foreach (flash() as $f): ?>
  <div class="msg <?= h($f[0]) ?>" role="status"><?= icon($f[0] === 'ok' ? 'check' : 'alert', 16) ?><span><?= h($f[1]) ?></span></div>
<?php endforeach; ?>
