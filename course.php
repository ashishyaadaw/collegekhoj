<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$pdo = db();
ensure_catalog_schema($pdo);
$query = $pdo->prepare(
    'SELECT c.*, cc.name AS category FROM courses c LEFT JOIN course_categories cc ON cc.id = c.category_id
     WHERE c.slug = :slug AND c.is_active = 1 LIMIT 1'
);
$query->execute(['slug' => trim(request_value($_GET, 'slug'))]);
$course = $query->fetch();
if (!$course) {
    render_not_found('Course');
}
$query = $pdo->prepare(
    'SELECT u.id, u.name, u.slug, u.logo_url, u.city, u.state, u.location, u.institution_type, u.approvals, cu.fee
     FROM course_universities cu JOIN universities u ON u.id = cu.university_id AND u.is_active = 1
     WHERE cu.course_id = :id ORDER BY u.featured DESC, u.name'
);
$query->execute(['id' => $course['id']]);
$offeredBy = $query->fetchAll();
$query = $pdo->prepare(
    'SELECT id, name, slug, full_name, duration, level FROM courses
     WHERE is_active = 1 AND id <> :id ORDER BY (level = :level) DESC, (category_id <=> :category) DESC, name LIMIT 3'
);
$query->execute(['id' => $course['id'], 'level' => $course['level'], 'category' => $course['category_id']]);
$related = $query->fetchAll();

$highlights = text_lines($course['highlights']);
$syllabus = text_lines($course['syllabus']);
$careers = text_lines($course['career_options']);
$eligibility = text_lines($course['eligibility']);
$universityFees = array_filter(array_column($offeredBy, 'fee'), static fn ($fee): bool => $fee !== null);
$fees = $universityFees ? fee_range(min($universityFees), max($universityFees)) : fee_range($course['fees_min'], $course['fees_max']);
$levelLabel = ['pg' => 'Postgraduate', 'ug' => 'Undergraduate', 'other' => 'Programme'][$course['level']] ?? 'Programme';
$sections = array_filter([
    'overview' => 'Overview',
    'eligibility' => $eligibility ? 'Eligibility' : null,
    'syllabus' => $syllabus ? 'Syllabus' : null,
    'universities' => $offeredBy ? 'Universities' : null,
    'careers' => $careers ? 'Career options' : null,
]);

$pageTitle = $course['meta_title'] ?: $course['name'] . ($course['full_name'] ? ' (' . $course['full_name'] . ')' : '') . ': Eligibility, Fees, Syllabus & Careers | Collegekhoj';
$pageDescription = $course['meta_description'] ?: mb_strimwidth(trim($course['name'] . ' – ' . ($course['description'] ?? '') . ' Check duration, eligibility, universities and career options.'), 0, 300, '…');
$pageCanonical = course_url($course['slug']);
require __DIR__ . '/includes/header.php';
echo json_ld(array_filter([
    '@context' => 'https://schema.org', '@type' => 'Course', 'name' => $course['name'],
    'description' => $course['description'] ?: $course['full_name'], 'url' => absolute_url($pageCanonical),
    'provider' => array_map(static fn (array $u): array => ['@type' => 'CollegeOrUniversity', 'name' => $u['name'], 'url' => absolute_url(university_url($u['slug']))], $offeredBy) ?: null,
]));
?>
<section class="detail-hero"><div class="container">
  <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= e(app_url('/')) ?>">Home</a><span aria-hidden="true">/</span><a href="<?= e(app_url('/#courses')) ?>">Courses</a><span aria-hidden="true">/</span><span aria-current="page"><?= e($course['name']) ?></span></nav>
  <div class="detail-hero-grid">
    <div>
      <div class="detail-pills"><span class="pill pill-<?= e($course['level']) ?>"><?= e($levelLabel) ?></span><?php if ($course['category']): ?><span class="pill"><?= e($course['category']) ?></span><?php endif; ?></div>
      <h1><?= e($course['name']) ?></h1>
      <?php if ($course['full_name']): ?><p class="detail-sub"><?= e($course['full_name']) ?></p><?php endif; ?>
      <div class="fact-row">
        <?php if ($course['duration']): ?><div class="fact"><span>Duration</span><strong><?= e($course['duration']) ?></strong></div><?php endif; ?>
        <div class="fact"><span>Mode</span><strong>Online</strong></div>
        <?php if ($fees): ?><div class="fact"><span>Total fees</span><strong><?= e($fees) ?></strong></div><?php endif; ?>
        <?php if ($offeredBy): ?><div class="fact"><span>Universities</span><strong><?= count($offeredBy) ?></strong></div><?php endif; ?>
      </div>
      <div class="hero-actions">
        <button class="button button-cta button-hero" type="button" data-counselling data-course="<?= (int) $course['id'] ?>"><?= e(site_setting('hero_button_label')) ?> <span aria-hidden="true">→</span></button>
        <button class="button button-light button-hero" type="button" data-open-modal="expert-modal"><?= ui_icon('phone') ?><?= e(site_setting('header_button_label')) ?></button>
      </div>
    </div>
    <div class="detail-hero-art" aria-hidden="true"><span><?= course_icon($course['name']) ?></span></div>
  </div>
