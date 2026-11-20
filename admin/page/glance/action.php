<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$year = ksers_admin_year($pdo);
$action = isset($_POST['action']) ? $_POST['action'] : '';

function ksers_glance_notice($message, $kind = 'ok') {
    $_SESSION['admin_glance_notice'] = $message;
    $_SESSION['admin_glance_notice_kind'] = $kind === 'warn' ? 'warn' : 'ok';
}

function ksers_glance_import_result($message, $ok) {
    $_SESSION['admin_glance_import'] = [
        'ok' => $ok ? 1 : 0,
        'message' => (string)$message,
    ];
}

function ksers_glance_redirect($query) {
    $url = '/admin/page/glance/index.php';
    $query = array_filter($query, function ($value) {
        return $value !== null && $value !== '';
    });
    if ($query) {
        $url .= '?' . http_build_query($query);
    }
    header('Location: ' . $url);
    exit;
}

function ksers_glance_return_day() {
    return trim(isset($_POST['return_day']) ? $_POST['return_day'] : '');
}

function ksers_glance_posted_sort($rows) {
    if (!isset($_POST['sort_order']) || trim((string)$_POST['sort_order']) === '') {
        return ksers_glance_next_sort($rows);
    }
    return (int)$_POST['sort_order'];
}

$returnDay = ksers_glance_return_day();

if ($action === 'move_room' || $action === 'move_day' || $action === 'move_slot') {
    $kind = str_replace('move_', '', $action);
    ksers_glance_move(
        $pdo,
        $year,
        $kind,
        isset($_POST['id']) ? $_POST['id'] : '',
        isset($_POST['direction']) ? $_POST['direction'] : ''
    );
    ksers_glance_redirect(['day' => $returnDay]);
}

if ($action === 'load_example') {
    $added = ksers_glance_load_example($pdo, $year);
    ksers_glance_notice($added > 0
        ? t('예시 시간표를 넣었습니다. 없는 항목만 추가했습니다.', 'Sample timetable added. Existing rows were left as they are.')
        : t('넣을 예시가 없습니다. 이미 같은 구분값이 있습니다.', 'Nothing new to add. Those ids already exist.'));
    ksers_glance_redirect(['day' => 'april-22']);
}

if ($action === 'import_schedule') {
    $file = isset($_FILES['schedule_file']) ? $_FILES['schedule_file'] : null;
    $uploadError = is_array($file) && isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;
    $tmp = is_array($file) && isset($file['tmp_name']) ? $file['tmp_name'] : '';
    $original = is_array($file) && isset($file['name']) ? (string)$file['name'] : '';
    $size = is_array($file) && isset($file['size']) ? (int)$file['size'] : 0;
    if ($uploadError !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
        ksers_glance_import_result(t('엑셀 파일을 선택해 주세요.', 'Choose an Excel file.'), false);
        ksers_glance_redirect(['day' => $returnDay]);
    }
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if ($ext !== 'xlsx' || $size < 1 || $size > 5 * 1024 * 1024) {
        ksers_glance_import_result(t('xlsx 파일만 올릴 수 있습니다. 5MB 이하로 올려 주세요.', 'Upload an xlsx file of 5MB or less.'), false);
        ksers_glance_redirect(['day' => $returnDay]);
    }
    $parsed = ksers_glance_parse_schedule_xlsx($tmp);
    if ($parsed['error'] !== '') {
        ksers_glance_import_result($parsed['error'], false);
        ksers_glance_redirect(['day' => $returnDay]);
    }
    if (!ksers_glance_replace_schedule($pdo, $year, $parsed)) {
        ksers_glance_import_result(t('시간표를 저장하지 못했습니다. 파일 내용을 다시 확인해 주세요.', 'The timetable was not saved. Check the file and try again.'), false);
        ksers_glance_redirect(['day' => $returnDay]);
    }
    $message = t(
        'Schedule_Input으로 시간표를 넣었습니다. 룸 ' . count($parsed['rooms']) . '개, 날짜 ' . count($parsed['days']) . '개, 칸 ' . count($parsed['sessions']) . '개입니다. 이 연도의 이전 시간표는 이 파일 내용으로 바뀌었습니다.',
        'Loaded the timetable from Schedule_Input. Rooms ' . count($parsed['rooms']) . ', days ' . count($parsed['days']) . ', cells ' . count($parsed['sessions']) . '. This year\'s previous timetable was replaced.'
    );
    if ($parsed['warnings']) {
        $message .= ' ' . implode(' ', $parsed['warnings']);
    }
    ksers_glance_import_result($message, true);
    ksers_glance_redirect(['day' => $parsed['first_day']]);
}

