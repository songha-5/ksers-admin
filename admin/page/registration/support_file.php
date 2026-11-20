<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$year = ksers_admin_year($pdo);
$seq = isset($_GET['seq']) ? (int)$_GET['seq'] : 0;
$stmt = $pdo->prepare(
    "SELECT support_file, support_stored FROM participant_info WHERE seq = ? AND year = ?"
);
$stmt->execute([$seq, $year]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$stored = $row ? basename((string)$row['support_stored']) : '';
$path = $stored !== '' ? ksers_submission_find('supporting_document', $stored) : '';
if (!$row || $stored === '' || $path === '' || !is_file($path)) {
    http_response_code(404);
    exit('Not found');
}

$downloadName = basename(str_replace(['\\', '/'], '', (string)$row['support_file']));
if ($downloadName === '') {
    $downloadName = 'supporting-document';
}
$ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
$inline = isset($_GET['inline']) && $_GET['inline'] === '1';
if ($ext === 'pdf') {
    $mime = 'application/pdf';
} elseif ($ext === 'jpg' || $ext === 'jpeg') {
    $mime = 'image/jpeg';
} else {
    $inline = false;
    $mime = 'application/octet-stream';
}

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($downloadName));
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
