<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/smtp.php';
require_once __DIR__ . '/includes/booking-email.php';
require_once __DIR__ . '/includes/eticket-pdf.php';
require_once __DIR__ . '/includes/coupon.php';
require_once __DIR__ . '/includes/payment-methods.php';
require_once __DIR__ . '/includes/eticket-config.php';
require_once __DIR__ . '/config/midtrans.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$db = db();

try {
    $bookingModeCol=$db->query("SHOW COLUMNS FROM packages LIKE 'booking_mode'")->fetch(PDO::FETCH_ASSOC);
    if(!$bookingModeCol) $db->exec("ALTER TABLE packages ADD COLUMN booking_mode VARCHAR(20) NOT NULL DEFAULT 'person' AFTER price_child");
} catch (Throwable $e) {}

$ticketOnlyEnabled = setting('booking_ticket_only_enabled', '1') === '1';
$hotelPickupEnabled = setting('booking_hotel_pickup_enabled', '1') === '1';
$packageId = (int)($_GET['package'] ?? $_POST['package_id'] ?? 0);

try {
    $db->exec("CREATE TABLE IF NOT EXISTS package_activity_times (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        package_id INT UNSIGNED NOT NULL,
        activity_time TIME NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        PRIMARY KEY(id),
        UNIQUE KEY uq_package_activity_time(package_id, activity_time),
        KEY idx_package_activity_times(package_id),
        CONSTRAINT fk_package_activity_times_package FOREIGN KEY(package_id) REFERENCES packages(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {}

try {
    $cols = $db->query("SHOW COLUMNS FROM bookings LIKE 'activity_time'")->fetch();
    if (!$cols) $db->exec("ALTER TABLE bookings ADD COLUMN activity_time TIME NULL AFTER booking_date");
} catch (Throwable $e) {}

$activityTimeRows = [];
$activityTimeMap = [];
try {
    $activityTimeRows = $db->query("SELECT package_id, TIME_FORMAT(activity_time, '%H:%i') AS activity_time FROM package_activity_times ORDER BY package_id, sort_order, activity_time")->fetchAll();
    foreach ($activityTimeRows as $atr) {
        $activityTimeMap[(int)$atr['package_id']][] = $atr['activity_time'];
    }
} catch (Throwable $e) {}

$packages = $db->query("SELECT id, name, price_adult, price_child, booking_mode, cover_image, short_description FROM packages WHERE status = 1 AND booking_enabled = 1 ORDER BY homepage_order ASC, name ASC")->fetchAll();

// Default booking artwork: use an ATV photo already stored in the Media Library
// through an active ATV package cover, then its package gallery as fallback.
$bookingDefaultImage = '';
try {
    $atvImageStmt = $db->query("
        SELECT p.cover_image
        FROM packages p
        WHERE p.status = 1
          AND p.cover_image IS NOT NULL AND TRIM(p.cover_image) <> ''
          AND (p.name LIKE '%ATV%' OR p.short_description LIKE '%ATV%')
        ORDER BY p.homepage_order ASC, p.id ASC
        LIMIT 1
    ");
    $bookingDefaultImage = trim((string)$atvImageStmt->fetchColumn());

    if ($bookingDefaultImage === '') {
        $atvGalleryStmt = $db->query("
            SELECT pi.image_path
            FROM package_images pi
            INNER JOIN packages p ON p.id = pi.package_id
            WHERE p.status = 1
              AND (p.name LIKE '%ATV%' OR p.short_description LIKE '%ATV%')
              AND pi.image_path IS NOT NULL AND TRIM(pi.image_path) <> ''
            ORDER BY p.homepage_order ASC, pi.sort_order ASC, pi.id ASC
            LIMIT 1
        ");
        $bookingDefaultImage = trim((string)$atvGalleryStmt->fetchColumn());
    }
} catch (Throwable $imageError) {
    error_log('BTA booking default ATV image lookup: '.$imageError->getMessage());
}
$areas = $db->query("SELECT id, name FROM pickup_areas WHERE status = 1 ORDER BY sort_order, name")->fetchAll();

$pickupTimeRows = $db->query("SELECT pickup_area_id, pickup_time FROM pickup_time_slots WHERE status = 1 ORDER BY pickup_area_id, sort_order, pickup_time")->fetchAll();
$pickupTimeMap = [];
foreach ($pickupTimeRows as $r) {
    $pickupTimeMap[(int)$r['pickup_area_id']][] = substr((string)$r['pickup_time'], 0, 5);
}

$pickupPriceRows = $db->query("SELECT package_id, pickup_area_id, price FROM package_pickup_prices")->fetchAll();
$pickupPriceMap = [];
foreach ($pickupPriceRows as $r) {
    $pickupPriceMap[(int)$r['package_id'] . '_' . (int)$r['pickup_area_id']] = (float)$r['price'];
}

$countries = require __DIR__ . '/config/countries.php';
$message = '';
$error = '';
$createdCode = '';
$createdToken = '';
$createdTotal = 0;
$paymentMethods = active_payment_methods();
$paymentMethod = $_POST['payment_method'] ?? ($paymentMethods[0] ?? '');
if (!$paymentMethods || !in_array($paymentMethod, $paymentMethods, true)) {
    $paymentMethod = $paymentMethods[0] ?? '';
}
$promoCode = trim($_POST['promo_code'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($paymentMethod === '' || !in_array($paymentMethod, $paymentMethods, true)) {
        $error = 'Please choose an available payment method.';
    }
    $packageId = (int)($_POST['package_id'] ?? 0);
    $name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['customer_email'] ?? '');
    $country = trim($_POST['customer_country'] ?? '');
    $waCode = trim($_POST['whatsapp_country_code'] ?? '+62');
    $waInput = trim($_POST['customer_whatsapp'] ?? '');
    $date = $_POST['booking_date'] ?? '';
    $activityTime = trim((string)($_POST['activity_time'] ?? ''));
    $adults = max(1, (int)($_POST['adults'] ?? 1));
    $children = max(0, (int)($_POST['children'] ?? 0));
    $requestedActivityUnits = max(1, min(50, (int)($_POST['activity_units'] ?? 1)));
    $pickup = ((int)($_POST['pickup_required'] ?? 0) === 1) ? 1 : 0;

    if ($pickup && !$hotelPickupEnabled) {
        $error = 'Hotel pickup is currently unavailable. Please choose Ticket Only.';
        $pickup = 0;
    } elseif (!$pickup && !$ticketOnlyEnabled && $hotelPickupEnabled) {
        $error = 'Ticket Only is currently unavailable. Please choose Hotel Pickup.';
    }

    if ($pickup) {
        $activityTime = '';
    }
    $areaId = $pickup ? (int)($_POST['pickup_area_id'] ?? 0) : null;
    $hotel = $pickup ? trim($_POST['hotel_name'] ?? '') : null;
    $room = $pickup ? trim($_POST['room_number'] ?? '') : null;
    $pickupTime = $pickup ? trim((string)($_POST['pickup_time'] ?? '')) : null;
    $special = trim($_POST['special_request'] ?? '');

    $waDigits = preg_replace('/\D+/', '', $waInput);
    $selectedCode = preg_replace('/\D+/', '', $waCode);
    $wa = $waDigits !== '' ? ($selectedCode !== '' ? (strpos($waDigits, $selectedCode) === 0 ? $waDigits : $selectedCode . ltrim($waDigits, '0')) : $waDigits) : '';

    $st = $db->prepare("SELECT * FROM packages WHERE id = ? AND status = 1 AND booking_enabled = 1");
    $st->execute([$packageId]);
    $pkg = $st->fetch();
    $today = date('Y-m-d');
    $bookingMode = $pkg['booking_mode'] ?? 'person';
    $isTandem = $pkg && $bookingMode === 'tandem';
    $isVehicle = $pkg && $bookingMode === 'vehicle';
    if ($isTandem || $isVehicle) {
        $adults = $requestedActivityUnits * ($isTandem ? 2 : 4);
        $children = 0;
    }

    if (!$pkg || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $country === '' || !array_key_exists($country, $countries) || !$date || $date < $today) {
        $error = 'Please complete all required fields and select a valid future booking date.';
    } elseif (!$pickup && !empty($activityTimeMap[$packageId]) && !in_array($activityTime, $activityTimeMap[$packageId], true)) {
        $error = 'Please select a valid activity start time.';
    } elseif ($pickup && ($adults + $children) < 2) {
        $error = 'Hotel pickup requires a minimum of 2 guests. Please select at least 2 people or choose Ticket Only.';
    ;
    } elseif ($pickup && !$areaId) {
        $error = 'Please select your hotel pickup area.';
    } elseif ($pickup && ($pickupTime === '' || !in_array($pickupTime, $pickupTimeMap[$areaId] ?? [], true))) {
        $error = 'Please select a valid pickup time for your location.';
    } else {
        $guests = $adults + $children;
        $activityCapacity = $isTandem ? 2 : ($isVehicle ? 4 : 0);
        $activityUnits = ($isTandem || $isVehicle) ? $requestedActivityUnits : 0;
        if ($activityCapacity > 0 && $guests > ($activityUnits * $activityCapacity)) {
            $error = 'Selected units are not enough for the number of guests. Please add more units.';
        }
        $pickupPrice = 0;
        $pickupUnitPrice = 0;
        $transportGuests = 0;
        $areaName = null;

        if ($pickup) {
            $st = $db->prepare("SELECT a.name, pp.price FROM pickup_areas a LEFT JOIN package_pickup_prices pp ON pp.pickup_area_id = a.id AND pp.package_id = ? WHERE a.id = ? AND a.status = 1");
            $st->execute([$packageId, $areaId]);
            $ar = $st->fetch();
            if (!$ar) {
                $error = 'Selected pickup area is not available for this package.';
            } else if ($ar['price'] === null) {
                $error = 'Transport price is not configured for this area.';
            } else {
                $pickupUnitPrice = (float)$ar['price'];
                $transportGuests = (int)ceil($guests / 4); // One private transport per maximum of 4 guests.
                $pickupPrice = $pickupUnitPrice * $transportGuests;
                $areaName = $ar['name'];
            }
        }

        if ($error === '') {
            $activityUnitPrice = (float)$pkg['price_adult'];
            $activityPrice = ($isTandem || $isVehicle)
                ? ($activityUnits * $activityUnitPrice)
                : (($adults * $activityUnitPrice) + ($children * (float)$pkg['price_child']));
            $subtotal = $activityPrice + $pickupPrice;
            $coupon = coupon_validate($promoCode, $subtotal, $packageId, $date);
            if ($promoCode !== '' && !$coupon['ok']) {
                $error = $coupon['message'];
            }

            if ($error === '') {
                $discount = $coupon['ok'] ? (float)$coupon['discount'] : 0;
                $discountCode = $coupon['ok'] ? $coupon['code'] : null;
                $total = max(0, $subtotal - $discount);
                $ticketPrefix = trim((string)eticket_text('eticket_ticket_code_prefix', 'BTA'));
                $ticketPrefix = preg_replace('/[^A-Za-z0-9]+/', '-', $ticketPrefix) ?: 'BTA';
                $code = strtoupper($ticketPrefix) . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

                try {
                    $db->beginTransaction();
                    $lock = $db->prepare('SELECT max_guests FROM packages WHERE id = ? AND status = 1 FOR UPDATE');
                    $lock->execute([$packageId]);
                    $lockedPkg = $lock->fetch();
                    if (!$lockedPkg) throw new Exception('Package unavailable.');

                    $blk = $db->prepare('SELECT reason FROM package_blackouts WHERE package_id = ? AND blackout_date = ? LIMIT 1');
                    $blk->execute([$packageId, $date]);
                    $blackout = $blk->fetchColumn();
                    if ($blackout !== false) throw new Exception('This package is unavailable on the selected date.');

                    $cap = (int)($lockedPkg['max_guests'] ?? 0);
                    $usedSt = $db->prepare("SELECT COALESCE(SUM(adults + children), 0) FROM bookings WHERE package_id = ? AND booking_date = ? AND booking_status <> 'cancelled'");
                    $usedSt->execute([$packageId, $date]);
                    $used = (int)$usedSt->fetchColumn();
                    if ($cap > 0 && ($used + $guests) > $cap) throw new Exception('This date is full.');

                    $st = $db->prepare("INSERT INTO bookings (booking_code, access_token, package_id, customer_name, customer_email, customer_country, customer_whatsapp, booking_date, activity_time, adults, children, pickup_required, pickup_area_id, hotel_name, room_number, pickup_time, special_request, activity_price, pickup_price, subtotal, total, payment_method, discount_code, discount_amount) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    $accessToken = bin2hex(random_bytes(32));
                    $st->execute([$code, $accessToken, $packageId, $name, $email, $country, $wa, $date, ($activityTime !== '' ? $activityTime : null), $adults, $children, $pickup, $areaId, $hotel, $room, $pickupTime, $special, $activityPrice, $pickupPrice, $subtotal, $total, $paymentMethod, $discountCode, $discount]);
                    $bookingId = (int)$db->lastInsertId();

                    if ($discount > 0 && $coupon['ok']) {
                        $u = $db->prepare("UPDATE coupons SET used_count = used_count + 1, updated_at = NOW() WHERE id = ? AND status = 1 AND (usage_limit IS NULL OR used_count < usage_limit)");
                        $u->execute([(int)$coupon['coupon']['id']]);
                    }

                    $invoicePrefix = trim((string)eticket_text('eticket_invoice_code_prefix', 'BTA-INV'));
                    $invoicePrefix = preg_replace('/[^A-Za-z0-9-]+/', '-', $invoicePrefix) ?: 'BTA-INV';
                    $invoice = strtoupper(rtrim($invoicePrefix, '-')) . '-' . date('Y') . '-' . str_pad((string)$bookingId, 6, '0', STR_PAD_LEFT);
                    $db->prepare("UPDATE bookings SET invoice_number = ? WHERE id = ?")->execute([$invoice, $bookingId]);

                    if ($isTandem) {
                        $items = [[$pkg['name'] . ' - Tandem ATV Unit (' . $activityUnits . ' Units)', $activityUnits, $activityUnitPrice, $activityPrice]];
                    } elseif ($isVehicle) {
                        $items = [[$pkg['name'] . ' - Volkswagen Vehicle (' . $activityUnits . ' Vehicles)', $activityUnits, $activityUnitPrice, $activityPrice]];
                    } else {
                        $items = [[$pkg['name'] . ' - Adult', $adults, $activityUnitPrice, $adults * $activityUnitPrice]];
                        if ($children > 0) $items[] = [$pkg['name'] . ' - Child', $children, (float)$pkg['price_child'], $children * (float)$pkg['price_child']];
                    }
                    if ($pickup) $items[] = ['Hotel Transport' . ($areaName ? ' - ' . $areaName : '') . ' (' . $transportGuests . ' Vehicle' . ($transportGuests > 1 ? 's' : '') . ')', $transportGuests, $pickupUnitPrice, $pickupPrice];
                    if ($discount > 0) $items[] = ['Promo Discount - ' . $discountCode, 1, -$discount, -$discount];

                    $it = $db->prepare("INSERT INTO booking_items(booking_id, item_name, quantity, unit_price, total_price) VALUES(?,?,?,?,?)");
                    foreach ($items as $i) $it->execute([$bookingId, $i[0], $i[1], $i[2], $i[3]]);

                    $db->commit();

                    // Resolve the selected pickup area again from the saved area ID.
                    // This ensures email HTML and the attached PDF always receive the
                    // canonical area name, even if the earlier validation value is empty.
                    if ($pickup && (int)$areaId > 0) {
                        try {
                            $areaNameStmt = $db->prepare('SELECT name FROM pickup_areas WHERE id = ? AND status = 1 LIMIT 1');
                            $areaNameStmt->execute([(int)$areaId]);
                            $savedAreaName = trim((string)$areaNameStmt->fetchColumn());
                            if ($savedAreaName !== '') $areaName = $savedAreaName;
                        } catch (Throwable $areaLookupError) {
                            error_log('[BTA PICKUP AREA LOOKUP] ' . $code . ': ' . $areaLookupError->getMessage());
                        }
                    }

                    $siteBaseUrl = rtrim((string)setting('site_url', BASE_URL), '/');
                    $confirmUrl = $siteBaseUrl . '/booking-confirmation?code=' . rawurlencode($code) . '&token=' . rawurlencode($accessToken);
                    // Email e-ticket opens the same A4 print view used by Booking Success.
                    $ticketUrl = $siteBaseUrl . '/booking-success?code=' . rawurlencode($code) . '&print=1';
                    $emailBooking = [
                        'booking_code' => $code, 'access_token' => $accessToken, 'invoice_number' => $invoice, 'package_name' => $pkg['name'],
                        'short_description' => $pkg['short_description'] ?? '', 'cover_image' => $pkg['cover_image'] ?? '',
                        'booking_date' => $date, 'activity_time' => $activityTime, 'pickup_time' => $pickupTime,
                        'adults' => $adults, 'children' => $children, 'booking_mode' => $bookingMode, 'customer_name' => $name, 'customer_email' => $email,
                        'customer_whatsapp' => $wa, 'customer_country' => $country, 'payment_method' => $paymentMethod,
                        'payment_status' => 'pending', 'booking_status' => 'new', 'subtotal' => $subtotal, 'discount_amount' => $discount, 'total' => $total,
                        'pickup_required' => $pickup, 'pickup_area_id' => $areaId, 'pickup_area_name' => $areaName, 'pickup_price' => $pickupPrice, 'hotel_name' => $hotel, 'room_number' => $room, 'special_request' => $special
                    ];

                    $siteName = trim((string)setting('site_name', 'Bali Tarantula Adventure'));
                    $subject = 'Booking ' . $code . ' - ' . $siteName;
                    $emailHtml = bta_booking_email_html($emailBooking, $confirmUrl, 'Booking Received', 'Thank you for choosing Bali Tarantula Adventure. Your booking has been received. Use the button below to open your A4 e-ticket.', 'customer', $ticketUrl);

                    // Release the PHP session lock before redirect/background work.
                    // This prevents other requests from the same browser session waiting
                    // while PDF generation and SMTP delivery continue.
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_write_close();
                    }

                    // Return the customer to the next page immediately after the booking
                    // transaction is committed. PDF generation and SMTP delivery continue
                    // after the HTTP response has been flushed (PHP-FPM / FastCGI).
                    if (strcasecmp($paymentMethod, 'Midtrans') === 0) {
                        header('Location: ' . rtrim(BASE_URL, '/') . '/midtrans-checkout?code=' . rawurlencode($code) . '&email=' . rawurlencode($email), true, 303);
                    } else {
                        header('Location: ' . rtrim(BASE_URL, '/') . '/booking-success?code=' . rawurlencode($code), true, 303);
                    }
                    ignore_user_abort(true);
                    if (function_exists('fastcgi_finish_request')) {
                        fastcgi_finish_request();
                    } else {
                        while (ob_get_level() > 0) { @ob_end_flush(); }
                        @flush();
                    }
                    @set_time_limit(120);

                    // Email delivery runs after the customer response, so slow SMTP/PDF
                    // processing no longer keeps the booking form loading.
                    // If PDF rendering fails, send the booking email without attachment.
                    $ticketPath = null;
                    $attachments = [];
                    try {
                        $ticketPath = bta_make_eticket_pdf($emailBooking);
                        if ($ticketPath && is_file($ticketPath) && is_readable($ticketPath)) {
                            $attachments = [['path' => $ticketPath, 'filename' => $code . '-E-Ticket.pdf', 'mime' => 'application/pdf']];
                        }
                    } catch (Throwable $pdfError) {
                        error_log('[BTA E-TICKET PDF] ' . $code . ': ' . $pdfError->getMessage());
                    }

                    try {
                        $customerSent = smtp_send($email, $subject, $emailHtml, '', null, $attachments);
                        if (!$customerSent) {
                            $firstError = smtp_last_error();
                            error_log('[BTA BOOKING EMAIL CUSTOMER] ' . $code . ': ' . $firstError);
                            // Retry without attachment even when PDF creation returned no file.
                            $customerSent = smtp_send($email, $subject, $emailHtml, '', null, []);
                            if (!$customerSent) {
                                $secondError = smtp_last_error();
                                error_log('[BTA BOOKING EMAIL CUSTOMER RETRY] ' . $code . ': ' . $secondError);
                                // One final retry handles temporary SMTP connection/server errors.
                                $customerSent = smtp_send($email, $subject, $emailHtml, '', null, []);
                                if (!$customerSent) error_log('[BTA BOOKING EMAIL CUSTOMER FINAL] ' . $code . ': ' . smtp_last_error());
                            }
                        }

                        $adminEmail = setting('booking_notification_email', setting('site_email', ''));
                        $adminRecipients = preg_split('/[,;\r\n]+/', (string)$adminEmail, -1, PREG_SPLIT_NO_EMPTY);
                        foreach ($adminRecipients as $recipient) {
                            $recipient = trim($recipient);
                            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) continue;
                            $adminHtml = bta_booking_email_html($emailBooking, $confirmUrl, 'New Booking Received', 'A new adventure booking has just arrived. Please review details below. Open the e-ticket using the button.', 'admin', $ticketUrl);
                            $adminSent = smtp_send($recipient, 'New ' . $subject, $adminHtml, $email, null, $attachments);
                            if (!$adminSent) {
                                $firstError = smtp_last_error();
                                error_log('[BTA BOOKING EMAIL ADMIN] ' . $code . ' to ' . $recipient . ': ' . $firstError);
                                // Retry without attachment, then retry once more for transient SMTP failures.
                                $adminSent = smtp_send($recipient, 'New ' . $subject, $adminHtml, $email, null, []);
                                if (!$adminSent) {
                                    $secondError = smtp_last_error();
                                    error_log('[BTA BOOKING EMAIL ADMIN RETRY] ' . $code . ' to ' . $recipient . ': ' . $secondError);
                                    $adminSent = smtp_send($recipient, 'New ' . $subject, $adminHtml, $email, null, []);
                                    if (!$adminSent) error_log('[BTA BOOKING EMAIL ADMIN FINAL] ' . $code . ' to ' . $recipient . ': ' . smtp_last_error());
                                }
                            }
                        }
                    } catch (Throwable $mailError) {
                        error_log('[BTA BOOKING EMAIL] ' . $code . ': ' . $mailError->getMessage());
                    } finally {
                        if ($ticketPath && is_file($ticketPath)) @unlink($ticketPath);
                    }

                    // The redirect was already sent before the background email work.
                    exit;

                } catch (Throwable $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    $error = ($e->getMessage() === 'This date is full.' ? 'The selected date is currently full for this package. Please choose another date.' : ($e->getMessage() === 'This package is unavailable on the selected date.' ? 'This package is unavailable on the selected date. Please choose another date.' : 'Unable to complete booking. Please try again.'));
                }
            }
        }
    }
}

$heroPkg = null;
foreach ($packages as $bp) {
    if ((int)$bp['id'] === $packageId) {
        $heroPkg = $bp;
        break;
    }
}
if (!$heroPkg && $packageId > 0) {
    $st = $db->prepare("SELECT id, name, price_adult, price_child, booking_mode, cover_image, short_description FROM packages WHERE id = ? AND status = 1 LIMIT 1");
    $st->execute([$packageId]);
    $heroPkg = $st->fetch() ?: null;
}

$title = 'Reserve Your Experience | ' . site_brand_name();
require __DIR__ . '/includes/header.php';
?>

<!-- Google Fonts: Barlow Condensed & Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,500;0,600;0,700;0,800;0,900;1,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style id="bta-booking-modern-theme">
:root {
  --bta-bg: #f5f4ef;
  --bta-panel: #ffffff;
  --bta-heading: #191919;
  --bta-text: #5d5b56;
  --bta-muted: #77736b;
  --bta-border: #dedbd4;
  --bta-orange: #ed3833;
  --bta-orange-hover: #ff743f;
  --bta-orange-glow: rgba(255, 90, 31, 0.35);
  --bta-green: #16a34a;
  --bta-font-display: 'Barlow Condensed', sans-serif;
  --bta-font-body: 'Inter', sans-serif;
}

body {
  background: var(--bta-bg);
  color: var(--bta-text);
  font-family: var(--bta-font-body);
  line-height: 1.6;
  margin: 0;
  padding: 0;
  -webkit-font-smoothing: antialiased;
}

.bta-booking * {
  box-sizing: border-box;
}

.bta-booking .wrap {
  width: min(1280px, 92%);
  margin: 0 auto;
}

/* Page Banner */
.page-banner {
  position: relative;
  min-height: 380px;
  display: flex;
  align-items: center;
  background: linear-gradient(112deg, #171717 0%, #242321 65%, #30271f 100%);
  border-bottom: 3px solid var(--bta-orange);
  overflow: hidden;
  padding: 120px 0 60px;
}

.page-banner::before {
  content: "";
  position: absolute;
  inset: 0;
  background: url('<?=e(asset_url(!empty($heroPkg['cover_image']) ? $heroPkg['cover_image'] : $bookingDefaultImage))?>') center/cover no-repeat;
  opacity: 0.22;
  filter: saturate(1.1);
}

.page-banner::after {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(23,23,23,0.7) 0%, rgba(23,23,23,0.92) 100%);
}

.page-banner-content {
  position: relative;
  z-index: 2;
}

.page-banner-content .eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-family: var(--bta-font-display);
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 2.5px;
  text-transform: uppercase;
  color: var(--bta-orange);
  margin: 0 0 10px;
}

.page-banner-content .eyebrow-line {
  display: inline-block;
  width: 24px;
  height: 2px;
  background: var(--bta-orange);
}

.page-banner-content h1 {
  font-family: var(--bta-font-display);
  font-size: clamp(40px, 6vw, 76px);
  font-weight: 900;
  line-height: 0.95;
  letter-spacing: -0.5px;
  text-transform: uppercase;
  color: #ffffff;
  margin: 0 0 16px;
}

.page-banner-content h1 span {
  color: var(--bta-orange);
}

.page-banner-content p {
  font-family: var(--bta-font-body);
  font-size: 16px;
  color: #e0ded9;
  max-width: 600px;
  margin: 0 0 20px;
}

.breadcrumbs {
  font-family: var(--bta-font-display);
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: #c9c6c0;
}

.breadcrumbs a {
  color: #c9c6c0;
  text-decoration: none;
  transition: color 0.2s;
}

.breadcrumbs a:hover {
  color: var(--bta-orange);
}

/* 2-Column Booking Layout */
.booking-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(340px, 1fr);
  gap: 40px;
  padding: 60px 0 120px;
  align-items: start;
}

.booking-card {
  background: var(--bta-panel);
  border: 1px solid var(--bta-border);
  border-top: 3px solid var(--bta-orange);
  border-radius: 16px;
  padding: 36px 32px;
  box-shadow: 0 16px 42px rgba(30, 25, 15, 0.06);
}

.booking-section-title {
  font-family: var(--bta-font-display);
  font-size: 24px;
  font-weight: 900;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--bta-heading);
  margin: 0 0 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.booking-section-title span.num {
  color: var(--bta-orange);
}

.form-group {
  margin-bottom: 22px;
}

.form-group label {
  display: block;
  font-family: var(--bta-font-display);
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--bta-heading);
  margin-bottom: 8px;
}

.form-group label span.req {
  color: var(--bta-orange);
}

.form-control {
  width: 100%;
  background: #ffffff;
  border: 1px solid #d8d4cc;
  border-radius: 8px;
  padding: 13px 16px;
  font-family: var(--bta-font-body);
  font-size: 15px;
  color: #191919;
  outline: none;
  transition: border-color 0.25s, box-shadow 0.25s;
}

.form-control:focus {
  border-color: var(--bta-orange);
  box-shadow: 0 0 0 3px rgba(255, 90, 31, 0.2);
}

.form-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
}

