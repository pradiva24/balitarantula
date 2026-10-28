<?php
require_once __DIR__.'/includes/auth.php';

$file=__DIR__.'/../menu-settings.json';
$defaults=[
 ['label'=>'Home','url'=>'/','enabled'=>1,'button'=>0,'ticket'=>0],
 ['label'=>'Adventures','url'=>'/packages.php','enabled'=>1,'button'=>0,'ticket'=>0],
 ['label'=>'About Us','url'=>'/about.php','enabled'=>1,'button'=>0,'ticket'=>0],
 ['label'=>'Travel Guide','url'=>'/blog.php','enabled'=>1,'button'=>0,'ticket'=>0],
 ['label'=>'Gallery','url'=>'/#gallery','enabled'=>1,'button'=>0,'ticket'=>0],
 ['label'=>'Reviews','url'=>'/#reviews','enabled'=>1,'button'=>0,'ticket'=>0],
 ['label'=>'Check Ticket','url'=>'/my-booking','enabled'=>1,'button'=>0,'ticket'=>1],
 ['label'=>'Book Now','url'=>'/booking.php','enabled'=>1,'button'=>1,'ticket'=>0]
];
$menus=$defaults;
if(is_file($file)){
  $x=json_decode(@file_get_contents($file),true);
  if(is_array($x)) $menus=$x;
}

/* Make the existing header ticket action editable from this admin page. */
$ticketFound=false;
foreach($menus as &$menuItem){
  $labelCheck=(string)($menuItem['label']??'');
  if(!empty($menuItem['ticket']) || preg_match('/check\s*ticket|view\s*(my\s*)?ticket|my\s*booking/i',$labelCheck)){
    $menuItem['ticket']=1;
    $ticketFound=true;
  } else {
    $menuItem['ticket']=0;
  }
}
unset($menuItem);

