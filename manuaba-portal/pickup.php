<?php
require __DIR__.'/includes/auth.php';
$p=db();
// Shared source of pickup-time options used by booking.php.
$p->exec("CREATE TABLE IF NOT EXISTS pickup_time_slots (id INT UNSIGNED NOT NULL AUTO_INCREMENT, pickup_area_id INT UNSIGNED NOT NULL, pickup_time TIME NOT NULL, status TINYINT(1) NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0, PRIMARY KEY(id), UNIQUE KEY uq_pickup_area_time(pickup_area_id,pickup_time), KEY idx_pickup_time_area(pickup_area_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
if($_SERVER['REQUEST_METHOD']==='POST'){
  admin_verify_csrf(); $act=$_POST['act']??'';
  if($act==='area'){$id=(int)($_POST['id']??0);$slug=admin_slug(($_POST['slug']??'')?:($_POST['name']??''));$status=(int)($_POST['status']??0);$sortOrder=(int)($_POST['sort_order']??0);$name=trim((string)($_POST['name']??''));$desc=trim((string)($_POST['description']??''));if($id)$p->prepare('UPDATE pickup_areas SET name=?,slug=?,description=?,status=?,sort_order=? WHERE id=?')->execute([$name,$slug,$desc,$status,$sortOrder,$id]);else$p->prepare('INSERT INTO pickup_areas(name,slug,description,status,sort_order) VALUES(?,?,?,?,?)')->execute([$name,$slug,$desc,$status,$sortOrder]);header('Location: pickup.php');exit;}
  if($act==='delarea'){$id=(int)$_POST['id'];if($id){$p->beginTransaction();try{$p->prepare('DELETE FROM package_pickup_prices WHERE pickup_area_id=?')->execute([$id]);$p->prepare('DELETE FROM pickup_areas WHERE id=?')->execute([$id]);$p->commit();}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}}header('Location: pickup.php');exit;}
  if($act==='slot'){
    $areaId=(int)($_POST['pickup_area_id']??0);$time=trim((string)($_POST['pickup_time']??''));$order=(int)($_POST['sort_order']??0);$status=(int)($_POST['status']??1);
    if($areaId>0 && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$time)){
      $q=$p->prepare('SELECT id FROM pickup_time_slots WHERE pickup_area_id=? AND pickup_time=?');$q->execute([$areaId,$time.':00']);$existing=$q->fetchColumn();
      if($existing)$p->prepare('UPDATE pickup_time_slots SET status=?,sort_order=? WHERE id=?')->execute([$status,$order,$existing]);
      else $p->prepare('INSERT INTO pickup_time_slots(pickup_area_id,pickup_time,status,sort_order) VALUES(?,?,?,?)')->execute([$areaId,$time.':00',$status,$order]);
    }
    header('Location: pickup.php#pickup-time-slots');exit;
  }
  if($act==='delslot'){$p->prepare('DELETE FROM pickup_time_slots WHERE id=?')->execute([(int)$_POST['id']]);header('Location: pickup.php#pickup-time-slots');exit;}
  if($act==='price'){$q=$p->prepare('SELECT id FROM package_pickup_prices WHERE package_id=? AND pickup_area_id=?');$q->execute([(int)$_POST['package_id'],(int)$_POST['area_id']]);$id=$q->fetchColumn();if($id)$p->prepare('UPDATE package_pickup_prices SET price=? WHERE id=?')->execute([(float)$_POST['price'],$id]);else$p->prepare('INSERT INTO package_pickup_prices(package_id,pickup_area_id,price) VALUES(?,?,?)')->execute([(int)$_POST['package_id'],(int)$_POST['area_id'],(float)$_POST['price']]);header('Location: pickup.php');exit;}
  if($act==='delprice'){$p->prepare('DELETE FROM package_pickup_prices WHERE id=?')->execute([(int)$_POST['id']]);header('Location: pickup.php');exit;}
}
$areas=$p->query('SELECT * FROM pickup_areas ORDER BY sort_order,name')->fetchAll();
$packages=$p->query('SELECT id,name FROM packages WHERE status=1 ORDER BY name')->fetchAll();
$prices=$p->query('SELECT pp.id,p.name package_name,a.name area_name,pp.price FROM package_pickup_prices pp JOIN packages p ON p.id=pp.package_id JOIN pickup_areas a ON a.id=pp.pickup_area_id ORDER BY p.name,a.sort_order')->fetchAll();
$pickupSlots=$p->query("SELECT s.id,s.pickup_area_id,s.pickup_time,s.status,s.sort_order,a.name area_name FROM pickup_time_slots s JOIN pickup_areas a ON a.id=s.pickup_area_id ORDER BY a.sort_order,a.name,s.sort_order,s.pickup_time")->fetchAll();
$page_title='Pickup & Pricing'; require __DIR__.'/includes/header.php';
?>
<div class="form"><h3>Add Pickup Area</h3><form method="post"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="act" value="area"><input type="hidden" name="id" value="0"><div class="formgrid"><div class="field"><label>Name</label><input required name="name"></div><div class="field"><label>Slug</label><input name="slug"></div><div class="field"><label>Description</label><input name="description"></div><div class="field"><label>Sort Order</label><input type="number" name="sort_order" value="99"></div></div><div class="actions"><button class="btn">Add Area</button></div></form></div>
<div class="section transport-price-panel pickup-area-panel">
  <div class="transport-price-heading">
    <div><h3>Pickup Areas — Edit / Delete</h3><div class="small" style="margin-top:6px">Edit area details in the table, then click Save. Deleting an area also removes its configured transport prices.</div></div>
    <span class="transport-price-count"><span aria-hidden="true">●</span> <?=count($areas)?> areas</span>
  </div>
  <div class="tablebox transport-price-tablebox">
    <table class="table transport-price-table pickup-area-table">
      <thead><tr><th>Name</th><th>Slug</th><th>Description</th><th>Status</th><th>Order</th><th style="text-align:right">Actions</th></tr></thead>
      <tbody>
      <?php foreach($areas as $x):?>
        <tr>
          <td><form method="post" class="pickup-area-edit-form"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="act" value="area"><input type="hidden" name="id" value="<?=$x['id']?>"><input required name="name" value="<?=admin_e($x['name'])?>" aria-label="Area name"></td>
          <td><input name="slug" value="<?=admin_e($x['slug'])?>" aria-label="Slug"></td>
          <td><input name="description" value="<?=admin_e($x['description']??'')?>" aria-label="Description"></td>
          <td><select name="status" aria-label="Status"><option value="1" <?=((int)$x['status']===1?'selected':'')?>>Active</option><option value="0" <?=((int)$x['status']===0?'selected':'')?>>Disabled</option></select></td>
          <td><input type="number" name="sort_order" value="<?=((int)$x['sort_order'])?>" aria-label="Sort order"></td>
          <td class="pickup-area-actions"><button class="btn pickup-save-btn">Save</button></form><form method="post" class="pickup-area-delete-form"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="act" value="delarea"><input type="hidden" name="id" value="<?=$x['id']?>"><button class="btn transport-delete-btn" data-confirm="Delete this pickup area and its related transport prices?">Delete</button></form></td>
        </tr>
      <?php endforeach;?>
      <?php if(!$areas):?><tr><td colspan="6" class="transport-price-empty"><span class="empty-mark">◇</span><strong>No pickup areas yet</strong><br><span>Add a pickup area above to see it listed here.</span></td></tr><?php endif;?>
      </tbody>
    </table>
  </div>
