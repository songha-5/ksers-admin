<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$year = ksers_admin_year($pdo);
$action = isset($_POST['action']) ? $_POST['action'] : '';

function ksers_scientific_notice($message, $kind = 'ok') {
    $_SESSION['admin_scientific_notice'] = $message;
    $_SESSION['admin_scientific_notice_kind'] = $kind === 'warn' ? 'warn' : 'ok';
}

function ksers_scientific_redirect($query) {
    $url = '/admin/page/scientific/index.php';
    $query = array_filter($query, function ($value) {
        return $value !== null && $value !== '';
    });
    if ($query) {
        $url .= '?' . http_build_query($query);
    }
    header('Location: ' . $url);
    exit;
}

function ksers_scientific_edit_redirect($id) {
    header('Location: /admin/page/scientific/edit.php?id=' . rawurlencode($id));
    exit;
}

function ksers_scientific_keep_category_draft() {
    $_SESSION['admin_scientific_category_draft'] = [
        'id' => trim(isset($_POST['id']) ? (string)$_POST['id'] : ''),
        'label' => trim(isset($_POST['label']) ? (string)$_POST['label'] : ''),
    ];
}

function ksers_scientific_keep_session_draft($categoryId) {
    $_SESSION['admin_scientific_draft'] = [
        'original_id' => trim(isset($_POST['original_id']) ? (string)$_POST['original_id'] : ''),
        'id' => trim(isset($_POST['id']) ? (string)$_POST['id'] : ''),
        'category_id' => $categoryId,
        'title' => trim(isset($_POST['title']) ? (string)$_POST['title'] : ''),
        'date_time' => trim(isset($_POST['date_time']) ? (string)$_POST['date_time'] : ''),
        'language' => trim(isset($_POST['language']) ? (string)$_POST['language'] : ''),
        'glance_id' => trim(isset($_POST['glance_id']) ? (string)$_POST['glance_id'] : ''),
    ];
}

function ksers_scientific_fail_session($categoryId, $original) {
    ksers_scientific_keep_session_draft($categoryId);
    if ($original !== '') {
        ksers_scientific_edit_redirect($original);
    }
    $url = '/admin/page/scientific/edit.php';
    if ($categoryId !== '') {
        $url .= '?category=' . rawurlencode($categoryId);
    }
    header('Location: ' . $url);
    exit;
}

if ($action === 'move_category') {
    $id = trim(isset($_POST['id']) ? $_POST['id'] : '');
    $direction = isset($_POST['direction']) ? $_POST['direction'] : '';
    $tab = trim(isset($_POST['tab']) ? $_POST['tab'] : '');
    if ($id !== ksers_scientific_free_id()) {
        $rows = $pdo->prepare('SELECT id FROM scientific_categories WHERE year = ? ORDER BY sort_order, id');
        $rows->execute([$year]);
        $ids = $rows->fetchAll(PDO::FETCH_COLUMN);
        $index = array_search($id, $ids, true);
        $step = $direction === 'left' ? -1 : ($direction === 'right' ? 1 : 0);
        $swap = $index === false ? -1 : $index + $step;
        if ($index !== false && $swap >= 0 && $swap < count($ids) && $ids[$swap] !== ksers_scientific_free_id()) {
            $current = $ids[$index];
            $ids[$index] = $ids[$swap];
            $ids[$swap] = $current;
            $update = $pdo->prepare('UPDATE scientific_categories SET sort_order = ? WHERE year = ? AND id = ?');
            foreach ($ids as $order => $itemId) {
                $update->execute([$order + 1, $year, $itemId]);
            }
            ksers_scientific_pin_free_paper($pdo, $year);
        }
    }
    ksers_scientific_redirect(['tab' => $tab]);
}

