<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);
$year = ksers_admin_year($pdo);
$seq = isset($_GET['seq']) ? (int)$_GET['seq'] : 0;

$session = [
    'seq' => 0,
    'session_id' => '',
    'category' => '',
    'session_code' => '',
    'session_title' => '',
    'session_date' => '',
    'start_time' => '',
    'end_time' => '',
    'room' => '',
    'bg_color' => '',
    'row_span' => 1,
    'col_span' => 1,
    'has_link' => 1,
    'sort_no' => 0,
    'is_public' => 1,
];
$chairs = [['name' => '', 'affiliation' => '', 'sort_no' => 1]];
$talks = [];

if ($seq > 0) {
    $stmt = $pdo->prepare("SELECT * FROM program_session WHERE seq = ? AND year = ?");
    $stmt->execute([$seq, $year]);
    $found = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$found) {
        header('Location: /admin/page/program/list.php');
        exit;
    }
    $session = $found;
    $chairStmt = $pdo->prepare("SELECT * FROM program_chair WHERE session_seq = ? ORDER BY sort_no, seq");
    $chairStmt->execute([$seq]);
    $chairs = $chairStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$chairs) {
        $chairs = [['name' => '', 'affiliation' => '', 'sort_no' => 1]];
    }
    $talkStmt = $pdo->prepare("SELECT * FROM program_talk WHERE session_seq = ? ORDER BY sort_no, seq");
    $talkStmt->execute([$seq]);
    $talks = $talkStmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = $seq > 0 ? t('세션 편집', 'Edit session') : t('세션 추가', 'Add session');
$page_heading = $page_title;

include '../../lib/header.php';
include '../../lib/menu.php';
?>

<?php if (!empty($_SESSION['admin_notice'])) { ?>
<div class="alert alert-info border-0 shadow-sm"><?= h($_SESSION['admin_notice']) ?></div>
<?php unset($_SESSION['admin_notice']); } ?>

