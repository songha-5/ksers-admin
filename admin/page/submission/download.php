<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$year = ksers_admin_year($pdo);
$kinds = ksers_submission_material_kinds();
$filter = isset($_POST['kind']) ? (string)$_POST['kind'] : 'all';
if ($filter !== 'all' && !in_array($filter, $kinds, true)) {
    $filter = 'all';
}
$back = '/admin/page/submission/list.php?kind=' . rawurlencode($filter);

$requested = isset($_POST['seq']) && is_array($_POST['seq']) ? $_POST['seq'] : [];
$seqs = [];
foreach ($requested as $seq) {
    $seq = (int)$seq;
    if ($seq > 0) {
        $seqs[$seq] = $seq;
    }
}
$seqs = array_values($seqs);
if (!$seqs) {
    header('Location: ' . $back . '&error=empty');
    exit;
}

$marks = implode(',', array_fill(0, count($seqs), '?'));
$kindMarks = implode(',', array_fill(0, count($kinds), '?'));
$stmt = $pdo->prepare(
    "SELECT seq, kind, stored_name, original_name
     FROM member_submission_file
     WHERE year = ? AND kind IN ($kindMarks) AND seq IN ($marks)"
);
$stmt->execute(array_merge([$year], $kinds, $seqs));
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$files = [];
$usedNames = [];
foreach ($rows as $row) {
    $path = ksers_submission_find($row['kind'], $row['stored_name']);
    if ($path === '' || !is_file($path)) {
        continue;
    }
    $files[] = [
        'path' => $path,
        'name' => ksers_zip_entry_name($row['original_name'], (int)$row['seq'], $usedNames),
        'size' => filesize($path),
        'download' => ksers_submission_safe_name($row['original_name']),
    ];
}
if (!$files) {
    header('Location: ' . $back . '&error=missing');
    exit;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
set_time_limit(0);
header('Cache-Control: private, no-store');

if (count($files) === 1) {
    $file = $files[0];
    $downloadName = $file['download'] !== '' ? $file['download'] : 'submission';
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($downloadName));
    header('Content-Length: ' . (int)$file['size']);
    readfile($file['path']);
    exit;
}

$entries = [];
foreach ($files as $file) {
    $entries[] = [
        'path' => $file['path'],
        'name' => $file['name'],
        'size' => (int)$file['size'],
    ];
}
$zipName = 'ksers-' . $year . '-' . ($filter === 'all' ? 'all' : $filter) . '.zip';
header('Content-Type: application/zip');
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($zipName));
header('Content-Length: ' . ksers_zip_length($entries));
$output = fopen('php://output', 'wb');
if ($output === false || !ksers_zip_stored_write($output, $entries)) {
    exit;
}
fclose($output);
exit;