if ($action === 'save_category') {
    $id = trim(isset($_POST['id']) ? $_POST['id'] : '');
    $original = trim(isset($_POST['original_id']) ? $_POST['original_id'] : '');
    $label = trim(isset($_POST['label']) ? $_POST['label'] : '');
    $tab = $original !== '' ? $original : $id;
    if ($original === ksers_scientific_free_id()) {
        ksers_scientific_notice(t('Free Paper 대분류는 바꾸지 않습니다.', 'The Free Paper category stays as it is.'), 'warn');
        ksers_scientific_redirect(['tab' => ksers_scientific_free_id()]);
    }
    if ($original !== '') {
        $id = $original;
    }
    $id = strtolower($id);
    if (!ksers_scientific_valid_id($id) || $label === '' || $id === ksers_scientific_free_id()) {
        ksers_scientific_keep_category_draft();
        ksers_scientific_notice(t('저장하지 않았습니다. 대분류 구분값은 영문 소문자·숫자·하이픈이고, 탭 글이 있어야 합니다.', 'Not saved. A category id uses lowercase letters, numbers, and hyphens, and the tab needs a label.'), 'warn');
        ksers_scientific_redirect(['edit' => $original === '' ? 'new-category' : 'category', 'id' => $original]);
    }
    $label = ksers_scientific_cut($label, 160);
    if ($original === '') {
        $sort = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM scientific_categories WHERE year = ? AND id <> ?');
        $sort->execute([$year, ksers_scientific_free_id()]);
        $insert = $pdo->prepare('INSERT INTO scientific_categories (year, id, label, sort_order) VALUES (?, ?, ?, ?)');
        try {
            $insert->execute([$year, $id, $label, (int)$sort->fetchColumn()]);
        } catch (PDOException $e) {
            ksers_scientific_keep_category_draft();
            ksers_scientific_notice(t('저장하지 않았습니다. 같은 구분값이 이미 있습니다. 다른 구분값을 입력하세요.', 'Not saved. That id already exists. Enter a different id.'), 'warn');
            ksers_scientific_redirect(['edit' => 'new-category']);
        }
    } else {
        $update = $pdo->prepare('UPDATE scientific_categories SET label = ? WHERE year = ? AND id = ?');
        $update->execute([$label, $year, $id]);
    }
    ksers_scientific_pin_free_paper($pdo, $year);
    unset($_SESSION['admin_scientific_category_draft']);
    ksers_scientific_notice(t('대분류를 저장했습니다. 프론트 상단 탭에 이 순서로 나옵니다.', 'Category saved. The public tabs follow this order.'));
    ksers_scientific_redirect(['tab' => $id]);
}

if ($action === 'delete_category') {
    $id = trim(isset($_POST['original_id']) ? $_POST['original_id'] : '');
    if ($id === '') {
        $id = trim(isset($_POST['id']) ? $_POST['id'] : '');
    }
    if ($id === ksers_scientific_free_id()) {
        ksers_scientific_notice(t('Free Paper 탭은 지울 수 없습니다.', 'The Free Paper tab cannot be deleted.'), 'warn');
        ksers_scientific_redirect(['tab' => $id]);
    }
    $sessions = $pdo->prepare('SELECT glance_id FROM scientific_sessions WHERE year = ? AND category_id = ? AND glance_id IS NOT NULL');
    $sessions->execute([$year, $id]);
    $clear = $pdo->prepare('UPDATE program_sessions SET href = NULL WHERE year = ? AND id = ?');
    foreach ($sessions->fetchAll(PDO::FETCH_COLUMN) as $glanceId) {
        $clear->execute([$year, $glanceId]);
    }
    $delete = $pdo->prepare('DELETE FROM scientific_categories WHERE year = ? AND id = ?');
    $delete->execute([$year, $id]);
    ksers_scientific_pin_free_paper($pdo, $year);
    ksers_scientific_notice(t('대분류와 그 안의 프로그램을 지웠습니다.', 'Deleted the category and the programs inside it.'));
    ksers_scientific_redirect([]);
}

