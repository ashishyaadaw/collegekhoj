<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/home_tags.php';

$pdo = db();
$universities = $pdo->query(
    'SELECT u.*, COUNT(c.id) AS college_count FROM universities u
     LEFT JOIN colleges c ON c.university_id = u.id AND c.is_active = 1
     WHERE u.is_active = 1 GROUP BY u.id ORDER BY u.featured DESC, u.name LIMIT 4'
)->fetchAll();
$courses = $pdo->query(
    'SELECT c.id, c.name, c.slug, c.duration, cc.name AS category, u.name AS university_name
     FROM courses c LEFT JOIN course_categories cc ON cc.id = c.category_id
     LEFT JOIN universities u ON u.id = c.university_id
     WHERE c.is_active = 1 ORDER BY cc.sort_order, c.name LIMIT 6'
)->fetchAll();
$colleges = $pdo->query(
    'SELECT c.id, c.name, c.slug, c.city, c.state, c.rating, c.fees_min, c.fees_max, u.name AS university_name
     FROM colleges c LEFT JOIN universities u ON u.id = c.university_id
     WHERE c.is_active = 1 ORDER BY c.featured DESC, c.rating DESC LIMIT 6'
)->fetchAll();
$exams = $pdo->query(
    'SELECT name, exam_date, application_deadline FROM exams
     WHERE is_active = 1 AND (exam_date >= CURDATE() OR exam_date IS NULL)
     ORDER BY exam_date IS NULL, exam_date LIMIT 4'
)->fetchAll();
$reviews = $pdo->query(
    'SELECT r.rating, r.title, r.review_text, c.name AS college_name, u.name AS reviewer_name
     FROM reviews r JOIN colleges c ON c.id = r.college_id JOIN users u ON u.id = r.user_id
     WHERE r.status = \'approved\' ORDER BY r.created_at DESC LIMIT 3'
)->fetchAll();
$tagGroups = home_tag_groups($pdo);
$pageTitle = 'Online Degrees, Courses & Universities | Collegekhoj';
$pageDescription = 'Explore online degree courses, universities, and colleges. Compare options and get admission guidance with Collegekhoj.';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="container hero-grid">
    <div><span class="eyebrow">Make your next move count</span>
      <h1>Find the right degree.<br><em>Build your future.</em></h1>
      <p>Explore degree programmes, compare colleges and universities, and get friendly guidance through eligibility and admissions—all in one place.</p>
      <form class="search-form" action="<?= e(app_url('/colleges.php')) ?>" method="get" aria-label="Search colleges">
        <input type="search" name="q" placeholder="Course, college or university" aria-label="Course, college or university">
        <input type="text" name="city" placeholder="City or state" aria-label="City or state">
        <select name="category" aria-label="Select stream"><option value="">All streams</option>
          <?php foreach ($pdo->query('SELECT id, name FROM course_categories ORDER BY sort_order, name') as $category): ?>
            <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="button" type="submit">Search</button>
      </form>
      <div class="trust-row"><span>Guidance tailored to you</span><span>Compare course options</span><span>Admission support</span></div>
    </div>
    <div class="hero-art">
      <aside class="hero-panel"><div class="hero-card-top"><h2>Find your learning path</h2><span class="pill">START HERE</span></div>
        <div class="degree-icon" role="img" aria-label="Graduation cap representing online degrees">🎓</div>
        <div class="mini-courses"><?php foreach (array_slice($courses, 0, 4) as $course): ?>
          <a class="mini-course" href="<?= e(app_url('/colleges.php?course=' . (int) $course['id'])) ?>"><span class="mini-icon" aria-hidden="true"><?= course_icon($course['name']) ?></span>
            <span><strong><?= e($course['name']) ?></strong><small><?= e($course['category'] ?? 'Degree programme') ?></small></span></a>
        <?php endforeach; ?></div>
        <a class="button" href="<?= e(app_url('/colleges.php')) ?>">Browse colleges</a>
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
      <h3 style="margin-top:10px"><?= e($course['name']) ?></h3><p><?= e($course['university_name'] ?? 'Multiple institutions') ?><?= $course['duration'] ? ' · ' . e($course['duration']) : '' ?></p>
      <a class="text-link" href="<?= e(app_url('/colleges.php?course=' . (int) $course['id'])) ?>">Explore colleges →</a></article>
  <?php endforeach; ?></div>
