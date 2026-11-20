<?php
$abstractTab = isset($abstractTab) ? $abstractTab : 'list';
?>
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link<?= $abstractTab === 'list' ? ' active' : '' ?>" href="/admin/page/abstract/list.php"><?= h(t('초록 조회 및 변경', 'Abstracts and changes')) ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link<?= $abstractTab === 'review' ? ' active' : '' ?>" href="/admin/page/abstract/review.php"><?= h(t('초록 심사 및 영상 확인', 'Abstract review and videos')) ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link<?= $abstractTab === 'setting' ? ' active' : '' ?>" href="/admin/page/abstract/setting.php"><?= h(t('초록 설정', 'Abstract settings')) ?></a>
    </li>
</ul>
