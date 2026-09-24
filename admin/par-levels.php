<?php
/** Par levels — how much of each article a shop wants on the shelf, and the pack it comes in. */
require __DIR__.'/inc.php';
$shopId = (int)($_GET['shop'] ?? 0) ?: (int)col("SELECT id FROM shops WHERE active=1 ORDER BY code LIMIT 1");
$supId  = (int)($_GET['supplier'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $shopId = (int)($_POST['shop_id'] ?? $shopId);
    foreach (($_POST['par'] ?? []) as $aid => $v) {
        $aid  = (int)$aid;
        $par  = trim($v);
        $pack = trim($_POST['pack'][$aid] ?? '');
        if ($par === '' && $pack === '') { q("DELETE FROM par_levels WHERE shop_id=? AND article_id=?", [$shopId, $aid]); continue; }
        q("INSERT INTO par_levels (shop_id,article_id,par_qty,pack_size) VALUES (?,?,?,?)
           ON DUPLICATE KEY UPDATE par_qty=VALUES(par_qty), pack_size=VALUES(pack_size)",
          [$shopId, $aid, (float)$par, (float)$pack]);
    }
    flash('Par levels saved.');
    redirect('par-levels.php?shop='.$shopId.($supId ? '&supplier='.$supId : ''));
}

$sql = "SELECT a.*, u.name AS unit, s.name AS supplier, p.par_qty, p.pack_size AS shop_pack
        FROM articles a LEFT JOIN units u ON u.id=a.unit_id
        LEFT JOIN suppliers s ON s.id=a.supplier_id
        LEFT JOIN par_levels p ON p.article_id=a.id AND p.shop_id=?
        WHERE a.active=1";
$p = [$shopId];
if ($supId) { $sql .= " AND a.supplier_id=?"; $p[] = $supId; }
$sql .= " ORDER BY s.name, a.name";
$rows = all($sql, $p);
$hand = on_hand_map($shopId);
admin_header('Par levels', 'par');
?>
<h1 class="admin-title">Par levels</h1>
<p class="lede">Par is the figure the shelf should be brought back up to. Leave it blank and the suggested
  order falls back to recent sales instead. Pack size rounds every suggestion up to whole cases —
  set it here to override the article default for this shop.</p>

<form method="get" class="card pad inline">
  <div style="flex:1 1 240px"><label for="shop">Shop</label>
    <select id="shop" name="shop" onchange="this.form.submit()">
      <?php foreach (all("SELECT * FROM shops WHERE active=1 ORDER BY code") as $s): ?>
        <option value="<?= (int)$s['id'] ?>" <?= $shopId == $s['id'] ? 'selected' : '' ?>>
          <?= h($s['code'].' '.$s['name']) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div style="flex:1 1 240px"><label for="supplier">Supplier</label>
    <select id="supplier" name="supplier" onchange="this.form.submit()">
      <option value="0">All suppliers</option>
      <?php foreach (all("SELECT * FROM suppliers ORDER BY name") as $s): ?>
        <option value="<?= (int)$s['id'] ?>" <?= $supId == $s['id'] ? 'selected' : '' ?>><?= h($s['name']) ?></option>
      <?php endforeach; ?>
    </select></div>
</form>

<form method="post">
  <?= csrf_field() ?><input type="hidden" name="shop_id" value="<?= (int)$shopId ?>">
  <input class="search" data-filter placeholder="Search articles" aria-label="Search articles">
  <div class="card scroll">
    <table>
      <thead><tr><th>Article</th><th>Supplier</th><th>Unit</th>
        <th class="num">On hand</th><th class="num">Par</th><th class="num">Pack size</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $a): $oh = $hand[$a['id']] ?? 0; ?>
        <tr data-row="<?= h($a['code'].' '.$a['name'].' '.$a['supplier']) ?>">
          <td><?= h($a['name']) ?><div class="code muted"><?= h($a['code']) ?></div></td>
          <td class="muted"><?= h($a['supplier'] ?: '—') ?></td>
          <td><?= h($a['unit'] ?: 'each') ?></td>
          <td class="num <?= $oh < 0 ? 'neg' : '' ?>"><?= h(rtrim(rtrim(number_format($oh,2,'.',''),'0'),'.')) ?></td>
          <td class="num"><input class="qty" style="width:90px" type="number" step="0.001" min="0"
                 name="par[<?= (int)$a['id'] ?>]" aria-label="Par level for <?= h($a['name']) ?>"
                 value="<?= $a['par_qty'] !== null ? h(rtrim(rtrim(number_format((float)$a['par_qty'],3,'.',''),'0'),'.')) : '' ?>"></td>
          <td class="num"><input class="qty" style="width:90px" type="number" step="0.001" min="0"
                 name="pack[<?= (int)$a['id'] ?>]" aria-label="Pack size for <?= h($a['name']) ?>"
                 placeholder="<?= h(rtrim(rtrim(number_format((float)$a['pack_size'],2,'.',''),'0'),'.')) ?>"
                 value="<?= $a['shop_pack'] > 0 ? h(rtrim(rtrim(number_format((float)$a['shop_pack'],3,'.',''),'0'),'.')) : '' ?>"></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="actionbar">
    <div class="left">Blank pack size falls back to the article default shown in grey.</div>
    <button class="btn primary">Save par levels</button>
  </div>
</form>
<?php admin_footer();
