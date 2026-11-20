<?php
header('Content-Type: text/html; charset=UTF-8');
$path = __DIR__ . '/status.html';
if (!is_file($path)) {
    http_response_code(404);
    echo 'Status page not found.';
    exit;
}
readfile($path);
