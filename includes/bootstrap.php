<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e(mixed $value): string
{
    if ($value !== null && !is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
        return '';
    }
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function request_value(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
}

function request_int(array $source, string $key): ?int
{
    $value = request_value($source, $key);
    if ($value === '') {
        return null;
    }
    $validated = filter_var($value, FILTER_VALIDATE_INT);
    return $validated === false ? null : (int) $validated;
}

function is_http_url(?string $value): bool
{
    if ($value === null || filter_var($value, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(400);
        exit('Invalid request token. Please reload the page and try again.');
    }
}

function require_admin(): void
{
    if (($_SESSION['user_role'] ?? '') === 'admin') {
        // Re-checked on every request so a deactivated or deleted admin is signed out immediately.
        $statement = db()->prepare("SELECT name FROM users WHERE id = :id AND role = 'admin' AND is_active = 1");
        $statement->execute(['id' => (int) ($_SESSION['user_id'] ?? 0)]);
        $name = $statement->fetchColumn();
        if ($name !== false) {
            $_SESSION['user_name'] = $name;
            return;
        }
        $_SESSION = [];
        session_regenerate_id(true);
    }
    header('Location: ' . app_url('/admin/login.php'));
    exit;
}

function active_admin_count(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
}

/** Validation errors for a new admin account; empty when the details are acceptable. */
function admin_account_errors(string $name, string $email, string $password, string $confirmation): array
{
    $errors = [];
    if ($name === '' || mb_strlen($name) > 120) {
        $errors[] = 'Enter a name of up to 120 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'Enter a valid email address.';
    }
    return array_merge($errors, admin_password_errors($password, $confirmation));
}

function admin_password_errors(string $password, string $confirmation): array
{
    if (strlen($password) < 12 || strlen($password) > 200) {
        return ['The password must be between 12 and 200 characters.'];
    }
    return hash_equals($password, $confirmation) ? [] : ['The passwords do not match.'];
}

/** Creates an active admin and returns its id, or null when the email is already registered. */
function create_admin_account(PDO $pdo, string $name, string $email, string $password): ?int
{
    try {
        $pdo->prepare("INSERT INTO users (name, email, password_hash, role, is_active) VALUES (:name, :email, :hash, 'admin', 1)")
            ->execute(['name' => $name, 'email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            return null;
        }
        throw $exception;
    }
    return (int) $pdo->lastInsertId();
}

function require_user(): void
{
    if (($_SESSION['user_role'] ?? '') !== 'user') {
        header('Location: ' . app_url('/account.php'));
        exit;
    }
}

function app_url(string $path = ''): string
{
    $configured = env('APP_BASE_PATH');
    if ($configured !== false) {
        $base = '/' . trim(str_replace('\\', '/', $configured), '/');
        if ($base === '/') {
            $base = '';
        }
    } else {
        $base = '';
        $root = realpath(dirname(__DIR__));
        $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($root !== false && $documentRoot !== false) {
            $rootNormalized = rtrim(str_replace('\\', '/', $root), '/');
            $documentNormalized = rtrim(str_replace('\\', '/', $documentRoot), '/');
            if (strncasecmp($rootNormalized, $documentNormalized . '/', strlen($documentNormalized) + 1) === 0) {
                $base = substr($rootNormalized, strlen($documentNormalized));
            }
        }
    }
    return $base . '/' . ltrim($path, '/');
}

function current_page_slug(): string
{
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'page.php') {
        return trim(request_value($_GET, 'slug'));
    }
    return pathinfo(basename($_SERVER['SCRIPT_NAME'] ?? 'index.php'), PATHINFO_FILENAME);
}

function render_tags(string $slot): void
{
    if (!in_array($slot, ['head', 'body'], true)) {
        throw new InvalidArgumentException('Invalid tag placement.');
    }
    $statement = db()->prepare(
        'SELECT tm.' . ($slot === 'head' ? 'head_code' : 'body_code') . ' AS code
         FROM tags_manager tm
         WHERE tm.is_active = 1
           AND (tm.target_type = \'global\' OR (tm.target_type = \'page\' AND tm.target_value = :slug))
         ORDER BY tm.target_type ASC, tm.id ASC'
    );
    $statement->execute(['slug' => current_page_slug()]);
    foreach ($statement->fetchAll() as $tag) {
        echo $tag['code'], "\n";
    }
}

function upload_image(string $field, ?string $currentPath = null): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return $currentPath;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Image upload failed or exceeds the 5 MB limit.');
    }
    $imageInfo = getimagesize($file['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($imageInfo === false || !isset($extensions[$mime])) {
        throw new RuntimeException('Upload a valid JPG, PNG, or WebP image.');
    }

    $directory = dirname(__DIR__) . '/assets/uploads';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create the image upload directory.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('The uploaded image could not be saved.');
    }
    return app_url('/assets/uploads/' . $filename);
}

function slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-');
}

function unique_slug(string $table, string $value, ?int $excludeId = null): string
{
    $tables = ['universities' => 'slug', 'courses' => 'slug', 'cms_pages' => 'slug'];
    if (!isset($tables[$table])) {
        throw new InvalidArgumentException('Unsupported slug table.');
    }
    $base = slugify($value);
    if ($base === '') {
        throw new InvalidArgumentException('Enter a title containing letters or numbers.');
    }
    $candidate = $base;
    $suffix = 2;
    while (true) {
        $sql = 'SELECT id FROM ' . $table . ' WHERE slug = :slug';
        $parameters = ['slug' => $candidate];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $parameters['id'] = $excludeId;
        }
        $query = db()->prepare($sql . ' LIMIT 1');
        $query->execute($parameters);
        if (!$query->fetch()) {
            return $candidate;
        }
        $candidate = $base . '-' . $suffix++;
    }
}

function flash(string $message, string $kind = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'kind' => $kind];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function course_icon(string $courseName): string
{
    // Icons matched on the programme name.
    $icons = [
        '/journalism|mass comm/i' => '📰', '/political/i' => '🏛️', '/sociology/i' => '🧠', '/economics/i' => '📈',
        '/english/i' => '📖', '/math/i' => '🔬', '/\bMBA\b/i' => '📊', '/\bMCA\b/i' => '💻', '/\bBBA\b/i' => '🚀',
        '/\bBCA\b/i' => '🧑‍💻', '/\bM\.?\s?Com\b/i' => '📗', '/\bB\.?\s?Com\b/i' => '📘', '/\bB\.?A\b/i' => '🌏',
    ];
    foreach ($icons as $pattern => $icon) {
        if (preg_match($pattern, $courseName)) {
            return $icon;
        }
    }
    return '🎓';
}

function institution_initials(string $name): string
{
    $initials = '';
    foreach (preg_split('/[\s\-—]+/', $name) ?: [] as $word) {
        if ($word !== '' && !preg_match('/^(online|of|and|the|&)$/i', $word)) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }
    }
    return mb_substr($initials, 0, 3) ?: '🎓';
}

function contact_email(): string
{
    $configured = env('CONTACT_EMAIL');
    return $configured !== false && filter_var($configured, FILTER_VALIDATE_EMAIL) ? $configured : 'help@collegekhoj.co.in';
}

function contact_email_link(string $class = 'text-link'): string
{
    $email = e(contact_email());
    return '<a class="' . e($class) . '" href="mailto:' . $email . '">' . $email . '</a>';
}

require_once __DIR__ . '/counselling.php';
require_once __DIR__ . '/home_tags.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/catalog.php';
