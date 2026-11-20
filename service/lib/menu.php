<?php
$current_page = basename($_SERVER['PHP_SELF']);
$memberPages = ['index.php', 'mypage.php', 'registration.php', 'abstract.php', 'materials.php', 'submission_upload.php'];
$showMemberTabs = in_array($current_page, $memberPages, true);
$memberTabsAdmin = isset($pdo) && function_exists('ksers_member_is_admin') && ksers_member_is_admin($pdo);
$memberTabs = [
    ['href' => 'mypage.php', 'pages' => ['mypage.php'], 'ko' => '회원정보', 'en' => 'Profile'],
    ['href' => 'registration.php', 'pages' => ['registration.php'], 'ko' => '참가 등록·결제', 'en' => 'Registration & Payment'],
    ['href' => 'abstract.php', 'pages' => ['abstract.php'], 'ko' => '초록 제출', 'en' => 'Abstract Submission'],
    ['href' => 'materials.php', 'pages' => ['materials.php', 'submission_upload.php'], 'ko' => '영상 제출', 'en' => 'Video Submission'],
];
include __DIR__ . '/site_header.php';
?>
<main class="main-content site-page">
    <?php if ($showMemberTabs) { ?>
    <?php if (!$memberTabsAdmin) { ?>
    <nav class="member-tabs" aria-label="<?= h(t('마이페이지 메뉴', 'My page menu')) ?>">
        <?php foreach ($memberTabs as $tab) {
            $tabCurrent = in_array($current_page, $tab['pages'], true);
        ?>
        <a class="member-tab<?= $tabCurrent ? ' is-current' : '' ?>" href="<?= h($tab['href']) ?>"<?= $tabCurrent ? ' aria-current="page"' : '' ?>><?= h(t($tab['ko'], $tab['en'])) ?></a>
        <?php } ?>
    </nav>
    <?php } ?>
    <div class="d-flex justify-content-end mb-2">
        <?php $langBtnClass = 'btn btn-sm ksers-lang'; include __DIR__ . '/lang_buttons.php'; ?>
    </div>
    <?php } ?>
    <?php if (isset($page_heading)): ?>
    <div class="pb-2 mb-4 border-bottom">
        <h2 class="h3 fw-bold m-0 text-dark"><?php echo h($page_heading); ?></h2>
    </div>
    <?php endif; ?>
