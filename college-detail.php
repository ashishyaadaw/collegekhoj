<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$slug = trim(request_value($_GET, 'slug'));
$statement = db()->prepare(
    'SELECT c.*, u.name AS university_name, u.slug AS university_slug
     FROM colleges c LEFT JOIN universities u ON u.id = c.university_id
     WHERE c.slug = :slug AND c.is_active = 1 LIMIT 1'
);
$statement->execute(['slug' => $slug]);
$college = $statement->fetch();
if (!$college) {
    http_response_code(404);
    $pageTitle = 'College not found | Collegekhoj';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section container"><h1>College not found</h1><p><a class="text-link" href="' . e(app_url('/colleges.php')) . '">Browse colleges</a></p></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}
$coursesQuery = db()->prepare(
    'SELECT c.name, c.duration, c.fees_min, c.fees_max, c.eligibility, cc.name AS category
     FROM courses c LEFT JOIN course_categories cc ON cc.id = c.category_id
     WHERE c.college_id = :college_id AND c.is_active = 1 ORDER BY cc.sort_order, c.name'
);
$coursesQuery->execute(['college_id' => $college['id']]);
$courses = $coursesQuery->fetchAll();
$reviewsQuery = db()->prepare(
    'SELECT r.rating, r.title, r.review_text, r.pros, r.cons, u.name AS reviewer_name, r.created_at
     FROM reviews r JOIN users u ON u.id = r.user_id
     WHERE r.college_id = :college_id AND r.status = \'approved\' ORDER BY r.created_at DESC LIMIT 20'
);
$reviewsQuery->execute(['college_id' => $college['id']]);
$reviews = $reviewsQuery->fetchAll();
$questionsQuery = db()->prepare(
    'SELECT q.question,q.answer,u.name AS responder_name,q.answered_at
     FROM college_questions q JOIN users u ON u.id = q.user_id
     WHERE q.college_id = :college_id AND q.status = \'approved\' AND q.answer IS NOT NULL
     ORDER BY q.answered_at DESC LIMIT 20'
);
$questionsQuery->execute(['college_id' => $college['id']]);
$questions = $questionsQuery->fetchAll();
$collegeExamsQuery = db()->prepare(
    'SELECT e.name,e.exam_date FROM college_exams ce JOIN exams e ON e.id=ce.exam_id
     WHERE ce.college_id=:college_id AND e.is_active=1 ORDER BY e.name'
);
$collegeExamsQuery->execute(['college_id' => $college['id']]);
$collegeExams = $collegeExamsQuery->fetchAll();
$pageTitle = $college['name'] . ' | Collegekhoj';
$pageDescription = mb_strimwidth(strip_tags($college['description'] ?? 'Explore college courses, fees, admissions and student reviews.'), 0, 300, '…');
require __DIR__ . '/includes/header.php';
?>
<section class="section"><div class="container">
  <div class="college-main">
    <?php if ($college['logo_url']): ?><img class="uni-logo" src="<?= e($college['logo_url']) ?>" alt="<?= e($college['name']) ?> logo"><?php else: ?><span class="uni-logo">🎓</span><?php endif; ?>
    <div><span class="eyebrow"><?= e($college['university_name'] ?? 'College') ?></span><h1 style="color:var(--navy)"><?= e($college['name']) ?></h1>
      <p class="muted"><?= e($college['location'] ?? trim(($college['city'] ?? '') . ', ' . ($college['state'] ?? ''), ', ')) ?> · <span class="rating">★ <?= e((string) $college['rating']) ?></span></p>
      <a class="button button-small" href="<?= e(app_url('/compare.php?ids[]=' . (int) $college['id'])) ?>">Add to comparison</a>
      <?php if (is_http_url($college['brochure_url'])): ?><a class="button button-small button-light" href="<?= e($college['brochure_url']) ?>" rel="noopener" target="_blank">Download brochure</a><?php endif; ?>
    </div>
  </div>
  <nav class="tabs" aria-label="College details"><a href="#overview">Overview</a><a href="#courses">Courses &amp; fees</a><a href="#admissions">Admissions</a><a href="#placements">Placements</a><a href="#reviews">Reviews &amp; Q&amp;A</a></nav>
  <section id="overview" class="detail-card"><h2>Overview &amp; highlights</h2>
    <div><?= nl2br(e($college['description'] ?? 'College profile information will be updated soon.')) ?></div>
    <?php if ($college['highlights']): ?><p><?= nl2br(e($college['highlights'])) ?></p><?php endif; ?>
    <p class="muted">Established: <?= $college['established_year'] ? e((string) $college['established_year']) : 'Information not provided' ?> · Rank: <?= $college['ranking'] ? '#' . e((string) $college['ranking']) : 'Not listed' ?></p>
  </section>
  <section id="courses" class="detail-card" style="margin-top:18px"><h2>Courses &amp; fees</h2><?php if ($courses): ?><div class="table-wrap"><table>
    <thead><tr><th>Course</th><th>Stream</th><th>Duration</th><th>Fees (indicative)</th><th>Eligibility</th></tr></thead><tbody>
      <?php foreach ($courses as $course): ?><tr><td><?= e($course['name']) ?></td><td><?= e($course['category'] ?? '—') ?></td><td><?= e($course['duration'] ?? '—') ?></td>
        <td><?= $course['fees_min'] !== null ? '₹' . number_format((float) $course['fees_min']) : 'Contact college' ?><?= $course['fees_max'] !== null ? ' – ₹' . number_format((float) $course['fees_max']) : '' ?></td><td><?= e($course['eligibility'] ?? 'Confirm with college') ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php else: ?><p class="muted">Course information has not been added yet.</p><?php endif; ?>
    <p class="small muted">Fees and eligibility can change by academic session. Confirm current details with the institution.</p>
  </section>
  <section id="admissions" class="detail-card" style="margin-top:18px"><h2>Admission process &amp; eligibility</h2>
    <h3>Eligibility</h3><p><?= nl2br(e($college['eligibility'] ?? 'Check the course-specific eligibility with the college.')) ?></p>
    <h3>Admission process</h3><p><?= nl2br(e($college['admission_process'] ?? 'Contact the institution for current application dates and admission steps.')) ?></p>
    <?php if ($collegeExams): ?><h3>Associated entrance exams</h3><ul><?php foreach ($collegeExams as $exam): ?>
      <li><?= e($exam['name']) ?><?= $exam['exam_date'] ? ' · ' . e(date('d M Y', strtotime($exam['exam_date']))) : '' ?></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </section>
  <section id="placements" class="detail-card" style="margin-top:18px"><h2>Cutoffs &amp; placements</h2>
    <h3>Cutoffs</h3><p><?= nl2br(e($college['cutoff_details'] ?? 'Cutoff information has not been provided. Check the latest official admission notice for course-, category- and year-specific cutoffs.')) ?></p>
    <p>Average placement package: <?= $college['placement_average'] ? '₹' . number_format((float) $college['placement_average']) : 'Not provided' ?></p>
    <p>Placement rate: <?= $college['placement_rate'] ? e((string) $college['placement_rate']) . '%' : 'Not provided' ?></p>
    <p class="small muted">Placement details are institution-reported where available and are not an employment guarantee.</p>
  </section>
  <section id="reviews" class="detail-card" style="margin-top:18px"><h2>Student reviews &amp; Q&amp;A</h2>
    <p><a class="button button-small" href="<?= e(app_url('/review.php?college=' . (int) $college['id'])) ?>">Write a review</a>
      <a class="button button-small button-light" href="<?= e(app_url('/question.php?college=' . (int) $college['id'])) ?>">Ask a question</a></p>
    <?php if ($reviews): foreach ($reviews as $review): ?><article class="card" style="margin-top:12px">
      <span class="rating"><?= str_repeat('★', (int) $review['rating']) ?><?= str_repeat('☆', 5 - (int) $review['rating']) ?></span>
      <h3><?= e($review['title'] ?: 'Student review') ?></h3><p><?= e($review['review_text']) ?></p>
      <?php if ($review['pros']): ?><p><strong>Pros:</strong> <?= e($review['pros']) ?></p><?php endif; ?>
      <?php if ($review['cons']): ?><p><strong>Cons:</strong> <?= e($review['cons']) ?></p><?php endif; ?>
      <small class="muted"><?= e($review['reviewer_name']) ?></small></article><?php endforeach; else: ?><p class="muted">No approved student reviews have been published yet.</p><?php endif; ?>
    <h3>Questions &amp; answers</h3>
    <?php if ($questions): foreach ($questions as $question): ?><article class="card" style="margin-top:12px">
      <p><strong>Q:</strong> <?= e($question['question']) ?></p><p><strong>A:</strong> <?= e($question['answer']) ?></p></article><?php endforeach; else: ?><p class="muted">No approved questions and answers yet.</p><?php endif; ?>
  </section>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
