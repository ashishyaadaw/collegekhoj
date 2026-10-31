<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$requestedIds = $_GET['ids'] ?? [];
$ids = [];
if (is_array($requestedIds)) {
    foreach ($requestedIds as $value) {
        if (!is_string($value) && !is_int($value)) {
            continue;
        }
        $validatedId = filter_var((string) $value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validatedId !== false) {
            $ids[] = (int) $validatedId;
        }
    }
}
$ids = array_slice(array_values(array_unique($ids)), 0, 3);
$colleges = [];
if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = db()->prepare(
        'SELECT c.*, u.name AS university_name FROM colleges c
         LEFT JOIN universities u ON u.id = c.university_id
         WHERE c.is_active = 1 AND c.id IN (' . $placeholders . ')'
    );
    $statement->execute($ids);
    $colleges = $statement->fetchAll();
}
$pageTitle = 'Compare Colleges | Collegekhoj';
require __DIR__ . '/includes/header.php';
?>
<section class="section"><div class="container">
  <span class="eyebrow">Make an informed choice</span><h1 style="color:var(--navy)">Compare colleges</h1>
  <?php if (count($colleges) < 2): ?><div class="card"><p>Select two or three colleges from the <a class="text-link" href="<?= e(app_url('/colleges.php')) ?>">college listing</a> to compare their fees, ratings, placements and courses side by side.</p></div>
  <?php else: ?><div class="table-wrap detail-card"><table><thead><tr><th>Compare</th><?php foreach ($colleges as $college): ?><th><a class="text-link" href="<?= e(app_url('/college-detail.php?slug=' . rawurlencode($college['slug']))) ?>"><?= e($college['name']) ?></a></th><?php endforeach; ?></tr></thead><tbody>
    <?php
    $fields = [
        'University' => static fn ($c) => e($c['university_name'] ?? '—'),
        'Location' => static fn ($c) => e($c['location'] ?? trim(($c['city'] ?? '') . ', ' . ($c['state'] ?? ''), ', ') ?: '—'),
        'Rating' => static fn ($c) => '★ ' . e((string) $c['rating']),
        'Annual / total fees' => static fn ($c) => ($c['fees_min'] !== null ? '₹' . number_format((float) $c['fees_min']) : '—') . ($c['fees_max'] !== null ? ' – ₹' . number_format((float) $c['fees_max']) : ''),
        'Average placement' => static fn ($c) => $c['placement_average'] ? '₹' . number_format((float) $c['placement_average']) : 'Not provided',
        'Placement rate' => static fn ($c) => $c['placement_rate'] ? e((string) $c['placement_rate']) . '%' : 'Not provided',
        'Established' => static fn ($c) => e((string) ($c['established_year'] ?? 'Not provided')),
    ];
    foreach ($fields as $label => $format): ?><tr><th><?= e($label) ?></th><?php foreach ($colleges as $college): ?><td><?= $format($college) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
    <tr><th>Courses</th><?php foreach ($colleges as $college):
      $courseQuery = db()->prepare('SELECT name FROM courses WHERE college_id = :id AND is_active = 1 ORDER BY name');
      $courseQuery->execute(['id' => $college['id']]);
      ?><td><?= e(implode(', ', array_column($courseQuery->fetchAll(), 'name')) ?: 'Not provided') ?></td><?php endforeach; ?></tr>
  </tbody></table></div><?php endif; ?>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
