<?php

function ksers_admin_mail_templates() {
    $office = 'ksers2027@ksers.kr';
    return [
        'deadline' => [
            'label_ko' => '초록 접수 마감 안내',
            'label_en' => 'Abstract deadline',
            'filter' => 'not_submitted',
            'subject' => 'KSERS 2027 Reminder: Abstract Submission Closes on January 14',
            'category' => 'ABSTRACT',
            'title' => 'Abstract submission closes soon',
            'body' => 'This is a friendly reminder that abstract submission for KSERS 2027 will close on January 14 (Thu), 2027 (KST). We look forward to receiving your abstract.',
            'rows' => [
                ['Deadline', 'January 14 (Thu), 2027 (KST)'],
                ['All presentation types', 'My Page > Abstract Submission'],
                ['Video / Mentor-Mentee Video', 'upload your video in My Page > Video Submission after submitting the abstract'],
            ],
            'button_label' => 'Submit Abstract',
            'button_target' => 'submission',
            'notice' => 'Abstracts can be edited until the deadline. After the deadline, no submissions or changes can be made.',
            'send_reason' => 'abstract submission',
        ],
        'result_accept' => [
            'label_ko' => '심사 결과 통보 - 채택',
            'label_en' => 'Review result - accepted',
            'filter' => 'submitted',
            'subject' => 'KSERS 2027 Abstract Acceptance Notification ({{receipt_no}})',
            'category' => 'ABSTRACT',
            'title' => 'Congratulations! Your abstract has been accepted',
            'body' => "We are pleased to inform you that your abstract has been accepted for presentation at KSERS 2027, which will be held on April 22–24, 2027 at Lotte Hotel Seoul, Korea.\n\nThe presenting author must register for KSERS 2027 using their own account. If the abstract was submitted on behalf of the presenting author, the presenting author must sign up and register personally.",
            'rows' => [
                ['Receipt No.', '{{receipt_no}}'],
                ['Title', '{{title}}'],
                ['Presenting Author', '{{presenter}}'],
                ['Presentation Type', '{{final_type}}'],
                ['Session', '{{session}}'],
            ],
            'button_label' => 'Register Now',
            'button_target' => 'registration',
            'notice' => 'The presenting author must complete registration by March 15 (Mon), 2027. Abstracts whose presenting author has not registered by this date will be withdrawn from the final program. Presentation guidelines will be provided separately.',
            'send_reason' => 'abstract acceptance',
        ],
        'result' => [
            'label_ko' => '심사 결과 통보 - 불채택',
            'label_en' => 'Review result - not accepted',
            'filter' => 'submitted',
            'subject' => 'KSERS 2027 Abstract Acceptance Notification ({{receipt_no}})',
            'category' => 'ABSTRACT',
            'title' => 'Abstract review result',
            'body' => "Thank you for submitting your abstract to KSERS 2027. After careful review by the Scientific Committee, we regret to inform you that your abstract could not be accepted for presentation.\n\nWe received a large number of high-quality submissions this year, and the selection was very competitive. We sincerely appreciate your interest and hope to welcome you to KSERS 2027 on April 22–24, 2027.",
            'rows' => [
                ['Receipt No.', '{{receipt_no}}'],
                ['Title', '{{title}}'],
            ],
            'button_label' => '',
            'button_target' => '',
            'notice' => '',
            'send_reason' => 'abstract review',
        ],
        'presenter' => [
            'label_ko' => '발표자 등록 안내',
            'label_en' => 'Presenter registration',
            'filter' => 'unregistered_presenter',
            'subject' => 'KSERS 2027 Reminder: Presenter Registration Due March 15',
            'category' => 'REGISTRATION',
            'title' => 'Please complete your registration',
            'body' => 'Our records show that the presenting author of the following accepted abstract has not yet registered for KSERS 2027. To keep your presentation in the program, please complete registration by the deadline below.',
            'rows' => [
                ['Receipt No.', '{{receipt_no}}'],
                ['Title', '{{title}}'],
                ['Presenting Author', '{{presenter}}'],
                ['Registration Deadline', 'March 15 (Mon), 2027'],
            ],
            'button_label' => 'Register Now',
            'button_target' => 'registration',
            'notice' => 'If the presenting author does not register by March 15 (Mon), 2027, the abstract will be withdrawn from the final program.',
            'send_reason' => 'presenter registration',
        ],
        'withdraw' => [
            'label_ko' => '미등록 초록 철회 안내',
            'label_en' => 'Withdrawal notice',
            'filter' => 'withdraw_party',
            'subject' => 'KSERS 2027 Abstract Withdrawn Due to Non-registration ({{receipt_no}})',
            'category' => 'ABSTRACT',
            'title' => 'Your abstract has been withdrawn from the program',
            'body' => 'As the presenting author of the following abstract did not complete registration by March 15 (Mon), 2027, the abstract has been withdrawn from the final program of KSERS 2027, in accordance with the abstract submission guidelines.',
            'rows' => [
                ['Receipt No.', '{{receipt_no}}'],
                ['Title', '{{title}}'],
                ['Presenting Author', '{{presenter}}'],
            ],
            'button_label' => '',
            'button_target' => '',
            'notice' => 'If you believe this is an error, please contact the Secretariat (' . $office . ').',
            'send_reason' => 'abstract withdrawal',
        ],
        'schedule' => [
            'label_ko' => '발표 일정 안내',
            'label_en' => 'Presentation schedule',
            'filter' => 'submitted',
            'subject' => 'KSERS 2027 Presentation Schedule ({{receipt_no}})',
            'category' => 'PROGRAM',
            'title' => 'Your presentation schedule',
            'body' => 'Thank you for your contribution to KSERS 2027. Please find your presentation schedule below.',
            'rows' => [
                ['Receipt No.', '{{receipt_no}}'],
                ['Title', '{{title}}'],
                ['Presentation Type', '{{final_type}}'],
                ['Session', '{{session}}'],
                ['Date & Time', '{{presentation_datetime}} (KST)'],
                ['Room', '{{room}}'],
                ['Presentation Time', '{{presentation_minutes}} min'],
            ],
            'button_label' => 'View Presentation Guidelines',
            'button_target' => 'guidelines',
            'notice' => 'Please submit your presentation file by {{file_deadline}}. Detailed instructions are available in the presentation guidelines.',
            'send_reason' => 'presentation schedule',
        ],
        'grant' => [
            'label_ko' => 'Travel Grant 선정 안내',
            'label_en' => 'Travel Grant',
            'filter' => 'grant_party',
            'subject' => 'KSERS 2027 Travel Grant Award Notification',
            'category' => 'TRAVEL GRANT',
            'title' => 'Congratulations on your Travel Grant',
            'body' => 'We are pleased to inform you that you have been selected as a recipient of the KSERS 2027 Travel Grant.',
            'rows' => [
                ['Recipient', '{{name}}'],
                ['Receipt No.', '{{receipt_no}}'],
                ['Title', '{{title}}'],
                ['Grant Amount', 'USD 500'],
            ],
            'button_label' => '',
            'button_target' => '',
            'notice' => 'The grant will be provided on the condition that you register for KSERS 2027 and submit your paper to JMIS. Details on payment will be provided separately.',
            'send_reason' => 'travel grant',
        ],
        'payment_wait' => [
            'label_ko' => '결제 대기 안내',
            'label_en' => 'Payment reminder',
            'filter' => 'all',
            'subject' => 'KSERS 2027 등록비 결제를 완료해 주세요',
            'category' => '참가 등록',
            'title' => '등록비 결제가 완료되지 않았습니다',
            'body' => 'KSERS 2027 참가 등록 정보는 저장되었으나 등록비 결제(입금)가 아직 확인되지 않았습니다. 결제가 완료되어야 참가 등록이 확정됩니다.',
            'rows' => [
                ['등록번호', '{{reg_no}}'],
                ['이름', '{{name}}'],
                ['등록 구분', '{{reg_category}}'],
                ['등록비', '{{fee}}'],
                ['결제 마감', '{{pay_deadline}}', true],
            ],
            'button_label' => '결제하기',
            'button_target' => 'registration',
            'notice' => '결제 마감일까지 결제하지 않으면 등록이 완료되지 않습니다. 이미 입금하셨다면 이 메일은 무시해 주세요.',
            'send_reason' => '참가 등록',
            'locales' => [
                'ko' => [
                    'subject' => 'KSERS 2027 등록비 결제를 완료해 주세요',
                    'category' => '참가 등록',
                    'title' => '등록비 결제가 완료되지 않았습니다',
                    'body' => 'KSERS 2027 참가 등록 정보는 저장되었으나 등록비 결제(입금)가 아직 확인되지 않았습니다. 결제가 완료되어야 참가 등록이 확정됩니다.',
                    'rows' => [
                        ['등록번호', '{{reg_no}}'],
                        ['이름', '{{name}}'],
                        ['등록 구분', '{{reg_category}}'],
                        ['등록비', '{{fee}}'],
                        ['결제 마감', '{{pay_deadline}}', true],
                    ],
                    'button_label' => '결제하기',
                    'button_target' => 'registration',
                    'notice' => '결제 마감일까지 결제하지 않으면 등록이 완료되지 않습니다. 이미 입금하셨다면 이 메일은 무시해 주세요.',
                    'send_reason' => '참가 등록',
                ],
                'en' => [
                    'subject' => 'KSERS 2027 Please Complete Your Registration Payment',
                    'category' => 'REGISTRATION',
                    'title' => 'Your payment is pending',
                    'body' => 'Your KSERS 2027 registration details have been saved, but your payment has not yet been confirmed. Your registration will be confirmed once the payment is completed.',
                    'rows' => [
                        ['Registration No.', '{{reg_no}}'],
                        ['Name', '{{name}}'],
                        ['Category', '{{reg_category}}'],
                        ['Registration Fee', '{{fee}}'],
                        ['Payment Deadline', '{{pay_deadline}}', true],
                    ],
                    'button_label' => 'Pay Now',
                    'button_target' => 'registration',
                    'notice' => 'If payment is not completed by the deadline, your registration will not be confirmed. If you have already made a bank transfer, please disregard this e-mail.',
                    'send_reason' => 'registration',
                ],
            ],
        ],
        'attend' => [
            'label_ko' => '참가 안내 (행사 전)',
            'label_en' => 'Participant information',
            'filter' => 'all',
            'subject' => 'KSERS 2027 학술대회 참가 안내',
            'category' => '참가 안내',
            'title' => 'KSERS 2027 참가 안내',
            'body' => 'KSERS 2027이 곧 개최됩니다. 행사 참가에 필요한 내용을 안내드립니다.',
            'rows' => [
                ['일시', '2027년 4월 22일(목) ~ 24일(토)'],
                ['장소', '롯데호텔 서울'],
                ['등록번호', '{{reg_no}}'],
                ['현장 등록데스크', '{{desk_info}}'],
            ],
            'button_label' => '프로그램 보기',
            'button_target' => 'glance',
            'notice' => '현장에서 명찰을 받으실 때 등록 확인서(My Page에서 출력) 또는 등록번호를 보여주세요.',
            'send_reason' => '참가 안내',
            'locales' => [
                'ko' => [
                    'subject' => 'KSERS 2027 학술대회 참가 안내',
                    'category' => '참가 안내',
                    'title' => 'KSERS 2027 참가 안내',
                    'body' => 'KSERS 2027이 곧 개최됩니다. 행사 참가에 필요한 내용을 안내드립니다.',
                    'rows' => [
                        ['일시', '2027년 4월 22일(목) ~ 24일(토)'],
                        ['장소', '롯데호텔 서울'],
                        ['등록번호', '{{reg_no}}'],
                        ['현장 등록데스크', '{{desk_info}}'],
                    ],
                    'button_label' => '프로그램 보기',
                    'button_target' => 'glance',
                    'notice' => '현장에서 명찰을 받으실 때 등록 확인서(My Page에서 출력) 또는 등록번호를 보여주세요.',
                    'send_reason' => '참가 안내',
                ],
                'en' => [
                    'subject' => 'KSERS 2027 Information for Participants',
                    'category' => 'PARTICIPATION',
                    'title' => 'See you at KSERS 2027',
                    'body' => 'KSERS 2027 is just around the corner. Please find the information you need for attending the congress below.',
                    'rows' => [
                        ['Dates', 'April 22 (Thu) – 24 (Sat), 2027'],
                        ['Venue', 'Lotte Hotel Seoul, Korea'],
                        ['Registration No.', '{{reg_no}}'],
                        ['On-site Registration Desk', '{{desk_info}}'],
                    ],
                    'button_label' => 'View Program',
                    'button_target' => 'glance',
                    'notice' => 'Please present your registration confirmation (printable on My Page) or your registration number when picking up your name badge on site.',
                    'send_reason' => 'participation',
                ],
            ],
        ],
    ];
}

