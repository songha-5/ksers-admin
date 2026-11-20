<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$year = ksers_admin_year($pdo);
$templates = ksers_admin_mail_templates();
$templateKey = isset($_GET['template']) ? (string)$_GET['template'] : 'deadline';
if (!isset($templates[$templateKey])) {
    $templateKey = 'deadline';
}
$template = $templates[$templateKey];
$filters = [
    'all' => t('전체', 'All'),
    'not_submitted' => t('초록 미제출자', 'No abstract'),
    'submitted' => t('초록 제출자', 'Abstract submitted'),
    'unregistered_presenter' => t('미등록 발표자', 'Unregistered presenter'),
    'withdraw_party' => t('미등록 초록의 발표자·제출자·교신저자', 'Presenter, submitter, corresponding'),
    'grant_party' => t('채택 초록의 발표자·제출자·교신저자', 'Accepted abstract parties'),
];
$filter = isset($_GET['filter']) ? (string)$_GET['filter'] : 'all';
if (!isset($filters[$filter])) {
    $filter = 'all';
}
if (isset($_GET['unpaid'])) {
    $unpaid = (string)$_GET['unpaid'] === '1';
} elseif ($templateKey === 'payment_wait' && !isset($_GET['filter'])) {
    $unpaid = true;
} else {
    $unpaid = false;
}
$mailLang = ksers_lang() === 'en' ? 'en' : 'ko';
$sourceTemplate = $templates[$templateKey];
$bilingual = !empty($sourceTemplate['locales']['ko']) && !empty($sourceTemplate['locales']['en']);
$template = $sourceTemplate;
if ($bilingual && !empty($sourceTemplate['locales'][$mailLang])) {
    $template = array_merge($sourceTemplate, $sourceTemplate['locales'][$mailLang]);
}
if (isset($_GET['attend'])) {
    $attendees = (string)$_GET['attend'] === '1';
} elseif ($templateKey === 'attend' && !isset($_GET['filter'])) {
    $attendees = true;
} else {
    $attendees = false;
}
$presentation = isset($_GET['presentation']) ? (string)$_GET['presentation'] : '';
$accept = isset($_GET['accept']) ? (string)$_GET['accept'] : '';
$registered = isset($_GET['registered']) ? (string)$_GET['registered'] : '';
$userClass = isset($_GET['user_class']) ? (string)$_GET['user_class'] : '';
$presentationChoices = [
    'oral' => 'Oral',
    'eposter' => 'E-Poster',
    'poster' => 'Poster',
    'video' => 'Video',
    'mentor' => 'Mentor-Mentee Video',
    'either' => 'No preference',
];
$acceptChoices = [
    'received' => t('접수', 'Submitted'),
    'accepted' => t('채택', 'Accepted'),
    'rejected' => t('불채택', 'Not accepted'),
    'withdrawn' => t('철회', 'Withdrawn'),
];
$classChoices = ['M0', 'M1', 'M2', 'M3'];
if (!isset($presentationChoices[$presentation])) {
    $presentation = '';
}
if (!isset($acceptChoices[$accept])) {
    $accept = '';
}
if ($registered !== 'yes' && $registered !== 'no') {
    $registered = '';
}
if (!in_array($userClass, $classChoices, true)) {
    $userClass = '';
}
$candidates = ksers_admin_mail_candidates($pdo, $year, $filter, $presentation, $accept, $registered, $userClass, $unpaid, $attendees);
$failedOnly = isset($_GET['history']) && $_GET['history'] === 'failed';
$history = ksers_admin_mail_history($pdo, $year, $failedOnly);
$mailResult = null;
if (!empty($_SESSION['admin_mail_result']) && is_array($_SESSION['admin_mail_result'])) {
    $mailResult = $_SESSION['admin_mail_result'];
}
unset($_SESSION['admin_mail_result']);
$page_title = 'E-Mail';
$page_heading = 'E-Mail';
$clientTemplates = [];
foreach ($templates as $key => $item) {
    $pack = [
        'subject' => $item['subject'],
        'title' => $item['title'],
        'body' => $item['body'],
        'category' => $item['category'],
        'rows' => $item['rows'],
        'button_label' => $item['button_label'],
        'notice' => $item['notice'],
        'send_reason' => $item['send_reason'],
    ];
    $entry = $pack;
    $entry['filter'] = $item['filter'];
    if (!empty($item['locales']['ko']) && !empty($item['locales']['en'])) {
        $entry['locales'] = [];
        foreach (['ko', 'en'] as $code) {
            $locale = array_merge($item, $item['locales'][$code]);
            $entry['locales'][$code] = [
                'subject' => $locale['subject'],
                'title' => $locale['title'],
                'body' => $locale['body'],
                'category' => $locale['category'],
                'rows' => $locale['rows'],
                'button_label' => $locale['button_label'],
                'notice' => $locale['notice'],
                'send_reason' => $locale['send_reason'],
            ];
        }
    }
    $clientTemplates[$key] = $entry;
}
$hasDesk = false;
foreach ($template['rows'] as $row) {
    if (isset($row[1]) && strpos((string)$row[1], '{{desk_info}}') !== false) {
        $hasDesk = true;
    }
}
$lockFilters = $unpaid || $attendees;
$greeting = 'Dear Author,';
if ($bilingual && $mailLang === 'ko') {
    $greeting = '받는 사람님, 안녕하세요.';
} elseif ($bilingual) {
    $greeting = 'Dear Recipient,';
}
include '../../lib/header.php';
include '../../lib/menu.php';
?>
<style>
.mail-layout { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr); gap: 16px; align-items: start; }
@media (max-width: 1100px) { .mail-layout { grid-template-columns: 1fr; } }
.mail-sheet { background: #F2F4F7; padding: 16px 10px; }
.mail-card { max-width: 600px; margin: 0 auto; background: #fff; border: 1px solid #E1E5EB; }
.mail-head { background: #012D6C; color: #fff; padding: 20px 28px; display: flex; gap: 18px; align-items: center; }
.mail-head img { height: 72px; width: auto; display: block; }
.mail-kicker { font-size: 11px; letter-spacing: 1px; color: #00E5FF; }
.mail-brand { font-size: 28px; line-height: 34px; font-weight: 700; }
.mail-brand span { color: #00E5FF; font-weight: 400; }
.mail-date { font-size: 13px; padding-top: 4px; }
.mail-body { padding: 28px 32px 8px; font-family: Arial, Helvetica, sans-serif; color: #333; }
.mail-cat { font-size: 12px; font-weight: 700; color: #0086C9; letter-spacing: 1px; text-transform: uppercase; }
.mail-edit { width: 100%; border: 0; background: transparent; font-family: Arial, Helvetica, sans-serif; color: #1A1A1A; padding: 0; }
.mail-edit:focus { outline: 1px dashed #0086C9; background: #f7fbff; }
.mail-edit.title { font-size: 22px; font-weight: 700; line-height: 30px; margin-top: 6px; resize: vertical; }
.mail-edit.body { font-size: 15px; line-height: 24px; min-height: 120px; resize: vertical; color: #333; }
.mail-subject { font-size: 14px; font-weight: 700; }
.mail-table { width: 100%; border-top: 2px solid #012D6C; border-collapse: collapse; font-size: 14px; }
.mail-table th { width: 34%; text-align: left; padding: 10px 12px; background: #F5F7FA; border-bottom: 1px solid #E1E5EB; color: #555; }
.mail-table td { padding: 10px 12px; border-bottom: 1px solid #E1E5EB; color: #1A1A1A; }
.mail-btn { display: inline-block; background: #012D6C; color: #fff; font-weight: 700; padding: 13px 32px; text-decoration: none; border-radius: 4px; }
.mail-notice { background: #FFF8E6; border-left: 4px solid #E0A526; color: #5A4A1F; font-size: 13px; line-height: 20px; padding: 14px 16px; }
.mail-foot { background: #F5F7FA; border-top: 1px solid #E1E5EB; color: #777; font-size: 12px; line-height: 18px; padding: 20px 32px; }
.recipient-list { max-height: 220px; overflow: auto; }
.recipient-list li { display: flex; justify-content: space-between; gap: 8px; align-items: center; }
.mail-fail-list { max-height: 220px; overflow-y: auto; margin: 12px 0 0; padding-left: 1.1rem; }
.mail-fail-list li { margin-bottom: 6px; word-break: break-all; }
</style>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-end">
        <div>
            <label class="form-label" for="templatePick"><?= h(t('메일 폼', 'Mail form')) ?></label>
            <select class="form-select" id="templatePick">
                <?php foreach ($templates as $key => $item) { ?>
                <option value="<?= h($key) ?>" <?= $key === $templateKey ? 'selected' : '' ?>><?= h(t($item['label_ko'], $item['label_en'])) ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="text-muted small"><?= h(t('폼을 바꾸면 제목과 본문이 그 폼의 기본 문구로 바뀝니다.', 'Choosing a form replaces the subject and body with that form.')) ?></div>
    </div>
</div>

<form method="post" action="action.php" id="mailSendForm">
<input type="hidden" name="template" id="templateField" value="<?= h($templateKey) ?>">
<input type="hidden" name="mail_lang" id="mailLangField" value="<?= h($mailLang) ?>">
<input type="hidden" name="recipients" id="recipientField" value="[]">
<?php if ($bilingual) { foreach (['ko', 'en'] as $code) {
    $pack = $sourceTemplate['locales'][$code];
?>
<input type="hidden" name="subject_<?= h($code) ?>" id="subject_<?= h($code) ?>" value="<?= h($pack['subject']) ?>">
<input type="hidden" name="mail_title_<?= h($code) ?>" id="mail_title_<?= h($code) ?>" value="<?= h($pack['title']) ?>">
<input type="hidden" name="body_<?= h($code) ?>" id="body_<?= h($code) ?>" value="<?= h($pack['body']) ?>">
<input type="hidden" name="desk_<?= h($code) ?>" id="desk_<?= h($code) ?>" value="">
<?php } } ?>
<div class="mail-layout mb-4">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <?php if ($bilingual) { ?>
            <div class="btn-group btn-group-sm mb-2" id="mailLangSwitch" role="group" aria-label="KO EN">
                <button type="button" class="btn <?= $mailLang === 'ko' ? 'btn-primary' : 'btn-outline-primary' ?>" data-mail-lang="ko">KO</button>
                <button type="button" class="btn <?= $mailLang === 'en' ? 'btn-primary' : 'btn-outline-primary' ?>" data-mail-lang="en">EN</button>
            </div>
            <?php } ?>
            <label class="form-label mail-subject" for="mailSubject"><?= h(t('메일 제목', 'Subject')) ?></label>
            <input class="form-control" id="mailSubject" name="subject" value="<?= h($template['subject']) ?>">
            <div id="deskWrap" class="mt-3<?= $hasDesk ? '' : ' d-none' ?>">
                <label class="form-label" for="deskInfo" id="deskLabel"><?= h($mailLang === 'en' ? 'On-site Registration Desk' : '현장 등록데스크') ?></label>
                <input class="form-control" id="deskInfo" value="">
            </div>
        </div>
        <div class="mail-sheet">
            <div class="mail-card">
                <div class="mail-head">
                    <img src="/mail/KSERS2027_mail_logo_mark.png" alt="KSERS">
                    <div>
                        <div class="mail-kicker">31st ANNIVERSARY</div>
                        <div class="mail-brand">KSERS<span>2027</span></div>
                        <div class="mail-date">April 22–24, 2027 | Lotte Hotel Seoul, Korea</div>
                    </div>
                </div>
                <div class="mail-body">
                    <div class="mail-cat" id="mailCategory"><?= h($template['category']) ?></div>
                    <textarea class="mail-edit title" id="mailTitle" name="mail_title" rows="2"><?= h($template['title']) ?></textarea>
                    <p style="margin:16px 0;" id="mailGreeting"><?= h($greeting) ?></p>
                    <textarea class="mail-edit body" id="mailBody" name="body" rows="6"><?= h($template['body']) ?></textarea>
                </div>
                <div style="padding:8px 32px;">
                    <table class="mail-table" id="mailRows">
                        <?php foreach ($template['rows'] as $row) { ?>
                        <tr><th><?= h($row[0]) ?></th><td><?= h($row[1]) ?></td></tr>
                        <?php } ?>
                    </table>
                </div>
                <div id="mailButtonWrap" style="text-align:center;padding:24px 32px 8px;<?= $template['button_label'] === '' ? 'display:none;' : '' ?>">
                    <span class="mail-btn" id="mailButton"><?= h($template['button_label']) ?></span>
                </div>
                <div id="mailNoticeWrap" style="padding:16px 32px 8px;<?= $template['notice'] === '' ? 'display:none;' : '' ?>">
                    <div class="mail-notice" id="mailNotice"><?= h($template['notice']) ?></div>
                </div>
                <div class="mail-body" style="padding-top:8px;" id="mailSignoff">
                    If you have any questions, please contact the Secretariat at ksers2027@ksers.kr.<br><br>
                    Best regards,<br><strong>KSERS 2027 Secretariat</strong>
                </div>
                <div class="mail-foot" id="mailFoot">
                    KSERS 2027 Secretariat<br>
                    This e-mail was sent in connection with your <?= h($template['send_reason']) ?> for KSERS 2027.
                </div>
            </div>
        </div>
    </div>
    <div>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-3"><h5 class="m-0 fw-bold"><?= h(t('받는 사람', 'Recipients')) ?></h5></div>
            <div class="card-body">
                <?php if ($attendees) { ?>
                <p class="text-muted small"><?= h(t('취소하지 않은 등록 저장 계정입니다. 등록번호가 메일에 들어갑니다.', 'Saved registrations that are not cancelled. The registration number is included.')) ?></p>
                <?php } ?>
                <div class="row g-2 mb-3">
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="unpaidPick" form="filterForm" name="unpaid" value="1"<?= $unpaid ? ' checked' : '' ?>>
                            <label class="form-check-label" for="unpaidPick"><?= h(t('등록 정보 저장 후 미결제/미입금자', 'Saved registration, payment not completed')) ?></label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="filterPick"><?= h(t('초록 기준', 'Abstract filter')) ?></label>
                        <select class="form-select" id="filterPick" form="filterForm" name="filter"<?= $lockFilters ? ' disabled' : '' ?>>
                            <?php foreach ($filters as $key => $label) { ?>
                            <option value="<?= h($key) ?>" <?= $key === $filter ? 'selected' : '' ?>><?= h($label) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="classPick"><?= h(t('회원 구분', 'Member class')) ?></label>
                        <select class="form-select" id="classPick" form="filterForm" name="user_class"<?= $lockFilters ? ' disabled' : '' ?>>
                            <option value=""><?= h(t('전체', 'All')) ?></option>
                            <?php foreach ($classChoices as $code) { ?>
                            <option value="<?= h($code) ?>" <?= $userClass === $code ? 'selected' : '' ?>><?= h(ksers_user_class_label($code)) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="typePick"><?= h(t('발표 형태', 'Presentation')) ?></label>
                        <select class="form-select" id="typePick" form="filterForm" name="presentation"<?= $lockFilters ? ' disabled' : '' ?>>
                            <option value=""><?= h(t('전체', 'All')) ?></option>
                            <?php foreach ($presentationChoices as $code => $label) { ?>
                            <option value="<?= h($code) ?>" <?= $presentation === $code ? 'selected' : '' ?>><?= h($label) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="acceptPick"><?= h(t('채택 여부', 'Decision')) ?></label>
                        <select class="form-select" id="acceptPick" form="filterForm" name="accept"<?= $lockFilters ? ' disabled' : '' ?>>
                            <option value=""><?= h(t('전체', 'All')) ?></option>
                            <?php foreach ($acceptChoices as $code => $label) { ?>
                            <option value="<?= h($code) ?>" <?= $accept === $code ? 'selected' : '' ?>><?= h($label) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="regPick"><?= h(t('등록 여부', 'Registration')) ?></label>
                        <select class="form-select" id="regPick" form="filterForm" name="registered"<?= $lockFilters ? ' disabled' : '' ?>>
                            <option value=""><?= h(t('전체', 'All')) ?></option>
                            <option value="yes" <?= $registered === 'yes' ? 'selected' : '' ?>><?= h(t('등록', 'Registered')) ?></option>
                            <option value="no" <?= $registered === 'no' ? 'selected' : '' ?>><?= h(t('미등록', 'Not registered')) ?></option>
                        </select>
                    </div>
                </div>
                <button class="btn btn-outline-secondary btn-sm" type="submit" form="filterForm"><?= h(t('조회', 'Search')) ?></button>
                <div class="table-responsive mt-3" style="max-height:280px;">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr>
                            <th></th>
                            <th><?= h(t('이름', 'Name')) ?></th>
                            <th><?= h(t('이메일', 'Email')) ?></th>
                            <th><?= h(t('역할', 'Role')) ?></th>
                        </tr></thead>
                        <tbody>
                        <?php if (!$candidates) { ?>
                        <tr><td colspan="4" class="text-muted text-center py-3"><?= h(t('조건에 맞는 사람이 없습니다.', 'No one matches this filter.')) ?></td></tr>
                        <?php } ?>
                        <?php foreach ($candidates as $person) {
                            $payload = json_encode([
                                'email' => $person['email'],
                                'name' => $person['name'],
                                'role' => $person['role'],
                                'context' => $person['context'],
                                'authors' => isset($person['authors']) ? $person['authors'] : [],
                            ], JSON_UNESCAPED_UNICODE);
                        ?>
                        <tr>
                            <td><input class="form-check-input candidate-check" type="checkbox" data-payload="<?= h($payload) ?>"></td>
                            <td><?= h($person['name']) ?></td>
                            <td><?= h($person['email']) ?></td>
                            <td><?= h($person['role']) ?><?php if (!empty($person['context']['receipt_no'])) { ?><br><span class="text-muted"><?= h($person['context']['receipt_no']) ?></span><?php } elseif (!empty($person['context']['reg_no'])) { ?><br><span class="text-muted"><?= h($person['context']['reg_no']) ?></span><?php } ?></td>
                        </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                    <button class="btn btn-primary-custom btn-sm" type="button" id="addRecipients"><?= h(t('추가', 'Add')) ?></button>
                    <?php if ($filter === 'submitted') { ?>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="allAuthors">
                        <label class="form-check-label" for="allAuthors"><?= h(t('체크시 초록에 등록된 모든 저자와 제출자에게 전송', 'When checked, send to every author and the submitter')) ?></label>
                    </div>
                    <?php } ?>
                </div>
                <?php if ($filter === 'submitted') { ?>
                <p class="text-muted small mb-0 mt-2"><?= h(t('체크하지 않으면 추가한 계정만 메일이 전송됩니다.', 'If this is not checked, the message goes only to the account you add.')) ?></p>
                <?php } ?>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between">
                <h5 class="m-0 fw-bold"><?= h(t('발송 목록', 'Send list')) ?></h5>
                <span class="text-muted" id="recipientCount">0</span>
            </div>
            <div class="card-body">
                <ul class="list-group recipient-list mb-3" id="recipientList"></ul>
                <button class="btn btn-primary-custom" type="button" id="sendMail"><?= h(t('발송', 'Send')) ?></button>
            </div>
        </div>
    </div>
</div>
</form>

<form method="get" action="index.php" id="filterForm">
    <input type="hidden" name="template" value="<?= h($templateKey) ?>">
    <?php if ($attendees) { ?>
    <input type="hidden" name="attend" value="1">
    <?php } ?>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="m-0 fw-bold"><?= h(t('발송 이력', 'Send history')) ?></h5>
        <a class="btn btn-sm btn-outline-secondary" href="index.php?<?= h(http_build_query(['template' => $templateKey, 'filter' => $filter, 'unpaid' => $unpaid ? '1' : '0', 'history' => $failedOnly ? '' : 'failed'])) ?>"><?= h($failedOnly ? t('전체 보기', 'Show all') : t('실패 주소만', 'Failed only')) ?></a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr>
                <th><?= h(t('일시', 'When')) ?></th>
                <th><?= h(t('받는 사람', 'Recipient')) ?></th>
                <th><?= h(t('제목', 'Subject')) ?></th>
                <th><?= h(t('결과', 'Result')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$history) { ?>
            <tr><td colspan="4" class="text-center text-muted py-3"><?= h(t('발송 이력이 없습니다.', 'No messages sent yet.')) ?></td></tr>
            <?php } ?>
            <?php foreach ($history as $log) { ?>
            <tr>
                <td><?= h($log['reg_date']) ?></td>
                <td><?= h($log['recipient_name']) ?><br><?= h($log['email']) ?></td>
                <td><?= h($log['subject']) ?></td>
                <td><?= h($log['status'] === 'sent' ? t('성공', 'Sent') : t('실패', 'Failed')) ?><?php if ($log['fail_reason'] !== '') { ?><br><span class="text-danger"><?= h($log['fail_reason']) ?></span><?php } ?></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= h(t('메일 폼 변경', 'Change mail form')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body"><?= h(t('작성 중인 제목과 본문이 선택한 폼의 기본 문구로 바뀝니다.', 'The subject and body you are writing will be replaced by the selected form.')) ?></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(t('취소', 'Cancel')) ?></button>
                <button type="button" class="btn btn-primary-custom" id="templateConfirm"><?= h(t('변경', 'Change')) ?></button>
            </div>
        </div>
    </div>
</div>
<?php if (is_array($mailResult)) {
    $resultFailed = isset($mailResult['failed']) && is_array($mailResult['failed']) ? $mailResult['failed'] : [];
    $resultOk = !empty($mailResult['ok']);
?>
<div class="modal fade" id="mailResultModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= h($resultOk ? t('발송을 마쳤습니다', 'Sending is complete') : t('발송에 실패했습니다', 'Sending failed')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <?php if ($resultOk) { ?>
                <p class="mb-0"><?= h(t('총 ' . (int)$mailResult['sent'] . '건', 'Total ' . (int)$mailResult['sent'])) ?></p>
                <?php } else { ?>
                <?php if ((int)$mailResult['sent'] > 0) { ?>
                <p class="mb-2"><?= h(t('성공 ' . (int)$mailResult['sent'] . '건은 발송되었습니다.', (int)$mailResult['sent'] . ' message(s) were sent.')) ?></p>
                <?php } ?>
                <?php if (trim((string)$mailResult['reason']) !== '') { ?>
                <p class="mb-0"><?= nl2br(h($mailResult['reason'])) ?></p>
                <?php } ?>
                <?php if ($resultFailed) { ?>
                <ul class="mail-fail-list">
                    <?php foreach ($resultFailed as $account) {
                        $failedEmail = trim(isset($account['email']) ? (string)$account['email'] : '');
                        $failedName = trim(isset($account['name']) ? (string)$account['name'] : '');
                        $failedReason = trim(isset($account['reason']) ? (string)$account['reason'] : '');
                        $label = $failedName !== '' ? ($failedName . ' · ' . $failedEmail) : $failedEmail;
                    ?>
                    <li><?= h($label) ?><?php if ($failedReason !== '' && $failedReason !== trim((string)$mailResult['reason'])) { ?><br><span class="text-danger"><?= h($failedReason) ?></span><?php } ?></li>
                    <?php } ?>
                </ul>
                <?php } ?>
                <?php } ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary-custom" data-bs-dismiss="modal"><?= h(t('확인', 'OK')) ?></button>
            </div>
        </div>
    </div>
</div>
<?php } ?>
<div class="modal fade" id="sendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= h(t('발송 확인', 'Confirm send')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body" id="sendModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(t('취소', 'Cancel')) ?></button>
                <button type="button" class="btn btn-primary-custom" id="sendConfirm"><?= h(t('발송', 'Send')) ?></button>
            </div>
        </div>
    </div>
</div>
<script>
window.ksersAdminMail = <?= json_encode([
    'template' => $templateKey,
    'mailLang' => $mailLang,
    'bilingual' => $bilingual,
    'templates' => $clientTemplates,
    'confirmSend' => t('%d명에게 이 메일을 보냅니다. 같은 주소는 한 번만 발송됩니다.', 'This message will be sent to %d people. Each address receives it once.'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<?php
$admin_page_script = __DIR__ . '/script.php';
include '../../lib/footer.php';
