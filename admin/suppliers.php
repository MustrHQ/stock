<?php
require __DIR__.'/inc.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'toggle') {
        q("UPDATE suppliers SET active = 1 - active WHERE id=?", [$id]); flash('Supplier updated.');
    } else {
        $f = [trim($_POST['code'] ?? ''), trim($_POST['name'] ?? ''), trim($_POST['contact'] ?? ''),
              trim($_POST['email'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['address'] ?? ''),
              max(0, (int)($_POST['lead_days'] ?? 0)), (float)($_POST['min_order'] ?? 0),
              trim($_POST['order_days'] ?? ''), trim($_POST['notes'] ?? '')];
        if ($f[1] === '') flash('A supplier needs a name.', 'err');
        elseif ($id) {
            q("UPDATE suppliers SET code=?,name=?,contact=?,email=?,phone=?,address=?,lead_days=?,
               min_order=?,order_days=?,notes=? WHERE id=?", array_merge($f, [$id]));
            flash('Supplier saved.');
        } else {
            q("INSERT INTO suppliers (code,name,contact,email,phone,address,lead_days,min_order,order_days,notes)
               VALUES (?,?,?,?,?,?,?,?,?,?)", $f);
            flash('Supplier added.');
        }
    }
    redirect('suppliers.php');
}
$edit = !empty($_GET['edit']) ? one("SELECT * FROM suppliers WHERE id=?", [(int)$_GET['edit']]) : null;
admin_header('Suppliers', 'suppliers');
?>
<h1 class="admin-title"><?= $edit ? 'Edit supplier' : 'Add a supplier' ?></h1>
<p class="lede">Lead days set the delivery date on a new order and how far ahead the suggested
  quantities cover when an article has no par level.</p>
<div class="card pad">
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="grid g3">
      <div><label for="code">Code</label><input id="code" name="code" value="<?= h($edit['code'] ?? '') ?>"></div>
      <div style="grid-column:span 2"><label for="name">Name</label>
        <input id="name" name="name" required value="<?= h($edit['name'] ?? '') ?>"></div>
      <div><label for="contact">Contact</label><input id="contact" name="contact" value="<?= h($edit['contact'] ?? '') ?>"></div>
      <div><label for="email">Email</label><input id="email" type="email" name="email" value="<?= h($edit['email'] ?? '') ?>"></div>
      <div><label for="phone">Phone</label><input id="phone" name="phone" value="<?= h($edit['phone'] ?? '') ?>"></div>
      <div style="grid-column:span 2"><label for="address">Address</label>
        <input id="address" name="address" value="<?= h($edit['address'] ?? '') ?>"></div>
      <div><label for="lead_days">Lead days</label>
        <input id="lead_days" type="number" min="0" name="lead_days" value="<?= h($edit['lead_days'] ?? 2) ?>"></div>
      <div><label for="min_order">Minimum order value</label>
        <input id="min_order" type="number" step="0.01" min="0" name="min_order" value="<?= h($edit['min_order'] ?? 0) ?>"></div>
      <div><label for="order_days">Order days</label>
        <input id="order_days" name="order_days" placeholder="Mon, Wed, Fri" value="<?= h($edit['order_days'] ?? '') ?>"></div>
      <div><label for="notes">Notes</label><input id="notes" name="notes" value="<?= h($edit['notes'] ?? '') ?>"></div>
    </div>
    <div style="margin-top:14px;display:flex;gap:8px">
      <button class="btn primary"><?= $edit ? 'Save supplier' : 'Add supplier' ?></button>
      <?php if ($edit): ?><a class="btn" href="suppliers.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>
<div class="card scroll">
  <table><thead><tr><th>Code</th><th>Name</th><th>Contact</th><th class="num">Lead days</th>
    <th class="num">Min order</th><th class="num">Articles</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach (all("SELECT * FROM suppliers ORDER BY name") as $s): ?>
      <tr><td class="muted"><?= h($s['code']) ?></td><td><?= h($s['name']) ?></td>
        <td class="muted"><?= h(trim($s['contact'].' '.$s['email'])) ?></td>
        <td class="num"><?= (int)$s['lead_days'] ?></td>
        <td class="num"><?= money($s['min_order']) ?></td>
        <td class="num"><?= (int)col("SELECT COUNT(*) FROM articles WHERE supplier_id=?", [$s['id']]) ?></td>
        <td><?= $s['active'] ? '<span class="tag ok">Active</span>' : '<span class="tag">Off</span>' ?></td>
        <td class="num" style="white-space:nowrap">
          <a class="btn sm" href="suppliers.php?edit=<?= (int)$s['id'] ?>">Edit</a>
          <form method="post" style="display:inline"><?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="btn sm"><?= $s['active'] ? 'Disable' : 'Enable' ?></button></form></td></tr>
    <?php endforeach; ?>
  </tbody></table>
</div>
<?php admin_footer();
