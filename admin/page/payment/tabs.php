<?php
$paymentTab = isset($paymentTab) ? $paymentTab : 'history';
?>
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link<?= $paymentTab === 'history' ? ' active' : '' ?>" href="/admin/page/payment/list.php"><?= h(t('결제 내역', 'Payment history')) ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link<?= $paymentTab === 'rule' ? ' active' : '' ?>" href="/admin/page/payment/rule.php"><?= h(t('결제 기준 등록', 'Payment rules')) ?></a>
    </li>
</ul>
