<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);
$year = ksers_admin_year($pdo);
$page_title = t('대회 설정', 'Conference settings');
$page_heading = t('대회 설정', 'Conference settings');
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'event') {
        $titleKo = trim(isset($_POST['title_ko']) ? $_POST['title_ko'] : '');
        $titleEn = trim(isset($_POST['title_en']) ? $_POST['title_en'] : '');
        $noticeKo = trim(isset($_POST['notice_ko']) ? $_POST['notice_ko'] : '');
        $noticeEn = trim(isset($_POST['notice_en']) ? $_POST['notice_en'] : '');
        $bankName = trim(isset($_POST['bank_name']) ? $_POST['bank_name'] : '');
        $bankAccount = trim(isset($_POST['bank_account']) ? $_POST['bank_account'] : '');
        $bankHolder = trim(isset($_POST['bank_holder']) ? $_POST['bank_holder'] : '');
        $mailFrom = trim(isset($_POST['mail_from']) ? $_POST['mail_from'] : '');
        $open = isset($_POST['is_open']) ? 1 : 0;
        if ($titleKo === '' || $titleEn === '') {
            $message = t('대회명을 국문과 영문으로 입력해 주세요.', 'Enter the conference name in Korean and English.');
        } elseif ($mailFrom !== '' && !filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
            $message = t('보내는 메일 주소를 확인해 주세요.', 'Check the sender email address.');
        } else {
            if ($open === 1) {
                $pdo->exec("UPDATE event_setting SET is_open = 0");
            }
            $save = $pdo->prepare(
                "INSERT INTO event_setting
                    (year, title_ko, title_en, notice_ko, notice_en, bank_name, bank_account, bank_holder, mail_from, is_open)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    title_ko = VALUES(title_ko), title_en = VALUES(title_en),
                    notice_ko = VALUES(notice_ko), notice_en = VALUES(notice_en),
                    bank_name = VALUES(bank_name), bank_account = VALUES(bank_account),
                    bank_holder = VALUES(bank_holder), mail_from = VALUES(mail_from),
                    is_open = VALUES(is_open)"
            );
            $save->execute([$year, $titleKo, $titleEn, $noticeKo, $noticeEn, $bankName, $bankAccount, $bankHolder, $mailFrom, $open]);
            $message = t('대회 설정을 저장했습니다.', 'Conference settings saved.');
        }
    }
    if ($action === 'fee') {
        $nameKo = trim(isset($_POST['name_ko']) ? $_POST['name_ko'] : '');
        $nameEn = trim(isset($_POST['name_en']) ? $_POST['name_en'] : '');
        $kind = isset($_POST['kind']) ? $_POST['kind'] : '';
        $amount = isset($_POST['amount']) ? (int)$_POST['amount'] : -1;
        $dateFrom = trim(isset($_POST['date_from']) ? $_POST['date_from'] : '');
        $dateTo = trim(isset($_POST['date_to']) ? $_POST['date_to'] : '');
        if ($nameKo === '' || $nameEn === '' || ($kind !== 'fee' && $kind !== 'extra') || $amount < 0) {
            $message = t('참가비 항목을 다시 확인해 주세요.', 'Check the fee item and try again.');
        } else {
            $insert = $pdo->prepare(
                "INSERT INTO fee_item
                    (year, kind, name_ko, name_en, detail_ko, detail_en, amount, date_from, date_to, sort_no, state)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
            );
            $insert->execute([
                $year, $kind, $nameKo, $nameEn,
                trim(isset($_POST['detail_ko']) ? $_POST['detail_ko'] : ''),
                trim(isset($_POST['detail_en']) ? $_POST['detail_en'] : ''),
                $amount,
                $dateFrom === '' ? null : $dateFrom,
                $dateTo === '' ? null : $dateTo,
                (int)(isset($_POST['sort_no']) ? $_POST['sort_no'] : 0),
            ]);
            $message = t('참가비 항목을 추가했습니다.', 'Fee item added.');
        }
    }
    if ($action === 'fee_off') {
        $off = $pdo->prepare("UPDATE fee_item SET state = 0 WHERE seq = ? AND year = ?");
        $off->execute([(int)$_POST['seq'], $year]);
        $message = t('항목을 내렸습니다. 이미 결제된 기록은 남아 있습니다.', 'The item is hidden. Existing payments stay.');
    }
    if ($action === 'topic') {
        $nameKo = trim(isset($_POST['name_ko']) ? $_POST['name_ko'] : '');
        $nameEn = trim(isset($_POST['name_en']) ? $_POST['name_en'] : '');
        if ($nameKo === '' || $nameEn === '') {
            $message = t('초록 분야를 국문과 영문으로 입력해 주세요.', 'Enter the topic in Korean and English.');
        } else {
            $insert = $pdo->prepare(
                "INSERT INTO abstract_topic (year, name_ko, name_en, sort_no, state) VALUES (?, ?, ?, ?, 1)"
            );
            $insert->execute([$year, $nameKo, $nameEn, (int)(isset($_POST['sort_no']) ? $_POST['sort_no'] : 0)]);
            $message = t('초록 분야를 추가했습니다.', 'Topic added.');
        }
    }
    if ($action === 'topic_off') {
        $off = $pdo->prepare("UPDATE abstract_topic SET state = 0 WHERE seq = ? AND year = ?");
        $off->execute([(int)$_POST['seq'], $year]);
        $message = t('분야를 내렸습니다.', 'Topic hidden.');
    }
}

