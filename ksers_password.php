<?php

function ksers_password_ensure_tables($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_reset (
            seq int NOT NULL AUTO_INCREMENT,
            member_seq int NOT NULL,
            token_hash char(64) NOT NULL,
            expires_at datetime NOT NULL,
            used_at datetime DEFAULT NULL,
            PRIMARY KEY (seq),
            UNIQUE KEY uq_password_reset_hash (token_hash),
            KEY idx_password_reset_member (member_seq)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
}

function ksers_password_same($left, $right) {
    return mb_strtolower(trim((string)$left), 'UTF-8') === mb_strtolower(trim((string)$right), 'UTF-8');
}

function ksers_password_match_state($pdo, $year, $country, $email, $nameKo, $firstName, $lastName) {
    $countryStmt = $pdo->prepare("SELECT minr, name_ko, name_eng, dial, iso FROM code WHERE majr = 1 AND state = 1 AND iso = ?");
    $countryStmt->execute([$country]);
    $countryRow = $countryStmt->fetch(PDO::FETCH_ASSOC);
    if (!$countryRow) {
        return 'none';
    }
    $korea = ksers_country_profile($countryRow)['korea'];
    $nameKo = trim((string)$nameKo);
    $firstName = trim((string)$firstName);
    $lastName = trim((string)$lastName);
    $stmt = $pdo->prepare(
        "SELECT email, name_ko, first_name_eng, last_name_eng
         FROM member_info
         WHERE year = ? AND country = ? AND (
            LOWER(email) = LOWER(?)
            OR (? <> '' AND LOWER(TRIM(name_ko)) = LOWER(?))
            OR (? <> '' AND ? <> '' AND LOWER(TRIM(first_name_eng)) = LOWER(?) AND LOWER(TRIM(last_name_eng)) = LOWER(?))
         )"
    );
    $stmt->execute([
        (int)$year,
        $country,
        $email,
        $korea ? $nameKo : '',
        $nameKo,
        $korea ? '' : $firstName,
        $korea ? '' : $lastName,
        $firstName,
        $lastName,
    ]);
    $emailOk = false;
    $nameOk = false;
    $sameAccount = false;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rowEmail = mb_strtolower(trim((string)$row['email']), 'UTF-8') === mb_strtolower(trim((string)$email), 'UTF-8');
        if ($korea) {
            $rowName = ksers_password_same($row['name_ko'], $nameKo);
        } else {
            $rowName = ksers_password_same($row['first_name_eng'], $firstName) && ksers_password_same($row['last_name_eng'], $lastName);
        }
        if ($rowEmail) {
            $emailOk = true;
        }
        if ($rowName) {
            $nameOk = true;
        }
        if ($rowEmail && $rowName) {
            $sameAccount = true;
        }
    }
    if ($sameAccount) {
        return 'match';
    }
    if ($emailOk !== $nameOk) {
        return 'partial';
    }
    return 'none';
}

function ksers_password_mail_from($pdo, $year) {
    $setting = ksers_setting($pdo, $year);
    $from = isset($setting['mail_from']) ? trim((string)$setting['mail_from']) : '';
    if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return $from;
    }
    return 'noreply@ksers.kr';
}

