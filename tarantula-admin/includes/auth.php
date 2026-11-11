<?php
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../includes/image-optimizer.php';
if(session_status()===PHP_SESSION_NONE){
    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    ini_set('session.use_strict_mode','1');
    ini_set('session.use_only_cookies','1');
    ini_set('session.use_trans_sid','0');
    ini_set('session.cookie_secure', $https ? '1' : '0');
    ini_set('session.cookie_httponly','1');
    ini_set('session.cookie_samesite','Lax');
    session_name('BTA_SESSION');
    session_set_cookie_params([
        'lifetime'=>0,
        'path'=>'/',
        'secure'=>$https,
        'httponly'=>true,
        'samesite'=>'Lax'
    ]);
    session_start();
}
// Prevent authenticated admin pages from being cached by browsers/proxies.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

if(!empty($_SESSION['admin_id'])){
    // Expire inactive admin sessions after 30 minutes.
    $last=(int)($_SESSION['admin_last_activity']??0);
    if($last>0 && (time()-$last)>1800){
        $_SESSION=[];
        if(ini_get('session.use_cookies')){
            $params=session_get_cookie_params();
            setcookie(session_name(),'',time()-42000,$params['path'],$params['domain']??'',(bool)$params['secure'],(bool)$params['httponly']);
        }
        session_destroy();
        header('Location: login.php?expired=1'); exit;
    }
    $_SESSION['admin_last_activity']=time();
    // Keep the authenticated session bound to the expected account namespace.
    if (!isset($_SESSION['admin_login_at'])) $_SESSION['admin_login_at']=time();
}
if(empty($_SESSION['admin_id'])){header('Location: login.php');exit;}
function admin_e($v){return e($v);} function admin_db(){return db();}
function admin_flash($type,$message){$_SESSION['admin_flash']=[$type,$message];}
function admin_flash_get(){$x=$_SESSION['admin_flash']??null;unset($_SESSION['admin_flash']);return $x;}
function admin_csrf(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['admin_csrf']) || !is_string($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}
function admin_verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $sessionToken = $_SESSION['admin_csrf'] ?? '';
    $postedToken = $_POST['csrf'] ?? '';
    if (!is_string($sessionToken) || !is_string($postedToken) || $sessionToken === '' || $postedToken === '' || !hash_equals($sessionToken, $postedToken)) {
        error_log('BTA CSRF failure: session_id=' . session_id() . '; admin_id=' . (string)($_SESSION['admin_id'] ?? '0') . '; has_post_token=' . ($postedToken !== '' ? '1' : '0'));
        http_response_code(419);
        exit('Invalid CSRF token. Please refresh the page and try again.');
    }
}
function admin_slug($s){$s=slugify($s);return substr($s,0,191);}
function admin_media_selected($field): ?string {
    $key='media_'.$field;
    $value=$_POST[$key]??null;
    if(is_array($value)) return null;
    $value=trim((string)$value);
    if($value==='') return null;
    if(!preg_match('~^uploads/(?!payment-proofs/)[A-Za-z0-9_./-]+\.(?:jpe?g|png|webp|gif|svg)$~i',$value)){
        throw new RuntimeException('Invalid Media Library image selection.');
    }
    $root=realpath(__DIR__.'/../../uploads');
    $full=realpath(__DIR__.'/../../'.ltrim($value,'/'));
    if(!$root || !$full || strncmp($full,$root.DIRECTORY_SEPARATOR,strlen($root)+1)!==0 || !is_file($full)){
        throw new RuntimeException('Selected Media Library image was not found.');
    }
    return $value;
}
function admin_media_has_selection($field): bool {
    $key='media_'.$field;
    $value=$_POST[$key]??null;
    if(is_array($value)) return count(array_filter($value,static fn($v)=>trim((string)$v)!==''))>0;
    return trim((string)$value)!=='';
}
function admin_media_selected_multiple($field): array {
    $key='media_'.$field;
    $values=$_POST[$key]??[];
    if(!is_array($values)) $values=[$values];
    $out=[];
    foreach($values as $value){
        $value=trim((string)$value);
        if($value==='') continue;
        if(!preg_match('~^uploads/(?!payment-proofs/)[A-Za-z0-9_./-]+\.(?:jpe?g|png|webp|gif|svg)$~i',$value)){
            throw new RuntimeException('Invalid Media Library image selection.');
        }
        $root=realpath(__DIR__.'/../../uploads');
        $full=realpath(__DIR__.'/../../'.ltrim($value,'/'));
        if(!$root || !$full || strncmp($full,$root.DIRECTORY_SEPARATOR,strlen($root)+1)!==0 || !is_file($full)){
            throw new RuntimeException('Selected Media Library image was not found.');
        }
        $out[]=$value;
    }
    return array_values(array_unique($out));
}
function admin_upload($field,$folder,$old=null){
    $selected=admin_media_selected($field);
    if($selected!==null) return $selected;
    if(empty($_FILES[$field])||($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return $old;
    $f=$_FILES[$field];
    $err=(int)($f['error']??UPLOAD_ERR_OK);
    if($err!==UPLOAD_ERR_OK){
        $map=[UPLOAD_ERR_INI_SIZE=>'Image is larger than the server upload limit.',UPLOAD_ERR_FORM_SIZE=>'Image is larger than the form upload limit.',UPLOAD_ERR_PARTIAL=>'Upload was interrupted before the image was completely received.',UPLOAD_ERR_NO_TMP_DIR=>'Server temporary upload folder is missing.',UPLOAD_ERR_CANT_WRITE=>'Server could not write the uploaded file.',UPLOAD_ERR_EXTENSION=>'A PHP extension stopped the upload.'];
        throw new RuntimeException($map[$err]??('Upload failed (PHP upload error '.$err.').'));
    }
    if((int)($f['size']??0)>bt_image_max_upload_bytes()) throw new RuntimeException('Image exceeds the 20 MB application limit.');
    $tmp=(string)($f['tmp_name']??'');
    if($tmp===''||!is_uploaded_file($tmp)) throw new RuntimeException('The uploaded file is not available on the server.');
    $info=@getimagesize($tmp);
    if(!$info||empty($info['mime'])) throw new RuntimeException('The uploaded file is not a valid image.');
    $mime=(string)$info['mime'];
    if(!bt_image_allowed_mime($mime)) throw new RuntimeException('Only JPG, PNG, WebP or GIF images are allowed.');

    // Duplicate protection is performed AFTER optimization.
    // The previous implementation compared the original upload hash against
    // already-optimized WebP files, so the hashes could never match and the
    // same image was saved again on every upload.

    $dir=__DIR__.'/../../uploads/'.$folder;
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir)) throw new RuntimeException('Cannot create upload folder: '.$folder);
    $ext=bt_image_output_extension($mime);
    $name=bin2hex(random_bytes(16)).'.'.$ext;
    $dest=$dir.'/'.$name;
    $tmpDest=$dest.'.tmp';
    try{
        bt_image_optimize($tmp,$tmpDest,2400,2400,82);
        if(!is_file($tmpDest)||filesize($tmpDest)<=0) throw new RuntimeException('Image optimizer produced an empty file.');
        if(!@rename($tmpDest,$dest)){
            if(!@copy($tmpDest,$dest)) throw new RuntimeException('Server could not save the optimized image.');
            @unlink($tmpDest);
        }
        if(!is_file($dest)||filesize($dest)<=0) throw new RuntimeException('Server could not save the uploaded image.');

        // Compare the final optimized file with existing assets in the same
        // destination folder. This catches repeated uploads of the same image
        // without recursively hashing the entire uploads tree on every request.
        // Images that are intentionally used in another section can still be
        // shared safely through the Media Library picker.
        $finalHash=@hash_file('sha256',$dest);
        if($finalHash && is_dir($dir)){
            foreach(new DirectoryIterator($dir) as $existing){
                if($existing->isDot() || !$existing->isFile()) continue;
                $existingPath=$existing->getPathname();
                if($existingPath===$dest || !preg_match('~\.(?:jpe?g|png|webp|gif)$~i',$existing->getFilename())) continue;
                if(@hash_file('sha256',$existingPath)===$finalHash){
                    @unlink($dest);
                    return 'uploads/'.$folder.'/'.$existing->getFilename();
                }
            }
        }
    }catch(Throwable $e){
        if(is_file($tmpDest)) @unlink($tmpDest); if(is_file($dest)) @unlink($dest);
        throw new RuntimeException('Image processing failed: '.$e->getMessage());
    }
    // Never delete the old asset here: the same Media Library file may be referenced elsewhere.
    return 'uploads/'.$folder.'/'.$name;
}
/**
 * Return true when a media path is still referenced anywhere in the database.
 * This keeps the CMS media library safe: deleting a gallery/package reference
 * must never delete a file that another page still uses.
 *
 * The optional second/third arguments allow callers to exclude the record
 * currently being deleted when needed. They are intentionally optional for
 * compatibility with older admin pages that call this helper with one arg.
 */
