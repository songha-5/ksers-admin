<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
$homeNext = ksers_homepage_return(isset($_GET['next']) ? $_GET['next'] : '');
$joinYear = ksers_site_year($pdo);
$joinDoneEmail = '';
if (isset($_GET['done']) && (string)$_GET['done'] === '1' && !empty($_SESSION['join_done_email'])) {
    $joinDoneEmail = trim((string)$_SESSION['join_done_email']);
} else {
    unset($_SESSION['join_done_email']);
}

// 기존 join.php 및 menu.php에서 사용하던 변수와 DB 쿼리
$page_title = t('회원가입 - ' . $joinYear . ' 국제학술대회', 'Sign Up - ' . $joinYear . ' International Conference');
$page_heading = t('회원가입', 'Sign Up');
$current_page = basename($_SERVER['PHP_SELF']);

$country_list = ksers_country_rows($pdo);
?>
<!DOCTYPE html>
<html lang="<?= h(ksers_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(isset($page_title) ? $page_title : ('KSERS ' . (int)$joinYear)); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/service/ksers.css" rel="stylesheet">
    <style>
        body { display: flex; flex-direction: column; min-height: 100vh; }
        main.main-content { flex: 1; }
        .form-text.is-invalid { color: #dc3545; }
        .join-dup,
        .join-ok {
            margin: 0.25rem 0 0;
            font-size: 0.875em;
        }
        .join-dup { color: #dc3545; }
        .input-group.is-invalid > .input-group-text,
        .input-group.is-invalid > .form-control {
            border-color: #dc3545;
        }
        .input-group.is-invalid > .form-control {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        .join-ok { color: #15803d; }
        #joinEmailCheck { position: relative; min-width: 6.5rem; }
        #joinEmailCheck.is-loading .join-check-label { visibility: hidden; }
        #joinEmailCheck .spinner-border {
            position: absolute;
            left: 50%;
            top: 50%;
            margin: -0.5rem 0 0 -0.5rem;
        }
        .join-consent-item {
            display: flex;
            align-items: flex-start;
            gap: 0.45rem;
        }
        .join-consent-item .form-check-input {
            float: none;
            position: static;
            margin: 0.2rem 0 0;
            flex: 0 0 auto;
        }
        .join-consent-badge {
            flex: 0 0 5.6rem;
            width: 5.6rem;
            margin: 0;
            font-size: 0.875rem;
            font-weight: 700;
            line-height: 1.5;
            white-space: nowrap;
        }
        .join-consent-body { flex: 1; min-width: 0; }
        .join-consent-head { line-height: 1.5; }
        .join-terms-link {
            padding: 0;
            border: 0;
            background: none;
            color: #09428f;
            font-size: 0.875rem;
            line-height: 1.5;
            text-decoration: underline;
            cursor: pointer;
        }
        .join-consent-note {
            margin: 0.2rem 0 0;
            color: #6b7280;
            font-size: 0.75rem;
            line-height: 1.5;
        }
        .join-done { text-align: center; padding: 1.5rem 0 0.5rem; }
        .join-done-title { font-size: 1.35rem; font-weight: 700; color: #1a1a1a; margin: 0 0 0.5rem; }
        .join-done-lead { color: #6b7280; font-size: 0.95rem; margin: 0 0 1.75rem; }
        .join-done-box {
            display: inline-block;
            min-width: min(100%, 28rem);
            margin: 0 auto 1.25rem;
            padding: 1.15rem 1.5rem;
            border: 1px solid #d7dee8;
            border-radius: 8px;
            background: #f8fafc;
            color: #1a1a1a;
            font-size: 1rem;
            line-height: 1.5;
        }
        .join-done-note {
            margin: 0 0 1.75rem;
            color: #6b7280;
            font-size: 0.875rem;
            line-height: 1.6;
        }
        .join-done-login {
            display: inline-block;
            min-width: min(100%, 16rem);
            padding: 0.85rem 2.5rem;
            font-size: 1.05rem;
        }
        .join-toast {
            position: fixed;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1080;
            width: min(420px, calc(100% - 24px));
            padding: 14px 16px;
            border-radius: 12px;
            background: #fff7ed;
            border: 1px solid #f59e0b;
            color: #7c2d12;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.18);
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/lib/site_header.php'; ?>

<!-- 본문 메인 컨테이너 시작 -->
<main class="main-content site-page">
    <div class="d-flex justify-content-end mb-2">
        <?php $langBtnClass = 'btn btn-sm ksers-lang'; include __DIR__ . '/lib/lang_buttons.php'; ?>
    </div>

    <!-- 페이지 서브 헤딩 -->
    <?php if (isset($page_heading)): ?>
    <div class="d-flex justify-content-between align-items-center gap-3 pb-2 mb-4 border-bottom">
        <h2 class="h3 fw-bold m-0 text-dark"><?php echo h($page_heading); ?></h2>
        <a class="site-back" href="/service/login.php?year=<?= (int)$joinYear ?><?= $homeNext !== '' ? ('&next=' . rawurlencode($homeNext)) : '' ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            <?= h(t('뒤로가기', 'Back')) ?>
        </a>
    </div>
    <?php endif; ?>

    <div class="mb-5">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4 p-md-5">

                    <?php if ($joinDoneEmail !== '') { ?>
                    <div class="join-done">
                        <h5 class="join-done-title"><?= h(t('회원가입이 완료되었습니다', 'Your account has been created')) ?></h5>
                        <p class="join-done-lead"><?= h(t('가입 정보는 입력한 이메일로 발송됩니다.', 'Your sign-up details are sent to the e-mail address you entered.')) ?></p>
                        <div class="join-done-box"><?= h(t('아이디 (이메일)', 'ID (E-mail)')) ?> : <?= h($joinDoneEmail) ?></div>
                        <p class="join-done-note">* <?= h(t('가입 완료 메일이 오지 않으면 사무국(ksers2027@ksers.kr)에 문의해 주세요.', 'If the sign-up e-mail does not arrive, please contact the Secretariat (ksers2027@ksers.kr).')) ?></p>
                        <a class="btn btn-primary-custom fw-bold join-done-login" href="/service/login.php?year=<?= (int)$joinYear ?><?= $homeNext !== '' ? ('&next=' . rawurlencode($homeNext)) : '' ?>"><?= h(t('로그인', 'Log in')) ?></a>
                    </div>
                    <?php } else { ?>
                    <div class="mb-4 pb-2 border-bottom">
                        <h5 class="fw-bold text-dark mb-1"><?= h(t('계정 정보 입력', 'Account details')) ?></h5>
                        <p class="text-muted small mb-0"><?= h(t('학술대회 참가 등록 및 초록 제출에 사용될 회원 정보를 입력해 주세요.', 'Enter the member details used for registration and abstract submission.')) ?></p>
                    </div>

                    <form id="joinForm" action="json.php" method="post" novalidate>
                        <input type="hidden" name="board" value="member">
                        <input type="hidden" name="mode" value="input">
                        <input type="hidden" name="congress_year" value="<?= (int)$joinYear ?>">
                        <?php if ($homeNext !== '') { ?>
                        <input type="hidden" name="next" value="<?= h($homeNext) ?>">
                        <?php } ?>

                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <label for="country" class="form-label small fw-bold"><?= h(t('국가', 'Country')) ?> <span class="text-danger">*</span></label>
                                <select class="form-select" id="country" name="country" required>
                                    <option value=""><?= h(t('국가를 선택해 주세요', 'Choose a country')) ?></option>
                                    <?php foreach($country_list as $c) {
                                        $countryProfile = ksers_country_profile($c);
                                    ?>
                                    <option value="<?=h($c['iso'])?>" data-korea="<?= $countryProfile['korea'] ? '1' : '0' ?>" data-dial="<?= h($countryProfile['dial']) ?>" data-mobile-placeholder="<?= h($countryProfile['mobile_placeholder']) ?>" data-telephone-placeholder="<?= h($countryProfile['telephone_placeholder']) ?>"><?=h(code_name($c))?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="joinEmail" class="form-label small fw-bold"><?= h(t('이메일 주소 (ID)', 'Email address (ID)')) ?> <span class="text-danger">*</span></label>
                                <div class="d-flex gap-2">
                                    <input type="text" class="form-control flex-grow-1" id="joinEmail" name="email" placeholder="example@domain.com" required>
                                    <button class="btn btn-outline-primary flex-shrink-0" id="joinEmailCheck" type="button">
                                        <span class="join-check-label"><?= h(t('중복확인', 'Check')) ?></span>
                                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                    </button>
                                </div>
                                <div id="joinEmailHint" class="form-text small"><?= h(t('유효한 이메일 주소를 입력해 주십시오. (해당 이메일 주소는 본인 확인용이며 연락 창구로도 사용됩니다.)', 'Please enter a valid e-mail address. It will be used for identity verification and as your contact point.')) ?></div>
                                <p id="joinEmailDup" class="join-dup d-none"><?= h(t('중복된 이메일입니다.', 'This email is already in use.')) ?></p>
                                <p id="joinEmailOk" class="join-ok d-none"><?= h(t('이메일 중복검사가 완료되었습니다.', 'The email check is complete.')) ?></p>
                            </div>

                            <div class="col-md-6">
                                <label for="joinPassword" class="form-label small fw-bold"><?= h(t('비밀번호', 'Password')) ?> <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="joinPassword" name="password" placeholder="<?= h(t('8자 이상 입력', 'At least 8 characters')) ?>" required>
                                <div id="joinPasswordHint" class="form-text small"><?= h(t('8자 이상, 특수문자를 포함해 입력해 주세요.', 'At least 8 characters, including a special character.')) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label for="joinPasswordConfirm" class="form-label small fw-bold"><?= h(t('비밀번호 확인', 'Confirm password')) ?> <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="joinPasswordConfirm" name="password_confirm" placeholder="<?= h(t('비밀번호 재입력', 'Re-enter password')) ?>" required>
                                <div id="joinPasswordMatch" class="form-text small is-invalid d-none"><?= h(t('비밀번호가 일치하지 않습니다.', 'The passwords do not match.')) ?></div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold text-success mb-3"><i class="bi bi-person-badge me-2"></i><?= h(t('개인정보', 'Personal information')) ?></h6>
                            <div class="row g-3">
                                <div class="col-12 d-none" id="joinNameKo">
                                    <label for="nameKo" class="form-label small fw-bold"><?= h(t('성명(국문)', 'Name (Korean)')) ?> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nameKo" name="name_ko" placeholder="<?= h(t('홍길동', 'Hong Gildong')) ?>" disabled>
                                </div>
                                <div class="col-12 d-none" id="joinNameEn">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="firstNameEn" class="form-label small fw-bold"><?= h(t('이름(영문)', 'First Name')) ?> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="firstNameEn" name="first_name_eng" placeholder="Gildong" disabled>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="lastNameEn" class="form-label small fw-bold"><?= h(t('성(영문)', 'Family (Last) Name')) ?> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="lastNameEn" name="last_name_eng" placeholder="Hong" disabled>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-label small fw-bold"><?= h(t('호칭', 'Title')) ?></div>
                                    <div class="d-flex flex-wrap gap-3">
                                        <?php foreach (['' => 'None', 'Prof.' => 'Prof.', 'Dr.' => 'Dr.', 'Mr.' => 'Mr.', 'Ms.' => 'Ms.'] as $salutationValue => $salutationLabel) {
                                            $salutationId = 'salutation' . ($salutationValue === '' ? 'None' : preg_replace('/[^A-Za-z]/', '', $salutationValue));
                                        ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="salutation" id="<?= h($salutationId) ?>" value="<?= h($salutationValue) ?>"<?= $salutationValue === '' ? ' checked' : '' ?>>
                                            <label class="form-check-label" for="<?= h($salutationId) ?>"><?= h($salutationLabel) ?></label>
                                        </div>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="mobile" class="form-label small fw-bold"><?= h(t('휴대전화', 'Cell Phone')) ?> <span class="text-danger">*</span></label>
                                    <div class="input-group" id="mobileGroup">
                                        <span class="input-group-text" id="mobileDial">+</span>
                                        <input type="tel" class="form-control" id="mobile" name="mobile" placeholder="010-1111-1111" required>
                                    </div>
                                    <div id="joinMobileFormat" class="form-text small is-invalid d-none"><?= h(t('하이픈(-) 2개를 넣어 입력해 주세요.', 'Enter two hyphens (-).')) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="telephone" class="form-label small fw-bold"><?= h(t('유선전화', 'Telephone')) ?></label>
                                    <div class="input-group" id="telephoneGroup">
                                        <span class="input-group-text" id="telephoneDial">+</span>
                                        <input type="tel" class="form-control" id="telephone" name="telephone" placeholder="02-1234-5678">
                                    </div>
                                    <div id="joinTelephoneFormat" class="form-text small is-invalid d-none"><?= h(t('하이픈(-) 2개를 넣어 입력해 주세요.', 'Enter two hyphens (-).')) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold text-success mb-3"><i class="bi bi-building me-2"></i><?= h(t('소속 정보', 'Affiliation information')) ?></h6>
                            <div class="row g-3">
                                <div class="col-12 d-none" id="joinAffiliationType">
                                    <label for="affiliationType" class="form-label small fw-bold"><?= h(t('소속 선택', 'Affiliation')) ?> <span class="text-danger">*</span></label>
                                    <select class="form-select" id="affiliationType" name="affiliation_type" disabled>
                                        <option value=""><?= h(t('소속을 선택해 주세요', 'Choose an affiliation')) ?></option>
                                        <?php foreach (ksers_affiliation_types() as $typeId => $typeLabel) { ?>
                                        <option value="<?= h($typeId) ?>"><?= h(t($typeLabel[0], $typeLabel[1])) ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-12 d-none" id="joinAffiliationKo">
                                    <label for="affiliationKo" class="form-label small fw-bold"><?= h(t('소속(국문)', 'Affiliation - In Korean')) ?> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="affiliationKo" name="affiliation_ko" placeholder="<?= h(t('서울대학교병원', 'Seoul National University Hospital')) ?>" disabled>
                                </div>
                                <div class="col-12">
                                    <label for="affiliationEn" class="form-label small fw-bold"><?= h(t('소속(영문)', 'Affiliation - In English')) ?> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="affiliationEn" name="affiliation_eng" placeholder="Seoul National University Hospital" required>
                                </div>
                                <div class="col-12">
                                    <label for="department" class="form-label small fw-bold"><?= h(t('진료과/부서', 'Department')) ?></label>
                                    <input type="text" class="form-control" id="department" name="department" maxlength="45" placeholder="<?= h(t('소화기내과', 'Gastroenterology')) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="card bg-light border-0 p-3 mb-2" id="joinConsent">
                            <div class="join-consent-item mb-3">
                                <input class="form-check-input" type="checkbox" id="termsAgree" name="terms_agree" value="1">
                                <label class="join-consent-badge text-danger" for="termsAgree"><?= h(t('[필수]', '[Required]')) ?></label>
                                <div class="join-consent-body">
                                    <div class="join-consent-head">
                                        <label class="form-check-label small text-danger" for="termsAgree"><?= h(t('이용약관 동의', 'Terms of Use')) ?></label>
                                        <button class="join-terms-link ms-2" type="button" data-terms="terms"><?= h(t('약관보기', 'View terms')) ?></button>
                                    </div>
                                </div>
                            </div>
                            <div class="join-consent-item mb-2">
                                <input class="form-check-input" type="checkbox" id="privacyAgree" name="privacy_agree" value="1">
                                <label class="join-consent-badge text-danger" for="privacyAgree"><?= h(t('[필수]', '[Required]')) ?></label>
                                <div class="join-consent-body">
                                    <div class="join-consent-head">
                                        <label class="form-check-label small text-danger" for="privacyAgree"><?= h(t('개인정보 수집·이용 동의', 'Privacy consent')) ?></label>
                                        <button class="join-terms-link ms-2" type="button" data-terms="privacy"><?= h(t('약관보기', 'View terms')) ?></button>
                                    </div>
                                    <p class="join-consent-note"><?= h(t('수집 목적', 'Purpose')) ?>: <?= h(t('회원 관리, 초록 접수·심사, 참가 등록·결제, 학술대회 운영 및 공지', 'Account management, abstract submission and review, registration and payment, congress operation and notices')) ?><br><?= h(t('수집 항목', 'Items')) ?>: <?= h(t('(필수) 국가, 이메일, 비밀번호, 영문 성명, 소속(영문), 휴대전화 / 국내: 국문 성명, 소속 구분, 소속(국문) (선택) 호칭, 유선전화, 진료과/부서', '(Required) Country, e-mail, password, name in English, affiliation in English, mobile number / Domestic: name in Korean, affiliation type, affiliation in Korean (Optional) Title, telephone, department')) ?><br><?= h(t('보유기간', 'Retention')) ?>: <?= h(t('KSERS 2027 학술대회 종료 후 3개월 이내 파기 (법령에 따라 보존이 필요한 정보는 해당 기간)', 'Destroyed within 3 months after the end of KSERS 2027 (information that must be kept under applicable laws is kept for the required period)')) ?><br><?= h(t('동의 거부 권리', 'Right to refuse')) ?>: <?= h(t('동의를 거부할 수 있으나, 거부하면 회원가입과 서비스 이용이 제한됩니다.', 'You may refuse to consent, but you will not be able to create an account or use the Services.')) ?></p>
                                </div>
                            </div>
                            <div class="join-consent-item">
                                <input class="form-check-input" type="checkbox" id="emailAgree" name="email_agree" value="1">
                                <label class="join-consent-badge" for="emailAgree"><?= h(t('[선택]', '[Optional]')) ?></label>
                                <div class="join-consent-body">
                                    <div class="join-consent-head">
                                        <label class="form-check-label small" for="emailAgree"><?= h(t('이메일 수신 동의', 'E-mail consent')) ?></label>
                                    </div>
                                    <p class="join-consent-note"><?= h(t('동의하지 않아도 회원가입과 서비스 이용에는 제한이 없으며, 마이페이지에서 언제든지 변경할 수 있습니다. 학술대회 운영에 필요한 필수 안내 메일은 수신 동의와 관계없이 발송됩니다.', 'You can still sign up and use the Services without agreeing, and you can change this at any time on My Page. Essential notices needed to run the congress are sent whether or not you agree.')) ?></p>
                                </div>
                            </div>
                        </div>
                        <div id="joinConsentHint" class="form-text small is-invalid d-none mb-4"><?= h(t('필수사항을 체크해 주세요.', 'Please check the required items.')) ?></div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary-custom py-2.5 fw-bold fs-6">
                                <?= h(t('회원가입 완료', 'Create account')) ?>
                            </button>
                        </div>

                    </form>
                    <?php } ?>

                </div>
            </div>
    </div>

    <?php if ($joinDoneEmail === '') { ?>
    <script type="module">
        import { z } from "https://cdn.jsdelivr.net/npm/zod@3.24.2/+esm";
        const joinCountry = document.getElementById('country');
        const joinNameKo = document.getElementById('joinNameKo');
        const joinNameEn = document.getElementById('joinNameEn');
        const joinNameKoInput = document.getElementById('nameKo');
        const joinFirstName = document.getElementById('firstNameEn');
        const joinLastName = document.getElementById('lastNameEn');
        const joinMobile = document.getElementById('mobile');
        const joinTelephone = document.getElementById('telephone');
        const joinMobileFormat = document.getElementById('joinMobileFormat');
        const joinTelephoneFormat = document.getElementById('joinTelephoneFormat');
        const phoneHyphenMessage = <?= json_encode(t('하이픈(-) 2개를 넣어 입력해 주세요.', 'Enter two hyphens (-).'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const joinForm = document.getElementById('joinForm');
        const joinEmail = document.getElementById('joinEmail');
        const joinEmailHint = document.getElementById('joinEmailHint');
        const joinEmailDup = document.getElementById('joinEmailDup');
        const joinEmailOk = document.getElementById('joinEmailOk');
        const joinEmailCheck = document.getElementById('joinEmailCheck');
        const joinPassword = document.getElementById('joinPassword');
        const joinPasswordHint = document.getElementById('joinPasswordHint');
        const joinPasswordConfirm = document.getElementById('joinPasswordConfirm');
        const joinPasswordMatch = document.getElementById('joinPasswordMatch');
        const joinCheckMessage = <?= json_encode(t('이메일 중복확인을 해 주세요.', 'Check whether this email is already registered.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const joinReviewMessage = <?= json_encode(t('입력폼을 다시 확인해 주세요.', 'Please check the form again.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        let checkedEmail = '';

        function passwordOk(value) {
            return value.length >= 8 && /[^0-9A-Za-z가-힣ㄱ-ㅎㅏ-ㅣ\s]/.test(value);
        }

        function showToasts(messages) {
            document.querySelectorAll('.join-toast').forEach(function (toast) { toast.remove(); });
            messages.forEach(function (message, index) {
                const toast = document.createElement('div');
                toast.className = 'join-toast';
                toast.setAttribute('role', 'alert');
                toast.style.top = (16 + index * 72) + 'px';
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(function () { toast.remove(); }, 4000);
            });
        }

        function setEmailCheckLoading(loading) {
            joinEmailCheck.disabled = loading;
            joinEmailCheck.classList.toggle('is-loading', loading);
            joinEmailCheck.querySelector('.spinner-border').classList.toggle('d-none', !loading);
            joinEmailCheck.setAttribute('aria-busy', loading ? 'true' : 'false');
        }

        function setNameField(field, active) {
            field.disabled = !active;
            field.required = active;
            if (!active) {
                field.classList.remove('is-invalid');
            }
        }

        function syncJoinCountry() {
            const option = joinCountry.options[joinCountry.selectedIndex];
            const chosen = joinCountry.value !== '';
            const korea = chosen && option.dataset.korea === '1';
            const dial = chosen && option.dataset.dial ? option.dataset.dial : '+';
            joinNameKo.classList.toggle('d-none', !korea);
            joinNameEn.classList.toggle('d-none', !chosen || korea);
            setNameField(joinNameKoInput, korea);
            setNameField(joinFirstName, chosen && !korea);
            setNameField(joinLastName, chosen && !korea);
            document.getElementById('joinAffiliationType').classList.toggle('d-none', !korea);
            document.getElementById('joinAffiliationKo').classList.toggle('d-none', !korea);
            setNameField(document.getElementById('affiliationType'), korea);
            setNameField(document.getElementById('affiliationKo'), korea);
            document.getElementById('mobileDial').textContent = dial;
            document.getElementById('telephoneDial').textContent = dial;
            joinMobile.placeholder = chosen && option.dataset.mobilePlaceholder ? option.dataset.mobilePlaceholder : '010-1111-1111';
            joinTelephone.placeholder = chosen && option.dataset.telephonePlaceholder ? option.dataset.telephonePlaceholder : '02-1234-5678';
        }

        function resetEmailCheck() {
            checkedEmail = '';
            joinEmailDup.classList.add('d-none');
            joinEmailOk.classList.add('d-none');
        }

        joinEmail.addEventListener('input', function () {
            resetEmailCheck();
            joinEmailHint.classList.remove('is-invalid');
            joinEmail.classList.remove('is-invalid');
        });

        joinEmailCheck.addEventListener('click', function () {
            const email = joinEmail.value.trim();
            resetEmailCheck();
            if (!email.includes('@')) {
                joinEmailHint.classList.add('is-invalid');
                joinEmail.classList.add('is-invalid');
                return;
            }
            joinEmailHint.classList.remove('is-invalid');
            joinEmail.classList.remove('is-invalid');
            setEmailCheckLoading(true);
            const body = new URLSearchParams();
            body.set('email', email);
            body.set('congress_year', joinForm.querySelector('[name="congress_year"]').value);
            fetch('email_check.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                if (!data || data.ok !== true) {
                    joinEmailHint.classList.add('is-invalid');
                    joinEmail.classList.add('is-invalid');
                    return;
                }
                if (data.duplicate) {
                    joinEmailDup.classList.remove('d-none');
                    joinEmail.classList.add('is-invalid');
                    return;
                }
                checkedEmail = email;
                joinEmailOk.classList.remove('d-none');
            }).catch(function () {
                showToasts([joinReviewMessage]);
            }).finally(function () {
                setEmailCheckLoading(false);
            });
        });

        function setPhoneInvalid(field, group, messageEl, invalid) {
            field.classList.toggle('is-invalid', invalid);
            group.classList.toggle('is-invalid', invalid);
            messageEl.classList.toggle('d-none', !invalid);
        }

        function checkPhones() {
            const hasTwoHyphens = function (value) { return (value.match(/-/g) || []).length === 2; };
            const parsed = z.object({
                mobile: z.string().trim().min(1, phoneHyphenMessage).refine(hasTwoHyphens, { message: phoneHyphenMessage }),
                telephone: z.string().trim().refine(function (value) {
                    return value === '' || hasTwoHyphens(value);
                }, { message: phoneHyphenMessage }),
            }).safeParse({
                mobile: joinMobile.value,
                telephone: joinTelephone.value,
            });
            const mobileIssue = parsed.success ? undefined : parsed.error.issues.find(function (issue) { return issue.path[0] === 'mobile'; });
            const telephoneIssue = parsed.success ? undefined : parsed.error.issues.find(function (issue) { return issue.path[0] === 'telephone'; });
            setPhoneInvalid(joinMobile, document.getElementById('mobileGroup'), joinMobileFormat, Boolean(mobileIssue));
            setPhoneInvalid(joinTelephone, document.getElementById('telephoneGroup'), joinTelephoneFormat, Boolean(telephoneIssue));
            return parsed.success;
        }

        joinMobile.addEventListener('input', function () {
            setPhoneInvalid(joinMobile, document.getElementById('mobileGroup'), joinMobileFormat, false);
        });
        joinTelephone.addEventListener('input', function () {
            setPhoneInvalid(joinTelephone, document.getElementById('telephoneGroup'), joinTelephoneFormat, false);
        });

        ['termsAgree', 'privacyAgree'].forEach(function (id) {
            document.getElementById(id).addEventListener('change', function () {
                const termsChecked = document.getElementById('termsAgree').checked;
                const privacyChecked = document.getElementById('privacyAgree').checked;
                document.getElementById('termsAgree').classList.toggle('is-invalid', !termsChecked && !document.getElementById('joinConsentHint').classList.contains('d-none'));
                document.getElementById('privacyAgree').classList.toggle('is-invalid', !privacyChecked && !document.getElementById('joinConsentHint').classList.contains('d-none'));
                if (termsChecked && privacyChecked) {
                    document.getElementById('joinConsentHint').classList.add('d-none');
                    document.getElementById('termsAgree').classList.remove('is-invalid');
                    document.getElementById('privacyAgree').classList.remove('is-invalid');
                }
            });
        });

        document.querySelectorAll('[data-terms]').forEach(function (button) {
            button.addEventListener('click', function () {
                const lang = document.documentElement.lang === 'en' ? 'en' : 'ko';
                const width = 768;
                const height = 860;
                const left = Math.max(0, Math.round((window.screen.width - width) / 2));
                const top = Math.max(0, Math.round((window.screen.height - height) / 2));
                window.open(
                    'terms.php?lang=' + lang + '&part=' + encodeURIComponent(button.getAttribute('data-terms')),
                    'ksersTerms',
                    'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes'
                );
            });
        });

        joinCountry.addEventListener('change', syncJoinCountry);
        syncJoinCountry();

        joinForm.addEventListener('submit', function (event) {
            const required = joinForm.querySelectorAll('[required]');
            const email = joinEmail.value.trim();
            const password = joinPassword.value;
            const confirm = joinPasswordConfirm.value;
            const messages = [];
            let failed = false;

            required.forEach(function (field) {
                if (field.disabled || field.closest('.d-none')) {
                    field.classList.remove('is-invalid');
                    return;
                }
                field.classList.remove('is-invalid');
                const empty = field.type === 'checkbox' ? !field.checked : String(field.value).trim() === '';
                if (empty) {
                    field.classList.add('is-invalid');
                    failed = true;
                }
            });
            joinEmailHint.classList.remove('is-invalid');
            joinPasswordHint.classList.remove('is-invalid');
            joinPasswordMatch.classList.add('d-none');

            if (email !== '' && !email.includes('@')) {
                joinEmailHint.classList.add('is-invalid');
                joinEmail.classList.add('is-invalid');
                failed = true;
            }
            if (!joinEmailDup.classList.contains('d-none')) {
                joinEmail.classList.add('is-invalid');
                failed = true;
            } else if (checkedEmail !== email) {
                messages.push(joinCheckMessage);
                failed = true;
            }
            if (!passwordOk(password)) {
                joinPasswordHint.classList.add('is-invalid');
                joinPassword.classList.add('is-invalid');
                failed = true;
            }
            if (password !== '' && confirm !== '' && password !== confirm) {
                joinPasswordMatch.classList.remove('d-none');
                joinPasswordConfirm.classList.add('is-invalid');
                failed = true;
            }
            if (!checkPhones()) {
                failed = true;
            }
            const consentHint = document.getElementById('joinConsentHint');
            const termsChecked = document.getElementById('termsAgree').checked;
            const privacyChecked = document.getElementById('privacyAgree').checked;
            document.getElementById('termsAgree').classList.toggle('is-invalid', !termsChecked);
            document.getElementById('privacyAgree').classList.toggle('is-invalid', !privacyChecked);
            consentHint.classList.toggle('d-none', termsChecked && privacyChecked);
            if (!termsChecked || !privacyChecked) {
                failed = true;
            }
            if (!failed) {
                return;
            }
            event.preventDefault();
            messages.push(joinReviewMessage);
            showToasts(messages);
        });
    </script>
    <?php } ?>

</main>

<?php include __DIR__.'/lib/site_footer.php'; ?>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>