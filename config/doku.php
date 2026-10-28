<?php
/**
 * DOKU Checkout gateway configuration helper.
 * Credentials are stored in the settings table and must never be exposed client-side.
 */
function doku_config(): array {
    $mode=(string)setting('doku_mode','sandbox');
    return [
        'mode' => $mode,
        'client_id' => trim((string)setting('doku_client_id','')),
        'secret_key' => trim((string)setting('doku_secret_key','')),
        'callback_url' => trim((string)setting('doku_callback_url',rtrim(setting('site_url',BASE_URL),'/').'/booking-success')),
        'notification_url' => trim((string)setting('doku_notification_url',rtrim(setting('site_url',BASE_URL),'/').'/doku-notification.php')),
        'api_base' => $mode === 'live' ? 'https://api.doku.com' : 'https://api-sandbox.doku.com',
        'checkout_endpoint' => ($mode === 'live' ? 'https://api.doku.com' : 'https://api-sandbox.doku.com').'/checkout/v1/payment',
    ];
}

function doku_api_endpoint(): string {
    return doku_config()['checkout_endpoint'];
}

function doku_is_configured(): bool {
    $c=doku_config();
    return $c['client_id'] !== '' && $c['secret_key'] !== '';
}
