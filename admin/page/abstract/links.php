<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$year = ksers_admin_year($pdo);
$seq = isset($_REQUEST['seq']) ? (int)$_REQUEST['seq'] : 0;
$infoStmt = $pdo->prepare(
    "SELECT a.seq
     FROM abstract_info a
     JOIN member_info m ON m.seq = a.member_seq
     WHERE a.seq = ? AND a.year = ?"
);
$infoStmt->execute([$seq, $year]);
$info = $infoStmt->fetch(PDO::FETCH_ASSOC);
if (!$info) {
    http_response_code(404);
    exit('Not found');
}
$stmt = $pdo->prepare(
    "SELECT l.token, l.reg_date, f.original_name, f.kind
     FROM abstract_video_link l
     JOIN member_submission_file f ON f.seq = l.file_seq
     WHERE l.abstract_seq = ? AND l.year = ?
     ORDER BY l.seq"
);
$stmt->execute([$seq, $year]);
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);
$receipt = sprintf('A%d-%04d', $year, $seq);
$publicBase = rtrim(ksers_public_base(), '/');
$linkRows = [];
foreach ($links as $link) {
    $linkRows[] = [
        'title' => (string)$link['original_name'],
        'kind' => ksers_submission_label($link['kind']),
        'date' => (string)$link['reg_date'],
        'url' => $publicBase . '/service/video_link.php?token=' . $link['token'],
    ];
}
?>
<!DOCTYPE html>
<html lang="<?= h(ksers_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(t('영상 링크', 'Video links')) ?> <?= h($receipt) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { margin: 0; min-height: 100vh; background: #e8eef5; color: #1e293b; font-family: Pretendard, sans-serif; }
        main { width: min(920px, calc(100% - 32px)); margin: 24px auto 40px; background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08); }
        h1 { margin: 0 0 16px; color: #09428f; font-size: 1.25rem; display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
        h1 span { color: #dc2626; font-size: 0.8rem; font-weight: 400; }
        .link-url { display: inline-block; box-sizing: border-box; max-width: 100%; color: #09428f; font-weight: 700; word-break: break-all; cursor: pointer; background: none; border: 0; padding: 0; text-align: left; vertical-align: top; }
    </style>
</head>
<body>
<main>
    <h1><?= h($receipt) ?> <span><?= h(t('URL을 누르면 링크가 복사됩니다.', 'Press a URL to copy the link.')) ?></span></h1>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= h(t('영상 타이틀', 'Video title')) ?></th>
                    <th><?= h(t('링크 생성일', 'Link created')) ?></th>
                    <th>URL</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$links) { ?>
                <tr><td colspan="3" class="text-center text-muted py-4"><?= h(t('생성된 링크가 없습니다.', 'No links yet.')) ?></td></tr>
                <?php } ?>
                <?php foreach ($linkRows as $linkRow) { ?>
                <tr>
                    <td><?= h($linkRow['title']) ?><div class="small text-muted"><?= h($linkRow['kind']) ?></div></td>
                    <td><?= h($linkRow['date']) ?></td>
                    <td><button class="link-url" type="button" data-copy-url="<?= h($linkRow['url']) ?>"><?= h($linkRow['url']) ?></button></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</main>
<script>
function ksersCopyText(url) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(url);
    }
    const input = document.createElement('textarea');
    input.value = url;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    input.remove();
    return Promise.resolve();
}
document.querySelectorAll('[data-copy-url]').forEach(function (button) {
    button.addEventListener('click', function () {
        const url = button.getAttribute('data-copy-url');
        ksersCopyText(url).then(function () {
            if (button.dataset.copying === '1') {
                return;
            }
            const previous = button.textContent;
            const box = button.getBoundingClientRect();
            button.style.width = Math.ceil(box.width) + 'px';
            button.style.height = Math.ceil(box.height) + 'px';
            button.dataset.copying = '1';
            button.textContent = <?= json_encode(t('복사됨', 'Copied'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
            window.setTimeout(function () {
                button.textContent = previous;
                button.style.width = '';
                button.style.height = '';
                button.dataset.copying = '';
            }, 1200);
        });
    });
});
</script>
</body>
</html>
