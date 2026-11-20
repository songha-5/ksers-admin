<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
$page_title = t('참가 등록 조회 - 2027 국제학술대회 관리자', 'Registration lookup - 2027 Conference Admin');
$page_heading = t('참가 등록', 'Registration');
$year = ksers_admin_year($pdo);

include '../../lib/header.php';
include '../../lib/menu.php';
$registrationTab = 'list';
include 'tabs.php';

$perPage = 20;
$page = isset($_GET['reg_page']) ? (int)$_GET['reg_page'] : 1;
if ($page < 1) {
    $page = 1;
}
$count = $pdo->prepare("SELECT COUNT(*) FROM participant_info WHERE year = ?");
$count->execute([$year]);
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
    "SELECT p.seq, p.reg_date, p.ksers_member, p.category, p.support_status, p.cancelled_at,
            p.license_no, p.cme_apply, p.support_file, p.dietary, p.dietary_detail, p.pay_confirm,
            m.email, m.name_ko, m.first_name_eng, m.last_name_eng, m.country
     FROM participant_info p
     JOIN member_info m ON m.seq = p.member_seq
     WHERE p.year = ?
     ORDER BY p.seq DESC
     LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
);
$stmt->execute([$year]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$categories = ksers_registration_categories();
$memberChoices = ksers_registration_member_choices();
$diets = ksers_registration_diets();
$regPageHref = function ($target) {
    return 'list.php?reg_page=' . (int)$target;
};
?>

<?php if (!empty($_SESSION['admin_notice'])) { ?>
<div class="alert alert-info border-0 shadow-sm"><?= h($_SESSION['admin_notice']) ?></div>
<?php unset($_SESSION['admin_notice']); } ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="m-0 fw-bold"><?= h($year . ' ' . t('참가 등록', 'registrations')) ?> <span class="text-muted fs-6"><?= $total ?></span></h5>
        <a class="btn btn-sm btn-outline-success" href="/admin/export.php?kind=registration"><?= h(t('엑셀', 'Excel')) ?></a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0 text-nowrap">
            <thead class="table-light">
                <tr>
                    <th><?= h(t('이메일', 'Email')) ?></th>
                    <th><?= h(t('성명(국문)', 'Name (Korean)')) ?></th>
                    <th><?= h(t('이름(영문)', 'First name (English)')) ?></th>
                    <th><?= h(t('성(영문)', 'Last name (English)')) ?></th>
                    <th><?= h(t('KSERS 회원여부', 'KSERS Membership')) ?></th>
                    <th><?= h(t('직종', 'Category')) ?></th>
                    <th><?= h(t('의사면허번호', 'Medical License No.')) ?></th>
                    <th><?= h(t('평점 신청', 'CME credit')) ?></th>
                    <th><?= h(t('증빙 상태', 'Document status')) ?></th>
                    <th><?= h(t('증빙서류', 'Supporting Document')) ?></th>
                    <th><?= h(t('식이 제한', 'Special dietary')) ?></th>
                    <th><?= h(t('결제완료', 'Payment')) ?></th>
                    <th><?= h(t('등록일', 'Registered')) ?></th>
                    <th><?= h(t('증빙 자세히보기', 'Document details')) ?></th>
                    <th><?= h(t('등록 취소', 'Cancel registration')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows) { ?>
                <tr><td colspan="15" class="text-center text-muted py-4"><?= h(t('등록된 참가자가 없습니다.', 'No registrations yet.')) ?></td></tr>
                <?php } ?>
                <?php foreach ($rows as $row) {
                    $categoryLabel = isset($categories[$row['category']]) ? t($categories[$row['category']][0], $categories[$row['category']][1]) : '';
                    $dietLabel = isset($diets[$row['dietary']]) ? $diets[$row['dietary']] : '';
                    if ($dietLabel !== '' && trim((string)$row['dietary_detail']) !== '') {
                        $dietLabel .= ' (' . $row['dietary_detail'] . ')';
                    }
                    $ksersLabel = isset($memberChoices[$row['ksers_member']]) ? t($memberChoices[$row['ksers_member']][0], $memberChoices[$row['ksers_member']][1]) : '';
                    $supportApplicable = strtoupper(trim((string)$row['country'])) !== 'KR' && in_array($row['category'], ['student', 'nurse'], true);
                    $lookupName = trim((string)$row['name_ko']);
                    if ($lookupName === '') {
                        $lookupName = trim($row['first_name_eng'] . ' ' . $row['last_name_eng']);
                    }
                    if ($lookupName === '') {
                        $lookupName = (string)$row['email'];
                    }
                    $cancelled = !empty($row['cancelled_at']);
                    $text = function ($value) use ($cancelled) {
                        $safe = h($value);
                        return $cancelled ? '<span class="reg-cancelled-text">' . $safe . '</span>' : $safe;
                    };
                ?>
                <tr>
                    <td><?= $text($row['email']) ?></td>
                    <td><?= $text($row['name_ko']) ?></td>
                    <td><?= $text($row['first_name_eng']) ?></td>
                    <td><?= $text($row['last_name_eng']) ?></td>
                    <td><?= $text($ksersLabel !== '' ? $ksersLabel : '—') ?></td>
                    <td><?= $text($categoryLabel) ?></td>
                    <td><?= $text($row['license_no']) ?></td>
                    <td><?= $text($row['cme_apply'] === 'Y' ? t('신청', 'Applied') : ($row['cme_apply'] === 'N' ? t('미신청', 'Not applied') : '—')) ?></td>
                    <td><?= $text(ksers_support_status_label(isset($row['support_status']) ? $row['support_status'] : '', $supportApplicable)) ?></td>
                    <td>
                        <?php if (trim((string)$row['support_file']) !== '') { ?>
                        <button class="btn btn-sm btn-outline-primary" type="button" onclick="ksersOpenSupport(<?= (int)$row['seq'] ?>)"><?= h(t('파일확인', 'View file')) ?></button>
                        <?php } else { ?><?= $text('—') ?><?php } ?>
                    </td>
                    <td><?= $text($dietLabel !== '' ? $dietLabel : '—') ?></td>
                    <td><?= $text($row['pay_confirm'] === 'Y' ? 'Y' : 'N') ?></td>
                    <td><?= $text($row['reg_date']) ?></td>
                    <td>
                        <?php if ($supportApplicable) { ?>
                        <a class="btn btn-sm btn-outline-primary" href="review.php?q=<?= rawurlencode($lookupName) ?>&focus=<?= (int)$row['seq'] ?>"><?= h(t('자세히보기', 'View details')) ?></a>
                        <?php } else { ?><?= $text('—') ?><?php } ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#registrationCancelModal" data-seq="<?= (int)$row['seq'] ?>" data-email="<?= h($row['email']) ?>"<?= $cancelled ? ' disabled' : '' ?>><?= h(t('참가 등록 취소', 'Cancel registration')) ?></button>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-3">
        <nav aria-label="<?= h(t('참가 등록 페이지', 'Registration pages')) ?>">
            <ul class="pagination pagination-sm justify-content-center m-0">
                <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                    <?php if ($page <= 1) { ?>
                    <span class="page-link"><?= h(t('이전', 'Previous')) ?></span>
                    <?php } else { ?>
                    <a class="page-link" href="<?= h($regPageHref($page - 1)) ?>"><?= h(t('이전', 'Previous')) ?></a>
                    <?php } ?>
                </li>
                <?php for ($i = $windowStart; $i <= $windowEnd; $i++) { ?>
                <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                    <a class="page-link" href="<?= h($regPageHref($i)) ?>"><?= $i ?></a>
                </li>
                <?php } ?>
                <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
                    <?php if ($page >= $pages) { ?>
                    <span class="page-link"><?= h(t('다음', 'Next')) ?></span>
                    <?php } else { ?>
                    <a class="page-link" href="<?= h($regPageHref($page + 1)) ?>"><?= h(t('다음', 'Next')) ?></a>
                    <?php } ?>
                </li>
            </ul>
        </nav>
    </div>
</div>

<style>
.reg-cancelled-text {
    color: #6c757d;
    text-decoration: line-through;
}
</style>

<div class="modal fade" id="registrationCancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="cancel.php">
            <div class="modal-header">
                <h5 class="modal-title"><?= h(t('참가 등록 취소', 'Cancel registration')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><?= h(t('이 참가 등록을 취소할까요? 취소하면 참가자 화면이 신청 전 상태로 돌아갑니다.', 'Cancel this registration? The participant page returns to the state before they applied.')) ?></p>
                <p class="mb-0" id="registrationCancelWho"></p>
                <p class="mb-0 mt-3"><?= h(t('확인을 누르면 사용자에게 등록 취소 안내 메일이 발송됩니다.', 'Confirming sends the participant a registration cancellation notice.')) ?></p>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="seq" id="registrationCancelSeq" value="">
                <input type="hidden" name="reg_page" value="<?= (int)$page ?>">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(t('닫기', 'Close')) ?></button>
                <button class="btn btn-danger" type="submit"><?= h(t('확인', 'Confirm')) ?></button>
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
    window.open(
        'support.php?seq=' + encodeURIComponent(seq),
        'ksersSupport' + seq,
        'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes'
    );
}
document.getElementById('registrationCancelModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('registrationCancelSeq').value = button ? (button.getAttribute('data-seq') || '') : '';
    const who = document.getElementById('registrationCancelWho');
    if (who) {
        who.textContent = button ? (button.getAttribute('data-email') || '') : '';
    }
});
</script>
<?php include '../../lib/footer.php'; ?>
