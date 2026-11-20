<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
$page_title = t('초록 조회 및 변경 - 2027 국제학술대회 관리자', 'Abstracts and changes - 2027 Conference Admin');
$page_heading = t('초록', 'Abstracts');
$year = ksers_admin_year($pdo);
$presentationLabel = [
    'oral' => 'Oral',
    'eposter' => 'E-Poster',
    'video' => 'Video',
    'mentor' => 'Mentor-Mentee Video',
    'poster' => t('포스터', 'Poster'),
    'either' => t('상관없음', 'No preference'),
];
$statusLabel = [];
foreach (ksers_abstract_review_statuses() as $code => $reviewStatus) {
    $statusLabel[$code] = t($reviewStatus['admin'][0], $reviewStatus['admin'][1]);
}
$finalTypeLabel = [
    'pending' => t('미정', 'Pending'),
    'oral' => 'Oral',
    'eposter' => 'E-Poster',
];
$statusFilter = [];
foreach ($statusLabel as $code => $label) {
    if ($code === 'accepted' || $code === 'rejected') {
        $statusFilter['result'] = $label;
        continue;
    }
    $statusFilter[$code] = $label;
}

$submitter = isset($_GET['submitter']) ? trim((string)$_GET['submitter']) : '';
$no = isset($_GET['no']) ? trim((string)$_GET['no']) : '';
$type = isset($_GET['type']) ? (string)$_GET['type'] : '';
$topic = isset($_GET['topic']) ? (string)$_GET['topic'] : '';
$country = isset($_GET['country']) ? (string)$_GET['country'] : '';
$final = isset($_GET['final']) ? (string)$_GET['final'] : '';
$status = isset($_GET['status']) ? (string)$_GET['status'] : '';
$mail = isset($_GET['mail']) ? (string)$_GET['mail'] : '';
if (!isset($presentationLabel[$type])) {
    $type = '';
}
if (!isset($finalTypeLabel[$final])) {
    $final = '';
}
if (!isset($statusFilter[$status])) {
    $status = '';
}
if ($mail !== 'Y' && $mail !== 'N') {
    $mail = '';
}

$topicStmt = $pdo->prepare("SELECT * FROM abstract_topic WHERE year = ? AND state = 1 ORDER BY sort_no, seq");
$topicStmt->execute([$year]);
$topicOptions = $topicStmt->fetchAll(PDO::FETCH_ASSOC);
$countryStmt = $pdo->prepare(
    "SELECT DISTINCT c.minr, c.name_ko, c.name_eng
     FROM (
        SELECT aa.country AS country_id
        FROM abstract_author aa
        JOIN abstract_info a ON a.seq = aa.abstract_seq
        WHERE a.year = ? AND aa.country > 0
        UNION
        SELECT a.author_country
        FROM abstract_info a
        WHERE a.year = ? AND a.author_country > 0
     ) src
     JOIN code c ON c.majr = 1 AND c.minr = src.country_id
     ORDER BY c.name_ko, c.minr"
);
$countryStmt->execute([$year, $year]);
$countryOptions = $countryStmt->fetchAll(PDO::FETCH_ASSOC);

