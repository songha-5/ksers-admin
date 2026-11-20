<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$year = ksers_admin_year($pdo);
$seq = isset($_POST['seq']) ? (int)$_POST['seq'] : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';
$allowed = array_keys(ksers_abstract_review_statuses());
$returnView = isset($_POST['return']) && $_POST['return'] === 'view';
$backList = '/admin/page/abstract/list.php';
if (!in_array($status, $allowed, true) || $seq < 1) {
    header('Location: ' . ($returnView && $seq > 0 ? '/admin/page/abstract/view.php?seq=' . $seq : $backList));
    exit;
}

$stmt = $pdo->prepare(
    "SELECT a.*, m.email, m.name_ko
     FROM abstract_info a
     JOIN member_info m ON m.seq = a.member_seq
     WHERE a.seq = ? AND a.year = ?"
);
$stmt->execute([$seq, $year]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    header('Location: ' . $backList);
    exit;
}

$finalAllowed = ['pending', 'oral', 'eposter'];
$finalEdited = isset($_POST['final_edited']) && $_POST['final_edited'] === '1';
$postedFinal = isset($_POST['final_type']) ? (string)$_POST['final_type'] : '';
if ($status === 'review_done' && (string)$row['status'] !== 'review_done' && !$finalEdited) {
    $finalType = (string)$row['presentation_type'];
    if ($finalType === 'poster') {
        $finalType = 'eposter';
    }
    if (!in_array($finalType, $finalAllowed, true) || $finalType === 'pending') {
        $finalType = isset($row['final_type']) ? (string)$row['final_type'] : 'pending';
    }
} elseif ($finalEdited) {
    $finalType = $postedFinal === 'poster' ? 'eposter' : $postedFinal;
    if (!in_array($finalType, $finalAllowed, true)) {
        $finalType = isset($row['final_type']) && $row['final_type'] !== '' ? (string)$row['final_type'] : 'pending';
    }
} else {
    $finalType = isset($row['final_type']) && $row['final_type'] !== '' ? (string)$row['final_type'] : 'pending';
}

$storedName = isset($row['stored_name']) ? (string)$row['stored_name'] : '';
$originalName = isset($row['file_name']) ? (string)$row['file_name'] : '';
$fileReplaced = false;
$upload = isset($_FILES['abstract_file']) ? $_FILES['abstract_file'] : null;
$hasFile = $upload && isset($upload['error']) && (int)$upload['error'] !== UPLOAD_ERR_NO_FILE;
if ($hasFile) {
    $fail = function ($message) use ($seq) {
        $_SESSION['admin_notice'] = $message;
        header('Location: /admin/page/abstract/view.php?seq=' . (int)$seq . '&error=1');
        exit;
    };
    if ((int)$upload['error'] !== UPLOAD_ERR_OK || (int)$upload['size'] > 10 * 1024 * 1024) {
        $fail(t('파일은 10MB 이하의 PDF, DOC, DOCX만 올릴 수 있습니다.', 'Upload a PDF, DOC, or DOCX file up to 10MB.'));
    }
    $ext = strtolower(pathinfo((string)$upload['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
        $fail(t('파일은 PDF, DOC, DOCX만 올릴 수 있습니다.', 'Only PDF, DOC, and DOCX files are allowed.'));
    }
    $storeDir = getenv('KSERS_ABSTRACT_DIR') ?: '/data/abstracts';
    if (!is_dir($storeDir) && !mkdir($storeDir, 0700, true) && !is_dir($storeDir)) {
        $fail(t('파일을 저장하지 못했습니다.', 'The file could not be saved.'));
    }
    $newStored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($upload['tmp_name'], $storeDir . '/' . $newStored)) {
        $fail(t('파일을 저장하지 못했습니다.', 'The file could not be saved.'));
    }
    $oldStored = basename($storedName);
    if ($oldStored !== '' && is_file($storeDir . '/' . $oldStored)) {
        unlink($storeDir . '/' . $oldStored);
    }
    $storedName = $newStored;
    $originalName = basename(str_replace(['\\', '/'], '', (string)$upload['name']));
    $fileReplaced = true;
}

$setting = ksers_setting($pdo, $year);
$mailSent = (int)$row['mail_sent'];
$mailDate = $row['mail_date'];
if ($status === 'accepted' || $status === 'rejected') {
    $resultKo = $status === 'accepted' ? '채택' : '미채택';
    $resultEn = $status === 'accepted' ? 'accepted' : 'not accepted';
    $sent = ksers_send_mail(
        $setting['mail_from'],
        $row['email'],
        $setting['title_ko'] . ' 초록 결과',
        $row['name_ko'] . " 님의 초록 결과: " . $resultKo . "\n제목: " . $row['title'] . "\n\nAbstract result: " . $resultEn . "\nTitle: " . $row['title']
    );
    if ($sent) {
        $mailSent = 1;
        $mailDate = date('Y-m-d H:i:s');
    }
    $_SESSION['admin_notice'] = $sent
        ? t('심사 결과를 저장하고 메일을 보냈습니다.', 'Result saved and email sent.')
        : t('심사 결과를 저장했습니다. 메일은 보내지 못했습니다. 보내는 주소를 확인해 주세요.', 'Result saved. The email was not sent. Check the sender address.');
} else {
    $_SESSION['admin_notice'] = $fileReplaced
        ? t('초록 파일과 심사 내용을 저장했습니다.', 'The abstract file and review details were saved.')
        : t('초록 상태를 저장했습니다.', 'Abstract status saved.');
}

if ($fileReplaced) {
    $update = $pdo->prepare(
        "UPDATE abstract_info SET status = ?, final_type = ?, mail_sent = ?, mail_date = ?, file_name = ?, stored_name = ?, update_date = NOW() WHERE seq = ? AND year = ?"
    );
    $update->execute([$status, $finalType, $mailSent, $mailDate, $originalName, $storedName, $seq, $year]);
} else {
    $update = $pdo->prepare(
        "UPDATE abstract_info SET status = ?, final_type = ?, mail_sent = ?, mail_date = ? WHERE seq = ? AND year = ?"
    );
    $update->execute([$status, $finalType, $mailSent, $mailDate, $seq, $year]);
}
header('Location: ' . ($returnView ? '/admin/page/abstract/view.php?seq=' . $seq . '&saved=1' : $backList));
exit;
