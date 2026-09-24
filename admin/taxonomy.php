<?php
require __DIR__.'/inc.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? '';
    if ($a === 'cat_save') {
        $id = (int)($_POST['id'] ?? 0);
        $n = trim($_POST['name'] ?? ''); $k = ($_POST['kind'] ?? 'product') === 'ingredient' ? 'ingredient' : 'product';
        $sort = (int)($_POST['sort'] ?? 0);
        if ($n === '') flash('Give the category a name.', 'err');
        elseif ($id) { q("UPDATE categories SET name=?,kind=?,sort=? WHERE id=?", [$n,$k,$sort,$id]); flash('Category saved.'); }
        else { q("INSERT INTO categories (name,kind,sort) VALUES (?,?,?)", [$n,$k,$sort]); flash('Category added.'); }
    }
    if ($a === 'cat_del') {
        $id = (int)$_POST['id'];
        if (col("SELECT COUNT(*) FROM articles WHERE category_id=?", [$id])) flash('That category still has articles in it.', 'err');
        else { q("DELETE FROM categories WHERE id=?", [$id]); flash('Category deleted.'); }
    }
    if ($a === 'unit_save') {
        $n = trim($_POST['name'] ?? '');
        if ($n === '') flash('Give the unit a name.', 'err');
        else { q("INSERT IGNORE INTO units (name) VALUES (?)", [$n]); flash('Unit added.'); }
    }
    if ($a === 'unit_del') {
        $id = (int)$_POST['id'];
        if (col("SELECT COUNT(*) FROM articles WHERE unit_id=?", [$id])) flash('That unit is still in use.', 'err');
        else { q("DELETE FROM units WHERE id=?", [$id]); flash('Unit deleted.'); }
    }
    redirect('taxonomy.php');
}
$edit = !empty($_GET['edit']) ? one("SELECT * FROM categories WHERE id=?", [(int)$_GET['edit']]) : null;
admin_header('Categories & units', 'taxonomy');
?>
<div class="two-col">
  <div>
    <h1 class="admin-title">Categories</h1>
    <p class="lede">Categories group the stales waste sheet and the loss report.</p>
    <div class="card pad">
      <form method="post" class="inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="cat_save">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div style="flex:2 1 180px"><label for="cname">Name</label>
          <input id="cname" name="name" required value="<?= h($edit['name'] ?? '') ?>"></div>
        <div style="flex:1 1 130px"><label for="ckind">Applies to</label>
          <select id="ckind" name="kind">
            <option value="product" <?= ($edit['kind'] ?? '') === 'product' ? 'selected' : '' ?>>Products</option>
            <option value="ingredient" <?= ($edit['kind'] ?? '') === 'ingredient' ? 'selected' : '' ?>>Ingredients</option>
          </select></div>
        <div style="flex:0 1 90px"><label for="csort">Order</label>
          <input id="csort" type="number" name="sort" value="<?= h($edit['sort'] ?? 0) ?>"></div>
        <div style="flex:0 0 auto"><button class="btn primary"><?= $edit ? 'Save' : 'Add' ?></button></div>
      </form>
    </div>
    <div class="card scroll">
      <table><thead><tr><th>Name</th><th>Applies to</th><th class="num">Order</th><th class="num">Articles</th><th></th></tr></thead>
        <tbody>
        <?php foreach (all("SELECT * FROM categories ORDER BY sort, name") as $c): ?>
          <tr><td><?= h($c['name']) ?></td><td><?= h(ucfirst($c['kind'])) ?>s</td>
            <td class="num"><?= (int)$c['sort'] ?></td>
            <td class="num"><?= (int)col("SELECT COUNT(*) FROM articles WHERE category_id=?", [$c['id']]) ?></td>
            <td class="num" style="white-space:nowrap">
              <a class="btn sm" href="taxonomy.php?edit=<?= (int)$c['id'] ?>">Edit</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this category?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="cat_del">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="btn sm danger">Delete</button></form></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
  </div>

  <div>
    <h1 class="admin-title">Units of measure</h1>
    <p class="lede">What a shop counts in: each, Pack, Bottle, Tray, Loaf.</p>
    <div class="card pad">
      <form method="post" class="inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="unit_save">
        <div style="flex:1 1 180px"><label for="uname">Name</label><input id="uname" name="name" required></div>
        <div style="flex:0 0 auto"><button class="btn primary">Add unit</button></div>
      </form>
    </div>
    <div class="card scroll">
      <table><thead><tr><th>Unit</th><th class="num">Articles</th><th></th></tr></thead><tbody>
        <?php foreach (all("SELECT * FROM units ORDER BY name") as $u): ?>
          <tr><td><?= h($u['name']) ?></td>
            <td class="num"><?= (int)col("SELECT COUNT(*) FROM articles WHERE unit_id=?", [$u['id']]) ?></td>
            <td class="num"><form method="post" onsubmit="return confirm('Delete this unit?')">
              <?= csrf_field() ?><input type="hidden" name="action" value="unit_del">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button class="btn sm danger">Delete</button></form></td></tr>
        <?php endforeach; ?>
      </tbody></table>
    </div>
  </div>
</div>
<?php admin_footer();
