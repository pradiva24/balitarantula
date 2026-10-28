<?php
/**
 * Midtrans HTTP notification endpoint.
 *
 * Success rule:
 *   status_code       = 200
 *   transaction_status = settlement OR capture
 *   fraud_status       = accept (when present)
 *
 * When successful:
 *   payment_status = paid
 *   booking_status = confirmed
 *
 * Signature:
 *   SHA512(order_id + status_code + gross_amount + ServerKey)
 */
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

function bta_midtrans_reply(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function bta_midtrans_log(string $message): void
{
    error_log('[BTA MIDTRANS] ' . $message);
}

$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    bta_midtrans_log('Empty notification body.');
    bta_midtrans_reply(400, ['ok' => false, 'error' => 'Empty notification body.']);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    bta_midtrans_log('Invalid JSON notification.');
    bta_midtrans_reply(400, ['ok' => false, 'error' => 'Invalid JSON.']);
}

$orderId      = trim((string)($data['order_id'] ?? ''));
$statusCode   = trim((string)($data['status_code'] ?? ''));
$grossAmount  = trim((string)($data['gross_amount'] ?? ''));
$transaction  = strtolower(trim((string)($data['transaction_status'] ?? '')));
$fraudStatus  = strtolower(trim((string)($data['fraud_status'] ?? '')));
$signatureKey = trim((string)($data['signature_key'] ?? ''));
$serverKey    = trim((string)setting('gateway_server_key', ''));

if ($orderId === '' || $statusCode === '' || $grossAmount === '' || $signatureKey === '') {
    bta_midtrans_log('Missing required fields for order ' . ($orderId ?: '[empty]'));
    bta_midtrans_reply(400, ['ok' => false, 'error' => 'Missing required notification fields.']);
}

if ($serverKey === '') {
    bta_midtrans_log('Gateway Server Key is not configured.');
    bta_midtrans_reply(500, ['ok' => false, 'error' => 'Gateway Server Key is not configured.']);
}

$expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
if (!hash_equals($expectedSignature, $signatureKey)) {
    bta_midtrans_log('Invalid signature for order ' . $orderId);
    bta_midtrans_reply(403, ['ok' => false, 'error' => 'Invalid signature.']);
}

// Midtrans recommends checking status_code=200, fraud_status=accept (when present),
// and transaction_status=capture/settlement for successful transactions.
$isSuccessful = $statusCode === '200'
    && in_array($transaction, ['capture', 'settlement'], true)
    && ($fraudStatus === '' || $fraudStatus === 'accept');

try {
    $db = db();
    $stmt = $db->prepare(
        "SELECT id, booking_code, total, payment_status, booking_status
         FROM bookings
         WHERE booking_code = ?
         LIMIT 1"
    );
    $stmt->execute([$orderId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        bta_midtrans_log('Booking not found for order ' . $orderId);
        bta_midtrans_reply(404, ['ok' => false, 'error' => 'Booking not found.']);
    }

    // Protect against a notification for a different amount.
    $bookingTotal = (float)($booking['total'] ?? 0);
    $notificationTotal = (float)$grossAmount;
    if (abs($bookingTotal - $notificationTotal) > 0.01) {
        bta_midtrans_log(
            'Gross amount mismatch for ' . $orderId .
            ' (booking=' . number_format($bookingTotal, 2, '.', '') .
            ', notification=' . number_format($notificationTotal, 2, '.', '') . ')'
        );
        bta_midtrans_reply(422, ['ok' => false, 'error' => 'Gross amount mismatch.']);
    }

    $db->beginTransaction();

    if ($isSuccessful) {
        // Idempotent: repeated settlement/capture notifications remain harmless.
        // Do not overwrite a cancelled booking automatically.
        $newBookingStatus = ((string)$booking['booking_status'] === 'cancelled')
            ? 'cancelled'
            : 'confirmed';

        $update = $db->prepare(
            "UPDATE bookings
             SET payment_status = 'paid',
                 booking_status = ?,
                 paid_at = COALESCE(paid_at, NOW()),
                 updated_at = NOW()
             WHERE id = ?"
        );
        $update->execute([$newBookingStatus, (int)$booking['id']]);

        $db->commit();

        bta_midtrans_log('Payment confirmed for ' . $orderId . ' (' . $transaction . ').');
        bta_midtrans_reply(200, [
            'ok' => true,
            'order_id' => $orderId,
            'transaction_status' => $transaction,
            'payment_status' => 'paid',
            'booking_status' => $newBookingStatus,
        ]);
    }

    if (in_array($transaction, ['cancel', 'deny', 'expire'], true)) {
        $update = $db->prepare(
            "UPDATE bookings
             SET payment_status = 'failed', updated_at = NOW()
             WHERE id = ?"
        );
        $update->execute([(int)$booking['id']]);
        $db->commit();

        bta_midtrans_log('Payment failed for ' . $orderId . ' (' . $transaction . ').');
        bta_midtrans_reply(200, [
            'ok' => true,
            'order_id' => $orderId,
            'transaction_status' => $transaction,
            'payment_status' => 'failed',
        ]);
    }

    if ($transaction === 'refund' || $transaction === 'partial_refund') {
        $update = $db->prepare(
            "UPDATE bookings
             SET payment_status = 'refunded', updated_at = NOW()
             WHERE id = ?"
        );
        $update->execute([(int)$booking['id']]);
        $db->commit();

        bta_midtrans_log('Payment refunded for ' . $orderId . ' (' . $transaction . ').');
        bta_midtrans_reply(200, [
            'ok' => true,
            'order_id' => $orderId,
            'transaction_status' => $transaction,
            'payment_status' => 'refunded',
        ]);
    }

    // Pending/other statuses do not mark the booking as paid.
    $db->commit();
    bta_midtrans_log('Non-final status received for ' . $orderId . ': ' . ($transaction ?: '[empty]'));
    bta_midtrans_reply(200, [
        'ok' => true,
        'order_id' => $orderId,
        'transaction_status' => $transaction,
        'payment_status' => (string)($booking['payment_status'] ?? 'pending'),
    ]);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    bta_midtrans_log('Exception for ' . ($orderId ?: '[empty]') . ': ' . $e->getMessage());
    bta_midtrans_reply(500, ['ok' => false, 'error' => 'Notification processing failed.']);
}