if ($action === 'save_room') {
    $code = trim(isset($_POST['code']) ? $_POST['code'] : '');
    $original = trim(isset($_POST['original_code']) ? $_POST['original_code'] : '');
    if ($original !== '') {
        $code = $original;
    }
    $floor = trim(isset($_POST['floor']) ? $_POST['floor'] : '');
    $label = trim(isset($_POST['label']) ? $_POST['label'] : '');
    $venue = ksers_glance_blank(isset($_POST['venue_name']) ? $_POST['venue_name'] : '');
    $bundle = ksers_glance_load($pdo, $year);
    $sort = ksers_glance_posted_sort($bundle['rooms']);
    if (!ksers_glance_valid_key($code, 40) || $floor === '' || $label === '') {
        ksers_glance_notice(t('룸 코드, 층, 이름을 입력해 주세요. 코드는 영문·숫자·하이픈입니다.', 'Enter a room code, floor, and name. The code uses letters, numbers, and hyphens.'));
        ksers_glance_redirect(['day' => $returnDay, 'edit' => $original === '' ? 'new-room' : 'room', 'id' => $original]);
    }
    if ($original === '') {
        $insert = $pdo->prepare(
            'INSERT INTO program_rooms (year, code, floor, label, venue_name, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
        );
        try {
            $insert->execute([$year, $code, $floor, $label, $venue, $sort]);
        } catch (PDOException $e) {
            ksers_glance_notice(t('저장하지 않았습니다. 같은 구분값이 이미 있습니다. 다른 구분값을 입력하세요.', 'Not saved. That id already exists. Enter a different id.'), 'warn');
            ksers_glance_redirect(['day' => $returnDay, 'edit' => 'new-room']);
        }
    } else {
        $update = $pdo->prepare(
            'UPDATE program_rooms
             SET code = ?, floor = ?, label = ?, venue_name = ?, sort_order = ?
             WHERE year = ? AND code = ?'
        );
        try {
            $update->execute([$code, $floor, $label, $venue, $sort, $year, $original]);
        } catch (PDOException $e) {
            ksers_glance_notice(t('룸 코드를 바꾸지 못했습니다. 같은 코드가 있는지 확인해 주세요.', 'The room code could not be changed. Check for a duplicate.'));
            ksers_glance_redirect(['day' => $returnDay, 'edit' => 'room', 'id' => $original]);
        }
        if ($update->rowCount() === 0) {
            $check = $pdo->prepare('SELECT code FROM program_rooms WHERE year = ? AND code = ?');
            $check->execute([$year, $code]);
            if (!$check->fetch(PDO::FETCH_ASSOC)) {
                ksers_glance_notice(t('룸을 찾지 못했습니다.', 'Room not found.'));
                ksers_glance_redirect(['day' => $returnDay]);
            }
        }
    }
    ksers_glance_notice(t('룸을 저장했습니다.', 'Room saved.'));
    ksers_glance_redirect(['day' => $returnDay, 'edit' => 'room', 'id' => $code]);
}

if ($action === 'delete_room') {
    $code = trim(isset($_POST['original_code']) ? $_POST['original_code'] : '');
    $used = $pdo->prepare('SELECT COUNT(*) FROM program_sessions WHERE year = ? AND room_code = ?');
    $used->execute([$year, $code]);
    if ((int)$used->fetchColumn() > 0) {
        ksers_glance_notice(t('이 룸을 쓰는 칸이 있어 지우지 못했습니다. 칸을 먼저 지우세요.', 'This room is used by a cell. Delete those cells first.'));
        ksers_glance_redirect(['day' => $returnDay, 'edit' => 'room', 'id' => $code]);
    }
    $delete = $pdo->prepare('DELETE FROM program_rooms WHERE year = ? AND code = ?');
    $delete->execute([$year, $code]);
    ksers_glance_notice(t('룸을 지웠습니다.', 'Room deleted.'));
    ksers_glance_redirect(['day' => $returnDay]);
}

