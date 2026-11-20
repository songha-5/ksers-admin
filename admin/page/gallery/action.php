<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/page/gallery/index.php');
    exit;
}

$year = ksers_admin_year($pdo);
$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
$popup = !empty($_POST['popup']);
$seq = isset($_POST['seq']) ? (int)$_POST['seq'] : 0;
$listPage = isset($_POST['page']) ? (int)$_POST['page'] : 1;
$error = null;

if ($action === 'create') {
    $error = ksers_gallery_add($pdo, $year, isset($_FILES['image']) ? $_FILES['image'] : null);
    if ($error === null) {
        $_SESSION['admin_gallery_notice'] = t('이미지를 저장했습니다. 맨 위 1번으로 올려 두었습니다.', 'Image saved. It is now number 1 at the top.');
        $_SESSION['admin_gallery_notice_kind'] = 'ok';
        header('Location: ' . ksers_gallery_list_url(1, ksers_gallery_count($pdo, $year)));
        exit;
    }
} elseif ($action === 'replace') {
    $error = ksers_gallery_replace($pdo, $year, $seq, isset($_FILES['image']) ? $_FILES['image'] : null);
    if ($error === null) {
        $_SESSION['admin_gallery_notice'] = t('이미지를 바꿨습니다.', 'Image replaced.');
        $_SESSION['admin_gallery_notice_kind'] = 'ok';
        if ($popup) {
            header('Location: /admin/page/gallery/edit.php?seq=' . $seq . '&saved=1');
            exit;
        }
    } elseif ($popup) {
        $_SESSION['admin_gallery_notice'] = $error;
        $_SESSION['admin_gallery_notice_kind'] = 'warn';
        header('Location: /admin/page/gallery/edit.php?seq=' . $seq);
        exit;
    }
} elseif ($action === 'delete') {
    $seqs = isset($_POST['seqs']) && is_array($_POST['seqs']) ? $_POST['seqs'] : [];
    $error = ksers_gallery_delete($pdo, $year, $seqs);
    if ($error === null) {
        $_SESSION['admin_gallery_notice'] = t('이미지를 삭제했습니다.', 'Image deleted.');
        $_SESSION['admin_gallery_notice_kind'] = 'ok';
    }
} elseif ($action === 'move') {
    $direction = (isset($_POST['direction']) && $_POST['direction'] === 'up') ? 'up' : 'down';
    $error = ksers_gallery_move($pdo, $year, $seq, $direction);
    if ($error === null) {
        $_SESSION['admin_gallery_notice'] = t('순서를 바꿨습니다.', 'Order updated.');
        $_SESSION['admin_gallery_notice_kind'] = 'ok';
    }
} else {
    $error = t('요청을 처리하지 못했습니다.', 'The request could not be handled.');
}

if ($error !== null) {
    $_SESSION['admin_gallery_notice'] = $error;
    $_SESSION['admin_gallery_notice_kind'] = 'warn';
}

header('Location: ' . ksers_gallery_list_url($listPage, ksers_gallery_count($pdo, $year)));
exit;
