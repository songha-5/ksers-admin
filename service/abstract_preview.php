<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';

$memberSeq = isset($_SESSION['seq']) ? (int)$_SESSION['seq'] : 0;
$seq = isset($_GET['seq']) ? (int)$_GET['seq'] : 0;
if ($memberSeq < 1 || $seq < 1) {
    header('Location: /service/login.php');
    exit;
}
ksers_require_participant($pdo);

$year = isset($eventYear) ? (int)$eventYear : 2027;
$stmt = $pdo->prepare("SELECT * FROM abstract_info WHERE seq = ? AND member_seq = ? AND year = ?");
$stmt->execute([$seq, $memberSeq, $year]);
$abstract = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$abstract) {
    header('Location: /service/abstract.php');
    exit;
}

$authors = $pdo->prepare(
    "SELECT first_name, last_name, affiliation, department, country, email, presenting, corresponding, mentor
     FROM abstract_author WHERE abstract_seq = ? ORDER BY sort_no, seq"
);
$authors->execute([$seq]);
$authorRows = $authors->fetchAll(PDO::FETCH_ASSOC);

$countryName = [];
foreach (ksers_country_rows($pdo) as $countryRow) {
    $countryName[(int)$countryRow['minr']] = code_name($countryRow);
}

$topicLabel = (string)$abstract['topic'];
$topicLookup = $pdo->prepare("SELECT name_ko, name_en FROM abstract_topic WHERE year = ? AND state = 1 AND (name_ko = ? OR name_en = ?) LIMIT 1");
$topicLookup->execute([$year, $topicLabel, $topicLabel]);
$topicRow = $topicLookup->fetch(PDO::FETCH_ASSOC);
if ($topicRow) {
    $topicLabel = ksers_text($topicRow, 'name_ko', 'name_en');
}

$presentationLabels = [
    'oral' => 'Oral',
    'eposter' => 'E-Poster',
    'video' => 'Video',
    'mentor' => 'Mentor-Mentee Video',
    'abstract_video_1' => 'Video',
    'abstract_video_2' => 'Mentor-Mentee Video',
    'poster' => t('포스터', 'Poster'),
    'either' => t('상관없음', 'No preference'),
];
$presentation = isset($presentationLabels[$abstract['presentation_type']])
    ? $presentationLabels[$abstract['presentation_type']]
    : $abstract['presentation_type'];
