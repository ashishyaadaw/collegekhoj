<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$pdo = db();
ensure_leads_table($pdo);

$status = request_value($_GET, 'status');
$status = isset(LEAD_STATUSES[$status]) ? $status : '';
$search = trim(request_value($_GET, 'q'));
$listQuery = array_filter(['status' => $status, 'q' => $search], static fn (string $v): bool => $v !== '');
$listUrl = app_url('/admin/leads.php' . ($listQuery ? '?' . http_build_query($listQuery) : ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = request_value($_POST, 'action');
    $id = request_int($_POST, 'id');
    if ($action === 'update' && $id) {
        $newStatus = request_value($_POST, 'status');
        if (isset(LEAD_STATUSES[$newStatus])) {
            $pdo->prepare('UPDATE leads SET status=:status, notes=:notes WHERE id=:id')
                ->execute(['status' => $newStatus, 'notes' => mb_substr(trim(request_value($_POST, 'notes')), 0, 2000) ?: null, 'id' => $id]);
            flash('Lead updated.');
        }
    } elseif ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM leads WHERE id=:id')->execute(['id' => $id]);
        flash('Lead deleted.');
    }
    header('Location: ' . $listUrl);
    exit;
}

$conditions = [];
$parameters = [];
if ($status !== '') {
    $conditions[] = 'status = :status';
    $parameters['status'] = $status;
}
if ($search !== '') {
    $conditions[] = '(name LIKE :q_name OR email LIKE :q_email OR phone LIKE :q_phone OR city LIKE :q_city OR course_name LIKE :q_course OR university_name LIKE :q_university)';
    foreach (['q_name', 'q_email', 'q_phone', 'q_city', 'q_course', 'q_university'] as $key) {
        $parameters[$key] = '%' . $search . '%';
    }
}
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

if (request_value($_GET, 'export') === 'csv') {
    $query = $pdo->prepare('SELECT id, created_at, name, email, phone, city, course_name, university_name, status, notes, source_page FROM leads' . $where . ' ORDER BY created_at DESC');
    $query->execute($parameters);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows names correctly.
    fputcsv($out, ['ID', 'Received', 'Name', 'Email', 'Phone', 'City', 'Course', 'University', 'Status', 'Notes', 'Page']);
    while ($row = $query->fetch()) {
        // Prefix values Excel would treat as formulas.
        fputcsv($out, array_map(static fn ($v): string => preg_match('/\A[=+\-@]/', (string) $v) ? "'" . $v : (string) $v, $row));
    }
    exit;
}

$perPage = 50;
$countQuery = $pdo->prepare('SELECT COUNT(*) FROM leads' . $where);
$countQuery->execute($parameters);
$total = (int) $countQuery->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($pages, max(1, request_int($_GET, 'page') ?? 1));
$query = $pdo->prepare('SELECT * FROM leads' . $where . ' ORDER BY created_at DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage));
$query->execute($parameters);
$leads = $query->fetchAll();
$statusCounts = $pdo->query('SELECT status, COUNT(*) AS total FROM leads GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);

$adminTitle = 'Counselling leads';
require __DIR__ . '/_nav.php';
?>
<section class="form-card">
  <form method="get" class="form-grid" style="align-items:end">
    <label>Search<input type="search" name="q" value="<?= e($search) ?>" placeholder="Name, email, phone, city, course&hellip;"></label>
    <label>Status<select name="status"><option value="">All statuses (<?= (int) array_sum($statusCounts) ?>)</option>
      <?php foreach (LEAD_STATUSES as $value => $label): ?><option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?> (<?= (int) ($statusCounts[$value] ?? 0) ?>)</option><?php endforeach; ?>
    </select></label>
    <div><button class="button" type="submit">Filter</button>
      <?php if ($listQuery): ?> <a class="button button-light" href="<?= e(app_url('/admin/leads.php')) ?>">Reset</a><?php endif; ?>
      <a class="button button-light" href="<?= e(app_url('/admin/leads.php?' . http_build_query($listQuery + ['export' => 'csv']))) ?>">Export CSV</a></div>
  </form>
  <p class="muted small">Leads are submitted from the &ldquo;Free counselling&rdquo; form on the public site. Form labels and widgets are managed under <a class="text-link" href="<?= e(app_url('/admin/settings.php')) ?>">Counselling &amp; widgets</a>.</p>
</section>
<section class="section">
  <h2><?= $total ?> lead<?= $total === 1 ? '' : 's' ?><?= $status !== '' ? ' &middot; ' . e(LEAD_STATUSES[$status]) : '' ?></h2>
  <div class="table-wrap card"><table><thead><tr><th>Received</th><th>Student</th><th>Contact</th><th>Interest</th><th>Status &amp; notes</th><th></th></tr></thead><tbody>
  <?php foreach ($leads as $lead): $phoneDigits = preg_replace('/\D+/', '', $lead['phone']); ?><tr>
    <td class="small" style="white-space:nowrap"><?= e(date('d M Y', strtotime($lead['created_at']))) ?><br><span class="muted"><?= e(date('h:i A', strtotime($lead['created_at']))) ?></span></td>
    <td><strong><?= e($lead['name']) ?></strong><br><span class="muted small"><?= e($lead['city']) ?></span></td>
    <td class="small"><a class="text-link" href="tel:<?= e($lead['phone']) ?>"><?= e($lead['phone']) ?></a>
      &middot; <a class="text-link" href="https://wa.me/<?= e(strlen($phoneDigits) === 10 ? '91' . $phoneDigits : $phoneDigits) ?>" target="_blank" rel="noopener">WhatsApp</a><br>
      <a class="text-link" href="mailto:<?= e($lead['email']) ?>"><?= e($lead['email']) ?></a></td>
    <td class="small"><?= e($lead['course_name'] ?? '—') ?><br><span class="muted"><?= e($lead['university_name'] ?? '') ?></span>
      <?php if ($lead['source_page']): ?><br><span class="muted" title="Page the form was submitted from"><?= e($lead['source_page']) ?></span><?php endif; ?></td>
    <td><form method="post" class="lead-update"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
      <select name="status" aria-label="Status"><?php foreach (LEAD_STATUSES as $value => $label): ?><option value="<?= e($value) ?>" <?= $lead['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      <textarea name="notes" rows="2" maxlength="2000" placeholder="Notes" aria-label="Notes"><?= e($lead['notes'] ?? '') ?></textarea>
      <button type="submit" class="adm-btn-soft">Save</button></form></td>
    <td><form class="inline-form" method="post" onsubmit="return confirm('Delete this lead?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $lead['id'] ?>"><button type="submit">Delete</button></form></td>
  </tr><?php endforeach; ?>
  <?php if (!$leads): ?><tr><td colspan="6" class="muted">No leads <?= $listQuery ? 'match these filters' : 'yet' ?>.</td></tr><?php endif; ?>
  </tbody></table></div>
  <?php if ($pages > 1): ?><nav class="lead-pages" aria-label="Pages">
    <?php for ($p = 1; $p <= $pages; $p++): ?><a class="<?= $p === $page ? 'active' : '' ?>" href="<?= e(app_url('/admin/leads.php?' . http_build_query($listQuery + ['page' => $p]))) ?>"><?= $p ?></a><?php endfor; ?>
  </nav><?php endif; ?>
</section>
<?php require __DIR__ . '/_end.php'; ?>
