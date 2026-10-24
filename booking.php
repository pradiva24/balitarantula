<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#171717">
<title>Book Your Adventure | Bali Tarantula Adventure</title>
<meta name="description" content="Send a booking request for your Bali Tarantula Adventure experience. Choose an activity, time slot, guest count, and optional pickup.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=20261028">
<script src="assets/js/main.js?v=20261028" defer></script>
</head>
<body id="top">
<div class="topline"><span>BUILT FOR THE WILD</span><span>BALI, INDONESIA <span class="orange">●</span></span></div>
<header class="site-header">
<a class="brand" href="index.php"><span class="brand-mark">BT<span>A</span></span><span class="brand-copy">BALI TARANTULA<small>ADVENTURE</small></span></a>
<button class="menu-toggle" aria-label="Open navigation" aria-expanded="false"><span></span><span></span><span></span></button>
<nav class="main-nav"><a href="index.php">Home</a><a href="about.php">About Us</a><a href="packages.php">Packages</a><a href="blog.php">Blog</a><a href="gallery.php">Gallery</a><a href="contact.php">Contact</a><a class="nav-cta" href="booking.php">BOOK NOW ↗</a></nav>
</header>
<main>
<section class="page-banner"><div class="wrap page-banner-content"><p class="eyebrow"><span class="eyebrow-line"></span>RESERVE YOUR EXPERIENCE</p><h1>LET'S GET <span>WILD.</span></h1><p>Tell us when you want to ride. Our team can confirm availability, final pricing, and pickup details.</p><div class="breadcrumbs"><a href="index.php">HOME</a> / BOOKING</div></div></section>
<section class="booking-page wrap">
<div class="booking-layout">
<form class="booking-form" id="booking-form">
<section class="booking-step">
<h2>01. Choose your adventure</h2><p>Select your activity and preferred date and start time.</p>
<div class="booking-fields">
<div class="booking-field full"><label for="activity">ACTIVITY / PACKAGE *</label><select id="activity" name="activity" required><option value="">Select an activity</option><option>ATV Adventure</option><option>Tarantula Jungle Track</option><option>Jungle & River Track</option><option>Custom Adventure Package</option></select></div>
<div class="booking-field"><label for="activity-date">ACTIVITY DATE *</label><input type="date" id="activity-date" name="activity_date" required></div>
<div class="booking-field"><label for="activity-time">START TIME *</label><select id="activity-time" name="activity_time" required><option value="">Select time</option><option>08:00</option><option>09:00</option><option>10:00</option><option>11:00</option><option>13:00</option><option>14:00</option></select></div>
<div class="booking-field"><label for="adults">ADULTS *</label><input type="number" id="adults" name="adults" min="1" value="2" required></div>
<div class="booking-field"><label for="children">CHILDREN</label><input type="number" id="children" name="children" min="0" value="0"></div>
</div>
</section>
<section class="booking-step">
<h2>02. Optional pickup</h2><p>Pickup is optional and priced separately. Final pickup availability and fee will be confirmed by our team.</p>
<div class="booking-options"><label class="booking-option"><input type="radio" name="pickup" value="no" checked> Ticket / activity only</label><label class="booking-option"><input type="radio" name="pickup" value="yes"> Add hotel or villa pickup</label></div>
<div class="booking-fields" id="pickup-fields" hidden style="margin-top:16px">
<div class="booking-field"><label for="pickup-area">PICKUP AREA</label><select id="pickup-area" name="pickup_area"><option value="">Choose area</option><option>Ubud</option><option>Gianyar</option><option>Sanur</option><option>Seminyak / Kuta</option><option>Canggu</option><option>Nusa Dua / Jimbaran</option><option>Other area</option></select></div>
<div class="booking-field"><label for="pickup-time">PREFERRED PICKUP TIME</label><select id="pickup-time" name="pickup_time"><option value="">Confirm with operator</option><option>07:00</option><option>08:00</option><option>09:00</option><option>10:00</option><option>11:00</option></select></div>
<div class="booking-field full"><label for="pickup-address">HOTEL / VILLA NAME AND ADDRESS</label><textarea id="pickup-address" name="pickup_address" placeholder="Enter accommodation name, address, or map link"></textarea></div>
</div>
</section>
<section class="booking-step">
<h2>03. Guest details</h2><p>We will use these details to follow up on your request.</p>
<div class="booking-fields">
<div class="booking-field"><label for="guest-name">FULL NAME *</label><input id="guest-name" name="guest_name" autocomplete="name" placeholder="Your full name" required></div>
<div class="booking-field"><label for="guest-email">EMAIL *</label><input type="email" id="guest-email" name="guest_email" autocomplete="email" placeholder="you@example.com" required></div>
<div class="booking-field"><label for="guest-phone">WHATSAPP / PHONE *</label><input type="tel" id="guest-phone" name="guest_phone" autocomplete="tel" placeholder="+ country code" required></div>
<div class="booking-field"><label for="payment-method">PAYMENT PREFERENCE *</label><select id="payment-method" name="payment_method" required><option value="">Choose preference</option><option>Bank transfer (manual)</option><option>Payment gateway (subject to availability)</option><option>Discuss with operator</option></select></div>
<div class="booking-field full"><label for="guest-notes">SPECIAL REQUEST (OPTIONAL)</label><textarea id="guest-notes" name="guest_notes" placeholder="Any questions or requests?"></textarea></div>
</div>
</section>
<div><button class="button button-orange booking-submit" type="submit">REVIEW BOOKING REQUEST ↗</button><small>This is a request form prototype. No reservation or payment is created until a booking backend is connected and the operator confirms it.</small><div class="booking-notice" id="booking-notice" role="status" aria-live="polite"></div></div>
</form>
<aside class="booking-summary">
<span class="summary-label">YOUR REQUEST</span><h2>Booking summary</h2><p>Review your choices before sending your request.</p>
<div class="summary-row"><span>Activity</span><strong id="summary-activity">Not selected</strong></div>
<div class="summary-row"><span>Date & time</span><strong id="summary-datetime">Not selected</strong></div>
<div class="summary-row"><span>Guests</span><strong id="summary-guests">2 adults, 0 children</strong></div>
<div class="summary-row"><span>Pickup</span><strong id="summary-pickup">Not included</strong></div>
<div class="summary-row"><span>Price</span><strong>To be confirmed</strong></div>
<p class="summary-note">Pricing is not shown until official activity rates and pickup fees are configured. Slot availability must also be checked before a booking can be confirmed.</p>
<a class="button button-outline" href="packages.php" style="width:100%;margin-top:8px">VIEW PACKAGES</a>
</aside>
</div>
</section>
</main>
<footer class="site-footer"><div class="wrap footer-main"><a class="brand" href="index.php"><span class="brand-mark">BT<span>A</span></span><span class="brand-copy">BALI TARANTULA<small>ADVENTURE</small></span></a><p>GO BEYOND THE ORDINARY.<br><span>EXPERIENCE THE WILD SIDE OF BALI.</span></p><a class="back-top" href="#top">BACK TO TOP ↑</a></div><div class="wrap footer-bottom"><span>© <span id="year">2026</span> BALI TARANTULA ADVENTURE</span><span>MADE FOR THE WILD.</span></div></footer>
<script>
(() => {
 const form=document.getElementById('booking-form');
 if(!form)return;
 const $=id=>document.getElementById(id);
 const pickupFields=$('pickup-fields');
 const pickupAddress=$('pickup-address');
 const pickupArea=$('pickup-area');
 const pickupTime=$('pickup-time');
 const updatePickup=()=>{
   const enabled=form.querySelector('input[name="pickup"]:checked')?.value==='yes';
   pickupFields.hidden=!enabled;
   [pickupAddress,pickupArea,pickupTime].forEach(el=>{el.required=enabled;el.disabled=!enabled;});
   $('summary-pickup').textContent=enabled?'Pickup requested':'Not included';
 };
 const updateSummary=()=>{
   $('summary-activity').textContent=$('activity').value||'Not selected';
   const date=$('activity-date').value;
   const time=$('activity-time').value;
   $('summary-datetime').textContent=date?(date+(time?' · '+time:'')):'Not selected';
   $('summary-guests').textContent=($('adults').value||'0')+' adults, '+($('children').value||'0')+' children';
   if(form.querySelector('input[name="pickup"]:checked')?.value==='yes'){
     const area=$('pickup-area').value;
     $('summary-pickup').textContent='Requested'+(area?' · '+area:'');
   } else $('summary-pickup').textContent='Not included';
 };
 form.querySelectorAll('input,select,textarea').forEach(el=>el.addEventListener('change',()=>{updatePickup();updateSummary();}));
 form.addEventListener('submit',event=>{
   event.preventDefault();
   if(!form.reportValidity())return;
   const notice=$('booking-notice');
   notice.textContent='Your form details are ready to review, but this website is not yet connected to a booking backend. Nothing has been submitted, no slot has been reserved, and no payment has been taken. Connect the PHP/PDO booking backend before accepting live bookings.';
   notice.classList.add('show');
   notice.scrollIntoView({behavior:'smooth',block:'nearest'});
 });
 updatePickup();updateSummary();
 const dateInput=$('activity-date');
 if(dateInput){const today=new Date();const localDate=new Date(today.getTime()-today.getTimezoneOffset()*60000).toISOString().slice(0,10);dateInput.min=localDate;}
})();
</script>
</body>
</html>