<?php
// 현재 활성화된 메뉴 하이라이트를 위한 변수 체크
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- 사이드바 (관리자 메뉴) -->
<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse p-3">
    <div class="d-flex align-items-center mb-4 text-white text-decoration-none">
        <i class="bi bi-award-fill fs-4 me-2"></i>
        <span class="fs-5 fw-bold"><?= h(t('학술대회 관리자', 'Conference admin')) ?></span>
    </div>
    <hr class="text-secondary">
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="/admin/index.php" class="nav-link <?php echo ($current_page == 'index.php' && strpos($_SERVER['REQUEST_URI'], '/admin/page/') === false) ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2 me-2"></i> <?= h(t('대시보드', 'Dashboard')) ?>
            </a>
        </li>
        <li>
            <a href="/admin/page/member/list.php" class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/page/member/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-people me-2"></i> <?= h(t('회원', 'Members')) ?>
            </a>
        </li>
        <li>
            <a href="/admin/page/registration/list.php" class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/page/registration/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-card-list me-2"></i> <?= h(t('참가 등록', 'Registration')) ?>
            </a>
        </li>
        <li>
            <a href="/admin/page/payment/list.php" class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/page/payment/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-credit-card me-2"></i> <?= h(t('결제', 'Payments')) ?>
            </a>
        </li>
        <li>
            <a href="/admin/page/abstract/list.php" class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/page/abstract/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-text me-2"></i> <?= h(t('초록', 'Abstracts')) ?>
            </a>
        </li>
        <li>
            <a href="/admin/page/submission/list.php" class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/page/submission/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-cloud-arrow-up me-2"></i> <?= h(t('참가자 제출 파일', 'Participant files')) ?>
            </a>
        </li>
        <li>
            <a href="/admin/page/glance/index.php" class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/page/glance/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-grid-3x3-gap me-2"></i> <?= h(t('일정표', 'Program at a Glance')) ?>
            </a>
        </li>
        <li>
            <a href="/admin/page/scientific/index.php" class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/page/scientific/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-journal-text me-2"></i> Scientific Program
            </a>
        </li>
        <li>
            <a href="/admin/page/gallery/index.php" class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/page/gallery/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-images me-2"></i> Gallery
            </a>
        </li>
        <li>
            <a href="/admin/page/mail/index.php" class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/page/mail/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-envelope me-2"></i> E-Mail
            </a>
        </li>
        <li>
            <a href="/admin/page/news/index.php" class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/page/news/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-newspaper me-2"></i> <?= h(t('게시판', 'Board')) ?>
            </a>
        </li>
        <?php /* 프로그램·연자 화면은 더 이상 쓰지 않아 메뉴에서만 숨긴다. */ ?>
        <li>
            <a href="/admin/page/setting/index.php" class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/page/setting/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-gear me-2"></i> <?= h(t('대회 설정', 'Settings')) ?>
            </a>
        </li>
    </ul>
</nav>

<!-- 메인 콘텐츠 영역 시작 -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

    <!-- 상단바 (연도 선택 / 국영문 전환 / 로그아웃) -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-4 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <h1 class="h3 m-0"><?php echo h(isset($page_heading) ? $page_heading : t('대시보드', 'Dashboard')); ?></h1>
            <!-- 다년도 연도 데이터 분리 선택기 -->
            <form method="post" action="/admin/year.php" class="m-0">
                <?php $adminYear = ksers_admin_year($pdo); ?>
                <select class="form-select form-select-sm w-auto" name="year" onchange="this.form.submit()">
                    <?php for ($y = 2027; $y <= 2028; $y++) { ?>
                    <option value="<?= $y ?>" <?= $adminYear === $y ? 'selected' : '' ?>><?= h($y . ' ' . t('국제학술대회', 'International Conference')) ?></option>
                    <?php } ?>
                </select>
            </form>
        </div>
        
        <div class="btn-toolbar mb-2 mb-md-0 gap-2">
            <?php if (!empty($page_heading_action)) { echo $page_heading_action; } ?>
            <!-- 국문 / 영문 전환 -->
            <?php $langBtnClass = 'btn btn-sm btn-outline-secondary'; include $_SERVER['DOCUMENT_ROOT'].'/service/lib/lang_buttons.php'; ?>
            <a href="/service/logout.php" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right me-1"></i> <?= h(t('로그아웃', 'Log out')) ?>
            </a>
        </div>
    </div>