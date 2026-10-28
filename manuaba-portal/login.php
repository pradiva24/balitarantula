<?php
require_once __DIR__.'/../config/database.php';require_once __DIR__.'/../includes/functions.php';
if(session_status()===PHP_SESSION_NONE){
  ini_set('session.use_strict_mode','1');
  ini_set('session.use_only_cookies','1');
  ini_set('session.use_trans_sid','0');
  ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
  ini_set('session.cookie_httponly','1');
  ini_set('session.cookie_samesite','Lax');
  session_start();
}
if(!empty($_SESSION['admin_id'])){header('Location: index.php');exit;}
$error='';
$lockoutSeconds = 900; // 15 minutes
$rateFile=sys_get_temp_dir().'/bta_login_'.hash('sha256',($_SERVER['REMOTE_ADDR']??'unknown'));
$rate=['count'=>0,'reset'=>time()+$lockoutSeconds];
if(is_file($rateFile)){ $tmp=json_decode((string)@file_get_contents($rateFile),true); if(is_array($tmp) && (int)($tmp['reset']??0)>time()) $rate=$tmp; else @unlink($rateFile); }
if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  if((int)$rate['count']>=4){ http_response_code(429); $remaining=max(1,(int)$rate['reset']-time()); $mins=(int)ceil($remaining/60); $error='Access temporarily locked after 4 failed login attempts. Please try again in '.$mins.' minute'.($mins===1?'':'s').'.'; }
  else {
    $email=trim($_POST['email']??'');
    $stmt=db()->prepare("SELECT * FROM admins WHERE email=? AND status=1 LIMIT 1");$stmt->execute([$email]);$admin=$stmt->fetch();
    if($admin&&password_verify($_POST['password']??'',$admin['password_hash'])){
      @unlink($rateFile); session_regenerate_id(true); $_SESSION['admin_id']=$admin['id'];$_SESSION['admin_name']=$admin['name'];$_SESSION['admin_email']=$admin['email'];$_SESSION['admin_role']=$admin['role']??'admin';$_SESSION['admin_last_activity']=time();$_SESSION['admin_login_at']=time(); header('Location: index.php');exit;
    }
    $rate['count']=(int)$rate['count']+1; if(!isset($rate['reset'])||$rate['reset']<=time())$rate['reset']=time()+$lockoutSeconds; @file_put_contents($rateFile,json_encode($rate),LOCK_EX); if((int)$rate['count']>=4){ http_response_code(429); $error='Access temporarily locked after 4 failed login attempts. Please try again in 15 minutes.'; } else { $left=4-(int)$rate['count']; $error='Invalid email or password. '.$left.' attempt'.($left===1?'':'s').' remaining before temporary lockout.'; }
  }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"><title>Admin Login | <?=e(site_brand_name())?></title><?php if(setting('favicon','')):?><link rel="icon" href="<?=e(BASE_URL.'/'.ltrim(setting('favicon',''),'/'))?>"><?php endif;?><link rel="stylesheet" href="<?=BASE_URL?>/manuaba-portal/assets/css/admin.css?v=15"></head><body class="login-body"><div class="login-card"><?php $loginLogo=setting('admin_logo',setting('site_logo','')); if($loginLogo): ?><img class="login-logo login-logo-only" src="<?=e(asset_url($loginLogo))?>" alt="" onerror="this.style.display='none'"><?php else: ?><div class="brand-mark">BT</div><?php endif;?><p>Administrator Login</p><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?><form method="post"><?=csrf_field()?><label>Email</label><input type="email" name="email" autocomplete="username" required><label>Password</label><input type="password" name="password" autocomplete="current-password" required><button class="btn login-btn">Sign In</button></form><a class="back-site" href="<?=BASE_URL?>/">← Back to website</a></div></body></html>