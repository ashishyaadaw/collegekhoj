<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$conditions = ['c.is_active = 1'];
$parameters = [];
$search = trim(request_value($_GET, 'q'));
if ($search !== '') {
    $conditions[] = '(c.name LIKE :search_name OR u.name LIKE :search_university OR c.city LIKE :search_city OR c.state LIKE :search_state OR EXISTS (SELECT 1 FROM courses sc WHERE (sc.college_id=c.id OR (c.university_id IS NOT NULL AND sc.university_id=c.university_id)) AND sc.is_active=1 AND sc.name LIKE :search_course))';
    $parameters['search_name'] = '%' . $search . '%';
    $parameters['search_university'] = '%' . $search . '%';
    $parameters['search_city'] = '%' . $search . '%';
    $parameters['search_state'] = '%' . $search . '%';
    $parameters['search_course'] = '%' . $search . '%';
}
$city = trim(request_value($_GET, 'city'));
if ($city !== '') {
    $conditions[] = '(c.city LIKE :city_name OR c.state LIKE :city_state)';
    $parameters['city_name'] = '%' . $city . '%';
    $parameters['city_state'] = '%' . $city . '%';
}
$categoryId = request_int($_GET, 'category');
if ($categoryId) {
    $conditions[] = 'EXISTS (SELECT 1 FROM courses fc WHERE fc.college_id = c.id AND fc.category_id = :category_id AND fc.is_active = 1)';
    $parameters['category_id'] = $categoryId;
}
$courseId = request_int($_GET, 'course');
if ($courseId) {
    $conditions[] = 'EXISTS (SELECT 1 FROM courses fc WHERE fc.college_id = c.id AND fc.id = :course_id AND fc.is_active = 1)';
    $parameters['course_id'] = $courseId;
}
$examId = request_int($_GET, 'exam');
if ($examId) {
    $conditions[] = 'EXISTS (SELECT 1 FROM college_exams ce WHERE ce.college_id = c.id AND ce.exam_id = :exam_id)';
    $parameters['exam_id'] = $examId;
}
$university = trim(request_value($_GET, 'university'));
if ($university !== '') {
    $conditions[] = 'u.slug = :university';
    $parameters['university'] = $university;
}
$minimum = request_int($_GET, 'min_fee');
if ($minimum !== null && $minimum >= 0) {
    $conditions[] = '(c.fees_max IS NULL OR c.fees_max >= :min_fee)';
    $parameters['min_fee'] = $minimum;
}
$maximum = request_int($_GET, 'max_fee');
if ($maximum !== null && $maximum >= 0) {
    $conditions[] = '(c.fees_min IS NULL OR c.fees_min <= :max_fee)';
    $parameters['max_fee'] = $maximum;
}
$ratingValue = request_value($_GET, 'rating');
$rating = $ratingValue !== '' ? filter_var($ratingValue, FILTER_VALIDATE_FLOAT) : null;
if ($rating !== false && $rating !== null && $rating >= 0 && $rating <= 5) {
    $conditions[] = 'c.rating >= :rating';
    $parameters['rating'] = $rating;
}
$sortOptions = ['featured' => 'c.featured DESC, c.rating DESC', 'rating' => 'c.rating DESC', 'fees' => 'c.fees_min ASC', 'name' => 'c.name ASC'];
$sort = request_value($_GET, 'sort') ?: 'featured';
$orderBy = $sortOptions[$sort] ?? $sortOptions['featured'];
$statement = $pdo->prepare(
    'SELECT c.id, c.name, c.slug, c.logo_url, c.city, c.state, c.location, c.rating, c.fees_min, c.fees_max,
            c.placement_average, u.name AS university_name
     FROM colleges c LEFT JOIN universities u ON u.id = c.university_id
     WHERE ' . implode(' AND ', $conditions) . ' ORDER BY ' . $orderBy . ' LIMIT 100'
);
$statement->execute($parameters);
$colleges = $statement->fetchAll();
$categories = $pdo->query('SELECT id, name FROM course_categories ORDER BY sort_order, name')->fetchAll();
$exams = $pdo->query('SELECT id,name FROM exams WHERE is_active=1 ORDER BY name')->fetchAll();
$pageTitle = 'Colleges & Universities | Collegekhoj';
require __DIR__ . '/includes/header.php';
?>
<section class="section"><div class="container">
  <div class="section-heading"><div><span class="eyebrow">Find your next campus</span><h1 style="color:var(--navy)">Explore colleges</h1>
    <p class="muted">Filter colleges by location, stream, fees and rating. Details should be verified with the institution.</p></div></div>
  <div class="listing-layout">
    <form class="card filters" method="get" action="<?= e(app_url('/colleges.php')) ?>"><h2>Filters</h2>
      <label>Course, college or university<input type="search" name="q" value="<?= e($search) ?>"></label>
      <label>City or state<input type="text" name="city" value="<?= e($city) ?>"></label>
      <label>Course stream<select name="category"><option value="">All streams</option><?php foreach ($categories as $category): ?>
        <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
      <?php endforeach; ?></select></label>
      <label>Entrance exam<select name="exam"><option value="">Any exam</option><?php foreach ($exams as $exam): ?>
        <option value="<?= (int) $exam['id'] ?>" <?= $examId === (int) $exam['id'] ? 'selected' : '' ?>><?= e($exam['name']) ?></option>
      <?php endforeach; ?></select></label>
      <div class="form-grid">
        <label>Min fees (₹)<input type="number" name="min_fee" min="0" value="<?= e(request_value($_GET, 'min_fee')) ?>"></label>
        <label>Max fees (₹)<input type="number" name="max_fee" min="0" value="<?= e(request_value($_GET, 'max_fee')) ?>"></label>
      </div>
      <label>Minimum rating<select name="rating"><option value="">Any rating</option><?php foreach ([3, 3.5, 4, 4.5] as $ratingOption): ?>
        <option value="<?= $ratingOption ?>" <?= $ratingValue === (string) $ratingOption ? 'selected' : '' ?>><?= $ratingOption ?>+</option>
      <?php endforeach; ?></select></label>
      <?php if ($courseId): ?><input type="hidden" name="course" value="<?= $courseId ?>"><?php endif; ?>
      <?php if ($examId): ?><input type="hidden" name="exam" value="<?= $examId ?>"><?php endif; ?>
      <?php if ($university !== ''): ?><input type="hidden" name="university" value="<?= e($university) ?>"><?php endif; ?>
      <label>Sort by<select name="sort"><?php foreach (['featured'=>'Featured','rating'=>'Rating','fees'=>'Lowest fees','name'=>'Name'] as $value=>$label): ?>
        <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
      <button class="button" type="submit">Apply filters</button>
    </form>
    <div><form method="get" action="<?= e(app_url('/compare.php')) ?>">
      <div class="section-heading"><strong><?= count($colleges) ?> colleges found</strong><button class="button button-small" type="submit">Compare selected (up to 3)</button></div>
      <div class="college-results"><?php foreach ($colleges as $college): ?>
        <article class="card college-row"><div class="college-main">
          <?php if ($college['logo_url']): ?><img class="uni-logo" src="<?= e($college['logo_url']) ?>" alt="">
          <?php else: ?><span class="uni-logo" aria-hidden="true">🎓</span><?php endif; ?>
          <div><h3><a href="<?= e(app_url('/college-detail.php?slug=' . rawurlencode($college['slug']))) ?>"><?= e($college['name']) ?></a></h3>
            <p><?= e($college['university_name'] ?? 'University') ?> · <?= e($college['location'] ?? trim(($college['city'] ?? '') . ', ' . ($college['state'] ?? ''), ', ')) ?></p>
            <div class="tag-list"><span>Fees: <?= $college['fees_min'] !== null ? '₹' . number_format((float) $college['fees_min']) : 'Contact college' ?><?= $college['fees_max'] !== null ? ' – ₹' . number_format((float) $college['fees_max']) : '' ?></span>
              <?php if ($college['placement_average']): ?><span>Avg. placement ₹<?= number_format((float) $college['placement_average']) ?></span><?php endif; ?></div>
          </div></div><div><span class="rating">★ <?= e((string) $college['rating']) ?></span><label class="small"><input type="checkbox" name="ids[]" value="<?= (int) $college['id'] ?>" style="width:auto"> Compare</label></div>
        </article>
      <?php endforeach; ?><?php if (!$colleges): ?><div class="card"><p>No colleges match these filters. Try broadening your search.</p></div><?php endif; ?></div>
    </form></div>
  </div>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
