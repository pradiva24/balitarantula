<?php
require __DIR__.'/includes/auth.php';
require_once __DIR__.'/../includes/payment-methods.php';
require_once __DIR__.'/../config/xendit.php';
require_once __DIR__.'/../config/doku.php';
$p=db();

function pg_save(PDO $p, string $key, string $value): void {
    $st=$p->prepare("SELECT id FROM settings WHERE setting_key=?");
    $st->execute([$key]);
    $id=$st->fetchColumn();
    if($id){
        $p->prepare("UPDATE settings SET setting_value=?,updated_at=NOW() WHERE id=?")->execute([$value,$id]);
    }else{
        $p->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?)")->execute([$key,$value]);
    }
}

$midtransEnv=setting('gateway_environment','sandbox');
$midtransServer=setting('gateway_server_key','');
$midtransClient=setting('gateway_client_key','');
$midtransWebhook=setting('gateway_webhook_secret','');
$xenditMode=setting('xendit_mode','sandbox');
$xenditSecret=setting('xendit_secret_key','');
$xenditWebhook=setting('xendit_webhook_token','');
$dokuMode=setting('doku_mode','sandbox');
$dokuClient=setting('doku_client_id','');
$dokuSecret=setting('doku_secret_key','');

if($_SERVER['REQUEST_METHOD']==='POST'){
    admin_verify_csrf();

    // Payment-method switches: unchecked boxes are explicitly saved as OFF.
    $toggleKeys=['payment_method_pay_at_venue','payment_method_bank_transfer','payment_method_midtrans','payment_method_doku','payment_method_xendit'];
    foreach($toggleKeys as $k) pg_save($p,$k,isset($_POST[$k])?'1':'0');

    $fields=[
        'payment_bank_name','payment_bank_account','payment_bank_holder','payment_instructions',
        'gateway_environment','gateway_server_key','gateway_client_key','gateway_webhook_secret',
        'xendit_mode','xendit_secret_key','xendit_webhook_token','xendit_success_url','xendit_failure_url',
        'doku_mode','doku_client_id','doku_secret_key','doku_callback_url','doku_notification_url'
    ];

    foreach($fields as $k){
        if(str_contains($k,'secret') || str_contains($k,'key') || str_contains($k,'token')){
            $v=(string)($_POST[$k]??'');
            if($v==='') continue; // keep existing secret
        }else{
            $v=trim((string)($_POST[$k]??''));
        }
        pg_save($p,$k,$v);
    }

    // Keep the legacy single-gateway selector compatible with the existing Midtrans integration.
    // It is no longer presented as the source of truth for the payment switches.
    $midOn=isset($_POST['payment_method_midtrans']);
    pg_save($p,'payment_gateway',$midOn?'midtrans':'manual');

    // Keep legacy comma-separated payment_methods in sync for older pages/templates.
    $legacy=[];
    if(isset($_POST['payment_method_bank_transfer'])) $legacy[]='Bank Transfer';
    if(isset($_POST['payment_method_pay_at_venue'])) $legacy[]='Cash / Pay at Venue';
    if(isset($_POST['payment_method_midtrans'])) $legacy[]='Midtrans';
    if(isset($_POST['payment_method_doku'])) $legacy[]='DOKU';
    if(isset($_POST['payment_method_xendit'])) $legacy[]='Xendit';
    pg_save($p,'payment_methods',implode(',',$legacy));

    admin_flash('success','Payment settings saved.');
    header('Location: payment-gateway.php');
    exit;
}

$midtransEnv=setting('gateway_environment','sandbox');
$midtransServer=setting('gateway_server_key','');
$midtransClient=setting('gateway_client_key','');
$midtransWebhook=setting('gateway_webhook_secret','');
$xenditMode=setting('xendit_mode','sandbox');
$xenditSecret=setting('xendit_secret_key','');
$xenditWebhook=setting('xendit_webhook_token','');
$dokuMode=setting('doku_mode','sandbox');
$dokuClient=setting('doku_client_id','');
$dokuSecret=setting('doku_secret_key','');

