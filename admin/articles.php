<?php
require __DIR__.'/inc.php';
$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? 'save';
    $id = (int)($_POST['id'] ?? 0);

    if ($a === 'toggle') {
        q("UPDATE articles SET active = 1 - active WHERE id=?", [$id]);
        flash('Article updated.');
        redirect('articles.php');
    }
    if ($a === 'save') {
        $f = [
            trim($_POST['code'] ?? ''), trim($_POST['name'] ?? ''),
            ((int)($_POST['category_id'] ?? 0)) ?: null, ((int)($_POST['unit_id'] ?? 0)) ?: null,
            ($_POST['kind'] ?? 'product') === 'ingredient' ? 'ingredient' : 'product',
            (float)($_POST['cost_price'] ?? 0), (float)($_POST['sell_price'] ?? 0),
            ((int)($_POST['supplier_id'] ?? 0)) ?: null, trim($_POST['supplier_ref'] ?? ''),
            max(0.001, (float)($_POST['pack_size'] ?? 1)), (float)($_POST['pack_cost'] ?? 0),
            isset($_POST['charity_ok']) ? 1 : 0, isset($_POST['blocked']) ? 1 : 0,
            trim($_POST['notes'] ?? ''),
        ];
        if ($f[0] === '' || $f[1] === '') {
            flash('An article needs a code and a name.', 'err');
            redirect('articles.php'.($id ? '?edit='.$id : ''));
        }
        try {
            if ($id) {
                q("UPDATE articles SET code=?,name=?,category_id=?,unit_id=?,kind=?,cost_price=?,sell_price=?,
                   supplier_id=?,supplier_ref=?,pack_size=?,pack_cost=?,charity_ok=?,blocked=?,notes=?
                   WHERE id=?", array_merge($f, [$id]));
                flash('Article saved.');
            } else {
                q("INSERT INTO articles (code,name,category_id,unit_id,kind,cost_price,sell_price,
                   supplier_id,supplier_ref,pack_size,pack_cost,charity_ok,blocked,notes)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)", $f);
                flash('Article added.');
            }
        } catch (PDOException $e) {
            flash('That article code is already used.', 'err');
        }
        redirect('articles.php');
    }
}
if (!empty($_GET['edit'])) $edit = one("SELECT * FROM articles WHERE id=?", [(int)$_GET['edit']]);

