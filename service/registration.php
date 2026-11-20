<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_participant($pdo);
$page_title = t('참가 등록·결제 - ' . $eventYear . ' 국제학술대회', 'Registration & Payment - ' . $eventYear . ' International Conference');
$page_heading = t('참가 등록·결제', 'Registration & Payment');

include 'lib/header.php';
include 'lib/menu.php';

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$memberSeq = (int)$no;
$year = isset($eventYear) ? (int)$eventYear : 2027;

$memberStmt = $pdo->prepare("SELECT * FROM member_info WHERE seq = ?");
$memberStmt->execute([$memberSeq]);
$member = $memberStmt->fetch(PDO::FETCH_ASSOC);

$countryStmt = $pdo->prepare("SELECT minr, name_ko, name_eng, dial, iso FROM code WHERE majr = 1 AND state = 1 AND iso = ?");
$countryStmt->execute([$member ? (string)$member['country'] : '']);
$memberCountry = $countryStmt->fetch(PDO::FETCH_ASSOC);
$memberKorea = ksers_country_profile($memberCountry ? $memberCountry : [])['korea'];

$participantStmt = $pdo->prepare("SELECT * FROM participant_info WHERE member_seq = ? AND year = ?");
$participantStmt->execute([$memberSeq, $year]);
$participant = $participantStmt->fetch(PDO::FETCH_ASSOC);
if ($participant && !empty($participant['cancelled_at'])) {
    $participant = null;
}

$memberChoices = ksers_registration_member_choices();
$savedMember = $participant && isset($participant['ksers_member']) ? (string)$participant['ksers_member'] : '';
$form = [
    'ksers_member' => isset($memberChoices[$savedMember]) ? $savedMember : 'non_member',
    'senior_lifetime' => $participant && !empty($participant['senior_lifetime']) ? 1 : 0,
    'category' => $participant && isset($participant['category']) ? $participant['category'] : '',
    'license_no' => $participant && isset($participant['license_no']) ? $participant['license_no'] : '',
    'dietary' => $participant && isset($participant['dietary']) && isset(ksers_registration_diets()[$participant['dietary']]) ? $participant['dietary'] : 'none',
    'dietary_detail' => $participant && isset($participant['dietary_detail']) ? $participant['dietary_detail'] : '',
    'support_file' => $participant && isset($participant['support_file']) ? $participant['support_file'] : '',
];
$alreadySaved = $participant ? true : false;
$justSaved = $alreadySaved && isset($_GET['saved']) && $_GET['saved'] === '1';
$paidLocked = ksers_registration_locked($pdo, $memberSeq, $year);
$regNo = $alreadySaved ? ('R' . $year . '-' . str_pad((string)$participant['seq'], 4, '0', STR_PAD_LEFT)) : '';
$regWhen = ($alreadySaved && !empty($participant['reg_date'])) ? date('Y-m-d H:i', strtotime($participant['reg_date'])) : '';
$eventSetting = ksers_setting($pdo, $year);
$eventNotice = ksers_text($eventSetting, 'notice_ko', 'notice_en');
$feeBoard = ksers_registration_fee_board($pdo, $year, $memberKorea);
?>

