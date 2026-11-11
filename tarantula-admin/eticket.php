<?php
require __DIR__.'/includes/auth.php';
require_once __DIR__.'/../includes/eticket-config.php';

$defaults = eticket_config();
$groups = [
 'Success Page' => [
  'eticket_success_title'=>'Success title','eticket_success_subtitle'=>'Success subtitle','eticket_success_message'=>'Success message','eticket_email_message'=>'Email confirmation message','eticket_booking_details_title'=>'Booking details title','eticket_activity_location'=>'Activity location text'
 ],
 'Trust Cards' => [
  'eticket_trust_1_title'=>'Card 1 title','eticket_trust_1_text'=>'Card 1 text','eticket_trust_2_title'=>'Card 2 title','eticket_trust_2_text'=>'Card 2 text','eticket_trust_3_title'=>'Card 3 title','eticket_trust_3_text'=>'Card 3 text','eticket_trust_4_title'=>'Card 4 title','eticket_trust_4_text'=>'Card 4 text'
 ],
 'Ticket Code Settings' => [
  'eticket_ticket_code_prefix'=>'Ticket code prefix (e.g. AUS)',
  'eticket_invoice_code_prefix'=>'Invoice code prefix (e.g. AUS-INV)'
 ],
 'E-Ticket' => [
  'eticket_ticket_title'=>'E-Ticket heading','eticket_ticket_subtitle'=>'E-Ticket subtitle','eticket_ticket_brand'=>'Ticket brand (use {{site_name}} for site name)','eticket_ticket_location'=>'Ticket location','eticket_ticket_note_title'=>'Ticket note title','eticket_ticket_note_text'=>'Ticket note text','eticket_qr_help'=>'QR help text','eticket_company_title'=>'Company card title','eticket_company_location_label'=>'Company location label','eticket_company_phone_label'=>'Company phone label'
 ],
 'Printable E-Ticket' => [
  'eticket_print_eyebrow'=>'Print hero eyebrow','eticket_print_title'=>'Print ticket title','eticket_print_activity_location_title'=>'Activity location heading','eticket_print_important_title'=>'Important information heading','eticket_print_help_title'=>'Help heading','eticket_info_1'=>'Important item 1','eticket_info_2'=>'Important item 2','eticket_info_3'=>'Important item 3','eticket_info_4'=>'Important item 4'
 ],
 'Bottom Information' => [
  'eticket_bottom_title'=>'Bottom information title','eticket_bottom_1'=>'Bottom item 1','eticket_bottom_2'=>'Bottom item 2','eticket_bottom_3'=>'Bottom item 3','eticket_bottom_4'=>'Bottom item 4','eticket_support_title'=>'Support title','eticket_support_text'=>'Support text','eticket_support_button'=>'Support button'
 ],
 'Footer' => [
  'eticket_footer_title'=>'Footer title (use {{site_name_upper}} for uppercase site name)','eticket_footer_subtitle'=>'Footer subtitle','eticket_footer_right'=>'Footer tagline'
 ]
];
if($_SERVER['REQUEST_METHOD']==='POST'){
 admin_verify_csrf();
 $save=[];
 foreach($groups as $fields){ foreach($fields as $key=>$label){ $save[$key]=trim((string)($_POST[$key]??'')); } }
 $p=db();
 $json=json_encode($save,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
 $st=$p->prepare("SELECT id FROM settings WHERE setting_key=?"); $st->execute(['eticket_config']); $id=$st->fetchColumn();
 if($id) $p->prepare("UPDATE settings SET setting_value=?,updated_at=NOW() WHERE id=?")->execute([$json,$id]);
 else $p->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?)")->execute(['eticket_config',$json]);
 admin_flash('success','E-Ticket content saved successfully.');
 header('Location: eticket.php'); exit;
}
$cfg=eticket_config();
$page_title='E-Ticket Editor';
require __DIR__.'/includes/header.php';
?>
<style>
.et-page{max-width:1100px;margin:24px auto;padding:0 18px}.et-intro{color:#667085;margin:-6px 0 20px}.et-card{background:#fff;border:1px solid #e3e8e5;border-radius:14px;padding:20px;margin-bottom:16px;box-shadow:0 3px 12px rgba(16,24,40,.04)}.et-card h2{margin:0 0 5px;color:#175f48;font-size:18px}.et-card p{margin:0 0 16px;color:#667085;font-size:13px}.et-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.et-field{display:flex;flex-direction:column;gap:6px}.et-field.full{grid-column:1/-1}.et-field label{font-size:12px;font-weight:700;color:#344054}.et-field input,.et-field textarea{width:100%;border:1px solid #d7dfda;border-radius:9px;padding:11px 12px;font:inherit;color:#1f3e32;background:#fff}.et-field textarea{min-height:82px;resize:vertical}.et-field small{color:#98a29d;font-size:11px}.et-actions{position:sticky;bottom:14px;display:flex;justify-content:flex-end;padding:12px 0}.et-save{border:0;border-radius:10px;background:#28764e;color:#fff;padding:12px 20px;font-weight:800;cursor:pointer;box-shadow:0 6px 16px rgba(40,118,78,.2)}.et-preview{display:inline-flex;margin-right:auto;align-items:center;gap:7px;color:#28764e;text-decoration:none;font-weight:700;padding:11px 14px;border:1px solid #cfe0d5;border-radius:10px;background:#f6fbf7}@media(max-width:720px){.et-grid{grid-template-columns:1fr}.et-field.full{grid-column:auto}}
</style>
<main class="et-page">
 <h1>E-Ticket Editor</h1>
 <p class="et-intro">Edit ticket codes and the text shown on the Booking Success page and printable e-ticket without changing the booking data or layout. Code prefix changes apply to new bookings only.</p>
 <form method="post">
  <input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>">
  <?php foreach($groups as $title=>$fields): ?>
   <section class="et-card"><h2><?=admin_e($title)?></h2><p>Changes apply immediately to new and existing booking-success pages.</p><div class="et-grid">
   <?php foreach($fields as $key=>$label): $value=(string)($cfg[$key]??''); $long=strlen($value)>90 || str_contains($key,'text') || str_contains($key,'message') || str_contains($key,'item_'); ?>
    <div class="et-field <?=($long?'full':'')?>"><label for="<?=admin_e($key)?>"><?=admin_e($label)?></label><?php if($long): ?><textarea id="<?=admin_e($key)?>" name="<?=admin_e($key)?>"><?=admin_e($value)?></textarea><?php else: ?><input id="<?=admin_e($key)?>" name="<?=admin_e($key)?>" value="<?=admin_e($value)?>"><?php endif; ?></div>
   <?php endforeach; ?></div></section>
  <?php endforeach; ?>
  <div class="et-actions"><a class="et-preview" href="<?=e(rtrim(BASE_URL,'/').'/booking-success')?>" target="_blank" rel="noopener">↗ Preview Booking Success</a><button class="et-save" type="submit">Save E-Ticket</button></div>
 </form>
</main>
<?php require __DIR__.'/includes/footer.php'; ?>
