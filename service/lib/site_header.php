<?php
require_once __DIR__ . '/site_nav.php';
require_once __DIR__ . '/site_profiles.php';

$siteLoggedIn = ksers_member_logged_in();
$siteProfile = ksers_site_profile(isset($pdo) ? ksers_site_year($pdo) : 2027);
$siteYear = (int)$siteProfile['year'];
$siteHome = ksers_congress_href($siteProfile['home']);
$siteNews = ksers_congress_href($siteProfile['news']);
$siteContact = ksers_congress_href($siteProfile['contact']);
$siteDday = ksers_dday_label($siteProfile['start']);
$siteLoginHref = '/service/login.php?year=' . $siteYear;
$siteJoinHref = '/service/join.php?year=' . $siteYear;
if (!empty($_GET['next'])) {
    $siteNext = rawurlencode((string)$_GET['next']);
    $siteLoginHref .= '&next=' . $siteNext;
    $siteJoinHref .= '&next=' . $siteNext;
}
$siteLogoutHref = '/service/logout.php';
$siteCrumbs = ksers_site_breadcrumb();
$siteMenus = $siteProfile['menus'];
$sitePage = basename(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
$siteMyPageCurrent = in_array($sitePage, ['index.php', 'mypage.php', 'registration.php', 'abstract.php', 'materials.php', 'submission_upload.php'], true);
?>
<header class="site-desktop-header">
    <div class="site-wrap">
        <div class="site-utility">
            <?php if ($siteDday !== '') { ?>
            <div class="site-dday" aria-label="D-day">
                <span>D-day</span>
                <strong><?= h($siteDday) ?></strong>
            </div>
            <?php } ?>
            <ul>
                <li><a href="<?= h($siteHome) ?>">Home</a></li>
                <?php if ($siteLoggedIn) { ?>
                <li><a href="<?= h($siteLogoutHref) ?>">Logout</a></li>
                <li><a href="/service/mypage.php">My Page</a></li>
                <?php } else { ?>
                <li><a href="<?= h($siteLoginHref) ?>">Login</a></li>
                <li><a href="<?= h($siteJoinHref) ?>">Sign Up</a></li>
                <?php } ?>
                <li><a href="<?= h($siteNews) ?>">News &amp; Notices</a></li>
                <li><a href="<?= h($siteContact) ?>">Contact us</a></li>
            </ul>
        </div>
        <div class="site-bar">
            <a class="site-logo" href="<?= h($siteHome) ?>"><span>KSERS</span><?= (int)$siteYear ?></a>
            <nav aria-label="주요 메뉴">
                <ul>
                    <?php foreach ($siteMenus as $menu) { ?>
                    <li>
                        <a href="<?= h(ksers_congress_href($menu['href'])) ?>"><?= h($menu['label']) ?></a>
                        <div class="site-sub">
                            <ul>
                                <?php foreach ($menu['items'] as $item) { ?>
                                <li><a href="<?= h(ksers_congress_href($item[1])) ?>"><?= h($item[0]) ?></a></li>
                                <?php } ?>
                            </ul>
                        </div>
                    </li>
                    <?php } ?>
                </ul>
            </nav>
        </div>
    </div>
</header>

<header class="site-mobile-header">
    <div class="site-wrap site-mobile-bar">
        <a class="site-logo" href="<?= h($siteHome) ?>"><span>KSERS</span><?= (int)$siteYear ?></a>
        <button type="button" class="site-menu-button" data-site-menu aria-expanded="false" aria-controls="site-mobile-panel" aria-label="메뉴 열기">
            <span class="site-menu-icon">
                <span></span><span></span><span></span>
            </span>
        </button>
    </div>
</header>
<button type="button" class="site-mobile-dim" data-site-dim aria-label="메뉴 닫기" tabindex="-1"></button>
<nav id="site-mobile-panel" class="site-mobile-panel" aria-label="주요 메뉴" aria-hidden="true" inert>
    <ul>
        <?php foreach ($siteMenus as $index => $menu) {
            $submenuId = 'site-mobile-sub-' . $index;
        ?>
        <li class="site-acc">
            <button type="button" class="site-acc-button" aria-expanded="false" aria-controls="<?= h($submenuId) ?>"><?= h($menu['label']) ?></button>
            <div class="site-acc-panel" id="<?= h($submenuId) ?>">
                <div class="site-acc-clip">
                    <ul>
                        <?php foreach ($menu['items'] as $item) { ?>
                        <li><a href="<?= h(ksers_congress_href($item[1])) ?>"><?= h($item[0]) ?></a></li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
        </li>
        <?php } ?>
        <?php if ($siteLoggedIn) { ?>
        <li><a class="site-mobile-link" href="<?= h($siteLogoutHref) ?>">Logout</a></li>
        <li><a class="site-mobile-link<?= $siteMyPageCurrent ? ' is-current' : '' ?>" href="/service/mypage.php"<?= $siteMyPageCurrent ? ' aria-current="page"' : '' ?>>My Page</a></li>
        <?php } else { ?>
        <li><a class="site-mobile-link<?= $sitePage === 'login.php' ? ' is-current' : '' ?>" href="<?= h($siteLoginHref) ?>"<?= $sitePage === 'login.php' ? ' aria-current="page"' : '' ?>>Login</a></li>
        <li><a class="site-mobile-link<?= $sitePage === 'join.php' ? ' is-current' : '' ?>" href="<?= h($siteJoinHref) ?>"<?= $sitePage === 'join.php' ? ' aria-current="page"' : '' ?>>Sign Up</a></li>
        <?php } ?>
        <li><a class="site-mobile-link" href="<?= h($siteNews) ?>">News &amp; Notices</a></li>
        <li><a class="site-mobile-link" href="<?= h($siteContact) ?>">Contact us</a></li>
    </ul>
</nav>
<div class="site-header-spacer" aria-hidden="true"></div>
<nav class="site-crumb" aria-label="현재 위치">
    <div class="site-wrap">
        <ol>
            <li><a href="<?= h($siteHome) ?>" aria-label="Home"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></a></li>
            <?php foreach ($siteCrumbs as $index => $crumb) {
                $isLast = $index === count($siteCrumbs) - 1;
                ?>
            <li<?= $isLast ? ' aria-current="page"' : '' ?>><?= h($crumb) ?></li>
                <?php
            } ?>
        </ol>
    </div>
</nav>
<script>
(function () {
    var toggle = document.querySelector("[data-site-menu]");
    var panel = document.getElementById("site-mobile-panel");
    var dim = document.querySelector("[data-site-dim]");
    if (!toggle || !panel || !dim) {
        return;
    }
    var scrollY = 0;
    var locked = false;

    function closeSections() {
        document.querySelectorAll(".site-acc.is-open").forEach(function (item) {
            item.classList.remove("is-open");
            var button = item.querySelector(".site-acc-button");
            if (button) {
                button.setAttribute("aria-expanded", "false");
            }
        });
    }

    function setMenu(open) {
        document.body.classList.toggle("site-menu-open", open);
        toggle.setAttribute("aria-expanded", open ? "true" : "false");
        toggle.setAttribute("aria-label", open ? "메뉴 닫기" : "메뉴 열기");
        panel.setAttribute("aria-hidden", open ? "false" : "true");
        dim.setAttribute("aria-hidden", open ? "false" : "true");
        dim.tabIndex = open ? 0 : -1;
        if (open) {
            panel.removeAttribute("inert");
            if (!locked) {
                scrollY = window.scrollY;
                document.body.style.position = "fixed";
                document.body.style.top = "-" + scrollY + "px";
                document.body.style.width = "100%";
                locked = true;
            }
            return;
        }
        panel.setAttribute("inert", "");
        closeSections();
        if (!locked) {
            return;
        }
        document.body.style.position = "";
        document.body.style.top = "";
        document.body.style.width = "";
        locked = false;
        window.scrollTo(0, scrollY);
    }

    toggle.addEventListener("click", function () {
        setMenu(!document.body.classList.contains("site-menu-open"));
    });
    dim.addEventListener("click", function () {
        setMenu(false);
    });
    document.querySelectorAll(".site-acc-button").forEach(function (button) {
        button.addEventListener("click", function () {
            var item = button.parentElement;
            var open = !item.classList.contains("is-open");
            closeSections();
            if (open) {
                item.classList.add("is-open");
                button.setAttribute("aria-expanded", "true");
            }
        });
    });
    window.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            setMenu(false);
        }
    });
    window.matchMedia("(min-width: 1100px)").addEventListener("change", function (event) {
        if (event.matches) {
            setMenu(false);
        }
    });
})();
</script>
