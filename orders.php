<?php
/** Ordering — out-of-stock items first, then build orders from suggested quantities. */
require_once __DIR__.'/lib.php';
require_login();
$SHOP = current_shop(); $shop = $SHOP['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $sup = (int)($_POST['supplier_id'] ?? 0);
    $supplier = $sup ? one("SELECT * FROM suppliers WHERE id=? AND active=1", [$sup]) : null;
    if (!$supplier) { flash('Choose a supplier first.', 'err'); redirect('orders.php'); }

    /* Put every out-of-stock item from this supplier onto its draft order. */
    if ($action === 'reorder') {
        $id = open_draft_order($shop, $sup);
        $sugg = [];
        foreach (suggest_order($shop, $sup) as $r) $sugg[(int)$r['id']] = $r;
        $n = 0;
        foreach (out_of_stock_alerts($shop) as $r) {
            if ((int)$r['supplier_id'] !== $sup) continue;
            $s    = $sugg[(int)$r['id']] ?? null;
            $pack = $s['pack'] ?? max(1, (float)$r['pack_size']);
            $qty  = max((float)($s['suggested'] ?? 0), $pack);   // never less than one pack
            $cost = $s['unit_cost'] ?? (float) col("SELECT cost_price FROM articles WHERE id=?", [$r['id']]);
            q("INSERT INTO order_lines (order_id,article_id,suggested_qty,qty,unit_cost,pack_size)
               VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE qty=GREATEST(qty,VALUES(qty))",
              [$id, $r['id'], $qty, $qty, $cost, $pack]);
            $n++;
        }
        flash($n.' out-of-stock '.($n === 1 ? 'item' : 'items').' added to the '.$supplier['name'].' order. Check the quantities, then send it.');
        redirect('order.php?id='.$id);
    }

    /* Build a full order from suggestions. */
    if ($action === 'create') {
        $existing = col("SELECT id FROM orders WHERE shop_id=? AND supplier_id=? AND status='draft'", [$shop, $sup]);
        if ($existing) { flash('You already have a draft for '.$supplier['name'].', so it is opened here.'); redirect('order.php?id='.$existing); }
        $id = open_draft_order($shop, $sup);
        foreach (suggest_order($shop, $sup) as $r) {
            if ($r['suggested'] <= 0 && empty($_POST['all'])) continue;
            q("INSERT INTO order_lines (order_id,article_id,suggested_qty,qty,unit_cost,pack_size) VALUES (?,?,?,?,?,?)",
              [$id, $r['id'], $r['suggested'], $r['suggested'], $r['unit_cost'], $r['pack']]);
        }
        flash('Order started with suggested quantities. Change anything you like before you send it.');
        redirect('order.php?id='.$id);
    }
    redirect('orders.php');
}

$oosAll = out_of_stock($shop);
$bySup = [];
foreach ($oosAll as $r) $bySup[$r['supplier_id'] ? (int)$r['supplier_id'] : 0][] = $r;
$oosCount = count(array_filter($oosAll, fn($r) => $r['on_order'] <= 0));

$status = $_GET['status'] ?? 'all';
$sql = "SELECT o.*, s.name AS supplier,
               (SELECT COUNT(*) FROM order_lines l WHERE l.order_id=o.id) AS lines_n,
               (SELECT COALESCE(SUM(l.qty*l.unit_cost),0) FROM order_lines l WHERE l.order_id=o.id) AS total
        FROM orders o JOIN suppliers s ON s.id=o.supplier_id WHERE o.shop_id=?";
$p = [$shop];
if (isset(ORDER_STATES[$status])) { $sql .= " AND o.status=?"; $p[] = $status; }
$sql .= " ORDER BY FIELD(o.status,'draft','sent','received','cancelled'), o.order_date DESC, o.id DESC LIMIT 100";
$orders = all($sql, $p);
$suppliers = all("SELECT * FROM suppliers WHERE active=1 ORDER BY name");
$stateTone = ['draft' => 'warn', 'sent' => 'blue', 'received' => 'ok', 'cancelled' => ''];

$TITLE = 'Ordering';
$HIDE_OOS = true;
require __DIR__.'/inc/header.php';
?>
<div class="page-head">
  <div><h1>Ordering</h1><div class="sub"><?= h(shop_label($SHOP)) ?></div></div>
</div>