$paymentMethods=payment_method_toggles();
$midtransReady=$midtransServer!=='' && $midtransClient!=='';
$xenditReady=xendit_is_configured();
$dokuReady=doku_is_configured();
$midtransWebhookUrl=rtrim(BASE_URL,'/').'/midtrans-notification.php';
$xenditSuccess=setting('xendit_success_url',rtrim(setting('site_url',BASE_URL),'/').'/booking-success');
$xenditFailure=setting('xendit_failure_url',rtrim(setting('site_url',BASE_URL),'/').'/booking');
$dokuCallback=setting('doku_callback_url',rtrim(setting('site_url',BASE_URL),'/').'/booking-success');
$dokuNotification=setting('doku_notification_url',rtrim(setting('site_url',BASE_URL),'/').'/doku-notification.php');
$page_title='Payment Gateway';
require __DIR__.'/includes/header.php';
?>
<style>
.pg-wrap{max-width:1000px}.pg-hero{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:18px}.pg-hero h2{margin:0 0 6px;font-size:22px}.pg-hero p{margin:0;max-width:760px}.pg-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(280px,.8fr);gap:18px}.pg-card{background:#fff;border:1px solid #eaecf0;border-radius:14px;padding:20px;box-shadow:0 1px 2px rgba(16,24,40,.04)}.pg-card h3{margin:0 0 5px;font-size:16px}.pg-desc{margin:0 0 16px}.pg-section{padding-bottom:18px;margin-bottom:18px;border-bottom:1px solid #eaecf0}.pg-section:last-child{padding-bottom:0;margin-bottom:0;border-bottom:0}.pg-field{display:grid;gap:7px;margin-bottom:14px}.pg-field:last-child{margin-bottom:0}.pg-field label{font-weight:700;font-size:12px;color:#344054}.pg-field input,.pg-field select,.pg-field textarea{font:inherit;border:1px solid #d0d5dd;border-radius:8px;padding:11px;width:100%;background:#fff;outline:none;box-sizing:border-box}.pg-field input:focus,.pg-field select:focus,.pg-field textarea:focus{border-color:#667085;box-shadow:0 0 0 3px rgba(16,24,40,.07)}.pg-field textarea{min-height:90px;resize:vertical}.pg-input-wrap{position:relative}.pg-input-wrap input{padding-right:72px}.pg-toggle{position:absolute;right:7px;top:50%;transform:translateY(-50%);border:0;background:#eef2f6;color:#475467;border-radius:6px;padding:6px 8px;font-size:10px;font-weight:700;cursor:pointer}.pg-help{font-size:11px;color:#667085;line-height:1.6}.pg-help code{font-size:11px;background:#f8fafc;border:1px solid #eaecf0;padding:3px 6px;border-radius:6px;word-break:break-all}.pg-note{padding:12px 13px;border-radius:10px;background:#fffaeb;color:#7a3e0b;border:1px solid #fedf89;font-size:11px;line-height:1.6}.pg-note strong{color:#66330a}.pg-meta{display:grid;gap:10px}.pg-meta-item{padding:12px;border:1px solid #eaecf0;border-radius:10px;background:#f9fafb}.pg-meta-item span{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.7px;color:#98a2b3;font-weight:700}.pg-meta-item strong{display:block;margin-top:4px;font-size:12px;color:#344054;word-break:break-word}.pg-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:18px}.pg-actions .small{margin:0}.payment-toggle-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.payment-toggle-card{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:12px 14px;border:1px solid #e1e8e3;border-radius:10px;background:#fbfdfb}.payment-toggle-card strong{display:block;font-size:13px;color:#173b2d}.payment-toggle-card small{display:block;margin-top:3px;color:#718078;font-size:11px;line-height:1.35}.payment-switch{position:relative;display:inline-block;width:42px;height:24px;flex:0 0 42px}.payment-switch input{opacity:0;width:0;height:0}.payment-slider{position:absolute;inset:0;background:#cbd5ce;border-radius:999px;cursor:pointer;transition:.2s}.payment-slider:before{content:"";position:absolute;width:18px;height:18px;left:3px;top:3px;background:#fff;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.18);transition:.2s}.payment-switch input:checked+.payment-slider{background:#2c6a48}.payment-switch input:checked+.payment-slider:before{transform:translateX(18px)}.gateway-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px}.gateway-head strong{display:block;color:#173b2d;font-size:14px}.gateway-head small{display:block;color:#718078;font-size:11px;margin-top:3px}.gateway-status{padding:4px 8px;border-radius:999px;font-size:10px;font-weight:800}.gateway-status.on{background:#e6f5ea;color:#20613d}.gateway-status.off{background:#f1f3f1;color:#718078}.gateway-card{border:1px solid #e1e8e3;border-radius:12px;background:#fbfdfb;padding:14px;margin-top:12px}.gateway-card:first-child{margin-top:0}.gateway-inner{margin-top:0}.section-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.section-grid .full{grid-column:1/-1}.section-title{display:flex;justify-content:space-between;align-items:center;gap:12px}.section-title h3{margin:0}.status-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;background:#f8faf8;border:1px solid #e6ece8;border-radius:9px;margin-bottom:12px;font-size:12px}.status-row strong{color:#173b2d}.muted-box{padding:10px 12px;border-radius:8px;background:#f8fafc;border:1px solid #eaecf0;font-size:11px;color:#667085;line-height:1.55}
@media(max-width:820px){.pg-grid,.section-grid{grid-template-columns:1fr}.section-grid .full{grid-column:auto}.pg-hero{display:block}.payment-toggle-grid{grid-template-columns:1fr}.pg-actions{align-items:flex-start;flex-direction:column}}
</style>

<div class="pg-wrap">
  <div class="pg-hero">
    <div><h2>Payment Gateway</h2><p class="small">Manage every customer payment option from one page. Bank transfer details, payment-method switches, Midtrans, DOKU and Xendit are all configured here.</p></div>
  </div>

  <div class="pg-grid">
    <div>
      <div class="pg-card">
        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>">

          <div class="pg-section">
            <div class="section-title"><h3>Payment Methods</h3></div>
            <p class="small pg-desc">Turn customer payment options on or off. Secret credentials are configured in the provider sections below.</p>
            <div class="payment-toggle-grid">
              <?php
              $toggleLabels=[
                'pay_at_venue'=>['Pay at Venue','Customer can pay at the venue.'],
                'bank_transfer'=>['Bank Transfer','Show your configured bank transfer details.'],
                'midtrans'=>['Midtrans','Enable after Midtrans credentials are configured.'],
                'doku'=>['DOKU','Enable when DOKU checkout is connected.'],
                'xendit'=>['Xendit','Enable when Xendit checkout is connected.'],
              ];
              foreach($toggleLabels as $key=>$meta):
              ?>
              <label class="payment-toggle-card"><span><strong><?=admin_e($meta[0])?></strong><small><?=admin_e($meta[1])?></small></span><span class="payment-switch"><input type="checkbox" name="payment_method_<?=admin_e($key)?>" value="1" <?=payment_method_is_enabled($key)?'checked':''?>><span class="payment-slider"></span></span></label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="pg-section">
            <h3>Bank Transfer</h3>
            <p class="small pg-desc">These details are used wherever the customer is shown bank-transfer payment instructions.</p>
            <div class="section-grid">
              <div class="pg-field"><label>Bank Name</label><input name="payment_bank_name" value="<?=admin_e(setting('payment_bank_name','BCA'))?>"></div>
              <div class="pg-field"><label>Bank Account</label><input name="payment_bank_account" value="<?=admin_e(setting('payment_bank_account',''))?>"></div>
              <div class="pg-field full"><label>Account Holder</label><input name="payment_bank_holder" value="<?=admin_e(setting('payment_bank_holder',site_brand_name()))?>"></div>
              <div class="pg-field full"><label>Payment Instructions</label><textarea name="payment_instructions"><?=admin_e(setting('payment_instructions','Please transfer the exact booking total and upload your proof.'))?></textarea></div>
            </div>
          </div>

          <div class="pg-section">
            <div class="section-title"><h3>Midtrans</h3><span class="gateway-status <?=($midtransReady?'on':'off')?>"><?=($midtransReady?'Configured':'Not Configured')?></span></div>
            <p class="small pg-desc">Existing Midtrans integration. The payment-method switch controls whether it can be offered to customers.</p>
            <div class="section-grid">
              <div class="pg-field"><label>Environment</label><select name="gateway_environment"><option value="sandbox" <?=$midtransEnv==='sandbox'?'selected':''?>>Sandbox / Testing</option><option value="production" <?=$midtransEnv==='production'?'selected':''?>>Production / Live</option></select></div>
              <div class="pg-field"><label>Server Key</label><div class="pg-input-wrap"><input id="mid_server" type="password" name="gateway_server_key" value="" placeholder="<?= $midtransServer!=='' ? 'Saved — leave blank to keep' : 'Enter Server Key' ?>" autocomplete="new-password"><button class="pg-toggle" type="button" data-target="mid_server">Show</button></div></div>
              <div class="pg-field"><label>Client Key</label><div class="pg-input-wrap"><input id="mid_client" type="password" name="gateway_client_key" value="" placeholder="<?= $midtransClient!=='' ? 'Saved — leave blank to keep' : 'Enter Client Key' ?>" autocomplete="new-password"><button class="pg-toggle" type="button" data-target="mid_client">Show</button></div></div>
              <div class="pg-field"><label>Webhook Secret <small>(optional)</small></label><div class="pg-input-wrap"><input id="mid_webhook" type="password" name="gateway_webhook_secret" value="" placeholder="<?= $midtransWebhook!=='' ? 'Saved — leave blank to keep' : 'Optional' ?>" autocomplete="new-password"><button class="pg-toggle" type="button" data-target="mid_webhook">Show</button></div></div>
              <div class="pg-field full"><label>Notification URL</label><input value="<?=admin_e($midtransWebhookUrl)?>" readonly></div>
            </div>
          </div>

          <div class="pg-section">
            <div class="section-title"><h3>Xendit</h3><span class="gateway-status <?=($xenditReady?'on':'off')?>"><?=($xenditReady?'Configured':'Not Configured')?></span></div>
            <p class="small pg-desc">Xendit Payment Session configuration. Credentials stay server-side.</p>
            <div class="section-grid">
              <div class="pg-field"><label>Mode</label><select name="xendit_mode"><option value="sandbox" <?=$xenditMode==='sandbox'?'selected':''?>>Sandbox / Test</option><option value="live" <?=$xenditMode==='live'?'selected':''?>>Live / Production</option></select></div>
              <div class="pg-field"><label>Secret API Key</label><div class="pg-input-wrap"><input id="x_secret" type="password" name="xendit_secret_key" value="" placeholder="<?= $xenditSecret!=='' ? 'Saved — leave blank to keep' : 'Enter Secret API Key' ?>" autocomplete="new-password"><button class="pg-toggle" type="button" data-target="x_secret">Show</button></div></div>
              <div class="pg-field"><label>Webhook Verification Token</label><div class="pg-input-wrap"><input id="x_webhook" type="password" name="xendit_webhook_token" value="" placeholder="<?= $xenditWebhook!=='' ? 'Saved — leave blank to keep' : 'Optional until webhook is connected' ?>" autocomplete="new-password"><button class="pg-toggle" type="button" data-target="x_webhook">Show</button></div></div>
              <div class="pg-field full"><label>Success Return URL</label><input type="url" name="xendit_success_url" value="<?=admin_e($xenditSuccess)?>"></div>
              <div class="pg-field full"><label>Failure / Cancel URL</label><input type="url" name="xendit_failure_url" value="<?=admin_e($xenditFailure)?>"></div>
              <div class="pg-field full"><label>API Endpoint</label><input value="https://api.xendit.co/sessions" readonly></div>
            </div>
          </div>

          <div class="pg-section">
            <div class="section-title"><h3>DOKU</h3><span class="gateway-status <?=($dokuReady?'on':'off')?>"><?=($dokuReady?'Configured':'Not Configured')?></span></div>
            <p class="small pg-desc">DOKU Checkout configuration. Credentials stay server-side.</p>
            <div class="section-grid">
              <div class="pg-field"><label>Mode</label><select name="doku_mode"><option value="sandbox" <?=$dokuMode==='sandbox'?'selected':''?>>Sandbox / Test</option><option value="live" <?=$dokuMode==='live'?'selected':''?>>Live / Production</option></select></div>
              <div class="pg-field"><label>Client ID</label><input name="doku_client_id" value="<?=admin_e($dokuClient)?>" autocomplete="off"></div>
              <div class="pg-field"><label>Secret Key</label><div class="pg-input-wrap"><input id="d_secret" type="password" name="doku_secret_key" value="" placeholder="<?= $dokuSecret!=='' ? 'Saved — leave blank to keep' : 'Enter Secret Key' ?>" autocomplete="new-password"><button class="pg-toggle" type="button" data-target="d_secret">Show</button></div></div>
              <div class="pg-field full"><label>Callback URL</label><input type="url" name="doku_callback_url" value="<?=admin_e($dokuCallback)?>"></div>
              <div class="pg-field full"><label>Notification URL</label><input type="url" name="doku_notification_url" value="<?=admin_e($dokuNotification)?>"></div>
              <div class="pg-field full"><label>API Endpoint</label><input value="<?=admin_e(doku_api_endpoint())?>" readonly></div>
            </div>
          </div>

          <div class="pg-actions"><p class="small">Save once after configuring the switches and gateway credentials. Blank secret/key fields keep existing saved credentials.</p><button class="btn" type="submit">Save Payment Settings</button></div>
        </form>
      </div>
    </div>

    <div class="pg-meta">
      <div class="pg-card">
        <h3>Payment status</h3><p class="small pg-desc">Current configuration overview.</p>
        <div class="pg-meta">
          <div class="pg-meta-item"><span>Pay at Venue</span><strong><?=payment_method_is_enabled('pay_at_venue')?'ON':'OFF'?></strong></div>
          <div class="pg-meta-item"><span>Bank Transfer</span><strong><?=payment_method_is_enabled('bank_transfer')?'ON':'OFF'?></strong></div>
          <div class="pg-meta-item"><span>Midtrans</span><strong><?=payment_method_is_enabled('midtrans')?($midtransReady?'ON':'ON — incomplete'):'OFF'?></strong></div>
          <div class="pg-meta-item"><span>DOKU</span><strong><?=payment_method_is_enabled('doku')?($dokuReady?'ON':'ON — incomplete'):'OFF'?></strong></div>
          <div class="pg-meta-item"><span>Xendit</span><strong><?=payment_method_is_enabled('xendit')?($xenditReady?'ON':'ON — incomplete'):'OFF'?></strong></div>
        </div>
      </div>

      <div class="pg-card">
        <h3>Bank transfer preview</h3><p class="small pg-desc">Values saved here are the values used by the booking/payment pages.</p>
        <div class="pg-meta">
          <div class="pg-meta-item"><span>Bank</span><strong><?=admin_e(setting('payment_bank_name','BCA'))?></strong></div>
          <div class="pg-meta-item"><span>Account</span><strong><?=admin_e(setting('payment_bank_account','—') ?: '—')?></strong></div>
          <div class="pg-meta-item"><span>Holder</span><strong><?=admin_e(setting('payment_bank_holder',site_brand_name()))?></strong></div>
        </div>
      </div>

      <div class="pg-card"><div class="muted-box"><strong>Compatibility:</strong> saved legacy payment settings are preserved in the database. Moving them to this page does not delete or reset the existing bank details or gateway credentials.</div></div>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.pg-toggle').forEach(function(button){
  button.addEventListener('click',function(){
    var input=document.getElementById(button.getAttribute('data-target'));
    if(!input) return;
    var visible=input.type==='text';
    input.type=visible?'password':'text';
    button.textContent=visible?'Show':'Hide';
  });
});
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
