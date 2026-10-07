<?php
/** One order — edit quantities, send it, then book in what actually turned up. */
require_once __DIR__.'/lib.php';
require_login();
$SHOP = current_shop(); $shop = $SHOP['id'];

$id = (int)($_GET['id'] ?? 0);
$o  = one("SELECT o.*, s.name AS supplier, s.email, s.phone, s.contact, s.lead_days, s.min_order
           FROM orders o JOIN suppliers s ON s.id=o.supplier_id WHERE o.id=? AND o.shop_id=?", [$id, $shop]);
if (!$o) { flash('That order is not in this shop.', 'err'); redirect('orders.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';

    /* quantities — ordered while it is a draft, received once it has been sent */
    if (in_array($action, ['save','send'], true) && $o['status'] === 'draft') {
        foreach (($_POST['qty'] ?? []) as $lineId => $v) {
            $v = trim($v);
            if ($v === '' || (float)$v <= 0) { q("DELETE FROM order_lines WHERE id=? AND order_id=?", [(int)$lineId, $id]); continue; }
            q("UPDATE order_lines SET qty=? WHERE id=? AND order_id=?", [(float)$v, (int)$lineId, $id]);
        }
        q("UPDATE orders SET delivery_date=?, notes=? WHERE id=?",
          [$_POST['delivery_date'] ?: null, trim($_POST['notes'] ?? ''), $id]);
    }

    if ($action === 'add' && $o['status'] === 'draft') {
        $a = resolve_article($_POST['article_id'] ?? 0, $_POST['article_name'] ?? '');
        if (!$a) flash('Pick an article from the list.', 'err');
        else {
            $s = supply_settings($shop, $a['id']);
            $cost = $a['pack_cost'] > 0 ? $a['pack_cost'] / max(1, $s['pack']) : $a['cost_price'];
            q("INSERT INTO order_lines (order_id,article_id,suggested_qty,qty,unit_cost,pack_size)
               VALUES (?,?,0,?,?,?) ON DUPLICATE KEY UPDATE qty=qty+VALUES(qty)",
              [$id, $a['id'], $s['pack'], $cost, $s['pack']]);
            flash($a['name'].' added.');
        }
        redirect('order.php?id='.$id);
    }

    if ($action === 'send' && $o['status'] === 'draft') {
        if (!col("SELECT COUNT(*) FROM order_lines WHERE order_id=?", [$id])) {
            flash('There is nothing on this order yet.', 'err'); redirect('order.php?id='.$id);
        }
        q("UPDATE orders SET status='sent', sent_at=NOW() WHERE id=?", [$id]);
        flash('Order marked as sent. Print it or email it to the supplier, then book it in when it arrives.');
        redirect('order.php?id='.$id);
    }

    if ($action === 'receive' && $o['status'] === 'sent') {
        $date = $_POST['received_date'] ?: date('Y-m-d');
        q("DELETE FROM movements WHERE ref=?", ['ORDER#'.$id]);
        foreach (all("SELECT * FROM order_lines WHERE order_id=?", [$id]) as $l) {
            $got = isset($_POST['got'][$l['id']]) && trim($_POST['got'][$l['id']]) !== ''
                 ? (float)$_POST['got'][$l['id']] : (float)$l['qty'];
            q("UPDATE order_lines SET received_qty=? WHERE id=?", [$got, $l['id']]);
            if ($got > 0) {
                $a = article($l['article_id']);
                move($shop, $l['article_id'], $date, MV_DELIVERY, $got,
                     $got * (float)$a['cost_price'], 'ORDER#'.$id);
            }
        }
        q("UPDATE orders SET status='received', received_at=NOW(), delivery_date=? WHERE id=?", [$date, $id]);
        flash('Booked in. The delivery is now on stock.');
        redirect('order.php?id='.$id);
    }

    if ($action === 'unreceive' && $o['status'] === 'received' && is_manager()) {
        q("DELETE FROM movements WHERE ref=?", ['ORDER#'.$id]);
        q("UPDATE orders SET status='sent', received_at=NULL WHERE id=?", [$id]);
        q("UPDATE order_lines SET received_qty=NULL WHERE order_id=?", [$id]);
        flash('Delivery reversed. The stock it added has been taken back off.');
        redirect('order.php?id='.$id);
    }

    if ($action === 'cancel' && $o['status'] !== 'received') {
        q("UPDATE orders SET status='cancelled' WHERE id=?", [$id]);
        flash('Order cancelled.'); redirect('orders.php');
    }
    if ($action === 'delete' && $o['status'] === 'draft') {
        q("DELETE FROM order_lines WHERE order_id=?", [$id]);
        q("DELETE FROM orders WHERE id=?", [$id]);
        flash('Draft deleted.'); redirect('orders.php');
    }
    if ($action === 'save') flash('Saved.');
    redirect('order.php?id='.$id);
}

$lines = all("SELECT l.*, a.name, a.code, a.supplier_ref, u.name AS unit
              FROM order_lines l JOIN articles a ON a.id=l.article_id
              LEFT JOIN units u ON u.id=a.unit_id
              WHERE l.order_id=? ORDER BY a.name", [$id]);
$hand  = on_hand_map($shop);
$total = 0; foreach ($lines as $l) $total += (float)$l['qty'] * (float)$l['unit_cost'];
$draft = $o['status'] === 'draft';
$sent  = $o['status'] === 'sent';

$TITLE = 'Order '.$o['order_no'];
require __DIR__.'/inc/header.php';
?>
<div class="page-head"><div><h1><?= h($o['order_no']) ?></h1><div class="sub"><?= h($o['supplier']) ?>, delivering to <?= h($SHOP['name']) ?></div></div></div>

<form method="post" data-dirty>
  <?= csrf_field() ?>
  <div class="card">
    <div class="ord-meta">
      <div><div class="l">Status</div><div class="v"><?= h(ORDER_STATES[$o['status']]) ?></div></div>
      <div><div class="l">Ordered</div><div class="v"><?= h(date('d/m/Y', strtotime($o['order_date']))) ?></div></div>
      <div><div class="l">Delivery expected</div>
        <div class="v"><?php if ($draft): ?>
          <input type="date" name="delivery_date" value="<?= h($o['delivery_date']) ?>">
        <?php else: ?><?= $o['delivery_date'] ? h(date('d/m/Y', strtotime($o['delivery_date']))) : '—' ?><?php endif; ?></div></div>
      <div><div class="l">Order value</div><div class="v"><?= money($total) ?>
        <?php if ($o['min_order'] > 0 && $total < $o['min_order']): ?>
          <div class="low" style="font-size:12px">below <?= money($o['min_order']) ?> minimum</div>
        <?php endif; ?></div></div>
      <div><div class="l">Supplier contact</div>
        <div class="v" style="font-weight:400;font-size:13px">
          <?= h($o['contact']) ?><?= $o['email'] ? '<br>'.h($o['email']) : '' ?><?= $o['phone'] ? '<br>'.h($o['phone']) : '' ?></div></div>
    </div>

    <div class="scroll">
    <table>
      <thead><tr>
        <th>Article</th><th>Pack</th><th class="num">On hand</th><th class="num">Suggested</th>
        <th class="num"><?= $draft ? 'Order' : 'Ordered' ?></th>
        <?php if ($sent || $o['status'] === 'received'): ?><th class="num">Received</th><?php endif; ?>
        <th class="num">Line cost</th>
      </tr></thead>
      <tbody>
        <?php if (!$lines): ?><tr><td colspan="7" class="empty">
          Nothing on this order. Add an article below.</td></tr><?php endif; ?>
        <?php foreach ($lines as $l): $oh = $hand[$l['article_id']] ?? 0; ?>
          <tr>
            <td><div class="art"><a class="info" href="lookup.php?a=<?= (int)$l['article_id'] ?>">i</a>
              <div><span class="name"><?= h($l['name']) ?></span>
                <div class="code"><?= h($l['code']) ?><?= $l['supplier_ref'] ? ', supplier code '.h($l['supplier_ref']) : '' ?></div></div></div></td>
            <td class="muted"><?= h(rtrim(rtrim(number_format((float)$l['pack_size'],2,'.',''),'0'),'.')) ?> × <?= h($l['unit'] ?: 'each') ?></td>
            <td class="num <?= $oh < 0 ? 'neg' : '' ?>"><?= h(rtrim(rtrim(number_format($oh,2,'.',''),'0'),'.')) ?></td>
            <td class="num sug"><b><?= h(rtrim(rtrim(number_format((float)$l['suggested_qty'],2,'.',''),'0'),'.')) ?></b></td>
            <td class="num">
              <?php if ($draft): ?>
                <input class="qty" type="number" step="0.001" min="0" name="qty[<?= (int)$l['id'] ?>]"
                       value="<?= h(rtrim(rtrim(number_format((float)$l['qty'],3,'.',''),'0'),'.')) ?>"
                       aria-label="Order quantity, <?= h($l['name']) ?>">
              <?php else: ?><?= h(rtrim(rtrim(number_format((float)$l['qty'],2,'.',''),'0'),'.')) ?><?php endif; ?>
            </td>
            <?php if ($sent): ?>
              <td class="num"><input class="qty" type="number" step="0.001" min="0" form="receiveForm"
                     name="got[<?= (int)$l['id'] ?>]" data-article="<?= (int)$l['article_id'] ?>" placeholder="<?= h(rtrim(rtrim(number_format((float)$l['qty'],2,'.',''),'0'),'.')) ?>"
                     aria-label="Received quantity, <?= h($l['name']) ?>"></td>
            <?php elseif ($o['status'] === 'received'): ?>
              <td class="num <?= $l['received_qty'] < $l['qty'] ? 'neg' : '' ?>">
                <?= h(rtrim(rtrim(number_format((float)$l['received_qty'],2,'.',''),'0'),'.')) ?></td>
            <?php endif; ?>
            <td class="num"><?= money((float)$l['qty'] * (float)$l['unit_cost']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="<?= $sent || $o['status'] === 'received' ? 6 : 5 ?>">Total</td>
        <td class="num"><?= money($total) ?></td></tr></tfoot>
    </table>
    </div>

    <?php if ($draft): ?>
      <div class="pad" style="border-top:1px solid var(--rule)">
        <label for="notes">Note for the supplier</label>
        <input id="notes" name="notes" value="<?= h($o['notes']) ?>" placeholder="Deliver before 10am, ring the side door">
      </div>
    <?php elseif ($o['notes']): ?>
      <div class="pad muted" style="border-top:1px solid var(--rule)">Note: <?= h($o['notes']) ?></div>
    <?php endif; ?>
  </div>

  <div class="actionbar">
    <div class="left"><?= count($lines) ?> line<?= count($lines) === 1 ? '' : 's' ?><?= $sent ? '. Leave a received box empty to accept the ordered quantity.' : '' ?></div>
    <a class="btn" href="orders.php">Back</a>
    <a class="btn" href="order-print.php?id=<?= (int)$id ?>" target="_blank">Print</a>
    <?php if ($draft): ?>
      <button class="btn" name="action" value="save">Save</button>
      <button class="btn danger" name="action" value="delete"
              onclick="return confirm('Delete this draft order?')">Delete</button>
      <button class="btn primary" name="action" value="send"><?= icon('send', 16) ?>Mark as sent</button>
    <?php elseif ($o['status'] === 'received' && is_manager()): ?>
      <button class="btn danger" name="action" value="unreceive"
              onclick="return confirm('Reverse this delivery? The stock it added will be taken back off.')">Reverse delivery</button>
    <?php endif; ?>
  </div>
</form>

<?php if ($sent): ?>
<form method="post" id="receiveForm" class="card pad">
  <?= csrf_field() ?><input type="hidden" name="action" value="receive">
  <h2 class="card-title">Book the delivery in</h2>
  <p class="lede">Scan each item as it comes off the van, or type what arrived against any line that came up short.
    Lines left empty are taken as delivered in full.</p>
  <div class="inline">
    <div style="flex:1 1 170px"><label for="rd">Delivered on</label>
      <input id="rd" type="date" name="received_date" value="<?= h(date('Y-m-d')) ?>" max="<?= h(date('Y-m-d')) ?>"></div>
    <div style="flex:0 0 auto"><?= scan_button('Scan delivery') ?></div>
    <div style="flex:0 0 auto"><button class="btn primary"><?= icon('truck', 16) ?>Receive into stock</button></div>
    <div style="flex:0 0 auto"><button class="btn danger" name="action" value="cancel"
      onclick="return confirm('Cancel this order?')">Cancel order</button></div>
  </div>
</form>
<?php endif; ?>

<?php if ($draft): ?>
<div class="card pad no-print">
  <h2 class="card-title">Add an article</h2>
  <form method="post" class="inline">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div style="flex:2 1 260px"><label for="art">Article</label>
      <input id="art" name="article_name" list="articles" placeholder="Type an article name"
             autocomplete="off" data-article-input="aid">
      <input type="hidden" name="article_id" id="aid">
      <datalist id="articles">
        <?php foreach (all("SELECT id,code,name FROM articles WHERE active=1 ORDER BY name") as $a): ?>
          <option data-id="<?= (int)$a['id'] ?>" value="<?= h($a['name']) ?>"><?= h($a['code']) ?></option>
        <?php endforeach; ?>
      </datalist></div>
    <div style="flex:0 0 auto" class="btn-row"><?= scan_button('Scan') ?><button class="btn blue"><?= icon('plus', 16) ?>Add to order</button></div>
  </form>
</div>
<?php endif; ?>
<?php if ($draft): ?><?= scanner_ui('pick', ['name' => '#art', 'id' => '#aid']) ?>
<?php elseif ($sent): ?><?= scanner_ui('tally') ?><?php endif; ?>
<?php require __DIR__.'/inc/footer.php';
