<?php
declare(strict_types=1);

if (!function_exists('e')) {
    function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

/** Read public site settings from the database, with safe fallbacks for fresh installs. */
function bta_setting(string $key, string $fallback = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            if (class_exists('Database')) {
                $rows = Database::connection()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) $cache[(string)$row['setting_key']] = (string)($row['setting_value'] ?? '');
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    $value = $cache[$key] ?? '';
    return $value !== '' ? $value : $fallback;
}

function bta_setting_public_url(string $key, string $fallback = ''): string
{
    $value = trim(bta_setting($key, $fallback));
    if ($value === '') return '';
    $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
    return filter_var($value, FILTER_VALIDATE_URL) && in_array($scheme, ['https','http'], true) ? $value : $fallback;
}

function bta_setting_secret_key(): string
{
    $configured = (string)($GLOBALS['app_config']['security']['csrf_key'] ?? '');
    if (strlen($configured) < 32 || str_contains($configured, 'REPLACE_WITH')) {
        throw new RuntimeException('Set a strong security.csrf_key in config/config.php before saving SMTP credentials.');
    }
    return hash('sha256', $configured . '|bta-site-settings-v1', true);
}

function bta_encrypt_setting_secret(string $plain): string
{
    if ($plain === '') return '';
    $key = bta_setting_secret_key();
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) throw new RuntimeException('Unable to encrypt SMTP secret.');
    return 'enc:v1:' . base64_encode($iv . $tag . $cipher);
}

function bta_decrypt_setting_secret(string $stored): string
{
    if (!str_starts_with($stored, 'enc:v1:')) return '';
    $raw = base64_decode(substr($stored, 7), true);
    if ($raw === false || strlen($raw) < 29) return '';
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', bta_setting_secret_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return is_string($plain) ? $plain : '';
}
