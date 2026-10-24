<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/includes/site-settings.php';

$pdo = Database::connection();

// Release a small batch of expired, unpaid temporary holds during normal booking traffic.
try {
    $pdo->beginTransaction();
    $expired = $pdo->query("SELECT id,slot_id,participants FROM bookings WHERE booking_status='pending' AND payment_status='unpaid' AND reservation_expires_at IS NOT NULL AND reservation_expires_at < NOW() ORDER BY id LIMIT 25 FOR UPDATE")->fetchAll();
    $expireStmt = $pdo->prepare("UPDATE bookings SET booking_status='expired' WHERE id=:id AND booking_status='pending' AND payment_status='unpaid'");
    $releaseStmt = $pdo->prepare("UPDATE activity_slots SET reserved_count=GREATEST(0,reserved_count-:qty) WHERE id=:slot");
    foreach ($expired as $oldBooking) {
        $expireStmt->execute(['id'=>(int)$oldBooking['id']]);
        if ($expireStmt->rowCount() === 1) {
            $releaseStmt->execute(['qty'=>(int)$oldBooking['participants'],'slot'=>(int)$oldBooking['slot_id']]);
        }
    }
    $pdo->commit();
} catch (Throwable $expiryError) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Expired booking cleanup failed: '.$expiryError->getMessage());
}
$errors = [];
$bookingCode = null;
$packageId = (int)($_GET['package_id'] ?? $_POST['package_id'] ?? 0);
$date = (string)($_GET['date'] ?? $_POST['activity_date'] ?? date('Y-m-d', strtotime('+1 day')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d', strtotime('+1 day'));

$packages = $pdo->query("SELECT id,name,price_per_person,currency,pickup_available,pickup_fee,min_participants,max_participants FROM packages WHERE status='published' ORDER BY name")->fetchAll();
$areas = $pdo->query("SELECT id,name,fee FROM pickup_areas WHERE status='active' ORDER BY name")->fetchAll();
$package = null;
foreach ($packages as $item) if ((int)$item['id'] === $packageId) $package = $item;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
    $name = trim((string)($_POST['full_name'] ?? ''));
    $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $phone = trim((string)($_POST['phone'] ?? ''));
    $country = trim((string)($_POST['country'] ?? ''));
    $slotId = filter_input(INPUT_POST, 'slot_id', FILTER_VALIDATE_INT) ?: 0;
    $adults = filter_input(INPUT_POST, 'adults', FILTER_VALIDATE_INT);
    $children = filter_input(INPUT_POST, 'children', FILTER_VALIDATE_INT);
    $areaId = filter_input(INPUT_POST, 'pickup_area_id', FILTER_VALIDATE_INT) ?: 0;
    $address = trim((string)($_POST['pickup_address'] ?? ''));
    $pickup = ($_POST['pickup_required'] ?? '0') === '1';
    $note = trim((string)($_POST['customer_note'] ?? ''));

    if ($name === '' || mb_strlen($name) > 160) $errors[] = 'Masukkan nama lengkap yang valid.';
    if (!$email) $errors[] = 'Masukkan email yang valid.';
    if ($adults === false || $adults === null || $adults < 1) $errors[] = 'Jumlah dewasa minimal 1 orang.';
    if ($children === false || $children === null || $children < 0) $errors[] = 'Jumlah anak tidak valid.';
    if ($packageId < 1 || $slotId < 1) $errors[] = 'Pilih paket dan waktu aktivitas.';
    if ($pickup && ($areaId < 1 || $address === '')) $errors[] = 'Pilih area pickup dan isi alamat hotel/villa.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $q = $pdo->prepare("SELECT s.id,s.slot_date,s.start_time,s.capacity,s.reserved_count,p.id package_id,p.name package_name,p.price_per_person,p.currency,p.pickup_available,p.pickup_fee,p.min_participants,p.max_participants FROM activity_slots s JOIN packages p ON p.id=s.package_id WHERE s.id=:slot AND s.package_id=:package AND s.status='open' AND p.status='published' AND (s.slot_date > CURDATE() OR (s.slot_date = CURDATE() AND s.start_time > CURTIME())) FOR UPDATE");
            $q->execute(['slot'=>$slotId,'package'=>$packageId]);
            $slot = $q->fetch();
            if (!$slot) throw new DomainException('Jadwal tidak tersedia. Pilih waktu lain.');
            $participants = (int)$adults + (int)$children;
            if ($participants < (int)$slot['min_participants']) throw new DomainException('Jumlah peserta di bawah minimum paket.');
            if ($slot['max_participants'] !== null && $participants > (int)$slot['max_participants']) throw new DomainException('Jumlah peserta melebihi batas paket.');
            if ((int)$slot['capacity'] - (int)$slot['reserved_count'] < $participants) throw new DomainException('Slot ini tidak memiliki kapasitas yang cukup.');
            if ($pickup && !(int)$slot['pickup_available']) throw new DomainException('Paket ini tidak menyediakan pickup.');

            $area = null; $pickupFee = 0.00;
            if ($pickup) {
                $q = $pdo->prepare("SELECT id,name,fee FROM pickup_areas WHERE id=:id AND status='active' FOR UPDATE");
                $q->execute(['id'=>$areaId]); $area = $q->fetch();
                if (!$area) throw new DomainException('Area pickup tidak tersedia.');
                $pickupFee = (float)$area['fee'];
                if ($pickupFee <= 0) $pickupFee = (float)$slot['pickup_fee'];
            }

            $unit = (float)$slot['price_per_person'];
            $subtotal = round($unit * $participants, 2);
            $total = round($subtotal + $pickupFee, 2);
            $q = $pdo->prepare('INSERT INTO customers (full_name,email,phone,country) VALUES (:name,:email,:phone,:country)');
            $q->execute(['name'=>$name,'email'=>mb_strtolower((string)$email),'phone'=>$phone ?: null,'country'=>$country ?: null]);
            $customerId = (int)$pdo->lastInsertId();
            $code = 'BTA-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $expires = date('Y-m-d H:i:s', time()+1800);
            $q = $pdo->prepare("INSERT INTO bookings (booking_code,customer_id,package_id,slot_id,activity_date,activity_time,adults,children,participants,price_per_person_snapshot,subtotal,pickup_required,pickup_area_id,pickup_area_snapshot,pickup_address,pickup_fee_snapshot,total_amount,currency,booking_status,payment_status,reservation_expires_at,customer_note) VALUES (:code,:customer,:package,:slot,:date,:time,:adults,:children,:participants,:price,:subtotal,:pickup,:area_id,:area_name,:address,:pickup_fee,:total,:currency,'pending','unpaid',:expires,:note)");
            $q->execute(['code'=>$code,'customer'=>$customerId,'package'=>$packageId,'slot'=>$slotId,'date'=>$slot['slot_date'],'time'=>$slot['start_time'],'adults'=>$adults,'children'=>$children,'participants'=>$participants,'price'=>$unit,'subtotal'=>$subtotal,'pickup'=>$pickup?1:0,'area_id'=>$area['id']??null,'area_name'=>$area['name']??null,'address'=>$pickup?$address:null,'pickup_fee'=>$pickupFee,'total'=>$total,'currency'=>$slot['currency'],'expires'=>$expires,'note'=>$note?:null]);
            $bookingId = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO payments (booking_id,method,amount,currency,status,notes) VALUES (:booking,'bank_transfer',:amount,:currency,'pending','Awaiting manual transfer and administrator verification')")->execute(['booking'=>$bookingId,'amount'=>$total,'currency'=>$slot['currency']]);
            $pdo->prepare('UPDATE activity_slots SET reserved_count=reserved_count+:qty WHERE id=:id')->execute(['qty'=>$participants,'id'=>$slotId]);
            $pdo->commit();
            redirect('booking-success.php?code=' . rawurlencode($code));
        } catch (DomainException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $ex->getMessage();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('BTA booking error: '.$ex->getMessage());
            $errors[] = 'Booking belum dapat diproses. Silakan coba kembali atau hubungi tim kami.';
        }
    }
}

