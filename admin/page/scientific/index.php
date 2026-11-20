<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);
$year = ksers_admin_year($pdo);
$page_title = 'Scientific Program';
$page_heading = 'Scientific Program';
$bundle = ksers_scientific_load($pdo, $year);
$categories = $bundle['categories'];
$tab = isset($_GET['tab']) ? (string)$_GET['tab'] : '';
$knownTab = false;
foreach ($categories as $category) {
    if ($category['id'] === $tab) {
        $knownTab = true;
    }
}
if (!$knownTab) {
    $tab = $categories ? $categories[0]['id'] : ksers_scientific_free_id();
}
$edit = isset($_GET['edit']) ? (string)$_GET['edit'] : '';
$editId = isset($_GET['id']) ? (string)$_GET['id'] : '';
$categoryRecord = ['id' => '', 'label' => '', 'original_id' => ''];
foreach ($categories as $category) {
    if ($edit === 'category' && $category['id'] === $editId) {
        $categoryRecord = [
            'id' => $category['id'],
            'label' => $category['label'],
            'original_id' => $category['id'],
        ];
    }
}
$sessions = [];
foreach ($bundle['sessions'] as $session) {
    if ($session['category_id'] === $tab) {
        $sessions[] = $session;
    }
}
$glanceLabel = [];
foreach (ksers_scientific_glance_rows($pdo, $year) as $cell) {
    $glanceLabel[$cell['id']] = trim($cell['title'] . ' ' . $cell['content']);
}
$categoryKnown = [];
foreach ($categories as $category) {
    $categoryKnown[] = ['id' => $category['id'], 'label' => $category['label']];
}
if ($edit === 'new-category' && $categoryRecord['original_id'] === '' && !empty($_SESSION['admin_scientific_category_draft']) && is_array($_SESSION['admin_scientific_category_draft'])) {
    $categoryDraft = $_SESSION['admin_scientific_category_draft'];
    if (isset($categoryDraft['id'])) {
        $categoryRecord['id'] = (string)$categoryDraft['id'];
    }
    if (isset($categoryDraft['label'])) {
        $categoryRecord['label'] = (string)$categoryDraft['label'];
    }
}
unset($_SESSION['admin_scientific_category_draft']);
$notice = isset($_SESSION['admin_scientific_notice']) ? (string)$_SESSION['admin_scientific_notice'] : '';
$noticeKind = (isset($_SESSION['admin_scientific_notice_kind']) && $_SESSION['admin_scientific_notice_kind'] === 'warn') ? 'warn' : 'ok';
unset($_SESSION['admin_scientific_notice'], $_SESSION['admin_scientific_notice_kind']);

