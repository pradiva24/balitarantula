<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
$admin = require_role(['owner','admin','content']);
$pdo = Database::connection();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'activity') {
            $name = trim((string)($_POST['name'] ?? ''));
            $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
            $description = trim((string)($_POST['description'] ?? ''));
            $slug = trim((string)preg_replace('/[^a-z0-9-]+/', '-', $slug), '-');
            if ($name === '' || $slug === '') throw new DomainException('Activity name and slug are required.');
            $stmt = $pdo->prepare("INSERT INTO activities (name,slug,short_description,status) VALUES (:name,:slug,:description,'published')");
            $stmt->execute(['name'=>$name,'slug'=>$slug,'description'=>$description ?: null]);
            $message = 'Activity created.';
        } elseif ($action === 'package') {
            $activityId = filter_input(INPUT_POST, 'activity_id', FILTER_VALIDATE_INT) ?: 0;
            $name = trim((string)($_POST['name'] ?? ''));
            $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
            $slug = trim((string)preg_replace('/[^a-z0-9-]+/', '-', $slug), '-');
            $price = filter_input(INPUT_POST, 'price_per_person', FILTER_VALIDATE_FLOAT);
            $pickup = isset($_POST['pickup_available']) ? 1 : 0;
            $pickupFee = (float)($_POST['pickup_fee'] ?? 0);
            $duration = filter_input(INPUT_POST, 'duration_minutes', FILTER_VALIDATE_INT) ?: null;
            if ($activityId < 1 || $name === '' || $slug === '' || $price === false || $price < 0 || $pickupFee < 0) {
                throw new DomainException('Check the activity, package name, slug, and non-negative prices.');
            }
            $stmt = $pdo->prepare("INSERT INTO packages (activity_id,name,slug,description,duration_minutes,price_per_person,pickup_available,pickup_fee,min_participants,status) VALUES (:activity,:name,:slug,:description,:duration,:price,:pickup,:pickup_fee,1,'published')");
            $stmt->execute(['activity'=>$activityId,'name'=>$name,'slug'=>$slug,'description'=>trim((string)($_POST['description']??'')) ?: null,'duration'=>$duration,'price'=>$price,'pickup'=>$pickup,'pickup_fee'=>$pickupFee]);
            $message = 'Package created.';
        } else {
            throw new DomainException('Unknown action.');
        }
    } catch (DomainException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Catalog update failed: '.$e->getMessage());
        $error = 'Could not save the record. Check that the slug is unique and try again.';
    }
}
$activities = $pdo->query("SELECT id,name,slug,status FROM activities ORDER BY name")->fetchAll();
$packages = $pdo->query("SELECT p.id,p.name,p.slug,p.price_per_person,p.currency,p.status,a.name activity_name FROM packages p JOIN activities a ON a.id=p.activity_id ORDER BY p.created_at DESC")->fetchAll();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Catalog | Bali Tarantula Admin</title>
<style>:root{--orange:#ff5a1f}*{box-sizing:border-box}body{margin:0;background:#f4f2ed;color:#191919;font:14px/1.5 Arial,sans-serif}.top{background:#151515;color:#fff;padding:18px 24px;display:flex;justify-content:space-between}.top a{color:#fff}.wrap{max-width:1100px;margin:auto;padding:30px 20px}h1{font-size:38px;margin:0}p{color:#666}.cols{display:grid;grid-template-columns:1fr 1fr;gap:18px}.panel{background:#fff;border:1px solid #e2ded6;border-top:3px solid var(--orange);padding:22px;margin:20px 0}.field{display:flex;flex-direction:column;gap:6px;margin:13px 0}.field label{font-size:11px;font-weight:800;letter-spacing:.5px}.field input,.field select,.field textarea{width:100%;padding:11px;border:1px solid #d7d2c9;font:inherit}.field textarea{min-height:75px}.btn{padding:12px 16px;background:var(--orange);border:0;font-weight:900;cursor:pointer}.notice{padding:12px;background:#e5f4e7;margin:14px 0}.err{background:#fde8e5}.tablewrap{overflow-x:auto}table{border-collapse:collapse;width:100%;background:#fff}th,td{text-align:left;padding:12px;border-bottom:1px solid #e6e2da;white-space:nowrap}th{font-size:10px;letter-spacing:.6px}@media(max-width:720px){.cols{grid-template-columns:1fr}.panel{padding:16px}.wrap{padding:24px 14px}}</style><link rel="stylesheet" href="../assets/css/admin-sidebar.css?v=20261024"></head><body><?php require __DIR__ . '/_sidebar.php'; ?><header class="top"><strong>BALI TARANTULA <span style="color:#ff5a1f">ADMIN</span></strong><a href="index.php">← Dashboard</a></header><main class="wrap"><h1>Activities & packages</h1><p>Start by adding an activity, then attach packages and their per-person prices.</p>
<?php if($message):?><div class="notice"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="notice err"><?=e($error)?></div><?php endif;?>
<div class="cols"><section class="panel"><h2>New activity</h2><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="activity"><div class="field"><label>ACTIVITY NAME</label><input name="name" maxlength="160" required placeholder="ATV Adventure"></div><div class="field"><label>URL SLUG</label><input name="slug" required placeholder="atv-adventure"></div><div class="field"><label>SHORT DESCRIPTION</label><textarea name="description" maxlength="500"></textarea></div><button class="btn">CREATE ACTIVITY</button></form></section>
<section class="panel"><h2>New package</h2><?php if(!$activities):?><p>Create an activity first.</p><?php else:?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="package"><div class="field"><label>ACTIVITY</label><select name="activity_id" required><?php foreach($activities as $a):?><option value="<?=(int)$a['id']?>"><?=e($a['name'])?></option><?php endforeach;?></select></div><div class="field"><label>PACKAGE NAME</label><input name="name" maxlength="180" required placeholder="Tarantula Jungle Track"></div><div class="field"><label>URL SLUG</label><input name="slug" required placeholder="tarantula-jungle-track"></div><div class="field"><label>PRICE PER PERSON (IDR)</label><input name="price_per_person" type="number" min="0" step="0.01" required value="0"></div><div class="field"><label>DURATION (MINUTES)</label><input name="duration_minutes" type="number" min="1" step="1"></div><div class="field"><label>PICKUP FEE (IDR)</label><input name="pickup_fee" type="number" min="0" step="0.01" value="0"></div><div class="field"><label><input type="checkbox" name="pickup_available" value="1"> Pickup available</label></div><div class="field"><label>DESCRIPTION</label><textarea name="description"></textarea></div><button class="btn">CREATE PACKAGE</button></form><?php endif;?></section></div>
<section class="panel"><h2>Published catalog</h2><div class="tablewrap"><table><thead><tr><th>ACTIVITY</th><th>PACKAGE</th><th>SLUG</th><th>PRICE / PERSON</th><th>STATUS</th></tr></thead><tbody><?php foreach($packages as $p):?><tr><td><?=e($p['activity_name'])?></td><td><?=e($p['name'])?></td><td><?=e($p['slug'])?></td><td><?=e($p['currency'])?> <?=number_format((float)$p['price_per_person'],2)?></td><td><?=e($p['status'])?></td></tr><?php endforeach;?><?php if(!$packages):?><tr><td colspan="5">No packages yet.</td></tr><?php endif;?></tbody></table></div></section>
<section class="panel"><h2>Activities</h2><div class="tablewrap"><table><thead><tr><th>NAME</th><th>SLUG</th><th>STATUS</th></tr></thead><tbody><?php foreach($activities as $a):?><tr><td><?=e($a['name'])?></td><td><?=e($a['slug'])?></td><td><?=e($a['status'])?></td></tr><?php endforeach;?><?php if(!$activities):?><tr><td colspan="3">No activities yet.</td></tr><?php endif;?></tbody></table></div></section></main></body></html>
