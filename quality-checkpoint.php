<?php
/** QCP — record damaged / unsellable articles found during a quality check. */
require_once __DIR__.'/lib.php';
require_login();
$SHOP = current_shop(); $shop = $SHOP['id'];
$date = date('Y-m-d');
$sess = waste_session($shop, $date, 'quality');
$REASONS = ['Damaged in transit','Short date','Wrong temperature','Dropped / spoiled','Packaging fault','Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'reopen' && is_manager()) {
        q("DELETE FROM movements WHERE ref=?", ['QCP#'.$sess['id']]);
        q("UPDATE waste_sessions SET status='open', confirmed_at=NULL WHERE id=?", [$sess['id']]);
        flash('Checkpoint reopened.');
        redirect('quality-checkpoint.php');
    }
    if ($sess['status'] === 'confirmed') redirect('quality-checkpoint.php');

    if ($action === 'add') {
        $qty = (float)($_POST['qty'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $a = resolve_article($_POST['article_id'] ?? 0, $_POST['article_name'] ?? '');
        $aid = $a['id'] ?? 0;
        if (!$a)            flash('Pick an article from the list.', 'err');
        elseif ($qty <= 0)  flash('Enter how many were damaged.', 'err');
        else {
            q("INSERT INTO waste_lines (session_id,article_id,qty,charity,reason,line_value)
               VALUES (?,?,?,0,?,?)
               ON DUPLICATE KEY UPDATE qty=qty+VALUES(qty), line_value=line_value+VALUES(line_value)",
              [$sess['id'], $aid, $qty, $reason ?: 'Other', $qty * (float)$a['cost_price']]);
            flash($a['name'].' recorded.');
        }
        redirect('quality-checkpoint.php');
    }
    if ($action === 'remove') {
        q("DELETE FROM waste_lines WHERE id=? AND session_id=?", [(int)$_POST['line_id'], $sess['id']]);
        flash('Line removed.');
        redirect('quality-checkpoint.php');
    }
    if ($action === 'confirm') {
        q("DELETE FROM movements WHERE ref=?", ['QCP#'.$sess['id']]);
        foreach (all("SELECT * FROM waste_lines WHERE session_id=?", [$sess['id']]) as $l) {
            move($shop, $l['article_id'], $date, MV_WASTE_QCP, -1 * (float)$l['qty'],
                 -1 * (float)$l['line_value'], 'QCP#'.$sess['id']);
        }
        q("UPDATE waste_sessions SET status='confirmed', confirmed_at=NOW() WHERE id=?", [$sess['id']]);
        flash('Quality checkpoint confirmed.');
        redirect('quality-checkpoint.php');
    }
}

$lines = all("SELECT wl.*, a.name, a.code, u.name AS unit
              FROM waste_lines wl JOIN articles a ON a.id=wl.article_id
              LEFT JOIN units u ON u.id=a.unit_id
              WHERE wl.session_id=? ORDER BY wl.id DESC", [$sess['id']]);
$locked = $sess['status'] === 'confirmed';
$total = 0; foreach ($lines as $l) $total += (float)$l['line_value'];

$TITLE = 'Quality checkpoint';
require __DIR__.'/inc/header.php';
?>
<div class="page-head"><div><h1>Quality checkpoint</h1><div class="sub"><?= h(shop_label($SHOP)) ?> · <?= h(date('l, d/m/Y')) ?></div></div></div>

<h2 class="section-title">Damaged and unsellable articles</h2>

<?php if (!$locked): ?>
<div class="card pad">
  <form method="post" class="inline">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div style="flex:2 1 260px">
      <label for="art">Find article</label>
      <input id="art" name="article_name" list="articles" placeholder="Type an article name"
             autocomplete="off" required data-article-input="aid">
      <input type="hidden" name="article_id" id="aid">
      <datalist id="articles">
        <?php foreach (all("SELECT id,code,name FROM articles WHERE active=1 ORDER BY name") as $a): ?>
          <option data-id="<?= (int)$a['id'] ?>" value="<?= h($a['name']) ?>"><?= h($a['code']) ?></option>
        <?php endforeach; ?>
      </datalist>
    </div>
    <div style="flex:1 1 110px">
      <label for="qty">Quantity</label>
      <input id="qty" class="qty" style="width:100%" type="number" step="0.001" min="0.001" name="qty" required>
    </div>
    <div style="flex:1 1 170px">
      <label for="reason">Reason</label>
      <select id="reason" name="reason">
        <?php foreach ($REASONS as $r): ?><option><?= h($r) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div style="flex:0 0 auto" class="btn-row"><?= scan_button('Scan') ?><button class="btn blue" type="submit"><?= icon('plus', 16) ?>Add article</button></div>
  </form>
  <p class="lede" style="margin:12px 0 0">
    Can't find it? Open the <a href="lookup.php">stock catalogue</a> and search there.</p>
</div>
<?php endif; ?>

<div class="card">
  <div class="scroll">
  <table>
    <thead><tr><th>Article</th><th>Unit</th><th>Reason</th>
      <th class="num">Quantity</th><th class="num">Cost</th><th></th></tr></thead>
    <tbody>
      <?php if (!$lines): ?>
        <tr><td colspan="6" class="empty">
          No damaged articles recorded today.</td></tr>
      <?php endif; ?>
      <?php foreach ($lines as $l): ?>
        <tr>
          <td><div class="art"><span class="info" aria-hidden="true">i</span>
            <div><span class="name"><?= h($l['name']) ?></span><div class="code"><?= h($l['code']) ?></div></div></div></td>
          <td><?= h($l['unit'] ?: 'each') ?></td>
          <td><?= h($l['reason']) ?></td>
          <td class="num"><?= h(rtrim(rtrim(number_format((float)$l['qty'],3,'.',''),'0'),'.')) ?></td>
          <td class="num"><?= money($l['line_value']) ?></td>
          <td class="num">
            <?php if (!$locked): ?>
            <form method="post" onsubmit="return confirm('Remove this line?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="line_id" value="<?= (int)$l['id'] ?>">
              <button class="btn sm danger" type="submit">Remove</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<form method="post" class="actionbar">
  <?= csrf_field() ?>
  <div class="left"><?= count($lines) ?> line(s) · <?= money($total) ?> at cost</div>
  <a class="btn" href="index.php">Back</a>
  <?php if ($locked && is_manager()): ?>
    <button class="btn danger" name="action" value="reopen">Reopen checkpoint</button>
  <?php elseif (!$locked && $lines): ?>
    <button class="btn primary" name="action" value="confirm"><?= icon('check', 16) ?>Confirm checkpoint</button>
  <?php endif; ?>
</form>
<?= scanner_ui('pick', ['name' => '#art', 'id' => '#aid', 'qty' => '#qty']) ?>
<?php require __DIR__.'/inc/footer.php';
