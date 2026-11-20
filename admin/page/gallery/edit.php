<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);
$year = ksers_admin_year($pdo);
$seq = isset($_GET['seq']) ? (int)$_GET['seq'] : 0;
$row = $seq > 0 ? ksers_gallery_find($pdo, $year, $seq) : null;
$saved = isset($_GET['saved']) && (string)$_GET['saved'] === '1';
$notice = isset($_SESSION['admin_gallery_notice']) ? (string)$_SESSION['admin_gallery_notice'] : '';
$noticeKind = (isset($_SESSION['admin_gallery_notice_kind']) && $_SESSION['admin_gallery_notice_kind'] === 'warn') ? 'warn' : 'ok';
unset($_SESSION['admin_gallery_notice'], $_SESSION['admin_gallery_notice_kind']);
$page_title = t('이미지 수정', 'Replace image');
?>
<!DOCTYPE html>
<html lang="<?= h(ksers_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/service/ksers.css" rel="stylesheet">
    <style>
        .gallery-preview { display: inline-block; margin-top: 12px; border: 1px solid #d5dde3; border-radius: 8px; background: #fff; padding: 8px; }
        .gallery-preview img { display: block; max-width: 100%; max-height: 420px; object-fit: contain; }
    </style>
</head>
<body class="bg-light">
<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php if ($saved) { ?>
            <div class="alert alert-success border-0 shadow-sm"><?= h(t('이미지를 바꿨습니다.', 'Image replaced.')) ?></div>
            <?php } elseif ($notice !== '') { ?>
            <div class="alert <?= $noticeKind === 'warn' ? 'alert-warning' : 'alert-success' ?> border-0 shadow-sm"><?= h($notice) ?></div>
            <?php } ?>
            <?php if (!$row) { ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 fw-bold"><?= h($page_title) ?></h1>
                    <p class="mb-0"><?= h(t('이미지를 찾지 못했습니다.', 'The image was not found.')) ?></p>
                </div>
            </div>
            <?php } else { ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h1 class="h5 m-0 fw-bold"><?= h($page_title) ?></h1>
                </div>
                <div class="card-body">
                    <form id="galleryEditForm" method="post" action="action.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="replace">
                        <input type="hidden" name="popup" value="1">
                        <input type="hidden" name="seq" value="<?= (int)$row['seq'] ?>">
                        <div class="mb-2 text-muted small"><?= h(t('순서', 'Order')) ?> <?= (int)$row['sort_order'] ?> · <?= h($row['file_name']) ?></div>
                        <label class="form-label" for="galleryEditImage"><?= h(t('바꿀 이미지', 'Replacement image')) ?></label>
                        <input class="form-control" id="galleryEditImage" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif">
                        <div class="form-text"><?= h(t('이미지를 고르면 미리보기가 바뀝니다. 저장은 순서를 바꾸지 않고 파일만 교체합니다.', 'Choosing an image updates the preview. Saving replaces the file and keeps its order.')) ?></div>
                        <p class="text-danger small mb-0 mt-2" id="galleryEditHint" hidden></p>
                        <div class="gallery-preview" id="galleryEditPreview">
                            <img id="galleryEditPreviewImg" src="<?= h(ksers_gallery_file_href($row['seq'])) ?>" alt="<?= h($row['file_name']) ?>">
                        </div>
                        <div class="mt-3 d-flex gap-2">
                            <button class="btn btn-primary-custom" type="submit"><?= h(t('저장', 'Save')) ?></button>
                            <button class="btn btn-outline-secondary" type="button" onclick="window.close()"><?= h(t('닫기', 'Close')) ?></button>
                        </div>
                    </form>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</main>

<?php if ($row) { ?>
<div class="modal fade" id="gallerySaveModal" tabindex="-1" aria-labelledby="gallerySaveTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="gallerySaveTitle"><?= h(t('저장 확인', 'Confirm save')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('닫기', 'Close')) ?>"></button>
            </div>
            <div class="modal-body"><?= h(t('이 이미지로 저장할까요?', 'Save this image?')) ?></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(t('취소', 'Cancel')) ?></button>
                <button type="button" class="btn btn-primary-custom" id="gallerySaveConfirm"><?= h(t('확인', 'Confirm')) ?></button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var chooseText = <?= json_encode(t('이미지를 선택해 주세요.', 'Choose an image.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var form = document.getElementById('galleryEditForm');
    var input = document.getElementById('galleryEditImage');
    var hint = document.getElementById('galleryEditHint');
    var preview = document.getElementById('galleryEditPreviewImg');
    var currentSrc = preview.getAttribute('src');
    var currentAlt = preview.getAttribute('alt') || '';
    var modal = new bootstrap.Modal(document.getElementById('gallerySaveModal'));

    input.addEventListener('change', function () {
        hint.hidden = true;
        var file = input.files && input.files[0] ? input.files[0] : null;
        if (!file) {
            preview.src = currentSrc;
            preview.alt = currentAlt;
            return;
        }
        var reader = new FileReader();
        reader.onload = function () {
            preview.src = reader.result;
            preview.alt = file.name;
        };
        reader.readAsDataURL(file);
    });

    form.addEventListener('submit', function (event) {
        if (form.dataset.confirmed === '1') {
            return;
        }
        event.preventDefault();
        if (!input.files || !input.files.length) {
            hint.textContent = chooseText;
            hint.hidden = false;
            return;
        }
        modal.show();
    });

    document.getElementById('gallerySaveConfirm').addEventListener('click', function () {
        form.dataset.confirmed = '1';
        form.submit();
    });
})();
</script>
<?php } else { ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php } ?>
<?php if ($saved) { ?>
<script>
if (window.opener && !window.opener.closed) {
    window.opener.location.reload();
    window.close();
} else {
    window.location.replace('/admin/page/gallery/index.php');
}
</script>
<?php } ?>
</body>
</html>
