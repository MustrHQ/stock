<?php
require __DIR__.'/inc.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'toggle') {
        q("UPDATE shops SET active = 1 - active WHERE id=?", [$id]); flash('Shop updated.');
    } else {
        $f = [trim($_POST['code'] ?? ''), trim($_POST['name'] ?? ''), trim($_POST['address'] ?? '')];
        if ($f[0] === '' || $f[1] === '') flash('A shop needs a code and a name.', 'err');
        else try {
            if ($id) { q("UPDATE shops SET code=?,name=?,address=? WHERE id=?", array_merge($f, [$id])); flash('Shop saved.'); }
            else { q("INSERT INTO shops (code,name,address) VALUES (?,?,?)", $f); flash('Shop added.'); }
        } catch (PDOException $e) { flash('That shop code is already used.', 'err'); }
    }
    redirect('shops.php');
}
$edit = !empty($_GET['edit']) ? one("SELECT * FROM shops WHERE id=?", [(int)$_GET['edit']]) : null;
admin_header('Shops', 'shops');
?>
<h1 class="admin-title"><?= $edit ? 'Edit shop' : 'Add a shop' ?></h1>
<div class="card pad">
  <form method="post" class="inline">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div style="flex:0 1 120px"><label for="code">Code</label>
      <input id="code" name="code" required value="<?= h($edit['code'] ?? '') ?>"></div>
    <div style="flex:1 1 200px"><label for="name">Name</label>
      <input id="name" name="name" required value="<?= h($edit['name'] ?? '') ?>"></div>
    <div style="flex:2 1 240px"><label for="address">Address</label>
      <input id="address" name="address" value="<?= h($edit['address'] ?? '') ?>"></div>
    <div style="flex:0 0 auto"><button class="btn primary"><?= $edit ? 'Save shop' : 'Add shop' ?></button></div>
  </form>
</div>
<div class="card scroll">
  <table><thead><tr><th>Code</th><th>Name</th><th>Address</th><th>Users</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach (all("SELECT * FROM shops ORDER BY code") as $s): ?>
      <tr><td class="muted"><?= h($s['code']) ?></td><td><?= h($s['name']) ?></td>
        <td class="muted"><?= h($s['address']) ?></td>
        <td><?= (int)col("SELECT COUNT(*) FROM users WHERE shop_id=?", [$s['id']]) ?></td>
        <td><?= $s['active'] ? '<span class="tag ok">Active</span>' : '<span class="tag">Closed</span>' ?></td>
        <td class="num" style="white-space:nowrap">
          <a class="btn sm" href="shops.php?edit=<?= (int)$s['id'] ?>">Edit</a>
          <form method="post" style="display:inline"><?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="btn sm"><?= $s['active'] ? 'Close' : 'Reopen' ?></button></form></td></tr>
    <?php endforeach; ?>
  </tbody></table>
</div>
<?php admin_footer();
