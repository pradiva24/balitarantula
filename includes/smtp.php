<?php
/**
 * Lightweight SMTP sender used by the admin test and booking emails.
 * Supports SMTP over SSL (465), STARTTLS (typically 587), and plain TCP.
 * Keeps a safe human-readable diagnostic in smtp_last_error().
 */
function smtp_last_error(): string {
    return (string)($GLOBALS['_smtp_last_error'] ?? 'Unknown SMTP error.');
}

function smtp_set_error(string $message): void {
    $GLOBALS['_smtp_last_error'] = $message;
}

function smtp_header_value(string $value): string {
    return trim(str_replace(["\r", "\n"], '', $value));
}

function smtp_valid_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        && strpos($email, "\r") === false
        && strpos($email, "\n") === false;
}

function smtp_read_response($fp, ?int &$code = null): string|false {
    $code = null;
    $response = '';
    $firstCode = null;

    while (true) {
        $line = fgets($fp, 4096);
        if ($line === false) {
            $meta = stream_get_meta_data($fp);
            smtp_set_error(!empty($meta['timed_out']) ? 'SMTP server response timed out.' : 'SMTP server closed the connection unexpectedly.');
            return false;
        }

        $response .= $line;
        $lineCode = (int)substr($line, 0, 3);
        if ($lineCode >= 100 && $lineCode <= 599 && $firstCode === null) {
            $firstCode = $lineCode;
        }

        // SMTP multiline reply continues with "250-..." and ends at "250 ...".
        if (strlen($line) >= 4 && substr($line, 3, 1) === ' ') {
            $code = $firstCode ?? $lineCode;
            return $response;
        }

        // Be defensive with malformed single-line replies.
        if (strlen($line) < 4 || ($lineCode < 100 || $lineCode > 599)) {
            $code = $firstCode ?? null;
            return $response;
        }
    }
}

function smtp_expect($fp, array $expected, string $step, ?string &$response = null): bool {
    $code = null;
    $response = smtp_read_response($fp, $code);
    if ($response === false) {
        if (smtp_last_error() === 'Unknown SMTP error.') smtp_set_error($step . ' failed.');
        return false;
    }
    if (!in_array((int)$code, $expected, true)) {
        $single = preg_replace('/\s+/', ' ', trim($response));
        $single = substr($single, 0, 500);
        smtp_set_error($step . ' rejected by server (' . (int)$code . ').' . ($single !== '' ? ' ' . $single : ''));
        return false;
    }
    return true;
}

function smtp_write($fp, string $data, string $step): bool {
    $len = strlen($data);
    $written = 0;
    while ($written < $len) {
        $n = fwrite($fp, substr($data, $written));
        if ($n === false || $n === 0) {
            smtp_set_error($step . ' could not be written to the SMTP server.');
            return false;
        }
        $written += $n;
    }
    return true;
}

function smtp_open(string $host, int $port, string $secure, int $timeout, &$error = '') {
    $error = '';
    $scheme = ($secure === 'ssl') ? 'ssl' : 'tcp';
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'peer_name' => $host,
        ],
    ]);

    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client(
        $scheme . '://' . $host . ':' . $port,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$fp) {
        $error = 'Connection failed' . ($errno ? ' (' . $errno . ')' : '') . ($errstr !== '' ? ': ' . $errstr : '.');
        return false;
    }

    stream_set_timeout($fp, $timeout);
    return $fp;
}

function smtp_ehlo($fp, string $hostname, array &$capabilities = []): bool {
    $capabilities = [];
    if (!smtp_write($fp, 'EHLO ' . $hostname . "\r\n", 'EHLO')) return false;
    $code = null;
    $response = smtp_read_response($fp, $code);
    if ($response === false) return false;
    if ((int)$code !== 250) {
        smtp_set_error('EHLO rejected by server (' . (int)$code . ').');
        return false;
    }

    foreach (preg_split('/\r?\n/', trim($response)) as $line) {
        $line = trim($line);
        if (strlen($line) < 4 || !preg_match('/^250[ -](.*)$/i', $line, $m)) continue;
        $cap = trim($m[1]);
        if ($cap === '') continue;
        $parts = preg_split('/\s+/', $cap);
        $name = strtoupper($parts[0] ?? '');
        if ($name !== '') $capabilities[$name] = $cap;
    }
    return true;
}

function smtp_starttls($fp, string $host): bool {
    if (!smtp_write($fp, "STARTTLS\r\n", 'STARTTLS')) return false;
    if (!smtp_expect($fp, [220], 'STARTTLS')) return false;

    $crypto = defined('STREAM_CRYPTO_METHOD_TLS_CLIENT')
        ? STREAM_CRYPTO_METHOD_TLS_CLIENT
        : (STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);

    $ok = @stream_socket_enable_crypto($fp, true, $crypto);
    if ($ok !== true) {
        smtp_set_error('TLS encryption could not be established with ' . $host . '.');
        return false;
    }
    return true;
}

