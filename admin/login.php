<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if (($_SESSION['user_role'] ?? '') === 'admin') {
    header('Location: ' . app_url('/admin/index.php'));
    exit;
}
$message = '';
// First run: until an active admin exists, this page creates one instead of signing in.
$setupMode = active_admin_count(db()) === 0;
if ($setupMode && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim(request_value($_POST, 'name'));
    $email = trim(request_value($_POST, 'email'));
    $password = request_value($_POST, 'password');
    $errors = admin_account_errors($name, $email, $password, request_value($_POST, 'password_confirm'));
    if (!$errors && active_admin_count(db()) > 0) {
        $errors[] = 'An administrator account was just created. Sign in instead.';
        $setupMode = false;
    }
    $adminId = $errors ? null : create_admin_account(db(), $name, $email, $password);
    if ($adminId) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $adminId;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_role'] = 'admin';
        flash('Administrator account created. Welcome, ' . $name . '!');
        header('Location: ' . app_url('/admin/index.php'));
        exit;
    }
    $message = $errors ? implode(' ', $errors) : 'That email is already registered. Use a different email address.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = filter_var(trim(request_value($_POST, 'email')), FILTER_VALIDATE_EMAIL);
    $password = request_value($_POST, 'password');
    $now = time();
    if (!isset($_SESSION['login_window']) || ($now - (int) $_SESSION['login_window']) > 900) {
        $_SESSION['login_window'] = $now;
        $_SESSION['login_failures'] = 0;
    }
    if ((int) ($_SESSION['login_failures'] ?? 0) >= 5) {
        $message = 'Too many unsuccessful sign-in attempts. Wait 15 minutes and try again.';
    } elseif ($email && $password !== '') {
        $statement = db()->prepare('SELECT id, name, password_hash FROM users WHERE email = :email AND role = \'admin\' AND is_active = 1 LIMIT 1');
        $statement->execute(['email' => $email]);
        $admin = $statement->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $admin['id'];
            $_SESSION['user_name'] = $admin['name'];
            $_SESSION['user_role'] = 'admin';
            unset($_SESSION['login_failures'], $_SESSION['login_window']);
            header('Location: ' . app_url('/admin/index.php'));
            exit;
        }
        $_SESSION['login_failures'] = (int) ($_SESSION['login_failures'] ?? 0) + 1;
        $message = 'Email or password is incorrect.';
    } else {
        $message = 'Enter a valid email address and password.';
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin sign in | Collegekhoj</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700;900&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(app_url('/assets/portal.css')) ?>"><link rel="stylesheet" href="<?= e(app_url('/assets/admin.css')) ?>"></head>
<body class="admin-body adm-auth">
<main class="adm-auth-card">
  <div class="adm-auth-head">
    <a class="adm-brand-mark adm-auth-logo" href="<?= e(app_url('/')) ?>"><img src="<?= e(app_url('/assets/collegekhoj-logo.png')) ?>" alt="Collegekhoj"></a>
    <p>CMS &ndash; Collegekhoj</p>
    <h1><?= $setupMode ? 'Create admin account' : 'Admin sign in' ?></h1>
  </div>
  <div class="adm-auth-body">
    <?php if ($message): ?><div class="notice error"><?= e($message) ?></div><?php endif; ?>
    <?php if ($setupMode): ?>
    <p class="adm-auth-note">No administrator exists yet. Create the first account to manage the site; you can add more admins later from <strong>Administrators</strong>.</p>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label>Full name<input name="name" required maxlength="120" autocomplete="name" value="<?= e(request_value($_POST, 'name')) ?>"></label>
      <label>Email<input type="email" name="email" required maxlength="190" autocomplete="username" value="<?= e(request_value($_POST, 'email')) ?>"></label>
      <label>Password<input type="password" name="password" required minlength="12" maxlength="200" autocomplete="new-password"><small class="muted">At least 12 characters.</small></label>
      <label>Confirm password<input type="password" name="password_confirm" required minlength="12" maxlength="200" autocomplete="new-password"></label>
      <button class="button" type="submit">Create account &amp; sign in</button>
    </form>
    <?php else: ?>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label>Email<input type="email" name="email" required autocomplete="username"></label>
      <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
      <button class="button" type="submit">Sign in</button>
    </form>
    <?php endif; ?>
    <a class="adm-auth-back" href="<?= e(app_url('/')) ?>">&larr; Back to Collegekhoj</a>
  </div>
</main>
</body></html>
