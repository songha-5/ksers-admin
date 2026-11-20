<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';

$memberSeq = isset($_SESSION['seq']) ? (int)$_SESSION['seq'] : 0;
$seq = isset($_GET['seq']) ? (int)$_GET['seq'] : 0;
if ($memberSeq < 1 || $seq < 1) {
    header('Location: /service/login.php');
    exit;
}

if (ksers_member_is_admin($pdo)) {
    $year = ksers_admin_year($pdo);
    $stmt = $pdo->prepare("SELECT file_name, stored_name FROM abstract_info WHERE seq = ? AND year = ?");
    $stmt->execute([$seq, $year]);
} else {
    ksers_require_participant($pdo);
    $year = isset($eventYear) ? (int)$eventYear : 2027;
    $stmt = $pdo->prepare("SELECT file_name, stored_name FROM abstract_info WHERE seq = ? AND member_seq = ? AND year = ?");
    $stmt->execute([$seq, $memberSeq, $year]);
}
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$stored = $row ? basename((string)$row['stored_name']) : '';
$storeDir = getenv('KSERS_ABSTRACT_DIR') ?: '/data/abstracts';
$path = $storeDir . '/' . $stored;
if (!$row || $stored === '' || !is_file($path)) {
    if (ksers_member_is_admin($pdo)) {
        http_response_code(404);
        exit('Not found');
    }
    header('Location: /service/abstract.php');
    exit;
}

$downloadName = basename(str_replace(['\\', '/'], '', (string)$row['file_name']));
if ($downloadName === '') {
    $downloadName = 'abstract';
}
$ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
$inline = isset($_GET['inline']) && $_GET['inline'] === '1' && $ext === 'pdf';

header('Content-Type: ' . ($inline ? 'application/pdf' : 'application/octet-stream'));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($downloadName) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