function smtp_auth($fp, string $user, string $pass, array $capabilities): bool {
    if ($user === '') return true;

    $auth = strtoupper($capabilities['AUTH'] ?? '');
    if ($auth === '') {
        smtp_set_error('SMTP server did not advertise an AUTH method.');
        return false;
    }

    // Prefer PLAIN when offered; LOGIN is widely supported by shared hosting mail servers.
    if (preg_match('/\bPLAIN\b/', $auth)) {
        $payload = base64_encode("\0" . $user . "\0" . $pass);
        if (!smtp_write($fp, "AUTH PLAIN " . $payload . "\r\n", 'SMTP authentication')) return false;
        if (smtp_expect($fp, [235], 'SMTP authentication')) return true;
        return false;
    }

    if (preg_match('/\bLOGIN\b/', $auth)) {
        if (!smtp_write($fp, "AUTH LOGIN\r\n", 'SMTP authentication')) return false;
        if (!smtp_expect($fp, [334], 'SMTP authentication')) return false;
        if (!smtp_write($fp, base64_encode($user) . "\r\n", 'SMTP username')) return false;
        if (!smtp_expect($fp, [334], 'SMTP username')) return false;
        if (!smtp_write($fp, base64_encode($pass) . "\r\n", 'SMTP password')) return false;
        return smtp_expect($fp, [235], 'SMTP authentication');
    }

    smtp_set_error('SMTP server supports AUTH, but not PLAIN or LOGIN.');
    return false;
}

