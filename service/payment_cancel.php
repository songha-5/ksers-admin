<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
require_once __DIR__ . '/../ksers_paygate.php';

$memberSeq = isset($_SESSION['seq']) ? (int)$_SESSION['seq'] : 0;
if ($memberSeq < 1) {
    header('Location: /service/login.php');
    exit;
}
ksers_require_participant($pdo);

$year = isset($eventYear) ? (int)$eventYear : 2027;
$orderSeq = isset($_POST['order']) ? (int)$_POST['order'] : 0;
$histPage = isset($_POST['hist_page']) ? (int)$_POST['hist_page'] : 1;
$mypagePay = function ($query) use ($histPage) {
    if ($histPage > 1) {
        $query['hist_page'] = $histPage;
    }
    return 'registration.php?' . http_build_query($query);
};
$stmt = $pdo->prepare(
    "SELECT seq, status, method, tid, currency FROM payment_order WHERE seq = ? AND member_seq = ? AND year = ? AND status IN ('pending', 'paid')"
);
$stmt->execute([$orderSeq, $memberSeq, $year]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    header('Location: ' . $mypagePay(['pay' => 'error']));
    exit;
}
if ($order['method'] === 'bank' && $order['status'] === 'paid') {
    header('Location: ' . $mypagePay(['pay' => 'error']));
    exit;
}

$card = ($order['method'] === 'card_kr' || $order['method'] === 'card_os');
$split = ksers_payment_order_amounts($pdo, $orderSeq);
if ($card && $order['status'] === 'paid' && !ksers_registration_refund_open()) {
    header('Location: ' . $mypagePay([
        'pay' => 'cancel_fail',
        'reason' => t('2027년 4월 1일부터는 환불되지 않습니다.', 'No refunds are made from April 1, 2027.'),
    ]));
    exit;
}
$cardSent = false;
if ($card && trim((string)$order['tid']) !== '') {
    list($cancelled, $cancelError) = ksers_paygate_cancel($order['method'], $order['tid']);
    if (!$cancelled) {
        header('Location: ' . $mypagePay(['pay' => 'cancel_fail', 'reason' => $cancelError]));
        exit;
    }
    $cardSent = true;
} elseif ($card && $order['status'] === 'paid') {
    header('Location: ' . $mypagePay(['pay' => 'cancel_fail', 'reason' => t('카드 거래번호가 없어 카드사에 취소를 보내지 못했습니다.', 'The card transaction number is missing, so the cancellation was not sent.')]));
    exit;
}
$update = $pdo->prepare("UPDATE payment_order SET status = 'cancelled' WHERE seq = ? AND member_seq = ? AND status IN ('pending', 'paid')");
$update->execute([$orderSeq, $memberSeq]);
ksers_sync_pay_confirm($pdo, $memberSeq, $year);
if ($card && $order['status'] === 'paid' && $split['registration'] > 0 && function_exists('ksers_registration_cancel_notify')) {
    $korea = ksers_member_is_korea($pdo, $memberSeq);
    $currency = isset($order['currency']) && $order['currency'] !== '' ? (string)$order['currency'] : 'KRW';
    $amountText = ksers_registration_refund_amount_text($korea, [
        $currency => $split,
    ]);
    $methodLabel = $amountText['positive']
        ? ($korea ? '카드 결제 취소' : 'Card payment cancellation')
        : '—';
    ksers_registration_cancel_notify(
        $pdo,
        $memberSeq,
        $year,
        $amountText['plain'],
        $amountText['html'],
        $methodLabel,
        $amountText['positive'],
        false
    );
}

$done = ['pay' => 'cancelled'];
if ($cardSent) {
    $done['card'] = '1';
}
header('Location: ' . $mypagePay($done));
exit;
