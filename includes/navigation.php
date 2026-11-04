<?php
declare(strict_types=1);

// Header menus: the Courses mega menu (grouped into postgraduate / undergraduate programmes)
// and the "More" menu, managed from admin/courses.php, admin/settings.php and admin/nav-links.php.

const COURSE_LEVELS = ['pg' => 'Postgraduate Programs', 'ug' => 'Undergraduate Programs', 'other' => 'Other Programs'];

const NAV_LINK_ICONS = [
    'phone' => 'Phone', 'mail' => 'Mail', 'users' => 'People', 'briefcase' => 'Briefcase', 'shield' => 'Shield',
    'info' => 'Info', 'page' => 'Page', 'star' => 'Star', 'book' => 'Book', 'chat' => 'Chat',
];

/** Special link targets that open a popup instead of navigating. */
const NAV_LINK_ACTIONS = ['#talk-to-expert' => 'expert-modal', '#counselling' => 'counselling-modal'];

function guess_course_level(string $name): string
{
    if (preg_match('/\b(PG|PGD|PGDM|Master|M\.?\s?(BA|CA|Com|A|Sc|Tech|Ed|Lib))\b/i', $name)) {
        return 'pg';
    }
    if (preg_match('/\b(Bachelor|B\.?\s?(BA|CA|Com|A|Sc|Tech|Ed|Lib))\b/i', $name)) {
        return 'ug';
    }
    return 'other';
}

function guess_course_full_name(string $name): ?string
{
    $names = [
        'MBA' => 'Master of Business Administration', 'MCA' => 'Master of Computer Applications', 'MCom' => 'Master of Commerce',
        'MA' => 'Master of Arts', 'MSc' => 'Master of Science', 'PGDM' => 'PG Diploma in Management',
        'BBA' => 'Bachelor of Business Administration', 'BCA' => 'Bachelor of Computer Applications', 'BCom' => 'Bachelor of Commerce',
        'BA' => 'Bachelor of Arts', 'BSc' => 'Bachelor of Science',
    ];
    foreach ($names as $short => $full) {
        $pattern = '/\b' . implode('\.?\s?', str_split($short)) . '\b/i';
        if (preg_match($pattern, $name)) {
            return $full;
        }
    }
    return null;
}

/** Adds courses.level / courses.full_name to databases created before the grouped menu, classifying existing courses. */
function ensure_course_menu_columns(PDO $pdo): void
{
    $columns = $pdo->query('SHOW COLUMNS FROM courses')->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('level', $columns, true) && in_array('full_name', $columns, true)) {
        return;
    }
    if (!in_array('level', $columns, true)) {
        $pdo->exec("ALTER TABLE courses ADD COLUMN level ENUM('pg','ug','other') NOT NULL DEFAULT 'ug' AFTER university_id");
    }
    if (!in_array('full_name', $columns, true)) {
        $pdo->exec('ALTER TABLE courses ADD COLUMN full_name VARCHAR(190) NULL AFTER name');
    }
    $update = $pdo->prepare('UPDATE courses SET level = :level, full_name = COALESCE(full_name, :full_name) WHERE id = :id');
    foreach ($pdo->query('SELECT id, name FROM courses')->fetchAll() as $course) {
        $update->execute(['level' => guess_course_level($course['name']), 'full_name' => guess_course_full_name($course['name']), 'id' => $course['id']]);
    }
}

function nav_courses(PDO $pdo): array
{
    $sql = 'SELECT c.id, c.name, c.slug, c.full_name, c.level, c.duration, cc.name AS category
            FROM courses c LEFT JOIN course_categories cc ON cc.id = c.category_id
            WHERE c.is_active = 1 ORDER BY cc.sort_order, cc.name, c.name';
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (PDOException) {
        ensure_course_menu_columns($pdo);
        return $pdo->query($sql)->fetchAll();
    }
}

function ensure_nav_links_table(PDO $pdo): void
{
    $exists = $pdo->query("SHOW TABLES LIKE 'nav_links'")->fetchColumn();
    if ($exists) {
        return;
    }
    $pdo->exec(
        'CREATE TABLE nav_links (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          label VARCHAR(80) NOT NULL,
          subtitle VARCHAR(120) NULL,
          url VARCHAR(500) NOT NULL,
          icon VARCHAR(30) NOT NULL DEFAULT \'page\',
          sort_order INT NOT NULL DEFAULT 0,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_nav_links_display (is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        "INSERT INTO nav_links (label, subtitle, url, icon, sort_order) VALUES
        ('Contact Us', 'Get in touch', '#talk-to-expert', 'phone', 10),
        ('About Us', 'Who we are', '/page.php?slug=about', 'users', 20),
        ('Free Counselling', 'Talk to an expert', '#counselling', 'chat', 30),
        ('Privacy Policy', 'Your data, protected', '/page.php?slug=privacy-policy', 'shield', 40)"
    );
}

function nav_more_links(PDO $pdo): array
{
    try {
        return $pdo->query('SELECT label, subtitle, url, icon FROM nav_links WHERE is_active = 1 ORDER BY sort_order, label')->fetchAll();
    } catch (PDOException) {
        ensure_nav_links_table($pdo);
        return $pdo->query('SELECT label, subtitle, url, icon FROM nav_links WHERE is_active = 1 ORDER BY sort_order, label')->fetchAll();
    }
}

function is_valid_nav_link_url(string $url): bool
{
    return isset(NAV_LINK_ACTIONS[$url]) || is_valid_home_tag_url($url);
}

/** Attributes for a More-menu link: popups get data-open-modal, external links open in a new tab. */
function nav_link_attributes(string $url): string
{
    if (isset(NAV_LINK_ACTIONS[$url])) {
        return 'href="' . e(app_url('/?counsel=1')) . '" data-open-modal="' . e(NAV_LINK_ACTIONS[$url]) . '"';
    }
    if (str_starts_with($url, '/')) {
        return 'href="' . e(app_url($url)) . '"';
    }
    return 'href="' . e($url) . '" target="_blank" rel="noopener"';
}

function nav_icon(string $name): string
{
    $paths = [
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
        'page' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v5h6M8 13h8M8 17h5"/>',
        'star' => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1Z"/>',
        'book' => '<path d="M2 4h7a3 3 0 0 1 3 3v13a2 2 0 0 0-2-2H2Z"/><path d="M22 4h-7a3 3 0 0 0-3 3v13a2 2 0 0 1 2-2h8Z"/>',
        'chat' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/>',
    ];
    return '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['page']) . '</svg>';
}
