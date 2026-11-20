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

$bundle = ksers_glance_load($pdo, $year);
$rooms = [];
foreach ($bundle['rooms'] as $row) {
    $rooms[] = [
        'code' => $row['code'],
        'floor' => $row['floor'],
        'label' => $row['label'],
        'venue_name' => $row['venue_name'],
        'sort_order' => (int)$row['sort_order'],
    ];
}
$days = [];
foreach ($bundle['days'] as $row) {
    $days[] = [
        'id' => $row['id'],
        'label' => $row['label'],
        'sort_order' => (int)$row['sort_order'],
    ];
}
$timeSlots = [];
foreach ($bundle['slots'] as $row) {
    $timeSlots[] = [
        'id' => $row['id'],
        'day_id' => $row['day_id'],
        'time_label' => $row['time_label'],
        'sort_order' => (int)$row['sort_order'],
    ];
}
$sessions = [];
foreach ($bundle['sessions'] as $row) {
    $href = $row['href'] === null ? null : ksers_glance_safe_href($row['href']);
    if ($href === '') {
        $href = null;
    }
    $sessions[] = [
        'id' => $row['id'],
        'slot_id' => $row['slot_id'],
        'room_code' => $row['room_code'],
        'column_span' => (int)$row['column_span'],
        'row_span' => (int)$row['row_span'],
        'title' => $row['title'],
        'content' => $row['content'],
        'topic' => $row['topic'],
        'href' => $href,
        'label' => $row['label'],
        'color' => $row['color'],
        'sort_order' => (int)$row['sort_order'],
    ];
}

echo json_encode([
    'year' => $year,
    'rooms' => $rooms,
    'days' => $days,
    'timeSlots' => $timeSlots,
    'sessions' => $sessions,
], JSON_UNESCAPED_UNICODE);