function ksers_admin_mail_template($key) {
    $all = ksers_admin_mail_templates();
    return isset($all[$key]) ? $all[$key] : $all['deadline'];
}

function ksers_admin_mail_button_url($target) {
    $base = function_exists('ksers_public_base') ? ksers_public_base() : '';
    if ($target === 'abstract') {
        return $base . '/service/abstract.php';
    }
    if ($target === 'registration') {
        return $base . '/service/registration.php';
    }
    if ($target === 'guidelines') {
        return 'https://ksers.kr/2027/en/guidelines';
    }
    if ($target === 'submission') {
        return 'https://ksers.kr/2027/en/submission';
    }
    if ($target === 'glance') {
        return 'https://ksers.kr/2027/en/glance';
    }
    return '';
}

function ksers_admin_mail_fill($text, $vars) {
    return strtr((string)$text, $vars);
}

function ksers_admin_mail_notice_html($notice) {
    $notice = (string)$notice;
    $office = 'ksers2027@ksers.kr';
    $link = '<a href="mailto:' . ksers_mail_h($office) . '" style="color:#0086C9;font-weight:bold;">' . ksers_mail_h($office) . '</a>';
    $parts = explode($office, $notice);
    $html = '';
    $last = count($parts) - 1;
    foreach ($parts as $index => $part) {
        $html .= ksers_mail_h($part);
        if ($index < $last) {
            $html .= $link;
        }
    }
    return $html;
}