<div class="mb-5">

        <div class="fee-period-note mb-3">
            <?= h($feeBoard['notice_lead']) ?><?php if ($feeBoard['emphasis'] !== '') { ?><strong><?= h($feeBoard['emphasis']) ?></strong><?php } ?><?= h($feeBoard['notice_tail']) ?>
        </div>

        <?php if ($feeBoard['rows']) { ?>
        <div class="card border-0 shadow-sm mb-4 fee-board">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><?= h(t('등록비', 'Registration fees')) ?></h5>
                <div class="table-responsive">
                    <table class="fee-table">
                        <thead>
                            <tr>
                                <th><?= h(t('구분', 'Category')) ?></th>
                                <?php foreach ($feeBoard['headings'] as $period => $heading) { ?>
                                <th class="<?= $feeBoard['current'] === $period ? 'is-current' : '' ?>"><?= h($heading) ?></th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feeBoard['rows'] as $feeRow) { ?>
                            <tr data-fee="<?= h($feeRow['code']) ?>">
                                <th><?= h($feeRow['label']) ?></th>
                                <?php foreach ($feeBoard['headings'] as $period => $heading) {
                                    $chosen = $feeRow['amounts'][$period];
                                ?>
                                <td class="<?= $feeBoard['current'] === $period ? 'is-current' : '' ?>"><?php
                                    if (!$chosen) {
                                        echo '—';
                                    } elseif ((int)$chosen['amount'] === 0) {
                                        echo h(t('무료', 'Free'));
                                    } else {
                                        echo h(ksers_money($chosen['amount'], ksers_fee_currency($chosen)));
                                    }
                                ?></td>
                                <?php } ?>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php } ?>

        <?php if ($justSaved) { ?>
        <div class="alert alert-success border-0 shadow-sm" role="alert">
            <?= h(t($year . '년 참가 등록을 저장했습니다. 같은 계정으로는 올해 등록이 하나뿐입니다.', 'Your ' . $year . ' registration is saved. This account can have only one registration this year.')) ?>
        </div>
        <?php } ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4 bg-white rounded-3">
                <h5 class="fw-bold text-success mb-2">
                    <i class="bi bi-info-circle me-2"></i><?= h(t($year . '년 참가 등록', $year . ' registration')) ?>
                </h5>
                <p class="text-muted small mb-0">
                    <?php if ($paidLocked) { ?>
                    <?= h(t('결제가 진행되어 참가자 정보를 수정할 수 없습니다.', 'Payment is in progress, so participant details cannot be edited.')) ?>
                    <?php } elseif ($alreadySaved) { ?>
                    <?= h(t('이미 저장된 등록입니다. 등록 내용 수정을 누른 뒤 고치고 저장하면, 새 등록이 생기지 않고 이 내용만 바뀝니다.', 'This registration is already saved. Click Edit registration, then save. Saving updates it and does not create another one.')) ?>
                    <?php } else { ?>
                    <?= h(t('참가 정보를 입력한 뒤 저장해 주세요. 한 계정으로 ' . $year . '년 등록은 한 번만 저장됩니다.', 'Enter your registration details, then save. This account can register once for ' . $year . '.')) ?>
                    <?php } ?>
                    <br>
                    <?= h(t('결제는 이 페이지 아래에서 합니다. 결제가 진행되면 참가자 정보는 고칠 수 없습니다.', 'Payment is below on this page. Once payment starts, participant details cannot be edited.')) ?>
                    <?php if ($eventNotice !== '') { ?><br><?= h($eventNotice) ?><?php } ?>
                </p>
            </div>
        </div>

        <form id="regForm" method="post" action="json.php" enctype="multipart/form-data" novalidate data-leave="registration" data-korea="<?= $memberKorea ? '1' : '0' ?>">
            <input type="hidden" name="board" value="participant">
            <input type="hidden" name="mode" value="<?= $alreadySaved ? 'update' : 'input' ?>">
            <fieldset id="regFields" <?= ($paidLocked || $alreadySaved) ? 'disabled' : '' ?><?= $paidLocked ? ' data-paid="1"' : '' ?>>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title m-0 fw-bold"><i class="bi bi-person-fill me-2"></i><?= h(t('참가자 정보', 'Participant details')) ?></h5>
                </div>
                <div class="card-body p-4">
                    <style>
                        .reg-hint.is-invalid { color: #dc3545; }
                    </style>
                    <div class="row g-3">
                        <?php if ($memberKorea) { ?>
                        <div class="col-12">
                            <div class="form-label small fw-bold"><?= h(t('KSERS 회원여부', 'KSERS Membership')) ?> <span class="text-danger">*</span></div>
                            <div class="d-flex flex-wrap gap-3" id="ksersMemberGroup">
                                <?php foreach ($memberChoices as $memberId => $memberLabel) { ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="ksers_member" id="ksersMember<?= h($memberId) ?>" value="<?= h($memberId) ?>"<?= $form['ksers_member'] === $memberId ? ' checked' : '' ?>>
                                    <label class="form-check-label" for="ksersMember<?= h($memberId) ?>"><?= h(t($memberLabel[0], $memberLabel[1])) ?></label>
                                </div>
                                <?php } ?>
                            </div>
                            <div id="ksersMemberHint" class="form-text small reg-hint is-invalid d-none"><?= h(t('KSERS 회원여부를 선택해 주세요.', 'Choose your KSERS membership.')) ?></div>
                        </div>
                        <div class="col-12">
                            <div class="membership-box<?= $form['ksers_member'] === 'lifetime' ? ' is-on' : '' ?>" id="membershipDetail">
                                <span class="membership-box-note small<?= $form['ksers_member'] === 'lifetime' ? ' d-none' : '' ?>" id="membershipBoxNote"><?= h(t('평생 회원 선택 시 활성', 'Active when Lifetime member is selected')) ?></span>
                                <div class="form-check" id="seniorLifetimeWrap">
                                    <input class="form-check-input" type="checkbox" id="seniorLifetime" name="senior_lifetime" value="1"<?= !empty($form['senior_lifetime']) ? ' checked' : '' ?><?= $form['ksers_member'] === 'lifetime' ? '' : ' disabled' ?>>
                                    <label class="form-check-label" for="seniorLifetime"><?= h(t('65세 이상 평생회원', 'Lifetime member aged 65 or older')) ?></label>
                                </div>
                                <div class="form-text small"><?= h(t('체크 시 국내 등록비는 무료입니다. 대상인지는 학회에서 확인합니다.', 'Checking this makes the domestic registration fee free. The society confirms eligibility.')) ?></div>
                            </div>
                        </div>
                        <?php } ?>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold" for="category"><?= h(t('직종', 'Category')) ?> <span class="text-danger">*</span></label>
                            <select class="form-select" id="category" name="category">
                                <option value=""><?= h(t('선택해 주세요', 'Choose')) ?></option>
                                <?php foreach (ksers_registration_categories() as $categoryId => $categoryLabel) { ?>
                                <option value="<?= h($categoryId) ?>" <?= $form['category'] === $categoryId ? 'selected' : '' ?>><?= h(t($categoryLabel[0], $categoryLabel[1])) ?></option>
                                <?php } ?>
                            </select>
                            <div id="categoryHint" class="form-text small reg-hint is-invalid d-none"><?= h(t('직종을 선택해 주세요.', 'Choose a category.')) ?></div>
                        </div>
                        <div class="col-md-6<?= ($memberKorea && ($form['category'] === 'specialist' || $form['category'] === 'resident')) ? '' : ' d-none' ?>" id="licenseBlock">
                            <label class="form-label small fw-bold" for="licenseNo"><?= h(t('의사면허번호', 'Medical License No.')) ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="licenseNo" name="license_no" inputmode="numeric" maxlength="7" pattern="[0-9]{1,7}" value="<?= h($form['license_no']) ?>" placeholder="0000"<?= ($memberKorea && ($form['category'] === 'specialist' || $form['category'] === 'resident')) ? '' : ' disabled' ?>>
                            <div class="form-text small"><?= h(t('연수평점 신고에 사용됩니다 (전문의 자격번호 아님). 의사면허번호가 없거나 평점을 신청하지 않는 경우 0000을 입력해 주세요.', 'Used for CME credit reporting (not a specialist qualification number). If you have no license number or are not applying for credit, enter 0000.')) ?></div>
                            <div id="licenseHint" class="form-text small reg-hint is-invalid d-none"><?= h(t('의사면허번호는 숫자 1~7자리로 입력해 주세요. 평점을 신청하지 않으면 0000입니다.', 'Enter 1 to 7 digits. Use 0000 if you are not applying for CME credit.')) ?></div>
                        </div>
                        <?php
                        $showSupport = !$memberKorea && ($form['category'] === 'student' || $form['category'] === 'nurse');
                        $showDietDetail = $form['dietary'] === 'vegetarian' || $form['dietary'] === 'others';
                        ?>
                        <div class="col-12<?= $showSupport ? '' : ' d-none' ?>" id="supportBlock" data-has-file="<?= $form['support_file'] !== '' ? '1' : '0' ?>">
                            <label class="form-label small fw-bold" for="supportFile"><?= h(t('증빙서류', 'Supporting Document')) ?> <span class="text-danger">*</span></label>
                            <div class="file-picker" id="supportPicker">
                                <span class="file-picker-btn">
                                    <span class="btn btn-outline-secondary"><?= h(t('파일 선택', 'Choose file')) ?></span>
                                    <input id="supportFile" name="support_file" type="file" accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg"<?= $showSupport ? '' : ' disabled' ?>>
                                </span>
                                <span class="file-picker-name" id="supportFileName"><?= h($form['support_file'] !== '' ? $form['support_file'] : t('선택된 파일 없음', 'No file selected')) ?></span>
                            </div>
                            <div class="form-text"><?= h(t('PDF, JPG, 5MB 이하', 'PDF or JPG, up to 5MB')) ?></div>
                            <p class="form-text mb-0"><?= h(t('학생 또는 간호사임을 확인할 수 있는 서류(학생증, 재직증명서 등)를 업로드해 주세요.', 'Please upload a document verifying that you are a student or nurse (e.g., student ID, certificate of employment).')) ?></p>
                            <div id="supportHint" class="form-text small reg-hint is-invalid d-none"><?= h(t('증빙서류를 업로드해 주세요.', 'Upload the supporting document.')) ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold" for="dietary"><?= h(t('식이 제한', 'Special dietary')) ?></label>
                            <select class="form-select" id="dietary" name="dietary">
                                <?php foreach (ksers_registration_diets() as $dietId => $dietLabel) { ?>
                                <option value="<?= h($dietId) ?>" <?= $form['dietary'] === $dietId ? 'selected' : '' ?>><?= h($dietLabel) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-12<?= $showDietDetail ? '' : ' d-none' ?>" id="dietDetailBlock">
                            <label class="form-label small fw-bold" for="dietaryDetail"><?= h(t('상세 내용', 'Details')) ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dietaryDetail" name="dietary_detail" maxlength="200" value="<?= h($form['dietary_detail']) ?>" placeholder="<?= h(t('예: 비건 종류, 알레르기', 'e.g. type of vegetarian diet, allergy')) ?>"<?= $showDietDetail ? '' : ' disabled' ?>>
                            <div class="form-text"><?= h(t('Vegetarian 또는 Others를 선택하신 경우 상세 내용을 입력해 주세요.', 'If you select Vegetarian or Others, please provide details.')) ?></div>
                            <div id="dietDetailHint" class="form-text small reg-hint is-invalid d-none"><?= h(t('상세 내용을 입력해 주세요.', 'Please provide details.')) ?></div>
                        </div>
                    </div>
                </div>
            </div>
            </fieldset>

            <div class="card border-0 shadow-sm bg-light mb-4">
                <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <?php if ($alreadySaved) { ?>
                        <span class="fs-5 fw-bold text-success d-block mb-1"><?= h(t('참가 신청 완료', 'Registration complete')) ?></span>
                        <span class="text-muted"><?= h(t('저장 상태: ', 'Status: ')) ?></span><span class="fw-bold text-success"><?= h(t('저장됨', 'Saved')) ?></span><span class="text-muted"><?= h(t(' · 등록번호 ', ' · Registration No. ')) ?><?= h($regNo) ?><?= $regWhen !== '' ? h(' · ' . $regWhen) : '' ?></span>
                        <?php } else { ?>
                        <span class="text-muted fs-6 d-block"><?= h(t('저장 전', 'Not saved')) ?></span>
                        <span class="fs-5 fw-bold text-dark"><?= h(t('아직 등록 전', 'Not registered')) ?></span>
                        <?php } ?>
                    </div>
                    <?php if ($paidLocked) { ?>
                    <span class="fw-bold"><?= h(t('결제가 진행되어 수정할 수 없습니다.', 'Payment is in progress, so this cannot be edited.')) ?></span>
                    <?php } elseif ($alreadySaved) { ?>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-lg btn-outline-secondary px-5 py-3 fw-bold" id="regEdit"><?= h(t('등록 내용 수정', 'Edit registration')) ?></button>
                        <button type="submit" class="btn btn-lg btn-primary-custom px-5 py-3 fw-bold d-none" id="regSave" disabled>
                            <i class="bi bi-check-lg me-2"></i><?= h(t('등록 내용 저장', 'Save registration')) ?>
                        </button>
                        <button type="button" class="btn btn-lg btn-outline-secondary px-5 py-3 fw-bold d-none" id="regCancel"><?= h(t('취소', 'Cancel')) ?></button>
                    </div>
                    <?php } else { ?>
                    <button type="submit" class="btn btn-lg btn-primary-custom px-5 py-3 fw-bold">
                        <i class="bi bi-check-lg me-2"></i><?= h(t('참가 등록하기', 'Register')) ?>
                    </button>
                    <?php } ?>
                </div>
            </div>
        </form>
</div>

<script>
(function () {
    const form = document.getElementById('regForm');
    if (!form) {
        return;
    }
    const fields = document.getElementById('regFields');
    const editButton = document.getElementById('regEdit');
    const saveButton = document.getElementById('regSave');
    const cancelButton = document.getElementById('regCancel');
    const memberRadios = Array.from(form.querySelectorAll('input[name="ksers_member"]'));
    const membershipDetail = document.getElementById('membershipDetail');
    const category = document.getElementById('category');
    const licenseBlock = document.getElementById('licenseBlock');
    const licenseInput = document.getElementById('licenseNo');
    const supportBlock = document.getElementById('supportBlock');
    const supportFile = document.getElementById('supportFile');
    const supportFileName = document.getElementById('supportFileName');
    const dietary = document.getElementById('dietary');
    const dietDetailBlock = document.getElementById('dietDetailBlock');
    const noFileLabel = <?= json_encode(t('선택된 파일 없음', 'No file selected'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    function selectedMember() {
        const checked = form.querySelector('input[name="ksers_member"]:checked');
        return checked ? checked.value : '';
    }

    function setEnabled(block, on) {
        if (!block) {
            return;
        }
        block.classList.toggle('d-none', !on);
        block.querySelectorAll('input, select').forEach(function (field) {
            field.disabled = !on || (fields && fields.disabled);
        });
    }

    function syncSenior(checkByDefault) {
        const senior = document.getElementById('seniorLifetime');
        const on = selectedMember() === 'lifetime';
        if (membershipDetail) {
            membershipDetail.classList.toggle('is-on', on);
        }
        const note = document.getElementById('membershipBoxNote');
        if (note) {
            note.classList.toggle('d-none', on);
        }
        if (senior) {
            senior.disabled = !on || (fields && fields.disabled);
            if (!on) {
                senior.checked = false;
            } else if (checkByDefault) {
                senior.checked = true;
            }
        }
        syncFeeHighlight();
    }

    function needsSupport() {
        const korea = form.getAttribute('data-korea') === '1';
        return !korea && category && (category.value === 'student' || category.value === 'nurse');
    }

    function syncLicense() {
        const korea = form.getAttribute('data-korea') === '1';
        const needsLicense = korea && category && (category.value === 'specialist' || category.value === 'resident');
        setEnabled(licenseBlock, !!needsLicense);
        setEnabled(supportBlock, needsSupport());
        syncFeeHighlight();
    }

    function syncDiet() {
        const show = dietary && (dietary.value === 'vegetarian' || dietary.value === 'others');
        setEnabled(dietDetailBlock, !!show);
    }

    function matchedFeeCode() {
        const korea = form.getAttribute('data-korea') === '1';
        const senior = document.getElementById('seniorLifetime');
        const categoryValue = category ? category.value : '';
        if (korea && selectedMember() === 'lifetime' && senior && senior.checked) {
            return 'lifetime_65';
        }
        if (categoryValue === 'student' || categoryValue === 'nurse') {
            return 'student_nurse';
        }
        if (categoryValue === 'resident') {
            return 'trainee';
        }
        if (categoryValue === 'specialist') {
            if (!korea) {
                return 'physician';
            }
            if (selectedMember() === 'lifetime') {
                return 'physician_lifetime';
            }
            if (selectedMember() === 'regular') {
                return 'physician_regular';
            }
            return 'physician_non';
        }
        return '';
    }

    function syncFeeHighlight() {
        const code = matchedFeeCode();
        document.querySelectorAll('.fee-table tbody tr').forEach(function (row) {
            row.classList.toggle('is-match', code !== '' && row.getAttribute('data-fee') === code);
        });
    }

    memberRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            syncSenior(radio.value === 'lifetime');
            const hint = document.getElementById('ksersMemberHint');
            if (hint) {
                hint.classList.add('d-none');
            }
            memberRadios.forEach(function (item) {
                item.classList.remove('is-invalid');
            });
        });
    });
    const seniorLifetime = document.getElementById('seniorLifetime');
    if (seniorLifetime) {
        seniorLifetime.addEventListener('change', syncFeeHighlight);
    }
    if (category) {
        category.addEventListener('change', function () {
            syncLicense();
            document.getElementById('categoryHint').classList.add('d-none');
            category.classList.remove('is-invalid');
        });
    }
    if (supportFile && supportFileName) {
        supportFile.addEventListener('change', function () {
            const file = supportFile.files && supportFile.files[0] ? supportFile.files[0] : null;
            if (file) {
                const ext = file.name.split('.').pop().toLowerCase();
                if ((ext !== 'pdf' && ext !== 'jpg' && ext !== 'jpeg') || file.size > 5 * 1024 * 1024) {
                    supportFile.value = '';
                    window.alert(<?= json_encode(t('PDF 또는 JPG 파일만, 5MB 이하로 업로드해 주세요.', 'Upload a PDF or JPG file of 5MB or less.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
                }
            }
            const picked = supportFile.files && supportFile.files[0] ? supportFile.files[0].name : '';
            const current = supportBlock && supportBlock.getAttribute('data-has-file') === '1' ? supportFileName.getAttribute('data-current') : '';
            supportFileName.textContent = picked !== '' ? picked : (current || noFileLabel);
            const hint = document.getElementById('supportHint');
            if (hint) {
                hint.classList.add('d-none');
            }
            const picker = document.getElementById('supportPicker');
            if (picker) {
                picker.classList.remove('is-invalid');
            }
        });
    }
    if (dietary) {
        dietary.addEventListener('change', syncDiet);
    }
    const dietaryDetail = document.getElementById('dietaryDetail');
    if (dietaryDetail) {
        dietaryDetail.addEventListener('input', function () {
            dietaryDetail.classList.remove('is-invalid');
            const hint = document.getElementById('dietDetailHint');
            if (hint) {
                hint.classList.add('d-none');
            }
        });
    }
    if (licenseInput) {
        licenseInput.addEventListener('input', function () {
            const digits = licenseInput.value.replace(/\D/g, '').slice(0, 7);
            if (licenseInput.value !== digits) {
                licenseInput.value = digits;
            }
            document.getElementById('licenseHint').classList.add('d-none');
            licenseInput.classList.remove('is-invalid');
        });
    }
    function clearRegHints() {
        form.querySelectorAll('.is-invalid').forEach(function (field) {
            field.classList.remove('is-invalid');
        });
        form.querySelectorAll('.reg-hint').forEach(function (hint) {
            hint.classList.add('d-none');
        });
    }

    function setRegEditing(on) {
        if (!fields || fields.getAttribute('data-paid') === '1') {
            return;
        }
        fields.disabled = !on;
        if (editButton) {
            editButton.classList.toggle('d-none', on);
        }
        if (saveButton) {
            saveButton.classList.toggle('d-none', !on);
            saveButton.disabled = !on;
        }
        if (cancelButton) {
            cancelButton.classList.toggle('d-none', !on);
        }
        syncSenior(false);
        syncLicense();
        syncDiet();
    }

    if (editButton) {
        editButton.addEventListener('click', function () {
            setRegEditing(true);
        });
    }
    if (cancelButton) {
        cancelButton.addEventListener('click', function () {
            form.reset();
            clearRegHints();
            setRegEditing(false);
        });
    }
    if (supportFileName && supportBlock && supportBlock.getAttribute('data-has-file') === '1') {
        supportFileName.setAttribute('data-current', supportFileName.textContent);
    }
    syncSenior(false);
    syncLicense();
    syncDiet();

    form.addEventListener('submit', function (event) {
        if (fields && fields.disabled) {
            event.preventDefault();
            return;
        }
        let failed = false;
        if (memberRadios.length && selectedMember() === '') {
            memberRadios.forEach(function (radio) {
                radio.classList.add('is-invalid');
            });
            document.getElementById('ksersMemberHint').classList.remove('d-none');
            failed = true;
        }
        if (!category || category.value === '') {
            if (category) {
                category.classList.add('is-invalid');
            }
            document.getElementById('categoryHint').classList.remove('d-none');
            failed = true;
        }
        const needsLicense = form.getAttribute('data-korea') === '1' && category && (category.value === 'specialist' || category.value === 'resident');
        if (needsLicense && licenseInput && !/^[0-9]{1,7}$/.test(licenseInput.value.trim())) {
            licenseInput.classList.add('is-invalid');
            document.getElementById('licenseHint').classList.remove('d-none');
            failed = true;
        }
        const hasSupportFile = supportBlock && supportBlock.getAttribute('data-has-file') === '1';
        const pickedSupport = supportFile && supportFile.files && supportFile.files[0];
        if (needsSupport() && !hasSupportFile && !pickedSupport) {
            const picker = document.getElementById('supportPicker');
            if (picker) {
                picker.classList.add('is-invalid');
            }
            document.getElementById('supportHint').classList.remove('d-none');
            failed = true;
        }
        if (dietary && (dietary.value === 'vegetarian' || dietary.value === 'others') && dietaryDetail && dietaryDetail.value.trim() === '') {
            dietaryDetail.classList.add('is-invalid');
            document.getElementById('dietDetailHint').classList.remove('d-none');
            failed = true;
        }
        if (failed) {
            event.preventDefault();
        }
    });
})();
</script>
<?php include 'lib/member_payment.php'; ?>
<?php
$ksersLeaveUnpaid = false;
if ($alreadySaved && !$paidLocked) {
    $leaveDue = 0;
    foreach (ksers_payable_items($pdo, $year, $memberSeq) as $leaveItem) {
        $leaveDue += (int)$leaveItem['amount'];
    }
    $leavePendingStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM payment_order WHERE member_seq = ? AND year = ? AND status = 'pending' AND amount > 0"
    );
    $leavePendingStmt->execute([$memberSeq, $year]);
    $ksersLeaveUnpaid = ($leaveDue + (int)$leavePendingStmt->fetchColumn()) > 0;
}
include 'lib/footer.php';
?>
