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

$bundle = ksers_scientific_load($pdo, $year);
$chairsBySession = [];
foreach ($bundle['chairs'] as $chair) {
    $chairsBySession[$chair['session_id']][] = [
        'id' => $chair['id'],
        'name' => $chair['name'],
        'sort_order' => (int)$chair['sort_order'],
    ];
}
$speakersBySession = [];
foreach ($bundle['speakers'] as $speaker) {
    $speakersBySession[$speaker['session_id']][] = [
        'id' => $speaker['id'],
        'badge' => $speaker['badge'],
        'talk_title' => $speaker['talk_title'],
        'name' => $speaker['name'],
        'affiliation' => $speaker['affiliation'],
        'description' => $speaker['description'],
        'image_url' => $speaker['image_url'],
        'cv_url' => $speaker['cv_url'],
        'abstract_url' => $speaker['abstract_url'],
        'sort_order' => (int)$speaker['sort_order'],
    ];
}
$sessionsByCategory = [];
foreach ($bundle['sessions'] as $session) {
    $sessionsByCategory[$session['category_id']][] = [
        'id' => $session['id'],
        'title' => $session['title'],
        'date_time' => $session['date_time'],
        'language' => $session['language'],
        'glance_id' => $session['glance_id'],
        'sort_order' => (int)$session['sort_order'],
        'chairs' => isset($chairsBySession[$session['id']]) ? $chairsBySession[$session['id']] : [],
        'speakers' => isset($speakersBySession[$session['id']]) ? $speakersBySession[$session['id']] : [],
    ];
}
$categories = [];
foreach ($bundle['categories'] as $category) {
    $categories[] = [
        'id' => $category['id'],
        'label' => $category['label'],
        'sort_order' => (int)$category['sort_order'],
        'sessions' => isset($sessionsByCategory[$category['id']]) ? $sessionsByCategory[$category['id']] : [],
    ];
}

echo json_encode([
    'year' => $year,
    'categories' => $categories,
], JSON_UNESCAPED_UNICODE);
