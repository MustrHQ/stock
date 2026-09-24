<?php
$TITLE = 'Home';
$HIDE_OOS = true;          // this page asks with its own prompt instead of the strip
require __DIR__.'/inc/header.php';

$today = date('Y-m-d');
$shop  = $SHOP['id'];

/* ---- stock count ---- */
$cs   = count_session($shop, $today, false);
$due  = array_unique(array_merge(scheduled_article_ids($shop, $today), negative_stock_articles($shop)));
$countTotal = count($due);
$countDone  = 0;
if ($cs) {
    $countDone  = (int) col("SELECT COUNT(*) FROM count_lines WHERE session_id=? AND qty IS NOT NULL", [$cs['id']]);
    $countTotal = max($countTotal, (int) col("SELECT COUNT(*) FROM count_lines WHERE session_id=?", [$cs['id']]));
}
$countConfirmed = $cs && $cs['status'] === 'confirmed';
$countLeft = $countConfirmed ? 0 : max(0, $countTotal - $countDone);

/* ---- waste sheets ---- */
function sheet_state($shop, $date, $type) {
    $s = waste_session($shop, $date, $type, false);
    $n = $s ? (int) col("SELECT COUNT(*) FROM waste_lines WHERE session_id=? AND qty>0", [$s['id']]) : 0;
    return ['done' => $s && $s['status'] === 'confirmed', 'n' => $n];
}
$ing = sheet_state($shop, $today, 'ingredient');
$sta = sheet_state($shop, $today, 'stales');
$qcp = sheet_state($shop, $today, 'quality');

/* ---- ordering + out of stock ---- */
$oos         = out_of_stock_alerts($shop);
$draftOrders = (int) col("SELECT COUNT(*) FROM orders WHERE shop_id=? AND status='draft'", [$shop]);
$dueOrders   = (int) col("SELECT COUNT(*) FROM orders WHERE shop_id=? AND status='sent'", [$shop]);

/* Prompt once per session for each new set of out-of-stock items. */
$sig = md5(implode(',', array_column($oos, 'id')));
$showPrompt = $oos && (($_SESSION['oos_seen'] ?? '') !== $sig);
if ($showPrompt) $_SESSION['oos_seen'] = $sig;

/* ---- this week ---- */
[$wkFrom, $wkTo] = week_bounds($today);
$var = (float) col("SELECT COALESCE(SUM(mv_value),0) FROM movements WHERE shop_id=? AND mv_type=? AND mv_date BETWEEN ? AND ?",
                   [$shop, MV_COUNT_ADJ, $wkFrom, $wkTo]);
$waste = (float) col("SELECT COALESCE(SUM(mv_value),0) FROM movements WHERE shop_id=? AND mv_type IN (?,?,?) AND mv_date BETWEEN ? AND ?",
                     [$shop, MV_WASTE_STA, MV_WASTE_ING, MV_WASTE_QCP, $wkFrom, $wkTo]);