.time-slots-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.time-slot-btn {
  background: #ffffff;
  border: 1px solid #d8d4cc;
  border-radius: 8px;
  color: var(--bta-heading);
  font-family: var(--bta-font-display);
  font-size: 15px;
  font-weight: 800;
  padding: 10px 4px;
  cursor: pointer;
  text-align: center;
  transition: all 0.2s;
}

.time-slot-btn:hover, .time-slot-btn.is-selected {
  background: var(--bta-orange);
  border-color: var(--bta-orange);
  color: #111111 !important;
}

.promo-row {
  display: flex;
  gap: 10px;
}

.btn-apply-promo {
  background: #252422;
  border: 1px solid #252422;
  color: #ffffff;
  font-family: var(--bta-font-display);
  font-size: 15px;
  font-weight: 800;
  padding: 0 20px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-apply-promo:hover {
  background: var(--bta-orange);
  border-color: var(--bta-orange);
  color: #111111;
}

.button-orange-submit {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  background: var(--bta-orange);
  color: #111111 !important;
  font-family: var(--bta-font-display);
  font-size: 18px;
  font-weight: 900;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  padding: 16px 24px;
  border-radius: 8px;
  border: 0;
  cursor: pointer;
  transition: all 0.25s ease;
  box-shadow: 0 4px 20px var(--bta-orange-glow);
  margin-top: 14px;
}

.button-orange-submit:hover {
  background: var(--bta-orange-hover);
  transform: translateY(-2px);
  color: #111111 !important;
}

/* Sidebar Order Summary */
.booking-summary-panel {
  position: sticky;
  top: 100px;
  background: var(--bta-panel);
  border: 1px solid var(--bta-border);
  border-top: 3px solid var(--bta-orange);
  border-radius: 16px;
  padding: 28px 24px;
  box-shadow: 0 16px 42px rgba(30, 25, 15, 0.06);
}

.summary-cover {
  width: 100%;
  height: 180px;
  object-fit: cover;
  border-radius: 10px;
  border: 1px solid var(--bta-border);
  margin-bottom: 16px;
}

.summary-pkg-name {
  font-family: var(--bta-font-display);
  font-size: 24px;
  font-weight: 900;
  text-transform: uppercase;
  color: var(--bta-heading);
  line-height: 1.15;
  margin: 0 0 6px;
}

.summary-pkg-desc {
  font-size: 13.5px;
  color: var(--bta-text);
  line-height: 1.5;
  margin: 0 0 20px;
}

.summary-divider {
  height: 1px;
  background: var(--bta-border);
  margin: 18px 0;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  font-size: 14px;
  color: var(--bta-text);
}

.summary-row b {
  color: var(--bta-heading);
}

.summary-total-box {
  background: rgba(255, 90, 31, 0.08);
  border: 1px solid rgba(255, 90, 31, 0.25);
  border-radius: 10px;
  padding: 16px 18px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 18px;
}

.summary-total-box span {
  font-family: var(--bta-font-display);
  font-size: 15px;
  font-weight: 800;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--bta-heading);
}

