<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$collegeId = request_int($_GET, 'college') ?? request_int($_POST, 'college_id');
$query = db()->prepare('SELECT id,name FROM colleges WHERE id=:id AND is_active=1 LIMIT 1');
$query->execute(['id' => $collegeId ?: 0]);
$college = $query->fetch();
if (!$college) {
    http_response_code(404);
    exit('College not found.');
}
if (($_SESSION['user_role'] ?? '') !== 'user') {
    header('Location: ' . app_url('/account.php?next=' . rawurlencode('question.php?college=' . (int) $college['id'])));
    exit;
}
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $question = trim(request_value($_POST, 'question'));
    if (mb_strlen($question) < 10 || mb_strlen($question) > 3000) {
        $error = 'Write a question between 10 and 3,000 characters.';
    } else {
        $insert = db()->prepare('INSERT INTO college_questions (user_id,college_id,question) VALUES (:user_id,:college_id,:question)');
        $insert->execute(['user_id' => (int) $_SESSION['user_id'], 'college_id' => (int) $college['id'], 'question' => $question]);
        $message = 'Your question has been sent for review.';
    }
}
$pageTitle = 'Ask a question | Collegekhoj';
require __DIR__ . '/includes/header.php';
?>
<section class="section"><div class="container" style="max-width:720px"><div class="form-card">
<h1 style="color:var(--navy)">Ask about <?= e($college['name']) ?></h1>
<?php if ($message): ?><div class="notice"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="college_id" value="<?= (int) $college['id'] ?>">
<label class="form-grid" style="display:grid">Your question<textarea name="question" rows="6" minlength="10" maxlength="3000" required></textarea></label>
<button class="button" type="submit">Submit question</button></form>
<p class="small muted">Questions are checked before they are published. For anything else, email <?= contact_email_link() ?>.</p></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
