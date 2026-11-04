<?php
declare(strict_types=1);

// Course and university detail pages (course.php, university.php) and the
// course <-> university relationship, managed from admin/courses.php and admin/universities.php.

function course_url(string $slug): string
{
    return app_url('/courses/' . rawurlencode($slug));
}

function university_url(string $slug): string
{
    return app_url('/universities/' . rawurlencode($slug));
}

/**
 * Adds the detail-page columns and the course_universities table to databases created before
 * the detail pages, copying each course's single university link into the new table.
 */
function ensure_catalog_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    if ($pdo->query("SHOW TABLES LIKE 'course_universities'")->fetchColumn()) {
        return;
    }
    ensure_course_menu_columns($pdo);
    $add = static function (string $table, array $definitions) use ($pdo): void {
        $existing = $pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($definitions as $column => $definition) {
            if (!in_array($column, $existing, true)) {
                $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
    };
    $add('courses', [
        'highlights' => 'TEXT NULL', 'syllabus' => 'TEXT NULL', 'career_options' => 'TEXT NULL',
        'meta_title' => 'VARCHAR(190) NULL', 'meta_description' => 'VARCHAR(320) NULL',
    ]);
    $add('universities', [
        'approvals' => 'VARCHAR(255) NULL', 'highlights' => 'TEXT NULL',
        'meta_title' => 'VARCHAR(190) NULL', 'meta_description' => 'VARCHAR(320) NULL',
    ]);
    $pdo->exec(
        'CREATE TABLE course_universities (
          course_id BIGINT UNSIGNED NOT NULL,
          university_id BIGINT UNSIGNED NOT NULL,
          fee DECIMAL(12,2) NULL,
          PRIMARY KEY (course_id, university_id),
          INDEX idx_course_universities_university (university_id),
          CONSTRAINT fk_cu_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
          CONSTRAINT fk_cu_university FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    if (in_array('university_id', $pdo->query('SHOW COLUMNS FROM courses')->fetchAll(PDO::FETCH_COLUMN), true)) {
        $pdo->exec('INSERT IGNORE INTO course_universities (course_id, university_id) SELECT id, university_id FROM courses WHERE university_id IS NOT NULL');
    }
}

/** Multi-line admin text as a list: one item per line, ignoring blank lines and leading bullets. */
function text_lines(?string $text): array
{
    $lines = [];
    foreach (preg_split('/\R/', (string) $text) ?: [] as $line) {
        $line = trim(preg_replace('/^\s*(?:[-*•]|\d+[.)])\s*/u', '', $line) ?? '');
        if ($line !== '') {
            $lines[] = $line;
        }
    }
    return $lines;
}

/** Plain text with blank-line paragraphs, escaped. */
function text_paragraphs(?string $text): string
{
    $html = '';
    foreach (preg_split('/\R\s*\R/', trim((string) $text)) ?: [] as $paragraph) {
        if (trim($paragraph) !== '') {
            $html .= '<p>' . nl2br(e(trim($paragraph)), false) . '</p>';
        }
    }
    return $html;
}

function format_inr(float|string|null $amount): ?string
{
    if ($amount === null || $amount === '') {
        return null;
    }
    // Indian digit grouping: 1,50,000.
    $rupees = (string) (int) round((float) $amount);
    $last3 = substr($rupees, -3);
    $rest = substr($rupees, 0, -3);
    if ($rest !== '') {
        $last3 = ',' . $last3;
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
    }
    return '₹' . $rest . $last3;
}

function fee_range(float|string|null $min, float|string|null $max): ?string
{
    $min = format_inr($min);
    $max = format_inr($max);
    if ($min && $max && $min !== $max) {
        return $min . ' – ' . $max;
    }
    return $min ?? $max;
}

function render_not_found(string $what): never
{
    http_response_code(404);
    $pageTitle = 'Page not found | Collegekhoj';
    require __DIR__ . '/header.php';
    echo '<section class="section"><div class="container detail-card" style="text-align:center"><h1 style="color:var(--navy)">' . e($what) . ' not found</h1>'
        . '<p class="muted">It may have been renamed or is no longer offered.</p>'
        . '<p><a class="button" href="' . e(app_url('/')) . '">Back to home</a> <button class="button button-cta" type="button" data-open-modal="counselling-modal">Get free counselling</button></p></div></section>';
    require __DIR__ . '/footer.php';
    exit;
}

/** JSON-LD block, escaped so content cannot close the script element. */
function json_ld(array $data): string
{
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
}

function absolute_url(string $path): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return ($https ? 'https' : 'http') . '://' . $host . $path;
}
