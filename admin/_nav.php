<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$adminFlash = take_flash();

if (!function_exists('admin_icon')) {
    function admin_icon(string $name): string
    {
        $paths = [
            'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
            'college' => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/>',
            'university' => '<path d="M3 21h18"/><path d="M5 21V8l7-5 7 5v13"/><path d="M9 21v-6h6v6"/><path d="M9 10h.01M15 10h.01"/>',
            'book' => '<path d="M2 4h7a3 3 0 0 1 3 3v13a2 2 0 0 0-2-2H2Z"/><path d="M22 4h-7a3 3 0 0 0-3 3v13a2 2 0 0 1 2-2h8Z"/>',
            'exam' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="m9 15 2 2 4-4"/>',
            'star' => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1Z"/>',
            'chat' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/>',
            'page' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v5h6M8 13h8M8 17h5"/>',
            'tag' => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8Z"/><path d="M7 7h.01"/>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
            'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1Z"/>',
            'chevron' => '<path d="m9 18 6-6-6-6"/>',
            'external' => '<path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
            'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
            'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        ];
        return '<svg class="ai" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
    }
}

$adminName = (string) ($_SESSION['user_name'] ?? 'Administrator');
$adminInitials = '';
foreach (preg_split('/\s+/', trim($adminName)) ?: [] as $part) {
    if ($part !== '' && strlen($adminInitials) < 2) {
        $adminInitials .= strtoupper(substr($part, 0, 1));
    }
}
$adminInitials = $adminInitials ?: 'A';
$adminScript = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$adminMenu = [
    ['index.php', 'Dashboard', 'dashboard', 'blue'],
    null,
    ['universities.php', 'Universities', 'university', 'amber'],
    ['colleges.php', 'Colleges', 'college', 'teal'],
    ['courses.php', 'Courses', 'book', 'indigo'],
    ['exams.php', 'Exams', 'exam', 'rose'],
    null,
    ['reviews.php', 'Reviews &amp; Q&amp;A', 'star', 'gold'],
    ['pages.php', 'Pages &amp; SEO', 'page', 'violet'],
    ['home-tags.php', 'Home page tags', 'search', 'teal'],
    ['tags.php', 'Tag manager', 'tag', 'green'],
];
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($adminTitle ?? 'Admin CMS') ?> | Collegekhoj</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700;900&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(app_url('/assets/portal.css')) ?>">
<link rel="stylesheet" href="<?= e(app_url('/assets/admin.css')) ?>"></head>
<body class="admin-body">
<header class="adm-topbar">
  <button class="adm-icon-btn adm-menu-toggle" type="button" aria-label="Toggle menu" aria-controls="adm-sidebar" aria-expanded="false"><?= admin_icon('menu') ?></button>
  <a class="adm-brand" href="<?= e(app_url('/admin/index.php')) ?>"><span class="adm-brand-mark"><img src="<?= e(app_url('/assets/collegekhoj-logo.png')) ?>" alt=""></span><span>CMS &ndash; Collegekhoj</span></a>
  <label class="adm-search"><?= admin_icon('search') ?><input type="search" id="adm-search" placeholder="Search menu (colleges, exams, pages, tags&hellip;)" autocomplete="off" aria-label="Search admin menu"><kbd>CTRL</kbd><kbd>K</kbd></label>
  <div class="adm-top-actions">
    <a class="adm-icon-btn" href="<?= e(app_url('/')) ?>" target="_blank" rel="noopener" title="View public site" aria-label="View public site"><?= admin_icon('external') ?></a>
    <a class="adm-icon-btn" href="<?= e(app_url('/admin/reviews.php')) ?>" title="Reviews &amp; Q&amp;A" aria-label="Reviews and Q&amp;A"><?= admin_icon('chat') ?></a>
    <span class="adm-avatar" title="<?= e($adminName) ?>"><?= e($adminInitials) ?></span>
  </div>
</header>
<div class="adm-layout">
  <aside class="adm-sidebar" id="adm-sidebar">
    <div class="adm-menu-label">Menu</div>
    <nav class="adm-nav" aria-label="Admin navigation">
      <?php foreach ($adminMenu as $item): ?>
        <?php if ($item === null): ?><hr class="adm-nav-divider"><?php continue; endif; ?>
        <?php [$file, $label, $icon, $tone] = $item; ?>
        <a class="adm-nav-link<?= $adminScript === $file ? ' active' : '' ?>" href="<?= e(app_url('/admin/' . $file)) ?>"<?= $adminScript === $file ? ' aria-current="page"' : '' ?>>
          <span class="adm-nav-icon tone-<?= $tone ?>"><?= admin_icon($icon) ?></span><span class="adm-nav-text"><?= $label ?></span>
        </a>
      <?php endforeach; ?>
      <p class="adm-nav-empty" hidden>No matching menu items.</p>
    </nav>
    <div class="adm-user">
      <span class="adm-avatar adm-avatar-dark"><?= e($adminInitials) ?></span>
      <div class="adm-user-meta"><strong><?= e($adminName) ?></strong><small>Admin</small></div>
      <form method="post" action="<?= e(app_url('/admin/logout.php')) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="adm-icon-btn adm-logout" type="submit" title="Sign out" aria-label="Sign out"><?= admin_icon('logout') ?></button></form>
    </div>
  </aside>
  <div class="adm-backdrop" hidden></div>
  <main class="adm-main">
    <nav class="adm-breadcrumb" aria-label="Breadcrumb">
      <a href="<?= e(app_url('/admin/index.php')) ?>"><?= admin_icon('home') ?>Home</a>
      <?= admin_icon('chevron') ?><span><?= $adminScript === 'index.php' ? 'Dashboard' : e($adminTitle ?? 'Admin') ?></span>
    </nav>
    <?php if (empty($adminHideTitle)): ?>
      <div class="adm-page-head"><h1><?= e($adminTitle ?? 'Admin') ?></h1><p>Collegekhoj content management</p></div>
    <?php endif; ?>
    <?php if ($adminFlash): ?><div class="notice <?= e($adminFlash['kind'] === 'error' ? 'error' : '') ?>"><?= e($adminFlash['message']) ?></div><?php endif; ?>
