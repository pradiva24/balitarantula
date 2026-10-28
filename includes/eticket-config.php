<?php
/** Central editable E-Ticket content. Stored in the existing settings table. */
function eticket_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $defaults = json_decode(<<<'JSON'
{"eticket_ticket_code_prefix":"AUS","eticket_invoice_code_prefix":"AUS-INV","eticket_brand":"{{site_name}}","eticket_success_title":"Booking Successful!","eticket_success_subtitle":"Thank you for your booking!","eticket_success_message":"Booking received successfully.","eticket_email_message":"A confirmation has been sent to your email.","eticket_trust_1_title":"Confirmation Sent","eticket_trust_1_text":"Booking received","eticket_trust_2_title":"Your E-Ticket","eticket_trust_2_text":"Ready below","eticket_trust_3_title":"Secure Booking","eticket_trust_3_text":"Your data is protected","eticket_trust_4_title":"Need Help?","eticket_trust_4_text":"Contact our team","eticket_booking_details_title":"Booking Details","eticket_activity_location":"{{site_name}} – Bali","eticket_ticket_title":"Your E-Ticket","eticket_ticket_subtitle":"Please show this e-ticket (digital or printed) at the entrance.","eticket_ticket_brand":"{{site_name}}","eticket_ticket_location":"Bali – Indonesia","eticket_ticket_note_title":"See you soon!","eticket_ticket_note_text":"Get ready for an amazing experience. Please keep this ticket for your visit.","eticket_qr_help":"Show this booking ID at the entrance. Keep your confirmation email for reference.","eticket_company_title":"{{site_name}}","eticket_company_location_label":"Location","eticket_company_phone_label":"WhatsApp / Phone","eticket_print_eyebrow":"Explore the real Bali","eticket_print_title":"E-TICKET / BOOKING VOUCHER","eticket_print_activity_location_title":"Activity Location","eticket_print_important_title":"Important Information","eticket_print_help_title":"Need Help?","eticket_info_1":"Arrive 15 minutes before your scheduled time.","eticket_info_2":"Show this e-ticket at the entrance.","eticket_info_3":"This ticket is non-transferable unless approved by our team.","eticket_info_4":"Bring a valid ID if required.","eticket_bottom_title":"Important Information","eticket_bottom_1":"Please arrive 15 minutes before your scheduled activity.","eticket_bottom_2":"This booking is non-transferable unless approved by our team.","eticket_bottom_3":"Bring a valid ID if required for your selected activity.","eticket_bottom_4":"For changes or assistance, please contact our support team.","eticket_support_title":"Need Help?","eticket_support_text":"Our team is ready to assist you with your booking.","eticket_support_button":"Contact Support","eticket_footer_title":"THANK YOU FOR CHOOSING {{site_name_upper}}!","eticket_footer_subtitle":"We look forward to welcoming you!","eticket_footer_right":"NATURE • ADVENTURE • HAPPINESS"}
JSON
, true);
    if (!is_array($defaults)) $defaults = [];
    $raw = (string)setting('eticket_config', '');
    $saved = json_decode($raw, true);
    if (is_array($saved)) {
        foreach ($saved as $k=>$v) {
            if (array_key_exists($k,$defaults)) $defaults[$k] = is_string($v) ? $v : (string)$v;
        }
    }
    $siteName = trim((string)setting('site_name','Manuaba Crystalfall'));
    foreach ($defaults as $k=>$v) {
        $defaults[$k] = str_replace(['{{site_name}}','{{site_name_upper}}'], [$siteName, strtoupper($siteName)], (string)$v);
    }
    return $cfg = $defaults;
}
function eticket_text(string $key, string $fallback=''): string {
    $cfg = eticket_config();
    return (string)($cfg[$key] ?? $fallback);
}