function ksers_admin_mail_blank_vars() {
    $blank = '—';
    $later = 'To be announced';
    return [
        '{{receipt_no}}' => $blank,
        '{{title}}' => $blank,
        '{{presenter}}' => $blank,
        '{{final_type}}' => $blank,
        '{{session}}' => $later,
        '{{presentation_datetime}}' => $later,
        '{{room}}' => $later,
        '{{presentation_minutes}}' => $blank,
        '{{file_deadline}}' => $later,
        '{{deleted_at}}' => function_exists('ksers_password_changed_at_kst') ? ksers_password_changed_at_kst() : '',
        '{{reg_no}}' => $blank,
        '{{reg_category}}' => $blank,
        '{{fee}}' => $blank,
        '{{pay_deadline}}' => function_exists('ksers_mail_temp_mark') ? ksers_mail_temp_mark() : '임시',
        '{{desk_info}}' => '',
    ];
}

function ksers_admin_mail_vars_from_row($row) {
    $vars = ksers_admin_mail_blank_vars();
    $map = [
        'receipt_no' => '{{receipt_no}}',
        'title' => '{{title}}',
        'presenter' => '{{presenter}}',
        'final_type' => '{{final_type}}',
        'session' => '{{session}}',
        'presentation_datetime' => '{{presentation_datetime}}',
        'room' => '{{room}}',
        'presentation_minutes' => '{{presentation_minutes}}',
        'file_deadline' => '{{file_deadline}}',
        'deleted_at' => '{{deleted_at}}',
        'reg_no' => '{{reg_no}}',
        'name' => '{{name}}',
        'reg_category' => '{{reg_category}}',
        'fee' => '{{fee}}',
        'pay_deadline' => '{{pay_deadline}}',
    ];
    foreach ($map as $field => $token) {
        if (!isset($row[$field])) {
            continue;
        }
        $value = trim((string)$row[$field]);
        if ($value !== '') {
            $vars[$token] = $value;
        }
    }
    return $vars;
}

