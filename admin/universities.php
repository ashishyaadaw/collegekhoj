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
        $query = $pdo->prepare('DELETE FROM universities WHERE id = :id');
        $query->execute(['id' => $id]);
        flash('University deleted. Associated course links were cleared.');
        header('Location: ' . app_url('/admin/universities.php'));
        exit;
    }
    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $error = 'University name is required.';
        } else {
            try {
                $current = null;
                if ($id) {
                    $query = $pdo->prepare('SELECT logo_url FROM universities WHERE id = :id');
                    $query->execute(['id' => $id]);
                    $current = $query->fetch();
                    if (!$current) {
                        throw new RuntimeException('The selected university no longer exists.');
                    }
                }
                $data = [
                    'name' => $name, 'slug' => unique_slug('universities', trim((string) ($_POST['slug'] ?? '')) ?: $name, $id),
                    'logo_url' => upload_image('logo', $current['logo_url'] ?? null),
                    'city' => trim((string) ($_POST['city'] ?? '')) ?: null,
                    'state' => trim((string) ($_POST['state'] ?? '')) ?: null,
                    'location' => trim((string) ($_POST['location'] ?? '')) ?: null,
                    'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                    'established_year' => filter_var($_POST['established_year'] ?? null, FILTER_VALIDATE_INT) ?: null,
                    'institution_type' => trim((string) ($_POST['institution_type'] ?? '')) ?: null,
                    'featured' => isset($_POST['featured']) ? 1 : 0,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ];
                if ($id) {
                    $data['id'] = $id;
                    $sql = 'UPDATE universities SET name=:name,slug=:slug,logo_url=:logo_url,city=:city,state=:state,location=:location,description=:description,established_year=:established_year,institution_type=:institution_type,featured=:featured,is_active=:is_active WHERE id=:id';
                } else {
                    $sql = 'INSERT INTO universities (name,slug,logo_url,city,state,location,description,established_year,institution_type,featured,is_active) VALUES (:name,:slug,:logo_url,:city,:state,:location,:description,:established_year,:institution_type,:featured,:is_active)';
                }
                $pdo->prepare($sql)->execute($data);
                flash('University saved.');
                header('Location: ' . app_url('/admin/universities.php'));
                exit;
            } catch (RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }
    }
}
if ($editId) {
    $query = $pdo->prepare('SELECT * FROM universities WHERE id=:id');
    $query->execute(['id' => $editId]);
    $edit = $query->fetch() ?: null;
}
$universities = $pdo->query('SELECT u.*, (SELECT COUNT(*) FROM course_universities cu WHERE cu.university_id = u.id) AS course_count FROM universities u ORDER BY u.name')->fetchAll();
$adminTitle = 'Manage universities';
require __DIR__ . '/_nav.php';
?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<section class="form-card"><h2><?= $edit ? 'Edit university' : 'Add university' ?></h2><form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
<div class="form-grid"><label>Name *<input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label><label>URL slug<input name="slug" value="<?= e($edit['slug'] ?? '') ?>"></label>
<label>Logo (JPG, PNG, WebP; max 5 MB)<input type="file" name="logo" accept="image/jpeg,image/png,image/webp"></label><label>Institution type<input name="institution_type" value="<?= e($edit['institution_type'] ?? '') ?>"></label>
<label>City<input name="city" value="<?= e($edit['city'] ?? '') ?>"></label><label>State<input name="state" value="<?= e($edit['state'] ?? '') ?>"></label>
<label>Location<input name="location" value="<?= e($edit['location'] ?? '') ?>"></label><label>Established year<input type="number" min="1800" max="<?= date('Y') ?>" name="established_year" value="<?= e((string) ($edit['established_year'] ?? '')) ?>"></label>
<label class="full">Description<textarea name="description" rows="4"><?= e($edit['description'] ?? '') ?></textarea></label></div>
<label><input type="checkbox" name="featured" style="width:auto" <?= !empty($edit['featured']) ? 'checked' : '' ?>> Featured</label>
<label><input type="checkbox" name="is_active" style="width:auto" <?= !isset($edit['is_active']) || $edit['is_active'] ? 'checked' : '' ?>> Published</label>
<button class="button" type="submit">Save university</button><?php if ($edit): ?> <a class="button button-light" href="<?= e(app_url('/admin/universities.php')) ?>">Cancel</a><?php endif; ?></form></section>
<section class="section"><h2>University directory</h2><div class="table-wrap card"><table><thead><tr><th>University</th><th>Location</th><th>Courses</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($universities as $university): ?><tr><td><?= e($university['name']) ?></td><td><?= e($university['location'] ?? '') ?></td><td><?= (int) $university['course_count'] ?></td><td><?= $university['is_active'] ? 'Published' : 'Hidden' ?><?= $university['featured'] ? ' · Featured' : '' ?></td><td><a class="text-link" href="<?= e(app_url('/admin/universities.php?edit=' . (int) $university['id'])) ?>">Edit</a>
<form class="inline-form" method="post" onsubmit="return confirm('Delete this university?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $university['id'] ?>"><button type="submit">Delete</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php require __DIR__ . '/_end.php'; ?>
