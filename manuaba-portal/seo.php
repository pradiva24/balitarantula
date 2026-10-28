<?php
require __DIR__.'/includes/auth.php';

$db = db();
$siteName = trim((string)site_brand_name());
$siteUrl = rtrim((string)setting('site_url', BASE_URL), '/');

$static = [
    'home'=>['Homepage','/','index,follow', $siteName.' | ATV, Rafting, Swing & Bali Experiences','Discover '.$siteName.' with exciting Bali adventures, activities and experiences.'],
    'about'=>['About Us','/about','index,follow','About Us | '.$siteName,'Learn about '.$siteName.', our team, values, service and Bali experiences.'],
    'packages'=>['Packages','/packages','index,follow','Bali Adventure Packages | '.$siteName,'Explore Bali adventure packages and activities with '.$siteName.'.'],
    'gallery'=>['Gallery','/gallery','index,follow','Adventure Gallery | '.$siteName,'Explore '.$siteName.' through our Bali adventure and travel gallery.'],
    'contact'=>['Contact Us','/contact-us','index,follow','Contact Us | '.$siteName,'Contact '.$siteName.' for bookings, directions and travel assistance in Bali.'],
    'review'=>['Reviews','/review','index,follow','Guest Reviews | '.$siteName,'Read guest reviews and traveler experiences with '.$siteName.'.'],
    'blog'=>['Blog / Travel Guide','/blog','index,follow','Bali Travel Guide | '.$siteName,'Read Bali travel tips, activity guides and useful planning advice from '.$siteName.'.'],
    'booking'=>['Booking','/booking','noindex,follow','Book Your Adventure | '.$siteName,'Complete your booking with '.$siteName.'.'],
    'booking-success'=>['Booking Success','/booking-success','noindex,nofollow','Booking Received | '.$siteName,'Your booking has been received.'],
    'booking-confirmation'=>['Booking Confirmation','/booking-confirmation','noindex,nofollow','Booking Confirmation | '.$siteName,'Booking confirmation and payment information.'],
    'my-booking'=>['My Booking','/my-booking','noindex,follow','Check My Booking | '.$siteName,'Check your booking status and reservation details.'],
    'payment'=>['Payment','/payment','noindex,nofollow','Payment | '.$siteName,'Complete payment for your booking.'],
    'midtrans-checkout'=>['Midtrans Checkout','/midtrans-checkout','noindex,nofollow','Secure Payment | '.$siteName,'Complete your booking payment securely.'],
    '404'=>['404','/404','noindex,nofollow','Page Not Found | '.$siteName,'The requested page could not be found.'],
    'sitemap'=>['Sitemap','/sitemap','noindex,nofollow','Sitemap | '.$siteName,'XML sitemap for '.$siteName.'.'],
];

function seo_admin_key_valid(string $key): bool {
    return (bool)preg_match('/^[a-z0-9:_-]{1,191}$/', $key);
}
function seo_admin_label(string $key, array $static): string {
    if(isset($static[$key])) return $static[$key][0];
    if(strpos($key,'page:')===0) return 'Page: '.substr($key,5);
    return $key;
}
function seo_admin_insert(PDO $db,string $key,string $robots='index,follow',string $title='',string $desc='',string $canonical=''): void {
    $st=$db->prepare('SELECT id FROM seo_meta WHERE page_key=? LIMIT 1');
    $st->execute([$key]);
    if($st->fetchColumn()) return;
    $db->prepare('INSERT INTO seo_meta(page_key,seo_title,meta_description,canonical_url,og_title,og_description,robots) VALUES(?,?,?,?,?,?,?)')
       ->execute([$key,$title,$desc,$canonical,$title,$desc,$robots]);
}

