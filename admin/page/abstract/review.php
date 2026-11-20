<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
$page_title = t('초록 심사 및 영상 확인 - 2027 국제학술대회 관리자', 'Abstract review and videos - 2027 Conference Admin');
$page_heading = t('초록', 'Abstracts');
$year = ksers_admin_year($pdo);
$statusLabel = [];
$statusChoices = [];
foreach (ksers_abstract_review_statuses() as $code => $reviewStatus) {
    $adminLabel = t($reviewStatus['admin'][0], $reviewStatus['admin'][1]);
    $statusLabel[$code] = $adminLabel;
    if ($code === 'accepted') {
        $statusChoices[$code] = $adminLabel . ' (' . t('채택', 'Accepted') . ')';
    } elseif ($code === 'rejected') {
        $statusChoices[$code] = $adminLabel . ' (' . t('불채택', 'Not accepted') . ')';
    } else {
        $statusChoices[$code] = $adminLabel;
    }
}
$statusFilter = [];
foreach ($statusLabel as $code => $label) {
    if ($code === 'accepted' || $code === 'rejected') {
        $statusFilter['result'] = $label;
        continue;
    }
    $statusFilter[$code] = $label;
}

$no = isset($_GET['no']) ? trim((string)$_GET['no']) : '';
$submitter = isset($_GET['submitter']) ? trim((string)$_GET['submitter']) : '';
$video = isset($_GET['video']) ? (string)$_GET['video'] : '';
$status = isset($_GET['status']) ? (string)$_GET['status'] : '';
if ($video !== 'Y' && $video !== 'N') {
    $video = '';
}
if (!isset($statusFilter[$status])) {
    $status = '';
}

