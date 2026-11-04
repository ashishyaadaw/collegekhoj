<?php
ensure_catalog_schema(db());
$navCourses = nav_courses(db());
$navCourseLevels = [];
foreach ($navCourses as $navCourse) {
    $navCourseLevels[$navCourse['level']][] = $navCourse;
}
$navCourseLevels = array_filter(array_merge(array_fill_keys(array_keys(COURSE_LEVELS), []), $navCourseLevels));
$navPopular = null;
foreach ($navCourses as $navCourse) {
    if ((string) $navCourse['id'] === site_setting('menu_popular_course_id')) {
        $navPopular = $navCourse;
    }
}
$navPopular ??= ($navCourseLevels['pg'][0] ?? $navCourses[0] ?? null);
$navMoreLinks = nav_more_links(db());
$navUniversities = db()->query(
    'SELECT u.id, u.name, u.slug, u.featured, u.city, u.state,
            (SELECT GROUP_CONCAT(cu.course_id) FROM course_universities cu WHERE cu.university_id = u.id) AS course_ids
     FROM universities u WHERE u.is_active = 1 ORDER BY u.featured DESC, u.name'
)->fetchAll();
$pageTitle = $pageTitle ?? 'Online Courses & Universities | Collegekhoj';
$pageDescription = $pageDescription ?? 'Explore online courses and universities and get free admission counselling with Collegekhoj.';
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
  <?php if (!empty($pageCanonical)): ?><link rel="canonical" href="<?= e(absolute_url($pageCanonical)) ?>"><?php endif; ?>
  <link rel="icon" type="image/png" href="<?= e(app_url('/assets/collegekhoj-logo.png')) ?>">
  <link rel="stylesheet" href="<?= e(app_url('/assets/portal.css')) ?>">
</head>
<body class="site-body">
<?php render_tags('body'); ?>
<?php if (site_setting('topbar_enabled') === '1'): $topPhone = trim(site_setting('topbar_phone')); $topEmail = trim(site_setting('topbar_email')); $topTagline = site_setting('topbar_tagline'); $topSocial = social_links(); ?>
<div class="topbar"><div class="container topbar-inner">
  <div class="topbar-contacts">
    <?php if ($topPhone !== ''): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $topPhone)) ?>"><?= ui_icon('phone') ?><?= e($topPhone) ?></a><?php endif; ?>
    <?php if ($topEmail !== ''): ?><a href="mailto:<?= e($topEmail) ?>"><?= ui_icon('mail') ?><?= e($topEmail) ?></a><?php endif; ?>
  </div>
  <?php if ($topTagline !== '' || $topSocial): ?>
  <div class="topbar-social">
    <?php if ($topTagline !== ''): ?><span class="topbar-tagline"><?= e($topTagline) ?></span><?php endif; ?>
    <?php foreach ($topSocial as $network => $social): ?><a class="topbar-social-link" href="<?= e($social['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($social['label']) ?>"><?= ui_icon($network) ?></a><?php endforeach; ?>
  </div>
  <?php endif; ?>
