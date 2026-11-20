<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$year = ksers_admin_year($pdo);
$seq = isset($_GET['seq']) ? (int)$_GET['seq'] : 0;
$stmt = $pdo->prepare(
    "SELECT a.*, m.email, m.name_ko, m.first_name_eng, m.last_name_eng,
            c.name_ko AS country_ko, c.name_eng AS country_en
     FROM abstract_info a
     JOIN member_info m ON m.seq = a.member_seq
     LEFT JOIN code c ON c.majr = 1 AND c.minr = a.author_country
     WHERE a.seq = ? AND a.year = ?"
);
$stmt->execute([$seq, $year]);
$abstract = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$abstract) {
    http_response_code(404);
    exit('Not found');
}

$authorStmt = $pdo->prepare(
    "SELECT first_name, last_name, affiliation, department, country, email, presenting, corresponding, mentor
     FROM abstract_author WHERE abstract_seq = ? ORDER BY sort_no, seq"
);
$authorStmt->execute([$seq]);
$authors = $authorStmt->fetchAll(PDO::FETCH_ASSOC);
$countries = ksers_country_rows($pdo);
$countryName = [];
$koreaCountry = [];
foreach ($countries as $countryRow) {
    $countryName[(int)$countryRow['minr']] = code_name($countryRow);
    if (!empty(ksers_country_profile($countryRow)['korea'])) {
        $koreaCountry[(int)$countryRow['minr']] = true;
    }
}
$authorError = '';
$authorDraft = [
    'first' => '',
    'last' => '',
    'affiliation' => '',
    'department' => '',
    'country' => 0,
    'email' => '',
    'presenting' => 0,
    'corresponding' => 0,
    'mentor' => 0,
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_author') {
    $authorDraft = [
        'first' => isset($_POST['author_first']) ? trim((string)$_POST['author_first']) : '',
        'last' => isset($_POST['author_last']) ? trim((string)$_POST['author_last']) : '',
        'affiliation' => isset($_POST['author_affiliation']) ? trim((string)$_POST['author_affiliation']) : '',
        'department' => isset($_POST['author_department']) ? trim((string)$_POST['author_department']) : '',
        'country' => isset($_POST['author_country']) ? (int)$_POST['author_country'] : 0,
        'email' => isset($_POST['author_email']) ? trim((string)$_POST['author_email']) : '',
        'presenting' => !empty($_POST['author_presenting']) ? 1 : 0,
        'corresponding' => !empty($_POST['author_corresponding']) ? 1 : 0,
        'mentor' => !empty($_POST['author_mentor']) ? 1 : 0,
    ];
    if (!in_array((string)$abstract['presentation_type'], ['mentor', 'mentee', 'abstract_video_2'], true)) {
        $authorDraft['mentor'] = 0;
    }
    $textLength = function ($value) {
        $chars = preg_match_all('/./u', $value, $unused);
        return $chars === false ? 0 : $chars;
    };
    $presentingCount = 0;
    $mentorCount = 0;
    foreach ($authors as $person) {
        if (!empty($person['presenting'])) {
            $presentingCount++;
        }
        if (!empty($person['mentor'])) {
            $mentorCount++;
        }
    }
    $overseas = $authorDraft['country'] !== 0 && !isset($koreaCountry[$authorDraft['country']]);
    $needsEmail = $authorDraft['presenting'] || $authorDraft['corresponding'] || $overseas;
    if (count($authors) >= 20) {
        $authorError = t('저자는 최대 20명까지 입력할 수 있습니다.', 'Up to 20 authors can be entered.');
    } elseif ($authorDraft['first'] === '' || $authorDraft['last'] === '' || $authorDraft['affiliation'] === '') {
        $authorError = t('저자의 영문 성명과 소속은 필수입니다.', 'Each author needs an English name and affiliation.');
    } elseif ($textLength($authorDraft['first']) > 80 || $textLength($authorDraft['last']) > 80 || $textLength($authorDraft['affiliation']) > 200 || $textLength($authorDraft['department']) > 120) {
        $authorError = t('저자 입력 길이를 줄여 주세요.', 'Shorten the author text and try again.');
    } elseif ($authorDraft['presenting'] && $authorDraft['mentor']) {
        $authorError = t('발표자와 Mentor는 동시에 지정할 수 없습니다.', 'The presenting author and the mentor must be different people.');
    } elseif (!$authorDraft['presenting'] && !$authorDraft['corresponding'] && !$authorDraft['mentor']) {
        $authorError = t('각 저자의 역할을 선택해 주세요. Presenting, Corresponding, Mentor 중 하나 이상을 선택해야 합니다.', 'Choose a role for each author. Select Presenting, Corresponding, Mentor, or a combination that is allowed.');
    } elseif ($authorDraft['presenting'] && !isset($countryName[$authorDraft['country']])) {
        $authorError = t('발표자의 국가는 필수입니다.', 'The presenting author needs a country.');
    } elseif (!$authorDraft['presenting'] && $authorDraft['country'] !== 0 && !isset($countryName[$authorDraft['country']])) {
        $authorError = t('저자 국가를 다시 선택해 주세요.', 'Choose the author country again.');
    } elseif ($needsEmail && !filter_var($authorDraft['email'], FILTER_VALIDATE_EMAIL)) {
        $authorError = $overseas && !$authorDraft['presenting'] && !$authorDraft['corresponding']
            ? t('해외 국가를 선택한 저자는 이메일이 필수입니다.', 'An author outside Korea needs an email address.')
            : t('발표자와 교신저자의 이메일을 입력해 주세요.', 'Enter an email for the presenting author and corresponding author.');
    } elseif (!$needsEmail && $authorDraft['email'] !== '' && !filter_var($authorDraft['email'], FILTER_VALIDATE_EMAIL)) {
        $authorError = t('저자 이메일 형식을 확인해 주세요.', 'Check the author email address.');
    } elseif ($authorDraft['presenting'] && $presentingCount > 0) {
        $authorError = t('발표자는 한 명만 선택할 수 있습니다.', 'Choose one presenting author.');
    } elseif ($authorDraft['mentor'] && $mentorCount > 0) {
        $authorError = t('Mentor는 한 명만 지정할 수 있습니다.', 'Choose one mentor.');
    }
    if ($authorError === '') {
        $insertAuthor = $pdo->prepare(
            "INSERT INTO abstract_author
                (abstract_seq, sort_no, first_name, last_name, affiliation, department, country, email, presenting, corresponding, mentor)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $insertAuthor->execute([
            $seq, count($authors) + 1, $authorDraft['first'], $authorDraft['last'], $authorDraft['affiliation'],
            $authorDraft['department'], $authorDraft['country'], $authorDraft['email'], $authorDraft['presenting'], $authorDraft['corresponding'], $authorDraft['mentor'],
        ]);
        $names = [];
        foreach ($authors as $person) {
            $names[] = trim($person['first_name'] . ' ' . $person['last_name']);
        }
        $names[] = trim($authorDraft['first'] . ' ' . $authorDraft['last']);
        $authorSummary = implode(', ', $names);
        if (function_exists('mb_substr')) {
            $authorSummary = mb_substr($authorSummary, 0, 1000, 'UTF-8');
        }
        $authorCountry = (int)$abstract['author_country'];
        $affiliation = (string)$abstract['affiliation'];
        if ($authorDraft['presenting']) {
            $authorCountry = $authorDraft['country'];
            $affiliation = $authorDraft['affiliation'];
        }
        $pdo->prepare(
            "UPDATE abstract_info SET authors = ?, author_country = ?, affiliation = ?, update_date = NOW() WHERE seq = ? AND year = ?"
        )->execute([$authorSummary, $authorCountry, $affiliation, $seq, $year]);
        $_SESSION['admin_notice'] = t('저자를 추가했습니다.', 'Author added.');
        header('Location: view.php?seq=' . $seq . '&author=1');
        exit;
    }
}
$videoStmt = $pdo->prepare(
    "SELECT seq, kind, original_name, file_size, reg_date, update_date
     FROM member_submission_file
     WHERE abstract_seq = ? AND year = ? AND kind IN ('abstract_video_1', 'abstract_video_2')
     ORDER BY seq"
);
$videoStmt->execute([$seq, $year]);
$videos = $videoStmt->fetchAll(PDO::FETCH_ASSOC);

$presentationLabel = [
    'oral' => 'Oral',
    'eposter' => 'E-Poster',
    'video' => 'Video',
    'mentor' => 'Mentor-Mentee Video',
    'abstract_video_1' => 'Video',
    'abstract_video_2' => 'Mentor-Mentee Video',
    'poster' => t('포스터', 'Poster'),
    'either' => t('상관없음', 'No preference'),
];
$preferredChoices = [
    'pending' => t('미정', 'Pending'),
    'oral' => 'Oral',
    'eposter' => 'E-Poster',
];
$statusLabel = [];
foreach (ksers_abstract_review_statuses() as $code => $reviewStatus) {
    $statusLabel[$code] = t($reviewStatus['admin'][0], $reviewStatus['admin'][1]);
}
$finalTypeLabel = $preferredChoices;
$finalCode = isset($abstract['final_type']) && $abstract['final_type'] !== '' ? (string)$abstract['final_type'] : 'pending';
$typeText = isset($presentationLabel[$abstract['presentation_type']]) ? $presentationLabel[$abstract['presentation_type']] : $abstract['presentation_type'];
$statusText = isset($statusLabel[$abstract['status']]) ? $statusLabel[$abstract['status']] : $abstract['status'];
$finalText = isset($finalTypeLabel[$finalCode]) ? $finalTypeLabel[$finalCode] : $finalCode;
$receiptNo = sprintf('A%d-%04d', $year, $seq);
$yn = function ($value) {
    return (int)$value === 1 ? 'Y' : 'N';
};
$notice = '';
if (isset($_GET['saved']) || isset($_GET['author']) || isset($_GET['error'])) {
    $notice = !empty($_SESSION['admin_notice'])
        ? $_SESSION['admin_notice']
        : t('저장했습니다.', 'Saved.');
    unset($_SESSION['admin_notice']);
}
?>
<!DOCTYPE html>
<html lang="<?= h(ksers_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(t('초록 자세히 보기', 'Abstract details')) ?> <?= h($receiptNo) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: #e8eef5;
            color: #1e293b;
            font-family: Pretendard, sans-serif;
        }
        .sheet {
            width: min(720px, calc(100% - 32px));
            margin: 24px auto 40px;
            padding: 28px 24px 24px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
        }
        h1 { margin: 0 0 18px; color: #09428f; font-size: 1.25rem; }
        .rowline {
            display: grid;
            grid-template-columns: 9.5rem 1fr;
            gap: 8px 12px;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.92rem;
        }
        .rowline strong { color: #64748b; font-weight: 600; }
        .edit-btn, .save-btn {
            height: 32px;
            padding: 0 12px;
            border-radius: 8px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .edit-btn { border: 1px solid #09428f; background: #fff; color: #09428f; }
        .save-btn { border: 0; background: #09428f; color: #fff; min-width: 88px; }
        .foot { display: flex; justify-content: flex-end; margin-top: 22px; }
        .inline { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .d-none { display: none !important; }
        .file-link { color: #09428f; font-weight: 700; text-decoration: none; }
        .file-link:hover { text-decoration: underline; }
        .author-add { margin-top: 12px; }
        .author-form { margin-top: 12px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; }
        .author-form .form-label { font-weight: 700; }
    </style>
</head>
<body>
<main class="sheet">
    <h1><?= h($receiptNo) ?></h1>
    <?php if ($notice !== '') { ?><div class="alert <?= isset($_GET['error']) ? 'alert-danger' : 'alert-success' ?> py-2"><?= h($notice) ?></div><?php } ?>
    <?php if ($authorError !== '') { ?><div class="alert alert-danger py-2"><?= h($authorError) ?></div><?php } ?>
        <div class="rowline"><strong><?= h(t('제출자', 'Submitter')) ?></strong><span><?= h($abstract['email']) ?></span></div>
        <div class="rowline"><strong><?= h(t('제목', 'Title')) ?></strong><span><?= h($abstract['title']) ?></span></div>
        <div class="rowline"><strong><?= h(t('발표 희망 형태', 'Preferred type')) ?></strong><span><?= h($typeText) ?></span></div>
        <div class="rowline"><strong><?= h(t('분야', 'Topic')) ?></strong><span><?= h($abstract['topic']) ?></span></div>
        <div class="rowline"><strong><?= h(t('저자', 'Authors')) ?></strong><span>
            <?php if (!$authors && trim((string)$abstract['authors']) === '') { ?><?= h(t('등록된 저자가 없습니다.', 'No authors yet.')) ?><?php } ?>
            <?php if (!$authors && trim((string)$abstract['authors']) !== '') { ?><?= h($abstract['authors']) ?><?php } ?>
            <?php foreach ($authors as $person) {
                $roles = [];
                if (!empty($person['presenting'])) {
                    $roles[] = 'Presenting';
                }
                if (!empty($person['corresponding'])) {
                    $roles[] = 'Corresponding';
                }
                if (!empty($person['mentor'])) {
                    $roles[] = 'Mentor';
                }
                $place = trim($person['affiliation']);
                if (trim((string)$person['department']) !== '') {
                    $place .= ($place !== '' ? ' / ' : '') . trim((string)$person['department']);
                }
                $personCountry = isset($countryName[(int)$person['country']]) ? $countryName[(int)$person['country']] : '';
            ?>
            <div><?= h(trim($person['first_name'] . ' ' . $person['last_name'])) ?> · <?= h($roles ? implode(', ', $roles) : '—') ?><?= $place !== '' ? ' · ' . h($place) : '' ?><?= $personCountry !== '' ? ' · ' . h($personCountry) : '' ?><?= trim((string)$person['email']) !== '' ? ' · ' . h($person['email']) : '' ?></div>
            <?php } ?>
            <div class="author-add">
                <button class="edit-btn" type="button" id="authorAddToggle"><?= h(t('저자 추가', 'Add author')) ?></button>
                <form method="post" action="view.php?seq=<?= $seq ?>" id="authorAddForm" class="author-form<?= $authorError === '' ? ' d-none' : '' ?>">
                    <input type="hidden" name="action" value="add_author">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small" for="authorFirst"><?= h(t('이름(영문)', 'First Name')) ?> <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm" id="authorFirst" name="author_first" maxlength="80" value="<?= h($authorDraft['first']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="authorLast"><?= h(t('성(영문)', 'Last Name')) ?> <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm" id="authorLast" name="author_last" maxlength="80" value="<?= h($authorDraft['last']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small" for="authorAffiliation"><?= h(t('소속(영문)', 'Affiliation')) ?> <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm" id="authorAffiliation" name="author_affiliation" maxlength="200" value="<?= h($authorDraft['affiliation']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small" for="authorDepartment"><?= h(t('부서(영문)', 'Department')) ?></label>
                            <input class="form-control form-control-sm" id="authorDepartment" name="author_department" maxlength="120" value="<?= h($authorDraft['department']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small" for="authorCountry"><?= h(t('국가', 'Country')) ?> <span class="text-danger d-none" id="authorCountryStar">*</span></label>
                            <select class="form-select form-select-sm" id="authorCountry" name="author_country">
                                <option value=""><?= h(t('국가를 선택해 주세요', 'Choose a country')) ?></option>
                                <?php foreach ($countries as $countryRow) {
                                    $countryKorea = !empty(ksers_country_profile($countryRow)['korea']);
                                ?>
                                <option value="<?= (int)$countryRow['minr'] ?>" data-korea="<?= $countryKorea ? '1' : '0' ?>" <?= (int)$authorDraft['country'] === (int)$countryRow['minr'] ? 'selected' : '' ?>><?= h(code_name($countryRow)) ?></option>
                                <?php } ?>
                            </select>
                            <div class="form-text" id="authorCountryHelp"><?= h(t('발표자를 선택하면 국가가 필수입니다. 공저자는 선택입니다.', 'Required when Presenting is selected. Optional for co-authors.')) ?></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small" for="authorEmail"><?= h(t('이메일', 'E-mail')) ?> <span class="text-danger d-none" id="authorEmailStar">*</span></label>
                            <input class="form-control form-control-sm" id="authorEmail" name="author_email" type="email" maxlength="120" value="<?= h($authorDraft['email']) ?>">
                            <div class="form-text" id="authorEmailHelp"><?= h(t('발표자와 교신저자는 이메일이 필수입니다. 해외 국가를 고르면 공저자도 이메일이 필수입니다.', 'Required for the presenting author and corresponding author. An author outside Korea also needs an email address.')) ?></div>
                        </div>
                        <div class="col-12">
                            <div class="form-label small"><?= h(t('역할', 'Role')) ?> <span class="text-danger">*</span></div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="authorPresenting" name="author_presenting" value="1" <?= $authorDraft['presenting'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="authorPresenting">Presenting</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="authorCorresponding" name="author_corresponding" value="1" <?= $authorDraft['corresponding'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="authorCorresponding">Corresponding</label>
                            </div>
                            <?php if (in_array((string)$abstract['presentation_type'], ['mentor', 'mentee', 'abstract_video_2'], true)) { ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="authorMentor" name="author_mentor" value="1" <?= $authorDraft['mentor'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="authorMentor">Mentor</label>
                            </div>
                            <?php } ?>
                            <div class="form-text"><?= h(t('발표자와 Mentor는 동시에 지정할 수 없습니다. 교신저자와 Mentor는 겸임할 수 있습니다.', 'The presenting author and the mentor must be different people. A corresponding author may also be the mentor.')) ?></div>
                        </div>
                        <div class="col-12">
                            <button class="edit-btn" type="submit"><?= h(t('추가', 'Add')) ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </span></div>
        <div class="rowline"><strong><?= h(t('저자 국가', 'Author country')) ?></strong><span><?= h(code_name(['name_ko' => $abstract['country_ko'], 'name_eng' => $abstract['country_en']])) ?></span></div>
        <div class="rowline"><strong><?= h(t('소속', 'Affiliation')) ?></strong><span><?= h($abstract['affiliation']) ?></span></div>
        <div class="rowline"><strong><?= h(t('초록 파일', 'Abstract file')) ?></strong><span class="inline">
            <span id="fileText">
                <?php if (trim((string)$abstract['file_name']) === '') { ?>N<?php } else { ?>
                Y · <a class="file-link" href="/service/abstract_file.php?seq=<?= $seq ?>"><?= h($abstract['file_name']) ?></a>
                <?php } ?>
            </span>
            <input class="d-none" id="abstractFileInput" name="abstract_file" type="file" form="detailForm" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
            <span id="abstractFileChosen" class="d-none"></span>
            <button class="edit-btn" type="button" id="fileEdit"><?= h(t('수정', 'Edit')) ?></button>
        </span></div>
        <div class="rowline"><strong><?= h(t('영상', 'Video')) ?></strong><span>
            <?php if (!$videos) { ?>N<?php } else { ?>Y<?php } ?>
            <?php foreach ($videos as $video) { ?>
            <div><?= h(ksers_submission_label($video['kind'])) ?> · <a class="file-link" href="/service/submission_file.php?seq=<?= (int)$video['seq'] ?>"><?= h($video['original_name']) ?></a></div>
            <?php } ?>
        </span></div>
    <form method="post" action="action.php" id="detailForm" enctype="multipart/form-data">
        <input type="hidden" name="seq" value="<?= $seq ?>">
        <input type="hidden" name="return" value="view">
        <input type="hidden" name="final_edited" id="finalEdited" value="0">
        <div class="rowline">
            <strong><?= h(t('심사 상태', 'Review status')) ?></strong>
            <span class="inline">
                <span id="statusText"><?= h($statusText) ?></span>
                <select class="form-select form-select-sm d-none" id="statusSelect" name="status" style="width:auto">
                    <?php foreach ($statusLabel as $code => $label) { ?>
                    <option value="<?= h($code) ?>" <?= $abstract['status'] === $code ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php } ?>
                </select>
                <button class="edit-btn" type="button" id="statusEdit"><?= h(t('수정', 'Edit')) ?></button>
            </span>
        </div>
        <div class="rowline">
            <strong><?= h(t('최종 형태', 'Final type')) ?></strong>
            <span class="inline">
                <span id="finalText"><?= h($finalText) ?></span>
                <select class="form-select form-select-sm d-none" id="finalSelect" name="final_type" style="width:auto">
                    <?php
                    $finalSelect = $finalCode === 'poster' ? 'eposter' : $finalCode;
                    if (!isset($preferredChoices[$finalSelect])) {
                        $finalSelect = 'pending';
                    }
                    foreach ($preferredChoices as $code => $label) { ?>
                    <option value="<?= h($code) ?>" <?= $finalSelect === $code ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php } ?>
                </select>
                <button class="edit-btn" type="button" id="finalEdit"><?= h(t('수정', 'Edit')) ?></button>
            </span>
        </div>
        <div class="rowline"><strong><?= h(t('동의', 'Consent')) ?></strong><span><?= h(t('공동저자', 'Co-author')) ?> <?= h($yn(isset($abstract['agree_coauthor']) ? $abstract['agree_coauthor'] : 0)) ?> · <?= h(t('개인정보', 'Privacy')) ?> <?= h($yn(isset($abstract['agree_privacy']) ? $abstract['agree_privacy'] : 0)) ?> · <?= h(t('게재', 'Publication')) ?> <?= h($yn(isset($abstract['agree_publication']) ? $abstract['agree_publication'] : 0)) ?></span></div>
        <div class="rowline"><strong><?= h(t('등록일', 'Submitted')) ?></strong><span><?= h($abstract['reg_date']) ?></span></div>
        <div class="rowline"><strong><?= h(t('수정일', 'Updated')) ?></strong><span><?= h(isset($abstract['update_date']) ? $abstract['update_date'] : '') ?></span></div>
        <div class="rowline"><strong><?= h(t('결과 메일', 'Result mail')) ?></strong><span><?= !empty($abstract['mail_sent']) ? 'Y' : 'N' ?><?= !empty($abstract['mail_date']) ? ' · ' . h($abstract['mail_date']) : '' ?></span></div>
        <div class="foot">
            <button class="save-btn" type="button" id="detailSave"><?= h(t('저장', 'Save')) ?></button>
        </div>
    </form>
</main>
<div class="modal fade" id="detailSaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= h(t('저장', 'Save')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body"><?= h(t('심사 상태, 최종 형태, 초록 파일을 저장할까요?', 'Save the review status, final type, and abstract file?')) ?></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(t('취소', 'Cancel')) ?></button>
                <button type="button" class="btn btn-primary" id="detailSaveConfirm"><?= h(t('확인', 'OK')) ?></button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('authorAddToggle').addEventListener('click', function () {
    document.getElementById('authorAddForm').classList.toggle('d-none');
});
(function () {
    const countrySelect = document.getElementById('authorCountry');
    const emailInput = document.getElementById('authorEmail');
    const countryStar = document.getElementById('authorCountryStar');
    const emailStar = document.getElementById('authorEmailStar');
    const countryHelp = document.getElementById('authorCountryHelp');
    const emailHelp = document.getElementById('authorEmailHelp');
    const helpPresenting = <?= json_encode(t('발표자는 국가와 이메일이 필수입니다.', 'The presenting author needs a country and an email address.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const helpCorresponding = <?= json_encode(t('교신저자는 이메일이 필수입니다. 국가는 선택입니다.', 'The corresponding author needs an email address. Country is optional.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const helpOverseas = <?= json_encode(t('해외 국가를 고르면 이메일이 필수입니다.', 'Choosing a country outside Korea makes the email address required.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const helpOptional = <?= json_encode(t('공저자는 국가와 이메일이 선택입니다. 발표자를 선택하면 둘 다 필수가 됩니다.', 'Country and email are optional for a co-author. Both are required for the presenting author.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const emailRequired = <?= json_encode(t('발표자와 교신저자의 이메일을 입력해 주세요.', 'Enter an email for the presenting author and corresponding author.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    function selectedKorea() {
        const option = countrySelect.options[countrySelect.selectedIndex];
        return !!(option && option.getAttribute('data-korea') === '1');
    }
    function syncAuthorRequired() {
        const presenting = document.getElementById('authorPresenting').checked;
        const corresponding = document.getElementById('authorCorresponding').checked;
        const needEmail = presenting || corresponding || (countrySelect.value !== '' && !selectedKorea());
        countryStar.classList.toggle('d-none', !presenting);
        emailStar.classList.toggle('d-none', !needEmail);
        if (presenting) {
            countryHelp.textContent = helpPresenting;
        } else if (corresponding) {
            countryHelp.textContent = helpCorresponding;
        } else if (countrySelect.value !== '' && !selectedKorea()) {
            countryHelp.textContent = helpOverseas;
        } else {
            countryHelp.textContent = helpOptional;
        }
        emailHelp.textContent = needEmail ? emailRequired : helpOptional;
    }
    const authorMentor = document.getElementById('authorMentor');
    document.getElementById('authorPresenting').addEventListener('change', function () {
        if (this.checked && authorMentor) {
            authorMentor.checked = false;
        }
        syncAuthorRequired();
    });
    if (authorMentor) {
        authorMentor.addEventListener('change', function () {
            if (this.checked) {
                document.getElementById('authorPresenting').checked = false;
            }
            syncAuthorRequired();
        });
    }
    document.getElementById('authorCorresponding').addEventListener('change', syncAuthorRequired);
    countrySelect.addEventListener('change', syncAuthorRequired);
    emailInput.addEventListener('input', syncAuthorRequired);
    syncAuthorRequired();
})();
document.getElementById('fileEdit').addEventListener('click', function () {
    const input = document.getElementById('abstractFileInput');
    input.classList.remove('d-none');
    input.click();
});
document.getElementById('abstractFileInput').addEventListener('change', function () {
    const chosen = document.getElementById('abstractFileChosen');
    const file = this.files && this.files[0] ? this.files[0].name : '';
    chosen.textContent = file;
    chosen.classList.toggle('d-none', file === '');
});
document.getElementById('statusEdit').addEventListener('click', function () {
    document.getElementById('statusText').classList.add('d-none');
    document.getElementById('statusSelect').classList.remove('d-none');
});
document.getElementById('finalEdit').addEventListener('click', function () {
    document.getElementById('finalText').classList.add('d-none');
    document.getElementById('finalSelect').classList.remove('d-none');
});
document.getElementById('finalSelect').addEventListener('change', function () {
    document.getElementById('finalEdited').value = '1';
});
document.getElementById('detailSave').addEventListener('click', function () {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('detailSaveModal')).show();
});
document.getElementById('detailSaveConfirm').addEventListener('click', function () {
    document.getElementById('detailForm').submit();
});
<?php if (isset($_GET['saved'])) { ?>
if (window.opener && !window.opener.closed) {
    window.opener.location.reload();
}
<?php } ?>
</script>
</body>
</html>
