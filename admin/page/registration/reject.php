<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$year = ksers_admin_year($pdo);
$seq = isset($_GET['seq']) ? (int)$_GET['seq'] : 0;
$stmt = $pdo->prepare(
    "SELECT p.seq, p.category, p.support_status, p.support_file, m.email, m.country
     FROM participant_info p
     JOIN member_info m ON m.seq = p.member_seq
     WHERE p.seq = ? AND p.year = ?"
);
$stmt->execute([$seq, $year]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$open = $row && $row['country'] !== 'KR' && in_array($row['category'], ['student', 'nurse'], true) && $row['support_status'] !== 'rejected';
if (!$open) {
    http_response_code(404);
    exit('Not found');
}
$keep = [];
foreach (['q', 'category', 'country', 'status', 'focus'] as $key) {
    if (isset($_GET[$key]) && (string)$_GET[$key] !== '') {
        $keep[$key] = (string)$_GET[$key];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= h(ksers_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(t('반려 사유', 'Reason for rejection')) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { margin: 0; min-height: 100vh; background: #e8eef5; color: #1e293b; font-family: Pretendard, sans-serif; }
        main { width: min(640px, calc(100% - 32px)); margin: 24px auto 40px; background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08); }
        h1 { margin: 0 0 8px; color: #09428f; font-size: 1.25rem; }
    </style>
</head>
<body>
<main>
    <h1><?= h(t('반려 사유', 'Reason for rejection')) ?></h1>
    <p><?= h($row['email']) ?></p>
    <form method="post" action="support_action.php">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="popup" value="1">
        <input type="hidden" name="seq" value="<?= (int)$row['seq'] ?>">
        <?php foreach ($keep as $key => $value) { ?>
        <input type="hidden" name="<?= h($key) ?>" value="<?= h($value) ?>">
        <?php } ?>
        <label class="form-label fw-bold" for="rejectReason"><?= h(t('반려 사유를 입력해 주세요.', 'Enter the reason for rejection.')) ?></label>
        <textarea class="form-control" id="rejectReason" name="reason" rows="6" maxlength="1000" required></textarea>
        <p class="form-text"><?= h(t('입력한 반려 사유는 참가자의 마이페이지와 참가 등록 화면에 표시됩니다.', 'The reason you enter is shown on the participant’s My Page and registration page.')) ?></p>
        <div class="d-flex justify-content-end">
            <button class="btn btn-danger" type="submit"><?= h(t('반려', 'Reject')) ?></button>
        </div>
    </form>
</main>
</body>
</html>
