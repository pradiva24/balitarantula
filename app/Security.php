<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf_or_fail(): void
{
    $sent = $_POST['_csrf'] ?? '';
    $known = $_SESSION['_csrf_token'] ?? '';
    if (!is_string($sent) || !is_string($known) || $known === '' || !hash_equals($known, $sent)) {
        http_response_code(419);
        exit('Your session has expired. Please go back, refresh the page, and try again.');
    }
}

function redirect(string $path): never
{
    $base = rtrim((string)($GLOBALS['app_config']['app']['base_url'] ?? ''), '/');
    if (preg_match('/^https?:\/\//i', $path)) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . $base . '/' . ltrim($path, '/'));
    }
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return is_string($value) ? $value : null;
}
