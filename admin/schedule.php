<?php
/** Count schedule — which days of the week each article is counted, per shop or for all shops. */
require __DIR__.'/inc.php';
$DAYS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
$shopId = (int)($_GET['shop'] ?? 0);   // 0 = applies to every shop

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $shopId = (int)($_POST['shop'] ?? 0);
    $sel = $_POST['d'] ?? [];
    if (($_POST['action'] ?? '') === 'bulk') {
        $ids  = array_map('intval', $_POST['pick'] ?? []);
        $days = array_map('intval', $_POST['bulkday'] ?? []);
        foreach ($ids as $aid) {
            $vals = [];
            for ($i = 0; $i < 7; $i++) $vals[] = in_array($i, $days, true) ? 1 : 0;
            q("INSERT INTO schedules (article_id,shop_id,d0,d1,d2,d3,d4,d5,d6) VALUES (?,?,?,?,?,?,?,?,?)
               ON DUPLICATE KEY UPDATE d0=VALUES(d0),d1=VALUES(d1),d2=VALUES(d2),d3=VALUES(d3),
                                       d4=VALUES(d4),d5=VALUES(d5),d6=VALUES(d6)",
              array_merge([$aid, $shopId ?: null], $vals));
        }
        flash(count($ids).' article(s) rescheduled.');
    } else {
        foreach (all("SELECT id FROM articles") as $a) {
            $aid = (int)$a['id'];
            $days = $sel[$aid] ?? [];
            $vals = [];
            for ($i = 0; $i < 7; $i++) $vals[] = isset($days[$i]) ? 1 : 0;
            if (array_sum($vals) === 0) {
                q("DELETE FROM schedules WHERE article_id=? AND ".($shopId ? "shop_id=?" : "shop_id IS NULL"),
                  $shopId ? [$aid, $shopId] : [$aid]);
                continue;
            }
            q("INSERT INTO schedules (article_id,shop_id,d0,d1,d2,d3,d4,d5,d6) VALUES (?,?,?,?,?,?,?,?,?)
               ON DUPLICATE KEY UPDATE d0=VALUES(d0),d1=VALUES(d1),d2=VALUES(d2),d3=VALUES(d3),
                                       d4=VALUES(d4),d5=VALUES(d5),d6=VALUES(d6)",
              array_merge([$aid, $shopId ?: null], $vals));
        }
        flash('Schedule saved.');
    }
    redirect('schedule.php?shop='.$shopId);
}

$sched = [];
foreach (all("SELECT * FROM schedules WHERE ".($shopId ? "shop_id=?" : "shop_id IS NULL"),
             $shopId ? [$shopId] : []) as $s) $sched[(int)$s['article_id']] = $s;
$rows = all("SELECT a.*, c.name AS cat FROM articles a LEFT JOIN categories c ON c.id=a.category_id
             WHERE a.active=1 ORDER BY a.kind, a.name");
admin_header('Count schedule', 'schedule');
?>
<h1 class="admin-title">Count schedule</h1>
<p class="lede">Tick the days an article appears on the stock count sheet. Some lines are counted once a week,
  others two or three times — set each one as you need it. Anything in negative stock is added automatically,
  scheduled or not.</p>

<form method="get" class="card pad inline">
  <div style="flex:1 1 280px">
    <label for="shop">Schedule applies to</label>
    <select id="shop" name="shop" onchange="this.form.submit()">
      <option value="0">All shops (default)</option>
      <?php foreach (all("SELECT * FROM shops WHERE active=1 ORDER BY code") as $s): ?>
        <option value="<?= (int)$s['id'] ?>" <?= $shopId == $s['id'] ? 'selected' : '' ?>>
          <?= h($s['code'].' '.$s['name']) ?> only</option>
      <?php endforeach; ?>
    </select>
  </div>
</form>

<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="shop" value="<?= (int)$shopId ?>">
  <div class="card pad no-print">
    <strong style="font-size:13px">Bulk set</strong>
    <p class="lede">Tick articles in the table, choose days, then apply.</p>
    <div class="days">
      <?php foreach ($DAYS as $i => $d): ?>
        <label><input type="checkbox" name="bulkday[]" value="<?= $i ?>"> <?= h($d) ?></label>
      <?php endforeach; ?>
      <button class="btn blue sm" name="action" value="bulk">Apply to ticked articles</button>
    </div>
  </div>

  <input class="search" data-filter placeholder="Search articles" aria-label="Search articles">
  <div class="card scroll">
    <table>
      <thead><tr><th></th><th>Article</th><th>Category</th>
        <?php foreach ($DAYS as $d): ?><th class="num"><?= h($d) ?></th><?php endforeach; ?>
        <th class="num">Per week</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $a): $s = $sched[$a['id']] ?? null;
        $n = 0; for ($i = 0; $i < 7; $i++) $n += $s ? (int)$s['d'.$i] : 0; ?>
        <tr data-row="<?= h($a['code'].' '.$a['name'].' '.$a['cat']) ?>">
          <td><input class="check" type="checkbox" name="pick[]" value="<?= (int)$a['id'] ?>"
                     aria-label="Select <?= h($a['name']) ?>"></td>
          <td><?= h($a['name']) ?><div class="code muted"><?= h($a['code']) ?></div></td>
          <td class="muted"><?= h($a['cat']) ?></td>
          <?php for ($i = 0; $i < 7; $i++): ?>
            <td class="num"><input class="check" type="checkbox" name="d[<?= (int)$a['id'] ?>][<?= $i ?>]" value="1"
              <?= $s && $s['d'.$i] ? 'checked' : '' ?> aria-label="<?= h($DAYS[$i].' '.$a['name']) ?>"></td>
          <?php endfor; ?>
          <td class="num"><?= $n ?: '<span class="muted">—</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="actionbar">
    <div class="left">Counting more often gives tighter variance but costs shop time.</div>
    <button class="btn primary" name="action" value="save">Save schedule</button>
  </div>
</form>
<?php admin_footer();
