<?php
/** Goods In — deliveries and shop-to-shop transfers. Feeds expected stock. */
require_once __DIR__.'/lib.php';
require_login();
$SHOP = current_shop(); $shop = $SHOP['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete' && is_manager()) {
        q("DELETE FROM movements WHERE id=? AND shop_id=? AND mv_type IN (?,?)",
          [(int)$_POST['id'], $shop, MV_DELIVERY, MV_TRANSFER]);
        flash('Entry deleted.');
        redirect('deliveries.php');
    }
    $qty  = (float)($_POST['qty'] ?? 0);
    $date = $_POST['mv_date'] ?? date('Y-m-d');
    $kind = $_POST['kind'] ?? 'delivery';
    $ref  = trim($_POST['ref'] ?? '');
    $a    = resolve_article($_POST['article_id'] ?? 0, $_POST['article_name'] ?? '');
    $aid  = $a['id'] ?? 0;
    if (!$a)           flash('Pick an article from the list.', 'err');
    elseif ($qty <= 0) flash('Enter a quantity greater than zero.', 'err');
    else {
        $signed = $kind === 'transfer_out' ? -$qty : $qty;
        $type   = $kind === 'delivery' ? MV_DELIVERY : MV_TRANSFER;
        move($shop, $aid, $date, $type, $signed, $signed * (float)$a['cost_price'],
             $ref !== '' ? $ref : strtoupper(str_replace('_', ' ', $kind)));
        flash($a['name'].' — '.($signed > 0 ? 'in' : 'out').' '.abs($qty).' recorded.');
    }
    redirect('deliveries.php');
}

$recent = all("SELECT m.*, a.name, a.code, u.name AS unit
               FROM movements m JOIN articles a ON a.id=m.article_id
               LEFT JOIN units u ON u.id=a.unit_id
               WHERE m.shop_id=? AND m.mv_type IN (?,?)
               ORDER BY m.mv_date DESC, m.id DESC LIMIT 60", [$shop, MV_DELIVERY, MV_TRANSFER]);

$TITLE = 'Goods in';
require __DIR__.'/inc/header.php';
?>
<div class="page-head"><div><h1>Goods in</h1><div class="sub"><?= h(shop_label($SHOP)) ?></div></div></div>

<div class="card pad">
  <form method="post" class="inline">
    <?= csrf_field() ?>
    <div style="flex:2 1 240px">
      <label for="art">Article</label>
      <input id="art" name="article_name" list="articles" placeholder="Type an article name"
             autocomplete="off" required data-article-input="aid">
      <input type="hidden" name="article_id" id="aid">
      <datalist id="articles">
        <?php foreach (all("SELECT id,code,name FROM articles WHERE active=1 ORDER BY name") as $a): ?>
          <option data-id="<?= (int)$a['id'] ?>" value="<?= h($a['name']) ?>"><?= h($a['code']) ?></option>
        <?php endforeach; ?>
      </datalist>
    </div>
    <div style="flex:1 1 110px"><label for="qty">Quantity</label>
      <input id="qty" class="qty" style="width:100%" type="number" step="0.001" min="0.001" name="qty" required></div>
    <div style="flex:1 1 150px"><label for="kind">Movement</label>
      <select id="kind" name="kind">
        <option value="delivery">Delivery in</option>
        <option value="transfer_in">Transfer in</option>
        <option value="transfer_out">Transfer out</option>
      </select></div>
    <div style="flex:1 1 140px"><label for="mv_date">Date</label>
      <input id="mv_date" type="date" name="mv_date" value="<?= h(date('Y-m-d')) ?>" max="<?= h(date('Y-m-d')) ?>"></div>
    <div style="flex:1 1 140px"><label for="ref">Reference</label>
      <input id="ref" type="text" name="ref" placeholder="Note / delivery no."></div>
    <div style="flex:0 0 auto" class="btn-row"><?= scan_button('Scan') ?><button class="btn primary" type="submit"><?= icon('plus', 16) ?>Record movement</button></div>
  </form>
</div>

<h2 class="section-title">Recent movements</h2>
<div class="card scroll">
  <table>
    <thead><tr><th>Date</th><th>Article</th><th>Unit</th><th>Type</th>
      <th class="num">Qty</th><th class="num">Value</th><th>Reference</th><th></th></tr></thead>
    <tbody>
      <?php if (!$recent): ?><tr><td colspan="8" class="empty">
        Nothing recorded yet.</td></tr><?php endif; ?>
      <?php foreach ($recent as $m): ?>
        <tr>
          <td><?= h(date('d/m/Y', strtotime($m['mv_date']))) ?></td>
          <td><?= h($m['name']) ?><div class="code muted"><?= h($m['code']) ?></div></td>
          <td><?= h($m['unit'] ?: 'each') ?></td>
          <td><?= h($m['mv_type'] === MV_DELIVERY ? 'Delivery' : 'Transfer') ?></td>
          <td class="num <?= $m['qty'] < 0 ? 'neg' : '' ?>"><?= h(rtrim(rtrim(number_format((float)$m['qty'],3,'.',''),'0'),'.')) ?></td>
          <td class="num"><?= money($m['mv_value']) ?></td>
          <td class="muted"><?= h(movement_ref($m['ref'])) ?></td>
          <td class="num"><?php if (is_manager()): ?>
            <form method="post" onsubmit="return confirm('Delete this entry?')">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <button class="btn sm danger">Delete</button>
            </form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= scanner_ui('pick', ['name' => '#art', 'id' => '#aid', 'qty' => '#qty']) ?>
<?php require __DIR__.'/inc/footer.php';
