<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$year = ksers_admin_year($pdo);
$action = isset($_POST['action']) ? $_POST['action'] : '';

function ksers_program_notice($message) {
    $_SESSION['admin_notice'] = $message;
}

function ksers_program_save_upload($talkSeq, $field, $file) {
    $allowedExt = [
        'photo' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'cv' => ['pdf'],
        'abstract' => ['pdf'],
    ];
    if (!isset($allowedExt[$field]) || !$file || !isset($file['error'])) {
        return [null, null];
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || (int)$file['size'] > 10 * 1024 * 1024) {
        return [null, t('파일은 10MB 이하만 올릴 수 있습니다.', 'Upload a file up to 10MB.')];
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt[$field], true)) {
        return [null, t('사진: JPG/PNG, CV·초록: PDF만 가능합니다.', 'Photo: JPG/PNG. CV and abstract: PDF only.')];
    }
    $dir = ksers_program_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $stored = $talkSeq . '_' . $field . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $stored;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return [null, t('파일을 저장하지 못했습니다.', 'The file could not be saved.')];
    }
    $url = ksers_program_file_url($talkSeq, $field) . '&file=' . rawurlencode($stored);
    return [$url, null];
}

function ksers_program_delete_stored($url) {
    if ($url === '' || !preg_match('/(?:^|[?&])file=([a-zA-Z0-9._-]+)/', $url, $m)) {
        return;
    }
    $path = ksers_program_dir() . '/' . basename($m[1]);
    if (is_file($path)) {
        unlink($path);
    }
}

