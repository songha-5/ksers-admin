<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$year = ksers_admin_year($pdo);
$seq = isset($_GET['seq']) ? (int)$_GET['seq'] : 0;
$stmt = $pdo->prepare(
    "SELECT support_file, support_stored, support_date FROM participant_info WHERE seq = ? AND year = ?"
);
$stmt->execute([$seq, $year]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$stored = $row ? basename((string)$row['support_stored']) : '';
$path = $stored !== '' ? ksers_submission_find('supporting_document', $stored) : '';
$ready = $row && $stored !== '' && $path !== '' && is_file($path);
if (!$row || trim((string)$row['support_file']) === '') {
    http_response_code(404);
    exit('Not found');
}
$ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
$isImage = $ready && ($ext === 'jpg' || $ext === 'jpeg');
$isPdf = $ready && $ext === 'pdf';
$uploaded = '';
if (!empty($row['support_date'])) {
    $stamp = strtotime((string)$row['support_date']);
    $uploaded = $stamp ? date('Y-m-d H:i', $stamp) : (string)$row['support_date'];
}
$fileName = (string)$row['support_file'];
$downloadUrl = 'support_file.php?seq=' . $seq . '&download=1';
$inlineUrl = 'support_file.php?seq=' . $seq . '&inline=1';
?>
<!DOCTYPE html>
<html lang="<?= h(ksers_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(t('증빙서류', 'Supporting Document')) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <style>
        body { margin: 0; min-height: 100vh; background: #e8eef5; color: #1e293b; font-family: Pretendard, sans-serif; }
        main { width: min(860px, calc(100% - 32px)); margin: 24px auto 40px; background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08); }
        h1 { margin: 0 0 16px; color: #09428f; font-size: 1.25rem; }
        dl { display: grid; grid-template-columns: 7rem 1fr; gap: 8px 12px; margin: 0 0 16px; }
        dt { color: #64748b; }
        dd { margin: 0; }
        a { color: #09428f; font-weight: 700; }
        .preview { display: block; color: inherit; text-decoration: none; }
        .preview img, .preview object { display: block; width: 100%; max-height: 70vh; object-fit: contain; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; }
        .preview object { height: 70vh; pointer-events: none; }
    </style>
</head>
<body>
<main>
    <h1><?= h(t('증빙서류', 'Supporting Document')) ?></h1>
    <dl>
        <dt><?= h(t('파일명', 'File name')) ?></dt>
        <dd><?php if ($ready) { ?><a href="<?= h($downloadUrl) ?>"><?= h($fileName) ?></a><?php } else { ?><?= h($fileName) ?><?php } ?></dd>
        <dt><?= h(t('올린 날', 'Uploaded')) ?></dt>
        <dd><?= h($uploaded !== '' ? $uploaded : '—') ?></dd>
    </dl>
    <?php if ($isImage) { ?>
    <a class="preview" href="<?= h($downloadUrl) ?>"><img src="<?= h($inlineUrl) ?>" alt="<?= h($fileName) ?>"></a>
    <?php } elseif ($isPdf) { ?>
    <a class="preview" href="<?= h($downloadUrl) ?>"><object data="<?= h($inlineUrl) ?>" type="application/pdf"></object></a>
    <?php } else { ?>
    <p><?= h(t('파일을 찾을 수 없습니다.', 'The file could not be found.')) ?></p>
    <?php } ?>
</main>
</body>
</html>
