<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$pdo = db();
ensure_catalog_schema($pdo);
$query = $pdo->prepare('SELECT * FROM universities WHERE slug = :slug AND is_active = 1 LIMIT 1');
$query->execute(['slug' => trim(request_value($_GET, 'slug'))]);
$university = $query->fetch();
if (!$university) {
    render_not_found('University');
}
$query = $pdo->prepare(
    'SELECT c.id, c.name, c.slug, c.full_name, c.level, c.duration, c.fees_min, c.fees_max, cu.fee
     FROM course_universities cu JOIN courses c ON c.id = cu.course_id AND c.is_active = 1
     WHERE cu.university_id = :id ORDER BY FIELD(c.level, \'pg\', \'ug\', \'other\'), c.name'
);
$query->execute(['id' => $university['id']]);
$courses = $query->fetchAll();
$coursesByLevel = [];
foreach ($courses as $course) {
    $coursesByLevel[$course['level']][] = $course;
}
$others = $pdo->prepare('SELECT name, slug, city, state FROM universities WHERE is_active = 1 AND id <> :id ORDER BY featured DESC, name LIMIT 4');
$others->execute(['id' => $university['id']]);
$others = $others->fetchAll();

$place = $university['location'] ?: trim(($university['city'] ?? '') . ', ' . ($university['state'] ?? ''), ', ');
$approvals = array_filter(array_map('trim', explode(',', (string) $university['approvals'])));
$highlights = text_lines($university['highlights']);

$pageTitle = $university['meta_title'] ?: $university['name'] . ': Courses, Fees, Approvals & Admission | Collegekhoj';
$pageDescription = $university['meta_description'] ?: mb_strimwidth(trim($university['name'] . ' – ' . strip_tags($university['description'] ?? '') . ' Explore courses offered and get free admission counselling.'), 0, 300, '…');
$pageCanonical = university_url($university['slug']);
require __DIR__ . '/includes/header.php';
echo json_ld(array_filter([
    '@context' => 'https://schema.org', '@type' => 'CollegeOrUniversity', 'name' => $university['name'],
    'description' => $university['description'], 'url' => absolute_url($pageCanonical),
    'logo' => $university['logo_url'] ? absolute_url($university['logo_url']) : null,
    'foundingDate' => $university['established_year'] ? (string) $university['established_year'] : null,
    'address' => $place !== '' ? array_filter(['@type' => 'PostalAddress', 'addressLocality' => $university['city'], 'addressRegion' => $university['state'], 'addressCountry' => 'IN']) : null,
]));
?>
<section class="detail-hero"><div class="container">
  <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= e(app_url('/')) ?>">Home</a><span aria-hidden="true">/</span><a href="<?= e(app_url('/#universities')) ?>">Universities</a><span aria-hidden="true">/</span><span aria-current="page"><?= e($university['name']) ?></span></nav>
  <div class="uni-hero">
    <?php if ($university['logo_url']): ?><img class="uni-hero-logo" src="<?= e($university['logo_url']) ?>" alt="<?= e($university['name']) ?> logo">
    <?php else: ?><span class="uni-hero-logo uni-hero-initials" aria-hidden="true"><?= e(institution_initials($university['name'])) ?></span><?php endif; ?>
    <div>
      <h1><?= e($university['name']) ?></h1>
      <p class="detail-sub"><?= e(implode(' · ', array_filter([$place, $university['institution_type'], $university['established_year'] ? 'Est. ' . $university['established_year'] : null]))) ?></p>
      <?php if ($approvals): ?><div class="detail-pills"><?php foreach ($approvals as $approval): ?><span class="pill pill-approval">✓ <?= e($approval) ?></span><?php endforeach; ?></div><?php endif; ?>
    </div>
  </div>
  <div class="hero-actions">
    <button class="button button-cta button-hero" type="button" data-counselling data-university="<?= (int) $university['id'] ?>"><?= e(site_setting('hero_button_label')) ?> <span aria-hidden="true">→</span></button>
    <button class="button button-light button-hero" type="button" data-open-modal="expert-modal"><?= ui_icon('phone') ?><?= e(site_setting('header_button_label')) ?></button>
  </div>
</div></section>

<div class="container detail-layout">
  <div class="detail-main">
    <section class="detail-card" id="about"><h2>About <?= e($university['name']) ?></h2>
      <?= text_paragraphs($university['description']) ?: '<p>Contact our counsellors for complete details about this university.</p>' ?>
      <?php if ($highlights): ?><h3>Why choose <?= e($university['name']) ?></h3><ul class="check-list"><?php foreach ($highlights as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </section>

    <section class="detail-card" id="courses"><h2>Courses offered</h2>
      <?php if (!$courses): ?><p>Course details for this university will be published soon. Our counsellors can share the current list.</p><?php endif; ?>
      <?php foreach ($coursesByLevel as $level => $levelCourses): ?>
        <h3 class="level-head"><span class="mc-level mc-level-<?= e($level) ?>"><?= e(strtoupper($level === 'other' ? 'More' : $level)) ?></span><?= e(COURSE_LEVELS[$level] ?? 'Programs') ?></h3>
        <div class="uni-course-grid"><?php foreach ($levelCourses as $course): $courseFee = $course['fee'] !== null ? format_inr($course['fee']) : fee_range($course['fees_min'], $course['fees_max']); ?>
          <article class="uni-course"><div class="uni-course-top"><span class="mini-icon" aria-hidden="true"><?= course_icon($course['name']) ?></span>
            <div><h4><a href="<?= e(course_url($course['slug'])) ?>"><?= e($course['name']) ?></a></h4><?php if ($course['full_name']): ?><small><?= e($course['full_name']) ?></small><?php endif; ?></div></div>
            <dl><?php if ($course['duration']): ?><div><dt>Duration</dt><dd><?= e($course['duration']) ?></dd></div><?php endif; ?><div><dt>Fees</dt><dd><?= e($courseFee ?? 'On request') ?></dd></div></dl>
            <div class="uni-course-actions"><a class="text-link" href="<?= e(course_url($course['slug'])) ?>">View course →</a>
              <button class="button button-cta button-small" type="button" data-counselling data-course="<?= (int) $course['id'] ?>" data-university="<?= (int) $university['id'] ?>">Enquire</button></div>
          </article>
        <?php endforeach; ?></div>
      <?php endforeach; ?>
    </section>

    <section class="cta-band"><div><h2>Planning to join <?= e($university['name']) ?>?</h2><p>Get free, personalised guidance on courses, fees and the admission process.</p></div>
      <button class="button button-cta" type="button" data-counselling data-university="<?= (int) $university['id'] ?>"><?= e(site_setting('form_button_label')) ?> <span aria-hidden="true">→</span></button></section>

    <?php if ($others): ?><section class="related"><h2>Other universities</h2><div class="related-grid">
      <?php foreach ($others as $item): ?><a class="related-card" href="<?= e(university_url($item['slug'])) ?>"><span class="mini-icon uni-mini" aria-hidden="true"><?= e(institution_initials($item['name'])) ?></span>
        <span><strong><?= e($item['name']) ?></strong><small><?= e(trim(($item['city'] ?? '') . ', ' . ($item['state'] ?? ''), ', ')) ?></small></span></a><?php endforeach; ?>
    </div></section><?php endif; ?>
  </div>

  <aside class="detail-aside"><div class="aside-card">
    <h2>Get free counselling</h2><p>Our experts will help you choose the right programme at <?= e($university['name']) ?>.</p>
    <ul class="aside-facts">
      <?php if ($place !== ''): ?><li><span>Location</span><strong><?= e($place) ?></strong></li><?php endif; ?>
      <?php if ($university['institution_type']): ?><li><span>Type</span><strong><?= e($university['institution_type']) ?></strong></li><?php endif; ?>
      <?php if ($university['established_year']): ?><li><span>Established</span><strong><?= (int) $university['established_year'] ?></strong></li><?php endif; ?>
      <li><span>Courses</span><strong><?= count($courses) ?></strong></li>
    </ul>
    <button class="button button-cta" type="button" data-counselling data-university="<?= (int) $university['id'] ?>"><?= e(site_setting('hero_button_label')) ?> <span aria-hidden="true">→</span></button>
    <button class="button button-light" type="button" data-open-modal="expert-modal"><?= ui_icon('phone') ?><?= e(site_setting('header_button_label')) ?></button>
  </div></aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
