<?php

function ksers_gallery_ensure_tables($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS gallery_photo (
            seq int NOT NULL AUTO_INCREMENT,
            year smallint NOT NULL,
            file_name varchar(200) NOT NULL,
            stored_name varchar(80) NOT NULL,
            sort_order int NOT NULL DEFAULT 1,
            reg_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (seq),
            KEY idx_gallery_year (year, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
}

function ksers_gallery_dir() {
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }
    $fromEnv = getenv('KSERS_GALLERY_DIR');
    $chosen = ($fromEnv !== false && $fromEnv !== '') ? $fromEnv : '/data/gallery';
    $chosen = rtrim($chosen, '/\\');
    if (!is_dir($chosen) && !@mkdir($chosen, 0755, true) && !is_dir($chosen)) {
        $chosen = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'gallery';
        if (!is_dir($chosen) && !@mkdir($chosen, 0755, true) && !is_dir($chosen)) {
            $chosen = '';
        }
    }
    $dir = $chosen;
    return $dir;
}

function ksers_gallery_page_size() {
    return 20;
}

function ksers_gallery_count($pdo, $year) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM gallery_photo WHERE year = ?');
    $stmt->execute([(int)$year]);
    return (int)$stmt->fetchColumn();
}

function ksers_gallery_list_url($page, $total) {
    $pages = $total > 0 ? (int)ceil($total / ksers_gallery_page_size()) : 1;
    $page = (int)$page;
    if ($page < 1) {
        $page = 1;
    }
    if ($page > $pages) {
        $page = $pages;
    }
    return '/admin/page/gallery/index.php' . ($page > 1 ? '?page=' . $page : '');
}

function ksers_gallery_rows($pdo, $year) {
    $stmt = $pdo->prepare(
        'SELECT seq, year, file_name, stored_name, sort_order, reg_date
         FROM gallery_photo
         WHERE year = ?
         ORDER BY sort_order ASC, seq ASC'
    );
    $stmt->execute([(int)$year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ksers_gallery_find($pdo, $year, $seq) {
    $stmt = $pdo->prepare(
        'SELECT seq, year, file_name, stored_name, sort_order, reg_date
         FROM gallery_photo
         WHERE year = ? AND seq = ?'
    );
    $stmt->execute([(int)$year, (int)$seq]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : null;
}

function ksers_gallery_file_href($seq) {
    return '/service/gallery_file.php?seq=' . (int)$seq;
}

function ksers_gallery_public_url($seq) {
    $path = ksers_gallery_file_href($seq);
    $base = ksers_public_base();
    return $base === '' ? $path : ($base . $path);
}

function ksers_gallery_public_photos($pdo, $year) {
    $photos = [];
    foreach (ksers_gallery_rows($pdo, $year) as $row) {
        $stored = basename((string)$row['stored_name']);
        if ($stored === '' || preg_match('/[^a-zA-Z0-9._-]/', $stored)) {
            continue;
        }
        $dir = ksers_gallery_dir();
        if ($dir === '') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $stored;
        if (!is_file($path)) {
            continue;
        }
        $info = @getimagesize($path);
        if ($info === false) {
            continue;
        }
        $name = (string)$row['file_name'];
        $alt = pathinfo($name, PATHINFO_FILENAME);
        if ($alt === '') {
            $alt = $name;
        }
        $photos[] = [
            'id' => (string)(int)$row['seq'],
            'src' => ksers_gallery_public_url($row['seq']),
            'width' => (int)$info[0],
            'height' => (int)$info[1],
            'alt' => $alt,
            'file_name' => $name,
            'sort_order' => (int)$row['sort_order'],
        ];
    }
    return $photos;
}

function ksers_gallery_delete_stored($stored) {
    $file = basename((string)$stored);
    if ($file === '' || preg_match('/[^a-zA-Z0-9._-]/', $file)) {
        return;
    }
    $dir = ksers_gallery_dir();
    if ($dir === '') {
        return;
    }
    $path = $dir . DIRECTORY_SEPARATOR . $file;
    if (is_file($path)) {
        unlink($path);
    }
}

function ksers_gallery_original_name($name) {
    $name = basename(str_replace('\\', '/', (string)$name));
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);
    if (!is_string($name) || $name === '') {
        return 'image';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 200, 'UTF-8');
    }
    return substr($name, 0, 200);
}

function ksers_gallery_store_upload($file) {
    if (!is_array($file) || !isset($file['error'])) {
        return [null, null, t('이미지를 선택해 주세요.', 'Choose an image.')];
    }
    if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null, t('이미지를 선택해 주세요.', 'Choose an image.')];
    }
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        return [null, null, t('이미지를 올리지 못했습니다.', 'The image could not be uploaded.')];
    }
    if ((int)$file['size'] > 10 * 1024 * 1024) {
        return [null, null, t('이미지는 10MB 이하만 올릴 수 있습니다.', 'Upload an image up to 10MB.')];
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed, true)) {
        return [null, null, t('JPG, PNG, WEBP, GIF 이미지만 올릴 수 있습니다.', 'Upload a JPG, PNG, WEBP, or GIF image.')];
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
    if (defined('IMAGETYPE_WEBP')) {
        $types[] = IMAGETYPE_WEBP;
    }
    if ($info === false || !in_array($info[2], $types, true)) {
        return [null, null, t('이미지 파일이 아닙니다.', 'That file is not an image.')];
    }
    $dir = ksers_gallery_dir();
    if ($dir === '') {
        return [null, null, t('이미지 저장 위치를 만들지 못했습니다.', 'Could not create the image folder.')];
    }
    $stored = 'g_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . DIRECTORY_SEPARATOR . $stored)) {
        return [null, null, t('이미지를 저장하지 못했습니다.', 'The image could not be saved.')];
    }
    return [$stored, ksers_gallery_original_name($file['name']), null];
}

