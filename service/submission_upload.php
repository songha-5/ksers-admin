<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_participant($pdo);

$memberSeq = isset($_SESSION['seq']) ? (int)$_SESSION['seq'] : 0;
if ($memberSeq < 1) {
    $returnPath = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/service/login.php';
    header('Location: /service/login.php');
    exit;
}

$submissionKind = isset($submissionKind) ? $submissionKind : '';
$config = ksers_submission_type($submissionKind);
if (!$config) {
    http_response_code(404);
    exit('Not found');
}

$year = isset($eventYear) ? (int)$eventYear : 2027;
$storeDir = ksers_submission_dir($submissionKind);
$videoPending = !empty($config['video']) && $storeDir === '';
$error = '';

$loadCurrent = function () use ($pdo, $memberSeq, $year, $submissionKind) {
    $stmt = $pdo->prepare(
        "SELECT * FROM member_submission_file WHERE member_seq = ? AND year = ? AND kind = ?"
    );
    $stmt->execute([$memberSeq, $year, $submissionKind]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ksers_submission_csrf_valid(isset($_POST['csrf']) ? $_POST['csrf'] : '')) {
        $error = t('요청이 만료되었습니다. 새로고침 후 다시 시도해 주세요.', 'The request expired. Refresh and try again.');
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $current = $loadCurrent();
        if ($current) {
            $delete = $pdo->prepare(
                "DELETE FROM member_submission_file WHERE seq = ? AND member_seq = ?"
            );
            $delete->execute([(int)$current['seq'], $memberSeq]);
            $path = ksers_submission_find($submissionKind, $current['stored_name']);
            if ($path !== '') {
                unlink($path);
            }
        }
        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?deleted=1');
        exit;
    } elseif ($videoPending) {
        $error = t('영상 저장 위치 확정 후 업로드가 열립니다.', 'Video upload opens after storage is confirmed.');
    } else {
        $file = isset($_FILES['submission_file']) ? $_FILES['submission_file'] : null;
        list($error, $meta) = ksers_submission_check_file($submissionKind, $file);
        $abstractSeq = null;
        if ($error === '' && !empty($config['video'])) {
            $abstractSeq = isset($_POST['abstract_seq']) ? (int)$_POST['abstract_seq'] : 0;
            $ownAbstract = $pdo->prepare(
                "SELECT seq FROM abstract_info WHERE seq = ? AND member_seq = ? AND year = ?"
            );
            $ownAbstract->execute([$abstractSeq, $memberSeq, $year]);
            if (!$ownAbstract->fetchColumn()) {
                $error = t('연결할 본인 초록을 선택해 주세요.', 'Choose one of your abstracts.');
            }
        }
        if ($error === '') {
            list($error, $storedName, $target) = ksers_submission_place($submissionKind, $meta, $year, $memberSeq);
        }
        if ($error === '') {
            $current = $loadCurrent();
            try {
                if ($current) {
                    $save = $pdo->prepare(
                        "UPDATE member_submission_file
                         SET abstract_seq = ?, original_name = ?, stored_name = ?, mime_type = ?, file_size = ?, update_date = NOW()
                         WHERE seq = ? AND member_seq = ?"
                    );
                    $save->execute([
                        $abstractSeq ?: null, $meta['original'], $storedName, $meta['mime'], $meta['size'],
                        (int)$current['seq'], $memberSeq,
                    ]);
                    $oldPath = ksers_submission_find($submissionKind, $current['stored_name']);
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
                        $memberSeq, $year, $submissionKind, $abstractSeq ?: null,
                        $meta['original'], $storedName, $meta['mime'], $meta['size'],
                    ]);
                }
                header('Location: ' . basename($_SERVER['PHP_SELF']) . '?saved=1');
                exit;
            } catch (Exception $e) {
                if (is_file($target)) {
                    unlink($target);
                }
                error_log('member submission save failed');
                $error = t('제출 정보를 저장하지 못했습니다.', 'Could not save the submission.');
            }
        }
    }
}

