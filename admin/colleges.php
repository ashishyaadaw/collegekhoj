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
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    if ($action === 'delete' && $id) {
        $statement = $pdo->prepare('DELETE FROM colleges WHERE id = :id');
        $statement->execute(['id' => $id]);
        flash('College deleted.');
        header('Location: ' . app_url('/admin/colleges.php'));
        exit;
    }
    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $error = 'College name is required.';
        } else {
            try {
                $current = null;
                if ($id) {
                    $query = $pdo->prepare('SELECT logo_url FROM colleges WHERE id = :id');
                    $query->execute(['id' => $id]);
                    $current = $query->fetch();
                    if (!$current) {
                        throw new RuntimeException('The selected college no longer exists.');
                    }
                }
                $logo = upload_image('logo', $current['logo_url'] ?? null);
                $brochureUrl = trim(request_value($_POST, 'brochure_url'));
                if ($brochureUrl !== '' && !is_http_url($brochureUrl)) {
                    throw new RuntimeException('Brochure URL must use http or https.');
                }
                $data = [
                    'university_id' => filter_var($_POST['university_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                    'name' => $name, 'slug' => unique_slug('colleges', trim((string) ($_POST['slug'] ?? '')) ?: $name, $id),
                    'logo_url' => $logo,
                    'city' => trim((string) ($_POST['city'] ?? '')) ?: null,
                    'state' => trim((string) ($_POST['state'] ?? '')) ?: null,
                    'location' => trim((string) ($_POST['location'] ?? '')) ?: null,
                    'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                    'established_year' => filter_var($_POST['established_year'] ?? null, FILTER_VALIDATE_INT) ?: null,
                    'ranking' => filter_var($_POST['ranking'] ?? null, FILTER_VALIDATE_INT) ?: null,
                    'rating' => min(5, max(0, (float) ($_POST['rating'] ?? 0))),
                    'fees_min' => ($_POST['fees_min'] ?? '') !== '' ? max(0, (float) $_POST['fees_min']) : null,
                    'fees_max' => ($_POST['fees_max'] ?? '') !== '' ? max(0, (float) $_POST['fees_max']) : null,
                    'placement_average' => ($_POST['placement_average'] ?? '') !== '' ? max(0, (float) $_POST['placement_average']) : null,
                    'placement_rate' => ($_POST['placement_rate'] ?? '') !== '' ? min(100, max(0, (float) $_POST['placement_rate'])) : null,
                    'brochure_url' => $brochureUrl !== '' ? $brochureUrl : null,
                    'admission_process' => trim((string) ($_POST['admission_process'] ?? '')) ?: null,
                    'eligibility' => trim((string) ($_POST['eligibility'] ?? '')) ?: null,
                    'cutoff_details' => trim((string) ($_POST['cutoff_details'] ?? '')) ?: null,
                    'highlights' => trim((string) ($_POST['highlights'] ?? '')) ?: null,
                    'featured' => isset($_POST['featured']) ? 1 : 0,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ];
                $postedExamIds = $_POST['exam_ids'] ?? [];
                $examIds = [];
                if (is_array($postedExamIds)) {
                    foreach ($postedExamIds as $examId) {
                        if (!is_string($examId) && !is_int($examId)) {
                            continue;
                        }
                        $validExamId = filter_var((string) $examId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                        if ($validExamId !== false) {
                            $examIds[] = (int) $validExamId;
                        }
                    }
                }
                $examIds = array_values(array_unique($examIds));
                if ($id) {
                    $data['id'] = $id;
                    $sql = 'UPDATE colleges SET university_id=:university_id,name=:name,slug=:slug,logo_url=:logo_url,city=:city,state=:state,location=:location,description=:description,established_year=:established_year,ranking=:ranking,rating=:rating,fees_min=:fees_min,fees_max=:fees_max,placement_average=:placement_average,placement_rate=:placement_rate,brochure_url=:brochure_url,admission_process=:admission_process,eligibility=:eligibility,cutoff_details=:cutoff_details,highlights=:highlights,featured=:featured,is_active=:is_active WHERE id=:id';
                } else {
                    $sql = 'INSERT INTO colleges (university_id,name,slug,logo_url,city,state,location,description,established_year,ranking,rating,fees_min,fees_max,placement_average,placement_rate,brochure_url,admission_process,eligibility,cutoff_details,highlights,featured,is_active) VALUES (:university_id,:name,:slug,:logo_url,:city,:state,:location,:description,:established_year,:ranking,:rating,:fees_min,:fees_max,:placement_average,:placement_rate,:brochure_url,:admission_process,:eligibility,:cutoff_details,:highlights,:featured,:is_active)';
                }
                $pdo->beginTransaction();
                try {
                    $pdo->prepare($sql)->execute($data);
                    $savedCollegeId = $id ?? (int) $pdo->lastInsertId();
                    $pdo->prepare('DELETE FROM college_exams WHERE college_id=:college_id')->execute(['college_id' => $savedCollegeId]);
                    $validExams = $pdo->query('SELECT id FROM exams')->fetchAll(PDO::FETCH_COLUMN);
                    $insertExam = $pdo->prepare('INSERT INTO college_exams (college_id,exam_id) VALUES (:college_id,:exam_id)');
                    foreach ($examIds as $examId) {
                        if (in_array($examId, array_map('intval', $validExams), true)) {
                            $insertExam->execute(['college_id' => $savedCollegeId, 'exam_id' => $examId]);
                        }
                    }
                    $pdo->commit();
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
                flash('College saved.');
                header('Location: ' . app_url('/admin/colleges.php'));
                exit;
            } catch (RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }
    }
}
if ($editId) {
    $query = $pdo->prepare('SELECT * FROM colleges WHERE id = :id');
    $query->execute(['id' => $editId]);
    $edit = $query->fetch() ?: null;
}
$universities = $pdo->query('SELECT id,name FROM universities ORDER BY name')->fetchAll();
$exams = $pdo->query('SELECT id,name FROM exams ORDER BY name')->fetchAll();
$selectedExamIds = [];
if ($edit) {
    $selectedExamsQuery = $pdo->prepare('SELECT exam_id FROM college_exams WHERE college_id=:college_id');
    $selectedExamsQuery->execute(['college_id' => $edit['id']]);
    $selectedExamIds = array_map('intval', $selectedExamsQuery->fetchAll(PDO::FETCH_COLUMN));
}
$colleges = $pdo->query('SELECT c.id,c.name,c.slug,c.city,c.state,c.featured,c.is_active,u.name AS university_name FROM colleges c LEFT JOIN universities u ON u.id=c.university_id ORDER BY c.name')->fetchAll();
$adminTitle = 'Manage colleges';
require __DIR__ . '/_nav.php';
?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<section class="form-card"><h2><?= $edit ? 'Edit college' : 'Add college' ?></h2>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
  <div class="form-grid">
    <label>College name *<input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
    <label>URL slug<input name="slug" value="<?= e($edit['slug'] ?? '') ?>" placeholder="Generated from name"></label>
    <label>University<select name="university_id"><option value="">Not linked</option><?php foreach ($universities as $university): ?><option value="<?= (int) $university['id'] ?>" <?= (int) ($edit['university_id'] ?? 0) === (int) $university['id'] ? 'selected' : '' ?>><?= e($university['name']) ?></option><?php endforeach; ?></select></label>
    <label>Logo image (JPG, PNG, WebP; max 5 MB)<input type="file" name="logo" accept="image/jpeg,image/png,image/webp"></label>
    <label>City<input name="city" value="<?= e($edit['city'] ?? '') ?>"></label><label>State<input name="state" value="<?= e($edit['state'] ?? '') ?>"></label>
    <label>Location<input name="location" value="<?= e($edit['location'] ?? '') ?>"></label><label>Established year<input type="number" min="1800" max="<?= date('Y') ?>" name="established_year" value="<?= e((string) ($edit['established_year'] ?? '')) ?>"></label>
    <label>Ranking<input type="number" min="1" name="ranking" value="<?= e((string) ($edit['ranking'] ?? '')) ?>"></label>
    <label>Rating (0–5)<input type="number" min="0" max="5" step="0.1" name="rating" value="<?= e((string) ($edit['rating'] ?? '0')) ?>"></label>
    <label>Minimum fees<input type="number" min="0" step="0.01" name="fees_min" value="<?= e((string) ($edit['fees_min'] ?? '')) ?>"></label><label>Maximum fees<input type="number" min="0" step="0.01" name="fees_max" value="<?= e((string) ($edit['fees_max'] ?? '')) ?>"></label>
    <label>Average placement package<input type="number" min="0" step="0.01" name="placement_average" value="<?= e((string) ($edit['placement_average'] ?? '')) ?>"></label><label>Placement rate (%)<input type="number" min="0" max="100" step="0.01" name="placement_rate" value="<?= e((string) ($edit['placement_rate'] ?? '')) ?>"></label>
    <label class="full">Brochure URL<input type="url" name="brochure_url" value="<?= e($edit['brochure_url'] ?? '') ?>"></label>
    <label class="full">Description<textarea name="description" rows="4"><?= e($edit['description'] ?? '') ?></textarea></label>
    <label class="full">Highlights<textarea name="highlights" rows="3"><?= e($edit['highlights'] ?? '') ?></textarea></label>
    <label class="full">Eligibility<textarea name="eligibility" rows="3"><?= e($edit['eligibility'] ?? '') ?></textarea></label>
    <label class="full">Admission process<textarea name="admission_process" rows="3"><?= e($edit['admission_process'] ?? '') ?></textarea></label>
    <label class="full">Cutoff details and source/session<textarea name="cutoff_details" rows="3" placeholder="Include exam, category, year and a reliable source."><?= e($edit['cutoff_details'] ?? '') ?></textarea></label>
    <label class="full">Associated entrance exams<select name="exam_ids[]" multiple size="4"><?php foreach ($exams as $exam): ?>
      <option value="<?= (int) $exam['id'] ?>" <?= in_array((int) $exam['id'], $selectedExamIds, true) ? 'selected' : '' ?>><?= e($exam['name']) ?></option>
    <?php endforeach; ?></select></label>
  </div>
  <label><input type="checkbox" name="featured" style="width:auto" <?= !empty($edit['featured']) ? 'checked' : '' ?>> Featured</label>
  <label><input type="checkbox" name="is_active" style="width:auto" <?= !isset($edit['is_active']) || $edit['is_active'] ? 'checked' : '' ?>> Published</label>
  <button class="button" type="submit">Save college</button><?php if ($edit): ?> <a class="button button-light" href="<?= e(app_url('/admin/colleges.php')) ?>">Cancel</a><?php endif; ?>
</form></section>
<section class="section"><h2>College directory</h2><div class="table-wrap card"><table><thead><tr><th>College</th><th>University</th><th>Location</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($colleges as $college): ?><tr><td><?= e($college['name']) ?></td><td><?= e($college['university_name'] ?? '—') ?></td><td><?= e(trim(($college['city'] ?? '') . ', ' . ($college['state'] ?? ''), ', ')) ?></td><td><?= $college['is_active'] ? 'Published' : 'Hidden' ?><?= $college['featured'] ? ' · Featured' : '' ?></td><td>
<a class="text-link" href="<?= e(app_url('/admin/colleges.php?edit=' . (int) $college['id'])) ?>">Edit</a>
<form class="inline-form" method="post" onsubmit="return confirm('Delete this college?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $college['id'] ?>"><button type="submit">Delete</button></form>
</td></tr><?php endforeach; ?></tbody></table></div></section>
<?php require __DIR__ . '/_end.php'; ?>
