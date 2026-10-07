<?php
/** Printable purchase order — hand it to the driver, or tick it off at the back door. */
require_once __DIR__.'/lib.php';
require_login();
$SHOP = current_shop(); $shop = $SHOP['id'];
$id = (int)($_GET['id'] ?? 0);
$o  = one("SELECT o.id, o.order_no, o.order_date, o.delivery_date, o.status, o.notes,
                  s.name AS sup_name, s.contact, s.email, s.phone, s.address AS sup_address, s.min_order
           FROM orders o JOIN suppliers s ON s.id=o.supplier_id WHERE o.id=? AND o.shop_id=?", [$id, $shop]);
if (!$o) { http_response_code(404); exit('Order not found.'); }
$lines = all("SELECT l.*, a.name, a.code, a.supplier_ref, u.name AS unit
              FROM order_lines l JOIN articles a ON a.id=l.article_id
              LEFT JOIN units u ON u.id=a.unit_id WHERE l.order_id=? ORDER BY a.name", [$id]);
$total = 0; foreach ($lines as $l) $total += (float)$l['qty'] * (float)$l['unit_cost'];
$user = user();
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($o['order_no']) ?> – Purchase order</title>
<link rel="stylesheet" href="assets/app.css"></head>
<body>
<div class="print-tools no-print">
  <button class="btn primary" onclick="window.print()"><?= icon('printer', 16) ?>Print purchase order</button>
  <a class="btn" href="order.php?id=<?= (int)$id ?>"><?= icon('left', 16) ?>Back to order</a>
</div>

<article class="print-sheet">
  <div class="po-top">
    <div><img src="assets/brand/logo-primary.svg" alt="MustrHQ" width="130" height="30"><div class="muted" style="margin-top:6px;font-weight:650"><?= h($SHOP['name']) ?></div></div>
    <div class="po-title">
      <h1>Purchase order</h1>
      <div class="muted"><?= h($o['order_no']) ?></div>
    </div>
  </div>

  <div class="po-parties">
    <div><div class="l">Supplier</div>
      <strong><?= h($o['sup_name']) ?></strong>
      <?= $o['contact'] ? '<br>'.h($o['contact']) : '' ?>
      <?= $o['sup_address'] ? '<br>'.h($o['sup_address']) : '' ?>
      <?= $o['email'] ? '<br>'.h($o['email']) : '' ?><?= $o['phone'] ? '<br>'.h($o['phone']) : '' ?></div>
    <div><div class="l">Deliver to</div>
      <strong><?= h($SHOP['code'].' '.$SHOP['name']) ?></strong><br><?= h($SHOP['address']) ?></div>
    <div><div class="l">Dates</div>
      Ordered <strong><?= h(date('d/m/Y', strtotime($o['order_date']))) ?></strong><br>
      Delivery <strong><?= $o['delivery_date'] ? h(date('d/m/Y', strtotime($o['delivery_date']))) : 'to be confirmed' ?></strong><br>
      <span class="muted">Raised by <?= h($user['name']) ?></span></div>
  </div>

  <div class="scroll"><table style="min-width:600px">
    <thead><tr><th>Code</th><th>Article</th><th>Pack</th>
      <th class="num">Qty</th><th class="num">Unit cost</th><th class="num">Line</th><th class="num">Received</th></tr></thead>
    <tbody>
      <?php if (!$lines): ?><tr><td colspan="7" class="empty">This order has no lines.</td></tr><?php endif; ?>
      <?php foreach ($lines as $l): ?>
        <tr>
          <td class="muted"><?= h($l['supplier_ref'] ?: $l['code']) ?></td>
          <td><?= h($l['name']) ?></td>
          <td class="muted"><?= h(fmt_qty($l['pack_size'])) ?> × <?= h($l['unit'] ?: 'each') ?></td>
          <td class="num"><strong><?= h(fmt_qty($l['qty'])) ?></strong></td>
          <td class="num"><?= money($l['unit_cost']) ?></td>
          <td class="num"><?= money((float)$l['qty'] * (float)$l['unit_cost']) ?></td>
          <td class="num"><?= $l['received_qty'] !== null ? '<strong>'.h(fmt_qty($l['received_qty'])).'</strong>' : '<span class="tick"></span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="5"><?= count($lines) ?> line<?= count($lines) === 1 ? '' : 's' ?><?= $o['min_order'] > 0 ? ', supplier minimum '.money($o['min_order']) : '' ?></td>
      <td class="num"><?= money($total) ?></td><td></td></tr></tfoot>
  </table></div>

  <?php if ($o['notes']): ?><div class="po-note"><strong>Note:</strong> <?= h($o['notes']) ?></div><?php endif; ?>

  <div class="sign">
    <div>Picked and packed by</div>
    <div>Delivered by</div>
    <div>Received by — name, signature, time</div>
  </div>
</article>
<?php if (!empty($_GET['print'])): ?><script>window.print();</script><?php endif; ?>
</body></html>
