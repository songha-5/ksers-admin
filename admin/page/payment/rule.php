<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);
$year = ksers_admin_year($pdo);
$page_title = t('결제 기준 등록 - ' . $year . ' 국제학술대회 관리자', 'Payment rules - ' . $year . ' Conference Admin');
$page_heading = t('결제', 'Payments');
$message = '';
ksers_seed_registration_fees($pdo, $year);
$feeCodes = ksers_payment_fee_codes();
$periodCodes = ksers_payment_period_codes();
$onsiteDates = ksers_onsite_dates($pdo, $year);

function ksers_rule_currency_value($value) {
    return $value === 'USD' ? 'USD' : ($value === 'KRW' ? 'KRW' : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) ? $_POST['action'] : '') === 'rule') {
    $feeCode = isset($_POST['fee_code']) ? trim((string)$_POST['fee_code']) : '';
    $periodCode = isset($_POST['period_code']) ? trim((string)$_POST['period_code']) : '';
    $currency = ksers_rule_currency_value(isset($_POST['currency']) ? $_POST['currency'] : '');
    $amountRaw = trim(isset($_POST['amount']) ? (string)$_POST['amount'] : '');
    $domesticOnly = isset($_POST['domestic_only']) ? 1 : 0;
    if ($currency === 'USD' && in_array($feeCode, ['physician_lifetime', 'physician_regular', 'physician_non'], true)) {
        $feeCode = 'physician';
    }
    if ($feeCode === 'physician' && $currency === 'KRW') {
        $message = t('해외 전문의 금액은 달러만 등록할 수 있습니다.', 'The overseas physician amount can only be saved in USD.');
    } elseif (!isset($feeCodes[$feeCode]) || !isset($periodCodes[$periodCode]) || $currency === '' || !preg_match('/^\d+$/', $amountRaw)) {
        $message = t('구분, 기간, 통화, 금액을 확인해 주세요.', 'Check the category, period, currency, and amount.');
    } else {
        $existing = $pdo->prepare(
            "SELECT seq FROM fee_item WHERE year = ? AND fee_code = ? AND period_code = ? AND currency = ? ORDER BY seq DESC LIMIT 1"
        );
        $existing->execute([$year, $feeCode, $periodCode, $currency]);
        $feeSeq = (int)$existing->fetchColumn();
        if ($feeSeq > 0) {
            $update = $pdo->prepare(
                "UPDATE fee_item SET name_ko = ?, name_en = ?, amount = ?, domestic_only = ?, state = 1 WHERE seq = ? AND year = ?"
            );
            $update->execute([$feeCodes[$feeCode][0], $feeCodes[$feeCode][1], (int)$amountRaw, $domesticOnly, $feeSeq, $year]);
        } else {
            $insert = $pdo->prepare(
                "INSERT INTO fee_item
                    (year, kind, name_ko, name_en, detail_ko, detail_en, amount, sort_no, state, affiliation_type, currency, fee_code, period_code, domestic_only)
                 VALUES (?, 'schedule', ?, ?, '', '', ?, 0, 1, '', ?, ?, ?, ?)"
            );
            $insert->execute([$year, $feeCodes[$feeCode][0], $feeCodes[$feeCode][1], (int)$amountRaw, $currency, $feeCode, $periodCode, $domesticOnly]);
        }
        $message = t('결제 기준을 등록했습니다.', 'Payment rule saved.');
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) ? $_POST['action'] : '') === 'rule_edit') {
    $feeSeq = isset($_POST['seq']) ? (int)$_POST['seq'] : 0;
    $periodCode = isset($_POST['period_code']) ? trim((string)$_POST['period_code']) : '';
    $currency = ksers_rule_currency_value(isset($_POST['currency']) ? $_POST['currency'] : '');
    $amountRaw = trim(isset($_POST['amount']) ? (string)$_POST['amount'] : '');
    $domesticOnly = isset($_POST['domestic_only']) ? 1 : 0;
    if ($feeSeq < 1 || !isset($periodCodes[$periodCode]) || $currency === '' || !preg_match('/^\d+$/', $amountRaw)) {
        $message = t('기간, 통화, 금액을 확인해 주세요.', 'Check the period, currency, and amount.');
    } else {
        $found = $pdo->prepare("SELECT seq, fee_code FROM fee_item WHERE seq = ? AND year = ? AND fee_code <> ''");
        $found->execute([$feeSeq, $year]);
        $foundRow = $found->fetch(PDO::FETCH_ASSOC);
        if (!$foundRow) {
            $message = t('수정할 금액 기준을 찾지 못했습니다.', 'That amount rule was not found.');
        } elseif ($foundRow['fee_code'] === 'physician' && $currency === 'KRW') {
            $message = t('해외 전문의 금액은 달러만 등록할 수 있습니다.', 'The overseas physician amount can only be saved in USD.');
        } elseif (in_array($foundRow['fee_code'], ['physician_lifetime', 'physician_regular', 'physician_non'], true) && $currency === 'USD') {
            $message = t('해외 전문의 금액은 회원 구분 없이 전문의(해외) 한 금액으로 등록해 주세요.', 'Save the overseas physician amount once, under Physician (overseas), not by membership.');
        } else {
            $dup = $pdo->prepare(
                "SELECT seq FROM fee_item WHERE year = ? AND fee_code = ? AND period_code = ? AND currency = ? AND seq <> ?"
            );
            $dup->execute([$year, $foundRow['fee_code'], $periodCode, $currency, $feeSeq]);
            if ($dup->fetchColumn()) {
                $message = t('같은 구분, 기간, 통화가 이미 있습니다.', 'That category, period, and currency already exists.');
            } else {
                $update = $pdo->prepare(
                    "UPDATE fee_item SET amount = ?, currency = ?, period_code = ?, domestic_only = ? WHERE seq = ? AND year = ?"
                );
                $update->execute([(int)$amountRaw, $currency, $periodCode, $domesticOnly, $feeSeq, $year]);
                $message = t('금액, 통화, 기간을 수정했습니다.', 'Amount, currency, and period updated.');
            }
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) ? $_POST['action'] : '') === 'rule_delete') {
    $feeSeq = isset($_POST['seq']) ? (int)$_POST['seq'] : 0;
    $delete = $pdo->prepare("DELETE FROM fee_item WHERE seq = ? AND year = ? AND fee_code <> ''");
    $delete->execute([$feeSeq, $year]);
    $message = $delete->rowCount() > 0
        ? t('등록한 금액을 삭제했습니다.', 'The saved amount was deleted.')
        : t('삭제할 금액 기준을 찾지 못했습니다.', 'That amount rule was not found.');
}

