<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
$lang = (isset($_GET['lang']) && $_GET['lang'] === 'en') ? 'en' : 'ko';
$part = isset($_GET['part']) ? (string)$_GET['part'] : '';
$allowed = ['terms', 'privacy', 'all'];
if (!in_array($part, $allowed, true)) {
    $part = '';
}
$path = __DIR__ . '/legal/' . $lang . '.html';
$fragment = is_file($path) ? file_get_contents($path) : '';
$fragment = ksers_legal_part($fragment, $part);
$titles = [
    'ko' => [
        '' => 'KSERS 2027 이용약관 및 개인정보처리방침',
        'all' => 'KSERS 2027 이용약관 및 개인정보처리방침',
        'terms' => 'KSERS 2027 이용약관',
        'privacy' => 'KSERS 2027 개인정보처리방침',
    ],
    'en' => [
        '' => 'KSERS 2027 Terms of Use and Privacy Policy',
        'all' => 'KSERS 2027 Terms of Use and Privacy Policy',
        'terms' => 'KSERS 2027 Terms of Use',
        'privacy' => 'KSERS 2027 Privacy Policy',
    ],
];
$title = $titles[$lang][$part];

function ksers_legal_part($html, $part) {
    if ($part === '' || $part === 'all' || $html === '') {
        return $html;
    }
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div id="ksers-legal-root">' . $html . '</div>');
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $node = $dom->getElementById($part);
    if (!$node) {
        return $html;
    }
    $suffix = '';
    if ($part === 'terms' || $part === 'privacy') {
        $supplement = $dom->getElementById('supplement');
        if ($supplement) {
            $suffix = $dom->saveHTML($supplement);
        }
    }
    return $dom->saveHTML($node) . $suffix;
}
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <style>
        body { margin: 0; font-family: Pretendard, sans-serif; color: #1f2937; background: #fff; }
        main { max-width: 760px; margin: 0 auto; padding: 28px 20px 48px; }
        h1 { font-size: 1.35rem; line-height: 1.4; margin: 0 0 8px; }
        h2 { font-size: 1.12rem; line-height: 1.4; margin: 1.8rem 0 0.7rem; padding-bottom: 0.35rem; border-bottom: 1px solid #e5e7eb; }
        h3 { font-size: 1rem; line-height: 1.45; margin: 1.2rem 0 0.45rem; }
        p, li { font-size: 0.92rem; line-height: 1.7; }
        p { margin: 0.3rem 0 0.7rem; }
        ol, ul { list-style: none; margin: 0.35rem 0 0.85rem; padding: 0; }
        li { position: relative; margin: 0.15rem 0; padding: 0 0 0 1.7rem; }
        .terms-num { position: absolute; left: 0; top: 0; min-width: 1.5rem; }
        .terms-table-wrap { overflow-x: auto; margin: 0.4rem 0 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d0d5dd; padding: 8px 10px; vertical-align: top; text-align: left; font-size: 0.84rem; line-height: 1.55; font-weight: 400; }
        th { background: #f3f4f6; font-weight: 700; }
    </style>
</head>
<body>
<main>
    <?= $fragment ?>
</main>
</body>
</html>
