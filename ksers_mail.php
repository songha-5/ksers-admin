<?php

function ksers_mail_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ksers_mail_info_row($label, $value, $raw = false) {
    return '<tr>'
        . '<td width="34%" style="padding:10px 12px;background-color:#F5F7FA;border-bottom:1px solid #E1E5EB;color:#555555;font-weight:bold;vertical-align:top;">' . ksers_mail_h($label) . '</td>'
        . '<td style="padding:10px 12px;border-bottom:1px solid #E1E5EB;color:#1A1A1A;vertical-align:top;">' . ($raw ? (string)$value : ksers_mail_h($value)) . '</td>'
        . '</tr>';
}

function ksers_mail_temp_mark() {
    return '<strong style="color:#D32F2F;">임시</strong>';
}

function ksers_mail_paragraphs($paragraphs) {
    $html = '';
    foreach ($paragraphs as $paragraph) {
        $html .= '<p style="margin:0 0 16px 0;">' . ksers_mail_h($paragraph) . '</p>';
    }
    return $html;
}

function ksers_mail_strip_block($html, $name) {
    $pattern = '/<!-- BLOCK:' . preg_quote($name, '/') . '\b.*?<!-- \/BLOCK:' . preg_quote($name, '/') . ' -->/s';
    $stripped = preg_replace($pattern, '', $html);
    return $stripped === null ? $html : $stripped;
}

function ksers_mail_host_is_private($host) {
    $host = strtolower(trim((string)$host, "[]"));
    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || substr($host, -6) === '.local') {
        return true;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
    return false;
}

function ksers_mail_logo_url() {
    if (!function_exists('ksers_public_base')) {
        return '';
    }
    $base = ksers_public_base();
    if ($base === '') {
        return '';
    }
    $host = parse_url($base, PHP_URL_HOST);
    if (!is_string($host) || ksers_mail_host_is_private($host)) {
        return '';
    }
    if (strncasecmp($base, 'http://', 7) === 0) {
        $base = 'https://' . substr($base, 7);
    }
    return rtrim($base, '/') . '/mail/KSERS2027_mail_logo_mark.png';
}

function ksers_mail_apply_logo($html) {
    $html = (string)$html;
    $url = ksers_mail_logo_url();
    if ($url === '' || $html === '' || strpos($html, $url) !== false) {
        return $html;
    }
    $html = str_replace('cid:ksers-logo.png', $url, $html);
    return str_replace('src="/mail/KSERS2027_mail_logo_mark.png"', 'src="' . $url . '"', $html);
}

function ksers_mail_render($lang, $fields) {
    $lang = $lang === 'en' ? 'en' : 'ko';
    $path = __DIR__ . '/mail/KSERS2027_mail_template_' . ($lang === 'en' ? 'EN' : 'KO') . '.html';
    $html = file_get_contents($path);
    if ($html === false) {
        return '';
    }
    if (trim((string)(isset($fields['info_rows']) ? $fields['info_rows'] : '')) === '') {
        $html = ksers_mail_strip_block($html, 'info_table');
    }
    if (trim((string)(isset($fields['button_url']) ? $fields['button_url'] : '')) === '') {
        $html = ksers_mail_strip_block($html, 'button');
    }
    if (trim((string)(isset($fields['notice_text']) ? $fields['notice_text'] : '')) === '') {
        $html = ksers_mail_strip_block($html, 'notice');
    }
    $htmlFields = ['body_paragraphs' => true, 'info_rows' => true, 'notice_text' => true];
    if (!isset($fields['logo_mark_url']) || trim((string)$fields['logo_mark_url']) === '' || $fields['logo_mark_url'] === 'cid:ksers-logo.png') {
        $logoUrl = ksers_mail_logo_url();
        $fields['logo_mark_url'] = $logoUrl !== '' ? $logoUrl : 'cid:ksers-logo.png';
    }
    if (!isset($fields['website_url']) || trim((string)$fields['website_url']) === '') {
        $fields['website_url'] = 'https://ksers.kr/2027';
    }
    if (!isset($fields['website_url_text']) || trim((string)$fields['website_url_text']) === '') {
        $fields['website_url_text'] = 'ksers.kr/2027';
    }
    $replace = [];
    foreach ($fields as $key => $value) {
        $replace['{{' . $key . '}}'] = isset($htmlFields[$key]) ? (string)$value : ksers_mail_h($value);
    }
    $html = strtr($html, $replace);
    $cleaned = preg_replace('/<!--.*?-->/s', '', $html);
    return $cleaned === null ? $html : $cleaned;
}

function ksers_signup_mail_parts($year, $korea, $email, $name, $affiliation, $loginUrl, $myPageUrl) {
    $year = (int)$year;
    $name = trim((string)$name);
    $affiliation = trim((string)$affiliation);
    $hereLink = '<a href="' . ksers_mail_h($myPageUrl) . '" target="_blank" style="color:#0086C9;font-weight:bold;">'
        . ksers_mail_h($korea ? '여기' : 'here') . '</a>';
    if ($korea) {
        $subject = 'KSERS ' . $year . ' 회원가입이 완료되었습니다';
        $noticeText = '본 계정은 KSERS ' . $year . ' 대회 전용 계정입니다. 회원정보는 My Page에서 확인·수정할 수 있습니다.' . "\n" . '개인정보를 확인·수정하시려면 여기를 눌러 주세요.';
        $noticeHtml = ksers_mail_h('본 계정은 KSERS ' . $year . ' 대회 전용 계정입니다. 회원정보는 My Page에서 확인·수정할 수 있습니다.')
            . '<br>' . ksers_mail_h('* 개인정보를 확인·수정하시려면 ') . $hereLink . ksers_mail_h('를 눌러 주세요.');
        $body = "회원가입이 완료되었습니다.\n\n아이디(이메일): " . $email . "\n이름: " . $name . "\n소속: " . $affiliation . "\n\n로그인: " . $loginUrl . "\n\n" . $noticeText . "\n" . $myPageUrl . "\n";
        $fields = [
            'mail_subject' => $subject,
            'preheader' => '회원가입이 완료되었습니다. 가입 정보는 메일에서 확인해 주세요.',
            'mail_category' => '회원가입',
            'mail_title' => '회원가입이 완료되었습니다',
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs(['KSERS ' . $year . ' 회원가입이 완료되었습니다. 가입 정보는 아래와 같습니다.']),
            'info_rows' => ksers_mail_info_row('아이디(이메일)', $email)
                . ksers_mail_info_row('이름', $name)
                . ksers_mail_info_row('소속', $affiliation),
            'button_url' => $loginUrl,
            'button_label' => '로그인하기',
            'notice_text' => $noticeHtml,
            'send_reason' => '회원가입',
            'recipient_email' => $email,
        ];
        $lang = 'ko';
    } else {
        $subject = 'KSERS ' . $year . ' Your account has been created';
        $noticeText = 'This account is for KSERS ' . $year . ' only. If you wish to view or modify your personal information, please visit My Page.' . "\n" . 'If you wish to see and modify your personal information, please click here.';
        $noticeHtml = ksers_mail_h('This account is for KSERS ' . $year . ' only. If you wish to view or modify your personal information, please visit My Page.')
            . '<br>' . ksers_mail_h('* If you wish to see and modify your personal information, please click ') . $hereLink . ksers_mail_h('.');
        $body = "Your KSERS " . $year . " account has been successfully created. Your account details are as follows.\n\nID (Email): " . $email . "\nName: " . $name . "\nAffiliation: " . $affiliation . "\n\nLog in: " . $loginUrl . "\n\n" . $noticeText . "\n" . $myPageUrl . "\n";
        $fields = [
            'mail_subject' => $subject,
            'preheader' => 'Your KSERS ' . $year . ' account has been successfully created.',
            'mail_category' => 'Sign up',
            'mail_title' => 'Your account has been created',
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs(['Your KSERS ' . $year . ' account has been successfully created. Your account details are as follows.']),
            'info_rows' => ksers_mail_info_row('ID (Email)', $email)
                . ksers_mail_info_row('Name', $name)
                . ksers_mail_info_row('Affiliation', $affiliation),
            'button_url' => $loginUrl,
            'button_label' => 'Log in',
            'notice_text' => $noticeHtml,
            'send_reason' => 'sign-up',
            'recipient_email' => $email,
        ];
        $lang = 'en';
    }
    return [
        'lang' => $lang,
        'subject' => $subject,
        'body' => $body,
        'fields' => $fields,
    ];
}

