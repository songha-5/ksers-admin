<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
$year = ksers_admin_year($pdo);
$page_title = t('초록 설정 - ' . $year . ' 국제학술대회 관리자', 'Abstract settings - ' . $year . ' Conference Admin');
$page_heading = t('초록', 'Abstracts');
$message = '';

$renumber = function () use ($pdo, $year) {
    $rows = $pdo->prepare("SELECT seq FROM abstract_topic WHERE year = ? AND state = 1 ORDER BY sort_no, seq");
    $rows->execute([$year]);
    $update = $pdo->prepare("UPDATE abstract_topic SET sort_no = ? WHERE seq = ? AND year = ?");
    $sort = 1;
    foreach ($rows->fetchAll(PDO::FETCH_COLUMN) as $seq) {
        $update->execute([$sort, (int)$seq, $year]);
        $sort++;
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'add') {
        $nameKo = trim(isset($_POST['name_ko']) ? $_POST['name_ko'] : '');
        $nameEn = trim(isset($_POST['name_en']) ? $_POST['name_en'] : '');
        if ($nameKo === '' || $nameEn === '') {
            $message = t('분야를 국문과 영문으로 입력해 주세요.', 'Enter the topic in Korean and English.');
        } else {
            $next = $pdo->prepare("SELECT COALESCE(MAX(sort_no), 0) + 1 FROM abstract_topic WHERE year = ? AND state = 1");
            $next->execute([$year]);
            $insert = $pdo->prepare(
                "INSERT INTO abstract_topic (year, name_ko, name_en, sort_no, state) VALUES (?, ?, ?, ?, 1)"
            );
            $insert->execute([$year, $nameKo, $nameEn, (int)$next->fetchColumn()]);
            $message = t('분야를 추가했습니다.', 'Topic added.');
        }
    }
    if ($action === 'update') {
        $seq = isset($_POST['seq']) ? (int)$_POST['seq'] : 0;
        $nameKo = trim(isset($_POST['name_ko']) ? $_POST['name_ko'] : '');
        $nameEn = trim(isset($_POST['name_en']) ? $_POST['name_en'] : '');
        if ($seq < 1 || $nameKo === '' || $nameEn === '') {
            $message = t('분야를 국문과 영문으로 입력해 주세요.', 'Enter the topic in Korean and English.');
        } else {
            $update = $pdo->prepare("UPDATE abstract_topic SET name_ko = ?, name_en = ? WHERE seq = ? AND year = ? AND state = 1");
            $update->execute([$nameKo, $nameEn, $seq, $year]);
            $message = t('분야를 수정했습니다.', 'Topic updated.');
        }
    }
    if ($action === 'delete') {
        $seq = isset($_POST['seq']) ? (int)$_POST['seq'] : 0;
        $delete = $pdo->prepare("DELETE FROM abstract_topic WHERE seq = ? AND year = ?");
        $delete->execute([$seq, $year]);
        $renumber();
        $message = t('분야를 삭제했습니다.', 'Topic deleted.');
    }
    if ($action === 'up' || $action === 'down') {
        $renumber();
        $seq = isset($_POST['seq']) ? (int)$_POST['seq'] : 0;
        $current = $pdo->prepare("SELECT seq, sort_no FROM abstract_topic WHERE seq = ? AND year = ? AND state = 1");
        $current->execute([$seq, $year]);
        $row = $current->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $neighborSql = $action === 'up'
                ? "SELECT seq, sort_no FROM abstract_topic WHERE year = ? AND state = 1 AND sort_no < ? ORDER BY sort_no DESC LIMIT 1"
                : "SELECT seq, sort_no FROM abstract_topic WHERE year = ? AND state = 1 AND sort_no > ? ORDER BY sort_no ASC LIMIT 1";
            $neighbor = $pdo->prepare($neighborSql);
            $neighbor->execute([$year, (int)$row['sort_no']]);
            $other = $neighbor->fetch(PDO::FETCH_ASSOC);
            if ($other) {
                $swap = $pdo->prepare("UPDATE abstract_topic SET sort_no = ? WHERE seq = ? AND year = ?");
                $swap->execute([(int)$other['sort_no'], (int)$row['seq'], $year]);
                $swap->execute([(int)$row['sort_no'], (int)$other['seq'], $year]);
            }
        }
    }
}