if ($action === 'seed_glance') {
    $glanceCells = ksers_scientific_glance_rows($pdo, $year);
    if (!$glanceCells) {
        ksers_scientific_notice(t('저장하지 않았습니다. 일정표에 칸이 없어서 프로그램을 등록하지 않았습니다.', 'Not saved. The timetable has no cells, so no programs were registered.'), 'warn');
        ksers_scientific_redirect([]);
    }
    $added = ksers_scientific_seed_from_glance($pdo, $year);
    if ($added < 0) {
        ksers_scientific_notice(t('일정표에서 프로그램을 만들지 못했습니다.', 'Could not create programs from the timetable.'), 'warn');
    } else {
        ksers_scientific_notice($added > 0
            ? t('일정표 칸 ' . $added . '개를 Scientific Program에 넣었습니다. 사진, CV, Abstract는 비워 두었습니다.', 'Added ' . $added . ' timetable cells to Scientific Program. Photos, CVs, and abstracts were left empty.')
            : t('새로 넣을 일정표 칸이 없습니다. 이미 연결된 프로그램은 그대로입니다.', 'No new timetable cells to add. Linked programs were left as they are.'));
    }
    ksers_scientific_redirect([]);
}

if ($action === 'move_session') {
    $id = trim(isset($_POST['id']) ? $_POST['id'] : '');
    $direction = isset($_POST['direction']) ? $_POST['direction'] : '';
    $parent = $pdo->prepare('SELECT category_id FROM scientific_sessions WHERE year = ? AND id = ?');
    $parent->execute([$year, $id]);
    $categoryId = $parent->fetchColumn();
    if ($categoryId) {
        $rows = $pdo->prepare('SELECT id FROM scientific_sessions WHERE year = ? AND category_id = ? ORDER BY sort_order, id');
        $rows->execute([$year, $categoryId]);
        $ids = $rows->fetchAll(PDO::FETCH_COLUMN);
        $index = array_search($id, $ids, true);
        $step = $direction === 'up' ? -1 : ($direction === 'down' ? 1 : 0);
        $swap = $index === false ? -1 : $index + $step;
        if ($index !== false && $swap >= 0 && $swap < count($ids)) {
            $current = $ids[$index];
            $ids[$index] = $ids[$swap];
            $ids[$swap] = $current;
            $update = $pdo->prepare('UPDATE scientific_sessions SET sort_order = ? WHERE year = ? AND id = ?');
            foreach ($ids as $order => $itemId) {
                $update->execute([$order + 1, $year, $itemId]);
            }
        }
    }
    ksers_scientific_redirect(['tab' => $categoryId ? $categoryId : '']);
}

if ($action === 'delete_session') {
    $id = trim(isset($_POST['original_id']) ? $_POST['original_id'] : '');
    if ($id === '') {
        $id = trim(isset($_POST['id']) ? $_POST['id'] : '');
    }
    $row = $pdo->prepare('SELECT category_id, glance_id FROM scientific_sessions WHERE year = ? AND id = ?');
    $row->execute([$year, $id]);
    $session = $row->fetch(PDO::FETCH_ASSOC);
    if ($session) {
        $files = $pdo->prepare('SELECT image_url, cv_url, abstract_url FROM scientific_speakers WHERE year = ? AND session_id = ?');
        $files->execute([$year, $id]);
        foreach ($files->fetchAll(PDO::FETCH_ASSOC) as $fileRow) {
            ksers_scientific_delete_stored($fileRow['image_url']);
            ksers_scientific_delete_stored($fileRow['cv_url']);
            ksers_scientific_delete_stored($fileRow['abstract_url']);
        }
        if ($session['glance_id']) {
            $clear = $pdo->prepare('UPDATE program_sessions SET href = NULL WHERE year = ? AND id = ?');
            $clear->execute([$year, $session['glance_id']]);
        }
        $delete = $pdo->prepare('DELETE FROM scientific_sessions WHERE year = ? AND id = ?');
        $delete->execute([$year, $id]);
        ksers_scientific_notice(t('프로그램을 지웠습니다.', 'Program deleted.'));
        ksers_scientific_redirect(['tab' => $session['category_id']]);
    }
    ksers_scientific_redirect([]);
}