function ksers_signup_notify($pdo, $year, $korea, $email, $name, $affiliation) {
    $email = trim((string)$email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $year = (int)$year;
    $from = function_exists('ksers_password_mail_from')
        ? ksers_password_mail_from($pdo, $year)
        : 'noreply@ksers.kr';
    $publicBase = function_exists('ksers_public_base') ? ksers_public_base() : '';
    $parts = ksers_signup_mail_parts(
        $year,
        $korea,
        $email,
        $name,
        $affiliation,
        $publicBase . '/service/login.php?year=' . $year,
        $publicBase . '/service/mypage.php'
    );
    return ksers_send_mail($from, $email, $parts['subject'], $parts['body'], ksers_mail_render($parts['lang'], $parts['fields']));
}

function ksers_password_changed_at_kst() {
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul'));
    return $now->format('Y-m-d H:i') . ' (KST)';
}

function ksers_password_changed_mail_parts($year, $korea, $email, $name, $changedAt, $loginUrl) {
    $year = (int)$year;
    $name = trim((string)$name);
    $office = 'ksers2027@ksers.kr';
    $officeLink = '<a href="mailto:' . ksers_mail_h($office) . '" style="color:#0086C9;font-weight:bold;">' . ksers_mail_h($office) . '</a>';
    if ($korea) {
        if ($name === '') {
            $name = '회원';
        }
        $subject = 'KSERS ' . $year . ' 비밀번호가 변경되었습니다';
        $noticeText = '본인이 변경하지 않았다면 사무국(' . $office . ')으로 바로 연락해 주세요.';
        $noticeHtml = ksers_mail_h('본인이 변경하지 않았다면 사무국(') . $officeLink . ksers_mail_h(')으로 바로 연락해 주세요.');
        $body = "비밀번호가 변경되었습니다. 변경 정보는 아래와 같습니다.\n\n아이디(이메일): " . $email . "\n변경 일시: " . $changedAt . "\n\n로그인: " . $loginUrl . "\n\n" . $noticeText . "\n";
        $fields = [
            'mail_subject' => $subject,
            'preheader' => '비밀번호가 변경되었습니다. 본인이 변경하지 않았다면 사무국으로 연락해 주세요.',
            'mail_category' => '비밀번호 변경',
            'mail_title' => '비밀번호가 변경되었습니다',
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs(['비밀번호가 변경되었습니다. 변경 정보는 아래와 같습니다.']),
            'info_rows' => ksers_mail_info_row('아이디(이메일)', $email)
                . ksers_mail_info_row('변경 일시', $changedAt),
            'button_url' => $loginUrl,
            'button_label' => '로그인하기',
            'notice_text' => $noticeHtml,
            'send_reason' => '비밀번호 변경',
            'recipient_email' => $email,
        ];
        $lang = 'ko';
    } else {
        if ($name === '') {
            $name = 'Member';
        }
        $subject = 'KSERS ' . $year . ' Your Password Has Been Changed';
        $noticeText = 'If you did not make this change, please contact the Secretariat (' . $office . ') immediately.';
        $noticeHtml = ksers_mail_h('If you did not make this change, please contact the Secretariat (') . $officeLink . ksers_mail_h(') immediately.');
        $body = "Your password has been changed. The details are as follows.\n\nID (E-mail): " . $email . "\nChanged on: " . $changedAt . "\n\nLog in: " . $loginUrl . "\n\n" . $noticeText . "\n";
        $fields = [
            'mail_subject' => $subject,
            'preheader' => 'Your password has been changed. Contact the Secretariat if you did not make this change.',
            'mail_category' => 'Password change',
            'mail_title' => 'Your Password Has Been Changed',
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs(['Your password has been changed. The details are as follows.']),
            'info_rows' => ksers_mail_info_row('ID (E-mail)', $email)
                . ksers_mail_info_row('Changed on', $changedAt),
            'button_url' => $loginUrl,
            'button_label' => 'Log in',
            'notice_text' => $noticeHtml,
            'send_reason' => 'password change',
            'recipient_email' => $email,
        ];
        $lang = 'en';
    }
    return [
        'lang' => $lang,
        'subject' => $subject,
        'body' => $body,
        'fields' => $fields,
    ];
}

function ksers_password_changed_notify($pdo, $memberSeq) {
    try {
        $memberStmt = $pdo->prepare(
            "SELECT email, year, country, name_ko, first_name_eng, last_name_eng FROM member_info WHERE seq = ?"
        );
        $memberStmt->execute([(int)$memberSeq]);
        $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
        if (!$member) {
            return false;
        }
        $email = trim((string)$member['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $year = (int)$member['year'];
        $countryStmt = $pdo->prepare("SELECT minr, name_ko, name_eng, dial, iso FROM code WHERE majr = 1 AND state = 1 AND iso = ?");
        $countryStmt->execute([isset($member['country']) ? (string)$member['country'] : '']);
        $countryRow = $countryStmt->fetch(PDO::FETCH_ASSOC);
        $korea = function_exists('ksers_country_profile') && ksers_country_profile($countryRow ? $countryRow : [])['korea'];
        $name = $korea
            ? trim((string)$member['name_ko'])
            : trim(trim((string)$member['first_name_eng']) . ' ' . trim((string)$member['last_name_eng']));
        $from = function_exists('ksers_password_mail_from')
            ? ksers_password_mail_from($pdo, $year)
            : 'noreply@ksers.kr';
        $publicBase = function_exists('ksers_public_base') ? ksers_public_base() : '';
        $parts = ksers_password_changed_mail_parts(
            $year,
            $korea,
            $email,
            $name,
            ksers_password_changed_at_kst(),
            $publicBase . '/service/login.php?year=' . $year
        );
        return ksers_send_mail($from, $email, $parts['subject'], $parts['body'], ksers_mail_render($parts['lang'], $parts['fields']));
    } catch (Exception $e) {
        error_log('ksers mail: password change notice failed');
        return false;
    }
}

function ksers_mail_byte_size($bytes) {
    $bytes = (int)$bytes;
    if ($bytes >= 1048576) {
        return number_format(max(1, (int)round($bytes / 1048576))) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format(max(1, (int)round($bytes / 1024))) . ' KB';
    }
    if ($bytes > 0) {
        return $bytes . ' B';
    }
    return '';
}

function ksers_abstract_type_en($code) {
    $map = [
        'oral' => 'Oral',
        'eposter' => 'E-Poster',
        'poster' => 'Poster',
        'either' => 'No preference',
        'video' => 'Video',
        'abstract_video_1' => 'Video',
        'mentor' => 'Mentor-Mentee Video',
        'mentee' => 'Mentor-Mentee Video',
        'abstract_video_2' => 'Mentor-Mentee Video',
    ];
    $code = (string)$code;
    return isset($map[$code]) ? $map[$code] : $code;
}

function ksers_abstract_topic_en($pdo, $year, $stored) {
    $stored = trim((string)$stored);
    if ($stored === '') {
        return '';
    }
    $stmt = $pdo->prepare("SELECT seq, name_ko, name_en FROM abstract_topic WHERE year = ?");
    $stmt->execute([(int)$year]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['seq'] === $stored || (string)$row['name_ko'] === $stored || (string)$row['name_en'] === $stored) {
            $english = trim((string)$row['name_en']);
            return $english !== '' ? $english : trim((string)$row['name_ko']);
        }
    }
    return $stored;
}

function ksers_abstract_author_label($author) {
    $name = trim(trim((string)$author['first_name']) . ' ' . trim((string)$author['last_name']));
    $roles = [];
    if (!empty($author['presenting'])) {
        $roles[] = 'Presenting';
    }
    if (!empty($author['corresponding'])) {
        $roles[] = 'Corresponding';
    }
    if (!empty($author['mentor'])) {
        $roles[] = 'Mentor';
    }
    if ($roles) {
        $name .= ' (' . implode(') (', $roles) . ')';
    }
    return $name;
}

function ksers_abstract_received_mail_parts($year, $receiptNo, $title, $typeValue, $topic, $authors, $abstractFile, $videoLine, $myPageUrl, $recipientName, $recipientEmail) {
    $year = (int)$year;
    $recipientName = trim((string)$recipientName);
    if ($recipientName === '') {
        $recipientName = 'Author';
    }
    $subject = 'KSERS ' . $year . ' Abstract Submission Received (' . $receiptNo . ')';
    $intro = 'Thank you for submitting your abstract to KSERS ' . $year . '. Your submission details are as follows.';
    $notice = 'You can edit or delete your abstract on My Page until January 14 (Thu), 2027. After the deadline, no changes can be made.';
    $videoNotice = '';
    if ($videoLine !== null) {
        $videoNotice = 'Please upload your video in My Page > Video Submission by January 14 (Thu), 2027.';
    }
    $infoRows = ksers_mail_info_row('Receipt No.', $receiptNo)
        . ksers_mail_info_row('Title', $title)
        . ksers_mail_info_row('Presentation Type', $typeValue)
        . ksers_mail_info_row('Topic', $topic)
        . ksers_mail_info_row('Authors', $authors)
        . ksers_mail_info_row('Abstract File', $abstractFile);
    $body = $intro . "\n\n"
        . "Receipt No.: " . $receiptNo . "\n"
        . "Title: " . $title . "\n"
        . "Presentation Type: " . $typeValue . "\n"
        . "Topic: " . $topic . "\n"
        . "Authors: " . $authors . "\n"
        . "Abstract File: " . $abstractFile . "\n\n"
        . "My Page: " . $myPageUrl . "\n\n"
        . $notice . "\n"
        . ($videoNotice !== '' ? $videoNotice . "\n" : '');
    $noticeHtml = ksers_mail_h($notice);
    if ($videoNotice !== '') {
        $noticeHtml .= '<br>' . ksers_mail_h($videoNotice);
    }
    return [
        'lang' => 'en',
        'subject' => $subject,
        'body' => $body,
        'fields' => [
            'mail_subject' => $subject,
            'preheader' => 'Your abstract ' . $receiptNo . ' has been received.',
            'mail_category' => 'Abstract',
            'mail_title' => 'Your abstract has been received',
            'recipient_name' => $recipientName,
            'body_paragraphs' => ksers_mail_paragraphs([$intro]),
            'info_rows' => $infoRows,
            'button_url' => $myPageUrl,
            'button_label' => 'Go to My Page',
            'notice_text' => $noticeHtml,
            'send_reason' => 'abstract submission',
            'recipient_email' => $recipientEmail,
        ],
    ];
}

function ksers_abstract_received_notify($pdo, $abstractSeq) {
    try {
        $abstractStmt = $pdo->prepare("SELECT * FROM abstract_info WHERE seq = ?");
        $abstractStmt->execute([(int)$abstractSeq]);
        $abstract = $abstractStmt->fetch(PDO::FETCH_ASSOC);
        if (!$abstract) {
            return false;
        }
        $year = (int)$abstract['year'];
        $receiptNo = sprintf('A%d-%04d', $year, (int)$abstract['seq']);
        $authorStmt = $pdo->prepare(
            "SELECT first_name, last_name, email, presenting, corresponding, mentor
             FROM abstract_author WHERE abstract_seq = ? ORDER BY sort_no, seq"
        );
        $authorStmt->execute([(int)$abstract['seq']]);
        $authors = $authorStmt->fetchAll(PDO::FETCH_ASSOC);
        $authorLines = [];
        foreach ($authors as $author) {
            $line = ksers_abstract_author_label($author);
            if ($line !== '') {
                $authorLines[] = $line;
            }
        }
        $memberStmt = $pdo->prepare("SELECT email, name_ko, first_name_eng, last_name_eng FROM member_info WHERE seq = ?");
        $memberStmt->execute([(int)$abstract['member_seq']]);
        $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
        $submitterName = '';
        $submitterEmail = '';
        if ($member) {
            $submitterEmail = trim((string)$member['email']);
            $submitterName = trim(trim((string)$member['first_name_eng']) . ' ' . trim((string)$member['last_name_eng']));
            if ($submitterName === '') {
                $submitterName = trim((string)$member['name_ko']);
            }
        }
        $recipients = [];
        $addRecipient = function ($email, $name) use (&$recipients) {
            $email = trim((string)$email);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $key = mb_strtolower($email, 'UTF-8');
            if (isset($recipients[$key])) {
                return;
            }
            $recipients[$key] = [
                'email' => $email,
                'name' => trim((string)$name),
            ];
        };
        $addRecipient($submitterEmail, $submitterName);
        foreach ($authors as $author) {
            if (empty($author['presenting']) && empty($author['corresponding'])) {
                continue;
            }
            $addRecipient($author['email'], trim(trim((string)$author['first_name']) . ' ' . trim((string)$author['last_name'])));
        }
        if (!$recipients) {
            return false;
        }
        $typeCode = (string)$abstract['presentation_type'];
        $videoKind = function_exists('ksers_video_kind_for_presentation') ? ksers_video_kind_for_presentation($typeCode) : '';
        $videoLine = null;
        if ($videoKind !== '') {
            $videoStmt = $pdo->prepare(
                "SELECT original_name, file_size FROM member_submission_file
                 WHERE abstract_seq = ? AND year = ? AND kind = ?
                 ORDER BY seq DESC LIMIT 1"
            );
            $videoStmt->execute([(int)$abstract['seq'], $year, $videoKind]);
            $video = $videoStmt->fetch(PDO::FETCH_ASSOC);
            $videoName = $video ? trim((string)$video['original_name']) : '';
            $videoSize = $video ? ksers_mail_byte_size($video['file_size']) : '';
            if ($videoName === '') {
                $videoLine = '—';
            } elseif ($videoSize !== '') {
                $videoLine = $videoName . ' (' . $videoSize . ')';
            } else {
                $videoLine = $videoName;
            }
        }
        $abstractFile = trim((string)$abstract['file_name']);
        if ($abstractFile === '') {
            $abstractFile = '—';
        }
        $topic = ksers_abstract_topic_en($pdo, $year, $abstract['topic']);
        if ($topic === '') {
            $topic = '—';
        }
        $title = trim((string)$abstract['title']);
        $authorsText = $authorLines ? implode(', ', $authorLines) : '—';
        $from = function_exists('ksers_password_mail_from')
            ? ksers_password_mail_from($pdo, $year)
            : 'noreply@ksers.kr';
        $publicBase = function_exists('ksers_public_base') ? ksers_public_base() : '';
        $myPageUrl = $publicBase . '/service/abstract.php';
        $sent = false;
        foreach ($recipients as $recipient) {
            $parts = ksers_abstract_received_mail_parts(
                $year,
                $receiptNo,
                $title,
                ksers_abstract_type_en($typeCode),
                $topic,
                $authorsText,
                $abstractFile,
                $videoLine,
                $myPageUrl,
                $recipient['name'],
                $recipient['email']
            );
            if (ksers_send_mail($from, $recipient['email'], $parts['subject'], $parts['body'], ksers_mail_render('en', $parts['fields']))) {
                $sent = true;
            }
        }
        return $sent;
    } catch (Exception $e) {
        error_log('ksers mail: abstract submission notice failed');
        return false;
    }
}

function ksers_abstract_mail_mark($value, $updated) {
    $value = trim((string)$value);
    if ($value === '') {
        $value = '—';
    }
    if ($updated) {
        $value .= ' (updated)';
    }
    return $value;
}

function ksers_abstract_updated_mail_parts($year, $receiptNo, $updatedAt, $title, $typeValue, $topic, $authors, $abstractFile, $videoLine, $myPageUrl, $recipientName, $recipientEmail) {
    $year = (int)$year;
    $recipientName = trim((string)$recipientName);
    if ($recipientName === '') {
        $recipientName = 'Author';
    }
    $subject = 'KSERS ' . $year . ' Abstract Updated (' . $receiptNo . ')';
    $intro = 'Your abstract has been successfully updated. The current details of your submission are as follows.';
    $notice = 'You can continue to edit your abstract until January 14 (Thu), 2027. If you did not make this change, please contact the Secretariat.';
    $infoRows = ksers_mail_info_row('Receipt No.', $receiptNo)
        . ksers_mail_info_row('Updated on', $updatedAt)
        . ksers_mail_info_row('Title', $title)
        . ksers_mail_info_row('Presentation Type', $typeValue)
        . ksers_mail_info_row('Topic', $topic)
        . ksers_mail_info_row('Authors', $authors)
        . ksers_mail_info_row('Abstract File', $abstractFile);
    $videoText = '';
    if ($videoLine !== null) {
        $infoRows .= ksers_mail_info_row('Video File', $videoLine);
        $videoText = "\nVideo File: " . $videoLine;
    }
    $body = $intro . "\n\n"
        . "Receipt No.: " . $receiptNo . "\n"
        . "Updated on: " . $updatedAt . "\n"
        . "Title: " . $title . "\n"
        . "Presentation Type: " . $typeValue . "\n"
        . "Topic: " . $topic . "\n"
        . "Authors: " . $authors . "\n"
        . "Abstract File: " . $abstractFile
        . $videoText . "\n\n"
        . "My Page: " . $myPageUrl . "\n\n"
        . $notice . "\n";
    return [
        'lang' => 'en',
        'subject' => $subject,
        'body' => $body,
        'fields' => [
            'mail_subject' => $subject,
            'preheader' => 'Your abstract ' . $receiptNo . ' has been updated.',
            'mail_category' => 'Abstract',
            'mail_title' => 'Your abstract has been updated',
            'recipient_name' => $recipientName,
            'body_paragraphs' => ksers_mail_paragraphs([$intro]),
            'info_rows' => $infoRows,
            'button_url' => $myPageUrl,
            'button_label' => 'Go to My Page',
            'notice_text' => ksers_mail_h($notice),
            'send_reason' => 'abstract update',
            'recipient_email' => $recipientEmail,
        ],
    ];
}

function ksers_abstract_updated_notify($pdo, $abstractSeq, $fileUpdated, $videoUpdated) {
    try {
        $abstractStmt = $pdo->prepare("SELECT * FROM abstract_info WHERE seq = ?");
        $abstractStmt->execute([(int)$abstractSeq]);
        $abstract = $abstractStmt->fetch(PDO::FETCH_ASSOC);
        if (!$abstract) {
            return false;
        }
        $year = (int)$abstract['year'];
        $receiptNo = sprintf('A%d-%04d', $year, (int)$abstract['seq']);
        $authorStmt = $pdo->prepare(
            "SELECT first_name, last_name, email, presenting, corresponding, mentor
             FROM abstract_author WHERE abstract_seq = ? ORDER BY sort_no, seq"
        );
        $authorStmt->execute([(int)$abstract['seq']]);
        $authors = $authorStmt->fetchAll(PDO::FETCH_ASSOC);
        $authorLines = [];
        foreach ($authors as $author) {
            $line = ksers_abstract_author_label($author);
            if ($line !== '') {
                $authorLines[] = $line;
            }
        }
        $memberStmt = $pdo->prepare("SELECT email, name_ko, first_name_eng, last_name_eng FROM member_info WHERE seq = ?");
        $memberStmt->execute([(int)$abstract['member_seq']]);
        $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
        $submitterName = '';
        $submitterEmail = '';
        if ($member) {
            $submitterEmail = trim((string)$member['email']);
            $submitterName = trim(trim((string)$member['first_name_eng']) . ' ' . trim((string)$member['last_name_eng']));
            if ($submitterName === '') {
                $submitterName = trim((string)$member['name_ko']);
            }
        }
        $recipients = [];
        $addRecipient = function ($email, $name) use (&$recipients) {
            $email = trim((string)$email);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $key = mb_strtolower($email, 'UTF-8');
            if (isset($recipients[$key])) {
                return;
            }
            $recipients[$key] = [
                'email' => $email,
                'name' => trim((string)$name),
            ];
        };
        $addRecipient($submitterEmail, $submitterName);
        foreach ($authors as $author) {
            if (empty($author['presenting']) && empty($author['corresponding'])) {
                continue;
            }
            $addRecipient($author['email'], trim(trim((string)$author['first_name']) . ' ' . trim((string)$author['last_name'])));
        }
        if (!$recipients) {
            return false;
        }
        $typeCode = (string)$abstract['presentation_type'];
        $videoKind = function_exists('ksers_video_kind_for_presentation') ? ksers_video_kind_for_presentation($typeCode) : '';
        $videoLine = null;
        if ($videoKind !== '') {
            $videoStmt = $pdo->prepare(
                "SELECT original_name, file_size FROM member_submission_file
                 WHERE abstract_seq = ? AND year = ? AND kind = ?
                 ORDER BY seq DESC LIMIT 1"
            );
            $videoStmt->execute([(int)$abstract['seq'], $year, $videoKind]);
            $video = $videoStmt->fetch(PDO::FETCH_ASSOC);
            $videoName = $video ? trim((string)$video['original_name']) : '';
            $videoSize = $video ? ksers_mail_byte_size($video['file_size']) : '';
            if ($videoName === '') {
                $videoLine = '—';
            } elseif ($videoSize !== '') {
                $videoLine = $videoName . ' (' . $videoSize . ')';
            } else {
                $videoLine = $videoName;
            }
            $videoLine = ksers_abstract_mail_mark($videoLine, $videoUpdated);
        }
        $abstractFile = ksers_abstract_mail_mark(trim((string)$abstract['file_name']), $fileUpdated);
        $topic = ksers_abstract_topic_en($pdo, $year, $abstract['topic']);
        if ($topic === '') {
            $topic = '—';
        }
        $title = trim((string)$abstract['title']);
        $authorsText = $authorLines ? implode(', ', $authorLines) : '—';
        $from = function_exists('ksers_password_mail_from')
            ? ksers_password_mail_from($pdo, $year)
            : 'noreply@ksers.kr';
        $publicBase = function_exists('ksers_public_base') ? ksers_public_base() : '';
        $myPageUrl = $publicBase . '/service/abstract.php';
        $updatedAt = ksers_password_changed_at_kst();
        $sent = false;
        foreach ($recipients as $recipient) {
            $parts = ksers_abstract_updated_mail_parts(
                $year,
                $receiptNo,
                $updatedAt,
                $title,
                ksers_abstract_type_en($typeCode),
                $topic,
                $authorsText,
                $abstractFile,
                $videoLine,
                $myPageUrl,
                $recipient['name'],
                $recipient['email']
            );
            if (ksers_send_mail($from, $recipient['email'], $parts['subject'], $parts['body'], ksers_mail_render('en', $parts['fields']))) {
                $sent = true;
            }
        }
        return $sent;
    } catch (Exception $e) {
        error_log('ksers mail: abstract update notice failed');
        return false;
    }
}

function ksers_abstract_deleted_mail_parts($year, $receiptNo, $title, $deletedAt, $recipientName, $recipientEmail) {
    $year = (int)$year;
    $recipientName = trim((string)$recipientName);
    if ($recipientName === '') {
        $recipientName = 'Author';
    }
    $office = 'ksers2027@ksers.kr';
    $officeLink = '<a href="mailto:' . ksers_mail_h($office) . '" style="color:#0086C9;font-weight:bold;">' . ksers_mail_h($office) . '</a>';
    $subject = 'KSERS ' . $year . ' Abstract Deleted (' . $receiptNo . ')';
    $intro = 'The following abstract submitted to KSERS ' . $year . ' has been deleted.';
    $noticeText = 'Deleted abstracts cannot be recovered. If you did not delete this abstract, please contact the Secretariat (' . $office . '). New abstracts can be submitted until January 14 (Thu), 2027.';
    $noticeHtml = ksers_mail_h('Deleted abstracts cannot be recovered. If you did not delete this abstract, please contact the Secretariat (')
        . $officeLink
        . ksers_mail_h('). New abstracts can be submitted until January 14 (Thu), 2027.');
    $body = $intro . "\n\n"
        . "Receipt No.: " . $receiptNo . "\n"
        . "Title: " . $title . "\n"
        . "Deleted on: " . $deletedAt . "\n\n"
        . $noticeText . "\n";
    return [
        'lang' => 'en',
        'subject' => $subject,
        'body' => $body,
        'fields' => [
            'mail_subject' => $subject,
            'preheader' => 'Your abstract ' . $receiptNo . ' has been deleted.',
            'mail_category' => 'Abstract',
            'mail_title' => 'Your abstract has been deleted',
            'recipient_name' => $recipientName,
            'body_paragraphs' => ksers_mail_paragraphs([$intro]),
            'info_rows' => ksers_mail_info_row('Receipt No.', $receiptNo)
                . ksers_mail_info_row('Title', $title)
                . ksers_mail_info_row('Deleted on', $deletedAt),
            'button_url' => '',
            'button_label' => '',
            'notice_text' => $noticeHtml,
            'send_reason' => 'abstract deletion',
            'recipient_email' => $recipientEmail,
        ],
    ];
}

function ksers_abstract_deleted_notify($pdo, $abstractSeq) {
    try {
        $abstractStmt = $pdo->prepare("SELECT seq, year, member_seq, title FROM abstract_info WHERE seq = ?");
        $abstractStmt->execute([(int)$abstractSeq]);
        $abstract = $abstractStmt->fetch(PDO::FETCH_ASSOC);
        if (!$abstract) {
            return false;
        }
        $year = (int)$abstract['year'];
        $receiptNo = sprintf('A%d-%04d', $year, (int)$abstract['seq']);
        $title = trim((string)$abstract['title']);
        if ($title === '') {
            $title = '—';
        }
        $authorStmt = $pdo->prepare(
            "SELECT first_name, last_name, email, presenting, corresponding
             FROM abstract_author WHERE abstract_seq = ? ORDER BY sort_no, seq"
        );
        $authorStmt->execute([(int)$abstract['seq']]);
        $authors = $authorStmt->fetchAll(PDO::FETCH_ASSOC);
        $memberStmt = $pdo->prepare("SELECT email, name_ko, first_name_eng, last_name_eng FROM member_info WHERE seq = ?");
        $memberStmt->execute([(int)$abstract['member_seq']]);
        $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
        $submitterName = '';
        $submitterEmail = '';
        if ($member) {
            $submitterEmail = trim((string)$member['email']);
            $submitterName = trim(trim((string)$member['first_name_eng']) . ' ' . trim((string)$member['last_name_eng']));
            if ($submitterName === '') {
                $submitterName = trim((string)$member['name_ko']);
            }
        }
        $recipients = [];
        $addRecipient = function ($email, $name) use (&$recipients) {
            $email = trim((string)$email);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $key = mb_strtolower($email, 'UTF-8');
            if (isset($recipients[$key])) {
                return;
            }
            $recipients[$key] = [
                'email' => $email,
                'name' => trim((string)$name),
            ];
        };
        $addRecipient($submitterEmail, $submitterName);
        foreach ($authors as $author) {
            if (empty($author['presenting']) && empty($author['corresponding'])) {
                continue;
            }
            $addRecipient($author['email'], trim(trim((string)$author['first_name']) . ' ' . trim((string)$author['last_name'])));
        }
        if (!$recipients) {
            return false;
        }
        $from = function_exists('ksers_password_mail_from')
            ? ksers_password_mail_from($pdo, $year)
            : 'noreply@ksers.kr';
        $deletedAt = ksers_password_changed_at_kst();
        $sent = false;
        foreach ($recipients as $recipient) {
            $parts = ksers_abstract_deleted_mail_parts(
                $year,
                $receiptNo,
                $title,
                $deletedAt,
                $recipient['name'],
                $recipient['email']
            );
            if (ksers_send_mail($from, $recipient['email'], $parts['subject'], $parts['body'], ksers_mail_render('en', $parts['fields']))) {
                $sent = true;
            }
        }
        return $sent;
    } catch (Exception $e) {
        error_log('ksers mail: abstract deletion notice failed');
        return false;
    }
}

function ksers_abstract_withdrawn_mail_parts($year, $receiptNo, $title, $withdrawnAt, $recipientName, $recipientEmail) {
    $year = (int)$year;
    $recipientName = trim((string)$recipientName);
    if ($recipientName === '') {
        $recipientName = 'Author';
    }
    $subject = 'KSERS ' . $year . ' Abstract Withdrawn (' . $receiptNo . ')';
    $intro = 'As requested, the following abstract has been withdrawn from KSERS ' . $year . '.';
    $body = $intro . "\n\n"
        . "Receipt No.: " . $receiptNo . "\n"
        . "Title: " . $title . "\n"
        . "Withdrawn on: " . $withdrawnAt . "\n";
    return [
        'lang' => 'en',
        'subject' => $subject,
        'body' => $body,
        'fields' => [
            'mail_subject' => $subject,
            'preheader' => 'Your abstract ' . $receiptNo . ' has been withdrawn.',
            'mail_category' => 'Abstract',
            'mail_title' => 'Your abstract has been withdrawn',
            'recipient_name' => $recipientName,
            'body_paragraphs' => ksers_mail_paragraphs([$intro]),
            'info_rows' => ksers_mail_info_row('Receipt No.', $receiptNo)
                . ksers_mail_info_row('Title', $title)
                . ksers_mail_info_row('Withdrawn on', $withdrawnAt),
            'button_url' => '',
            'button_label' => '',
            'notice_text' => '',
            'send_reason' => 'abstract withdrawal',
            'recipient_email' => $recipientEmail,
        ],
    ];
}

function ksers_abstract_withdrawn_notify($pdo, $abstractSeq) {
    try {
        $abstractStmt = $pdo->prepare(
            "SELECT a.*, m.email, m.name_ko, m.first_name_eng, m.last_name_eng
             FROM abstract_info a
             JOIN member_info m ON m.seq = a.member_seq
             WHERE a.seq = ?"
        );
        $abstractStmt->execute([(int)$abstractSeq]);
        $abstract = $abstractStmt->fetch(PDO::FETCH_ASSOC);
        if (!$abstract) {
            return [0, 0];
        }
        $year = (int)$abstract['year'];
        $authorStmt = $pdo->prepare(
            "SELECT first_name, last_name, email, presenting, corresponding
             FROM abstract_author WHERE abstract_seq = ? ORDER BY sort_no, seq"
        );
        $authorStmt->execute([(int)$abstract['seq']]);
        $authors = $authorStmt->fetchAll(PDO::FETCH_ASSOC);
        $submitterName = trim(trim((string)$abstract['first_name_eng']) . ' ' . trim((string)$abstract['last_name_eng']));
        if ($submitterName === '') {
            $submitterName = trim((string)$abstract['name_ko']);
        }
        $recipients = [];
        $addRecipient = function ($email, $name) use (&$recipients) {
            $email = trim((string)$email);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $key = mb_strtolower($email, 'UTF-8');
            if (isset($recipients[$key])) {
                return;
            }
            $recipients[$key] = [
                'email' => $email,
                'name' => trim((string)$name),
            ];
        };
        $addRecipient($abstract['email'], $submitterName);
        foreach ($authors as $author) {
            if (empty($author['presenting']) && empty($author['corresponding'])) {
                continue;
            }
            $addRecipient($author['email'], trim(trim((string)$author['first_name']) . ' ' . trim((string)$author['last_name'])));
        }
        $receiptNo = sprintf('A%d-%04d', $year, (int)$abstract['seq']);
        $title = trim((string)$abstract['title']);
        $withdrawnAt = ksers_password_changed_at_kst();
        $from = function_exists('ksers_password_mail_from')
            ? ksers_password_mail_from($pdo, $year)
            : 'noreply@ksers.kr';
        $insert = $pdo->prepare(
            "INSERT INTO admin_mail_log (year, template_key, email, recipient_name, subject, status, fail_reason, reg_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $sent = 0;
        $failed = 0;
        foreach ($recipients as $recipient) {
            $parts = ksers_abstract_withdrawn_mail_parts(
                $year,
                $receiptNo,
                $title,
                $withdrawnAt,
                $recipient['name'],
                $recipient['email']
            );
            $ok = false;
            try {
                $ok = ksers_send_mail($from, $recipient['email'], $parts['subject'], $parts['body'], ksers_mail_render('en', $parts['fields']));
            } catch (Exception $e) {
                $ok = false;
            }
            $subject = function_exists('mb_substr') ? mb_substr($parts['subject'], 0, 255, 'UTF-8') : substr($parts['subject'], 0, 255);
            $insert->execute([
                $year,
                'withdraw',
                $recipient['email'],
                $recipient['name'],
                $subject,
                $ok ? 'sent' : 'failed',
                $ok ? '' : '메일 서버가 이 주소를 받지 못했습니다.',
            ]);
            if ($ok) {
                $sent++;
            } else {
                $failed++;
            }
        }
        return [$sent, $failed];
    } catch (Exception $e) {
        error_log('ksers mail: abstract withdrawal notice failed');
        return [0, 0];
    }
}

function ksers_registration_mail_money($amount, $currency, $korea) {
    $formatted = number_format((int)$amount);
    if ($currency === 'USD') {
        return 'USD ' . $formatted;
    }
    return $korea ? ($formatted . '원') : ($formatted . ' KRW');
}

function ksers_registration_paid_category($pdo, $participant, $year, $korea) {
    $categories = function_exists('ksers_registration_categories') ? ksers_registration_categories() : [];
    $feeCodes = function_exists('ksers_payment_fee_codes') ? ksers_payment_fee_codes() : [];
    $categoryKey = isset($participant['category']) ? (string)$participant['category'] : '';
    $categoryLabel = '';
    if (isset($categories[$categoryKey])) {
        $categoryLabel = $korea ? $categories[$categoryKey][0] : $categories[$categoryKey][1];
    }
    $feeCode = function_exists('ksers_participant_fee_code') ? ksers_participant_fee_code($participant, $korea) : '';
    $label = isset($feeCodes[$feeCode])
        ? ($korea ? $feeCodes[$feeCode][0] : $feeCodes[$feeCode][1])
        : $categoryLabel;
    $periodCode = '';
    if (function_exists('ksers_payment_period_for') && function_exists('ksers_onsite_dates')) {
        $periodCode = ksers_payment_period_for(
            isset($participant['reg_date']) ? $participant['reg_date'] : '',
            $year,
            ksers_onsite_dates($pdo, $year)
        );
    }
    $periods = $korea
        ? ['early' => '얼리버드', 'pre' => '사전등록', 'onsite' => '현장등록']
        : ['early' => 'Early-bird', 'pre' => 'Pre-registration', 'onsite' => 'On-site'];
    if ($label !== '' && isset($periods[$periodCode])) {
        $label .= ' · ' . $periods[$periodCode];
    }
    return $label !== '' ? $label : '—';
}

function ksers_registration_paid_mail_parts($year, $korea, $regNo, $name, $affiliation, $category, $fee, $paidAt, $confirmUrl, $email) {
    $year = (int)$year;
    $office = 'ksers2027@ksers.kr';
    $officeLink = '<a href="mailto:' . ksers_mail_h($office) . '" style="color:#0086C9;font-weight:bold;">' . ksers_mail_h($office) . '</a>';
    if ($korea) {
        $subject = 'KSERS ' . $year . ' 참가 등록이 완료되었습니다 (' . $regNo . ')';
        $title = '참가 등록이 완료되었습니다';
        $intro = 'KSERS ' . $year . ' 참가 등록과 등록비 결제가 완료되었습니다. 등록 내용은 아래와 같습니다.';
        $noticeText = '취소는 사무국 이메일(' . $office . ')로 서면 요청하신 경우에만 접수됩니다. 2027년 3월 31일(수)까지 취소하시면 전액 환불(카드 결제 취소)되고, 4월 1일(목)부터는 환불되지 않습니다. 등록 확인서는 My Page에서 출력할 수 있습니다.';
        $noticeHtml = ksers_mail_h('취소는 사무국 이메일(') . $officeLink . ksers_mail_h(')로 서면 요청하신 경우에만 접수됩니다. 2027년 3월 31일(수)까지 취소하시면 전액 환불(카드 결제 취소)되고, 4월 1일(목)부터는 환불되지 않습니다. 등록 확인서는 My Page에서 출력할 수 있습니다.');
        $rows = ksers_mail_info_row('등록번호', $regNo)
            . ksers_mail_info_row('이름', $name)
            . ksers_mail_info_row('소속', $affiliation)
            . ksers_mail_info_row('등록 구분', $category)
            . ksers_mail_info_row('등록비', $fee)
            . ksers_mail_info_row('결제 수단', '카드')
            . ksers_mail_info_row('결제 일시', $paidAt . ' (KST)');
        $plain = $intro . "\n\n"
            . "등록번호: " . $regNo . "\n"
            . "이름: " . $name . "\n"
            . "소속: " . $affiliation . "\n"
            . "등록 구분: " . $category . "\n"
            . "등록비: " . $fee . "\n"
            . "결제 수단: 카드\n"
            . "결제 일시: " . $paidAt . " (KST)\n\n"
            . "등록 확인서: " . $confirmUrl . "\n\n"
            . $noticeText . "\n";
        $fields = [
            'mail_subject' => $subject,
            'preheader' => '참가 등록과 등록비 결제가 완료되었습니다.',
            'mail_category' => '참가 등록',
            'mail_title' => $title,
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs([$intro]),
            'info_rows' => $rows,
            'button_url' => $confirmUrl,
            'button_label' => '등록 확인서 보기',
            'notice_text' => $noticeHtml,
            'send_reason' => '참가 등록',
            'recipient_email' => $email,
        ];
        $lang = 'ko';
    } else {
        $subject = 'KSERS ' . $year . ' Registration Confirmed (' . $regNo . ')';
        $title = 'Your registration is confirmed';
        $intro = 'Thank you for registering for KSERS ' . $year . '. Your registration and payment have been completed. Please find your registration details below.';
        $noticeText = 'Cancellations are accepted only in writing by e-mail (' . $office . '). Cancellations made by March 31 (Wed), 2027 will be fully refunded through card payment cancellation; no refunds will be made from April 1 (Thu), 2027. You can print your registration confirmation on My Page.';
        $noticeHtml = ksers_mail_h('Cancellations are accepted only in writing by e-mail (') . $officeLink . ksers_mail_h('). Cancellations made by March 31 (Wed), 2027 will be fully refunded through card payment cancellation; no refunds will be made from April 1 (Thu), 2027. You can print your registration confirmation on My Page.');
        $rows = ksers_mail_info_row('Registration No.', $regNo)
            . ksers_mail_info_row('Name', $name)
            . ksers_mail_info_row('Affiliation', $affiliation)
            . ksers_mail_info_row('Category', $category)
            . ksers_mail_info_row('Registration Fee', $fee)
            . ksers_mail_info_row('Payment Method', 'Credit card')
            . ksers_mail_info_row('Paid on', $paidAt . ' (KST)');
        $plain = $intro . "\n\n"
            . "Registration No.: " . $regNo . "\n"
            . "Name: " . $name . "\n"
            . "Affiliation: " . $affiliation . "\n"
            . "Category: " . $category . "\n"
            . "Registration Fee: " . $fee . "\n"
            . "Payment Method: Credit card\n"
            . "Paid on: " . $paidAt . " (KST)\n\n"
            . "Confirmation: " . $confirmUrl . "\n\n"
            . $noticeText . "\n";
        $fields = [
            'mail_subject' => $subject,
            'preheader' => 'Your KSERS ' . $year . ' registration and payment are complete.',
            'mail_category' => 'Registration',
            'mail_title' => $title,
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs([$intro]),
            'info_rows' => $rows,
            'button_url' => $confirmUrl,
            'button_label' => 'View Confirmation',
            'notice_text' => $noticeHtml,
            'send_reason' => 'registration',
            'recipient_email' => $email,
        ];
        $lang = 'en';
    }
    return [
        'lang' => $lang,
        'subject' => $subject,
        'body' => $plain,
        'fields' => $fields,
    ];
}

function ksers_registration_paid_notify($pdo, $memberSeq, $year, $order) {
    try {
        $method = isset($order['method']) ? (string)$order['method'] : '';
        if ($method !== 'card_kr' && $method !== 'card_os') {
            return false;
        }
        $year = (int)$year;
        $memberSeq = (int)$memberSeq;
        $stmt = $pdo->prepare(
            "SELECT p.seq, p.category, p.ksers_member, p.senior_lifetime, p.reg_date,
                    m.email, m.name_ko, m.first_name_eng, m.last_name_eng,
                    m.affiliation_ko, m.affiliation_eng,
                    c.name_ko AS country_ko, c.name_eng AS country_en
             FROM participant_info p
             JOIN member_info m ON m.seq = p.member_seq
             LEFT JOIN code c ON c.majr = 1 AND c.iso = m.country
             WHERE p.member_seq = ? AND p.year = ?"
        );
        $stmt->execute([$memberSeq, $year]);
        $participant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$participant) {
            return false;
        }
        $email = trim((string)$participant['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $korea = true;
        if (function_exists('ksers_country_profile')) {
            $korea = !empty(ksers_country_profile([
                'name_ko' => $participant['country_ko'],
                'name_eng' => $participant['country_en'],
            ])['korea']);
        }
        $koreanName = trim((string)$participant['name_ko']);
        $englishName = trim(trim((string)$participant['first_name_eng']) . ' ' . trim((string)$participant['last_name_eng']));
        if ($korea) {
            $name = $koreanName !== '' ? $koreanName : $englishName;
            $affiliation = trim((string)$participant['affiliation_ko']);
            if ($affiliation === '') {
                $affiliation = trim((string)$participant['affiliation_eng']);
            }
        } else {
            $name = $englishName !== '' ? $englishName : $koreanName;
            $affiliation = trim((string)$participant['affiliation_eng']);
            if ($affiliation === '') {
                $affiliation = trim((string)$participant['affiliation_ko']);
            }
        }
        if ($name === '') {
            $name = $korea ? '회원' : 'Member';
        }
        if ($affiliation === '') {
            $affiliation = '—';
        }
        $currency = function_exists('ksers_fee_currency') ? ksers_fee_currency($order) : 'KRW';
        $paidAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul')))->format('Y-m-d H:i');
        $publicBase = function_exists('ksers_public_base') ? ksers_public_base() : '';
        $from = function_exists('ksers_password_mail_from')
            ? ksers_password_mail_from($pdo, $year)
            : 'noreply@ksers.kr';
        $parts = ksers_registration_paid_mail_parts(
            $year,
            $korea,
            'R' . $year . '-' . str_pad((string)$participant['seq'], 4, '0', STR_PAD_LEFT),
            $name,
            $affiliation,
            ksers_registration_paid_category($pdo, $participant, $year, $korea),
            ksers_registration_mail_money($order['amount'], $currency, $korea),
            $paidAt,
            $publicBase . '/service/registration.php',
            $email
        );
        return ksers_send_mail($from, $email, $parts['subject'], $parts['body'], ksers_mail_render($parts['lang'], $parts['fields']));
    } catch (Exception $e) {
        error_log('ksers mail: registration payment notice failed');
        return false;
    }
}

function ksers_registration_free_reason($participant, $korea) {
    $category = isset($participant['category']) ? (string)$participant['category'] : '';
    $feeCode = function_exists('ksers_participant_fee_code') ? ksers_participant_fee_code($participant, $korea) : '';
    if ($feeCode === 'lifetime_65') {
        return $korea ? '65세 이상 평생회원' : 'Lifetime member aged 65 or older';
    }
    if ($korea && $category === 'student') {
        return '국내 학생';
    }
    if ($korea && $category === 'nurse') {
        return '국내 간호사';
    }
    return '';
}

function ksers_registration_free_mail_parts($year, $korea, $regNo, $name, $affiliation, $category, $reason, $confirmUrl, $email) {
    $year = (int)$year;
    $fee = $korea ? ('무료 (' . $reason . ')') : ('Free (' . $reason . ')');
    if ($korea) {
        $subject = 'KSERS ' . $year . ' 참가 등록이 완료되었습니다 (' . $regNo . ')';
        $title = '참가 등록이 완료되었습니다';
        $intro = 'KSERS ' . $year . ' 참가 등록이 완료되었습니다. 등록비 면제 대상으로 등록되었으며, 등록 내용은 아래와 같습니다.';
        $notice = '등록 확인서는 My Page에서 출력할 수 있습니다.';
        $rows = ksers_mail_info_row('등록번호', $regNo)
            . ksers_mail_info_row('이름', $name)
            . ksers_mail_info_row('소속', $affiliation)
            . ksers_mail_info_row('등록 구분', $category)
            . ksers_mail_info_row('등록비', $fee);
        $plain = $intro . "\n\n"
            . "등록번호: " . $regNo . "\n"
            . "이름: " . $name . "\n"
            . "소속: " . $affiliation . "\n"
            . "등록 구분: " . $category . "\n"
            . "등록비: " . $fee . "\n\n"
            . "등록 확인서: " . $confirmUrl . "\n\n"
            . $notice . "\n";
        $fields = [
            'mail_subject' => $subject,
            'preheader' => '등록비 면제 대상으로 참가 등록이 완료되었습니다.',
            'mail_category' => '참가 등록',
            'mail_title' => $title,
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs([$intro]),
            'info_rows' => $rows,
            'button_url' => $confirmUrl,
            'button_label' => '등록 확인서 보기',
            'notice_text' => ksers_mail_h($notice),
            'send_reason' => '참가 등록',
            'recipient_email' => $email,
        ];
        $lang = 'ko';
    } else {
        $subject = 'KSERS ' . $year . ' Registration Confirmed (' . $regNo . ')';
        $title = 'Your registration is confirmed';
        $intro = 'Thank you for registering for KSERS ' . $year . '. Your registration has been completed free of charge. Please find your registration details below.';
        $notice = 'You can print your registration confirmation on My Page.';
        $rows = ksers_mail_info_row('Registration No.', $regNo)
            . ksers_mail_info_row('Name', $name)
            . ksers_mail_info_row('Affiliation', $affiliation)
            . ksers_mail_info_row('Category', $category)
            . ksers_mail_info_row('Registration Fee', $fee);
        $plain = $intro . "\n\n"
            . "Registration No.: " . $regNo . "\n"
            . "Name: " . $name . "\n"
            . "Affiliation: " . $affiliation . "\n"
            . "Category: " . $category . "\n"
            . "Registration Fee: " . $fee . "\n\n"
            . "Confirmation: " . $confirmUrl . "\n\n"
            . $notice . "\n";
        $fields = [
            'mail_subject' => $subject,
            'preheader' => 'Your KSERS ' . $year . ' registration is complete and free of charge.',
            'mail_category' => 'Registration',
            'mail_title' => $title,
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs([$intro]),
            'info_rows' => $rows,
            'button_url' => $confirmUrl,
            'button_label' => 'View Confirmation',
            'notice_text' => ksers_mail_h($notice),
            'send_reason' => 'registration',
            'recipient_email' => $email,
        ];
        $lang = 'en';
    }
    return [
        'lang' => $lang,
        'subject' => $subject,
        'body' => $plain,
        'fields' => $fields,
    ];
}

function ksers_registration_free_notify($pdo, $memberSeq, $year) {
    try {
        $year = (int)$year;
        $memberSeq = (int)$memberSeq;
        $stmt = $pdo->prepare(
            "SELECT p.seq, p.category, p.ksers_member, p.senior_lifetime, p.reg_date,
                    m.email, m.name_ko, m.first_name_eng, m.last_name_eng,
                    m.affiliation_ko, m.affiliation_eng,
                    c.iso AS country_iso, c.name_ko AS country_ko, c.name_eng AS country_en
             FROM participant_info p
             JOIN member_info m ON m.seq = p.member_seq
             LEFT JOIN code c ON c.majr = 1 AND c.iso = m.country
             WHERE p.member_seq = ? AND p.year = ?"
        );
        $stmt->execute([$memberSeq, $year]);
        $participant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$participant) {
            return false;
        }
        $korea = true;
        if (function_exists('ksers_country_profile')) {
            $korea = !empty(ksers_country_profile([
                'iso' => $participant['country_iso'],
                'name_ko' => $participant['country_ko'],
                'name_eng' => $participant['country_en'],
            ])['korea']);
        }
        $reason = ksers_registration_free_reason($participant, $korea);
        if ($reason === '') {
            return false;
        }
        $email = trim((string)$participant['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $koreanName = trim((string)$participant['name_ko']);
        $englishName = trim(trim((string)$participant['first_name_eng']) . ' ' . trim((string)$participant['last_name_eng']));
        if ($korea) {
            $name = $koreanName !== '' ? $koreanName : $englishName;
            $affiliation = trim((string)$participant['affiliation_ko']);
            if ($affiliation === '') {
                $affiliation = trim((string)$participant['affiliation_eng']);
            }
        } else {
            $name = $englishName !== '' ? $englishName : $koreanName;
            $affiliation = trim((string)$participant['affiliation_eng']);
            if ($affiliation === '') {
                $affiliation = trim((string)$participant['affiliation_ko']);
            }
        }
        if ($name === '') {
            $name = $korea ? '회원' : 'Member';
        }
        if ($affiliation === '') {
            $affiliation = '—';
        }
        $publicBase = function_exists('ksers_public_base') ? ksers_public_base() : '';
        $from = function_exists('ksers_password_mail_from')
            ? ksers_password_mail_from($pdo, $year)
            : 'noreply@ksers.kr';
        $parts = ksers_registration_free_mail_parts(
            $year,
            $korea,
            'R' . $year . '-' . str_pad((string)$participant['seq'], 4, '0', STR_PAD_LEFT),
            $name,
            $affiliation,
            ksers_registration_paid_category($pdo, $participant, $year, $korea),
            $reason,
            $publicBase . '/service/registration.php',
            $email
        );
        return ksers_send_mail($from, $email, $parts['subject'], $parts['body'], ksers_mail_render($parts['lang'], $parts['fields']));
    } catch (Exception $e) {
        error_log('ksers mail: free registration notice failed');
        return false;
    }
}

function ksers_registration_support_mail_parts($year, $regNo, $name, $category, $pageUrl, $email) {
    $year = (int)$year;
    $subject = 'KSERS ' . $year . ' Supporting Document Received (' . $regNo . ')';
    $title = 'Your document is under review';
    $intro = 'Thank you for registering for KSERS ' . $year . '. We have received your supporting document for free registration. The Secretariat will review it and notify you of the result by e-mail.';
    $notice = 'Your registration will be confirmed once the document has been verified. If any additional information is required, the Secretariat will contact you.';
    $rows = ksers_mail_info_row('Registration No.', $regNo)
        . ksers_mail_info_row('Name', $name)
        . ksers_mail_info_row('Category', $category)
        . ksers_mail_info_row('Status', 'Under review');
    $plain = $intro . "\n\n"
        . "Registration No.: " . $regNo . "\n"
        . "Name: " . $name . "\n"
        . "Category: " . $category . "\n"
        . "Status: Under review\n\n"
        . "My Page: " . $pageUrl . "\n\n"
        . $notice . "\n";
    return [
        'lang' => 'en',
        'subject' => $subject,
        'body' => $plain,
        'fields' => [
            'mail_subject' => $subject,
            'preheader' => 'Your supporting document is under review.',
            'mail_category' => 'Registration',
            'mail_title' => $title,
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs([$intro]),
            'info_rows' => $rows,
            'button_url' => $pageUrl,
            'button_label' => 'Go to My Page',
            'notice_text' => ksers_mail_h($notice),
            'send_reason' => 'registration',
            'recipient_email' => $email,
        ],
    ];
}

function ksers_registration_support_notify($pdo, $memberSeq, $year) {
    try {
        $year = (int)$year;
        $memberSeq = (int)$memberSeq;
        $stmt = $pdo->prepare(
            "SELECT p.seq, p.category, p.ksers_member, p.senior_lifetime, p.reg_date, p.support_status,
                    m.email, m.name_ko, m.first_name_eng, m.last_name_eng,
                    m.country,
                    c.iso AS country_iso, c.name_ko AS country_ko, c.name_eng AS country_en
             FROM participant_info p
             JOIN member_info m ON m.seq = p.member_seq
             LEFT JOIN code c ON c.majr = 1 AND c.iso = m.country
             WHERE p.member_seq = ? AND p.year = ?"
        );
        $stmt->execute([$memberSeq, $year]);
        $participant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$participant || (string)$participant['support_status'] !== 'review') {
            return false;
        }
        $category = (string)$participant['category'];
        if ($category !== 'student' && $category !== 'nurse') {
            return false;
        }
        $korea = false;
        if (function_exists('ksers_country_profile')) {
            $korea = !empty(ksers_country_profile([
                'iso' => $participant['country_iso'],
                'name_ko' => $participant['country_ko'],
                'name_eng' => $participant['country_en'],
            ])['korea']);
        }
        if ($korea || strtoupper(trim((string)$participant['country'])) === 'KR') {
            return false;
        }
        $email = trim((string)$participant['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $englishName = trim(trim((string)$participant['first_name_eng']) . ' ' . trim((string)$participant['last_name_eng']));
        $name = $englishName !== '' ? $englishName : trim((string)$participant['name_ko']);
        if ($name === '') {
            $name = 'Member';
        }
        $publicBase = function_exists('ksers_public_base') ? ksers_public_base() : '';
        $from = function_exists('ksers_password_mail_from')
            ? ksers_password_mail_from($pdo, $year)
            : 'noreply@ksers.kr';
        $parts = ksers_registration_support_mail_parts(
            $year,
            'R' . $year . '-' . str_pad((string)$participant['seq'], 4, '0', STR_PAD_LEFT),
            $name,
            ksers_registration_paid_category($pdo, $participant, $year, false),
            $publicBase . '/service/registration.php',
            $email
        );
        return ksers_send_mail($from, $email, $parts['subject'], $parts['body'], ksers_mail_render($parts['lang'], $parts['fields']));
    } catch (Exception $e) {
        error_log('ksers mail: supporting document notice failed');
        return false;
    }
}

function ksers_registration_support_reject_mail_parts($year, $regNo, $name, $reason, $pageUrl, $email) {
    $year = (int)$year;
    $subject = 'KSERS ' . $year . ' Supporting Document Not Accepted (' . $regNo . ')';
    $title = 'Please upload your document again';
    $intro = 'Thank you for registering for KSERS ' . $year . '. Unfortunately, we were unable to verify the supporting document you submitted for free registration. Please see the reason below and upload a new document on My Page.';
    $notice = 'Your registration will be confirmed after the new document has been verified. If you have any questions, please contact the Secretariat.';
    $reasonHtml = nl2br(ksers_mail_h($reason), false);
    $rows = ksers_mail_info_row('Registration No.', $regNo)
        . ksers_mail_info_row('Name', $name)
        . ksers_mail_info_row('Reason', $reasonHtml, true);
    $plain = $intro . "\n\n"
        . "Registration No.: " . $regNo . "\n"
        . "Name: " . $name . "\n"
        . "Reason: " . $reason . "\n\n"
        . "Upload: " . $pageUrl . "\n\n"
        . $notice . "\n";
    return [
        'lang' => 'en',
        'subject' => $subject,
        'body' => $plain,
        'fields' => [
            'mail_subject' => $subject,
            'preheader' => 'Please upload your supporting document again.',
            'mail_category' => 'Registration',
            'mail_title' => $title,
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs([$intro]),
            'info_rows' => $rows,
            'button_url' => $pageUrl,
            'button_label' => 'Upload Document',
            'notice_text' => ksers_mail_h($notice),
            'send_reason' => 'registration',
            'recipient_email' => $email,
        ],
    ];
}

function ksers_registration_support_reject_notify($pdo, $memberSeq, $year) {
    try {
        $year = (int)$year;
        $memberSeq = (int)$memberSeq;
        $stmt = $pdo->prepare(
            "SELECT p.seq, p.category, p.support_status, p.support_reason,
                    m.email, m.name_ko, m.first_name_eng, m.last_name_eng,
                    m.country,
                    c.iso AS country_iso, c.name_ko AS country_ko, c.name_eng AS country_en
             FROM participant_info p
             JOIN member_info m ON m.seq = p.member_seq
             LEFT JOIN code c ON c.majr = 1 AND c.iso = m.country
             WHERE p.member_seq = ? AND p.year = ?"
        );
        $stmt->execute([$memberSeq, $year]);
        $participant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$participant || (string)$participant['support_status'] !== 'rejected') {
            return false;
        }
        $category = (string)$participant['category'];
        if ($category !== 'student' && $category !== 'nurse') {
            return false;
        }
        $korea = false;
        if (function_exists('ksers_country_profile')) {
            $korea = !empty(ksers_country_profile([
                'iso' => $participant['country_iso'],
                'name_ko' => $participant['country_ko'],
                'name_eng' => $participant['country_en'],
            ])['korea']);
        }
        if ($korea || strtoupper(trim((string)$participant['country'])) === 'KR') {
            return false;
        }
        $reason = trim((string)$participant['support_reason']);
        if ($reason === '') {
            return false;
        }
        $email = trim((string)$participant['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $englishName = trim(trim((string)$participant['first_name_eng']) . ' ' . trim((string)$participant['last_name_eng']));
        $name = $englishName !== '' ? $englishName : trim((string)$participant['name_ko']);
        if ($name === '') {
            $name = 'Member';
        }
        $publicBase = function_exists('ksers_public_base') ? ksers_public_base() : '';
        $from = function_exists('ksers_password_mail_from')
            ? ksers_password_mail_from($pdo, $year)
            : 'noreply@ksers.kr';
        $parts = ksers_registration_support_reject_mail_parts(
            $year,
            'R' . $year . '-' . str_pad((string)$participant['seq'], 4, '0', STR_PAD_LEFT),
            $name,
            $reason,
            $publicBase . '/service/registration.php',
            $email
        );
        return ksers_send_mail($from, $email, $parts['subject'], $parts['body'], ksers_mail_render($parts['lang'], $parts['fields']));
    } catch (Exception $e) {
        error_log('ksers mail: supporting document rejection notice failed');
        return false;
    }
}

function ksers_registration_support_approved_mail_parts($year, $regNo, $name, $affiliation, $category, $pageUrl, $email) {
    $year = (int)$year;
    $subject = 'KSERS ' . $year . ' Registration Confirmed (' . $regNo . ')';
    $title = 'Your registration is confirmed';
    $intro = 'Your supporting document has been verified, and your registration for KSERS ' . $year . ' has been completed free of charge.';
    $notice = 'You can print your registration confirmation on My Page.';
    $rows = ksers_mail_info_row('Registration No.', $regNo)
        . ksers_mail_info_row('Name', $name)
        . ksers_mail_info_row('Affiliation', $affiliation)
        . ksers_mail_info_row('Category', $category)
        . ksers_mail_info_row('Registration Fee', 'Free')
        . ksers_mail_info_row('Status', 'Registration completed');
    $plain = $intro . "\n\n"
        . "Registration No.: " . $regNo . "\n"
        . "Name: " . $name . "\n"
        . "Affiliation: " . $affiliation . "\n"
        . "Category: " . $category . "\n"
        . "Registration Fee: Free\n"
        . "Status: Registration completed\n\n"
        . "Confirmation: " . $pageUrl . "\n\n"
        . $notice . "\n";
    return [
        'lang' => 'en',
        'subject' => $subject,
        'body' => $plain,
        'fields' => [
            'mail_subject' => $subject,
            'preheader' => 'Your registration is confirmed and free of charge.',
            'mail_category' => 'Registration',
            'mail_title' => $title,
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs([$intro]),
            'info_rows' => $rows,
            'button_url' => $pageUrl,
            'button_label' => 'View Confirmation',
            'notice_text' => ksers_mail_h($notice),
            'send_reason' => 'registration',
            'recipient_email' => $email,
        ],
    ];
}

function ksers_registration_support_approved_notify($pdo, $memberSeq, $year) {
    try {
        $year = (int)$year;
        $memberSeq = (int)$memberSeq;
        $stmt = $pdo->prepare(
            "SELECT p.seq, p.category, p.ksers_member, p.senior_lifetime, p.reg_date, p.support_status,
                    m.email, m.name_ko, m.first_name_eng, m.last_name_eng,
                    m.affiliation_ko, m.affiliation_eng, m.country,
                    c.iso AS country_iso, c.name_ko AS country_ko, c.name_eng AS country_en
             FROM participant_info p
             JOIN member_info m ON m.seq = p.member_seq
             LEFT JOIN code c ON c.majr = 1 AND c.iso = m.country
             WHERE p.member_seq = ? AND p.year = ?"
        );
        $stmt->execute([$memberSeq, $year]);
        $participant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$participant || (string)$participant['support_status'] !== 'approved') {
            return false;
        }
        $category = (string)$participant['category'];
        if ($category !== 'student' && $category !== 'nurse') {
            return false;
        }
        $korea = false;
        if (function_exists('ksers_country_profile')) {
            $korea = !empty(ksers_country_profile([
                'iso' => $participant['country_iso'],
                'name_ko' => $participant['country_ko'],
                'name_eng' => $participant['country_en'],
            ])['korea']);
        }
        if ($korea || strtoupper(trim((string)$participant['country'])) === 'KR') {
            return false;
        }
        $email = trim((string)$participant['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $englishName = trim(trim((string)$participant['first_name_eng']) . ' ' . trim((string)$participant['last_name_eng']));
        $name = $englishName !== '' ? $englishName : trim((string)$participant['name_ko']);
        if ($name === '') {
            $name = 'Member';
        }
        $affiliation = trim((string)$participant['affiliation_eng']);
        if ($affiliation === '') {
            $affiliation = trim((string)$participant['affiliation_ko']);
        }
        if ($affiliation === '') {
            $affiliation = '—';
        }
        $publicBase = function_exists('ksers_public_base') ? ksers_public_base() : '';
        $from = function_exists('ksers_password_mail_from')
            ? ksers_password_mail_from($pdo, $year)
            : 'noreply@ksers.kr';
        $parts = ksers_registration_support_approved_mail_parts(
            $year,
            'R' . $year . '-' . str_pad((string)$participant['seq'], 4, '0', STR_PAD_LEFT),
            $name,
            $affiliation,
            ksers_registration_paid_category($pdo, $participant, $year, false),
            $publicBase . '/service/registration.php',
            $email
        );
        return ksers_send_mail($from, $email, $parts['subject'], $parts['body'], ksers_mail_render($parts['lang'], $parts['fields']));
    } catch (Exception $e) {
        error_log('ksers mail: supporting document approval notice failed');
        return false;
    }
}

function ksers_registration_bank_mail_parts($year, $korea, $regNo, $name, $fee, $bankHtml, $bankText, $pageUrl, $email) {
    $year = (int)$year;
    $office = 'ksers2027@ksers.kr';
    $officeLink = '<a href="mailto:' . ksers_mail_h($office) . '" style="color:#0086C9;font-weight:bold;">' . ksers_mail_h($office) . '</a>';
    if ($korea) {
        $subject = 'KSERS ' . $year . ' 등록비 입금 안내 (' . $regNo . ')';
        $intro = 'KSERS ' . $year . ' 참가 등록 정보가 저장되었습니다. 아래 계좌로 등록비를 입금해 주시면, 사무국이 입금을 확인한 뒤 등록 완료 메일을 보내드립니다. 입금 확인까지 영업일 기준 5일 이내 걸립니다.';
        $noticeText = '입금자명은 등록자 이름으로 해 주세요. 다른 이름으로 입금한 경우 사무국(' . $office . ')으로 알려주세요. 송금 수수료는 등록자 부담입니다.';
        $noticeHtml = ksers_mail_h('입금자명은 등록자 이름으로 해 주세요. 다른 이름으로 입금한 경우 사무국(') . $officeLink . ksers_mail_h(')으로 알려주세요. 송금 수수료는 등록자 부담입니다.');
        $rows = ksers_mail_info_row('등록번호', $regNo)
            . ksers_mail_info_row('이름', $name)
            . ksers_mail_info_row('입금액', $fee)
            . ksers_mail_info_row('입금 계좌', $bankHtml, true)
            . ksers_mail_info_row('입금 기한', ksers_mail_temp_mark(), true);
        $plain = $intro . "\n\n"
            . "등록번호: " . $regNo . "\n"
            . "이름: " . $name . "\n"
            . "입금액: " . $fee . "\n"
            . "입금 계좌: " . $bankText . "\n"
            . "입금 기한: 임시\n\n"
            . "등록 내역: " . $pageUrl . "\n\n"
            . $noticeText . "\n";
        $fields = [
            'mail_subject' => $subject,
            'preheader' => '등록비를 아래 계좌로 입금해 주세요.',
            'mail_category' => '참가 등록',
            'mail_title' => '등록비를 입금해 주세요',
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs([$intro]),
            'info_rows' => $rows,
            'button_url' => $pageUrl,
            'button_label' => '등록 내역 보기',
            'notice_text' => $noticeHtml,
            'send_reason' => '등록비 입금',
            'recipient_email' => $email,
        ];
        $lang = 'ko';
    } else {
        $subject = 'KSERS ' . $year . ' Payment Instructions for Bank Transfer (' . $regNo . ')';
        $intro = 'Your KSERS ' . $year . ' registration details have been saved. Please transfer the registration fee to the account below. You will receive a confirmation e-mail once the Secretariat has verified your payment, which may take up to 5 business days.';
        $noticeText = 'Please use the registrant\'s name as the remitter name. If the remitter name is different, please inform the Secretariat (' . $office . '). All bank charges for remittance must be paid by the registrant.';
        $noticeHtml = ksers_mail_h('Please use the registrant\'s name as the remitter name. If the remitter name is different, please inform the Secretariat (') . $officeLink . ksers_mail_h('). All bank charges for remittance must be paid by the registrant.');
        $rows = ksers_mail_info_row('Registration No.', $regNo)
            . ksers_mail_info_row('Name', $name)
            . ksers_mail_info_row('Amount', $fee)
            . ksers_mail_info_row('Bank Account', $bankHtml, true)
            . ksers_mail_info_row('Payment Deadline', ksers_mail_temp_mark(), true);
        $plain = $intro . "\n\n"
            . "Registration No.: " . $regNo . "\n"
            . "Name: " . $name . "\n"
            . "Amount: " . $fee . "\n"
            . "Bank Account: " . $bankText . "\n"
            . "Payment Deadline: 임시\n\n"
            . "Registration: " . $pageUrl . "\n\n"
            . $noticeText . "\n";
        $fields = [
            'mail_subject' => $subject,
            'preheader' => 'Please transfer your KSERS ' . $year . ' registration fee.',
            'mail_category' => 'Registration',
            'mail_title' => 'Please complete your bank transfer',
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs([$intro]),
            'info_rows' => $rows,
            'button_url' => $pageUrl,
            'button_label' => 'View Registration',
            'notice_text' => $noticeHtml,
            'send_reason' => 'bank transfer',
            'recipient_email' => $email,
        ];
        $lang = 'en';
    }
    return [
        'lang' => $lang,
        'subject' => $subject,
        'body' => $plain,
        'fields' => $fields,
    ];
}

function ksers_registration_bank_notify($pdo, $memberSeq, $year, $order) {
    try {
        $year = (int)$year;
        $memberSeq = (int)$memberSeq;
        $stmt = $pdo->prepare(
            "SELECT p.seq, m.email, m.name_ko, m.first_name_eng, m.last_name_eng,
                    c.name_ko AS country_ko, c.name_eng AS country_en
             FROM participant_info p
             JOIN member_info m ON m.seq = p.member_seq
             LEFT JOIN code c ON c.majr = 1 AND c.iso = m.country
             WHERE p.member_seq = ? AND p.year = ?"
        );
        $stmt->execute([$memberSeq, $year]);
        $participant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$participant) {
            return false;
        }
        $email = trim((string)$participant['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $korea = true;
        if (function_exists('ksers_country_profile')) {
            $korea = !empty(ksers_country_profile([
                'name_ko' => $participant['country_ko'],
                'name_eng' => $participant['country_en'],
            ])['korea']);
        }
        $koreanName = trim((string)$participant['name_ko']);
        $englishName = trim(trim((string)$participant['first_name_eng']) . ' ' . trim((string)$participant['last_name_eng']));
        if ($korea) {
            $name = $koreanName !== '' ? $koreanName : $englishName;
        } else {
            $name = $englishName !== '' ? $englishName : $koreanName;
        }
        if ($name === '') {
            $name = $korea ? '회원' : 'Member';
        }
        $currency = function_exists('ksers_fee_currency') ? ksers_fee_currency($order) : 'KRW';
        $bankText = '임시';
        $bankHtml = ksers_mail_temp_mark();
        if ($korea) {
            $bankText = '하나은행 576-910013-04605 (예금주: 대한내시경로봇외과학회)';
            $bankHtml = ksers_mail_h($bankText);
        }
        $publicBase = function_exists('ksers_public_base') ? ksers_public_base() : '';
        $from = function_exists('ksers_password_mail_from')
            ? ksers_password_mail_from($pdo, $year)
            : 'noreply@ksers.kr';
        $parts = ksers_registration_bank_mail_parts(
            $year,
            $korea,
            'R' . $year . '-' . str_pad((string)$participant['seq'], 4, '0', STR_PAD_LEFT),
            $name,
            ksers_registration_mail_money($order['amount'], $currency, $korea),
            $bankHtml,
            $bankText,
            $publicBase . '/service/registration.php',
            $email
        );
        return ksers_send_mail($from, $email, $parts['subject'], $parts['body'], ksers_mail_render($parts['lang'], $parts['fields']));
    } catch (Exception $e) {
        error_log('ksers mail: bank transfer notice failed');
        return false;
    }
}

function ksers_registration_bank_confirmed_mail_parts($year, $korea, $regNo, $name, $affiliation, $category, $fee, $confirmedAt, $confirmUrl, $email) {
    $year = (int)$year;
    $office = 'ksers2027@ksers.kr';
    $officeLink = '<a href="mailto:' . ksers_mail_h($office) . '" style="color:#0086C9;font-weight:bold;">' . ksers_mail_h($office) . '</a>';
    if ($korea) {
        $subject = 'KSERS ' . $year . ' 참가 등록이 완료되었습니다 (' . $regNo . ')';
        $intro = '등록비 입금이 확인되어 KSERS ' . $year . ' 참가 등록이 완료되었습니다. 등록 내용은 아래와 같습니다.';
        $noticeText = '취소는 사무국 이메일(' . $office . ')로 서면 요청하신 경우에만 접수됩니다. 2027년 3월 31일(수)까지 취소하시면 전액 환불되고, 4월 1일(목)부터는 환불되지 않습니다. 환불은 대회 종료 1개월 후에 진행됩니다. 등록 확인서는 My Page에서 출력할 수 있습니다.';
        $noticeHtml = ksers_mail_h('취소는 사무국 이메일(') . $officeLink . ksers_mail_h(')로 서면 요청하신 경우에만 접수됩니다. 2027년 3월 31일(수)까지 취소하시면 전액 환불되고, 4월 1일(목)부터는 환불되지 않습니다. 환불은 대회 종료 1개월 후에 진행됩니다. 등록 확인서는 My Page에서 출력할 수 있습니다.');
        $rows = ksers_mail_info_row('등록번호', $regNo)
            . ksers_mail_info_row('이름', $name)
            . ksers_mail_info_row('소속', $affiliation)
            . ksers_mail_info_row('등록 구분', $category)
            . ksers_mail_info_row('등록비', $fee)
            . ksers_mail_info_row('결제 수단', '계좌이체')
            . ksers_mail_info_row('입금 확인일', $confirmedAt . ' (KST)');
        $plain = $intro . "\n\n"
            . "등록번호: " . $regNo . "\n"
            . "이름: " . $name . "\n"
            . "소속: " . $affiliation . "\n"
            . "등록 구분: " . $category . "\n"
            . "등록비: " . $fee . "\n"
            . "결제 수단: 계좌이체\n"
            . "입금 확인일: " . $confirmedAt . " (KST)\n\n"
            . "등록 확인서: " . $confirmUrl . "\n\n"
            . $noticeText . "\n";
        $fields = [
            'mail_subject' => $subject,
            'preheader' => '입금이 확인되어 참가 등록이 완료되었습니다.',
            'mail_category' => '참가 등록',
            'mail_title' => '입금이 확인되어 등록이 완료되었습니다',
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs([$intro]),
            'info_rows' => $rows,
            'button_url' => $confirmUrl,
            'button_label' => '등록 확인서 보기',
            'notice_text' => $noticeHtml,
            'send_reason' => '입금 확인',
            'recipient_email' => $email,
        ];
        $lang = 'ko';
    } else {
        $subject = 'KSERS ' . $year . ' Registration Confirmed (' . $regNo . ')';
        $intro = 'Your bank transfer has been confirmed, and your registration for KSERS ' . $year . ' is now complete. Please find your registration details below.';
        $noticeText = 'Cancellations are accepted only in writing by e-mail (' . $office . '). Cancellations made by March 31 (Wed), 2027 will be fully refunded; no refunds will be made from April 1 (Thu), 2027. Refunds will be made 1 month after the congress. You can print your registration confirmation on My Page.';
        $noticeHtml = ksers_mail_h('Cancellations are accepted only in writing by e-mail (') . $officeLink . ksers_mail_h('). Cancellations made by March 31 (Wed), 2027 will be fully refunded; no refunds will be made from April 1 (Thu), 2027. Refunds will be made 1 month after the congress. You can print your registration confirmation on My Page.');
        $rows = ksers_mail_info_row('Registration No.', $regNo)
            . ksers_mail_info_row('Name', $name)
            . ksers_mail_info_row('Affiliation', $affiliation)
            . ksers_mail_info_row('Category', $category)
            . ksers_mail_info_row('Registration Fee', $fee)
            . ksers_mail_info_row('Payment Method', 'Bank transfer')
            . ksers_mail_info_row('Confirmed on', $confirmedAt . ' (KST)');
        $plain = $intro . "\n\n"
            . "Registration No.: " . $regNo . "\n"
            . "Name: " . $name . "\n"
            . "Affiliation: " . $affiliation . "\n"
            . "Category: " . $category . "\n"
            . "Registration Fee: " . $fee . "\n"
            . "Payment Method: Bank transfer\n"
            . "Confirmed on: " . $confirmedAt . " (KST)\n\n"
            . "Confirmation: " . $confirmUrl . "\n\n"
            . $noticeText . "\n";
        $fields = [
            'mail_subject' => $subject,
            'preheader' => 'Your bank transfer has been confirmed.',
            'mail_category' => 'Registration',
            'mail_title' => 'Your payment has been confirmed',
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs([$intro]),
            'info_rows' => $rows,
            'button_url' => $confirmUrl,
            'button_label' => 'View Confirmation',
            'notice_text' => $noticeHtml,
            'send_reason' => 'bank transfer confirmation',
            'recipient_email' => $email,
        ];
        $lang = 'en';
    }
    return [
        'lang' => $lang,
        'subject' => $subject,
        'body' => $plain,
        'fields' => $fields,
    ];
}

function ksers_registration_bank_confirmed_notify($pdo, $memberSeq, $year, $order) {
    try {
        if (!isset($order['method']) || $order['method'] !== 'bank') {
            return false;
        }
        $year = (int)$year;
        $memberSeq = (int)$memberSeq;
        $stmt = $pdo->prepare(
            "SELECT p.seq, p.category, p.ksers_member, p.senior_lifetime, p.reg_date,
                    m.email, m.name_ko, m.first_name_eng, m.last_name_eng,
                    m.affiliation_ko, m.affiliation_eng,
                    c.name_ko AS country_ko, c.name_eng AS country_en
             FROM participant_info p
             JOIN member_info m ON m.seq = p.member_seq
             LEFT JOIN code c ON c.majr = 1 AND c.iso = m.country
             WHERE p.member_seq = ? AND p.year = ?"
        );
        $stmt->execute([$memberSeq, $year]);
        $participant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$participant) {
            return false;
        }
        $email = trim((string)$participant['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $korea = true;
        if (function_exists('ksers_country_profile')) {
            $korea = !empty(ksers_country_profile([
                'name_ko' => $participant['country_ko'],
                'name_eng' => $participant['country_en'],
            ])['korea']);
        }
        $koreanName = trim((string)$participant['name_ko']);
        $englishName = trim(trim((string)$participant['first_name_eng']) . ' ' . trim((string)$participant['last_name_eng']));
        if ($korea) {
            $name = $koreanName !== '' ? $koreanName : $englishName;
            $affiliation = trim((string)$participant['affiliation_ko']);
            if ($affiliation === '') {
                $affiliation = trim((string)$participant['affiliation_eng']);
            }
        } else {
            $name = $englishName !== '' ? $englishName : $koreanName;
            $affiliation = trim((string)$participant['affiliation_eng']);
            if ($affiliation === '') {
                $affiliation = trim((string)$participant['affiliation_ko']);
            }
        }
        if ($name === '') {
            $name = $korea ? '회원' : 'Member';
        }
        if ($affiliation === '') {
            $affiliation = '—';
        }
        $currency = function_exists('ksers_fee_currency') ? ksers_fee_currency($order) : 'KRW';
        $confirmedAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul')))->format('Y-m-d H:i');
        $publicBase = function_exists('ksers_public_base') ? ksers_public_base() : '';
        $from = function_exists('ksers_password_mail_from')
            ? ksers_password_mail_from($pdo, $year)
            : 'noreply@ksers.kr';
        $parts = ksers_registration_bank_confirmed_mail_parts(
            $year,
            $korea,
            'R' . $year . '-' . str_pad((string)$participant['seq'], 4, '0', STR_PAD_LEFT),
            $name,
            $affiliation,
            ksers_registration_paid_category($pdo, $participant, $year, $korea),
            ksers_registration_mail_money($order['amount'], $currency, $korea),
            $confirmedAt,
            $publicBase . '/service/registration.php',
            $email
        );
        return ksers_send_mail($from, $email, $parts['subject'], $parts['body'], ksers_mail_render($parts['lang'], $parts['fields']));
    } catch (Exception $e) {
        error_log('ksers mail: bank transfer confirmation failed');
        return false;
    }
}

function ksers_registration_cancel_when($korea) {
    $when = new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul'));
    $weekKo = ['일', '월', '화', '수', '목', '금', '토'];
    $weekEn = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $index = (int)$when->format('w');
    if ($korea) {
        return $when->format('Y') . '년 ' . $when->format('n') . '월 ' . $when->format('j') . '일(' . $weekKo[$index] . ')';
    }
    return $when->format('F j') . ' (' . $weekEn[$index] . '), ' . $when->format('Y');
}

function ksers_registration_refund_open() {
    $today = function_exists('ksers_seoul_today') ? ksers_seoul_today() : date('Y-m-d');
    return $today <= '2027-03-31';
}

function ksers_payment_order_amounts($pdo, $orderSeq) {
    $stmt = $pdo->prepare(
        "SELECT l.amount, f.fee_code
         FROM payment_line l
         LEFT JOIN fee_item f ON f.seq = l.fee_seq
         WHERE l.order_seq = ?"
    );
    $stmt->execute([(int)$orderSeq]);
    $registration = 0;
    $dinner = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $line) {
        $code = isset($line['fee_code']) ? (string)$line['fee_code'] : '';
        if ($code === 'dinner_individual' || $code === 'dinner_group') {
            $dinner += (int)$line['amount'];
        } else {
            $registration += (int)$line['amount'];
        }
    }
    return ['registration' => $registration, 'dinner' => $dinner];
}

function ksers_registration_refund_amount_text($korea, $byCurrency) {
    $plain = [];
    $html = [];
    foreach ($byCurrency as $currency => $parts) {
        $registration = isset($parts['registration']) ? (int)$parts['registration'] : 0;
        $dinner = isset($parts['dinner']) ? (int)$parts['dinner'] : 0;
        if ($registration < 1 && $dinner < 1) {
            continue;
        }
        $plainBits = [];
        $htmlBits = [];
        if ($registration > 0) {
            $money = ksers_registration_mail_money($registration, $currency, $korea);
            $plainBits[] = $money;
            $htmlBits[] = ksers_mail_h($money);
        }
        if ($dinner > 0) {
            $money = ksers_registration_mail_money($dinner, $currency, $korea);
            $label = ($korea ? '갈라 디너 ' : 'Gala dinner ') . $money;
            $plainBits[] = $label . ' - 임시';
            $htmlBits[] = ksers_mail_h($label) . ' - ' . ksers_mail_temp_mark();
        }
        $plain[] = implode(', ', $plainBits);
        $html[] = implode(', ', $htmlBits);
    }
    if (!$plain) {
        $zero = ksers_registration_mail_money(0, $korea ? 'KRW' : 'USD', $korea);
        return ['plain' => $zero, 'html' => ksers_mail_h($zero), 'positive' => false];
    }
    return ['plain' => implode(', ', $plain), 'html' => implode(', ', $html), 'positive' => true];
}

function ksers_registration_cancel_mail_parts($year, $korea, $regNo, $name, $requestedAt, $refundAmountPlain, $refundAmountHtml, $refundMethod, $showCard, $showBank, $email) {
    $year = (int)$year;
    $office = 'ksers2027@ksers.kr';
    $officeLink = '<a href="mailto:' . ksers_mail_h($office) . '" style="color:#0086C9;font-weight:bold;">' . ksers_mail_h($office) . '</a>';
    if ($korea) {
        $subject = 'KSERS ' . $year . ' 참가 등록 취소가 처리되었습니다 (' . $regNo . ')';
        $title = '참가 등록 취소가 처리되었습니다';
        $intro = '요청하신 KSERS ' . $year . ' 참가 등록 취소가 처리되었습니다. 취소 내용은 아래와 같습니다.';
        $policy = '환불 규정: 2027년 3월 31일(수)까지 취소 시 전액 환불, 4월 1일(목)부터는 환불되지 않습니다.';
        $card = '카드 결제: 결제가 취소되었으며, 카드사에 따라 반영까지 영업일 기준 3~7일이 걸릴 수 있습니다.';
        $bank = '계좌이체: 대회 종료 1개월 후 환불됩니다. 환불받을 계좌(은행·계좌번호·예금주)를 사무국(' . $office . ')으로 보내주세요. 송금 수수료는 등록자 부담입니다.';
        $bankHtml = ksers_mail_h('계좌이체: 대회 종료 1개월 후 환불됩니다. 환불받을 계좌(은행·계좌번호·예금주)를 사무국(') . $officeLink . ksers_mail_h(')으로 보내주세요. 송금 수수료는 등록자 부담입니다.');
        $rows = ksers_mail_info_row('등록번호', $regNo)
            . ksers_mail_info_row('이름', $name)
            . ksers_mail_info_row('취소 요청일', $requestedAt)
            . ksers_mail_info_row('환불 금액', $refundAmountHtml, true)
            . ksers_mail_info_row('환불 방법', $refundMethod);
        $fallback = '회원';
        $category = '참가 등록';
        $reason = '참가 등록 취소';
    } else {
        $subject = 'KSERS ' . $year . ' Registration Cancellation Confirmed (' . $regNo . ')';
        $title = 'Your cancellation has been processed';
        $intro = 'As requested, your KSERS ' . $year . ' registration has been cancelled. Please find the details below.';
        $policy = 'Refund policy: Cancellations made by March 31 (Wed), 2027 will be fully refunded; no refunds will be made from April 1 (Thu), 2027.';
        $card = 'Card payment: Your payment has been cancelled. It may take 3–7 business days to appear on your statement, depending on your card issuer.';
        $bank = 'Bank transfer: The refund will be made 1 month after the congress. Please send your refund account details (bank name, account number, account holder, and SWIFT code for overseas accounts) to the Secretariat (' . $office . '). All bank charges for remittance must be paid by the registrant.';
        $bankHtml = ksers_mail_h('Bank transfer: The refund will be made 1 month after the congress. Please send your refund account details (bank name, account number, account holder, and SWIFT code for overseas accounts) to the Secretariat (') . $officeLink . ksers_mail_h('). All bank charges for remittance must be paid by the registrant.');
        $rows = ksers_mail_info_row('Registration No.', $regNo)
            . ksers_mail_info_row('Name', $name)
            . ksers_mail_info_row('Cancellation Requested on', $requestedAt)
            . ksers_mail_info_row('Refund Amount', $refundAmountHtml, true)
            . ksers_mail_info_row('Refund Method', $refundMethod);
        $fallback = 'Member';
        $category = 'Registration';
        $reason = 'registration cancellation';
    }
    if ($name === '') {
        $name = $fallback;
    }
    $noticePlain = [$policy];
    $noticeHtml = [ksers_mail_h($policy)];
    if ($showCard) {
        $noticePlain[] = $card;
        $noticeHtml[] = ksers_mail_h($card);
    }
    if ($showBank) {
        $noticePlain[] = $bank;
        $noticeHtml[] = $bankHtml;
    }
    $plain = $intro . "\n\n"
        . ($korea ? "등록번호: " : "Registration No.: ") . $regNo . "\n"
        . ($korea ? "이름: " : "Name: ") . $name . "\n"
        . ($korea ? "취소 요청일: " : "Cancellation Requested on: ") . $requestedAt . "\n"
        . ($korea ? "환불 금액: " : "Refund Amount: ") . $refundAmountPlain . "\n"
        . ($korea ? "환불 방법: " : "Refund Method: ") . $refundMethod . "\n\n"
        . implode("\n", $noticePlain) . "\n";
    return [
        'lang' => $korea ? 'ko' : 'en',
        'subject' => $subject,
        'body' => $plain,
        'fields' => [
            'mail_subject' => $subject,
            'preheader' => $title,
            'mail_category' => $category,
            'mail_title' => $title,
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs([$intro]),
            'info_rows' => $rows,
            'button_url' => '',
            'button_label' => '',
            'notice_text' => implode('<br>', $noticeHtml),
            'send_reason' => $reason,
            'recipient_email' => $email,
        ],
    ];
}

function ksers_registration_cancel_notify($pdo, $memberSeq, $year, $refundAmountPlain, $refundAmountHtml, $refundMethod, $showCard, $showBank) {
    try {
        $year = (int)$year;
        $memberSeq = (int)$memberSeq;
        $stmt = $pdo->prepare(
            "SELECT p.seq, p.cancelled_at,
                    m.email, m.name_ko, m.first_name_eng, m.last_name_eng,
                    c.name_ko AS country_ko, c.name_eng AS country_en, c.iso AS country_iso
             FROM participant_info p
             JOIN member_info m ON m.seq = p.member_seq
             LEFT JOIN code c ON c.majr = 1 AND c.iso = m.country
             WHERE p.member_seq = ? AND p.year = ?"
        );
        $stmt->execute([$memberSeq, $year]);
        $participant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$participant) {
            return false;
        }
        $email = trim((string)$participant['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $korea = true;
        if (function_exists('ksers_country_profile')) {
            $korea = !empty(ksers_country_profile([
                'iso' => $participant['country_iso'],
                'name_ko' => $participant['country_ko'],
                'name_eng' => $participant['country_en'],
            ])['korea']);
        }
        $koreanName = trim((string)$participant['name_ko']);
        $englishName = trim(trim((string)$participant['first_name_eng']) . ' ' . trim((string)$participant['last_name_eng']));
        $name = $korea
            ? ($koreanName !== '' ? $koreanName : $englishName)
            : ($englishName !== '' ? $englishName : $koreanName);
        $from = function_exists('ksers_password_mail_from')
            ? ksers_password_mail_from($pdo, $year)
            : 'noreply@ksers.kr';
        $parts = ksers_registration_cancel_mail_parts(
            $year,
            $korea,
            'R' . $year . '-' . str_pad((string)$participant['seq'], 4, '0', STR_PAD_LEFT),
            $name,
            ksers_registration_cancel_when($korea),
            $refundAmountPlain,
            $refundAmountHtml,
            $refundMethod,
            $showCard,
            $showBank,
            $email
        );
        return ksers_send_mail($from, $email, $parts['subject'], $parts['body'], ksers_mail_render($parts['lang'], $parts['fields']));
    } catch (Exception $e) {
        error_log('ksers mail: registration cancellation notice failed');
        return false;
    }
}

function ksers_refund_account_label($raw) {
    $raw = trim((string)$raw);
    $digits = preg_replace('/\D+/', '', $raw);
    if (!is_string($digits) || strlen($digits) < 4) {
        return '';
    }
    $last = substr($digits, -4);
    $name = trim(preg_replace('/[0-9]+/', ' ', $raw));
    $name = trim(preg_replace('/[\-\s]+/', ' ', $name));
    if ($name === '') {
        return $last;
    }
    return $name . ' ' . $last;
}

function ksers_registration_refunded_when($korea) {
    $when = new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul'));
    $weekKo = ['일', '월', '화', '수', '목', '금', '토'];
    $weekEn = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $index = (int)$when->format('w');
    if ($korea) {
        return $when->format('Y') . '년 ' . $when->format('n') . '월 ' . $when->format('j') . '일(' . $weekKo[$index] . ') ' . $when->format('H:i') . ' (KST)';
    }
    return $when->format('F j') . ' (' . $weekEn[$index] . '), ' . $when->format('Y') . ' ' . $when->format('H:i') . ' (KST)';
}

function ksers_registration_bank_refunded_mail_parts($year, $korea, $regNo, $name, $refundedAt, $refundAmountPlain, $refundAmountHtml, $account, $email) {
    $year = (int)$year;
    $office = 'ksers2027@ksers.kr';
    $officeLink = '<a href="mailto:' . ksers_mail_h($office) . '" style="color:#0086C9;font-weight:bold;">' . ksers_mail_h($office) . '</a>';
    if ($korea) {
        $subject = 'KSERS ' . $year . ' 등록비 환불이 완료되었습니다 (' . $regNo . ')';
        $title = '등록비 환불이 완료되었습니다';
        $intro = 'KSERS ' . $year . ' 등록비 환불(계좌 송금)이 완료되었습니다. 환불 내용은 아래와 같습니다.';
        $notice = '송금 수수료를 뺀 금액이 입금됩니다. 입금이 확인되지 않으면 사무국(' . $office . ')으로 연락해 주세요.';
        $noticeHtml = ksers_mail_h('송금 수수료를 뺀 금액이 입금됩니다. 입금이 확인되지 않으면 사무국(') . $officeLink . ksers_mail_h(')으로 연락해 주세요.');
        $rows = ksers_mail_info_row('등록번호', $regNo)
            . ksers_mail_info_row('이름', $name)
            . ksers_mail_info_row('환불 일시', $refundedAt)
            . ksers_mail_info_row('환불 금액', $refundAmountHtml, true)
            . ksers_mail_info_row('환불 계좌', $account);
        $plain = $intro . "\n\n"
            . "등록번호: " . $regNo . "\n"
            . "이름: " . $name . "\n"
            . "환불 일시: " . $refundedAt . "\n"
            . "환불 금액: " . $refundAmountPlain . "\n"
            . "환불 계좌: " . $account . "\n\n"
            . $notice . "\n";
        $category = '참가 등록';
        $reason = '등록비 환불';
        $fallback = '회원';
    } else {
        $subject = 'KSERS ' . $year . ' Refund Completed (' . $regNo . ')';
        $title = 'Your refund has been completed';
        $intro = 'Your KSERS ' . $year . ' registration fee has been refunded by bank transfer. Please find the details below.';
        $notice = 'The refund amount is net of remittance charges. If you have not received the refund, please contact the Secretariat (' . $office . ').';
        $noticeHtml = ksers_mail_h('The refund amount is net of remittance charges. If you have not received the refund, please contact the Secretariat (') . $officeLink . ksers_mail_h(').');
        $rows = ksers_mail_info_row('Registration No.', $regNo)
            . ksers_mail_info_row('Name', $name)
            . ksers_mail_info_row('Refunded on', $refundedAt)
            . ksers_mail_info_row('Refund Amount', $refundAmountHtml, true)
            . ksers_mail_info_row('Refund Account', $account);
        $plain = $intro . "\n\n"
            . "Registration No.: " . $regNo . "\n"
            . "Name: " . $name . "\n"
            . "Refunded on: " . $refundedAt . "\n"
            . "Refund Amount: " . $refundAmountPlain . "\n"
            . "Refund Account: " . $account . "\n\n"
            . $notice . "\n";
        $category = 'Registration';
        $reason = 'registration refund';
        $fallback = 'Member';
    }
    if ($name === '') {
        $name = $fallback;
    }
    return [
        'lang' => $korea ? 'ko' : 'en',
        'subject' => $subject,
        'body' => $plain,
        'fields' => [
            'mail_subject' => $subject,
            'preheader' => $title,
            'mail_category' => $category,
            'mail_title' => $title,
            'recipient_name' => $name,
            'body_paragraphs' => ksers_mail_paragraphs([$intro]),
            'info_rows' => $rows,
            'button_url' => '',
            'button_label' => '',
            'notice_text' => $noticeHtml,
            'send_reason' => $reason,
            'recipient_email' => $email,
        ],
    ];
}

function ksers_registration_bank_refunded_notify($pdo, $memberSeq, $year, $refundAmountPlain, $refundAmountHtml, $account) {
    try {
        $year = (int)$year;
        $memberSeq = (int)$memberSeq;
        $account = trim((string)$account);
        if ($account === '') {
            return false;
        }
        $stmt = $pdo->prepare(
            "SELECT p.seq,
                    m.email, m.name_ko, m.first_name_eng, m.last_name_eng,
                    c.name_ko AS country_ko, c.name_eng AS country_en, c.iso AS country_iso
             FROM member_info m
             LEFT JOIN participant_info p ON p.member_seq = m.seq AND p.year = ?
             LEFT JOIN code c ON c.majr = 1 AND c.iso = m.country
             WHERE m.seq = ?"
        );
        $stmt->execute([$year, $memberSeq]);
        $participant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$participant) {
            return false;
        }
        $email = trim((string)$participant['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $korea = true;
        if (function_exists('ksers_country_profile')) {
            $korea = !empty(ksers_country_profile([
                'iso' => $participant['country_iso'],
                'name_ko' => $participant['country_ko'],
                'name_eng' => $participant['country_en'],
            ])['korea']);
        }
        $koreanName = trim((string)$participant['name_ko']);
        $englishName = trim(trim((string)$participant['first_name_eng']) . ' ' . trim((string)$participant['last_name_eng']));
        $name = $korea
            ? ($koreanName !== '' ? $koreanName : $englishName)
            : ($englishName !== '' ? $englishName : $koreanName);
        $seq = isset($participant['seq']) ? (int)$participant['seq'] : 0;
        $regNo = $seq > 0 ? ('R' . $year . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT)) : '—';
        $from = function_exists('ksers_password_mail_from')
            ? ksers_password_mail_from($pdo, $year)
            : 'noreply@ksers.kr';
        $parts = ksers_registration_bank_refunded_mail_parts(
            $year,
            $korea,
            $regNo,
            $name,
            ksers_registration_refunded_when($korea),
            $refundAmountPlain,
            $refundAmountHtml,
            $account,
            $email
        );
        return ksers_send_mail($from, $email, $parts['subject'], $parts['body'], ksers_mail_render($parts['lang'], $parts['fields']));
    } catch (Exception $e) {
        error_log('ksers mail: bank refund completion notice failed');
        return false;
    }
}

function ksers_registration_bank_refund_mail_due($pdo, $year) {
    $year = (int)$year;
    if (!function_exists('ksers_bank_refund_from') || ksers_seoul_today() < ksers_bank_refund_from($pdo, $year)) {
        return 0;
    }
    $stmt = $pdo->prepare(
        "SELECT seq, member_seq, amount, currency
         FROM payment_order
         WHERE year = ? AND method = 'bank' AND status = 'refunded'
           AND refund_mail_sent = 0 AND refund_account_label <> ''"
    );
    $stmt->execute([$year]);
    $mark = $pdo->prepare("UPDATE payment_order SET refund_mail_sent = 1 WHERE seq = ? AND refund_mail_sent = 0");
    $sent = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $order) {
        $accountStmt = $pdo->prepare("SELECT refund_account_label FROM payment_order WHERE seq = ?");
        $accountStmt->execute([(int)$order['seq']]);
        $account = trim((string)$accountStmt->fetchColumn());
        if ($account === '') {
            continue;
        }
        $korea = function_exists('ksers_member_is_korea') ? ksers_member_is_korea($pdo, (int)$order['member_seq']) : true;
        $split = ksers_payment_order_amounts($pdo, (int)$order['seq']);
        if ($split['registration'] < 1 && $split['dinner'] < 1) {
            $currency = function_exists('ksers_fee_currency') ? ksers_fee_currency($order) : 'KRW';
            $split = ['registration' => (int)$order['amount'], 'dinner' => 0];
            $buckets = [$currency => $split];
        } else {
            $currency = function_exists('ksers_fee_currency') ? ksers_fee_currency($order) : 'KRW';
            $buckets = [$currency => $split];
        }
        $amountText = ksers_registration_refund_amount_text($korea, $buckets);
        if (!ksers_registration_bank_refunded_notify($pdo, (int)$order['member_seq'], $year, $amountText['plain'], $amountText['html'], $account)) {
            continue;
        }
        $mark->execute([(int)$order['seq']]);
        $sent++;
    }
    return $sent;
}