if ($action === 'save_session') {
    $seq = isset($_POST['seq']) ? (int)$_POST['seq'] : 0;
    $sessionId = trim(isset($_POST['session_id']) ? $_POST['session_id'] : '');
    $category = trim(isset($_POST['category']) ? $_POST['category'] : '');
    $sessionCode = trim(isset($_POST['session_code']) ? $_POST['session_code'] : '');
    $title = trim(isset($_POST['session_title']) ? $_POST['session_title'] : '');
    $date = trim(isset($_POST['session_date']) ? $_POST['session_date'] : '');
    $start = trim(isset($_POST['start_time']) ? $_POST['start_time'] : '');
    $end = trim(isset($_POST['end_time']) ? $_POST['end_time'] : '');
    $room = trim(isset($_POST['room']) ? $_POST['room'] : '');
    $bg = trim(isset($_POST['bg_color']) ? $_POST['bg_color'] : '');
    $rowSpan = max(1, (int)(isset($_POST['row_span']) ? $_POST['row_span'] : 1));
    $colSpan = max(1, (int)(isset($_POST['col_span']) ? $_POST['col_span'] : 1));
    $hasLink = isset($_POST['has_link']) ? 1 : 0;
    $sortNo = (int)(isset($_POST['sort_no']) ? $_POST['sort_no'] : 0);
    $isPublic = isset($_POST['is_public']) ? 1 : 0;

    if ($sessionId === '' || $title === '' || $date === '' || $start === '' || $end === '') {
        ksers_program_notice(t('세션 ID, 제목, 날짜, 시간을 입력해 주세요.', 'Enter session ID, title, date, and time.'));
        header('Location: /admin/page/program/edit.php' . ($seq > 0 ? ('?seq=' . $seq) : ''));
        exit;
    }

    if ($seq > 0) {
        $check = $pdo->prepare("SELECT seq FROM program_session WHERE seq = ? AND year = ?");
        $check->execute([$seq, $year]);
        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            header('Location: /admin/page/program/list.php');
            exit;
        }
        $dup = $pdo->prepare("SELECT seq FROM program_session WHERE year = ? AND session_id = ? AND seq <> ?");
        $dup->execute([$year, $sessionId, $seq]);
        if ($dup->fetch(PDO::FETCH_ASSOC)) {
            ksers_program_notice(t('같은 세션 ID가 이미 있습니다.', 'That session ID already exists.'));
            header('Location: /admin/page/program/edit.php?seq=' . $seq);
            exit;
        }
        $update = $pdo->prepare(
            "UPDATE program_session SET
                session_id = ?, category = ?, session_code = ?, session_title = ?,
                session_date = ?, start_time = ?, end_time = ?, room = ?, bg_color = ?,
                row_span = ?, col_span = ?, has_link = ?, sort_no = ?, is_public = ?
             WHERE seq = ? AND year = ?"
        );
        $update->execute([
            $sessionId, $category, $sessionCode, $title, $date, $start, $end, $room, $bg,
            $rowSpan, $colSpan, $hasLink, $sortNo, $isPublic, $seq, $year,
        ]);
    } else {
        $dup = $pdo->prepare("SELECT seq FROM program_session WHERE year = ? AND session_id = ?");
        $dup->execute([$year, $sessionId]);
        if ($dup->fetch(PDO::FETCH_ASSOC)) {
            ksers_program_notice(t('같은 세션 ID가 이미 있습니다.', 'That session ID already exists.'));
            header('Location: /admin/page/program/edit.php');
            exit;
        }
        $insert = $pdo->prepare(
            "INSERT INTO program_session
                (year, session_id, category, session_code, session_title, session_date, start_time, end_time,
                 room, bg_color, row_span, col_span, has_link, sort_no, is_public, reg_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $insert->execute([
            $year, $sessionId, $category, $sessionCode, $title, $date, $start, $end,
            $room, $bg, $rowSpan, $colSpan, $hasLink, $sortNo, $isPublic,
        ]);
        $seq = (int)$pdo->lastInsertId();
    }

    $pdo->prepare("DELETE FROM program_chair WHERE session_seq = ?")->execute([$seq]);
    $chairNames = isset($_POST['chair_name']) && is_array($_POST['chair_name']) ? $_POST['chair_name'] : [];
    $chairAffs = isset($_POST['chair_affiliation']) && is_array($_POST['chair_affiliation']) ? $_POST['chair_affiliation'] : [];
    $chairSorts = isset($_POST['chair_sort']) && is_array($_POST['chair_sort']) ? $_POST['chair_sort'] : [];
    $chairIns = $pdo->prepare(
        "INSERT INTO program_chair (session_seq, name, affiliation, sort_no) VALUES (?, ?, ?, ?)"
    );
    foreach ($chairNames as $i => $name) {
        $name = trim((string)$name);
        if ($name === '') {
            continue;
        }
        $aff = trim(isset($chairAffs[$i]) ? (string)$chairAffs[$i] : '');
        $sort = (int)(isset($chairSorts[$i]) ? $chairSorts[$i] : ($i + 1));
        $chairIns->execute([$seq, $name, $aff, $sort]);
    }

    ksers_program_notice(t('세션을 저장했습니다.', 'Session saved.'));
    header('Location: /admin/page/program/edit.php?seq=' . $seq);
    exit;
}

if ($action === 'delete_session') {
    $seq = isset($_POST['seq']) ? (int)$_POST['seq'] : 0;
    $check = $pdo->prepare("SELECT seq FROM program_session WHERE seq = ? AND year = ?");
    $check->execute([$seq, $year]);
    if ($check->fetch(PDO::FETCH_ASSOC)) {
        $talks = $pdo->prepare("SELECT photo_url, cv_url, abstract_url FROM program_talk WHERE session_seq = ?");
        $talks->execute([$seq]);
        foreach ($talks->fetchAll(PDO::FETCH_ASSOC) as $talk) {
            ksers_program_delete_stored($talk['photo_url']);
            ksers_program_delete_stored($talk['cv_url']);
            ksers_program_delete_stored($talk['abstract_url']);
        }
        $pdo->prepare("DELETE FROM program_talk WHERE session_seq = ?")->execute([$seq]);
        $pdo->prepare("DELETE FROM program_chair WHERE session_seq = ?")->execute([$seq]);
        $pdo->prepare("DELETE FROM program_session WHERE seq = ? AND year = ?")->execute([$seq, $year]);
        ksers_program_notice(t('세션을 삭제했습니다.', 'Session deleted.'));
    }
    header('Location: /admin/page/program/list.php');
    exit;
}

if ($action === 'save_talk') {
    $sessionSeq = isset($_POST['session_seq']) ? (int)$_POST['session_seq'] : 0;
    $talkSeq = isset($_POST['talk_seq']) ? (int)$_POST['talk_seq'] : 0;
    $check = $pdo->prepare("SELECT seq FROM program_session WHERE seq = ? AND year = ?");
    $check->execute([$sessionSeq, $year]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        header('Location: /admin/page/program/list.php');
        exit;
    }

    $talkCode = trim(isset($_POST['talk_code']) ? $_POST['talk_code'] : '');
    $talkTitle = trim(isset($_POST['talk_title']) ? $_POST['talk_title'] : '');
    $speakerName = trim(isset($_POST['speaker_name']) ? $_POST['speaker_name'] : '');
    $speakerAff = trim(isset($_POST['speaker_affiliation']) ? $_POST['speaker_affiliation'] : '');
    $speakerCountry = trim(isset($_POST['speaker_country']) ? $_POST['speaker_country'] : '');
    $note = trim(isset($_POST['note']) ? $_POST['note'] : '');
    $sortNo = (int)(isset($_POST['sort_no']) ? $_POST['sort_no'] : 0);
    $isPublic = isset($_POST['is_public']) ? 1 : 0;

    if ($talkTitle === '' || $speakerName === '') {
        ksers_program_notice(t('발표 제목과 연자 이름을 입력해 주세요.', 'Enter the talk title and speaker name.'));
        header('Location: /admin/page/program/edit.php?seq=' . $sessionSeq);
        exit;
    }

    if ($talkSeq > 0) {
        $existing = $pdo->prepare("SELECT * FROM program_talk WHERE seq = ? AND session_seq = ?");
        $existing->execute([$talkSeq, $sessionSeq]);
        $old = $existing->fetch(PDO::FETCH_ASSOC);
        if (!$old) {
            header('Location: /admin/page/program/edit.php?seq=' . $sessionSeq);
            exit;
        }
        $photoUrl = $old['photo_url'];
        $cvUrl = $old['cv_url'];
        $abstractUrl = $old['abstract_url'];
    } else {
        $insert = $pdo->prepare(
            "INSERT INTO program_talk
                (session_seq, talk_code, talk_title, speaker_name, speaker_affiliation, speaker_country,
                 photo_url, cv_url, abstract_url, note, sort_no, is_public, reg_date)
             VALUES (?, ?, ?, ?, ?, ?, '', '', '', ?, ?, ?, NOW())"
        );
        $insert->execute([
            $sessionSeq, $talkCode, $talkTitle, $speakerName, $speakerAff, $speakerCountry,
            $note, $sortNo, $isPublic,
        ]);
        $talkSeq = (int)$pdo->lastInsertId();
        $photoUrl = '';
        $cvUrl = '';
        $abstractUrl = '';
    }

    foreach (['photo', 'cv', 'abstract'] as $field) {
        $fileKey = $field . '_file';
        list($newUrl, $err) = ksers_program_save_upload($talkSeq, $field, isset($_FILES[$fileKey]) ? $_FILES[$fileKey] : null);
        if ($err) {
            ksers_program_notice($err);
            header('Location: /admin/page/program/edit.php?seq=' . $sessionSeq);
            exit;
        }
        if ($newUrl) {
            if ($field === 'photo') {
                ksers_program_delete_stored($photoUrl);
                $photoUrl = $newUrl;
            } elseif ($field === 'cv') {
                ksers_program_delete_stored($cvUrl);
                $cvUrl = $newUrl;
            } else {
                ksers_program_delete_stored($abstractUrl);
                $abstractUrl = $newUrl;
            }
        }
    }

    $update = $pdo->prepare(
        "UPDATE program_talk SET
            talk_code = ?, talk_title = ?, speaker_name = ?, speaker_affiliation = ?, speaker_country = ?,
            photo_url = ?, cv_url = ?, abstract_url = ?, note = ?, sort_no = ?, is_public = ?
         WHERE seq = ? AND session_seq = ?"
    );
    $update->execute([
        $talkCode, $talkTitle, $speakerName, $speakerAff, $speakerCountry,
        $photoUrl, $cvUrl, $abstractUrl, $note, $sortNo, $isPublic, $talkSeq, $sessionSeq,
    ]);

    ksers_program_notice(t('발표·연자를 저장했습니다.', 'Talk and speaker saved.'));
    header('Location: /admin/page/program/edit.php?seq=' . $sessionSeq);
    exit;
}

if ($action === 'delete_talk') {
    $sessionSeq = isset($_POST['session_seq']) ? (int)$_POST['session_seq'] : 0;
    $talkSeq = isset($_POST['talk_seq']) ? (int)$_POST['talk_seq'] : 0;
    $check = $pdo->prepare(
        "SELECT t.photo_url, t.cv_url, t.abstract_url
         FROM program_talk t
         JOIN program_session s ON s.seq = t.session_seq
         WHERE t.seq = ? AND t.session_seq = ? AND s.year = ?"
    );
    $check->execute([$talkSeq, $sessionSeq, $year]);
    $row = $check->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        ksers_program_delete_stored($row['photo_url']);
        ksers_program_delete_stored($row['cv_url']);
        ksers_program_delete_stored($row['abstract_url']);
        $pdo->prepare("DELETE FROM program_talk WHERE seq = ?")->execute([$talkSeq]);
        ksers_program_notice(t('발표를 삭제했습니다.', 'Talk deleted.'));
    }
    header('Location: /admin/page/program/edit.php?seq=' . $sessionSeq);
    exit;
}