<?php if ($oosAll): ?>
<section class="card" id="out">
  <div class="rep-head">
    <div><h2><?= icon('alert', 17) ?> Out of stock</h2>
      <div class="wk"><?= $oosCount ? $oosCount.' '.($oosCount === 1 ? 'item needs' : 'items need').' ordering' : 'Everything that has run out is already on an order' ?></div></div>
  </div>
  <?php foreach ($bySup as $sid => $items):
      $pending = array_filter($items, fn($r) => $r['on_order'] <= 0);
      $supName = $items[0]['supplier'] ?: 'No supplier set'; ?>
    <div class="oos-sup">
      <div class="oos-sup-head">
        <div><h3><?= h($supName) ?></h3>
          <div class="muted"><?= $pending ? count($pending).' to order' : 'Already on order — nothing more to do' ?><?= $pending && count($items) > count($pending) ? ', '.(count($items) - count($pending)).' already on order' : '' ?></div></div>
        <?php if ($sid && $pending): ?>
          <form method="post" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="reorder">
            <input type="hidden" name="supplier_id" value="<?= (int)$sid ?>">
            <button class="btn primary sm"><?= icon('plus', 15) ?>Add <?= count($pending) ?> to order</button>
          </form>
        <?php elseif (!$sid): ?>
          <?php if (is_admin()): ?><a class="btn sm" href="admin/articles.php">Set a supplier</a>
          <?php else: ?><span class="tag warn">Ask an admin to set a supplier</span><?php endif; ?>
        <?php endif; ?>
      </div>
      <div class="scroll"><table class="fixed" style="min-width:560px">
        <colgroup><col><col style="width:16%"><col style="width:11%"><col style="width:13%"><col style="width:19%"></colgroup>
        <thead><tr><th>Article</th><th class="num">On hand</th><th class="num">Par</th><th class="num">On order</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($items as $r): ?>
          <tr>
            <td><div class="art"><a class="info" href="lookup.php?a=<?= (int)$r['id'] ?>" title="Article details">i</a>
              <div><span class="name"><?= h($r['name']) ?></span><div class="code"><?= h($r['code']) ?></div></div></div></td>
            <td class="num neg"><?= h(fmt_qty($r['on_hand'])) ?> <span class="faint"><?= h($r['unit'] ?: 'each') ?></span></td>
            <td class="num"><?= $r['par'] > 0 ? h(fmt_qty($r['par'])) : '<span class="faint">—</span>' ?></td>
            <td class="num"><?= $r['on_order'] > 0 ? h(fmt_qty($r['on_order'])) : '<span class="faint">—</span>' ?></td>
            <td><?= $r['on_order'] > 0 ? '<span class="tag blue">On order</span>' : '<span class="tag todo">Needs ordering</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if (!$suppliers): ?>
  <div class="notice"><?= icon('info', 16) ?>
    <div>No suppliers yet. <?= is_admin() ? 'Add one in <a href="admin/suppliers.php">Admin, Suppliers</a>' : 'Ask an admin to add one' ?>,
      then set which supplier each article comes from.</div></div>
<?php else: ?>
<section class="card pad">
  <h2 class="card-title">Start an order</h2>
  <p class="lede">Quantities come from stock on hand against the par level, or from recent sales where no par is set,
    rounded up to whole packs. Nothing is sent anywhere — you get a sheet to check, print and send yourself.</p>
  <form method="post" class="inline">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div style="flex:2 1 260px"><label for="supplier_id">Supplier</label>
      <select id="supplier_id" name="supplier_id" required>
        <?php foreach ($suppliers as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= h($s['name']) ?><?= $s['order_days'] ? ' (orders '.h($s['order_days']).')' : '' ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="days" style="flex:1 1 auto"><label><input type="checkbox" name="all" value="1"> Include articles that need nothing</label></div>
    <div><button class="btn primary"><?= icon('cart', 16) ?>Build order</button></div>
  </form>
</section>
<?php endif; ?>

<div class="page-head" style="margin-top:28px;margin-bottom:12px">
  <h2 class="card-title" style="margin:0">Orders</h2>
  <div class="btn-row no-print">
    <a class="btn sm <?= $status === 'all' ? 'on' : '' ?>" href="orders.php">All</a>
    <?php foreach (ORDER_STATES as $k => $v): ?>
      <a class="btn sm <?= $status === $k ? 'on' : '' ?>" href="orders.php?status=<?= h($k) ?>"><?= h($v) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<div class="card scroll">
  <table>
    <thead><tr><th>Order</th><th>Supplier</th><th>Ordered</th><th>Due</th>
      <th class="num">Lines</th><th class="num">Value</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php if (!$orders): ?><tr><td colspan="8" class="empty">No orders yet. Start one above.</td></tr><?php endif; ?>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td><a href="order.php?id=<?= (int)$o['id'] ?>"><strong><?= h($o['order_no']) ?></strong></a></td>
          <td><?= h($o['supplier']) ?></td>
          <td><?= h(date('d/m/Y', strtotime($o['order_date']))) ?></td>
          <td><?= $o['delivery_date'] ? h(date('d/m/Y', strtotime($o['delivery_date']))) : '<span class="faint">—</span>' ?></td>
          <td class="num"><?= (int)$o['lines_n'] ?></td>
          <td class="num"><?= money($o['total']) ?></td>
          <td><span class="tag <?= h($stateTone[$o['status']]) ?>"><?= h(ORDER_STATES[$o['status']]) ?></span></td>
          <td class="num"><div class="btn-row" style="justify-content:flex-end;flex-wrap:nowrap">
            <a class="btn sm" href="order.php?id=<?= (int)$o['id'] ?>">Open</a>
            <a class="btn sm" href="order-print.php?id=<?= (int)$o['id'] ?>" target="_blank" title="Print"><?= icon('printer', 15) ?></a></div></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__.'/inc/footer.php';
