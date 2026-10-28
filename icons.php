<?php
require __DIR__.'/includes/auth.php';
$db=db();
$icons=[
 'home.why_1'=>['Homepage · Why Choose Us 1','fa-solid fa-water'],'home.why_2'=>['Homepage · Why Choose Us 2','fa-solid fa-shield-heart'],'home.why_3'=>['Homepage · Why Choose Us 3','fa-solid fa-mountain-sun'],'home.why_4'=>['Homepage · Why Choose Us 4','fa-solid fa-people-group'],
 'home.value_1'=>['Homepage · Value 1','fa-solid fa-leaf'],'home.value_2'=>['Homepage · Value 2','fa-solid fa-handshake'],'home.value_3'=>['Homepage · Value 3','fa-solid fa-heart'],'home.value_4'=>['Homepage · Value 4','fa-solid fa-star'],
 'about.why_1'=>['About · Why Choose Us 1','fa-solid fa-water'],'about.why_2'=>['About · Why Choose Us 2','fa-solid fa-shield-heart'],'about.why_3'=>['About · Why Choose Us 3','fa-solid fa-mountain-sun'],'about.why_4'=>['About · Why Choose Us 4','fa-solid fa-people-group'],
 'about.value_1'=>['About · Value 1','fa-solid fa-leaf'],'about.value_2'=>['About · Value 2','fa-solid fa-handshake'],'about.value_3'=>['About · Value 3','fa-solid fa-heart'],'about.value_4'=>['About · Value 4','fa-solid fa-star'],
 'booking.safe'=>['Booking · Safe & Secure','fa-solid fa-shield-halved'],'booking.instant'=>['Booking · Instant Confirmation','fa-solid fa-bolt'],'booking.support'=>['Booking · 24/7 Support','fa-solid fa-headset'],'booking.guests'=>['Booking · Guests','fa-solid fa-users'],'booking.name'=>['Booking · Full Name','fa-solid fa-user'],'booking.country'=>['Booking · Country','fa-solid fa-globe'],'booking.pickup'=>['Booking · Hotel Pickup','fa-solid fa-car'],'booking.request'=>['Booking · Special Request','fa-regular fa-note-sticky'],'booking.promo'=>['Booking · Promo Code','fa-solid fa-percent'],'booking.activity'=>['Booking · Activity','fa-solid fa-person-hiking'],'booking.date'=>['Booking · Date','fa-regular fa-calendar'],'booking.email'=>['Booking · Email','fa-regular fa-envelope'],'booking.phone'=>['Booking · Phone','fa-solid fa-phone'],'booking.location'=>['Booking · Location','fa-solid fa-location-dot'],'booking.easy'=>['Booking · Easy Booking','fa-solid fa-circle-check'],
 'package.safe'=>['Package · Safe & Comfortable','fa-solid fa-shield-heart'],'package.location'=>['Package · Location','fa-solid fa-location-dot'],'package.safety'=>['Package · Safety','fa-solid fa-circle-check'],'package.share'=>['Package · Share','fa-solid fa-share-nodes'],
 'gallery.adventure'=>['Gallery · Real Adventure','fa-solid fa-person-hiking'],'gallery.guests'=>['Gallery · Happy Guests','fa-solid fa-users'],'gallery.beauty'=>['Gallery · Beauty of Bali','fa-solid fa-camera'],
 'contact.location'=>['Contact · Location','fa-solid fa-location-dot'],'contact.phone'=>['Contact · Phone','fa-solid fa-phone'],'contact.email'=>['Contact · Email','fa-regular fa-envelope'],'contact.hours'=>['Contact · Opening Hours','fa-regular fa-clock'],
 'blog.scenic'=>['Blog · Scenic Track','fa-solid fa-mountain-sun'],'blog.safety'=>['Blog · Safety Equipment','fa-solid fa-helmet-safety'],
 'footer.instagram'=>['Footer · Instagram','fa-brands fa-instagram'],'footer.facebook'=>['Footer · Facebook','fa-brands fa-facebook-f'],'footer.youtube'=>['Footer · YouTube','fa-brands fa-youtube'],'footer.tripadvisor'=>['Footer · Tripadvisor','fa-brands fa-tripadvisor'],'footer.tiktok'=>['Footer · TikTok','fa-brands fa-tiktok'],'footer.link'=>['Footer · Link Arrow','fa-solid fa-chevron-right'],'footer.address'=>['Footer · Address','fa-solid fa-location-dot'],'footer.phone'=>['Footer · Phone','fa-solid fa-phone'],'footer.email'=>['Footer · Email','fa-regular fa-envelope'],'footer.hours'=>['Footer · Hours','fa-regular fa-clock'],'footer.trust_1'=>['Footer · Trust 1','fa-solid fa-circle-check'],'footer.trust_2'=>['Footer · Trust 2','fa-solid fa-shield-heart'],'footer.trust_3'=>['Footer · Trust 3','fa-solid fa-handshake'],
 'error.adventure'=>['404 · Adventures','fa-solid fa-person-hiking'],'error.photo'=>['404 · Photo Spots','fa-solid fa-camera'],'error.packages'=>['404 · Tour Packages','fa-solid fa-box-open'],'error.about'=>['404 · About Us','fa-solid fa-circle-info'],'error.book'=>['404 · Book Now','fa-solid fa-calendar-check'],
 'ticket.confirmation'=>['Ticket · Confirmation','fa-regular fa-envelope'],'ticket.eticket'=>['Ticket · E-Ticket','fa-solid fa-ticket'],'ticket.help'=>['Ticket · Need Help','fa-solid fa-circle-question'],'ticket.secure'=>['Ticket · Secure Booking','fa-solid fa-shield-halved'],'ticket.success'=>['Ticket · Success','fa-solid fa-circle-check'],
 'admin.dashboard'=>['Admin · Dashboard','fa-solid fa-house'],'admin.homepage'=>['Admin · Homepage','fa-solid fa-house-chimney'],'admin.packages'=>['Admin · Packages','fa-solid fa-box'],'admin.categories'=>['Admin · Categories','fa-solid fa-shapes'],'admin.gallery'=>['Admin · Gallery','fa-regular fa-images'],'admin.about'=>['Admin · About','fa-solid fa-circle-info'],'admin.reviews'=>['Admin · Reviews','fa-solid fa-star'],'admin.blog'=>['Admin · Blog','fa-solid fa-pen-to-square'],'admin.pages'=>['Admin · Pages','fa-solid fa-file-lines'],'admin.menu'=>['Admin · Navigation','fa-solid fa-bars'],'admin.contact'=>['Admin · Contact','fa-regular fa-envelope'],'admin.availability'=>['Admin · Availability','fa-regular fa-calendar-days'],'admin.capacity'=>['Admin · Capacity','fa-solid fa-table-cells'],'admin.payment'=>['Admin · Payment Gateway','fa-solid fa-credit-card'],'admin.reports'=>['Admin · Reports','fa-solid fa-chart-column'],'admin.coupons'=>['Admin · Coupons','fa-solid fa-percent'],'admin.bookings'=>['Admin · Bookings','fa-solid fa-box'],'admin.pickup'=>['Admin · Pickup','fa-solid fa-location-dot'],'admin.users'=>['Admin · Users','fa-solid fa-user-shield'],'admin.security'=>['Admin · Security','fa-solid fa-shield-halved'],'admin.backup'=>['Admin · Backup','fa-solid fa-download'],'admin.seo'=>['Admin · SEO','fa-solid fa-magnifying-glass'],'admin.media'=>['Admin · Media','fa-solid fa-file-lines'],'admin.footer'=>['Admin · Footer','fa-solid fa-file-lines'],'admin.icons'=>['Admin · Icon Manager','fa-solid fa-icons'],'admin.settings'=>['Admin · Settings','fa-solid fa-gear'],'admin.permalinks'=>['Admin · Permalinks','fa-solid fa-arrow-up-right-from-square'],'admin.logout'=>['Admin · Logout','fa-solid fa-right-from-bracket'],
];
if($_SERVER['REQUEST_METHOD']==='POST'){
 admin_verify_csrf();
 foreach($icons as $key=>$meta){
   $v=trim((string)($_POST['icon_'.$key]??''));
   if(!preg_match('/^fa-(?:solid|regular|brands|light|thin|duotone)\s+fa-[a-z0-9-]+(?:\s+fa-[a-z0-9-]+)*$/i',$v)) $v=$meta[1];
   $settingKey='icon_'.$key;
   try {
     $st=$db->prepare('SELECT id FROM settings WHERE setting_key=?');
     $st->execute([$settingKey]);
     $id=$st->fetchColumn();
     if($id) {
       $db->prepare('UPDATE settings SET setting_value=? WHERE id=?')->execute([$v,$id]);
     } else {
       $db->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?)')->execute([$settingKey,$v]);
     }
   } catch(Throwable $e) {
     // Keep the page-specific source of truth even if an optional settings column/schema differs.
   }
   if(strpos($key,'home.')===0){
     $field=str_replace('home.','',$key).'_icon';
     try { $db->prepare("UPDATE homepage_content SET `{$field}`=? WHERE id=1")->execute([$v]); } catch(Throwable $e) {}
   } elseif(strpos($key,'about.')===0){
     $field=str_replace('about.','',$key).'_icon';
     try { $db->prepare("UPDATE about_page SET `{$field}`=? WHERE id=1")->execute([$v]); } catch(Throwable $e) {}
   }
 }
 admin_flash('success','All icon settings saved.'); header('Location: icons.php'); exit;
}
function icon_manager_current(PDO $db, string $key, string $default): string {
    try {
        if(strpos($key,'home.')===0){
            $field=str_replace('home.','',$key).'_icon';
            $allowed=['why_1_icon','why_2_icon','why_3_icon','why_4_icon','value_1_icon','value_2_icon','value_3_icon','value_4_icon'];
            if(in_array($field,$allowed,true)){
                $st=$db->query("SELECT `{$field}` FROM homepage_content WHERE id=1 LIMIT 1");
                $v=trim((string)($st->fetchColumn()?:''));
                if($v!=='') return $v;
            }
        } elseif(strpos($key,'about.')===0){
            $field=str_replace('about.','',$key).'_icon';
            $allowed=['why_1_icon','why_2_icon','why_3_icon','why_4_icon','value_1_icon','value_2_icon','value_3_icon','value_4_icon'];
            if(in_array($field,$allowed,true)){
                $st=$db->query("SELECT `{$field}` FROM about_page WHERE id=1 LIMIT 1");
                $v=trim((string)($st->fetchColumn()?:''));
                if($v!=='') return $v;
            }
        }
        $st=$db->prepare('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1');
        $st->execute(['icon_'.$key]);
        $v=trim((string)($st->fetchColumn()?:''));
        return $v!==''?$v:$default;
    } catch(Throwable $e){ return $default; }
}
$page_title='Icon Manager'; require __DIR__.'/includes/header.php';
?>
<div class="card"><div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap"><div><h3>Font Awesome Icon Manager</h3><p class="muted-line">Change website icons without editing PHP. Use a Font Awesome Free class such as <code>fa-solid fa-water</code> or <code>fa-brands fa-instagram</code>.</p></div><a class="btn gray" target="_blank" rel="noopener" href="https://fontawesome.com/search?o=r&m=free">Browse Free Icons ↗</a></div></div>
<form method="post"><input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>">
<div class="card"><div class="form-grid">
<?php foreach($icons as $key=>$meta): $current=icon_manager_current($db,$key,$meta[1]); ?>
<div class="field"><label><?=admin_e($meta[0])?></label><div style="display:flex;gap:10px;align-items:center"><input style="flex:1" name="icon_<?=admin_e($key)?>" value="<?=admin_e($current)?>" data-icon-input><span data-icon-preview style="width:42px;height:42px;border:1px solid #d0d5dd;border-radius:10px;display:grid;place-items:center;font-size:18px"><i class="<?=admin_e($current)?>"></i></span></div></div>
<?php endforeach; ?>
</div><div style="margin-top:20px"><button class="btn primary" type="submit">Save All Icons</button></div></div></form>
<script>
document.querySelectorAll('[data-icon-input]').forEach(function(input){input.addEventListener('input',function(){var p=input.parentElement.querySelector('[data-icon-preview]'); if(p)p.innerHTML='<i class="'+input.value.replace(/[^a-z0-9 -]/gi,'')+'"></i>';});});
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
