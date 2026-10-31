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
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'add_category') {
        $categoryName = trim((string) ($_POST['category_name'] ?? ''));
        if ($categoryName === '') {
            $error = 'Category name is required.';
        } else {
            $existing = $pdo->prepare('SELECT id FROM course_categories WHERE name = :name LIMIT 1');
            $existing->execute(['name' => $categoryName]);
            if ($existing->fetch()) {
                $error = 'A course category with that name already exists.';
            } else {
                $slug = slugify($categoryName);
                if ($slug === '') {
                    $error = 'Enter a category name containing letters or numbers.';
                } else {
                    $suffix = 2;
                    $base = $slug;
                    while (true) {
                        $check = $pdo->prepare('SELECT id FROM course_categories WHERE slug = :slug LIMIT 1');
                        $check->execute(['slug' => $slug]);
                        if (!$check->fetch()) {
                            break;
                        }
                        $slug = $base . '-' . $suffix++;
                    }
                    $pdo->prepare('INSERT INTO course_categories (name,slug,sort_order) VALUES (:name,:slug,:sort_order)')
                        ->execute(['name' => $categoryName, 'slug' => $slug, 'sort_order' => (int) ($_POST['sort_order'] ?? 0)]);
                    flash('Course category added.');
                    header('Location: ' . app_url('/admin/courses.php'));
                    exit;
                }
            }
        }
    } elseif ($action === 'delete' && filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT)) {
        $pdo->prepare('DELETE FROM courses WHERE id=:id')->execute(['id' => (int) $_POST['id']]);
        flash('Course deleted.');
        header('Location: ' . app_url('/admin/courses.php'));
        exit;
    } elseif ($action === 'save') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $error = 'Course name is required.';
        } else {
            $data = [
                'category_id' => filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                'university_id' => filter_var($_POST['university_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                'college_id' => filter_var($_POST['college_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                'name' => $name, 'slug' => unique_slug('courses', trim((string) ($_POST['slug'] ?? '')) ?: $name, $id),
                'duration' => trim((string) ($_POST['duration'] ?? '')) ?: null,
                'fees_min' => ($_POST['fees_min'] ?? '') !== '' ? max(0, (float) $_POST['fees_min']) : null,
                'fees_max' => ($_POST['fees_max'] ?? '') !== '' ? max(0, (float) $_POST['fees_max']) : null,
                'eligibility' => trim((string) ($_POST['eligibility'] ?? '')) ?: null,
                'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];
            if ($id) {
                $data['id'] = $id;
                $sql = 'UPDATE courses SET category_id=:category_id,university_id=:university_id,college_id=:college_id,name=:name,slug=:slug,duration=:duration,fees_min=:fees_min,fees_max=:fees_max,eligibility=:eligibility,description=:description,is_active=:is_active WHERE id=:id';
            } else {
                $sql = 'INSERT INTO courses (category_id,university_id,college_id,name,slug,duration,fees_min,fees_max,eligibility,description,is_active) VALUES (:category_id,:university_id,:college_id,:name,:slug,:duration,:fees_min,:fees_max,:eligibility,:description,:is_active)';
            }
            $pdo->prepare($sql)->execute($data);
            flash('Course saved.');
            header('Location: ' . app_url('/admin/courses.php'));
            exit;
        }
    }
}
if ($editId) {
    $query = $pdo->prepare('SELECT * FROM courses WHERE id=:id');
    $query->execute(['id' => $editId]);
    $edit = $query->fetch() ?: null;
}
$categories = $pdo->query('SELECT * FROM course_categories ORDER BY sort_order,name')->fetchAll();
$universities = $pdo->query('SELECT id,name FROM universities ORDER BY name')->fetchAll();
$colleges = $pdo->query('SELECT id,name FROM colleges ORDER BY name')->fetchAll();
$courses = $pdo->query('SELECT c.*,cc.name AS category_name,u.name AS university_name FROM courses c LEFT JOIN course_categories cc ON cc.id=c.category_id LEFT JOIN universities u ON u.id=c.university_id ORDER BY c.name')->fetchAll();
$adminTitle = 'Manage courses';
require __DIR__ . '/_nav.php';
?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<section class="form-card"><h2><?= $edit ? 'Edit course' : 'Add course' ?></h2><form method="post">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
<div class="form-grid"><label>Course name *<input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label><label>Slug<input name="slug" value="<?= e($edit['slug'] ?? '') ?>"></label>
<label>Stream/category<select name="category_id"><option value="">Uncategorized</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($edit['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
<label>University<select name="university_id"><option value="">Not linked</option><?php foreach ($universities as $university): ?><option value="<?= (int) $university['id'] ?>" <?= (int) ($edit['university_id'] ?? 0) === (int) $university['id'] ? 'selected' : '' ?>><?= e($university['name']) ?></option><?php endforeach; ?></select></label>
<label>College<select name="college_id"><option value="">Not linked</option><?php foreach ($colleges as $college): ?><option value="<?= (int) $college['id'] ?>" <?= (int) ($edit['college_id'] ?? 0) === (int) $college['id'] ? 'selected' : '' ?>><?= e($college['name']) ?></option><?php endforeach; ?></select></label>
<label>Duration<input name="duration" placeholder="e.g. 2 years" value="<?= e($edit['duration'] ?? '') ?>"></label><label>Minimum fees<input type="number" min="0" step="0.01" name="fees_min" value="<?= e((string) ($edit['fees_min'] ?? '')) ?>"></label><label>Maximum fees<input type="number" min="0" step="0.01" name="fees_max" value="<?= e((string) ($edit['fees_max'] ?? '')) ?>"></label>
<label class="full">Eligibility<textarea name="eligibility" rows="3"><?= e($edit['eligibility'] ?? '') ?></textarea></label><label class="full">Description<textarea name="description" rows="3"><?= e($edit['description'] ?? '') ?></textarea></label></div>
<label><input type="checkbox" name="is_active" style="width:auto" <?= !isset($edit['is_active']) || $edit['is_active'] ? 'checked' : '' ?>> Published</label>
<button class="button" type="submit">Save course</button><?php if ($edit): ?> <a class="button button-light" href="<?= e(app_url('/admin/courses.php')) ?>">Cancel</a><?php endif; ?></form></section>
<section class="form-card" style="margin-top:20px"><h2>Add course category</h2><form method="post" class="form-grid">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_category">
<label>Category name<input name="category_name" required></label><label>Menu sort order<input type="number" name="sort_order" value="0"></label><div><button class="button" type="submit">Add category</button></div>
</form><p class="small muted">Categories organize the public Courses dropdown.</p></section>
<section class="section"><h2>Course directory</h2><div class="table-wrap card"><table><thead><tr><th>Course</th><th>Category</th><th>University</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($courses as $course): ?><tr><td><?= e($course['name']) ?></td><td><?= e($course['category_name'] ?? '—') ?></td><td><?= e($course['university_name'] ?? '—') ?></td><td><?= $course['is_active'] ? 'Published' : 'Hidden' ?></td><td><a class="text-link" href="<?= e(app_url('/admin/courses.php?edit=' . (int) $course['id'])) ?>">Edit</a>
<form class="inline-form" method="post" onsubmit="return confirm('Delete this course?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $course['id'] ?>"><button type="submit">Delete</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php require __DIR__ . '/_end.php'; ?>