</div>
<div class="section transport-price-panel" id="pickup-time-slots">
  <div class="transport-price-heading"><div><h3>Pickup Time Slots</h3><div class="small" style="margin-top:6px">Manage the times shown in the booking form. Active times are synchronized by pickup area.</div></div><span class="transport-price-count"><?=count(array_filter($pickupSlots,fn($s)=>(int)$s['status']===1))?> active times</span></div>
  <form method="post" class="form" style="margin-bottom:18px"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="act" value="slot"><div class="formgrid"><div class="field"><label>Pickup Area</label><select name="pickup_area_id" required><?php foreach($areas as $a):?><option value="<?=$a['id']?>"><?=admin_e($a['name'])?></option><?php endforeach;?></select></div><div class="field"><label>Pickup Time</label><input type="time" name="pickup_time" required></div><div class="field"><label>Sort Order</label><input type="number" name="sort_order" value="0"></div><div class="field"><label>Status</label><select name="status"><option value="1">Active — show in booking</option><option value="0">Disabled — hide in booking</option></select></div></div><div class="actions"><button class="btn">Save Pickup Time</button></div></form>
  <div class="tablebox transport-price-tablebox"><table class="table transport-price-table"><thead><tr><th>Pickup Area</th><th>Pickup Time</th><th>Status</th><th>Order</th><th style="text-align:right">Action</th></tr></thead><tbody>
  <?php foreach($pickupSlots as $slot):?><tr><td><span class="transport-area"><?=admin_e($slot['area_name'])?></span></td><td><strong><?=admin_e(substr((string)$slot['pickup_time'],0,5))?></strong></td><td><?=((int)$slot['status']===1)?'<span class="transport-area">Active</span>':'<span class="transport-area" style="background:#f2f4f7;color:#667085">Disabled</span>'?></td><td><?=((int)$slot['sort_order'])?></td><td style="text-align:right"><form method="post" style="display:inline;margin:0"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="act" value="delslot"><input type="hidden" name="id" value="<?=$slot['id']?>"><button class="btn transport-delete-btn" data-confirm="Delete this pickup time?">Delete</button></form></td></tr><?php endforeach;?>
  <?php if(!$pickupSlots):?><tr><td colspan="5" class="transport-price-empty"><strong>No pickup times configured yet</strong><br><span>Add times above. They will then appear in booking.php for the selected area.</span></td></tr><?php endif;?></tbody></table></div>
