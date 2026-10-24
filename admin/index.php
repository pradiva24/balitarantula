<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
$admin = require_admin();
$pdo = Database::connection();
$stats = [
    'bookings_today' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE activity_date = CURDATE()")->fetchColumn(),
    'pending_payments' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE payment_status IN ('unpaid','pending_review') AND booking_status <> 'cancelled'")->fetchColumn(),
    'active_packages' => (int)$pdo->query("SELECT COUNT(*) FROM packages WHERE status = 'published'")->fetchColumn(),
    'redeemed_today' => (int)$pdo->query("SELECT COUNT(*) FROM redemptions WHERE DATE(redeemed_at) = CURDATE()")->fetchColumn(),
];
?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard | Bali Tarantula Adventure</title>
<style>
:root{--orange:#ff5a1f;--bg:#f4f2ed;--ink:#191919;--muted:#69665f}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.5 Arial,sans-serif}.top{background:#151515;color:#fff;padding:18px clamp(18px,4vw,48px);display:flex;align-items:center;justify-content:space-between;gap:18px}.brand{font-weight:900;letter-spacing:1px}.brand span{color:var(--orange)}.top small{display:block;color:#bbb;font-weight:400;margin-top:3px}.top form{margin:0}.top button{background:transparent;border:1px solid #555;color:#fff;padding:9px 12px;cursor:pointer}.wrap{max-width:1200px;margin:auto;padding:36px 22px}.eyebrow{font-size:10px;font-weight:900;letter-spacing:2px;color:var(--orange)}h1{font-size:clamp(30px,5vw,44px);line-height:1;margin:8px 0}p{color:var(--muted)}.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:28px 0}.card,.section{background:#fff;border:1px solid #e3dfd7;padding:22px;box-shadow:0 8px 24px #21190b08}.card small{color:var(--muted)}.number{font-size:38px;font-weight:900;margin-top:10px}.links{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.links a{display:block;padding:18px;background:#1a1a1a;color:#fff;text-decoration:none;border-bottom:3px solid var(--orange);font-weight:800}.links a span{display:block;color:#bbb;font-weight:400;font-size:12px;margin-top:7px}@media(max-width:760px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.links{grid-template-columns:1fr 1fr}}@media(max-width:440px){.stats{gap:9px}.card{padding:15px}.number{font-size:30px}.links{grid-template-columns:1fr}.top{align-items:flex-start}}
</style></head><body><header class="top"><div class="brand">BALI TARANTULA <span>ADVENTURE</span><small>ADMIN CONTROL ROOM · <?= e($admin['name']) ?></small></div><form method="post" action="logout.php"><?= csrf_field() ?><button type="submit">Sign out</button></form></header>
<main class="wrap"><div class="eyebrow">OPERATIONS OVERVIEW</div><h1>Dashboard</h1><p>Ringkasan operasional. Angka akan terisi setelah database dan data booking tersedia.</p>
<section class="stats">
<div class="card"><small>Aktivitas hari ini</small><div class="number"><?= number_format($stats['bookings_today']) ?></div></div>
<div class="card"><small>Pembayaran perlu ditinjau</small><div class="number"><?= number_format($stats['pending_payments']) ?></div></div>
<div class="card"><small>Paket aktif</small><div class="number"><?= number_format($stats['active_packages']) ?></div></div>
<div class="card"><small>Redeem hari ini</small><div class="number"><?= number_format($stats['redeemed_today']) ?></div></div>
</section>
<div class="section"><h2>Modul berikutnya</h2><p>Fondasi autentikasi dan database sudah disiapkan. Modul CRUD paket, pengelolaan slot, booking publik, pembayaran, dan redeem akan ditambahkan bertahap.</p><nav class="links"><a href="catalog.php">Activities & packages<span>Tambah activity dan paket beserta harga</span></a><a href="slots.php">Activity slots<span>Atur tanggal, jam, dan kapasitas</span></a><a href="bookings.php">Bookings<span>Lihat pesanan dan status pembayaran/tiket</span></a><a href="../booking.php">Public booking<span>Uji alur booking publik</span></a><a href="../index.php">Public website<span>Buka homepage yang ada</span></a><a href="../database/schema.sql">Database schema<span>File SQL instalasi awal</span></a><a href="../README.md">Project guide<span>Setup lokal dan hosting</span></a></nav></div>
</main></body></html>
