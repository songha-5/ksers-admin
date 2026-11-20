<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);
$year = ksers_admin_year($pdo);
$page_title = t('프로그램·연자', 'Program & speakers');
$page_heading = t('프로그램·연자', 'Program & speakers');

$stmt = $pdo->prepare(
    "SELECT s.*,
            (SELECT COUNT(*) FROM program_chair c WHERE c.session_seq = s.seq) AS chair_count,
            (SELECT COUNT(*) FROM program_talk t WHERE t.session_seq = s.seq) AS talk_count
     FROM program_session s
     WHERE s.year = ?
     ORDER BY s.session_date, s.start_time, s.sort_no, s.seq"
);
$stmt->execute([$year]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../lib/header.php';
include '../../lib/menu.php';
?>

<?php if (!empty($_SESSION['admin_notice'])) { ?>
<div class="alert alert-info border-0 shadow-sm"><?= h($_SESSION['admin_notice']) ?></div>
<?php unset($_SESSION['admin_notice']); } ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="m-0 fw-bold"><?= h($year . ' ' . t('세션', 'sessions')) ?> <span class="text-muted fs-6"><?= count($rows) ?></span></h5>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-primary-custom" href="/admin/page/program/edit.php"><?= h(t('세션 추가', 'Add session')) ?></a>
            <a class="btn btn-sm btn-outline-secondary" href="/admin/page/program/sample.csv"><?= h(t('CSV 양식', 'CSV template')) ?></a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= h(t('날짜', 'Date')) ?></th>
                    <th><?= h(t('시간', 'Time')) ?></th>
                    <th><?= h(t('룸', 'Room')) ?></th>
                    <th><?= h(t('세션', 'Session')) ?></th>
                    <th><?= h(t('좌장', 'Chairs')) ?></th>
                    <th><?= h(t('발표', 'Talks')) ?></th>
                    <th><?= h(t('공개', 'Public')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows) { ?>
                <tr><td colspan="8" class="text-center text-muted py-4"><?= h(t('등록된 세션이 없습니다. 세션을 추가하거나 CSV로 올려 주세요.', 'No sessions yet. Add a session or import a CSV.')) ?></td></tr>
                <?php } ?>
                <?php foreach ($rows as $row) { ?>
                <tr>
                    <td><?= h($row['session_date']) ?></td>
                    <td><?= h(substr($row['start_time'], 0, 5)) ?>–<?= h(substr($row['end_time'], 0, 5)) ?></td>
                    <td><?= h($row['room']) ?></td>
                    <td>
                        <div class="fw-semibold"><?= h($row['session_title']) ?></div>
                        <div class="text-muted small">#<?= h($row['session_id']) ?><?= $row['session_code'] !== '' ? ' · ' . h($row['session_code']) : '' ?><?= $row['category'] !== '' ? ' · ' . h($row['category']) : '' ?></div>
                    </td>
                    <td><?= (int)$row['chair_count'] ?></td>
                    <td><?= (int)$row['talk_count'] ?></td>
                    <td><?= (int)$row['is_public'] === 1 ? h(t('공개', 'Yes')) : h(t('숨김', 'Hidden')) ?></td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="/admin/page/program/edit.php?seq=<?= (int)$row['seq'] ?>"><?= h(t('편집', 'Edit')) ?></a>
                        <form method="post" action="action.php" class="d-inline" onsubmit="return confirm(<?= json_encode(t('이 세션과 연자·파일을 삭제할까요?', 'Delete this session, speakers, and files?'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
                            <input type="hidden" name="action" value="delete_session">
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

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3"><h5 class="m-0 fw-bold"><?= h(t('CSV 일괄 등록', 'CSV bulk import')) ?></h5></div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            <?= h(t('열: category, session_id, session_code, session_title, date, start, end, room, chair_name, chair_affiliation, talk_code, talk_title, speaker_name, speaker_affiliation, sort', 'Columns: category, session_id, session_code, session_title, date, start, end, room, chair_name, chair_affiliation, talk_code, talk_title, speaker_name, speaker_affiliation, sort')) ?>
        </p>
        <form method="post" action="action.php" enctype="multipart/form-data" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="import_csv">
            <div class="col-md-8">
                <label class="form-label"><?= h(t('CSV 파일', 'CSV file')) ?></label>
                <input class="form-control" type="file" name="csv_file" accept=".csv,text/csv" required>
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary-custom" type="submit"><?= h(t('CSV 올리기', 'Import CSV')) ?></button>
            </div>
        </form>
        <p class="text-muted small mt-3 mb-0"><?= h(t('사진·CV·Abstract 파일은 세션 편집 화면에서 발표별로 올립니다.', 'Upload photo, CV, and abstract files per talk on the session edit screen.')) ?></p>
    </div>
</div>

<?php include '../../lib/footer.php'; ?>
