<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
$year = ksers_admin_year($pdo);
if (function_exists('ksers_registration_bank_refund_mail_due')) {
    ksers_registration_bank_refund_mail_due($pdo, $year);
}
$page_title = t('결제 내역 - ' . $year . ' 국제학술대회 관리자', 'Payment history - ' . $year . ' Conference Admin');
$page_heading = t('결제', 'Payments');

include '../../lib/header.php';
include '../../lib/menu.php';
$paymentTab = 'history';
include 'tabs.php';

$perPage = 20;
$page = isset($_GET['pay_page']) ? (int)$_GET['pay_page'] : 1;
if ($page < 1) {
    $page = 1;
}
$affiliationTypes = ksers_affiliation_types();
$userClasses = ['M0', 'M1', 'M2', 'M3'];
$statusOptions = [
    'pending' => t('입금 확인 중', 'Checking the transfer'),
    'paid' => t('결제 완료', 'Paid'),
    'refunded' => t('환불', 'Refunded'),
    'cancelled' => t('취소', 'Cancelled'),
];
$filterClass = isset($_GET['user_class']) && in_array($_GET['user_class'], $userClasses, true) ? $_GET['user_class'] : '';
$filterAffiliation = isset($_GET['affiliation']) && isset($affiliationTypes[$_GET['affiliation']]) ? $_GET['affiliation'] : '';
$filterStatus = isset($_GET['pay_status']) && isset($statusOptions[$_GET['pay_status']]) ? $_GET['pay_status'] : '';
$filterQuery = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$where = ['o.year = ?'];
$params = [$year];
if ($filterClass !== '') {
    $where[] = 'm.user_class = ?';
    $params[] = $filterClass;
}
if ($filterAffiliation !== '') {
    $where[] = 'm.affiliation_type = ?';
    $params[] = $filterAffiliation;
}
if ($filterStatus !== '') {
    $where[] = 'o.status = ?';
    $params[] = $filterStatus;
}
if ($filterQuery !== '') {
    $like = '%' . $filterQuery . '%';
    $amountSql = '';
    if (preg_match('/^\d+$/', $filterQuery)) {
        $amountSql = ' OR o.amount = ?';
    }
    $where[] = '(m.name_ko LIKE ? OR m.first_name_eng LIKE ? OR m.last_name_eng LIKE ? OR CONCAT(m.first_name_eng, \' \', m.last_name_eng) LIKE ?' . $amountSql . ')';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    if ($amountSql !== '') {
        $params[] = (int)$filterQuery;
    }
}
$whereSql = implode(' AND ', $where);
$count = $pdo->prepare(
    "SELECT COUNT(*) FROM payment_order o JOIN member_info m ON m.seq = o.member_seq WHERE " . $whereSql
);
$count->execute($params);
$total = (int)$count->fetchColumn();
$pages = $total > 0 ? (int)ceil($total / $perPage) : 1;
if ($page > $pages) {
    $page = $pages;
}
$pageWindow = 10;
$windowStart = (int)(floor(($page - 1) / $pageWindow) * $pageWindow) + 1;
$windowEnd = min($windowStart + $pageWindow - 1, $pages);
$offset = ($page - 1) * $perPage;
$stmt = $pdo->prepare(
    "SELECT o.seq, o.method, o.status, o.amount, o.currency, o.reg_date, o.paid_date,
            m.seq AS member_seq, m.email, m.name_ko, m.first_name_eng, m.last_name_eng,
            m.affiliation_type, m.user_class,
            (SELECT c.name_ko FROM code c WHERE c.majr = 1 AND c.iso = m.country ORDER BY c.seq LIMIT 1) AS country_ko,
            (SELECT c.name_eng FROM code c WHERE c.majr = 1 AND c.iso = m.country ORDER BY c.seq LIMIT 1) AS country_en
     FROM payment_order o
     JOIN member_info m ON m.seq = o.member_seq
     WHERE " . $whereSql . "
     ORDER BY o.reg_date DESC, o.seq DESC
     LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
$methodLabel = [
    'bank' => t('무통장 입금', 'Bank transfer'),
    'card_kr' => t('국내 카드', 'Domestic card'),
    'card_os' => t('해외 카드', 'Overseas card'),
];
$filterArgs = [
    'user_class' => $filterClass,
    'affiliation' => $filterAffiliation,
    'q' => $filterQuery,
    'pay_status' => $filterStatus,
];
$payPageHref = function ($target) use ($filterArgs) {
    $query = $filterArgs;
    $query['pay_page'] = (int)$target;
    return 'list.php?' . http_build_query($query);
};
?>

<?php if (!empty($_SESSION['admin_notice'])) { ?>
<div class="alert alert-info border-0 shadow-sm"><?= h($_SESSION['admin_notice']) ?></div>
<?php unset($_SESSION['admin_notice']); } ?>

<form class="card border-0 shadow-sm mb-3" method="get" action="list.php">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1" for="filterClass"><?= h(t('회원 유형', 'Member class')) ?></label>
                <select class="form-select form-select-sm" id="filterClass" name="user_class">
                    <option value=""><?= h(t('전체', 'All')) ?></option>
                    <?php foreach ($userClasses as $classCode) { ?>
                    <option value="<?= h($classCode) ?>"<?= $filterClass === $classCode ? ' selected' : '' ?>><?= h(ksers_user_class_label($classCode)) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1" for="filterAffiliation"><?= h(t('소속 선택', 'Affiliation')) ?></label>
                <select class="form-select form-select-sm" id="filterAffiliation" name="affiliation">
                    <option value=""><?= h(t('전체', 'All')) ?></option>
                    <?php foreach ($affiliationTypes as $typeId => $typeLabel) { ?>
                    <option value="<?= h($typeId) ?>"<?= $filterAffiliation === $typeId ? ' selected' : '' ?>><?= h(t($typeLabel[0], $typeLabel[1])) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1" for="filterQuery"><?= h(t('이름 / 결제 금액', 'Name / amount')) ?></label>
                <input class="form-control form-control-sm" id="filterQuery" name="q" value="<?= h($filterQuery) ?>" placeholder="<?= h(t('이름 또는 금액', 'Name or amount')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1" for="filterStatus"><?= h(t('결제 상태', 'Payment status')) ?></label>
                <select class="form-select form-select-sm" id="filterStatus" name="pay_status">
                    <option value=""><?= h(t('전체', 'All')) ?></option>
                    <?php foreach ($statusOptions as $statusCode => $statusLabel) { ?>
                    <option value="<?= h($statusCode) ?>"<?= $filterStatus === $statusCode ? ' selected' : '' ?>><?= h($statusLabel) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
                <button class="btn btn-sm btn-primary-custom" type="submit"><?= h(t('검색', 'Search')) ?></button>
                <a class="btn btn-sm ksers-outline" href="list.php"><?= h(t('초기화', 'Reset')) ?></a>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="m-0 fw-bold"><?= h(t('결제 내역', 'Payment history')) ?> <span class="text-muted fs-6"><?= $total ?></span></h5>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0 text-nowrap">
            <thead class="table-light">
                <tr>
                    <th><?= h(t('번호', 'No.')) ?></th>
                    <th><?= h(t('회원 유형', 'Member class')) ?></th>
                    <th><?= h(t('소속 선택', 'Affiliation')) ?></th>
                    <th><?= h(t('이름', 'Name')) ?></th>
                    <th><?= h(t('결제 금액', 'Amount')) ?></th>
                    <th><?= h(t('결제수단', 'Method')) ?></th>
                    <th><?= h(t('결제 상태', 'Status')) ?></th>
                    <th><?= h(t('주문일', 'Ordered')) ?></th>
                    <th><?= h(t('결제일', 'Paid')) ?></th>
                    <th><?= h(t('결제 관리', 'Payment actions')) ?></th>
                    <th><?= h(t('회원으로 이동', 'Open member')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$payments) { ?>
                <tr><td colspan="11" class="text-center text-muted py-4"><?= h(t('결제 기록이 없습니다.', 'No payments.')) ?></td></tr>
                <?php } ?>
                <?php foreach ($payments as $payment) {
                    $method = isset($methodLabel[$payment['method']]) ? $methodLabel[$payment['method']] : $payment['method'];
                    $affiliation = '';
                    if (isset($affiliationTypes[$payment['affiliation_type']])) {
                        $affiliation = t($affiliationTypes[$payment['affiliation_type']][0], $affiliationTypes[$payment['affiliation_type']][1]);
                    } elseif (trim((string)$payment['affiliation_type']) === '' && ($payment['country_en'] === 'United States' || $payment['country_ko'] === '미국')) {
                        $affiliation = t('외국인', 'Overseas');
                    }
                    $name = trim((string)$payment['name_ko']);
                    if ($name === '') {
                        $name = trim($payment['first_name_eng'] . ' ' . $payment['last_name_eng']);
                    }
                    $memberQuery = ['q' => $payment['email'], 'member' => (int)$payment['member_seq']];
                ?>
                <tr>
                    <td><?= h('P' . $year . '-' . str_pad((string)$payment['seq'], 4, '0', STR_PAD_LEFT)) ?></td>
                    <td><?= h(ksers_user_class_label($payment['user_class'])) ?></td>
                    <td><?= h($affiliation) ?></td>
                    <td><?= h($name) ?></td>
                    <td><?= h(ksers_money($payment['amount'], ksers_fee_currency($payment))) ?></td>
                    <td><?= h($method) ?></td>
                    <td><?= h(ksers_payment_status_label($payment['status'], $payment['method'], $payment['amount'])) ?></td>
                    <td><?= h($payment['reg_date']) ?></td>
                    <td><?= h($payment['paid_date'] ? $payment['paid_date'] : '—') ?></td>
                    <td>
                        <div class="d-flex flex-nowrap gap-2">
                            <button class="btn btn-sm btn-primary-custom" type="button" data-bs-toggle="modal" data-bs-target="#payManageModal" data-pay-action="confirm" data-order="<?= (int)$payment['seq'] ?>" data-member="<?= h($name) ?>" data-amount="<?= h(ksers_money($payment['amount'], ksers_fee_currency($payment))) ?>"<?= $payment['status'] === 'pending' ? '' : ' disabled' ?>><?= h(t('결제 완료', 'Mark paid')) ?></button>
                            <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#payManageModal" data-pay-action="refund" data-order="<?= (int)$payment['seq'] ?>" data-method="<?= h($payment['method']) ?>" data-member="<?= h($name) ?>" data-amount="<?= h(ksers_money($payment['amount'], ksers_fee_currency($payment))) ?>"<?= $payment['status'] === 'paid' ? '' : ' disabled' ?>><?= h(t('환불', 'Refund')) ?></button>
                        </div>
                    </td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="/admin/page/member/list.php?<?= h(http_build_query($memberQuery)) ?>"><?= h(t('회원으로 이동', 'Open member')) ?></a>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-3">
        <nav aria-label="<?= h(t('결제 페이지', 'Payment pages')) ?>">
            <ul class="pagination pagination-sm justify-content-center m-0">
                <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                    <?php if ($page <= 1) { ?>
                    <span class="page-link"><?= h(t('이전', 'Previous')) ?></span>
                    <?php } else { ?>
                    <a class="page-link" href="<?= h($payPageHref($page - 1)) ?>"><?= h(t('이전', 'Previous')) ?></a>
                    <?php } ?>
                </li>
                <?php for ($i = $windowStart; $i <= $windowEnd; $i++) { ?>
                <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                    <a class="page-link" href="<?= h($payPageHref($i)) ?>"><?= $i ?></a>
                </li>
                <?php } ?>
                <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
                    <?php if ($page >= $pages) { ?>
                    <span class="page-link"><?= h(t('다음', 'Next')) ?></span>
                    <?php } else { ?>
                    <a class="page-link" href="<?= h($payPageHref($page + 1)) ?>"><?= h(t('다음', 'Next')) ?></a>
                    <?php } ?>
                </li>
            </ul>
        </nav>
    </div>
</div>

<?php
$bankRefundFrom = ksers_bank_refund_from($pdo, $year);
$bankRefundStamp = strtotime($bankRefundFrom);
$bankRefundLabel = $bankRefundStamp
    ? (date('Y', $bankRefundStamp) . '년 ' . date('n', $bankRefundStamp) . '월 ' . date('j', $bankRefundStamp) . '일')
    : $bankRefundFrom;
?>
<div class="modal fade" id="payManageModal" tabindex="-1" aria-labelledby="payManageTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="/admin/page/registration/action.php">
            <div class="modal-header">
                <h5 class="modal-title" id="payManageTitle"><?= h(t('결제 확인', 'Confirm payment')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body" id="payManageBody" style="white-space: pre-line;"></div>
            <div class="px-3 pb-3 d-none" id="payRefundAccount">
                <label class="form-label fw-bold" for="refundAccount"><?= h(t('환불 계좌 번호 입력', 'Refund account number')) ?> <span class="text-danger">*</span></label>
                <input class="form-control" id="refundAccount" name="refund_account" maxlength="80" autocomplete="off" inputmode="text">
                <div class="small text-danger fw-bold mt-2" id="payRefundWait"><?= h(t('환불 안내 메일은 대회 종료일(4월 24일)로부터 1개월 후인 ' . $bankRefundLabel . '에 발송됩니다.', 'The refund notice is sent on ' . $bankRefundFrom . ', one month after the congress ends on April 24.')) ?></div>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="order" id="payManageOrder" value="">
                <input type="hidden" name="action" id="payManageAction" value="">
                <input type="hidden" name="return" value="payment">
                <input type="hidden" name="pay_page" value="<?= (int)$page ?>">
                <input type="hidden" name="user_class" value="<?= h($filterClass) ?>">
                <input type="hidden" name="affiliation" value="<?= h($filterAffiliation) ?>">
                <input type="hidden" name="q" value="<?= h($filterQuery) ?>">
                <input type="hidden" name="pay_status" value="<?= h($filterStatus) ?>">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(t('취소', 'Cancel')) ?></button>
                <button class="btn btn-primary-custom" id="payManageSubmit" type="submit"><?= h(t('확인', 'Confirm')) ?></button>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('payManageModal')?.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    if (!button || button.disabled) {
        event.preventDefault();
        return;
    }
    const action = button.getAttribute('data-pay-action') || '';
    const member = button.getAttribute('data-member') || '';
    const amount = button.getAttribute('data-amount') || '';
    const method = button.getAttribute('data-method') || '';
    const complete = action === 'confirm';
    const bankRefund = !complete && method === 'bank';
    document.getElementById('payManageOrder').value = button.getAttribute('data-order') || '';
    document.getElementById('payManageAction').value = action;
    document.getElementById('payManageTitle').textContent = complete
        ? <?= json_encode(t('결제 완료', 'Mark paid'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
        : <?= json_encode(t('환불', 'Refund'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    document.getElementById('payManageBody').textContent = (complete
        ? <?= json_encode(t('이 결제를 완료로 저장합니다.', 'This payment will be saved as paid.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
        : <?= json_encode(t('카드 결제면 카드사에 전체 취소를 요청한 뒤 환불로 저장합니다. 계좌 송금은 이 화면의 기록만 환불로 바꿉니다.', 'A card payment sends a full cancellation to the card company, then saves a refund. A bank transfer only changes the record here.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)
        + '\n' + member + ' · ' + amount;
    document.getElementById('payManageSubmit').textContent = complete
        ? <?= json_encode(t('결제 완료', 'Mark paid'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
        : <?= json_encode(t('환불', 'Refund'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    document.getElementById('payManageSubmit').className = complete ? 'btn btn-primary-custom' : 'btn btn-danger';
    const accountBox = document.getElementById('payRefundAccount');
    const accountInput = document.getElementById('refundAccount');
    accountBox.classList.toggle('d-none', !bankRefund);
    accountInput.value = '';
    accountInput.disabled = !bankRefund;
    const syncSubmit = function () {
        const digits = (accountInput.value || '').replace(/\D/g, '');
        document.getElementById('payManageSubmit').disabled = bankRefund && digits.length < 4;
    };
    accountInput.oninput = syncSubmit;
    syncSubmit();
});
</script>

<?php include '../../lib/footer.php'; ?>
