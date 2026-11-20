<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);
$year = ksers_admin_year($pdo);
$page_title = 'Program at a Glance';
$page_heading = 'Program at a Glance';

$bundle = ksers_glance_load($pdo, $year);
$scientificByGlance = ksers_scientific_ids_by_glance($pdo, $year);
$rooms = $bundle['rooms'];
$days = $bundle['days'];
$dayId = isset($_GET['day']) ? (string)$_GET['day'] : '';
$knownDay = false;
foreach ($days as $day) {
    if ($day['id'] === $dayId) {
        $knownDay = true;
    }
}
if (!$knownDay) {
    $dayId = $days ? $days[0]['id'] : '';
}
$slots = ($dayId !== '' && isset($bundle['slotsByDay'][$dayId])) ? $bundle['slotsByDay'][$dayId] : [];
$daySessions = [];
foreach ($slots as $slot) {
    if (empty($bundle['sessionsBySlot'][$slot['id']])) {
        continue;
    }
    foreach ($bundle['sessionsBySlot'][$slot['id']] as $session) {
        $daySessions[] = $session;
    }
}
$grid = ksers_glance_grid($rooms, $slots, $daySessions);
$edit = isset($_GET['edit']) ? (string)$_GET['edit'] : '';
$editId = isset($_GET['id']) ? (string)$_GET['id'] : '';
$colorNames = ksers_glance_color_names();
$roomKnown = [];
foreach ($rooms as $room) {
    $roomKnown[] = ['id' => $room['code'], 'label' => trim($room['label'] . ' ' . $room['floor'])];
}
$dayKnown = [];
foreach ($days as $day) {
    $dayKnown[] = ['id' => $day['id'], 'label' => $day['label']];
}
$slotKnown = [];
foreach ($bundle['slots'] as $slot) {
    $slotKnown[] = ['id' => $slot['id'], 'label' => $slot['time_label']];
}
$sessionKnown = [];
foreach ($bundle['sessions'] as $session) {
    $sessionKnown[] = ['id' => $session['id'], 'label' => trim((string)$session['title'])];
}

function ksers_glance_page_url($query) {
    return '/admin/page/glance/index.php?' . http_build_query($query);
}

include '../../lib/header.php';
include '../../lib/menu.php';
?>