function smtp_send(string $to, string $subject, string $html, string $replyTo = '', ?array $config = null, array $attachments = []): bool {
    $GLOBALS['_smtp_last_error'] = 'Unknown SMTP error.';

    $cfg = $config ?? [
        'smtp_host' => setting('smtp_host', ''),
        'smtp_port' => setting('smtp_port', 587),
        'smtp_username' => setting('smtp_username', ''),
        'smtp_password' => setting('smtp_password', ''),
        'smtp_secure' => setting('smtp_secure', 'tls'),
        'smtp_from_email' => setting('smtp_from_email', setting('site_email', '')),
        'smtp_from_name' => setting('smtp_from_name', site_brand_name()),
    ];

    $host = trim((string)($cfg['smtp_host'] ?? ''));
    $port = (int)($cfg['smtp_port'] ?? 587);
    $user = trim((string)($cfg['smtp_username'] ?? ''));
    $pass = (string)($cfg['smtp_password'] ?? '');
    $secure = strtolower(trim((string)($cfg['smtp_secure'] ?? 'tls')));
    $from = trim((string)($cfg['smtp_from_email'] ?? ''));
    if ($from === '') $from = $user !== '' ? $user : trim((string)($cfg['site_email'] ?? ''));
    $fromName = trim((string)($cfg['smtp_from_name'] ?? site_brand_name()));

    if ($host === '') { smtp_set_error('SMTP Host is empty.'); return false; }
    if ($port < 1 || $port > 65535) { smtp_set_error('SMTP Port is invalid.'); return false; }
    if (!in_array($secure, ['ssl', 'tls', 'none'], true)) { smtp_set_error('SMTP Security must be TLS, SSL, or None.'); return false; }
    if (!smtp_valid_email($to)) { smtp_set_error('Recipient email address is invalid.'); return false; }
    if (!smtp_valid_email($from)) { smtp_set_error('From Email is invalid.'); return false; }
    if ($replyTo !== '' && !smtp_valid_email($replyTo)) { smtp_set_error('Reply-To email address is invalid.'); return false; }

    if ($secure === 'ssl' && $port === 587) {
        // Avoid silently doing SSL on the common STARTTLS port.
        smtp_set_error('SSL is normally used with port 465. For port 587, choose TLS.');
        return false;
    }
    if ($secure === 'tls' && $port === 465) {
        smtp_set_error('TLS/STARTTLS is normally used with port 587. For port 465, choose SSL.');
        return false;
    }

    $timeout = 20;
    $openError = '';
    $fp = smtp_open($host, $port, $secure, $timeout, $openError);
    if ($fp === false) { smtp_set_error($openError !== '' ? $openError : 'Unable to connect to SMTP server.'); return false; }

    $close = static function() use (&$fp): void {
        if (is_resource($fp)) @fclose($fp);
        $fp = null;
    };

    try {
        if (!smtp_expect($fp, [220], 'SMTP greeting')) { $close(); return false; }

        $ehloHost = preg_replace('/[^A-Za-z0-9.\-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
        $capabilities = [];
        if (!smtp_ehlo($fp, $ehloHost, $capabilities)) { $close(); return false; }

        if ($secure === 'tls') {
            if (!isset($capabilities['STARTTLS'])) {
                smtp_set_error('SMTP server does not advertise STARTTLS. Use SSL/465 if supported by the mail server.');
                $close();
                return false;
            }
            if (!smtp_starttls($fp, $host)) { $close(); return false; }
            $capabilities = [];
            if (!smtp_ehlo($fp, $ehloHost, $capabilities)) { $close(); return false; }
        }

        if (!smtp_auth($fp, $user, $pass, $capabilities)) { $close(); return false; }

        if (!smtp_write($fp, 'MAIL FROM:<' . smtp_header_value($from) . ">\r\n", 'MAIL FROM')) { $close(); return false; }
        if (!smtp_expect($fp, [250], 'MAIL FROM')) { $close(); return false; }

        if (!smtp_write($fp, 'RCPT TO:<' . smtp_header_value($to) . ">\r\n", 'RCPT TO')) { $close(); return false; }
        if (!smtp_expect($fp, [250, 251], 'RCPT TO')) { $close(); return false; }

        if (!smtp_write($fp, "DATA\r\n", 'DATA')) { $close(); return false; }
        if (!smtp_expect($fp, [354], 'DATA')) { $close(); return false; }

        $fromName = smtp_header_value($fromName);
        $subject = smtp_header_value($subject);
        $headers = 'Date: ' . date(DATE_RFC2822) . "\r\n";
        $headers .= 'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . smtp_header_value($from) . ">\r\n";
        $headers .= 'To: <' . smtp_header_value($to) . ">\r\n";
        if ($replyTo !== '') $headers .= 'Reply-To: <' . smtp_header_value($replyTo) . ">\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= 'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8') . "\r\n";
        $headers .= 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $host . ">\r\n";

        // Keep the original HTML-only path intact. Add multipart/mixed only when
        // at least one valid attachment is present.
        $validAttachments = [];
        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) continue;
            $path = trim((string)($attachment['path'] ?? ''));
            if ($path === '' || !is_file($path) || !is_readable($path)) continue;
            $data = @file_get_contents($path);
            if ($data === false) continue;
            $filename = trim((string)($attachment['filename'] ?? basename($path)));
            $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'attachment';
            $mime = trim((string)($attachment['mime'] ?? 'application/octet-stream'));
            if (!preg_match('~^[A-Za-z0-9.+-]+/[A-Za-z0-9.+-]+$~', $mime)) $mime = 'application/octet-stream';
            $validAttachments[] = ['filename' => $filename, 'mime' => $mime, 'data' => $data];
        }

        if ($validAttachments) {
            $boundary = '=_BTA_' . bin2hex(random_bytes(12));
            $headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . "\r\n";
            $body = '--' . $boundary . "\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            // Use quoted-printable for the HTML body. The previous 8bit body
            // could contain a single 5KB+ HTML line, which Exim rejects with
            // "message has lines too long for transport (limit 998)".
            $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $htmlBody = preg_replace('/\r?\n/', "\r\n", $html) ?? '';
            $htmlBody = quoted_printable_encode($htmlBody);
            $htmlBody = preg_replace('/(^|\r\n)\./', '$1..', $htmlBody) ?? $htmlBody;
            $body .= $htmlBody . "\r\n";
            foreach ($validAttachments as $attachment) {
                $body .= '--' . $boundary . "\r\n";
                $body .= 'Content-Type: ' . $attachment['mime'] . '; name="' . $attachment['filename'] . '"' . "\r\n";
                $body .= 'Content-Disposition: attachment; filename="' . $attachment['filename'] . '"' . "\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $body .= chunk_split(base64_encode($attachment['data']), 76, "\r\n");
            }
            $body .= '--' . $boundary . "--\r\n";
        } else {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            // Quote-printable keeps every MIME body line below SMTP's 998-byte limit.
            $headers .= "Content-Transfer-Encoding: quoted-printable\r\n";
            $body = preg_replace('/\r?\n/', "\r\n", $html) ?? '';
            $body = quoted_printable_encode($body);
            $body = preg_replace('/(^|\r\n)\./', '$1..', $body) ?? $body;
        }
        $message = $headers . "\r\n" . $body . "\r\n.\r\n";

        if (!smtp_write($fp, $message, 'Message body')) { $close(); return false; }
        if (!smtp_expect($fp, [250], 'Message delivery')) { $close(); return false; }

        @smtp_write($fp, "QUIT\r\n", 'QUIT');
        @fclose($fp);
        $fp = null;
        $GLOBALS['_smtp_last_error'] = '';
        return true;
    } catch (Throwable $e) {
        $close();
        smtp_set_error('SMTP error: ' . $e->getMessage());
        return false;
    }
}