$current = $loadCurrent();
$abstracts = [];
if (!empty($config['video'])) {
    $stmt = $pdo->prepare(
        "SELECT seq, title FROM abstract_info WHERE member_seq = ? AND year = ? ORDER BY seq DESC"
    );
    $stmt->execute([$memberSeq, $year]);
    $abstracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = ksers_submission_label($submissionKind) . ' - KSERS ' . $year;
$page_heading = ksers_submission_label($submissionKind);
include __DIR__ . '/lib/header.php';
include __DIR__ . '/lib/menu.php';
?>

<div class="row justify-content-center mb-5">
    <div class="col-lg-8">
        <?php if (isset($_GET['saved'])) { ?>
        <div class="alert alert-success"><?= h(t('파일을 제출했습니다.', 'The file was submitted.')) ?></div>
        <?php } elseif (isset($_GET['deleted'])) { ?>
        <div class="alert alert-info"><?= h(t('제출 파일을 삭제했습니다.', 'The submitted file was deleted.')) ?></div>
        <?php } ?>
        <?php if ($error !== '') { ?><div class="alert alert-danger"><?= h($error) ?></div><?php } ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><?= h(ksers_submission_label($submissionKind)) ?></h5>
                <?php if ($videoPending) { ?>
                <div class="alert alert-warning mb-0">
                    <?= h(t('제출 화면은 준비되었습니다. 영상 저장 위치와 용량 기준 확정 후 업로드가 열립니다.', 'The submission page is ready. Upload opens after video storage and limits are confirmed.')) ?>
                </div>
                <?php } else { ?>
                <p class="text-muted small">
                    <?= h(t('허용 형식: ', 'Allowed: ') . strtoupper(implode(', ', $config['extensions']))) ?>
                    · <?= h(t('최대 ', 'Max ') . number_format($config['max_bytes'] / 1024 / 1024) . ' MB') ?>
                </p>

                <?php if ($current) { ?>
                <div class="border rounded p-3 mb-4 bg-light">
                    <div class="fw-bold"><?= h($current['original_name']) ?></div>
                    <div class="small text-muted mb-2">
                        <?= h(number_format((int)$current['file_size'] / 1024 / 1024, 2) . ' MB') ?>
                        · <?= h($current['update_date'] ?: $current['reg_date']) ?>
                    </div>
                    <a class="btn btn-sm btn-outline-primary" href="/service/submission_file.php?seq=<?= (int)$current['seq'] ?>"><?= h(t('다운로드', 'Download')) ?></a>
                    <form method="post" class="d-inline" onsubmit="return confirm('<?= h(t('제출 파일을 삭제할까요?', 'Delete the submitted file?')) ?>')">
                        <input type="hidden" name="csrf" value="<?= h(ksers_submission_csrf()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn btn-sm btn-outline-danger" type="submit"><?= h(t('삭제', 'Delete')) ?></button>
                    </form>
                </div>
                <?php } ?>

                <form method="post" enctype="multipart/form-data" data-leave="materials">
                    <input type="hidden" name="csrf" value="<?= h(ksers_submission_csrf()) ?>">
                    <input type="hidden" name="action" value="save">
                    <?php if (!empty($config['video'])) { ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><?= h(t('연결할 초록', 'Abstract')) ?></label>
                        <select class="form-select" name="abstract_seq" required>
                            <option value=""><?= h(t('선택', 'Choose')) ?></option>
                            <?php foreach ($abstracts as $abstract) { ?>
                            <option value="<?= (int)$abstract['seq'] ?>" <?= $current && (int)$current['abstract_seq'] === (int)$abstract['seq'] ? 'selected' : '' ?>>
                                <?= h($abstract['title']) ?>
                            </option>
                            <?php } ?>
                        </select>
                        <?php if (!$abstracts) { ?><div class="form-text text-danger"><?= h(t('먼저 초록을 제출해 주세요.', 'Submit an abstract first.')) ?></div><?php } ?>
                    </div>
                    <?php } ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><?= h(t('파일', 'File')) ?></label>
                        <input class="form-control" type="file" name="submission_file"
                               accept="<?= h(implode(',', array_map(function ($ext) { return '.' . $ext; }, $config['extensions']))) ?>" required>
                    </div>
                    <button class="btn btn-primary-custom fw-bold" type="submit" <?= !empty($config['video']) && !$abstracts ? 'disabled' : '' ?>>
                        <?= h($current ? t('파일 교체', 'Replace file') : t('제출', 'Submit')) ?>
                    </button>
                </form>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/lib/footer.php'; ?>