function ksers_admin_mail_render_parts($template, $subject, $title, $body, $vars, $recipientName, $recipientEmail) {
    $name = trim((string)$recipientName);
    if ($name === '') {
        $name = 'Author';
    }
    if (!isset($vars['{{name}}']) || trim((string)$vars['{{name}}']) === '' || $vars['{{name}}'] === '{{name}}') {
        $vars['{{name}}'] = $name;
    }
    $subject = ksers_admin_mail_fill($subject, $vars);
    $title = ksers_admin_mail_fill($title, $vars);
    $body = ksers_admin_mail_fill($body, $vars);
    $paragraphs = preg_split("/\r\n|\n|\r/", $body);
    $blocks = [];
    $current = '';
    foreach ($paragraphs as $line) {
        if (trim($line) === '') {
            if ($current !== '') {
                $blocks[] = $current;
                $current = '';
            }
            continue;
        }
        $current = $current === '' ? $line : ($current . "\n" . $line);
    }
    if ($current !== '') {
        $blocks[] = $current;
    }
    if (!$blocks) {
        $blocks[] = '';
    }
    $info = '';
    $plainRows = '';
    foreach ($template['rows'] as $row) {
        $value = ksers_admin_mail_fill($row[1], $vars);
        $raw = !empty($row[2]) || strpos($value, '<') !== false;
        $info .= ksers_mail_info_row($row[0], $value, $raw);
        $plainRows .= $row[0] . ': ' . trim(strip_tags($value)) . "\n";
    }
    $notice = ksers_admin_mail_fill($template['notice'], $vars);
    $buttonUrl = ksers_admin_mail_button_url($template['button_target']);
    $buttonLabel = (string)$template['button_label'];
    $plain = $title . "\n\n" . $body . "\n\n" . $plainRows . "\n" . $notice . "\n";
    return [
        'subject' => $subject,
        'body' => $plain,
        'fields' => [
            'mail_subject' => $subject,
            'preheader' => $title,
            'mail_category' => $template['category'],
            'mail_title' => $title,
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs($blocks),
            'info_rows' => $info,
            'button_url' => $buttonLabel === '' ? '' : $buttonUrl,
            'button_label' => $buttonLabel,
            'notice_text' => $notice === '' ? '' : ksers_admin_mail_notice_html($notice),
            'send_reason' => $template['send_reason'],
            'recipient_email' => $recipientEmail,
        ],
    ];
}

function ksers_admin_mail_person_name($row) {
    $english = trim(trim(isset($row['first_name_eng']) ? (string)$row['first_name_eng'] : '') . ' ' . trim(isset($row['last_name_eng']) ? (string)$row['last_name_eng'] : ''));
    if ($english === '' && isset($row['first_name'])) {
        $english = trim(trim((string)$row['first_name']) . ' ' . trim((string)$row['last_name']));
    }
    if ($english !== '') {
        return $english;
    }
    return trim(isset($row['name_ko']) ? (string)$row['name_ko'] : '');
}

function ksers_admin_mail_abstract_context($row) {
    $receipt = sprintf('A%d-%04d', (int)$row['year'], (int)$row['abstract_seq']);
    $final = isset($row['final_type']) ? trim((string)$row['final_type']) : '';
    if ($final === '' || $final === 'pending') {
        $final = isset($row['presentation_type']) ? (string)$row['presentation_type'] : '';
    }
    return [
        'receipt_no' => $receipt,
        'title' => trim((string)$row['title']),
        'presenter' => trim(isset($row['presenter']) ? (string)$row['presenter'] : ''),
        'final_type' => function_exists('ksers_abstract_type_en') ? ksers_abstract_type_en($final) : $final,
    ];
}

function ksers_admin_mail_pay_deadline($pdo, $year, $korea) {
    $raw = '';
    if (function_exists('ksers_setting')) {
        $setting = ksers_setting($pdo, $year);
        $raw = isset($setting['pay_date_to']) ? substr((string)$setting['pay_date_to'], 0, 10) : '';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) || $raw === '0000-00-00') {
        return function_exists('ksers_mail_temp_mark') ? ksers_mail_temp_mark() : '임시';
    }
    $stamp = strtotime($raw . ' 00:00:00');
    if (!$stamp) {
        return function_exists('ksers_mail_temp_mark') ? ksers_mail_temp_mark() : '임시';
    }
    if ($korea) {
        $week = ['일', '월', '화', '수', '목', '금', '토'][(int)date('w', $stamp)];
        return date('Y년 n월 j일', $stamp) . ' (' . $week . ')';
    }
    return date('F j, Y', $stamp);
}

