<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/home_tags.php';

$pdo = db();
ensure_catalog_schema($pdo);
$universities = $pdo->query(
    'SELECT u.*, COUNT(c.id) AS course_count FROM universities u
     LEFT JOIN course_universities cu ON cu.university_id = u.id
     LEFT JOIN courses c ON c.id = cu.course_id AND c.is_active = 1
     WHERE u.is_active = 1 GROUP BY u.id ORDER BY u.featured DESC, u.name LIMIT 4'
)->fetchAll();
$courses = $pdo->query(
    'SELECT c.id, c.name, c.slug, c.full_name, c.duration, cc.name AS category,
            (SELECT COUNT(*) FROM course_universities cu JOIN universities u ON u.id = cu.university_id AND u.is_active = 1 WHERE cu.course_id = c.id) AS university_count
     FROM courses c LEFT JOIN course_categories cc ON cc.id = c.category_id
     WHERE c.is_active = 1 ORDER BY cc.sort_order, c.name LIMIT 6'
)->fetchAll();
$exams = $pdo->query(
    'SELECT name, exam_date, application_deadline FROM exams
     WHERE is_active = 1 AND (exam_date >= CURDATE() OR exam_date IS NULL)
     ORDER BY exam_date IS NULL, exam_date LIMIT 4'
)->fetchAll();
$tagGroups = home_tag_groups($pdo);
$pageTitle = 'Online Degrees, Courses & Universities | Collegekhoj';
$pageDescription = 'Explore online degree courses and universities, and get free admission counselling from Collegekhoj experts.';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="container hero-grid">
    <div><span class="eyebrow">Make your next move count</span>
      <h1>Find the right degree.<br><em>Build your future.</em></h1>
      <p>Explore degree programmes and universities, and get friendly guidance from our experts through eligibility and admissions—all in one place.</p>
      <div class="hero-actions">
        <button class="button button-cta button-hero" type="button" data-open-modal="counselling-modal"><?= e(site_setting('hero_button_label')) ?> <span aria-hidden="true">→</span></button>
        <button class="button button-light button-hero" type="button" data-open-modal="expert-modal"><?= ui_icon('phone') ?><?= e(site_setting('header_button_label')) ?></button>
      </div>
      <div class="trust-row"><span>Guidance tailored to you</span><span>Compare course options</span><span>Admission support</span></div>
    </div>
    <div class="hero-art">
      <aside class="hero-panel"><div class="hero-card-top"><h2>Find your learning path</h2><span class="pill">START HERE</span></div>
        <div class="degree-icon" role="img" aria-label="Graduation cap representing online degrees">🎓</div>
        <div class="mini-courses"><?php foreach (array_slice($courses, 0, 4) as $course): ?>
          <a class="mini-course" href="<?= e(course_url($course['slug'])) ?>"><span class="mini-icon" aria-hidden="true"><?= course_icon($course['name']) ?></span>
            <span><strong><?= e($course['name']) ?></strong><small><?= e($course['category'] ?? 'Degree programme') ?></small></span></a>
        <?php endforeach; ?></div>
        <button class="button" type="button" data-open-modal="counselling-modal">Get free counselling</button>
      </aside>
      <div class="float-note">Your goals. Your degree.<span>Let’s find the right fit.</span></div>
    </div>
  </div>
</section>
<section class="section" id="courses"><div class="container">
  <div class="section-head"><span class="eyebrow">Explore study options</span><h2>Popular courses</h2>
    <p>Programme availability, eligibility and fees vary by institution and intake. Confirm details with the official university.</p></div>
  <div class="grid"><?php foreach ($courses as $course): ?>
    <article class="card course-card"><div class="card-icon" aria-hidden="true"><?= course_icon($course['name']) ?></div><span class="pill"><?= e($course['category'] ?? 'Degree') ?></span>
      <h3 style="margin-top:10px"><a href="<?= e(course_url($course['slug'])) ?>"><?= e($course['name']) ?></a></h3>
      <p><?= e(implode(' · ', array_filter([$course['full_name'], $course['duration'], $course['university_count'] ? $course['university_count'] . ' universit' . ($course['university_count'] == 1 ? 'y' : 'ies') : null]))) ?></p>
      <div class="card-actions"><a class="text-link" href="<?= e(course_url($course['slug'])) ?>">View details →</a><a class="text-link" href="<?= e(app_url('/?counsel=1&course=' . (int) $course['id'])) ?>" data-counselling data-course="<?= (int) $course['id'] ?>">Free counselling</a></div></article>
  <?php endforeach; ?></div>
