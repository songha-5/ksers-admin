<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
require_once __DIR__ . '/../ksers_paygate.php';

$memberSeq = isset($_SESSION['seq']) ? (int)$_SESSION['seq'] : 0;
if ($memberSeq < 1) {
    header('Location: /service/login.php');
    exit;
}
ksers_require_participant($pdo);

unset($_POST['card_number'], $_POST['card_no'], $_POST['card'], $_POST['cvv'], $_POST['cvc']);

$year = isset($eventYear) ? (int)$eventYear : 2027;
$method = isset($_POST['pay_method']) ? $_POST['pay_method'] : '';
$back = 'registration.php?pay=error';
$isCard = ($method === 'card');
$memberKorea = ksers_member_is_korea($pdo, $memberSeq);
if ($isCard) {
    $method = $memberKorea ? 'card_kr' : 'card_os';
}
$leave = function ($location) use ($isCard) {
    if ($isCard) {
        ksers_pay_popup_done($location);
    }
    header('Location: ' . $location);
    exit;
};

if (!$isCard && $method !== 'bank') {
    $leave($back);
}

$participantStmt = $pdo->prepare(
    "SELECT p.seq, p.category, p.support_status, p.cancelled_at, m.country
     FROM participant_info p
     JOIN member_info m ON m.seq = p.member_seq
     WHERE p.member_seq = ? AND p.year = ?"
);
$participantStmt->execute([$memberSeq, $year]);
$participant = $participantStmt->fetch(PDO::FETCH_ASSOC);
if (!$participant || !empty($participant['cancelled_at'])) {
    $leave('registration.php?pay=register');
}
if (strtoupper(trim((string)$participant['country'])) !== 'KR' && in_array($participant['category'], ['student', 'nurse'], true) && $participant['support_status'] !== 'approved') {
    $leave('registration.php?pay=review');
}

$allowed = [];
foreach (ksers_payable_items($pdo, $year, $memberSeq) as $item) {
    $allowed[(int)$item['seq']] = $item;
}
$picked = isset($_POST['fee_seq']) && is_array($_POST['fee_seq']) ? $_POST['fee_seq'] : [];
$lines = [];
$total = 0;
$currency = '';
foreach ($picked as $feeSeq) {
    $feeSeq = (int)$feeSeq;
    if (!isset($allowed[$feeSeq])) {
        continue;
    }
    $itemCurrency = ksers_fee_currency($allowed[$feeSeq]);
    if ($currency === '') {
        $currency = $itemCurrency;
    }
    if ($itemCurrency !== $currency) {
        $leave($back);
    }
    $lines[] = $allowed[$feeSeq];
    $total += (int)$allowed[$feeSeq]['amount'];
}
if (!$lines || $total < 0) {
    $leave('registration.php?pay=empty');
}
if ($total === 0 && (isset($_POST['zero_confirm']) ? $_POST['zero_confirm'] : '') !== '1') {
    $leave('registration.php?pay=zero');
}
if ($total === 0) {
    $pdo->beginTransaction();
    $order = $pdo->prepare(
        "INSERT INTO payment_order (member_seq, year, method, status, amount, currency, reg_date, paid_date)
         VALUES (?, ?, 'bank', 'paid', 0, ?, NOW(), NOW())"
    );
    $order->execute([$memberSeq, $year, $currency === '' ? 'KRW' : $currency]);
    $orderSeq = (int)$pdo->lastInsertId();
    $line = $pdo->prepare(
        "INSERT INTO payment_line (order_seq, fee_seq, name_ko, name_en, amount) VALUES (?, ?, ?, ?, ?)"
    );
    foreach ($lines as $item) {
        $line->execute([$orderSeq, (int)$item['seq'], $item['name_ko'], $item['name_en'], (int)$item['amount']]);
    }
    ksers_sync_pay_confirm($pdo, $memberSeq, $year);
    $pdo->commit();
    $leave('registration.php?pay=paid');
}
if ($method === 'card_kr' && $currency !== 'KRW') {
    $leave('registration.php?pay=krw');
}
if ($method === 'card_os' && $currency !== 'USD') {
    $leave('registration.php?pay=usd');
}
if ($isCard && !ksers_paygate_ready($method)) {
    $leave('registration.php?pay=card');
}

$pdo->beginTransaction();
if ($isCard) {
    $cancel = $pdo->prepare(
        "UPDATE payment_order SET status = 'cancelled'
         WHERE member_seq = ? AND year = ? AND status = 'pending' AND method IN ('card_kr', 'card_os')"
    );
    $cancel->execute([$memberSeq, $year]);
}
$order = $pdo->prepare(
    "INSERT INTO payment_order (member_seq, year, method, status, amount, currency, reg_date)
     VALUES (?, ?, ?, 'pending', ?, ?, NOW())"
);
$order->execute([$memberSeq, $year, $isCard ? $method : 'bank', $total, $currency]);
$orderSeq = (int)$pdo->lastInsertId();
$line = $pdo->prepare(
    "INSERT INTO payment_line (order_seq, fee_seq, name_ko, name_en, amount) VALUES (?, ?, ?, ?, ?)"
);
foreach ($lines as $item) {
    $line->execute([$orderSeq, (int)$item['seq'], $item['name_ko'], $item['name_en'], (int)$item['amount']]);
}
$pdo->commit();

if ($isCard) {
    $_SESSION['paygate_order'] = $orderSeq;
    header('Location: payment_card.php?order=' . $orderSeq);
    exit;
}

if (function_exists('ksers_registration_bank_notify')) {
    ksers_registration_bank_notify($pdo, $memberSeq, $year, [
        'amount' => $total,
        'currency' => $currency,
        'method' => 'bank',
    ]);
}

$leave('registration.php?pay=bank');