function ksers_admin_mail_registration_context($pdo, $year, $row) {
    $korea = false;
    if (function_exists('ksers_country_profile')) {
        $korea = !empty(ksers_country_profile([
            'iso' => isset($row['country_iso']) ? $row['country_iso'] : '',
            'name_ko' => isset($row['country_ko']) ? $row['country_ko'] : '',
            'name_eng' => isset($row['country_en']) ? $row['country_en'] : '',
        ])['korea']);
    }
    if (!$korea && strtoupper(trim(isset($row['country']) ? (string)$row['country'] : '')) === 'KR') {
        $korea = true;
    }
    $koreanName = trim(isset($row['name_ko']) ? (string)$row['name_ko'] : '');
    $englishName = trim(trim(isset($row['first_name_eng']) ? (string)$row['first_name_eng'] : '') . ' ' . trim(isset($row['last_name_eng']) ? (string)$row['last_name_eng'] : ''));
    $name = $korea ? ($koreanName !== '' ? $koreanName : $englishName) : ($englishName !== '' ? $englishName : $koreanName);
    $participant = [
        'category' => isset($row['category']) ? $row['category'] : '',
        'ksers_member' => isset($row['ksers_member']) ? $row['ksers_member'] : '',
        'senior_lifetime' => isset($row['senior_lifetime']) ? $row['senior_lifetime'] : 0,
        'reg_date' => isset($row['reg_date']) ? $row['reg_date'] : '',
    ];
    $fee = '—';
    $feeDue = false;
    $category = '—';
    if (function_exists('ksers_participant_fee_code') && function_exists('ksers_payment_period_for')) {
        $feeCode = ksers_participant_fee_code($participant, $korea);
        $periodCode = ksers_payment_period_for(
            $participant['reg_date'],
            $year,
            function_exists('ksers_onsite_dates') ? ksers_onsite_dates($pdo, $year) : []
        );
        if ($feeCode !== '') {
            $matched = $pdo->prepare(
                "SELECT * FROM fee_item
                 WHERE year = ? AND state = 1 AND fee_code = ? AND (period_code = ? OR period_code = 'any')
                 ORDER BY seq"
            );
            $matched->execute([(int)$year, $feeCode, $periodCode]);
            $specific = [];
            $any = [];
            foreach ($matched->fetchAll(PDO::FETCH_ASSOC) as $feeRow) {
                if ($feeRow['period_code'] === 'any') {
                    $any[] = $feeRow;
                } else {
                    $specific[] = $feeRow;
                }
            }
            $chosen = function_exists('ksers_choose_fee_rows') ? ksers_choose_fee_rows($specific ? $specific : $any, $korea) : null;
            if ($chosen && (int)$chosen['amount'] > 0 && function_exists('ksers_registration_mail_money')) {
                $fee = ksers_registration_mail_money($chosen['amount'], function_exists('ksers_fee_currency') ? ksers_fee_currency($chosen) : 'KRW', $korea);
                $feeDue = true;
            }
        }
    }
    if (function_exists('ksers_registration_paid_category')) {
        $category = ksers_registration_paid_category($pdo, $participant, $year, $korea);
    }
    if (!$korea && ((isset($row['category']) ? (string)$row['category'] : '') === 'student' || (isset($row['category']) ? (string)$row['category'] : '') === 'nurse')) {
        $feeDue = false;
    }
    $seq = isset($row['participant_seq']) ? (int)$row['participant_seq'] : 0;
    return [
        'korea' => $korea ? '1' : '0',
        'reg_no' => $seq > 0 ? ('R' . (int)$year . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT)) : '',
        'name' => $name,
        'reg_category' => $category,
        'fee' => $fee,
        'fee_due' => $feeDue,
        'pay_deadline' => ksers_admin_mail_pay_deadline($pdo, $year, $korea),
    ];
}

