<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
if (isset($_GET['pay']) || isset($_GET['hist_page'])) {
    header('Location: registration.php?' . $_SERVER['QUERY_STRING']);
    exit;
}
$page_title = t('회원정보 - ' . $eventYear . ' 국제학술대회', 'Profile - ' . $eventYear . ' International Conference');
$page_heading = t('회원정보', 'Profile');

include 'lib/header.php';
include 'lib/menu.php';

$country_list = ksers_country_rows($pdo);

$memberStmt = $pdo->prepare("SELECT * FROM member_info WHERE seq = ?");
$memberStmt->execute([(int)$no]);
$data = $memberStmt->fetch(PDO::FETCH_ASSOC);
$memberCountry = [];
foreach ($country_list as $countryRow) {
    if ((string)$countryRow['iso'] === (string)$data['country']) {
        $memberCountry = $countryRow;
        break;
    }
}
$memberProfile = ksers_country_profile($memberCountry);
$memberKorea = $memberProfile['korea'];
$memberDial = $memberProfile['dial'] !== '' ? $memberProfile['dial'] : '+';
$mobileLocal = ksers_phone_local($memberProfile['dial'], isset($data['mobile']) ? $data['mobile'] : '');
$telephoneLocal = ksers_phone_local($memberProfile['dial'], isset($data['telephone']) ? $data['telephone'] : '');
$displayName = $memberKorea
    ? trim((string)$data['name_ko'])
    : trim($data['first_name_eng'] . ' ' . $data['last_name_eng']);
$displayAffiliation = $memberKorea ? $data['affiliation_ko'] : $data['affiliation_eng'];
$initialSource = $displayName !== '' ? $displayName : $data['email'];
$displayInitial = function_exists('mb_substr') ? mb_substr($initialSource, 0, 1, 'UTF-8') : preg_replace('/^(.).*/u', '$1', $initialSource);
$emailAgreeChangedAt = '';
if (!empty($_SESSION['email_agree_changed_at'])) {
    $emailAgreeChangedAt = trim((string)$_SESSION['email_agree_changed_at']);
    unset($_SESSION['email_agree_changed_at']);
}
?>

<?php if ($emailAgreeChangedAt !== '') { ?>
<div class="alert alert-success border-0 shadow-sm"><?= h(t('수신 동의가 변경되었습니다 (' . $emailAgreeChangedAt . ')', 'E-mail consent was updated (' . $emailAgreeChangedAt . ')')) ?></div>
<?php } ?>

<!-- 사용자 요약 프로필 카드 -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-4 bg-white d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary-custom text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-4" style="width: 56px; height: 56px;">
                <?= h($displayInitial) ?>
            </div>
            <div>
                <h5 class="fw-bold m-0"><?= h($displayName) ?><?= $memberKorea ? h(t(' 님', '')) : '' ?></h5>
                <p class="text-muted small m-0"><?= h($data['email']) ?><?= $displayAffiliation !== '' ? ' | ' . h($displayAffiliation) : '' ?></p>
            </div>
        </div>
        <?php if (!empty($data['is_admin'])) { ?>
        <div class="d-flex gap-2">
            <a href="/admin/index.php" target="_blank" class="btn btn-sm btn-primary-custom">
                <i class="bi bi-speedometer2 me-1"></i> <?= h(t('관리자', 'Admin')) ?>
            </a>
        </div>
        <?php } ?>
    </div>
</div>

<?php
$supportStmt = $pdo->prepare("SELECT category, support_status, support_reason, cancelled_at FROM participant_info WHERE member_seq = ? AND year = ?");
$supportStmt->execute([(int)$no, (int)$eventYear]);
$supportRow = $supportStmt->fetch(PDO::FETCH_ASSOC);
$showSupportStatus = !$memberKorea && $supportRow && empty($supportRow['cancelled_at']) && in_array($supportRow['category'], ['student', 'nurse'], true);
?>
<?php if ($showSupportStatus) { ?>
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="card-title m-0 fw-bold"><i class="bi bi-file-earmark-check me-2"></i><?= h(t('증빙 심사', 'Document review')) ?></h5>
    </div>
    <div class="card-body p-4">
        <p class="mb-0"><?= h(t('심사 상태', 'Review status')) ?>: <?= h(ksers_support_status_label($supportRow['support_status'], true)) ?></p>
        <?php if ($supportRow['support_status'] === 'rejected' && trim((string)$supportRow['support_reason']) !== '') { ?>
        <p class="mb-0 mt-2"><?= h(t('반려 사유', 'Reason for rejection')) ?>: <?= h($supportRow['support_reason']) ?></p>
        <?php } ?>
    </div>
</div>
<?php } ?>

