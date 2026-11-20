<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/common.php';

ksers_auth_cors();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET, OPTIONS');
    echo json_encode(['error' => 'method'], JSON_UNESCAPED_UNICODE);
    exit;
}

$year = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : (int)$eventYear;
if ($year < 2027 || $year > 2035) {
    http_response_code(400);
    echo json_encode(['error' => 'year'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'year' => $year,
    'photos' => ksers_gallery_public_photos($pdo, $year),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
