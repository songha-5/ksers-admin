<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
$page_title = t('증빙 확인 처리 - 2027 국제학술대회 관리자', 'Document review - 2027 Conference Admin');
$page_heading = t('참가 등록', 'Registration');
$year = ksers_admin_year($pdo);

include '../../lib/header.php';
include '../../lib/menu.php';
$registrationTab = 'review';
include 'tabs.php';

$categories = ksers_registration_categories();
$statusOptions = [
    'review' => t('증빙 확인 중', 'Checking the document'),
    'approved' => t('등록 완료(무료)', 'Registered (free)'),
    'rejected' => t('반려', 'Rejected'),
];
$filterQuery = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$filterCategory = isset($_GET['category']) && in_array($_GET['category'], ['student', 'nurse'], true) ? $_GET['category'] : '';
$filterCountry = isset($_GET['country']) ? strtoupper(trim((string)$_GET['country'])) : '';
$filterStatus = isset($_GET['status']) && isset($statusOptions[$_GET['status']]) ? $_GET['status'] : '';
$focus = isset($_GET['focus']) ? (int)$_GET['focus'] : 0;

$countryStmt = $pdo->prepare(
    "SELECT DISTINCT m.country, c.name_ko, c.name_eng
     FROM participant_info p
     JOIN member_info m ON m.seq = p.member_seq
     LEFT JOIN code c ON c.majr = 1 AND c.state = 1 AND c.iso = m.country
     WHERE p.year = ? AND p.category IN ('student', 'nurse') AND UPPER(TRIM(m.country)) <> 'KR' AND TRIM(m.country) <> ''
     ORDER BY c.name_ko, m.country"
);
$countryStmt->execute([$year]);
$countries = $countryStmt->fetchAll(PDO::FETCH_ASSOC);
$countryCodes = [];
foreach ($countries as $countryRow) {
    $countryCodes[] = strtoupper((string)$countryRow['country']);
}
if ($filterCountry !== '' && !in_array($filterCountry, $countryCodes, true)) {
    $filterCountry = '';
}

