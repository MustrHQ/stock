<?php
require __DIR__.'/inc.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $a  = $_POST['action'] ?? 'save';
    if ($a === 'toggle') {
        if ($id === (int)user()['id']) flash('You cannot deactivate your own account.', 'err');
        else { q("UPDATE users SET active = 1 - active WHERE id=?", [$id]); flash('User updated.'); }
        redirect('users.php');
    }
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role  = in_array($_POST['role'] ?? '', ['admin','manager','staff'], true) ? $_POST['role'] : 'staff';
    $shop  = ((int)($_POST['shop_id'] ?? 0)) ?: null;
    $pass  = $_POST['password'] ?? '';
    if ($name === '' || $email === '') flash('Name and email are both needed.', 'err');
    elseif (!$id && strlen($pass) < 8) flash('Set a password of at least 8 characters.', 'err');
    else try {
        if ($id) {
            q("UPDATE users SET name=?,email=?,role=?,shop_id=? WHERE id=?", [$name,$email,$role,$shop,$id]);
            if ($pass !== '') {
                if (strlen($pass) < 8) { flash('The new password is too short — it was not changed.', 'err'); }
                else q("UPDATE users SET pass_hash=? WHERE id=?", [password_hash($pass, PASSWORD_DEFAULT), $id]);
            }
            flash('User saved.');
        } else {
            q("INSERT INTO users (name,email,pass_hash,role,shop_id,created_at) VALUES (?,?,?,?,?,NOW())",
              [$name,$email,password_hash($pass, PASSWORD_DEFAULT),$role,$shop]);
            flash('User added.');
        }
    } catch (PDOException $e) { flash('That email address is already registered.', 'err'); }
    redirect('users.php');
}
$edit = !empty($_GET['edit']) ? one("SELECT * FROM users WHERE id=?", [(int)$_GET['edit']]) : null;
$shops = all("SELECT * FROM shops ORDER BY code");
admin_header('Users', 'users');
?>
<h1 class="admin-title"><?= $edit ? 'Edit user' : 'Add a user' ?></h1>
<p class="lede">Staff fill in sheets. Managers can also reopen a confirmed sheet. Admins get this panel.</p>
<div class="card pad">
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="grid g3">
      <div><label for="name">Name</label><input id="name" name="name" required value="<?= h($edit['name'] ?? '') ?>"></div>
      <div><label for="email">Email</label><input id="email" type="email" name="email" required value="<?= h($edit['email'] ?? '') ?>"></div>
      <div><label for="role">Role</label>
        <select id="role" name="role">
          <?php foreach (['staff' => 'Staff', 'manager' => 'Manager', 'admin' => 'Admin'] as $k => $v): ?>
            <option value="<?= h($k) ?>" <?= ($edit['role'] ?? '') === $k ? 'selected' : '' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label for="shop_id">Home shop</label>
        <select id="shop_id" name="shop_id">
          <option value="">—</option>
          <?php foreach ($shops as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= ($edit['shop_id'] ?? 0) == $s['id'] ? 'selected' : '' ?>>
              <?= h($s['code'].' '.$s['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label for="password"><?= $edit ? 'New password (leave blank to keep)' : 'Password' ?></label>
        <input id="password" type="password" name="password" autocomplete="new-password" <?= $edit ? '' : 'required' ?>></div>
    </div>
    <div style="margin-top:14px;display:flex;gap:8px">
      <button class="btn primary"><?= $edit ? 'Save user' : 'Add user' ?></button>
      <?php if ($edit): ?><a class="btn" href="users.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>
<div class="card scroll">
  <table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Home shop</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach (all("SELECT u.*, s.code, s.name AS shop FROM users u LEFT JOIN shops s ON s.id=u.shop_id
                        ORDER BY u.role, u.name") as $u): ?>
      <tr><td><?= h($u['name']) ?></td><td class="muted"><?= h($u['email']) ?></td>
        <td><?= h(ucfirst($u['role'])) ?></td><td class="muted"><?= h(trim($u['code'].' '.$u['shop'])) ?></td>
        <td><?= $u['active'] ? '<span class="tag ok">Active</span>' : '<span class="tag">Disabled</span>' ?></td>
        <td class="num" style="white-space:nowrap">
          <a class="btn sm" href="users.php?edit=<?= (int)$u['id'] ?>">Edit</a>
          <form method="post" style="display:inline"><?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="btn sm"><?= $u['active'] ? 'Disable' : 'Enable' ?></button></form></td></tr>
    <?php endforeach; ?>
  </tbody></table>
</div>
<?php admin_footer();