function admin_image_is_referenced($path, $excludeTable = null, $excludeId = null): bool {
    // Backward compatibility: some older admin code passed the PDO object first,
    // e.g. admin_image_is_referenced($db, $path). Normalize that call signature.
    if ($path instanceof PDO) {
        $pdoFromCaller = $path;
        $path = $excludeTable;
        $excludeTable = null;
        $excludeId = null;
    } else {
        $pdoFromCaller = null;
    }

    if ($path instanceof PDO || is_object($path) || is_array($path)) {
        return true; // fail closed rather than trying to stringify an object
    }
    $path = trim(str_replace('\\', '/', (string)$path));
    if ($path === '' || !preg_match('~^uploads/(?!payment-proofs/)[A-Za-z0-9_./-]+$~i', $path)) {
        return false;
    }

    // Normalize common legacy forms so exact comparisons are reliable.
    $path = preg_replace('~^/+~', '', $path);
    $path = preg_replace('~^(?:uploads/){2,}~i', 'uploads/', $path);
    $variants = array_values(array_unique([
        $path,
        '/'.$path,
        ltrim($path, '/'),
        basename($path),
    ]));

    try {
        $pdo = $pdoFromCaller instanceof PDO ? $pdoFromCaller : db();
        $tables = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $table = (string)$table;
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) continue;
            if ($excludeTable !== null && strcasecmp($table, (string)$excludeTable) === 0) continue;

            $colsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND DATA_TYPE IN ('char','varchar','text','tinytext','mediumtext','longtext')");
            $colsStmt->execute([$table]);
            $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($cols as $column) {
                $column = (string)$column;
                if (!preg_match('/^[A-Za-z0-9_]+$/', $column)) continue;

                $sql = "SELECT 1 FROM `{$table}` WHERE `{$column}` IN (" . implode(',', array_fill(0, count($variants), '?')) . ") LIMIT 1";
                try {
                    $st = $pdo->prepare($sql);
                    $st->execute($variants);
                    if ($st->fetchColumn()) return true;
                } catch (Throwable $ignored) {
                    // Some legacy tables/columns may not be queryable; keep checking others.
                }
            }
        }
    } catch (Throwable $ignored) {
        // If reference detection cannot be completed, fail closed: keep the file.
        return true;
    }
    return false;
}

function admin_delete_image($path, $maybePath = null){
    // Backward compatibility: admin_delete_image($db, $path)
    if ($path instanceof PDO) $path = $maybePath;
    if ($path === null || is_object($path) || is_array($path)) return;
    $path = (string)$path;
    if($path==='') return;
    if(admin_image_is_referenced($path)) return;
    $f=__DIR__.'/../../'.ltrim($path,'/');
    if(is_file($f)) @unlink($f);
}
