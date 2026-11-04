<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// Receives the "Free counselling" form. Answers JSON to the modal's fetch() call,
// or redirects back to the page with a notice when JavaScript is unavailable.

$wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
$source = safe_source_path(trim(request_value($_POST, 'source')));

$respond = static function (bool $ok, string $message) use ($wantsJson, $source): never {
    if ($wantsJson) {
        http_response_code($ok ? 200 : 422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    } else {
        flash($message, $ok ? 'success' : 'error');
        header('Location: ' . $source);
    }
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('/'));
    exit;
}
$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
    $respond(false, 'Your session expired. Please reload the page and try again.');
}
// Bots fill the hidden "website" field; pretend success so they move on.
if (trim(request_value($_POST, 'website')) !== '') {
    $respond(true, site_setting('form_success_message'));
}
if (time() - (int) ($_SESSION['last_lead_at'] ?? 0) < 30) {
    $respond(false, 'We have just received your details. Please wait a moment before submitting again.');
}

$name = trim(request_value($_POST, 'name'));
$email = filter_var(trim(request_value($_POST, 'email')), FILTER_VALIDATE_EMAIL);
$phone = normalize_phone(request_value($_POST, 'phone'));
$city = trim(request_value($_POST, 'city'));
if ($name === '' || mb_strlen($name) > 120) {
    $respond(false, 'Please enter your full name.');
}
if (!$email || strlen($email) > 190) {
    $respond(false, 'Please enter a valid email address.');
}
if ($phone === null) {
    $respond(false, 'Please enter a valid phone number (10 digits).');
}
if ($city === '' || mb_strlen($city) > 100) {
    $respond(false, 'Please enter your city.');
}
if (!isset($_POST['consent'])) {
    $respond(false, 'Please accept the consent to be contacted.');
}

$pdo = db();
$lookup = static function (string $table, ?int $id) use ($pdo): ?array {
    if (!$id) {
        return null;
    }
    $query = $pdo->prepare('SELECT id, name FROM ' . $table . ' WHERE id = :id AND is_active = 1');
    $query->execute(['id' => $id]);
    return $query->fetch() ?: null;
};
$course = $lookup('courses', request_int($_POST, 'course_id'));
$university = $lookup('universities', request_int($_POST, 'university_id'));

ensure_leads_table($pdo);
$pdo->prepare(
    'INSERT INTO leads (name, email, phone, city, course_id, course_name, university_id, university_name, source_page, ip_address)
     VALUES (:name, :email, :phone, :city, :course_id, :course_name, :university_id, :university_name, :source_page, :ip_address)'
)->execute([
    'name' => $name, 'email' => $email, 'phone' => $phone, 'city' => $city,
    'course_id' => $course['id'] ?? null, 'course_name' => $course['name'] ?? null,
    'university_id' => $university['id'] ?? null, 'university_name' => $university['name'] ?? null,
    'source_page' => $source, 'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
]);
$_SESSION['last_lead_at'] = time();
$respond(true, site_setting('form_success_message'));
