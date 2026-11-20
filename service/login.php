<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
$homeNext = ksers_homepage_return(isset($_GET['next']) ? $_GET['next'] : '');
$loginProbe = isset($_GET['probe']) && $_GET['probe'] === '1';
if ($homeNext !== '' && !empty($_SESSION['seq']) && !empty($_SESSION['login_at'])) {
    $code = ksers_issue_auth_code($pdo, (int)$_SESSION['seq'], (int)$_SESSION['login_at']);
    header('Location: ' . ksers_with_query($homeNext, 'code', $code));
    exit;
}
if ($homeNext !== '' && $loginProbe) {
    header('Location: ' . ksers_with_query($homeNext, 'probed', '1'));
    exit;
}
$loginYear = ksers_site_year($pdo);
?>
<!DOCTYPE html>
<html lang="<?= h(ksers_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(t('로그인 - ' . $loginYear . ' 국제학술대회', 'Log in - ' . $loginYear . ' International Conference')) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/service/ksers.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --ksers-primary: #09428F;
            --ksers-primary-hover: #073472;
            --ksers-danger: #b91c1c;
            --ksers-text: #263238;
            --ksers-muted: #52525b;
            --ksers-border: #e4e4e7;
            --ksers-cyan: #4fd2e0;
            --ksers-footer: #37474f;
            --ksers-soft: #f4f7fb;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: var(--ksers-soft);
            color: var(--ksers-text);
            font-family: Pretendard, Roboto, Arial, sans-serif;
        }

        .login-nav {
            background-color: #ffffff;
            border-bottom: 1px solid #cfd8dc;
        }
        .login-nav .nav-link {
            color: var(--ksers-primary);
            font-weight: 800;
        }
        .login-logo {
            font-size: 1.7rem;
            line-height: 1;
            letter-spacing: -0.03em;
            text-decoration: none;
        }
        .login-logo-name {
            font-weight: 800;
            color: var(--ksers-primary);
        }
        .login-logo-year {
            font-weight: 500;
            color: var(--ksers-cyan);
        }
        .login-nav .nav-link:hover,
        .login-nav .nav-link:focus,
        .login-nav .navbar-brand:hover {
            color: var(--ksers-danger);
        }
        .login-nav .navbar-toggler {
            border-color: var(--ksers-primary);
        }

        .login-lang {
            color: var(--ksers-primary);
            border-color: var(--ksers-primary);
            background-color: #ffffff;
            font-weight: 700;
        }
        .login-lang:hover,
        .login-lang.active {
            color: #ffffff;
            background-color: var(--ksers-primary);
            border-color: var(--ksers-primary);
        }

        .login-lang-bar { padding: 0.75rem 0 0; }
        .login-lang-bar .site-wrap { display: flex; justify-content: flex-end; }
        .login-lang-form { display: none; }

        @media (max-width: 767.98px) {
            .login-lang-bar { display: none; }
            .login-lang-form {
                display: flex;
                justify-content: center;
                margin-bottom: 0.75rem;
            }
        }

        .login-signup {
            color: var(--ksers-primary);
            border: 1px solid var(--ksers-primary);
            background-color: #ffffff;
            font-weight: 700;
        }
        .login-signup:hover {
            color: #ffffff;
            background-color: var(--ksers-primary);
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

        .form-label {
            color: var(--ksers-text);
        }
        .form-control:focus {
            border-color: var(--ksers-primary);
            box-shadow: 0 0 0 0.2rem rgba(9, 66, 143, 0.18);
        }
        .form-check-input:checked {
            background-color: var(--ksers-primary);
            border-color: var(--ksers-primary);
        }
        .form-check-input:focus {
            border-color: var(--ksers-primary);
            box-shadow: 0 0 0 0.2rem rgba(9, 66, 143, 0.18);
        }
        .login-forgot {
            color: var(--ksers-primary);
        }
        .login-forgot:hover {
            color: var(--ksers-danger);
        }

        main.main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-footer {
            background-color: var(--ksers-footer);
            color: #ffffff;
        }

        .login-hint {
            margin: 0.2rem 0 0;
            color: #737373;
            font-size: 0.75rem;
            line-height: 1.3;
        }

        .login-hint.is-invalid {
            color: #dc3545;
        }

        .row > .login-panel {
            width: min(calc(510px + var(--bs-gutter-x)), 100%);
            max-width: 100%;
            min-width: 0;
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/lib/site_header.php'; ?>

    <div class="login-lang-bar">
        <div class="site-wrap">
            <?php $langBtnClass = 'btn btn-sm login-lang'; include __DIR__ . '/lib/lang_buttons.php'; ?>
        </div>
    </div>

    <!-- 2. 메인 로그인 카드 영역 -->
    <main class="main-content container py-5">
        <div class="row justify-content-center w-100">
            <div class="login-panel">
                
                <div class="card border-0 shadow rounded-3">
                    <div class="card-body p-4 p-md-5">
                        
                        <!-- 학술대회 타이틀 -->
                        <div class="text-center mb-4">
                            <img class="login-logo" src="img/KSERS_logo.png" alt="KSERS">
                            <h4 class="login-title mb-1">KSERS <?= (int)$loginYear ?></h4>
                            <p class="text-muted small mb-0"><?= h(t('등록 및 초록 접수를 위해 로그인해 주세요.', 'Log in to register and submit an abstract.')) ?></p>
                        </div>

                        <!-- 로그인 폼 -->
                        <form id="loginForm" action="login.json.php" method="post" novalidate>
                            <input type="hidden" name="congress_year" value="<?= (int)$loginYear ?>">
                            <?php if ($homeNext !== '') { ?>
                            <input type="hidden" name="next" value="<?= h($homeNext) ?>">
                            <?php } ?>
                            <!-- 이메일 아이디 -->
                            <div class="mb-3">
                                <label for="email" class="form-label small fw-bold"><?= h(t('이메일 주소 (ID)', 'Email address (ID)')) ?></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control border-start-0 ps-0" id="email" name="email" placeholder="example@domain.com" required autocomplete="email">
                                </div>
                                <p id="loginEmailMessage" class="login-hint"><?= h(t('@를 포함해 입력해 주세요.', 'Include @ in the email address.')) ?></p>
                            </div>

                            <!-- 비밀번호 -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label for="password" class="form-label small fw-bold mb-0"><?= h(t('비밀번호', 'Password')) ?></label>
                                    <a href="find_password.php?year=<?= (int)$loginYear ?><?= $homeNext !== '' ? ('&next=' . rawurlencode($homeNext)) : '' ?>" class="login-forgot text-decoration-none small"><?= h(t('비밀번호 찾기', 'Forgot password')) ?></a>
                                </div>
                                <div class="input-group mt-1">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="<?= h(t('비밀번호를 입력하세요', 'Enter your password')) ?>" required>
                                </div>
                                <p id="loginPasswordHint" class="login-hint"><?= h(t('8자 이상, 특수문자를 포함해 입력해 주세요.', 'At least 8 characters, including a special character.')) ?></p>
                            </div>

                            <!-- 제출 버튼 -->
                            <button type="submit" class="btn btn-primary-custom w-100 py-2.5 fw-bold fs-6">
                                <?= h(t('로그인', 'Log in')) ?>
                            </button>
                        </form>

                        <hr class="my-4 text-muted opacity-25">

                        <div class="login-lang-form">
                            <?php $langBtnClass = 'btn btn-sm login-lang'; include __DIR__ . '/lib/lang_buttons.php'; ?>
                        </div>

                        <!-- 회원가입 링크 -->
                        <div class="text-center">
                            <p class="small text-muted text-center mb-1"><?= h(t('이전 대회 계정은 사용할 수 없습니다', 'Accounts from a previous congress cannot be used.')) ?></p>
                            <p class="small text-muted mb-2"><?= h(t('아직 계정이 없으신가요?', 'No account yet?')) ?></p>
                            <a href="join.php?year=<?= (int)$loginYear ?><?= $homeNext !== '' ? ('&next=' . rawurlencode($homeNext)) : '' ?>" class="btn login-signup btn-sm px-4">
                                <?= h(t('신규 회원가입', 'Create account')) ?>
                            </a>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- 3. 하단 푸터 -->
    <?php include __DIR__.'/lib/site_footer.php'; ?>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script type="module">
        import { z } from "https://cdn.jsdelivr.net/npm/zod@3.24.2/+esm";

        const form = document.getElementById("loginForm");
        const emailMessage = document.getElementById("loginEmailMessage");
        const passwordHint = document.getElementById("loginPasswordHint");
        const hasSpecialCharacter = (value) => /[^0-9A-Za-z가-힣ㄱ-ㅎㅏ-ㅣ\s]/.test(value);
        const schema = z.object({
            email: z.string().refine((value) => value.includes("@"), {
                message: emailMessage.textContent,
            }),
            password: z.string().refine((value) => value.length >= 8 && hasSpecialCharacter(value), {
                message: passwordHint.textContent,
            }),
        });

        form.addEventListener("submit", (event) => {
            const parsed = schema.safeParse({
                email: form.email.value.trim(),
                password: form.password.value,
            });
            const emailError = parsed.success ? undefined : parsed.error.issues.find((issue) => issue.path[0] === "email");
            const passwordError = parsed.success ? undefined : parsed.error.issues.find((issue) => issue.path[0] === "password");

            emailMessage.classList.toggle("is-invalid", Boolean(emailError));
            passwordHint.classList.toggle("is-invalid", Boolean(passwordError));
            if (!parsed.success) {
                event.preventDefault();
            }
        });
    </script>

</body>
</html>