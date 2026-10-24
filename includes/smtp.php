<?php
declare(strict_types=1);
require_once __DIR__ . '/site-settings.php';

/**
 * Minimal SMTP transport for the admin's test-email action.
 * Existing booking emails should call this helper explicitly before they use SMTP settings.
 */
function bta_smtp_send(string $to, string $subject, string $body): void
{
    $host = trim(bta_setting('smtp_host'));
    $port = (int)bta_setting('smtp_port', '587');
    $encryption = strtolower(trim(bta_setting('smtp_encryption', 'tls')));
    $username = trim(bta_setting('smtp_username'));
    $passwordStored = bta_setting('smtp_password_encrypted');
    $password = $passwordStored !== '' ? bta_decrypt_setting_secret($passwordStored) : '';
    $from = trim(bta_setting('smtp_from_email', bta_setting('contact_email')));
    $fromName = trim(bta_setting('smtp_from_name', bta_setting('site_title', 'Bali Tarantula Adventure')));
    if ($host === '' || !filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Complete a valid SMTP host, sender email, and recipient email first.');
    }
    if (!in_array($encryption, ['tls','ssl','none'], true) || $port < 1 || $port > 65535) {
        throw new RuntimeException('SMTP encryption or port is invalid.');
    }
    $target = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $context = stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]]);
    $socket = @stream_socket_client($target, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) throw new RuntimeException('SMTP connection failed. Check host, port, and encryption.');
    stream_set_timeout($socket, 15);
    $read = static function(array $expected) use ($socket): string {
        $response = '';
        do {
            $line = fgets($socket, 515);
            if ($line === false) throw new RuntimeException('SMTP server closed the connection.');
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');
        $code = (int)substr($response, -strlen($line), 3);
        if (!in_array($code, $expected, true)) throw new RuntimeException('SMTP server rejected a command (code ' . $code . ').');
        return $response;
    };
    $write = static function(string $line) use ($socket): void {
        if (fwrite($socket, $line . "\r\n") === false) throw new RuntimeException('Could not write to SMTP server.');
    };
    try {
        $read([220]);
        $write('EHLO ' . (gethostname() ?: 'localhost')); $read([250]);
        if ($encryption === 'tls') {
            $write('STARTTLS'); $read([220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('Could not establish encrypted SMTP connection.');
            $write('EHLO ' . (gethostname() ?: 'localhost')); $read([250]);
        }
        if ($username !== '') {
            if ($password === '') throw new RuntimeException('SMTP password is missing. Re-enter it in Settings.');
            $write('AUTH LOGIN'); $read([334]);
            $write(base64_encode($username)); $read([334]);
            $write(base64_encode($password)); $read([235]);
        }
        $write('MAIL FROM:<' . $from . '>'); $read([250]);
        $write('RCPT TO:<' . $to . '>'); $read([250,251]);
        $write('DATA'); $read([354]);
        $safeSubject = trim(str_replace(["\r","\n"], '', $subject));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($safeSubject) . '?=';
        $encodedFrom = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $message = "Date: " . date(DATE_RFC2822) . "\r\n"
            . "From: " . $encodedFrom . " <" . $from . ">\r\n"
            . "To: <" . $to . ">\r\n"
            . "Subject: " . $encodedSubject . "\r\n"
            . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
            . str_replace(["\r\n","\r"], "\n", $body);
        $message = preg_replace('/(?m)^\./', '..', $message) ?? $message;
        fwrite($socket, str_replace("\n", "\r\n", rtrim($message, "\r\n")) . "\r\n.\r\n");
        $read([250]);
        $write('QUIT'); $read([221]);
    } finally {
        fclose($socket);
    }
}
