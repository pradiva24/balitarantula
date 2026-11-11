<?php
require __DIR__.'/includes/auth.php';
$configFile = __DIR__.'/gsc-config.php';
$gscConfig = is_file($configFile) ? require $configFile : [];
if (!is_array($gscConfig)) $gscConfig = [];
$db = db();
$page_title = 'Google Search Console';
$settings = [
 'gsc_client_id' => '', 'gsc_client_secret' => '',
 'gsc_property' => (string)($gscConfig['property'] ?? 'sc-domain:manuabacrystalfall.com'),
 'gsc_token' => '', 'gsc_refresh_token' => '', 'gsc_token_expiry' => '0'
];
try {
 $st=$db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'gsc_%'");
 foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row) if(array_key_exists($row['setting_key'],$settings)) $settings[$row['setting_key']]=$row['setting_value'];
} catch(Throwable $e) { /* setup below will report a safe message */ }
$settings['gsc_client_id'] = trim((string)($settings['gsc_client_id'] ?: ($gscConfig['client_id'] ?? '')));
$settings['gsc_client_secret'] = trim((string)($settings['gsc_client_secret'] ?: ($gscConfig['client_secret'] ?? '')));
$settings['gsc_property'] = trim((string)($settings['gsc_property'] ?: ($gscConfig['property'] ?? 'sc-domain:manuabacrystalfall.com')));
$gscConfigured = $settings['gsc_client_id'] !== '' && $settings['gsc_client_secret'] !== '';
function gsc_save(PDO $db,string $key,string $value):void {
 $q=$db->prepare('SELECT id FROM settings WHERE setting_key=?'); $q->execute([$key]); $id=$q->fetchColumn();
 if($id) { $q=$db->prepare('UPDATE settings SET setting_value=?, updated_at=NOW() WHERE id=?'); $q->execute([$value,$id]); }
 else { $q=$db->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (?,?)'); $q->execute([$key,$value]); }
}
function gsc_http(string $url, ?array $post=null, array $headers=[]):array {
 if(!function_exists('curl_init')) throw new RuntimeException('PHP cURL extension is required for Google API requests.');
 $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_HTTPHEADER=>$headers]);
 if($post!==null){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($post));}
 $body=curl_exec($ch); $err=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
 if($body===false) throw new RuntimeException('Could not connect to Google: '.$err);
 $json=json_decode($body,true); if($status<200||$status>=300) { $msg=is_array($json)?($json['error']['message']??'Google API request failed'):'Google API request failed'; throw new RuntimeException($msg.' (HTTP '.$status.')'); }
 return is_array($json)?$json:[];
}
function gsc_token(PDO $db,array &$s):string {
 $token=(string)$s['gsc_token']; $expiry=(int)$s['gsc_token_expiry'];
 if($token!=='' && $expiry>time()+90) return $token;
 if(empty($s['gsc_refresh_token'])||empty($s['gsc_client_id'])||empty($s['gsc_client_secret'])) throw new RuntimeException('Connect Google Search Console first.');
 $r=gsc_http('https://oauth2.googleapis.com/token',['client_id'=>$s['gsc_client_id'],'client_secret'=>$s['gsc_client_secret'],'refresh_token'=>$s['gsc_refresh_token'],'grant_type'=>'refresh_token']);
 $token=(string)($r['access_token']??''); if($token==='') throw new RuntimeException('Google did not return an access token.');
 $expiry=time()+(int)($r['expires_in']??3600); gsc_save($db,'gsc_token',$token); gsc_save($db,'gsc_token_expiry',(string)$expiry); $s['gsc_token']=$token; $s['gsc_token_expiry']=(string)$expiry; return $token;
}
function gsc_api(string $url,string $token,?array $body=null):array {
 $headers=['Authorization: Bearer '.$token,'Accept: application/json']; if($body!==null)$headers[]='Content-Type: application/json';
 if($body===null) { // GET request
  if(!function_exists('curl_init')) throw new RuntimeException('PHP cURL extension is required.');
  $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_HTTPHEADER=>$headers]); $raw=curl_exec($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($raw===false)throw new RuntimeException('Google API connection failed: '.$err);$j=json_decode($raw,true);if($status<200||$status>=300)throw new RuntimeException((string)($j['error']['message']??'Google API request failed').' (HTTP '.$status.')');return is_array($j)?$j:[];
 }
 if(!function_exists('curl_init')) throw new RuntimeException('PHP cURL extension is required.');
 $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>$headers]);$raw=curl_exec($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($raw===false)throw new RuntimeException('Google API connection failed: '.$err);$j=json_decode($raw,true);if($status<200||$status>=300)throw new RuntimeException((string)($j['error']['message']??'Google API request failed').' (HTTP '.$status.')');return is_array($j)?$j:[];
}
$notice='';$noticeType='';$error='';
$redirectUri=rtrim(BASE_URL,'/').'/manuaba-portal/search-console.php?oauth_callback=1';
try {
 if(isset($_GET['oauth_callback'])) {
  if(!hash_equals((string)($_SESSION['gsc_oauth_state']??''),(string)($_GET['state']??'')) || empty($_SESSION['gsc_oauth_state'])) throw new RuntimeException('OAuth state validation failed. Please connect again.');
  unset($_SESSION['gsc_oauth_state']);
  if(isset($_GET['error'])) throw new RuntimeException('Google authorization was cancelled or denied.');
  $code=(string)($_GET['code']??''); if($code==='') throw new RuntimeException('Google did not return an authorization code.');
  $r=gsc_http('https://oauth2.googleapis.com/token',['code'=>$code,'client_id'=>$settings['gsc_client_id'],'client_secret'=>$settings['gsc_client_secret'],'redirect_uri'=>$redirectUri,'grant_type'=>'authorization_code']);
  if(empty($r['access_token'])) throw new RuntimeException('Google did not return an access token.');
  gsc_save($db,'gsc_token',(string)$r['access_token']);gsc_save($db,'gsc_token_expiry',(string)(time()+(int)($r['expires_in']??3600)));
  if(!empty($r['refresh_token']))gsc_save($db,'gsc_refresh_token',(string)$r['refresh_token']);
  header('Location: search-console.php?connected=1');exit;
 }
 if($_SERVER['REQUEST_METHOD']==='POST') {
  admin_verify_csrf(); $action=(string)($_POST['action']??'');
  if($action==='install_verification') {
   // Accept the exact filename and content supplied by Google Search Console.
   $verificationName=trim((string)($_POST['verification_name']??''));
   $verificationContent=trim((string)($_POST['verification_content']??''));
   if($verificationName==='' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.html$/i',$verificationName) || strpos($verificationName,'..')!==false || strpos($verificationName,'/')!==false || strpos($verificationName,'\\')!==false) {
    throw new RuntimeException('Nama file tidak valid. Masukkan nama file HTML persis seperti yang diberikan Google.');
   }
   if($verificationContent==='' || !preg_match('/^google-site-verification:\s*[^\r\n]+$/i',$verificationContent)) {
    throw new RuntimeException('Isi file tidak valid. Salin seluruh isi file HTML verifikasi dari Google Search Console.');
   }
   // search-console.php lives in /manuaba-portal; verification file must be in the site document root.
   $siteRoot=dirname(__DIR__);
   $verificationPath=$siteRoot.DIRECTORY_SEPARATOR.$verificationName;
   if(!is_dir($siteRoot) || !is_writable($siteRoot)) {
    throw new RuntimeException('Folder root website tidak dapat ditulis oleh PHP. Periksa permission hosting atau upload file HTML secara manual.');
   }
   $written=@file_put_contents($verificationPath,$verificationContent,LOCK_EX);
   if($written===false || $written!==strlen($verificationContent)) {
    throw new RuntimeException('File verifikasi gagal dibuat di root website. Periksa permission hosting.');
   }
   @chmod($verificationPath,0644);
   $_SESSION['gsc_verification_name']=$verificationName;
   $_SESSION['gsc_verification_content']=$verificationContent;
   header('Location: search-console.php?verification_installed=1'); exit;
  } elseif($action==='save') {
   $cid=trim((string)($_POST['client_id']??''));
   $secret=trim((string)($_POST['client_secret']??''));
   $property=trim((string)($_POST['property']??''));
   if($cid==='' || !filter_var($cid,FILTER_VALIDATE_EMAIL) && !preg_match('/^[0-9]+-[a-zA-Z0-9_-]+\.apps\.googleusercontent\.com$/',$cid)) throw new RuntimeException('Masukkan Client ID Google OAuth yang valid.');
   if($secret==='') $secret=(string)$settings['gsc_client_secret'];
   if($secret==='') throw new RuntimeException('Client Secret wajib diisi saat konfigurasi pertama.');
   if(!preg_match('/^(sc-domain:[a-zA-Z0-9.-]+|https?:\/\/.+)$/',$property)) throw new RuntimeException('Property harus berupa sc-domain:domain.com atau URL prefix lengkap.');
   gsc_save($db,'gsc_client_id',$cid); gsc_save($db,'gsc_client_secret',$secret); gsc_save($db,'gsc_property',$property);
   header('Location: search-console.php?config_saved=1'); exit;
  } elseif($action==='connect') {
   if(!$gscConfigured) { throw new RuntimeException('OAuth belum dikonfigurasi. Isi client_id dan client_secret pada file gsc-config.php di server terlebih dahulu.'); }
   $state=bin2hex(random_bytes(24));$_SESSION['gsc_oauth_state']=$state;
   $params=['client_id'=>$settings['gsc_client_id'],'redirect_uri'=>$redirectUri,'response_type'=>'code','scope'=>'https://www.googleapis.com/auth/webmasters.readonly','access_type'=>'offline','prompt'=>'consent','state'=>$state];
   header('Location: https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($params));exit;
  } elseif($action==='disconnect') {
   foreach(['gsc_token','gsc_refresh_token','gsc_token_expiry'] as $k)gsc_save($db,$k,'');
   header('Location: search-console.php?disconnected=1');exit;
  }
 }
} catch(Throwable $e) { $error=$e->getMessage(); }
if(isset($_GET['config_saved'])){$notice='Konfigurasi Google Search Console berhasil disimpan.';$noticeType='success';}
if(isset($_GET['verification_installed'])){$notice='File '.(string)($_SESSION['gsc_verification_name']??'verifikasi HTML').' berhasil dibuat/diperbarui di root website. Buka URL file tersebut lalu klik Verify di Google Search Console.';$noticeType='success';}
$verificationName=(string)($_SESSION['gsc_verification_name']??'google19c665a4d6f0f768.html');
$verificationContent=(string)($_SESSION['gsc_verification_content']??'google-site-verification: google19c665a4d6f0f768.html');
if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.html$/i',$verificationName)) $verificationName='google19c665a4d6f0f768.html';
if(!preg_match('/^google-site-verification:\s*[^\r\n]+$/i',$verificationContent)) $verificationContent='google-site-verification: google19c665a4d6f0f768.html';
if(isset($_GET['connected'])){$notice='Google Search Console berhasil dihubungkan.';$noticeType='success';}
if(isset($_GET['disconnected'])){$notice='Koneksi Google telah diputus.';$noticeType='success';}
$connected=!empty($settings['gsc_refresh_token'])||(!empty($settings['gsc_token'])&&(int)$settings['gsc_token_expiry']>time());
$days=(int)($_GET['days']??28);if(!in_array($days,[7,28,90],true))$days=28;
$summary=['clicks'=>0,'impressions'=>0,'ctr'=>0,'position'=>0];$queries=[];$pages=[];$dataError='';
if($connected && !isset($_GET['disconnected'])) {
 try {
  $token=gsc_token($db,$settings);$property=(string)$settings['gsc_property'];$encoded=rawurlencode($property);
  $sites=gsc_api('https://www.googleapis.com/webmasters/v3/sites',$token);$allowed=[];foreach(($sites['siteEntry']??[]) as $site)$allowed[]=(string)($site['siteUrl']??'');
  if(!in_array($property,$allowed,true))throw new RuntimeException('Akun Google terhubung, tetapi tidak memiliki akses ke property '.$property.'. Periksa property di Search Console.');
  $end=date('Y-m-d',strtotime('-3 days'));$start=date('Y-m-d',strtotime('-'.($days+2).' days'));
  $endpoint='https://www.googleapis.com/webmasters/v3/sites/'.$encoded.'/searchAnalytics/query';
  $sum=gsc_api($endpoint,$token,['startDate'=>$start,'endDate'=>$end,'type'=>'web','dataState'=>'final']);$rows=$sum['rows']??[];if($rows){$summary['clicks']=(int)round($rows[0]['clicks']??0);$summary['impressions']=(int)round($rows[0]['impressions']??0);$summary['ctr']=(float)($rows[0]['ctr']??0);$summary['position']=(float)($rows[0]['position']??0);}
  $q=gsc_api($endpoint,$token,['startDate'=>$start,'endDate'=>$end,'type'=>'web','dimensions'=>['query'],'rowLimit'=>10,'dataState'=>'final']);$queries=$q['rows']??[];
  $pg=gsc_api($endpoint,$token,['startDate'=>$start,'endDate'=>$end,'type'=>'web','dimensions'=>['page'],'rowLimit'=>10,'dataState'=>'final']);$pages=$pg['rows']??[];
 } catch(Throwable $e) { $dataError=$e->getMessage(); }
}
require __DIR__.'/includes/header.php';
?>
<style>
.gsc-wrap{max-width:1200px;margin:0 auto;color:#20352a}.gsc-hero{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:26px;border-radius:20px;background:radial-gradient(circle at 90% 15%,#3b9667 0,transparent 34%),linear-gradient(125deg,#103b2b,#24784e);color:#fff;margin-bottom:22px;box-shadow:0 12px 28px #123d2d1a}.gsc-hero h2{margin:0 0 7px;font-size:24px;letter-spacing:-.35px}.gsc-hero p{margin:0;color:#e0f2e8;line-height:1.6}.gsc-pill{display:inline-flex;align-items:center;gap:7px;padding:8px 12px;border-radius:999px;background:#e4f8eb;color:#17643d;font-size:12px;font-weight:700;white-space:nowrap}.gsc-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:18px 0}.gsc-card,.gsc-panel{background:#fff;border:1px solid #e3ebe5;border-radius:16px;padding:20px;box-shadow:0 5px 20px #173b2b08}.gsc-card small{color:#64756b}.gsc-card strong{display:block;font-size:27px;margin-top:8px;color:#183c2c}.gsc-panel{margin:16px 0}.gsc-panel h3{margin:0 0 10px;font-size:18px;letter-spacing:-.2px}.gsc-panel-intro{display:flex;align-items:flex-start;gap:12px;margin-bottom:18px}.gsc-step{display:flex;align-items:center;justify-content:center;flex:0 0 36px;height:36px;border-radius:11px;background:#e8f5ec;color:#1d6847;font-weight:800}.gsc-formgrid{display:grid;grid-template-columns:1fr 1fr;gap:17px}.gsc-field label{display:block;font-weight:700;font-size:13px;color:#294538;margin-bottom:8px}.gsc-field input{display:block;width:100%;box-sizing:border-box;min-height:46px;padding:11px 13px;border:1px solid #d8e3db;border-radius:11px;background:#fbfdfb;color:#1d3327;font:inherit;outline:none;transition:border-color .15s,box-shadow .15s,background .15s}.gsc-field input:focus{border-color:#32845a;background:#fff;box-shadow:0 0 0 4px #2b87551a}.gsc-field input::placeholder{color:#9aa79e}.gsc-field small{display:block;margin-top:7px}.gsc-field-wide{grid-column:1/-1}.gsc-redirect{display:flex;align-items:flex-start;gap:10px;padding:13px 14px;border:1px solid #dcebe0;border-radius:12px;background:#f5faf6;margin:13px 0 20px}.gsc-redirect code{display:block;overflow-wrap:anywhere;color:#24583a;font-size:12px;line-height:1.6;margin-top:5px}.gsc-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:18px}.gsc-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:43px;border:0;border-radius:11px;padding:11px 17px;font-weight:750;cursor:pointer;text-decoration:none;background:linear-gradient(135deg,#1d6847,#258353);color:#fff!important;box-shadow:0 5px 12px #1d68471c;transition:transform .15s,box-shadow .15s}.gsc-btn:hover{transform:translateY(-1px);box-shadow:0 8px 17px #1d68472a}.gsc-btn.secondary{background:#eef5f0;color:#294538!important;box-shadow:none}.gsc-btn.danger{background:#fff0ed;color:#9a3022!important;box-shadow:none}.gsc-divider{height:1px;background:#edf1ed;margin:20px 0}.gsc-table-wrap{overflow:auto}.gsc-table{width:100%;border-collapse:collapse;min-width:550px}.gsc-table th,.gsc-table td{text-align:left;padding:11px;border-bottom:1px solid #edf0ee;font-size:13px}.gsc-table th{color:#607067;background:#f8faf8}.gsc-table td:first-child{overflow-wrap:anywhere;max-width:480px}.gsc-note{padding:13px 15px;border-radius:12px;margin:12px 0;background:#eff6ff;color:#244d76;border:1px solid #dbeafe;line-height:1.55}.gsc-note.error{background:#fff5f2;color:#8c2b20;border-color:#f8ddd6}.gsc-note.success{background:#effaf2;color:#1e6038;border-color:#d7f0dd}.gsc-muted{color:#68766e;font-size:13px;line-height:1.65}.gsc-select{padding:9px 12px;border:1px solid #d7e0da;border-radius:9px;background:white}
@media(max-width:800px){.gsc-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.gsc-hero{align-items:flex-start;flex-direction:column;padding:21px}.gsc-formgrid{grid-template-columns:1fr}.gsc-field-wide{grid-column:auto}.gsc-card strong{font-size:23px}}@media(max-width:480px){.gsc-grid{gap:9px}.gsc-card{padding:13px}.gsc-card strong{font-size:20px}.gsc-panel{padding:16px}.gsc-actions .gsc-btn{width:100%;box-sizing:border-box}.gsc-hero h2{font-size:21px}}

.gsc-quicksteps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:16px 0 20px}.gsc-quickstep{display:flex;align-items:flex-start;gap:10px;padding:14px;border:1px solid #e0e9e2;border-radius:13px;background:#fbfdfb;text-decoration:none;color:#294538!important;min-width:0}.gsc-quickstep>span:nth-child(2){flex:1;min-width:0}.gsc-quickstep strong{display:block;font-size:13px;margin-bottom:5px}.gsc-quickstep small{display:block;color:#68766e;font-size:12px;line-height:1.5}.gsc-quickstep>i{font-size:12px;margin-top:3px;color:#568066}.gsc-redirect{flex-wrap:wrap}.gsc-redirect .gsc-btn{flex-shrink:0}
@media(max-width:800px){.gsc-quicksteps{grid-template-columns:1fr}.gsc-quickstep{padding:12px}.gsc-redirect .gsc-btn{width:100%}}
</style>
<div class="gsc-wrap">
 <section class="gsc-hero"><div><h2><i class="fa-brands fa-google"></i> Google Search Console</h2><p><?= $connected ? 'Pantau performa SEO website langsung dari Manuaba Portal.' : 'Hubungkan akun Google satu kali, lalu pantau performa SEO dari halaman ini.' ?></p></div><span class="gsc-pill"><?= $connected?'Google Connected':'Belum terhubung' ?></span></section>
 <?php if($notice):?><div class="gsc-note success"><?=admin_e($notice)?></div><?php endif;?>
 <?php if($error):?><div class="gsc-note error"><?=admin_e($error)?></div><?php endif;?>
 <?php if($dataError):?><div class="gsc-note error"><strong>Data belum dapat dimuat:</strong> <?=admin_e($dataError)?> <span class="gsc-muted">Periksa akses properti dan koneksi Google. Jika akses dicabut, putuskan koneksi lalu hubungkan kembali.</span></div><?php endif;?>
 <section class="gsc-panel">
  <div class="gsc-panel-intro"><span class="gsc-step"><i class="fa-solid fa-shield-check"></i></span><div><h3>Verifikasi kepemilikan website</h3><p class="gsc-muted">Pasang file HTML Google langsung dari Manuaba Portal. File akan dibuat di root website, bukan di folder manuaba-portal.</p></div></div>
  <form method="post">
   <input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="action" value="install_verification">
   <div class="gsc-formgrid">
    <div class="gsc-field gsc-field-wide"><label for="verification_name">Nama file verifikasi HTML</label><input id="verification_name" name="verification_name" type="text" required maxlength="180" value="<?=admin_e($verificationName)?>" placeholder="googlexxxxxxxx.html"><small class="gsc-muted">Masukkan nama file persis seperti yang diberikan Google.</small></div>
    <div class="gsc-field gsc-field-wide"><label for="verification_content">Isi file verifikasi</label><input id="verification_content" name="verification_content" type="text" required maxlength="500" value="<?=admin_e($verificationContent)?>" placeholder="google-site-verification: token.html"><small class="gsc-muted">Salin isi persis dari file HTML yang diunduh dari Search Console. Jangan mengarang token.</small></div>
   </div>
   <div class="gsc-note">URL file yang akan dibuat: <a href="<?=admin_e(rtrim(BASE_URL,'/').'/'.$verificationName)?>" target="_blank" rel="noopener"><?=admin_e(rtrim(BASE_URL,'/').'/'.$verificationName)?></a></div>
   <div class="gsc-actions"><button class="gsc-btn" type="submit"><i class="fa-solid fa-file-circle-check"></i> Simpan / Perbarui File Verifikasi</button><a class="gsc-btn secondary" target="_blank" rel="noopener" href="https://search.google.com/search-console/">Buka Google Search Console</a></div>
  </form>
 </section>
 <?php if(!$connected): ?>
 <section class="gsc-panel">
 <div class="gsc-panel-intro"><span class="gsc-step"><i class="fa-brands fa-google"></i></span><div><h3>Hubungkan akun Google</h3><p class="gsc-muted">Klik tombol di bawah, pilih akun Google yang memiliki akses ke properti Search Console, lalu setujui izin baca. Setelah berhasil, dashboard performa SEO akan tampil di halaman ini.</p></div></div>
 <div class="gsc-actions" style="margin-top:20px"><form method="post"><input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="action" value="connect"><button class="gsc-btn" type="submit"><i class="fa-brands fa-google"></i> Connect with Google</button></form></div>
 <?php if(!$gscConfigured): ?><div class="gsc-note error" style="margin-top:16px"><strong>Konfigurasi OAuth belum lengkap.</strong> Isi form konfigurasi di bawah ini satu kali untuk mengaktifkan koneksi Google.</div><?php endif; ?>
 <?php if(!$gscConfigured): ?>
 <div class="gsc-divider"></div>
 <h3>Konfigurasi Google Search Console</h3>
 <p class="gsc-muted">Masukkan kredensial OAuth dari Google Cloud satu kali. Data disimpan di tabel settings portal agar tidak perlu mengedit file server. Jangan bagikan Client Secret.</p>
 <form method="post" autocomplete="off">
  <input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="action" value="save">
  <div class="gsc-formgrid">
   <div class="gsc-field gsc-field-wide"><label for="gsc-client-id">OAuth Client ID</label><input id="gsc-client-id" name="client_id" type="text" required placeholder="...apps.googleusercontent.com" value="<?=admin_e($settings['gsc_client_id'])?>" autocomplete="off"></div>
   <div class="gsc-field gsc-field-wide"><label for="gsc-client-secret">OAuth Client Secret</label><input id="gsc-client-secret" name="client_secret" type="password" required placeholder="Masukkan Client Secret" autocomplete="new-password"></div>
   <div class="gsc-field gsc-field-wide"><label for="gsc-property">Search Console Property</label><input id="gsc-property" name="property" type="text" required value="<?=admin_e($settings['gsc_property'])?>" placeholder="sc-domain:manuabacrystalfall.com"></div>
  </div>
  <div class="gsc-redirect"><div><strong>Authorized redirect URI</strong><code><?=admin_e($redirectUri)?></code></div></div>
  <div class="gsc-actions"><button class="gsc-btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan Konfigurasi</button></div>
 </form>
 <?php endif; ?>
 <p class="gsc-muted" style="margin-top:17px"><i class="fa-solid fa-lock"></i> Akses hanya-baca (<code>webmasters.readonly</code>). Client Secret tidak ditampilkan kembali setelah disimpan.</p></section>
 <?php else: ?>
 <section class="gsc-panel" style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap"><div><h3 style="margin:0 0 5px"><i class="fa-solid fa-circle-check" style="color:#258353"></i> Akun Google sudah terhubung</h3><p class="gsc-muted" style="margin:0">Data performa SEO ditampilkan di bawah. Anda tidak perlu membuka dashboard Search Console terpisah.</p></div><form method="post" onsubmit="return confirm('Putuskan koneksi Google Search Console?')"><input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="action" value="disconnect"><button class="gsc-btn danger" type="submit"><i class="fa-solid fa-link-slash"></i> Putuskan koneksi</button></form></section>
 <?php endif; ?>
 <?php if($connected && !$dataError):?><section class="gsc-panel"><div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><h3 style="margin:0">Ringkasan performa pencarian</h3><form method="get"><label for="days">Date range&nbsp;</label><select class="gsc-select" name="days" id="days" onchange="this.form.submit()"><option value="7" <?=$days===7?'selected':''?>>Last 7 days</option><option value="28" <?=$days===28?'selected':''?>>Last 28 days</option><option value="90" <?=$days===90?'selected':''?>>Last 90 days</option></select></form></div><p class="gsc-muted">Data finalized through <?=admin_e(date('d M Y',strtotime('-3 days')))?>. Google may delay recent data.</p><div class="gsc-grid"><div class="gsc-card"><small>Total clicks</small><strong><?=number_format($summary['clicks'])?></strong></div><div class="gsc-card"><small>Total impressions</small><strong><?=number_format($summary['impressions'])?></strong></div><div class="gsc-card"><small>Average CTR</small><strong><?=number_format($summary['ctr']*100,2)?>%</strong></div><div class="gsc-card"><small>Average position</small><strong><?=number_format($summary['position'],1)?></strong></div></div></section>
 <section class="gsc-panel"><h3>Keyword teratas dari Google</h3><div class="gsc-table-wrap"><table class="gsc-table"><thead><tr><th>Search query</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Position</th></tr></thead><tbody><?php if(!$queries):?><tr><td colspan="5">No query data available for this period.</td></tr><?php else:foreach($queries as $r):?><tr><td><?=admin_e($r['keys'][0]??'')?></td><td><?=number_format((int)($r['clicks']??0))?></td><td><?=number_format((int)($r['impressions']??0))?></td><td><?=number_format((float)($r['ctr']??0)*100,2)?>%</td><td><?=number_format((float)($r['position']??0),1)?></td></tr><?php endforeach;endif;?></tbody></table></div></section>
 <section class="gsc-panel"><h3>Halaman website terpopuler</h3><div class="gsc-table-wrap"><table class="gsc-table"><thead><tr><th>Page URL</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Position</th></tr></thead><tbody><?php if(!$pages):?><tr><td colspan="5">No page data available for this period.</td></tr><?php else:foreach($pages as $r):?><tr><td><?=admin_e($r['keys'][0]??'')?></td><td><?=number_format((int)($r['clicks']??0))?></td><td><?=number_format((int)($r['impressions']??0))?></td><td><?=number_format((float)($r['ctr']??0)*100,2)?>%</td><td><?=number_format((float)($r['position']??0),1)?></td></tr><?php endforeach;endif;?></tbody></table></div></section><?php endif;?>
 <p class="gsc-muted">Catatan: Google API hanya mengembalikan data milik property yang dapat diakses akun Google yang diotorisasi. Ini adalah laporan performa penelusuran, bukan alat untuk mengubah ranking atau mengindeks halaman secara langsung.</p>
</div>
<script>
function gscCopyCallback(){const el=document.getElementById('gsc-callback');if(!el)return;const value=el.textContent.trim();if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(value).then(()=>alert('Callback berhasil disalin.')).catch(()=>gscCopyFallback(value));}else{gscCopyFallback(value);}}
function gscCopyFallback(value){const ta=document.createElement('textarea');ta.value=value;ta.style.position='fixed';ta.style.opacity='0';document.body.appendChild(ta);ta.select();try{document.execCommand('copy');alert('Callback berhasil disalin.');}catch(e){alert('Silakan salin alamat callback secara manual.');}ta.remove();}
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
