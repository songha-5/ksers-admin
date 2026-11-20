<?php
$payItems = $participant ? ksers_payable_items($pdo, $year, (int)$no) : [];
usort($payItems, function ($left, $right) {
    $leftOptional = empty($left['pay_optional']) ? 0 : 1;
    $rightOptional = empty($right['pay_optional']) ? 0 : 1;
    if ($leftOptional === $rightOptional) {
        return 0;
    }
    return $leftOptional - $rightOptional;
});
$orderStmt = $pdo->prepare("SELECT * FROM payment_order WHERE member_seq = ? AND year = ? ORDER BY seq DESC");
$orderStmt->execute([(int)$no, $year]);
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
$lineStmt = $pdo->prepare(
    "SELECT l.* FROM payment_line l
     JOIN payment_order o ON o.seq = l.order_seq
     WHERE o.member_seq = ? AND o.year = ?
     ORDER BY l.seq"
);
$lineStmt->execute([(int)$no, $year]);
$linesByOrder = [];
foreach ($lineStmt->fetchAll(PDO::FETCH_ASSOC) as $line) {
    $linesByOrder[(int)$line['order_seq']][] = $line;
}
$bankWaiting = [];
foreach ($orders as $order) {
    if ($order['status'] !== 'pending' || $order['method'] !== 'bank' || !isset($linesByOrder[(int)$order['seq']])) {
        continue;
    }
    $orderCurrency = ksers_fee_currency($order);
    foreach ($linesByOrder[(int)$order['seq']] as $line) {
        $line['currency'] = $orderCurrency;
        $bankWaiting[] = $line;
    }
}
$paidRows = [];
foreach ($orders as $order) {
    if ($order['status'] !== 'paid' || !isset($linesByOrder[(int)$order['seq']])) {
        continue;
    }
    $orderCurrency = ksers_fee_currency($order);
    foreach ($linesByOrder[(int)$order['seq']] as $line) {
        $line['currency'] = $orderCurrency;
        $paidRows[] = $line;
    }
}
$payCurrency = $payItems ? ksers_fee_currency($payItems[0]) : 'KRW';
$histPerPage = 10;
$histTotal = count($orders);
$histPages = $histTotal > 0 ? (int)ceil($histTotal / $histPerPage) : 1;
$histPage = isset($_GET['hist_page']) ? (int)$_GET['hist_page'] : 1;
if ($histPage < 1) {
    $histPage = 1;
}
if ($histPage > $histPages) {
    $histPage = $histPages;
}
$histWindow = 10;
$histWindowStart = (int)(floor(($histPage - 1) / $histWindow) * $histWindow) + 1;
$histWindowEnd = min($histWindowStart + $histWindow - 1, $histPages);
$histOrders = array_slice($orders, ($histPage - 1) * $histPerPage, $histPerPage);
$histPageHref = function ($target) {
    return 'registration.php?hist_page=' . (int)$target;
};
$payNotes = [
    'bank' => t('무통장 입금 대기로 저장했습니다. 아래 계좌로 입금해 주세요.', 'Saved as a bank transfer. Pay into the account below.'),
    'card' => t('카드 결제 설정이 없습니다. 카드번호는 이 사이트에 저장하지 않습니다.', 'Card payment is not configured. Card numbers are not saved here.'),
    'paid' => t('카드 결제가 완료되었습니다.', 'The card payment is complete.'),
    'fail' => t('승인 거절로 인해 결제가 되지 않았습니다.', 'The payment was not completed because the approval was declined.'),
    'check' => t('결제사 응답을 금액 검증으로 확인하지 못했습니다. 승인되었다면 사무국에 문의해 주세요.', 'The payment result could not be verified. If the card was approved, contact the secretariat.'),
    'usd' => t('해외 가입자는 달러로 등록된 참가비만 결제할 수 있습니다.', 'An overseas account can pay only a fee registered in US dollars.'),
    'krw' => t('대한민국 가입자는 원으로 등록된 참가비만 결제할 수 있습니다.', 'A Korea account can pay only a fee registered in won.'),
    'account' => t('입금 계좌가 아직 없습니다. 사무국 설정을 기다려 주세요.', 'The bank account is not set yet.'),
    'register' => t('참가 등록 후 결제할 수 있습니다.', 'Register before paying.'),
    'empty' => t('결제할 항목을 선택해 주세요.', 'Choose an item to pay.'),
    'error' => t('결제를 진행하지 못했습니다.', 'The payment could not be started.'),
    'zero' => t('0원 항목은 확인 후에 결제 완료로 저장됩니다.', 'A free item is marked paid after you confirm.'),
    'review' => t('증빙 확인이 끝나기 전에는 결제할 수 없습니다.', 'Payment stays closed until the document review is finished.'),
    'cancelled' => t('결제를 취소했습니다. 카드 결제였으면 카드사에 전체 취소를 요청했습니다.', 'The payment was cancelled. A card payment also sent a full cancellation to the card company.'),
    'cancel_fail' => t('카드사 취소를 완료하지 못해 상태를 바꾸지 않았습니다.', 'The status was not changed because the card cancellation did not succeed.'),
];
$payNote = isset($_GET['pay']) && isset($payNotes[$_GET['pay']]) ? $payNotes[$_GET['pay']] : '';
$payResultTitle = '';
$payResultBody = '';
$payCode = isset($_GET['pay']) ? (string)$_GET['pay'] : '';
if ($payCode === 'cancelled') {
    $payNote = '';
    if (isset($_GET['card']) && (string)$_GET['card'] === '1') {
        $payResultTitle = t('취소가 접수되었습니다', 'The cancellation was submitted');
        $payResultBody = t('카드사에 전체 취소를 신청했습니다. 아직 매입 전이면 카드 앱에서 당일 안에 확인할 수 있습니다. 이미 매입된 결제는 보통 1–3영업일 뒤에 취소로 표시됩니다. 신용카드는 결제 한도가 돌아오며, 계좌로 바로 입금되지는 않습니다.', 'A full cancellation was sent to the card company. If the payment has not been captured, it appears in the card app the same day. A captured payment usually shows as cancelled in 1–3 business days. A credit card limit is restored. The amount is not deposited straight into a bank account.');
    } else {
        $payResultTitle = t('결제를 취소했습니다', 'The payment was cancelled');
        $payResultBody = t('카드사에 승인된 거래가 아니라 이 화면의 상태만 취소로 바꿨습니다. 카드 앱에는 따로 표시되지 않습니다.', 'This payment had no approved card transaction, so only the status on this page was changed. It does not appear in a card app.');
    }
} elseif ($payCode === 'fail' || $payCode === 'check') {
    $payNote = '';
    $payResultTitle = t('결제가 되지 않았습니다', 'The payment was not completed');
    $reasonText = [
        'declined' => t('카드사에서 승인을 거절해 결제가 되지 않았습니다.', 'The card company declined the payment, so it was not completed.'),
        'amount' => t('응답 금액이 없거나 주문 금액과 다릅니다.', 'The returned amount was missing or did not match the order.'),
        'currency' => t('응답 통화가 없거나 주문 통화와 다릅니다.', 'The returned currency was missing or did not match the order.'),
        'serial' => t('주문번호가 없거나 이 주문과 다릅니다.', 'The order number was missing or did not match this order.'),
        'hash' => t('금액 검증값이 맞지 않습니다.', 'The amount verification value did not match.'),
        'tid' => t('카드사 거래번호를 받지 못했습니다.', 'The card transaction number was not received.'),
        'ready' => t('결제 검증 설정이 없습니다.', 'Payment verification is not configured.'),
    ];
    $whyRaw = isset($_GET['why']) ? (string)$_GET['why'] : '';
    $why = [];
    foreach (explode(',', $whyRaw) as $token) {
        $token = trim($token);
        if (isset($reasonText[$token]) && !in_array($token, $why, true)) {
            $why[] = $token;
        }
    }
    if (!$why && $payCode === 'fail') {
        $why[] = 'declined';
    }
    $lines = [];
    if ($payCode === 'check') {
        $lines[] = t('카드사 응답을 확인하지 못해 결제를 완료로 저장하지 않았습니다.', 'The payment was not saved as complete because the card response could not be verified.');
        if (!$why) {
            $lines[] = t('금액, 통화, 주문번호, 거래번호, 검증값 중 확인되지 않은 항목이 있습니다.', 'One of the amount, currency, order number, transaction number, or verification value could not be confirmed.');
        }
    }
    foreach ($why as $token) {
        $lines[] = $reasonText[$token];
    }
    $replyCode = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
    if (preg_match('/^[A-Za-z0-9_-]{1,12}$/', $replyCode)) {
        $lines[] = t('응답 코드', 'Response code') . ': ' . $replyCode;
    }
    $replyMsg = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
    if (function_exists('mb_substr')) {
        $replyMsg = mb_substr($replyMsg, 0, 120, 'UTF-8');
    } else {
        $replyMsg = substr($replyMsg, 0, 120);
    }
    if ($replyMsg !== '') {
        $lines[] = t('사유', 'Reason') . ': ' . $replyMsg;
    }
    if ($payCode === 'check') {
        $lines[] = t('이 화면의 결제는 완료되지 않았습니다. 카드 앱에 승인이 보이면 사무국에 문의해 주세요.', 'This payment was not completed here. If the card app shows an approval, contact the secretariat.');
    }
    $payResultBody = implode("\n", $lines);
} elseif ($payCode === 'cancel_fail') {
    $payNote = '';
    $payResultTitle = t('취소를 진행하지 못했습니다', 'The cancellation could not be completed');
    $reason = isset($_GET['reason']) ? trim((string)$_GET['reason']) : '';
    $payResultBody = $reason !== ''
        ? $reason
        : t('카드사 취소를 완료하지 못해 상태를 바꾸지 않았습니다.', 'The status was not changed because the card cancellation did not succeed.');
}
$payPositive = false;
foreach ($payItems as $payItem) {
    if ((int)$payItem['amount'] > 0) {
        $payPositive = true;
        break;
    }
}
$payCover = '';
$supportReasonText = '';
if (!$member || empty($member['is_admin'])) {
    $supportCategory = $participant && isset($participant['category']) ? (string)$participant['category'] : '';
    if ($participant && !$memberKorea && ($supportCategory === 'student' || $supportCategory === 'nurse')) {
        $supportState = isset($participant['support_status']) ? (string)$participant['support_status'] : '';
        $supportReasonText = isset($participant['support_reason']) ? (string)$participant['support_reason'] : '';
        if ($supportState === 'rejected') {
            $payCover = 'rejected';
        } elseif ($supportState !== 'approved') {
            $payCover = 'review';
        }
    }
}
$payCoverLabel = t('준비중', 'Preparing');
if ($payCover === 'review') {
    $payCoverLabel = t('심사중', 'Under review');
} elseif ($payCover === 'rejected') {
    $payCoverLabel = t('반려', 'Rejected');
}
?>
<?php if ($payCover === 'review') { ?>
<div class="alert alert-info border-0 shadow-sm">
    <p class="fw-bold mb-1"><?= h(t('관리자가 심사중입니다.', 'An administrator is reviewing your document.')) ?></p>
    <p class="mb-0"><?= h(t('회원정보에서 심사 상태를 확인할 수 있습니다.', 'You can check the review status in Profile.')) ?></p>
</div>
<?php } elseif ($payCover === 'rejected') { ?>
<div class="alert alert-danger border-0 shadow-sm">
    <p class="fw-bold mb-1"><?= h(t('증빙서류가 반려되었습니다.', 'The supporting document was rejected.')) ?></p>
    <p class="mb-1"><?= h(t('사무국에 문의하거나 서류를 다시 업로드해 주세요.', 'Contact the secretariat or upload the document again.')) ?></p>
    <?php if (trim($supportReasonText) !== '') { ?>
    <p class="mb-0"><?= h(t('반려 사유', 'Reason for rejection')) ?>: <?= h($supportReasonText) ?></p>
    <?php } ?>
</div>
<?php } ?>
<?php if ($payCover !== '') { ?>
<!-- pay-preparing: 심사·반려일 때만 결제 화면을 가린다. 스테이징의 일반 결제는 이 가림이 없다. -->
<div class="pay-preparing">
    <div class="pay-preparing-panel<?= ($payCover === 'review' || $payCover === 'rejected') ? ' is-review' : '' ?>" role="status">
        <?php if ($payCover === 'review' || $payCover === 'rejected') { ?>
        <div class="pay-status-card">
            <p class="pay-status-title"><?= h($payCoverLabel) ?></p>
            <?php if ($payCover === 'review') { ?>
            <p class="pay-status-copy"><?= h(t('관리자가 심사중입니다.', 'An administrator is reviewing your document.')) ?><br><?= h(t('회원정보에서 심사 상태를 확인할 수 있습니다.', 'You can check the review status in Profile.')) ?></p>
            <?php } else { ?>
            <p class="pay-status-copy"><?= h(t('증빙서류가 반려되었습니다.', 'The supporting document was rejected.')) ?><br><?= h(t('사무국에 문의하거나 서류를 다시 업로드해 주세요.', 'Contact the secretariat or upload the document again.')) ?><?php if (trim($supportReasonText) !== '') { ?><br><?= h(t('반려 사유', 'Reason for rejection')) ?>: <?= h($supportReasonText) ?><?php } ?></p>
            <?php } ?>
        </div>
        <?php } else { ?>
        <p><?= h($payCoverLabel) ?></p>
        <?php } ?>
    </div>
    <div class="pay-preparing-body" inert>
<?php } ?>
<?php if ((!$member || empty($member['is_admin'])) && (!$participant || $payPositive || $bankWaiting)) { ?>
<div class="card border-0 shadow-sm rounded-3 mb-4" id="paymentSection">
    <div class="card-header bg-white py-3">
        <h5 class="card-title m-0 fw-bold"><i class="bi bi-wallet2 me-2"></i><?= h(t('결제', 'Payment')) ?></h5>
    </div>
    <div class="card-body p-4">
        <?php if ($payNote !== '') { ?>
        <div class="alert alert-info border-0"><?= h($payNote) ?></div>
        <?php } ?>
        <?php if (!$participant) { ?>
        <p class="mb-0"><?= h(t('올해 참가 등록이 없습니다. 등록 후 결제할 수 있습니다.', 'There is no registration for this year yet.')) ?></p>
        <?php } else { ?>
        <?php if ($payItems) { ?>
        <form method="post" action="payment.php" id="payForm">
        <input type="hidden" name="zero_confirm" id="zeroConfirm" value="">
        <?php } ?>
            <div class="table-responsive mb-3">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><?= h(t('결제 내용', 'Item')) ?></th>
                            <th class="text-end"><?= h(t('금액', 'Amount')) ?></th>
                            <th><?= h(t('상태', 'Status')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payItems as $item) {
                            $itemCurrency = ksers_fee_currency($item);
                        ?>
                        <tr>
                            <td>
                                <label class="d-flex align-items-center gap-2 mb-0">
                                    <input class="form-check-input pay-check m-0" type="checkbox" name="fee_seq[]" value="<?= (int)$item['seq'] ?>" data-price="<?= (int)$item['amount'] ?>"<?= empty($item['pay_optional']) ? ' checked' : '' ?> onchange="calculateMyTotal()">
                                    <span><?= h(ksers_text($item, 'name_ko', 'name_en')) ?></span>
                                </label>
                            </td>
                            <td class="text-end"><?= h(ksers_money($item['amount'], $itemCurrency)) ?></td>
                            <td><?= h(t('미결제', 'Unpaid')) ?></td>
                        </tr>
                        <?php } ?>
                        <?php foreach ($bankWaiting as $item) { ?>
                        <tr>
                            <td><?= h(ksers_text($item, 'name_ko', 'name_en')) ?></td>
                            <td class="text-end"><?= h(ksers_money($item['amount'], ksers_fee_currency($item))) ?></td>
                            <td><?= h(t('입금 확인 중', 'Checking the transfer')) ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php if ($payItems) { ?>
            <div class="pay-total mb-4">
                <span><?= h(t('총 결제금액', 'Total')) ?></span>
                <strong id="selectedTotalAmount" data-unit="<?= h($payCurrency === 'USD' ? ' USD' : t('원', ' KRW')) ?>"></strong>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="pay-method-card">
                        <input type="radio" name="pay_method" value="card" checked>
                        <span class="pay-method-mark" aria-hidden="true"></span>
                        <span>
                            <span class="d-block fw-bold"><?= h(t('카드 결제', 'Card')) ?></span>
                            <span class="d-block small text-muted"><?= h($memberKorea
                                ? t('원화(KRW) 결제', 'KRW payment')
                                : t('USD 결제', 'USD payment')) ?></span>
                        </span>
                    </label>
                </div>
                <div class="col-md-6">
                    <label class="pay-method-card">
                        <input type="radio" name="pay_method" value="bank">
                        <span class="pay-method-mark" aria-hidden="true"></span>
                        <span>
                            <span class="d-block fw-bold"><?= h(t('계좌 송금', 'Bank transfer')) ?></span>
                            <span class="d-block small text-muted"><?= h(t('학회 계좌로 직접 이체', 'Transfer directly to the society account')) ?></span>
                        </span>
                    </label>
                </div>
            </div>
            <div class="pay-guide mb-4">
                <?php if ($memberKorea) { ?>
                <h6 class="pay-guide-title fw-bold mb-3">카드 결제 KRW · 국내 계좌</h6>
                <h6 class="fw-bold mb-2">결제 안내</h6>
                <ul class="mb-3">
                    <li>결제할 항목을 선택한 뒤 결제 방법(카드 결제 / 계좌 송금)을 골라 주세요.</li>
                    <li>결제가 완료되면 이 화면에서 등록 내용을 바꿀 수 없습니다. 변경·취소는 사무국(ksers2027@ksers.kr)으로 문의해 주세요.</li>
                </ul>
                <h6 class="fw-bold mb-2">카드 결제</h6>
                <ul class="mb-3">
                    <li>원화(KRW)로 결제됩니다.</li>
                    <li>결제는 새 창에서 진행되며, 카드 정보는 이 사이트에 저장되지 않습니다.</li>
                </ul>
                <h6 class="fw-bold mb-2">계좌 송금</h6>
                <ul class="mb-0">
                    <li>입금 계좌: 하나은행 576-910013-04605 (예금주: 대한내시경로봇외과학회)</li>
                    <li>입금자명은 등록자 이름과 같게 입력해 주세요.</li>
                    <li>'결제' 버튼을 눌러도 바로 출금되지 않습니다. 인터넷뱅킹이나 모바일뱅킹으로 직접 이체해 주세요.</li>
                    <li>사무국이 입금을 확인한 뒤 '완납'으로 바뀝니다. 확인까지 영업일 기준 5일 이내 걸립니다.</li>
                </ul>
                <?php } else { ?>
                <h6 class="pay-guide-title fw-bold mb-3">Credit card USD · overseas account</h6>
                <h6 class="fw-bold mb-2">Payment Information</h6>
                <ul class="mb-3">
                    <li>Please select the items to pay, then choose a payment method (Credit Card / Bank Transfer).</li>
                    <li>Once payment is completed, your registration cannot be changed on this page. For changes or cancellation, please contact the Secretariat (ksers2027@ksers.kr).</li>
                </ul>
                <h6 class="fw-bold mb-2">Credit Card</h6>
                <ul class="mb-3">
                    <li>Payment will be processed in USD.</li>
                    <li>Payment is processed in a new window. Your card information is not stored on this site.</li>
                </ul>
                <h6 class="fw-bold mb-2">Bank Transfer</h6>
                <ul class="mb-0">
                    <li>Bank Name / Account No. / Account Holder / SWIFT Code / Bank Address / Branch: <span class="pay-guide-pending">[학회 확인 후 별도 전달]</span></li>
                    <li>Please make the transfer under the same name as the registrant.</li>
                    <li>All bank charges are the responsibility of the remitter. Please make sure the full registration fee is received.</li>
                    <li>Clicking "Pay" does not withdraw money. Please transfer the amount via your bank.</li>
                    <li>Your status will change to "Paid" after the Secretariat confirms your payment. This may take up to 5 business days.</li>
                </ul>
                <?php } ?>
            </div>
            <div class="d-flex justify-content-end">
                <button class="btn btn-lg btn-primary-custom px-4" type="submit" id="paySubmit" data-card="<?= h(t('%s 결제하기', 'Pay %s')) ?>" data-bank="<?= h(t('송금 신청', 'Request a transfer')) ?>" disabled><?= h(t('송금 신청', 'Request a transfer')) ?></button>
            </div>
        </form>
            <?php } ?>
        <?php } ?>
    </div>
</div>
<?php } ?>

<?php if (!$member || empty($member['is_admin'])) { ?>
<div class="card border-0 shadow-sm rounded-3 mb-5">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="card-title m-0 fw-bold"><i class="bi bi-receipt me-2"></i><?= h(t('결제 기록과 서류', 'Payments and documents')) ?></h5>
        <?php if ($participant) { ?>
        <a class="btn btn-sm btn-outline-secondary" href="document.php?kind=confirmation" onclick="return ksersOpenConfirmation(this.href);"><?= h(t('등록 확인서', 'Confirmation')) ?></a>
        <?php } ?>
    </div>
    <div class="table-responsive site-table-gap">
        <table class="table align-middle mb-0 pay-history-table">
            <thead class="table-light">
                <tr>
                    <th><?= h(t('번호', 'No.')) ?></th>
                    <th><?= h(t('항목', 'Item')) ?></th>
                    <th class="text-center"><?= h(t('상태', 'Status')) ?></th>
                    <th class="pay-history-amount text-center"><?= h(t('금액', 'Amount')) ?></th>
                    <th class="text-center"><?= h(t('서류', 'Document')) ?></th>
                    <th class="pay-history-cancel"><?= h(t('결제 취소', 'Cancel payment')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$orders) { ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= h(t('결제 기록이 없습니다.', 'No payments yet.')) ?></td></tr>
                <?php } ?>
                <?php foreach ($histOrders as $order) {
                    $names = [];
                    if (isset($linesByOrder[(int)$order['seq']])) {
                        foreach ($linesByOrder[(int)$order['seq']] as $line) {
                            $names[] = ksers_text($line, 'name_ko', 'name_en');
                        }
                    }
                ?>
                <tr>
                    <td><?= h('P' . $year . '-' . str_pad((string)$order['seq'], 4, '0', STR_PAD_LEFT)) ?></td>
                    <td><?= h(implode(', ', $names)) ?></td>
                    <td class="text-center"><?= h(ksers_payment_status_label($order['status'], $order['method'], $order['amount'])) ?></td>
                    <td class="pay-history-amount text-end"><?= h(ksers_money($order['amount'], ksers_fee_currency($order))) ?></td>
                    <td class="text-center">
                        <?php if ($order['status'] === 'paid' || $order['status'] === 'refunded') { ?>
                        <a href="document.php?kind=receipt&order=<?= (int)$order['seq'] ?>" onclick="return ksersOpenSheet(this.href);"><?= h(t('영수증', 'Receipt')) ?></a>
                        <?php } ?>
                    </td>
                    <td class="pay-history-cancel">
                        <?php
                        $bankPaid = ($order['method'] === 'bank' && $order['status'] === 'paid');
                        $canCancel = ($order['status'] === 'pending' || ($order['status'] === 'paid' && !$bankPaid));
                        ?>
                        <?php if (!$bankPaid) { ?>
                        <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#payCancelModal" data-order="<?= (int)$order['seq'] ?>"<?= $canCancel ? '' : ' disabled' ?>><?= h(t('결제 취소', 'Cancel payment')) ?></button>
                        <?php } ?>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-3">
        <nav aria-label="<?= h(t('결제 기록 페이지', 'Payment history pages')) ?>">
            <ul class="pagination pagination-sm justify-content-center m-0">
                <li class="page-item<?= $histPage <= 1 ? ' disabled' : '' ?>">
                    <?php if ($histPage <= 1) { ?>
                    <span class="page-link"><?= h(t('이전', 'Previous')) ?></span>
                    <?php } else { ?>
                    <a class="page-link" href="<?= h($histPageHref($histPage - 1)) ?>"><?= h(t('이전', 'Previous')) ?></a>
                    <?php } ?>
                </li>
                <?php for ($i = $histWindowStart; $i <= $histWindowEnd; $i++) { ?>
                <li class="page-item<?= $i === $histPage ? ' active' : '' ?>">
                    <a class="page-link" href="<?= h($histPageHref($i)) ?>"><?= $i ?></a>
                </li>
                <?php } ?>
                <li class="page-item<?= $histPage >= $histPages ? ' disabled' : '' ?>">
                    <?php if ($histPage >= $histPages) { ?>
                    <span class="page-link"><?= h(t('다음', 'Next')) ?></span>
                    <?php } else { ?>
                    <a class="page-link" href="<?= h($histPageHref($histPage + 1)) ?>"><?= h(t('다음', 'Next')) ?></a>
                    <?php } ?>
                </li>
            </ul>
        </nav>
    </div>
</div>
<?php } ?>
<?php if ($payCover !== '') { ?>
    </div>
</div>
<!-- /pay-preparing -->
<?php } ?>

<div class="modal fade" id="bankPayModal" tabindex="-1" aria-labelledby="bankPayTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bankPayTitle"><?= h(t('송금 신청', 'Bank transfer')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body"><?= h(t('송금신청 하시겠습니까?', 'Request a bank transfer?')) ?></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(t('취소', 'Cancel')) ?></button>
                <button type="button" class="btn btn-primary-custom" id="bankPayConfirm"><?= h(t('확인', 'Confirm')) ?></button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="zeroPayModal" tabindex="-1" aria-labelledby="zeroPayTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="zeroPayTitle"><?= h(t('0원 결제', 'Free payment')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body"><?= h(t('선택한 항목은 0원입니다. 카드와 계좌 송금은 0원을 결제할 수 없어, 확인하면 선택한 0원 항목만 결제 완료로 저장합니다.', 'The selected items are free. Card and bank transfer cannot charge zero, so confirming marks only those selected free items as paid.')) ?></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(t('취소', 'Cancel')) ?></button>
                <button type="button" class="btn btn-primary-custom" id="zeroPayConfirm"><?= h(t('결제 완료', 'Mark paid')) ?></button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="payCancelModal" tabindex="-1" aria-labelledby="payCancelTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="payment_cancel.php">
            <div class="modal-header">
                <h5 class="modal-title" id="payCancelTitle"><?= h(t('결제 취소', 'Cancel payment')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body"><?= h(t('이 결제를 취소할까요? 카드 결제이고 카드사 거래번호가 있으면 카드사에 전체 취소를 보낸 뒤에 상태가 취소로 바뀝니다.', 'Cancel this payment? A card payment with a card-company transaction number sends a full cancellation first, then the status becomes cancelled.')) ?></div>
            <div class="modal-footer">
                <input type="hidden" name="order" id="payCancelOrder" value="">
                <input type="hidden" name="hist_page" value="<?= (int)$histPage ?>">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(t('닫기', 'Close')) ?></button>
                <button class="btn btn-danger" type="submit"><?= h(t('취소 확인', 'Confirm cancel')) ?></button>
            </div>
        </form>
    </div>
</div>
<?php if ($payResultTitle !== '') { ?>
<div class="modal fade" id="payResultModal" tabindex="-1" aria-labelledby="payResultTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="payResultTitle"><?= h($payResultTitle) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body"><?= nl2br(h($payResultBody)) ?></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary-custom" data-bs-dismiss="modal"><?= h(t('확인', 'OK')) ?></button>
            </div>
        </div>
    </div>
</div>
<?php } ?>
<script>
    function ksersPayPrice(item) {
        const price = Number(item.getAttribute('data-price'));
        return Number.isFinite(price) ? price : 0;
    }

    function ksersOpenConfirmation(url) {
        const width = 768;
        const height = 900;
        const left = Math.max(0, Math.round((screen.width - width) / 2));
        const top = Math.max(0, Math.round((screen.height - height) / 2));
        const popup = window.open(url, 'ksersConfirmation', 'popup=yes,width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes');
        if (popup) {
            popup.focus();
        }
        return false;
    }

    function ksersOpenSheet(url) {
        const width = 720;
        const height = 780;
        const left = Math.max(0, Math.round((screen.width - width) / 2));
        const top = Math.max(0, Math.round((screen.height - height) / 2));
        const popup = window.open(url, 'ksersReceipt', 'popup=yes,width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes');
        if (popup) {
            popup.focus();
        }
        return false;
    }

    function calculateMyTotal() {
        let total = 0;
        document.querySelectorAll('.pay-check').forEach(item => {
            const row = item.closest('tr');
            const due = row ? row.querySelector('.pay-due') : null;
            const price = ksersPayPrice(item);
            if (item.checked) {
                total += price;
            }
            if (due) {
                due.textContent = item.checked ? (due.getAttribute('data-money') || '') : (due.getAttribute('data-money-zero') || '');
            }
        });
        const target = document.getElementById('selectedTotalAmount');
        if (target) {
            target.innerText = total.toLocaleString() + (target.getAttribute('data-unit') || <?= json_encode(t('원', ' KRW'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
        }
        const button = document.getElementById('paySubmit');
        if (button) {
            const card = document.querySelector('input[name="pay_method"][value="card"]');
            const checkedCount = document.querySelectorAll('.pay-check:checked').length;
            const bankLabel = button.getAttribute('data-bank') || '';
            const cardLabel = button.getAttribute('data-card') || '%s';
            const amountText = target ? target.innerText : '';
            button.disabled = checkedCount < 1;
            button.textContent = (card && card.checked)
                ? cardLabel.replace('%s', amountText)
                : bankLabel;
        }
        return total;
    }
    document.querySelectorAll('input[name="pay_method"]').forEach(function (radio) {
        radio.addEventListener('change', calculateMyTotal);
    });
    calculateMyTotal();

    document.getElementById('bankPayConfirm')?.addEventListener('click', function () {
        const form = document.getElementById('payForm');
        if (!form) {
            return;
        }
        form.dataset.bankOk = '1';
        if (window.ksersAllowLeave) {
            window.ksersAllowLeave();
        }
        if (window.bootstrap) {
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('bankPayModal')).hide();
        }
        form.submit();
    });

    document.getElementById('zeroPayConfirm')?.addEventListener('click', function () {
        const form = document.getElementById('payForm');
        const flag = document.getElementById('zeroConfirm');
        if (!form || !flag) {
            return;
        }
        flag.value = '1';
        form.dataset.zeroOk = '1';
        form.removeAttribute('target');
        if (window.ksersAllowLeave) {
            window.ksersAllowLeave();
        }
        if (window.bootstrap) {
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('zeroPayModal')).hide();
        }
        form.submit();
    });

    document.addEventListener('DOMContentLoaded', function () {
        const payResult = document.getElementById('payResultModal');
        if (payResult && window.bootstrap) {
            window.bootstrap.Modal.getOrCreateInstance(payResult).show();
        }
    });

    document.getElementById('payCancelModal')?.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button || button.disabled) {
            event.preventDefault();
            return;
        }
        document.getElementById('payCancelOrder').value = button.getAttribute('data-order') || '';
    });

    document.getElementById('payForm')?.addEventListener('submit', function (event) {
        const checked = Array.from(this.querySelectorAll('.pay-check:checked'));
        const total = calculateMyTotal();
        if (checked.length > 0 && total === 0 && this.dataset.zeroOk !== '1') {
            event.preventDefault();
            if (window.bootstrap) {
                window.bootstrap.Modal.getOrCreateInstance(document.getElementById('zeroPayModal')).show();
            }
            return;
        }
        const selected = this.querySelector('input[name="pay_method"]:checked');
        if (!selected || selected.value !== 'card') {
            this.removeAttribute('target');
            if (selected && selected.value === 'bank' && this.dataset.bankOk !== '1') {
                event.preventDefault();
                if (window.bootstrap) {
                    window.bootstrap.Modal.getOrCreateInstance(document.getElementById('bankPayModal')).show();
                }
            } else if (window.ksersAllowLeave) {
                window.ksersAllowLeave();
            }
            return;
        }
        const width = 720;
        const height = 780;
        const left = Math.max(0, Math.round((screen.width - width) / 2));
        const top = Math.max(0, Math.round((screen.height - height) / 2));
        const features = 'popup=yes,width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes';
        const popup = window.open('', 'ksersPay', features);
        if (!popup) {
            event.preventDefault();
            alert(<?= json_encode(t('결제 창이 차단되었습니다. 브라우저에서 팝업을 허용해 주세요.', 'The payment window was blocked. Allow pop-ups for this site.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
            return;
        }
        if (window.ksersAllowLeave) {
            window.ksersAllowLeave();
        }
        popup.focus();
        this.target = 'ksersPay';
    });
</script>

