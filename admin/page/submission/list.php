<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
$year = ksers_admin_year($pdo);
$page_title = t('참가자 제출 파일 - ' . $year . ' 국제학술대회 관리자', 'Participant files - ' . $year . ' Conference Admin');
$page_heading = t('참가자 제출 파일', 'Participant files');
$kinds = ksers_submission_material_kinds();
$filter = isset($_GET['kind']) ? (string)$_GET['kind'] : 'all';
if ($filter !== 'all' && !in_array($filter, $kinds, true)) {
    $filter = 'all';
}
$selectedKinds = $filter === 'all' ? $kinds : [$filter];

include '../../lib/header.php';
include '../../lib/menu.php';

$marks = implode(',', array_fill(0, count($selectedKinds), '?'));
$stmt = $pdo->prepare(
    "SELECT f.seq, f.kind, f.abstract_seq, f.original_name, f.file_size, f.mime_type, f.reg_date, f.update_date,
            m.email, m.name_ko, m.first_name_eng, m.last_name_eng, a.title AS abstract_title
     FROM member_submission_file f
     JOIN member_info m ON m.seq = f.member_seq
     LEFT JOIN abstract_info a ON a.seq = f.abstract_seq
     WHERE f.year = ? AND f.kind IN ($marks)
     ORDER BY f.seq DESC"
);
$stmt->execute(array_merge([$year], $selectedKinds));
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$downloadError = isset($_GET['error']) ? (string)$_GET['error'] : '';
?>

