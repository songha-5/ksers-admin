<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/page/abstract/list.php');
    exit;
}

$year = ksers_admin_year($pdo);
$seq = isset($_POST['seq']) ? (int)$_POST['seq'] : 0;
$allowedReturn = ['submitter', 'no', 'type', 'topic', 'country', 'final', 'status', 'mail', 'page'];
$return = [];
if (isset($_POST['return']) && is_string($_POST['return'])) {
    parse_str($_POST['return'], $parsed);
    if (is_array($parsed)) {
        foreach ($allowedReturn as $key) {
            if (isset($parsed[$key]) && is_string($parsed[$key]) && $parsed[$key] !== '') {
                $return[$key] = $parsed[$key];
            }
        }
    }
}
$back = '/admin/page/abstract/list.php' . ($return ? '?' . http_build_query($return) : '');

$stmt = $pdo->prepare("SELECT seq, status FROM abstract_info WHERE seq = ? AND year = ?");
$stmt->execute([$seq, $year]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    $_SESSION['admin_notice'] = t('초록을 찾지 못했습니다.', 'The abstract was not found.');
    header('Location: ' . $back);
    exit;
}
if ((string)$row['status'] === 'withdrawn') {
    $_SESSION['admin_notice'] = t('이미 철회된 초록입니다.', 'This abstract is already withdrawn.');
    header('Location: ' . $back);
    exit;
}

$update = $pdo->prepare("UPDATE abstract_info SET status = 'withdrawn', update_date = NOW() WHERE seq = ? AND year = ? AND status <> 'withdrawn'");
$update->execute([$seq, $year]);
list($sent, $failed) = ksers_abstract_withdrawn_notify($pdo, $seq);
if ($sent > 0 && $failed === 0) {
    $_SESSION['admin_notice'] = t('초록을 철회하고 안내 메일을 보냈습니다.', 'The abstract was withdrawn and the notice was sent.');
} elseif ($sent > 0) {
    $_SESSION['admin_notice'] = t('초록을 철회했습니다. 안내 메일 일부만 보냈습니다.', 'The abstract was withdrawn. Only some notices were sent.');
} else {
    $_SESSION['admin_notice'] = t('초록을 철회했습니다. 안내 메일은 보내지 못했습니다.', 'The abstract was withdrawn. The notice was not sent.');
}
header('Location: ' . $back);
exit;
