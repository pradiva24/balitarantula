<?php
declare(strict_types=1);

/**
 * Run from the project root:
 * php bin/create-admin.php "Administrator Name" admin@example.com
 * Password is read interactively where supported, or from BTA_ADMIN_PASSWORD.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/app/bootstrap.php';

$name = trim($argv[1] ?? '');
$email = filter_var($argv[2] ?? '', FILTER_VALIDATE_EMAIL);
$password = getenv('BTA_ADMIN_PASSWORD') ?: '';

if ($name === '' || !$email) {
    fwrite(STDERR, "Usage: php bin/create-admin.php \"Admin Name\" admin@example.com\n");
    exit(2);
}
if ($password === '' && function_exists('readline')) {
    $password = (string)readline('Temporary password (input may be visible): ');
}
if (strlen($password) < 12) {
    fwrite(STDERR, "Set BTA_ADMIN_PASSWORD or enter a password of at least 12 characters.\n");
    exit(2);
}

$pdo = Database::connection();
$stmt = $pdo->prepare('INSERT INTO admins (name, email, password_hash, role, status) VALUES (:name, :email, :hash, \'owner\', \'active\')');
$stmt->execute([
    'name' => $name,
    'email' => mb_strtolower(trim((string)$email)),
    'hash' => password_hash($password, PASSWORD_DEFAULT),
]);
fwrite(STDOUT, "Admin created. Sign in at /admin/login.php and remove BTA_ADMIN_PASSWORD from the environment.\n");
