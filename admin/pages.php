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
        $pdo->prepare('DELETE FROM cms_pages WHERE id=:id')->execute(['id' => $id]);
        flash('Page deleted.');
        header('Location: ' . app_url('/admin/pages.php'));
        exit;
    }
    if ($action === 'save') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($title === '' || $body === '') {
            $error = 'Page title and page content are required.';
        } else {
            $data = [
                'title' => $title, 'slug' => unique_slug('cms_pages', trim((string) ($_POST['slug'] ?? '')) ?: $title, $id),
                'body' => $body, 'meta_title' => trim((string) ($_POST['meta_title'] ?? '')) ?: null,
                'meta_description' => trim((string) ($_POST['meta_description'] ?? '')) ?: null,
                'meta_keywords' => trim((string) ($_POST['meta_keywords'] ?? '')) ?: null,
                'status' => ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft',
            ];
            if ($id) {
                $data['id'] = $id;
                $sql = 'UPDATE cms_pages SET title=:title,slug=:slug,body=:body,meta_title=:meta_title,meta_description=:meta_description,meta_keywords=:meta_keywords,status=:status WHERE id=:id';
            } else {
                $sql = 'INSERT INTO cms_pages (title,slug,body,meta_title,meta_description,meta_keywords,status) VALUES (:title,:slug,:body,:meta_title,:meta_description,:meta_keywords,:status)';
            }
            $pdo->prepare($sql)->execute($data);
            flash('Page saved.');
            header('Location: ' . app_url('/admin/pages.php'));
            exit;
        }
    }
}
if ($editId) {
    $query = $pdo->prepare('SELECT * FROM cms_pages WHERE id=:id');
    $query->execute(['id' => $editId]);
    $edit = $query->fetch() ?: null;
}
$pages = $pdo->query('SELECT id,title,slug,status,updated_at FROM cms_pages ORDER BY updated_at DESC')->fetchAll();
$adminTitle = 'Manage pages & SEO';
require __DIR__ . '/_nav.php';
?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<section class="form-card"><h2><?= $edit ? 'Edit page' : 'Create page' ?></h2>
<p class="small muted">Content HTML is rendered on the public page. Only paste content from trusted sources; admin-edited HTML can run in visitors' browsers.</p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
<div class="form-grid"><label>Page title *<input name="title" required value="<?= e($edit['title'] ?? '') ?>"></label><label>URL slug<input name="slug" value="<?= e($edit['slug'] ?? '') ?>"></label>
<label>Meta title<input name="meta_title" maxlength="190" value="<?= e($edit['meta_title'] ?? '') ?>"></label><label>Meta description<input name="meta_description" maxlength="320" value="<?= e($edit['meta_description'] ?? '') ?>"></label>
<label class="full">Meta keywords (optional)<input name="meta_keywords" maxlength="500" value="<?= e($edit['meta_keywords'] ?? '') ?>"></label>
<label>Status<select name="status"><option value="draft" <?= ($edit['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option><option value="published" <?= ($edit['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option></select></label>
<label class="full">Page HTML *<textarea name="body" rows="14" required><?= e($edit['body'] ?? '') ?></textarea></label></div>
<button class="button" type="submit">Save page</button><?php if ($edit): ?> <a class="button button-light" href="<?= e(app_url('/admin/pages.php')) ?>">Cancel</a><?php endif; ?></form></section>
<section class="section"><h2>Pages</h2><div class="table-wrap card"><table><thead><tr><th>Title</th><th>Slug</th><th>Status</th><th>Public link</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($pages as $page): ?><tr><td><?= e($page['title']) ?></td><td><?= e($page['slug']) ?></td><td><?= e($page['status']) ?></td><td><a class="text-link" href="<?= e(app_url('/page.php?slug=' . rawurlencode($page['slug']))) ?>">View</a></td><td><a class="text-link" href="<?= e(app_url('/admin/pages.php?edit=' . (int) $page['id'])) ?>">Edit</a>
<form class="inline-form" method="post" onsubmit="return confirm('Delete this page?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $page['id'] ?>"><button type="submit">Delete</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php require __DIR__ . '/_end.php'; ?>
