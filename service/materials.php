<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';

$memberSeq = isset($_SESSION['seq']) ? (int)$_SESSION['seq'] : 0;
if ($memberSeq < 1) {
    header('Location: /service/login.php');
    exit;
}
ksers_require_participant($pdo);

$year = isset($eventYear) ? (int)$eventYear : 2027;
$videoKinds = ['abstract_video_1', 'abstract_video_2'];
$videoMaxBytes = 1024 * 1024 * 1024;
$error = '';

$memberStmt = $pdo->prepare("SELECT name_ko, first_name_eng, last_name_eng FROM member_info WHERE seq = ?");
$memberStmt->execute([$memberSeq]);
$member = $memberStmt->fetch(PDO::FETCH_ASSOC);
$nameKo = $member ? trim((string)$member['name_ko']) : '';
$nameEn = $member ? trim($member['first_name_eng'] . ' ' . $member['last_name_eng']) : '';
$registrantName = ksers_lang() === 'en'
    ? ($nameEn !== '' ? $nameEn : $nameKo)
    : ($nameKo !== '' ? $nameKo : $nameEn);

$loadOwned = function ($seq) use ($pdo, $memberSeq, $year, $videoKinds) {
    $stmt = $pdo->prepare(
        "SELECT * FROM member_submission_file
         WHERE seq = ? AND member_seq = ? AND year = ? AND kind IN ('abstract_video_1', 'abstract_video_2')"
    );
    $stmt->execute([(int)$seq, $memberSeq, $year]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !in_array($row['kind'], $videoKinds, true)) {
        return null;
    }
    return $row;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if (!ksers_submission_csrf_valid(isset($_POST['csrf']) ? $_POST['csrf'] : '')) {
        $error = t('요청이 만료되었습니다. 새로고침 후 다시 시도해 주세요.', 'The request expired. Refresh and try again.');
    } elseif ($action === 'delete') {
        $current = $loadOwned(isset($_POST['seq']) ? $_POST['seq'] : 0);
        if (!$current) {
            $error = t('삭제할 파일을 찾지 못했습니다.', 'That file could not be found.');
        } else {
            $delete = $pdo->prepare("DELETE FROM member_submission_file WHERE seq = ? AND member_seq = ?");
            $delete->execute([(int)$current['seq'], $memberSeq]);
            $pdo->prepare("DELETE FROM abstract_video_link WHERE file_seq = ?")->execute([(int)$current['seq']]);
            $path = ksers_submission_find($current['kind'], $current['stored_name']);
            if ($path !== '' && is_file($path)) {
                unlink($path);
            }
            header('Location: materials.php?deleted=1');
            exit;
        }
    } elseif ($action === 'save') {
        $kind = isset($_POST['kind']) ? $_POST['kind'] : '';
        $abstractSeq = isset($_POST['abstract_seq']) ? (int)$_POST['abstract_seq'] : 0;
        $ownAbstract = $pdo->prepare("SELECT seq, presentation_type FROM abstract_info WHERE seq = ? AND member_seq = ? AND year = ?");
        $ownAbstract->execute([$abstractSeq, $memberSeq, $year]);
        $abstractRow = $ownAbstract->fetch(PDO::FETCH_ASSOC);
        $expectedKind = $abstractRow ? ksers_video_kind_for_presentation($abstractRow['presentation_type']) : '';
        $meta = null;
        $storedName = '';
        $target = '';
        if (!in_array($kind, $videoKinds, true) || $expectedKind === '' || $kind !== $expectedKind) {
            $error = t('Video 또는 Mentor-Mentee Video로 제출한 초록만 연결할 수 있습니다.', 'Only an abstract submitted as Video or Mentor-Mentee Video can be linked.');
        }
        if ($error === '' && empty($_POST['video_agree'])) {
            $error = t('영상 확인 및 사용 동의를 체크해 주세요.', 'Please check the video confirmation and consent.');
        }
        if ($error === '' && $kind === 'abstract_video_2') {
            $mentorStmt = $pdo->prepare("SELECT presenting, mentor FROM abstract_author WHERE abstract_seq = ?");
            $mentorStmt->execute([$abstractSeq]);
            $mentorCount = 0;
            $mentorConflict = false;
            foreach ($mentorStmt->fetchAll(PDO::FETCH_ASSOC) as $authorRow) {
                if (!empty($authorRow['mentor']) && !empty($authorRow['presenting'])) {
                    $mentorConflict = true;
                }
                if (!empty($authorRow['mentor'])) {
                    $mentorCount++;
                }
            }
            if ($mentorConflict || $mentorCount !== 1) {
                $error = t('Mentor-Mentee Video는 발표자가 아닌 Mentor를 한 명 지정해야 합니다. 초록 제출에서 저자 역할을 수정해 주세요.', 'Mentor-Mentee Video needs one mentor who is not the presenting author. Update the author roles in Abstract Submission.');
            }
        }
        if ($error === '') {
            $file = isset($_FILES['submission_file']) ? $_FILES['submission_file'] : null;
            $extension = strtolower(pathinfo(isset($file['name']) ? (string)$file['name'] : '', PATHINFO_EXTENSION));
            $size = isset($file['size']) ? (int)$file['size'] : 0;
            if ($extension !== 'mp4' || $size > $videoMaxBytes) {
                $error = t('파일은 1GB 이하의 MP4만 올릴 수 있습니다.', 'Upload an MP4 file up to 1 GB.');
            } else {
                list($error, $meta) = ksers_submission_check_file($kind, $file);
            }
        }
        if ($error === '') {
            list($error, $storedName, $target) = ksers_submission_place($kind, $meta, $year, $memberSeq);
        }
        if ($error === '') {
            try {
                $currentStmt = $pdo->prepare(
                    "SELECT * FROM member_submission_file
                     WHERE member_seq = ? AND year = ? AND kind = ? AND abstract_seq = ?"
                );
                $currentStmt->execute([$memberSeq, $year, $kind, $abstractSeq]);
                $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
                if ($current) {
                    $save = $pdo->prepare(
                        "UPDATE member_submission_file
                         SET original_name = ?, stored_name = ?, mime_type = ?, file_size = ?, update_date = NOW()
                         WHERE seq = ? AND member_seq = ?"
                    );
                    $save->execute([
                        $meta['original'], $storedName, $meta['mime'], $meta['size'],
                        (int)$current['seq'], $memberSeq,
                    ]);
                    $oldPath = ksers_submission_find($kind, $current['stored_name']);
                    if ($oldPath !== '' && $oldPath !== $target && is_file($oldPath)) {
                        unlink($oldPath);
                    }
                } else {
                    $save = $pdo->prepare(
                        "INSERT INTO member_submission_file
                            (member_seq, year, kind, abstract_seq, original_name, stored_name,
                             mime_type, file_size, reg_date, update_date)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                    );
                    $save->execute([
                        $memberSeq, $year, $kind, $abstractSeq,
                        $meta['original'], $storedName, $meta['mime'], $meta['size'],
                    ]);
                }
                $fileSeq = $current ? (int)$current['seq'] : (int)$pdo->lastInsertId();
                ksers_abstract_video_link_ensure($pdo, $fileSeq, $abstractSeq, $year);
                if ($kind !== 'abstract_video_2') {
                    $pdo->prepare("UPDATE abstract_author SET mentor = 0 WHERE abstract_seq = ?")->execute([$abstractSeq]);
                }
                ksers_abstract_updated_notify($pdo, $abstractSeq, false, (bool)$current);
                header('Location: materials.php?saved=1');
                exit;
            } catch (Exception $e) {
                if ($target !== '' && is_file($target)) {
                    unlink($target);
                }
                error_log('material submission save failed');
                $error = t('제출 정보를 저장하지 못했습니다.', 'Could not save the submission.');
            }
        }
    }
}