$cats  = all("SELECT * FROM categories ORDER BY sort, name");
$sups  = all("SELECT * FROM suppliers ORDER BY name");
$units = all("SELECT * FROM units ORDER BY name");
$rows  = all("SELECT a.*, c.name AS cat, u.name AS unit, sp.name AS supplier FROM articles a
              LEFT JOIN categories c ON c.id=a.category_id
              LEFT JOIN units u ON u.id=a.unit_id
              LEFT JOIN suppliers sp ON sp.id=a.supplier_id ORDER BY a.kind, a.name");
$bcount = [];
foreach (all("SELECT article_id, COUNT(*) n FROM barcodes GROUP BY article_id") as $b) $bcount[(int)$b['article_id']] = (int)$b['n'];
admin_header('Articles', 'articles');
?>
<h1 class="admin-title"><?= $edit ? 'Edit article' : 'Add an article' ?></h1>
<div class="card pad">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="grid g3">
      <div><label for="code">Code</label>
        <input id="code" name="code" required value="<?= h($edit['code'] ?? '') ?>"></div>
      <div style="grid-column:span 2"><label for="name">Name</label>
        <input id="name" name="name" required value="<?= h($edit['name'] ?? '') ?>"></div>
      <div><label for="kind">Type</label>
        <select id="kind" name="kind">
          <option value="product" <?= ($edit['kind'] ?? '') === 'product' ? 'selected' : '' ?>>Product we sell</option>
          <option value="ingredient" <?= ($edit['kind'] ?? '') === 'ingredient' ? 'selected' : '' ?>>Ingredient</option>
        </select></div>
      <div><label for="category_id">Category</label>
        <select id="category_id" name="category_id">
          <option value="">—</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($edit['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
              <?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label for="unit_id">Unit of measure</label>
        <select id="unit_id" name="unit_id">
          <option value="">—</option>
          <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= ($edit['unit_id'] ?? 0) == $u['id'] ? 'selected' : '' ?>>
              <?= h($u['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label for="cost_price">Cost price (£)</label>
        <input id="cost_price" type="number" step="0.0001" min="0" name="cost_price" value="<?= h($edit['cost_price'] ?? '0') ?>"></div>
      <div><label for="sell_price">Sell price ex VAT (£)</label>
        <input id="sell_price" type="number" step="0.0001" min="0" name="sell_price" value="<?= h($edit['sell_price'] ?? '0') ?>"></div>
      <div><label for="supplier_id">Supplier</label>
        <select id="supplier_id" name="supplier_id">
          <option value="">—</option>
          <?php foreach ($sups as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= ($edit['supplier_id'] ?? 0) == $s['id'] ? 'selected' : '' ?>>
              <?= h($s['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label for="supplier_ref">Supplier's code for it</label>
        <input id="supplier_ref" name="supplier_ref" value="<?= h($edit['supplier_ref'] ?? '') ?>"></div>
      <div><label for="pack_size">Units in a pack or case</label>
        <input id="pack_size" type="number" step="0.001" min="0.001" name="pack_size" value="<?= h($edit['pack_size'] ?? 1) ?>"></div>
      <div><label for="pack_cost">Cost of a full pack</label>
        <input id="pack_cost" type="number" step="0.0001" min="0" name="pack_cost" value="<?= h($edit['pack_cost'] ?? 0) ?>"></div>
      <div><label for="notes">Notes</label>
        <input id="notes" name="notes" value="<?= h($edit['notes'] ?? '') ?>"></div>
    </div>
    <div class="days" style="margin-top:12px">
      <label><input type="checkbox" name="charity_ok" value="1" <?= !isset($edit['charity_ok']) || $edit['charity_ok'] ? 'checked' : '' ?>> Can go to charity</label>
      <label><input type="checkbox" name="blocked" value="1" <?= !empty($edit['blocked']) ? 'checked' : '' ?>> Blocked — do not order or sell</label>
    </div>
    <div style="margin-top:14px;display:flex;gap:8px">
      <button class="btn primary" type="submit"><?= $edit ? 'Save article' : 'Add article' ?></button>
      <?php if ($edit): ?><a class="btn" href="articles.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<h2 class="section-title">Catalogue (<?= count($rows) ?>)</h2>
<input class="search" data-filter placeholder="Search articles" aria-label="Search articles">
<div class="card scroll">
  <table>
    <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Category</th><th>Unit</th>
      <th>Supplier</th><th class="num">Pack</th><th class="num">Barcodes</th>
      <th class="num">Cost</th><th class="num">Sell</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr data-row="<?= h($r['code'].' '.$r['name'].' '.$r['cat'].' '.$r['supplier']) ?>" class="<?= $r['blocked'] ? 'blocked' : '' ?>">
        <td class="muted"><?= h($r['code']) ?></td>
        <td><span class="name"><?= h($r['name']) ?></span></td>
        <td><?= h(ucfirst($r['kind'])) ?></td>
        <td class="muted"><?= h($r['cat']) ?></td>
        <td><?= h($r['unit']) ?></td>
        <td class="muted"><?= h($r['supplier'] ?: '—') ?></td>
        <td class="num"><?= h(rtrim(rtrim(number_format((float)$r['pack_size'],2,'.',''),'0'),'.')) ?></td>
        <td class="num"><a href="barcodes.php?article=<?= (int)$r['id'] ?>"><?= $bcount[$r['id']] ?? 0 ?></a></td>
        <td class="num"><?= money($r['cost_price']) ?></td>
        <td class="num"><?= money($r['sell_price']) ?></td>
        <td><?= $r['active'] ? '<span class="tag ok">Active</span>' : '<span class="tag">Hidden</span>' ?>
            <?= $r['blocked'] ? '<span class="tag todo">Blocked</span>' : '' ?></td>
        <td class="num" style="white-space:nowrap">
          <a class="btn sm" href="articles.php?edit=<?= (int)$r['id'] ?>">Edit</a>
          <form method="post" style="display:inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn sm"><?= $r['active'] ? 'Hide' : 'Show' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_footer();