if ($action === 'import_csv') {
    $file = isset($_FILES['csv_file']) ? $_FILES['csv_file'] : null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        ksers_program_notice(t('CSV 파일을 선택해 주세요.', 'Choose a CSV file.'));
        header('Location: /admin/page/program/list.php');
        exit;
    }
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        ksers_program_notice(t('CSV를 읽지 못했습니다.', 'Could not read the CSV.'));
        header('Location: /admin/page/program/list.php');
        exit;
    }
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        ksers_program_notice(t('CSV 헤더가 없습니다.', 'CSV header is missing.'));
        header('Location: /admin/page/program/list.php');
        exit;
    }
    if (isset($header[0])) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    }
    $map = [];
    foreach ($header as $i => $col) {
        $map[strtolower(trim((string)$col))] = $i;
    }
    $need = ['session_id', 'session_title', 'date', 'start', 'end'];
    foreach ($need as $col) {
        if (!isset($map[$col])) {
            fclose($handle);
            ksers_program_notice(t('필수 열이 없습니다: ', 'Missing required column: ') . $col);
            header('Location: /admin/page/program/list.php');
            exit;
        }
    }
    $get = function ($row, $key) use ($map) {
        if (!isset($map[$key])) {
            return '';
        }
        $i = $map[$key];
        return isset($row[$i]) ? trim((string)$row[$i]) : '';
    };

    $sessionFind = $pdo->prepare("SELECT seq FROM program_session WHERE year = ? AND session_id = ?");
    $sessionIns = $pdo->prepare(
        "INSERT INTO program_session
            (year, session_id, category, session_code, session_title, session_date, start_time, end_time,
             room, bg_color, row_span, col_span, has_link, sort_no, is_public, reg_date)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '', 1, 1, 1, ?, 1, NOW())"
    );
    $sessionUpd = $pdo->prepare(
        "UPDATE program_session SET
            category = ?, session_code = ?, session_title = ?, session_date = ?, start_time = ?, end_time = ?,
            room = ?, sort_no = ?
         WHERE seq = ?"
    );
    $chairFind = $pdo->prepare(
        "SELECT seq FROM program_chair WHERE session_seq = ? AND name = ? AND affiliation = ?"
    );
    $chairIns = $pdo->prepare(
        "INSERT INTO program_chair (session_seq, name, affiliation, sort_no) VALUES (?, ?, ?, ?)"
    );
    $talkIns = $pdo->prepare(
        "INSERT INTO program_talk
            (session_seq, talk_code, talk_title, speaker_name, speaker_affiliation, speaker_country,
             photo_url, cv_url, abstract_url, note, sort_no, is_public, reg_date)
         VALUES (?, ?, ?, ?, ?, '', '', '', '', '', ?, 1, NOW())"
    );

    $sessionCount = 0;
    $talkCount = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $sessionId = $get($row, 'session_id');
        $title = $get($row, 'session_title');
        $date = $get($row, 'date');
        $start = $get($row, 'start');
        $end = $get($row, 'end');
        if ($sessionId === '' || $title === '' || $date === '') {
            continue;
        }
        $category = $get($row, 'category');
        $sessionCode = $get($row, 'session_code');
        $room = $get($row, 'room');
        $sort = (int)$get($row, 'sort');
        $sessionFind->execute([$year, $sessionId]);
        $found = $sessionFind->fetch(PDO::FETCH_ASSOC);
        if ($found) {
            $sessionSeq = (int)$found['seq'];
            $sessionUpd->execute([$category, $sessionCode, $title, $date, $start, $end, $room, $sort, $sessionSeq]);
        } else {
            $sessionIns->execute([$year, $sessionId, $category, $sessionCode, $title, $date, $start, $end, $room, $sort]);
            $sessionSeq = (int)$pdo->lastInsertId();
            $sessionCount++;
        }
        $chairName = $get($row, 'chair_name');
        $chairAff = $get($row, 'chair_affiliation');
        if ($chairName !== '') {
            $chairFind->execute([$sessionSeq, $chairName, $chairAff]);
            if (!$chairFind->fetch(PDO::FETCH_ASSOC)) {
                $chairIns->execute([$sessionSeq, $chairName, $chairAff, 1]);
            }
        }
        $talkTitle = $get($row, 'talk_title');
        $speakerName = $get($row, 'speaker_name');
        if ($talkTitle !== '' && $speakerName !== '') {
            $talkIns->execute([
                $sessionSeq,
                $get($row, 'talk_code'),
                $talkTitle,
                $speakerName,
                $get($row, 'speaker_affiliation'),
                $sort > 0 ? $sort : $talkCount + 1,
            ]);
            $talkCount++;
        }
    }
    fclose($handle);
    ksers_program_notice(
        t('CSV 반영: 새 세션 ', 'CSV imported: new sessions ') . $sessionCount
        . t('건, 발표 ', ', talks ') . $talkCount . t('건', '')
    );
    header('Location: /admin/page/program/list.php');
    exit;
}

header('Location: /admin/page/program/list.php');
exit;
