<?php
$registrationTab = isset($registrationTab) ? $registrationTab : 'list';
?>
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link<?= $registrationTab === 'list' ? ' active' : '' ?>" href="/admin/page/registration/list.php"><?= h(t('참가 등록 조회', 'Registration lookup')) ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link<?= $registrationTab === 'review' ? ' active' : '' ?>" href="/admin/page/registration/review.php"><?= h(t('증빙 확인 처리', 'Document review')) ?></a>
    </li>
</ul>