$editSeq = isset($_GET['topic']) ? (int)$_GET['topic'] : 0;
$topicStmt = $pdo->prepare("SELECT * FROM abstract_topic WHERE year = ? AND state = 1 ORDER BY sort_no, seq");
$topicStmt->execute([$year]);
$topics = $topicStmt->fetchAll(PDO::FETCH_ASSOC);

include '../../lib/header.php';
include '../../lib/menu.php';
$abstractTab = 'setting';
include 'tabs.php';
?>

<?php if ($message !== '') { ?>
<div class="alert alert-info border-0 shadow-sm"><?= h($message) ?></div>
<?php } ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="m-0 fw-bold"><?= h(t('발표 분야', 'Topics')) ?></h5>
    </div>
    <div class="card-body">
        <form method="post" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="add">
            <div class="col-md-4">
                <label class="form-label" for="topicKo"><?= h(t('분야 (국문)', 'Topic (Korean)')) ?></label>
                <input class="form-control" id="topicKo" name="name_ko" maxlength="100" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="topicEn"><?= h(t('분야 (영문)', 'Topic (English)')) ?></label>
                <input class="form-control" id="topicEn" name="name_en" maxlength="100" required>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary-custom" type="submit"><?= h(t('추가', 'Add')) ?></button>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= h(t('순서', 'Order')) ?></th>
                    <th><?= h(t('분야 (국문)', 'Topic (Korean)')) ?></th>
                    <th><?= h(t('분야 (영문)', 'Topic (English)')) ?></th>
                    <th><?= h(t('수정', 'Edit')) ?></th>
                    <th><?= h(t('삭제', 'Delete')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$topics) { ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?= h(t('등록된 분야가 없습니다.', 'No topics yet.')) ?></td></tr>
                <?php } ?>
                <?php foreach ($topics as $topic) {
                    $editing = (int)$topic['seq'] === $editSeq;
                ?>
                <tr>
                    <td>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="up">
                            <input type="hidden" name="seq" value="<?= (int)$topic['seq'] ?>">
                            <button class="btn btn-sm btn-outline-secondary" type="submit" aria-label="<?= h(t('위로', 'Up')) ?>">↑</button>
                        </form>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="down">
                            <input type="hidden" name="seq" value="<?= (int)$topic['seq'] ?>">
                            <button class="btn btn-sm btn-outline-secondary" type="submit" aria-label="<?= h(t('아래로', 'Down')) ?>">↓</button>
                        </form>
                    </td>
                    <?php if ($editing) { ?>
                    <td colspan="2">
                        <form method="post" class="row g-2 align-items-center">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="seq" value="<?= (int)$topic['seq'] ?>">
                            <div class="col-md-5"><input class="form-control form-control-sm" name="name_ko" maxlength="100" required value="<?= h($topic['name_ko']) ?>"></div>
                            <div class="col-md-5"><input class="form-control form-control-sm" name="name_en" maxlength="100" required value="<?= h($topic['name_en']) ?>"></div>
                            <div class="col-md-2"><button class="btn btn-sm btn-primary-custom" type="submit"><?= h(t('저장', 'Save')) ?></button></div>
                        </form>
                    </td>
                    <?php } else { ?>
                    <td><?= h($topic['name_ko']) ?></td>
                    <td><?= h($topic['name_en']) ?></td>
                    <?php } ?>
                    <td>
                        <?php if (!$editing) { ?>
                        <a href="setting.php?topic=<?= (int)$topic['seq'] ?>"><?= h(t('수정', 'Edit')) ?></a>
                        <?php } ?>
                    </td>
                    <td>
                        <form method="post" onsubmit="return confirm(<?= json_encode(t('이 분야를 삭제할까요?', 'Delete this topic?'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="seq" value="<?= (int)$topic['seq'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit"><?= h(t('삭제', 'Delete')) ?></button>
                        </form>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../lib/footer.php'; ?>
