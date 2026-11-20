<?php
if (!isset($siteProfile)) {
    require_once __DIR__ . '/site_profiles.php';
    $siteProfile = ksers_site_profile(isset($pdo) ? ksers_site_year($pdo) : 2027);
}
?>
<section class="site-hosted" aria-label="Hosted by">
    <div class="site-wrap site-hosted-row">
        <p>Hosted by</p>
        <a href="https://ksers.or.kr/v2/" target="_blank" rel="noopener noreferrer">
            <img src="/service/img/logo_ksels.png" alt="대한내시경로봇외과학회" width="131" height="36">
        </a>
    </div>
</section>
<footer class="site-footer">
    <div class="site-wrap site-footer-grid">
        <div>
            <h2><?= h(isset($siteProfile['footer_title']) ? $siteProfile['footer_title'] : 'KSERS Congress Secretariat [Fm.inc]') ?></h2>
            <?php foreach ((isset($siteProfile['footer_lines']) ? $siteProfile['footer_lines'] : []) as $footerLine) { ?>
            <p><?= h($footerLine) ?></p>
            <?php } ?>
            <?php if (!empty($siteProfile['footer_email'])) { ?>
            <p>E-mail. <a href="mailto:<?= h($siteProfile['footer_email']) ?>"><?= h($siteProfile['footer_email']) ?></a></p>
            <?php } ?>
        </div>
        <div>
            <h2 class="site-footer-society"><span>The Korean Society of Endo-Laparoscopic &amp; Robotic Surgery</span><span class="site-footer-society-ko">[대한내시경로봇외과학회]</span></h2>
            <p>서울특별시 종로구 창경궁로 109 세운스퀘어 별관 136호 대한내시경로봇외과학회</p>
            <p>TEL. +82-2-592-5009</p>
            <p>FAX. +82-2-533-5009</p>
            <p>E-mail. <a href="mailto:ksels2010@gmail.com">ksels2010@gmail.com</a></p>
        </div>
    </div>
    <button type="button" class="site-top" aria-label="Back to top" onclick="window.scrollTo({top:0,behavior:'smooth'})">
        <img src="/service/img/icon_arrow_top.png" alt="" width="17" height="10">
    </button>
</footer>
