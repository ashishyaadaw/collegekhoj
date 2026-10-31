<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if (($_SESSION['user_role'] ?? '') === 'admin') {
    header('Location: ' . app_url('/admin/index.php'));
    exit;
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    <h1>Admin sign in</h1>
  </div>
  <div class="adm-auth-body">
    <?php if ($message): ?><div class="notice error"><?= e($message) ?></div><?php endif; ?>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label>Email<input type="email" name="email" required autocomplete="username"></label>
      <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
      <button class="button" type="submit">Sign in</button>
    </form>
    <a class="adm-auth-back" href="<?= e(app_url('/')) ?>">&larr; Back to Collegekhoj</a>
  </div>
</main>
</body></html>
