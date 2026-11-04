<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$pdo = db();
$currentId = (int) $_SESSION['user_id'];
$errors = [];
$editId = request_int($_GET, 'edit');

$findAdmin = static function (int $id) use ($pdo): ?array {
    $statement = $pdo->prepare("SELECT id, name, email, is_active, created_at FROM users WHERE id = :id AND role = 'admin'");
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
};
$back = static function (string $message, string $kind = 'success'): never {
    flash($message, $kind);
    header('Location: ' . app_url('/admin/users.php'));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = request_value($_POST, 'action');
    $id = request_int($_POST, 'id') ?? 0;
    $target = $id ? $findAdmin($id) : null;

    if ($action === 'create') {
        $name = trim(request_value($_POST, 'name'));
        $email = trim(request_value($_POST, 'email'));
        $password = request_value($_POST, 'password');
        $errors = admin_account_errors($name, $email, $password, request_value($_POST, 'password_confirm'));
        if (!$errors) {
            if (create_admin_account($pdo, $name, $email, $password) === null) {
                $errors[] = 'That email is already registered. Use a different email address.';
            } else {
                $back('Administrator ' . $name . ' created. They can now sign in at /admin/login.php.');
            }
        }
    } elseif ($target && $action === 'update') {
        $name = trim(request_value($_POST, 'name'));
        $password = request_value($_POST, 'password');
        if ($name === '' || mb_strlen($name) > 120) {
            $errors[] = 'Enter a name of up to 120 characters.';
        }
        if ($password !== '') {
            $errors = array_merge($errors, admin_password_errors($password, request_value($_POST, 'password_confirm')));
        }
        if (!$errors) {
            $pdo->prepare('UPDATE users SET name = :name WHERE id = :id')->execute(['name' => $name, 'id' => $id]);
            if ($password !== '') {
                $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
                    ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
            }
            $back('Administrator ' . $name . ' updated' . ($password !== '' ? ' and password changed.' : '.'));
        }
        $editId = $id;
    } elseif ($target && in_array($action, ['toggle', 'delete'], true)) {
        if ($id === $currentId) {
            $back('You cannot deactivate or delete your own account.', 'error');
        }
        if ($target['is_active'] && active_admin_count($pdo) <= 1) {
            $back('At least one active administrator is required.', 'error');
        }
        if ($action === 'toggle') {
            $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $id]);
            $back('Administrator ' . $target['name'] . ($target['is_active'] ? ' deactivated.' : ' activated.'));
        }
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
        $back('Administrator ' . $target['name'] . ' deleted.');
    } else {
        $back('That administrator no longer exists.', 'error');
    }
}

$edit = $editId ? $findAdmin($editId) : null;
$admins = $pdo->query("SELECT id, name, email, is_active, created_at FROM users WHERE role = 'admin' ORDER BY is_active DESC, name")->fetchAll();
$adminTitle = 'Administrators';
require __DIR__ . '/_nav.php';
?>
<?php foreach ($errors as $error): ?><div class="notice error"><?= e($error) ?></div><?php endforeach; ?>
<?php if ($edit): ?>
<section class="form-card"><h2>Edit <?= e($edit['name']) ?></h2>
<p class="muted small"><?= e($edit['email']) ?> &middot; leave the password fields empty to keep the current password.</p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
<div class="form-grid">
  <label class="full">Full name *<input name="name" required maxlength="120" value="<?= e($errors ? request_value($_POST, 'name') : $edit['name']) ?>"></label>
  <label>New password<input type="password" name="password" minlength="12" maxlength="200" autocomplete="new-password"><small class="muted">At least 12 characters.</small></label>
  <label>Confirm new password<input type="password" name="password_confirm" minlength="12" maxlength="200" autocomplete="new-password"></label>
</div>
<button class="button" type="submit">Save changes</button> <a class="button button-light" href="<?= e(app_url('/admin/users.php')) ?>">Cancel</a>
</form></section>
<?php else: ?>
<section class="form-card"><h2>Add administrator</h2>
<p class="muted small">Administrators can manage all content, leads and settings. They sign in at <code>/admin/login.php</code> with the email and password set here.</p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
<div class="form-grid">
  <label>Full name *<input name="name" required maxlength="120" autocomplete="off" value="<?= e($errors ? request_value($_POST, 'name') : '') ?>"></label>
  <label>Email *<input type="email" name="email" required maxlength="190" autocomplete="off" value="<?= e($errors ? request_value($_POST, 'email') : '') ?>"></label>
  <label>Password *<input type="password" name="password" required minlength="12" maxlength="200" autocomplete="new-password"><small class="muted">At least 12 characters.</small></label>
  <label>Confirm password *<input type="password" name="password_confirm" required minlength="12" maxlength="200" autocomplete="new-password"></label>
</div>
<button class="button" type="submit">Create administrator</button>
</form></section>
<?php endif; ?>
<section class="section"><h2>Administrators (<?= count($admins) ?>)</h2>
  <div class="table-wrap card"><table><thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Added</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($admins as $admin): $isSelf = (int) $admin['id'] === $currentId; ?><tr>
    <td><strong><?= e($admin['name']) ?></strong><?php if ($isSelf): ?> <small class="muted">(you)</small><?php endif; ?></td>
    <td class="small"><?= e($admin['email']) ?></td>
    <td><?= $admin['is_active'] ? 'Active' : 'Deactivated' ?></td>
    <td class="small"><?= e(date('d M Y', strtotime($admin['created_at']))) ?></td>
    <td style="white-space:nowrap"><a class="text-link" href="<?= e(app_url('/admin/users.php?edit=' . (int) $admin['id'])) ?>">Edit</a>
      <?php if (!$isSelf): ?>
      <form class="inline-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $admin['id'] ?>"><button type="submit" class="adm-btn-soft"><?= $admin['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
      <form class="inline-form" method="post" onsubmit="return confirm('Delete this administrator? They will no longer be able to sign in.')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $admin['id'] ?>"><button type="submit">Delete</button></form>
      <?php endif; ?></td>
  </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<?php require __DIR__ . '/_end.php'; ?>