include '../../lib/header.php';
include '../../lib/menu.php';
?>
<style>
.sci-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.sci-tab { display: flex; align-items: center; gap: 6px; border: 1px solid #c5ced6; border-radius: 999px; padding: 4px 10px; background: #fff; }
.sci-tab.active { background: #0b3f86; border-color: #0b3f86; color: #fff; }
.sci-tab a { color: inherit; text-decoration: none; }
.sci-move { border: 1px solid #c5ced6; background: #fff; color: #3e4a54; border-radius: 999px; width: 28px; height: 28px; }
.sci-card { border: 1px solid #e4e7eb; border-radius: 12px; padding: 14px 16px; background: #fff; }
.sci-help { color: #52606d; }
.glance-known { margin-top: 6px; }
.glance-known summary { cursor: pointer; color: #52606d; font-size: .8rem; }
.glance-known div { margin-top: 4px; max-height: 7.5rem; overflow: auto; color: #52606d; font-size: .8rem; line-height: 1.45; }
.sci-toast {
    position: fixed;
    top: 16px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1080;
    width: min(560px, calc(100% - 24px));
    padding: 14px 16px;
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(0, 0, 0, .18);
}
.sci-toast-warn { background: #fff7ed; border: 1px solid #f59e0b; color: #7c2d12; }
.sci-toast-ok { background: #f0fdf4; border: 1px solid #86efac; color: #14532d; }
</style>
<?php if ($notice !== '') { ?>
<div class="sci-toast sci-toast-<?= $noticeKind ?>" role="alert">
    <div class="d-flex gap-3 align-items-start">
        <div><?= h($notice) ?></div>
        <button class="btn-close ms-auto" type="button" aria-label="<?= h(t('닫기', 'Close')) ?>" onclick="this.closest('.sci-toast').remove()"></button>
    </div>
</div>
<?php if ($noticeKind === 'ok') { ?>
<script>setTimeout(function () { var toast = document.querySelector('.sci-toast'); if (toast) toast.remove(); }, 4000);</script>
<?php } } ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-3">
            <div>
                <h2 class="h5 mb-1"><?= h($year . ' Scientific Program') ?></h2>
                <p class="sci-help mb-0"><?= h(t('위 알약은 프론트 상단 탭입니다. Free Paper는 항상 마지막이고, 대분류를 고르지 않은 프로그램은 그 탭에 들어갑니다. 일정표와 연결하면 시간과 링크가 칸에 자동으로 들어갑니다.', 'The pills are the public tabs. Free Paper stays last. A program without a category goes there. Linking a timetable cell fills its time and link.')) ?></p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="/admin/page/scientific/index.php?edit=new-category"><?= h(t('대분류 추가', 'Add category')) ?></a>
                <a class="btn btn-sm btn-outline-secondary" href="/admin/page/scientific/edit.php?category=<?= h($tab) ?>"><?= h(t('프로그램 추가', 'Add program')) ?></a>
                <form method="post" action="action.php" onsubmit="return confirm(<?= json_encode(t('일정표에 있는 칸 중 아직 연결되지 않은 것을 프로그램으로 넣을까요? 사진, CV, Abstract는 비웁니다.', 'Create programs for timetable cells that are not linked yet? Photos, CVs, and abstracts stay empty.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
                    <input type="hidden" name="action" value="seed_glance">
                    <button class="btn btn-sm btn-primary-custom" type="submit"><?= h(t('일정표에서 프로그램 만들기', 'Create from timetable')) ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="sci-tabs mb-4">
    <?php foreach ($categories as $category) {
        $active = $category['id'] === $tab;
        $locked = $category['id'] === ksers_scientific_free_id();
    ?>
    <div class="sci-tab <?= $active ? 'active' : '' ?>">
        <?php if (!$locked) { ?>
        <form method="post" action="action.php" class="m-0">
            <input type="hidden" name="action" value="move_category">
            <input type="hidden" name="id" value="<?= h($category['id']) ?>">
            <input type="hidden" name="direction" value="left">
            <input type="hidden" name="tab" value="<?= h($tab) ?>">
            <button class="sci-move" type="submit">←</button>
        </form>
        <?php } ?>
        <a href="/admin/page/scientific/index.php?tab=<?= h($category['id']) ?>"><?= h($category['label']) ?></a>
        <?php if (!$locked) { ?>
        <a class="small" href="/admin/page/scientific/index.php?tab=<?= h($tab) ?>&edit=category&id=<?= h($category['id']) ?>"><?= h(t('수정', 'Edit')) ?></a>
        <form method="post" action="action.php" class="m-0">
            <input type="hidden" name="action" value="move_category">
            <input type="hidden" name="id" value="<?= h($category['id']) ?>">
            <input type="hidden" name="direction" value="right">
            <input type="hidden" name="tab" value="<?= h($tab) ?>">
            <button class="sci-move" type="submit">→</button>
        </form>
        <?php } ?>
    </div>
    <?php } ?>
</div>

<?php if ($edit === 'new-category' || $edit === 'category') { ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h3 class="h6"><?= h($categoryRecord['original_id'] === '' ? t('대분류 추가', 'Add category') : t('대분류 수정', 'Edit category')) ?></h3>
        <p class="sci-help"><?= h(t('구분값이 프론트 주소의 # 뒤가 됩니다. 한 번 정하면 바꾸지 않습니다.', 'The id becomes the text after # in the public address. It stays fixed after the first save.')) ?></p>
        <form method="post" action="action.php" class="row g-3" novalidate>
            <input type="hidden" name="action" value="save_category">
            <input type="hidden" name="original_id" value="<?= h($categoryRecord['original_id']) ?>">
            <div class="col-md-4">
                <label class="form-label"><?= h(t('구분값', 'Id')) ?></label>
                <?php if ($categoryRecord['original_id'] !== '') { ?>
                <input class="form-control" value="<?= h($categoryRecord['original_id']) ?>" readonly>
                <?php } else { ?>
                <?= ksers_glance_new_id_input('id', 'keynote-lecture', array_column($categoryKnown, 'id'), $categoryRecord['id']) ?>
                <?= ksers_glance_known_list($categoryKnown) ?>
                <?php } ?>
            </div>
            <div class="col-md-5">
                <label class="form-label"><?= h(t('탭에 보이는 글', 'Tab label')) ?></label>
                <input class="form-control" name="label" required value="<?= h($categoryRecord['label']) ?>" <?= $categoryRecord['original_id'] === ksers_scientific_free_id() ? 'readonly' : '' ?>>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <?php if ($categoryRecord['original_id'] !== ksers_scientific_free_id()) { ?>
                <button class="btn btn-primary-custom" type="submit"><?= h(t('저장', 'Save')) ?></button>
                <?php } ?>
                <?php if ($categoryRecord['original_id'] !== '' && $categoryRecord['original_id'] !== ksers_scientific_free_id()) { ?>
                <button class="btn btn-outline-danger" type="submit" name="action" value="delete_category" formnovalidate onclick="return confirm(<?= json_encode(t('이 대분류와 안의 프로그램을 모두 지울까요?', 'Delete this category and every program inside it?'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);"><?= h(t('삭제', 'Delete')) ?></button>
                <?php } ?>
            </div>
            <?php if ($categoryRecord['original_id'] !== '') { ?>
            <div class="col-12 sci-help"><?= h(ksers_scientific_href($year, $categoryRecord['original_id'])) ?></div>
            <?php } ?>
        </form>
    </div>
</div>
<?php } ?>

<div class="d-flex flex-column gap-3">
    <?php if (!$sessions) { ?>
    <div class="sci-card sci-help"><?= h(t('이 탭에는 프로그램이 없습니다.', 'This tab has no programs.')) ?></div>
    <?php } ?>
    <?php foreach ($sessions as $session) { ?>
    <div class="sci-card">
        <div class="d-flex justify-content-between flex-wrap gap-2">
            <div>
                <div class="fw-bold"><?= h($session['title']) ?></div>
                <div class="sci-help small"><?= h($session['date_time'] ? $session['date_time'] : t('시간 없음', 'No time')) ?></div>
                <div class="small mt-1"><?= h(ksers_scientific_href($year, $session['id'])) ?></div>
                <?php if ($session['glance_id']) { ?>
                <div class="small"><?= h(t('일정표 칸', 'Timetable cell')) ?>: <?= h(isset($glanceLabel[$session['glance_id']]) ? $glanceLabel[$session['glance_id']] : $session['glance_id']) ?></div>
                <?php } ?>
            </div>
            <div class="d-flex gap-2 align-items-start">
                <form method="post" action="action.php" class="m-0">
                    <input type="hidden" name="action" value="move_session">
                    <input type="hidden" name="id" value="<?= h($session['id']) ?>">
                    <input type="hidden" name="direction" value="up">
                    <button class="sci-move" type="submit">↑</button>
                </form>
                <form method="post" action="action.php" class="m-0">
                    <input type="hidden" name="action" value="move_session">
                    <input type="hidden" name="id" value="<?= h($session['id']) ?>">
                    <input type="hidden" name="direction" value="down">
                    <button class="sci-move" type="submit">↓</button>
                </form>
                <a class="btn btn-sm btn-outline-secondary" href="/admin/page/scientific/edit.php?id=<?= h($session['id']) ?>"><?= h(t('수정', 'Edit')) ?></a>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<script>
function sciWarn(message) {
    var oldToast = document.querySelector('.sci-toast');
    if (oldToast) oldToast.remove();
    var toast = document.createElement('div');
    toast.className = 'sci-toast sci-toast-warn';
    toast.setAttribute('role', 'alert');
    var row = document.createElement('div');
    row.className = 'd-flex gap-3 align-items-start';
    var text = document.createElement('div');
    text.textContent = message;
    var close = document.createElement('button');
    close.className = 'btn-close ms-auto';
    close.type = 'button';
    close.setAttribute('aria-label', <?= json_encode(t('닫기', 'Close'), JSON_UNESCAPED_UNICODE) ?>);
    close.addEventListener('click', function () { toast.remove(); });
    row.appendChild(text);
    row.appendChild(close);
    toast.appendChild(row);
    document.body.appendChild(toast);
}
document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        var submitter = event.submitter;
        if (submitter && submitter.name === 'action' && String(submitter.value).indexOf('delete_') === 0) return;
        var action = form.querySelector('input[name="action"]');
        if (!action || action.value !== 'save_category') return;
        var idInput = form.querySelector('[data-taken]');
        if (idInput) {
            var value = idInput.value.trim().toLowerCase();
            if (!/^[a-z0-9][a-z0-9-]{0,79}$/.test(value)) {
                event.preventDefault();
                sciWarn(<?= json_encode(t('저장하지 않았습니다. 대분류 구분값은 영문 소문자·숫자·하이픈이고, 탭 글이 있어야 합니다.', 'Not saved. A category id uses lowercase letters, numbers, and hyphens, and the tab needs a label.'), JSON_UNESCAPED_UNICODE) ?>);
                return;
            }
            var taken = idInput.getAttribute('data-taken').split(',').filter(Boolean);
            if (taken.indexOf(value) !== -1) {
                event.preventDefault();
                sciWarn(<?= json_encode(t('저장하지 않았습니다. 같은 구분값이 이미 있습니다. 다른 구분값을 입력하세요.', 'Not saved. That id already exists. Enter a different id.'), JSON_UNESCAPED_UNICODE) ?>);
                return;
            }
        }
        var label = form.querySelector('[name="label"]');
        if (label && label.value.trim() === '') {
            event.preventDefault();
            sciWarn(<?= json_encode(t('저장하지 않았습니다. 대분류 구분값은 영문 소문자·숫자·하이픈이고, 탭 글이 있어야 합니다.', 'Not saved. A category id uses lowercase letters, numbers, and hyphens, and the tab needs a label.'), JSON_UNESCAPED_UNICODE) ?>);
        }
    });
});
</script>

<?php include '../../lib/footer.php'; ?>
