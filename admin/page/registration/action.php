<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
require_once __DIR__ . '/../../../ksers_paygate.php';
ksers_require_admin($pdo);

function ksers_payment_list_url() {
    $payPage = isset($_POST['pay_page']) ? (int)$_POST['pay_page'] : 1;
    $query = ['pay_page' => max(1, $payPage)];
    foreach (['user_class', 'affiliation', 'q', 'pay_status'] as $key) {
        if (isset($_POST[$key]) && (string)$_POST[$key] !== '') {
            $query[$key] = (string)$_POST[$key];
        }
    }
    return '/admin/page/payment/list.php?' . http_build_query($query);
}

$year = ksers_admin_year($pdo);
$action = isset($_POST['action']) ? $_POST['action'] : '';
$orderSeq = isset($_POST['order']) ? (int)$_POST['order'] : 0;
$setting = ksers_setting($pdo, $year);

$stmt = $pdo->prepare(
    "SELECT o.*, m.email, m.name_ko
     FROM payment_order o
     JOIN member_info m ON m.seq = o.member_seq
     WHERE o.seq = ? AND o.year = ?"
);
$stmt->execute([$orderSeq, $year]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if ($order && $action === 'confirm' && $order['status'] === 'pending') {
    $update = $pdo->prepare("UPDATE payment_order SET status = 'paid', paid_date = NOW() WHERE seq = ? AND status = 'pending'");
    $update->execute([$orderSeq]);
    ksers_sync_pay_confirm($pdo, (int)$order['member_seq'], $year);
    $amountLabel = ksers_money((int)$order['amount'], ksers_fee_currency($order));
    $bank = $order['method'] === 'bank';
    if ($bank && function_exists('ksers_registration_bank_confirmed_notify')) {
        $sent = ksers_registration_bank_confirmed_notify($pdo, (int)$order['member_seq'], $year, $order);
    } else {
        $sent = ksers_send_mail(
            $setting['mail_from'],
            $order['email'],
            $setting['title_ko'] . ' 결제 완료',
            $order['name_ko'] . " 님의 결제가 완료로 저장되었습니다.\n금액: " . $amountLabel . "\n\nYour payment has been marked paid.\nAmount: " . $amountLabel
        );
    }
    $_SESSION['admin_notice'] = $sent
        ? t('결제를 완료로 저장하고 메일을 보냈습니다.', 'Payment marked paid and email sent.')
        : t('결제를 완료로 저장했습니다. 메일은 보내지 못했습니다. 보내는 주소를 확인해 주세요.', 'Payment marked paid. The email was not sent. Check the sender address.');
}

if ($order && $action === 'cancel' && $order['status'] === 'pending') {
    $update = $pdo->prepare("UPDATE payment_order SET status = 'cancelled' WHERE seq = ? AND status = 'pending'");
    $update->execute([$orderSeq]);
    $_SESSION['admin_notice'] = t('입금 대기를 취소했습니다.', 'The pending payment was cancelled.');
}

if ($order && $action === 'refund' && $order['status'] === 'paid') {
    $card = ($order['method'] === 'card_kr' || $order['method'] === 'card_os');
    $bank = $order['method'] === 'bank';
    $split = ksers_payment_order_amounts($pdo, $orderSeq);
    $return = isset($_POST['return']) ? $_POST['return'] : '';
    $leavePayment = function () use ($return) {
        if ($return === 'payment') {
            header('Location: ' . ksers_payment_list_url());
            exit;
        }
        header('Location: /admin/page/registration/list.php');
        exit;
    };
    if ($bank) {
        $account = ksers_refund_account_label(isset($_POST['refund_account']) ? $_POST['refund_account'] : '');
        if ($account === '') {
            $_SESSION['admin_notice'] = t('환불 계좌 번호는 숫자 4자리 이상이어야 합니다.', 'The refund account number needs at least 4 digits.');
            $leavePayment();
        }
        $update = $pdo->prepare(
            "UPDATE payment_order
             SET status = 'refunded', refund_date = NOW(), refund_account_label = ?, refund_mail_sent = 0
             WHERE seq = ? AND status = 'paid'"
        );
        $update->execute([$account, $orderSeq]);
        ksers_sync_pay_confirm($pdo, (int)$order['member_seq'], $year);
        $bankFrom = ksers_bank_refund_from($pdo, $year);
        $stamp = strtotime($bankFrom);
        $when = $stamp ? (date('Y', $stamp) . '년 ' . date('n', $stamp) . '월 ' . date('j', $stamp) . '일') : $bankFrom;
        if (ksers_seoul_today() >= $bankFrom) {
            ksers_registration_bank_refund_mail_due($pdo, $year);
            $sentStmt = $pdo->prepare("SELECT refund_mail_sent FROM payment_order WHERE seq = ?");
            $sentStmt->execute([$orderSeq]);
            $sent = (int)$sentStmt->fetchColumn() === 1;
            $_SESSION['admin_notice'] = $sent
                ? t('계좌 환불로 저장하고 안내 메일을 보냈습니다.', 'The bank refund was saved and the notice was sent.')
                : t('계좌 환불로 저장했습니다. 안내 메일은 보내지 못했습니다.', 'The bank refund was saved. The notice was not sent.');
        } else {
            $_SESSION['admin_notice'] = t('계좌 환불로 저장했습니다. 환불 안내 메일은 ' . $when . '에 발송됩니다.', 'The bank refund was saved. The notice will be sent on ' . $bankFrom . '.');
        }
    } else {
    if ($card && ($split['registration'] > 0 || $split['dinner'] > 0) && !ksers_registration_refund_open()) {
        $_SESSION['admin_notice'] = t('2027년 4월 1일부터는 환불되지 않습니다.', 'No refunds are made from April 1, 2027.');
        $leavePayment();
    }
    if ($card) {
        list($cancelled, $cancelError) = ksers_paygate_cancel($order['method'], isset($order['tid']) ? $order['tid'] : '');
        if (!$cancelled) {
            $_SESSION['admin_notice'] = $cancelError;
            $leavePayment();
        }
    }
    $update = $pdo->prepare("UPDATE payment_order SET status = 'refunded', refund_date = NOW() WHERE seq = ? AND status = 'paid'");
    $update->execute([$orderSeq]);
    ksers_sync_pay_confirm($pdo, (int)$order['member_seq'], $year);
    if ($card && $split['registration'] > 0 && function_exists('ksers_registration_cancel_notify')) {
        $korea = ksers_member_is_korea($pdo, (int)$order['member_seq']);
        $currency = function_exists('ksers_fee_currency') ? ksers_fee_currency($order) : 'KRW';
        $amountText = ksers_registration_refund_amount_text($korea, [$currency => $split]);
        $methodLabel = $amountText['positive']
            ? ($korea ? '카드 결제 취소' : 'Card payment cancellation')
            : '—';
        $sent = ksers_registration_cancel_notify(
            $pdo,
            (int)$order['member_seq'],
            $year,
            $amountText['plain'],
            $amountText['html'],
            $methodLabel,
            $amountText['positive'],
            false
        );
    } else {
        $sent = ksers_send_mail(
            $setting['mail_from'],
            $order['email'],
            $setting['title_ko'] . ' 전액 환불',
            $order['name_ko'] . " 님의 결제가 전액 환불로 기록되었습니다.\n금액: " . number_format((int)$order['amount']) . "원\n부분 환불은 하지 않습니다.\n\nThe payment was recorded as a full refund.\nAmount: " . number_format((int)$order['amount']) . " KRW"
        );
    }
    $_SESSION['admin_notice'] = $sent
        ? ($card
            ? t('카드사에 전체 취소를 요청하고 환불로 저장했습니다. 메일도 보냈습니다.', 'A full cancellation was sent to the card company, the refund was saved, and the email was sent.')
            : t('전액 환불로 저장하고 메일을 보냈습니다.', 'Full refund saved and email sent.'))
        : ($card
            ? t('카드사에 전체 취소를 요청하고 환불로 저장했습니다. 메일은 보내지 못했습니다.', 'A full cancellation was sent to the card company and the refund was saved. The email was not sent.')
            : t('전액 환불로 저장했습니다. 메일은 보내지 못했습니다.', 'Full refund saved. The email was not sent.'));
    }
}

$return = isset($_POST['return']) ? $_POST['return'] : '';
if ($return === 'payment') {
    header('Location: ' . ksers_payment_list_url());
    exit;
}
header('Location: /admin/page/registration/list.php');
exit;
