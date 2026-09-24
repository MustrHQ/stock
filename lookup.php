<?php
/** Lookup Stock — catalogue search, on-hand figures and a per-article movement history. */
require_once __DIR__.'/lib.php';
require_login();
$SHOP = current_shop(); $shop = $SHOP['id'];
$aid  = (int)($_GET['a'] ?? 0);
$TITLE = 'Lookup stock';
require __DIR__.'/inc/header.php';

if ($aid && ($a = article($aid))):
  $unit = col("SELECT name FROM units WHERE id=?", [$a['unit_id']]);
  $cat  = col("SELECT name FROM categories WHERE id=?", [$a['category_id']]);
  $qty  = on_hand($shop, $aid);
  $hist = all("SELECT * FROM movements WHERE shop_id=? AND article_id=?
               ORDER BY mv_date DESC, id DESC LIMIT 40", [$shop, $aid]);
  $lastCount = one("SELECT cs.count_date, cl.qty, cl.expected_qty, cl.variance_qty, cl.variance_value
                    FROM count_lines cl JOIN count_sessions cs ON cs.id=cl.session_id
                    WHERE cs.shop_id=? AND cl.article_id=? AND cs.status='confirmed'
                    ORDER BY cs.count_date DESC LIMIT 1", [$shop, $aid]);
?>
  <div class="page-head"><div><h1><?= h($a['name']) ?></h1><div class="sub"><?= h($a['code']) ?> · <?= h($cat ?: 'Uncategorised') ?> · per <?= h($unit ?: 'each') ?></div></div></div>

  <div class="kpis">
    <div class="kpi"><div class="l">Stock on hand</div>
      <div class="v <?= $qty < 0 ? 'neg' : '' ?>"><?= h(rtrim(rtrim(number_format($qty,3,'.',''),'0'),'.')) ?></div></div>
    <div class="kpi"><div class="l">Value at cost</div><div class="v"><?= money($qty * (float)$a['cost_price']) ?></div></div>
    <div class="kpi"><div class="l">Cost price</div><div class="v"><?= money($a['cost_price']) ?></div></div>
    <div class="kpi"><div class="l">Last confirmed count</div>
      <div class="v"><?= $lastCount ? h(date('d/m', strtotime($lastCount['count_date']))) : '—' ?></div>
      <div class="h">
        <?= $lastCount ? 'variance '.money($lastCount['variance_value']) : 'never counted' ?></div></div>
  </div>

  <h2 class="section-title">Movement history</h2>
  <div class="card scroll">
    <table><thead><tr><th>Date</th><th>Type</th><th class="num">Qty</th><th class="num">Value</th><th>Reference</th></tr></thead>
      <tbody>
      <?php if (!$hist): ?><tr><td colspan="5" class="empty">No movements yet.</td></tr><?php endif; ?>
      <?php foreach ($hist as $m): ?>
        <tr><td><?= h(date('d/m/Y', strtotime($m['mv_date']))) ?></td>
          <td><?= h(ucwords(str_replace('_',' ', $m['mv_type']))) ?></td>
          <td class="num <?= $m['qty'] < 0 ? 'neg' : 'pos' ?>"><?= h(rtrim(rtrim(number_format((float)$m['qty'],3,'.',''),'0'),'.')) ?></td>
          <td class="num"><?= money($m['mv_value']) ?></td>
          <td class="muted"><?= h($m['ref']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
  </div>
  <div class="actionbar"><a class="btn" href="lookup.php">Back to catalogue</a></div>

<?php else:
  $onhand = on_hand_map($shop);
  $rows = all("SELECT a.*, u.name AS unit, c.name AS cat FROM articles a
               LEFT JOIN units u ON u.id=a.unit_id LEFT JOIN categories c ON c.id=a.category_id
               WHERE a.active=1 ORDER BY a.name");
?>
  <div class="page-head"><div><h1>Lookup stock</h1><div class="sub"><?= h(shop_label($SHOP)) ?></div></div></div>
  <div class="toolbar">
    <input class="search" data-filter placeholder="Search the catalogue" autocomplete="off" aria-label="Search the catalogue">
    <?= scan_button('Scan') ?>
  </div>
  <div class="card scroll">
    <table><thead><tr><th>Article</th><th>Category</th><th>Unit</th>
      <th class="num">On hand</th><th class="num">At cost</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $a): $qty = $onhand[$a['id']] ?? 0; ?>
        <tr data-row="<?= h($a['name'].' '.$a['code'].' '.$a['cat']) ?>">
          <td><div class="art"><a class="info" href="lookup.php?a=<?= (int)$a['id'] ?>">i</a>
            <div><a class="name" href="lookup.php?a=<?= (int)$a['id'] ?>"><?= h($a['name']) ?></a>
            <div class="code"><?= h($a['code']) ?></div></div></div></td>
          <td class="muted"><?= h($a['cat']) ?></td>
          <td><?= h($a['unit'] ?: 'each') ?></td>
          <td class="num <?= $qty < 0 ? 'neg' : '' ?>"><?= h(rtrim(rtrim(number_format($qty,3,'.',''),'0'),'.')) ?></td>
          <td class="num"><?= money($qty * (float)$a['cost_price']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
  </div>
<?php endif; ?>
<?= scanner_ui('lookup') ?>
<?php require __DIR__.'/inc/footer.php';
