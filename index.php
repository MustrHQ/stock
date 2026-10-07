<?php
$TITLE = 'Today';
$HIDE_OOS = true;          // this page lists what has run out itself
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
    $v = $s ? (float) col("SELECT COALESCE(SUM(line_value),0) FROM waste_lines WHERE session_id=?", [$s['id']]) : 0;
    return ['done' => $s && $s['status'] === 'confirmed', 'n' => $n, 'value' => $v,
            'at' => $s && $s['confirmed_at'] ? date('H:i', strtotime($s['confirmed_at'])) : ''];
}
$ing = sheet_state($shop, $today, 'ingredient');
$sta = sheet_state($shop, $today, 'stales');
$dmg = sheet_state($shop, $today, 'quality');

/* ---- ordering + out of stock ---- */
$oos = out_of_stock_alerts($shop);
$arriving = all("SELECT o.id, o.order_no, o.delivery_date, s.name AS supplier,
                        (SELECT COUNT(*) FROM order_lines l WHERE l.order_id=o.id) AS n
                 FROM orders o JOIN suppliers s ON s.id=o.supplier_id
                 WHERE o.shop_id=? AND o.status='sent' ORDER BY o.delivery_date, o.id LIMIT 6", [$shop]);
$dueToday = count(array_filter($arriving, fn($o) => $o['delivery_date'] && $o['delivery_date'] <= $today));
$drafts = (int) col("SELECT COUNT(*) FROM orders WHERE shop_id=? AND status='draft'", [$shop]);

/* Ask once per session for each new set of out-of-stock items. */
$sig = md5(implode(',', array_column($oos, 'id')));
$showPrompt = $oos && (($_SESSION['oos_seen'] ?? '') !== $sig);
if ($showPrompt) $_SESSION['oos_seen'] = $sig;

/* ---- this week ---- */
[$wkFrom, $wkTo] = week_bounds($today);
$var = (float) col("SELECT COALESCE(SUM(mv_value),0) FROM movements WHERE shop_id=? AND mv_type=? AND mv_date BETWEEN ? AND ?",
                   [$shop, MV_COUNT_ADJ, $wkFrom, $wkTo]);
$waste = (float) col("SELECT COALESCE(SUM(mv_value),0) FROM movements WHERE shop_id=? AND mv_type IN (?,?,?) AND mv_date BETWEEN ? AND ?",
                     [$shop, MV_WASTE_STA, MV_WASTE_ING, MV_WASTE_DMG, $wkFrom, $wkTo]);

/** One job on today's run-sheet. $state: done | open | todo | idle */
function run_row($state, $href, $title, $detail, $stateText, $action) {
    static $nextShown = false;                       // only the next job gets the navy button
    $isNext = !$nextShown && ($state === 'todo' || $state === 'open');
    if ($isNext) $nextShown = true;
    $mark = $state === 'done' ? icon('check', 14) : '';
    echo '<li class="run '.h($state).'">'.
         '<span class="run-mark" aria-hidden="true">'.$mark.'</span>'.
         '<a class="run-main" href="'.h($href).'"><strong>'.h($title).'</strong><span>'.h($detail).'</span></a>'.
         '<span class="run-state">'.h($stateText).'</span>'.
         '<a class="btn sm'.($isNext ? ' go' : '').'" href="'.h($href).'">'.h($action).'</a>'.
         '</li>';
}
function lines($n) { return $n.' line'.($n === 1 ? '' : 's'); }
$multiShop = is_admin() && (int) col("SELECT COUNT(*) FROM shops WHERE active=1") > 1;
?>
<section class="daybar">
  <div>
    <h1 class="daybar-date"><?= h(date('l j F')) ?></h1>
    <div class="daybar-place">
      <?php if ($multiShop): ?>
        <form method="get" class="inline-form no-print">
          <label for="shop" class="sr">Shop</label>
          <select id="shop" name="shop" onchange="this.form.submit()" style="width:auto;height:34px;font-size:14px">
            <?php foreach (all("SELECT * FROM shops WHERE active=1 ORDER BY code") as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= $s['id'] == $shop ? 'selected' : '' ?>><?= h($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php else: ?>
        <?= h($SHOP['name']) ?><?= $SHOP['address'] ? ', '.h($SHOP['address']) : '' ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="daybar-figs">
    <div class="fig"><b class="<?= $oos ? 'neg' : '' ?>"><?= count($oos) ?></b><span>run out</span></div>
    <div class="fig"><b><?= $countConfirmed ? icon('check', 22) : (int)$countLeft ?></b><span><?= $countConfirmed ? 'count confirmed' : 'left to count' ?></span></div>
    <div class="fig"><b class="<?= $var < 0 ? 'neg' : '' ?>"><?= money($var) ?></b><span>count variance this week</span></div>
    <div class="fig"><b class="<?= $waste < 0 ? 'neg' : '' ?>"><?= money($waste) ?></b><span>waste this week</span></div>
  </div>
</section>

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

<div class="today-grid">
  <section class="card">
    <div class="rep-head"><h2>Today's jobs</h2>
      <span class="muted" style="font-size:13.5px"><?= (int)$countConfirmed + (int)$sta['done'] + (int)$ing['done'] ?> of 3 sheets confirmed</span></div>
    <ul class="runsheet">
      <?php
      if ($countConfirmed)
          run_row('done', 'stock-count.php', 'Stock count', lines($countTotal).' counted', 'Confirmed '.date('H:i', strtotime($cs['confirmed_at'])), 'View');
      elseif ($countTotal === 0)
          run_row('idle', 'stock-count.php', 'Stock count', 'Nothing is scheduled for today', 'Not needed', 'Count anyway');
      else
          run_row($countDone ? 'open' : 'todo', 'stock-count.php', 'Stock count', $countDone.' of '.lines($countTotal).' counted',
                  $countDone ? 'In progress' : 'Not started', $countDone ? 'Carry on' : 'Start count');

      run_row($sta['done'] ? 'done' : ($sta['n'] ? 'open' : 'todo'), 'product-waste.php', 'Product waste',
              $sta['n'] ? lines($sta['n']).', '.money($sta['value']).' at cost' : 'Unsold food, end of day',
              $sta['done'] ? 'Confirmed '.$sta['at'] : ($sta['n'] ? 'In progress' : 'Not started'),
              $sta['done'] ? 'View' : ($sta['n'] ? 'Carry on' : 'Record waste'));

      run_row($ing['done'] ? 'done' : ($ing['n'] ? 'open' : 'todo'), 'ingredient-waste.php', 'Ingredient waste',
              $ing['n'] ? lines($ing['n']).', '.money($ing['value']).' at cost' : 'Out-of-date or spoiled ingredients',
              $ing['done'] ? 'Confirmed '.$ing['at'] : ($ing['n'] ? 'In progress' : 'Not started'),
              $ing['done'] ? 'View' : ($ing['n'] ? 'Carry on' : 'Record waste'));

      run_row($dmg['done'] ? 'done' : ($dmg['n'] ? 'open' : 'idle'), 'damaged-stock.php', 'Damaged stock',
              $dmg['n'] ? lines($dmg['n']).', '.money($dmg['value']).' at cost' : 'Only if something arrived or got damaged',
              $dmg['done'] ? 'Confirmed '.$dmg['at'] : ($dmg['n'] ? 'In progress' : 'Nothing logged'),
              $dmg['n'] || $dmg['done'] ? 'View' : 'Log damage');

      if ($dueToday)
          run_row('todo', 'orders.php?status=sent', 'Receive deliveries', $dueToday.' '.($dueToday === 1 ? 'order is' : 'orders are').' due today',
                  'Waiting', 'Book in');
      ?>
    </ul>
  </section>

  <div>
    <section class="card" id="runout">
      <div class="rep-head"><h2>Run out</h2>
        <?php if ($oos): ?><a class="btn sm primary" href="orders.php#out">Order these</a><?php endif; ?></div>
      <?php if (!$oos): ?>
        <p class="pad muted" style="margin:0">Nothing has run out.</p>
      <?php else: ?>
        <ul class="list">
          <?php foreach (array_slice($oos, 0, 6) as $r): ?>
            <li><span class="what"><strong><?= h($r['name']) ?></strong><span><?= h($r['supplier'] ?: 'No supplier set') ?></span></span>
              <span class="amt neg"><?= h(fmt_qty($r['on_hand'])) ?> <?= h($r['unit'] ?: 'each') ?></span></li>
          <?php endforeach; ?>
        </ul>
        <?php if (count($oos) > 6): ?><div class="panel-foot"><a href="orders.php#out"><?= count($oos) - 6 ?> more</a></div><?php endif; ?>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="rep-head"><h2>Arriving</h2><a class="btn sm" href="orders.php">All orders</a></div>
      <?php if (!$arriving): ?>
        <p class="pad muted" style="margin:0">No orders on the way.<?= $drafts ? ' '.$drafts.' draft'.($drafts > 1 ? 's' : '').' not sent yet.' : '' ?></p>
      <?php else: ?>
        <ul class="list">
          <?php foreach ($arriving as $o): $late = $o['delivery_date'] && $o['delivery_date'] < $today; ?>
            <li><a class="what" href="order.php?id=<?= (int)$o['id'] ?>" style="color:inherit"><strong><?= h($o['supplier']) ?></strong>
                <span><?= h($o['order_no']) ?>, <?= (int)$o['n'] ?> line<?= $o['n'] == 1 ? '' : 's' ?></span></a>
              <span class="tag <?= $late ? 'todo' : ($o['delivery_date'] === $today ? 'warn' : 'blue') ?>">
                <?= !$o['delivery_date'] ? 'No date' : ($late ? 'Late' : ($o['delivery_date'] === $today ? 'Today' : h(date('D j M', strtotime($o['delivery_date']))))) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <div class="shortcuts no-print">
      <a class="btn sm" href="deliveries.php"><?= icon('truck', 15) ?>Goods in</a>
      <a class="btn sm" href="lookup.php"><?= icon('search', 15) ?>Look up stock</a>
      <a class="btn sm" href="reports.php"><?= icon('chart', 15) ?>Stock loss</a>
    </div>
  </div>
</div>

<?php if ($showPrompt): ?>
<dialog class="prompt" id="oosPrompt" aria-labelledby="oosTitle">
  <div class="prompt-head">
    <div class="prompt-ic"><?= icon('cart', 20) ?></div>
    <div>
      <h2 id="oosTitle"><?= count($oos) === 1 ? 'An item has run out' : count($oos).' items have run out' ?></h2>
      <p>They are at zero or below and nobody has ordered them yet.</p>
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
    <button class="btn" value="later">Not now</button>
    <a class="btn primary" href="orders.php#out"><?= icon('cart', 16) ?>Order them</a>
  </form>
</dialog>
<script>(function(){var d=document.getElementById('oosPrompt');if(d&&d.showModal)d.showModal();})();</script>
<?php endif; ?>
<?php require __DIR__.'/inc/footer.php';