function ksers_password_deliver($pdo, $year, $country, $email, $nameKo, $firstName, $lastName) {
    $countryStmt = $pdo->prepare("SELECT minr, name_ko, name_eng, dial, iso FROM code WHERE majr = 1 AND state = 1 AND iso = ?");
    $countryStmt->execute([$country]);
    $countryRow = $countryStmt->fetch(PDO::FETCH_ASSOC);
    if (!$countryRow) {
        return;
    }
    $korea = ksers_country_profile($countryRow)['korea'];
    $memberStmt = $pdo->prepare(
        "SELECT seq, name_ko, first_name_eng, last_name_eng
         FROM member_info
         WHERE year = ? AND LOWER(email) = LOWER(?) AND country = ?"
    );
    $memberStmt->execute([(int)$year, $email, $country]);
    $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
    if (!$member) {
        return;
    }
    if ($korea) {
        if (!ksers_password_same($member['name_ko'], $nameKo)) {
            return;
        }
    } elseif (!ksers_password_same($member['first_name_eng'], $firstName) || !ksers_password_same($member['last_name_eng'], $lastName)) {
        return;
    }
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $pdo->prepare("DELETE FROM password_reset WHERE member_seq = ? AND used_at IS NULL")->execute([(int)$member['seq']]);
    $pdo->prepare(
        "INSERT INTO password_reset (member_seq, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
    )->execute([(int)$member['seq'], $hash]);
    $link = ksers_public_base() . '/service/reset_password.php?token=' . rawurlencode($token);
    $lang = $korea ? 'ko' : 'en';
    $recipientName = $korea
        ? trim((string)$member['name_ko'])
        : trim(trim((string)$member['first_name_eng']) . ' ' . trim((string)$member['last_name_eng']));
    if ($lang === 'ko') {
        $subject = 'KSERS ' . (int)$year . ' 비밀번호 재설정';
        $body = "비밀번호 재설정을 요청하셨습니다.\n아래 주소에서 새 비밀번호를 설정해 주세요. 이 링크는 1시간 동안 사용할 수 있습니다.\n\n" . $link . "\n\n본인이 요청하지 않았다면 이 메일을 무시해 주세요.\n";
        $fields = [
            'mail_subject' => $subject,
            'preheader' => '비밀번호 재설정 링크를 보내 드립니다. 1시간 동안 사용할 수 있습니다.',
            'mail_category' => '비밀번호 재설정',
            'mail_title' => '비밀번호 재설정을 요청하셨습니다',
            'recipient_name' => $recipientName,
            'body_paragraphs' => ksers_mail_paragraphs(['아래 버튼을 누르면 새 비밀번호를 입력하는 화면이 열립니다.']),
            'button_url' => $link,
            'button_label' => '비밀번호 재설정',
            'notice_text' => ksers_mail_h('이 버튼은 1시간 동안 사용할 수 있습니다.') . '<br>' . ksers_mail_h('본인이 요청하지 않았다면 이 메일을 무시해 주세요.'),
            'send_reason' => '비밀번호 재설정 요청',
            'recipient_email' => $email,
        ];
    } else {
        $subject = 'KSERS ' . (int)$year . ' password reset';
        $body = "You asked to reset your password.\nSet a new password at the address below. This link can be used for 1 hour.\n\n" . $link . "\n\nIf you did not ask for this, you can ignore this email.\n";
        $fields = [
            'mail_subject' => $subject,
            'preheader' => 'A password reset link is ready. It can be used for 1 hour.',
            'mail_category' => 'Password reset',
            'mail_title' => 'You asked to reset your password',
            'recipient_name' => $recipientName,
            'body_paragraphs' => ksers_mail_paragraphs(['The button below opens a page where you can enter a new password.']),
            'button_url' => $link,
            'button_label' => 'Reset password',
            'notice_text' => ksers_mail_h('This button can be used for 1 hour.') . '<br>' . ksers_mail_h('If you did not ask for this, you can ignore this email.'),
            'send_reason' => 'password reset request',
            'recipient_email' => $email,
        ];
    }
    ksers_send_mail(ksers_password_mail_from($pdo, $year), $email, $subject, $body, ksers_mail_render($lang, $fields));
}

function ksers_password_find_token($pdo, $token) {
    if (!preg_match('/^[a-f0-9]{64}$/', (string)$token)) {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT seq, member_seq FROM password_reset
         WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()"
    );
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : null;
}

function ksers_password_apply($pdo, $token, $password) {
    $row = ksers_password_find_token($pdo, $token);
    if (!$row) {
        return false;
    }
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare("UPDATE member_info SET password = HEX(AES_ENCRYPT(?, ?)) WHERE seq = ?");
        $update->execute([$password, ksers_aes_key(), (int)$row['member_seq']]);
        $used = $pdo->prepare("UPDATE password_reset SET used_at = NOW() WHERE seq = ? AND used_at IS NULL");
        $used->execute([(int)$row['seq']]);
        if ($used->rowCount() !== 1) {
            $pdo->rollBack();
            return false;
        }
        $pdo->commit();
        ksers_password_changed_notify($pdo, (int)$row['member_seq']);
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}
