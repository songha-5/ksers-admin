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
$orderSeq = isset($_GET['order']) ? (int)$_GET['order'] : 0;
$orderStmt = $pdo->prepare(
    "SELECT * FROM payment_order WHERE seq = ? AND member_seq = ? AND year = ? AND status = 'pending'"
);
$orderStmt->execute([$orderSeq, $memberSeq, $year]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);
$profile = $order ? ksers_paygate_profile($order['method']) : null;
$orderCurrency = $order ? ksers_fee_currency($order) : '';
$profileOk = $profile && (
    ($order['method'] === 'card_kr' && $orderCurrency === 'KRW' && $profile['currency'] === 'WON')
    || ($order['method'] === 'card_os' && $orderCurrency === 'USD' && $profile['currency'] === 'USD')
);
if (!$order || !$profileOk || !ksers_paygate_ready($order['method'])) {
    ksers_pay_popup_done('registration.php?pay=error');
}

$memberStmt = $pdo->prepare("SELECT email, name_ko, first_name_eng, last_name_eng FROM member_info WHERE seq = ?");
$memberStmt->execute([$memberSeq]);
$member = $memberStmt->fetch(PDO::FETCH_ASSOC);
if (!$member) {
    ksers_pay_popup_done('registration.php?pay=error');
}
$buyer = trim((string)$member['name_ko']);
if ($buyer === '') {
    $buyer = trim((string)$member['first_name_eng'] . ' ' . (string)$member['last_name_eng']);
}
if (function_exists('mb_substr')) {
    $buyer = mb_substr($buyer, 0, 20, 'UTF-8');
}

$lineStmt = $pdo->prepare("SELECT name_ko, name_en FROM payment_line WHERE order_seq = ? ORDER BY seq");
$lineStmt->execute([$orderSeq]);
$names = [];
foreach ($lineStmt->fetchAll(PDO::FETCH_ASSOC) as $line) {
    $names[] = ksers_text($line, 'name_ko', 'name_en');
}
$goodname = 'KSERS ' . $year;
if ($names) {
    $goodname .= ' ' . implode(', ', $names);
}
if (function_exists('mb_substr')) {
    $goodname = mb_substr($goodname, 0, 100, 'UTF-8');
}

$page_title = t('카드 결제', 'Card payment');
$serial = (string)$orderSeq;
$unitprice = (string)(int)$order['amount'];
?>
<!DOCTYPE html>
<html lang="<?= h(ksers_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?></title>
    <link href="/service/ksers.css" rel="stylesheet">
    <style>
        body { margin: 0; background: #fff; }
        .pay-card-wrap {
            width: min(560px, calc(100% - 48px));
            margin: 0 auto;
            padding: 28px 0 16px;
        }
        @media (min-width: 480px) {
            .pay-card-wrap { width: min(560px, calc(100% - 64px)); }
        }
        .pay-card-wrap h1 { margin: 0 0 12px; font-size: 1.35rem; }
        .pay-card-note { margin: 0; color: #d32f2f; font-size: 0.95rem; line-height: 1.55; }
        .pay-card-amount { margin: 14px 0 18px; color: #0b3a82; font-size: 1.2rem; font-weight: 800; }
    </style>
    <script src="<?= h(ksers_paygate_script()) ?>"></script>
    <script>
    function pgPayValue(name) {
        var fromApi = (typeof getPGIOElement === 'function') ? getPGIOElement(name) : '';
        if (fromApi === undefined || fromApi === null) {
            fromApi = '';
        }
        fromApi = String(fromApi);
        if (fromApi === 'undefined' || fromApi === 'null') {
            fromApi = '';
        }
        var form = document.PGIOForm;
        var fromForm = (form && form.elements[name]) ? String(form.elements[name].value || '') : '';
        if (fromForm === 'undefined' || fromForm === 'null') {
            fromForm = '';
        }
        return fromApi !== '' ? fromApi : fromForm;
    }
    function getPGIOresult() {
        var result = document.getElementById('payResult');
        if (!result || typeof getPGIOElement !== 'function') {
            location.href = 'registration.php?pay=fail';
            return;
        }
        if (typeof displayStatus === 'function') {
            displayStatus(getPGIOElement('ResultScreen'));
        }
        result.elements['replycode'].value = pgPayValue('replycode');
        result.elements['replyMsg'].value = pgPayValue('replyMsg');
        result.elements['tid'].value = pgPayValue('tid');
        result.elements['hashresult'].value = pgPayValue('hashresult');
        result.elements['unitprice'].value = pgPayValue('unitprice');
        result.elements['goodcurrency'].value = pgPayValue('goodcurrency');
        result.elements['mb_serial_no'].value = pgPayValue('mb_serial_no');
        result.submit();
    }
    </script>
</head>
<body>
<div class="pay-card-wrap">
    <h1><?= h($page_title) ?></h1>
    <p class="pay-card-note"><?= h(t('결제사 화면에서 카드 정보를 입력합니다. 카드 번호는 이 사이트에 저장되지 않습니다.', 'Enter the card details on the payment company screen. Card numbers are not stored on this site.')) ?></p>
    <p class="pay-card-amount"><?= h(t('결제 금액: ', 'Payment amount: ') . ksers_money($order['amount'], $orderCurrency)) ?></p>
    <div id="PGIOscreen"></div>
</div>
<form name="PGIOForm" method="post">
    <input type="hidden" name="charset" value="UTF-8">
    <input type="hidden" name="mid" value="<?= h($profile['mid']) ?>">
    <input type="hidden" name="langcode" value="<?= h($profile['langcode']) ?>">
    <input type="hidden" name="paymethod" value="<?= h($profile['paymethod']) ?>">
    <input type="hidden" name="goodcurrency" value="<?= h($profile['currency']) ?>">
    <input type="hidden" name="unitprice" value="<?= h($unitprice) ?>">
    <input type="hidden" name="goodname" value="<?= h($goodname) ?>">
    <input type="hidden" name="receipttoname" value="<?= h($buyer) ?>">
    <input type="hidden" name="receipttoemail" value="<?= h($member['email']) ?>">
    <input type="hidden" name="mb_serial_no" value="<?= h($serial) ?>">
    <input type="hidden" name="tid" value="">
    <input type="hidden" name="replycode" value="">
    <input type="hidden" name="replyMsg" value="">
    <input type="hidden" name="hashresult" value="">
    <input type="hidden" name="profile_no" value="">
    <input type="hidden" name="riskscore" value="">
    <input type="hidden" name="cardtype" value="">
    <input type="hidden" name="cardnumber" value="">
    <input type="hidden" name="cardsecretnumber" value="">
    <input type="hidden" name="cardexpiremonth" value="">
    <input type="hidden" name="cardexpireyear" value="">
    <input type="hidden" name="cardownernumber" value="">
    <input type="hidden" name="cardauthcode" value="">
    <input type="hidden" name="cardquota" value="00">
</form>
<form id="payResult" method="post" action="payment_return.php">
    <input type="hidden" name="order" value="<?= (int)$orderSeq ?>">
    <input type="hidden" name="replycode" value="">
    <input type="hidden" name="replyMsg" value="">
    <input type="hidden" name="tid" value="">
    <input type="hidden" name="hashresult" value="">
    <input type="hidden" name="unitprice" value="">
    <input type="hidden" name="goodcurrency" value="">
    <input type="hidden" name="mb_serial_no" value="">
</form>
<script>
window.addEventListener('load', function () {
    if (typeof doTransaction === 'function') {
        doTransaction(document.PGIOForm);
    }
});
</script>
</body>
</html>
