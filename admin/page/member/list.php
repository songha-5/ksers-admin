<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
$year = ksers_admin_year($pdo);
$page_title = t('회원 - ' . $year . ' 국제학술대회 관리자', 'Members - ' . $year . ' Conference Admin');
$page_heading = t('회원', 'Members');

include '../../lib/header.php';
include '../../lib/menu.php';

$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
$memberSeq = isset($_GET['member']) ? (int)$_GET['member'] : 0;
$perPage = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$where = " WHERE m.year = ?";
$params = [$year];
if ($keyword !== '') {
    $where .= " AND (m.email LIKE ? OR m.name_ko LIKE ? OR m.first_name_eng LIKE ? OR m.last_name_eng LIKE ? OR m.affiliation_ko LIKE ? OR m.affiliation_eng LIKE ? OR m.department LIKE ? OR m.mobile LIKE ? OR m.telephone LIKE ? OR m.salutation LIKE ? OR c.name_ko LIKE ? OR c.name_eng LIKE ?)";
    $like = '%' . $keyword . '%';
    $params = array_merge($params, array_fill(0, 12, $like));
}
if ($memberSeq > 0) {
    $where .= " AND m.seq = ?";
    $params[] = $memberSeq;
}
$from = " FROM member_info m
        LEFT JOIN code c ON c.majr = 1 AND c.iso = m.country";
$count = $pdo->prepare("SELECT COUNT(*)" . $from . $where);
$count->execute($params);
$total = (int)$count->fetchColumn();
$pages = $total > 0 ? (int)ceil($total / $perPage) : 1;
if ($page > $pages) {
    $page = $pages;
}
$pageWindow = 10;
$windowStart = (int)(floor(($page - 1) / $pageWindow) * $pageWindow) + 1;
$windowEnd = min($windowStart + $pageWindow - 1, $pages);
$offset = ($page - 1) * $perPage;
$stmt = $pdo->prepare(
    "SELECT m.seq, m.email, m.name_ko, m.first_name_eng, m.last_name_eng, m.affiliation_ko, m.affiliation_eng,
            m.affiliation_type, m.department, m.mobile, m.telephone, m.salutation, m.agree_email, m.user_class, m.reg_date, m.is_admin,
            c.name_ko AS country_ko, c.name_eng AS country_en"
    . $from . $where . " ORDER BY m.seq DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
$affiliationTypes = ksers_affiliation_types();
$memberPageHref = function ($target) use ($keyword, $memberSeq) {
    $query = ['page' => $target];
    if ($keyword !== '') {
        $query['q'] = $keyword;
    }
    if ($memberSeq > 0) {
        $query['member'] = $memberSeq;
    }
    return 'list.php?' . http_build_query($query);
};
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="list.php">
            <div class="input-group">
                <input class="form-control" name="q" value="<?= h($keyword) ?>" placeholder="<?= h(t('이름, 소속, 이메일, 전화', 'Name, affiliation, email, or phone')) ?>">
                <button class="btn btn-primary-custom" type="submit"><?= h(t('검색', 'Search')) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="m-0 fw-bold"><?= h(t('회원 목록', 'Member list')) ?> <span class="text-muted fs-6"><?= $total ?></span></h5>
        <a class="btn btn-sm btn-outline-success" href="/admin/export.php?kind=member<?= $keyword !== '' ? ('&q=' . rawurlencode($keyword)) : '' ?><?= $memberSeq > 0 ? ('&member=' . $memberSeq) : '' ?>"><?= h(t('엑셀', 'Excel')) ?></a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0 text-nowrap">
            <thead class="table-light">
                <tr>
                    <th><?= h(t('회원 유형', 'Member class')) ?></th>
                    <th><?= h(t('국가', 'Country')) ?></th>
                    <th><?= h(t('이메일', 'Email')) ?></th>
                    <th><?= h(t('성명(국문)', 'Name (Korean)')) ?></th>
                    <th><?= h(t('이름(영문)', 'First name (English)')) ?></th>
                    <th><?= h(t('성(영문)', 'Last name (English)')) ?></th>
                    <th><?= h(t('호칭', 'Title')) ?></th>
                    <th><?= h(t('휴대전화', 'Cell Phone')) ?></th>
                    <th><?= h(t('유선전화', 'Telephone')) ?></th>
                    <th><?= h(t('소속 선택', 'Affiliation')) ?></th>
                    <th><?= h(t('소속(국문)', 'Affiliation - In Korean')) ?></th>
                    <th><?= h(t('소속(영문)', 'Affiliation - In English')) ?></th>
                    <th><?= h(t('진료과/부서', 'Department')) ?></th>
                    <th><?= h(t('이메일 수신 동의', 'E-mail consent')) ?></th>
                    <th><?= h(t('가입일', 'Joined')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$members) { ?>
                <tr><td colspan="15" class="text-center text-muted py-4"><?= h(t('회원이 없습니다.', 'No members.')) ?></td></tr>
                <?php } ?>
                <?php foreach ($members as $member) {
                    $typeLabel = '';
                    if (isset($affiliationTypes[$member['affiliation_type']])) {
                        $typeLabel = t($affiliationTypes[$member['affiliation_type']][0], $affiliationTypes[$member['affiliation_type']][1]);
                    }
                ?>
                <tr>
                    <td><?= h(ksers_user_class_label($member['user_class'])) ?></td>
                    <td><?= h(code_name(['name_ko' => $member['country_ko'], 'name_eng' => $member['country_en']])) ?></td>
                    <td><?= h($member['email']) ?><?= (int)$member['is_admin'] === 1 ? ' '.h(t('(관리자)', '(Admin)')) : '' ?></td>
                    <td><?= h($member['name_ko']) ?></td>
                    <td><?= h($member['first_name_eng']) ?></td>
                    <td><?= h($member['last_name_eng']) ?></td>
                    <td><?= h($member['salutation']) ?></td>
                    <td><?= h($member['mobile']) ?></td>
                    <td><?= h($member['telephone']) ?></td>
                    <td><?= h($typeLabel) ?></td>
                    <td><?= h($member['affiliation_ko']) ?></td>
                    <td><?= h($member['affiliation_eng']) ?></td>
                    <td><?= h($member['department']) ?></td>
                    <td><?= h((int)$member['agree_email'] === 1 ? t('동의', 'Agreed') : t('미동의', 'Not agreed')) ?></td>
                    <td><?= h($member['reg_date']) ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-3">
        <nav aria-label="<?= h(t('회원 페이지', 'Member pages')) ?>">
            <ul class="pagination pagination-sm justify-content-center m-0">
                <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                    <?php if ($page <= 1) { ?>
                    <span class="page-link"><?= h(t('이전', 'Previous')) ?></span>
                    <?php } else { ?>
                    <a class="page-link" href="<?= h($memberPageHref($page - 1)) ?>"><?= h(t('이전', 'Previous')) ?></a>
                    <?php } ?>
                </li>
                <?php for ($i = $windowStart; $i <= $windowEnd; $i++) { ?>
                <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                    <a class="page-link" href="<?= h($memberPageHref($i)) ?>"><?= $i ?></a>
                </li>
                <?php } ?>
                <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
                    <?php if ($page >= $pages) { ?>
                    <span class="page-link"><?= h(t('다음', 'Next')) ?></span>
                    <?php } else { ?>
                    <a class="page-link" href="<?= h($memberPageHref($page + 1)) ?>"><?= h(t('다음', 'Next')) ?></a>
                    <?php } ?>
                </li>
            </ul>
        </nav>
    </div>
</div>

<?php include '../../lib/footer.php'; ?>
