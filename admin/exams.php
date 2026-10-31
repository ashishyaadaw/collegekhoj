<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$pdo = db();
$error = '';
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT) ?: null;
$edit = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = request_value($_POST, 'action');
    $id = request_int($_POST, 'id');
    if ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM exams WHERE id=:id')->execute(['id' => $id]);
        flash('Exam deleted.');
        header('Location: ' . app_url('/admin/exams.php'));
        exit;
    }
    if ($action === 'save') {
        $name = trim(request_value($_POST, 'name'));
        if ($name === '') {
            $error = 'Exam name is required.';
        } else {
            $base = slugify(trim(request_value($_POST, 'slug')) ?: $name);
            if ($base === '') {
                $error = 'Enter an exam name containing letters or numbers.';
            } else {
                $slug = $base;
                $suffix = 2;
                while (true) {
                    $check = $pdo->prepare('SELECT id FROM exams WHERE slug=:slug' . ($id ? ' AND id<>:id' : '') . ' LIMIT 1');
                    $checkParameters = ['slug' => $slug];
                    if ($id) {
                        $checkParameters['id'] = $id;
                    }
                    $check->execute($checkParameters);
                    if (!$check->fetch()) {
                        break;
                    }
                    $slug = $base . '-' . $suffix++;
                }
                $data = [
                    'name' => $name, 'slug' => $slug,
                    'exam_date' => request_value($_POST, 'exam_date') ?: null,
                    'application_deadline' => request_value($_POST, 'application_deadline') ?: null,
                    'eligibility' => trim(request_value($_POST, 'eligibility')) ?: null,
                    'description' => trim(request_value($_POST, 'description')) ?: null,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ];
                foreach (['exam_date', 'application_deadline'] as $dateField) {
                    if ($data[$dateField] !== null) {
                        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $data[$dateField]);
                        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $data[$dateField]) {
                            $error = 'Enter valid exam and application dates.';
                            break;
                        }
                    }
                }
                if ($error === '') {
                    if ($id) {
                        $data['id'] = $id;
                        $sql = 'UPDATE exams SET name=:name,slug=:slug,exam_date=:exam_date,application_deadline=:application_deadline,eligibility=:eligibility,description=:description,is_active=:is_active WHERE id=:id';
                    } else {
                        $sql = 'INSERT INTO exams (name,slug,exam_date,application_deadline,eligibility,description,is_active) VALUES (:name,:slug,:exam_date,:application_deadline,:eligibility,:description,:is_active)';
                    }
                    $pdo->prepare($sql)->execute($data);
                    flash('Exam saved.');
                    header('Location: ' . app_url('/admin/exams.php'));
                    exit;
                }
            }
        }
    }
}
if ($editId) {
    $statement = $pdo->prepare('SELECT * FROM exams WHERE id=:id');
    $statement->execute(['id' => $editId]);
    $edit = $statement->fetch() ?: null;
}
$exams = $pdo->query('SELECT * FROM exams ORDER BY exam_date IS NULL,exam_date,name')->fetchAll();
$adminTitle = 'Manage exams';
require __DIR__ . '/_nav.php';
?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<section class="form-card"><h2><?= $edit ? 'Edit exam' : 'Add exam' ?></h2><form method="post">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
<div class="form-grid"><label>Exam name *<input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label><label>Slug<input name="slug" value="<?= e($edit['slug'] ?? '') ?>"></label>
<label>Exam date<input type="date" name="exam_date" value="<?= e($edit['exam_date'] ?? '') ?>"></label><label>Application deadline<input type="date" name="application_deadline" value="<?= e($edit['application_deadline'] ?? '') ?>"></label>
<label class="full">Eligibility<textarea name="eligibility" rows="3"><?= e($edit['eligibility'] ?? '') ?></textarea></label><label class="full">Description<textarea name="description" rows="3"><?= e($edit['description'] ?? '') ?></textarea></label></div>
<label><input type="checkbox" name="is_active" style="width:auto" <?= !isset($edit['is_active']) || $edit['is_active'] ? 'checked' : '' ?>> Published</label>
<button class="button" type="submit">Save exam</button><?php if ($edit): ?> <a class="button button-light" href="<?= e(app_url('/admin/exams.php')) ?>">Cancel</a><?php endif; ?></form></section>
<section class="section"><h2>Exam directory</h2><div class="table-wrap card"><table><thead><tr><th>Exam</th><th>Exam date</th><th>Application deadline</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($exams as $exam): ?><tr><td><?= e($exam['name']) ?></td><td><?= e($exam['exam_date'] ?? 'Not announced') ?></td><td><?= e($exam['application_deadline'] ?? 'Not announced') ?></td><td><?= $exam['is_active'] ? 'Published' : 'Hidden' ?></td><td><a class="text-link" href="<?= e(app_url('/admin/exams.php?edit=' . (int) $exam['id'])) ?>">Edit</a>
<form class="inline-form" method="post" onsubmit="return confirm('Delete this exam?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $exam['id'] ?>"><button type="submit">Delete</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php require __DIR__ . '/_end.php'; ?>