foreach($static as $key=>$def){
    $canonical=$siteUrl.($def[1]==='/'?'':$def[1]);
    seo_admin_insert($db,$key,$def[2],$def[3],$def[4],$canonical);
}
try{
    foreach($db->query("SELECT slug FROM pages WHERE status=1 ORDER BY title")->fetchAll(PDO::FETCH_COLUMN) as $slug){
        seo_admin_insert($db,'page:'.trim((string)$slug),'index,follow');
    }
}catch(Throwable $e){}

$key=trim((string)($_GET['key']??'home'));
if(!seo_admin_key_valid($key)) $key='home';

if($_SERVER['REQUEST_METHOD']==='POST'){
    admin_verify_csrf();
    $key=trim((string)($_POST['page_key']??'home'));
    if(!seo_admin_key_valid($key)) $key='home';
    $data=[
        trim((string)($_POST['seo_title']??'')),
        trim((string)($_POST['meta_description']??'')),
        trim((string)($_POST['focus_keyword']??'')),
        trim((string)($_POST['canonical_url']??'')),
        trim((string)($_POST['og_title']??'')),
        trim((string)($_POST['og_description']??'')),
        trim((string)($_POST['og_image']??'')),
        strtolower(trim((string)($_POST['robots']??'index,follow'))),
        trim((string)($_POST['schema_json']??'')),
    ];
    if(!in_array($data[7],['index,follow','index,nofollow','noindex,follow','noindex,nofollow'],true)){
        admin_flash('error','Invalid robots value.'); header('Location: seo.php?key='.urlencode($key)); exit;
    }
    if(!seo_json_is_valid($data[8])){
        admin_flash('error','Schema JSON-LD is not valid JSON.'); header('Location: seo.php?key='.urlencode($key)); exit;
    }
    $st=$db->prepare('SELECT * FROM seo_meta WHERE page_key=? LIMIT 1');
    $st->execute([$key]); $old=$st->fetch(PDO::FETCH_ASSOC)?:null;
    if(admin_media_has_selection('og_image_upload') || !empty($_FILES['og_image_upload']['name'])){
        try{
            $newImage=admin_upload('og_image_upload','seo',null);
            $oldImage=(string)($old['og_image']??'');
            if($oldImage!==''&&!preg_match('~^https?://~i',$oldImage)&&strpos($oldImage,'//')!==0) admin_delete_image($oldImage);
            $data[6]=$newImage;
        }catch(Throwable $e){
            admin_flash('error','OG image upload failed: '.$e->getMessage());
            header('Location: seo.php?key='.urlencode($key)); exit;
        }
    }
    if($data[4]==='') $data[4]=$data[0];
    if($data[5]==='') $data[5]=$data[1];
    if($old){
        $db->prepare('UPDATE seo_meta SET seo_title=?,meta_description=?,focus_keyword=?,canonical_url=?,og_title=?,og_description=?,og_image=?,robots=?,schema_json=? WHERE id=?')
          ->execute([$data[0],$data[1],$data[2],$data[3],$data[4],$data[5],$data[6],$data[7],$data[8],(int)$old['id']]);
    }else{
        $db->prepare('INSERT INTO seo_meta(seo_title,meta_description,focus_keyword,canonical_url,og_title,og_description,og_image,robots,schema_json,page_key) VALUES(?,?,?,?,?,?,?,?,?,?)')
          ->execute([$data[0],$data[1],$data[2],$data[3],$data[4],$data[5],$data[6],$data[7],$data[8],$key]);
    }
    admin_flash('success','SEO metadata saved for '.seo_admin_label($key,$static).'.');
    header('Location: seo.php?key='.urlencode($key)); exit;
}

$st=$db->prepare('SELECT * FROM seo_meta WHERE page_key=? LIMIT 1');
$st->execute([$key]); $x=$st->fetch(PDO::FETCH_ASSOC)?:[];
$keys=[]; foreach($static as $k=>$d) $keys[$k]=seo_admin_label($k,$static);
try{
    foreach($db->query("SELECT slug FROM pages WHERE status=1 ORDER BY title")->fetchAll(PDO::FETCH_COLUMN) as $slug){
        $k='page:'.trim((string)$slug); $keys[$k]=seo_admin_label($k,$static);
    }
}catch(Throwable $e){}
if(!isset($keys[$key])) $keys[$key]=seo_admin_label($key,$static);

