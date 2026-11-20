<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
require_once __DIR__ . '/../ksers_password.php';

$token = isset($_POST['token']) ? (string)$_POST['token'] : (isset($_GET['token']) ? (string)$_GET['token'] : '');
$resetYear = ksers_site_year($pdo);
$loginHref = '/service/login.php?year=' . (int)$resetYear;
$row = ksers_password_find_token($pdo, $token);
$errors = [
    'password' => '',
    'confirm' => '',
];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $row) {
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $confirm = isset($_POST['password_confirm']) ? (string)$_POST['password_confirm'] : '';
    $length = preg_match_all('/./u', $password, $unused);
    $special = preg_match('/[^0-9A-Za-z가-힣ㄱ-ㅎㅏ-ㅣ\s]/u', $password);
    if ($password === '') {
        $errors['password'] = t('비밀번호를 입력해 주세요.', 'Enter a password.');
    } elseif ($length === false || $length < 8 || !$special) {
        $errors['password'] = t('8자 이상, 특수문자를 포함해 입력해 주세요.', 'At least 8 characters, including a special character.');
    }
    if ($confirm === '') {
        $errors['confirm'] = t('비밀번호를 다시 입력해 주세요.', 'Re-enter the password.');
    } elseif ($password !== $confirm) {
        $errors['confirm'] = t('비밀번호가 서로 다릅니다.', 'The passwords do not match.');
    }
    if ($errors['password'] === '' && $errors['confirm'] === '') {
        if (!ksers_password_apply($pdo, $token, $password)) {
            $row = null;
        } else {
            $saved = true;
            $row = null;
        }
    }
}