<style>
.glance-help { color: #52606d; }
.glance-days { display: flex; gap: 8px; flex-wrap: wrap; }
.glance-day {
    flex: 1 1 160px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 46px;
    padding: 8px 12px;
    border: 1px solid #d9dee5;
    background: #f7f8fa;
    color: #3e4a54;
    font-weight: 700;
    text-decoration: none;
}
.glance-day.active { background: #0b3f86; border-color: #0b3f86; color: #fff; }
.glance-day .glance-move { color: inherit; border-color: currentColor; }
.glance-table { width: 100%; min-width: 72rem; border-collapse: collapse; table-layout: fixed; background: #fff; }
.glance-table th, .glance-table td { min-width: 8rem; border: 1px solid #e4e7eb; text-align: center; vertical-align: middle; }
.glance-floor, .glance-room, .glance-time {
    background: #3e4a54;
    color: #fff;
    font-weight: 700;
    padding: 10px 6px;
}
.glance-room { font-weight: 650; }
.glance-room a, .glance-time a { color: #fff; text-decoration: none; }
.glance-room small { display: block; font-weight: 500; opacity: .9; }
.glance-time { width: 118px; white-space: nowrap; }
.glance-cell { position: relative; height: 92px; padding: 8px 6px; }
.glance-hit { position: absolute; inset: 0; z-index: 1; }
.glance-program { position: absolute; right: 4px; bottom: 4px; z-index: 3; font-size: 11px; line-height: 1.2; background: #fff; color: #0b3f86; border-radius: 4px; padding: 2px 6px; text-decoration: none; }
.glance-body { position: relative; z-index: 2; pointer-events: none; }
.glance-body a { color: inherit; }
.glance-title { font-weight: 800; line-height: 1.25; }
.glance-link { text-decoration: underline; }
.glance-content { margin-top: 2px; font-size: .92rem; line-height: 1.3; }
.glance-topic { margin-top: 3px; color: #8b939c; font-size: .78rem; line-height: 1.25; }
.glance-badge {
    display: inline-block;
    margin-bottom: 3px;
    padding: 0 6px;
    border-radius: 999px;
    background: rgba(0, 0, 0, .08);
    font-size: .72rem;
}
.glance-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 92px;
    color: #9aa3ad;
    text-decoration: none;
    font-size: 1.4rem;
}
.glance-empty:hover, .glance-cell:hover { background-image: linear-gradient(rgba(9, 66, 143, .06), rgba(9, 66, 143, .06)); }
.glance-selected { outline: 3px solid #0b3f86; outline-offset: -3px; }
.glance-move {
    border: 1px solid rgba(255, 255, 255, .45);
    background: transparent;
    color: inherit;
    border-radius: 4px;
    line-height: 1;
    padding: 0 4px;
    font-size: 12px;
}
.glance-day .glance-move { border-color: #c5ced6; background: #fff; color: #3e4a54; }
.glance-swatch { width: 28px; height: 28px; border-radius: 6px; border: 1px solid rgba(0,0,0,.12); display: inline-block; }
.glance-c-gray { background: #eceff1; }
.glance-c-light-green { background: #d7efc2; }
.glance-c-purple { background: #e6dff6; }
.glance-c-light-blue { background: #d7ebf8; }
.glance-c-green { background: #bfe3c8; }
.glance-c-red { background: #f6d0d0; }
.glance-c-orange { background: #f8e0b0; }
.glance-c-indigo { background: #d9dff6; }
.glance-c-pink { background: #f8d3de; }
.glance-c-dark-yellow { background: #f3e4a0; }
.glance-c-mint { background: #d2f3e4; }
.glance-c-blue { background: #c9def8; }
.glance-c-none { background: #fff; }
.glance-sample { width: 180px; min-height: 88px; }
.glance-known { margin-top: 6px; }
.glance-known summary { cursor: pointer; color: #52606d; font-size: .8rem; }
.glance-known div { margin-top: 4px; max-height: 7.5rem; overflow: auto; color: #52606d; font-size: .8rem; line-height: 1.45; }
.glance-locked { background: #f4f6f8; }
.glance-toast {
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
.glance-toast-warn { background: #fff7ed; border: 1px solid #f59e0b; color: #7c2d12; }
.glance-toast-ok { background: #f0fdf4; border: 1px solid #86efac; color: #14532d; }
</style>

<?php
$glanceImport = (isset($_SESSION['admin_glance_import']) && is_array($_SESSION['admin_glance_import'])) ? $_SESSION['admin_glance_import'] : null;
$glanceImportOk = is_array($glanceImport) && !empty($glanceImport['ok']);
$glanceImportMessage = is_array($glanceImport) && isset($glanceImport['message']) ? (string)$glanceImport['message'] : '';
unset($_SESSION['admin_glance_import']);
$glanceNotice = isset($_SESSION['admin_glance_notice']) ? (string)$_SESSION['admin_glance_notice'] : '';
$glanceNoticeKind = (isset($_SESSION['admin_glance_notice_kind']) && $_SESSION['admin_glance_notice_kind'] === 'warn') ? 'warn' : 'ok';
unset($_SESSION['admin_glance_notice'], $_SESSION['admin_glance_notice_kind']);
if ($glanceNotice !== '') { ?>
<div class="glance-toast glance-toast-<?= $glanceNoticeKind ?>" role="alert">
    <div class="d-flex gap-3 align-items-start">
        <div><?= h($glanceNotice) ?></div>
        <button class="btn-close ms-auto" type="button" aria-label="<?= h(t('닫기', 'Close')) ?>" onclick="this.closest('.glance-toast').remove()"></button>
    </div>
</div>
<?php if ($glanceNoticeKind === 'ok') { ?>
<script>setTimeout(function () { var toast = document.querySelector('.glance-toast'); if (toast) toast.remove(); }, 4000);</script>
<?php } } ?>
<?php if ($glanceImportMessage !== '') { ?>
<div class="modal fade" id="glanceImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= h($glanceImportOk ? t('업로드를 마쳤습니다', 'Upload complete') : t('업로드에 실패했습니다', 'Upload failed')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0"><?= nl2br(h($glanceImportMessage)) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary-custom" data-bs-dismiss="modal"><?= h(t('확인', 'OK')) ?></button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('glanceImportModal');
    if (modal && window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }
});
</script>
<?php } ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-3">
            <div>
                <h2 class="h5 mb-1"><?= h($year . ' ' . t('일정표', 'timetable')) ?></h2>
                <p class="glance-help mb-0"><?= h(t('왼쪽 룸, 위아래 시간입니다. 칸을 누르면 그 칸을 고칩니다. 빈 칸의 + 는 그 자리에 세션을 넣습니다. 화살표는 순서만 바꿉니다.', 'Rooms run left to right and times run top to bottom. Click a cell to edit it. + adds a cell. Arrows change order only.')) ?></p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-start">
                <a class="btn btn-sm btn-outline-secondary" href="<?= h(ksers_glance_page_url(['day' => $dayId, 'edit' => 'new-room'])) ?>"><?= h(t('룸 추가', 'Add room')) ?></a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= h(ksers_glance_page_url(['day' => $dayId, 'edit' => 'new-day'])) ?>"><?= h(t('날짜 추가', 'Add day')) ?></a>
                <?php if ($dayId !== '') { ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= h(ksers_glance_page_url(['day' => $dayId, 'edit' => 'new-slot'])) ?>"><?= h(t('시간 추가', 'Add time')) ?></a>
                <?php } ?>
                <form method="post" action="action.php" onsubmit="return confirm(<?= json_encode(t('예시 시간표를 넣을까요? 이미 있는 구분값은 건너뜁니다.', 'Load the sample timetable? Existing ids are skipped.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
                    <input type="hidden" name="action" value="load_example">
                    <button class="btn btn-sm btn-primary-custom" type="submit"><?= h(t('예시 시간표 넣기', 'Load sample')) ?></button>
                </form>
                <form method="post" action="action.php" enctype="multipart/form-data" class="d-flex flex-wrap gap-2" onsubmit="return confirm(<?= json_encode(t('Schedule_Input 시트로 올해 시간표를 바꿀까요? 이 연도의 룸, 날짜, 시간, 칸은 파일 내용으로 바뀝니다.', 'Replace this year\'s timetable with the Schedule_Input sheet? Rooms, days, times, and cells for this year will match the file.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
                    <input type="hidden" name="action" value="import_schedule">
                    <input class="form-control form-control-sm" style="max-width: 16rem;" type="file" name="schedule_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                    <button class="btn btn-sm btn-primary-custom" type="submit"><?= h(t('Schedule_Input으로 채우기', 'Load Schedule_Input')) ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!$rooms || !$days) { ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-4 align-items-center">
            <div class="col-md-7">
                <h3 class="h6"><?= h(t('표는 네 조각입니다', 'The grid has four parts')) ?></h3>
                <ol class="glance-help mb-0">
                    <li><?= h(t('룸: 왼쪽부터. 같은 층이 연속이면 위 칸이 하나로 합쳐집니다.', 'Rooms go left to right. Consecutive rooms on the same floor share one header.')) ?></li>
                    <li><?= h(t('날짜: 위 버튼에 보이는 글입니다.', 'Days are the buttons above the grid.')) ?></li>
                    <li><?= h(t('시간: 위에서 아래로. 글은 입력한 그대로 나옵니다. 칸이 없으면 빈 줄입니다.', 'Times stack downward. The text is shown as typed. No cells means a blank row.')) ?></li>
                    <li><?= h(t('칸: 제목, 본문, 주제, 링크, 배지, 색입니다. 연결된 프로그램은 Scientific Program에서 고칩니다.', 'A cell holds title, text, topic, link, badge, and color. Edit a linked program in Scientific Program.')) ?></li>
                </ol>
            </div>
            <div class="col-md-5">
                <div class="glance-cell glance-c-orange glance-sample border mx-auto">
                    <div class="glance-body">
                        <div class="glance-title">VP01</div>
                        <div class="glance-content glance-link">Video Presentation 1</div>
                        <div class="glance-topic">UGI</div>
                    </div>
                </div>
                <p class="small text-muted text-center mt-2 mb-0"><?= h(t('굵은 제목, 밑줄 본문, 회색 주제', 'Bold title, underlined text, gray topic')) ?></p>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="glance-days mb-3">
            <?php foreach ($days as $day) { ?>
            <div class="glance-day <?= $day['id'] === $dayId ? 'active' : '' ?>">
                <?= ksers_glance_move_form('day', $day['id'], 'left', '←', $dayId) ?>
                <a class="<?= $day['id'] === $dayId ? 'text-white' : 'text-body' ?>" href="<?= h(ksers_glance_page_url(['day' => $day['id']])) ?>"><?= h($day['label']) ?></a>
                <a class="small <?= $day['id'] === $dayId ? 'text-white' : 'text-secondary' ?>" href="<?= h(ksers_glance_page_url(['day' => $day['id'], 'edit' => 'day', 'id' => $day['id']])) ?>"><?= h(t('수정', 'Edit')) ?></a>
                <?= ksers_glance_move_form('day', $day['id'], 'right', '→', $dayId) ?>
            </div>
            <?php } ?>
            <?php if (!$days) { ?>
            <div class="glance-help"><?= h(t('날짜 버튼을 만들면 그 날짜의 시간표가 열립니다.', 'Add a day to open that timetable.')) ?></div>
            <?php } ?>
        </div>

        <?php if ($grid['warnings']) { ?>
        <div class="alert alert-warning"><?= h(t('겹치거나 표 밖으로 나간 칸은 빼고 그렸습니다.', 'Overlapping or out-of-range cells were left off the grid.')) ?> <?= h(implode(', ', $grid['warnings'])) ?></div>
        <?php } ?>

        <?php if ($dayId !== '' && !$slots) { ?>
        <p class="glance-help mb-0"><?= h(t('이 날짜에는 시간이 없습니다. 시간을 추가하면 줄이 생깁니다.', 'This day has no times. Add a time to create a row.')) ?></p>
        <?php } elseif ($rooms && $slots) { ?>
        <div class="table-responsive">
            <table class="glance-table">
                <thead>
                    <tr>
                        <th class="glance-time" rowspan="2">Time</th>
                        <?php foreach (ksers_glance_floor_groups($rooms) as $group) { ?>
                        <th class="glance-floor" colspan="<?= (int)$group['span'] ?>"><?= h($group['floor']) ?></th>
                        <?php } ?>
                    </tr>
                    <tr>
                        <?php foreach ($rooms as $room) { ?>
                        <th class="glance-room">
                            <?= ksers_glance_move_form('room', $room['code'], 'left', '←', $dayId) ?>
                            <a href="<?= h(ksers_glance_page_url(['day' => $dayId, 'edit' => 'room', 'id' => $room['code']])) ?>">
                                <?= h($room['label']) ?>
                                <?php if ($room['venue_name']) { ?><small>(<?= h($room['venue_name']) ?>)</small><?php } ?>
                            </a>
                            <?= ksers_glance_move_form('room', $room['code'], 'right', '→', $dayId) ?>
                        </th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($slots as $rowIndex => $slot) { ?>
                    <tr>
                        <th class="glance-time">
                            <?= ksers_glance_move_form('slot', $slot['id'], 'up', '↑', $dayId) ?>
                            <a href="<?= h(ksers_glance_page_url(['day' => $dayId, 'edit' => 'slot', 'id' => $slot['id']])) ?>"><?= h($slot['time_label']) ?></a>
                            <?= ksers_glance_move_form('slot', $slot['id'], 'down', '↓', $dayId) ?>
                        </th>
                        <?php foreach ($rooms as $colIndex => $room) {
                            if (!empty($grid['skip'][$rowIndex][$colIndex])) {
                                continue;
                            }
                            $session = $grid['cells'][$rowIndex][$colIndex];
                            if (!$session) { ?>
                        <td>
                            <a class="glance-empty" href="<?= h(ksers_glance_page_url(['day' => $dayId, 'edit' => 'new-session', 'slot' => $slot['id'], 'room' => $room['code']])) ?>">+</a>
                        </td>
                            <?php continue; }
                            $selected = ($edit === 'session' && $editId === $session['id']) ? ' glance-selected' : '';
                        ?>
                        <td class="glance-cell <?= h(ksers_glance_color_class($session['color'])) . $selected ?>" colspan="<?= (int)$session['column_span'] ?>" rowspan="<?= (int)$session['row_span'] ?>">
                            <a class="glance-hit" href="<?= h(ksers_glance_page_url(['day' => $dayId, 'edit' => 'session', 'id' => $session['id']])) ?>"></a>
                            <div class="glance-body"><?= ksers_glance_render_cell($session) ?></div>
                            <?php if (!empty($scientificByGlance[$session['id']])) { ?>
                            <a class="glance-program" href="/admin/page/scientific/edit.php?id=<?= h($scientificByGlance[$session['id']]) ?>"><?= h(t('프로그램 보기', 'Open program')) ?></a>
                            <?php } ?>
                        </td>
                        <?php } ?>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } elseif ($dayId !== '' && !$rooms) { ?>
        <p class="glance-help mb-0"><?= h(t('룸을 추가하면 열이 생깁니다.', 'Add a room to create a column.')) ?></p>
        <?php } ?>
    </div>
</div>

<?php
$editorTitle = '';
$formAction = '';
$record = null;
if ($edit === 'room' || $edit === 'new-room') {
    $editorTitle = $edit === 'room' ? t('룸 수정', 'Edit room') : t('룸 추가', 'Add room');
    $formAction = 'save_room';
    $record = ['code' => '', 'floor' => '', 'label' => '', 'venue_name' => '', 'sort_order' => ksers_glance_next_sort($rooms), 'original_code' => ''];
    if ($edit === 'room') {
        foreach ($rooms as $room) {
            if ($room['code'] === $editId) {
                $record = $room;
                $record['original_code'] = $room['code'];
            }
        }
    }
} elseif ($edit === 'day' || $edit === 'new-day') {
    $editorTitle = $edit === 'day' ? t('날짜 수정', 'Edit day') : t('날짜 추가', 'Add day');
    $formAction = 'save_day';
    $record = ['id' => '', 'label' => '', 'sort_order' => ksers_glance_next_sort($days), 'original_id' => ''];
    if ($edit === 'day') {
        foreach ($days as $day) {
            if ($day['id'] === $editId) {
                $record = $day;
                $record['original_id'] = $day['id'];
            }
        }
    }
} elseif ($edit === 'slot' || $edit === 'new-slot') {
    $editorTitle = $edit === 'slot' ? t('시간 수정', 'Edit time') : t('시간 추가', 'Add time');
    $formAction = 'save_slot';
    $record = [
        'id' => '',
        'day_id' => $dayId,
        'time_label' => '',
        'sort_order' => ksers_glance_next_sort($slots),
        'original_id' => '',
    ];
    if ($edit === 'slot') {
        foreach ($bundle['slots'] as $slot) {
            if ($slot['id'] === $editId) {
                $record = $slot;
                $record['original_id'] = $slot['id'];
            }
        }
    }
} elseif ($edit === 'session' || $edit === 'new-session') {
    $editorTitle = $edit === 'session' ? t('칸 수정', 'Edit cell') : t('칸 추가', 'Add cell');
    $formAction = 'save_session';
    $slotDefault = isset($_GET['slot']) ? (string)$_GET['slot'] : ($slots ? $slots[0]['id'] : '');
    $roomDefault = isset($_GET['room']) ? (string)$_GET['room'] : ($rooms ? $rooms[0]['code'] : '');
    $sibling = isset($bundle['sessionsBySlot'][$slotDefault]) ? $bundle['sessionsBySlot'][$slotDefault] : [];
    $record = [
        'id' => '',
        'slot_id' => $slotDefault,
        'room_code' => $roomDefault,
        'column_span' => 1,
        'row_span' => 1,
        'title' => '',
        'content' => '',
        'topic' => '',
        'href' => '',
        'label' => '',
        'color' => 'none',
        'sort_order' => ksers_glance_next_sort($sibling),
        'original_id' => '',
    ];
    if ($edit === 'session') {
        foreach ($bundle['sessions'] as $session) {
            if ($session['id'] === $editId) {
                $record = $session;
                $record['original_id'] = $session['id'];
            }
        }
    }
    if (!empty($_SESSION['admin_glance_draft']) && is_array($_SESSION['admin_glance_draft'])) {
        foreach ($_SESSION['admin_glance_draft'] as $draftKey => $draftValue) {
            if ($draftKey === 'original_id') {
                continue;
            }
            $record[$draftKey] = $draftValue;
        }
        unset($_SESSION['admin_glance_draft']);
    }
}
?>

<?php if ($formAction && $record && !isset($record['missing'])) { ?>
<div class="card border-0 shadow-sm mb-4" id="glance-editor">
    <div class="card-header bg-white py-3"><h3 class="h5 m-0"><?= h($editorTitle) ?></h3></div>
    <div class="card-body">
        <?php if ($formAction === 'save_room' && $record['code'] === '' && $edit === 'room') { ?>
        <p class="text-muted mb-0"><?= h(t('룸을 찾지 못했습니다.', 'Room not found.')) ?></p>
        <?php } elseif ($formAction === 'save_room') { ?>
        <p class="glance-help"><?= h(t('순서가 작은 룸이 왼쪽입니다. 같은 층 글자가 연속이면 헤더가 한 칸으로 합쳐집니다.', 'A smaller order sits further left. Matching floors in a row share one header.')) ?></p>
        <form method="post" action="action.php" class="row g-3">
            <input type="hidden" name="action" value="save_room">
            <input type="hidden" name="original_code" value="<?= h($record['original_code']) ?>">
            <input type="hidden" name="return_day" value="<?= h($dayId) ?>">
            <div class="col-md-3">
                <label class="form-label"><?= h(t('구분값', 'Id')) ?></label>
                <?php if ($record['original_code'] !== '') { ?>
                <input class="form-control glance-locked" value="<?= h($record['original_code']) ?>" readonly>
                <?php } else { ?>
                <?= ksers_glance_new_id_input('code', 'A', array_column($roomKnown, 'id')) ?>
                <?= ksers_glance_known_list($roomKnown) ?>
                <?php } ?>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= h(t('층', 'Floor')) ?></label>
                <input class="form-control" name="floor" value="<?= h($record['floor']) ?>" required placeholder="2F">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= h(t('룸 이름', 'Room name')) ?></label>
                <input class="form-control" name="label" value="<?= h($record['label']) ?>" required placeholder="Room A">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= h(t('장소 이름', 'Venue')) ?></label>
                <input class="form-control" name="venue_name" value="<?= h($record['venue_name']) ?>" placeholder="Crystal 1">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= h(t('순서', 'Order')) ?></label>
                <input class="form-control" type="number" name="sort_order" value="<?= (int)$record['sort_order'] ?>">
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary-custom" type="submit"><?= h(t('저장', 'Save')) ?></button>
                <?php if ($record['original_code'] !== '') { ?>
                <button class="btn btn-outline-danger" type="submit" name="action" value="delete_room" formnovalidate onclick="return confirm(<?= json_encode(t('이 룸을 지울까요?', 'Delete this room?'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);"><?= h(t('삭제', 'Delete')) ?></button>
                <?php } ?>
            </div>
        </form>
        <?php } elseif ($formAction === 'save_day' && $edit === 'day' && $record['id'] === '') { ?>
        <p class="text-muted mb-0"><?= h(t('날짜를 찾지 못했습니다.', 'Day not found.')) ?></p>
        <?php } elseif ($formAction === 'save_day') { ?>
        <p class="glance-help"><?= h(t('버튼에 보일 글을 그대로 적습니다. 순서가 작은 날짜가 왼쪽 버튼입니다.', 'Type the button text as it should appear. A smaller order is the left button.')) ?></p>
        <form method="post" action="action.php" class="row g-3">
            <input type="hidden" name="action" value="save_day">
            <input type="hidden" name="original_id" value="<?= h($record['original_id']) ?>">
            <input type="hidden" name="return_day" value="<?= h($dayId) ?>">
            <div class="col-md-4">
                <label class="form-label"><?= h(t('구분값', 'Id')) ?></label>
                <?php if ($record['original_id'] !== '') { ?>
                <input class="form-control glance-locked" value="<?= h($record['original_id']) ?>" readonly>
                <?php } else { ?>
                <?= ksers_glance_new_id_input('id', 'april-22', array_column($dayKnown, 'id')) ?>
                <?= ksers_glance_known_list($dayKnown) ?>
                <?php } ?>
            </div>
            <div class="col-md-5">
                <label class="form-label"><?= h(t('버튼 글', 'Button text')) ?></label>
                <input class="form-control" name="label" value="<?= h($record['label']) ?>" required placeholder="April 22 (Thu)">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= h(t('순서', 'Order')) ?></label>
                <input class="form-control" type="number" name="sort_order" value="<?= (int)$record['sort_order'] ?>">
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary-custom" type="submit"><?= h(t('저장', 'Save')) ?></button>
                <?php if ($record['original_id'] !== '') { ?>
                <button class="btn btn-outline-danger" type="submit" name="action" value="delete_day" formnovalidate onclick="return confirm(<?= json_encode(t('이 날짜의 시간과 칸까지 지울까요?', 'Delete this day, including its times and cells?'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);"><?= h(t('삭제', 'Delete')) ?></button>
                <?php } ?>
            </div>
        </form>
        <?php } elseif ($formAction === 'save_slot' && $edit === 'slot' && $record['id'] === '') { ?>
        <p class="text-muted mb-0"><?= h(t('시간을 찾지 못했습니다.', 'Time not found.')) ?></p>
        <?php } elseif ($formAction === 'save_slot') { ?>
        <p class="glance-help"><?= h(t('시간 글은 적힌 그대로 왼쪽 칸에 나옵니다. 순서가 크면 아래에 붙습니다. 이 시간에 칸을 안 넣으면 빈 줄입니다.', 'The time text is shown as typed. A larger order sits lower. Leave it without cells for a blank row.')) ?></p>
        <form method="post" action="action.php" class="row g-3">
            <input type="hidden" name="action" value="save_slot">
            <input type="hidden" name="original_id" value="<?= h($record['original_id']) ?>">
            <input type="hidden" name="return_day" value="<?= h($dayId) ?>">
            <div class="col-md-3">
                <label class="form-label"><?= h(t('구분값', 'Id')) ?></label>
                <?php if ($record['original_id'] !== '') { ?>
                <input class="form-control glance-locked" value="<?= h($record['original_id']) ?>" readonly>
                <?php } else { ?>
                <?= ksers_glance_new_id_input('id', 'apr22-0830', array_column($slotKnown, 'id')) ?>
                <?= ksers_glance_known_list($slotKnown) ?>
                <?php } ?>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= h(t('날짜 구분값', 'Day id')) ?></label>
                <select class="form-select" name="day_id" required>
                    <?php foreach ($days as $day) { ?>
                    <option value="<?= h($day['id']) ?>" <?= $record['day_id'] === $day['id'] ? 'selected' : '' ?>><?= h($day['id'] . ' · ' . $day['label']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= h(t('시간 글', 'Time text')) ?></label>
                <input class="form-control" name="time_label" value="<?= h($record['time_label']) ?>" required placeholder="08:30-09:30">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= h(t('순서', 'Order')) ?></label>
                <input class="form-control" type="number" name="sort_order" value="<?= (int)$record['sort_order'] ?>">
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary-custom" type="submit"><?= h(t('저장', 'Save')) ?></button>
                <?php if ($record['original_id'] !== '') { ?>
                <button class="btn btn-outline-danger" type="submit" name="action" value="delete_slot" formnovalidate onclick="return confirm(<?= json_encode(t('이 시간과 칸을 지울까요?', 'Delete this time and its cells?'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);"><?= h(t('삭제', 'Delete')) ?></button>
                <?php } ?>
            </div>
        </form>
        <?php } elseif ($formAction === 'save_session' && $edit === 'session' && $record['id'] === '') { ?>
        <p class="text-muted mb-0"><?= h(t('칸을 찾지 못했습니다.', 'Cell not found.')) ?></p>
        <?php } elseif ($formAction === 'save_session' && (!$rooms || !$slots)) { ?>
        <p class="text-muted mb-0"><?= h(t('칸을 넣으려면 룸과 시간이 먼저 있어야 합니다.', 'Add a room and a time before adding a cell.')) ?></p>
        <?php } elseif ($formAction === 'save_session') { ?>
        <p class="glance-help"><?= h(t('제목은 굵은 글입니다. 본문이 없고 링크만 있으면 제목이 링크가 됩니다. 본문과 링크가 있으면 본문만 밑줄입니다. 주제는 회색, 배지는 작은 글입니다. 가로 칸은 오른쪽 룸, 세로 칸은 아래 시간을 덮습니다.', 'The title is bold. With a link and no body, the title is the link. With both, only the body is underlined. Topic is gray and the badge is small. Column span covers rooms to the right. Row span covers times below.')) ?></p>
        <form method="post" action="action.php" class="row g-3" id="glance-session-form">
            <input type="hidden" name="action" value="save_session">
            <input type="hidden" name="original_id" value="<?= h($record['original_id']) ?>">
            <input type="hidden" name="return_day" value="<?= h($dayId) ?>">
            <div class="col-lg-4">
                <div class="border rounded p-3 glance-c-none" id="glance-live">
                    <div class="glance-body" id="glance-live-body"></div>
                </div>
                <p class="small text-muted mt-2 mb-0"><?= h(t('저장 전 칸 모양', 'Cell preview')) ?></p>
            </div>
            <div class="col-lg-8">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label"><?= h(t('칸 구분값', 'Cell id')) ?></label>
                        <?php if ($record['original_id'] !== '') { ?>
                        <input class="form-control glance-locked" value="<?= h($record['original_id']) ?>" readonly>
                        <?php } else { ?>
                        <?= ksers_glance_new_id_input('id', 'apr22-vp01', array_column($sessionKnown, 'id'), $record['id']) ?>
                        <?= ksers_glance_known_list($sessionKnown) ?>
                        <?php } ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= h(t('시간 구분값', 'Time id')) ?></label>
                        <select class="form-select" name="slot_id" required>
                            <?php foreach ($slots as $slot) { ?>
                            <option value="<?= h($slot['id']) ?>" <?= $record['slot_id'] === $slot['id'] ? 'selected' : '' ?>><?= h($slot['id'] . ' · ' . $slot['time_label']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= h(t('룸 구분값', 'Room id')) ?></label>
                        <select class="form-select" name="room_code" required>
                            <?php foreach ($rooms as $room) { ?>
                            <option value="<?= h($room['code']) ?>" <?= $record['room_code'] === $room['code'] ? 'selected' : '' ?>><?= h($room['code'] . ' · ' . $room['label'] . ' · ' . $room['floor']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= h(t('제목', 'Title')) ?></label>
                        <input class="form-control" id="glance-title" name="title" value="<?= h($record['title']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= h(t('본문', 'Body')) ?></label>
                        <input class="form-control" id="glance-content" name="content" value="<?= h($record['content']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= h(t('주제', 'Topic')) ?></label>
                        <input class="form-control" id="glance-topic" name="topic" value="<?= h($record['topic']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= h(t('링크 주소', 'Link')) ?></label>
                        <input class="form-control" id="glance-href" name="href" value="<?= h($record['href']) ?>" placeholder="/2027/en/scientific-program#vp01">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= h(t('배지', 'Badge')) ?></label>
                        <input class="form-control" id="glance-label" name="label" value="<?= h($record['label']) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><?= h(t('가로', 'Across')) ?></label>
                        <input class="form-control" type="number" min="1" name="column_span" value="<?= (int)$record['column_span'] ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><?= h(t('세로', 'Down')) ?></label>
                        <input class="form-control" type="number" min="1" name="row_span" value="<?= (int)$record['row_span'] ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><?= h(t('순서', 'Order')) ?></label>
                        <input class="form-control" type="number" name="sort_order" value="<?= (int)$record['sort_order'] ?>">
                    </div>
                    <div class="col-12">
                        <div class="form-label"><?= h(t('색', 'Color')) ?></div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($colorNames as $color => $colorLabel) { ?>
                            <label class="border rounded px-2 py-1 d-flex align-items-center gap-2">
                                <input type="radio" name="color" value="<?= h($color) ?>" <?= $record['color'] === $color ? 'checked' : '' ?>>
                                <span class="glance-swatch <?= h(ksers_glance_color_class($color)) ?>"></span>
                                <span class="small"><?= h($colorLabel) ?></span>
                            </label>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary-custom" type="submit"><?= h(t('저장', 'Save')) ?></button>
                <?php if ($record['original_id'] !== '') { ?>
                <button class="btn btn-outline-danger" type="submit" name="action" value="delete_session" formnovalidate onclick="return confirm(<?= json_encode(t('이 칸을 지울까요?', 'Delete this cell?'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);"><?= h(t('삭제', 'Delete')) ?></button>
                <?php } ?>
                <?php if ($record['original_id'] !== '' && !empty($scientificByGlance[$record['original_id']])) { ?>
                <a class="btn btn-outline-secondary" href="/admin/page/scientific/edit.php?id=<?= h($scientificByGlance[$record['original_id']]) ?>"><?= h(t('프로그램 보기', 'Open program')) ?></a>
                <?php } elseif ($record['original_id'] !== '') { ?>
                <a class="btn btn-outline-secondary" href="/admin/page/scientific/edit.php?glance=<?= h($record['original_id']) ?>"><?= h(t('Scientific Program 만들기', 'Create Scientific Program')) ?></a>
                <?php } ?>
            </div>
        </form>
        <script>
        (function () {
            var form = document.getElementById('glance-session-form');
            var body = document.getElementById('glance-live-body');
            var box = document.getElementById('glance-live');
            if (!form || !body || !box) return;
            function textOf(id) {
                var field = document.getElementById(id);
                return field ? field.value.trim() : '';
            }
            function addText(tag, className, value) {
                var node = document.createElement(tag);
                node.className = className;
                node.textContent = value;
                body.appendChild(node);
            }
            function paint() {
                var title = textOf('glance-title');
                var content = textOf('glance-content');
                var topic = textOf('glance-topic');
                var label = textOf('glance-label');
                var href = textOf('glance-href');
                var color = 'none';
                var chosen = form.querySelector('input[name="color"]:checked');
                if (chosen) color = chosen.value;
                box.className = 'border rounded p-3 ' + 'glance-c-' + color;
                body.textContent = '';
                if (label) addText('span', 'glance-badge', label);
                if (title) addText(content || !href ? 'div' : 'a', content || !href ? 'glance-title' : 'glance-title glance-link', title);
                if (content) addText(href ? 'a' : 'div', href ? 'glance-content glance-link' : 'glance-content', content);
                if (topic) addText('div', 'glance-topic', topic);
            }
            form.addEventListener('input', paint);
            form.addEventListener('change', paint);
            paint();
        }());
        </script>
        <?php } ?>
    </div>
</div>
<?php } ?>

<p class="small text-muted"><?= h(t('홈페이지 시간표는 이 화면의 룸, 날짜, 시간, 칸만 읽습니다. 안내 문구와 표 제목은 여기서 저장하지 않습니다.', 'The homepage timetable reads only rooms, days, times, and cells from this screen. The page title and notice are not stored here.')) ?></p>
<script>
function glanceWarn(message) {
    var oldToast = document.querySelector('.glance-toast');
    if (oldToast) oldToast.remove();
    var toast = document.createElement('div');
    toast.className = 'glance-toast glance-toast-warn';
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
        var input = form.querySelector('[data-taken]');
        if (!input) return;
        var value = input.value.trim();
        var taken = input.getAttribute('data-taken').split(',');
        if (value && taken.indexOf(value) !== -1) {
            event.preventDefault();
            glanceWarn(<?= json_encode(t('저장하지 않았습니다. 같은 구분값이 이미 있습니다. 다른 구분값을 입력하세요.', 'Not saved. That id already exists. Enter a different id.'), JSON_UNESCAPED_UNICODE) ?>);
        }
    });
});
</script>

<?php include '../../lib/footer.php'; ?>
