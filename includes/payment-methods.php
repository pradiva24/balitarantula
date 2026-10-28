<?php
/**
 * Central payment-method visibility/availability helpers.
 *
 * The existing comma-separated payment_methods setting is kept as a
 * backwards-compatible fallback. New explicit on/off settings override it.
 */
function payment_toggle_enabled(string $key, string $legacyLabel, bool $default=false): bool {
    $raw = setting($key, null);
    if ($raw !== null && $raw !== '') {
        return (string)$raw === '1';
    }
    $legacy = array_values(array_filter(array_map('trim', explode(',', (string)setting('payment_methods','')))));
    if ($legacy) {
        foreach ($legacy as $name) {
            if (strcasecmp($name, $legacyLabel) === 0) return true;
        }
        return false;
    }
    return $default;
}

function payment_method_toggles(): array {
    return [
        'pay_at_venue' => [
            'setting' => 'payment_method_pay_at_venue',
            'label' => 'Pay at Venue',
            'legacy' => 'Cash / Pay at Venue',
            'default' => true,
        ],
        'bank_transfer' => [
            'setting' => 'payment_method_bank_transfer',
            'label' => 'Bank Transfer',
            'legacy' => 'Bank Transfer',
            'default' => true,
        ],
        'midtrans' => [
            'setting' => 'payment_method_midtrans',
            'label' => 'Midtrans',
            'legacy' => 'Midtrans',
            'default' => false,
        ],
        'doku' => [
            'setting' => 'payment_method_doku',
            'label' => 'DOKU',
            'legacy' => 'DOKU',
            'default' => false,
        ],
        'xendit' => [
            'setting' => 'payment_method_xendit',
            'label' => 'Xendit',
            'legacy' => 'Xendit',
            'default' => false,
        ],
    ];
}

function payment_method_is_enabled(string $key): bool {
    $map = payment_method_toggles();
    if (!isset($map[$key])) return false;
    $m = $map[$key];
    return payment_toggle_enabled($m['setting'], $m['legacy'], (bool)$m['default']);
}

/**
 * Returns methods that are both switched on and implemented/configured.
 * DOKU/Xendit switches can be turned on in Admin, but they are intentionally
 * not exposed to customers until their actual checkout integration exists.
 */
function active_payment_methods(): array {
    $methods = [];
    if (payment_method_is_enabled('bank_transfer')) $methods[] = 'Bank Transfer';
    if (payment_method_is_enabled('pay_at_venue')) $methods[] = 'Pay at Venue';

    // Midtrans is customer-visible only when its existing integration is ready.
    if (payment_method_is_enabled('midtrans')
        && setting('payment_gateway','manual') === 'midtrans'
        && trim((string)setting('gateway_server_key','')) !== ''
        && trim((string)setting('gateway_client_key','')) !== '') {
        $methods[] = 'Midtrans';
    }

    // DOKU/Xendit are reserved for their real gateway integrations.
    if (payment_method_is_enabled('doku') && file_exists(__DIR__.'/../config/doku.php')) {
        $methods[] = 'DOKU';
    }
    if (payment_method_is_enabled('xendit') && file_exists(__DIR__.'/../config/xendit.php')) {
        $methods[] = 'Xendit';
    }
    return $methods;
}
