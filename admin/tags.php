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
        $pdo->prepare('DELETE FROM tags_manager WHERE id=:id')->execute(['id' => $id]);
        flash('Tracking tag deleted.');
        header('Location: ' . app_url('/admin/tags.php'));
        exit;
    }
    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $targetType = ($_POST['target_type'] ?? '') === 'page' ? 'page' : 'global';
        $targetValue = trim((string) ($_POST['target_value'] ?? ''));
        if ($name === '' || ($targetType === 'page' && $targetValue === '')) {
            $error = 'Name and, for page-specific tags, a page slug are required.';
        } else {
            $data = [
                'name' => $name, 'target_type' => $targetType,
                'target_value' => $targetType === 'page' ? $targetValue : null,
                'head_code' => trim((string) ($_POST['head_code'] ?? '')) ?: null,
                'body_code' => trim((string) ($_POST['body_code'] ?? '')) ?: null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];
            if ($id) {
                $data['id'] = $id;
                $sql = 'UPDATE tags_manager SET name=:name,target_type=:target_type,target_value=:target_value,head_code=:head_code,body_code=:body_code,is_active=:is_active WHERE id=:id';
            } else {
                $sql = 'INSERT INTO tags_manager (name,target_type,target_value,head_code,body_code,is_active) VALUES (:name,:target_type,:target_value,:head_code,:body_code,:is_active)';
            }
            $pdo->prepare($sql)->execute($data);
            flash('Tracking tag configuration saved.');
            header('Location: ' . app_url('/admin/tags.php'));
            exit;
        }
    }
}
if ($editId) {
    $query = $pdo->prepare('SELECT * FROM tags_manager WHERE id=:id');
    $query->execute(['id' => $editId]);
    $edit = $query->fetch() ?: null;
}
$tags = $pdo->query('SELECT * FROM tags_manager ORDER BY name')->fetchAll();
$adminTitle = 'Tag manager';
require __DIR__ . '/_nav.php';
?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<div class="notice error"><strong>Security:</strong> Tracking snippets execute as JavaScript/HTML on public pages. Only add code from trusted providers and restrict CMS access to trusted administrators.</div>
<section class="form-card"><h2><?= $edit ? 'Edit tag' : 'Add tracking tag' ?></h2>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
<div class="form-grid"><label>Configuration name<input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
<label>Apply to<select name="target_type"><option value="global" <?= ($edit['target_type'] ?? 'global') === 'global' ? 'selected' : '' ?>>All pages</option><option value="page" <?= ($edit['target_type'] ?? '') === 'page' ? 'selected' : '' ?>>One page</option></select></label>
<label class="full">Page slug (when page-specific)<input name="target_value" value="<?= e($edit['target_value'] ?? '') ?>" placeholder="index or CMS page slug"></label>
<label class="full">HTML / JavaScript for &lt;head&gt;<textarea name="head_code" rows="8" spellcheck="false"><?= e($edit['head_code'] ?? '') ?></textarea></label>
<label class="full">HTML / JavaScript for &lt;body&gt;<textarea name="body_code" rows="8" spellcheck="false"><?= e($edit['body_code'] ?? '') ?></textarea></label></div>
<label><input type="checkbox" name="is_active" style="width:auto" <?= !isset($edit['is_active']) || $edit['is_active'] ? 'checked' : '' ?>> Active</label>
<button class="button" type="submit">Save tag</button><?php if ($edit): ?> <a class="button button-light" href="<?= e(app_url('/admin/tags.php')) ?>">Cancel</a><?php endif; ?></form></section>
<section class="section"><h2>Configured tags</h2><div class="table-wrap card"><table><thead><tr><th>Name</th><th>Scope</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($tags as $tag): ?><tr><td><?= e($tag['name']) ?></td><td><?= $tag['target_type'] === 'global' ? 'All pages' : 'Page: ' . e($tag['target_value']) ?></td><td><?= $tag['is_active'] ? 'Active' : 'Disabled' ?></td><td><a class="text-link" href="<?= e(app_url('/admin/tags.php?edit=' . (int) $tag['id'])) ?>">Edit</a>
<form class="inline-form" method="post" onsubmit="return confirm('Delete this tag configuration?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $tag['id'] ?>"><button type="submit">Delete</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php require __DIR__ . '/_end.php'; ?>
