<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/page/news/index.php');
    exit;
}

$year = ksers_admin_year($pdo);
$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
$seq = isset($_POST['seq']) ? (int)$_POST['seq'] : 0;
$listTitle = isset($_POST['filter_title']) ? (string)$_POST['filter_title'] : '';
$listDate = isset($_POST['filter_date']) ? (string)$_POST['filter_date'] : '';
$listPage = isset($_POST['page']) ? (int)$_POST['page'] : 1;

if ($action === 'upload') {
    header('Content-Type: application/json; charset=UTF-8');
    $kind = isset($_POST['kind']) ? (string)$_POST['kind'] : '';
    $result = ksers_news_store_upload(isset($_FILES['file']) ? $_FILES['file'] : null, $kind);
    if (isset($result['error'])) {
        http_response_code(400);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'save') {
    $postedTitle = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
    $postedBody = isset($_POST['body']) ? (string)$_POST['body'] : '';
    $invalid = [];
    if ($postedTitle === '') {
        $invalid['title'] = 1;
    }
    if (ksers_news_body_is_empty($postedBody)) {
        $invalid['body'] = 1;
    }
    if ($invalid) {
        $_SESSION['admin_news_invalid'] = $invalid;
        $_SESSION['admin_news_draft'] = [
            'seq' => $seq,
            'title' => $postedTitle,
            'body' => $postedBody,
        ];
        header('Location: /admin/page/news/edit.php' . ($seq > 0 ? '?seq=' . $seq : ''));
        exit;
    }
    $saved = ksers_news_save($pdo, $year, $seq, $postedTitle, $postedBody);
    if ($saved[1] === null) {
        $_SESSION['admin_news_notice'] = $seq > 0
            ? t('글을 수정했습니다.', 'Post updated.')
            : t('글을 등록했습니다.', 'Post published.');
        $_SESSION['admin_news_notice_kind'] = 'ok';
        header('Location: /admin/page/news/index.php');
        exit;
    }
    $_SESSION['admin_news_notice'] = $saved[1];
    $_SESSION['admin_news_notice_kind'] = 'warn';
    $_SESSION['admin_news_draft'] = [
        'seq' => $seq,
        'title' => isset($_POST['title']) ? (string)$_POST['title'] : '',
        'body' => isset($_POST['body']) ? (string)$_POST['body'] : '',
    ];
    header('Location: /admin/page/news/edit.php' . ($seq > 0 ? '?seq=' . $seq : ''));
    exit;
}

if ($action === 'delete') {
    $error = ksers_news_delete($pdo, $year, $seq);
    if ($error === null) {
        $_SESSION['admin_news_notice'] = t('글을 삭제했습니다.', 'Post deleted.');
        $_SESSION['admin_news_notice_kind'] = 'ok';
    } else {
        $_SESSION['admin_news_notice'] = $error;
        $_SESSION['admin_news_notice_kind'] = 'warn';
    }
    header('Location: ' . ksers_news_list_url($listTitle, $listDate, $listPage));
    exit;
}

if ($action === 'visibility') {
    $hidden = isset($_POST['hidden']) && (string)$_POST['hidden'] === '1';
    $error = ksers_news_set_hidden($pdo, $year, $seq, $hidden);
    if ($error === null) {
        $_SESSION['admin_news_notice'] = $hidden
            ? t('글을 홈페이지에서 숨겼습니다.', 'The post is hidden on the homepage.')
            : t('글을 홈페이지에 다시 보입니다.', 'The post is visible on the homepage again.');
        $_SESSION['admin_news_notice_kind'] = 'ok';
    } else {
        $_SESSION['admin_news_notice'] = $error;
        $_SESSION['admin_news_notice_kind'] = 'warn';
    }
    header('Location: ' . ksers_news_list_url($listTitle, $listDate, $listPage));
    exit;
}

$_SESSION['admin_news_notice'] = t('요청을 처리하지 못했습니다.', 'The request could not be handled.');
$_SESSION['admin_news_notice_kind'] = 'warn';
header('Location: /admin/page/news/index.php');
exit;
