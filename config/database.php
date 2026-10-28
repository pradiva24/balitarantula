<?php
// MAMP default MySQL settings.
// Change these if your MAMP configuration is different.
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'gapurabo_balitarantula');
define('DB_USER', 'gapurabo_balitarantula');
define('DB_PASS', '#~o(HX4nV^jg@Ys]');

define('BASE_URL', 'https://tarantula.baliatvgo.com/');

date_default_timezone_set('Asia/Makassar');

if (DB_NAME === '' || DB_USER === '') {
    error_log('BTA database configuration is missing. Set BTA_DB_NAME and BTA_DB_USER.');
}

date_default_timezone_set('Asia/Makassar');

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name('BTA_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (parse_url(BASE_URL, PHP_URL_SCHEME) === 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
