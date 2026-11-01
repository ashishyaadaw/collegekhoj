<?php
declare(strict_types=1);

function load_env(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || env($key) !== false) {
            continue;
        }

        $length = strlen($value);
        if ($length >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[$length - 1] === $value[0]) {
            $value = substr($value, 1, -1);
        }

        // Stored in $_ENV rather than relying on putenv(), which some hosts disable.
        $_ENV[$key] = $value;
    }
}

/** Server environment first, then values loaded from .env; false when unset (like getenv()). */
function env(string $key): string|false
{
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    return isset($_ENV[$key]) && is_string($_ENV[$key]) ? $_ENV[$key] : false;
}

load_env(__DIR__ . '/.env');

function db(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = env('DB_HOST') ?: '127.0.0.1';
    $name = env('DB_NAME') ?: 'collegekhoj';
    $user = env('DB_USER') ?: 'root';
    $password = env('DB_PASSWORD') ?: '';
    $charset = 'utf8mb4';

    $connection = new PDO(
        "mysql:host={$host};dbname={$name};charset={$charset}",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $connection;
}