$topicStmt = $pdo->prepare("SELECT seq, name_ko, name_en FROM abstract_topic WHERE year = ? AND state = 1 ORDER BY sort_no, seq");
$topicStmt->execute([$year]);
$topics = $topicStmt->fetchAll(PDO::FETCH_ASSOC);
$abstractStmt = $pdo->prepare("SELECT * FROM abstract_info WHERE member_seq = ? AND year = ? ORDER BY seq DESC");
$abstractStmt->execute([$memberSeq, $year]);
$abstracts = [];
foreach ($abstractStmt->fetchAll(PDO::FETCH_ASSOC) as $abstractRow) {
    if (ksers_video_kind_for_presentation($abstractRow['presentation_type']) !== '') {
        $abstracts[] = $abstractRow;
    }
}
$videoByAbstract = [];
$videoStmt = $pdo->prepare(
    "SELECT abstract_seq, file_size FROM member_submission_file
     WHERE member_seq = ? AND year = ? AND kind IN ('abstract_video_1', 'abstract_video_2') AND abstract_seq IS NOT NULL
     ORDER BY seq DESC"
);
$videoStmt->execute([$memberSeq, $year]);
foreach ($videoStmt->fetchAll(PDO::FETCH_ASSOC) as $videoRow) {
    $videoSeq = (int)$videoRow['abstract_seq'];
    if ($videoSeq > 0 && !isset($videoByAbstract[$videoSeq])) {
        $videoByAbstract[$videoSeq] = (int)$videoRow['file_size'];
    }
}
$presentationTypes = [
    'oral' => 'Oral',
    'eposter' => 'E-Poster',
    'poster' => t('포스터', 'Poster'),
    'either' => t('상관없음', 'No preference'),
    'video' => 'Video',
    'abstract_video_1' => t('영상 발표', 'Video'),
    'mentor' => 'Mentor-Mentee Video',
    'abstract_video_2' => 'Mentor-Mentee Video',
];
$topicLabel = function ($stored) use ($topics) {
    foreach ($topics as $topicRow) {
        if ((string)$topicRow['seq'] === (string)$stored || $topicRow['name_ko'] === $stored || $topicRow['name_en'] === $stored) {
            return ksers_text($topicRow, 'name_ko', 'name_en');
        }
    }
    return (string)$stored;
};
$presentationBadge = function ($code) {
    if ($code === 'video' || $code === 'abstract_video_1') {
        return 'is-video';
    }
    if ($code === 'mentor' || $code === 'mentee' || $code === 'abstract_video_2') {
        return 'is-mentor';
    }
    if ($code === 'eposter' || $code === 'poster') {
        return 'is-eposter';
    }
    return 'is-oral';
};
$fileSizeLabel = function ($bytes) {
    $bytes = (int)$bytes;
    if ($bytes >= 1048576) {
        return number_format(max(1, (int)round($bytes / 1048576))) . ' MB';
    }
    if ($bytes > 0) {
        return number_format(max(1, (int)round($bytes / 1024))) . ' KB';
    }
    return '';
};

