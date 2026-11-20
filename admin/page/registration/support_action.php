<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$year = ksers_admin_year($pdo);
$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
$popup = isset($_POST['popup']) && (string)$_POST['popup'] === '1';
$seq = isset($_POST['seq']) ? (int)$_POST['seq'] : 0;
$return = ['q' => '', 'category' => '', 'country' => '', 'status' => '', 'focus' => ''];
foreach ($return as $key => $unused) {
    if (isset($_POST[$key])) {
        $return[$key] = (string)$_POST[$key];
    }
}
$back = '/admin/page/registration/review.php';
$query = [];
foreach (['q', 'category', 'country', 'status', 'focus'] as $key) {
    if ($return[$key] !== '') {
        $query[$key] = $return[$key];
    }
}

$stmt = $pdo->prepare(
    "SELECT p.seq, p.member_seq, p.category, p.support_status, p.support_file, m.country
     FROM participant_info p
     JOIN member_info m ON m.seq = p.member_seq
     WHERE p.seq = ? AND p.year = ?"
);
$stmt->execute([$seq, $year]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$applicable = $row && strtoupper(trim((string)$row['country'])) !== 'KR' && in_array($row['category'], ['student', 'nurse'], true);
$wasApproved = $row && $row['support_status'] === 'approved';

$actor = '';
$actorSeq = isset($_SESSION['seq']) ? (int)$_SESSION['seq'] : 0;
if ($actorSeq > 0) {
    $actorStmt = $pdo->prepare("SELECT email, name_ko, first_name_eng, last_name_eng FROM member_info WHERE seq = ?");
    $actorStmt->execute([$actorSeq]);
    $actorRow = $actorStmt->fetch(PDO::FETCH_ASSOC);
    if ($actorRow) {
        $actor = trim((string)$actorRow['name_ko']);
        if ($actor === '') {
            $actor = trim($actorRow['first_name_eng'] . ' ' . $actorRow['last_name_eng']);
        }
        if ($actor === '') {
            $actor = (string)$actorRow['email'];
        }
    }
}

if ($applicable && $action === 'approve' && !$wasApproved) {
    $update = $pdo->prepare(
        "UPDATE participant_info
         SET support_status = 'approved', support_reason = '', support_handled_at = NOW(), support_handler = ?
         WHERE seq = ? AND year = ?"
    );
    $update->execute([$actor, $seq, $year]);
    ksers_record_free_registration($pdo, (int)$row['member_seq'], $year);
    if (function_exists('ksers_registration_support_approved_notify')) {
        ksers_registration_support_approved_notify($pdo, (int)$row['member_seq'], $year);
    }
    $_SESSION['admin_notice'] = t('증빙을 통과 처리했습니다.', 'The document was approved.');
} elseif ($applicable && $action === 'reject' && $row['support_status'] !== 'rejected') {
    $reason = isset($_POST['reason']) ? trim((string)$_POST['reason']) : '';
    if ($reason === '') {
        $_SESSION['admin_notice'] = t('반려 사유를 입력해 주세요.', 'Enter the reason for rejection.');
    } elseif (function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') > 1000 : strlen($reason) > 1000) {
        $_SESSION['admin_notice'] = t('반려 사유는 1000자 안으로 입력해 주세요.', 'Enter the reason in 1000 characters or fewer.');
    } else {
        $update = $pdo->prepare(
            "UPDATE participant_info
             SET support_status = 'rejected', support_reason = ?, support_handled_at = NOW(), support_handler = ?
             WHERE seq = ? AND year = ?"
        );
        $update->execute([$reason, $actor, $seq, $year]);
        if (function_exists('ksers_registration_support_reject_notify')) {
            ksers_registration_support_reject_notify($pdo, (int)$row['member_seq'], $year);
        }
        if ($wasApproved) {
            $freeOrders = $pdo->prepare(
                "SELECT DISTINCT o.seq
                 FROM payment_order o
                 JOIN payment_line l ON l.order_seq = o.seq
                 LEFT JOIN fee_item f ON f.seq = l.fee_seq
                 WHERE o.member_seq = ? AND o.year = ? AND o.status = 'paid' AND o.amount = 0
                   AND (f.fee_code IS NULL OR f.fee_code NOT IN ('dinner_individual', 'dinner_group'))"
            );
            $freeOrders->execute([(int)$row['member_seq'], $year]);
            $cancel = $pdo->prepare("UPDATE payment_order SET status = 'cancelled' WHERE seq = ? AND status = 'paid' AND amount = 0");
            foreach ($freeOrders->fetchAll(PDO::FETCH_COLUMN) as $orderSeq) {
                $cancel->execute([(int)$orderSeq]);
            }
            ksers_sync_pay_confirm($pdo, (int)$row['member_seq'], $year);
        }
        $_SESSION['admin_notice'] = t('증빙을 반려했습니다.', 'The document was rejected.');
    }
}

$location = $back . ($query ? ('?' . http_build_query($query)) : '');
if ($popup) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><script>';
    echo 'var next=' . json_encode($location) . ';';
    echo 'if(window.opener&&!window.opener.closed){window.opener.location.href=next;}';
    echo 'window.close();location.href=next;';
    echo '</script></body></html>';
    exit;
}
header('Location: ' . $location);
exit;
