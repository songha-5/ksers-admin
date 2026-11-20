<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);
$year = ksers_admin_year($pdo);
$page_title = t('대시보드', 'Dashboard');
$page_heading = t('대시보드', 'Dashboard');

$memberCount = $pdo->prepare("SELECT COUNT(*) FROM member_info WHERE year = ?");
$memberCount->execute([$year]);
$members = (int)$memberCount->fetchColumn();
$count = $pdo->prepare("SELECT COUNT(*) FROM participant_info WHERE year = ?");
$count->execute([$year]);
$registrations = (int)$count->fetchColumn();
$count = $pdo->prepare("SELECT COUNT(*) FROM payment_order WHERE year = ? AND status = 'paid'");
$count->execute([$year]);
$paid = (int)$count->fetchColumn();
$count = $pdo->prepare("SELECT COUNT(*) FROM payment_order WHERE year = ? AND status = 'pending'");
$count->execute([$year]);
$pending = (int)$count->fetchColumn();
$count = $pdo->prepare("SELECT COUNT(*) FROM abstract_info WHERE year = ?");
$count->execute([$year]);
$abstracts = (int)$count->fetchColumn();
$count = $pdo->prepare("SELECT COUNT(*) FROM abstract_info WHERE year = ? AND status IN ('accepted', 'rejected')");
$count->execute([$year]);
$reviewed = (int)$count->fetchColumn();
$count = $pdo->prepare("SELECT COUNT(*) FROM program_sessions WHERE year = ?");
$count->execute([$year]);
$glanceCells = (int)$count->fetchColumn();
$count = $pdo->prepare("SELECT COUNT(*) FROM scientific_sessions WHERE year = ?");
$count->execute([$year]);
$scientificPrograms = (int)$count->fetchColumn();

$recentPerPage = 10;
$recentPage = isset($_GET['user_page']) ? (int)$_GET['user_page'] : 1;
if ($recentPage < 1) {
    $recentPage = 1;
}
$recentCount = $pdo->prepare(
    "SELECT COUNT(*) FROM member_info WHERE year = ? AND reg_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
);
$recentCount->execute([$year]);
$recentTotal = (int)$recentCount->fetchColumn();
$recentPages = $recentTotal > 0 ? (int)ceil($recentTotal / $recentPerPage) : 1;
if ($recentPage > $recentPages) {
    $recentPage = $recentPages;
}
$recentOffset = ($recentPage - 1) * $recentPerPage;
$recentWindow = 10;
$recentWindowStart = (int)(floor(($recentPage - 1) / $recentWindow) * $recentWindow) + 1;
$recentWindowEnd = min($recentWindowStart + $recentWindow - 1, $recentPages);
$affiliationTypes = ksers_affiliation_types();
$recent = $pdo->prepare(
    "SELECT m.email, m.name_ko, m.first_name_eng, m.last_name_eng, m.affiliation_ko, m.affiliation_eng,
            m.affiliation_type, m.department, m.mobile, m.telephone, m.salutation, m.agree_email, m.user_class, m.reg_date, m.is_admin,
            c.name_ko AS country_ko, c.name_eng AS country_en
     FROM member_info m
     LEFT JOIN code c ON c.majr = 1 AND c.iso = m.country
     WHERE m.year = ? AND m.reg_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY m.reg_date DESC, m.seq DESC
     LIMIT " . (int)$recentPerPage . " OFFSET " . (int)$recentOffset
);
$recent->execute([$year]);
$recentRows = $recent->fetchAll(PDO::FETCH_ASSOC);

include 'lib/header.php';
include 'lib/menu.php';
?>

