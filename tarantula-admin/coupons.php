<?php
require __DIR__.'/includes/auth.php';
$p=db();
$action=$_GET['action']??'list';
$id=(int)($_GET['id']??0);
if($_SERVER['REQUEST_METHOD']==='POST'){
 admin_verify_csrf(); $act=$_POST['act']??'';
 try{
  if($act==='save'){
   $id=(int)$_POST['id']; $code=strtoupper(trim($_POST['code'])); $title=trim($_POST['title']);
   $type=$_POST['discount_type']==='fixed'?'fixed':'percent'; $value=max(0,(float)$_POST['discount_value']);
   $min=max(0,(float)$_POST['min_subtotal']); $max=$_POST['max_discount']!==''?max(0,(float)$_POST['max_discount']):null;
   $limit=$_POST['usage_limit']!==''?max(0,(int)$_POST['usage_limit']):null; $start=$_POST['start_date']?:null; $end=$_POST['end_date']?:null;
   $package=(int)($_POST['package_id']??0)?:null; $status=(int)$_POST['status'];
   if($id) $p->prepare('UPDATE coupons SET code=?,title=?,discount_type=?,discount_value=?,min_subtotal=?,max_discount=?,usage_limit=?,start_date=?,end_date=?,package_id=?,status=?,updated_at=NOW() WHERE id=?')->execute([$code,$title,$type,$value,$min,$max,$limit,$start,$end,$package,$status,$id]);
   else $p->prepare('INSERT INTO coupons(code,title,discount_type,discount_value,min_subtotal,max_discount,usage_limit,start_date,end_date,package_id,status) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$code,$title,$type,$value,$min,$max,$limit,$start,$end,$package,$status]);
   admin_flash('','Promo saved'); header('Location: coupons.php'); exit;
  }
  if($act==='delete'){$p->prepare('DELETE FROM coupons WHERE id=?')->execute([(int)$_POST['id']]);admin_flash('','Promo deleted');header('Location: coupons.php');exit;}
 }catch(Throwable $e){admin_flash('error','Could not save promo. Check that the code is unique.');header('Location: coupons.php');exit;}
}
$edit=null;
if($action==='edit'){ $q=$p->prepare('SELECT * FROM coupons WHERE id=?'); $q->execute([$id]); $edit=$q->fetch(); }
$packages=$p->query('SELECT id,name FROM packages ORDER BY name')->fetchAll();
$rows=$p->query('SELECT c.*,p.name package_name FROM coupons c LEFT JOIN packages p ON p.id=c.package_id ORDER BY c.id DESC')->fetchAll();
$page_title='Promos & Coupons'; require __DIR__.'/includes/header.php';
if($action==='add'||$action==='edit'):
$x=$edit??[];
?>
<div class="form">
<h2><?= $edit?'Edit':'Add' ?> Promo</h2>
<form method="post">
<input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="act" value="save"><input type="hidden" name="id" value="<?=admin_e($x['id']??0)?>">
<div class="formgrid">
<div class="field"><label>Code</label><input required maxlength="50" name="code" value="<?=admin_e($x['code']??'')?>" placeholder="BALI10"></div>
<div class="field"><label>Title</label><input required name="title" value="<?=admin_e($x['title']??'')?>" placeholder="10% Bali Adventure Promo"></div>
<div class="field"><label>Discount Type</label><select name="discount_type"><option value="percent" <?=($x['discount_type']??'percent')==='percent'?'selected':''?>>Percent (%)</option><option value="fixed" <?=($x['discount_type']??'')==='fixed'?'selected':''?>>Fixed (IDR)</option></select></div>
<div class="field"><label>Discount Value</label><input type="number" step="0.01" min="0" name="discount_value" value="<?=admin_e($x['discount_value']??0)?>"></div>
<div class="field"><label>Minimum Subtotal</label><input type="number" step="0.01" min="0" name="min_subtotal" value="<?=admin_e($x['min_subtotal']??0)?>"></div>
<div class="field"><label>Max Discount <small>(optional)</small></label><input type="number" step="0.01" min="0" name="max_discount" value="<?=admin_e($x['max_discount']??'')?>"></div>
<div class="field"><label>Usage Limit <small>(optional)</small></label><input type="number" min="1" name="usage_limit" value="<?=admin_e($x['usage_limit']??'')?>"></div>
<div class="field"><label>Package</label><select name="package_id"><option value="">All packages</option><?php foreach($packages as $pp):?><option value="<?=$pp['id']?>" <?=($x['package_id']??'')==$pp['id']?'selected':''?>><?=admin_e($pp['name'])?></option><?php endforeach;?></select></div>
<div class="field"><label>Start Date</label><input type="date" name="start_date" value="<?=admin_e($x['start_date']??'')?>"></div>
<div class="field"><label>End Date</label><input type="date" name="end_date" value="<?=admin_e($x['end_date']??'')?>"></div>
<div class="field"><label>Status</label><select name="status"><option value="1" <?=($x['status']??1)?'selected':''?>>Active</option><option value="0" <?=isset($x['status'])&&!$x['status']?'selected':''?>>Inactive</option></select></div>
</div><div class="actions"><button class="btn">Save Promo</button><a class="btn gray" href="coupons.php">Cancel</a></div>
</form></div>
<?php else: ?>
<div class="head"><h2>Promo Codes</h2><a class="btn" href="?action=add">+ Add Promo</a></div>
<div class="tablebox"><table class="table"><tr><th>Code</th><th>Discount</th><th>Validity</th><th>Usage</th><th>Package</th><th>Status</th><th></th></tr>
<?php foreach($rows as $r):?><tr><td><b><?=admin_e($r['code'])?></b><div class="small"><?=admin_e($r['title'])?></div></td><td><?=$r['discount_type']==='percent'?admin_e($r['discount_value']).'%':rupiah($r['discount_value'])?></td><td><?=admin_e($r['start_date']?:'Any')?> → <?=admin_e($r['end_date']?:'Any')?></td><td><?=number_format((int)$r['used_count'])?><?= $r['usage_limit']!==null?' / '.number_format((int)$r['usage_limit']):'' ?></td><td><?=admin_e($r['package_name']?:'All packages')?></td><td><?=$r['status']?'<span class="badge green">Active</span>':'<span class="badge redtxt">Inactive</span>'?></td><td><a class="btn gray" href="?action=edit&id=<?=$r['id']?>">Edit</a> <form class="inline" method="post"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn red" data-confirm="Delete this promo?">Delete</button></form></td></tr><?php endforeach;?></table></div>
<?php endif; require __DIR__.'/includes/footer.php';