$setting = ksers_setting($pdo, $year);
$fees = $pdo->prepare("SELECT * FROM fee_item WHERE year = ? ORDER BY state DESC, sort_no, seq");
$fees->execute([$year]);
$feeRows = $fees->fetchAll(PDO::FETCH_ASSOC);
$topics = $pdo->prepare("SELECT * FROM abstract_topic WHERE year = ? ORDER BY state DESC, sort_no, seq");
$topics->execute([$year]);
$topicRows = $topics->fetchAll(PDO::FETCH_ASSOC);

include '../../lib/header.php';
include '../../lib/menu.php';
?>

<?php if ($message !== '') { ?>
<div class="alert alert-info border-0 shadow-sm"><?= h($message) ?></div>
<?php } ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3"><h5 class="m-0 fw-bold"><?= h(t('대회 정보', 'Conference')) ?> <?= (int)$year ?></h5></div>
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="action" value="event">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label"><?= h(t('대회명 (한글)', 'Name in Korean')) ?></label>
                    <input class="form-control" name="title_ko" maxlength="120" required value="<?= h($setting['title_ko']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= h(t('대회명 (영문)', 'Name in English')) ?></label>
                    <input class="form-control" name="title_en" maxlength="120" required value="<?= h($setting['title_en']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= h(t('안내 문구 (한글)', 'Notice in Korean')) ?></label>
                    <textarea class="form-control" name="notice_ko" maxlength="1000" rows="3"><?= h($setting['notice_ko']) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= h(t('안내 문구 (영문)', 'Notice in English')) ?></label>
                    <textarea class="form-control" name="notice_en" maxlength="1000" rows="3"><?= h($setting['notice_en']) ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= h(t('은행', 'Bank')) ?></label>
                    <input class="form-control" name="bank_name" maxlength="80" value="<?= h($setting['bank_name']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= h(t('계좌', 'Account')) ?></label>
                    <input class="form-control" name="bank_account" maxlength="80" value="<?= h($setting['bank_account']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= h(t('예금주', 'Account holder')) ?></label>
                    <input class="form-control" name="bank_holder" maxlength="80" value="<?= h($setting['bank_holder']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= h(t('보내는 메일 주소', 'Sender email')) ?></label>
                    <input class="form-control" name="mail_from" maxlength="120" value="<?= h($setting['mail_from']) ?>" placeholder="name@domain.com">
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_open" id="isOpen" <?= (int)$setting['is_open'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isOpen"><?= h(t('이 연도를 참가자에게 연다', 'Open this year to participants')) ?></label>
                    </div>
                </div>
            </div>
            <button class="btn btn-primary-custom mt-3" type="submit"><?= h(t('대회 정보 저장', 'Save conference')) ?></button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3"><h5 class="m-0 fw-bold"><?= h(t('참가비와 추가 항목', 'Fees and extra items')) ?></h5></div>
    <div class="card-body">
        <form method="post" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="action" value="fee">
            <div class="col-md-2">
                <label class="form-label"><?= h(t('종류', 'Kind')) ?></label>
                <select class="form-select" name="kind">
                    <option value="fee"><?= h(t('참가비', 'Registration fee')) ?></option>
                    <option value="extra"><?= h(t('추가 항목', 'Extra item')) ?></option>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label"><?= h(t('이름', 'Name')) ?></label><input class="form-control" name="name_ko" required maxlength="80"></div>
            <div class="col-md-2"><label class="form-label"><?= h(t('영문 이름', 'English name')) ?></label><input class="form-control" name="name_en" required maxlength="80"></div>
            <div class="col-md-2"><label class="form-label"><?= h(t('금액 (원)', 'Amount (KRW)')) ?></label><input class="form-control" name="amount" type="number" min="0" required></div>
            <div class="col-md-2"><label class="form-label"><?= h(t('시작', 'From')) ?></label><input class="form-control" name="date_from" type="date"></div>
            <div class="col-md-2"><label class="form-label"><?= h(t('종료', 'To')) ?></label><input class="form-control" name="date_to" type="date"></div>
            <div class="col-md-2"><button class="btn btn-primary-custom" type="submit"><?= h(t('항목 추가', 'Add item')) ?></button></div>
        </form>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead class="table-light"><tr><th><?= h(t('종류', 'Kind')) ?></th><th><?= h(t('이름', 'Name')) ?></th><th><?= h(t('금액', 'Amount')) ?></th><th><?= h(t('기간', 'Period')) ?></th><th><?= h(t('상태', 'State')) ?></th><th></th></tr></thead>
                <tbody>
                <?php if (!$feeRows) { ?><tr><td colspan="6" class="text-muted"><?= h(t('등록된 참가비가 없습니다.', 'No fees yet.')) ?></td></tr><?php } ?>
                <?php foreach ($feeRows as $fee) { ?>
                <tr>
                    <td><?= h($fee['kind'] === 'extra' ? t('추가', 'Extra') : t('참가비', 'Fee')) ?></td>
                    <td><?= h($fee['name_ko']) ?> / <?= h($fee['name_en']) ?></td>
                    <td><?= h(ksers_money($fee['amount'])) ?></td>
                    <td><?= h(trim($fee['date_from'] . ' ~ ' . $fee['date_to'], ' ~')) ?></td>
                    <td><?= h((int)$fee['state'] === 1 ? t('사용', 'On') : t('내림', 'Off')) ?></td>
                    <td>
                        <?php if ((int)$fee['state'] === 1) { ?>
                        <form method="post">
                            <input type="hidden" name="action" value="fee_off">
                            <input type="hidden" name="seq" value="<?= (int)$fee['seq'] ?>">
                            <button class="btn btn-sm btn-outline-secondary" type="submit"><?= h(t('내리기', 'Hide')) ?></button>
                        </form>
                        <?php } ?>
                    </td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3"><h5 class="m-0 fw-bold"><?= h(t('초록 분야', 'Abstract topics')) ?></h5></div>
    <div class="card-body">
        <form method="post" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="action" value="topic">
            <div class="col-md-4"><label class="form-label"><?= h(t('분야 (한글)', 'Topic in Korean')) ?></label><input class="form-control" name="name_ko" required maxlength="100"></div>
            <div class="col-md-4"><label class="form-label"><?= h(t('분야 (영문)', 'Topic in English')) ?></label><input class="form-control" name="name_en" required maxlength="100"></div>
            <div class="col-md-2"><button class="btn btn-primary-custom" type="submit"><?= h(t('분야 추가', 'Add topic')) ?></button></div>
        </form>
        <ul class="list-group">
            <?php if (!$topicRows) { ?><li class="list-group-item text-muted"><?= h(t('분야가 없으면 참가자는 분야를 직접 입력합니다.', 'With no topics, participants type the topic.')) ?></li><?php } ?>
            <?php foreach ($topicRows as $topic) { ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><?= h($topic['name_ko']) ?> / <?= h($topic['name_en']) ?> <?= (int)$topic['state'] === 1 ? '' : h(t('(내림)', '(off)')) ?></span>
                <?php if ((int)$topic['state'] === 1) { ?>
                <form method="post">
                    <input type="hidden" name="action" value="topic_off">
                    <input type="hidden" name="seq" value="<?= (int)$topic['seq'] ?>">
                    <button class="btn btn-sm btn-outline-secondary" type="submit"><?= h(t('내리기', 'Hide')) ?></button>
                </form>
                <?php } ?>
            </li>
            <?php } ?>
        </ul>
    </div>
</div>

<?php include '../../lib/footer.php'; ?>
