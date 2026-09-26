<?php
/**
 * Shared waste sheet used by ingredient-waste.php and product-waste.php.
 * Expects $TYPE ('ingredient'|'stales'), $TITLE, $HEADING, $KIND ('ingredient'|'product'),
 * $CHARITY (bool), $GROUPED (bool).
 */
require_once __DIR__.'/../lib.php';
require_login();
$SHOP = current_shop();
$shop = $SHOP['id'];

$date = $_GET['d'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
if ($date > date('Y-m-d')) $date = date('Y-m-d');
$self = basename($_SERVER['SCRIPT_NAME']);
$sess = waste_session($shop, $date, $TYPE);
$mvType = $TYPE === 'ingredient' ? MV_WASTE_ING : MV_WASTE_STA;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'reopen' && is_manager()) {
        q("DELETE FROM movements WHERE ref=?", ['WASTE#'.$sess['id']]);
        q("UPDATE waste_sessions SET status='open', confirmed_at=NULL WHERE id=?", [$sess['id']]);
        flash('Sheet reopened. The stock it wrote off has been put back.');
        redirect($self.'?d='.$date);
    }

    if ($sess['status'] === 'open') {
        $charity = $_POST['charity'] ?? [];
        foreach (($_POST['qty'] ?? []) as $aid => $val) {
            $aid = (int)$aid; $val = trim($val);
            $a = article($aid); if (!$a) continue;
            if ($val === '' || (float)$val == 0) {
                q("DELETE FROM waste_lines WHERE session_id=? AND article_id=? AND reason=''", [$sess['id'], $aid]);
                continue;
            }
            $qty = (float)$val;
            q("INSERT INTO waste_lines (session_id,article_id,qty,charity,reason,line_value)
               VALUES (?,?,?,?,'',?)
               ON DUPLICATE KEY UPDATE qty=VALUES(qty), charity=VALUES(charity), line_value=VALUES(line_value)",
              [$sess['id'], $aid, $qty, !empty($charity[$aid]) ? 1 : 0, $qty * (float)$a['cost_price']]);
        }
        if ($action === 'confirm') {
            q("DELETE FROM movements WHERE ref=?", ['WASTE#'.$sess['id']]);
            foreach (all("SELECT * FROM waste_lines WHERE session_id=?", [$sess['id']]) as $l) {
                move($shop, $l['article_id'], $date, $mvType, -1 * (float)$l['qty'],
                     -1 * (float)$l['line_value'], 'WASTE#'.$sess['id']);
            }
            q("UPDATE waste_sessions SET status='confirmed', confirmed_at=NOW() WHERE id=?", [$sess['id']]);
            flash('Waste confirmed and taken off stock.');
        } else {
            flash('Saved.');
        }
        redirect($self.'?d='.$date);
    }
}