function ksers_admin_mail_candidates($pdo, $year, $filter, $presentation, $accept, $registered, $userClass, $unpaid = false, $attendees = false) {
    $year = (int)$year;
    $rows = [];
    if ($unpaid) {
        $sql = "SELECT p.seq AS participant_seq, p.category, p.ksers_member, p.senior_lifetime, p.reg_date,
                m.seq AS member_seq, m.email, m.name_ko, m.first_name_eng, m.last_name_eng, m.user_class, m.country,
                c.iso AS country_iso, c.name_ko AS country_ko, c.name_eng AS country_en
            FROM participant_info p
            JOIN member_info m ON m.seq = p.member_seq
            LEFT JOIN code c ON c.majr = 1 AND c.iso = m.country
            WHERE p.year = ? AND m.is_admin = 0 AND p.cancelled_at IS NULL
              AND NOT EXISTS (
                SELECT 1 FROM payment_order o
                JOIN payment_line l ON l.order_seq = o.seq
                LEFT JOIN fee_item f ON f.seq = l.fee_seq
                WHERE o.member_seq = p.member_seq AND o.year = p.year AND o.status = 'paid'
                  AND (f.fee_code IS NULL OR f.fee_code NOT IN ('dinner_individual', 'dinner_group'))
              )
            ORDER BY p.seq DESC LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$year]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $member) {
            $context = ksers_admin_mail_registration_context($pdo, $year, $member);
            if (empty($context['fee_due'])) {
                continue;
            }
            $rows[] = [
                'email' => trim((string)$member['email']),
                'name' => ksers_admin_mail_person_name($member),
                'role' => '등록자',
                'member_seq' => (int)$member['member_seq'],
                'abstract_seq' => 0,
                'user_class' => (string)$member['user_class'],
                'context' => $context,
            ];
        }
        return ksers_admin_mail_unique_rows($rows);
    }
    if ($attendees) {
        $sql = "SELECT p.seq AS participant_seq, p.category, p.ksers_member, p.senior_lifetime, p.reg_date,
                m.seq AS member_seq, m.email, m.name_ko, m.first_name_eng, m.last_name_eng, m.user_class, m.country,
                c.iso AS country_iso, c.name_ko AS country_ko, c.name_eng AS country_en
            FROM participant_info p
            JOIN member_info m ON m.seq = p.member_seq
            LEFT JOIN code c ON c.majr = 1 AND c.iso = m.country
            WHERE p.year = ? AND m.is_admin = 0 AND p.cancelled_at IS NULL
            ORDER BY p.seq DESC LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$year]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $member) {
            $rows[] = [
                'email' => trim((string)$member['email']),
                'name' => ksers_admin_mail_person_name($member),
                'role' => '등록자',
                'member_seq' => (int)$member['member_seq'],
                'abstract_seq' => 0,
                'user_class' => (string)$member['user_class'],
                'context' => ksers_admin_mail_registration_context($pdo, $year, $member),
            ];
        }
        return ksers_admin_mail_unique_rows($rows);
    }
    $presenterSql = "(SELECT GROUP_CONCAT(TRIM(CONCAT(first_name, ' ', last_name)) ORDER BY sort_no, seq SEPARATOR ', ')
        FROM abstract_author WHERE abstract_seq = a.seq AND presenting = 1)";
    $paidExists = "EXISTS (SELECT 1 FROM payment_order o WHERE o.member_seq = m.seq AND o.year = m.year AND o.status = 'paid')";
    $authorPaid = "EXISTS (
        SELECT 1 FROM member_info pm
        JOIN payment_order o ON o.member_seq = pm.seq AND o.year = a.year AND o.status = 'paid'
        WHERE LOWER(pm.email) = LOWER(aa.email)
    )";
    if ($filter === 'all') {
        $sql = "SELECT m.seq AS member_seq, m.email, m.name_ko, m.first_name_eng, m.last_name_eng, m.user_class, m.country,
                c.iso AS country_iso, c.name_ko AS country_ko, c.name_eng AS country_en,
                p.seq AS participant_seq, p.category, p.ksers_member, p.senior_lifetime, p.reg_date
            FROM member_info m
            LEFT JOIN code c ON c.majr = 1 AND c.iso = m.country
            LEFT JOIN participant_info p ON p.member_seq = m.seq AND p.year = m.year
            WHERE m.year = ? AND m.is_admin = 0";
        $params = [$year];
        if ($userClass !== '') {
            $sql .= " AND m.user_class = ?";
            $params[] = $userClass;
        }
        if ($registered === 'yes') {
            $sql .= " AND " . $paidExists;
        } elseif ($registered === 'no') {
            $sql .= " AND NOT " . $paidExists;
        }
        if ($presentation !== '' || $accept !== '') {
            $sql .= " AND EXISTS (
                SELECT 1 FROM abstract_info a
                WHERE a.member_seq = m.seq AND a.year = m.year";
            if ($presentation !== '') {
                $sql .= " AND a.presentation_type = ?";
                $params[] = $presentation;
            }
            if ($accept !== '') {
                $sql .= " AND a.status = ?";
                $params[] = $accept;
            }
            $sql .= ")";
        }
        $sql .= " ORDER BY m.seq DESC LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $member) {
            $context = [];
            if (!empty($member['participant_seq'])) {
                $context = ksers_admin_mail_registration_context($pdo, $year, $member);
            }
            $rows[] = [
                'email' => trim((string)$member['email']),
                'name' => ksers_admin_mail_person_name($member),
                'role' => '회원',
                'member_seq' => (int)$member['member_seq'],
                'abstract_seq' => 0,
                'user_class' => (string)$member['user_class'],
                'context' => $context,
            ];
        }
        return ksers_admin_mail_unique_rows($rows);
    }
    if ($filter === 'not_submitted') {
        $sql = "SELECT m.seq AS member_seq, m.email, m.name_ko, m.first_name_eng, m.last_name_eng, m.user_class
            FROM member_info m
            WHERE m.year = ? AND m.is_admin = 0
              AND NOT EXISTS (
                SELECT 1 FROM abstract_info a
                WHERE a.member_seq = m.seq AND a.year = m.year AND a.status <> 'withdrawn'
              )";
        $params = [$year];
        if ($userClass !== '') {
            $sql .= " AND m.user_class = ?";
            $params[] = $userClass;
        }
        if ($registered === 'yes') {
            $sql .= " AND " . $paidExists;
        } elseif ($registered === 'no') {
            $sql .= " AND NOT " . $paidExists;
        }
        $sql .= " ORDER BY m.seq DESC LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $member) {
            $rows[] = [
                'email' => trim((string)$member['email']),
                'name' => ksers_admin_mail_person_name($member),
                'role' => '회원',
                'member_seq' => (int)$member['member_seq'],
                'abstract_seq' => 0,
                'user_class' => (string)$member['user_class'],
                'context' => [],
            ];
        }
        return ksers_admin_mail_unique_rows($rows);
    }
    if ($filter === 'unregistered_presenter' || $filter === 'withdraw_party' || $filter === 'grant_party') {
        $sql = "SELECT a.seq AS abstract_seq, a.year, a.title, a.presentation_type, a.final_type, a.status, a.member_seq,
                m.email AS submitter_email, m.name_ko, m.first_name_eng, m.last_name_eng, m.user_class,
                aa.email AS author_email, aa.first_name, aa.last_name, aa.presenting, aa.corresponding,
                {$presenterSql} AS presenter
            FROM abstract_info a
            JOIN member_info m ON m.seq = a.member_seq
            JOIN abstract_author aa ON aa.abstract_seq = a.seq
            WHERE a.year = ?
              AND NOT EXISTS (
                SELECT 1 FROM abstract_author p
                JOIN member_info pm ON LOWER(pm.email) = LOWER(p.email)
                JOIN payment_order o ON o.member_seq = pm.seq AND o.year = a.year AND o.status = 'paid'
                WHERE p.abstract_seq = a.seq AND p.presenting = 1
              )";
        $params = [$year];
        if ($filter === 'grant_party') {
            $sql = "SELECT a.seq AS abstract_seq, a.year, a.title, a.presentation_type, a.final_type, a.status, a.member_seq,
                m.email AS submitter_email, m.name_ko, m.first_name_eng, m.last_name_eng, m.user_class,
                aa.email AS author_email, aa.first_name, aa.last_name, aa.presenting, aa.corresponding,
                {$presenterSql} AS presenter
            FROM abstract_info a
            JOIN member_info m ON m.seq = a.member_seq
            JOIN abstract_author aa ON aa.abstract_seq = a.seq
            WHERE a.year = ? AND a.status = 'accepted'";
            $params = [$year];
        }
        if ($presentation !== '') {
            $sql .= " AND a.presentation_type = ?";
            $params[] = $presentation;
        }
        if ($accept !== '') {
            $sql .= " AND a.status = ?";
            $params[] = $accept;
        }
        if ($userClass !== '') {
            $sql .= " AND m.user_class = ?";
            $params[] = $userClass;
        }
        $sql .= " ORDER BY a.seq DESC, aa.sort_no, aa.seq LIMIT 400";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $context = ksers_admin_mail_abstract_context($item);
            $includeAuthor = false;
            $role = '';
            if ($filter === 'unregistered_presenter') {
                $includeAuthor = !empty($item['presenting']);
                $role = '발표자';
            } else {
                if (!empty($item['presenting'])) {
                    $includeAuthor = true;
                    $role = '발표자';
                } elseif (!empty($item['corresponding'])) {
                    $includeAuthor = true;
                    $role = '교신저자';
                }
            }
            if ($includeAuthor) {
                $rows[] = [
                    'email' => trim((string)$item['author_email']),
                    'name' => trim(trim((string)$item['first_name']) . ' ' . trim((string)$item['last_name'])),
                    'role' => $role,
                    'member_seq' => (int)$item['member_seq'],
                    'abstract_seq' => (int)$item['abstract_seq'],
                    'user_class' => (string)$item['user_class'],
                    'context' => $context,
                ];
            }
            if ($filter !== 'unregistered_presenter') {
                $rows[] = [
                    'email' => trim((string)$item['submitter_email']),
                    'name' => ksers_admin_mail_person_name($item),
                    'role' => '제출자',
                    'member_seq' => (int)$item['member_seq'],
                    'abstract_seq' => (int)$item['abstract_seq'],
                    'user_class' => (string)$item['user_class'],
                    'context' => $context,
                ];
            }
        }
        return ksers_admin_mail_unique_rows($rows);
    }
    $sql = "SELECT a.seq AS abstract_seq, a.year, a.title, a.presentation_type, a.final_type, a.status,
            m.seq AS member_seq, m.email, m.name_ko, m.first_name_eng, m.last_name_eng, m.user_class,
            {$presenterSql} AS presenter
        FROM abstract_info a
        JOIN member_info m ON m.seq = a.member_seq
        WHERE a.year = ?";
    $params = [$year];
    if ($presentation !== '') {
        $sql .= " AND a.presentation_type = ?";
        $params[] = $presentation;
    }
    if ($accept !== '') {
        $sql .= " AND a.status = ?";
        $params[] = $accept;
    }
    if ($userClass !== '') {
        $sql .= " AND m.user_class = ?";
        $params[] = $userClass;
    }
    if ($registered === 'yes') {
        $sql .= " AND " . $paidExists;
    } elseif ($registered === 'no') {
        $sql .= " AND NOT " . $paidExists;
    }
    $sql .= " ORDER BY a.seq DESC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $authorMap = [];
    $seqs = [];
    foreach ($items as $item) {
        $seqs[] = (int)$item['abstract_seq'];
    }
    if ($seqs) {
        $marks = implode(',', array_fill(0, count($seqs), '?'));
        $authorStmt = $pdo->prepare(
            "SELECT abstract_seq, first_name, last_name, email
             FROM abstract_author
             WHERE abstract_seq IN ($marks)
             ORDER BY sort_no, seq"
        );
        $authorStmt->execute($seqs);
        foreach ($authorStmt->fetchAll(PDO::FETCH_ASSOC) as $author) {
            $email = trim((string)$author['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $authorMap[(int)$author['abstract_seq']][] = [
                'email' => $email,
                'name' => trim(trim((string)$author['first_name']) . ' ' . trim((string)$author['last_name'])),
            ];
        }
    }
    foreach ($items as $item) {
        $abstractSeq = (int)$item['abstract_seq'];
        $rows[] = [
            'email' => trim((string)$item['email']),
            'name' => ksers_admin_mail_person_name($item),
            'role' => '제출자',
            'member_seq' => (int)$item['member_seq'],
            'abstract_seq' => $abstractSeq,
            'user_class' => (string)$item['user_class'],
            'context' => ksers_admin_mail_abstract_context($item),
            'authors' => isset($authorMap[$abstractSeq]) ? $authorMap[$abstractSeq] : [],
        ];
    }
    return ksers_admin_mail_unique_rows($rows);
}

