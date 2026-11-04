<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$pdo = db();
ensure_course_menu_columns($pdo);
ensure_catalog_schema($pdo);
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
        // Universities offering the course, each with an optional university-specific fee.
        $knownUniversities = array_map('intval', $pdo->query('SELECT id FROM universities')->fetchAll(PDO::FETCH_COLUMN));
        $postedFees = is_array($_POST['university_fees'] ?? null) ? $_POST['university_fees'] : [];
        $offered = [];
        foreach ((array) ($_POST['universities'] ?? []) as $universityId) {
            $universityId = filter_var($universityId, FILTER_VALIDATE_INT);
            if ($universityId && in_array($universityId, $knownUniversities, true)) {
                $fee = trim((string) ($postedFees[$universityId] ?? ''));
                $offered[$universityId] = $fee !== '' && is_numeric($fee) ? max(0, (float) $fee) : null;
            }
        }
        if ($name === '') {
            $error = 'Course name is required.';
        } else {
            $data = [
                'category_id' => filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                // Kept for older code paths: the first university ticked.
                'university_id' => array_key_first($offered),
                'level' => isset(COURSE_LEVELS[request_value($_POST, 'level')]) ? request_value($_POST, 'level') : guess_course_level($name),
                'full_name' => mb_substr(trim(request_value($_POST, 'full_name')), 0, 190) ?: null,
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
                $sql = 'UPDATE courses SET category_id=:category_id,university_id=:university_id,level=:level,full_name=:full_name,name=:name,slug=:slug,duration=:duration,fees_min=:fees_min,fees_max=:fees_max,eligibility=:eligibility,description=:description,is_active=:is_active WHERE id=:id';
            } else {
                $sql = 'INSERT INTO courses (category_id,university_id,level,full_name,name,slug,duration,fees_min,fees_max,eligibility,description,is_active) VALUES (:category_id,:university_id,:level,:full_name,:name,:slug,:duration,:fees_min,:fees_max,:eligibility,:description,:is_active)';
            }
            $pdo->beginTransaction();
            $pdo->prepare($sql)->execute($data);
            $courseId = $id ?: (int) $pdo->lastInsertId();
            $pdo->prepare('DELETE FROM course_universities WHERE course_id = :id')->execute(['id' => $courseId]);
            $link = $pdo->prepare('INSERT INTO course_universities (course_id, university_id, fee) VALUES (:course, :university, :fee)');
            foreach ($offered as $universityId => $fee) {
                $link->execute(['course' => $courseId, 'university' => $universityId, 'fee' => $fee]);
            }
            $pdo->commit();
            $offeredCount = count($offered);
            flash($offeredCount
                ? 'Course saved and offered by ' . $offeredCount . ' universit' . ($offeredCount === 1 ? 'y.' : 'ies.')
                : 'Course saved. Tick the universities that offer it so it appears on their pages.');
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
$offeredFees = [];
if ($edit) {
    $query = $pdo->prepare('SELECT university_id, fee FROM course_universities WHERE course_id = :id');
    $query->execute(['id' => $edit['id']]);
    $offeredFees = $query->fetchAll(PDO::FETCH_KEY_PAIR);
}
$categories = $pdo->query('SELECT * FROM course_categories ORDER BY sort_order,name')->fetchAll();
$universities = $pdo->query('SELECT id,name,city,state,is_active FROM universities ORDER BY name')->fetchAll();
$courses = $pdo->query(
    "SELECT c.*, cc.name AS category_name,
            (SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ', ') FROM course_universities cu JOIN universities u ON u.id = cu.university_id WHERE cu.course_id = c.id) AS university_names
     FROM courses c LEFT JOIN course_categories cc ON cc.id = c.category_id ORDER BY c.name"
)->fetchAll();
$adminTitle = 'Manage courses';
require __DIR__ . '/_nav.php';
?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<section class="form-card"><h2><?= $edit ? 'Edit course' : 'Add course' ?></h2><form method="post">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
<div class="form-grid"><label>Course name *<input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label><label>Slug<input name="slug" value="<?= e($edit['slug'] ?? '') ?>"></label>
<label>Full name<input name="full_name" maxlength="190" placeholder="e.g. Master of Business Administration" value="<?= e($edit['full_name'] ?? '') ?>"></label>
<label>Level (header menu column)<select name="level"><?php $currentLevel = $edit['level'] ?? ''; ?><option value="">Detect from name</option><?php foreach (COURSE_LEVELS as $levelValue => $levelLabel): ?><option value="<?= e($levelValue) ?>" <?= $currentLevel === $levelValue ? 'selected' : '' ?>><?= e($levelLabel) ?></option><?php endforeach; ?></select></label>
<label>Stream/category<select name="category_id"><option value="">Uncategorized</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($edit['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
<label>Duration<input name="duration" placeholder="e.g. 2 years" value="<?= e($edit['duration'] ?? '') ?>"></label><label>Minimum fees<input type="number" min="0" step="0.01" name="fees_min" value="<?= e((string) ($edit['fees_min'] ?? '')) ?>"></label><label>Maximum fees<input type="number" min="0" step="0.01" name="fees_max" value="<?= e((string) ($edit['fees_max'] ?? '')) ?>"></label>
<fieldset class="full uni-picker"><legend>Offered by universities</legend>
  <p class="muted small">Tick every university that offers this course. The fee is optional and specific to that university; leave it empty to use the minimum/maximum fees above.</p>
  <?php if ($universities): ?>
  <div class="uni-picker-tools"><input type="search" class="uni-picker-search" placeholder="Filter universities&hellip;" aria-label="Filter universities"><span class="uni-picker-count muted small"></span></div>
  <div class="uni-picker-list">
    <?php foreach ($universities as $university): $uid = (int) $university['id']; $checked = array_key_exists($uid, $offeredFees); $place = trim(($university['city'] ?? '') . ', ' . ($university['state'] ?? ''), ', '); ?>
    <div class="uni-pick<?= $checked ? ' is-checked' : '' ?>">
      <label><input type="checkbox" name="universities[]" value="<?= $uid ?>" <?= $checked ? 'checked' : '' ?>><span><strong><?= e($university['name']) ?></strong><?php if ($place !== '' || !$university['is_active']): ?><small><?= e(implode(' · ', array_filter([$place, $university['is_active'] ? '' : 'Hidden']))) ?></small><?php endif; ?></span></label>
      <input type="number" name="university_fees[<?= $uid ?>]" min="0" step="0.01" placeholder="Fee (&#8377;)" aria-label="Fee at <?= e($university['name']) ?>" value="<?= e((string) ($offeredFees[$uid] ?? '')) ?>" <?= $checked ? '' : 'disabled' ?>>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?><p class="muted small">No universities yet. <a class="text-link" href="<?= e(app_url('/admin/universities.php')) ?>">Add a university</a> first.</p><?php endif; ?>
</fieldset>
<label class="full">Eligibility<textarea name="eligibility" rows="3"><?= e($edit['eligibility'] ?? '') ?></textarea></label><label class="full">Description<textarea name="description" rows="3"><?= e($edit['description'] ?? '') ?></textarea></label></div>
<label><input type="checkbox" name="is_active" style="width:auto" <?= !isset($edit['is_active']) || $edit['is_active'] ? 'checked' : '' ?>> Published</label>
<button class="button" type="submit">Save course</button><?php if ($edit): ?> <a class="button button-light" href="<?= e(app_url('/admin/courses.php')) ?>">Cancel</a><?php endif; ?></form></section>
<section class="form-card" style="margin-top:20px"><h2>Add course category</h2><form method="post" class="form-grid">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_category">
<label>Category name<input name="category_name" required></label><label>Menu sort order<input type="number" name="sort_order" value="0"></label><div><button class="button" type="submit">Add category</button></div>
</form><p class="small muted">Categories organize the public Courses dropdown.</p></section>
<section class="section"><h2>Course directory</h2><div class="table-wrap card"><table><thead><tr><th>Course</th><th>Level</th><th>Category</th><th>Offered by</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($courses as $course): ?><tr><td><?= e($course['name']) ?><?php if ($course['full_name']): ?><br><small class="muted"><?= e($course['full_name']) ?></small><?php endif; ?></td><td><?= e(COURSE_LEVELS[$course['level']] ?? '—') ?></td><td><?= e($course['category_name'] ?? '—') ?></td><td class="small"><?= e($course['university_names'] ?? '—') ?></td><td><?= $course['is_active'] ? 'Published' : 'Hidden' ?></td><td><a class="text-link" href="<?= e(app_url('/admin/courses.php?edit=' . (int) $course['id'])) ?>">Edit</a>
<form class="inline-form" method="post" onsubmit="return confirm('Delete this course?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $course['id'] ?>"><button type="submit">Delete</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<script>
document.querySelectorAll('.uni-picker').forEach(function (picker) {
  var rows = picker.querySelectorAll('.uni-pick'), search = picker.querySelector('.uni-picker-search'), count = picker.querySelector('.uni-picker-count');
  if (!search) { return; }
  function update() {
    count.textContent = picker.querySelectorAll('.uni-pick input[type=checkbox]:checked').length + ' selected';
  }
  rows.forEach(function (row) {
    var box = row.querySelector('input[type=checkbox]'), fee = row.querySelector('input[type=number]');
    box.addEventListener('change', function () {
      fee.disabled = !box.checked;
      row.classList.toggle('is-checked', box.checked);
      if (box.checked) { fee.focus(); }
      update();
    });
  });
  search.addEventListener('input', function () {
    var q = search.value.trim().toLowerCase();
    rows.forEach(function (row) { row.hidden = q !== '' && row.textContent.toLowerCase().indexOf(q) === -1; });
  });
  search.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); } });
  update();
});
</script>
<?php require __DIR__ . '/_end.php'; ?>