$where = ['a.year = ?'];
$params = [$year];
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
if ($submitter !== '') {
    $where[] = 'm.email LIKE ?';
    $params[] = '%' . $submitter . '%';
}
if ($video === 'Y') {
    $where[] = "EXISTS (SELECT 1 FROM member_submission_file v WHERE v.abstract_seq = a.seq AND v.year = a.year AND v.kind IN ('abstract_video_1', 'abstract_video_2'))";
} elseif ($video === 'N') {
    $where[] = "NOT EXISTS (SELECT 1 FROM member_submission_file v WHERE v.abstract_seq = a.seq AND v.year = a.year AND v.kind IN ('abstract_video_1', 'abstract_video_2'))";
}
if ($status === 'result') {
    $where[] = "a.status IN ('accepted', 'rejected')";
} elseif ($status !== '') {
    $where[] = 'a.status = ?';
    $params[] = $status;
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
$perPage = 20;
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
    "SELECT a.seq, a.status, m.email,
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
    'no' => $no,
    'submitter' => $submitter,
    'video' => $video,
    'status' => $status,
];
$pageHref = function ($target) use ($filterQuery) {
    $query = $filterQuery;
    $query['page'] = (int)$target;
    return 'review.php?' . http_build_query($query);
};
$linksByAbstract = [];
if ($rows) {
    $ids = [];
    foreach ($rows as $row) {
        $ids[] = (int)$row['seq'];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $linkStmt = $pdo->prepare(
        "SELECT abstract_seq, token FROM abstract_video_link WHERE year = ? AND abstract_seq IN ($placeholders) ORDER BY seq"
    );
    $linkStmt->execute(array_merge([$year], $ids));
    foreach ($linkStmt as $link) {
        $linksByAbstract[(int)$link['abstract_seq']][] = (string)$link['token'];
    }
}
$publicBase = rtrim(ksers_public_base(), '/');
$linkUrl = function ($token) use ($publicBase) {
    return $publicBase . '/service/video_link.php?token=' . $token;
};

include '../../lib/header.php';
include '../../lib/menu.php';
$abstractTab = 'review';
include 'tabs.php';
?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="get" action="review.php" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1" for="filterNo"><?= h(t('접수번호', 'Receipt No.')) ?></label>
                <input class="form-control form-control-sm" id="filterNo" name="no" value="<?= h($no) ?>" placeholder="A<?= (int)$year ?>-0001">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1" for="filterSubmitter"><?= h(t('제출자', 'Submitter')) ?></label>
                <input class="form-control form-control-sm" id="filterSubmitter" name="submitter" value="<?= h($submitter) ?>" placeholder="<?= h(t('계정', 'Account')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1" for="filterVideo"><?= h(t('영상 여부', 'Video')) ?></label>
                <select class="form-select form-select-sm" id="filterVideo" name="video">
                    <option value=""><?= h(t('전체', 'All')) ?></option>
                    <option value="Y" <?= $video === 'Y' ? 'selected' : '' ?>>Y</option>
                    <option value="N" <?= $video === 'N' ? 'selected' : '' ?>>N</option>
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
            <div class="col-12 d-flex justify-content-end gap-2">
                <button class="btn btn-sm btn-primary-custom" type="submit"><?= h(t('검색', 'Search')) ?></button>
                <a class="btn btn-sm ksers-outline" href="review.php"><?= h(t('초기화', 'Reset')) ?></a>
            </div>
        </form>
    </div>
</div>

<form method="post" action="review_action.php" id="reviewForm">
    <input type="hidden" name="action" id="reviewAction" value="">
    <input type="hidden" name="filter_no" value="<?= h($no) ?>">
    <input type="hidden" name="filter_submitter" value="<?= h($submitter) ?>">
    <input type="hidden" name="filter_video" value="<?= h($video) ?>">
    <input type="hidden" name="filter_status" value="<?= h($status) ?>">
    <input type="hidden" name="filter_page" value="<?= (int)$page ?>">
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h5 class="m-0 fw-bold d-flex align-items-baseline gap-2"><?= h(t('심사 결과 변경', 'Change review result')) ?> <span class="fw-normal text-secondary" style="font-size:0.8rem"><?= h(t('변경할 테이블을 선택 후 저장버튼을 누르세요.', 'Select the rows to change, then press Save.')) ?></span></h5>
        </div>
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-1" for="bulkStatus"><?= h(t('심사 상태', 'Review status')) ?></label>
                    <select class="form-select form-select-sm" id="bulkStatus" name="status">
                        <option value=""><?= h(t('상태를 선택해 주세요', 'Choose a status')) ?></option>
                        <?php foreach ($statusChoices as $code => $label) { ?>
                        <option value="<?= h($code) ?>"><?= h($label) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-sm btn-primary-custom" type="button" id="reviewSave"><?= h(t('저장', 'Save')) ?></button>
                </div>
            </div>
        </div>
    </div>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h5 class="m-0 fw-bold d-flex align-items-baseline gap-2"><?= h(t('심사 영상 링크 생성', 'Review video links')) ?> <span class="fw-normal text-secondary" style="font-size:0.8rem"><?= h(t('테이블에서 체크박스를 선택한 뒤 버튼을 누르세요.', 'Select the checkboxes in the table, then press a button.')) ?></span></h5>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-sm ksers-outline" type="submit" data-review-action="link_create"><?= h(t('링크 생성', 'Create links')) ?></button>
                <button class="btn btn-sm ksers-outline" type="submit" data-review-action="link_end"><?= h(t('링크 종료', 'Close links')) ?></button>
                <button class="btn btn-sm btn-outline-danger" type="submit" data-review-action="link_end_all"><?= h(t('전체 링크 종료', 'Close all links')) ?></button>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <?php if (!empty($_SESSION['admin_notice'])) { ?>
        <div class="alert alert-info border-0 shadow-sm mb-0"><?= h($_SESSION['admin_notice']) ?></div>
        <?php unset($_SESSION['admin_notice']); } ?>
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="m-0 fw-bold"><?= h(t('초록 심사 및 영상 확인', 'Abstract review and videos')) ?> <span class="text-muted fs-6"><?= $total ?></span></h5>
            <button class="btn btn-sm btn-outline-success" type="button" id="reviewExcel"><?= h(t('엑셀', 'Excel')) ?></button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 review-table">
                <thead class="table-light">
                    <tr>
                        <th class="col-check">
                            <input class="form-check-input" type="checkbox" id="reviewCheckAll" aria-label="<?= h(t('전체 선택', 'Select all')) ?>" <?= $rows ? '' : 'disabled' ?>>
                        </th>
                        <th><?= h(t('접수번호', 'Receipt No.')) ?></th>
                        <th><?= h(t('제출자', 'Submitter')) ?></th>
                        <th class="col-center"><?= h(t('영상 여부', 'Video')) ?></th>
                        <th class="col-center"><?= h(t('심사 상태', 'Review status')) ?></th>
                        <th class="col-center"><?= h(t('심사용 영상 링크', 'Review video link')) ?></th>
                        <th class="col-center"><?= h(t('초록 조회', 'Abstracts')) ?></th>
                        <th class="col-center"><?= h(t('영상 링크', 'Video link')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows) { ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><?= h(t('접수된 초록이 없습니다.', 'No abstracts yet.')) ?></td></tr>
                    <?php } ?>
                    <?php foreach ($rows as $row) {
                        $seq = (int)$row['seq'];
                        $receipt = sprintf('A%d-%04d', $year, $seq);
                        $tokens = isset($linksByAbstract[$seq]) ? $linksByAbstract[$seq] : [];
                        $statusText = isset($statusChoices[$row['status']]) ? $statusChoices[$row['status']] : $row['status'];
                        $listStatus = ($row['status'] === 'accepted' || $row['status'] === 'rejected') ? 'result' : $row['status'];
                        $lookup = '/admin/page/abstract/list.php?' . http_build_query([
                            'no' => $receipt,
                            'submitter' => $row['email'],
                            'status' => $listStatus,
                        ]);
                    ?>
                    <tr>
                        <td class="col-check"><input class="form-check-input review-check" type="checkbox" name="abs[]" value="<?= $seq ?>"></td>
                        <td><?= h($receipt) ?></td>
                        <td><?= h($row['email']) ?></td>
                        <td class="col-center"><?= (int)$row['video_count'] > 0 ? 'Y' : 'N' ?></td>
                        <td class="col-center"><?= h($statusText) ?></td>
                        <td class="col-center"><?= $tokens ? 'Y' : 'N' ?></td>
                        <td class="col-center"><a class="btn btn-sm ksers-outline" href="<?= h($lookup) ?>"><?= h(t('초록 조회', 'Abstracts')) ?></a></td>
                        <td class="col-center">
                            <?php if ($tokens) { ?>
                            <button class="btn btn-sm btn-video-link" type="button" onclick="ksersOpenVideoLinks(<?= $seq ?>)"><?= h(t('영상 링크', 'Video link')) ?></button>
                            <?php } else { ?>
                            <button class="btn btn-sm btn-video-link" type="button" disabled><?= h(t('영상 링크', 'Video link')) ?></button>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white py-3">
            <nav aria-label="<?= h(t('초록 심사 페이지', 'Review pages')) ?>">
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
</form>

<div class="modal fade" id="reviewSaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= h(t('심사 상태 변경', 'Change review status')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body"><?= h(t('선택한 초록의 심사 상태를 변경할까요? 결과 메일은 보내지 않습니다.', 'Change the review status of the selected abstracts? No result email will be sent.')) ?></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(t('취소', 'Cancel')) ?></button>
                <button type="button" class="btn btn-primary" id="reviewSaveConfirm"><?= h(t('확인', 'OK')) ?></button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="reviewEndAllModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= h(t('전체 링크 종료', 'Close all links')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body"><?= h(t('살아있는 영상 링크를 모두 종료할까요? 종료된 링크로는 영상을 볼 수 없습니다.', 'Close every live video link? Closed links can no longer play the video.')) ?></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(t('취소', 'Cancel')) ?></button>
                <button type="button" class="btn btn-outline-danger" id="reviewEndAllConfirm"><?= h(t('확인', 'OK')) ?></button>
            </div>
        </div>
    </div>
</div>
<style>
    main .review-table th.col-check,
    main .review-table td.col-check {
        min-width: 2.5rem;
        width: 2.5rem;
        text-align: center;
    }
    main .review-table th.col-center,
    main .review-table td.col-center {
        text-align: center;
        min-width: 6.5rem;
    }
    .btn-video-link {
        color: #7c3aed;
        background: #fff;
        border: 1px solid #7c3aed;
    }
    .btn-video-link:hover,
    .btn-video-link:focus {
        color: #6d28d9;
        background: #f5f3ff;
        border-color: #6d28d9;
    }
    .btn-video-link:disabled {
        color: #c4b5fd;
        background: #fff;
        border-color: #c4b5fd;
    }
</style>
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
function ksersOpenVideoLinks(seq) {
    const width = 860;
    const height = 640;
    const left = Math.max(0, Math.round((window.screen.width - width) / 2));
    const top = Math.max(0, Math.round((window.screen.height - height) / 2));
    window.open(
        'links.php?seq=' + seq,
        'ksersVideoLinks',
        'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes'
    );
}
(function () {
    const form = document.getElementById('reviewForm');
    const actionInput = document.getElementById('reviewAction');
    const all = document.getElementById('reviewCheckAll');
    const boxes = document.querySelectorAll('.review-check');
    const needSelect = <?= json_encode(t('초록을 선택해 주세요.', 'Select an abstract.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const needStatus = <?= json_encode(t('심사 상태를 선택해 주세요.', 'Choose a review status.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const copied = <?= json_encode(t('복사됨', 'Copied'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    function selectedCount() {
        return document.querySelectorAll('.review-check:checked').length;
    }
    if (all) {
        all.addEventListener('change', function () {
            boxes.forEach(function (box) {
                box.checked = all.checked;
            });
        });
    }
    boxes.forEach(function (box) {
        box.addEventListener('change', function () {
            all.checked = boxes.length > 0 && selectedCount() === boxes.length;
        });
    });
    document.getElementById('reviewSave').addEventListener('click', function () {
        if (document.getElementById('bulkStatus').value === '') {
            window.alert(needStatus);
            return;
        }
        if (selectedCount() < 1) {
            window.alert(needSelect);
            return;
        }
        actionInput.value = 'status';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('reviewSaveModal')).show();
    });
    document.getElementById('reviewSaveConfirm').addEventListener('click', function () {
        form.submit();
    });
    document.querySelectorAll('[data-review-action]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            const next = button.getAttribute('data-review-action');
            if (next !== 'link_end_all' && selectedCount() < 1) {
                event.preventDefault();
                window.alert(needSelect);
                return;
            }
            if (next === 'link_end_all') {
                event.preventDefault();
                actionInput.value = next;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('reviewEndAllModal')).show();
                return;
            }
            actionInput.value = next;
        });
    });
    document.getElementById('reviewEndAllConfirm').addEventListener('click', function () {
        actionInput.value = 'link_end_all';
        form.submit();
    });
    document.getElementById('reviewExcel').addEventListener('click', function () {
        const params = new URLSearchParams();
        params.set('kind', 'review');
        const checked = document.querySelectorAll('.review-check:checked');
        if (checked.length) {
            checked.forEach(function (box) {
                params.append('abs[]', box.value);
            });
        } else {
            const current = new URLSearchParams(window.location.search);
            ['no', 'submitter', 'video', 'status'].forEach(function (key) {
                const value = current.get(key);
                if (value) {
                    params.set(key, value);
                }
            });
        }
        window.location = '/admin/export.php?' + params.toString();
    });
    document.querySelectorAll('[data-copy-url]').forEach(function (button) {
        button.addEventListener('click', function () {
            const url = button.getAttribute('data-copy-url');
            const previous = button.textContent;
            ksersCopyText(url).then(function () {
                button.textContent = copied;
                window.setTimeout(function () {
                    button.textContent = previous;
                }, 1200);
            });
        });
    });
})();
</script>

<?php include '../../lib/footer.php'; ?>
