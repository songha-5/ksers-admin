<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/page/mail/index.php');
    exit;
}

$year = ksers_admin_year($pdo);
$templateKey = isset($_POST['template']) ? (string)$_POST['template'] : '';
$templates = ksers_admin_mail_templates();
if (!isset($templates[$templateKey])) {
    $templateKey = 'deadline';
}
$subject = trim(isset($_POST['subject']) ? (string)$_POST['subject'] : '');
$title = trim(isset($_POST['mail_title']) ? (string)$_POST['mail_title'] : '');
$body = trim(isset($_POST['body']) ? (string)$_POST['body'] : '');
$template = $templates[$templateKey];
$bilingual = !empty($template['locales']['ko']) && !empty($template['locales']['en']);
$localeEdits = [];
if ($bilingual) {
    foreach (['ko', 'en'] as $code) {
        $localeEdits[$code] = [
            'subject' => trim(isset($_POST['subject_' . $code]) ? (string)$_POST['subject_' . $code] : ''),
            'title' => trim(isset($_POST['mail_title_' . $code]) ? (string)$_POST['mail_title_' . $code] : ''),
            'body' => trim(isset($_POST['body_' . $code]) ? (string)$_POST['body_' . $code] : ''),
            'desk' => trim(isset($_POST['desk_' . $code]) ? (string)$_POST['desk_' . $code] : ''),
        ];
    }
    $subject = $localeEdits['ko']['subject'];
    $title = $localeEdits['ko']['title'];
    $body = $localeEdits['ko']['body'];
}
$decoded = json_decode(isset($_POST['recipients']) ? (string)$_POST['recipients'] : '', true);
$recipients = is_array($decoded) ? $decoded : [];
$missingCopy = $subject === '' || $title === '' || $body === '';
if ($bilingual) {
    foreach (['ko', 'en'] as $code) {
        if ($localeEdits[$code]['subject'] === '' || $localeEdits[$code]['title'] === '' || $localeEdits[$code]['body'] === '') {
            $missingCopy = true;
        }
    }
}
if ($missingCopy) {
    $_SESSION['admin_mail_result'] = [
        'ok' => false,
        'sent' => 0,
        'reason' => $bilingual
            ? t('한국어와 영어 제목, 본문을 모두 입력해 주세요.', 'Enter the Korean and English subject and body.')
            : t('제목과 본문을 입력해 주세요.', 'Enter a subject and a body.'),
        'failed' => [],
    ];
    header('Location: /admin/page/mail/index.php?template=' . rawurlencode($templateKey));
    exit;
}
if (!$recipients) {
    $_SESSION['admin_mail_result'] = [
        'ok' => false,
        'sent' => 0,
        'reason' => t('발송 목록에 받을 사람을 추가해 주세요.', 'Add at least one recipient.'),
        'failed' => [],
    ];
    header('Location: /admin/page/mail/index.php?template=' . rawurlencode($templateKey));
    exit;
}
$editedLang = isset($_POST['mail_lang']) && (string)$_POST['mail_lang'] === 'en' ? 'en' : 'ko';
list($sent, $failedAccounts) = ksers_admin_mail_send($pdo, $year, $templateKey, $subject, $title, $body, $recipients, $editedLang, $localeEdits);
$reason = '';
if ($failedAccounts) {
    $reasons = [];
    foreach ($failedAccounts as $account) {
        $text = trim(isset($account['reason']) ? (string)$account['reason'] : '');
        if ($text !== '') {
            $reasons[$text] = true;
        }
    }
    $reason = implode("\n", array_keys($reasons));
    if ($reason === '') {
        $reason = t('메일을 보내지 못했습니다.', 'The message could not be sent.');
    }
} elseif ($sent < 1) {
    $reason = t('보낼 수 있는 메일 주소가 없습니다.', 'There is no address that can receive this message.');
}
$_SESSION['admin_mail_result'] = [
    'ok' => !$failedAccounts && $sent > 0,
    'sent' => $sent,
    'reason' => $reason,
    'failed' => $failedAccounts,
];
header('Location: /admin/page/mail/index.php?template=' . rawurlencode($templateKey));
exit;
