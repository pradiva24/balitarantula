<?php
declare(strict_types=1);

// Public, lightweight server clock for same-day booking slot filtering.
// Always use Bali time, independent of the visitor's device timezone.
date_default_timezone_set('Asia/Makassar');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Makassar'));

echo json_encode([
    'date' => $now->format('Y-m-d'),
    'minutes' => ((int)$now->format('H') * 60) + (int)$now->format('i'),
    'time' => $now->format('H:i:s'),
    'timezone' => 'Asia/Makassar'
], JSON_UNESCAPED_SLASHES);
