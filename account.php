<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (($_SESSION['user_role'] ?? '') === 'admin') {
    header('Location: ' . app_url('/admin/index.php'));
    exit;
}
$candidateNext = request_value($_POST, 'next') ?: request_value($_GET, 'next');
$next = preg_match('/\A(?:review|question)\.php\?college=[1-9][0-9]*\z/', $candidateNext) ? $candidateNext : '';
$error = '';
$mode = (request_value($_POST, 'mode') ?: request_value($_GET, 'mode')) === 'register' ? 'register' : 'login';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    verify_csrf();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: ' . app_url('/'));
    exit;
}
if (($_SESSION['user_role'] ?? '') === 'user') {
    header('Location: ' . app_url($next !== '' ? '/' . $next : '/'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim(request_value($_POST, 'name'));
    $email = filter_var(trim(request_value($_POST, 'email')), FILTER_VALIDATE_EMAIL);
    $password = request_value($_POST, 'password');
    if (!$email || strlen($password) < 12 || strlen($password) > 200) {
        $error = 'Enter a valid email address and a password between 12 and 200 characters.';
    } elseif ($mode === 'register' && ($name === '' || mb_strlen($name) > 120)) {
        $error = 'Enter a name of 120 characters or fewer.';
    } elseif ($mode === 'register' && !isset($_POST['privacy_consent'])) {
        $error = 'Please read and accept the privacy notice before creating an account.';
    } else {
        $query = db()->prepare('SELECT id,name,password_hash FROM users WHERE email=:email AND role=\'user\' AND is_active=1 LIMIT 1');
        $query->execute(['email' => $email]);
        $user = $query->fetch();
        if ($mode === 'register') {
            $existing = db()->prepare('SELECT id FROM users WHERE email=:email LIMIT 1');
            $existing->execute(['email' => $email]);
            if ($existing->fetch()) {
                $error = 'An account already uses this email. Sign in instead.';
            } else {
                $insert = db()->prepare('INSERT INTO users (name,email,password_hash,role,is_active) VALUES (:name,:email,:hash,\'user\',1)');
                $insert->execute(['name' => $name, 'email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
                $user = ['id' => (int) db()->lastInsertId(), 'name' => $name];
            }
        } elseif (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Email or password is incorrect.';
        }
        if (!$error && $user) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = 'user';
            header('Location: ' . app_url($next !== '' ? '/' . $next : '/'));
            exit;
        }
    }
}
$pageTitle = $mode === 'register' ? 'Create account | Collegekhoj' : 'Sign in | Collegekhoj';
require __DIR__ . '/includes/header.php';
?>
<section class="section"><div class="container" style="max-width:520px"><div class="form-card">
  <h1 style="color:var(--navy)"><?= $mode === 'register' ? 'Create your account' : 'Sign in' ?></h1>
  <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
  <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="mode" value="<?= e($mode) ?>"><input type="hidden" name="next" value="<?= e($next) ?>">
    <?php if ($mode === 'register'): ?><label class="form-grid" style="display:grid">Name<input name="name" maxlength="120" required autocomplete="name" value="<?= e(request_value($_POST, 'name')) ?>"></label><?php endif; ?>
    <label class="form-grid" style="display:grid">Email<input type="email" name="email" maxlength="190" required autocomplete="email" value="<?= e(request_value($_POST, 'email')) ?>"></label>
    <label class="form-grid" style="display:grid">Password<input type="password" name="password" minlength="12" maxlength="200" required autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>"></label>
    <?php if ($mode === 'register'): ?><label><input type="checkbox" name="privacy_consent" required style="width:auto"> I have read the <a class="text-link" href="<?= e(app_url('/page.php?slug=privacy-policy')) ?>">privacy notice</a>.</label><?php endif; ?>
    <button class="button" type="submit"><?= $mode === 'register' ? 'Create account' : 'Sign in' ?></button>
  </form>
  <p class="small muted"><?= $mode === 'register' ? 'Already registered?' : 'New to Collegekhoj?' ?>
    <a class="text-link" href="<?= e(app_url('/account.php?mode=' . ($mode === 'register' ? 'login' : 'register') . ($next ? '&next=' . rawurlencode($next) : ''))) ?>"><?= $mode === 'register' ? 'Sign in' : 'Create an account' ?></a></p>
  <p class="small muted">Trouble signing in, or want your account data or deletion? Email <?= contact_email_link() ?>.</p>
</div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
