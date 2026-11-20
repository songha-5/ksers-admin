<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$kind = isset($_GET['kind']) ? $_GET['kind'] : '';
$year = ksers_admin_year($pdo);
$exportTitles = [
    'member' => t('회원', 'Members'),
    'registration' => t('참가 등록 조회', 'Registration lookup'),
    'abstract' => t('초록 조회 및 변경', 'Abstracts and changes'),
    'review' => t('초록 심사 및 영상 확인', 'Abstract review and videos'),
    'submission' => t('참가자 제출 파일', 'Participant files'),
];
$filename = isset($exportTitles[$kind]) ? $exportTitles[$kind] . '.csv' : ('ksers-' . $kind . '-' . $year . '.csv');
$asciiName = 'ksers-' . preg_replace('/[^A-Za-z0-9._-]/', '', $kind) . '-' . $year . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');

if ($kind === 'member') {
    $keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
    $memberSeq = isset($_GET['member']) ? (int)$_GET['member'] : 0;
    $affiliationTypes = ksers_affiliation_types();
    fputcsv($out, [
        t('회원 유형', 'Member class'),
        t('국가', 'Country'),
        t('이메일', 'Email'),
        t('성명(국문)', 'Name (Korean)'),
        t('이름(영문)', 'First name (English)'),
        t('성(영문)', 'Last name (English)'),
        t('호칭', 'Title'),
        t('휴대전화', 'Cell Phone'),
        t('유선전화', 'Telephone'),
        t('소속 선택', 'Affiliation'),
        t('소속(국문)', 'Affiliation - In Korean'),
        t('소속(영문)', 'Affiliation - In English'),
        t('진료과/부서', 'Department'),
        t('이메일 수신 동의', 'E-mail consent'),
        t('가입일', 'Joined'),
    ]);
    $where = " WHERE m.year = ?";
    $params = [$year];
    if ($keyword !== '') {
        $where .= " AND (m.email LIKE ? OR m.name_ko LIKE ? OR m.first_name_eng LIKE ? OR m.last_name_eng LIKE ? OR m.affiliation_ko LIKE ? OR m.affiliation_eng LIKE ? OR m.department LIKE ? OR m.mobile LIKE ? OR m.telephone LIKE ? OR m.salutation LIKE ? OR c.name_ko LIKE ? OR c.name_eng LIKE ?)";
        $params = array_merge($params, array_fill(0, 12, '%' . $keyword . '%'));
    }
    if ($memberSeq > 0) {
        $where .= " AND m.seq = ?";
        $params[] = $memberSeq;
    }
    $stmt = $pdo->prepare(
        "SELECT m.email, m.name_ko, m.first_name_eng, m.last_name_eng, m.affiliation_ko, m.affiliation_eng,
                m.affiliation_type, m.department, m.mobile, m.telephone, m.salutation, m.agree_email, m.user_class, m.reg_date, m.is_admin,
                c.name_ko AS country_ko, c.name_eng AS country_en
         FROM member_info m
         LEFT JOIN code c ON c.majr = 1 AND c.iso = m.country"
        . $where . " ORDER BY m.seq DESC"
    );
    $stmt->execute($params);
    foreach ($stmt as $row) {
        $typeLabel = '';
        if (isset($affiliationTypes[$row['affiliation_type']])) {
            $typeLabel = t($affiliationTypes[$row['affiliation_type']][0], $affiliationTypes[$row['affiliation_type']][1]);
        }
        $email = $row['email'];
        if ((int)$row['is_admin'] === 1) {
            $email .= ' ' . t('(관리자)', '(Admin)');
        }
        $joined = '';
        if (!empty($row['reg_date'])) {
            $joinedAt = strtotime((string)$row['reg_date']);
            $joined = $joinedAt ? date('Y-m-d H:i:s', $joinedAt) : (string)$row['reg_date'];
            $joined = "\t" . $joined;
        }
        fputcsv($out, array_map('ksers_csv_cell', [
            ksers_user_class_label($row['user_class']),
            code_name(['name_ko' => $row['country_ko'], 'name_eng' => $row['country_en']]),
            $email,
            $row['name_ko'],
            $row['first_name_eng'],
            $row['last_name_eng'],
            $row['salutation'],
            $row['mobile'],
            $row['telephone'],
            $typeLabel,
            $row['affiliation_ko'],
            $row['affiliation_eng'],
            $row['department'],
            (int)$row['agree_email'] === 1 ? t('동의', 'Agreed') : t('미동의', 'Not agreed'),
            $joined,
        ]));
    }
} elseif ($kind === 'registration') {
    $categories = ksers_registration_categories();
    $memberChoices = ksers_registration_member_choices();
    $diets = ksers_registration_diets();
    fputcsv($out, [
        t('이메일', 'Email'),
        t('성명(국문)', 'Name (Korean)'),
        t('이름(영문)', 'First name (English)'),
        t('성(영문)', 'Last name (English)'),
        t('KSERS 회원여부', 'KSERS Membership'),
        t('직종', 'Category'),
        t('의사면허번호', 'Medical License No.'),
        t('평점 신청', 'CME credit'),
        t('증빙 상태', 'Document status'),
        t('증빙서류', 'Supporting Document'),
        t('식이 제한', 'Special dietary'),
        t('결제완료', 'Payment'),
        t('등록일', 'Registered'),
    ]);
    $stmt = $pdo->prepare(
        "SELECT p.reg_date, p.ksers_member, p.category, p.support_status,
                p.license_no, p.cme_apply, p.support_file, p.dietary, p.dietary_detail, p.pay_confirm,
                m.email, m.name_ko, m.first_name_eng, m.last_name_eng, m.country
         FROM participant_info p
         JOIN member_info m ON m.seq = p.member_seq
         WHERE p.year = ?
         ORDER BY p.seq DESC"
    );
    $stmt->execute([$year]);
    foreach ($stmt as $row) {
        $categoryLabel = isset($categories[$row['category']]) ? t($categories[$row['category']][0], $categories[$row['category']][1]) : '';
        $dietLabel = isset($diets[$row['dietary']]) ? $diets[$row['dietary']] : '';
        if ($dietLabel !== '' && trim((string)$row['dietary_detail']) !== '') {
            $dietLabel .= ' (' . $row['dietary_detail'] . ')';
        }
        $ksersLabel = isset($memberChoices[$row['ksers_member']]) ? t($memberChoices[$row['ksers_member']][0], $memberChoices[$row['ksers_member']][1]) : '';
        $supportApplicable = $row['country'] !== 'KR' && in_array($row['category'], ['student', 'nurse'], true);
        $supportLabel = ksers_support_status_label(isset($row['support_status']) ? $row['support_status'] : '', $supportApplicable);
        if ($supportLabel === '—') {
            $supportLabel = '';
        }
        $registered = '';
        if (!empty($row['reg_date'])) {
            $registeredAt = strtotime((string)$row['reg_date']);
            $registered = $registeredAt ? date('Y-m-d H:i:s', $registeredAt) : (string)$row['reg_date'];
            $registered = "\t" . $registered;
        }
        fputcsv($out, array_map('ksers_csv_cell', [
            $row['email'],
            $row['name_ko'],
            $row['first_name_eng'],
            $row['last_name_eng'],
            $ksersLabel,
            $categoryLabel,
            $row['license_no'],
            $row['cme_apply'] === 'Y' ? t('신청', 'Applied') : ($row['cme_apply'] === 'N' ? t('미신청', 'Not applied') : ''),
            $supportLabel,
            $row['support_file'],
            $dietLabel,
            $row['pay_confirm'] === 'Y' ? 'Y' : 'N',
            $registered,
        ]));
    }
} elseif ($kind === 'abstract') {
    $presentationLabel = [
        'oral' => 'Oral',
        'eposter' => 'E-Poster',
        'video' => 'Video',
        'mentor' => 'Mentor-Mentee Video',
        'poster' => t('포스터', 'Poster'),
        'either' => t('상관없음', 'No preference'),
    ];
    $statusLabel = [];
    foreach (ksers_abstract_review_statuses() as $code => $reviewStatus) {
        $statusLabel[$code] = t($reviewStatus['admin'][0], $reviewStatus['admin'][1]);
    }
    $selected = [];
    if (isset($_GET['abs']) && is_array($_GET['abs'])) {
        foreach ($_GET['abs'] as $value) {
            $seq = (int)$value;
            if ($seq > 0) {
                $selected[$seq] = $seq;
            }
        }
    }
    fputcsv($out, [
        t('접수번호', 'Receipt No.'),
        t('아이디', 'ID'),
        t('이름', 'Name'),
        t('발표 희망 형태', 'Preferred type'),
        t('분야', 'Topic'),
        t('제목', 'Title'),
        t('영상', 'Video'),
        t('초록 파일', 'Abstract file'),
        t('심사 상태', 'Review status'),
    ]);
    $abstractWhere = 'a.year = ?';
    $abstractParams = [$year];
    if ($selected) {
        $placeholders = implode(',', array_fill(0, count($selected), '?'));
        $abstractWhere .= ' AND a.seq IN (' . $placeholders . ')';
        $abstractParams = array_merge($abstractParams, array_values($selected));
    }
    $stmt = $pdo->prepare(
        "SELECT a.seq, a.title, a.presentation_type, a.topic, a.status, a.file_name,
                m.email, m.name_ko, m.first_name_eng, m.last_name_eng,
                (SELECT COUNT(*) FROM member_submission_file v
                  WHERE v.abstract_seq = a.seq AND v.year = a.year
                    AND v.kind IN ('abstract_video_1', 'abstract_video_2')) AS video_count
         FROM abstract_info a
         JOIN member_info m ON m.seq = a.member_seq
         WHERE $abstractWhere
         ORDER BY a.seq DESC"
    );
    $stmt->execute($abstractParams);
    foreach ($stmt as $row) {
        $typeCode = (string)$row['presentation_type'];
        $statusCode = (string)$row['status'];
        fputcsv($out, array_map('ksers_csv_cell', [
            sprintf('A%d-%04d', $year, (int)$row['seq']),
            $row['email'],
            ksers_member_preferred_name($row),
            isset($presentationLabel[$typeCode]) ? $presentationLabel[$typeCode] : $typeCode,
            $row['topic'],
            $row['title'],
            (int)$row['video_count'] > 0 ? 'Y' : 'N',
            trim((string)$row['file_name']) !== '' ? 'Y' : 'N',
            isset($statusLabel[$statusCode]) ? $statusLabel[$statusCode] : $statusCode,
        ]));
    }
} elseif ($kind === 'review') {
    $statusLabel = [];
    foreach (ksers_abstract_review_statuses() as $code => $reviewStatus) {
        $adminLabel = t($reviewStatus['admin'][0], $reviewStatus['admin'][1]);
        if ($code === 'accepted') {
            $statusLabel[$code] = $adminLabel . ' (' . t('채택', 'Accepted') . ')';
        } elseif ($code === 'rejected') {
            $statusLabel[$code] = $adminLabel . ' (' . t('불채택', 'Not accepted') . ')';
        } else {
            $statusLabel[$code] = $adminLabel;
        }
    }
    $where = ['a.year = ?'];
    $params = [$year];
    $selected = [];
    if (isset($_GET['abs']) && is_array($_GET['abs'])) {
        foreach ($_GET['abs'] as $value) {
            $seq = (int)$value;
            if ($seq > 0) {
                $selected[$seq] = $seq;
            }
        }
    }
    if ($selected) {
        $placeholders = implode(',', array_fill(0, count($selected), '?'));
        $where[] = 'a.seq IN (' . $placeholders . ')';
        $params = array_merge($params, array_values($selected));
    } else {
        $no = isset($_GET['no']) ? trim((string)$_GET['no']) : '';
        $submitter = isset($_GET['submitter']) ? trim((string)$_GET['submitter']) : '';
        $video = isset($_GET['video']) ? (string)$_GET['video'] : '';
        $status = isset($_GET['status']) ? (string)$_GET['status'] : '';
        if ($no !== '') {
            $seqFilter = 0;
            if (preg_match('/-(\d+)$/', $no, $match)) {
                $seqFilter = (int)$match[1];
            } elseif (ctype_digit($no)) {
                $seqFilter = (int)$no;
            }
            $where[] = 'a.seq = ?';
            $params[] = $seqFilter;
        }
        if ($submitter !== '') {
            $where[] = 'm.email LIKE ?';
            $params[] = '%' . $submitter . '%';
        }
        if ($video === 'Y') {
            $where[] = "EXISTS (SELECT 1 FROM member_submission_file v WHERE v.abstract_seq = a.seq AND v.year = a.year AND v.kind IN ('abstract_video_1', 'abstract_video_2'))";
        } elseif ($video === 'N') {
            $where[] = "NOT EXISTS (SELECT 1 FROM member_submission_file v WHERE v.abstract_seq = a.seq AND v.year = a.year AND v.kind IN ('abstract_video_1', 'abstract_video_2'))";
        }
        if ($status === 'result') {
            $where[] = "a.status IN ('accepted', 'rejected')";
        } elseif ($status !== '' && isset($statusLabel[$status])) {
            $where[] = 'a.status = ?';
            $params[] = $status;
        }
    }
    fputcsv($out, [
        t('접수번호', 'Receipt No.'),
        t('제출자', 'Submitter'),
        t('영상 여부', 'Video'),
        t('심사 상태', 'Review status'),
        t('심사용 영상 링크', 'Review video link'),
        t('초록 파일', 'Abstract file'),
        t('영상 파일', 'Video file'),
        t('영상 링크', 'Video link'),
    ]);
    $stmt = $pdo->prepare(
        "SELECT a.seq, a.status, a.file_name, m.email
         FROM abstract_info a
         JOIN member_info m ON m.seq = a.member_seq
         WHERE " . implode(' AND ', $where) . "
         ORDER BY a.seq DESC"
    );
    $stmt->execute($params);
    $abstracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $filesByAbstract = [];
    $linksByFile = [];
    if ($abstracts) {
        $ids = [];
        foreach ($abstracts as $row) {
            $ids[] = (int)$row['seq'];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $fileStmt = $pdo->prepare(
            "SELECT seq, abstract_seq, original_name
             FROM member_submission_file
             WHERE year = ? AND abstract_seq IN ($placeholders) AND kind IN ('abstract_video_1', 'abstract_video_2')
             ORDER BY seq"
        );
        $fileStmt->execute(array_merge([$year], $ids));
        $fileIds = [];
        foreach ($fileStmt as $file) {
            $filesByAbstract[(int)$file['abstract_seq']][] = $file;
            $fileIds[] = (int)$file['seq'];
        }
        if ($fileIds) {
            $linkPlaceholders = implode(',', array_fill(0, count($fileIds), '?'));
            $linkStmt = $pdo->prepare("SELECT file_seq, token FROM abstract_video_link WHERE year = ? AND file_seq IN ($linkPlaceholders)");
            $linkStmt->execute(array_merge([$year], $fileIds));
            foreach ($linkStmt as $link) {
                $linksByFile[(int)$link['file_seq']] = (string)$link['token'];
            }
        }
    }
    $publicBase = rtrim(ksers_public_base(), '/');
    foreach ($abstracts as $row) {
        $seq = (int)$row['seq'];
        $files = isset($filesByAbstract[$seq]) ? $filesByAbstract[$seq] : [];
        $fileLines = [];
        $urlLines = [];
        foreach ($files as $file) {
            $fileLines[] = (string)$file['original_name'];
            $fileSeq = (int)$file['seq'];
            $urlLines[] = isset($linksByFile[$fileSeq])
                ? $publicBase . '/service/video_link.php?token=' . $linksByFile[$fileSeq]
                : '-';
        }
        $statusCode = (string)$row['status'];
        $abstractFile = trim((string)$row['file_name']);
        fputcsv($out, array_map('ksers_csv_cell', [
            sprintf('A%d-%04d', $year, $seq),
            $row['email'],
            $files ? 'Y' : 'N',
            isset($statusLabel[$statusCode]) ? $statusLabel[$statusCode] : $statusCode,
            $urlLines && implode('', $urlLines) !== str_repeat('-', count($urlLines)) ? 'Y' : 'N',
            $abstractFile !== '' ? $abstractFile : '-',
            $fileLines ? implode("\n", $fileLines) : '-',
            $urlLines ? implode("\n", $urlLines) : '-',
        ]));
    }
} elseif ($kind === 'submission') {
    $materialKinds = ksers_submission_material_kinds();
    $fileKind = isset($_GET['file_kind']) ? (string)$_GET['file_kind'] : 'all';
    if ($fileKind !== 'all' && !in_array($fileKind, $materialKinds, true)) {
        $fileKind = 'all';
    }
    $selected = [];
    if (isset($_GET['seq']) && is_array($_GET['seq'])) {
        foreach ($_GET['seq'] as $value) {
            $seq = (int)$value;
            if ($seq > 0) {
                $selected[$seq] = $seq;
            }
        }
    }
    $where = ['f.year = ?'];
    $params = [$year];
    if ($selected) {
        $placeholders = implode(',', array_fill(0, count($selected), '?'));
        $where[] = 'f.seq IN (' . $placeholders . ')';
        $params = array_merge($params, array_values($selected));
    } else {
        $kinds = $fileKind === 'all' ? $materialKinds : [$fileKind];
        $placeholders = implode(',', array_fill(0, count($kinds), '?'));
        $where[] = 'f.kind IN (' . $placeholders . ')';
        $params = array_merge($params, $kinds);
    }
    $csvQuote = function ($value) {
        return '"' . str_replace('"', '""', (string)$value) . '"';
    };
    $csvTime = function ($value) {
        $time = strtotime((string)$value);
        if (!$time) {
            return '"-"';
        }
        return '"=""' . date('Y-m-d H:i', $time) . '"""';
    };
    fwrite($out, implode(',', [
        $csvQuote(t('초록 접수번호', 'Abstract No.')),
        $csvQuote(t('종류', 'Type')),
        $csvQuote(t('제출자', 'Submitter')),
        $csvQuote(t('파일', 'File')),
        $csvQuote(t('용량', 'Size')),
        $csvQuote(t('제출일', 'Submitted')),
        $csvQuote(''),
        $csvQuote(t('수정일', 'Updated')),
    ]) . "\n");
    $stmt = $pdo->prepare(
        "SELECT f.seq, f.kind, f.abstract_seq, f.original_name, f.file_size, f.reg_date, f.update_date, m.email
         FROM member_submission_file f
         JOIN member_info m ON m.seq = f.member_seq
         WHERE " . implode(' AND ', $where) . "
         ORDER BY f.seq DESC"
    );
    $stmt->execute($params);
    foreach ($stmt as $row) {
        $bytes = (int)$row['file_size'];
        $sizeLabel = $bytes >= 1048576
            ? number_format(max(1, (int)round($bytes / 1048576))) . ' MB'
            : ($bytes > 0 ? number_format(max(1, (int)round($bytes / 1024))) . ' KB' : '-');
        $receipt = (int)$row['abstract_seq'] > 0 ? sprintf('A%d-%04d', $year, (int)$row['abstract_seq']) : '-';
        fwrite($out, implode(',', [
            $csvQuote(ksers_csv_cell($receipt)),
            $csvQuote(ksers_csv_cell(ksers_submission_label($row['kind']))),
            $csvQuote(ksers_csv_cell($row['email'])),
            $csvQuote(ksers_csv_cell($row['original_name'])),
            $csvQuote(ksers_csv_cell($sizeLabel)),
            $csvTime($row['reg_date']),
            $csvQuote(''),
            $csvTime($row['update_date']),
        ]) . "\n");
    }
} else {
    fputcsv($out, ['error']);
}
fclose($out);
exit;
