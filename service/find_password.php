<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
require_once __DIR__ . '/../ksers_password.php';

$postedNext = isset($_POST['next']) ? $_POST['next'] : (isset($_GET['next']) ? $_GET['next'] : '');
$homeNext = ksers_homepage_return($postedNext);
$findYear = ksers_site_year($pdo);
$loginHref = '/service/login.php?year=' . (int)$findYear . ($homeNext !== '' ? ('&next=' . rawurlencode($homeNext)) : '');

$values = [
    'country' => '',
    'email' => '',
    'name_ko' => '',
    'first_name_eng' => '',
    'last_name_eng' => '',
];
$errors = [
    'country' => '',
    'email' => '',
    'name_ko' => '',
    'first_name_eng' => '',
    'last_name_eng' => '',
];
$sent = isset($_GET['sent']) && $_GET['sent'] === '1' && !empty($_SESSION['password_find_sent']);
$mismatch = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = false;
    unset($_SESSION['password_find_sent']);
    $values['country'] = isset($_POST['country']) ? strtoupper(trim((string)$_POST['country'])) : '';
    $values['email'] = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $values['name_ko'] = isset($_POST['name_ko']) ? trim((string)$_POST['name_ko']) : '';
    $values['first_name_eng'] = isset($_POST['first_name_eng']) ? trim((string)$_POST['first_name_eng']) : '';
    $values['last_name_eng'] = isset($_POST['last_name_eng']) ? trim((string)$_POST['last_name_eng']) : '';
    if (!preg_match('/^[A-Z]{2}$/', $values['country'])) {
        $values['country'] = '';
    }
    $countryStmt = $pdo->prepare("SELECT minr, name_ko, name_eng, dial, iso FROM code WHERE majr = 1 AND state = 1 AND iso = ?");
    $countryStmt->execute([$values['country']]);
    $countryRow = $values['country'] === '' ? null : $countryStmt->fetch(PDO::FETCH_ASSOC);
    $korea = $countryRow ? ksers_country_profile($countryRow)['korea'] : false;
    $textLength = function ($value) {
        $count = preg_match_all('/./u', (string)$value, $unused);
        return $count === false ? 0 : $count;
    };
    if (!$countryRow) {
        $errors['country'] = t('국가를 선택해 주세요.', 'Choose a country.');
    }
    if ($values['email'] === '') {
        $errors['email'] = t('이메일 주소를 입력해 주세요.', 'Enter your email address.');
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || $textLength($values['email']) > 45) {
        $errors['email'] = t('이메일 주소를 확인해 주세요.', 'Check the email address.');
    }
    if ($countryRow && $korea && $values['name_ko'] === '') {
        $errors['name_ko'] = t('성명(국문)을 입력해 주세요.', 'Enter your Korean name.');
    }
    if ($countryRow && !$korea && $values['first_name_eng'] === '') {
        $errors['first_name_eng'] = t('이름(영문)을 입력해 주세요.', 'Enter your first name.');
    }
    if ($countryRow && !$korea && $values['last_name_eng'] === '') {
        $errors['last_name_eng'] = t('성(영문)을 입력해 주세요.', 'Enter your family name.');
    }
    if ($textLength($values['name_ko']) > 45 || $textLength($values['first_name_eng']) > 45 || $textLength($values['last_name_eng']) > 45) {
        if ($korea) {
            $errors['name_ko'] = t('각 항목은 45자 안으로 입력해 주세요.', 'Each field must be 45 characters or fewer.');
        } else {
            if ($textLength($values['first_name_eng']) > 45) {
                $errors['first_name_eng'] = t('각 항목은 45자 안으로 입력해 주세요.', 'Each field must be 45 characters or fewer.');
            }
            if ($textLength($values['last_name_eng']) > 45) {
                $errors['last_name_eng'] = t('각 항목은 45자 안으로 입력해 주세요.', 'Each field must be 45 characters or fewer.');
            }
        }
    }
    $failed = $errors['country'] !== '' || $errors['email'] !== '' || $errors['name_ko'] !== '' || $errors['first_name_eng'] !== '' || $errors['last_name_eng'] !== '';
    if (!$failed) {
        $nameKo = $korea ? $values['name_ko'] : '';
        $firstName = $korea ? '' : $values['first_name_eng'];
        $lastName = $korea ? '' : $values['last_name_eng'];
        $matchState = ksers_password_match_state($pdo, $findYear, $values['country'], $values['email'], $nameKo, $firstName, $lastName);
        if ($matchState === 'partial') {
            $mismatch = t('가입된 정보와 맞지 않습니다.', 'The information does not match the registered account.');
        } else {
            if ($matchState === 'match') {
                ksers_password_deliver($pdo, $findYear, $values['country'], $values['email'], $nameKo, $firstName, $lastName);
            }
            $_SESSION['password_find_sent'] = 1;
            header('Location: find_password.php?year=' . (int)$findYear . ($homeNext !== '' ? ('&next=' . rawurlencode($homeNext)) : '') . '&sent=1');
            exit;
        }
    }
}

