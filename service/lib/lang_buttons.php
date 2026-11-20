<?php
$langBtnClass = isset($langBtnClass) ? $langBtnClass : 'btn btn-sm btn-outline-light';
?>
<div class="btn-group btn-group-sm" role="group">
    <a href="/service/lang.php?lang=ko" class="<?= h($langBtnClass) ?> <?= ksers_lang() === 'ko' ? 'active' : '' ?>">국문</a>
    <a href="/service/lang.php?lang=en" class="<?= h($langBtnClass) ?> <?= ksers_lang() === 'en' ? 'active' : '' ?>">ENG</a>
</div>
