<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = $GLOBALS['app_config']['database'] ?? [];
        foreach (['host', 'name', 'username', 'password'] as $key) {
            if (!array_key_exists($key, $config) || ($config[$key] === '' && $key !== 'password')) {
                throw new RuntimeException('Database configuration is incomplete. Check config/config.php.');
            }
        }

        $charset = $config['charset'] ?? 'utf8mb4';
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            (int)($config['port'] ?? 3306),
            $config['name'],
            $charset
        );

        self::$connection = new PDO($dsn, $config['username'], (string)$config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);

        return self::$connection;
    }
}