$country_list = ksers_country_rows($pdo);
$page_title = t('비밀번호 찾기 - ' . $findYear . ' 국제학술대회', 'Forgot password - ' . $findYear . ' International Conference');
$page_heading = t('비밀번호 찾기', 'Forgot password');
?>
<!DOCTYPE html>
<html lang="<?= h(ksers_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/service/ksers.css" rel="stylesheet">
    <style>
        body { display: flex; flex-direction: column; min-height: 100vh; }
        main.main-content { flex: 1; }
        #passwordReset[aria-disabled="true"] {
            background-color: #c5cdd8 !important;
            border-color: #c5cdd8 !important;
            color: #ffffff !important;
        }
        .find-toast {
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
            text-align: center;
        }
        #matchError {
            text-align: center;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/lib/site_header.php'; ?>

<main class="main-content site-page">
    <div class="d-flex justify-content-end mb-2">
        <?php $langBtnClass = 'btn btn-sm ksers-lang'; include __DIR__ . '/lib/lang_buttons.php'; ?>
    </div>

    <div class="d-flex justify-content-between align-items-center gap-3 pb-2 mb-4 border-bottom">
        <h2 class="h3 fw-bold m-0 text-dark"><?= h($page_heading) ?></h2>
        <a class="site-back" href="<?= h($loginHref) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            <?= h(t('뒤로가기', 'Back')) ?>
        </a>
    </div>

    <div class="mb-5">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-4 p-md-5">
                <?php if ($sent) { ?>
                <h5 class="fw-bold text-dark mb-3"><?= h(t('비밀번호 재설정 메일 전송', 'Password reset email sent')) ?></h5>
                <p class="mb-4" role="status"><?= h(t('입력하신 회원정보가 있다면 해당 메일로 비밀번호 재설정 메일을 전송했습니다.', 'If the member information you entered exists, a password reset email has been sent to that address.')) ?></p>
                <a class="btn btn-primary-custom w-100 py-2.5 fw-bold fs-6" href="<?= h($loginHref) ?>"><?= h(t('로그인으로 돌아가기', 'Back to log in')) ?></a>
                <?php } else { ?>
                <form id="findForm" method="post" action="find_password.php?year=<?= (int)$findYear ?><?= $homeNext !== '' ? ('&next=' . rawurlencode($homeNext)) : '' ?>" novalidate>
                    <input type="hidden" name="congress_year" value="<?= (int)$findYear ?>">
                    <?php if ($homeNext !== '') { ?>
                    <input type="hidden" name="next" value="<?= h($homeNext) ?>">
                    <?php } ?>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label for="country" class="form-label small fw-bold"><?= h(t('국가', 'Country')) ?> <span class="text-danger">*</span></label>
                            <select class="form-select<?= $errors['country'] !== '' ? ' is-invalid' : '' ?>" id="country" name="country" required>
                                <option value=""><?= h(t('국가를 선택해 주세요', 'Choose a country')) ?></option>
                                <?php foreach ($country_list as $c) {
                                    $countryProfile = ksers_country_profile($c);
                                ?>
                                <option value="<?= h($c['iso']) ?>" data-korea="<?= $countryProfile['korea'] ? '1' : '0' ?>"<?= $values['country'] === $c['iso'] ? ' selected' : '' ?>><?= h(code_name($c)) ?></option>
                                <?php } ?>
                            </select>
                            <div id="countryError" class="form-text small is-invalid<?= $errors['country'] === '' ? ' d-none' : '' ?>"><?= h($errors['country'] !== '' ? $errors['country'] : t('국가를 선택해 주세요.', 'Choose a country.')) ?></div>
                        </div>
                        <div class="col-12">
                            <label for="findEmail" class="form-label small fw-bold"><?= h(t('이메일 주소 (ID)', 'Email address (ID)')) ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= $errors['email'] !== '' ? ' is-invalid' : '' ?>" id="findEmail" name="email" value="<?= h($values['email']) ?>" placeholder="example@domain.com" required autocomplete="email">
                            <div id="emailError" class="form-text small is-invalid<?= $errors['email'] === '' ? ' d-none' : '' ?>"><?= h($errors['email'] !== '' ? $errors['email'] : t('이메일 주소를 입력해 주세요.', 'Enter your email address.')) ?></div>
                        </div>
                        <div class="col-12 d-none" id="findNameKo">
                            <label for="nameKo" class="form-label small fw-bold"><?= h(t('성명(국문)', 'Name (Korean)')) ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= $errors['name_ko'] !== '' ? ' is-invalid' : '' ?>" id="nameKo" name="name_ko" value="<?= h($values['name_ko']) ?>" placeholder="<?= h(t('홍길동', 'Hong Gildong')) ?>" disabled>
                            <div id="nameKoError" class="form-text small is-invalid<?= $errors['name_ko'] === '' ? ' d-none' : '' ?>"><?= h($errors['name_ko'] !== '' ? $errors['name_ko'] : t('성명(국문)을 입력해 주세요.', 'Enter your Korean name.')) ?></div>
                        </div>
                        <div class="col-12 d-none" id="findNameEn">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="firstNameEn" class="form-label small fw-bold"><?= h(t('이름(영문)', 'First Name')) ?> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control<?= $errors['first_name_eng'] !== '' ? ' is-invalid' : '' ?>" id="firstNameEn" name="first_name_eng" value="<?= h($values['first_name_eng']) ?>" placeholder="Gildong" disabled>
                                    <div id="firstNameError" class="form-text small is-invalid<?= $errors['first_name_eng'] === '' ? ' d-none' : '' ?>"><?= h($errors['first_name_eng'] !== '' ? $errors['first_name_eng'] : t('이름(영문)을 입력해 주세요.', 'Enter your first name.')) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="lastNameEn" class="form-label small fw-bold"><?= h(t('성(영문)', 'Family (Last) Name')) ?> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control<?= $errors['last_name_eng'] !== '' ? ' is-invalid' : '' ?>" id="lastNameEn" name="last_name_eng" value="<?= h($values['last_name_eng']) ?>" placeholder="Hong" disabled>
                                    <div id="lastNameError" class="form-text small is-invalid<?= $errors['last_name_eng'] === '' ? ' d-none' : '' ?>"><?= h($errors['last_name_eng'] !== '' ? $errors['last_name_eng'] : t('성(영문)을 입력해 주세요.', 'Enter your family name.')) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary-custom py-2.5 fw-bold fs-6" id="passwordReset" aria-disabled="true">
                            <?= h(t('비밀번호 재설정', 'Reset password')) ?>
                        </button>
                        <div id="matchError" class="form-text small is-invalid<?= $mismatch === '' ? ' d-none' : '' ?>" role="alert"><?= h($mismatch) ?></div>
                    </div>
                </form>
                <?php } ?>
            </div>
        </div>
    </div>

    <?php if (!$sent) { ?>
    <script>
        const findCountry = document.getElementById('country');
        const findNameKo = document.getElementById('findNameKo');
        const findNameEn = document.getElementById('findNameEn');
        const nameKo = document.getElementById('nameKo');
        const firstName = document.getElementById('firstNameEn');
        const lastName = document.getElementById('lastNameEn');
        const findEmail = document.getElementById('findEmail');
        const findForm = document.getElementById('findForm');
        const resetButton = document.getElementById('passwordReset');
        const reviewMessage = <?= json_encode(t('입력폼을 다시 확인해 주세요.', 'Please check the form again.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const matchMessage = <?= json_encode($mismatch, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const emailEmpty = <?= json_encode(t('이메일 주소를 입력해 주세요.', 'Enter your email address.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const emailInvalid = <?= json_encode(t('이메일 주소를 확인해 주세요.', 'Check the email address.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        function selectedOption() {
            return findCountry.options[findCountry.selectedIndex];
        }

        function isKorea() {
            const option = selectedOption();
            return findCountry.value !== '' && option && option.dataset.korea === '1';
        }

        function setNameField(field, active) {
            field.disabled = !active;
            field.required = active;
            if (!active) {
                field.classList.remove('is-invalid');
            }
        }

        function markField(field, messageEl, message) {
            const invalid = message !== '';
            field.classList.toggle('is-invalid', invalid);
            messageEl.classList.toggle('d-none', !invalid);
            if (invalid) {
                messageEl.textContent = message;
            }
        }

        function emailMessage() {
            const email = findEmail.value.trim();
            if (email === '') {
                return emailEmpty;
            }
            if (!email.includes('@') || email.length > 45) {
                return emailInvalid;
            }
            return '';
        }

        function fieldErrors() {
            const korea = isKorea();
            const chosen = findCountry.value !== '';
            return {
                country: chosen ? '' : document.getElementById('countryError').textContent,
                email: emailMessage(),
                nameKo: chosen && korea && nameKo.value.trim() === '' ? document.getElementById('nameKoError').textContent : '',
                firstName: chosen && !korea && firstName.value.trim() === '' ? document.getElementById('firstNameError').textContent : '',
                lastName: chosen && !korea && lastName.value.trim() === '' ? document.getElementById('lastNameError').textContent : ''
            };
        }

        function formReady() {
            const chosen = findCountry.value !== '';
            const korea = isKorea();
            if (!chosen || findEmail.value.trim() === '') {
                return false;
            }
            if (korea) {
                return nameKo.value.trim() !== '';
            }
            return firstName.value.trim() !== '' && lastName.value.trim() !== '';
        }

        function syncButton() {
            const ready = formReady();
            resetButton.setAttribute('aria-disabled', ready ? 'false' : 'true');
        }

        function syncCountry() {
            const chosen = findCountry.value !== '';
            const korea = isKorea();
            findNameKo.classList.toggle('d-none', !korea);
            findNameEn.classList.toggle('d-none', !chosen || korea);
            setNameField(nameKo, korea);
            setNameField(firstName, chosen && !korea);
            setNameField(lastName, chosen && !korea);
            if (!korea) {
                document.getElementById('nameKoError').classList.add('d-none');
            }
            if (korea || !chosen) {
                document.getElementById('firstNameError').classList.add('d-none');
                document.getElementById('lastNameError').classList.add('d-none');
            }
            syncButton();
        }

        function showReview(message) {
            document.querySelectorAll('.find-toast').forEach(function (toast) { toast.remove(); });
            const toast = document.createElement('div');
            toast.className = 'find-toast';
            toast.setAttribute('role', 'alert');
            toast.style.top = '16px';
            toast.textContent = message || reviewMessage;
            document.body.appendChild(toast);
            setTimeout(function () { toast.remove(); }, 4000);
        }

        function showErrors() {
            const chosen = findCountry.value !== '';
            const korea = isKorea();
            const issues = fieldErrors();
            markField(findCountry, document.getElementById('countryError'), issues.country);
            markField(findEmail, document.getElementById('emailError'), issues.email);
            markField(nameKo, document.getElementById('nameKoError'), chosen && korea ? issues.nameKo : '');
            markField(firstName, document.getElementById('firstNameError'), chosen && !korea ? issues.firstName : '');
            markField(lastName, document.getElementById('lastNameError'), chosen && !korea ? issues.lastName : '');
            if (!formReady()) {
                showReview();
                return false;
            }
            return true;
        }

        function clearMatchError() {
            const matchError = document.getElementById('matchError');
            if (matchError) {
                matchError.classList.add('d-none');
                matchError.textContent = '';
            }
        }

        findCountry.addEventListener('change', function () {
            markField(findCountry, document.getElementById('countryError'), '');
            clearMatchError();
            syncCountry();
        });
        findEmail.addEventListener('input', function () {
            markField(findEmail, document.getElementById('emailError'), '');
            clearMatchError();
            syncButton();
        });
        nameKo.addEventListener('input', function () {
            markField(nameKo, document.getElementById('nameKoError'), '');
            clearMatchError();
            syncButton();
        });
        firstName.addEventListener('input', function () {
            markField(firstName, document.getElementById('firstNameError'), '');
            clearMatchError();
            syncButton();
        });
        lastName.addEventListener('input', function () {
            markField(lastName, document.getElementById('lastNameError'), '');
            clearMatchError();
            syncButton();
        });
        findForm.addEventListener('submit', function (event) {
            if (!showErrors()) {
                event.preventDefault();
            }
        });
        syncCountry();
        if (matchMessage) {
            showReview(matchMessage);
        }
    </script>
    <?php } ?>
</main>

<?php include __DIR__.'/lib/site_footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
