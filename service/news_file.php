<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/common.php';

$name = isset($_GET['name']) ? basename((string)$_GET['name']) : '';
if (!preg_match('/^[a-f0-9]{32}\.[a-z0-9]+$/', $name)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$dir = ksers_news_dir();
$path = $dir === '' ? '' : $dir . DIRECTORY_SEPARATOR . $name;
if ($path === '' || !is_file($path)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'pdf' => 'application/pdf',
    'txt' => 'text/plain; charset=UTF-8',
    'csv' => 'text/csv; charset=UTF-8',
];
header('Content-Type: ' . (isset($types[$ext]) ? $types[$ext] : 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600');
readfile($path);
exit;
