<?php
require __DIR__.'/includes/auth.php';
$p = db();

$p->exec("CREATE TABLE IF NOT EXISTS gallery_page_settings (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  hero_kicker VARCHAR(120) NOT NULL DEFAULT 'PHOTO GALLERY',
  hero_title VARCHAR(255) NOT NULL DEFAULT 'FEEL THE',
  hero_accent VARCHAR(120) NOT NULL DEFAULT 'ADRENALINE.',
  hero_description TEXT NULL,
  hero_image VARCHAR(500) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$p->exec("INSERT IGNORE INTO gallery_page_settings (id) VALUES (1)");

$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  admin_verify_csrf();
  $action = (string)($_POST['act'] ?? '');
  try {
    if ($action === 'save_header') {
      $current = $p->query("SELECT hero_image FROM gallery_page_settings WHERE id=1")->fetchColumn();
      $heroImage = admin_upload('hero_image', 'gallery-hero', $current ?: null);
      $st = $p->prepare("UPDATE gallery_page_settings SET hero_kicker=?, hero_title=?, hero_accent=?, hero_description=?, hero_image=? WHERE id=1");
      $st->execute([
        trim((string)($_POST['hero_kicker'] ?? '')),
        trim((string)($_POST['hero_title'] ?? '')),
        trim((string)($_POST['hero_accent'] ?? '')),
        trim((string)($_POST['hero_description'] ?? '')),
        $heroImage
      ]);
      $message = 'Gallery header saved successfully.';
    } elseif ($action === 'save') {
      $title = trim((string)($_POST['title'] ?? ''));
      $alt = trim((string)($_POST['alt_text'] ?? ''));
      $category = trim((string)($_POST['category'] ?? ''));
      $sort = (int)($_POST['sort_order'] ?? 0);
      if (empty($_FILES['image']) || (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Please choose an image before clicking Upload.');
      }
      $path = admin_upload('image', 'gallery');
      if (!$path) throw new RuntimeException('The image upload did not return a saved file path.');
      $st = $p->prepare("INSERT INTO gallery (title,image_path,alt_text,category,sort_order,status) VALUES (?,?,?,?,?,1)");
      $st->execute([$title, $path, $alt, $category, $sort]);
      $message = 'Image uploaded and saved to the gallery successfully.';
    } elseif ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id < 1) throw new RuntimeException('Invalid gallery image selected.');
      $st = $p->prepare("SELECT image_path FROM gallery WHERE id=?");
      $st->execute([$id]);
      $path = $st->fetchColumn();
      if ($path === false) throw new RuntimeException('Gallery image was not found.');
      if (function_exists('admin_delete_image')) admin_delete_image($path);
      $p->prepare("DELETE FROM gallery WHERE id=?")->execute([$id]);
      $message = 'Gallery image deleted.';
    } else {
      throw new RuntimeException('Unknown gallery action. Please refresh and try again.');
    }
  } catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'error';
    error_log('BTA Gallery admin error: '.$e->getMessage());
  }
}

$rows = $p->query("SELECT * FROM gallery ORDER BY sort_order ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
$hero = $p->query("SELECT * FROM gallery_page_settings WHERE id=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$page_title = 'Gallery';
require __DIR__.'/includes/header.php';
?>
<?php if ($message !== ''): ?>
  <div class="form" role="alert" style="margin-bottom:18px;border-left:4px solid <?= $messageType === 'error' ? '#dc2626' : '#16a34a' ?>;">
    <strong><?=admin_e($messageType === 'error' ? 'Unable to save' : 'Success')?></strong>
    <p><?=admin_e($message)?></p>
  </div>
<?php endif; ?>

<div class="form" style="margin-bottom:20px">
  <div class="head"><h2>Gallery Page Header</h2></div>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=admin_csrf()?>">
    <input type="hidden" name="act" value="save_header">
    <div class="formgrid">
      <div class="field"><label>Hero Kicker</label><input name="hero_kicker" value="<?=admin_e($hero['hero_kicker'] ?? 'PHOTO GALLERY')?>"></div>
      <div class="field"><label>Hero Title</label><input name="hero_title" value="<?=admin_e($hero['hero_title'] ?? 'FEEL THE')?>"></div>
      <div class="field"><label>Hero Accent</label><input name="hero_accent" value="<?=admin_e($hero['hero_accent'] ?? 'ADRENALINE.')?>"></div>
      <div class="field full"><label>Hero Description</label><textarea name="hero_description"><?=admin_e($hero['hero_description'] ?? 'A glimpse of the landscapes, tracks, and outdoor moments that inspire every Bali Tarantula expedition.')?></textarea></div>
      <div class="field full">
        <label>Hero Background Image</label>
        <input type="file" name="hero_image" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
        <?php if (!empty($hero['hero_image'])): ?><small class="muted-line">Current: <?=admin_e($hero['hero_image'])?></small><?php endif; ?>
      </div>
    </div>
    <div class="actions"><button class="btn" type="submit">Save Header</button></div>
  </form>
</div>

<div class="form">
  <div class="head"><h2>Upload Gallery Image</h2></div>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=admin_csrf()?>">
    <input type="hidden" name="act" value="save">
    <div class="formgrid">
      <div class="field"><label>Image</label><input required type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif"></div>
      <div class="field"><label>Title</label><input name="title" maxlength="255"></div>
      <div class="field"><label>Alt Text</label><input name="alt_text" maxlength="500"></div>
      <div class="field"><label>Category</label><input name="category" placeholder="ATV, Rafting, Swing..." maxlength="120"></div>
      <div class="field"><label>Sort Order</label><input type="number" name="sort_order" value="0"></div>
    </div>
    <div class="actions"><button class="btn" type="submit">Upload &amp; Save Image</button></div>
    <p class="muted-line">Accepted formats: JPG, PNG, WebP and GIF. Maximum file size: 20 MB (subject to hosting PHP upload limits).</p>
  </form>
</div>

<div class="section tablebox">
  <table class="table">
    <tr><th>Image</th><th>Title</th><th>Category</th><th>Action</th></tr>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td><img class="thumb" src="../<?=admin_e($row['image_path'])?>" alt="<?=admin_e($row['alt_text'] ?? '')?>"></td>
        <td><?=admin_e($row['title'] ?? '')?><br><span class="small"><?=admin_e($row['alt_text'] ?? '')?></span></td>
        <td><?=admin_e($row['category'] ?? '')?></td>
        <td><form method="post" onsubmit="return confirm('Delete this gallery image?')"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><button class="btn red" type="submit">Delete</button></form></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4">No gallery images uploaded yet.</td></tr><?php endif; ?>
  </table>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