if ($action === 'save_day') {
    $id = trim(isset($_POST['id']) ? $_POST['id'] : '');
    $original = trim(isset($_POST['original_id']) ? $_POST['original_id'] : '');
    if ($original !== '') {
        $id = $original;
    }
    $label = trim(isset($_POST['label']) ? $_POST['label'] : '');
    $bundle = ksers_glance_load($pdo, $year);
    $sort = ksers_glance_posted_sort($bundle['days']);
    if (!ksers_glance_valid_key($id, 80) || $label === '') {
        ksers_glance_notice(t('날짜 구분값과 버튼 글을 입력해 주세요.', 'Enter the day id and the button label.'));
        ksers_glance_redirect(['day' => $returnDay, 'edit' => $original === '' ? 'new-day' : 'day', 'id' => $original]);
    }
    if ($original === '') {
        $insert = $pdo->prepare('INSERT INTO program_days (year, id, label, sort_order) VALUES (?, ?, ?, ?)');
        try {
            $insert->execute([$year, $id, $label, $sort]);
        } catch (PDOException $e) {
            ksers_glance_notice(t('저장하지 않았습니다. 같은 구분값이 이미 있습니다. 다른 구분값을 입력하세요.', 'Not saved. That id already exists. Enter a different id.'), 'warn');
            ksers_glance_redirect(['day' => $returnDay, 'edit' => 'new-day']);
        }
    } else {
        $update = $pdo->prepare(
            'UPDATE program_days SET id = ?, label = ?, sort_order = ? WHERE year = ? AND id = ?'
        );
        try {
            $update->execute([$id, $label, $sort, $year, $original]);
        } catch (PDOException $e) {
            ksers_glance_notice(t('날짜 구분값을 바꾸지 못했습니다.', 'The day id could not be changed.'));
            ksers_glance_redirect(['day' => $returnDay, 'edit' => 'day', 'id' => $original]);
        }
    }
    ksers_scientific_sync_year($pdo, $year);
    ksers_glance_notice(t('날짜를 저장했습니다.', 'Day saved.'));
    ksers_glance_redirect(['day' => $id, 'edit' => 'day', 'id' => $id]);
}

if ($action === 'delete_day') {
    $id = trim(isset($_POST['original_id']) ? $_POST['original_id'] : '');
    $delete = $pdo->prepare('DELETE FROM program_days WHERE year = ? AND id = ?');
    $delete->execute([$year, $id]);
    ksers_glance_notice(t('날짜와 그 시간·칸을 지웠습니다.', 'Deleted the day and its times and cells.'));
    ksers_glance_redirect([]);
}

if ($action === 'save_slot') {
    $id = trim(isset($_POST['id']) ? $_POST['id'] : '');
    $original = trim(isset($_POST['original_id']) ? $_POST['original_id'] : '');
    if ($original !== '') {
        $id = $original;
    }
    $dayId = trim(isset($_POST['day_id']) ? $_POST['day_id'] : '');
    $timeLabel = trim(isset($_POST['time_label']) ? $_POST['time_label'] : '');
    $bundle = ksers_glance_load($pdo, $year);
    $siblingSlots = isset($bundle['slotsByDay'][$dayId]) ? $bundle['slotsByDay'][$dayId] : [];
    $sort = ksers_glance_posted_sort($siblingSlots);
    $dayOk = false;
    foreach ($bundle['days'] as $day) {
        if ($day['id'] === $dayId) {
            $dayOk = true;
        }
    }
    if (!ksers_glance_valid_key($id, 80) || !$dayOk || $timeLabel === '') {
        ksers_glance_notice(t('시간 구분값, 날짜, 시간 글을 입력해 주세요.', 'Enter the time id, day, and the time text.'));
        ksers_glance_redirect(['day' => $dayId !== '' ? $dayId : $returnDay, 'edit' => $original === '' ? 'new-slot' : 'slot', 'id' => $original]);
    }
    if ($original === '') {
        $insert = $pdo->prepare(
            'INSERT INTO program_time_slots (year, id, day_id, time_label, sort_order) VALUES (?, ?, ?, ?, ?)'
        );
        try {
            $insert->execute([$year, $id, $dayId, $timeLabel, $sort]);
        } catch (PDOException $e) {
            ksers_glance_notice(t('저장하지 않았습니다. 같은 구분값이 이미 있습니다. 다른 구분값을 입력하세요.', 'Not saved. That id already exists. Enter a different id.'), 'warn');
            ksers_glance_redirect(['day' => $dayId, 'edit' => 'new-slot']);
        }
    } else {
        $update = $pdo->prepare(
            'UPDATE program_time_slots SET id = ?, day_id = ?, time_label = ?, sort_order = ? WHERE year = ? AND id = ?'
        );
        try {
            $update->execute([$id, $dayId, $timeLabel, $sort, $year, $original]);
        } catch (PDOException $e) {
            ksers_glance_notice(t('시간을 저장하지 못했습니다.', 'The time could not be saved.'));
            ksers_glance_redirect(['day' => $returnDay, 'edit' => 'slot', 'id' => $original]);
        }
    }
    ksers_scientific_sync_year($pdo, $year);
    ksers_glance_notice(t('시간을 저장했습니다. 칸을 넣지 않으면 빈 줄로 보입니다.', 'Time saved. A time with no cells stays an empty row.'));
    ksers_glance_redirect(['day' => $dayId, 'edit' => 'slot', 'id' => $id]);
}

