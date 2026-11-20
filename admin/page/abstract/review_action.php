<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$year = ksers_admin_year($pdo);
$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
$ids = [];
if (isset($_POST['abs']) && is_array($_POST['abs'])) {
    foreach ($_POST['abs'] as $value) {
        $seq = (int)$value;
        if ($seq > 0) {
            $ids[$seq] = $seq;
        }
    }
}
$ids = array_values($ids);
$back = ['no' => '', 'submitter' => '', 'video' => '', 'status' => '', 'page' => ''];
foreach ($back as $key => $unused) {
    $posted = isset($_POST['filter_' . $key]) ? trim((string)$_POST['filter_' . $key]) : '';
    $back[$key] = $posted;
}
$redirect = function () use ($back) {
    $query = [];
    foreach ($back as $key => $value) {
        if ($value !== '') {
            $query[$key] = $value;
        }
    }
    header('Location: review.php' . ($query ? '?' . http_build_query($query) : ''));
    exit;
};
$videoKinds = ['abstract_video_1', 'abstract_video_2'];

if ($action === 'link_end_all') {
    $pdo->prepare("DELETE FROM abstract_video_link WHERE year = ?")->execute([$year]);
    $_SESSION['admin_notice'] = t('살아있는 영상 링크를 모두 종료했습니다.', 'All live video links were closed.');
    $redirect();
}

if (!$ids) {
    $_SESSION['admin_notice'] = t('초록을 선택해 주세요.', 'Select an abstract.');
    $redirect();
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$owned = $pdo->prepare("SELECT seq, status, presentation_type, final_type FROM abstract_info WHERE year = ? AND seq IN ($placeholders)");
$owned->execute(array_merge([$year], $ids));
$rows = $owned->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    $_SESSION['admin_notice'] = t('선택한 초록을 찾지 못했습니다.', 'The selected abstracts were not found.');
    $redirect();
}
$found = [];
foreach ($rows as $row) {
    $found[(int)$row['seq']] = $row;
}
$foundIds = array_keys($found);

if ($action === 'status') {
    $status = isset($_POST['status']) ? (string)$_POST['status'] : '';
    $allowed = array_keys(ksers_abstract_review_statuses());
    $finalAllowed = ['pending', 'oral', 'eposter'];
    if (!in_array($status, $allowed, true)) {
        $_SESSION['admin_notice'] = t('심사 상태를 선택해 주세요.', 'Choose a review status.');
        $redirect();
    }
    $update = $pdo->prepare("UPDATE abstract_info SET status = ?, final_type = ? WHERE seq = ? AND year = ?");
    foreach ($found as $row) {
        $finalType = isset($row['final_type']) && $row['final_type'] !== '' ? (string)$row['final_type'] : 'pending';
        if ($status === 'review_done' && (string)$row['status'] !== 'review_done') {
            $nextFinal = (string)$row['presentation_type'];
            if ($nextFinal === 'poster') {
                $nextFinal = 'eposter';
            }
            if (in_array($nextFinal, $finalAllowed, true) && $nextFinal !== 'pending') {
                $finalType = $nextFinal;
            }
        }
        $update->execute([$status, $finalType, (int)$row['seq'], $year]);
    }
    $_SESSION['admin_notice'] = t('심사 상태를 변경했습니다. 결과 메일은 보내지 않았습니다.', 'Review status updated. No result email was sent.');
    $redirect();
}

if ($action === 'link_end') {
    $endPlaceholders = implode(',', array_fill(0, count($foundIds), '?'));
    $pdo->prepare("DELETE FROM abstract_video_link WHERE year = ? AND abstract_seq IN ($endPlaceholders)")
        ->execute(array_merge([$year], $foundIds));
    $_SESSION['admin_notice'] = t('선택한 초록의 영상 링크를 종료했습니다.', 'Video links for the selected abstracts were closed.');
    $redirect();
}

if ($action === 'link_create') {
    $kindPlaceholders = implode(',', array_fill(0, count($videoKinds), '?'));
    $filePlaceholders = implode(',', array_fill(0, count($foundIds), '?'));
    $files = $pdo->prepare(
        "SELECT seq, abstract_seq FROM member_submission_file
         WHERE year = ? AND abstract_seq IN ($filePlaceholders) AND kind IN ($kindPlaceholders)
         ORDER BY seq"
    );
    $files->execute(array_merge([$year], $foundIds, $videoKinds));
    $fileRows = $files->fetchAll(PDO::FETCH_ASSOC);
    if (!$fileRows) {
        $_SESSION['admin_notice'] = t('선택한 초록에 영상이 없습니다.', 'The selected abstracts have no video.');
        $redirect();
    }
    $created = 0;
    foreach ($fileRows as $file) {
        if (ksers_abstract_video_link_ensure($pdo, (int)$file['seq'], (int)$file['abstract_seq'], $year)) {
            $created++;
        }
    }
    $_SESSION['admin_notice'] = $created > 0
        ? t('영상 링크를 만들었습니다.', 'Video links created.')
        : t('선택한 영상의 링크가 이미 있습니다.', 'Those videos already have links.');
    $redirect();
}

$_SESSION['admin_notice'] = t('요청을 처리하지 못했습니다.', 'The request could not be completed.');
$redirect();