<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small"><?= h(t('회원', 'Members')) ?></div>
            <div class="fs-4 fw-bold"><?= $members ?></div>
        </div></div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small"><?= h($year . ' ' . t('등록', 'registrations')) ?></div>
            <div class="fs-4 fw-bold"><?= $registrations ?></div>
        </div></div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small"><?= h(t('결제 완료', 'Paid')) ?></div>
            <div class="fs-4 fw-bold"><?= $paid ?></div>
            <div class="text-muted small"><?= h(t('입금 대기', 'Waiting')) ?> <?= $pending ?></div>
        </div></div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small"><?= h(t('초록', 'Abstracts')) ?></div>
            <div class="fs-4 fw-bold"><?= $abstracts ?></div>
            <div class="text-muted small"><?= h(t('결과 입력', 'Results entered')) ?> <?= $reviewed ?></div>
        </div></div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <a class="card border-0 shadow-sm h-100 text-decoration-none text-body" href="/admin/page/glance/index.php">
            <div class="card-body">
                <div class="text-muted small">Program at a Glance</div>
                <div class="fs-4 fw-bold"><?= $glanceCells ?></div>
                <div class="text-muted small"><?= h(t('일정표 칸 열기', 'Open the timetable')) ?></div>
            </div>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <a class="card border-0 shadow-sm h-100 text-decoration-none text-body" href="/admin/page/scientific/index.php">
            <div class="card-body">
                <div class="text-muted small">Scientific Program</div>
                <div class="fs-4 fw-bold"><?= $scientificPrograms ?></div>
                <div class="text-muted small"><?= h(t('프로그램 열기', 'Open programs')) ?></div>
            </div>
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="m-0 fw-bold"><?= h(t('최근 등록 유저', 'Recent users')) ?></h5>
        <div class="text-muted small"><?= h(t('일주일 기준', 'Last 7 days')) ?></div>
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
                <?php if (!$recentRows) { ?>
                <tr><td colspan="15" class="text-center text-muted py-4"><?= h(t('일주일 안에 가입한 회원이 없습니다.', 'No members joined in the last 7 days.')) ?></td></tr>
                <?php } ?>
                <?php foreach ($recentRows as $row) {
                    $typeLabel = '';
                    if (isset($affiliationTypes[$row['affiliation_type']])) {
                        $typeLabel = t($affiliationTypes[$row['affiliation_type']][0], $affiliationTypes[$row['affiliation_type']][1]);
                    }
                ?>
                <tr>
                    <td><?= h(ksers_user_class_label($row['user_class'])) ?></td>
                    <td><?= h(code_name(['name_ko' => $row['country_ko'], 'name_eng' => $row['country_en']])) ?></td>
                    <td><?= h($row['email']) ?><?= (int)$row['is_admin'] === 1 ? ' '.h(t('(관리자)', '(Admin)')) : '' ?></td>
                    <td><?= h($row['name_ko']) ?></td>
                    <td><?= h($row['first_name_eng']) ?></td>
                    <td><?= h($row['last_name_eng']) ?></td>
                    <td><?= h($row['salutation']) ?></td>
                    <td><?= h($row['mobile']) ?></td>
                    <td><?= h($row['telephone']) ?></td>
                    <td><?= h($typeLabel) ?></td>
                    <td><?= h($row['affiliation_ko']) ?></td>
                    <td><?= h($row['affiliation_eng']) ?></td>
                    <td><?= h($row['department']) ?></td>
                    <td><?= h((int)$row['agree_email'] === 1 ? t('동의', 'Agreed') : t('미동의', 'Not agreed')) ?></td>
                    <td><?= h($row['reg_date']) ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php if ($recentTotal > $recentPerPage) { ?>
    <div class="card-footer bg-white py-3">
        <nav aria-label="<?= h(t('최근 등록 유저 페이지', 'Recent users pages')) ?>">
            <ul class="pagination pagination-sm justify-content-center m-0">
                <li class="page-item<?= $recentPage <= 1 ? ' disabled' : '' ?>">
                    <?php if ($recentPage <= 1) { ?>
                    <span class="page-link"><?= h(t('이전', 'Previous')) ?></span>
                    <?php } else { ?>
                    <a class="page-link" href="/admin/index.php?user_page=<?= $recentPage - 1 ?>"><?= h(t('이전', 'Previous')) ?></a>
                    <?php } ?>
                </li>
                <?php for ($i = $recentWindowStart; $i <= $recentWindowEnd; $i++) { ?>
                <li class="page-item<?= $i === $recentPage ? ' active' : '' ?>">
                    <a class="page-link" href="/admin/index.php?user_page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php } ?>
                <li class="page-item<?= $recentPage >= $recentPages ? ' disabled' : '' ?>">
                    <?php if ($recentPage >= $recentPages) { ?>
                    <span class="page-link"><?= h(t('다음', 'Next')) ?></span>
                    <?php } else { ?>
                    <a class="page-link" href="/admin/index.php?user_page=<?= $recentPage + 1 ?>"><?= h(t('다음', 'Next')) ?></a>
                    <?php } ?>
                </li>
            </ul>
        </nav>
    </div>
    <?php } ?>
</div>

<?php include 'lib/footer.php'; ?>