$where = ['a.year = ?'];
$params = [$year];
if ($submitter !== '') {
    $where[] = 'm.email LIKE ?';
    $params[] = '%' . $submitter . '%';
}
if ($no !== '') {
    $seqFilter = 0;
    if (preg_match('/-(\d+)$/', $no, $match)) {
        $seqFilter = (int)$match[1];
    } elseif (ctype_digit($no)) {
        $seqFilter = (int)$no;
    }
    $where[] = 'a.seq = ?';
    $params[] = $seqFilter;
}
if ($type !== '') {
    $where[] = 'a.presentation_type = ?';
    $params[] = $type;
}
if ($topic !== '') {
    $where[] = 'a.topic = ?';
    $params[] = $topic;
}
if ($country !== '' && ctype_digit($country)) {
    $where[] = '(a.author_country = ? OR EXISTS (SELECT 1 FROM abstract_author aa WHERE aa.abstract_seq = a.seq AND aa.country = ?))';
    $params[] = (int)$country;
    $params[] = (int)$country;
}
if ($final !== '') {
    $where[] = 'a.final_type = ?';
    $params[] = $final;
}
if ($status === 'result') {
    $where[] = "a.status IN ('accepted', 'rejected')";
} elseif ($status !== '') {
    $where[] = 'a.status = ?';
    $params[] = $status;
}
if ($mail === 'Y') {
    $where[] = 'a.mail_sent = 1';
} elseif ($mail === 'N') {
    $where[] = '(a.mail_sent = 0 OR a.mail_sent IS NULL)';
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM abstract_info a
     JOIN member_info m ON m.seq = a.member_seq
     WHERE $whereSql"
);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$perPage = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$pages = $total > 0 ? (int)ceil($total / $perPage) : 1;
if ($page > $pages) {
    $page = $pages;
}
$pageWindow = 10;
$windowStart = (int)(floor(($page - 1) / $pageWindow) * $pageWindow) + 1;
$windowEnd = min($windowStart + $pageWindow - 1, $pages);
$offset = ($page - 1) * $perPage;

$listStmt = $pdo->prepare(
    "SELECT a.seq, a.title, a.presentation_type, a.topic, a.status, a.file_name,
            m.email, m.name_ko, m.first_name_eng, m.last_name_eng,
            (SELECT COUNT(*) FROM member_submission_file v
              WHERE v.abstract_seq = a.seq AND v.year = a.year
                AND v.kind IN ('abstract_video_1', 'abstract_video_2')) AS video_count
     FROM abstract_info a
     JOIN member_info m ON m.seq = a.member_seq
     WHERE $whereSql
     ORDER BY a.seq DESC
     LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
);
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$filterQuery = [
    'submitter' => $submitter,
    'no' => $no,
    'type' => $type,
    'topic' => $topic,
    'country' => $country,
    'final' => $final,
    'status' => $status,
    'mail' => $mail,
];
$pageHref = function ($target) use ($filterQuery) {
    $query = $filterQuery;
    $query['page'] = (int)$target;
    return 'list.php?' . http_build_query($query);
};

