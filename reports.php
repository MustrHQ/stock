<?php
/** Stock Loss — variance (count vs expected) and waste at cost, by product, category and date. */
require_once __DIR__.'/lib.php';
require_login();
$SHOP = current_shop(); $shop = $SHOP['id'];

$kind = ($_GET['kind'] ?? 'product') === 'ingredient' ? 'ingredient' : 'product';
$off  = (int)($_GET['w'] ?? 0);
$ref  = date('Y-m-d', strtotime($off.' week'));
[$from, $to] = week_bounds($ref);
$weekLabel = date('o', strtotime($from)).'W'.date('W', strtotime($from));

/* ---- NSEV: use the imported daily figure when there is one, else derive from sales ---- */
function nsev_range($shop, $from, $to) {
    $imported = (float) col("SELECT COALESCE(SUM(nsev),0) FROM sales_days
                             WHERE shop_id=? AND sales_date BETWEEN ? AND ?", [$shop, $from, $to]);
    if ($imported > 0) return $imported;
    return (float) col("SELECT COALESCE(SUM(-m.qty * a.sell_price),0) FROM movements m
                        JOIN articles a ON a.id=m.article_id
                        WHERE m.shop_id=? AND m.mv_type=? AND m.mv_date BETWEEN ? AND ?",
                       [$shop, MV_SALE, $from, $to]);
}
function ratio($num, $den) { return $den != 0 ? ($num / $den) * 100 : 0; }
function qtyfmt($n) { return rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.'); }

$NSEV   = nsev_range($shop, $from, $to);
$wasteT = [MV_WASTE_STA, MV_WASTE_ING, MV_WASTE_QCP];

/* ---- variance by product ---- */
$byProduct = all("SELECT a.name, a.code, SUM(m.mv_value) AS var, SUM(m.qty) AS qty
                  FROM movements m
                  JOIN articles a ON a.id=m.article_id
                  WHERE m.shop_id=? AND m.mv_type=? AND m.mv_date BETWEEN ? AND ? AND a.kind=?
                  GROUP BY a.id, a.name, a.code ORDER BY var ASC", [$shop, MV_COUNT_ADJ, $from, $to, $kind]);
/* per-article sales value for the same week */
$artSales = [];
foreach (all("SELECT a.code, SUM(-m.qty * a.sell_price) AS val FROM movements m
              JOIN articles a ON a.id=m.article_id
              WHERE m.shop_id=? AND m.mv_type=? AND m.mv_date BETWEEN ? AND ?
              GROUP BY a.id, a.code", [$shop, MV_SALE, $from, $to]) as $r) $artSales[$r['code']] = (float)$r['val'];

/* ---- variance by date ---- */
$byDate = all("SELECT m.mv_date, SUM(m.mv_value) AS var FROM movements m
               JOIN articles a ON a.id=m.article_id
               WHERE m.shop_id=? AND m.mv_type=? AND m.mv_date BETWEEN ? AND ? AND a.kind=?
               GROUP BY m.mv_date ORDER BY m.mv_date", [$shop, MV_COUNT_ADJ, $from, $to, $kind]);

/* ---- waste at cost by category and by date ---- */
$in = implode(',', array_fill(0, count($wasteT), '?'));
$byCat = all("SELECT COALESCE(c.name,'Uncategorised') AS cat, SUM(m.mv_value) AS stales
              FROM movements m JOIN articles a ON a.id=m.article_id
              LEFT JOIN categories c ON c.id=a.category_id
              WHERE m.shop_id=? AND m.mv_type IN ($in) AND m.mv_date BETWEEN ? AND ? AND a.kind=?
              GROUP BY c.id, c.name ORDER BY stales ASC",
             array_merge([$shop], $wasteT, [$from, $to, $kind]));
$wasteByDate = all("SELECT m.mv_date, SUM(m.mv_value) AS stales
                    FROM movements m JOIN articles a ON a.id=m.article_id
                    WHERE m.shop_id=? AND m.mv_type IN ($in) AND m.mv_date BETWEEN ? AND ? AND a.kind=?
                    GROUP BY m.mv_date ORDER BY m.mv_date",
                   array_merge([$shop], $wasteT, [$from, $to, $kind]));

/* sales value per category, so each category's waste is measured against its own takings */
$catSales = [];
foreach (all("SELECT COALESCE(c.name,'Uncategorised') AS cat, SUM(-m.qty * a.sell_price) AS val
              FROM movements m JOIN articles a ON a.id=m.article_id
              LEFT JOIN categories c ON c.id=a.category_id
              WHERE m.shop_id=? AND m.mv_type=? AND m.mv_date BETWEEN ? AND ?
              GROUP BY c.id, c.name", [$shop, MV_SALE, $from, $to]) as $r) $catSales[$r['cat']] = (float)$r['val'];

$varTotal   = 0; $qtyTotal = 0; foreach ($byProduct as $r) { $varTotal += (float)$r['var']; $qtyTotal += (float)$r['qty']; }
$stalesTotal = 0; foreach ($byCat as $r) $stalesTotal += (float)$r['stales'];
$maxVar = 0.0001; foreach ($byProduct as $r) $maxVar = max($maxVar, abs((float)$r['var']));
$maxSt  = 0.0001; foreach ($byCat as $r) $maxSt = max($maxSt, abs((float)$r['stales']));

$nsevDay = [];
foreach (all("SELECT sales_date, nsev FROM sales_days WHERE shop_id=? AND sales_date BETWEEN ? AND ?",
             [$shop, $from, $to]) as $r) $nsevDay[$r['sales_date']] = (float)$r['nsev'];

$TITLE = 'Stock loss';
require __DIR__.'/inc/header.php';
?>
<div class="page-head">
  <div><h1>Stock loss</h1>
    <div class="sub"><?= h($weekLabel) ?> · week ending <?= h(date('l j F Y', strtotime($to))) ?> · <?= h(shop_label($SHOP)) ?></div></div>
  <div class="btn-row no-print">
    <a class="btn sm <?= $kind === 'product' ? 'on' : '' ?>" href="?kind=product&w=<?= $off ?>">Products</a>
    <a class="btn sm <?= $kind === 'ingredient' ? 'on' : '' ?>" href="?kind=ingredient&w=<?= $off ?>">Ingredients</a>
    <a class="btn sm" href="?kind=<?= h($kind) ?>&w=<?= $off - 1 ?>" title="Previous week"><?= icon('left', 16) ?></a>
    <?php if ($off < 0): ?><a class="btn sm" href="?kind=<?= h($kind) ?>&w=<?= $off + 1 ?>" title="Next week"><?= icon('right', 16) ?></a><?php endif; ?>
    <button class="btn sm" type="button" onclick="window.print()"><?= icon('printer', 15) ?>Print</button>
  </div>
</div>

<div class="kpis">
  <div class="kpi"><div class="l">Count variance</div>
    <div class="v <?= $varTotal < 0 ? 'neg' : ($varTotal > 0 ? 'pos' : '') ?>"><?= money($varTotal) ?></div>
    <div class="h"><?= pct(ratio($varTotal, $NSEV)) ?> of NSEV</div></div>
  <div class="kpi"><div class="l">Waste at cost</div>
    <div class="v <?= $stalesTotal < 0 ? 'neg' : '' ?>"><?= money($stalesTotal) ?></div>
    <div class="h"><?= pct(ratio($stalesTotal, $NSEV)) ?> of NSEV</div></div>
  <div class="kpi"><div class="l">Total loss</div>
    <div class="v <?= ($varTotal + $stalesTotal) < 0 ? 'neg' : '' ?>"><?= money($varTotal + $stalesTotal) ?></div>
    <div class="h"><?= pct(ratio($varTotal + $stalesTotal, $NSEV)) ?> of NSEV</div></div>
  <div class="kpi"><div class="l">NSEV</div><div class="v"><?= money($NSEV) ?></div>
    <div class="h">Net sales excluding VAT</div></div>
</div>

<div class="two-col">
  <div class="card">
    <div class="rep-head"><h2>Variance by product</h2></div>
    <div class="scroll"><table>
      <thead><tr><th>Article name</th><th class="num">Var.</th><th class="num">Qty</th>
        <th class="num">NSEV</th><th class="num">Var. % NSEV</th></tr></thead>
      <tbody>
        <?php if (!$byProduct): ?><tr><td colspan="5" class="empty">
          No confirmed counts in this week yet.</td></tr><?php endif; ?>
        <?php foreach ($byProduct as $r): $ns = $artSales[$r['code']] ?? 0; ?>
          <tr><td><?= h($r['name']) ?></td>
            <td class="num bar-cell"><i style="width:calc((100% - 32px) * <?= round(abs((float)$r['var']) / $maxVar, 3) ?>)"></i>
              <span class="<?= $r['var'] < 0 ? 'neg' : 'pos' ?>"><?= money($r['var']) ?></span></td>
            <td class="num"><?= h(qtyfmt($r['qty'])) ?></td>
            <td class="num"><?= money($ns) ?></td>
            <td class="num"><?= pct(ratio((float)$r['var'], $ns)) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td>Total</td><td class="num"><?= money($varTotal) ?></td>
        <td class="num"><?= h(qtyfmt($qtyTotal)) ?></td><td class="num"><?= money($NSEV) ?></td>
        <td class="num"><?= pct(ratio($varTotal, $NSEV)) ?></td></tr></tfoot>
    </table></div>
  </div>

  <div class="card">
    <div class="rep-head"><h2>Variance by date</h2></div>
    <div class="scroll"><table>
      <thead><tr><th>Date</th><th class="num">Var.</th><th class="num">NSEV</th><th class="num">Var. % NSEV</th></tr></thead>
      <tbody>
        <?php if (!$byDate): ?><tr><td colspan="4" class="empty">
          Nothing to show for this week.</td></tr><?php endif; ?>
        <?php foreach ($byDate as $r): $ns = $nsevDay[$r['mv_date']] ?? nsev_range($shop, $r['mv_date'], $r['mv_date']); ?>
          <tr><td><?= h(date('d/m/Y', strtotime($r['mv_date']))) ?></td>
            <td class="num <?= $r['var'] < 0 ? 'neg' : 'pos' ?>"><?= money($r['var']) ?></td>
            <td class="num"><?= money($ns) ?></td>
            <td class="num"><?= pct(ratio((float)$r['var'], $ns)) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td>Total</td><td class="num"><?= money($varTotal) ?></td>
        <td class="num"><?= money($NSEV) ?></td><td class="num"><?= pct(ratio($varTotal, $NSEV)) ?></td></tr></tfoot>
    </table></div>
  </div>

  <div class="card">
    <div class="rep-head"><h2>Waste at cost by category</h2></div>
    <div class="scroll"><table>
      <thead><tr><th>Category</th><th class="num">Waste</th><th class="num">Category sales</th><th class="num">Waste % NSEV</th></tr></thead>
      <tbody>
        <?php if (!$byCat): ?><tr><td colspan="4" class="empty">
          No waste confirmed this week.</td></tr><?php endif; ?>
        <?php foreach ($byCat as $r): $cs = $catSales[$r['cat']] ?? 0; ?>
          <tr><td><?= h($r['cat']) ?></td>
            <td class="num bar-cell"><i style="width:calc((100% - 32px) * <?= round(abs((float)$r['stales']) / $maxSt, 3) ?>)"></i>
              <span class="neg"><?= money($r['stales']) ?></span></td>
            <td class="num"><?= $cs > 0 ? money($cs) : '<span class="faint">—</span>' ?></td>
            <td class="num"><?= $cs > 0 ? pct(ratio((float)$r['stales'], $cs)) : '<span class="faint">—</span>' ?></td></tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td>Total</td><td class="num"><?= money($stalesTotal) ?></td>
        <td class="num"><?= money($NSEV) ?></td><td class="num"><?= pct(ratio($stalesTotal, $NSEV)) ?></td></tr></tfoot>
    </table></div>
  </div>

  <div class="card">
    <div class="rep-head"><h2>Waste at cost by date</h2></div>
    <div class="scroll"><table>
      <thead><tr><th>Date</th><th class="num">Waste</th><th class="num">NSEV</th><th class="num">Waste % NSEV</th></tr></thead>
      <tbody>
        <?php if (!$wasteByDate): ?><tr><td colspan="4" class="empty">
          No waste confirmed this week.</td></tr><?php endif; ?>
        <?php foreach ($wasteByDate as $r): $ns = $nsevDay[$r['mv_date']] ?? nsev_range($shop, $r['mv_date'], $r['mv_date']); ?>
          <tr><td><?= h(date('d/m/Y', strtotime($r['mv_date']))) ?></td>
            <td class="num neg"><?= money($r['stales']) ?></td>
            <td class="num"><?= money($ns) ?></td>
            <td class="num"><?= pct(ratio((float)$r['stales'], $ns)) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td>Total</td><td class="num"><?= money($stalesTotal) ?></td>
        <td class="num"><?= money($NSEV) ?></td><td class="num"><?= pct(ratio($stalesTotal, $NSEV)) ?></td></tr></tfoot>
    </table></div>
  </div>
</div>

<p class="lede" style="margin-top:16px">Variance is counted stock minus expected stock, valued at cost. Waste covers stales, ingredient and quality checkpoint sheets.</p>

<?php require __DIR__.'/inc/footer.php';
