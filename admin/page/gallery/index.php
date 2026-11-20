<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);
$year = ksers_admin_year($pdo);
$page_title = 'Gallery';
$page_heading = 'Gallery';
$rows = ksers_gallery_rows($pdo, $year);
$notice = isset($_SESSION['admin_gallery_notice']) ? (string)$_SESSION['admin_gallery_notice'] : '';
$noticeKind = (isset($_SESSION['admin_gallery_notice_kind']) && $_SESSION['admin_gallery_notice_kind'] === 'warn') ? 'warn' : 'ok';
unset($_SESSION['admin_gallery_notice'], $_SESSION['admin_gallery_notice_kind']);
$perPage = ksers_gallery_page_size();
$total = count($rows);
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
$pageRows = array_slice($rows, ($page - 1) * $perPage, $perPage);
$galleryPageHref = function ($target) {
    $target = (int)$target;
    return '/admin/page/gallery/index.php' . ($target > 1 ? '?page=' . $target : '');
};

include '../../lib/header.php';
include '../../lib/menu.php';
?>
<style>
.gallery-table th.check,
.gallery-table td.check { min-width: 2.75rem; width: 2.75rem; }
.gallery-table th.order,
.gallery-table td.order { min-width: 4.5rem; width: 4.5rem; }
.gallery-preview { display: inline-block; margin-top: 12px; border: 1px solid #d5dde3; border-radius: 8px; background: #fff; padding: 8px; }
.gallery-preview img { display: block; max-width: min(420px, 100%); max-height: 280px; object-fit: contain; }
.gallery-actions { display: flex; flex-wrap: wrap; gap: 6px; }
.gallery-filename {
    display: inline-block;
    max-width: 18rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    vertical-align: bottom;
}
</style>

<?php if ($notice !== '') { ?>
<div class="alert <?= $noticeKind === 'warn' ? 'alert-warning' : 'alert-success' ?> border-0 shadow-sm"><?= h($notice) ?></div>
<?php } ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="m-0 fw-bold">Gallery 추가</h5>
    </div>
    <div class="card-body">
        <form id="galleryAddForm" method="post" action="action.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <label class="form-label" for="galleryAddImage"><?= h(t('이미지 추가', 'Add image')) ?></label>
            <input class="form-control" id="galleryAddImage" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif">
            <div class="form-text"><?= h(t('JPG, PNG, WEBP, GIF. 10MB 이하. 저장하면 맨 위 1번이 되고, 그 순서가 갤러리 1페이지의 첫 사진입니다.', 'JPG, PNG, WEBP, or GIF. Up to 10MB. Saving places it at number 1, which is the first photo on gallery page 1.')) ?></div>
            <p class="text-danger small mb-0 mt-2" id="galleryAddHint" hidden></p>
            <div class="gallery-preview" id="galleryAddPreview" hidden>
                <img id="galleryAddPreviewImg" alt="">
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button class="btn btn-primary-custom" type="submit"><?= h(t('저장', 'Save')) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="m-0 fw-bold">Gallery 리스트</h5>
        <button class="btn btn-sm btn-outline-danger" type="button" id="galleryBulkDelete"><?= h(t('삭제', 'Delete')) ?></button>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0 gallery-table">
            <thead class="table-light">
                <tr>
                    <th class="check">
                        <input class="form-check-input" id="galleryCheckAll" type="checkbox" aria-label="<?= h(t('전체 선택', 'Select all')) ?>">
                    </th>
                    <th class="order"><?= h(t('순서', 'Order')) ?></th>
                    <th><?= h(t('파일명', 'File name')) ?></th>
                    <th><?= h(t('등록일자', 'Date added')) ?></th>
                    <th><?= h(t('관리', 'Manage')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$pageRows) { ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-4"><?= h(t('등록된 이미지가 없습니다.', 'No images yet.')) ?></td>
                </tr>
                <?php } ?>
                <?php foreach ($pageRows as $row) {
                    $registered = $row['reg_date'] !== '' ? date('Y-m-d H:i', strtotime($row['reg_date'])) : '';
                    $isFirst = (int)$row['sort_order'] === 1;
                    $isLast = (int)$row['sort_order'] === $total;
                ?>
                <tr>
                    <td class="check">
                        <input class="form-check-input gallery-check" type="checkbox" value="<?= (int)$row['seq'] ?>" aria-label="<?= h(t('선택', 'Select')) ?>">
                    </td>
                    <td class="order"><?= (int)$row['sort_order'] ?></td>
                    <td><span class="gallery-filename" title="<?= h($row['file_name']) ?>"><?= h($row['file_name']) ?></span></td>
                    <td><?= h($registered) ?></td>
                    <td>
                        <div class="gallery-actions">
                            <button class="btn btn-sm btn-outline-primary" type="button" onclick="ksersGalleryEdit('/admin/page/gallery/edit.php?seq=<?= (int)$row['seq'] ?>')"><?= h(t('수정', 'Edit')) ?></button>
                            <button class="btn btn-sm btn-outline-danger gallery-delete-one" type="button" data-seq="<?= (int)$row['seq'] ?>"><?= h(t('삭제', 'Delete')) ?></button>
                            <form method="post" action="action.php" class="d-inline">
                                <input type="hidden" name="action" value="move">
                                <input type="hidden" name="direction" value="up">
                                <input type="hidden" name="seq" value="<?= (int)$row['seq'] ?>">
                                <input type="hidden" name="page" value="<?= (int)$page ?>">
                                <button class="btn btn-sm btn-outline-secondary" type="submit" <?= $isFirst ? 'disabled' : '' ?>><?= h(t('위로', 'Up')) ?></button>
                            </form>
                            <form method="post" action="action.php" class="d-inline">
                                <input type="hidden" name="action" value="move">
                                <input type="hidden" name="direction" value="down">
                                <input type="hidden" name="seq" value="<?= (int)$row['seq'] ?>">
                                <input type="hidden" name="page" value="<?= (int)$page ?>">
                                <button class="btn btn-sm btn-outline-secondary" type="submit" <?= $isLast ? 'disabled' : '' ?>><?= h(t('아래로', 'Down')) ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-3">
        <nav aria-label="<?= h(t('갤러리 페이지', 'Gallery pages')) ?>">
            <ul class="pagination pagination-sm justify-content-center m-0">
                <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                    <?php if ($page <= 1) { ?>
                    <span class="page-link"><?= h(t('이전', 'Previous')) ?></span>
                    <?php } else { ?>
                    <a class="page-link" href="<?= h($galleryPageHref($page - 1)) ?>"><?= h(t('이전', 'Previous')) ?></a>
                    <?php } ?>
                </li>
                <?php for ($i = $windowStart; $i <= $windowEnd; $i++) { ?>
                <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                    <a class="page-link" href="<?= h($galleryPageHref($i)) ?>"><?= $i ?></a>
                </li>
                <?php } ?>
                <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
                    <?php if ($page >= $pages) { ?>
                    <span class="page-link"><?= h(t('다음', 'Next')) ?></span>
                    <?php } else { ?>
                    <a class="page-link" href="<?= h($galleryPageHref($page + 1)) ?>"><?= h(t('다음', 'Next')) ?></a>
                    <?php } ?>
                </li>
            </ul>
        </nav>
    </div>
</div>

<form id="galleryDeleteForm" method="post" action="action.php">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="page" value="<?= (int)$page ?>">
    <div id="galleryDeleteSeqs"></div>
</form>

<div class="modal fade" id="galleryDeleteModal" tabindex="-1" aria-labelledby="galleryDeleteTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="galleryDeleteTitle"><?= h(t('삭제 확인', 'Confirm delete')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body" id="galleryDeleteBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(t('취소', 'Cancel')) ?></button>
                <button type="button" class="btn btn-danger" id="galleryDeleteConfirm"><?= h(t('확인', 'Confirm')) ?></button>
            </div>
        </div>
    </div>
</div>

<?php $admin_page_script = __DIR__ . '/script.php'; ?>
<?php include '../../lib/footer.php'; ?>