</div></section>

<?php if (count($sections) > 1): ?><nav class="detail-tabs" aria-label="On this page"><div class="container">
  <?php foreach ($sections as $anchor => $label): ?><a href="#<?= e($anchor) ?>"><?= e($label) ?></a><?php endforeach; ?>
</div></nav><?php endif; ?>

<div class="container detail-layout">
  <div class="detail-main">
    <section class="detail-card" id="overview"><h2>About <?= e($course['name']) ?></h2>
      <?= text_paragraphs($course['description']) ?: '<p>Get in touch with our counsellors for full details of this programme.</p>' ?>
      <?php if ($highlights): ?><h3>Programme highlights</h3><ul class="check-list"><?php foreach ($highlights as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </section>

    <?php if ($eligibility): ?><section class="detail-card" id="eligibility"><h2>Eligibility</h2>
      <?php if (count($eligibility) === 1): ?><p><?= e($eligibility[0]) ?></p><?php else: ?><ul class="check-list"><?php foreach ($eligibility as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <p class="muted small">Eligibility can vary by university and intake. Confirm the current criteria with our counsellors or the university.</p>
    </section><?php endif; ?>

    <?php if ($syllabus): ?><section class="detail-card" id="syllabus"><h2>Syllabus &amp; subjects</h2>
      <ol class="syllabus-list"><?php foreach ($syllabus as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ol>
    </section><?php endif; ?>

    <?php if ($offeredBy): ?><section class="detail-card" id="universities"><h2>Universities offering <?= e($course['name']) ?></h2>
      <div class="offer-list"><?php foreach ($offeredBy as $university): ?>
        <article class="offer-row">
          <?php if ($university['logo_url']): ?><img class="offer-logo" src="<?= e($university['logo_url']) ?>" alt="">
          <?php else: ?><span class="offer-logo offer-initials" aria-hidden="true"><?= e(institution_initials($university['name'])) ?></span><?php endif; ?>
          <div class="offer-body"><h3><a href="<?= e(university_url($university['slug'])) ?>"><?= e($university['name']) ?></a></h3>
            <p><?= e(implode(' · ', array_filter([$university['location'] ?: trim(($university['city'] ?? '') . ', ' . ($university['state'] ?? ''), ', '), $university['institution_type'], $university['approvals']]))) ?></p></div>
          <div class="offer-fee"><?php if ($university['fee'] !== null): ?><span>Total fees</span><strong><?= e(format_inr($university['fee'])) ?></strong><?php else: ?><span>Fees</span><strong>On request</strong><?php endif; ?></div>
          <div class="offer-actions"><button class="button button-cta button-small" type="button" data-counselling data-course="<?= (int) $course['id'] ?>" data-university="<?= (int) $university['id'] ?>">Enquire now</button>
            <a class="text-link" href="<?= e(university_url($university['slug'])) ?>">View university →</a></div>
        </article>
      <?php endforeach; ?></div>
    </section><?php endif; ?>

    <?php if ($careers): ?><section class="detail-card" id="careers"><h2>Career options after <?= e($course['name']) ?></h2>
      <ul class="career-grid"><?php foreach ($careers as $item): ?><li><span aria-hidden="true">💼</span><?= e($item) ?></li><?php endforeach; ?></ul>
    </section><?php endif; ?>

    <section class="cta-band"><div><h2>Not sure if <?= e($course['name']) ?> is right for you?</h2><p>Talk to an expert for free guidance on universities, fees and admissions.</p></div>
      <button class="button button-cta" type="button" data-counselling data-course="<?= (int) $course['id'] ?>"><?= e(site_setting('form_button_label')) ?> <span aria-hidden="true">→</span></button></section>

    <?php if ($related): ?><section class="related"><h2>Related courses</h2><div class="related-grid">
      <?php foreach ($related as $item): ?><a class="related-card" href="<?= e(course_url($item['slug'])) ?>"><span class="mini-icon" aria-hidden="true"><?= course_icon($item['name']) ?></span>
        <span><strong><?= e($item['name']) ?></strong><small><?= e($item['full_name'] ?: ($item['duration'] ?? '')) ?></small></span></a><?php endforeach; ?>
    </div></section><?php endif; ?>
  </div>

  <aside class="detail-aside"><div class="aside-card">
    <h2>Get free counselling</h2><p>Speak to our experts about <?= e($course['name']) ?> admissions, fees and the right university for you.</p>
    <ul class="aside-facts">
      <?php if ($course['duration']): ?><li><span>Duration</span><strong><?= e($course['duration']) ?></strong></li><?php endif; ?>
      <li><span>Level</span><strong><?= e($levelLabel) ?></strong></li>
      <?php if ($fees): ?><li><span>Fees</span><strong><?= e($fees) ?></strong></li><?php endif; ?>
    </ul>
    <button class="button button-cta" type="button" data-counselling data-course="<?= (int) $course['id'] ?>"><?= e(site_setting('hero_button_label')) ?> <span aria-hidden="true">→</span></button>
    <button class="button button-light" type="button" data-open-modal="expert-modal"><?= ui_icon('phone') ?><?= e(site_setting('header_button_label')) ?></button>
  </div></aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
