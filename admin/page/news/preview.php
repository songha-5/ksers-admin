<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/page/news/index.php');
    exit;
}

$title = trim(isset($_POST['title']) ? (string)$_POST['title'] : '');
$body = ksers_news_sanitize(isset($_POST['body']) ? (string)$_POST['body'] : '');
$writtenAt = ksers_news_today();
$viewCount = 0;
$seq = isset($_POST['seq']) ? (int)$_POST['seq'] : 0;
if ($seq > 0) {
    $row = ksers_news_find($pdo, ksers_admin_year($pdo), $seq);
    if ($row) {
        $writtenAt = (string)$row['written_at'];
        $viewCount = (int)$row['view_count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title !== '' ? $title : 'News') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; background: #ffffff; color: #171717; font-family: Pretendard, Roboto, Arial, sans-serif; }
        .news-preview { padding-top: 40px; padding-bottom: 64px; font-size: 14px; line-height: 1.6; color: #595959; }
        @media (min-width: 1100px) { .news-preview { padding-top: 64px; } }
        .news-preview-gutter { padding-left: 24px; padding-right: 24px; }
        @media (min-width: 768px) and (max-width: 1099px) { .news-preview-gutter { padding-left: 40px; padding-right: 40px; } }
        .news-preview-inner { width: 100%; max-width: 1200px; margin: 0 auto; }
        .news-preview-page-title { margin: 0; padding-bottom: 30px; text-align: center; font-family: Roboto, Pretendard, Arial, sans-serif; font-size: 30px; font-weight: 400; line-height: 1.4; color: #263238; }
        @media (min-width: 1100px) { .news-preview-page-title { padding-bottom: 40px; font-size: 36px; } }
        .news-preview-back { display: inline-flex; align-items: center; justify-content: center; gap: 4px; background: #09428f; padding: 12px 32px; font-size: 16px; font-weight: 700; line-height: 1; color: #ffffff; text-decoration: none; }
        .news-preview-title { margin: 32px 0 0; font-family: Roboto, Pretendard, Arial, sans-serif; font-size: 20px; font-weight: 700; line-height: 1.4; color: #263238; }
        @media (min-width: 1100px) { .news-preview-title { font-size: 24px; } }
        .news-preview-meta { margin: 12px 0 0; font-size: 14px; line-height: 1.4; color: #8d8d8d; }
        .news-preview-body { margin-top: 32px; border-top: 1px solid #e4e4e4; padding-top: 32px; line-height: 1.625; color: #595959; }
        .news-preview-body p { margin: 0 0 16px; }
        .news-preview-body ul { margin: 0 0 16px; padding-left: 20px; list-style: disc; }
        .news-preview-body ol { margin: 0 0 16px; padding-left: 20px; list-style: decimal; }
        .news-preview-body a { font-weight: 700; color: #09428f; text-decoration: underline; }
        .news-preview-body h1 { margin: 24px 0 12px; font-size: 24px; font-weight: 700; color: #263238; }
        .news-preview-body h2 { margin: 24px 0 12px; font-size: 20px; font-weight: 700; color: #263238; }
        .news-preview-body h3 { margin: 16px 0 8px; font-size: 18px; font-weight: 700; color: #263238; }
        .news-preview-body h4 { margin: 16px 0 8px; font-size: 16px; font-weight: 700; color: #263238; }
        .news-preview-body blockquote { margin: 16px 0; padding-left: 16px; border-left: 4px solid #e8eef5; }
        .news-preview-body img { margin: 16px 0; height: auto; max-width: 100%; }
        .news-preview-body video { margin: 16px 0; height: auto; max-width: 100%; }
        .news-preview-body table { width: 100%; margin: 0 0 16px; border-collapse: collapse; font-size: 14px; }
        .news-preview-body th, .news-preview-body td { border: 1px solid #e4e4e4; padding: 8px 12px; text-align: left; }
        .news-preview-body th { background: #e8eef5; }
        .news-preview-body pre { margin: 0 0 16px; overflow-x: auto; background: #f7f9fb; padding: 16px; }
        .news-preview-body hr { margin: 24px 0; border: 0; border-top: 1px solid #e4e4e4; }
        .news-preview-body figure { margin: 16px 0; }
        .news-preview-body figcaption { margin-top: 8px; font-size: 14px; color: #8d8d8d; }
        .news-preview-body a.news-file { display: inline-flex; align-items: center; gap: 8px; margin: 8px 0; padding: 12px 16px; border: 1px solid #e4e4e4; background: #f7f9fb; font-weight: 400; color: #263238; text-decoration: none; }
        .news-preview-body strong, .news-preview-body b { font-weight: 700; }
    </style>
</head>
<body>
<section class="news-preview">
    <div class="news-preview-gutter">
        <div class="news-preview-inner">
            <h1 class="news-preview-page-title">News &amp; Notices</h1>
            <article>
                <a class="news-preview-back" href="#">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    List
                </a>
                <h2 class="news-preview-title"><?= h($title) ?></h2>
                <p class="news-preview-meta"><?= h($writtenAt) ?> · 조회수 <?= (int)$viewCount ?></p>
                <div class="news-preview-body"><?= $body ?></div>
            </article>
        </div>
    </div>
</section>
<script>
(function () {
    var extensions = ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx', '.zip', '.hwp', '.hwpx', '.txt', '.csv'];
    var icon = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>';
    document.querySelectorAll('.news-preview-body video').forEach(function (video) {
        var source = video.querySelector('source');
        if (!video.getAttribute('src') && source && source.getAttribute('src')) {
            video.setAttribute('src', source.getAttribute('src'));
        }
        video.controls = true;
    });
    document.querySelectorAll('.news-preview-body a').forEach(function (link) {
        var href = link.getAttribute('href') || '';
        var path = href.split('?')[0].split('#')[0].toLowerCase();
        var isFile = link.hasAttribute('download') || extensions.some(function (ext) { return path.endsWith(ext); });
        if (isFile) {
            link.classList.add('news-file');
            link.insertAdjacentHTML('afterbegin', icon);
            return;
        }
        if (href.indexOf('http://') === 0 || href.indexOf('https://') === 0) {
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
        }
    });
    var back = document.querySelector('.news-preview-back');
    if (back) {
        back.addEventListener('click', function (event) { event.preventDefault(); });
    }
})();
</script>
</body>
</html>
