<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';

$token = isset($_GET['token']) ? (string)$_GET['token'] : '';
$link = null;
$file = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = $pdo->prepare(
        "SELECT l.token, l.file_seq, f.kind, f.original_name, f.stored_name, f.mime_type, a.title, a.seq AS abstract_seq, a.year
         FROM abstract_video_link l
         JOIN member_submission_file f ON f.seq = l.file_seq
         JOIN abstract_info a ON a.seq = l.abstract_seq AND a.year = l.year
         WHERE l.token = ?"
    );
    $stmt->execute([$token]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($link) {
        $file = $link;
    }
}

$closed = function () {
    http_response_code(404);
    $title = t('종료된 링크', 'Link closed');
    $message = t('이 영상 링크는 종료되어 재생할 수 없습니다.', 'This video link has been closed and can no longer be played.');
    echo '<!DOCTYPE html><html lang="' . h(ksers_lang()) . '"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . h($title) . '</title>';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">';
    echo '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#e8eef5;font-family:Pretendard,sans-serif;color:#1e293b}main{width:min(520px,calc(100% - 32px));background:#fff;border-radius:16px;padding:32px 24px;box-shadow:0 12px 32px rgba(15,23,42,.08)}h1{margin:0 0 12px;color:#09428f;font-size:1.25rem}p{margin:0;line-height:1.6}</style>';
    echo '</head><body><main><h1>' . h($title) . '</h1><p>' . h($message) . '</p></main></body></html>';
    exit;
};

if (!$file) {
    $closed();
}
$path = ksers_submission_find($file['kind'], $file['stored_name']);
if ($path === '' || !is_file($path)) {
    $closed();
}

if (isset($_GET['play']) && $_GET['play'] === '1') {
    session_write_close();
    $size = filesize($path);
    $mime = (isset($file['mime_type']) && strpos((string)$file['mime_type'], 'video/') === 0) ? $file['mime_type'] : 'video/mp4';
    $start = 0;
    $end = $size > 0 ? $size - 1 : 0;
    $partial = false;
    if ($size > 0 && isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string)$_SERVER['HTTP_RANGE'], $match)) {
        if ($match[1] === '' && $match[2] !== '') {
            $length = (int)$match[2];
            $start = max(0, $size - $length);
        } else {
            $start = (int)$match[1];
            if ($match[2] !== '') {
                $end = (int)$match[2];
            }
        }
        if ($start > $end || $start >= $size) {
            header('HTTP/1.1 416 Range Not Satisfiable');
            header('Content-Range: bytes */' . $size);
            exit;
        }
        $end = min($end, $size - 1);
        $partial = true;
    }
    header('Content-Type: ' . $mime);
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    if ($partial) {
        header('HTTP/1.1 206 Partial Content');
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        header('Content-Length: ' . ($end - $start + 1));
    } else {
        header('Content-Length: ' . $size);
    }
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        http_response_code(404);
        exit;
    }
    if ($start > 0) {
        fseek($handle, $start);
    }
    $left = $partial ? ($end - $start + 1) : $size;
    while ($left > 0 && !feof($handle)) {
        $chunk = fread($handle, (int)min(1048576, $left));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $left -= strlen($chunk);
        if (connection_aborted()) {
            break;
        }
    }
    fclose($handle);
    exit;
}

$receipt = sprintf('A%d-%04d', (int)$file['year'], (int)$file['abstract_seq']);
$playUrl = 'video_link.php?token=' . rawurlencode($token) . '&play=1';
?>
<!DOCTYPE html>
<html lang="<?= h(ksers_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($receipt) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <style>
        body { margin: 0; min-height: 100vh; background: #e8eef5; color: #1e293b; font-family: Pretendard, sans-serif; }
        main { width: min(960px, calc(100% - 32px)); margin: 24px auto 40px; background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08); }
        h1 { margin: 0 0 8px; color: #09428f; font-size: 1.25rem; }
        p { margin: 0 0 16px; color: #64748b; }
        video { width: 100%; background: #0f172a; border-radius: 12px; }
    </style>
</head>
<body>
<main>
    <h1><?= h($file['original_name']) ?></h1>
    <p><?= h($receipt) ?> · <?= h(ksers_submission_label($file['kind'])) ?><?= trim((string)$file['title']) !== '' ? ' · ' . h($file['title']) : '' ?></p>
    <video controls autoplay playsinline preload="metadata" src="<?= h($playUrl) ?>"></video>
</main>
</body>
</html>
