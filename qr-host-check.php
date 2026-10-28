<?php
header('Content-Type: text/plain; charset=utf-8');

echo 'PHP: '.PHP_VERSION."\n";
echo 'cURL: '.(function_exists('curl_init') ? 'YES' : 'NO')."\n";
echo 'GD imagecreatefromstring: '.(function_exists('imagecreatefromstring') ? 'YES' : 'NO')."\n";
echo 'GD imagejpeg: '.(function_exists('imagejpeg') ? 'YES' : 'NO')."\n";

$url = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=HOSTING_TEST_QR';
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_CONNECTTIMEOUT=>8, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    echo 'QRServer HTTP: '.$http."\n";
    echo 'QRServer PNG: '.(is_string($body) && strncmp($body, "\x89PNG\r\n\x1a\n", 8) === 0 ? 'YES' : 'NO')."\n";
    if ($error !== '') echo 'cURL error: '.$error."\n";
} else {
    echo "QRServer test skipped: cURL is not enabled\n";
}