</div>
<div class="section form"><h3>Package Transport Price</h3><div class="small" style="margin:-6px 0 14px">Set the transport price <b>per person</b>. The booking page adds this price for every guest when pickup is selected.</div><form method="post"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="act" value="price"><div class="formgrid"><div class="field"><label>Package</label><select name="package_id"><?php foreach($packages as $x):?><option value="<?=$x['id']?>"><?=admin_e($x['name'])?></option><?php endforeach;?></select></div><div class="field"><label>Area</label><select name="area_id"><?php foreach($areas as $x):?><option value="<?=$x['id']?>"><?=admin_e($x['name'])?></option><?php endforeach;?></select></div><div class="field"><label>Transport Price / Person</label><input type="number" step="0.01" min="0" name="price" required></div></div><div class="actions"><button class="btn">Save Price</button></div></form></div>
<style>
.transport-price-panel{background:linear-gradient(135deg,#f8fafc 0%,#eef3fb 100%);padding:22px;border:1px solid #e5eaf3;border-radius:18px;box-shadow:0 8px 24px rgba(31,45,78,.06);margin-top:22px}
.transport-price-heading{display:flex;align-items:center;justify-content:space-between;gap:14px;margin:0 0 18px;flex-wrap:wrap}
.transport-price-heading h3{margin:0;font-size:19px;font-weight:800;letter-spacing:-.3px;color:#172033}
.transport-price-count{display:inline-flex;align-items:center;gap:7px;background:#e7f6ee;color:#167346;border:1px solid #c8ead7;border-radius:999px;padding:6px 11px;font-size:12px;font-weight:750}
.transport-price-tablebox{border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;background:#fff;box-shadow:0 3px 10px rgba(16,24,40,.035)}
.transport-price-table{width:100%;border-collapse:separate;border-spacing:0;margin:0}
.transport-price-table thead th{background:#f7f9fc;color:#667085;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.055em;text-align:left;padding:15px 18px;border-bottom:1px solid #e7ebf2;white-space:nowrap}
.transport-price-table tbody td{padding:15px 18px;border-bottom:1px solid #edf0f5;color:#344054;font-size:14px;vertical-align:middle}
.transport-price-table tbody tr:last-child td{border-bottom:0}
.transport-price-table tbody tr{transition:background .18s ease}
.transport-price-table tbody tr:hover{background:#f8fbff}
.transport-package{font-weight:750;color:#1d2939}
.transport-area{display:inline-flex;align-items:center;background:#eff6ff;color:#2458a6;border:1px solid #dbeafe;border-radius:7px;padding:5px 9px;font-size:12px;font-weight:700}
.transport-price-value{font-size:14px;font-weight:800;color:#147647;white-space:nowrap;font-variant-numeric:tabular-nums}
.transport-delete-btn{background:#fff1f0!important;color:#c43232!important;border:1px solid #ffd4d0!important;border-radius:9px!important;padding:8px 13px!important;font-size:12px!important;font-weight:750!important;box-shadow:none!important;transition:all .18s ease}
.transport-delete-btn:hover{background:#c43232!important;color:#fff!important;border-color:#c43232!important;transform:translateY(-1px)}
.transport-price-empty{padding:30px 18px!important;text-align:center;color:#667085!important}
.transport-price-empty .empty-mark{display:block;font-size:24px;margin-bottom:8px}
@media(max-width:640px){.transport-price-panel{padding:14px;border-radius:14px}.transport-price-table thead th,.transport-price-table tbody td{padding:12px 10px}.transport-price-table thead th{font-size:10px}.transport-price-table tbody td{font-size:12px}.transport-price-heading h3{font-size:17px}}

.pickup-area-panel{margin-top:22px}
.pickup-area-table{min-width:900px}
.pickup-area-table input,.pickup-area-table select{width:100%;min-width:90px;box-sizing:border-box;border:1px solid #dce3ed;border-radius:8px;background:#fff;padding:9px 10px;color:#344054;font:inherit;font-size:13px;outline:none;transition:border-color .15s,box-shadow .15s}
.pickup-area-table input:focus,.pickup-area-table select:focus{border-color:#6b8de3;box-shadow:0 0 0 3px rgba(83,120,220,.12)}
.pickup-area-table td:first-child input{font-weight:750;color:#1d2939;min-width:130px}
.pickup-area-table td:nth-child(2) input{min-width:110px}
.pickup-area-table td:nth-child(3) input{min-width:150px}
.pickup-area-table td:nth-child(5) input{min-width:70px;width:76px}
.pickup-area-actions{white-space:nowrap;text-align:right;display:table-cell}
.pickup-area-actions .btn{margin:2px 0 2px 5px}
.pickup-save-btn{background:#e8f7ef!important;color:#147647!important;border:1px solid #c7ead7!important;border-radius:9px!important;padding:8px 13px!important;font-size:12px!important;font-weight:750!important;box-shadow:none!important;transition:all .18s ease}
.pickup-save-btn:hover{background:#147647!important;color:#fff!important;border-color:#147647!important;transform:translateY(-1px)}
.pickup-area-delete-form{display:inline;margin:0}
@media(max-width:640px){.pickup-area-panel{padding:14px;border-radius:14px}.pickup-area-table{min-width:850px}.pickup-area-table thead th,.pickup-area-table tbody td{padding:12px 10px}.pickup-area-table input,.pickup-area-table select{font-size:12px;padding:8px}}

</style>
<div class="section transport-price-panel">
  <div class="transport-price-heading">
    <h3>Configured Transport Prices</h3>
    <span class="transport-price-count"><span aria-hidden="true">●</span> <?=count($prices)?> configured</span>
  </div>
  <div class="tablebox transport-price-tablebox">
    <table class="table transport-price-table">
      <thead><tr><th>Package</th><th>Pickup Area</th><th>Transport Price</th><th style="text-align:right">Action</th></tr></thead>
      <tbody>
      <?php foreach($prices as $x):?>
        <tr>
          <td><span class="transport-package"><?=admin_e($x['package_name'])?></span></td>
          <td><span class="transport-area"><?=admin_e($x['area_name'])?></span></td>
          <td><span class="transport-price-value"><?=rupiah($x['price'])?></span></td>
          <td style="text-align:right;white-space:nowrap"><form method="post" style="margin:0;display:inline"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="act" value="delprice"><input type="hidden" name="id" value="<?=$x['id']?>"><button class="btn transport-delete-btn" data-confirm="Delete this pickup price?">Delete</button></form></td>
        </tr>
      <?php endforeach;?>
      <?php if(!$prices):?><tr><td colspan="4" class="transport-price-empty"><span class="empty-mark">◇</span><strong>No transport prices yet</strong><br><span>Add a package transport price above to see it listed here.</span></td></tr><?php endif;?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__.'/includes/footer.php';?>