</div></section>
<section class="section soft" id="universities"><div class="container">
  <div class="section-head"><span class="eyebrow">Compare your options</span><h2>Featured universities</h2><p>Browse institutions and their associated colleges and programmes.</p></div>
  <div class="grid"><?php foreach ($universities as $university): ?>
    <article class="card uni-card"><div class="uni-row">
      <?php if ($university['logo_url']): ?><img class="uni-logo" src="<?= e($university['logo_url']) ?>" alt="">
      <?php else: ?><span class="uni-logo uni-initials" aria-hidden="true"><?= e(institution_initials($university['name'])) ?></span><?php endif; ?>
      <div><h3><?= e($university['name']) ?></h3><p><?= e($university['location'] ?? trim(($university['city'] ?? '') . ', ' . ($university['state'] ?? ''), ', ')) ?></p></div>
    </div><p><?= e(mb_strimwidth(strip_tags($university['description'] ?? ''), 0, 150, '…')) ?></p>
    <a class="text-link" href="<?= e(app_url('/colleges.php?university=' . rawurlencode($university['slug']))) ?>">View <?= (int) $university['college_count'] ?> colleges →</a></article>
  <?php endforeach; ?></div>
</div></section>
<section class="section"><div class="container">
  <div class="section-head"><span class="eyebrow">Find the right campus</span><h2>Trending colleges</h2></div>
  <div class="grid"><?php foreach ($colleges as $college): ?>
    <article class="card"><div class="section-heading"><span class="pill"><?= e($college['city'] ?? 'India') ?></span><span class="rating">★ <?= e((string) $college['rating']) ?></span></div>
      <h3><?= e($college['name']) ?></h3><p><?= e($college['university_name'] ?? 'University') ?></p>
      <p>Fees: <?= $college['fees_min'] !== null ? '₹' . number_format((float) $college['fees_min']) : 'Contact institution' ?>
        <?= $college['fees_max'] !== null ? '– ₹' . number_format((float) $college['fees_max']) : '' ?></p>
      <a class="text-link" href="<?= e(app_url('/college-detail.php?slug=' . rawurlencode($college['slug']))) ?>">View college →</a></article>
  <?php endforeach; ?></div>
</div></section>
<section class="section soft"><div class="container">
  <div class="section-head"><span class="eyebrow">Plan ahead</span><h2>Entrance exams</h2><p>Dates are shown when published. Check official exam authorities for current information.</p></div>
  <div class="grid"><?php foreach ($exams as $exam): ?>
    <article class="card"><h3><?= e($exam['name']) ?></h3><p><?= $exam['exam_date'] ? 'Exam date: ' . e(date('d M Y', strtotime($exam['exam_date']))) : 'Schedule to be announced' ?></p>
      <?php if ($exam['exam_date']): $daysRemaining = (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($exam['exam_date']))->days; ?>
        <span class="pill"><?= $daysRemaining === 0 ? 'Exam day' : $daysRemaining . ' days remaining' ?></span>
      <?php endif; ?>
      <p><?= $exam['application_deadline'] ? 'Application deadline: ' . e(date('d M Y', strtotime($exam['application_deadline']))) : 'Check the official exam website for updates.' ?></p></article>
  <?php endforeach; ?></div>
</div></section>
<?php if ($reviews): ?><section class="section"><div class="container">
  <div class="section-head"><span class="eyebrow">Student perspectives</span><h2>Recent student reviews</h2></div>
  <div class="grid"><?php foreach ($reviews as $review): ?><article class="card">
    <div class="rating"><?= str_repeat('★', (int) $review['rating']) ?><?= str_repeat('☆', 5 - (int) $review['rating']) ?></div>
    <h3><?= e($review['title'] ?: 'Student review') ?></h3><p><?= e(mb_strimwidth($review['review_text'], 0, 220, '…')) ?></p>
    <small class="muted"><?= e($review['reviewer_name']) ?> · <?= e($review['college_name']) ?></small></article><?php endforeach; ?></div>
</div></section><?php endif; ?>
<?php if ($tagGroups): ?><section class="tag-cloud" aria-labelledby="popular-searches"><div class="container">
  <h2 id="popular-searches">Popular searches</h2>
  <?php foreach ($tagGroups as $groupLabel => $tags): ?>
    <div class="tag-group"><h3><?= e($groupLabel) ?></h3>
      <ul><?php foreach ($tags as $tag): $external = !str_starts_with($tag['url'], '/'); ?><li><a href="<?= e(home_tag_href($tag['url'])) ?>"<?= $external ? ' target="_blank" rel="noopener"' : '' ?>><?= e($tag['label']) ?></a></li><?php endforeach; ?></ul>
    </div>
  <?php endforeach; ?>
</div></section><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
