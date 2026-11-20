<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$year = ksers_admin_year($pdo);
$seq = isset($_GET['seq']) ? (int)$_GET['seq'] : 0;
$row = $seq > 0 ? ksers_news_find($pdo, $year, $seq) : null;
if ($seq > 0 && !$row) {
    $_SESSION['admin_news_notice'] = t('글을 찾지 못했습니다.', 'The post was not found.');
    $_SESSION['admin_news_notice_kind'] = 'warn';
    header('Location: /admin/page/news/index.php');
    exit;
}
$notice = isset($_SESSION['admin_news_notice']) ? (string)$_SESSION['admin_news_notice'] : '';
$noticeKind = (isset($_SESSION['admin_news_notice_kind']) && $_SESSION['admin_news_notice_kind'] === 'warn') ? 'warn' : 'ok';
unset($_SESSION['admin_news_notice'], $_SESSION['admin_news_notice_kind']);
$isEdit = (bool)$row;
$page_title = t('게시판', 'Board');
$page_heading = $page_title;
$sectionTitle = $isEdit ? t('글 수정', 'Edit post') : t('글등록', 'New post');
$body = $row ? (string)$row['body'] : '';
$titleValue = $row ? (string)$row['title'] : '';
if (!empty($_SESSION['admin_news_draft']) && is_array($_SESSION['admin_news_draft'])) {
    $draft = $_SESSION['admin_news_draft'];
    if ((int)(isset($draft['seq']) ? $draft['seq'] : 0) === ($row ? (int)$row['seq'] : 0)) {
        $titleValue = isset($draft['title']) ? (string)$draft['title'] : $titleValue;
        $body = ksers_news_sanitize(isset($draft['body']) ? (string)$draft['body'] : $body);
    }
}
unset($_SESSION['admin_news_draft']);
$invalid = (!empty($_SESSION['admin_news_invalid']) && is_array($_SESSION['admin_news_invalid'])) ? $_SESSION['admin_news_invalid'] : [];
unset($_SESSION['admin_news_invalid']);

include '../../lib/header.php';
include '../../lib/menu.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jodit@4.20.2/es2021/jodit.min.css">
<style>
.jodit-wysiwyg { font-family: Pretendard, sans-serif; font-size: 16px; }
</style>

<?php if ($notice !== '') { ?>
<div class="alert <?= $noticeKind === 'warn' ? 'alert-warning' : 'alert-success' ?> border-0 shadow-sm"><?= h($notice) ?></div>
<?php } ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="m-0 fw-bold"><?= h($sectionTitle) ?></h5>
        <a class="btn btn-sm btn-outline-secondary" href="/admin/page/news/index.php">← <?= h(t('뒤로가기', 'Back')) ?></a>
    </div>
    <div class="card-body">
        <form id="newsForm" method="post" action="action.php"
            data-upload-error="<?= h(t('파일을 올리지 못했습니다.', 'The file could not be uploaded.')) ?>"
            data-delete-confirm="<?= h(t('이 글을 삭제할까요?', 'Delete this post?')) ?>"
            data-video="<?= h(t('영상', 'Video')) ?>"
            data-file="<?= h(t('파일', 'File')) ?>">
            <input type="hidden" name="seq" value="<?= $row ? (int)$row['seq'] : 0 ?>">
            <div class="d-flex align-items-center gap-2 mb-2">
                <label class="form-label m-0" for="newsTitle"><?= h(t('제목', 'Title')) ?></label>
                <span class="text-danger small" id="newsTitleHint"<?= empty($invalid['title']) ? ' hidden' : '' ?>><?= h(t('제목을 입력해주세요', 'Enter a title.')) ?></span>
            </div>
            <input class="form-control" id="newsTitle" name="title" type="text" maxlength="300" value="<?= h($titleValue) ?>">
            <label class="form-label mt-4" for="newsEditor"><?= h(t('본문', 'Body')) ?></label>
            <div id="newsEditorHost">
                <textarea id="newsEditor" name="body"><?= h($body) ?></textarea>
            </div>
            <p class="text-danger small mb-0 mt-2" id="newsBodyHint"<?= empty($invalid['body']) ? ' hidden' : '' ?>><?= h(t('본문을 입력해주세요', 'Enter the body.')) ?></p>
            <p class="text-danger small mb-0 mt-2" id="newsUploadHint" hidden></p>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button class="btn btn-outline-secondary" type="submit" name="action" value="preview" formaction="preview.php" formtarget="_blank"><?= h(t('미리보기', 'Preview')) ?></button>
                <?php if ($isEdit) { ?>
                <button class="btn btn-outline-danger" type="submit" name="action" value="delete"><?= h(t('삭제', 'Delete')) ?></button>
                <button class="btn btn-primary-custom" type="submit" name="action" value="save"><?= h(t('수정', 'Save')) ?></button>
                <?php } else { ?>
                <button class="btn btn-primary-custom" type="submit" name="action" value="save"><?= h(t('등록', 'Publish')) ?></button>
                <?php } ?>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jodit@4.20.2/es2018/jodit.fat.min.js"></script>
<?php $admin_page_script = __DIR__ . '/script.php'; ?>
<?php include '../../lib/footer.php'; ?>
