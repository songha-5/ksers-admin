<?php

// Scientific Program tables for Railway MySQL.
// Column names follow the Supabase draft. Differences the homepage must follow:
// - every table has year; read the congress year
// - categories and sessions use primary key (year, id)
// - chairs and speakers use a char(36) id, plus year
// - scientific_sessions.glance_id links one Program at a Glance cell
// - there is no public schema, RLS, anon key, or storage bucket
// - image_url, cv_url, and abstract_url are file addresses or null
// - category id free-paper always exists and sorts last

function ksers_scientific_ensure_tables($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS scientific_categories (
            year smallint NOT NULL,
            id varchar(80) NOT NULL,
            label varchar(160) NOT NULL,
            sort_order int NOT NULL DEFAULT 0,
            PRIMARY KEY (year, id),
            KEY scientific_categories_sort_idx (year, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS scientific_sessions (
            year smallint NOT NULL,
            id varchar(80) NOT NULL,
            category_id varchar(80) NOT NULL,
            title varchar(255) NOT NULL,
            date_time varchar(160) DEFAULT NULL,
            language varchar(40) DEFAULT NULL,
            glance_id varchar(80) DEFAULT NULL,
            sort_order int NOT NULL DEFAULT 0,
            PRIMARY KEY (year, id),
            UNIQUE KEY scientific_sessions_glance_idx (year, glance_id),
            KEY scientific_sessions_category_idx (year, category_id, sort_order),
            CONSTRAINT fk_scientific_sessions_category
                FOREIGN KEY (year, category_id) REFERENCES scientific_categories (year, id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS scientific_chairs (
            id char(36) NOT NULL,
            year smallint NOT NULL,
            session_id varchar(80) NOT NULL,
            name varchar(255) NOT NULL,
            sort_order int NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY scientific_chairs_session_idx (year, session_id, sort_order),
            CONSTRAINT fk_scientific_chairs_session
                FOREIGN KEY (year, session_id) REFERENCES scientific_sessions (year, id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS scientific_speakers (
            id char(36) NOT NULL,
            year smallint NOT NULL,
            session_id varchar(80) NOT NULL,
            badge varchar(80) DEFAULT NULL,
            talk_title varchar(255) NOT NULL,
            name varchar(255) NOT NULL,
            affiliation varchar(255) DEFAULT NULL,
            description text,
            image_url varchar(500) DEFAULT NULL,
            cv_url varchar(500) DEFAULT NULL,
            abstract_url varchar(500) DEFAULT NULL,
            sort_order int NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY scientific_speakers_session_idx (year, session_id, sort_order),
            CONSTRAINT fk_scientific_speakers_session
                FOREIGN KEY (year, session_id) REFERENCES scientific_sessions (year, id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
}

function ksers_scientific_free_id() {
    return 'free-paper';
}

function ksers_scientific_valid_id($value) {
    return (bool)preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', (string)$value);
}

function ksers_scientific_slug($text) {
    $text = strtolower(trim((string)$text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string)$text, '-');
}

function ksers_scientific_uuid() {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function ksers_scientific_href($year, $id) {
    return '/' . (int)$year . '/en/scientific-program#' . $id;
}

function ksers_scientific_cut($text, $max) {
    if ($text === null) {
        return null;
    }
    $text = trim((string)$text);
    if ($text === '') {
        return null;
    }
    if (preg_match('/^.{0,' . (int)$max . '}/us', $text, $match)) {
        return $match[0];
    }
    return $text;
}

function ksers_scientific_ensure_free_paper($pdo, $year) {
    $year = (int)$year;
    $id = ksers_scientific_free_id();
    $exists = $pdo->prepare('SELECT id FROM scientific_categories WHERE year = ? AND id = ?');
    $exists->execute([$year, $id]);
    if (!$exists->fetchColumn()) {
        $insert = $pdo->prepare(
            'INSERT INTO scientific_categories (year, id, label, sort_order) VALUES (?, ?, ?, 1)'
        );
        $insert->execute([$year, $id, 'Free Paper']);
    }
    ksers_scientific_pin_free_paper($pdo, $year);
}

function ksers_scientific_pin_free_paper($pdo, $year) {
    $year = (int)$year;
    $id = ksers_scientific_free_id();
    $max = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM scientific_categories WHERE year = ? AND id <> ?');
    $max->execute([$year, $id]);
    $order = (int)$max->fetchColumn() + 1;
    $update = $pdo->prepare('UPDATE scientific_categories SET sort_order = ? WHERE year = ? AND id = ?');
    $update->execute([$order, $year, $id]);
}

function ksers_scientific_load($pdo, $year) {
    $year = (int)$year;
    ksers_scientific_ensure_free_paper($pdo, $year);
    $categories = $pdo->prepare(
        'SELECT id, label, sort_order FROM scientific_categories WHERE year = ? ORDER BY sort_order, id'
    );
    $categories->execute([$year]);
    $sessions = $pdo->prepare(
        'SELECT id, category_id, title, date_time, language, glance_id, sort_order
         FROM scientific_sessions WHERE year = ? ORDER BY sort_order, id'
    );
    $sessions->execute([$year]);
    $chairs = $pdo->prepare(
        'SELECT id, session_id, name, sort_order FROM scientific_chairs WHERE year = ? ORDER BY sort_order, id'
    );
    $chairs->execute([$year]);
    $speakers = $pdo->prepare(
        'SELECT id, session_id, badge, talk_title, name, affiliation, description, image_url, cv_url, abstract_url, sort_order
         FROM scientific_speakers WHERE year = ? ORDER BY sort_order, id'
    );
    $speakers->execute([$year]);
    return [
        'categories' => $categories->fetchAll(PDO::FETCH_ASSOC),
        'sessions' => $sessions->fetchAll(PDO::FETCH_ASSOC),
        'chairs' => $chairs->fetchAll(PDO::FETCH_ASSOC),
        'speakers' => $speakers->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function ksers_scientific_ids_by_glance($pdo, $year) {
    $stmt = $pdo->prepare('SELECT glance_id, id FROM scientific_sessions WHERE year = ? AND glance_id IS NOT NULL');
    $stmt->execute([(int)$year]);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[$row['glance_id']] = $row['id'];
    }
    return $map;
}

function ksers_scientific_glance_rows($pdo, $year) {
    $stmt = $pdo->prepare(
        'SELECT s.id, s.title, s.content, s.topic, s.room_code, d.label AS day_label, d.sort_order AS day_sort,
                t.time_label, t.sort_order AS time_sort, s.sort_order
         FROM program_sessions s
         JOIN program_time_slots t ON t.year = s.year AND t.id = s.slot_id
         JOIN program_days d ON d.year = t.year AND d.id = t.day_id
         WHERE s.year = ?
         ORDER BY d.sort_order, t.sort_order, s.sort_order, s.id'
    );
    $stmt->execute([(int)$year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ksers_scientific_date_time($dayLabel, $timeLabel) {
    $day = trim((string)$dayLabel);
    if (preg_match('/([A-Za-z]+ \d{1,2} \([A-Za-z]{3}\))/', $day, $match)) {
        $day = $match[1];
    }
    $time = trim((string)$timeLabel);
    if ($day === '') {
        return $time === '' ? null : $time;
    }
    if ($time === '') {
        return $day;
    }
    return ksers_scientific_cut($day . ', ' . $time, 160);
}

function ksers_scientific_glance_info($pdo, $year, $glanceId) {
    foreach (ksers_scientific_glance_rows($pdo, $year) as $row) {
        if ($row['id'] === $glanceId) {
            return $row;
        }
    }
    return null;
}

function ksers_scientific_sync_year($pdo, $year) {
    $year = (int)$year;
    $sessions = $pdo->prepare(
        'SELECT id, glance_id FROM scientific_sessions WHERE year = ? AND glance_id IS NOT NULL'
    );
    $sessions->execute([$year]);
    $rows = ksers_scientific_glance_rows($pdo, $year);
    $byId = [];
    foreach ($rows as $row) {
        $byId[$row['id']] = $row;
    }
    $updateSession = $pdo->prepare('UPDATE scientific_sessions SET date_time = ? WHERE year = ? AND id = ?');
    $updateHref = $pdo->prepare('UPDATE program_sessions SET href = ? WHERE year = ? AND id = ?');
    $clear = $pdo->prepare('UPDATE scientific_sessions SET glance_id = NULL WHERE year = ? AND id = ?');
    foreach ($sessions->fetchAll(PDO::FETCH_ASSOC) as $session) {
        if (!isset($byId[$session['glance_id']])) {
            $clear->execute([$year, $session['id']]);
            continue;
        }
        $glance = $byId[$session['glance_id']];
        $updateSession->execute([
            ksers_scientific_date_time($glance['day_label'], $glance['time_label']),
            $year,
            $session['id'],
        ]);
        $updateHref->execute([ksers_scientific_href($year, $session['id']), $year, $session['glance_id']]);
    }
}

function ksers_scientific_detach_glance($pdo, $year, $glanceId) {
    $stmt = $pdo->prepare('UPDATE scientific_sessions SET glance_id = NULL WHERE year = ? AND glance_id = ?');
    $stmt->execute([(int)$year, (string)$glanceId]);
}

function ksers_scientific_category_for_cell($title) {
    $title = trim((string)$title);
    $coded = [
        '/^VP\d+$/i' => ['video-presentation', 'Video Presentation'],
        '/^SY\d+$/i' => ['symposium', 'Symposium'],
        '/^JS\d+$/i' => ['joint-symposium', 'Joint Symposium'],
        '/^LS\d+$/i' => ['luncheon-symposium', 'Luncheon Symposium'],
        '/^CS\d+$/i' => ['co-symposium', 'Co-Symposium'],
        '/^MV\d+$/i' => ['master-video', 'Master Video'],
        '/^OP\d+$/i' => ['oral-presentation', 'Oral Presentation'],
        '/^SL\d+$/i' => ['semi-live', 'Semi-Live'],
        '/^PS\d+$/i' => ['plenary', 'Plenary'],
        '/^KL\d+$/i' => ['keynote-lecture', 'Keynote Lecture'],
    ];
    foreach ($coded as $pattern => $category) {
        if (preg_match($pattern, $title)) {
            return $category;
        }
    }
    if (preg_match('/keynote/i', $title)) {
        return ['keynote-lecture', 'Keynote Lecture'];
    }
    if (preg_match('/break|coffee/i', $title)) {
        return ['break', 'Break'];
    }
    if (preg_match('/ceremony|dinner|gala|assembly|presidential lecture|opening/i', $title)) {
        return ['ceremony', 'Ceremony'];
    }
    return [ksers_scientific_free_id(), 'Free Paper'];
}

function ksers_scientific_seed_title($title, $content) {
    $title = trim((string)$title);
    $content = trim((string)$content);
    if (preg_match('/^[A-Z]{1,6}\d+$/', $title)) {
        $text = '[' . $title . ']';
        if ($content !== '') {
            $text .= ' ' . $content;
        }
        return $text;
    }
    if ($content !== '' && stripos($content, $title) === false) {
        return trim($title . ' ' . $content);
    }
    return $title;
}

function ksers_scientific_seed_language($text) {
    if (preg_match('/\[\s*kor\s*\]/i', (string)$text)) {
        return 'Kor';
    }
    return null;
}

function ksers_scientific_seed_from_glance($pdo, $year) {
    $year = (int)$year;
    ksers_scientific_ensure_free_paper($pdo, $year);
    $takenGlance = ksers_scientific_ids_by_glance($pdo, $year);
    $usedIds = [];
    $existing = $pdo->prepare('SELECT id FROM scientific_sessions WHERE year = ?');
    $existing->execute([$year]);
    foreach ($existing->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $usedIds[$id] = true;
    }
    $categories = [];
    $knownCategories = $pdo->prepare('SELECT id FROM scientific_categories WHERE year = ?');
    $knownCategories->execute([$year]);
    foreach ($knownCategories->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $categories[$id] = true;
    }
    $nextCategory = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM scientific_categories WHERE year = ? AND id <> ?');
    $nextCategory->execute([$year, ksers_scientific_free_id()]);
    $categorySort = (int)$nextCategory->fetchColumn();
    $sessionSort = [];
    $sortRows = $pdo->prepare(
        'SELECT category_id, COALESCE(MAX(sort_order), 0) AS sort_order FROM scientific_sessions WHERE year = ? GROUP BY category_id'
    );
    $sortRows->execute([$year]);
    foreach ($sortRows->fetchAll(PDO::FETCH_ASSOC) as $sortRow) {
        $sessionSort[$sortRow['category_id']] = (int)$sortRow['sort_order'];
    }
    $added = 0;
    $insertCategory = $pdo->prepare(
        'INSERT INTO scientific_categories (year, id, label, sort_order) VALUES (?, ?, ?, ?)'
    );
    $insertSession = $pdo->prepare(
        'INSERT INTO scientific_sessions
            (year, id, category_id, title, date_time, language, glance_id, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $pdo->beginTransaction();
    try {
        foreach (ksers_scientific_glance_rows($pdo, $year) as $cell) {
            if (isset($takenGlance[$cell['id']])) {
                continue;
            }
            list($categoryId, $categoryLabel) = ksers_scientific_category_for_cell($cell['title']);
            if (!isset($categories[$categoryId])) {
                $categorySort++;
                $insertCategory->execute([$year, $categoryId, $categoryLabel, $categorySort]);
                $categories[$categoryId] = true;
            }
            $base = preg_match('/^[A-Z]{1,6}\d+$/', trim((string)$cell['title']))
                ? strtolower(trim((string)$cell['title']))
                : ksers_scientific_slug($cell['title']);
            if ($base === '' || $base === $categoryId) {
                $short = ksers_scientific_slug(preg_replace('/\s.*$/u', '', trim((string)$cell['title'])));
                $base = ($short !== '' && $short !== $categoryId && ksers_scientific_valid_id($short))
                    ? $short
                    : ksers_scientific_slug($cell['id']);
            }
            if ($base === '' || !ksers_scientific_valid_id($base)) {
                $base = 'session';
            }
            $id = $base;
            $n = 2;
            while (isset($usedIds[$id])) {
                $id = $base . '-' . $n;
                $n++;
            }
            $usedIds[$id] = true;
            $source = trim((string)$cell['title'] . ' ' . (string)$cell['content']);
            $language = ksers_scientific_seed_language($source);
            $title = ksers_scientific_seed_title($cell['title'], (string)$cell['content']);
            if ($language !== null) {
                $title = trim(preg_replace('/\[\s*kor\s*\]/i', '', $title));
            }
            $title = ksers_scientific_cut($title, 255);
            if ($title === null) {
                $title = $id;
            }
            $sessionSort[$categoryId] = isset($sessionSort[$categoryId]) ? $sessionSort[$categoryId] + 1 : 1;
            $insertSession->execute([
                $year,
                $id,
                $categoryId,
                $title,
                ksers_scientific_date_time($cell['day_label'], $cell['time_label']),
                $language,
                $cell['id'],
                $sessionSort[$categoryId],
            ]);
            $added++;
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('scientific seed failed: ' . $e->getMessage());
        return -1;
    }
    ksers_scientific_pin_free_paper($pdo, $year);
    ksers_scientific_sync_year($pdo, $year);
    return $added;
}

function ksers_scientific_dir() {
    $dir = getenv('KSERS_SCIENTIFIC_DIR');
    if ($dir !== false && $dir !== '') {
        return $dir;
    }
    return '/data/scientific';
}

function ksers_scientific_file_url($speakerId, $field, $stored) {
    $path = '/service/scientific_file.php?id=' . rawurlencode($speakerId)
        . '&f=' . rawurlencode($field)
        . '&file=' . rawurlencode($stored);
    $base = ksers_public_base();
    return $base === '' ? $path : ($base . $path);
}

function ksers_scientific_delete_stored($url) {
    if (!preg_match('/(?:^|[?&])file=([^&]+)/', (string)$url, $match)) {
        return;
    }
    $file = basename(rawurldecode($match[1]));
    if ($file === '' || preg_match('/[^a-zA-Z0-9._-]/', $file)) {
        return;
    }
    $path = ksers_scientific_dir() . '/' . $file;
    if (is_file($path)) {
        unlink($path);
    }
}

function ksers_scientific_store_upload($speakerId, $field, $file) {
    $allowed = [
        'image' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'cv' => ['pdf'],
        'abstract' => ['pdf'],
    ];
    if (!isset($allowed[$field]) || !is_array($file) || !isset($file['error'])) {
        return [null, null];
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || (int)$file['size'] > 10 * 1024 * 1024) {
        return [null, t('파일은 10MB 이하만 올릴 수 있습니다.', 'Upload a file up to 10MB.')];
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed[$field], true)) {
        return [null, t('사진은 JPG·PNG, CV와 Abstract는 PDF만 올릴 수 있습니다.', 'Photos must be JPG or PNG. CV and Abstract must be PDF.')];
    }
    $dir = ksers_scientific_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return [null, t('파일 저장 위치를 만들지 못했습니다.', 'Could not create the file folder.')];
    }
    $stored = $field . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
        return [null, t('파일을 저장하지 못했습니다.', 'The file could not be saved.')];
    }
    return [ksers_scientific_file_url($speakerId, $field, $stored), null];
}