function tile($href, $icon, $tone, $title, $sub, $num, $numTone, $tag, $tagTone) {
    echo '<a class="tile '.h($tone).'" href="'.h($href).'">'.
         '<span class="tile-ic">'.icon($icon, 20).'</span>'.
         '<span class="t">'.h($title).'</span>'.
         ($sub !== '' ? '<span class="s">'.h($sub).'</span>' : '').
         '<span class="foot"><span class="tag '.h($tagTone).'">'.h($tag).'</span>'.
         ($num === null ? '' : '<span class="n '.h($numTone).'">'.h($num).'</span>').
         '</span></a>';
}
?>
<div class="page-head">
  <div>
    <h1><?= h($SHOP['name']) ?></h1>
    <div class="sub"><?= h(shop_label($SHOP)) ?> · <?= h(date('l j F Y')) ?></div>
  </div>
  <?php if (is_admin() && (int)col("SELECT COUNT(*) FROM shops WHERE active=1") > 1): ?>
  <form method="get" class="no-print" style="min-width:240px">
    <label for="shop">Working in</label>
    <select id="shop" name="shop" onchange="this.form.submit()">
      <?php foreach (all("SELECT * FROM shops WHERE active=1 ORDER BY code") as $s): ?>
        <option value="<?= (int)$s['id'] ?>" <?= $s['id'] == $shop ? 'selected' : '' ?>><?= h($s['code'].' '.$s['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php endif; ?>
</div>

<?php if ($oos): ?>
<div class="msg err" role="status"><?= icon('alert', 16) ?>
  <span><strong><?= count($oos) ?> <?= count($oos) === 1 ? 'item has' : 'items have' ?> run out</strong> and
    <?= count($oos) === 1 ? 'is' : 'are' ?> not on any order yet. <a href="orders.php#out">Order <?= count($oos) === 1 ? 'it' : 'them' ?> now</a></span>
</div>
<?php endif; ?>

<section class="card pad install-card" data-install hidden>
  <div class="install-row">
    <span class="tile-ic"><?= icon('phone', 20) ?></span>
    <div><h2 class="card-title">Put this on your phone as an app</h2>
      <p class="lede" style="margin:0" data-install-text>Supervisors can count, record waste and scan from the shop floor, and owners can
        check any shop from anywhere. No app store needed.</p></div>
    <button class="btn primary" type="button" data-install-btn hidden><?= icon('download', 16) ?>Install app</button>
    <button class="btn ghost sm" type="button" data-install-dismiss aria-label="Hide this"><?= icon('x', 16) ?></button>
  </div>
</section>

<h2 class="grp-title">Today's sheets</h2>
<div class="tiles">
  <?php
  tile('stock-count.php', 'count', $countConfirmed ? '' : 'blue', 'Stock count',
       $countConfirmed ? 'Confirmed' : $countDone.' of '.$countTotal.' counted',
       $countConfirmed ? null : $countLeft, $countLeft ? 'todo' : 'done',
       $countConfirmed ? 'Complete' : 'To do', $countConfirmed ? 'ok' : 'todo');
  tile('stales-waste.php', 'trash', 'amber', 'Stales waste', $sta['n'].' line'.($sta['n'] === 1 ? '' : 's').' recorded',
       null, '', $sta['done'] ? 'Complete' : 'To do', $sta['done'] ? 'ok' : 'todo');
  tile('ingredient-waste.php', 'drop', 'amber', 'Ingredient waste', $ing['n'].' line'.($ing['n'] === 1 ? '' : 's').' recorded',
       null, '', $ing['done'] ? 'Complete' : 'To do', $ing['done'] ? 'ok' : 'todo');
  tile('quality-checkpoint.php', 'shield', 'purple', 'Quality checkpoint', 'Damaged and unsellable',
       $qcp['n'] ?: null, '', $qcp['done'] ? 'Complete' : ($qcp['n'] ? 'Open' : 'Nothing logged'),
       $qcp['done'] ? 'ok' : ($qcp['n'] ? 'warn' : ''));
  ?>
</div>

<h2 class="grp-title">Stock and ordering</h2>
<div class="tiles">
  <?php
  tile('orders.php', 'cart', $oos ? 'red' : '', 'Ordering',
       $dueOrders ? $dueOrders.' awaiting delivery' : 'Build, print and receive',
       $oos ? count($oos) : ($draftOrders ?: null), $oos ? 'todo' : '',
       $oos ? 'Out of stock' : ($draftOrders ? $draftOrders.' draft'.($draftOrders > 1 ? 's' : '') : 'Up to date'),
       $oos ? 'todo' : ($draftOrders ? 'warn' : 'ok'));
  tile('deliveries.php', 'truck', 'blue', 'Goods in', 'Deliveries and transfers', null, '', 'Record', 'blue');
  tile('lookup.php', 'search', 'blue', 'Lookup stock', 'On hand and history', null, '', 'Search', 'blue');
  tile('reports.php', 'chart', '', 'Stock loss', 'Variance and waste', null, '', 'Report', 'blue');
  ?>
</div>

<h2 class="grp-title">This week · <?= h(date('j M', strtotime($wkFrom))) ?> to <?= h(date('j M', strtotime($wkTo))) ?></h2>
<div class="kpis">
  <div class="kpi"><div class="l">Out of stock</div>
    <div class="v <?= $oos ? 'neg' : '' ?>"><?= count($oos) ?></div><div class="h">Not on any order yet</div></div>
  <div class="kpi"><div class="l">Counted today</div>
    <div class="v"><?= (int)$countDone ?> <span class="faint" style="font-size:16px">/ <?= (int)$countTotal ?></span></div>
    <div class="h">Lines with a quantity</div></div>
  <div class="kpi"><div class="l">Count variance</div>
    <div class="v <?= $var < 0 ? 'neg' : ($var > 0 ? 'pos' : '') ?>"><?= money($var) ?></div><div class="h">At cost price</div></div>
  <div class="kpi"><div class="l">Waste</div>
    <div class="v <?= $waste < 0 ? 'neg' : '' ?>"><?= money($waste) ?></div><div class="h">Stales, ingredient and QCP</div></div>
</div>

<?php if ($showPrompt): ?>
<dialog class="prompt" id="oosPrompt" aria-labelledby="oosTitle">
  <div class="prompt-head">
    <div class="prompt-ic"><?= icon('cart', 20) ?></div>
    <div>
      <h2 id="oosTitle"><?= count($oos) === 1 ? 'An item has run out' : count($oos).' items have run out' ?></h2>
      <p>These are at zero or below and nobody has ordered them yet. Add them to an order now?</p>
    </div>
  </div>
  <ul class="prompt-list">
    <?php foreach (array_slice($oos, 0, 8) as $r): ?>
      <li><span><strong><?= h($r['name']) ?></strong><br><span class="faint"><?= h($r['supplier'] ?: 'No supplier set') ?></span></span>
        <span class="neg"><?= h(fmt_qty($r['on_hand'])) ?> <?= h($r['unit'] ?: 'each') ?></span></li>
    <?php endforeach; ?>
    <?php if (count($oos) > 8): ?><li class="faint">and <?= count($oos) - 8 ?> more</li><?php endif; ?>
  </ul>
  <form method="dialog" class="prompt-foot">
    <button class="btn" value="later">Remind me later</button>
    <a class="btn primary" href="orders.php#out"><?= icon('cart', 16) ?>Review and order</a>
  </form>
</dialog>
<script>(function(){var d=document.getElementById('oosPrompt');if(d&&d.showModal)d.showModal();})();</script>
<?php endif; ?>
<?php require __DIR__.'/inc/footer.php';
