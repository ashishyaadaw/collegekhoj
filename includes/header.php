<?php
$navCourses = db()->query(
    'SELECT c.id, c.name, c.slug, c.duration, cc.name AS category
     FROM courses c LEFT JOIN course_categories cc ON cc.id = c.category_id
     WHERE c.is_active = 1 ORDER BY cc.sort_order, cc.name, c.name'
)->fetchAll();
$navUniversities = db()->query(
    'SELECT name, slug, featured, city, state FROM universities WHERE is_active = 1 ORDER BY featured DESC, name'
)->fetchAll();
$pageTitle = $pageTitle ?? 'Explore Courses & Colleges | Collegekhoj';
$pageDescription = $pageDescription ?? 'Explore courses and universities, compare colleges, and get admission guidance with Collegekhoj.';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <?php render_tags('head'); ?>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= e($pageDescription) ?>">
  <?php if (!empty($pageKeywords)): ?><meta name="keywords" content="<?= e($pageKeywords) ?>"><?php endif; ?>
  <meta name="theme-color" content="#102d5a">
  <title><?= e($pageTitle) ?></title>
  <link rel="icon" type="image/png" href="<?= e(app_url('/assets/collegekhoj-logo.png')) ?>">
  <link rel="stylesheet" href="<?= e(app_url('/assets/portal.css')) ?>">
</head>
<body class="site-body">
<?php render_tags('body'); ?>
<div class="topbar"><div class="container topbar-inner"><span class="topbar-tagline">🎓 Your next degree starts with the right guidance</span><strong>For Admission Guidance &amp; Support Only</strong></div></div>
<header class="site-header">
  <div class="container nav-inner">
    <a class="brand" href="<?= e(app_url('/')) ?>"><img src="<?= e(app_url('/assets/collegekhoj-logo.png')) ?>" alt="Collegekhoj" width="175" height="76"></a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" aria-label="Open menu"><span class="menu-toggle-bars" aria-hidden="true"><span></span><span></span><span></span></span></button>
    <nav id="primary-nav" class="nav-links" aria-label="Main navigation">
      <div class="nav-menu">
        <button class="nav-trigger" type="button" aria-expanded="false" aria-haspopup="true">Courses <svg class="nav-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
        <div class="dropdown mega-menu">
          <div class="mega-head"><span class="mega-head-icon" aria-hidden="true">🎓</span><div><strong>Explore courses</strong><small>Online degrees grouped by stream</small></div></div>
          <?php if ($navCourses): ?>
            <?php $navCourseGroups = []; foreach ($navCourses as $navCourse) { $navCourseGroups[$navCourse['category'] ?? 'Other courses'][] = $navCourse; } ?>
            <div class="mega-grid">
              <?php $navTone = 0; foreach ($navCourseGroups as $navCategory => $navGroupCourses): $navTone++; ?>
                <div class="mega-col tone-<?= ($navTone - 1) % 6 + 1 ?>">
                  <strong class="mega-col-title"><?= e($navCategory) ?></strong>
                  <?php foreach ($navGroupCourses as $navCourse): ?>
                    <a class="mega-item" href="<?= e(app_url('/colleges.php?course=' . (int) $navCourse['id'])) ?>">
                      <span class="mega-icon" aria-hidden="true"><?= course_icon($navCourse['name']) ?></span>
                      <span><strong><?= e($navCourse['name']) ?></strong><?php if ($navCourse['duration']): ?><small><?= e($navCourse['duration']) ?></small><?php endif; ?></span>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?><span class="dropdown-empty">Courses will appear here soon.</span><?php endif; ?>
          <a class="mega-footer" href="<?= e(app_url('/colleges.php')) ?>">Browse all colleges <span aria-hidden="true">→</span></a>
        </div>
      </div>
      <div class="nav-menu">
        <button class="nav-trigger" type="button" aria-expanded="false" aria-haspopup="true">Universities <svg class="nav-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
        <div class="dropdown mega-menu mega-narrow">
          <div class="mega-head"><span class="mega-head-icon" aria-hidden="true">🏛️</span><div><strong>Universities</strong><small>Browse colleges by institution</small></div></div>
          <?php if ($navUniversities): ?>
            <div class="mega-list">
              <?php foreach ($navUniversities as $navIndex => $navUniversity): ?>
                <a class="mega-item tone-<?= $navIndex % 6 + 1 ?>" href="<?= e(app_url('/colleges.php?university=' . rawurlencode($navUniversity['slug']))) ?>">
                  <span class="mega-icon mega-initials" aria-hidden="true"><?= e(institution_initials($navUniversity['name'])) ?></span>
                  <span><strong><?= e($navUniversity['name']) ?></strong><?php $navPlace = trim(($navUniversity['city'] ?? '') . ', ' . ($navUniversity['state'] ?? ''), ', '); if ($navPlace !== ''): ?><small><?= e($navPlace) ?></small><?php endif; ?></span>
                  <?php if ($navUniversity['featured']): ?><em class="mega-badge">Featured</em><?php endif; ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?><span class="dropdown-empty">Universities will appear here soon.</span><?php endif; ?>
          <a class="mega-footer" href="<?= e(app_url('/compare.php')) ?>">Compare colleges <span aria-hidden="true">→</span></a>
        </div>
      </div>
      <a href="<?= e(app_url('/colleges.php')) ?>">Colleges</a>
      <a href="<?= e(app_url('/compare.php')) ?>">Compare</a>
      <?php if (($_SESSION['user_role'] ?? '') === 'user'): ?>
        <form method="post" action="<?= e(app_url('/account.php')) ?>" class="inline-form">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout">
          <button class="nav-trigger" type="submit">Sign out</button>
        </form>
      <?php else: ?><a href="<?= e(app_url('/account.php')) ?>">Sign in</a><?php endif; ?>
      <a class="button button-small" href="<?= e(app_url('/colleges.php')) ?>">Find a college</a>
    </nav>
  </div>
</header>
<main>