$memberStatus = ksers_abstract_member_status($abstract['status']);
$receiptNo = sprintf('A%d-%04d', $year, $seq);
$ext = strtolower(pathinfo((string)$abstract['stored_name'], PATHINFO_EXTENSION));
if ($ext === '') {
    $ext = strtolower(pathinfo((string)$abstract['file_name'], PATHINFO_EXTENSION));
}
$displayName = $receiptNo . ($ext !== '' ? '.' . $ext : '');
$originalName = (string)$abstract['file_name'];
$isPdf = $ext === 'pdf';
$stamp = function ($value) {
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return '';
    }
    $time = strtotime($value);
    return $time ? date('Y-m-d H:i', $time) : '';
};
$submittedAt = $stamp($abstract['reg_date']);
$updatedAt = $stamp(isset($abstract['update_date']) ? $abstract['update_date'] : '');
if ($updatedAt === '') {
    $updatedAt = $submittedAt;
}
$storeDir = getenv('KSERS_ABSTRACT_DIR') ?: '/data/abstracts';
$stored = basename((string)$abstract['stored_name']);
$filePath = $storeDir . '/' . $stored;
$fileSize = ($stored !== '' && is_file($filePath)) ? (int)filesize($filePath) : 0;
$sizeLabel = '';
if ($fileSize > 0) {
    $sizeLabel = $fileSize >= 1048576
        ? number_format($fileSize / 1048576, 1) . ' MB'
        : number_format(max(1, (int)round($fileSize / 1024))) . ' KB';
}
$videoStmt = $pdo->prepare(
    "SELECT seq, kind, original_name, file_size, reg_date, update_date
     FROM member_submission_file
     WHERE abstract_seq = ? AND member_seq = ? AND year = ? AND kind IN ('abstract_video_1', 'abstract_video_2')
     ORDER BY seq"
);
$videoStmt->execute([$seq, $memberSeq, $year]);
$linkedVideos = $videoStmt->fetchAll(PDO::FETCH_ASSOC);
$agreements = [
    [t('공동저자 동의 확인', 'Co-author Consent'), !isset($abstract['agree_coauthor']) || (int)$abstract['agree_coauthor'] === 1],
    [t('개인정보 수집·이용 동의', 'Privacy Consent'), !isset($abstract['agree_privacy']) || (int)$abstract['agree_privacy'] === 1],
    [t('초록 게재 동의', 'Publication Consent'), !isset($abstract['agree_publication']) || (int)$abstract['agree_publication'] === 1],
];
$roleText = function ($row) {
    $roles = [];
    if (!empty($row['presenting'])) {
        $roles[] = 'Presenting';
    }
    if (!empty($row['corresponding'])) {
        $roles[] = 'Corresponding';
    }
    if (!empty($row['mentor'])) {
        $roles[] = 'Mentor';
    }
    return $roles ? implode(', ', $roles) : '—';
};
?>
<!DOCTYPE html>
<html lang="<?= h(ksers_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(t('초록 미리보기', 'Abstract preview')) ?> <?= h($receiptNo) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px 24px;
            background: #e8eef5;
            color: #1e293b;
            font-family: Pretendard, sans-serif;
        }
        .sheet {
            display: flex;
            flex-direction: column;
            width: min(720px, 100%);
            height: 100%;
            max-height: 820px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .sheet-body {
            flex: 1 1 auto;
            overflow: auto;
            padding: 28px 24px 8px;
        }
        h1 {
            margin: 0 0 14px;
            font-size: 1.25rem;
            color: #09428f;
        }
        .meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px 12px;
            margin-bottom: 22px;
            padding-bottom: 16px;
            border-bottom: 2px solid #09428f;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            background: #09428f;
            color: #fff;
            font-size: 0.82rem;
            font-weight: 700;
        }
        .pill.is-received { background: #157347; }
        .pill.is-accept { background: #1d4ed8; }
        .pill.is-reject { background: #dc2626; }
        .pill.is-withdrawn { background: #64748b; }
        .dates { margin-left: auto; color: #64748b; font-size: 0.82rem; }
        section { margin: 0 0 22px; }
        h2 {
            margin: 0 0 10px;
            padding-left: 10px;
            border-left: 3px solid #09428f;
            font-size: 0.95rem;
        }
        dl { margin: 0; }
        .row {
            display: grid;
            grid-template-columns: 8.5rem 1fr;
            gap: 8px 12px;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.92rem;
        }
        dt { color: #64748b; }
        dd { margin: 0; }
        table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
        th, td { padding: 8px 6px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
        th { background: #f8fafc; color: #334155; font-weight: 700; text-align: center; }
        .file-card {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid #dbe3ee;
            border-radius: 12px;
            background: #f8fafc;
        }
        .file-card strong { display: block; }
        .file-card span { color: #64748b; font-size: 0.8rem; }
        .file-card a { color: #09428f; font-weight: 700; white-space: nowrap; }
        iframe {
            width: 100%;
            height: 420px;
            margin-top: 12px;
            border: 1px solid #dbe3ee;
            border-radius: 12px;
            background: #fff;
        }
        .agreements { display: flex; flex-wrap: wrap; gap: 8px; }
        .agree {
            padding: 6px 10px;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid #dbe3ee;
            font-size: 0.82rem;
        }
        .agree.is-on { color: #157347; border-color: #b7e4c7; background: #f0fdf4; }
        .foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex: 0 0 auto;
            margin-top: auto;
            padding: 16px 24px 22px;
            border-top: 1px solid #e2e8f0;
            background: #fff;
        }
        .foot p { margin: 0; color: #64748b; font-size: 0.82rem; }
        .actions { display: flex; gap: 8px; }
        button {
            min-width: 72px;
            height: 36px;
            border-radius: 8px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .close-btn { border: 1px solid #cbd5e1; background: #fff; color: #334155; }
        .edit-btn { border: 0; background: #09428f; color: #fff; }
        @media (max-width: 640px) {
            .dates { margin-left: 0; }
            .row { grid-template-columns: 1fr; gap: 2px; }
            .foot { align-items: stretch; flex-direction: column; }
        }
    </style>
</head>
<body>
<main class="sheet">
<div class="sheet-body">
    <h1><?= h(t('초록 미리보기', 'Abstract preview')) ?></h1>
    <div class="meta">
        <span class="pill"><?= h($receiptNo) ?></span>
        <span class="pill <?= h($memberStatus['class']) ?>"><?= h($memberStatus['label']) ?></span>
        <span class="dates">
            <?= h(t('제출', 'Submitted')) ?> <?= h($submittedAt !== '' ? $submittedAt : '—') ?>
            · <?= h(t('최종 수정', 'Last updated')) ?> <?= h($updatedAt !== '' ? $updatedAt : '—') ?>
        </span>
    </div>

    <section>
        <h2><?= h(t('초록 정보', 'Abstract')) ?></h2>
        <dl>
            <div class="row"><dt><?= h(t('발표 희망 형태', 'Presentation Type')) ?></dt><dd><?= h($presentation) ?></dd></div>
            <div class="row"><dt><?= h(t('분야', 'Topic')) ?></dt><dd><?= h($topicLabel) ?></dd></div>
            <div class="row"><dt><?= h(t('제목', 'Title')) ?></dt><dd><?= h($abstract['title']) ?></dd></div>
        </dl>
    </section>

    <section>
        <h2><?= h(t('저자', 'Authors')) ?></h2>
        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th><?= h(t('이름', 'Name')) ?></th>
                        <th><?= h(t('소속 / 부서', 'Affiliation / Department')) ?></th>
                        <th><?= h(t('국가', 'Country')) ?></th>
                        <th><?= h(t('이메일', 'E-mail')) ?></th>
                        <th><?= h(t('역할', 'Role')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$authorRows) { ?>
                    <tr><td colspan="6" style="text-align:center;color:#64748b"><?= h(t('등록된 저자가 없습니다.', 'No authors yet.')) ?></td></tr>
                    <?php } ?>
                    <?php foreach ($authorRows as $index => $author) {
                        $place = trim($author['affiliation']);
                        if (trim((string)$author['department']) !== '') {
                            $place .= ($place !== '' ? ' / ' : '') . trim((string)$author['department']);
                        }
                        $country = isset($countryName[(int)$author['country']]) ? $countryName[(int)$author['country']] : '—';
                    ?>
                    <tr>
                        <td style="text-align:center"><?= $index + 1 ?></td>
                        <td><?= h(trim($author['first_name'] . ' ' . $author['last_name'])) ?></td>
                        <td><?= h($place !== '' ? $place : '—') ?></td>
                        <td><?= h($country !== '' ? $country : '—') ?></td>
                        <td><?= h(trim((string)$author['email']) !== '' ? $author['email'] : '—') ?></td>
                        <td><?= h($roleText($author)) ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <h2><?= h(t('초록 파일', 'Abstract file')) ?></h2>
        <div class="file-card">
            <div>
                <strong><?= h($displayName) ?></strong>
                <span><?= h($sizeLabel) ?><?= $sizeLabel !== '' && $originalName !== '' ? ' · ' : '' ?><?= h($originalName !== '' ? t('원본 ', 'Original ') . $originalName : '') ?></span>
            </div>
            <a href="abstract_file.php?seq=<?= $seq ?>"><?= h(t('다운로드', 'Download')) ?></a>
        </div>
        <?php if ($isPdf && $fileSize > 0) { ?>
        <iframe src="abstract_file.php?seq=<?= $seq ?>&amp;inline=1" title="<?= h($displayName) ?>"></iframe>
        <?php } elseif (!$isPdf) { ?>
        <p style="margin:10px 0 0;color:#64748b;font-size:0.82rem"><?= h(t('Word 파일은 화면에서 미리 보지 않습니다. 다운로드해서 확인해 주세요.', 'Word files are not previewed here. Download the file to open it.')) ?></p>
        <?php } ?>
    </section>

    <?php foreach ($linkedVideos as $video) {
        $videoSize = (int)$video['file_size'];
        $videoSizeLabel = $videoSize >= 1048576
            ? number_format(max(1, (int)round($videoSize / 1048576))) . ' MB'
            : ($videoSize > 0 ? number_format(max(1, (int)round($videoSize / 1024))) . ' KB' : '');
        $videoWhen = $stamp($video['update_date'] ?: $video['reg_date']);
    ?>
    <section>
        <h2><?= h(ksers_submission_label($video['kind'])) ?></h2>
        <div class="file-card">
            <div>
                <strong><?= h($video['original_name']) ?></strong>
                <span><?= h($videoSizeLabel) ?><?= $videoSizeLabel !== '' && $videoWhen !== '' ? ' · ' : '' ?><?= h($videoWhen) ?></span>
            </div>
            <a href="submission_file.php?seq=<?= (int)$video['seq'] ?>"><?= h(t('다운로드', 'Download')) ?></a>
        </div>
    </section>
    <?php } ?>

    <section>
        <h2><?= h(t('동의', 'Consent')) ?></h2>
        <div class="agreements">
            <?php foreach ($agreements as $item) { ?>
            <span class="agree<?= $item[1] ? ' is-on' : '' ?>"><?= $item[1] ? '✓' : '—' ?> <?= h($item[0]) ?></span>
            <?php } ?>
        </div>
    </section>
</div>

    <div class="foot">
        <p><?= h(t('2027년 1월 14일(목)까지 초록을 수정할 수 있습니다.', 'You may edit your abstract until January 14 (Thu), 2027.')) ?></p>
        <div class="actions">
            <button class="close-btn" type="button" onclick="window.close()"><?= h(t('닫기', 'Close')) ?></button>
            <button class="edit-btn" type="button" id="previewEdit"><?= h(t('수정', 'Edit')) ?></button>
        </div>
    </div>
</main>
<script>
document.getElementById('previewEdit').addEventListener('click', function () {
    const url = 'abstract.php?edit=<?= $seq ?>';
    if (window.opener && !window.opener.closed) {
        window.opener.location.href = url;
        window.opener.focus();
        window.close();
        return;
    }
    window.location.href = url;
});
</script>
</body>
</html>
