<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';

ksers_auth_cors();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');
$code = isset($_GET['code']) ? $_GET['code'] : '';
$result = ksers_consume_auth_code($pdo, $code);
echo json_encode($result ? $result : ksers_auth_denied(), JSON_UNESCAPED_UNICODE);
