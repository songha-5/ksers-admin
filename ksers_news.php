<?php

function ksers_news_ensure_tables($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS news_post (
            seq int NOT NULL AUTO_INCREMENT,
            year smallint NOT NULL,
            public_id varchar(80) NOT NULL,
            title varchar(300) NOT NULL,
            written_at date NOT NULL,
            view_count int NOT NULL DEFAULT 0,
            body mediumtext NOT NULL,
            is_hidden tinyint NOT NULL DEFAULT 0,
            reg_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (seq),
            UNIQUE KEY uq_news_year_id (year, public_id),
            KEY idx_news_year_date (year, written_at, seq)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
}

function ksers_news_page_size() {
    return 15;
}

function ksers_news_today() {
    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul')))->format('Y-m-d');
}

function ksers_news_dir() {
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }
    $fromEnv = getenv('KSERS_NEWS_DIR');
    $chosen = ($fromEnv !== false && $fromEnv !== '') ? $fromEnv : '/data/news';
    $chosen = rtrim($chosen, '/\\');
    if (!is_dir($chosen) && !@mkdir($chosen, 0755, true) && !is_dir($chosen)) {
        $chosen = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'news';
        if (!is_dir($chosen) && !@mkdir($chosen, 0755, true) && !is_dir($chosen)) {
            $chosen = '';
        }
    }
    $dir = $chosen;
    return $dir;
}

function ksers_news_list_url($title, $date, $page) {
    $query = [];
    $title = trim((string)$title);
    $date = trim((string)$date);
    if ($title !== '') {
        $query['title'] = $title;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $query['date'] = $date;
    }
    $page = (int)$page;
    if ($page > 1) {
        $query['page'] = $page;
    }
    $qs = http_build_query($query);
    return '/admin/page/news/index.php' . ($qs !== '' ? '?' . $qs : '');
}

function ksers_news_slug($title) {
    $title = strtolower(trim((string)$title));
    $title = preg_replace('/[^a-z0-9]+/', '-', $title);
    $title = trim((string)$title, '-');
    if (strlen($title) > 60) {
        $title = rtrim(substr($title, 0, 60), '-');
    }
    return $title;
}

function ksers_news_id_taken($pdo, $year, $publicId, $exceptSeq) {
    $stmt = $pdo->prepare('SELECT seq FROM news_post WHERE year = ? AND public_id = ? AND seq <> ?');
    $stmt->execute([(int)$year, $publicId, (int)$exceptSeq]);
    return (bool)$stmt->fetchColumn();
}

function ksers_news_unique_id($pdo, $year, $title, $seq) {
    $base = ksers_news_slug($title);
    if ($base === '') {
        $base = 'n' . (int)$seq;
    }
    $candidate = $base;
    $suffix = 2;
    while (ksers_news_id_taken($pdo, $year, $candidate, $seq)) {
        $candidate = $base . '-' . $suffix;
        $suffix++;
        if ($suffix > 200) {
            return 'n' . (int)$seq;
        }
    }
    return $candidate;
}

function ksers_news_find($pdo, $year, $seq) {
    $stmt = $pdo->prepare(
        'SELECT seq, year, public_id, title, written_at, view_count, body, is_hidden
         FROM news_post WHERE year = ? AND seq = ?'
    );
    $stmt->execute([(int)$year, (int)$seq]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : null;
}

function ksers_news_search($pdo, $year, $title, $date, $page) {
    $where = 'year = ?';
    $params = [(int)$year];
    $title = trim((string)$title);
    if ($title !== '') {
        $where .= ' AND title LIKE ?';
        $params[] = '%' . addcslashes($title, '%_\\') . '%';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) {
        $where .= ' AND written_at = ?';
        $params[] = $date;
    }
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM news_post WHERE ' . $where);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $perPage = ksers_news_page_size();
    $pages = $total > 0 ? (int)ceil($total / $perPage) : 1;
    $page = (int)$page;
    if ($page < 1) {
        $page = 1;
    }
    if ($page > $pages) {
        $page = $pages;
    }
    $offset = ($page - 1) * $perPage;
    $sql = 'SELECT seq, public_id, title, written_at, view_count, is_hidden
            FROM news_post WHERE ' . $where . '
            ORDER BY written_at DESC, seq DESC
            LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return [
        'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
    ];
}

function ksers_news_public_row($row) {
    return [
        'id' => (string)$row['public_id'],
        'title' => (string)$row['title'],
        'writtenAt' => (string)$row['written_at'],
        'viewCount' => (int)$row['view_count'],
        'body' => (string)$row['body'],
    ];
}

function ksers_news_public_posts($pdo, $year) {
    $stmt = $pdo->prepare(
        'SELECT public_id, title, written_at, view_count, body
         FROM news_post
         WHERE year = ? AND is_hidden = 0
         ORDER BY written_at DESC, seq DESC'
    );
    $stmt->execute([(int)$year]);
    $posts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $posts[] = ksers_news_public_row($row);
    }
    return $posts;
}

