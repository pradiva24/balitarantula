<?php
declare(strict_types=1);

/**
 * Copy to config/config.php on the server and fill in real values.
 * Keep config/config.php out of Git and outside the public document root when possible.
 */
return [
    'app' => [
        'name' => 'Bali Tarantula Adventure',
        'env' => 'production',
        'debug' => false,
        'base_url' => 'https://tarantula.baliatvgo.com',
        'timezone' => 'Asia/Makassar',
        'session_name' => 'bta_admin',
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'CHANGE_ME',
        'username' => 'CHANGE_ME',
        'password' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'csrf_key' => 'REPLACE_WITH_A_LONG_RANDOM_SECRET',
        'session_secure' => true,
    ],
    'mail' => [
        'from_email' => 'no-reply@example.com',
        'from_name' => 'Bali Tarantula Adventure',
    ],
    'payments' => [
        'gateway_enabled' => false,
        'gateway_name' => '',
        'gateway_secret' => '',
        'webhook_secret' => '',
    ],
];
