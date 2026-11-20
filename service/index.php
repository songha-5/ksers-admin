<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_participant($pdo);
$page_title = t('홈 - ' . $eventYear . ' 국제학술대회', 'Home - ' . $eventYear . ' International Conference');
$page_heading = t('홈', 'Home');

include 'lib/header.php';
include 'lib/menu.php';

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$memberSeq = (int)$no;
$year = isset($eventYear) ? (int)$eventYear : 2027;

$memberStmt = $pdo->prepare("SELECT name_ko, email FROM member_info WHERE seq = ?");
$memberStmt->execute([$memberSeq]);
$member = $memberStmt->fetch(PDO::FETCH_ASSOC);

$participantStmt = $pdo->prepare("SELECT seq, reg_date, pay_confirm, cancelled_at FROM participant_info WHERE member_seq = ? AND year = ?");
$participantStmt->execute([$memberSeq, $year]);
$participant = $participantStmt->fetch(PDO::FETCH_ASSOC);
if ($participant && !empty($participant['cancelled_at'])) {
    $participant = null;
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5 bg-white">
                <h5 class="fw-bold mb-3"><?= h($member ? $member['name_ko'] : '') ?><?= h(t(' 님, ', ', ')) ?><?= $year ?><?= h(t(' 국제학술대회 등록입니다.', ' International Conference registration.')) ?></h5>
                <?php if ($participant) { ?>
                <p class="mb-2"><?= h(t('올해 참가 등록이 저장되어 있습니다.', 'This year’s registration is saved.')) ?></p>
                <p class="text-muted small mb-4"><?= h(t('처음 저장: ', 'First saved: ')) ?><?= h($participant['reg_date']) ?>. <?php if (isset($participant['pay_confirm']) && $participant['pay_confirm'] === 'Y') { ?><?= h(t('결제가 완료되어 등록 내용은 수정할 수 없습니다.', 'Payment is complete, so the registration cannot be edited.')) ?><?php } else { ?><?= h(t('내용을 고치려면 참가자 등록 화면으로 들어가면 됩니다. 다시 저장해도 등록은 하나뿐입니다.', 'Open registration to change it. Saving again updates the same registration.')) ?><?php } ?></p>
                <a href="registration.php" class="btn btn-primary-custom fw-bold"><?= h(t('등록 내용 보기', 'View registration')) ?></a>
                <?php } else { ?>
                <p class="mb-2"><?= h(t('아직 올해 참가 등록이 없습니다.', 'You have not registered for this year.')) ?></p>
                <p class="text-muted small mb-4"><?= h(t('회원가입과 별도입니다. 로그인한 뒤 참가자 정보를 한 번 저장하면 이 화면에서 결제할 수 있습니다.', 'This is separate from sign-up. After logging in, save your participant details once. You can pay on this page after saving.')) ?></p>
                <a href="registration.php" class="btn btn-primary-custom fw-bold"><?= h(t('참가 등록하기', 'Register')) ?></a>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php
include 'lib/footer.php';
?>
