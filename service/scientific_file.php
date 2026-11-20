<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';

$id = isset($_GET['id']) ? (string)$_GET['id'] : '';
$field = isset($_GET['f']) ? (string)$_GET['f'] : '';
$file = isset($_GET['file']) ? basename((string)$_GET['file']) : '';
$columns = [
    'image' => 'image_url',
    'cv' => 'cv_url',
    'abstract' => 'abstract_url',
];
if (!preg_match('/^[0-9a-f-]{36}$/', $id) || !isset($columns[$field]) || $file === '' || preg_match('/[^a-zA-Z0-9._-]/', $file)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$column = $columns[$field];
$stmt = $pdo->prepare("SELECT {$column} AS file_url FROM scientific_speakers WHERE id = ?");
$stmt->execute([$id]);
$url = (string)$stmt->fetchColumn();
if ($url === '' || strpos($url, 'file=' . rawurlencode($file)) === false && strpos($url, 'file=' . $file) === false) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$path = ksers_scientific_dir() . '/' . $file;
if (!is_file($path)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
];
header('Content-Type: ' . (isset($types[$ext]) ? $types[$ext] : 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
readfile($path);
exit;
