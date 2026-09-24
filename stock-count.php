<?php
/** Stock Count — the sheet a shop fills in for the articles due today. */
require_once __DIR__.'/lib.php';
require_login();
$SHOP = current_shop();
$shop = $SHOP['id'];
$date = date('Y-m-d');
$sess = count_session($shop, $date);

/* ---------- build today's list: scheduled articles + anything in negative stock ---------- */
if ($sess['status'] === 'open') {
    $due = array_unique(array_merge(scheduled_article_ids($shop, $date), []));
    $neg = negative_stock_articles($shop);
    foreach ($due as $aid) {
        q("INSERT IGNORE INTO count_lines (session_id,article_id,auto_added) VALUES (?,?,0)", [$sess['id'], $aid]);
    }
    foreach ($neg as $aid) {
        q("INSERT INTO count_lines (session_id,article_id,auto_added) VALUES (?,?,1)
           ON DUPLICATE KEY UPDATE auto_added=1", [$sess['id'], $aid]);
    }
}

/* ---------- actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'add' && $sess['status'] === 'open') {
        $a = resolve_article($_POST['article_id'] ?? 0, $_POST['article_name'] ?? '');
        if ($a) {
            q("INSERT IGNORE INTO count_lines (session_id,article_id,auto_added) VALUES (?,?,0)", [$sess['id'], $a['id']]);
            $add = (float)($_POST['qty'] ?? 0);
            if ($add > 0) {   // came from a scan: count the item we just added
                q("UPDATE count_lines SET qty=COALESCE(qty,0)+? WHERE session_id=? AND article_id=?", [$add, $sess['id'], $a['id']]);
                flash($a['name'].' added to today\'s count with '.fmt_qty($add).' counted.');
            } else {
                flash('Item added to today\'s count.');
            }
        } else {
            flash('Pick an article from the list first.', 'err');
        }
        redirect('stock-count.php');
    }

    if ($action === 'reopen' && is_manager()) {
        q("DELETE FROM movements WHERE ref=?", ['COUNT#'.$sess['id']]);
        q("UPDATE count_sessions SET status='open', confirmed_at=NULL WHERE id=?", [$sess['id']]);
        flash('Count reopened. The stock adjustments it made have been reversed.');
        redirect('stock-count.php');
    }

    if ($sess['status'] === 'open') {
        foreach (($_POST['qty'] ?? []) as $lineId => $val) {
            $val = trim($val);
            q("UPDATE count_lines SET qty=? WHERE id=? AND session_id=?",
              [$val === '' ? null : (float)$val, (int)$lineId, $sess['id']]);
        }
        if ($action === 'confirm') {
            $blank = (int) col("SELECT COUNT(*) FROM count_lines WHERE session_id=? AND qty IS NULL", [$sess['id']]);
            if ($blank) {
                flash($blank.' line(s) still have no quantity. Enter a number for every article, or remove the line.', 'err');
                redirect('stock-count.php');
            }
            q("DELETE FROM movements WHERE ref=?", ['COUNT#'.$sess['id']]);
            foreach (all("SELECT * FROM count_lines WHERE session_id=?", [$sess['id']]) as $l) {
                $a   = article($l['article_id']);
                $exp = on_hand($shop, $l['article_id'], $date);
                $var = (float)$l['qty'] - $exp;
                q("UPDATE count_lines SET expected_qty=?, variance_qty=?, variance_value=? WHERE id=?",
                  [$exp, $var, $var * (float)$a['cost_price'], $l['id']]);
                if (abs($var) > 0.0001) {
                    move($shop, $l['article_id'], $date, MV_COUNT_ADJ, $var,
                         $var * (float)$a['cost_price'], 'COUNT#'.$sess['id']);
                }
            }
            q("UPDATE count_sessions SET status='confirmed', confirmed_at=NOW() WHERE id=?", [$sess['id']]);
            flash('Stock count confirmed. Variance has been posted to stock loss.');
        } else {
            flash('Saved. You can come back and finish this later today.');
        }
        redirect('stock-count.php');
    }
}

/* ---------- read ---------- */
$lines = all("SELECT cl.*, a.code, a.name, a.blocked, u.name AS unit
              FROM count_lines cl
              JOIN articles a ON a.id=cl.article_id
              LEFT JOIN units u ON u.id=a.unit_id
              WHERE cl.session_id=?
              ORDER BY cl.auto_added DESC, a.name", [$sess['id']]);
$locked  = $sess['status'] === 'confirmed';
$onhand  = on_hand_map($shop, $date);
$hasAuto = false; foreach ($lines as $l) if ($l['auto_added']) $hasAuto = true;

$TITLE = 'Stock count';
require __DIR__.'/inc/header.php';
?>
<div class="page-head"><div><h1>Stock count</h1><div class="sub"><?= h(shop_label($SHOP)) ?></div></div></div>

<?php if ($hasAuto): ?>
<div class="notice"><?= icon('alert', 16) ?>
  <div>Any item shown in purple was in negative stock last night and has been added to today's count automatically.</div>
</div>
<?php endif; ?>

<h2 class="section-title">Today's count</h2>

<label class="sr" for="find" style="display:none">Search</label>
<div class="toolbar">
  <input id="find" class="search" data-filter placeholder="Search" autocomplete="off" aria-label="Search articles">
  <?php if (!$locked): ?><?= scan_button('Scan to count') ?><?php endif; ?>
</div>

<form method="post" data-dirty data-draft="count-<?= (int)$shop ?>-<?= h($date) ?>" data-locked="<?= $locked ? 1 : 0 ?>">
  <?= csrf_field() ?>
  <div class="card">
    <div class="datebar"><?= h(date('l, d/m/Y', strtotime($date))) ?></div>
    <div class="scroll">
    <table>
      <thead><tr>
        <th>Article</th><th class="col-unit">Unit</th>
        <?php if ($locked): ?><th class="num">Expected</th><?php endif; ?>
        <th class="num">Quantity</th>
        <?php if ($locked): ?><th class="num">Variance</th><?php endif; ?>
      </tr></thead>
      <tbody>
      <?php if (!$lines): ?>
        <tr><td colspan="5" class="empty">
          Nothing is scheduled to be counted today. Use <strong>Add count item</strong> below to count something anyway.</td></tr>
      <?php endif; ?>
      <?php foreach ($lines as $l): ?>
        <tr class="<?= $l['auto_added'] ? 'auto' : '' ?>" data-row="<?= h($l['name'].' '.$l['code']) ?>">
          <td><div class="art">
            <a class="info" href="lookup.php?a=<?= (int)$l['article_id'] ?>" title="Article details">i</a>
            <div><span class="name"><?= h($l['name']) ?></span>
              <?php if ($l['auto_added']): ?>
                <span class="tag todo">negative <?= h(number_format((float)($onhand[$l['article_id']] ?? 0), 0)) ?></span>
              <?php endif; ?>
              <div class="code"><?= h($l['code']) ?><span class="unit-sm"> · <?= h($l['unit'] ?: 'each') ?></span></div></div>
          </div></td>
          <td class="col-unit"><?= h($l['unit'] ?: 'each') ?></td>
          <?php if ($locked): ?><td class="num"><?= h(rtrim(rtrim(number_format((float)$l['expected_qty'],2,'.',''),'0'),'.')) ?></td><?php endif; ?>
          <td class="num">
            <input class="qty" type="number" step="0.001" inputmode="decimal" data-article="<?= (int)$l['article_id'] ?>"
                   name="qty[<?= (int)$l['id'] ?>]" value="<?= $l['qty'] === null ? '' : h(rtrim(rtrim(number_format((float)$l['qty'],3,'.',''),'0'),'.')) ?>"
                   <?= $locked ? 'readonly' : '' ?> aria-label="Quantity for <?= h($l['name']) ?>">
          </td>
          <?php if ($locked): ?>
            <td class="num <?= (float)$l['variance_value'] < 0 ? 'neg' : 'pos' ?>">
              <?= h(rtrim(rtrim(number_format((float)$l['variance_qty'],2,'.',''),'0'),'.')) ?>
              <span class="muted">(<?= money($l['variance_value']) ?>)</span>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <div class="actionbar">
    <div class="left">
      <?php if ($locked): ?>
        Confirmed <?= h(date('H:i', strtotime($sess['confirmed_at']))) ?>
      <?php else: ?>
        <?= count(array_filter($lines, fn($l) => $l['qty'] !== null)) ?> of <?= count($lines) ?> counted
      <?php endif; ?>
    </div>
    <a class="btn" href="index.php">Back</a>
    <?php if ($locked && is_manager()): ?>
      <button class="btn danger" name="action" value="reopen"
              onclick="return confirm('Reopen this count? The stock adjustments it posted will be reversed.')">Reopen count</button>
    <?php elseif (!$locked): ?>
      <button class="btn" name="action" value="save"><?= icon('save', 16) ?>Save draft</button>
      <button class="btn primary" name="action" value="confirm"><?= icon('check', 16) ?>Confirm count</button>
    <?php endif; ?>
  </div>
</form>

<?php if (!$locked): ?>
<div class="card pad no-print">
  <h3 class="card-title">Add count item</h3>
  <form method="post" class="inline" id="addForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add"><input type="hidden" name="qty" value="">
    <div style="flex:1 1 260px">
      <label for="art">Article</label>
      <input id="art" name="article_name" list="articles" placeholder="Type an article name"
             autocomplete="off" data-article-input="aid">
      <input type="hidden" name="article_id" id="aid">
      <datalist id="articles">
        <?php foreach (all("SELECT id,code,name FROM articles WHERE active=1 ORDER BY name") as $a): ?>
          <option data-id="<?= (int)$a['id'] ?>" value="<?= h($a['name']) ?>"><?= h($a['code']) ?></option>
        <?php endforeach; ?>
      </datalist>
    </div>
    <div style="flex:0 0 auto"><button class="btn blue" type="submit"><?= icon('plus', 16) ?>Add count item</button></div>
  </form>
</div>
<?php endif; ?>
<?= scanner_ui('tally', ['add_form' => '#addForm']) ?>
<?php require __DIR__.'/inc/footer.php';