$seoTitle=(string)($x['seo_title']??'');
$metaDescription=(string)($x['meta_description']??'');
$focus=(string)($x['focus_keyword']??'');
$canonical=(string)($x['canonical_url']??'');
$ogTitle=(string)($x['og_title']??'');
$ogDescription=(string)($x['og_description']??'');
$ogImage=(string)($x['og_image']??'');
$robots=(string)($x['robots']??'index,follow');
$schema=(string)($x['schema_json']??'');

$page_title='SEO Manager';
require __DIR__.'/includes/header.php';
?>
<style>
.seo-shell{display:grid;grid-template-columns:250px minmax(0,1fr);gap:18px}.seo-nav,.seo-main{background:#fff;border:1px solid #e4e9e6;border-radius:12px}.seo-nav{padding:10px;max-height:760px;overflow:auto}.seo-group{padding:10px 9px 5px;font-size:10px;font-weight:800;letter-spacing:1.2px;color:#78847c;text-transform:uppercase}.seo-link{display:block;padding:8px 9px;border-radius:7px;text-decoration:none;color:#344139;font-size:12px;margin:2px 0}.seo-link:hover{background:#f3f7f4}.seo-link.active{background:#e9f5ed;color:#176442;font-weight:800}.seo-main{padding:20px}.seo-main h2{font-size:20px;margin:0 0 4px}.seo-help{font-size:10px;line-height:1.5;color:#7a867e}.seo-grid{display:grid;grid-template-columns:1fr 1fr;gap:13px}.seo-field{display:flex;flex-direction:column;gap:5px}.seo-field.full{grid-column:1/-1}.seo-field label{font-size:12px;font-weight:800;color:#344139}.seo-field input,.seo-field select,.seo-field textarea{width:100%;box-sizing:border-box;border:1px solid #d9e1dc;border-radius:8px;padding:9px 10px;font:inherit;font-size:13px;background:#fff}.seo-field textarea{min-height:88px;resize:vertical}.seo-field textarea.schema{min-height:260px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px}.seo-count{float:right;color:#7b8780;font-weight:500}.seo-actions{display:flex;gap:8px;margin-top:15px}.seo-preview{margin-top:18px;background:#f8faf9;border:1px solid #e3e8e5;border-radius:10px;padding:15px}.serp-title{font-size:18px;color:#1a5bda}.serp-url{font-size:11px;color:#36764d;margin:3px 0}.serp-desc{font-size:12px;color:#5b665f;line-height:1.5}.og-card{max-width:650px;margin-top:13px;border:1px solid #dce4df;border-radius:8px;overflow:hidden;background:#fff}.og-card img{width:100%;height:190px;object-fit:cover;display:block;background:#e7ece9}.og-body{padding:10px}.og-title{font-size:14px;font-weight:800}.og-desc{font-size:11px;color:#6c7770;margin-top:3px}.seo-analysis{margin:0 0 18px;padding:16px;border:1px solid #e0e7e2;border-radius:12px;background:#fbfdfb}.seo-analysis-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}.seo-score{font-size:13px;font-weight:800;padding:7px 11px;border-radius:999px;background:#eef2ef;color:#526057}.seo-score.good{background:#dcfce7;color:#166534}.seo-score.warn{background:#fef3c7;color:#92400e}.seo-score.bad{background:#fee2e2;color:#991b1b}.seo-analysis-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.seo-check{display:flex;gap:9px;align-items:flex-start;padding:10px;border:1px solid #e4eae6;background:#fff;border-radius:9px;min-width:0}.seo-check .seo-dot{width:10px;height:10px;border-radius:50%;flex:0 0 10px;margin-top:4px;background:#9ca3af}.seo-check.good{border-color:#bbf7d0}.seo-check.good .seo-dot{background:#16a34a}.seo-check.warn{border-color:#fde68a}.seo-check.warn .seo-dot{background:#d97706}.seo-check.bad{border-color:#fecaca}.seo-check.bad .seo-dot{background:#dc2626}.seo-check-title{font-size:12px;font-weight:800;color:#26352b}.seo-check-detail{font-size:11px;line-height:1.5;color:#68756c;margin-top:3px;overflow-wrap:anywhere}.seo-analysis-note{font-size:11px;color:#6c7770;margin-top:10px;line-height:1.5}@media(max-width:900px){.seo-shell{grid-template-columns:1fr}.seo-nav{max-height:none}.seo-grid{grid-template-columns:1fr}.seo-field.full{grid-column:auto}.seo-analysis-grid{grid-template-columns:1fr}}
</style>
<div class="seo-shell">
<aside class="seo-nav">
<div class="seo-group">Core Pages</div>
<?php foreach($static as $k=>$d): ?><a class="seo-link <?=$key===$k?'active':''?>" href="?key=<?=urlencode($k)?>"><?=admin_e($d[0])?></a><?php endforeach; ?>
<?php
$custom=[]; foreach($keys as $k=>$v) if(strpos($k,'page:')===0) $custom[$k]=$v;
if($custom): ?><div class="seo-group">Custom Pages</div><?php foreach($custom as $k=>$v): ?><a class="seo-link <?=$key===$k?'active':''?>" href="?key=<?=urlencode($k)?>"><?=admin_e($v)?></a><?php endforeach; endif; ?>
</aside>
<section class="seo-main">
<h2><?=admin_e($keys[$key]??$key)?></h2>
<div class="seo-help" style="margin-bottom:17px">Central SEO metadata for this public URL. Package detail SEO remains editable from <strong>Packages</strong>, and blog article SEO remains editable from <strong>Blog</strong>; their existing SEO fields are preserved.</div>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="page_key" value="<?=admin_e($key)?>">
<div class="seo-grid">
<div class="seo-field full"><label>SEO Title <span class="seo-count" id="titleCount"><?=strlen($seoTitle)?></span></label><input id="seoTitle" maxlength="255" name="seo_title" value="<?=admin_e($seoTitle)?>"><div class="seo-help">Recommended around 50–60 characters. Keep the main topic and brand clear.</div></div>
<div class="seo-field full"><label>Meta Description <span class="seo-count" id="descCount"><?=strlen($metaDescription)?></span></label><textarea id="metaDescription" maxlength="320"><?=admin_e($metaDescription)?></textarea><input type="hidden" id="metaDescriptionStore" name="meta_description" value="<?=admin_e($metaDescription)?>"><div class="seo-help">Recommended around 140–160 characters. Write naturally for searchers.</div></div>
<div class="seo-field"><label>Focus Keyword</label><input name="focus_keyword" value="<?=admin_e($focus)?>"><div class="seo-help">Primary search phrase for this page.</div></div>
<div class="seo-field"><label>Robots</label><select name="robots"><?php foreach(['index,follow','index,nofollow','noindex,follow','noindex,nofollow'] as $o): ?><option value="<?=$o?>" <?=$robots===$o?'selected':''?>><?=$o?></option><?php endforeach; ?></select></div>
<div class="seo-field full"><label>Canonical URL</label><input name="canonical_url" value="<?=admin_e($canonical)?>" placeholder="<?=admin_e($siteUrl)?>/clean-url"><div class="seo-help">Use the preferred clean absolute URL. The frontend removes query strings from canonical output.</div></div>
<div class="seo-field"><label>Open Graph Title</label><input name="og_title" value="<?=admin_e($ogTitle)?>"></div>
<div class="seo-field"><label>Open Graph Description</label><textarea name="og_description"><?=admin_e($ogDescription)?></textarea></div>
<div class="seo-field"><label>Open Graph Image URL / Path</label><input name="og_image" value="<?=admin_e($ogImage)?>" placeholder="uploads/seo/image.webp or https://..."></div>
<div class="seo-field"><label>Upload OG Image</label><input type="file" name="og_image_upload" accept="image/jpeg,image/png,image/webp,image/gif"><div class="seo-help">The same OG image is used for Twitter Card metadata.</div></div>
<div class="seo-field full"><label>Schema JSON-LD</label><textarea class="schema" name="schema_json"><?=admin_e($schema)?></textarea><div class="seo-help">Optional valid JSON-LD. Leave blank to use the site's generated schema.</div></div>
</div>
<div class="seo-actions"><button class="btn" type="submit">Save SEO Metadata</button><a class="btn gray" target="_blank" rel="noopener" href="<?=e(seo_normalize_canonical($canonical?:$siteUrl.'/'))?>">Open URL</a></div>
</form>
<div class="seo-preview">
<strong style="font-size:12px">Search & Social Preview</strong>
<div style="margin-top:10px"><div class="serp-title" id="pTitle"><?=e($seoTitle?:'Your SEO Title')?></div><div class="serp-url" id="pUrl"><?=e($canonical?:$siteUrl.'/')?></div><div class="serp-desc" id="pDesc"><?=e($metaDescription?:'Your meta description preview will appear here.')?></div></div>
<div class="og-card">
<?php if($ogImage): ?><img id="pImage" src="<?=e(seo_absolute_asset($ogImage))?>" alt=""><?php else: ?><div id="pImage" style="height:190px;display:grid;place-items:center;color:#7b877f;font-size:12px">OG image preview</div><?php endif; ?>
<div class="og-body"><div class="og-title" id="pOgTitle"><?=e($ogTitle?:$seoTitle?:'Social Share Title')?></div><div class="og-desc" id="pOgDesc"><?=e($ogDescription?:$metaDescription?:'Social share description')?></div></div>
</div>
</div>
<?php
// Render a server-side baseline immediately so the panel never remains stuck on a JS loading placeholder.
$initialChecks=[];
$tl=strlen($seoTitle); $dl=strlen($metaDescription); $kw=mb_strtolower($focus,'UTF-8');
$initialChecks[]=[($tl>=30&&$tl<=60?'good':(($tl>=20&&$tl<=70)?'warn':'bad')),'SEO title',$tl.' karakter. Target 30–60 karakter.'];
$initialChecks[]= [($dl>=120&&$dl<=160?'good':(($dl>=70&&$dl<=200)?'warn':'bad')),'Meta description',$dl.' karakter. Target sekitar 120–160 karakter.'];
$initialChecks[]=[$kw!==''?(mb_stripos($seoTitle,$focus)!==false?'good':'bad'):'warn','Focus keyword di title',$kw===''?'Focus keyword belum diisi.':(mb_stripos($seoTitle,$focus)!==false?'Keyword ditemukan.':'Keyword belum ditemukan pada title.')];
$initialChecks[]=($canonical!==''&&preg_match('~^https?://~i',$canonical))?['good','Canonical URL','Canonical URL terisi. Pastikan URL benar.']:['bad','Canonical URL','Canonical URL kosong atau tidak valid.'];
$initialChecks[]=($ogTitle!==''||$seoTitle!=='')?['good','Open Graph title','Judul social sharing tersedia.']:['bad','Open Graph title','Tambahkan judul Open Graph.'];
$initialChecks[]=($ogDescription!==''||$metaDescription!=='')?['good','Open Graph description','Deskripsi social sharing tersedia.']:['warn','Open Graph description','Tambahkan deskripsi social sharing.'];
$initialChecks[]=$ogImage!==''?['good','Open Graph image','Gambar social sharing ditentukan.']:['warn','Open Graph image','Tambahkan gambar OG landscape 1200×630 px.'];
$initialChecks[]=strpos($robots,'noindex')===0?['bad','Indexability','Robots disetel noindex.']:['good','Indexability','Robots mengizinkan indexing.'];
$initialChecks[]=($schema!=='')?(seo_json_is_valid($schema)?['good','Schema JSON-LD','JSON valid.']:['bad','Schema JSON-LD','JSON-LD tidak valid.']):['warn','Schema JSON-LD','Schema custom kosong; schema default mungkin digunakan.'];
$ig=count(array_filter($initialChecks,fn($v)=>$v[0]==='good')); $ib=count(array_filter($initialChecks,fn($v)=>$v[0]==='bad')); $iw=count($initialChecks)-$ig-$ib;
$iclass=$ib?'bad':($iw?'warn':'good');
?>
<div class="seo-analysis" id="seoAnalysis" aria-live="polite">
 <div class="seo-analysis-head"><div><strong style="font-size:14px">SEO Content Analysis</strong><div class="seo-help">Analisis metadata awal dirender oleh server; analisis konten halaman akan ditambahkan jika JavaScript berhasil berjalan.</div></div><span class="seo-score <?=$iclass?>" id="seoOverall"><?=$ig?> baik · <?=$iw?> perlu ditingkatkan · <?=$ib?> masalah</span></div>
 <div class="seo-analysis-grid" id="seoChecks"><?php foreach($initialChecks as $check): ?><div class="seo-check <?=admin_e($check[0])?>"><span class="seo-dot"></span><div><div class="seo-check-title"><?=admin_e($check[1])?></div><div class="seo-check-detail"><?=admin_e($check[2])?></div></div></div><?php endforeach; ?></div>
 <div class="seo-analysis-note" id="seoAnalysisNote">Hasil metadata ditampilkan langsung dari server. Pemeriksaan H1, konten, gambar ALT dan internal link memerlukan JavaScript browser.</div>
</div>
</section>
</div>
<script>
(function(){
 const checksBox=document.getElementById('seoChecks'),scoreBox=document.getElementById('seoOverall'),noteBox=document.getElementById('seoAnalysisNote');
 const title=document.getElementById('seoTitle'),desc=document.getElementById('metaDescription'),keyword=document.querySelector('[name="focus_keyword"]'),canonical=document.querySelector('[name="canonical_url"]'),ogTitle=document.querySelector('[name="og_title"]'),ogDesc=document.querySelector('[name="og_description"]'),ogImage=document.querySelector('[name="og_image"]'),robots=document.querySelector('[name="robots"]'),schema=document.querySelector('[name="schema_json"]');
 const pageUrl=<?=json_encode(($siteUrl.($static[$key][1]??('/'.substr($key,5)))) , JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>;
 let pageData=null, crawlMessage='';
 const norm=s=>(s||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();
 function add(arr,status,name,detail){arr.push({status,name,detail});}
 function analyze(){
  const a=[],t=title.value.trim(),d=desc.value.trim(),k=norm(keyword.value),c=canonical.value.trim(),oi=ogImage.value.trim(),ot=ogTitle.value.trim(),od=ogDesc.value.trim(),r=robots.value,sc=schema.value.trim();
  const titleLen=t.length; add(a,titleLen>=30&&titleLen<=60?'good':(titleLen>=20&&titleLen<=70?'warn':'bad'),'SEO title',titleLen+' karakter. Target 30–60 karakter; pastikan topik utama muncul di awal.');
  add(a,d.length>=120&&d.length<=160?'good':(d.length>=70&&d.length<=200?'warn':'bad'),'Meta description',d.length+' karakter. Target sekitar 120–160 karakter dan menarik untuk diklik.');
  add(a,k?(norm(t).includes(k)?'good':'bad'):'warn','Focus keyword di title',k?(norm(t).includes(k)?'Keyword ditemukan di SEO title.':'Keyword belum ditemukan di SEO title.'):'Isi focus keyword untuk analisis yang lebih akurat.');
  add(a,k?(norm(d).includes(k)?'good':'warn'):'warn','Focus keyword di description',k?(norm(d).includes(k)?'Keyword ditemukan di meta description.':'Sebaiknya masukkan keyword secara alami ke meta description.'):'Focus keyword belum diisi.');
  add(a,c&&/^https?:\/\//i.test(c)?'good':'bad','Canonical URL',c?'Canonical URL sudah diisi. Pastikan mengarah ke URL utama yang benar.':'Canonical URL kosong atau bukan URL absolut.');
  add(a,(ot||t)?'good':'bad','Open Graph title',(ot||t)?'Judul untuk social sharing tersedia.':'Tambahkan judul Open Graph.');
  add(a,(od||d)?'good':'warn','Open Graph description',(od||d)?'Deskripsi social sharing tersedia.':'Tambahkan deskripsi untuk preview saat dibagikan.');
  add(a,oi?'good':'warn','Open Graph image',oi?'Gambar social sharing sudah ditentukan.':'Tambahkan gambar OG, idealnya landscape 1200×630 px.');
  add(a,r.startsWith('noindex')?'bad':'good','Indexability',r.startsWith('noindex')?'Halaman disetel noindex sehingga tidak ditujukan untuk muncul di hasil pencarian.':'Robots mengizinkan indexing. Pastikan ini memang halaman publik.');
  add(a,sc?(function(){try{JSON.parse(sc);return 'good'}catch(e){return 'bad'}})():'warn','Schema JSON-LD',sc?'Schema berisi JSON valid. Validitas tipe schema perlu diperiksa lebih lanjut.':'Schema custom kosong; schema default website mungkin tetap digunakan.');
  if(pageData){
   const h1=pageData.h1||[],words=pageData.words||0,imgs=pageData.images||[],badAlt=imgs.filter(x=>!x.alt.trim()).length,links=pageData.internalLinks||0;
   add(a,h1.length===1?'good':(h1.length===0?'bad':'warn'),'H1 heading',h1.length===1?'Ditemukan satu H1: '+h1[0].slice(0,100):h1.length===0?'Tidak ditemukan H1. Tambahkan satu heading utama yang menjelaskan halaman.':'Ditemukan '+h1.length+' H1. Idealnya gunakan satu H1 utama.');
   add(a,words>=300?'good':(words>=150?'warn':'bad'),'Panjang konten',words+' kata teks terdeteksi. Target praktis 300+ kata untuk halaman informatif; halaman transaksi bisa lebih singkat.');
   add(a,imgs.length===0?'warn':(badAlt===0?'good':'bad'),'Image alt text',imgs.length===0?'Tidak ada gambar terdeteksi pada konten halaman.':(badAlt===0?imgs.length+' gambar ditemukan dan semuanya memiliki alt.':badAlt+' dari '+imgs.length+' gambar belum memiliki alt text.')); 
   add(a,links>0?'good':'warn','Internal links',links+' tautan internal ditemukan. Tambahkan tautan kontekstual ke halaman relevan bila sesuai.');
   add(a,k?(h1.some(x=>norm(x).includes(k))?'good':'warn'):'warn','Focus keyword di H1',k?(h1.some(x=>norm(x).includes(k))?'Keyword ditemukan di H1.':'Masukkan keyword ke H1 jika terdengar alami.'):'Isi focus keyword untuk mengecek H1.');
   if(pageData.pageTitle) add(a,pageData.pageTitle.length>=30&&pageData.pageTitle.length<=60?'good':'warn','Title aktual halaman','Title HTML publik: '+pageData.pageTitle.length+' karakter. '+pageData.pageTitle.slice(0,120));
   noteBox.textContent=crawlMessage||'Analisis berdasarkan halaman publik yang berhasil dibaca dan metadata pada form. Hasil ini adalah panduan, bukan jaminan ranking Google.';
  } else {
   add(a,'warn','Konten, H1, gambar, dan tautan','Konten publik belum berhasil dibaca. Periksa URL, akses halaman, atau cache/server. Pemeriksaan metadata tetap aktif.');
   noteBox.textContent=crawlMessage||'Belum dapat membaca konten publik. Pemeriksaan title, description, keyword, canonical, OG, robots, dan schema tetap aktif.';
  }
  const good=a.filter(x=>x.status==='good').length,bad=a.filter(x=>x.status==='bad').length,warn=a.length-good-bad;
  scoreBox.textContent=good+' baik · '+warn+' perlu ditingkatkan · '+bad+' masalah';scoreBox.className='seo-score '+(bad?'bad':(warn?'warn':'good'));
  checksBox.innerHTML='';a.forEach(x=>{const el=document.createElement('div');el.className='seo-check '+x.status;const dot=document.createElement('span');dot.className='seo-dot';const body=document.createElement('div'),name=document.createElement('div'),detail=document.createElement('div');name.className='seo-check-title';detail.className='seo-check-detail';name.textContent=x.name;detail.textContent=x.detail;body.append(name,detail);el.append(dot,body);checksBox.appendChild(el)});
 }
 async function crawl(){
  try{const u=new URL(pageUrl,location.href); if(u.origin!==location.origin){crawlMessage='URL halaman berbeda domain dengan panel admin; browser membatasi analisis konten otomatis.';analyze();return;}
   const res=await fetch(u.href,{credentials:'same-origin',cache:'no-store'});if(!res.ok)throw new Error('Halaman merespons HTTP '+res.status+'.');const html=await res.text(),doc=new DOMParser().parseFromString(html,'text/html');
   const body=doc.body;const text=(body?.innerText||body?.textContent||'').replace(/\s+/g,' ').trim();const images=Array.from(doc.images).map(i=>({alt:i.getAttribute('alt')||''}));const internalLinks=Array.from(doc.querySelectorAll('a[href]')).filter(a=>{try{return new URL(a.href,location.href).origin===location.origin}catch(e){return false}}).length;
   pageData={pageTitle:doc.title||'',h1:Array.from(doc.querySelectorAll('h1')).map(h=>(h.innerText||h.textContent||'').trim()).filter(Boolean),words:(text.match(/\b[\p{L}\p{N}]+\b/gu)||[]).length,images,internalLinks};crawlMessage='Halaman publik berhasil dianalisis.';
  }catch(e){pageData=null;crawlMessage='Konten halaman tidak dapat dibaca otomatis: '+e.message+' Metadata tetap dianalisis.';}analyze();
 }
 [title,desc,keyword,canonical,ogTitle,ogDesc,ogImage,robots,schema].forEach(el=>el&&el.addEventListener('input',analyze));
 analyze();crawl();
})();
(function(){
 const title=document.getElementById('seoTitle'),desc=document.getElementById('metaDescription'),store=document.getElementById('metaDescriptionStore');
 const ct=document.getElementById('titleCount'),cd=document.getElementById('descCount'),pt=document.getElementById('pTitle'),pd=document.getElementById('pDesc');
 const url=document.querySelector('[name=canonical_url]'),pu=document.getElementById('pUrl'),ot=document.querySelector('[name=og_title]'),od=document.querySelector('[name=og_description]');
 const pot=document.getElementById('pOgTitle'),pod=document.getElementById('pOgDesc');
 function sync(){ct.textContent=title.value.length;cd.textContent=desc.value.length;store.value=desc.value;pt.textContent=title.value||'Your SEO Title';pd.textContent=desc.value||'Your meta description preview will appear here.';pu.textContent=url.value||'<?php echo e($siteUrl); ?>/';pot.textContent=ot.value||title.value||'Social Share Title';pod.textContent=od.value||desc.value||'Social share description';}
 [title,desc,url,ot,od].forEach(x=>x&&x.addEventListener('input',sync));sync();
})();
</script>
<?php require __DIR__.'/includes/footer.php';?>
