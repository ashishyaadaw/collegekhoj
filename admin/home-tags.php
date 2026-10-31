<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/home_tags.php';
require_admin();
$pdo = db();
ensure_home_tags_table($pdo);
$error = '';
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT) ?: null;
$edit = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    if ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM home_tags WHERE id=:id')->execute(['id' => $id]);
        flash('Tag deleted.');
        header('Location: ' . app_url('/admin/home-tags.php'));
        exit;
    }
    if ($action === 'toggle' && $id) {
        $pdo->prepare('UPDATE home_tags SET is_active = 1 - is_active WHERE id=:id')->execute(['id' => $id]);
        flash('Tag visibility updated.');
        header('Location: ' . app_url('/admin/home-tags.php'));
        exit;
    }
    if ($action === 'suggest') {
        $added = add_home_tag_suggestions($pdo);
        flash($added ? $added . ' suggested tag' . ($added === 1 ? '' : 's') . ' added from the catalog.' : 'No new suggestions: every catalog link already has a tag.');
        header('Location: ' . app_url('/admin/home-tags.php'));
        exit;
    }
    if ($action === 'save') {
        $group = trim(request_value($_POST, 'group_name'));
        $label = trim(request_value($_POST, 'label'));
        $url = trim(request_value($_POST, 'url'));
        if ($group === '' || $label === '' || $url === '') {
            $error = 'Group, label and link are required.';
        } elseif (mb_strlen($group) > 80 || mb_strlen($label) > 120 || strlen($url) > 500) {
            $error = 'Group (80), label (120) or link (500) is too long.';
        } elseif (!is_valid_home_tag_url($url)) {
            $error = 'Link must be a site path starting with / (for example /colleges.php?city=Jaipur) or a full http(s) URL.';
        } else {
            $data = [
                'group_name' => $group, 'label' => $label, 'url' => $url,
                'sort_order' => request_int($_POST, 'sort_order') ?? 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];
            if ($id) {
                $data['id'] = $id;
                $sql = 'UPDATE home_tags SET group_name=:group_name,label=:label,url=:url,sort_order=:sort_order,is_active=:is_active WHERE id=:id';
            } else {
                $sql = 'INSERT INTO home_tags (group_name,label,url,sort_order,is_active) VALUES (:group_name,:label,:url,:sort_order,:is_active)';
            }
            $pdo->prepare($sql)->execute($data);
            flash('Tag saved.');
            header('Location: ' . app_url('/admin/home-tags.php'));
            exit;
        }
    }
}
if ($editId) {
    $query = $pdo->prepare('SELECT * FROM home_tags WHERE id=:id');
    $query->execute(['id' => $editId]);
    $edit = $query->fetch() ?: null;
}
$tags = $pdo->query(
    'SELECT t.* FROM home_tags t JOIN (SELECT group_name, MIN(sort_order) AS group_order FROM home_tags GROUP BY group_name) g ON g.group_name = t.group_name
     ORDER BY g.group_order, t.group_name, t.sort_order, t.label'
)->fetchAll();
$groupNames = array_values(array_unique(array_column($tags, 'group_name')));
$nextSortOrder = $tags ? max(array_map('intval', array_column($tags, 'sort_order'))) + 10 : 10;
$form = $edit ?? ($error ? $_POST : []);
$adminTitle = 'Home page tags';
require __DIR__ . '/_nav.php';
?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<section class="form-card"><h2><?= $edit ? 'Edit tag' : 'Add tag' ?></h2>
<p class="muted small">Tags appear in the &ldquo;Popular searches&rdquo; section just above the footer on the home page, grouped by their group name. Groups are shown in order of their lowest sort order.</p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
<div class="form-grid">
  <label>Group *<input name="group_name" required maxlength="80" list="home-tag-groups" value="<?= e($form['group_name'] ?? '') ?>" placeholder="e.g. Popular courses"></label>
  <label>Label *<input name="label" required maxlength="120" value="<?= e($form['label'] ?? '') ?>" placeholder="e.g. Online MBA"></label>
  <label class="full">Link *<input name="url" required maxlength="500" value="<?= e($form['url'] ?? '') ?>" placeholder="/colleges.php?city=Jaipur or https://example.com"></label>
  <label>Sort order<input type="number" name="sort_order" value="<?= e((string) ($form['sort_order'] ?? $nextSortOrder)) ?>"></label>
</div>
<datalist id="home-tag-groups"><?php foreach ($groupNames as $groupName): ?><option value="<?= e($groupName) ?>"><?php endforeach; ?></datalist>
<label><input type="checkbox" name="is_active" style="width:auto" <?= !isset($form['group_name']) || !empty($form['is_active']) ? 'checked' : '' ?>> Show on home page</label>
<button class="button" type="submit">Save tag</button><?php if ($edit): ?> <a class="button button-light" href="<?= e(app_url('/admin/home-tags.php')) ?>">Cancel</a><?php endif; ?>
</form></section>
<section class="section">
  <div class="section-heading"><h2 style="margin:0">Tags (<?= count($tags) ?>)</h2>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="suggest"><button class="button button-light" type="submit" title="Adds a tag for each course, stream, university, location and linked exam that does not have one yet">Add suggested tags from catalog</button></form>
  </div>
  <div class="table-wrap card"><table><thead><tr><th>Group</th><th>Label</th><th>Link</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($tags as $tag): ?><tr>
    <td><?= e($tag['group_name']) ?></td><td><?= e($tag['label']) ?></td>
    <td class="small"><a class="text-link" href="<?= e(home_tag_href($tag['url'])) ?>" target="_blank" rel="noopener"><?= e($tag['url']) ?></a></td>
    <td><?= (int) $tag['sort_order'] ?></td><td><?= $tag['is_active'] ? 'Visible' : 'Hidden' ?></td>
    <td style="white-space:nowrap"><a class="text-link" href="<?= e(app_url('/admin/home-tags.php?edit=' . (int) $tag['id'])) ?>">Edit</a>
      <form class="inline-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $tag['id'] ?>"><button type="submit" class="adm-btn-soft"><?= $tag['is_active'] ? 'Hide' : 'Show' ?></button></form>
      <form class="inline-form" method="post" onsubmit="return confirm('Delete this tag?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $tag['id'] ?>"><button type="submit">Delete</button></form></td>
  </tr><?php endforeach; ?>
  <?php if (!$tags): ?><tr><td colspan="6" class="muted">No tags yet. Add one above, or use &ldquo;Add suggested tags from catalog&rdquo;.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
<?php require __DIR__ . '/_end.php'; ?>