$where = ["p.year = ?", "p.category IN ('student', 'nurse')", "UPPER(TRIM(m.country)) <> 'KR'"];
$params = [$year];
if ($focus > 0) {
    $where[] = 'p.seq = ?';
    $params[] = $focus;
}
if ($filterCategory !== '') {
    $where[] = 'p.category = ?';
    $params[] = $filterCategory;
}
if ($filterCountry !== '') {
    $where[] = 'UPPER(TRIM(m.country)) = ?';
    $params[] = $filterCountry;
}
if ($filterStatus === 'review') {
    $where[] = "(p.support_status = 'review' OR p.support_status = '')";
} elseif ($filterStatus !== '') {
    $where[] = 'p.support_status = ?';
    $params[] = $filterStatus;
}
if ($filterQuery !== '') {
    $where[] = '(m.email LIKE ? OR m.name_ko LIKE ? OR m.first_name_eng LIKE ? OR m.last_name_eng LIKE ? OR CONCAT(m.first_name_eng, \' \', m.last_name_eng) LIKE ?)';
    $like = '%' . $filterQuery . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
$stmt = $pdo->prepare(
    "SELECT p.seq, p.category, p.support_file, p.support_status, p.support_handled_at, p.support_handler,
            m.email, m.country
     FROM participant_info p
     JOIN member_info m ON m.seq = p.member_seq
     WHERE " . implode(' AND ', $where) . "
     ORDER BY p.seq DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$keep = [
    'q' => $filterQuery,
    'category' => $filterCategory,
    'country' => $filterCountry,
    'status' => $filterStatus,
    'focus' => $focus > 0 ? (string)$focus : '',
];
?>

<?php if (!empty($_SESSION['admin_notice'])) { ?>
<div class="alert alert-info border-0 shadow-sm"><?= h($_SESSION['admin_notice']) ?></div>
<?php unset($_SESSION['admin_notice']); } ?>

<form class="card border-0 shadow-sm mb-3" method="get" action="review.php">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1" for="filterQuery"><?= h(t('이름', 'Name')) ?></label>
                <input class="form-control form-control-sm" id="filterQuery" name="q" value="<?= h($filterQuery) ?>" placeholder="<?= h(t('이름 또는 이메일', 'Name or email')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1" for="filterCategory"><?= h(t('직종', 'Category')) ?></label>
                <select class="form-select form-select-sm" id="filterCategory" name="category">
                    <option value=""><?= h(t('전체', 'All')) ?></option>
                    <option value="student"<?= $filterCategory === 'student' ? ' selected' : '' ?>><?= h(t('학생', 'Student')) ?></option>
                    <option value="nurse"<?= $filterCategory === 'nurse' ? ' selected' : '' ?>><?= h(t('간호사', 'Nurse')) ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1" for="filterCountry"><?= h(t('국가', 'Country')) ?></label>
                <select class="form-select form-select-sm" id="filterCountry" name="country">
                    <option value=""><?= h(t('전체', 'All')) ?></option>
                    <?php foreach ($countries as $countryRow) {
                        $code = strtoupper((string)$countryRow['country']);
                        $countryLabel = ksers_lang() === 'en'
                            ? ((string)$countryRow['name_eng'] !== '' ? $countryRow['name_eng'] : $code)
                            : ((string)$countryRow['name_ko'] !== '' ? $countryRow['name_ko'] : $code);
                    ?>
                    <option value="<?= h($code) ?>"<?= $filterCountry === $code ? ' selected' : '' ?>><?= h($countryLabel) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1" for="filterStatus"><?= h(t('상태', 'Status')) ?></label>
                <select class="form-select form-select-sm" id="filterStatus" name="status">
                    <option value=""><?= h(t('전체', 'All')) ?></option>
                    <?php foreach ($statusOptions as $statusCode => $statusLabel) { ?>
                    <option value="<?= h($statusCode) ?>"<?= $filterStatus === $statusCode ? ' selected' : '' ?>><?= h($statusLabel) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
                <button class="btn btn-sm btn-primary-custom" type="submit"><?= h(t('조회', 'Search')) ?></button>
                <a class="btn btn-sm ksers-outline" href="review.php"><?= h(t('초기화', 'Reset')) ?></a>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="m-0 fw-bold"><?= h(t('증빙 확인 처리', 'Document review')) ?> <span class="text-muted fs-6 fw-normal"><?= h(t('외국인 학생·간호사만 표시됩니다.', 'Only students and nurses from outside Korea are shown.')) ?> <?= count($rows) ?></span></h5>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0 text-nowrap">
            <thead class="table-light">
                <tr>
                    <th><?= h(t('이메일', 'Email')) ?></th>
                    <th><?= h(t('직종', 'Category')) ?></th>
                    <th><?= h(t('증빙서류', 'Supporting Document')) ?></th>
                    <th><?= h(t('처리 일시', 'Processed')) ?></th>
                    <th><?= h(t('처리자', 'Processed by')) ?></th>
                    <th><?= h(t('상태', 'Status')) ?></th>
                    <th><?= h(t('관리', 'Manage')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows) { ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><?= h(t('해당하는 참가자가 없습니다.', 'No matching participants.')) ?></td></tr>
                <?php } ?>
                <?php foreach ($rows as $row) {
                    $categoryLabel = isset($categories[$row['category']]) ? t($categories[$row['category']][0], $categories[$row['category']][1]) : '';
                    $handled = '';
                    if (!empty($row['support_handled_at'])) {
                        $stamp = strtotime((string)$row['support_handled_at']);
                        $handled = $stamp ? date('Y-m-d H:i', $stamp) : (string)$row['support_handled_at'];
                    }
                    $passed = $row['support_status'] === 'approved';
                    $rejected = $row['support_status'] === 'rejected';
                ?>
                <tr>
                    <td><?= h($row['email']) ?></td>
                    <td><?= h($categoryLabel) ?></td>
                    <td>
                        <?php if (trim((string)$row['support_file']) !== '') { ?>
                        <button class="btn btn-sm btn-outline-primary" type="button" onclick="ksersOpenSupport(<?= (int)$row['seq'] ?>)"><?= h(t('파일확인', 'View file')) ?></button>
                        <?php } else { ?>—<?php } ?>
                    </td>
                    <td><?= h($handled !== '' ? $handled : '—') ?></td>
                    <td><?= h(trim((string)$row['support_handler']) !== '' ? $row['support_handler'] : '—') ?></td>
                    <td><?= h(ksers_support_status_label(isset($row['support_status']) ? $row['support_status'] : '', true)) ?></td>
                    <td>
                        <button class="btn btn-sm btn-primary-custom" type="button" data-bs-toggle="modal" data-bs-target="#supportPassModal" data-seq="<?= (int)$row['seq'] ?>"<?= $passed ? ' disabled' : '' ?>><?= h(t('통과', 'Approve')) ?></button>
                        <button class="btn btn-sm btn-outline-danger" type="button" onclick="ksersOpenReject(<?= (int)$row['seq'] ?>)"<?= $rejected ? ' disabled' : '' ?>><?= h(t('반려', 'Reject')) ?></button>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="supportPassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="support_action.php">
            <div class="modal-header">
                <h5 class="modal-title"><?= h(t('통과', 'Approve')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body"><?= h(t('증빙을 통과 처리할까요? 통과하면 등록 완료(무료)로 바뀌고, 참가자가 결제할 수 있습니다.', 'Approve this document? The status becomes Registered (free), and the participant can continue to payment.')) ?></div>
            <div class="modal-footer">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="seq" id="supportPassSeq" value="">
                <?php foreach ($keep as $key => $value) { if ($value === '') { continue; } ?>
                <input type="hidden" name="<?= h($key) ?>" value="<?= h($value) ?>">
                <?php } ?>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(t('취소', 'Cancel')) ?></button>
                <button class="btn btn-primary-custom" type="submit"><?= h(t('통과', 'Approve')) ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function ksersOpenSupport(seq) {
    const width = 860;
    const height = 900;
    const left = Math.max(0, Math.round((screen.width - width) / 2));
    const top = Math.max(0, Math.round((screen.height - height) / 2));
    window.open('support.php?seq=' + encodeURIComponent(seq), 'ksersSupport' + seq, 'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes');
}
function ksersOpenReject(seq) {
    const params = new URLSearchParams(<?= json_encode($keep, JSON_UNESCAPED_UNICODE) ?>);
    params.set('seq', String(seq));
    const width = 720;
    const height = 640;
    const left = Math.max(0, Math.round((screen.width - width) / 2));
    const top = Math.max(0, Math.round((screen.height - height) / 2));
    window.open('reject.php?' + params.toString(), 'ksersReject' + seq, 'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes');
}
document.getElementById('supportPassModal')?.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('supportPassSeq').value = button ? (button.getAttribute('data-seq') || '') : '';
});
</script>
<?php include '../../lib/footer.php'; ?>