</div></section>
<section class="section soft" id="universities"><div class="container">
  <div class="section-head"><span class="eyebrow">Compare your options</span><h2>Featured universities</h2><p>Browse institutions and their online programmes, then talk to an expert about admissions.</p></div>
  <div class="grid"><?php foreach ($universities as $university): ?>
    <article class="card uni-card"><div class="uni-row">
      <?php if ($university['logo_url']): ?><img class="uni-logo" src="<?= e($university['logo_url']) ?>" alt="">
      <?php else: ?><span class="uni-logo uni-initials" aria-hidden="true"><?= e(institution_initials($university['name'])) ?></span><?php endif; ?>
      <div><h3><a href="<?= e(university_url($university['slug'])) ?>"><?= e($university['name']) ?></a></h3><p><?= e($university['location'] ?? trim(($university['city'] ?? '') . ', ' . ($university['state'] ?? ''), ', ')) ?></p></div>
    </div><p><?= e(mb_strimwidth(strip_tags($university['description'] ?? ''), 0, 150, '…')) ?></p>
    <div class="card-actions"><a class="text-link" href="<?= e(university_url($university['slug'])) ?>">View <?= (int) $university['course_count'] ?> course<?= (int) $university['course_count'] === 1 ? '' : 's' ?> →</a><a class="text-link" href="<?= e(app_url('/?counsel=1&university=' . (int) $university['id'])) ?>" data-counselling data-university="<?= (int) $university['id'] ?>">Free counselling</a></div></article>
  <?php endforeach; ?></div>
</div></section>
<section class="section"><div class="container">
  <div class="section-head"><span class="eyebrow">Plan ahead</span><h2>Entrance exams</h2><p>Dates are shown when published. Check official exam authorities for current information.</p></div>
  <div class="grid"><?php foreach ($exams as $exam): ?>
    <article class="card"><h3><?= e($exam['name']) ?></h3><p><?= $exam['exam_date'] ? 'Exam date: ' . e(date('d M Y', strtotime($exam['exam_date']))) : 'Schedule to be announced' ?></p>
      <?php if ($exam['exam_date']): $daysRemaining = (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($exam['exam_date']))->days; ?>
        <span class="pill"><?= $daysRemaining === 0 ? 'Exam day' : $daysRemaining . ' days remaining' ?></span>
      <?php endif; ?>
      <p><?= $exam['application_deadline'] ? 'Application deadline: ' . e(date('d M Y', strtotime($exam['application_deadline']))) : 'Check the official exam website for updates.' ?></p></article>
  <?php endforeach; ?></div>
</div></section>
<?php if ($tagGroups): ?><section class="tag-cloud" aria-labelledby="popular-searches"><div class="container">
  <h2 id="popular-searches">Popular searches</h2>
  <?php foreach ($tagGroups as $groupLabel => $tags): ?>
    <div class="tag-group"><h3><?= e($groupLabel) ?></h3>
      <ul><?php foreach ($tags as $tag): $external = !str_starts_with($tag['url'], '/'); ?><li><a href="<?= e(home_tag_href($tag['url'])) ?>"<?= $external ? ' target="_blank" rel="noopener"' : '' ?>><?= e($tag['label']) ?></a></li><?php endforeach; ?></ul>
    </div>
  <?php endforeach; ?>
</div></section><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