$slots = [];
if ($packageId > 0) {
    $q=$pdo->prepare("SELECT id,start_time,capacity,reserved_count FROM activity_slots WHERE package_id=:package AND slot_date=:date AND status='open' AND (slot_date > CURDATE() OR (slot_date = CURDATE() AND start_time > CURTIME())) ORDER BY start_time");
    $q->execute(['package'=>$packageId,'date'=>$date]); $slots=$q->fetchAll();
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#171717"><?php $faviconPath=bta_site_favicon(); if($faviconPath!==''):?><link rel="icon" href="<?=e($faviconPath)?>"><?php endif;?>
<title><?=e(bta_setting('site_title','Bali Tarantula Adventure'))?> | Booking</title><meta name="description" content="<?=e(bta_setting('site_description','Book an outdoor adventure in Bali with Bali Tarantula Adventure.'))?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=20261029"><script src="assets/js/main.js?v=20261029" defer></script>
<style>.booking-message{background:#fff;padding:18px;margin:0 0 22px;border-left:4px solid #ff5a1f;color:#191919}.booking-message.error{border-color:#e52232}.booking-message h2{margin:0 0 8px}.booking-message p{color:#555}.filter-card{background:#fff;border:1px solid #e3dfd7;padding:20px;margin-bottom:22px}.filter-card .booking-fields{align-items:end}.booking-field small{color:#666;font-size:11px}</style>
</head><body id="top"><?php require_once __DIR__.'/includes/header.php'; ?>
<main><section class="page-banner"><div class="wrap page-banner-content"><p class="eyebrow"><span class="eyebrow-line"></span> RESERVE YOUR EXPERIENCE</p><h1>LET'S GET <span>WILD.</span></h1><p>Choose a package and available activity time. Pickup is optional when offered.</p><div class="breadcrumbs"><a href="index.php">HOME</a> / BOOKING</div></div></section>
<section class="booking-page wrap">
<?php if($bookingCode): ?><div class="booking-message"><h2>Booking request received</h2><p>Your booking code is <strong><?=e($bookingCode)?></strong>. Payment status is unpaid. Your slot is temporarily held for 30 minutes; contact the team for official bank-transfer instructions. A ticket is not issued until payment is verified.</p></div><?php endif; ?>
<?php if($errors): ?><div class="booking-message error"><strong>We couldn't complete the booking.</strong><ul><?php foreach($errors as $error): ?><li><?=e($error)?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form class="filter-card" method="get" action="booking.php"><div class="booking-fields">
<div class="booking-field"><label for="package_id">ADVENTURE PACKAGE *</label><select id="package_id" name="package_id" required><option value="">Select a package</option><?php foreach($packages as $p): ?><option value="<?=(int)$p['id']?>" <?=(int)$p['id']===$packageId?'selected':''?>><?=e($p['name'])?> — <?=e($p['currency'])?> <?=number_format((float)$p['price_per_person'],0)?> / person</option><?php endforeach; ?></select></div>
<div class="booking-field"><label for="date">ACTIVITY DATE *</label><input id="date" type="date" name="date" value="<?=e($date)?>" min="<?=e(date('Y-m-d'))?>" required></div>
<div class="booking-field"><button class="button button-orange" type="submit">CHECK AVAILABLE TIMES ↗</button></div></div></form>
<div class="booking-layout"><form class="booking-form" method="post" action="booking.php?package_id=<?=$packageId?>&date=<?=e($date)?>"><?=csrf_field()?><input type="hidden" name="package_id" value="<?=$packageId?>"><input type="hidden" name="activity_date" value="<?=e($date)?>">
<section class="booking-step"><h2>01 / Choose activity time</h2><p><?= $package ? 'Selected: '.e($package['name']) : 'Select a package above first.' ?></p><div class="booking-fields"><div class="booking-field full"><label for="slot_id">AVAILABLE ACTIVITY TIME *</label><select id="slot_id" name="slot_id" required <?=!$package?'disabled':''?>><option value="">Select a time</option><?php foreach($slots as $s):$remaining=(int)$s['capacity']-(int)$s['reserved_count'];if($remaining>0):?><option value="<?=(int)$s['id']?>" <?=(int)($_POST['slot_id']??0)===(int)$s['id']?'selected':''?>><?=e(substr((string)$s['start_time'],0,5))?> — <?=$remaining?> spaces left</option><?php endif;endforeach;?></select><?php if($package && !$slots):?><small>No open times for this date. Check another date.</small><?php endif;?></div></div></section>
<section class="booking-step"><h2>02 / Guest details</h2><p>We will use these details to follow up on your booking.</p><div class="booking-fields">
<div class="booking-field full"><label for="full_name">FULL NAME *</label><input id="full_name" name="full_name" maxlength="160" value="<?=e($_POST['full_name']??'')?>" autocomplete="name" required></div>
<div class="booking-field"><label for="email">EMAIL *</label><input id="email" type="email" name="email" maxlength="190" value="<?=e($_POST['email']??'')?>" autocomplete="email" required></div>
<div class="booking-field"><label for="phone">PHONE / WHATSAPP</label><input id="phone" name="phone" maxlength="40" value="<?=e($_POST['phone']??'')?>" autocomplete="tel"></div>
<div class="booking-field"><label for="country">COUNTRY</label><input id="country" name="country" maxlength="100" value="<?=e($_POST['country']??'')?>" autocomplete="country-name"></div>
<div class="booking-field"><label for="adults">ADULTS *</label><input id="adults" type="number" name="adults" min="1" max="100" value="<?=e($_POST['adults']??'1')?>" required></div>
<div class="booking-field"><label for="children">CHILDREN</label><input id="children" type="number" name="children" min="0" max="100" value="<?=e($_POST['children']??'0')?>"></div>
<div class="booking-field full"><label for="customer_note">SPECIAL REQUEST (OPTIONAL)</label><textarea id="customer_note" name="customer_note" maxlength="2000"><?=e($_POST['customer_note']??'')?></textarea></div></div></section>
<section class="booking-step"><h2>03 / Optional pickup</h2><p>Pickup is available only for packages that support it.</p>
<?php if($package && (int)$package['pickup_available']===1):?><div class="booking-options"><label class="booking-option"><input type="radio" name="pickup_required" value="0" <?=($_POST['pickup_required']??'0')!=='1'?'checked':''?>> No pickup</label><label class="booking-option"><input type="radio" name="pickup_required" value="1" <?=($_POST['pickup_required']??'')==='1'?'checked':''?>> Add pickup</label></div>
<div class="booking-fields" style="margin-top:14px"><div class="booking-field"><label for="pickup_area_id">PICKUP AREA</label><select id="pickup_area_id" name="pickup_area_id"><option value="">Choose area</option><?php foreach($areas as $a):?><option value="<?=(int)$a['id']?>" <?=(int)($_POST['pickup_area_id']??0)===(int)$a['id']?'selected':''?>><?=e($a['name'])?> — <?=e(number_format((float)$a['fee'],0))?> IDR</option><?php endforeach;?></select></div><div class="booking-field"><label for="pickup_address">HOTEL / VILLA ADDRESS</label><input id="pickup_address" name="pickup_address" maxlength="500" value="<?=e($_POST['pickup_address']??'')?>" placeholder="Hotel/villa name and address"></div></div>
<?php else:?><p>Pickup is not available for this package or no package has been selected.</p><?php endif;?></section>
<section class="booking-step"><h2>04 / Submit request</h2><p>Payment uses manual bank transfer initially. The team must verify payment before issuing a ticket.</p><button class="button button-orange booking-submit" type="submit" <?=!$package?'disabled':''?>>REQUEST BOOKING <span>↗</span></button><small>Your booking is calculated on the server. The selected slot is locked and capacity checked before saving.</small></section></form>
<aside class="booking-summary"><span class="summary-label">BOOKING SUMMARY</span><h2><?= $package?e($package['name']):'Your adventure' ?></h2><div class="summary-row"><span>Price per person</span><strong><?=$package?e($package['currency']).' '.number_format((float)$package['price_per_person'],0):'Choose a package'?></strong></div><div class="summary-row"><span>Date</span><strong><?=e($date)?></strong></div><div class="summary-row"><span>Pickup</span><strong>Optional if available</strong></div><p class="summary-note">The final price is calculated from the published package price and pickup area fee. Availability is checked again during submission to help prevent overbooking.</p><a class="button button-outline" href="packages.php" style="width:100%;margin-top:8px">VIEW PACKAGES</a></aside></div></section></main>
<?php require_once __DIR__.'/includes/footer.php'; ?><script>
(()=>{const radios=document.querySelectorAll('input[name="pickup_required"]');const area=document.getElementById('pickup_area_id');const address=document.getElementById('pickup_address');function sync(){const on=document.querySelector('input[name="pickup_required"]:checked')?.value==='1';if(area){area.disabled=!on;area.required=on;}if(address){address.disabled=!on;address.required=on;}}radios.forEach(r=>r.addEventListener('change',sync));sync();})();
</script></body></html>