include '../../lib/header.php';
include '../../lib/menu.php';
$abstractTab = 'list';
include 'tabs.php';
?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="get" action="list.php" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1" for="filterSubmitter"><?= h(t('제출자', 'Submitter')) ?></label>
                <input class="form-control form-control-sm" id="filterSubmitter" name="submitter" value="<?= h($submitter) ?>" placeholder="<?= h(t('계정', 'Account')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1" for="filterNo"><?= h(t('번호', 'No.')) ?></label>
                <input class="form-control form-control-sm" id="filterNo" name="no" value="<?= h($no) ?>" placeholder="A<?= (int)$year ?>-0001">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1" for="filterType"><?= h(t('형태', 'Type')) ?></label>
                <select class="form-select form-select-sm" id="filterType" name="type">
                    <option value=""><?= h(t('전체', 'All')) ?></option>
                    <?php foreach ($presentationLabel as $code => $label) { ?>
                    <option value="<?= h($code) ?>" <?= $type === $code ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1" for="filterTopic"><?= h(t('분야', 'Topic')) ?></label>
                <select class="form-select form-select-sm" id="filterTopic" name="topic">
                    <option value=""><?= h(t('전체', 'All')) ?></option>
                    <?php foreach ($topicOptions as $topicRow) { ?>
                    <option value="<?= h($topicRow['name_ko']) ?>" <?= $topic === $topicRow['name_ko'] ? 'selected' : '' ?>><?= h(ksers_text($topicRow, 'name_ko', 'name_en')) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1" for="filterCountry"><?= h(t('국가', 'Country')) ?></label>
                <select class="form-select form-select-sm" id="filterCountry" name="country">
                    <option value=""><?= h(t('전체', 'All')) ?></option>
                    <?php foreach ($countryOptions as $countryRow) { ?>
                    <option value="<?= (int)$countryRow['minr'] ?>" <?= (string)$country === (string)$countryRow['minr'] ? 'selected' : '' ?>><?= h(code_name($countryRow)) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1" for="filterFinal"><?= h(t('최종 형태', 'Final type')) ?></label>
                <select class="form-select form-select-sm" id="filterFinal" name="final">
                    <option value=""><?= h(t('전체', 'All')) ?></option>
                    <?php foreach ($finalTypeLabel as $code => $label) { ?>
                    <option value="<?= h($code) ?>" <?= $final === $code ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1" for="filterStatus"><?= h(t('심사 상태', 'Review status')) ?></label>
                <select class="form-select form-select-sm" id="filterStatus" name="status">
                    <option value=""><?= h(t('전체', 'All')) ?></option>
                    <?php foreach ($statusFilter as $code => $label) { ?>
                    <option value="<?= h($code) ?>" <?= $status === $code ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1" for="filterMail"><?= h(t('결과 메일', 'Result mail')) ?></label>
                <select class="form-select form-select-sm" id="filterMail" name="mail">
                    <option value=""><?= h(t('전체', 'All')) ?></option>
                    <option value="Y" <?= $mail === 'Y' ? 'selected' : '' ?>>Y</option>
                    <option value="N" <?= $mail === 'N' ? 'selected' : '' ?>>N</option>
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
    <?php if (!empty($_SESSION['admin_notice'])) { ?>
    <div class="alert alert-info border-0 shadow-sm mb-0"><?= h($_SESSION['admin_notice']) ?></div>
    <?php unset($_SESSION['admin_notice']); } ?>
    <form method="post" action="download.php" id="abstractDownloadForm">
        <?php foreach (['submitter', 'no', 'type', 'topic', 'country', 'final', 'status', 'mail'] as $filterKey) { ?>
        <input type="hidden" name="<?= h($filterKey) ?>" value="<?= h($filterQuery[$filterKey]) ?>">
        <?php } ?>
        <input type="hidden" name="page" value="<?= (int)$page ?>">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <h5 class="m-0 fw-bold"><?= h($year . ' ' . t('초록', 'abstracts')) ?> <span class="text-muted fs-6"><?= $total ?></span></h5>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-sm btn-outline-success" id="abstractExcel" type="button"><?= h(t('엑셀', 'Excel')) ?></button>
            <button class="btn btn-sm btn-primary-custom" type="submit" id="abstractFileDownload" data-label="<?= h(t('선택 초록 다운로드', 'Download selected abstracts')) ?>"><?= h(t('선택 초록 다운로드', 'Download selected abstracts')) ?></button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0 abstract-admin-table">
            <thead class="table-light">
                <tr>
                    <th class="col-check">
                        <input class="form-check-input" type="checkbox" id="abstractCheckAll" aria-label="<?= h(t('전체 선택', 'Select all')) ?>" <?= $rows ? '' : 'disabled' ?>>
                    </th>
                    <th><?= h(t('접수번호', 'Receipt No.')) ?></th>
                    <th><?= h(t('아이디', 'ID')) ?></th>
                    <th><?= h(t('이름', 'Name')) ?></th>
                    <th><?= h(t('발표 희망 형태', 'Preferred type')) ?></th>
                    <th><?= h(t('분야', 'Topic')) ?></th>
                    <th class="col-title"><?= h(t('제목', 'Title')) ?></th>
                    <th class="col-yn"><?= h(t('영상', 'Video')) ?></th>
                    <th class="col-yn"><?= h(t('초록파일', 'Abstract file')) ?></th>
                    <th><?= h(t('심사 상태', 'Review status')) ?></th>
                    <th><?= h(t('자세히 보기', 'Details')) ?></th>
                    <th><?= h(t('초록 철회', 'Withdraw abstract')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows) { ?>
                <tr><td colspan="12" class="text-center text-muted py-4"><?= h(t('접수된 초록이 없습니다.', 'No abstracts yet.')) ?></td></tr>
                <?php } ?>
                <?php foreach ($rows as $row) {
                    $typeText = isset($presentationLabel[$row['presentation_type']]) ? $presentationLabel[$row['presentation_type']] : $row['presentation_type'];
                    $statusText = isset($statusLabel[$row['status']]) ? $statusLabel[$row['status']] : $row['status'];
                    $receiptNo = sprintf('A%d-%04d', $year, (int)$row['seq']);
                    $withdrawn = (string)$row['status'] === 'withdrawn';
                    $submitterName = ksers_member_preferred_name($row);
                ?>
                <tr>
                    <td class="col-check">
                        <input class="form-check-input" type="checkbox" name="seq[]" value="<?= (int)$row['seq'] ?>" aria-label="<?= h($receiptNo) ?>">
                    </td>
                    <td><?= h($receiptNo) ?></td>
                    <td><?= h($row['email']) ?></td>
                    <td><?= h($submitterName) ?></td>
                    <td><?= h($typeText) ?></td>
                    <td><?= h($row['topic']) ?></td>
                    <td class="col-title"><span title="<?= h($row['title']) ?>"><?= h($row['title']) ?></span></td>
                    <td class="col-yn"><?= (int)$row['video_count'] > 0 ? 'Y' : 'N' ?></td>
                    <td class="col-yn"><?= trim((string)$row['file_name']) !== '' ? 'Y' : 'N' ?></td>
                    <td><?= h($statusText) ?></td>
                    <td><button class="btn btn-sm ksers-outline" type="button" onclick="ksersOpenAbstractDetail(<?= (int)$row['seq'] ?>)"><?= h(t('자세히 보기', 'Details')) ?></button></td>
                    <td>
                        <?php if ($withdrawn) { ?>
                        <button class="btn btn-sm btn-outline-secondary" type="button" disabled><?= h(t('철회됨', 'Withdrawn')) ?></button>
                        <?php } else { ?>
                        <button class="btn btn-sm btn-outline-danger" type="button" onclick="ksersOpenAbstractWithdraw(<?= (int)$row['seq'] ?>, <?= h(json_encode($receiptNo, JSON_UNESCAPED_UNICODE)) ?>)"><?= h(t('초록 철회', 'Withdraw')) ?></button>
                        <?php } ?>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    </form>
    <div class="card-footer bg-white py-3">
        <nav aria-label="<?= h(t('초록 페이지', 'Abstract pages')) ?>">
            <ul class="pagination pagination-sm justify-content-center m-0">
                <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                    <?php if ($page <= 1) { ?>
                    <span class="page-link"><?= h(t('이전', 'Previous')) ?></span>
                    <?php } else { ?>
                    <a class="page-link" href="<?= h($pageHref($page - 1)) ?>"><?= h(t('이전', 'Previous')) ?></a>
                    <?php } ?>
                </li>
                <?php for ($i = $windowStart; $i <= $windowEnd; $i++) { ?>
                <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                    <a class="page-link" href="<?= h($pageHref($i)) ?>"><?= $i ?></a>
                </li>
                <?php } ?>
                <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
                    <?php if ($page >= $pages) { ?>
                    <span class="page-link"><?= h(t('다음', 'Next')) ?></span>
                    <?php } else { ?>
                    <a class="page-link" href="<?= h($pageHref($page + 1)) ?>"><?= h(t('다음', 'Next')) ?></a>
                    <?php } ?>
                </li>
            </ul>
        </nav>
    </div>
</div>
<div class="modal fade" id="abstractWithdrawModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="withdraw.php">
            <div class="modal-header">
                <h5 class="modal-title"><?= h(t('초록 철회', 'Withdraw abstract')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><?= h(t('접수번호', 'Receipt No.')) ?> <strong id="withdrawReceipt"></strong> <?= h(t('초록을 철회할까요?', 'Withdraw this abstract?')) ?></p>
                <p class="mb-0"><?= h(t('철회하면 심사 상태가 철회로 바뀝니다. 제출자, 발표자, 교신저자에게 철회 안내 메일이 자동으로 발송됩니다.', 'The review status will change to withdrawn. A withdrawal notice will be sent automatically to the submitter, presenting author, and corresponding author.')) ?></p>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="seq" id="withdrawSeq" value="">
                <input type="hidden" name="return" value="<?= h(http_build_query($filterQuery + ['page' => $page])) ?>">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(t('취소', 'Cancel')) ?></button>
                <button class="btn btn-danger" type="submit"><?= h(t('철회', 'Withdraw')) ?></button>
            </div>
        </form>
    </div>
</div>
<script>
function ksersOpenAbstractWithdraw(seq, receipt) {
    const modal = document.getElementById('abstractWithdrawModal');
    const seqField = document.getElementById('withdrawSeq');
    const receiptField = document.getElementById('withdrawReceipt');
    if (!modal || !seqField || !receiptField || !window.bootstrap) {
        return;
    }
    seqField.value = String(seq);
    receiptField.textContent = receipt;
    window.bootstrap.Modal.getOrCreateInstance(modal).show();
}
function ksersOpenAbstractDetail(seq) {
    const width = 768;
    const height = 900;
    const left = Math.max(0, Math.round((window.screen.width - width) / 2));
    const top = Math.max(0, Math.round((window.screen.height - height) / 2));
    window.open(
        'view.php?seq=' + seq,
        'ksersAbstractAdmin',
        'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes'
    );
}
(function () {
    var form = document.getElementById('abstractDownloadForm');
    var all = document.getElementById('abstractCheckAll');
    var button = document.getElementById('abstractFileDownload');
    if (!form || !button) {
        return;
    }
    var boxes = form.querySelectorAll('input[name="seq[]"]');
    var baseLabel = button.getAttribute('data-label') || button.textContent;
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
        button.textContent = count > 0 ? baseLabel + ' (' + count + ')' : baseLabel;
        if (!all) {
            return;
        }
        all.checked = boxes.length > 0 && count === boxes.length;
        all.indeterminate = count > 0 && count < boxes.length;
    }
    boxes.forEach(function (box) {
        box.addEventListener('change', refresh);
    });
    if (all) {
        all.addEventListener('change', function () {
            boxes.forEach(function (box) {
                box.checked = all.checked;
            });
            refresh();
        });
    }
    var excel = document.getElementById('abstractExcel');
    if (excel) {
        excel.addEventListener('click', function () {
            var params = new URLSearchParams();
            params.set('kind', 'abstract');
            boxes.forEach(function (box) {
                if (box.checked) {
                    params.append('abs[]', box.value);
                }
            });
            window.location = '/admin/export.php?' + params.toString();
        });
    }
    refresh();
})();
</script>
<style>
    main .abstract-admin-table th.col-check,
    main .abstract-admin-table td.col-check {
        width: 40px;
        min-width: 40px;
        max-width: 40px;
        padding: 0;
        text-align: center;
        vertical-align: middle;
    }
    main .abstract-admin-table .col-check .form-check-input {
        margin: 0;
        float: none;
        vertical-align: middle;
    }
    main .abstract-admin-table th.col-yn,
    main .abstract-admin-table td.col-yn {
        width: 4.5rem;
        padding-left: 0.35rem;
        padding-right: 0.35rem;
        text-align: center;
        white-space: nowrap;
    }
    main .abstract-admin-table th.col-title,
    main .abstract-admin-table td.col-title {
        width: 28rem;
        max-width: 28rem;
    }
    main .abstract-admin-table td.col-title span {
        display: inline-block;
        max-width: 28rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: bottom;
    }
</style>

<?php include '../../lib/footer.php'; ?>
