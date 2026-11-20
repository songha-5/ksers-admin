<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';

$memberSeq = isset($_SESSION['seq']) ? (int)$_SESSION['seq'] : 0;
if ($memberSeq < 1) {
    header('Location: /service/login.php');
    exit;
}
ksers_require_participant($pdo);

$year = isset($eventYear) ? (int)$eventYear : 2027;
$kind = isset($_GET['kind']) ? $_GET['kind'] : '';
$setting = ksers_setting($pdo, $year);
$title = ksers_text($setting, 'title_ko', 'title_en');

$participantStmt = $pdo->prepare(
    "SELECT p.*, m.email, m.mobile, m.name_ko, m.first_name_eng, m.last_name_eng,
            m.affiliation_ko, m.affiliation_eng, m.country,
            c.name_ko AS country_ko, c.name_eng AS country_en
     FROM participant_info p
     JOIN member_info m ON m.seq = p.member_seq
     LEFT JOIN code c ON c.majr = 1 AND c.iso = m.country
     WHERE p.member_seq = ? AND p.year = ?"
);
$participantStmt->execute([$memberSeq, $year]);
$participant = $participantStmt->fetch(PDO::FETCH_ASSOC);
if ($participant && !empty($participant['cancelled_at']) && $kind === 'confirmation') {
    $participant = null;
}

