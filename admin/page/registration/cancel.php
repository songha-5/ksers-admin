<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
require_once __DIR__ . '/../../../ksers_paygate.php';
ksers_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/page/registration/list.php');
    exit;
}

$year = ksers_admin_year($pdo);
$seq = isset($_POST['seq']) ? (int)$_POST['seq'] : 0;
$page = isset($_POST['reg_page']) ? (int)$_POST['reg_page'] : 1;
if ($page < 1) {
    $page = 1;
}
$back = '/admin/page/registration/list.php?reg_page=' . $page;

$stmt = $pdo->prepare(
    "SELECT seq, member_seq, cancelled_at
     FROM participant_info
     WHERE seq = ? AND year = ?"
);
$stmt->execute([$seq, $year]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    $_SESSION['admin_notice'] = t('참가 등록을 찾지 못했습니다.', 'The registration was not found.');
    header('Location: ' . $back);
    exit;
}
if (!empty($row['cancelled_at'])) {
    $_SESSION['admin_notice'] = t('이미 취소된 참가 등록입니다.', 'This registration is already cancelled.');
    header('Location: ' . $back);
    exit;
}

$memberSeq = (int)$row['member_seq'];
$update = $pdo->prepare(
    "UPDATE participant_info
     SET cancelled_at = NOW(), pay_confirm = 'N'
     WHERE seq = ? AND year = ? AND cancelled_at IS NULL"
);
$update->execute([$seq, $year]);
if ($update->rowCount() < 1) {
    $_SESSION['admin_notice'] = t('이미 취소된 참가 등록입니다.', 'This registration is already cancelled.');
    header('Location: ' . $back);
    exit;
}

$pending = $pdo->prepare(
    "UPDATE payment_order o
     SET o.status = 'cancelled'
     WHERE o.member_seq = ? AND o.year = ? AND o.status = 'pending'
       AND EXISTS (
            SELECT 1 FROM payment_line l
            LEFT JOIN fee_item f ON f.seq = l.fee_seq
            WHERE l.order_seq = o.seq
              AND (f.fee_code IS NULL OR f.fee_code NOT IN ('dinner_individual', 'dinner_group'))
       )"
);
$pending->execute([$memberSeq, $year]);
$free = $pdo->prepare(
    "UPDATE payment_order o
     SET o.status = 'cancelled'
     WHERE o.member_seq = ? AND o.year = ? AND o.status = 'paid' AND o.amount = 0
       AND EXISTS (
            SELECT 1 FROM payment_line l
            LEFT JOIN fee_item f ON f.seq = l.fee_seq
            WHERE l.order_seq = o.seq
              AND (f.fee_code IS NULL OR f.fee_code NOT IN ('dinner_individual', 'dinner_group'))
       )"
);
$free->execute([$memberSeq, $year]);

$paidStmt = $pdo->prepare(
    "SELECT seq, method, amount, currency, tid
     FROM payment_order
     WHERE member_seq = ? AND year = ? AND status = 'paid' AND amount > 0"
);
$paidStmt->execute([$memberSeq, $year]);
$paidOrders = $paidStmt->fetchAll(PDO::FETCH_ASSOC);
$refundable = ksers_registration_refund_open();
$byCurrency = [];
$cardFailed = false;
$markRefunded = $pdo->prepare("UPDATE payment_order SET status = 'refunded', refund_date = NOW() WHERE seq = ? AND status = 'paid'");
foreach ($paidOrders as $order) {
    $method = (string)$order['method'];
    $isCard = $method === 'card_kr' || $method === 'card_os';
    if (!$isCard || !$refundable) {
        continue;
    }
    $split = ksers_payment_order_amounts($pdo, (int)$order['seq']);
    if ($split['registration'] < 1 && $split['dinner'] < 1) {
        continue;
    }
    list($cancelled, $cancelError) = ksers_paygate_cancel($method, isset($order['tid']) ? $order['tid'] : '');
    if (!$cancelled) {
        $cardFailed = true;
        continue;
    }
    $markRefunded->execute([(int)$order['seq']]);
    $currency = function_exists('ksers_fee_currency') ? ksers_fee_currency($order) : 'KRW';
    if (!isset($byCurrency[$currency])) {
        $byCurrency[$currency] = ['registration' => 0, 'dinner' => 0];
    }
    $byCurrency[$currency]['registration'] += $split['registration'];
    $byCurrency[$currency]['dinner'] += $split['dinner'];
}
ksers_sync_pay_confirm($pdo, $memberSeq, $year);

$korea = ksers_member_is_korea($pdo, $memberSeq);
$amountText = ksers_registration_refund_amount_text($korea, $byCurrency);
$refundMethod = $amountText['positive']
    ? ($korea ? '카드 결제 취소' : 'Card payment cancellation')
    : '—';
$showCard = $amountText['positive'];

$sent = function_exists('ksers_registration_cancel_notify')
    ? ksers_registration_cancel_notify($pdo, $memberSeq, $year, $amountText['plain'], $amountText['html'], $refundMethod, $showCard, false)
    : false;
$notice = $sent
    ? t('참가 등록을 취소하고 안내 메일을 보냈습니다.', 'The registration was cancelled and the notice was sent.')
    : t('참가 등록을 취소했습니다. 안내 메일은 보내지 못했습니다.', 'The registration was cancelled. The notice was not sent.');
if ($cardFailed) {
    $notice .= ' ' . t('카드 결제는 취소되지 않아 환불 금액에 넣지 않았습니다.', 'The card payment was not cancelled, so it was left out of the refund amount.');
}
$_SESSION['admin_notice'] = $notice;
header('Location: ' . $back);
exit;
