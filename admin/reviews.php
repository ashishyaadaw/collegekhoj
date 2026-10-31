<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $type = ($_POST['type'] ?? '') === 'question' ? 'question' : 'review';
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $action = (string) ($_POST['action'] ?? '');
    $table = $type === 'question' ? 'college_questions' : 'reviews';
    if ($id && in_array($action, ['approved', 'rejected', 'delete'], true)) {
        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM ' . $table . ' WHERE id=:id')->execute(['id' => $id]);
        } elseif ($type === 'question') {
            $answer = trim((string) ($_POST['answer'] ?? ''));
            $pdo->prepare('UPDATE college_questions SET status=:status,answer=:answer,answered_at=NOW() WHERE id=:id')
                ->execute(['status' => $action, 'answer' => $answer !== '' ? $answer : null, 'id' => $id]);
        } else {
            $pdo->prepare('UPDATE reviews SET status=:status WHERE id=:id')->execute(['status' => $action, 'id' => $id]);
        }
        flash(ucfirst($type) . ' updated.');
        header('Location: ' . app_url('/admin/reviews.php'));
        exit;
    }
}
$reviews = $pdo->query(
    'SELECT r.id,r.rating,r.title,r.review_text,r.pros,r.cons,r.status,r.created_at,u.name AS user_name,c.name AS college_name
     FROM reviews r JOIN users u ON u.id=r.user_id JOIN colleges c ON c.id=r.college_id ORDER BY r.created_at DESC LIMIT 100'
)->fetchAll();
$questions = $pdo->query(
    'SELECT q.id,q.question,q.answer,q.status,q.created_at,u.name AS user_name,c.name AS college_name
     FROM college_questions q JOIN users u ON u.id=q.user_id JOIN colleges c ON c.id=q.college_id ORDER BY q.created_at DESC LIMIT 100'
)->fetchAll();
$adminTitle = 'Reviews & Q&A';
require __DIR__ . '/_nav.php';
?>
<section><h2>Student reviews</h2><?php if (!$reviews): ?><p class="muted">No reviews have been submitted.</p><?php endif; ?>
<?php foreach ($reviews as $review): ?><article class="card" style="margin:12px 0"><div class="section-heading"><div><strong><?= e($review['college_name']) ?></strong> · <?= e($review['user_name']) ?> · <?= (int) $review['rating'] ?>/5</div><span class="pill"><?= e($review['status']) ?></span></div>
<h3><?= e($review['title'] ?? 'Student review') ?></h3><p><?= e($review['review_text']) ?></p>
<?php if ($review['pros']): ?><p><strong>Pros:</strong> <?= e($review['pros']) ?></p><?php endif; ?><?php if ($review['cons']): ?><p><strong>Cons:</strong> <?= e($review['cons']) ?></p><?php endif; ?>
<?php foreach (['approved'=>'Approve','rejected'=>'Reject','delete'=>'Delete'] as $action=>$label): ?><form class="inline-form" method="post" onsubmit="<?= $action === 'delete' ? "return confirm('Delete this review?')" : '' ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="type" value="review"><input type="hidden" name="id" value="<?= (int) $review['id'] ?>"><input type="hidden" name="action" value="<?= e($action) ?>"><button type="submit"><?= e($label) ?></button></form><?php endforeach; ?>
</article><?php endforeach; ?></section>
<section class="section"><h2>College questions</h2><?php if (!$questions): ?><p class="muted">No questions have been submitted.</p><?php endif; ?>
<?php foreach ($questions as $question): ?><article class="card" style="margin:12px 0"><div class="section-heading"><div><strong><?= e($question['college_name']) ?></strong> · <?= e($question['user_name']) ?></div><span class="pill"><?= e($question['status']) ?></span></div>
<p><strong>Q:</strong> <?= e($question['question']) ?></p><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="type" value="question"><input type="hidden" name="id" value="<?= (int) $question['id'] ?>"><label>Answer<textarea name="answer" rows="3"><?= e($question['answer'] ?? '') ?></textarea></label>
<?php foreach (['approved'=>'Publish answer','rejected'=>'Reject','delete'=>'Delete'] as $action=>$label): ?><button type="submit" name="action" value="<?= e($action) ?>"><?= e($label) ?></button><?php endforeach; ?></form></article><?php endforeach; ?></section>
<?php require __DIR__ . '/_end.php'; ?>