if ($action === 'save_session') {
    $id = strtolower(trim(isset($_POST['id']) ? $_POST['id'] : ''));
    $original = trim(isset($_POST['original_id']) ? $_POST['original_id'] : '');
    if ($original !== '') {
        $id = $original;
    }
    $categoryId = trim(isset($_POST['category_id']) ? $_POST['category_id'] : '');
    if ($categoryId === '') {
        $categoryId = ksers_scientific_free_id();
    }
    $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
    $language = ksers_scientific_cut(isset($_POST['language']) ? $_POST['language'] : '', 40);
    $glanceId = trim(isset($_POST['glance_id']) ? $_POST['glance_id'] : '');
    $glanceId = $glanceId === '' ? null : $glanceId;
    $dateTime = ksers_scientific_cut(isset($_POST['date_time']) ? $_POST['date_time'] : '', 160);
    $categoryOk = $pdo->prepare('SELECT id FROM scientific_categories WHERE year = ? AND id = ?');
    $categoryOk->execute([$year, $categoryId]);
    if (!ksers_scientific_valid_id($id) || $title === '' || !$categoryOk->fetchColumn()) {
        ksers_scientific_notice(t('저장하지 않았습니다. 프로그램 구분값, 제목, 대분류를 확인해 주세요. 구분값은 영문 소문자·숫자·하이픈입니다.', 'Not saved. Check the program id, title, and category. The id uses lowercase letters, numbers, and hyphens.'), 'warn');
        ksers_scientific_fail_session($categoryId, $original);
    }
    if ($glanceId !== null) {
        $glance = ksers_scientific_glance_info($pdo, $year, $glanceId);
        $taken = $pdo->prepare('SELECT id FROM scientific_sessions WHERE year = ? AND glance_id = ? AND id <> ?');
        $taken->execute([$year, $glanceId, $original === '' ? $id : $original]);
        if (!$glance || $taken->fetchColumn()) {
            ksers_scientific_notice(t('저장하지 않았습니다. 그 일정표 칸은 없거나 다른 프로그램에 이미 연결되어 있습니다.', 'Not saved. That timetable cell is missing or already linked to another program.'), 'warn');
            ksers_scientific_fail_session($categoryId, $original);
        }
        $dateTime = ksers_scientific_date_time($glance['day_label'], $glance['time_label']);
    }
    $postedSpeakerIds = isset($_POST['speaker_id']) && is_array($_POST['speaker_id']) ? $_POST['speaker_id'] : [];
    $speakerFileTypes = [
        'image' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'cv' => ['pdf'],
        'abstract' => ['pdf'],
    ];
    foreach ($postedSpeakerIds as $index => $postedSpeakerId) {
        $postedSpeakerId = trim((string)$postedSpeakerId);
        $talkCheck = ksers_scientific_cut(isset($_POST['speaker_talk'][$index]) ? $_POST['speaker_talk'][$index] : '', 255);
        $nameCheck = ksers_scientific_cut(isset($_POST['speaker_name'][$index]) ? $_POST['speaker_name'][$index] : '', 255);
        $badgeCheck = ksers_scientific_cut(isset($_POST['speaker_badge'][$index]) ? $_POST['speaker_badge'][$index] : '', 80);
        $affiliationCheck = ksers_scientific_cut(isset($_POST['speaker_affiliation'][$index]) ? $_POST['speaker_affiliation'][$index] : '', 255);
        $descriptionCheck = ksers_scientific_cut(isset($_POST['speaker_description'][$index]) ? $_POST['speaker_description'][$index] : '', 4000);
        $uploadStarted = false;
        foreach ($speakerFileTypes as $field => $allowedExt) {
            if (!isset($_FILES['speaker_' . $field]['error'][$index])) {
                continue;
            }
            $uploadError = (int)$_FILES['speaker_' . $field]['error'][$index];
            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $uploadStarted = true;
            $uploadSize = isset($_FILES['speaker_' . $field]['size'][$index]) ? (int)$_FILES['speaker_' . $field]['size'][$index] : 0;
            $uploadExt = strtolower(pathinfo((string)$_FILES['speaker_' . $field]['name'][$index], PATHINFO_EXTENSION));
            if ($uploadError !== UPLOAD_ERR_OK || $uploadSize > 10 * 1024 * 1024) {
                ksers_scientific_notice(t('저장하지 않았습니다. 파일은 10MB 이하만 올릴 수 있습니다.', 'Not saved. Upload a file up to 10MB.'), 'warn');
                ksers_scientific_fail_session($categoryId, $original);
            }
            if (!in_array($uploadExt, $allowedExt, true)) {
                ksers_scientific_notice(t('저장하지 않았습니다. 사진은 JPG·PNG, CV와 Abstract는 PDF만 올릴 수 있습니다.', 'Not saved. Photos must be JPG or PNG. CV and Abstract must be PDF.'), 'warn');
                ksers_scientific_fail_session($categoryId, $original);
            }
        }
        $cardStarted = $postedSpeakerId !== '' || $talkCheck !== null || $nameCheck !== null || $badgeCheck !== null || $affiliationCheck !== null || $descriptionCheck !== null || $uploadStarted;
        if ($cardStarted && ($talkCheck === null || $nameCheck === null)) {
            ksers_scientific_notice(t('저장하지 않았습니다. 카드에는 발표 제목과 연자 이름이 있어야 합니다.', 'Not saved. A card needs a talk title and a speaker name.'), 'warn');
            ksers_scientific_fail_session($categoryId, $original);
        }
    }
    $title = ksers_scientific_cut($title, 255);
    $previousGlance = null;
    if ($original === '') {
        $sort = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM scientific_sessions WHERE year = ? AND category_id = ?');
        $sort->execute([$year, $categoryId]);
        $insert = $pdo->prepare(
            'INSERT INTO scientific_sessions (year, id, category_id, title, date_time, language, glance_id, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        try {
            $insert->execute([$year, $id, $categoryId, $title, $dateTime, $language, $glanceId, (int)$sort->fetchColumn()]);
        } catch (PDOException $e) {
            ksers_scientific_notice(t('저장하지 않았습니다. 같은 구분값이 이미 있습니다. 다른 구분값을 입력하세요.', 'Not saved. That id already exists. Enter a different id.'), 'warn');
            ksers_scientific_fail_session($categoryId, $original);
        }
    } else {
        $previous = $pdo->prepare('SELECT glance_id FROM scientific_sessions WHERE year = ? AND id = ?');
        $previous->execute([$year, $original]);
        $previousGlance = $previous->fetchColumn();
        $update = $pdo->prepare(
            'UPDATE scientific_sessions
             SET category_id = ?, title = ?, date_time = ?, language = ?, glance_id = ?
             WHERE year = ? AND id = ?'
        );
        $update->execute([$categoryId, $title, $dateTime, $language, $glanceId, $year, $original]);
        if ($previousGlance && $previousGlance !== $glanceId) {
            $clear = $pdo->prepare('UPDATE program_sessions SET href = NULL WHERE year = ? AND id = ?');
            $clear->execute([$year, $previousGlance]);
        }
    }

    $postedChairIds = isset($_POST['chair_id']) && is_array($_POST['chair_id']) ? $_POST['chair_id'] : [];
    $postedChairNames = isset($_POST['chair_name']) && is_array($_POST['chair_name']) ? $_POST['chair_name'] : [];
    $keepChairs = [];
    $chairOrder = 0;
    $insertChair = $pdo->prepare('INSERT INTO scientific_chairs (id, year, session_id, name, sort_order) VALUES (?, ?, ?, ?, ?)');
    $updateChair = $pdo->prepare('UPDATE scientific_chairs SET name = ?, sort_order = ? WHERE id = ? AND year = ? AND session_id = ?');
    foreach ($postedChairNames as $index => $name) {
        $name = ksers_scientific_cut($name, 255);
        $chairId = isset($postedChairIds[$index]) ? trim((string)$postedChairIds[$index]) : '';
        if ($name === null) {
            continue;
        }
        $chairOrder++;
        if ($chairId === '') {
            $chairId = ksers_scientific_uuid();
            $insertChair->execute([$chairId, $year, $id, $name, $chairOrder]);
        } else {
            $updateChair->execute([$name, $chairOrder, $chairId, $year, $id]);
        }
        $keepChairs[] = $chairId;
    }
    if ($keepChairs) {
        $marks = implode(',', array_fill(0, count($keepChairs), '?'));
        $deleteChairs = $pdo->prepare("DELETE FROM scientific_chairs WHERE year = ? AND session_id = ? AND id NOT IN ($marks)");
        $deleteChairs->execute(array_merge([$year, $id], $keepChairs));
    } else {
        $pdo->prepare('DELETE FROM scientific_chairs WHERE year = ? AND session_id = ?')->execute([$year, $id]);
    }

    $speakerIds = isset($_POST['speaker_id']) && is_array($_POST['speaker_id']) ? $_POST['speaker_id'] : [];
    $keepSpeakers = [];
    $saveWarning = false;
    $insertSpeaker = $pdo->prepare(
        'INSERT INTO scientific_speakers
            (id, year, session_id, badge, talk_title, name, affiliation, description, image_url, cv_url, abstract_url, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, ?)'
    );
    $updateSpeaker = $pdo->prepare(
        'UPDATE scientific_speakers
         SET badge = ?, talk_title = ?, name = ?, affiliation = ?, description = ?, sort_order = ?
         WHERE id = ? AND year = ? AND session_id = ?'
    );
    $speakerOrder = 0;
    foreach ($speakerIds as $index => $speakerId) {
        $speakerId = trim((string)$speakerId);
        $talk = ksers_scientific_cut(isset($_POST['speaker_talk'][$index]) ? $_POST['speaker_talk'][$index] : '', 255);
        $name = ksers_scientific_cut(isset($_POST['speaker_name'][$index]) ? $_POST['speaker_name'][$index] : '', 255);
        $badge = ksers_scientific_cut(isset($_POST['speaker_badge'][$index]) ? $_POST['speaker_badge'][$index] : '', 80);
        $affiliation = ksers_scientific_cut(isset($_POST['speaker_affiliation'][$index]) ? $_POST['speaker_affiliation'][$index] : '', 255);
        $description = ksers_scientific_cut(isset($_POST['speaker_description'][$index]) ? $_POST['speaker_description'][$index] : '', 4000);
        $uploadStarted = false;
        foreach (['image', 'cv', 'abstract'] as $field) {
            if (isset($_FILES['speaker_' . $field]['error'][$index]) && (int)$_FILES['speaker_' . $field]['error'][$index] !== UPLOAD_ERR_NO_FILE) {
                $uploadStarted = true;
            }
        }
        $started = $speakerId !== '' || $talk !== null || $name !== null || $badge !== null || $affiliation !== null || $description !== null || $uploadStarted;
        if (!$started) {
            continue;
        }
        if ($talk === null || $name === null) {
            if (!$saveWarning) {
                ksers_scientific_notice(t('저장하지 않았습니다. 카드에는 발표 제목과 연자 이름이 있어야 합니다.', 'Not saved. A card needs a talk title and a speaker name.'), 'warn');
                $saveWarning = true;
            }
            if ($speakerId !== '') {
                $keepSpeakers[] = $speakerId;
            }
            continue;
        }
        $speakerOrder++;
        $isNew = $speakerId === '';
        if ($isNew) {
            $speakerId = ksers_scientific_uuid();
            $insertSpeaker->execute([$speakerId, $year, $id, $badge, $talk, $name, $affiliation, $description, $speakerOrder]);
        } else {
            $updateSpeaker->execute([$badge, $talk, $name, $affiliation, $description, $speakerOrder, $speakerId, $year, $id]);
        }
        $current = $pdo->prepare('SELECT image_url, cv_url, abstract_url FROM scientific_speakers WHERE id = ? AND year = ? AND session_id = ?');
        $current->execute([$speakerId, $year, $id]);
        $urls = $current->fetch(PDO::FETCH_ASSOC);
        if (!$urls) {
            continue;
        }
        foreach (['image' => 'image_url', 'cv' => 'cv_url', 'abstract' => 'abstract_url'] as $field => $column) {
            $clears = isset($_POST['clear_' . $field]) && is_array($_POST['clear_' . $field]) ? $_POST['clear_' . $field] : [];
            $upload = null;
            if (isset($_FILES['speaker_' . $field]) && is_array($_FILES['speaker_' . $field]['error']) && isset($_FILES['speaker_' . $field]['error'][$index])) {
                $upload = [
                    'name' => $_FILES['speaker_' . $field]['name'][$index],
                    'type' => $_FILES['speaker_' . $field]['type'][$index],
                    'tmp_name' => $_FILES['speaker_' . $field]['tmp_name'][$index],
                    'error' => $_FILES['speaker_' . $field]['error'][$index],
                    'size' => $_FILES['speaker_' . $field]['size'][$index],
                ];
            }
            list($stored, $fileError) = ksers_scientific_store_upload($speakerId, $field, $upload);
            if ($fileError !== null) {
                if (!$saveWarning) {
                    ksers_scientific_notice($fileError, 'warn');
                    $saveWarning = true;
                }
                continue;
            }
            if ($stored !== null) {
                ksers_scientific_delete_stored($urls[$column]);
                $pdo->prepare("UPDATE scientific_speakers SET {$column} = ? WHERE id = ?")->execute([$stored, $speakerId]);
            } elseif (in_array($speakerId, $clears, true)) {
                ksers_scientific_delete_stored($urls[$column]);
                $pdo->prepare("UPDATE scientific_speakers SET {$column} = NULL WHERE id = ?")->execute([$speakerId]);
            }
        }
        $keepSpeakers[] = $speakerId;
    }
    $existingSpeakers = $pdo->prepare('SELECT id, image_url, cv_url, abstract_url FROM scientific_speakers WHERE year = ? AND session_id = ?');
    $existingSpeakers->execute([$year, $id]);
    foreach ($existingSpeakers->fetchAll(PDO::FETCH_ASSOC) as $speaker) {
        if (in_array($speaker['id'], $keepSpeakers, true)) {
            continue;
        }
        ksers_scientific_delete_stored($speaker['image_url']);
        ksers_scientific_delete_stored($speaker['cv_url']);
        ksers_scientific_delete_stored($speaker['abstract_url']);
        $pdo->prepare('DELETE FROM scientific_speakers WHERE id = ? AND year = ?')->execute([$speaker['id'], $year]);
    }

    ksers_scientific_sync_year($pdo, $year);
    unset($_SESSION['admin_scientific_draft']);
    if (!$saveWarning) {
        ksers_scientific_notice(t('프로그램을 저장했습니다. 연결된 일정표 칸에는 이 프로그램 주소가 들어갑니다.', 'Program saved. A linked timetable cell now points at this program.'));
    }
    ksers_scientific_edit_redirect($id);
}

ksers_scientific_redirect([]);