<p class="mb-3"><a href="/admin/page/program/list.php">&larr; <?= h(t('목록', 'List')) ?></a></p>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3"><h5 class="m-0 fw-bold"><?= h(t('세션 정보', 'Session')) ?></h5></div>
    <div class="card-body">
        <form method="post" action="action.php">
            <input type="hidden" name="action" value="save_session">
            <input type="hidden" name="seq" value="<?= (int)$session['seq'] ?>">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">session_id *</label>
                    <input class="form-control" name="session_id" required maxlength="40" value="<?= h($session['session_id']) ?>" placeholder="mv01">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= h(t('카테고리', 'Category')) ?></label>
                    <input class="form-control" name="category" maxlength="80" value="<?= h($session['category']) ?>" placeholder="Master Video">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= h(t('세션 코드', 'Session code')) ?></label>
                    <input class="form-control" name="session_code" maxlength="40" value="<?= h($session['session_code']) ?>" placeholder="MV01">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= h(t('정렬', 'Sort')) ?></label>
                    <input class="form-control" type="number" name="sort_no" value="<?= (int)$session['sort_no'] ?>">
                </div>
                <div class="col-12">
                    <label class="form-label"><?= h(t('세션명', 'Session title')) ?> *</label>
                    <input class="form-control" name="session_title" required maxlength="255" value="<?= h($session['session_title']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= h(t('날짜', 'Date')) ?> *</label>
                    <input class="form-control" type="date" name="session_date" required value="<?= h($session['session_date']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= h(t('시작', 'Start')) ?> *</label>
                    <input class="form-control" type="time" name="start_time" required value="<?= h(substr((string)$session['start_time'], 0, 5)) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= h(t('종료', 'End')) ?> *</label>
                    <input class="form-control" type="time" name="end_time" required value="<?= h(substr((string)$session['end_time'], 0, 5)) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= h(t('룸', 'Room')) ?></label>
                    <input class="form-control" name="room" maxlength="80" value="<?= h($session['room']) ?>" placeholder="Room A (Crystal 1)">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= h(t('배경색', 'Color')) ?></label>
                    <input class="form-control" name="bg_color" maxlength="20" value="<?= h($session['bg_color']) ?>" placeholder="#E8F4FC">
                </div>
                <div class="col-md-2">
                    <label class="form-label">row_span</label>
                    <input class="form-control" type="number" min="1" name="row_span" value="<?= (int)$session['row_span'] ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">col_span</label>
                    <input class="form-control" type="number" min="1" name="col_span" value="<?= (int)$session['col_span'] ?>">
                </div>
                <div class="col-md-5 d-flex align-items-end gap-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="has_link" id="hasLink" <?= (int)$session['has_link'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="hasLink"><?= h(t('SP 링크', 'Link to SP')) ?></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_public" id="isPublic" <?= (int)$session['is_public'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isPublic"><?= h(t('공개', 'Public')) ?></label>
                    </div>
                </div>
            </div>

            <hr class="my-4">
            <h6 class="fw-bold mb-3"><?= h(t('좌장', 'Chairs')) ?></h6>
            <div id="chairRows">
                <?php foreach ($chairs as $i => $chair) { ?>
                <div class="row g-2 mb-2 chair-row">
                    <div class="col-md-4">
                        <input class="form-control" name="chair_name[]" placeholder="<?= h(t('이름', 'Name')) ?>" value="<?= h(isset($chair['name']) ? $chair['name'] : '') ?>">
                    </div>
                    <div class="col-md-6">
                        <input class="form-control" name="chair_affiliation[]" placeholder="<?= h(t('소속', 'Affiliation')) ?>" value="<?= h(isset($chair['affiliation']) ? $chair['affiliation'] : '') ?>">
                    </div>
                    <div class="col-md-2">
                        <input class="form-control" type="number" name="chair_sort[]" placeholder="<?= h(t('정렬', 'Sort')) ?>" value="<?= (int)(isset($chair['sort_no']) ? $chair['sort_no'] : ($i + 1)) ?>">
                    </div>
                </div>
                <?php } ?>
            </div>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="addChair"><?= h(t('좌장 행 추가', 'Add chair row')) ?></button>

            <div class="mt-4">
                <button class="btn btn-primary-custom" type="submit"><?= h(t('세션·좌장 저장', 'Save session & chairs')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php if ($seq > 0) { ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3"><h5 class="m-0 fw-bold"><?= h(t('발표·연자', 'Talks & speakers')) ?> <span class="text-muted fs-6"><?= count($talks) ?></span></h5></div>
    <div class="card-body">
        <?php if ($talks) { ?>
        <div class="table-responsive mb-4">
            <table class="table align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?= h(t('코드', 'Code')) ?></th>
                        <th><?= h(t('제목', 'Title')) ?></th>
                        <th><?= h(t('연자', 'Speaker')) ?></th>
                        <th><?= h(t('파일', 'Files')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($talks as $talk) { ?>
                    <tr>
                        <td><?= h($talk['talk_code']) ?></td>
                        <td><?= h($talk['talk_title']) ?><?= $talk['note'] !== '' ? '<div class="text-muted small">' . h($talk['note']) . '</div>' : '' ?></td>
                        <td><?= h($talk['speaker_name']) ?><br><span class="text-muted small"><?= h($talk['speaker_affiliation']) ?></span></td>
                        <td class="small">
                            <?= $talk['photo_url'] !== '' ? 'Photo ' : '' ?>
                            <?= $talk['cv_url'] !== '' ? 'CV ' : '' ?>
                            <?= $talk['abstract_url'] !== '' ? 'Abstract' : '' ?>
                            <?= ($talk['photo_url'] === '' && $talk['cv_url'] === '' && $talk['abstract_url'] === '') ? '—' : '' ?>
                        </td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="#talk-<?= (int)$talk['seq'] ?>"><?= h(t('수정', 'Edit')) ?></a>
                            <form method="post" action="action.php" class="d-inline" onsubmit="return confirm(<?= json_encode(t('이 발표를 삭제할까요?', 'Delete this talk?'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
                                <input type="hidden" name="action" value="delete_talk">
                                <input type="hidden" name="session_seq" value="<?= (int)$seq ?>">
                                <input type="hidden" name="talk_seq" value="<?= (int)$talk['seq'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><?= h(t('삭제', 'Delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>

        <?php
        $forms = $talks;
        $forms[] = [
            'seq' => 0,
            'talk_code' => '',
            'talk_title' => '',
            'speaker_name' => '',
            'speaker_affiliation' => '',
            'speaker_country' => '',
            'note' => '',
            'sort_no' => count($talks) + 1,
            'is_public' => 1,
            'photo_url' => '',
            'cv_url' => '',
            'abstract_url' => '',
        ];
        foreach ($forms as $talk) {
            $isNew = (int)$talk['seq'] === 0;
        ?>
        <div class="border rounded p-3 mb-3" id="talk-<?= $isNew ? 'new' : (int)$talk['seq'] ?>">
            <h6 class="fw-bold mb-3"><?= $isNew ? h(t('발표 추가', 'Add talk')) : h(t('발표 수정', 'Edit talk')) ?></h6>
            <form method="post" action="action.php" enctype="multipart/form-data" class="row g-2">
                <input type="hidden" name="action" value="save_talk">
                <input type="hidden" name="session_seq" value="<?= (int)$seq ?>">
                <input type="hidden" name="talk_seq" value="<?= (int)$talk['seq'] ?>">
                <div class="col-md-2">
                    <label class="form-label"><?= h(t('코드', 'Code')) ?></label>
                    <input class="form-control" name="talk_code" maxlength="40" value="<?= h($talk['talk_code']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= h(t('제목', 'Title')) ?> *</label>
                    <input class="form-control" name="talk_title" required maxlength="500" value="<?= h($talk['talk_title']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= h(t('정렬', 'Sort')) ?></label>
                    <input class="form-control" type="number" name="sort_no" value="<?= (int)$talk['sort_no'] ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_public" id="talkPublic<?= (int)$talk['seq'] ?>" <?= (int)$talk['is_public'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="talkPublic<?= (int)$talk['seq'] ?>"><?= h(t('공개', 'Public')) ?></label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= h(t('연자', 'Speaker')) ?> *</label>
                    <input class="form-control" name="speaker_name" required maxlength="120" value="<?= h($talk['speaker_name']) ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label"><?= h(t('소속', 'Affiliation')) ?></label>
                    <input class="form-control" name="speaker_affiliation" maxlength="255" value="<?= h($talk['speaker_affiliation']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= h(t('국가', 'Country')) ?></label>
                    <select class="form-select" name="speaker_country">
                        <option value=""><?= h(t('국가를 선택해 주세요', 'Choose a country')) ?></option>
                        <?php
                        $speakerCountries = ksers_country_rows($pdo);
                        $speakerKnown = false;
                        foreach ($speakerCountries as $countryRow) {
                            $selected = $talk['speaker_country'] === $countryRow['name_eng'];
                            if ($selected) {
                                $speakerKnown = true;
                            }
                        ?>
                        <option value="<?= h($countryRow['name_eng']) ?>"<?= $selected ? ' selected' : '' ?>><?= h(code_name($countryRow)) ?></option>
                        <?php } ?>
                        <?php if ($talk['speaker_country'] !== '' && !$speakerKnown) { ?>
                        <option value="<?= h($talk['speaker_country']) ?>" selected><?= h($talk['speaker_country']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label"><?= h(t('비고', 'Note')) ?></label>
                    <input class="form-control" name="note" maxlength="255" value="<?= h($talk['note']) ?>" placeholder="Pre-recorded">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= h(t('사진', 'Photo')) ?> (JPG/PNG)</label>
                    <input class="form-control" type="file" name="photo_file" accept=".jpg,.jpeg,.png,.webp,.gif">
                    <?php if ($talk['photo_url'] !== '') { ?><div class="small mt-1"><a href="<?= h($talk['photo_url']) ?>" target="_blank" rel="noopener">URL</a></div><?php } ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label">CV (PDF)</label>
                    <input class="form-control" type="file" name="cv_file" accept=".pdf">
                    <?php if ($talk['cv_url'] !== '') { ?><div class="small mt-1"><a href="<?= h($talk['cv_url']) ?>" target="_blank" rel="noopener">URL</a></div><?php } ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Abstract (PDF)</label>
                    <input class="form-control" type="file" name="abstract_file" accept=".pdf">
                    <?php if ($talk['abstract_url'] !== '') { ?><div class="small mt-1"><a href="<?= h($talk['abstract_url']) ?>" target="_blank" rel="noopener">URL</a></div><?php } ?>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary-custom" type="submit"><?= $isNew ? h(t('발표 추가', 'Add talk')) : h(t('발표 저장', 'Save talk')) ?></button>
                </div>
            </form>
        </div>
        <?php } ?>
    </div>
</div>
<?php } else { ?>
<div class="alert alert-secondary border-0"><?= h(t('세션을 먼저 저장하면 발표·연자와 파일을 올릴 수 있습니다.', 'Save the session first, then add talks, speakers, and files.')) ?></div>
<?php } ?>

<script>
document.getElementById('addChair').addEventListener('click', function () {
    var wrap = document.getElementById('chairRows');
    var row = wrap.querySelector('.chair-row').cloneNode(true);
    row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
    wrap.appendChild(row);
});
</script>

<?php include '../../lib/footer.php'; ?>
