<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
$year = ksers_site_year($pdo);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 45) {
    echo json_encode(['ok' => false, 'reason' => 'format']);
    exit;
}

$stmt = $pdo->prepare('SELECT seq FROM member_info WHERE email = ? AND year = ?');
$stmt->execute([$email, $year]);
echo json_encode(['ok' => true, 'duplicate' => (bool)$stmt->fetch(PDO::FETCH_ASSOC)]);
