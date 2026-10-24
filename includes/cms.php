<?php
declare(strict_types=1);

/**
 * Public-safe content lookup. If the CMS migration has not been installed yet,
 * the public website continues to render its built-in fallback content.
 */
function bta_content(string $key, string $fallback = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            if (class_exists('Database')) {
                $rows = Database::connection()->query('SELECT content_key, content_value FROM cms_content')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $cache[(string)$row['content_key']] = (string)($row['content_value'] ?? '');
                }
            }
        } catch (Throwable $e) {
            // CMS is optional until its migration is imported.
            $cache = [];
        }
    }
    $value = $cache[$key] ?? $fallback;
    return $value !== '' ? $value : $fallback;
}

function bta_content_image(string $key, string $fallback): string
{
    $value = bta_content($key, $fallback);
    // Prevent stored markup or unsafe schemes from being used as an image URL.
    if (!preg_match('~^(https://|/|assets/)~i', $value) || str_contains($value, '..')) {
        return $fallback;
    }
    return $value;
}