$page_title = t('비밀번호 재설정 - ' . $resetYear . ' 국제학술대회', 'Reset password - ' . $resetYear . ' International Conference');
$passwordHint = $errors['password'] !== '' ? $errors['password'] : t('8자 이상, 특수문자를 포함해 입력해 주세요.', 'At least 8 characters, including a special character.');
$confirmHint = $errors['confirm'] !== '' ? $errors['confirm'] : t('비밀번호를 한 번 더 입력해 주세요.', 'Enter the password again.');
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
        :root {
            --ksers-primary: #09428F;
            --ksers-primary-hover: #073472;
            --ksers-text: #263238;
            --ksers-muted: #52525b;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #f4f7fb;
            color: var(--ksers-text);
            font-family: Pretendard, Roboto, Arial, sans-serif;
        }
        .login-logo {
            display: block;
            width: 148px;
            height: auto;
            margin: 0 auto 0.75rem;
        }
        .login-title {
            color: var(--ksers-text);
            font-family: Roboto, Pretendard, Arial, sans-serif;
            font-weight: 700;
        }
        .btn-primary-custom {
            background-color: var(--ksers-primary);
            border-color: var(--ksers-primary);
            color: #ffffff;
            font-weight: 700;
        }
        .btn-primary-custom:hover,
        .btn-primary-custom:focus {
            background-color: var(--ksers-primary-hover);
            border-color: var(--ksers-primary-hover);
            color: #ffffff;
        }
        .form-label { color: var(--ksers-text); }
        .form-control:focus {
            border-color: var(--ksers-primary);
            box-shadow: 0 0 0 0.2rem rgba(9, 66, 143, 0.18);
        }
        main.main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-hint {
            margin: 0.2rem 0 0;
            color: #737373;
            font-size: 0.75rem;
            line-height: 1.3;
        }
        .login-hint.is-invalid { color: #dc3545; }
        .row > .login-panel {
            width: min(calc(510px + var(--bs-gutter-x)), 100%);
            max-width: 100%;
            min-width: 0;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/lib/site_header.php'; ?>
<main class="main-content container py-5">
    <div class="row justify-content-center w-100">
        <div class="login-panel">
            <div class="card border-0 shadow rounded-3">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <img class="login-logo" src="img/KSERS_logo.png" alt="KSERS">
                        <h4 class="login-title mb-1">KSERS <?= (int)$resetYear ?></h4>
                        <p class="text-muted small mb-0"><?= h(t('새 비밀번호를 입력해 주세요.', 'Enter a new password.')) ?></p>
                    </div>
                    <?php if ($saved) { ?>
                    <p class="mb-4 text-center" role="status"><?= h(t('비밀번호를 변경했습니다. 로그인해 주세요.', 'Your password has been changed. Please log in.')) ?></p>
                    <a class="btn btn-primary-custom w-100 py-2.5 fw-bold fs-6" href="<?= h($loginHref) ?>"><?= h(t('로그인으로 돌아가기', 'Back to log in')) ?></a>
                    <?php } elseif (!$row) { ?>
                    <p class="mb-4 text-center" role="status"><?= h(t('재설정 링크가 만료되었거나 이미 사용되었습니다.', 'This reset link has expired or has already been used.')) ?></p>
                    <a class="btn btn-primary-custom w-100 py-2.5 fw-bold fs-6" href="/service/find_password.php?year=<?= (int)$resetYear ?>"><?= h(t('비밀번호 찾기', 'Forgot password')) ?></a>
                    <?php } else { ?>
                    <form id="resetForm" method="post" action="reset_password.php" novalidate
                        data-empty-password="<?= h(t('비밀번호를 입력해 주세요.', 'Enter a password.')) ?>"
                        data-rule="<?= h(t('8자 이상, 특수문자를 포함해 입력해 주세요.', 'At least 8 characters, including a special character.')) ?>"
                        data-empty-confirm="<?= h(t('비밀번호를 다시 입력해 주세요.', 'Re-enter the password.')) ?>"
                        data-mismatch="<?= h(t('비밀번호가 서로 다릅니다.', 'The passwords do not match.')) ?>"
                        data-password-hint="<?= h(t('8자 이상, 특수문자를 포함해 입력해 주세요.', 'At least 8 characters, including a special character.')) ?>"
                        data-confirm-hint="<?= h(t('비밀번호를 한 번 더 입력해 주세요.', 'Enter the password again.')) ?>">
                        <input type="hidden" name="token" value="<?= h($token) ?>">
                        <div class="mb-3">
                            <label for="resetPassword" class="form-label small fw-bold"><?= h(t('비밀번호', 'Password')) ?></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control border-start-0 ps-0<?= $errors['password'] !== '' ? ' is-invalid' : '' ?>" id="resetPassword" name="password" placeholder="<?= h(t('비밀번호를 입력하세요', 'Enter your password')) ?>" required autocomplete="new-password">
                            </div>
                            <p id="resetPasswordHint" class="login-hint<?= $errors['password'] !== '' ? ' is-invalid' : '' ?>"><?= h($passwordHint) ?></p>
                        </div>
                        <div class="mb-3">
                            <label for="resetPasswordConfirm" class="form-label small fw-bold"><?= h(t('비밀번호 재입력', 'Re-enter password')) ?></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control border-start-0 ps-0<?= $errors['confirm'] !== '' ? ' is-invalid' : '' ?>" id="resetPasswordConfirm" name="password_confirm" placeholder="<?= h(t('비밀번호를 다시 입력하세요', 'Enter your password again')) ?>" required autocomplete="new-password">
                            </div>
                            <p id="resetConfirmHint" class="login-hint<?= $errors['confirm'] !== '' ? ' is-invalid' : '' ?>"><?= h($confirmHint) ?></p>
                        </div>
                        <button type="submit" class="btn btn-primary-custom w-100 py-2.5 fw-bold fs-6"><?= h(t('확인', 'Confirm')) ?></button>
                    </form>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__.'/lib/site_footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($row) { ?>
<script>
    const form = document.getElementById("resetForm");
    const passwordHint = document.getElementById("resetPasswordHint");
    const confirmHint = document.getElementById("resetConfirmHint");
    const hasSpecialCharacter = (value) => /[^0-9A-Za-z가-힣ㄱ-ㅎㅏ-ㅣ\s]/.test(value);
    form.addEventListener("submit", (event) => {
        const password = form.password.value;
        const confirm = form.password_confirm.value;
        let passwordMessage = "";
        let confirmMessage = "";
        if (password === "") {
            passwordMessage = form.dataset.emptyPassword;
        } else if (password.length < 8 || !hasSpecialCharacter(password)) {
            passwordMessage = form.dataset.rule;
        }
        if (confirm === "") {
            confirmMessage = form.dataset.emptyConfirm;
        } else if (password !== confirm) {
            confirmMessage = form.dataset.mismatch;
        }
        passwordHint.textContent = passwordMessage || form.dataset.passwordHint;
        confirmHint.textContent = confirmMessage || form.dataset.confirmHint;
        passwordHint.classList.toggle("is-invalid", passwordMessage !== "");
        confirmHint.classList.toggle("is-invalid", confirmMessage !== "");
        form.password.classList.toggle("is-invalid", passwordMessage !== "");
        form.password_confirm.classList.toggle("is-invalid", confirmMessage !== "");
        if (passwordMessage !== "" || confirmMessage !== "") {
            event.preventDefault();
        }
    });
</script>
<?php } ?>
</body>
</html>