$list = $pdo->prepare(
    "SELECT * FROM member_submission_file
     WHERE member_seq = ? AND year = ? AND kind IN ('abstract_video_1', 'abstract_video_2')
     ORDER BY seq DESC"
);
$list->execute([$memberSeq, $year]);
$rows = $list->fetchAll(PDO::FETCH_ASSOC);

$page_title = t('영상 제출 - KSERS ' . $year, 'Video Submission - KSERS ' . $year);
$page_heading = t('영상 제출', 'Video Submission');
include __DIR__ . '/lib/header.php';
include __DIR__ . '/lib/menu.php';
$deleteConfirm = json_encode(t('이 파일을 삭제할까요?', 'Delete this file?'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$noFileLabel = t('선택된 파일 없음', 'No file selected');
?>

<div class="mb-4">
        <div class="video-intro">
            <button class="video-link-btn" type="button" data-bs-toggle="modal" data-bs-target="#abstractLinkModal"><?= h(t('+ 영상을 첨부할 초록 연결', '+ Link an abstract for this video')) ?></button>
            <p class="video-guide">
                <?= h(t('초록 제출 메뉴에서 발표 형태를 Video 또는 Mentor-Mentee Video로 선택해 초록을 먼저 제출해 주세요.', 'In Abstract Submission, choose Video or Mentor-Mentee Video and submit the abstract first.')) ?>
                <a href="abstract.php"><?= h(t('바로가기', 'Go')) ?></a>
            </p>
        </div>
        <?php if (isset($_GET['saved'])) { ?>
        <div class="alert alert-success"><?= h(t('파일을 등록했습니다.', 'The file was uploaded.')) ?></div>
        <?php } elseif (isset($_GET['deleted'])) { ?>
        <div class="alert alert-info"><?= h(t('파일을 삭제했습니다.', 'The file was deleted.')) ?></div>
        <?php } ?>
        <?php if ($error !== '') { ?><div class="alert alert-danger"><?= h($error) ?></div><?php } ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><?= h(t('파일 등록', 'Upload a file')) ?></h5>
                <form method="post" enctype="multipart/form-data" id="videoForm" class="video-form is-locked" data-leave="materials">
                    <input type="hidden" name="csrf" value="<?= h(ksers_submission_csrf()) ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="abstract_seq" id="linkedAbstract" value="">
                    <input type="hidden" name="kind" id="videoKind" value="">
                    <div class="row g-3 align-items-end mb-4">
                        <div class="col-md-5">
                            <label class="form-label fw-bold" for="linkedReceipt"><?= h(t('초록 접수번호', 'Abstract No.')) ?></label>
                            <input class="form-control" id="linkedReceipt" value="" disabled>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold" for="linkedName"><?= h(t('등록자명', 'Registrant')) ?></label>
                            <input class="form-control" id="linkedName" value="" disabled>
                        </div>
                        <div class="col-md-2">
                            <button class="btn ksers-outline w-100" type="button" id="linkedPreview" disabled><?= h(t('미리보기', 'Preview')) ?></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-label fw-bold"><?= h(t('영상 파일', 'Video File')) ?></div>
                        <div class="video-type-grid">
                            <?php foreach ($videoKinds as $kind) { ?>
                            <div class="video-type-card">
                                <input type="radio" value="<?= h($kind) ?>" disabled>
                                <span><?= h(ksers_submission_label($kind)) ?></span>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold" for="videoFile"><?= h(t('파일', 'File')) ?></label>
                        <div class="file-picker is-wide">
                            <span class="file-picker-btn">
                                <span class="btn btn-outline-secondary"><?= h(t('파일 선택', 'Choose file')) ?></span>
                                <input id="videoFile" type="file" name="submission_file" accept=".mp4,video/mp4" disabled>
                            </span>
                            <span class="file-picker-name" id="videoFileName"><?= h($noFileLabel) ?></span>
                        </div>
                        <div class="form-text"><?= h(t('허용 형식: MP4 · 최대 1 GB', 'Allowed: MP4 · Max 1 GB')) ?></div>
                    </div>
                    <div class="card bg-light border-0 p-3 mt-3 mb-2" id="videoConsent">
                        <div class="join-consent-item">
                            <input class="form-check-input" type="checkbox" id="videoAgree" name="video_agree" value="1" disabled>
                            <label class="join-consent-badge text-danger" for="videoAgree"><?= h(t('[필수]', '[Required]')) ?></label>
                            <div class="join-consent-body">
                                <div class="join-consent-head">
                                    <label class="form-check-label small text-danger" for="videoAgree"><?= h(t('영상 확인 및 사용 동의', 'Video Confirmation & Consent')) ?></label>
                                </div>
                                <p class="join-consent-note"><?= h(t('영상에 환자를 식별할 수 있는 정보가 모두 삭제되었고, 필요한 동의와 권리를 확보했음을 확인합니다. 이 영상이 KSERS 2027의 초록 심사와 발표·상영에 사용되는 데 동의합니다.', 'I confirm that all patient-identifiable information has been removed from the video and that I have obtained the necessary consent and rights. I agree that the video may be used for abstract review and for presentation and screening at KSERS 2027.')) ?></p>
                            </div>
                        </div>
                    </div>
                    <div id="videoConsentHint" class="form-text small is-invalid d-none"><?= h(t('필수사항을 체크해 주세요.', 'Please check the required items.')) ?></div>
                    <div class="d-flex justify-content-end mt-3">
                        <button class="btn btn-primary-custom fw-bold" type="submit" id="videoUpload" disabled><?= h(t('등록', 'Upload')) ?></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="m-0 fw-bold"><?= h(t('등록된 파일', 'Uploaded files')) ?> <span class="text-muted fs-6"><?= count($rows) ?></span></h5>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 video-file-table">
                    <thead class="table-light">
                        <tr>
                            <th class="video-col-no"><?= h(t('초록 접수번호', 'Abstract No.')) ?></th>
                            <th class="video-col-type"><?= h(t('종류', 'Type')) ?></th>
                            <th class="video-col-file"><?= h(t('파일', 'File')) ?></th>
                            <th class="video-col-date"><?= h(t('제출일', 'Submitted')) ?></th>
                            <th class="video-col-manage text-end"><?= h(t('관리', 'Manage')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows) { ?>
                        <tr><td colspan="5" class="text-center text-muted py-4"><?= h(t('아직 등록한 파일이 없습니다.', 'No files yet.')) ?></td></tr>
                        <?php } ?>
                        <?php foreach ($rows as $row) { ?>
                        <tr>
                            <td><?= (int)$row['abstract_seq'] > 0 ? h(sprintf('A%d-%04d', $year, (int)$row['abstract_seq'])) : '—' ?></td>
                            <td><?= h(ksers_submission_label($row['kind'])) ?></td>
                            <td class="video-col-file">
                                <a href="/service/submission_file.php?seq=<?= (int)$row['seq'] ?>"><?= h($row['original_name']) ?></a>
                                <div class="small text-muted"><?= h($fileSizeLabel($row['file_size'])) ?></div>
                            </td>
                            <td class="text-nowrap"><?= h($row['update_date'] ?: $row['reg_date']) ?></td>
                            <td class="text-end">
                                <form method="post" class="d-inline" onsubmit="return confirm(<?= $deleteConfirm ?>)">
                                    <input type="hidden" name="csrf" value="<?= h(ksers_submission_csrf()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="seq" value="<?= (int)$row['seq'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><?= h(t('삭제', 'Delete')) ?></button>
                                </form>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
</div>

<div class="modal fade" id="abstractLinkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= h(t('영상을 첨부할 초록 연결', 'Link an abstract for this video')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive abstract-list-scroll">
                    <table class="table align-middle mb-0 abstract-list-table">
                        <thead class="table-light">
                            <tr>
                                <th></th>
                                <th><?= h(t('접수번호', 'Receipt No.')) ?></th>
                                <th><?= h(t('발표 희망 형태', 'Preferred type')) ?></th>
                                <th><?= h(t('제목', 'Title')) ?></th>
                                <th><?= h(t('분야', 'Topic')) ?></th>
                                <th><?= h(t('영상', 'Video')) ?></th>
                                <th><?= h(t('심사 상태', 'Review status')) ?></th>
                                <th><?= h(t('미리보기', 'Preview')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$abstracts) { ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4"><?= h(t('Video 또는 Mentor-Mentee Video로 제출한 초록이 없습니다.', 'No abstract submitted as Video or Mentor-Mentee Video.')) ?></td>
                            </tr>
                            <?php } ?>
                            <?php foreach ($abstracts as $row) {
                                $abstractNo = sprintf('A%d-%04d', $year, (int)$row['seq']);
                                $memberStatus = ksers_abstract_member_status($row['status']);
                                $videoSize = isset($videoByAbstract[(int)$row['seq']]) ? $videoByAbstract[(int)$row['seq']] : 0;
                                $typeCode = (string)$row['presentation_type'];
                                $typeText = isset($presentationTypes[$typeCode]) ? $presentationTypes[$typeCode] : $typeCode;
                            ?>
                            <tr class="abstract-pick-row">
                                <td class="text-center">
                                    <input class="form-check-input abstract-pick" type="radio" name="abstract_pick" value="<?= (int)$row['seq'] ?>" data-receipt="<?= h($abstractNo) ?>" data-kind="<?= h(ksers_video_kind_for_presentation($typeCode)) ?>">
                                </td>
                                <td class="abstract-no"><?= h($abstractNo) ?></td>
                                <td><span class="abstract-type <?= h($presentationBadge($typeCode)) ?>"><?= h($typeText) ?></span></td>
                                <td class="abstract-title"><?= h($row['title']) ?></td>
                                <td><?= h($topicLabel($row['topic'])) ?></td>
                                <td>
                                    <?php if ($videoSize > 0) { ?>
                                    <div class="abstract-video-done"><?= h(t('제출 완료', 'Submitted')) ?></div>
                                    <div class="abstract-video-size"><?= h($fileSizeLabel($videoSize)) ?></div>
                                    <?php } else { ?>
                                    <span class="abstract-dash">-</span>
                                    <?php } ?>
                                </td>
                                <td><span class="abstract-status <?= h($memberStatus['class']) ?>"><?= h($memberStatus['label']) ?></span></td>
                                <td>
                                    <button class="abstract-action is-preview" type="button" onclick="ksersOpenPreview(<?= (int)$row['seq'] ?>)"><?= h(t('미리보기', 'Preview')) ?></button>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <?php if (!$abstracts) { ?>
                <a class="btn btn-primary-custom" href="abstract.php?add=1"><?= h(t('초록 제출하기', 'Submit an abstract')) ?></a>
                <?php } else { ?>
                <button class="btn btn-primary-custom" type="button" id="abstractLinkConfirm" disabled><?= h(t('등록', 'Link')) ?></button>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script>
const registrantName = <?= json_encode($registrantName, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const noFileLabel = <?= json_encode($noFileLabel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const videoForm = document.getElementById('videoForm');
const linkedAbstract = document.getElementById('linkedAbstract');
const linkedReceipt = document.getElementById('linkedReceipt');
const linkedName = document.getElementById('linkedName');
const linkedPreview = document.getElementById('linkedPreview');
const videoFile = document.getElementById('videoFile');
const videoFileName = document.getElementById('videoFileName');
const videoUpload = document.getElementById('videoUpload');
const videoAgree = document.getElementById('videoAgree');
const videoKind = document.getElementById('videoKind');
const linkConfirm = document.getElementById('abstractLinkConfirm');

function syncVideoUpload() {
    const fileReady = videoFile.files && videoFile.files.length > 0;
    const agreed = videoAgree && videoAgree.checked;
    videoUpload.disabled = linkedAbstract.value === '' || videoKind.value === '' || !fileReady || !agreed;
    if (videoAgree) {
        videoAgree.classList.toggle('is-invalid', linkedAbstract.value !== '' && fileReady && !agreed);
    }
    const consentHint = document.getElementById('videoConsentHint');
    if (consentHint) {
        consentHint.classList.toggle('d-none', linkedAbstract.value === '' || !fileReady || agreed);
    }
}

document.querySelectorAll('.abstract-pick-row').forEach(function (row) {
    const radio = row.querySelector('.abstract-pick');
    if (!radio) {
        return;
    }
    row.addEventListener('click', function () {
        radio.checked = true;
        radio.dispatchEvent(new Event('change'));
    });
});
document.querySelectorAll('.abstract-pick').forEach(function (radio) {
    radio.addEventListener('change', function () {
        if (linkConfirm) {
            linkConfirm.disabled = !document.querySelector('.abstract-pick:checked');
        }
    });
});
if (linkConfirm) {
    linkConfirm.addEventListener('click', function () {
        const picked = document.querySelector('.abstract-pick:checked');
        if (!picked) {
            return;
        }
        const kind = picked.getAttribute('data-kind') || '';
        linkedAbstract.value = picked.value;
        videoKind.value = kind;
        linkedReceipt.value = picked.getAttribute('data-receipt') || '';
        linkedName.value = registrantName;
        linkedPreview.disabled = false;
        videoForm.classList.remove('is-locked');
        videoForm.querySelectorAll('.video-type-card').forEach(function (card) {
            const radio = card.querySelector('input');
            const on = !!(radio && radio.value === kind);
            if (radio) {
                radio.checked = on;
                radio.disabled = true;
            }
            card.classList.toggle('is-on', on);
        });
        videoFile.disabled = false;
        videoAgree.disabled = false;
        syncVideoUpload();
        if (window.bootstrap) {
            const modal = document.getElementById('abstractLinkModal');
            window.bootstrap.Modal.getOrCreateInstance(modal).hide();
        }
    });
}
linkedPreview.addEventListener('click', function () {
    if (linkedAbstract.value !== '') {
        ksersOpenPreview(linkedAbstract.value);
    }
});
videoAgree.addEventListener('change', function () {
    videoAgree.classList.remove('is-invalid');
    syncVideoUpload();
});
videoFile.addEventListener('change', function () {
    const file = videoFile.files && videoFile.files[0];
    videoFileName.textContent = file ? file.name : noFileLabel;
    syncVideoUpload();
});
function ksersOpenPreview(seq) {
    const width = 768;
    const height = 900;
    const left = Math.max(0, Math.round((window.screen.width - width) / 2));
    const top = Math.max(0, Math.round((window.screen.height - height) / 2));
    window.open(
        'abstract_preview.php?seq=' + seq,
        'ksersAbstractPreview',
        'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes'
    );
}
</script>

<?php include __DIR__ . '/lib/footer.php'; ?>
