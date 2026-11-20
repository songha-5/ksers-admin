<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);
$year = ksers_admin_year($pdo);
$page_title = 'Scientific Program';
$page_heading = 'Scientific Program';
$id = isset($_GET['id']) ? (string)$_GET['id'] : '';
$bundle = ksers_scientific_load($pdo, $year);
$session = null;
foreach ($bundle['sessions'] as $row) {
    if ($row['id'] === $id) {
        $session = $row;
    }
}
if ($id !== '' && !$session) {
    header('Location: /admin/page/scientific/index.php');
    exit;
}
$chairs = [];
$speakers = [];
if ($session) {
    foreach ($bundle['chairs'] as $chair) {
        if ($chair['session_id'] === $session['id']) {
            $chairs[] = $chair;
        }
    }
    foreach ($bundle['speakers'] as $speaker) {
        if ($speaker['session_id'] === $session['id']) {
            $speakers[] = $speaker;
        }
    }
}
$glancePrefill = isset($_GET['glance']) ? (string)$_GET['glance'] : '';
$categoryPrefill = isset($_GET['category']) ? (string)$_GET['category'] : ksers_scientific_free_id();
if (!$session) {
    $session = [
        'id' => '',
        'category_id' => $categoryPrefill,
        'title' => '',
        'date_time' => '',
        'language' => '',
        'glance_id' => $glancePrefill,
        'sort_order' => 0,
    ];
    if ($glancePrefill !== '') {
        $info = ksers_scientific_glance_info($pdo, $year, $glancePrefill);
        if ($info) {
            $session['title'] = ksers_scientific_seed_title($info['title'], (string)$info['content']);
            $session['date_time'] = (string)ksers_scientific_date_time($info['day_label'], $info['time_label']);
            $session['id'] = preg_match('/^[A-Z]{1,6}\d+$/', trim((string)$info['title']))
                ? strtolower(trim((string)$info['title']))
                : ksers_scientific_slug($info['title']);
            list($session['category_id']) = ksers_scientific_category_for_cell($info['title']);
        }
    }
}
$chairs[] = ['id' => '', 'name' => ''];
$speakers[] = [
    'id' => '',
    'badge' => '',
    'talk_title' => '',
    'name' => '',
    'affiliation' => '',
    'description' => '',
    'image_url' => '',
    'cv_url' => '',
    'abstract_url' => '',
];
$programKnown = [];
foreach ($bundle['sessions'] as $row) {
    $programKnown[] = ['id' => $row['id'], 'label' => (string)$row['title']];
}
if (!empty($_SESSION['admin_scientific_draft']) && is_array($_SESSION['admin_scientific_draft'])) {
    $sessionDraft = $_SESSION['admin_scientific_draft'];
    $draftOriginal = isset($sessionDraft['original_id']) ? (string)$sessionDraft['original_id'] : '';
    if ($draftOriginal === $id) {
        foreach (['id', 'category_id', 'title', 'date_time', 'language', 'glance_id'] as $draftKey) {
            if (array_key_exists($draftKey, $sessionDraft)) {
                $session[$draftKey] = (string)$sessionDraft[$draftKey];
            }
        }
    }
}
unset($_SESSION['admin_scientific_draft']);
$linked = ksers_scientific_ids_by_glance($pdo, $year);
$glanceRows = ksers_scientific_glance_rows($pdo, $year);
$notice = isset($_SESSION['admin_scientific_notice']) ? (string)$_SESSION['admin_scientific_notice'] : '';
$noticeKind = (isset($_SESSION['admin_scientific_notice_kind']) && $_SESSION['admin_scientific_notice_kind'] === 'warn') ? 'warn' : 'ok';
unset($_SESSION['admin_scientific_notice'], $_SESSION['admin_scientific_notice_kind']);

