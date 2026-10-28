<?php
// Midtrans configuration and Snap token helper.
// This file intentionally keeps the existing settings keys used by Admin > Payment Gateway.
require_once __DIR__ . '/../includes/payment-methods.php';

function midtrans_config(): array {
    return [
        'enabled' => payment_method_is_enabled('midtrans')
            && setting('payment_gateway', 'manual') === 'midtrans',
        'production' => strtolower((string)setting('gateway_environment', 'sandbox')) === 'production',
        'server_key' => trim((string)setting('gateway_server_key', '')),
        'client_key' => trim((string)setting('gateway_client_key', '')),
    ];
}

function midtrans_base_url(): string {
    return midtrans_config()['production']
        ? 'https://app.midtrans.com'
        : 'https://app.sandbox.midtrans.com';
}

function midtrans_snap_api_url(): string {
    return midtrans_base_url() . '/snap/v1/transactions';
}

function midtrans_notification_url(): string {
    return rtrim((string)setting('site_url', BASE_URL), '/') . '/midtrans-notification.php';
}

function midtrans_log(string $message): void {
    error_log('[BTA MIDTRANS] ' . $message);
}

function midtrans_snap_token(array $booking): string {
    $c = midtrans_config();

    if (!$c['enabled']) {
        throw new RuntimeException('Midtrans payment method is not enabled.');
    }
    if ($c['server_key'] === '') {
        throw new RuntimeException('Midtrans Server Key is not configured.');
    }
    if ($c['client_key'] === '') {
        throw new RuntimeException('Midtrans Client Key is not configured.');
    }

    $orderId = trim((string)($booking['booking_code'] ?? ''));
    if ($orderId === '') {
        throw new RuntimeException('Booking code is missing.');
    }

    $total = (int)round((float)($booking['total'] ?? 0));
    if ($total < 1) {
        throw new RuntimeException('Invalid booking total.');
    }

    $packageName = trim((string)($booking['package_name'] ?? site_brand_name()));
    if ($packageName === '') $packageName = site_brand_name();

    $payload = [
        'transaction_details' => [
            'order_id' => $orderId,
            'gross_amount' => $total,
        ],
        'item_details' => [[
            'id' => 'BTA-' . (int)($booking['id'] ?? 0),
            'price' => $total,
            'quantity' => 1,
            'name' => substr($packageName, 0, 50),
        ]],
        'customer_details' => [
            'first_name' => substr(trim((string)($booking['customer_name'] ?? 'Customer')) ?: 'Customer', 0, 50),
            'email' => trim((string)($booking['customer_email'] ?? '')),
            'phone' => trim((string)($booking['customer_whatsapp'] ?? '')),
        ],
    ];

    if ($payload['customer_details']['email'] !== '' && !filter_var($payload['customer_details']['email'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Customer email is invalid.');
    }

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Unable to prepare Midtrans request.');
    }

    $ch = curl_init(midtrans_snap_api_url());
    if ($ch === false) {
        throw new RuntimeException('Unable to initialize payment connection.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERPWD => $c['server_key'] . ':',
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Override-Notification: ' . midtrans_notification_url(),
        ],
        CURLOPT_POSTFIELDS => $json,
    ]);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $curlError !== '') {
        midtrans_log('Connection error for ' . $orderId . ': ' . ($curlError !== '' ? $curlError : 'unknown cURL error'));
        throw new RuntimeException('Payment service connection failed.');
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        midtrans_log('Invalid JSON response for ' . $orderId . ' (HTTP ' . $httpCode . ').');
        throw new RuntimeException('Invalid response from Midtrans.');
    }

    if ($httpCode < 200 || $httpCode >= 300 || empty($data['token'])) {
        $message = trim((string)($data['error_messages'][0] ?? $data['status_message'] ?? $data['error'] ?? ''));
        if ($message === '') $message = 'Midtrans rejected the payment request.';
        midtrans_log('API rejection for ' . $orderId . ' (HTTP ' . $httpCode . '): ' . $message);
        throw new RuntimeException('Midtrans rejected the payment request.');
    }

    midtrans_log('Snap token created for ' . $orderId . ' (HTTP ' . $httpCode . ').');
    return (string)$data['token'];
}
