<?php
declare(strict_types=1);

// "Popular searches" tags shown above the footer on the home page, managed from admin/home-tags.php.

function ensure_home_tags_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS home_tags (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          group_name VARCHAR(80) NOT NULL,
          label VARCHAR(120) NOT NULL,
          url VARCHAR(500) NOT NULL,
          sort_order INT NOT NULL DEFAULT 0,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_home_tags_display (is_active, group_name, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/** Internal links are stored as site-relative paths ("/colleges.php?course=1"); external links as full http(s) URLs. */
function is_valid_home_tag_url(string $url): bool
{
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        return !preg_match('/[\s<>"]/', $url);
    }
    return is_http_url($url);
}

function home_tag_href(string $url): string
{
    return str_starts_with($url, '/') ? app_url($url) : $url;
}

/** Active tags grouped for display; groups are ordered by their lowest sort order. */
function home_tag_groups(PDO $pdo): array
{
    try {
        $rows = $pdo->query(
            'SELECT t.group_name, t.label, t.url FROM home_tags t
             JOIN (SELECT group_name, MIN(sort_order) AS group_order FROM home_tags WHERE is_active = 1 GROUP BY group_name) g
               ON g.group_name = t.group_name
             WHERE t.is_active = 1 ORDER BY g.group_order, t.group_name, t.sort_order, t.label'
        )->fetchAll();
    } catch (PDOException) {
        return []; // Table not created yet: the section is simply hidden.
    }
    $groups = [];
    foreach ($rows as $row) {
        $groups[$row['group_name']][] = $row;
    }
    return $groups;
}

/** Tags generated from the live catalog, used by the "Add suggested tags" action. */
function home_tag_suggestions(PDO $pdo): array
{
    $suggestions = [];
    $add = static function (string $group, int $groupOrder, array $rows, callable $build) use (&$suggestions): void {
        foreach (array_values($rows) as $index => $row) {
            [$label, $query] = $build($row);
            $suggestions[] = ['group_name' => $group, 'label' => $label, 'url' => '/colleges.php?' . http_build_query($query), 'sort_order' => $groupOrder + ($index + 1) * 10];
        }
    };
    $add('Popular courses', 1000, $pdo->query('SELECT id, name FROM courses WHERE is_active = 1 ORDER BY name')->fetchAll(),
        static fn (array $r): array => [course_icon($r['name']) . ' ' . $r['name'], ['course' => (int) $r['id']]]);
    $add('Streams', 2000, $pdo->query('SELECT DISTINCT cc.id, cc.name, cc.sort_order FROM course_categories cc JOIN courses c ON c.category_id = cc.id AND c.is_active = 1 ORDER BY cc.sort_order, cc.name')->fetchAll(),
        static fn (array $r): array => [$r['name'] . ' colleges', ['category' => (int) $r['id']]]);
    $add('Universities', 3000, $pdo->query('SELECT name, slug FROM universities WHERE is_active = 1 ORDER BY featured DESC, name')->fetchAll(),
        static fn (array $r): array => [$r['name'], ['university' => $r['slug']]]);
    $add('Colleges by location', 4000, $pdo->query("SELECT place FROM (SELECT city AS place FROM colleges WHERE is_active = 1 AND city <> '' UNION SELECT state FROM colleges WHERE is_active = 1 AND state <> '') places WHERE place IS NOT NULL ORDER BY place")->fetchAll(),
        static fn (array $r): array => ['Colleges in ' . $r['place'], ['city' => $r['place']]]);
    $add('Entrance exams', 5000, $pdo->query('SELECT DISTINCT e.id, e.name FROM exams e JOIN college_exams ce ON ce.exam_id = e.id JOIN colleges c ON c.id = ce.college_id AND c.is_active = 1 WHERE e.is_active = 1 ORDER BY e.name')->fetchAll(),
        static fn (array $r): array => [$r['name'] . ' colleges', ['exam' => (int) $r['id']]]);
    return $suggestions;
}

/** Inserts suggestions whose URL is not already used by a tag. Returns the number added. */
function add_home_tag_suggestions(PDO $pdo): int
{
    $existing = array_flip($pdo->query('SELECT url FROM home_tags')->fetchAll(PDO::FETCH_COLUMN));
    $insert = $pdo->prepare('INSERT INTO home_tags (group_name, label, url, sort_order, is_active) VALUES (:group_name, :label, :url, :sort_order, 1)');
    $added = 0;
    foreach (home_tag_suggestions($pdo) as $tag) {
        if (!isset($existing[$tag['url']])) {
            $insert->execute($tag);
            $existing[$tag['url']] = true;
            $added++;
        }
    }
    return $added;
}
