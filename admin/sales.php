<?php
/** Sales — the figures the loss report measures against, plus a sales import. */
require __DIR__.'/inc.php';
$shopId = (int)($_GET['shop'] ?? 0) ?: (int)col("SELECT id FROM shops WHERE active=1 ORDER BY code LIMIT 1");
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $shopId = (int)($_POST['shop_id'] ?? $shopId);
    $a = $_POST['action'] ?? '';

    if ($a === 'net_sales') {
        $d = $_POST['sales_date'] ?? date('Y-m-d');
        q("INSERT INTO sales_days (shop_id,sales_date,net_sales) VALUES (?,?,?)
           ON DUPLICATE KEY UPDATE net_sales=VALUES(net_sales)", [$shopId, $d, (float)($_POST['net_sales'] ?? 0)]);
        flash('Daily net sales saved.');
        redirect('sales.php?shop='.$shopId);
    }

    if ($a === 'import') {
        $raw = trim($_POST['csv'] ?? '');
        $ok = 0; $bad = []; $line = 0;
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $row) {
            $line++; $row = trim($row);
            if ($row === '') continue;
            $p = array_map('trim', str_getcsv($row));
            if (strtolower($p[0] ?? '') === 'date') continue;             // header row
            if (count($p) < 3) { $bad[] = "line $line: needs date, code, qty"; continue; }
            [$d, $code, $qty] = $p;
            $d = date('Y-m-d', strtotime($d));
            $art = one("SELECT * FROM articles WHERE code=?", [$code]);
            if (!$art)      { $bad[] = "line $line: no article with code $code"; continue; }
            if (!is_numeric($qty)) { $bad[] = "line $line: qty is not a number"; continue; }
            $qty = (float)$qty;
            $net = isset($p[3]) && is_numeric($p[3]) ? (float)$p[3] : $qty * (float)$art['sell_price'];
            q("DELETE FROM movements WHERE shop_id=? AND article_id=? AND mv_date=? AND mv_type=?",
              [$shopId, $art['id'], $d, MV_SALE]);
            move($shopId, $art['id'], $d, MV_SALE, -$qty, -$qty * (float)$art['cost_price'], 'SALES IMPORT');
            q("INSERT INTO sales_days (shop_id,sales_date,net_sales) VALUES (?,?,?)
               ON DUPLICATE KEY UPDATE net_sales=net_sales+VALUES(net_sales)", [$shopId, $d, 0]);
            $ok++;
        }
        $report = ['ok' => $ok, 'bad' => $bad];
        flash($ok.' sales line'.($ok === 1 ? '' : 's').' imported.'.($bad ? ' '.count($bad).' skipped.' : ''), $bad ? 'warn' : 'ok');
    }
}

$days = all("SELECT * FROM sales_days WHERE shop_id=? ORDER BY sales_date DESC LIMIT 30", [$shopId]);
admin_header('Sales', 'sales');
?>
<h1 class="admin-title">Sales</h1>
<p class="lede">Loss is reported as a percentage of net sales (sales excluding VAT). Enter the daily figure by hand,
  or import sales lines so expected stock moves down as things sell.</p>

<form method="get" class="card pad inline">
  <div style="flex:1 1 280px"><label for="shop">Shop</label>
    <select id="shop" name="shop" onchange="this.form.submit()">
      <?php foreach (all("SELECT * FROM shops ORDER BY code") as $s): ?>
        <option value="<?= (int)$s['id'] ?>" <?= $shopId == $s['id'] ? 'selected' : '' ?>>
          <?= h($s['code'].' '.$s['name']) ?></option>
      <?php endforeach; ?>
    </select></div>
</form>

<div class="two-col">
  <div>
    <div class="card pad">
      <h2 class="card-title">Daily net sales</h2>
      <form method="post" class="inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="net_sales">
        <input type="hidden" name="shop_id" value="<?= (int)$shopId ?>">
        <div style="flex:1 1 150px"><label for="sd">Date</label>
          <input id="sd" type="date" name="sales_date" value="<?= h(date('Y-m-d')) ?>" max="<?= h(date('Y-m-d')) ?>"></div>
        <div style="flex:1 1 150px"><label for="net_sales">Net sales ex VAT (<?= h(APP_CCY) ?>)</label>
          <input id="net_sales" type="number" step="0.01" min="0" name="net_sales" required></div>
        <div style="flex:0 0 auto"><button class="btn primary">Save</button></div>
      </form>
    </div>
    <div class="card scroll">
      <table><thead><tr><th>Date</th><th class="num">Net sales</th></tr></thead><tbody>
        <?php if (!$days): ?><tr><td colspan="2" class="empty">
          No figures entered yet.</td></tr><?php endif; ?>
        <?php foreach ($days as $d): ?>
          <tr><td><?= h(date('D d/m/Y', strtotime($d['sales_date']))) ?></td>
            <td class="num"><?= money($d['net_sales']) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table>
    </div>
  </div>

  <div>
    <div class="card pad">
      <h2 class="card-title">Import sales lines</h2>
      <p class="lede">One line per row: <code>date, article code, quantity sold, net value</code>.
        Net value is optional — it falls back to the article's sell price. Re-importing a day replaces that day's lines.</p>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="import">
        <input type="hidden" name="shop_id" value="<?= (int)$shopId ?>">
        <label for="csv">Rows</label>
        <textarea id="csv" name="csv" rows="9" placeholder="2026-09-15,P2001,84,105.00
2026-09-15,P1001,12,26.40"></textarea>
        <div style="margin-top:12px"><button class="btn primary">Import sales</button></div>
      </form>
      <?php if ($report && $report['bad']): ?>
        <div class="msg warn" style="margin-top:12px">
          <strong>Skipped rows</strong>
          <ul style="margin:6px 0 0 18px;padding:0">
            <?php foreach (array_slice($report['bad'], 0, 12) as $b): ?><li><?= h($b) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php admin_footer();