</div></div>
<?php endif; ?>
<header class="site-header">
  <div class="container nav-inner">
    <a class="brand" href="<?= e(app_url('/')) ?>"><img src="<?= e(app_url('/assets/collegekhoj-logo.png')) ?>" alt="Collegekhoj" width="175" height="76"></a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" aria-label="Open menu"><span class="menu-toggle-bars" aria-hidden="true"><span></span><span></span><span></span></span></button>
    <nav id="primary-nav" class="nav-links" aria-label="Main navigation">
      <div class="nav-menu">
        <button class="nav-trigger" type="button" aria-expanded="false" aria-haspopup="true">Courses <svg class="nav-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
        <div class="dropdown mega-menu mega-courses">
          <?php if ($navCourses): ?>
            <div class="mc-layout">
              <?php foreach ($navCourseLevels as $navLevel => $navLevelCourses): ?>
                <div class="mc-col mc-<?= e($navLevel) ?>">
                  <div class="mc-col-head"><span class="mc-level"><?= e(strtoupper($navLevel === 'other' ? 'More' : $navLevel)) ?></span><strong><?= e(COURSE_LEVELS[$navLevel]) ?></strong></div>
                  <?php foreach ($navLevelCourses as $navIndex => $navCourse): ?>
                    <a class="mc-item dot-<?= $navIndex % 6 + 1 ?>" href="<?= e(course_url($navCourse['slug'])) ?>">
                      <strong><?= e($navCourse['name']) ?></strong>
                      <?php $navSub = $navCourse['full_name'] ?: $navCourse['duration']; if ($navSub): ?><small><?= e($navSub) ?></small><?php endif; ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>
              <?php if ($navPopular): ?>
                <div class="mc-popular">
                  <span class="mc-popular-art" aria-hidden="true">🎓</span>
                  <?php if (site_setting('menu_popular_badge') !== ''): ?><span class="mc-popular-badge"><span aria-hidden="true">🔥</span> <?= e(site_setting('menu_popular_badge')) ?></span><?php endif; ?>
                  <strong class="mc-popular-name"><?= e($navPopular['name']) ?></strong>
                  <p><?= e(site_setting('menu_popular_text')) ?></p>
                  <a class="mc-popular-btn" href="<?= e(course_url($navPopular['slug'])) ?>"><?= e(site_setting('menu_popular_button')) ?> <span aria-hidden="true">→</span></a>
                </div>
              <?php endif; ?>
            </div>
          <?php else: ?><span class="dropdown-empty">Courses will appear here soon.</span><?php endif; ?>
        </div>
      </div>
      <div class="nav-menu">
        <button class="nav-trigger" type="button" aria-expanded="false" aria-haspopup="true">Universities <svg class="nav-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
        <div class="dropdown mega-menu mega-narrow">
          <div class="mega-head"><span class="mega-head-icon" aria-hidden="true">🏛️</span><div><strong>Universities</strong><small>Explore institutions and their programmes</small></div></div>
          <?php if ($navUniversities): ?>
            <div class="mega-list">
              <?php foreach ($navUniversities as $navIndex => $navUniversity): ?>
                <a class="mega-item tone-<?= $navIndex % 6 + 1 ?>" href="<?= e(university_url($navUniversity['slug'])) ?>">
                  <span class="mega-icon mega-initials" aria-hidden="true"><?= e(institution_initials($navUniversity['name'])) ?></span>
                  <span><strong><?= e($navUniversity['name']) ?></strong><?php $navPlace = trim(($navUniversity['city'] ?? '') . ', ' . ($navUniversity['state'] ?? ''), ', '); if ($navPlace !== ''): ?><small><?= e($navPlace) ?></small><?php endif; ?></span>
                  <?php if ($navUniversity['featured']): ?><em class="mega-badge">Featured</em><?php endif; ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?><span class="dropdown-empty">Universities will appear here soon.</span><?php endif; ?>
          <a class="mega-footer" href="<?= e(app_url('/?counsel=1')) ?>" data-counselling>Get free counselling <span aria-hidden="true">→</span></a>
        </div>
      </div>
      <?php if ($navMoreLinks): ?>
      <div class="nav-menu">
        <button class="nav-trigger" type="button" aria-expanded="false" aria-haspopup="true">More <svg class="nav-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
        <div class="dropdown mega-menu mega-more">
          <div class="more-grid">
            <?php foreach ($navMoreLinks as $navLink): ?>
              <a class="more-item" <?= nav_link_attributes($navLink['url']) ?>>
                <span class="more-icon"><?= nav_icon($navLink['icon']) ?></span>
                <span><strong><?= e($navLink['label']) ?></strong><?php if ($navLink['subtitle']): ?><small><?= e($navLink['subtitle']) ?></small><?php endif; ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <?php if (($_SESSION['user_role'] ?? '') === 'user'): ?>
        <form method="post" action="<?= e(app_url('/account.php')) ?>" class="inline-form">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout">
          <button class="nav-trigger" type="submit">Sign out</button>
        </form>
      <?php endif; ?>
      <button class="button button-small button-expert" type="button" data-open-modal="expert-modal"><?= ui_icon('phone') ?><?= e(site_setting('header_button_label')) ?></button>
    </nav>
  </div>
</header>
<main>
<?php $siteFlash = take_flash(); if ($siteFlash): ?><div class="container"><div class="notice<?= $siteFlash['kind'] === 'error' ? ' error' : '' ?>" role="status"><?= e($siteFlash['message']) ?></div></div><?php endif; ?>
