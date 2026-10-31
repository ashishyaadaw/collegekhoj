<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/db.php';

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    return trim((string) fgets(STDIN));
}

$pdo = db();
$adminCount = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = \'admin\'')->fetchColumn();
if ($adminCount > 0) {
    fwrite(STDERR, "An administrator account already exists. No account was created.\n");
    exit(1);
}

$name = prompt('Administrator name: ');
$email = filter_var(prompt('Administrator email: '), FILTER_VALIDATE_EMAIL);
if ($name === '' || mb_strlen($name) > 120 || !$email) {
    fwrite(STDERR, "Enter a name (up to 120 characters) and a valid email address.\n");
    exit(1);
}

fwrite(STDOUT, "Administrator password (input is visible): ");
$password = trim((string) fgets(STDIN));
fwrite(STDOUT, "Confirm password (input is visible): ");
$confirmation = trim((string) fgets(STDIN));
if (strlen($password) < 12 || strlen($password) > 200) {
    fwrite(STDERR, "Password must be between 12 and 200 characters.\n");
    exit(1);
}
if (!hash_equals($password, $confirmation)) {
    fwrite(STDERR, "Passwords do not match. No account was created.\n");
    exit(1);
}

try {
    $statement = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, is_active)
         VALUES (:name, :email, :password_hash, \'admin\', 1)'
    );
    $statement->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        fwrite(STDERR, "That email is already in use. No account was created.\n");
        exit(1);
    }
    throw $exception;
}

fwrite(STDOUT, "Admin account created for {$email}.\n");
