<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$year = ksers_admin_year($pdo);
$filterTitle = isset($_GET['title']) ? trim((string)$_GET['title']) : '';
$filterDate = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $filterDate = '';
}
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$result = ksers_news_search($pdo, $year, $filterTitle, $filterDate, $page);
$page = $result['page'];
$pages = $result['pages'];
$rows = $result['rows'];
$pageWindow = 10;
$windowStart = (int)(floor(($page - 1) / $pageWindow) * $pageWindow) + 1;
$windowEnd = min($windowStart + $pageWindow - 1, $pages);
$newsPageHref = function ($target) use ($filterTitle, $filterDate) {
    return ksers_news_list_url($filterTitle, $filterDate, $target);
};
$notice = isset($_SESSION['admin_news_notice']) ? (string)$_SESSION['admin_news_notice'] : '';
$noticeKind = (isset($_SESSION['admin_news_notice_kind']) && $_SESSION['admin_news_notice_kind'] === 'warn') ? 'warn' : 'ok';
unset($_SESSION['admin_news_notice'], $_SESSION['admin_news_notice_kind']);
$page_title = t('게시판', 'Board');
$page_heading = t('게시판', 'Board');

include '../../lib/header.php';
include '../../lib/menu.php';
?>
<style>
main .news-table { width: 100%; table-layout: fixed; }
main .news-table th.news-title,
main .news-table td.news-title { width: 58%; min-width: 0; }
main .news-table td.news-title span {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
main .news-table th.news-when,
main .news-table td.news-when { width: 9rem; min-width: 9rem; }
main .news-table th.news-views,
main .news-table td.news-views { width: 6rem; min-width: 6rem; }
main .news-table th.news-manage,
main .news-table td.news-manage { width: 16rem; min-width: 16rem; white-space: normal; }
.news-actions { display: flex; flex-wrap: wrap; gap: 6px; }
main .news-table tr.news-hidden td,
main .news-table tr.news-hidden td span,
main .news-table tr.news-hidden td a,
main .news-table tr.news-hidden td button {
    color: #8d8d8d !important;
    text-decoration: line-through;
}
</style>

<?php if ($notice !== '') { ?>
<div class="alert <?= $noticeKind === 'warn' ? 'alert-warning' : 'alert-success' ?> border-0 shadow-sm"><?= h($notice) ?></div>
<?php } ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="/admin/page/news/index.php">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="newsFilterTitle"><?= h(t('제목', 'Title')) ?></label>
                    <input class="form-control" id="newsFilterTitle" type="text" name="title" value="<?= h($filterTitle) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="newsFilterDate"><?= h(t('등록 날짜', 'Registration date')) ?></label>
                    <input class="form-control" id="newsFilterDate" type="date" name="date" value="<?= h($filterDate) ?>">
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button class="btn btn-primary-custom" type="submit"><?= h(t('조회', 'Search')) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="m-0 fw-bold"><?= h(t('게시글 리스트', 'Posts')) ?></h5>
        <a class="btn btn-sm btn-primary-custom" href="/admin/page/news/edit.php">+ <?= h(t('글등록', 'New post')) ?></a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0 news-table">
            <thead class="table-light">
                <tr>
                    <th class="news-title"><?= h(t('제목', 'Title')) ?></th>
                    <th class="news-when"><?= h(t('작성시간', 'Written')) ?></th>
                    <th class="news-views"><?= h(t('조회수', 'Views')) ?></th>
                    <th class="news-manage"><?= h(t('관리', 'Manage')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows) { ?>
                <tr>
                    <td colspan="4" class="text-center text-muted py-4"><?= h(t('등록된 글이 없습니다.', 'No posts yet.')) ?></td>
                </tr>
                <?php } ?>
                <?php foreach ($rows as $row) {
                    $hidden = (int)$row['is_hidden'] === 1;
                ?>
                <tr<?= $hidden ? ' class="news-hidden"' : '' ?>>
                    <td class="news-title"><span title="<?= h($row['title']) ?>"><?= h($row['title']) ?></span></td>
                    <td class="news-when"><?= h($row['written_at']) ?></td>
                    <td class="news-views"><?= (int)$row['view_count'] ?></td>
                    <td class="news-manage">
                        <div class="news-actions">
                            <a class="btn btn-sm btn-outline-primary" href="/admin/page/news/edit.php?seq=<?= (int)$row['seq'] ?>"><?= h(t('수정', 'Edit')) ?></a>
                            <form method="post" action="action.php" class="d-inline" onsubmit="return confirm(<?= h(json_encode(t('이 글을 삭제할까요?', 'Delete this post?'))) ?>);">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="seq" value="<?= (int)$row['seq'] ?>">
                                <input type="hidden" name="filter_title" value="<?= h($filterTitle) ?>">
                                <input type="hidden" name="filter_date" value="<?= h($filterDate) ?>">
                                <input type="hidden" name="page" value="<?= (int)$page ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><?= h(t('삭제', 'Delete')) ?></button>
                            </form>
                            <form method="post" action="action.php" class="d-inline">
                                <input type="hidden" name="action" value="visibility">
                                <input type="hidden" name="seq" value="<?= (int)$row['seq'] ?>">
                                <input type="hidden" name="hidden" value="<?= $hidden ? '0' : '1' ?>">
                                <input type="hidden" name="filter_title" value="<?= h($filterTitle) ?>">
                                <input type="hidden" name="filter_date" value="<?= h($filterDate) ?>">
                                <input type="hidden" name="page" value="<?= (int)$page ?>">
                                <button class="btn btn-sm btn-outline-secondary" type="submit"><?= h($hidden ? t('표출', 'Show') : t('미표출', 'Hide')) ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-3">
        <nav aria-label="<?= h(t('게시판 페이지', 'Board pages')) ?>">
            <ul class="pagination pagination-sm justify-content-center m-0">
                <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                    <?php if ($page <= 1) { ?>
                    <span class="page-link"><?= h(t('이전', 'Previous')) ?></span>
                    <?php } else { ?>
                    <a class="page-link" href="<?= h($newsPageHref($page - 1)) ?>"><?= h(t('이전', 'Previous')) ?></a>
                    <?php } ?>
                </li>
                <?php for ($i = $windowStart; $i <= $windowEnd; $i++) { ?>
                <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                    <a class="page-link" href="<?= h($newsPageHref($i)) ?>"><?= $i ?></a>
                </li>
                <?php } ?>
                <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
                    <?php if ($page >= $pages) { ?>
                    <span class="page-link"><?= h(t('다음', 'Next')) ?></span>
                    <?php } else { ?>
                    <a class="page-link" href="<?= h($newsPageHref($page + 1)) ?>"><?= h(t('다음', 'Next')) ?></a>
                    <?php } ?>
                </li>
            </ul>
        </nav>
    </div>
</div>

<?php include '../../lib/footer.php'; ?>