<!-- SECTION 1: 회원정보 수정 영역 (상단) -->
<div class="card border-0 shadow-sm rounded-3 mb-5" id="profileSection">
    <div class="card-header bg-white py-3">
        <h5 class="card-title m-0 fw-bold"><i class="bi bi-person-gear me-2"></i><?= h(t('회원정보 수정', 'Edit profile')) ?></h5>
    </div>
    <div class="card-body p-4 p-md-5">
        
        <form id="editProfileForm" action="json.php" method="post" data-leave="profile">
            <input type="hidden" name="board" value="member">
            <input type="hidden" name="mode" value="update">
            <input type="hidden" name="seq" value="<?=$no?>">
            <fieldset id="profileFields" disabled>
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <label class="form-label small fw-bold"><?= h(t('국가', 'Country')) ?></label>
                    <input type="text" class="form-control bg-light" value="<?= h($memberCountry ? code_name($memberCountry) : '') ?>" readonly>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold"><?= h(t('이메일 주소 (ID)', 'Email address (ID)')) ?></label>
                    <input type="email" class="form-control bg-light" value="<?= h($data['email']) ?>" readonly>
                    <div class="form-text small"><?= h(t('국가, 이메일, 성명은 수정할 수 없습니다.', 'Country, email, and name cannot be changed.')) ?></div>
                </div>
                <div class="col-md-6">
                    <label for="newPassword" class="form-label small fw-bold"><?= h(t('새 비밀번호', 'New password')) ?></label>
                    <input type="password" class="form-control" id="newPassword" name="new_password" placeholder="<?= h(t('변경할 경우에만 입력하세요', 'Only if you want to change it')) ?>">
                    <div class="form-text small"><?= h(t('8자 이상, 특수문자를 포함해 입력해 주세요.', 'At least 8 characters, including a special character.')) ?></div>
                </div>
                <div class="col-md-6">
                    <label for="newPasswordConfirm" class="form-label small fw-bold"><?= h(t('새 비밀번호 확인', 'Confirm new password')) ?></label>
                    <input type="password" class="form-control" id="newPasswordConfirm" name="new_password_confirm" placeholder="<?= h(t('새 비밀번호 재입력', 'Re-enter the new password')) ?>">
                </div>
            </div>

            <div class="mb-4">
                <h6 class="fw-bold text-success mb-3"><i class="bi bi-person-badge me-2"></i><?= h(t('개인정보', 'Personal information')) ?></h6>
                <div class="row g-3">
                    <?php if ($memberKorea) { ?>
                    <div class="col-12">
                        <label class="form-label small fw-bold"><?= h(t('성명(국문)', 'Name (Korean)')) ?></label>
                        <input type="text" class="form-control bg-light" value="<?= h($data['name_ko']) ?>" readonly>
                    </div>
                    <?php } else { ?>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold"><?= h(t('이름(영문)', 'First name (English)')) ?></label>
                        <input type="text" class="form-control bg-light" value="<?= h($data['first_name_eng']) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold"><?= h(t('성(영문)', 'Last name (English)')) ?></label>
                        <input type="text" class="form-control bg-light" value="<?= h($data['last_name_eng']) ?>" readonly>
                    </div>
                    <?php } ?>
                    <div class="col-12">
                        <div class="form-label small fw-bold"><?= h(t('호칭', 'Title')) ?></div>
                        <div class="d-flex flex-wrap gap-3">
                            <?php
                            $salutationOptions = ['' => 'None', 'Prof.' => 'Prof.', 'Dr.' => 'Dr.', 'Mr.' => 'Mr.', 'Ms.' => 'Ms.'];
                            $currentSalutation = isset($data['salutation']) ? (string)$data['salutation'] : '';
                            if (!array_key_exists($currentSalutation, $salutationOptions)) {
                                $currentSalutation = '';
                            }
                            foreach ($salutationOptions as $salutationValue => $salutationLabel) {
                                $salutationId = 'salutation' . ($salutationValue === '' ? 'None' : preg_replace('/[^A-Za-z]/', '', $salutationValue));
                            ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="salutation" id="<?= h($salutationId) ?>" value="<?= h($salutationValue) ?>"<?= $currentSalutation === $salutationValue ? ' checked' : '' ?>>
                                <label class="form-check-label" for="<?= h($salutationId) ?>"><?= h($salutationLabel) ?></label>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="mobile" class="form-label small fw-bold"><?= h(t('휴대전화', 'Cell Phone')) ?> <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><?= h($memberDial) ?></span>
                            <input type="tel" class="form-control" id="mobile" name="mobile" value="<?= h($mobileLocal) ?>" placeholder="<?= h($memberProfile['mobile_placeholder']) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="telephone" class="form-label small fw-bold"><?= h(t('유선전화', 'Telephone')) ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><?= h($memberDial) ?></span>
                            <input type="tel" class="form-control" id="telephone" name="telephone" value="<?= h($telephoneLocal) ?>" placeholder="<?= h($memberProfile['telephone_placeholder']) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <h6 class="fw-bold text-success mb-3"><i class="bi bi-building me-2"></i><?= h(t('소속 정보', 'Affiliation information')) ?></h6>
                <div class="row g-3">
                    <?php if ($memberKorea) { ?>
                    <div class="col-12">
                        <label for="affiliationType" class="form-label small fw-bold"><?= h(t('소속 선택', 'Affiliation')) ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="affiliationType" name="affiliation_type" required>
                            <option value=""><?= h(t('소속을 선택해 주세요', 'Choose an affiliation')) ?></option>
                            <?php foreach (ksers_affiliation_types() as $typeId => $typeLabel) { ?>
                            <option value="<?= h($typeId) ?>"<?= (isset($data['affiliation_type']) ? $data['affiliation_type'] : '') === $typeId ? ' selected' : '' ?>><?= h(t($typeLabel[0], $typeLabel[1])) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="affiliationKo" class="form-label small fw-bold"><?= h(t('소속(국문)', 'Affiliation - In Korean')) ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="affiliationKo" name="affiliation_ko" value="<?= h($data['affiliation_ko']) ?>" required>
                    </div>
                    <?php } ?>
                    <div class="col-12">
                        <label for="affiliationEn" class="form-label small fw-bold"><?= h(t('소속(영문)', 'Affiliation - In English')) ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="affiliationEn" name="affiliation_eng" value="<?= h($data['affiliation_eng']) ?>" required>
                    </div>
                    <div class="col-12">
                        <label for="department" class="form-label small fw-bold"><?= h(t('진료과/부서', 'Department')) ?></label>
                        <input type="text" class="form-control" id="department" name="department" maxlength="45" value="<?= h(isset($data['department']) ? $data['department'] : '') ?>" placeholder="<?= h(t('소화기내과', 'Gastroenterology')) ?>">
                    </div>
                </div>
            </div>

            <div class="join-consent-item mb-4">
                <input class="form-check-input" type="checkbox" id="emailAgree" name="email_agree" value="1"<?= !empty($data['agree_email']) ? ' checked' : '' ?>>
                <label class="join-consent-badge" for="emailAgree"><?= h(t('[선택]', '[Optional]')) ?></label>
                <div class="join-consent-body">
                    <div class="join-consent-head">
                        <label class="form-check-label small" for="emailAgree"><?= h(t('이메일 수신 동의', 'E-mail consent')) ?></label>
                    </div>
                    <p class="join-consent-note"><?= h(t('안내: 뉴스레터 등 행사 소식 메일의 수신 여부입니다. 초록·등록·결제 등 필수 안내 메일은 수신 동의와 관계없이 발송됩니다.', 'This setting applies to newsletters and event updates. Essential e-mails, such as abstract, registration and payment notices, will be sent regardless of this setting.')) ?></p>
                </div>
            </div>
            </fieldset>

            <div class="d-flex justify-content-end gap-2 border-top pt-4">
                <button type="button" class="btn btn-outline-secondary px-4 fw-bold" id="profileEditStart"><?= h(t('수정하기', 'Edit')) ?></button>
                <button type="submit" class="btn btn-primary-custom px-4 fw-bold d-none" id="profileSave" disabled>
                    <i class="bi bi-check-lg me-1"></i> <?= h(t('저장', 'Save')) ?>
                </button>
            </div>

        </form>

    </div>
</div>

<script>
    document.getElementById('profileEditStart')?.addEventListener('click', function () {
        const fields = document.getElementById('profileFields');
        const save = document.getElementById('profileSave');
        if (fields) {
            fields.disabled = false;
        }
        if (save) {
            save.disabled = false;
            save.classList.remove('d-none');
        }
        this.classList.add('d-none');
    });

    document.getElementById('editProfileForm')?.addEventListener('submit', function(e) {
        const newPass = document.getElementById('newPassword').value;
        const newPassConfirm = document.getElementById('newPasswordConfirm').value;

        if (newPass !== '' && newPass !== newPassConfirm) {
            e.preventDefault();
            alert(<?= json_encode(t('새 비밀번호가 일치하지 않습니다. 다시 확인해 주세요.', 'The new passwords do not match.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
            document.getElementById('newPasswordConfirm').focus();
        }
    });
</script>

<?php
include 'lib/footer.php';
?>