$saved = $pdo->prepare(
    "SELECT * FROM fee_item WHERE year = ? AND state = 1 AND fee_code <> '' ORDER BY fee_code, period_code, currency, seq"
);
$saved->execute([$year]);
$savedRows = $saved->fetchAll(PDO::FETCH_ASSOC);

include '../../lib/header.php';
include '../../lib/menu.php';
$paymentTab = 'rule';
include 'tabs.php';
?>

<?php if ($message !== '') { ?>
<div class="alert alert-info border-0 shadow-sm"><?= h($message) ?></div>
<?php } ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="m-0 fw-bold"><?= h(t('결제 기준 등록', 'Payment rules')) ?></h5>
    </div>
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="action" value="rule">
            <div class="payment-rule-layout">
                <div class="payment-rule-side">
                    <div class="payment-rule-fields">
                        <div>
                            <label class="form-label small mb-1" for="ruleFee"><?= h(t('구분', 'Category')) ?></label>
                            <select class="form-select form-select-sm" id="ruleFee" name="fee_code">
                                <?php foreach ($feeCodes as $code => $label) {
                                    $optionLabel = $code === 'physician'
                                        ? t('전문의 (해외, 회원 구분 없음)', 'Physician (overseas, one price)')
                                        : t($label[0], $label[1]);
                                ?>
                                <option value="<?= h($code) ?>"><?= h($optionLabel) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small mb-1" for="rulePeriod"><?= h(t('기간', 'Period')) ?></label>
                            <select class="form-select form-select-sm" id="rulePeriod" name="period_code">
                                <?php foreach ($periodCodes as $code => $label) { ?>
                                <option value="<?= h($code) ?>"><?= h(ksers_payment_period_label($code, $year, $onsiteDates)) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="payment-rule-side">
                    <div class="payment-rule-fields">
                        <div>
                            <label class="form-label small mb-1" for="ruleCurrency"><?= h(t('통화', 'Currency')) ?></label>
                            <select class="form-select form-select-sm" id="ruleCurrency" name="currency">
                                <option value="KRW"><?= h(t('원 (KRW)', 'KRW')) ?></option>
                                <option value="USD"><?= h(t('달러 (USD)', 'USD')) ?></option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small mb-1" for="ruleAmount"><?= h(t('결제 금액', 'Amount')) ?></label>
                            <input class="form-control form-control-sm" id="ruleAmount" name="amount" type="number" min="0" step="1" inputmode="numeric" required>
                        </div>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="ruleDomestic" name="domestic_only" value="1">
                        <label class="form-check-label" for="ruleDomestic"><?= h(t('국내만 적용', 'Domestic only')) ?></label>
                    </div>
                </div>
            </div>
            <button class="btn btn-primary-custom mt-3" type="submit"><?= h(t('등록', 'Save')) ?></button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="m-0 fw-bold"><?= h(t('등록한 금액', 'Saved amounts')) ?> <span class="text-muted fs-6"><?= count($savedRows) ?></span></h5>
    </div>
    <div class="table-responsive">
        <table class="table payment-rule-table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="rule-col-fee"><?= h(t('구분', 'Category')) ?></th>
                    <th class="rule-col-period"><?= h(t('기간', 'Period')) ?></th>
                    <th class="rule-col-currency"><?= h(t('통화', 'Currency')) ?></th>
                    <th class="rule-col-amount"><?= h(t('결제 금액', 'Amount')) ?></th>
                    <th class="text-end rule-col-action"><?= h(t('수정/삭제', 'Edit/Delete')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$savedRows) { ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?= h(t('등록된 금액 기준이 없습니다.', 'No amount rules yet.')) ?></td></tr>
                <?php } ?>
                <?php foreach ($savedRows as $rule) {
                    $formId = 'ruleEdit' . (int)$rule['seq'];
                    $code = $rule['fee_code'];
                    $feeLabel = isset($feeCodes[$code]) ? t($feeCodes[$code][0], $feeCodes[$code][1]) : $code;
                    $rowCurrency = ksers_fee_currency($rule);
                ?>
                <tr>
                    <td>
                        <?= h($feeLabel) ?>
                        <?php if (!empty($rule['domestic_only'])) { ?>
                        <div class="small text-muted"><?= h(t('국내만', 'Domestic only')) ?></div>
                        <?php } ?>
                        <label class="rule-domestic-edit d-none">
                            <input class="form-check-input" type="checkbox" name="domestic_only" value="1" form="<?= h($formId) ?>"<?= !empty($rule['domestic_only']) ? ' checked' : '' ?>>
                            <span><?= h(t('국내만', 'Domestic only')) ?></span>
                        </label>
                    </td>
                    <td>
                        <span class="rule-period-text"><?= h(ksers_payment_period_label($rule['period_code'], $year, $onsiteDates)) ?></span>
                        <select class="form-select form-select-sm rule-period-edit d-none" name="period_code" form="<?= h($formId) ?>">
                            <?php foreach ($periodCodes as $periodCode => $periodLabel) { ?>
                            <option value="<?= h($periodCode) ?>"<?= $rule['period_code'] === $periodCode ? ' selected' : '' ?>><?= h(ksers_payment_period_label($periodCode, $year, $onsiteDates)) ?></option>
                            <?php } ?>
                        </select>
                    </td>
                    <td>
                        <span class="rule-currency-text"><?= h($rowCurrency === 'USD' ? t('달러', 'USD') : t('원', 'KRW')) ?></span>
                        <select class="form-select form-select-sm rule-currency-edit d-none" name="currency" form="<?= h($formId) ?>">
                            <option value="KRW"<?= $rowCurrency === 'KRW' ? ' selected' : '' ?>><?= h(t('원', 'KRW')) ?></option>
                            <option value="USD"<?= $rowCurrency === 'USD' ? ' selected' : '' ?>><?= h(t('달러', 'USD')) ?></option>
                        </select>
                    </td>
                    <td>
                        <span class="rule-amount-text"><?= h(ksers_money($rule['amount'], $rowCurrency)) ?></span>
                        <form method="post" id="<?= h($formId) ?>" class="rule-edit-form d-none">
                            <input type="hidden" name="action" value="rule_edit">
                            <input type="hidden" name="seq" value="<?= (int)$rule['seq'] ?>">
                            <input class="form-control form-control-sm" name="amount" type="number" min="0" step="1" inputmode="numeric" value="<?= (int)$rule['amount'] ?>" required>
                        </form>
                    </td>
                    <td class="text-end">
                        <div class="rule-actions">
                            <button class="btn btn-sm btn-outline-primary" type="button" data-rule-edit><?= h(t('수정', 'Edit')) ?></button>
                            <button class="btn btn-sm btn-primary-custom d-none" type="submit" form="<?= h($formId) ?>" data-rule-save><?= h(t('저장', 'Save')) ?></button>
                            <form method="post" class="d-inline" onsubmit="return confirm(<?= json_encode(t('이 등록 금액을 삭제할까요?', 'Delete this amount?'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
                                <input type="hidden" name="action" value="rule_delete">
                                <input type="hidden" name="seq" value="<?= (int)$rule['seq'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><?= h(t('삭제', 'Delete')) ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.payment-rule-layout { display: grid; grid-template-columns: 1.4fr 1fr; gap: 1.5rem 2rem; }
.payment-rule-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.rule-period-edit.d-none, .rule-currency-edit.d-none, .rule-edit-form.d-none, .rule-domestic-edit.d-none { display: none; }
.rule-edit-form:not(.d-none) { display: block; }
table.payment-rule-table { width: 100%; min-width: 46rem; table-layout: fixed; }
table.payment-rule-table th, table.payment-rule-table td { min-width: 0; }
.rule-col-fee { width: 28%; }
.rule-col-period { width: 24%; }
.rule-col-currency { width: 12%; }
.rule-col-amount { width: 18%; }
.rule-col-action { width: 18%; }
.rule-actions { display: inline-flex; align-items: center; justify-content: flex-end; gap: 0.5rem; }
.rule-actions > .btn, .rule-actions form .btn { width: 4.5rem; }
.rule-domestic-edit { display: flex; align-items: center; gap: 0.35rem; margin-top: 0.35rem; font-size: 0.8125rem; }
@media (max-width: 767.98px) {
    .payment-rule-layout, .payment-rule-fields { grid-template-columns: 1fr; }
}
</style>
<script>
(function () {
    var fee = document.getElementById('ruleFee');
    var currency = document.getElementById('ruleCurrency');
    if (!fee || !currency) {
        return;
    }
    function syncFeeOptions() {
        var usd = currency.value === 'USD';
        var first = '';
        Array.prototype.forEach.call(fee.options, function (option) {
            var membership = option.value === 'physician_lifetime' || option.value === 'physician_regular' || option.value === 'physician_non';
            var hide = usd ? membership : option.value === 'physician';
            option.hidden = hide;
            option.disabled = hide;
            if (!hide && first === '') {
                first = option.value;
            }
        });
        if (fee.selectedOptions.length && fee.selectedOptions[0].disabled && first !== '') {
            fee.value = first;
        }
    }
    currency.addEventListener('change', syncFeeOptions);
    syncFeeOptions();
})();
document.querySelectorAll('[data-rule-edit]').forEach(function (button) {
    button.addEventListener('click', function () {
        var row = button.closest('tr');
        row.querySelector('.rule-amount-text').classList.add('d-none');
        row.querySelector('.rule-edit-form').classList.remove('d-none');
        row.querySelector('.rule-period-text').classList.add('d-none');
        row.querySelector('.rule-period-edit').classList.remove('d-none');
        row.querySelector('.rule-currency-text').classList.add('d-none');
        row.querySelector('.rule-currency-edit').classList.remove('d-none');
        var domestic = row.querySelector('.rule-domestic-edit');
        if (domestic) {
            domestic.classList.remove('d-none');
        }
        button.classList.add('d-none');
        row.querySelector('[data-rule-save]').classList.remove('d-none');
        row.querySelector('.rule-edit-form input[name="amount"]').focus();
    });
});
</script>

<?php include '../../lib/footer.php'; ?>
