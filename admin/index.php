<?php
require __DIR__.'/inc.php';
require_once MUSTR_ROOT.'/inc/demo.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'demo_remove') {
        try { $n = demo_remove(); flash($n.' demo '.($n === 1 ? 'article' : 'articles').' removed, with their stock history, barcodes and orders. Your own data is untouched.'); }
        catch (Throwable $e) { flash('Could not remove the demo data: '.$e->getMessage(), 'err'); }
    }
    if (($_POST['action'] ?? '') === 'demo_add') {
        try { demo_seed(db()); flash('Demo data added: a fictional takeaway menu, three suppliers and four barcodes. Remove it from here whenever you like.'); }
        catch (Throwable $e) { flash('Could not add the demo data: '.$e->getMessage(), 'err'); }
    }
    redirect('index.php');
}
$demo = demo_summary();
$emptyCatalogue = !(int)col("SELECT COUNT(*) FROM articles");
$shops = all("SELECT * FROM shops ORDER BY code");
$today = date('Y-m-d');
admin_header('Dashboard', 'index');
?>
<h1 class="admin-title">System overview</h1>
<p class="lede">Who is counting, what is still open, and where the loss sits today.</p>

<?php if ($demo): ?>
<section class="card pad" style="border-left:3px solid var(--amber)">
  <div class="install-row">
    <span class="tile-ic" style="background:var(--amber-tint);color:var(--amber)"><?= icon('box', 20) ?></span>
    <div><h2 class="card-title">Demo data is loaded</h2>
      <p class="lede" style="margin:0"><?= (int)$demo['articles'] ?> demo articles<?= $demo['movements'] ? ', '.(int)$demo['movements'].' stock movements' : '' ?><?= $demo['orders'] ? ' and '.(int)$demo['orders'].' order'.($demo['orders'] == 1 ? '' : 's') : '' ?>.
        Remove it before you start using the system for real — anything you have added yourself stays.</p></div>
    <form method="post" onsubmit="return confirm('Remove all demo data? Your own articles, sheets and orders are not touched.')">
      <?= csrf_field() ?><input type="hidden" name="action" value="demo_remove">
      <button class="btn danger"><?= icon('trash', 16) ?>Remove demo data</button></form>
  </div>
</section>
<?php elseif ($emptyCatalogue): ?>
<section class="card pad">
  <div class="install-row">
    <span class="tile-ic"><?= icon('box', 20) ?></span>
    <div><h2 class="card-title">Your catalogue is empty</h2>
      <p class="lede" style="margin:0">Add your own articles, or load a small fictional takeaway menu to try the app first.
        It can be removed again in one click.</p></div>
    <div class="btn-row">
      <a class="btn primary" href="articles.php"><?= icon('plus', 16) ?>Add articles</a>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="demo_add">
        <button class="btn">Load demo data</button></form>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="kpis">
  <div class="kpi"><div class="l">Active articles</div>
    <div class="v"><?= (int)col("SELECT COUNT(*) FROM articles WHERE active=1") ?></div></div>
  <div class="kpi"><div class="l">Shops</div>
    <div class="v"><?= (int)col("SELECT COUNT(*) FROM shops WHERE active=1") ?></div></div>
  <div class="kpi"><div class="l">Users</div>
    <div class="v"><?= (int)col("SELECT COUNT(*) FROM users WHERE active=1") ?></div></div>
  <div class="kpi"><div class="l">Counts confirmed today</div>
    <div class="v"><?= (int)col("SELECT COUNT(*) FROM count_sessions WHERE count_date=? AND status='confirmed'", [$today]) ?>
      / <?= count($shops) ?></div></div>
</div>

<h2 class="section-title">Today by shop</h2>
<div class="card scroll">
  <table>
    <thead><tr><th>Shop</th><th>Stock count</th><th>Ingredient waste</th><th>Product waste</th>
      <th>Damaged</th><th class="num">Negative lines</th><th class="num">Variance this week</th></tr></thead>
    <tbody>
    <?php
    [$wf, $wt] = week_bounds($today);
    foreach ($shops as $s):
      $cs = one("SELECT status FROM count_sessions WHERE shop_id=? AND count_date=?", [$s['id'], $today]);
      $ws = [];
      foreach (['ingredient','stales','quality'] as $t)
          $ws[$t] = col("SELECT status FROM waste_sessions WHERE shop_id=? AND waste_date=? AND waste_type=?",
                        [$s['id'], $today, $t]);
      $neg = count(negative_stock_articles($s['id']));
      $var = (float) col("SELECT COALESCE(SUM(mv_value),0) FROM movements
                          WHERE shop_id=? AND mv_type=? AND mv_date BETWEEN ? AND ?",
                         [$s['id'], MV_COUNT_ADJ, $wf, $wt]);
      $tag = fn($st) => $st === 'confirmed'
          ? '<span class="tag ok">Confirmed</span>' : '<span class="tag todo">To do</span>';
    ?>
      <tr>
        <td><?= h($s['code'].' '.$s['name']) ?><div class="code muted"><?= h($s['address']) ?></div></td>
        <td><?= $tag($cs['status'] ?? null) ?></td>
        <td><?= $tag($ws['ingredient']) ?></td>
        <td><?= $tag($ws['stales']) ?></td>
        <td><?= $tag($ws['quality']) ?></td>
        <td class="num <?= $neg ? 'neg' : '' ?>"><?= (int)$neg ?></td>
        <td class="num <?= $var < 0 ? 'neg' : 'pos' ?>"><?= money($var) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<h2 class="section-title">Latest confirmations</h2>
<div class="card scroll">
  <table>
    <thead><tr><th>When</th><th>Shop</th><th>Sheet</th><th>Confirmed by</th></tr></thead>
    <tbody>
    <?php
    $recent = all("SELECT cs.confirmed_at, s.code, s.name, 'Stock count' AS sheet, u.name AS who
                   FROM count_sessions cs JOIN shops s ON s.id=cs.shop_id
                   LEFT JOIN users u ON u.id=cs.created_by
                   WHERE cs.status='confirmed'
                   UNION ALL
                   SELECT ws.confirmed_at, s.code, s.name, CASE ws.waste_type WHEN 'stales' THEN 'Product waste' WHEN 'ingredient' THEN 'Ingredient waste' ELSE 'Damaged stock' END, u.name
                   FROM waste_sessions ws JOIN shops s ON s.id=ws.shop_id
                   LEFT JOIN users u ON u.id=ws.created_by
                   WHERE ws.status='confirmed'
                   ORDER BY 1 DESC LIMIT 20");
    if (!$recent) echo '<tr><td colspan="4" class="empty">Nothing confirmed yet.</td></tr>';
    foreach ($recent as $r): ?>
      <tr><td><?= h(date('d/m/Y H:i', strtotime($r['confirmed_at']))) ?></td>
        <td><?= h($r['code'].' '.$r['name']) ?></td><td><?= h($r['sheet']) ?></td>
        <td class="muted"><?= h($r['who']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_footer();