$order = null;
$lines = [];
if ($kind === 'receipt') {
    $orderStmt = $pdo->prepare(
        "SELECT * FROM payment_order WHERE seq = ? AND member_seq = ? AND year = ? AND status IN ('paid', 'refunded')"
    );
    $orderStmt->execute([isset($_GET['order']) ? (int)$_GET['order'] : 0, $memberSeq, $year]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    if ($order) {
        $lineStmt = $pdo->prepare("SELECT * FROM payment_line WHERE order_seq = ? ORDER BY seq");
        $lineStmt->execute([(int)$order['seq']]);
        $lines = $lineStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$confirmSheet = null;
if ($kind === 'confirmation' && $participant) {
    $confirmKorea = ksers_country_profile([
        'name_ko' => $participant['country_ko'],
        'name_eng' => $participant['country_en'],
    ])['korea'];
    $docEnglish = ksers_lang() === 'en';
    $label = function ($ko, $en) use ($docEnglish) {
        return $docEnglish ? $en : $ko;
    };
    $moneyText = function ($amount, $currency) use ($docEnglish) {
        $formatted = number_format((int)$amount);
        if ($currency === 'USD') {
            return 'USD ' . $formatted;
        }
        return $docEnglish ? ($formatted . ' KRW') : ($formatted . '원');
    };
    $categories = ksers_registration_categories();
    $memberChoices = ksers_registration_member_choices();
    $feeCodes = ksers_payment_fee_codes();
    $categoryKey = isset($participant['category']) ? $participant['category'] : '';
    $categoryLabel = isset($categories[$categoryKey])
        ? $label($categories[$categoryKey][0], $categories[$categoryKey][1])
        : '';
    $feeCode = ksers_participant_fee_code($participant, $confirmKorea);
    $feeLabel = isset($feeCodes[$feeCode])
        ? $label($feeCodes[$feeCode][0], $feeCodes[$feeCode][1])
        : $categoryLabel;
    $periodCode = ksers_payment_period_for(
        isset($participant['reg_date']) ? $participant['reg_date'] : '',
        $year,
        ksers_onsite_dates($pdo, $year)
    );
    $periodLabels = [
        'early' => 'Early-bird',
        'pre' => $label('사전등록', 'Pre-registration'),
        'onsite' => $label('현장등록', 'On-site'),
    ];
    $division = $feeLabel;
    if (isset($periodLabels[$periodCode])) {
        $division .= ' · ' . $periodLabels[$periodCode];
    }
    $membership = null;
    if ($confirmKorea && isset($memberChoices[$participant['ksers_member']])) {
        $choice = $memberChoices[$participant['ksers_member']];
        $membership = $label($choice[0], $choice[1]);
        if ($participant['ksers_member'] === 'lifetime' && !empty($participant['senior_lifetime'])) {
            $membership .= ' (' . $label('65세 이상', 'Aged 65 or older') . ')';
        }
    }
    $orderStmt = $pdo->prepare(
        "SELECT * FROM payment_order
         WHERE member_seq = ? AND year = ? AND status IN ('pending', 'paid')
         ORDER BY seq"
    );
    $orderStmt->execute([$memberSeq, $year]);
    $confirmOrders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
    $lineStmt = $pdo->prepare("SELECT * FROM payment_line WHERE order_seq = ? ORDER BY seq");
    $payLines = [];
    $totalAmount = 0;
    $payCurrency = $confirmKorea ? 'KRW' : 'USD';
    $pendingBank = false;
    $pendingOther = false;
    $paidOrders = [];
    foreach ($confirmOrders as $confirmOrder) {
        $payCurrency = ksers_fee_currency($confirmOrder);
        $totalAmount += (int)$confirmOrder['amount'];
        if ($confirmOrder['status'] === 'pending' && $confirmOrder['method'] === 'bank') {
            $pendingBank = true;
        } elseif ($confirmOrder['status'] === 'pending') {
            $pendingOther = true;
        } elseif ($confirmOrder['status'] === 'paid') {
            $paidOrders[] = $confirmOrder;
        }
        $lineStmt->execute([(int)$confirmOrder['seq']]);
        foreach ($lineStmt->fetchAll(PDO::FETCH_ASSOC) as $line) {
            $payLines[] = [
                'name' => $docEnglish ? $line['name_en'] : $line['name_ko'],
                'amount' => $moneyText($line['amount'], ksers_fee_currency($confirmOrder)),
            ];
        }
    }
    $preview = true;
    if (!$confirmOrders || $pendingOther) {
        $payStatus = $label('미결제', 'Unpaid');
    } elseif ($pendingBank) {
        $payStatus = $label('입금 확인 중', 'Checking the transfer');
    } elseif ($totalAmount === 0) {
        $payStatus = $label('등록 완료(무료)', 'Registered (free)');
        $preview = false;
    } else {
        $latest = $paidOrders[count($paidOrders) - 1];
        $methodLabel = strpos((string)$latest['method'], 'card') === 0
            ? $label('카드 결제', 'Card')
            : $label('계좌 송금', 'Bank transfer');
        $paidOn = substr((string)($latest['paid_date'] !== null && $latest['paid_date'] !== '' ? $latest['paid_date'] : $latest['reg_date']), 0, 10);
        $payStatus = $label('결제 완료', 'Paid') . ' (' . $methodLabel . ' · ' . $paidOn . ')';
        $preview = false;
    }
    $payLead = '';
    $payRest = $payStatus;
    $payDone = false;
    $paidLead = $label('결제 완료', 'Paid');
    $freeLead = $label('등록 완료(무료)', 'Registered (free)');
    if (strpos($payStatus, $paidLead) === 0 || $payStatus === $freeLead) {
        $payDone = true;
        $payLead = strpos($payStatus, $paidLead) === 0 ? $paidLead : $freeLead;
        $payRest = substr($payStatus, strlen($payLead));
    }
    $koreanName = trim((string)$participant['name_ko']);
    $englishName = trim($participant['first_name_eng'] . ' ' . $participant['last_name_eng']);
    if ($docEnglish || !$confirmKorea) {
        $confirmName = $englishName !== '' ? $englishName : $koreanName;
        $confirmAffiliation = $participant['affiliation_eng'] !== '' ? $participant['affiliation_eng'] : $participant['affiliation_ko'];
    } else {
        $confirmName = $koreanName !== '' ? $koreanName : $englishName;
        $confirmAffiliation = $participant['affiliation_ko'] !== '' ? $participant['affiliation_ko'] : $participant['affiliation_eng'];
    }
    $issued = new DateTime('now', new DateTimeZone('Asia/Seoul'));
    $confirmSheet = [
        'korea' => $confirmKorea,
        'english' => $docEnglish,
        'name' => $confirmName,
        'affiliation' => $confirmAffiliation,
        'reg_no' => 'R' . $year . '-' . str_pad((string)$participant['seq'], 4, '0', STR_PAD_LEFT),
        'membership' => $membership,
        'category' => $categoryLabel,
        'division' => $division,
        'lines' => $payLines,
        'total' => $moneyText($totalAmount, $payCurrency),
        'pay_status' => $payStatus,
        'pay_lead' => $payLead,
        'pay_rest' => $payRest,
        'pay_done' => $payDone,
        'preview' => $preview,
        'reg_date' => substr((string)$participant['reg_date'], 0, 10),
        'issue_date' => $issued->format('Y-m-d'),
        'label' => $label,
    ];
}

$ok = ($kind === 'confirmation' && $participant) || ($kind === 'receipt' && $order);
$heading = $kind === 'receipt'
    ? t('결제 영수증', 'Payment receipt')
    : t('등록 확인서', 'Registration confirmation');
?>
<!DOCTYPE html>
<html lang="<?= h($confirmSheet ? ($confirmSheet['english'] ? 'en' : 'ko') : ksers_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= h($heading) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <link href="/service/ksers.css" rel="stylesheet">
    <style>
        body { margin: 0; background: #fff; color: #1a1a1a; font-family: Pretendard, sans-serif; }
        .sheet { max-width: 760px; margin: 24px auto; padding: 28px 32px; border: 1px solid #cfd8dc; }
        h1 { color: #09428F; font-size: 26px; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { border-bottom: 1px solid #e4e4e7; text-align: left; padding: 8px 4px; vertical-align: top; }
        th { width: 28%; color: #3f3f46; font-weight: 700; }
        .muted { color: #52525b; }
        body.confirm-page { background: #e8edf3; }
        .confirm-print { max-width: 820px; margin: 16px auto 0; padding: 0 16px; }
        .confirm-sheet {
            max-width: 820px;
            margin: 16px auto 32px;
            padding: 28px 32px 22px;
            background: #fff;
            border: 1px solid #e6ebf2;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        }
        .confirm-congress {
            margin: 0;
            padding-bottom: 16px;
            border-bottom: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 13px;
            line-height: 1.5;
        }
        .confirm-sheet h1 {
            margin: 18px 0 0;
            color: #0b3a82;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .confirm-sub { margin: 4px 0 0; color: #6b7280; font-size: 14px; }
        .confirm-sheet table { margin-top: 18px; border-top: 2px solid #0b3a82; }
        .confirm-sheet th,
        .confirm-sheet td {
            padding: 12px 14px;
            border-bottom: 1px solid #e6ebf2;
            vertical-align: middle;
            font-size: 14px;
        }
        .confirm-sheet th {
            width: 1%;
            white-space: nowrap;
            background: #eef4fb;
            color: #374151;
            font-weight: 600;
        }
        .confirm-sheet td { color: #111827; background: #fff; }
        .confirm-sheet tr:last-child th,
        .confirm-sheet tr:last-child td { border-bottom: 0; }
        .confirm-total { font-weight: 800; }
        .pay-done { color: #15803d; font-weight: 700; }
        .preview { margin: 12px 0 0; padding: 8px 10px; background: #fff7ed; color: #9a3412; font-size: 13px; font-weight: 700; }
        .confirm-sheet .onsite {
            margin-top: 18px;
            padding: 14px 16px;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            background: #f3f8ff;
            color: #1e3a8a;
            font-size: 13px;
            line-height: 1.55;
        }
        .confirm-sheet .onsite strong { display: block; margin-bottom: 4px; color: #1d4ed8; font-size: 14px; }
        .confirm-sheet .foot { margin: 16px 0 0; color: #9ca3af; font-size: 12px; line-height: 1.65; }
        .confirm-sheet .office { margin: 16px 0 0; text-align: right; color: #1d4ed8; font-size: 13px; font-weight: 700; }
        @page { size: A4; margin: 10mm; }
        @media print {
            .no-print { display: none !important; }
            body.confirm-page {
                background: #e8edf3 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .confirm-sheet {
                max-width: none;
                margin: 0 auto;
                box-shadow: 0 0 0 1px #e6ebf2;
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .confirm-sheet th,
            .confirm-sheet td,
            .confirm-sheet .onsite,
            .pay-done {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body class="<?= ($kind === 'confirmation' && $confirmSheet) ? 'confirm-page' : '' ?>">
<?php if ($kind === 'confirmation' && $confirmSheet) { ?>
<p class="no-print confirm-print"><button type="button" onclick="window.print()"><?= h($confirmSheet['label']('인쇄', 'Print')) ?></button></p>
<?php } ?>
<div class="sheet<?= ($kind === 'confirmation' && $confirmSheet) ? ' confirm-sheet' : '' ?>">
    <?php if (!$ok) { ?>
        <p><?= h(t('발급할 문서가 없습니다.', 'There is no document to open.')) ?></p>
        <p class="no-print"><a href="registration.php"><?= h(t('참가 등록·결제', 'Registration & Payment')) ?></a></p>
    <?php } else { ?>
        <?php if (!($kind === 'confirmation' && $confirmSheet)) { ?>
        <p class="no-print"><button type="button" onclick="window.print()"><?= h(t('인쇄', 'Print')) ?></button></p>
        <?php } ?>
        <?php if ($kind === 'confirmation' && $confirmSheet) {
            $doc = $confirmSheet['label'];
        ?>
        <p class="confirm-congress">The 59th Congress of the Korean Society of Endo-Laparoscopic &amp; Robotic Surgery (KSERS) &amp; the 17th International Symposium</p>
        <h1><?= h($doc('등록 확인서', 'Registration Confirmation')) ?></h1>
        <?php if (!$confirmSheet['english']) { ?><p class="confirm-sub">Registration Confirmation</p><?php } ?>
        <?php if ($confirmSheet['preview']) { ?>
        <p class="preview"><?= h($doc('결제 전 미리보기', 'Preview before payment')) ?></p>
        <?php } ?>
        <table>
            <tr><th><?= h($doc('등록번호', 'Registration no.')) ?></th><td><?= h($confirmSheet['reg_no']) ?></td></tr>
            <tr><th><?= h($doc('이름', 'Name')) ?></th><td><?= h($confirmSheet['name']) ?></td></tr>
            <tr><th><?= h($doc('소속', 'Affiliation')) ?></th><td><?= h($confirmSheet['affiliation']) ?></td></tr>
            <tr><th><?= h($doc('이메일', 'Email')) ?></th><td><?= h($participant['email']) ?></td></tr>
            <?php if ($confirmSheet['membership'] !== null) { ?>
            <tr><th><?= h($doc('회원 유형', 'Membership type')) ?></th><td><?= h($confirmSheet['membership']) ?></td></tr>
            <?php } ?>
            <tr><th><?= h($doc('직종', 'Category')) ?></th><td><?= h($confirmSheet['category']) ?></td></tr>
            <tr><th><?= h($doc('등록 구분', 'Registration')) ?></th><td><?= h($confirmSheet['division']) ?></td></tr>
            <tr>
                <th><?= h($doc('결제 내역', 'Payment details')) ?></th>
                <td>
                    <?php if (!$confirmSheet['lines']) { ?>
                    <?= h($doc('없음', 'None')) ?>
                    <?php } else { foreach ($confirmSheet['lines'] as $payLine) { ?>
                    <div><?= h($payLine['name'] . ' ' . $payLine['amount']) ?></div>
                    <?php } } ?>
                </td>
            </tr>
            <tr><th><?= h($doc('총 결제금액', 'Total')) ?></th><td class="confirm-total"><?= h($confirmSheet['total']) ?></td></tr>
            <tr><th><?= h($doc('결제 상태', 'Payment status')) ?></th><td><?php if ($confirmSheet['pay_done']) { ?><span class="pay-done"><?= h($confirmSheet['pay_lead']) ?></span><?= h($confirmSheet['pay_rest']) ?><?php } else { ?><?= h($confirmSheet['pay_status']) ?><?php } ?></td></tr>
            <tr><th><?= h($doc('등록일 / 발급일', 'Registered / Issued')) ?></th><td><?= h($confirmSheet['reg_date'] . ' / ' . $confirmSheet['issue_date']) ?></td></tr>
        </table>
        <div class="onsite">
            <strong><?= h($doc('현장 빠른 확인 안내', 'On-site check-in')) ?></strong>
            <?= h($doc(
                '본 등록 확인서를 출력해서 가지고 오시면 현장 등록 데스크에서 빠르게 등록 확인을 받으실 수 있습니다.',
                'Please bring a printed copy of this confirmation for quick check-in at the on-site registration desk.'
            )) ?>
        </div>
        <p class="foot"><?= h($doc(
            '본 확인서는 KSERS 2027 참가 등록 사실을 확인하는 문서이며, 영수증으로 사용할 수 없습니다. 영수증은 마이페이지 > 참가 등록·결제에서 출력할 수 있습니다.',
            'This confirmation verifies registration for KSERS 2027 and cannot be used as a receipt. A receipt can be printed from My Page > Registration & Payment.'
        )) ?></p>
        <p class="office">KSERS 2027 <?= h($doc('사무국', 'Secretariat')) ?> · ksers2027@ksers.kr</p>
        <?php } else { ?>
        <h1><?= h($heading) ?></h1>
        <p class="muted"><?= h($title) ?></p>
        <table>
            <tr><th><?= h(t('영수증 번호', 'Receipt no.')) ?></th><td><?= h('P' . $year . '-' . str_pad((string)$order['seq'], 4, '0', STR_PAD_LEFT)) ?></td></tr>
            <tr><th><?= h(t('상태', 'Status')) ?></th><td><?= h($order['status'] === 'refunded' ? t('전액 환불', 'Refunded in full') : t('결제 완료', 'Paid')) ?></td></tr>
            <tr><th><?= h(t('수단', 'Method')) ?></th><td><?php
                $methodLabels = [
                    'bank' => t('무통장 입금', 'Bank transfer'),
                    'card_kr' => t('국내 카드', 'Domestic card'),
                    'card_os' => t('해외 카드', 'Overseas card'),
                ];
                $methodCode = isset($order['method']) ? (string)$order['method'] : '';
                echo h(isset($methodLabels[$methodCode]) ? $methodLabels[$methodCode] : $methodCode);
            ?></td></tr>
            <tr><th><?= h(t('결제일', 'Paid')) ?></th><td><?= h($order['paid_date']) ?></td></tr>
            <?php foreach ($lines as $line) { ?>
            <tr><th><?= h(ksers_text($line, 'name_ko', 'name_en')) ?></th><td><?= h(ksers_money($line['amount'], ksers_fee_currency($order))) ?></td></tr>
            <?php } ?>
            <tr><th><?= h(t('합계', 'Total')) ?></th><td><?= h(ksers_money($order['amount'], ksers_fee_currency($order))) ?></td></tr>
        </table>
        <p class="muted"><?= h(t('이 영수증은 학회 등록 시스템의 결제 기록입니다. 국세청 연동 영수증이 아닙니다.', 'This receipt is the conference payment record. It is not linked to the tax agency.')) ?></p>
        <?php } ?>
    <?php } ?>
</div>
</body>
</html>
