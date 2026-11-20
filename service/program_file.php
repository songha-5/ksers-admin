<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';

$seq = isset($_GET['seq']) ? (int)$_GET['seq'] : 0;
$field = isset($_GET['f']) ? $_GET['f'] : '';
$file = isset($_GET['file']) ? basename((string)$_GET['file']) : '';
$allowed = ['photo', 'cv', 'abstract'];
if ($seq < 1 || !in_array($field, $allowed, true) || $file === '' || preg_match('/[^a-zA-Z0-9._-]/', $file)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$col = $field . '_url';
$stmt = $pdo->prepare(
    "SELECT t.{$col} AS file_url, t.is_public, s.is_public AS session_public
     FROM program_talk t
     JOIN program_session s ON s.seq = t.session_seq
     WHERE t.seq = ?"
);
$stmt->execute([$seq]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || (int)$row['is_public'] !== 1 || (int)$row['session_public'] !== 1) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$url = (string)$row['file_url'];
if ($url === '' || strpos($url, 'file=' . $file) === false) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$path = ksers_program_dir() . '/' . $file;
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
$contentType = isset($types[$ext]) ? $types[$ext] : 'application/octet-stream';
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=86400');
readfile($path);
exit;