include '../../lib/header.php';
include '../../lib/menu.php';
?>
<style>
.sci-help { color: #52606d; }
.sci-card { border: 1px solid #e4e7eb; border-radius: 12px; padding: 14px; background: #fff; }
.sci-file { font-size: .85rem; color: #0b3f86; }
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

<div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
    <a class="btn btn-sm btn-outline-secondary" href="/admin/page/scientific/index.php?tab=<?= h($session['category_id']) ?>"><?= h(t('목록으로', 'Back to list')) ?></a>
    <?php if ($session['id'] !== '') { ?>
    <div class="sci-help small align-self-center"><?= h(ksers_scientific_href($year, $session['id'])) ?></div>
    <?php } ?>
</div>

<form method="post" action="action.php" enctype="multipart/form-data" class="d-flex flex-column gap-3" id="sci-session-form" novalidate>
    <input type="hidden" name="action" value="save_session">
    <input type="hidden" name="original_id" value="<?= h($id) ?>">
    <div class="sci-card">
        <h2 class="h6"><?= h($id === '' ? t('프로그램 추가', 'Add program') : t('프로그램 수정', 'Edit program')) ?></h2>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label"><?= h(t('프로그램 구분값', 'Program id')) ?></label>
                <?php if ($id !== '') { ?>
                <input class="form-control" value="<?= h($id) ?>" readonly>
                <?php } else { ?>
                <?= ksers_glance_new_id_input('id', 'vp01', array_column($programKnown, 'id'), $session['id']) ?>
                <?= ksers_glance_known_list($programKnown) ?>
                <?php } ?>
                <div class="form-text"><?= h(t('일정표 링크의 # 뒤입니다. 저장 후 바꾸지 않습니다.', 'This is the text after # in the timetable link. It stays fixed after saving.')) ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= h(t('대분류', 'Category')) ?></label>
                <select class="form-select" name="category_id">
                    <?php foreach ($bundle['categories'] as $category) { ?>
                    <option value="<?= h($category['id']) ?>" <?= $session['category_id'] === $category['id'] ? 'selected' : '' ?>><?= h($category['label']) ?></option>
                    <?php } ?>
                </select>
                <div class="form-text"><?= h(t('새 프로그램의 기본 탭은 Free Paper입니다.', 'A new program starts in Free Paper.')) ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= h(t('언어', 'Language')) ?></label>
                <input class="form-control" name="language" value="<?= h($session['language']) ?>" placeholder="Kor">
                <div class="form-text"><?= h(t('비우면 프론트에서 언어 줄이 숨습니다.', 'Leave this empty and the public page hides the language line.')) ?></div>
            </div>
            <div class="col-12">
                <label class="form-label"><?= h(t('제목', 'Title')) ?></label>
                <input class="form-control" name="title" required value="<?= h($session['title']) ?>">
            </div>
            <div class="col-md-7">
                <label class="form-label"><?= h(t('일정표 칸', 'Timetable cell')) ?></label>
                <select class="form-select" name="glance_id" id="sci-glance">
                    <option value=""><?= h(t('연결 안 함', 'Not linked')) ?></option>
                    <?php foreach ($glanceRows as $cell) {
                        $owner = isset($linked[$cell['id']]) ? $linked[$cell['id']] : '';
                        if ($owner !== '' && $owner !== $id) {
                            continue;
                        }
                        $when = ksers_scientific_date_time($cell['day_label'], $cell['time_label']);
                        $text = trim($cell['title'] . ' ' . $cell['content']) . ' · ' . $when;
                    ?>
                    <option value="<?= h($cell['id']) ?>" data-time="<?= h($when) ?>" <?= $session['glance_id'] === $cell['id'] ? 'selected' : '' ?>><?= h($text) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label"><?= h(t('날짜·시간', 'Date / time')) ?></label>
                <input class="form-control" name="date_time" id="sci-time" value="<?= h($session['date_time']) ?>" <?= $session['glance_id'] ? 'readonly' : '' ?>>
                <div class="form-text"><?= h(t('일정표와 연결하면 그 칸의 시간이 여기 표시되고, 링크도 칸에 자동으로 들어갑니다.', 'A linked cell shows its time here and receives the program link automatically.')) ?></div>
            </div>
        </div>
    </div>

    <div class="sci-card">
        <h2 class="h6"><?= h(t('좌장', 'Chairs')) ?></h2>
        <p class="sci-help"><?= h(t('한 명이면 Chair, 두 명 이상이면 Chairs로 프론트가 표시합니다. 빈 줄은 저장되지 않습니다.', 'One name is shown as Chair. Two or more are shown as Chairs. Blank rows are not saved.')) ?></p>
        <div class="d-flex flex-column gap-2">
            <?php foreach ($chairs as $chair) { ?>
            <div class="row g-2">
                <input type="hidden" name="chair_id[]" value="<?= h($chair['id']) ?>">
                <div class="col-md-8">
                    <input class="form-control" name="chair_name[]" value="<?= h($chair['name']) ?>" placeholder="<?= h(t('이름과 소속', 'Name and affiliation')) ?>">
                </div>
            </div>
            <?php } ?>
        </div>
    </div>

    <div class="sci-card">
        <h2 class="h6"><?= h(t('카드', 'Cards')) ?></h2>
        <p class="sci-help"><?= h(t('카드 한 장에 사진 한 장입니다. 사진이 있으면 사진 카드, 없으면 글 카드입니다. CV 또는 Abstract 파일을 올리면 그 버튼만 나옵니다.', 'Each card has one photo. A photo makes a photo card. Without one, it is a text card. A CV or Abstract file shows only that button.')) ?></p>
        <div class="d-flex flex-column gap-3">
            <?php foreach ($speakers as $index => $speaker) { ?>
            <div class="border rounded p-3">
                <input type="hidden" name="speaker_id[]" value="<?= h($speaker['id']) ?>">
                <div class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label"><?= h(t('배지', 'Badge')) ?></label>
                        <input class="form-control" name="speaker_badge[]" value="<?= h($speaker['badge']) ?>" placeholder="KL01">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label"><?= h(t('발표 제목', 'Talk title')) ?></label>
                        <input class="form-control" name="speaker_talk[]" value="<?= h($speaker['talk_title']) ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label"><?= h(t('연자', 'Speaker')) ?></label>
                        <input class="form-control" name="speaker_name[]" value="<?= h($speaker['name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= h(t('소속', 'Affiliation')) ?></label>
                        <input class="form-control" name="speaker_affiliation[]" value="<?= h($speaker['affiliation']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= h(t('설명', 'Description')) ?></label>
                        <input class="form-control" name="speaker_description[]" value="<?= h($speaker['description']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= h(t('사진 1장', 'One photo')) ?></label>
                        <input class="form-control" type="file" name="speaker_image[]" accept=".jpg,.jpeg,.png,.webp,.gif">
                        <?php if ($speaker['image_url']) { ?><div class="sci-file mt-1"><?= h(t('사진 있음', 'Photo attached')) ?></div><label class="small"><input type="checkbox" name="clear_image[]" value="<?= h($speaker['id']) ?>"> <?= h(t('사진 빼기', 'Remove photo')) ?></label><?php } ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">CV</label>
                        <input class="form-control" type="file" name="speaker_cv[]" accept=".pdf,application/pdf">
                        <?php if ($speaker['cv_url']) { ?><div class="sci-file mt-1"><?= h(t('CV 버튼 나옴', 'CV button will show')) ?></div><label class="small"><input type="checkbox" name="clear_cv[]" value="<?= h($speaker['id']) ?>"> <?= h(t('CV 빼기', 'Remove CV')) ?></label><?php } else { ?><div class="form-text"><?= h(t('파일이 없으면 버튼이 없습니다.', 'No file means no button.')) ?></div><?php } ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Abstract</label>
                        <input class="form-control" type="file" name="speaker_abstract[]" accept=".pdf,application/pdf">
                        <?php if ($speaker['abstract_url']) { ?><div class="sci-file mt-1"><?= h(t('Abstract 버튼 나옴', 'Abstract button will show')) ?></div><label class="small"><input type="checkbox" name="clear_abstract[]" value="<?= h($speaker['id']) ?>"> <?= h(t('Abstract 빼기', 'Remove Abstract')) ?></label><?php } else { ?><div class="form-text"><?= h(t('파일이 없으면 버튼이 없습니다.', 'No file means no button.')) ?></div><?php } ?>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary-custom" type="submit"><?= h(t('저장', 'Save')) ?></button>
        <?php if ($id !== '') { ?>
        <button class="btn btn-outline-danger" type="submit" name="action" value="delete_session" formnovalidate onclick="return confirm(<?= json_encode(t('이 프로그램과 좌장, 카드를 지울까요? 연결된 일정표 링크도 빠집니다.', 'Delete this program, its chairs, and its cards? The timetable link is removed too.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);"><?= h(t('삭제', 'Delete')) ?></button>
        <?php } ?>
    </div>
</form>
<script>
(function () {
    var glance = document.getElementById('sci-glance');
    var time = document.getElementById('sci-time');
    if (!glance || !time) return;
    function paint() {
        var option = glance.options[glance.selectedIndex];
        var linked = glance.value !== '';
        time.readOnly = linked;
        if (linked && option && option.getAttribute('data-time')) {
            time.value = option.getAttribute('data-time');
        }
    }
    glance.addEventListener('change', paint);
})();
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
document.getElementById('sci-session-form').addEventListener('submit', function (event) {
    var form = event.currentTarget;
    var submitter = event.submitter;
    if (submitter && submitter.name === 'action' && String(submitter.value).indexOf('delete_') === 0) return;
    var idInput = form.querySelector('[data-taken]');
    if (idInput) {
        var value = idInput.value.trim().toLowerCase();
        if (!/^[a-z0-9][a-z0-9-]{0,79}$/.test(value)) {
            event.preventDefault();
            sciWarn(<?= json_encode(t('저장하지 않았습니다. 프로그램 구분값은 영문 소문자·숫자·하이픈입니다.', 'Not saved. A program id uses lowercase letters, numbers, and hyphens.'), JSON_UNESCAPED_UNICODE) ?>);
            return;
        }
        var taken = idInput.getAttribute('data-taken').split(',').filter(Boolean);
        if (taken.indexOf(value) !== -1) {
            event.preventDefault();
            sciWarn(<?= json_encode(t('저장하지 않았습니다. 같은 구분값이 이미 있습니다. 다른 구분값을 입력하세요.', 'Not saved. That id already exists. Enter a different id.'), JSON_UNESCAPED_UNICODE) ?>);
            return;
        }
    }
    var title = form.querySelector('[name="title"]');
    if (!title || title.value.trim() === '') {
        event.preventDefault();
        sciWarn(<?= json_encode(t('저장하지 않았습니다. 제목을 입력해 주세요.', 'Not saved. Enter a title.'), JSON_UNESCAPED_UNICODE) ?>);
        return;
    }
    var talks = form.querySelectorAll('[name="speaker_talk[]"]');
    var names = form.querySelectorAll('[name="speaker_name[]"]');
    var badges = form.querySelectorAll('[name="speaker_badge[]"]');
    var affiliations = form.querySelectorAll('[name="speaker_affiliation[]"]');
    var descriptions = form.querySelectorAll('[name="speaker_description[]"]');
    var images = form.querySelectorAll('[name="speaker_image[]"]');
    var cvs = form.querySelectorAll('[name="speaker_cv[]"]');
    var abstracts = form.querySelectorAll('[name="speaker_abstract[]"]');
    for (var i = 0; i < talks.length; i++) {
        var talk = talks[i].value.trim();
        var name = names[i] ? names[i].value.trim() : '';
        var extra = (badges[i] && badges[i].value.trim()) || (affiliations[i] && affiliations[i].value.trim()) || (descriptions[i] && descriptions[i].value.trim());
        var file = (images[i] && images[i].files && images[i].files.length) || (cvs[i] && cvs[i].files && cvs[i].files.length) || (abstracts[i] && abstracts[i].files && abstracts[i].files.length);
        if ((talk || name || extra || file) && (!talk || !name)) {
            event.preventDefault();
            sciWarn(<?= json_encode(t('저장하지 않았습니다. 카드에는 발표 제목과 연자 이름이 있어야 합니다.', 'Not saved. A card needs a talk title and a speaker name.'), JSON_UNESCAPED_UNICODE) ?>);
            return;
        }
    }
});
</script>

<?php include '../../lib/footer.php'; ?>
