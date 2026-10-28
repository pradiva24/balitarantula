<?php
/**
 * Xendit gateway configuration helper.
 * Credentials are stored in the settings table and must never be exposed client-side.
 */
function xendit_config(): array {
    return [
        'mode' => (string)setting('xendit_mode','sandbox'),
        'secret_key' => trim((string)setting('xendit_secret_key','')),
        'webhook_token' => trim((string)setting('xendit_webhook_token','')),
        'success_url' => trim((string)setting('xendit_success_url',rtrim(setting('site_url',BASE_URL),'/').'/booking-success')),
        'failure_url' => trim((string)setting('xendit_failure_url',rtrim(setting('site_url',BASE_URL),'/').'/booking')),
        'api_base' => 'https://api.xendit.co',
        'sessions_endpoint' => 'https://api.xendit.co/sessions',
    ];
}

function xendit_is_configured(): bool {
    $c=xendit_config();
    return $c['secret_key'] !== '';
}