function ksers_admin_mail_unique_rows($rows) {
    $seen = [];
    $unique = [];
    foreach ($rows as $row) {
        $email = strtolower(trim((string)$row['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$email])) {
            continue;
        }
        $seen[$email] = true;
        $row['email'] = trim((string)$row['email']);
        $unique[] = $row;
    }
    return $unique;
}

function ksers_admin_mail_history($pdo, $year, $failedOnly) {
    $sql = "SELECT seq, template_key, email, recipient_name, subject, status, fail_reason, reg_date
        FROM admin_mail_log WHERE year = ?";
    if ($failedOnly) {
        $sql .= " AND status = 'failed'";
    }
    $sql .= " ORDER BY seq DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ksers_admin_mail_send($pdo, $year, $templateKey, $subject, $title, $body, $recipients, $editedLang = '', $localeEdits = []) {
    $template = ksers_admin_mail_template($templateKey);
    $from = function_exists('ksers_password_mail_from') ? ksers_password_mail_from($pdo, $year) : 'noreply@ksers.kr';
    $insert = $pdo->prepare(
        "INSERT INTO admin_mail_log (year, template_key, email, recipient_name, subject, status, fail_reason, reg_date)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $sent = 0;
    $failedAccounts = [];
    $seen = [];
    foreach ($recipients as $recipient) {
        if (!is_array($recipient)) {
            continue;
        }
        $email = trim(isset($recipient['email']) ? (string)$recipient['email'] : '');
        $key = strtolower($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $name = trim(isset($recipient['name']) ? (string)$recipient['name'] : '');
        $context = isset($recipient['context']) && is_array($recipient['context']) ? $recipient['context'] : [];
        $vars = ksers_admin_mail_vars_from_row($context);
        $useTemplate = $template;
        $useSubject = $subject;
        $useTitle = $title;
        $useBody = $body;
        $lang = 'en';
        if (!empty($template['locales']['ko']) && !empty($template['locales']['en'])) {
            $korea = isset($context['korea']) && (string)$context['korea'] === '1';
            $lang = $korea ? 'ko' : 'en';
            $locale = $template['locales'][$lang];
            $useTemplate = array_merge($template, $locale);
            $edit = isset($localeEdits[$lang]) && is_array($localeEdits[$lang]) ? $localeEdits[$lang] : [];
            $useSubject = isset($edit['subject']) && trim((string)$edit['subject']) !== '' ? trim((string)$edit['subject']) : $locale['subject'];
            $useTitle = isset($edit['title']) && trim((string)$edit['title']) !== '' ? trim((string)$edit['title']) : $locale['title'];
            $useBody = isset($edit['body']) && trim((string)$edit['body']) !== '' ? trim((string)$edit['body']) : $locale['body'];
            $vars['{{desk_info}}'] = isset($edit['desk']) ? trim((string)$edit['desk']) : '';
        }
        $parts = ksers_admin_mail_render_parts($useTemplate, $useSubject, $useTitle, $useBody, $vars, $name, $email);
        $ok = false;
        try {
            $ok = ksers_send_mail($from, $email, $parts['subject'], $parts['body'], ksers_mail_render($lang, $parts['fields']));
        } catch (Exception $e) {
            $ok = false;
        }
        $reason = $ok ? '' : '메일 서버가 이 주소를 받지 못했습니다.';
        $insert->execute([
            (int)$year,
            $templateKey,
            $email,
            $name,
            function_exists('mb_substr') ? mb_substr($parts['subject'], 0, 255, 'UTF-8') : substr($parts['subject'], 0, 255),
            $ok ? 'sent' : 'failed',
            $reason,
        ]);
        if ($ok) {
            $sent++;
        } else {
            $failedAccounts[] = [
                'email' => $email,
                'name' => $name,
                'reason' => $reason,
            ];
        }
    }
    return [$sent, $failedAccounts];
}