function ksers_news_public_find($pdo, $year, $publicId) {
    $stmt = $pdo->prepare(
        'SELECT public_id, title, written_at, view_count, body
         FROM news_post
         WHERE year = ? AND public_id = ? AND is_hidden = 0'
    );
    $stmt->execute([(int)$year, (string)$publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? ksers_news_public_row($row) : null;
}

function ksers_news_increment_view($pdo, $year, $publicId) {
    $stmt = $pdo->prepare(
        'UPDATE news_post
         SET view_count = view_count + 1
         WHERE year = ? AND public_id = ? AND is_hidden = 0'
    );
    $stmt->execute([(int)$year, (string)$publicId]);
    if ($stmt->rowCount() < 1) {
        return null;
    }
    return ksers_news_public_find($pdo, $year, $publicId);
}

function ksers_news_body_is_empty($html) {
    $html = (string)$html;
    if (preg_match('/<(img|video|table)\b/i', $html)) {
        return false;
    }
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\xc2\xa0", ' ', $text);
    return trim($text) === '';
}

function ksers_news_save($pdo, $year, $seq, $title, $body) {
    $title = trim((string)$title);
    if ($title === '') {
        return [null, t('제목을 입력해주세요', 'Enter a title.')];
    }
    if (ksers_news_body_is_empty($body)) {
        return [null, t('본문을 입력해주세요', 'Enter the body.')];
    }
    if (mb_strlen($title) > 300) {
        return [null, t('제목은 300자까지입니다.', 'The title can be up to 300 characters.')];
    }
    if (strlen((string)$body) > 200000) {
        return [null, t('본문이 너무 깁니다.', 'The body is too long.')];
    }
    $body = ksers_news_sanitize($body);
    $seq = (int)$seq;
    if ($seq > 0) {
        $current = ksers_news_find($pdo, $year, $seq);
        if (!$current) {
            return [null, t('글을 찾지 못했습니다.', 'The post was not found.')];
        }
        ksers_news_release_files((string)$current['body'], $body);
        $stmt = $pdo->prepare('UPDATE news_post SET title = ?, body = ? WHERE year = ? AND seq = ?');
        $stmt->execute([$title, $body, (int)$year, $seq]);
        return [$seq, null];
    }
    $placeholder = 'tmp-' . bin2hex(random_bytes(8));
    $stmt = $pdo->prepare(
        'INSERT INTO news_post (year, public_id, title, written_at, view_count, body, is_hidden)
         VALUES (?, ?, ?, ?, 0, ?, 0)'
    );
    $stmt->execute([(int)$year, $placeholder, $title, ksers_news_today(), $body]);
    $seq = (int)$pdo->lastInsertId();
    $publicId = ksers_news_unique_id($pdo, $year, $title, $seq);
    $rename = $pdo->prepare('UPDATE news_post SET public_id = ? WHERE seq = ?');
    $rename->execute([$publicId, $seq]);
    return [$seq, null];
}

function ksers_news_delete($pdo, $year, $seq) {
    $current = ksers_news_find($pdo, $year, $seq);
    if (!$current) {
        return t('글을 찾지 못했습니다.', 'The post was not found.');
    }
    ksers_news_release_files((string)$current['body'], '');
    $stmt = $pdo->prepare('DELETE FROM news_post WHERE year = ? AND seq = ?');
    $stmt->execute([(int)$year, (int)$seq]);
    return null;
}

function ksers_news_set_hidden($pdo, $year, $seq, $hidden) {
    $stmt = $pdo->prepare('UPDATE news_post SET is_hidden = ? WHERE year = ? AND seq = ?');
    $stmt->execute([$hidden ? 1 : 0, (int)$year, (int)$seq]);
    if ($stmt->rowCount() < 1 && !ksers_news_find($pdo, $year, $seq)) {
        return t('글을 찾지 못했습니다.', 'The post was not found.');
    }
    return null;
}

function ksers_news_safe_url($value) {
    $url = trim((string)$value);
    if ($url === '') {
        return '';
    }
    $lower = strtolower($url);
    if (strpos($lower, 'javascript:') === 0 || strpos($lower, 'data:') === 0 || strpos($url, '//') === 0) {
        return '';
    }
    if ($url[0] === '/' || strpos($lower, 'mailto:') === 0 || strpos($lower, 'https://') === 0 || strpos($lower, 'http://') === 0) {
        return $url;
    }
    return '';
}

function ksers_news_sanitize($html) {
    $html = str_replace("\0", '', (string)$html);
    $trimmed = ltrim($html);
    if ($trimmed === '') {
        return '';
    }
    if ($trimmed[0] !== '<') {
        return '<p>' . htmlspecialchars(trim($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    }
    return ksers_news_sanitize_html($trimmed);
}

function ksers_news_sanitize_html($html) {
    $allowed = [
        'p' => true, 'br' => true, 'strong' => true, 'b' => true, 'em' => true, 'i' => true, 'u' => true,
        'ul' => true, 'ol' => true, 'li' => true, 'h1' => true, 'h2' => true, 'h3' => true, 'h4' => true,
        'blockquote' => true, 'div' => true, 'span' => true, 'pre' => true, 'code' => true, 'hr' => true,
        'figcaption' => true, 'table' => true, 'thead' => true, 'tbody' => true, 'tfoot' => true,
        'tr' => true, 'th' => true, 'td' => true, 'img' => true, 'figure' => true, 'video' => true,
        'source' => true, 'a' => true,
    ];
    $drop = [
        'script' => true, 'style' => true, 'iframe' => true, 'object' => true, 'embed' => true,
        'noscript' => true, 'template' => true, 'svg' => true, 'math' => true, 'link' => true,
        'meta' => true, 'base' => true, 'form' => true,
    ];
    $source = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $source->loadHTML(
        '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'
        . $html . '</body></html>',
        LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $body = $source->getElementsByTagName('body')->item(0);
    if (!$body) {
        return '';
    }
    $clean = new DOMDocument('1.0', 'UTF-8');
    $root = $clean->createElement('div');
    $clean->appendChild($root);
    foreach (ksers_news_sanitize_children($body, $clean, $allowed, $drop) as $child) {
        $root->appendChild($child);
    }
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $clean->saveHTML($child);
    }
    return $out;
}

function ksers_news_sanitize_children($node, $clean, $allowed, $drop) {
    $items = [];
    foreach ($node->childNodes as $child) {
        foreach (ksers_news_sanitize_node($child, $clean, $allowed, $drop) as $item) {
            $items[] = $item;
        }
    }
    return $items;
}

function ksers_news_sanitize_node($node, $clean, $allowed, $drop) {
    if ($node->nodeType === XML_TEXT_NODE) {
        return [$clean->createTextNode($node->nodeValue)];
    }
    if ($node->nodeType !== XML_ELEMENT_NODE) {
        return [];
    }
    $tag = strtolower($node->nodeName);
    if (isset($drop[$tag])) {
        return [];
    }
    if (!isset($allowed[$tag])) {
        return ksers_news_sanitize_children($node, $clean, $allowed, $drop);
    }
    $element = $clean->createElement($tag);
    ksers_news_copy_attributes($node, $element, $tag);
    foreach (ksers_news_sanitize_children($node, $clean, $allowed, $drop) as $child) {
        $element->appendChild($child);
    }
    if ($tag === 'img' && $element->getAttribute('src') === '') {
        return [];
    }
    if ($tag === 'source' && $element->getAttribute('src') === '') {
        return [];
    }
    if ($tag === 'video' && !ksers_news_video_has_source($element)) {
        return [];
    }
    return [$element];
}

function ksers_news_video_has_source($element) {
    if ($element->getAttribute('src') !== '') {
        return true;
    }
    foreach ($element->getElementsByTagName('source') as $source) {
        if ($source->getAttribute('src') !== '') {
            return true;
        }
    }
    return false;
}

function ksers_news_copy_attributes($source, $target, $tag) {
    if ($tag === 'a') {
        $href = ksers_news_safe_url($source->getAttribute('href'));
        if ($href !== '') {
            $target->setAttribute('href', $href);
        }
        if ($source->hasAttribute('download')) {
            $name = trim($source->getAttribute('download'));
            $name = str_replace(['"', '<', '>', "\r", "\n"], '', $name);
            if (strlen($name) > 180) {
                $name = substr($name, 0, 180);
            }
            $target->setAttribute('download', $name);
        }
        return;
    }
    if ($tag === 'img') {
        $src = ksers_news_safe_url($source->getAttribute('src'));
        if ($src !== '') {
            $target->setAttribute('src', $src);
        }
        $target->setAttribute('alt', trim($source->getAttribute('alt')));
        return;
    }
    if ($tag === 'video' || $tag === 'source') {
        $src = ksers_news_safe_url($source->getAttribute('src'));
        if ($src !== '') {
            $target->setAttribute('src', $src);
        }
        return;
    }
    if ($tag === 'th' || $tag === 'td') {
        foreach (['colspan', 'rowspan'] as $name) {
            $span = (int)$source->getAttribute($name);
            if ($span > 1) {
                $target->setAttribute($name, (string)$span);
            }
        }
    }
}

function ksers_news_file_names($html) {
    $names = [];
    if (!preg_match_all('/news_file\.php\?name=([A-Za-z0-9._%-]+)/', (string)$html, $matches)) {
        return $names;
    }
    foreach ($matches[1] as $raw) {
        $stored = basename(rawurldecode($raw));
        if (preg_match('/^[a-f0-9]{32}\.[a-z0-9]+$/', $stored)) {
            $names[$stored] = true;
        }
    }
    return $names;
}

function ksers_news_release_files($before, $after) {
    $keep = ksers_news_file_names($after);
    $dir = ksers_news_dir();
    if ($dir === '') {
        return;
    }
    foreach (ksers_news_file_names($before) as $stored => $unused) {
        if (isset($keep[$stored])) {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $stored;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function ksers_news_download_name($name) {
    $name = basename(str_replace('\\', '/', (string)$name));
    $name = preg_replace('/[^\p{L}\p{N}._ -]+/u', '', $name);
    $name = trim((string)$name);
    if ($name === '' || $name === '.' || $name === '..') {
        return 'file';
    }
    if (strlen($name) > 120) {
        $name = substr($name, -120);
    }
    return $name;
}

function ksers_news_file_url($stored) {
    $path = '/service/news_file.php?name=' . rawurlencode($stored);
    $base = ksers_public_base();
    return $base === '' ? $path : ($base . $path);
}

function ksers_news_store_upload($file, $kind) {
    if (!is_array($file) || !isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['error' => t('파일을 선택해 주세요.', 'Choose a file.')];
    }
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => t('파일을 올리지 못했습니다.', 'The file could not be uploaded.')];
    }
    $groups = [
        'image' => ['ext' => ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'limit' => 10 * 1024 * 1024],
        'video' => ['ext' => ['mp4', 'webm'], 'limit' => 80 * 1024 * 1024],
        'file' => ['ext' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'hwp', 'hwpx', 'txt', 'csv'], 'limit' => 20 * 1024 * 1024],
    ];
    if (!isset($groups[$kind])) {
        return ['error' => t('파일을 올리지 못했습니다.', 'The file could not be uploaded.')];
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $groups[$kind]['ext'], true)) {
        return ['error' => t('이 파일 형식은 넣을 수 없습니다.', 'This file type cannot be used.')];
    }
    if ((int)$file['size'] > $groups[$kind]['limit']) {
        return ['error' => t('파일이 너무 큽니다.', 'The file is too large.')];
    }
    $dir = ksers_news_dir();
    if ($dir === '') {
        return ['error' => t('파일을 저장할 곳이 없습니다.', 'There is nowhere to store the file.')];
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $dir . DIRECTORY_SEPARATOR . $stored;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['error' => t('파일을 저장하지 못했습니다.', 'The file could not be saved.')];
    }
    return [
        'url' => ksers_news_file_url($stored),
        'name' => ksers_news_download_name($file['name']),
    ];
}
