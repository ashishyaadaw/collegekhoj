<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$statement = db()->prepare('SELECT title, body, meta_title, meta_description, meta_keywords FROM cms_pages WHERE slug = :slug AND status = \'published\' LIMIT 1');
$statement->execute(['slug' => trim(request_value($_GET, 'slug'))]);
$page = $statement->fetch();
if (!$page) {
    http_response_code(404);
    $pageTitle = 'Page not found | Collegekhoj';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section container"><h1>Page not found</h1></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}
$pageTitle = $page['meta_title'] ?: $page['title'];
$pageDescription = $page['meta_description'] ?: 'Collegekhoj information page.';
$pageKeywords = $page['meta_keywords'] ?? '';
require __DIR__ . '/includes/header.php';
?>
<section class="section"><article class="container detail-card"><h1 style="color:var(--navy)"><?= e($page['title']) ?></h1>
  <div class="cms-content"><?= $page['body'] ?></div></article></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
