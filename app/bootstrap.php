<?php
declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    exit('Application is not configured. Copy config/config.example.php to config/config.php and set database credentials.');
}
$GLOBALS['app_config'] = require $configPath;
date_default_timezone_set($GLOBALS['app_config']['app']['timezone'] ?? 'Asia/Makassar');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name($GLOBALS['app_config']['app']['session_name'] ?? 'bta_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (bool)($GLOBALS['app_config']['security']['session_secure'] ?? $isHttps),
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Auth.php';
