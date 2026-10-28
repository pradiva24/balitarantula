<?php
require __DIR__.'/includes/auth.php';
$p = db();

$fields = [
    'floating_whatsapp_number',
    'floating_whatsapp_text',
    'floating_whatsapp_message',
    'floating_email',
    'floating_email_text',
    'floating_phone',
    'floating_phone_text',
];

$defaults = [
    'floating_whatsapp_number' => '',
    'floating_whatsapp_text' => 'WhatsApp',
    'floating_whatsapp_message' => 'Hello Manuaba Crystalfall, I would like to ask about your experiences.',
    'floating_email' => '',
    'floating_email_text' => 'Email Us',
    'floating_phone' => '',
    'floating_phone_text' => 'Call Us',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf();
    foreach ($fields as $key) {
        $value = trim((string)($_POST[$key] ?? ''));
        $st = $p->prepare("SELECT id FROM settings WHERE setting_key=?");
        $st->execute([$key]);
        $id = $st->fetchColumn();
        if ($id) {
            $p->prepare("UPDATE settings SET setting_value=?, updated_at=NOW() WHERE id=?")->execute([$value, $id]);
        } else {
            $p->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?)")->execute([$key, $value]);
        }
    }
    admin_flash('success', 'Floating contact settings saved successfully.');
    header('Location: floating-contact.php');
    exit;
}

$cfg = [];
$placeholders = implode(',', array_fill(0, count($fields), '?'));
$st = $p->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
$st->execute($fields);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $cfg[$row['setting_key']] = $row['setting_value'];
}
foreach ($defaults as $key => $value) {
    if (!array_key_exists($key, $cfg)) $cfg[$key] = $value;
}

$page_title = 'Floating Contact';
require __DIR__.'/includes/header.php';
?>
<style>
.fc-wrap{max-width:920px;margin:0 auto}
.fc-intro{margin:-4px 0 22px;color:#6b7c74;line-height:1.6}
.fc-section{background:#fff;border:1px solid #dfe6e2;border-radius:13px;padding:22px;margin-bottom:18px;box-shadow:0 4px 18px rgba(16,50,37,.05)}
.fc-section h2{margin:0 0 5px;font-size:18px;color:#17352a}
.fc-section>p{margin:0 0 18px;color:#6b7c74;font-size:13px;line-height:1.5}
.fc-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.fc-field{margin-bottom:16px}.fc-field:last-child{margin-bottom:0}
.fc-field label{display:block;font-size:13px;font-weight:700;margin-bottom:7px;color:#17352a}
.fc-field input,.fc-field textarea{width:100%;border:1px solid #ccd8d2;border-radius:9px;padding:11px 12px;font:inherit;color:#17352a;background:#fff;box-sizing:border-box}
.fc-field input:focus,.fc-field textarea:focus{outline:none;border-color:#4caf5a;box-shadow:0 0 0 3px rgba(76,175,90,.12)}
.fc-field textarea{min-height:100px;resize:vertical}
.fc-hint{font-size:12px;color:#6b7c74;margin-top:6px;line-height:1.45}
.fc-actions{display:flex;justify-content:flex-end;margin:4px 0 20px}
.fc-save{border:0;border-radius:9px;padding:12px 20px;background:#174b35;color:#fff;font-weight:700;cursor:pointer}
.fc-save:hover{background:#123d2b}
.fc-note{padding:13px 15px;border-radius:9px;background:#f0f7f3;color:#35584a;font-size:13px;line-height:1.5}
.fc-preview{display:flex;flex-direction:column;align-items:flex-end;gap:8px;margin-top:18px}
.fc-pill{display:inline-flex;align-items:center;gap:9px;border-radius:999px;padding:10px 17px;background:#4caf5a;color:#fff;font-weight:700;font-size:14px;box-shadow:0 5px 15px rgba(0,0,0,.12)}
.fc-pill svg{width:17px;height:17px}.fc-pill.email{background:#28634c}.fc-pill.phone{background:#174b35}
@media(max-width:680px){.fc-row{grid-template-columns:1fr}.fc-preview{align-items:stretch}.fc-pill{justify-content:center}}
</style>

<div class="fc-wrap">
    <p class="fc-intro">Manage the floating contact buttons shown on the website. Leave a contact field empty and that button will not appear.</p>

    <form class="form" method="post">
        <input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>">

        <section class="fc-section">
            <h2>WhatsApp</h2>
            <p>Shows only when the WhatsApp number is filled in.</p>
            <div class="fc-field">
                <label>WhatsApp Number</label>
                <input name="floating_whatsapp_number" value="<?=admin_e($cfg['floating_whatsapp_number'])?>" placeholder="6281234567890">
                <div class="fc-hint">Use international format without +, spaces or dashes. Example: 6281234567890</div>
            </div>
            <div class="fc-field">
                <label>Button Text</label>
                <input name="floating_whatsapp_text" value="<?=admin_e($cfg['floating_whatsapp_text'])?>" placeholder="WhatsApp">
            </div>
            <div class="fc-field">
                <label>Chat Message</label>
                <textarea name="floating_whatsapp_message" placeholder="Hello Manuaba Crystalfall..."><?=admin_e($cfg['floating_whatsapp_message'])?></textarea>
                <div class="fc-hint">This message is automatically inserted when visitors click the WhatsApp floating button.</div>
            </div>
        </section>

        <section class="fc-section">
            <h2>Email</h2>
            <p>Shows only when the email address is filled in.</p>
            <div class="fc-row">
                <div class="fc-field">
                    <label>Email Address</label>
                    <input type="email" name="floating_email" value="<?=admin_e($cfg['floating_email'])?>" placeholder="info@manuabacrystalfall.com">
                </div>
                <div class="fc-field">
                    <label>Button Text</label>
                    <input name="floating_email_text" value="<?=admin_e($cfg['floating_email_text'])?>" placeholder="Email Us">
                </div>
            </div>
        </section>

        <section class="fc-section">
            <h2>Phone</h2>
            <p>Shows only when the phone number is filled in.</p>
            <div class="fc-row">
                <div class="fc-field">
                    <label>Phone Number</label>
                    <input name="floating_phone" value="<?=admin_e($cfg['floating_phone'])?>" placeholder="+62 812 3456 7890">
                </div>
                <div class="fc-field">
                    <label>Button Text</label>
                    <input name="floating_phone_text" value="<?=admin_e($cfg['floating_phone_text'])?>" placeholder="Call Us">
                </div>
            </div>
        </section>

        <div class="fc-actions">
            <button class="fc-save" type="submit">Save Floating Contact</button>
        </div>
    </form>

    <div class="fc-note"><strong>Display rule:</strong> WhatsApp, Email and Phone are independent. If a number or email is blank, its floating button is automatically hidden. The other buttons continue to display normally.</div>

    <div class="fc-preview" aria-label="Preview">
        <?php if(trim((string)$cfg['floating_whatsapp_number'])!==''): ?>
            <span class="fc-pill"><i class="fa-brands fa-whatsapp"></i><?=admin_e($cfg['floating_whatsapp_text'])?></span>
        <?php endif; ?>
        <?php if(trim((string)$cfg['floating_email'])!==''): ?>
            <span class="fc-pill email"><i class="fa-regular fa-envelope"></i><?=admin_e($cfg['floating_email_text'])?></span>
        <?php endif; ?>
        <?php if(trim((string)$cfg['floating_phone'])!==''): ?>
            <span class="fc-pill phone"><i class="fa-solid fa-phone"></i><?=admin_e($cfg['floating_phone_text'])?></span>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__.'/includes/footer.php'; ?>
