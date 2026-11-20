<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';

$memberSeq = isset($_SESSION['seq']) ? (int)$_SESSION['seq'] : 0;
$seq = isset($_GET['seq']) ? (int)$_GET['seq'] : 0;
if ($memberSeq < 1 || $seq < 1) {
    header('Location: /service/login.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT f.*, m.is_admin
     FROM member_submission_file f
     JOIN member_info m ON m.seq = ?
     WHERE f.seq = ?"
);
$stmt->execute([$memberSeq, $seq]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || ((int)$row['member_seq'] !== $memberSeq && (int)$row['is_admin'] !== 1)) {
    http_response_code(404);
    exit('Not found');
}

$config = ksers_submission_type($row['kind']);
$path = $config ? ksers_submission_find($row['kind'], $row['stored_name']) : '';
if (!$config || $path === '') {
    http_response_code(404);
    exit('Not found');
}

$downloadName = ksers_submission_safe_name($row['original_name']);
if ($downloadName === '') {
    $downloadName = 'submission';
}
header('Content-Type: application/octet-stream');
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($downloadName));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
readfile($path);
exit;
