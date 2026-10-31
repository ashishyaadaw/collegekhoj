<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$pdo = db();
$count = static fn (string $sql): int => (int) $pdo->query($sql)->fetchColumn();
$stats = [
    ['Active universities', $count('SELECT COUNT(*) FROM universities WHERE is_active = 1'), 'universities.php', 'university', 'amber'],
    ['Active colleges', $count('SELECT COUNT(*) FROM colleges WHERE is_active = 1'), 'colleges.php', 'college', 'teal'],
    ['Active courses', $count('SELECT COUNT(*) FROM courses WHERE is_active = 1'), 'courses.php', 'book', 'indigo'],
    ['Active exams', $count('SELECT COUNT(*) FROM exams WHERE is_active = 1'), 'exams.php', 'exam', 'rose'],
    ['Published pages', $count("SELECT COUNT(*) FROM cms_pages WHERE status = 'published'"), 'pages.php', 'page', 'violet'],
    ['Active tracking tags', $count('SELECT COUNT(*) FROM tags_manager WHERE is_active = 1'), 'tags.php', 'tag', 'green'],
    ['Pending reviews', $count("SELECT COUNT(*) FROM reviews WHERE status = 'pending'"), 'reviews.php', 'star', 'gold'],
    ['Pending questions', $count("SELECT COUNT(*) FROM college_questions WHERE status = 'pending'"), 'reviews.php', 'chat', 'blue'],
    ['Registered students', $count("SELECT COUNT(*) FROM users WHERE role = 'user'"), null, 'users', 'navy'],
];
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$adminTitle = 'Admin dashboard';
$adminHideTitle = true;
require __DIR__ . '/_nav.php';
?>
<section class="adm-welcome">
  <div class="adm-welcome-user">
    <span class="adm-avatar adm-avatar-lg"><?= e($adminInitials) ?></span>
    <div>
      <p class="adm-welcome-greet" data-greeting><?= e($greeting) ?>,</p>
      <h1><?= e($adminName) ?></h1>
      <span class="adm-role-badge"><?= admin_icon('user') ?>System Administrator</span>
    </div>
  </div>
  <div class="adm-welcome-date" data-today>
    <small><?= e(date('l')) ?></small>
    <strong><?= e(date('F j, Y')) ?></strong>
  </div>
</section>

<div class="adm-section-title">
  <h2>Global Overview</h2>
  <p>Catalog content, moderation queue and site configuration at a glance.</p>
</div>
<section class="adm-stats">
  <?php foreach ($stats as [$label, $value, $link, $icon, $tone]): ?>
    <?php $tag = $link ? 'a' : 'div'; ?>
    <<?= $tag ?> class="adm-stat tone-<?= $tone ?>"<?= $link ? ' href="' . e(app_url('/admin/' . $link)) . '"' : '' ?>>
      <div class="adm-stat-head"><span class="adm-stat-label"><?= e($label) ?></span><span class="adm-stat-icon"><?= admin_icon($icon) ?></span></div>
      <strong class="adm-stat-value"><?= number_format($value) ?></strong>
    </<?= $tag ?>>
  <?php endforeach; ?>
</section>
<p class="adm-hint"><?= admin_icon('clock') ?>Uploaded images accept JPG, PNG and WebP files up to 5 MB. Press <kbd>Ctrl</kbd> + <kbd>K</kbd> to jump to any section.</p>
<script>
(function () {
  var now = new Date(), h = now.getHours();
  document.querySelector('[data-greeting]').textContent = (h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening') + ',';
  var box = document.querySelector('[data-today]');
  box.querySelector('small').textContent = now.toLocaleDateString('en-US', { weekday: 'long' });
  box.querySelector('strong').textContent = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
})();
</script>
<?php require __DIR__ . '/_end.php'; ?>
