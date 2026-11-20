<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
require_once __DIR__ . '/../ksers_paygate.php';

$memberSeq = isset($_SESSION['seq']) ? (int)$_SESSION['seq'] : 0;
if ($memberSeq < 1) {
    header('Location: /service/login.php');
    exit;
}
ksers_require_participant($pdo);

unset(
    $_POST['cardnumber'],
    $_POST['cardsecretnumber'],
    $_POST['cardownernumber'],
    $_POST['cardexpiremonth'],
    $_POST['cardexpireyear'],
    $_POST['cardauthcode']
);

$year = isset($eventYear) ? (int)$eventYear : 2027;
$orderSeq = isset($_POST['order']) ? (int)$_POST['order'] : 0;
$replycode = isset($_POST['replycode']) ? trim((string)$_POST['replycode']) : '';
$tid = isset($_POST['tid']) ? trim((string)$_POST['tid']) : '';
$hashresult = isset($_POST['hashresult']) ? strtolower(trim((string)$_POST['hashresult'])) : '';
$unitprice = isset($_POST['unitprice']) ? (string)$_POST['unitprice'] : '';
$currency = isset($_POST['goodcurrency']) ? strtoupper(trim((string)$_POST['goodcurrency'])) : '';
$serial = isset($_POST['mb_serial_no']) ? trim((string)$_POST['mb_serial_no']) : '';
$replyMsg = isset($_POST['replyMsg']) ? trim((string)$_POST['replyMsg']) : '';
$replyMsg = preg_replace('/\s+/u', ' ', $replyMsg);
$replyMsg = trim((string)$replyMsg);
if (function_exists('mb_substr')) {
    $replyMsg = mb_substr($replyMsg, 0, 120, 'UTF-8');
} else {
    $replyMsg = substr($replyMsg, 0, 120);
}
$replyMsg = preg_replace('/\d{6,}/', '', $replyMsg);
$replyMsg = trim((string)$replyMsg);
$payBack = function ($pay, $why, $extra = []) {
    $query = ['pay' => $pay];
    if ($why !== '') {
        $query['why'] = $why;
    }
    foreach ($extra as $key => $value) {
        $value = trim((string)$value);
        if ($value !== '') {
            $query[$key] = $value;
        }
    }
    ksers_pay_popup_done('registration.php?' . http_build_query($query));
};

$orderStmt = $pdo->prepare("SELECT * FROM payment_order WHERE seq = ? AND member_seq = ? AND year = ?");
$orderStmt->execute([$orderSeq, $memberSeq, $year]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);
if (!$order || ($order['method'] !== 'card_kr' && $order['method'] !== 'card_os')) {
    ksers_pay_popup_done('registration.php?pay=error');
}
if ($order['status'] === 'paid') {
    ksers_pay_popup_done('registration.php?pay=paid');
}
if ($order['status'] !== 'pending' || $replycode !== '0000') {
    if ($order['status'] === 'pending') {
        $cancel = $pdo->prepare("UPDATE payment_order SET status = 'cancelled' WHERE seq = ? AND status = 'pending'");
        $cancel->execute([$orderSeq]);
    }
    $code = preg_match('/^[A-Za-z0-9_-]{1,12}$/', $replycode) ? $replycode : '';
    $payBack('fail', 'declined', ['code' => $code, 'msg' => $replyMsg]);
}

$orderPrice = ksers_paygate_price((string)(int)$order['amount']);
$postedPrice = ksers_paygate_price($unitprice);
$amountOk = ($orderPrice !== '' && ($postedPrice === '' || $postedPrice === $orderPrice));
$expectedCurrency = $order['method'] === 'card_os' ? 'USD' : 'KRW';
$postedCurrency = strtoupper(trim($currency));
if ($postedCurrency === 'WON') {
    $postedCurrency = 'KRW';
}
$currencyOk = (ksers_fee_currency($order) === $expectedCurrency)
    && ($postedCurrency === '' || $postedCurrency === $expectedCurrency);
$postedSerial = trim($serial);
$serialOk = ($postedSerial === '' || $postedSerial === (string)$orderSeq);
$tidOk = ($tid !== '' && strlen($tid) <= 80);
$hashOk = ksers_paygate_hash_matches($hashresult, $replycode, $tid, (string)$orderSeq, $orderPrice, $expectedCurrency);
if (!$amountOk || !$currencyOk || !$hashOk || !$serialOk || !$tidOk || !ksers_paygate_ready($order['method'])) {
    error_log('ksers pay check'
        . ' amount=' . ($amountOk ? '1' : '0')
        . ' currency=' . ($currencyOk ? '1' : '0')
        . ' hash=' . ($hashOk ? '1' : '0')
        . ' serial=' . ($serialOk ? '1' : '0')
        . ' tid=' . ($tidOk ? '1' : '0')
        . ' hash_len=' . strlen($hashresult)
        . ' price_posted=' . ($postedPrice === '' ? '0' : '1')
        . ' currency_posted=' . ($postedCurrency === '' ? '0' : '1'));
    $why = [];
    if (!$amountOk) {
        $why[] = 'amount';
    }
    if (!$currencyOk) {
        $why[] = 'currency';
    }
    if (!$serialOk) {
        $why[] = 'serial';
    }
    if (!$tidOk) {
        $why[] = 'tid';
    }
    if (!$hashOk) {
        $why[] = 'hash';
    }
    if (!ksers_paygate_ready($order['method'])) {
        $why[] = 'ready';
    }
    $payBack('check', implode(',', $why));
}

$pdo->beginTransaction();
$update = $pdo->prepare(
    "UPDATE payment_order SET status = 'paid', paid_date = NOW(), tid = ? WHERE seq = ? AND status = 'pending'"
);
$update->execute([$tid, $orderSeq]);
if ($update->rowCount() !== 1) {
    $pdo->rollBack();
    ksers_pay_popup_done('registration.php?pay=check');
}
ksers_sync_pay_confirm($pdo, $memberSeq, $year);
$pdo->commit();
ksers_paygate_verify_received($tid);
if (function_exists('ksers_registration_paid_notify')) {
    ksers_registration_paid_notify($pdo, $memberSeq, $year, $order);
}

ksers_pay_popup_done('registration.php?pay=paid');
