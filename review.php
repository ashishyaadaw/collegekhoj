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
    header('Location: ' . app_url('/account.php?next=' . rawurlencode('review.php?college=' . (int) $college['id'])));
    exit;
}
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $rating = filter_var(request_value($_POST, 'rating'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    $reviewText = trim(request_value($_POST, 'review_text'));
    if (!$rating || mb_strlen($reviewText) < 20 || mb_strlen($reviewText) > 5000) {
        $error = 'Choose a rating and write a review between 20 and 5,000 characters.';
    } else {
        $insert = db()->prepare(
            'INSERT INTO reviews (user_id,college_id,rating,title,review_text,pros,cons,status)
             VALUES (:user_id,:college_id,:rating,:title,:review_text,:pros,:cons,\'pending\')'
        );
        $insert->execute([
            'user_id' => (int) $_SESSION['user_id'], 'college_id' => (int) $college['id'],
            'rating' => $rating, 'title' => trim(request_value($_POST, 'title')) ?: null,
            'review_text' => $reviewText, 'pros' => trim(request_value($_POST, 'pros')) ?: null,
            'cons' => trim(request_value($_POST, 'cons')) ?: null,
        ]);
        $message = 'Thank you. Your review is pending administrator approval.';
    }
}
$pageTitle = 'Write a review | Collegekhoj';
require __DIR__ . '/includes/header.php';
?>
<section class="section"><div class="container" style="max-width:760px"><div class="form-card">
<h1 style="color:var(--navy)">Review <?= e($college['name']) ?></h1>
<?php if ($message): ?><div class="notice"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="college_id" value="<?= (int) $college['id'] ?>">
<div class="form-grid"><label>Rating<select name="rating" required><option value="">Select rating</option><?php for ($i=5;$i>=1;$i--): ?><option value="<?= $i ?>"><?= $i ?> / 5</option><?php endfor; ?></select></label>
<label>Review title<input name="title" maxlength="180"></label><label class="full">Your review<textarea name="review_text" rows="6" minlength="20" maxlength="5000" required></textarea></label>
<label>Pros<textarea name="pros" rows="3" maxlength="2000"></textarea></label><label>Cons<textarea name="cons" rows="3" maxlength="2000"></textarea></label></div>
<button class="button" type="submit">Submit for review</button></form>
<p class="small muted">Reviews are checked before they are published. To change or remove a review, email <?= contact_email_link() ?>.</p>
</div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