<?php if ($downloadError === 'missing') { ?>
<div class="alert alert-warning"><?= h(t('선택한 파일을 저장소에서 찾지 못했습니다.', 'The selected files were not found in storage.')) ?></div>
<?php } elseif ($downloadError === 'empty') { ?>
<div class="alert alert-warning"><?= h(t('다운로드할 파일을 선택해 주세요.', 'Choose files to download.')) ?></div>
<?php } ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="get" action="list.php" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1" for="storageKind"><?= h(t('구분', 'Type')) ?></label>
                <select class="form-select form-select-sm" id="storageKind" name="kind">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>><?= h(t('전체', 'All')) ?></option>
                    <?php foreach ($kinds as $kind) { ?>
                    <option value="<?= h($kind) ?>" <?= $filter === $kind ? 'selected' : '' ?>><?= h(ksers_submission_label($kind)) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
                <button class="btn btn-sm btn-primary-custom" type="submit"><?= h(t('검색', 'Search')) ?></button>
                <a class="btn btn-sm ksers-outline" href="list.php"><?= h(t('초기화', 'Reset')) ?></a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <form method="post" action="download.php" id="storageDownloadForm">
        <input type="hidden" name="kind" value="<?= h($filter) ?>">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center gap-2">
            <h5 class="m-0 fw-bold">
                <?= h($year . ' ' . t('참가자 제출 파일', 'participant files')) ?>
                <span class="text-muted fs-6"><?= count($rows) ?></span>
            </h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-primary-custom" id="storageDownload" type="submit" disabled><?= h(t('다운로드', 'Download')) ?></button>
                <button class="btn btn-sm btn-outline-success" id="storageExcel" type="button"><?= h(t('엑셀', 'Excel')) ?></button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 submission-table">
                <thead class="table-light">
                    <tr>
                        <th class="col-check">
                            <input class="form-check-input" type="checkbox" id="storageCheckAll" aria-label="<?= h(t('전체 선택', 'Select all')) ?>" <?= $rows ? '' : 'disabled' ?>>
                        </th>
                        <th><?= h(t('초록 접수번호', 'Abstract No.')) ?></th>
                        <th><?= h(t('종류', 'Type')) ?></th>
                        <th><?= h(t('제출자', 'Submitter')) ?></th>
                        <th><?= h(t('파일', 'File')) ?></th>
                        <th><?= h(t('용량', 'Size')) ?></th>
                        <th class="col-date"><?= h(t('제출일', 'Submitted')) ?></th>
                        <th class="col-date"><?= h(t('수정일', 'Updated')) ?></th>
                        <th><?= h(t('연결된 초록', 'Linked abstract')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows) { ?>
                    <tr><td colspan="9" class="text-center text-muted py-4"><?= h(t('제출된 파일이 없습니다.', 'No submitted files.')) ?></td></tr>
                    <?php } ?>
                    <?php foreach ($rows as $row) {
                        $bytes = (int)$row['file_size'];
                        $sizeLabel = $bytes >= 1048576
                            ? number_format(max(1, (int)round($bytes / 1048576))) . ' MB'
                            : ($bytes > 0 ? number_format(max(1, (int)round($bytes / 1024))) . ' KB' : '—');
                        $submittedAt = strtotime((string)$row['reg_date']);
                        $submittedLabel = $submittedAt ? date('Y-m-d H:i', $submittedAt) : '—';
                        $updatedAt = strtotime((string)$row['update_date']);
                        $updatedLabel = $updatedAt ? date('Y-m-d H:i', $updatedAt) : '—';
                        $receipt = (int)$row['abstract_seq'] > 0 ? sprintf('A%d-%04d', $year, (int)$row['abstract_seq']) : '';
                    ?>
                    <tr>
                        <td class="col-check">
                            <input class="form-check-input" type="checkbox" name="seq[]" value="<?= (int)$row['seq'] ?>" aria-label="<?= h($row['original_name']) ?>">
                        </td>
                        <td class="col-receipt"><?= $receipt !== '' ? h($receipt) : '—' ?><?php if ($row['abstract_title']) { ?><br><span class="receipt-title small text-muted" title="<?= h($row['abstract_title']) ?>"><?= h($row['abstract_title']) ?></span><?php } ?></td>
                        <td><?= h(ksers_submission_label($row['kind'])) ?></td>
                        <td><?= h($row['email']) ?></td>
                        <td>
                            <a href="/service/submission_file.php?seq=<?= (int)$row['seq'] ?>"><?= h($row['original_name']) ?></a>
                        </td>
                        <td><?= h($sizeLabel) ?></td>
                        <td class="col-date"><?= h($submittedLabel) ?></td>
                        <td class="col-date"><?= h($updatedLabel) ?></td>
                        <td>
                            <?php if ($receipt !== '') { ?>
                            <a class="btn btn-sm ksers-outline" href="/admin/page/abstract/list.php?<?= h(http_build_query(['no' => $receipt])) ?>"><?= h(t('연결된 초록', 'Linked abstract')) ?></a>
                            <?php } else { ?>
                            <button class="btn btn-sm ksers-outline" type="button" disabled><?= h(t('연결된 초록', 'Linked abstract')) ?></button>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </form>
</div>
<style>
    main .submission-table th,
    main .submission-table td {
        text-align: center;
    }
    main .submission-table th.col-check,
    main .submission-table td.col-check {
        min-width: 2.5rem;
        width: 2.5rem;
        max-width: 2.5rem;
    }
    main .submission-table th.col-date,
    main .submission-table td.col-date {
        min-width: 11rem;
    }
    main .submission-table .receipt-title {
        display: block;
        width: 14rem;
        max-width: 14rem;
        margin: 0 auto;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>
<script>
(function () {
    var form = document.getElementById("storageDownloadForm");
    var all = document.getElementById("storageCheckAll");
    var button = document.getElementById("storageDownload");
    if (!form || !button) {
        return;
    }
    var boxes = form.querySelectorAll('input[name="seq[]"]');
    function selectedCount() {
        var count = 0;
        boxes.forEach(function (box) {
            if (box.checked) {
                count += 1;
            }
        });
        return count;
    }
    function refresh() {
        var count = selectedCount();
        button.disabled = count === 0;
        if (!all) {
            return;
        }
        all.checked = boxes.length > 0 && count === boxes.length;
        all.indeterminate = count > 0 && count < boxes.length;
    }
    boxes.forEach(function (box) {
        box.addEventListener("change", refresh);
    });
    if (all) {
        all.addEventListener("change", function () {
            boxes.forEach(function (box) {
                box.checked = all.checked;
            });
            refresh();
        });
    }
    form.addEventListener("submit", function (event) {
        if (selectedCount() === 0) {
            event.preventDefault();
        }
    });
    var excel = document.getElementById("storageExcel");
    if (excel) {
        excel.addEventListener("click", function () {
            var params = new URLSearchParams();
            params.set("kind", "submission");
            var chosen = [];
            boxes.forEach(function (box) {
                if (box.checked) {
                    chosen.push(box.value);
                }
            });
            if (chosen.length) {
                chosen.forEach(function (seq) {
                    params.append("seq[]", seq);
                });
            } else {
                var current = new URLSearchParams(window.location.search);
                params.set("file_kind", current.get("kind") || "all");
            }
            window.location = "/admin/export.php?" + params.toString();
        });
    }
})();
</script>

<?php include '../../lib/footer.php'; ?>