if ($action === 'delete_slot') {
    $id = trim(isset($_POST['original_id']) ? $_POST['original_id'] : '');
    $delete = $pdo->prepare('DELETE FROM program_time_slots WHERE year = ? AND id = ?');
    $delete->execute([$year, $id]);
    ksers_glance_notice(t('시간과 그 칸을 지웠습니다.', 'Deleted the time and its cells.'));
    ksers_glance_redirect(['day' => $returnDay]);
}

if ($action === 'save_session') {
    $id = trim(isset($_POST['id']) ? $_POST['id'] : '');
    $original = trim(isset($_POST['original_id']) ? $_POST['original_id'] : '');
    if ($original !== '') {
        $id = $original;
    }
    $slotId = trim(isset($_POST['slot_id']) ? $_POST['slot_id'] : '');
    $roomCode = trim(isset($_POST['room_code']) ? $_POST['room_code'] : '');
    $columnSpan = max(1, (int)(isset($_POST['column_span']) ? $_POST['column_span'] : 1));
    $rowSpan = max(1, (int)(isset($_POST['row_span']) ? $_POST['row_span'] : 1));
    $title = ksers_glance_blank(isset($_POST['title']) ? $_POST['title'] : '');
    $content = ksers_glance_blank(isset($_POST['content']) ? $_POST['content'] : '');
    $topic = ksers_glance_blank(isset($_POST['topic']) ? $_POST['topic'] : '');
    $href = ksers_glance_blank(isset($_POST['href']) ? $_POST['href'] : '');
    $label = ksers_glance_blank(isset($_POST['label']) ? $_POST['label'] : '');
    $color = trim(isset($_POST['color']) ? $_POST['color'] : 'none');
    $bundle = ksers_glance_load($pdo, $year);
    $slot = null;
    foreach ($bundle['slots'] as $row) {
        if ($row['id'] === $slotId) {
            $slot = $row;
        }
    }
    $sibling = ($slot && isset($bundle['sessionsBySlot'][$slotId])) ? $bundle['sessionsBySlot'][$slotId] : [];
    $sort = ksers_glance_posted_sort($sibling);
    if (!in_array($color, ksers_glance_colors(), true)) {
        $color = '';
    }
    if ($href !== null && ksers_glance_safe_href($href) === '') {
        ksers_glance_notice(t('링크는 /로 시작하거나 http 주소만 넣을 수 있습니다.', 'A link must start with / or be an http address.'));
        ksers_glance_redirect([
            'day' => $slot ? $slot['day_id'] : $returnDay,
            'edit' => $original === '' ? 'new-session' : 'session',
            'id' => $original,
            'slot' => $slotId,
            'room' => $roomCode,
        ]);
    }
    $daySlots = ($slot && isset($bundle['slotsByDay'][$slot['day_id']])) ? $bundle['slotsByDay'][$slot['day_id']] : [];
    $daySessions = [];
    foreach ($daySlots as $daySlot) {
        if (!isset($bundle['sessionsBySlot'][$daySlot['id']])) {
            continue;
        }
        foreach ($bundle['sessionsBySlot'][$daySlot['id']] as $existing) {
            if ($existing['id'] === $original) {
                continue;
            }
            $daySessions[] = $existing;
        }
    }
    $candidate = [
        'id' => $id,
        'slot_id' => $slotId,
        'room_code' => $roomCode,
        'column_span' => $columnSpan,
        'row_span' => $rowSpan,
        'sort_order' => $sort,
    ];
    $placeIssue = ($slot && ksers_glance_valid_key($id, 80) && $color !== '')
        ? ksers_glance_place_issue($bundle['rooms'], $daySlots, $daySessions, $candidate)
        : '';
    $blocked = !$slot || !ksers_glance_valid_key($id, 80) || $color === '' || $placeIssue !== '';
    if ($blocked) {
        $_SESSION['admin_glance_draft'] = [
            'id' => $id,
            'slot_id' => $slotId,
            'room_code' => $roomCode,
            'column_span' => $columnSpan,
            'row_span' => $rowSpan,
            'title' => (string)$title,
            'content' => (string)$content,
            'topic' => (string)$topic,
            'href' => (string)$href,
            'label' => (string)$label,
            'color' => $color !== '' ? $color : 'none',
            'sort_order' => $sort,
        ];
        ksers_glance_notice(
            $placeIssue !== ''
                ? $placeIssue
                : t('저장하지 않았습니다. 구분값, 시간, 룸, 색을 다시 확인해 주세요.', 'Not saved. Check the id, time, room, and color.'),
            'warn'
        );
        ksers_glance_redirect([
            'day' => $slot ? $slot['day_id'] : $returnDay,
            'edit' => $original === '' ? 'new-session' : 'session',
            'id' => $original,
            'slot' => $slotId,
            'room' => $roomCode,
        ]);
    }
    $params = [$slotId, $roomCode, $columnSpan, $rowSpan, $title, $content, $topic, $href, $label, $color, $sort];
    if ($original === '') {
        $insert = $pdo->prepare(
            'INSERT INTO program_sessions
                (year, id, slot_id, room_code, column_span, row_span, title, content, topic, href, label, color, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        try {
            $insert->execute(array_merge([$year, $id], $params));
        } catch (PDOException $e) {
            $_SESSION['admin_glance_draft'] = [
                'id' => $id,
                'slot_id' => $slotId,
                'room_code' => $roomCode,
                'column_span' => $columnSpan,
                'row_span' => $rowSpan,
                'title' => (string)$title,
                'content' => (string)$content,
                'topic' => (string)$topic,
                'href' => (string)$href,
                'label' => (string)$label,
                'color' => $color !== '' ? $color : 'none',
                'sort_order' => $sort,
            ];
            ksers_glance_notice(t('저장하지 않았습니다. 같은 구분값이 이미 있습니다. 다른 구분값을 입력하세요.', 'Not saved. That id already exists. Enter a different id.'), 'warn');
            ksers_glance_redirect(['day' => $slot['day_id'], 'edit' => 'new-session', 'slot' => $slotId, 'room' => $roomCode]);
        }
    } else {
        $update = $pdo->prepare(
            'UPDATE program_sessions
             SET id = ?, slot_id = ?, room_code = ?, column_span = ?, row_span = ?, title = ?, content = ?, topic = ?, href = ?, label = ?, color = ?, sort_order = ?
             WHERE year = ? AND id = ?'
        );
        try {
            $update->execute(array_merge([$id], $params, [$year, $original]));
        } catch (PDOException $e) {
            ksers_glance_notice(t('칸을 저장하지 못했습니다.', 'The cell could not be saved.'));
            ksers_glance_redirect(['day' => $slot['day_id'], 'edit' => 'session', 'id' => $original]);
        }
    }
    ksers_scientific_sync_year($pdo, $year);
    ksers_glance_notice(t('칸을 저장했습니다.', 'Cell saved.'));
    ksers_glance_redirect(['day' => $slot['day_id'], 'edit' => 'session', 'id' => $id]);
}

if ($action === 'delete_session') {
    $id = trim(isset($_POST['original_id']) ? $_POST['original_id'] : '');
    ksers_scientific_detach_glance($pdo, $year, $id);
    $delete = $pdo->prepare('DELETE FROM program_sessions WHERE year = ? AND id = ?');
    $delete->execute([$year, $id]);
    ksers_glance_notice(t('칸을 지웠습니다.', 'Cell deleted.'));
    ksers_glance_redirect(['day' => $returnDay]);
}

ksers_glance_redirect(['day' => $returnDay]);