if(!$ticketFound){
  $ticketItem=['label'=>'Check Ticket','url'=>'/my-booking','enabled'=>1,'button'=>0,'ticket'=>1];
  $insertAt=count($menus);
  foreach($menus as $i=>$menuItem){
    if(!empty($menuItem['button']) || preg_match('/book|reserve|booking/i',(string)($menuItem['label']??''))){
      $insertAt=$i;
      break;
    }
  }
  array_splice($menus,$insertAt,0,[$ticketItem]);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  admin_verify_csrf();
  $payload=json_decode($_POST['menu_json']??'[]',true);
  if(!is_array($payload)) $payload=[];
  $clean=[];
  foreach($payload as $m){
    if(!is_array($m)) continue;
    $label=trim((string)($m['label']??''));
    $url=trim((string)($m['url']??''));
    if($label==='') continue;
    if($url==='') $url='#';
    // Allow relative paths, anchors, mailto/tel, and normal HTTPS/HTTP links.
    if(!preg_match('~^(https?://|mailto:|tel:|#|/)~i',$url)) $url='/'.$url;
    $clean[]=[
      'label'=>mb_substr($label,0,80),
      'url'=>mb_substr($url,0,500),
      'enabled'=>!empty($m['enabled'])?1:0,
      'button'=>!empty($m['button'])?1:0,
      'ticket'=>!empty($m['ticket'])?1:0
    ];
  }
  file_put_contents($file,json_encode($clean,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),LOCK_EX);
  header('Location: menu-manager.php?saved=1'); exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Navigation Menu</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="assets/css/admin.css">
<style>
*{box-sizing:border-box}.menu-page{max-width:1120px;margin:24px auto;padding:0 18px}.intro{color:#667085;margin-top:-6px}.toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;margin:18px 0}.toolbar-left{display:flex;gap:8px;flex-wrap:wrap}.toolbar button,.save{border:0;border-radius:8px;padding:11px 15px;font-weight:700;cursor:pointer}.add{background:#eef6ff;color:#185a9d}.save{background:#198754;color:#fff}.notice{padding:12px 14px;background:#eaf7ee;border:1px solid #b9dfc4;border-radius:9px;color:#17663a;margin-bottom:16px}.menu-list{min-height:20px}.menu-row{display:grid;grid-template-columns:38px 44px minmax(150px,1fr) minmax(180px,1.3fr) 78px 78px 78px 42px;gap:9px;align-items:center;background:#fff;border:1px solid #e3e6e8;border-radius:10px;padding:10px;margin-bottom:8px;box-shadow:0 1px 2px rgba(0,0,0,.03)}.handle{cursor:grab;text-align:center;font-size:21px;color:#777;user-select:none}.handle:active{cursor:grabbing}.menu-row input[type=text]{width:100%;padding:9px;border:1px solid #d8dce0;border-radius:7px;font:inherit}.toggle,.button-check,.ticket-check{display:flex;align-items:center;justify-content:center}.status{font-size:12px;color:#667085;text-align:center}.remove{width:34px;height:34px;border:0;border-radius:7px;background:#fff0f0;color:#b42318;cursor:pointer;font-weight:800;font-size:18px}.dragging{opacity:.45}.drop-target{outline:2px dashed #198754;outline-offset:2px}.empty{padding:28px;text-align:center;color:#777;border:1px dashed #bbb;border-radius:9px}.legend{font-size:12px;color:#667085;margin:10px 0 6px}.menu-row.head{background:#f9fafb;color:#667085;font-size:11px;font-weight:700;border:0;box-shadow:none;cursor:default}.menu-row.head .handle{font-size:11px;cursor:default}.mobile-note{display:none}
@media(max-width:760px){.menu-row{grid-template-columns:34px 34px 1fr 42px}.menu-row input.url{grid-column:3}.menu-row .status,.menu-row .button-check,.menu-row .ticket-check{display:none}.menu-row.head{display:none}.mobile-note{display:block}.toolbar{align-items:stretch;flex-direction:column}.toolbar .save{width:100%}}
</style>
</head>
<body>
<?php include __DIR__.'/includes/header.php'; ?>
<main class="menu-page">
  <h1>Navigation Menu</h1>
  <p class="intro">Tambah, hapus, aktif/nonaktifkan, ubah nama dan URL menu tanpa mengedit kode. Drag <b>☷</b> untuk mengatur urutan.</p>
  <?php if(isset($_GET['saved'])):?><div class="notice">Navigation menu berhasil disimpan dan langsung digunakan di website.</div><?php endif;?>

  <div class="toolbar">
    <div class="toolbar-left"><button type="button" class="add" id="add">＋ Add Menu</button></div>
    <span id="count" class="small"></span>
  </div>

  <div class="menu-row head" aria-hidden="true">
    <div>ORDER</div><div>ON</div><div>MENU NAME</div><div>URL</div><div>BUTTON</div><div>TICKET</div><div>STATUS</div><div></div>
  </div>
  <form method="post" id="menuForm">
    <input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>">
    <input type="hidden" name="menu_json" id="menuJson">
    <div class="menu-list" id="menuList">
    <?php foreach($menus as $m): ?>
      <div class="menu-row" draggable="true">
        <div class="handle" title="Drag to reorder">☷</div>
        <div class="toggle"><input class="enabled" type="checkbox" <?=$m['enabled']??1?'checked':''?> title="Active"></div>
        <input class="label" type="text" value="<?=admin_e($m['label']??'')?>" placeholder="Menu name" maxlength="80">
        <input class="url" type="text" value="<?=admin_e($m['url']??'#')?>" placeholder="/page/your-page-slug or https://..." maxlength="500">
        <div class="button-check"><input class="is-button" type="checkbox" <?=!empty($m['button'])?'checked':''?> title="Style as button"></div>
        <div class="ticket-check"><input class="is-ticket" type="checkbox" <?=!empty($m['ticket'])?'checked':''?> title="Editable Check Ticket item"></div>
        <div class="status"></div>
        <button type="button" class="remove" title="Remove menu">×</button>
      </div>
    <?php endforeach;?>
    </div>
    <div class="mobile-note legend">Di mobile, geser dengan tombol drag/urutan pada perangkat yang mendukung drag. Anda tetap bisa menambah atau menghapus menu.</div>
    <div class="toolbar"><button type="submit" class="save">Save Menu Changes</button></div>
  </form>
</main>
<script>
const list=document.getElementById('menuList');
const count=document.getElementById('count');
let drag=null;
const rows=()=>[...list.querySelectorAll('.menu-row:not(.head)')];
function refresh(){
  const rs=rows();
  count.textContent=rs.length+' menu';
  rs.forEach((r,i)=>{
    const active=r.querySelector('.enabled').checked;
    r.querySelector('.status').textContent=active?'Active':'Hidden';
    r.dataset.order=i+1;
  });
}
function bindRow(row){
  row.addEventListener('dragstart',e=>{drag=row;row.classList.add('dragging');e.dataTransfer.effectAllowed='move';});
  row.addEventListener('dragend',()=>{drag=null;row.classList.remove('dragging');rows().forEach(x=>x.classList.remove('drop-target'));refresh();});
  row.addEventListener('dragover',e=>{
    e.preventDefault(); if(!drag||drag===row)return;
    row.classList.add('drop-target');
    const rect=row.getBoundingClientRect();
    if(e.clientY < rect.top+rect.height/2) list.insertBefore(drag,row); else list.insertBefore(drag,row.nextSibling);
  });
  row.addEventListener('dragleave',()=>row.classList.remove('drop-target'));
  row.querySelector('.remove').addEventListener('click',()=>{if(confirm('Hapus menu ini?')){row.remove();refresh();}});
  row.querySelector('.enabled').addEventListener('change',refresh);
}
rows().forEach(bindRow);
document.getElementById('add').addEventListener('click',()=>{
  const row=document.createElement('div');
  row.className='menu-row';row.draggable=true;
  row.innerHTML='<div class="handle" title="Drag to reorder">☷</div><div class="toggle"><input class="enabled" type="checkbox" checked title="Active"></div><input class="label" type="text" placeholder="Menu name" maxlength="80"><input class="url" type="text" placeholder="/page/your-page-slug or https://..." maxlength="500"><div class="button-check"><input class="is-button" type="checkbox" title="Style as button"></div><div class="ticket-check"><input class="is-ticket" type="checkbox" title="Editable Check Ticket item"></div><div class="status">Active</div><button type="button" class="remove" title="Remove menu">×</button>';
  list.appendChild(row);bindRow(row);row.querySelector('.label').focus();refresh();
});
document.getElementById('menuForm').addEventListener('submit',()=>{
  const data=rows().map(r=>({
    label:r.querySelector('.label').value.trim(),
    url:r.querySelector('.url').value.trim(),
    enabled:r.querySelector('.enabled').checked?1:0,
    button:r.querySelector('.is-button').checked?1:0,
    ticket:r.querySelector('.is-ticket').checked?1:0
  })).filter(x=>x.label);
  document.getElementById('menuJson').value=JSON.stringify(data);
});
refresh();
</script>
</body></html>
