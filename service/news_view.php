<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/common.php';

ksers_auth_cors();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST, OPTIONS');
    echo json_encode(['error' => 'method'], JSON_UNESCAPED_UNICODE);
    exit;
}

$year = isset($_POST['year']) && $_POST['year'] !== '' ? (int)$_POST['year'] : (int)$eventYear;
$id = isset($_POST['id']) ? trim((string)$_POST['id']) : '';
if ($id === '' && isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (is_array($payload)) {
        if ($id === '' && isset($payload['id'])) {
            $id = trim((string)$payload['id']);
        }
        if (isset($payload['year']) && $payload['year'] !== '') {
            $year = (int)$payload['year'];
        }
    }
}
if ($year < 2027 || $year > 2035 || $id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'post'], JSON_UNESCAPED_UNICODE);
    exit;
}

$post = ksers_news_increment_view($pdo, $year, $id);
if (!$post) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'id' => $post['id'],
    'viewCount' => $post['viewCount'],
], JSON_UNESCAPED_UNICODE);