function ksers_gallery_ids($pdo, $year) {
    $stmt = $pdo->prepare(
        'SELECT seq FROM gallery_photo WHERE year = ? ORDER BY sort_order ASC, seq ASC'
    );
    $stmt->execute([(int)$year]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function ksers_gallery_write_order($pdo, $year, $ids) {
    $update = $pdo->prepare('UPDATE gallery_photo SET sort_order = ? WHERE year = ? AND seq = ?');
    $order = 1;
    foreach ($ids as $id) {
        $update->execute([$order, (int)$year, (int)$id]);
        $order++;
    }
}

function ksers_gallery_add($pdo, $year, $file) {
    list($stored, $original, $error) = ksers_gallery_store_upload($file);
    if ($error !== null) {
        return $error;
    }
    try {
        $pdo->beginTransaction();
        $insert = $pdo->prepare(
            'INSERT INTO gallery_photo (year, file_name, stored_name, sort_order, reg_date)
             VALUES (?, ?, ?, 0, NOW())'
        );
        $insert->execute([(int)$year, $original, $stored]);
        $seq = (int)$pdo->lastInsertId();
        $ids = array_values(array_filter(ksers_gallery_ids($pdo, $year), function ($id) use ($seq) {
            return $id !== $seq;
        }));
        array_unshift($ids, $seq);
        ksers_gallery_write_order($pdo, $year, $ids);
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        ksers_gallery_delete_stored($stored);
        error_log('gallery add failed: ' . $e->getMessage());
        return t('이미지를 저장하지 못했습니다.', 'The image could not be saved.');
    }
    return null;
}

function ksers_gallery_replace($pdo, $year, $seq, $file) {
    $row = ksers_gallery_find($pdo, $year, $seq);
    if (!$row) {
        return t('이미지를 찾지 못했습니다.', 'The image was not found.');
    }
    list($stored, $original, $error) = ksers_gallery_store_upload($file);
    if ($error !== null) {
        return $error;
    }
    try {
        $update = $pdo->prepare(
            'UPDATE gallery_photo SET file_name = ?, stored_name = ? WHERE year = ? AND seq = ?'
        );
        $update->execute([$original, $stored, (int)$year, (int)$seq]);
    } catch (Exception $e) {
        ksers_gallery_delete_stored($stored);
        error_log('gallery replace failed: ' . $e->getMessage());
        return t('이미지를 저장하지 못했습니다.', 'The image could not be saved.');
    }
    if ($row['stored_name'] !== $stored) {
        ksers_gallery_delete_stored($row['stored_name']);
    }
    return null;
}

function ksers_gallery_delete($pdo, $year, $seqs) {
    $wanted = [];
    foreach ($seqs as $seq) {
        $seq = (int)$seq;
        if ($seq > 0) {
            $wanted[$seq] = $seq;
        }
    }
    $wanted = array_values($wanted);
    if (!$wanted) {
        return t('삭제할 이미지를 선택해 주세요.', 'Select images to delete.');
    }
    $marks = implode(',', array_fill(0, count($wanted), '?'));
    $stmt = $pdo->prepare(
        "SELECT seq, stored_name FROM gallery_photo WHERE year = ? AND seq IN ($marks)"
    );
    $stmt->execute(array_merge([(int)$year], $wanted));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        return t('삭제할 이미지를 찾지 못했습니다.', 'Those images were not found.');
    }
    $found = [];
    $stored = [];
    foreach ($rows as $row) {
        $found[] = (int)$row['seq'];
        $stored[] = $row['stored_name'];
    }
    $deleteMarks = implode(',', array_fill(0, count($found), '?'));
    try {
        $pdo->beginTransaction();
        $delete = $pdo->prepare(
            "DELETE FROM gallery_photo WHERE year = ? AND seq IN ($deleteMarks)"
        );
        $delete->execute(array_merge([(int)$year], $found));
        ksers_gallery_write_order($pdo, $year, ksers_gallery_ids($pdo, $year));
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('gallery delete failed: ' . $e->getMessage());
        return t('이미지를 삭제하지 못했습니다.', 'The images could not be deleted.');
    }
    foreach ($stored as $name) {
        ksers_gallery_delete_stored($name);
    }
    return null;
}

function ksers_gallery_move($pdo, $year, $seq, $direction) {
    $ids = ksers_gallery_ids($pdo, $year);
    $index = array_search((int)$seq, $ids, true);
    if ($index === false) {
        return t('이미지를 찾지 못했습니다.', 'The image was not found.');
    }
    $swap = $direction === 'up' ? $index - 1 : $index + 1;
    if ($swap < 0 || $swap >= count($ids)) {
        return null;
    }
    $hold = $ids[$swap];
    $ids[$swap] = $ids[$index];
    $ids[$index] = $hold;
    ksers_gallery_write_order($pdo, $year, $ids);
    return null;
}