.summary-total-box strong {
  font-family: var(--bta-font-display);
  font-size: 28px;
  font-weight: 900;
  color: var(--bta-orange);
}

.alert-error {
  background: rgba(239, 68, 68, 0.12);
  border: 1px solid rgba(239, 68, 68, 0.35);
  color: #b91c1c;
  padding: 16px;
  border-radius: 8px;
  margin-bottom: 24px;
  font-size: 14px;
}

@media (max-width: 992px) {
  .booking-layout {
    grid-template-columns: 1fr;
    gap: 30px;
  }
  .booking-summary-panel {
    position: relative;
    top: auto;
  }
}

@media (max-width: 600px) {
  .form-grid-2 {
    grid-template-columns: 1fr;
  }
  .time-slots-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>

<div class="bta-booking">
  <!-- Page Banner -->
  <section class="page-banner">
    <div class="wrap page-banner-content">
      <p class="eyebrow"><span class="eyebrow-line"></span>RESERVE YOUR EXPERIENCE</p>
      <h1>LET'S GET <span>WILD.</span></h1>
      <p>Select your track, customize your ride date, and get ready for Bali's premier extreme adventure.</p>
      <div class="breadcrumbs">
        <a href="<?=BASE_URL?>/">HOME</a> / BOOKING
      </div>
    </div>
  </section>

  <!-- Main Booking Form & Summary -->
  <main class="wrap booking-layout">
    
    <!-- Left Form Section -->
    <section class="booking-card">
      
      <?php if (!empty($error)): ?>
        <div class="alert-error">
          <b>✕ Unable to process booking:</b><br><?=e($error)?>
        </div>
      <?php endif; ?>

      <form method="post" id="bookingForm" novalidate>
        <?=csrf_field()?>
        <input type="hidden" name="package_id" id="package_id" value="<?=(int)$packageId?>">

        <!-- Step 1: Package & Schedule -->
        <div class="booking-section-title">
          <span class="num">01 /</span> EXPERIENCE &amp; SCHEDULE
        </div>

        <div class="form-group">
          <label for="package_picker">Choose Adventure Track <span class="req">*</span></label>
          <select class="form-control" name="package_picker" id="package_picker" required>
            <option value="">Select track package</option>
            <?php foreach ($packages as $p): ?>
              <option value="<?=$p['id']?>" 
                      data-adult="<?=$p['price_adult']?>"
                      data-booking-mode="<?=e($p['booking_mode'] ?? 'person')?>" 
                      data-child="<?=$p['price_child']?>" 
                      data-cover="<?=e(asset_url($p['cover_image'] ?? ''))?>" 
                      data-description="<?=e($p['short_description'] ?? '')?>" 
                      <?=$packageId == (int)$p['id'] ? 'selected' : ''?>>
                <?=e($p['name'])?> &mdash; <?=rupiah($p['price_adult'])?> / <?=in_array(($p['booking_mode']??'person'),['tandem','vehicle'],true)?'unit':'adult'?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label id="bookingDateLabel">Activity Date <span class="req">*</span></label>
            <input class="form-control" type="date" name="booking_date" min="<?=date('Y-m-d')?>" value="<?=e($_POST['booking_date'] ?? date('Y-m-d', strtotime('+1 day')))?>" required>
          </div>
          <div class="form-group" id="guestCountSelector">
            <label>Guests (Adults &amp; Children) <span class="req">*</span></label>
            <div style="display:flex; gap:10px;">
              <input class="form-control" type="number" id="adults" name="adults" min="1" value="<?=e($_POST['adults'] ?? '1')?>" placeholder="Adults" required>
              <input class="form-control" type="number" id="children" name="children" min="0" value="<?=e($_POST['children'] ?? '0')?>" placeholder="Children">
            </div>
          </div>
          <div class="form-group" id="unitBookingNotice" style="display:none;">
            <label id="unitBookingLabel">Number of Units</label>
            <div style="display:flex;align-items:center;gap:8px;">
              <button type="button" id="unitMinus" class="form-control" style="width:46px;flex:0 0 46px;font-size:22px;font-weight:800;">−</button>
              <input class="form-control" type="number" id="activity_units" name="activity_units" min="1" max="50" value="<?=e($_POST['activity_units'] ?? '1')?>" style="text-align:center;font-weight:800;" required>
              <button type="button" id="unitPlus" class="form-control" style="width:46px;flex:0 0 46px;font-size:22px;font-weight:800;">+</button>
            </div>
            <small id="unitBookingText" style="display:block;margin-top:7px;color:#9a3412;font-weight:700;">Choose how many units you want to book.</small>
          </div>
        </div>

        <div class="form-group" id="activityTimeGroup" style="display:none;">
          <label>Preferred Start Time <span class="req">*</span></label>
          <select name="activity_time" id="activity_time" style="display:none;">
            <option value="">Select activity time</option>
            <?php foreach (($activityTimeMap[$packageId] ?? []) as $at): ?>
              <option value="<?=e($at)?>" <?=($_POST['activity_time'] ?? '') === $at ? 'selected' : ''?>><?=e($at)?></option>
            <?php endforeach; ?>
          </select>
          <div class="time-slots-grid" id="activityTimeOptions"></div>
        </div>

        <div class="summary-divider"></div>

        <!-- Step 2: Rider Information -->
        <div class="booking-section-title">
          <span class="num">02 /</span> RIDER INFORMATION
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label for="customer_name">Full Name <span class="req">*</span></label>
            <input class="form-control" name="customer_name" id="customer_name" value="<?=e($_POST['customer_name'] ?? '')?>" placeholder="Lead rider name" required>
          </div>
          <div class="form-group">
            <label for="customer_email">Email Address <span class="req">*</span></label>
            <input class="form-control" type="email" name="customer_email" id="customer_email" value="<?=e($_POST['customer_email'] ?? '')?>" placeholder="E-ticket recipient" required>
          </div>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label for="customer_country">Country / Nationality <span class="req">*</span></label>
            <select class="form-control" name="customer_country" id="customer_country" required>
              <option value="">Select your country</option>
              <?php foreach ($countries as $c => $code): ?>
                <option value="<?=e($c)?>" <?=((string)($_POST['customer_country'] ?? '') === $c) ? 'selected' : ''?>><?=e($c)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="customer_whatsapp">WhatsApp Phone <span class="req">*</span></label>
            <div style="display:flex; gap:8px;">
              <select class="form-control" name="whatsapp_country_code" style="max-width:110px;">
                <?php foreach ($countries as $c => $code): if ($code !== ''): ?>
                  <option value="<?=e($code)?>" <?=((string)($_POST['whatsapp_country_code'] ?? '+62') === $code) ? 'selected' : ''?>><?=e($code)?></option>
                <?php endif; endforeach; ?>
              </select>
              <input class="form-control" name="customer_whatsapp" id="customer_whatsapp" value="<?=e($_POST['customer_whatsapp'] ?? '')?>" placeholder="812 3456 789" required>
            </div>
          </div>
        </div>

        <div class="summary-divider"></div>

        <!-- Step 3: Transport & Pickup -->
        <div class="booking-section-title">
          <span class="num">03 /</span> HOTEL TRANSPORT
        </div>

        <div class="form-group">
          <label>Transport Option</label>
          <select class="form-control" name="pickup_required" id="pickup_required">
            <option value="0" <?=((int)($_POST['pickup_required'] ?? 0) === 0) ? 'selected' : ''?>>Ticket Only &mdash; Self Arrival to Basecamp</option>
            <option value="1" <?=((int)($_POST['pickup_required'] ?? 0) === 1) ? 'selected' : ''?>>Ticket + Private Return Hotel Pickup (Min. 2 People · Max. 4 People per Vehicle)</option>
          </select>
        </div>

        <div id="pickupFields" style="display:none;">
          <div class="form-grid-2">
            <div class="form-group">
              <label>Pickup Area <span class="req">*</span></label>
              <select class="form-control" name="pickup_area_id" id="pickup_area_id">
                <option value="">Select hotel area</option>
                <?php foreach ($areas as $a): ?>
                  <option value="<?=$a['id']?>" <?=((int)($_POST['pickup_area_id'] ?? 0) == $a['id']) ? 'selected' : ''?>><?=e($a['name'])?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Pickup Time <span class="req">*</span></label>
              <select class="form-control" name="pickup_time" id="pickup_time">
                <option value="">Select pickup slot</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label>Hotel or Villa Name</label>
            <input class="form-control" name="hotel_name" id="hotel_name" value="<?=e($_POST['hotel_name'] ?? '')?>" placeholder="Hotel name &amp; complete address">
          </div>
        </div>

        <div class="form-group">
          <label for="special_request">Special Requests (Optional)</label>
          <textarea class="form-control" name="special_request" rows="2" placeholder="Dietary restrictions, tandem preferences, or special notes..."><?=e($_POST['special_request'] ?? '')?></textarea>
        </div>

        <div class="summary-divider"></div>

        <!-- Step 4: Promo & Payment -->
        <div class="booking-section-title">
          <span class="num">04 /</span> PROMO &amp; PAYMENT
        </div>

        <div class="form-group">
          <label>Promo Code</label>
          <div class="promo-row">
            <input class="form-control" name="promo_code" id="promo_code" value="<?=e($promoCode)?>" placeholder="Enter promo code">
            <button type="button" class="btn-apply-promo" id="applyPromo">APPLY</button>
          </div>
          <div id="promoResult" style="font-size:12px; margin-top:6px;"></div>
        </div>

        <div class="form-group">
          <label>Payment Method <span class="req">*</span></label>
          <select class="form-control" name="payment_method">
            <?php foreach ($paymentMethods as $pm): ?>
              <option value="<?=e($pm)?>" <?=hash_equals((string)$paymentMethod, (string)$pm) ? 'selected' : ''?>><?=e($pm)?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="button-orange-submit">
          CONFIRM &amp; PROCEED TO PAYMENT <span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span>
        </button>
      </form>

    </section>

    <!-- Right Sidebar: Order Summary -->
    <aside class="booking-summary-panel">
      <img id="summaryImage" class="summary-cover" 
           src="<?=e(asset_url(!empty($heroPkg['cover_image']) ? $heroPkg['cover_image'] : $bookingDefaultImage))?>" 
           alt="Adventure Cover">

      <h3 id="summaryPackageName" class="summary-pkg-name">
        <?=e($heroPkg['name'] ?? 'Select Track')?>
      </h3>
      <p id="summaryPackageDescription" class="summary-pkg-desc">
        <?=e($heroPkg['short_description'] ?? 'Choose your track to begin reservation.')?>
      </p>

      <div class="summary-divider"></div>

      <div class="summary-row">
        <span>Activity Date</span>
        <b id="summaryBookingDate">&mdash;</b>
      </div>
      <div class="summary-row">
        <span>Riders / Guests</span>
        <b id="summaryGuestCount">1 Person</b>
      </div>
      <div class="summary-row">
        <span>Hotel Transport</span>
        <b id="summaryPickup">Ticket Only</b>
      </div>
      <div class="summary-row" id="summaryPromoRow" style="display:none; color:#16a34a;">
        <span>Promo Discount</span>
        <b id="summaryDiscount">&mdash;</b>
      </div>

      <div class="summary-total-box">
        <span>TOTAL DUE</span>
        <strong id="summaryTotal">&mdash;</strong>
      </div>
    </aside>

  </main>
</div>

<script>
(function(){
  const picker = document.getElementById('package_picker');
  const hidden = document.getElementById('package_id');
  const adults = document.getElementById('adults');
  const children = document.getElementById('children');
  const guestCountSelector = document.getElementById('guestCountSelector');
  const unitBookingNotice = document.getElementById('unitBookingNotice');
  const unitBookingText = document.getElementById('unitBookingText');
  const unitBookingLabel = document.getElementById('unitBookingLabel');
  const activityUnitsInput = document.getElementById('activity_units');
  const unitMinus = document.getElementById('unitMinus');
  const unitPlus = document.getElementById('unitPlus');
  const pickup = document.getElementById('pickup_required');
  const area = document.getElementById('pickup_area_id');
  const fields = document.getElementById('pickupFields');
  const hotel = document.getElementById('hotel_name');
  const pickupTime = document.getElementById('pickup_time');
  const promo = document.getElementById('promo_code');
  const promoBtn = document.getElementById('applyPromo');
  const promoResult = document.getElementById('promoResult');

  const summaryImage = document.getElementById('summaryImage');
  const summaryPackageName = document.getElementById('summaryPackageName');
  const summaryPackageDescription = document.getElementById('summaryPackageDescription');
  const summaryBookingDate = document.getElementById('summaryBookingDate');
  const summaryGuestCount = document.getElementById('summaryGuestCount');
  const summaryPickup = document.getElementById('summaryPickup');
  const summaryTotal = document.getElementById('summaryTotal');
  const summaryPromoRow = document.getElementById('summaryPromoRow');
  const summaryDiscount = document.getElementById('summaryDiscount');

  const activityTimeGroup = document.getElementById('activityTimeGroup');
  const activityTimeSelect = document.getElementById('activity_time');
  const activityTimeOptions = document.getElementById('activityTimeOptions');

  const pickupPrices = <?=json_encode($pickupPriceMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
  const pickupTimeMap = <?=json_encode($pickupTimeMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
  const activityTimes = <?=json_encode($activityTimeMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
  let discount = 0;

  function rupiah(n){
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
  }

  function bookingMode(){
    const o = picker.options[picker.selectedIndex];
    return o?.dataset.bookingMode || 'person';
  }

  function unitCapacity(){
    return bookingMode() === 'tandem' ? 2 : (bookingMode() === 'vehicle' ? 4 : 0);
  }

  function unitCount(){
    return Math.max(1, Math.min(50, parseInt(activityUnitsInput?.value || '1', 10) || 1));
  }

  function syncGuestFields(){
    const mode = bookingMode();
    const unitBased = mode === 'tandem' || mode === 'vehicle';
    if (guestCountSelector) guestCountSelector.style.display = unitBased ? 'none' : '';
    if (adults) adults.required = !unitBased;
    if (children) children.required = false;
    if (activityUnitsInput) activityUnitsInput.required = unitBased;
    if (unitBookingNotice) unitBookingNotice.style.display = unitBased ? 'block' : 'none';
    const guests = (+adults.value || 1) + (+children.value || 0);
    if (unitBased) {
      const capacity = mode === 'tandem' ? 2 : 4;
      adults.value = String(unitCount() * capacity);
      children.value = '0';
    }
    if (unitBased && unitBookingText) {
      const cap = mode === 'tandem' ? 2 : 4;
      const units = unitCount();
      const noun = mode === 'tandem' ? 'unit' : 'vehicle';
      if (unitBookingLabel) unitBookingLabel.textContent = mode === 'tandem' ? 'Number of Tandem ATV Units' : 'Number of Volkswagen Vehicles';
      unitBookingText.textContent = units + ' ' + noun + (units > 1 ? 's' : '') + ' selected · ' + (units * cap) + ' participant capacity';
      unitBookingText.style.color = '#9a3412';
    }
  }

  function activitySubtotal(){
    const o = picker.options[picker.selectedIndex];
    const a = parseFloat(o?.dataset.adult || 0);
    const c = parseFloat(o?.dataset.child || 0);
    return (bookingMode() === 'tandem' || bookingMode() === 'vehicle')
      ? unitCount() * a
      : ((+adults.value || 1) * a + (+children.value || 0) * c);
  }

  function transportInfo(){
    const guests = (+adults.value || 1) + (+children.value || 0);
    if (pickup.value !== '1' || !area.value) return { unit: 0, count: 0, total: 0, guests };
    if (guests < 2) return { unit: 0, count: 0, total: 0, guests, minGuests: true };
    const unit = pickupPrices[picker.value + '_' + area.value];
    if (typeof unit !== 'number') return { unit: 0, count: 0, total: 0, missing: true, guests };
    // Each private transport accommodates up to 4 guests; charge once per vehicle.
    const count = Math.ceil(guests / 4);
    return { unit, count, total: unit * count, guests };
  }

  function renderActivityTimes(){
    const id = String(picker.value || '');
    const withTransport = pickup.value === '1';
    const times = withTransport ? [] : (activityTimes[id] || []);
    const previous = activityTimeSelect.value;
    activityTimeSelect.innerHTML = '<option value="">Select activity time</option>';
    activityTimeOptions.innerHTML = '';

    times.forEach(t => {
      const o = document.createElement('option');
      o.value = t; o.textContent = t;
      if (t === previous) o.selected = true;
      activityTimeSelect.appendChild(o);

      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'time-slot-btn' + (t === previous ? ' is-selected' : '');
      b.textContent = t;
      b.addEventListener('click', () => {
        activityTimeSelect.value = t;
        activityTimeOptions.querySelectorAll('.time-slot-btn').forEach(x => x.classList.remove('is-selected'));
        b.classList.add('is-selected');
      });
      activityTimeOptions.appendChild(b);
    });

    activityTimeGroup.style.display = times.length ? 'block' : 'none';
  }

  function refreshPickupTimes(){
    if (!pickupTime) return;
    const id = String(area?.value || '');
    const times = Array.isArray(pickupTimeMap[id]) ? pickupTimeMap[id] : [];
    pickupTime.innerHTML = '<option value="">Select pickup slot</option>';
    times.forEach(t => {
      const opt = document.createElement('option');
      opt.value = String(t).slice(0, 5);
      opt.textContent = String(t).slice(0, 5);
      pickupTime.appendChild(opt);
    });
  }

  function update(){
    syncGuestFields();
    const o = picker.options[picker.selectedIndex];
    const guests = (+adults.value || 1) + (+children.value || 0);
    const actSub = activitySubtotal();
    const t = transportInfo();
    const sub = actSub + t.total;
    const total = Math.max(0, sub - discount);

    hidden.value = o ? o.value : '';

    if (o && o.value) {
      summaryGuestCount.textContent = guests + ' ' + (guests > 1 ? 'People' : 'Person');
      const d = document.querySelector('[name=booking_date]').value;
      summaryBookingDate.textContent = d ? new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
      summaryPackageName.textContent = o.textContent.split(' — ')[0].trim();
      summaryPackageDescription.textContent = o.dataset.description || 'Extreme trail ride in Bali.';
      if (summaryImage && o.dataset.cover) summaryImage.src = o.dataset.cover;

      if (pickup.value !== '1') {
        summaryPickup.textContent = 'Ticket Only';
      } else if (t.minGuests) {
        summaryPickup.textContent = 'Min. 2 People';
      } else if (t.missing) {
        summaryPickup.textContent = 'Price Unavailable';
      } else {
        summaryPickup.textContent = rupiah(t.total);
      }

      if (discount > 0) {
        summaryPromoRow.style.display = 'flex';
        summaryDiscount.textContent = '- ' + rupiah(discount);
      } else {
        summaryPromoRow.style.display = 'none';
      }

      summaryTotal.textContent = rupiah(total);
    } else {
      summaryGuestCount.textContent = '1 Person';
      summaryBookingDate.textContent = '—';
      summaryPickup.textContent = 'Ticket Only';
      summaryTotal.textContent = '—';
      summaryPromoRow.style.display = 'none';
    }
  }

  function togglePickup(){
    const yes = pickup.value === '1';
    fields.style.display = yes ? 'block' : 'none';
    if (area) area.required = yes;
    if (hotel) hotel.required = yes;
    if (pickupTime) pickupTime.required = yes;
    if (yes) refreshPickupTimes();
    renderActivityTimes();
    update();
  }

  picker.addEventListener('change', () => {
    syncGuestFields();
    hidden.value = picker.value;
    discount = 0;
    promoResult.textContent = '';
    renderActivityTimes();
    refreshPickupTimes();
    update();
  });

  document.querySelector('[name=booking_date]').addEventListener('change', update);
  adults.addEventListener('input', () => { discount = 0; promoResult.textContent = ''; update(); });
  children.addEventListener('input', () => { discount = 0; promoResult.textContent = ''; update(); });
  if (activityUnitsInput) activityUnitsInput.addEventListener('input', () => {
    activityUnitsInput.value = Math.max(1, Math.min(50, parseInt(activityUnitsInput.value || '1', 10) || 1));
    discount = 0; promoResult.textContent = ''; update();
  });
  if (unitMinus) unitMinus.addEventListener('click', () => { activityUnitsInput.value = Math.max(1, unitCount() - 1); discount = 0; promoResult.textContent = ''; update(); });
  if (unitPlus) unitPlus.addEventListener('click', () => { activityUnitsInput.value = Math.min(50, unitCount() + 1); discount = 0; promoResult.textContent = ''; update(); });
  pickup.addEventListener('change', togglePickup);
  area.addEventListener('change', () => { refreshPickupTimes(); update(); });
  if (pickupTime) pickupTime.addEventListener('change', update);

  promoBtn.addEventListener('click', async function(){
    discount = 0;
    promoResult.textContent = 'Validating promo...';
    promoResult.style.color = 'var(--bta-gray-400)';
    const t = transportInfo();
    const qs = new URLSearchParams({
      code: promo.value,
      subtotal: activitySubtotal() + t.total,
      package_id: picker.value,
      date: document.querySelector('[name=booking_date]').value
    });

    try {
      const r = await fetch('coupon-check.php?' + qs.toString(), { headers: { Accept: 'application/json' } });
      const j = await r.json();
      if (j.ok) {
        discount = +j.discount || 0;
        promoResult.textContent = '✓ ' + j.message;
        promoResult.style.color = '#4ade80';
      } else {
        promoResult.textContent = '✕ ' + (j.message || 'Invalid promo code');
        promoResult.style.color = '#f87171';
      }
      update();
    } catch (e) {
      promoResult.textContent = 'Promo will be verified at checkout.';
      update();
    }
  });

  renderActivityTimes();
  syncGuestFields();
  togglePickup();
})();
</script>

<!-- Booking mobile layout: keep order summary in normal document flow, never fixed to viewport. -->
<style id="booking-summary-responsive-fix">
@media (max-width: 767px) {
  html body main.booking-layout > aside.booking-summary-panel,
  html body main.booking-layout > .booking-summary-panel {
    position: static !important;
    inset: auto !important;
    top: auto !important;
    right: auto !important;
    bottom: auto !important;
    left: auto !important;
    width: 100% !important;
    max-height: none !important;
    height: auto !important;
    overflow: visible !important;
    margin: 0 !important;
    transform: none !important;
    z-index: auto !important;
  }
  html body main.booking-layout {
    padding-bottom: 32px !important;
    scroll-padding-bottom: 32px !important;
  }
}

/* Mobile booking submit CTA override. */
@media screen and (max-width:767px){html body .bta-booking button.button-orange-submit,html body .bta-booking input.button-orange-submit{font-size:12px!important;}}
</style>

<?php $disableFloatingWhatsApp = true; require __DIR__ . '/includes/footer.php'; ?>
