<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$pdo = db();
ensure_nav_links_table($pdo);
$error = '';
$editId = request_int($_GET, 'edit');
$edit = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = request_value($_POST, 'action') ?: 'save';
    $id = request_int($_POST, 'id');
    if ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM nav_links WHERE id=:id')->execute(['id' => $id]);
        flash('Menu link deleted.');
        header('Location: ' . app_url('/admin/nav-links.php'));
        exit;
    }
    if ($action === 'toggle' && $id) {
        $pdo->prepare('UPDATE nav_links SET is_active = 1 - is_active WHERE id=:id')->execute(['id' => $id]);
        flash('Menu link visibility updated.');
        header('Location: ' . app_url('/admin/nav-links.php'));
        exit;
    }
    if ($action === 'save') {
        $label = trim(request_value($_POST, 'label'));
        $subtitle = trim(request_value($_POST, 'subtitle'));
        $url = trim(request_value($_POST, 'url'));
        $icon = request_value($_POST, 'icon');
        if ($label === '' || $url === '') {
            $error = 'Label and link are required.';
        } elseif (mb_strlen($label) > 80 || mb_strlen($subtitle) > 120 || strlen($url) > 500) {
            $error = 'Label (80), subtitle (120) or link (500) is too long.';
        } elseif (!is_valid_nav_link_url($url)) {
            $error = 'Link must be a site path starting with / (for example /page.php?slug=about), a full http(s) URL, #talk-to-expert or #counselling.';
        } else {
            $data = [
                'label' => $label, 'subtitle' => $subtitle !== '' ? $subtitle : null, 'url' => $url,
                'icon' => isset(NAV_LINK_ICONS[$icon]) ? $icon : 'page',
                'sort_order' => request_int($_POST, 'sort_order') ?? 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];
            if ($id) {
                $data['id'] = $id;
                $sql = 'UPDATE nav_links SET label=:label,subtitle=:subtitle,url=:url,icon=:icon,sort_order=:sort_order,is_active=:is_active WHERE id=:id';
            } else {
                $sql = 'INSERT INTO nav_links (label,subtitle,url,icon,sort_order,is_active) VALUES (:label,:subtitle,:url,:icon,:sort_order,:is_active)';
            }
            $pdo->prepare($sql)->execute($data);
            flash('Menu link saved.');
            header('Location: ' . app_url('/admin/nav-links.php'));
            exit;
        }
    }
}
if ($editId) {
    $query = $pdo->prepare('SELECT * FROM nav_links WHERE id=:id');
    $query->execute(['id' => $editId]);
    $edit = $query->fetch() ?: null;
}
$links = $pdo->query('SELECT * FROM nav_links ORDER BY sort_order, label')->fetchAll();
$nextSortOrder = $links ? max(array_map('intval', array_column($links, 'sort_order'))) + 10 : 10;
$pages = $pdo->query("SELECT title, slug FROM cms_pages WHERE status = 'published' ORDER BY title")->fetchAll();
$form = $edit ?? ($error ? $_POST : []);
$adminTitle = 'More menu';
require __DIR__ . '/_nav.php';
?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<section class="form-card"><h2><?= $edit ? 'Edit menu link' : 'Add menu link' ?></h2>
<p class="muted small">Links appear in the &ldquo;More&rdquo; dropdown of the site header. The menu is hidden when no link is visible.</p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
<div class="form-grid">
  <label>Label *<input name="label" required maxlength="80" value="<?= e($form['label'] ?? '') ?>" placeholder="e.g. Contact Us"></label>
  <label>Subtitle<input name="subtitle" maxlength="120" value="<?= e($form['subtitle'] ?? '') ?>" placeholder="e.g. Get in touch"></label>
  <label class="full">Link *<input name="url" required maxlength="500" list="nav-link-targets" value="<?= e($form['url'] ?? '') ?>" placeholder="/page.php?slug=about, https://example.com, #talk-to-expert or #counselling">
    <small class="muted">Use <code>#talk-to-expert</code> to open the Talk to Expert popup or <code>#counselling</code> to open the counselling form.</small></label>
  <label>Icon<select name="icon"><?php foreach (NAV_LINK_ICONS as $iconValue => $iconLabel): ?><option value="<?= e($iconValue) ?>" <?= ($form['icon'] ?? 'page') === $iconValue ? 'selected' : '' ?>><?= e($iconLabel) ?></option><?php endforeach; ?></select></label>
  <label>Sort order<input type="number" name="sort_order" value="<?= e((string) ($form['sort_order'] ?? $nextSortOrder)) ?>"></label>
</div>
<datalist id="nav-link-targets"><option value="#talk-to-expert"><option value="#counselling"><?php foreach ($pages as $page): ?><option value="/page.php?slug=<?= e($page['slug']) ?>"><?= e($page['title']) ?></option><?php endforeach; ?></datalist>
<label><input type="checkbox" name="is_active" style="width:auto" <?= !isset($form['label']) || !empty($form['is_active']) ? 'checked' : '' ?>> Show in menu</label>
<button class="button" type="submit">Save link</button><?php if ($edit): ?> <a class="button button-light" href="<?= e(app_url('/admin/nav-links.php')) ?>">Cancel</a><?php endif; ?>
</form></section>
<section class="section"><h2>Menu links (<?= count($links) ?>)</h2>
  <div class="table-wrap card"><table><thead><tr><th>Label</th><th>Link</th><th>Icon</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($links as $link): ?><tr>
    <td><strong><?= e($link['label']) ?></strong><?php if ($link['subtitle']): ?><br><small class="muted"><?= e($link['subtitle']) ?></small><?php endif; ?></td>
    <td class="small"><?= e($link['url']) ?></td><td><?= e(NAV_LINK_ICONS[$link['icon']] ?? $link['icon']) ?></td>
    <td><?= (int) $link['sort_order'] ?></td><td><?= $link['is_active'] ? 'Visible' : 'Hidden' ?></td>
    <td style="white-space:nowrap"><a class="text-link" href="<?= e(app_url('/admin/nav-links.php?edit=' . (int) $link['id'])) ?>">Edit</a>
      <form class="inline-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $link['id'] ?>"><button type="submit" class="adm-btn-soft"><?= $link['is_active'] ? 'Hide' : 'Show' ?></button></form>
      <form class="inline-form" method="post" onsubmit="return confirm('Delete this menu link?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $link['id'] ?>"><button type="submit">Delete</button></form></td>
  </tr><?php endforeach; ?>
  <?php if (!$links): ?><tr><td colspan="6" class="muted">No links yet. Add one above.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
<?php require __DIR__ . '/_end.php'; ?>
