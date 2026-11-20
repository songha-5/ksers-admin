<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$year = ksers_admin_year($pdo);
$presentationLabel = [
    'oral' => 'Oral',
    'eposter' => 'E-Poster',
    'video' => 'Video',
    'mentor' => 'Mentor-Mentee Video',
    'poster' => 'Poster',
    'either' => 'Either',
];
$finalTypeLabel = [
    'pending' => 'Pending',
    'oral' => 'Oral',
    'eposter' => 'E-Poster',
];
$statusFilter = [];
foreach (ksers_abstract_review_statuses() as $code => $reviewStatus) {
    if ($code === 'accepted' || $code === 'rejected') {
        $statusFilter['result'] = true;
        continue;
    }
    $statusFilter[$code] = true;
}

$submitter = isset($_POST['submitter']) ? trim((string)$_POST['submitter']) : '';
$no = isset($_POST['no']) ? trim((string)$_POST['no']) : '';
$type = isset($_POST['type']) ? (string)$_POST['type'] : '';
$topic = isset($_POST['topic']) ? (string)$_POST['topic'] : '';
$country = isset($_POST['country']) ? (string)$_POST['country'] : '';
$final = isset($_POST['final']) ? (string)$_POST['final'] : '';
$status = isset($_POST['status']) ? (string)$_POST['status'] : '';
$mail = isset($_POST['mail']) ? (string)$_POST['mail'] : '';
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if (!isset($presentationLabel[$type])) {
    $type = '';
}
if (!isset($finalTypeLabel[$final])) {
    $final = '';
}
if (!isset($statusFilter[$status])) {
    $status = '';
}
if ($mail !== 'Y' && $mail !== 'N') {
    $mail = '';
}
if ($page < 1) {
    $page = 1;
}

$backQuery = [
    'submitter' => $submitter,
    'no' => $no,
    'type' => $type,
    'topic' => $topic,
    'country' => $country,
    'final' => $final,
    'status' => $status,
    'mail' => $mail,
    'page' => $page,
];
$back = '/admin/page/abstract/list.php';
$backParams = [];
foreach ($backQuery as $key => $value) {
    if ((string)$value !== '' && !($key === 'page' && (int)$value === 1)) {
        $backParams[$key] = $value;
    }
}
if ($backParams) {
    $back .= '?' . http_build_query($backParams);
}

$selected = [];
if (isset($_POST['seq']) && is_array($_POST['seq'])) {
    foreach ($_POST['seq'] as $value) {
        $seq = (int)$value;
        if ($seq > 0) {
            $selected[$seq] = $seq;
        }
    }
}

$where = ['a.year = ?'];
$params = [$year];
if ($selected) {
    $placeholders = implode(',', array_fill(0, count($selected), '?'));
    $where[] = 'a.seq IN (' . $placeholders . ')';
    $params = array_merge($params, array_values($selected));
} else {
    if ($submitter !== '') {
        $where[] = 'm.email LIKE ?';
        $params[] = '%' . $submitter . '%';
    }
    if ($no !== '') {
        $seqFilter = 0;
        if (preg_match('/-(\d+)$/', $no, $match)) {
            $seqFilter = (int)$match[1];
        } elseif (ctype_digit($no)) {
            $seqFilter = (int)$no;
        }
        $where[] = 'a.seq = ?';
        $params[] = $seqFilter;
    }
    if ($type !== '') {
        $where[] = 'a.presentation_type = ?';
        $params[] = $type;
    }
    if ($topic !== '') {
        $where[] = 'a.topic = ?';
        $params[] = $topic;
    }
    if ($country !== '' && ctype_digit($country)) {
        $where[] = '(a.author_country = ? OR EXISTS (SELECT 1 FROM abstract_author aa WHERE aa.abstract_seq = a.seq AND aa.country = ?))';
        $params[] = (int)$country;
        $params[] = (int)$country;
    }
    if ($final !== '') {
        $where[] = 'a.final_type = ?';
        $params[] = $final;
    }
    if ($status === 'result') {
        $where[] = "a.status IN ('accepted', 'rejected')";
    } elseif ($status !== '') {
        $where[] = 'a.status = ?';
        $params[] = $status;
    }
    if ($mail === 'Y') {
        $where[] = 'a.mail_sent = 1';
    } elseif ($mail === 'N') {
        $where[] = '(a.mail_sent = 0 OR a.mail_sent IS NULL)';
    }
}

$stmt = $pdo->prepare(
    "SELECT a.seq, a.file_name, a.stored_name
     FROM abstract_info a
     JOIN member_info m ON m.seq = a.member_seq
     WHERE " . implode(' AND ', $where) . "
     ORDER BY a.seq DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$storeDir = getenv('KSERS_ABSTRACT_DIR') ?: '/data/abstracts';
$files = [];
$usedNames = [];
foreach ($rows as $row) {
    $stored = basename((string)$row['stored_name']);
    if ($stored === '' || $stored === '.' || $stored === '..') {
        continue;
    }
    $path = $storeDir . '/' . $stored;
    if (!is_file($path)) {
        continue;
    }
    $receipt = sprintf('A%d-%04d', $year, (int)$row['seq']);
    $files[] = [
        'path' => $path,
        'name' => ksers_abstract_download_entry($receipt, $row['file_name'], $stored, $usedNames),
        'size' => filesize($path),
    ];
}

if (!$files) {
    $_SESSION['admin_notice'] = t('다운로드할 초록 파일이 없습니다.', 'There is no abstract file to download.');
    header('Location: ' . $back);
    exit;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
set_time_limit(0);
header('Cache-Control: private, no-store');

if (count($files) === 1) {
    $file = $files[0];
    $downloadName = $file['name'] !== '' ? $file['name'] : 'abstract';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . ksers_abstract_download_fallback($downloadName) . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('Content-Length: ' . (int)$file['size']);
    readfile($file['path']);
    exit;
}

$zipName = 'KSERS' . (int)$year . '_Abstract.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"; filename*=UTF-8\'\'' . rawurlencode($zipName));
header('Content-Length: ' . ksers_zip_length($files));
$output = fopen('php://output', 'wb');
if ($output === false || !ksers_zip_stored_write($output, $files)) {
    exit;
}
fclose($output);
exit;
