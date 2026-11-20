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

$id = isset($_GET['id']) ? trim((string)$_GET['id']) : '';
if ($id !== '') {
    $post = ksers_news_public_find($pdo, $year, $id);
    if (!$post) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['year' => $year, 'post' => $post], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'year' => $year,
    'posts' => ksers_news_public_posts($pdo, $year),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