$locked = $sess['status'] === 'confirmed';
$saved  = [];
foreach (all("SELECT * FROM waste_lines WHERE session_id=? AND reason=''", [$sess['id']]) as $l) {
    $saved[(int)$l['article_id']] = $l;
}
$rows = all("SELECT a.*, u.name AS unit, c.name AS cat, c.sort
             FROM articles a
             LEFT JOIN units u ON u.id=a.unit_id
             LEFT JOIN categories c ON c.id=a.category_id
             WHERE a.active=1 AND a.kind=?
             ORDER BY c.sort, c.name, a.name", [$KIND]);

$groups = [];
foreach ($rows as $r) $groups[$r['cat'] ?: 'Other'][] = $r;
if (!$GROUPED) $groups = ['' => $rows];

$total = 0; foreach ($saved as $l) $total += (float)$l['line_value'];

require __DIR__.'/header.php';
?>
<div class="page-head"><div><h1><?= h($TITLE) ?></h1><div class="sub"><?= h(shop_label($SHOP)) ?></div></div></div>

<h2 class="section-title"><?= h($HEADING) ?></h2>

<div class="toolbar">
  <input class="search" data-filter placeholder="Search" autocomplete="off" aria-label="Search articles">
  <?php if (!$locked): ?><?= scan_button('Scan waste') ?><?php endif; ?>
</div>

<form method="post" data-dirty data-draft="<?= h($TYPE) ?>-<?= (int)$shop ?>-<?= h($date) ?>" data-locked="<?= $locked ? 1 : 0 ?>">
  <?= csrf_field() ?>
  <div class="card">
    <div class="datebar">
      <a href="<?= h($self) ?>?d=<?= h(date('Y-m-d', strtotime($date.' -1 day'))) ?>" aria-label="Previous day"><?= icon('left', 18) ?></a>
      <span><?= h(date('l, d/m/Y', strtotime($date))) ?></span>
      <?php if ($date < date('Y-m-d')): ?>
        <a href="<?= h($self) ?>?d=<?= h(date('Y-m-d', strtotime($date.' +1 day'))) ?>" aria-label="Next day"><?= icon('right', 18) ?></a>
      <?php else: ?><span class="gap"></span><?php endif; ?>
    </div>

    <?php $gi = 0; foreach ($groups as $gname => $items): $gi++; ?>
      <?php if ($GROUPED): ?>
        <div class="group-head" data-group="g<?= $gi ?>"><?= icon('down', 16) ?> <?= h($gname) ?> <span class="count"><?= count($items) ?></span></div>
      <?php endif; ?>
      <div class="scroll">
      <table class="fixed">
        <colgroup><col><col class="col-unit" style="width:16%"><col style="width:140px">
          <?php if ($CHARITY): ?><col style="width:96px"><?php endif; ?>
          <?php if ($locked): ?><col style="width:110px"><?php endif; ?></colgroup>
        <thead><tr>
          <th>Article</th><th class="col-unit">Unit</th><th class="num">Quantity</th>
          <?php if ($CHARITY): ?><th class="num">Charity?</th><?php endif; ?>
          <?php if ($locked): ?><th class="num">Cost</th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($items as $a): $l = $saved[$a['id']] ?? null; ?>
          <tr data-row="<?= h($a['name'].' '.$a['code']) ?>" data-in="g<?= $gi ?>">
            <td><div class="art">
              <a class="info" href="lookup.php?a=<?= (int)$a['id'] ?>" title="Article details">i</a>
              <div><span class="name"><?= h($a['name']) ?></span><div class="code"><?= h($a['code']) ?><span class="unit-sm"> · <?= h($a['unit'] ?: 'each') ?></span></div></div>
            </div></td>
            <td class="col-unit"><?= h($a['unit'] ?: 'each') ?></td>
            <td class="num">
              <input class="qty" type="number" step="0.001" min="0" inputmode="decimal" data-article="<?= (int)$a['id'] ?>"
                     name="qty[<?= (int)$a['id'] ?>]"
                     value="<?= $l ? h(rtrim(rtrim(number_format((float)$l['qty'],3,'.',''),'0'),'.')) : '' ?>"
                     <?= $locked ? 'readonly' : '' ?> aria-label="Quantity wasted, <?= h($a['name']) ?>">
            </td>
            <?php if ($CHARITY): ?>
              <td class="num">
                <?php if ($a['charity_ok']): ?>
                  <input class="check" type="checkbox" name="charity[<?= (int)$a['id'] ?>]" value="1"
                         <?= $l && $l['charity'] ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>
                         aria-label="Given to charity, <?= h($a['name']) ?>">
                <?php else: ?><span class="muted">—</span><?php endif; ?>
              </td>
            <?php endif; ?>
            <?php if ($locked): ?>
              <td class="num"><?= $l ? money($l['line_value']) : '' ?></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="actionbar">
    <div class="left">
      <?= count($saved) ?> line(s) · <?= money($total) ?> at cost
      <?= $locked ? ' · confirmed '.h(date('H:i', strtotime($sess['confirmed_at']))) : '' ?>
    </div>
    <a class="btn" href="index.php">Back</a>
    <?php if ($locked && is_manager()): ?>
      <button class="btn danger" name="action" value="reopen"
              onclick="return confirm('Reopen this sheet? The stock it wrote off will be put back.')">Reopen sheet</button>
    <?php elseif (!$locked): ?>
      <button class="btn" name="action" value="save"><?= icon('save', 16) ?>Save draft</button>
      <button class="btn primary" name="action" value="confirm"><?= icon('check', 16) ?>Confirm waste</button>
    <?php endif; ?>
  </div>
</form>
<?= scanner_ui('tally') ?>
<?php require __DIR__.'/footer.php';
