<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';

$seq = isset($_GET['seq']) ? (int)$_GET['seq'] : 0;
if ($seq < 1) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$stmt = $pdo->prepare('SELECT stored_name FROM gallery_photo WHERE seq = ?');
$stmt->execute([$seq]);
$stored = basename((string)$stmt->fetchColumn());
if ($stored === '' || preg_match('/[^a-zA-Z0-9._-]/', $stored)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$path = ksers_gallery_dir() . DIRECTORY_SEPARATOR . $stored;
if (!is_file($path)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
];
header('Content-Type: ' . (isset($types[$ext]) ? $types[$ext] : 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600');
readfile($path);
exit;
