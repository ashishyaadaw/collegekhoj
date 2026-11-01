<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/db.php';
$email = filter_var(env('ADMIN_EMAIL') ?: '', FILTER_VALIDATE_EMAIL);
$password = env('ADMIN_PASSWORD') ?: '';
$name = trim(env('ADMIN_NAME') ?: 'Site Administrator');
if (!$email || strlen($password) < 12) {
    fwrite(STDERR, "Set ADMIN_EMAIL to a valid email and ADMIN_PASSWORD to at least 12 characters.\n");
    exit(1);
}
$statement = db()->prepare('INSERT INTO users (name,email,password_hash,role,is_active) VALUES (:name,:email,:hash,\'admin\',1)');
try {
    $statement->execute(['name' => $name, 'email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        fwrite(STDERR, "An account with that email already exists. No account was changed.\n");
        exit(1);
    }
    throw $exception;
}
fwrite(STDOUT, "Administrator account created: {$email}\n");
