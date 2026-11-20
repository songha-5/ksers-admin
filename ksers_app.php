<?php

function ksers_ensure_tables($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS event_setting (
            year smallint NOT NULL,
            title_ko varchar(120) NOT NULL,
            title_en varchar(120) NOT NULL,
            notice_ko varchar(1000) NOT NULL DEFAULT '',
            notice_en varchar(1000) NOT NULL DEFAULT '',
            bank_name varchar(80) NOT NULL DEFAULT '',
            bank_account varchar(80) NOT NULL DEFAULT '',
            bank_holder varchar(80) NOT NULL DEFAULT '',
            mail_from varchar(120) NOT NULL DEFAULT '',
            is_open tinyint NOT NULL DEFAULT 0,
            pay_date_from date DEFAULT NULL,
            pay_date_to date DEFAULT NULL,
            pay_unlimited tinyint NOT NULL DEFAULT 1,
            PRIMARY KEY (year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS fee_item (
            seq int NOT NULL AUTO_INCREMENT,
            year smallint NOT NULL,
            kind varchar(20) NOT NULL,
            name_ko varchar(80) NOT NULL,
            name_en varchar(80) NOT NULL,
            detail_ko varchar(200) NOT NULL DEFAULT '',
            detail_en varchar(200) NOT NULL DEFAULT '',
            amount int NOT NULL,
            date_from date DEFAULT NULL,
            date_to date DEFAULT NULL,
            sort_no int NOT NULL DEFAULT 0,
            state tinyint NOT NULL DEFAULT 1,
            affiliation_type varchar(40) NOT NULL DEFAULT '',
            currency char(3) NOT NULL DEFAULT 'KRW',
            fee_code varchar(40) NOT NULL DEFAULT '',
            period_code varchar(20) NOT NULL DEFAULT '',
            domestic_only tinyint NOT NULL DEFAULT 0,
            PRIMARY KEY (seq),
            KEY idx_fee_year (year, state)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS abstract_topic (
            seq int NOT NULL AUTO_INCREMENT,
            year smallint NOT NULL,
            name_ko varchar(100) NOT NULL,
            name_en varchar(100) NOT NULL,
            sort_no int NOT NULL DEFAULT 0,
            state tinyint NOT NULL DEFAULT 1,
            PRIMARY KEY (seq),
            KEY idx_topic_year (year, state)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS abstract_author (
            seq int NOT NULL AUTO_INCREMENT,
            abstract_seq int NOT NULL,
            sort_no int NOT NULL DEFAULT 0,
            first_name varchar(80) NOT NULL,
            last_name varchar(80) NOT NULL,
            affiliation varchar(200) NOT NULL,
            department varchar(120) NOT NULL DEFAULT '',
            country int NOT NULL DEFAULT 0,
            email varchar(120) NOT NULL DEFAULT '',
            presenting tinyint NOT NULL DEFAULT 0,
            corresponding tinyint NOT NULL DEFAULT 0,
            mentor tinyint NOT NULL DEFAULT 0,
            PRIMARY KEY (seq),
            KEY idx_abstract_author (abstract_seq, sort_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS payment_order (
            seq int NOT NULL AUTO_INCREMENT,
            member_seq int NOT NULL,
            year smallint NOT NULL,
            method varchar(20) NOT NULL,
            status varchar(20) NOT NULL,
            amount int NOT NULL,
            currency char(3) NOT NULL DEFAULT 'KRW',
            tid varchar(40) NOT NULL DEFAULT '',
            reg_date datetime DEFAULT NULL,
            paid_date datetime DEFAULT NULL,
            refund_date datetime DEFAULT NULL,
            PRIMARY KEY (seq),
            KEY idx_pay_member (member_seq, year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS payment_line (
            seq int NOT NULL AUTO_INCREMENT,
            order_seq int NOT NULL,
            fee_seq int NOT NULL,
            name_ko varchar(80) NOT NULL,
            name_en varchar(80) NOT NULL,
            amount int NOT NULL,
            PRIMARY KEY (seq),
            KEY idx_line_order (order_seq)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS program_session (
            seq int NOT NULL AUTO_INCREMENT,
            year smallint NOT NULL,
            session_id varchar(40) NOT NULL,
            category varchar(80) NOT NULL DEFAULT '',
            session_code varchar(40) NOT NULL DEFAULT '',
            session_title varchar(255) NOT NULL,
            session_date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            room varchar(80) NOT NULL DEFAULT '',
            bg_color varchar(20) NOT NULL DEFAULT '',
            row_span tinyint NOT NULL DEFAULT 1,
            col_span tinyint NOT NULL DEFAULT 1,
            has_link tinyint NOT NULL DEFAULT 1,
            sort_no int NOT NULL DEFAULT 0,
            is_public tinyint NOT NULL DEFAULT 1,
            reg_date datetime DEFAULT NULL,
            PRIMARY KEY (seq),
            UNIQUE KEY uq_program_session_year_id (year, session_id),
            KEY idx_program_session_year (year, session_date, sort_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS program_chair (
            seq int NOT NULL AUTO_INCREMENT,
            session_seq int NOT NULL,
            name varchar(120) NOT NULL,
            affiliation varchar(255) NOT NULL DEFAULT '',
            sort_no int NOT NULL DEFAULT 0,
            PRIMARY KEY (seq),
            KEY idx_program_chair_session (session_seq, sort_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS program_talk (
            seq int NOT NULL AUTO_INCREMENT,
            session_seq int NOT NULL,
            talk_code varchar(40) NOT NULL DEFAULT '',
            talk_title varchar(500) NOT NULL,
            speaker_name varchar(120) NOT NULL,
            speaker_affiliation varchar(255) NOT NULL DEFAULT '',
            speaker_country varchar(80) NOT NULL DEFAULT '',
            photo_url varchar(500) NOT NULL DEFAULT '',
            cv_url varchar(500) NOT NULL DEFAULT '',
            abstract_url varchar(500) NOT NULL DEFAULT '',
            note varchar(255) NOT NULL DEFAULT '',
            sort_no int NOT NULL DEFAULT 0,
            is_public tinyint NOT NULL DEFAULT 1,
            reg_date datetime DEFAULT NULL,
            PRIMARY KEY (seq),
            KEY idx_program_talk_session (session_seq, sort_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS auth_ticket (
            seq int NOT NULL AUTO_INCREMENT,
            token_hash char(64) NOT NULL,
            member_seq int NOT NULL,
            expires_at datetime NOT NULL,
            session_expires_unix int UNSIGNED DEFAULT NULL,
            used_at datetime DEFAULT NULL,
            reg_date datetime NOT NULL,
            PRIMARY KEY (seq),
            UNIQUE KEY uq_auth_ticket_hash (token_hash),
            KEY idx_auth_ticket_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS member_submission_file (
            seq int NOT NULL AUTO_INCREMENT,
            member_seq int NOT NULL,
            year smallint NOT NULL,
            kind varchar(40) NOT NULL,
            abstract_seq int DEFAULT NULL,
            original_name varchar(255) NOT NULL,
            stored_name varchar(255) NOT NULL,
            mime_type varchar(120) NOT NULL DEFAULT '',
            file_size bigint NOT NULL DEFAULT 0,
            reg_date datetime NOT NULL,
            update_date datetime DEFAULT NULL,
            PRIMARY KEY (seq),
            KEY idx_submission_year_kind (year, kind),
            KEY idx_submission_abstract (abstract_seq)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $submissionUnique = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_submission_file'
           AND INDEX_NAME = 'uq_submission_member_year_kind'"
    );
    $submissionUnique->execute();
    if ((int)$submissionUnique->fetchColumn() > 0) {
        $pdo->exec("ALTER TABLE member_submission_file DROP INDEX uq_submission_member_year_kind");
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS abstract_video_link (
            seq int NOT NULL AUTO_INCREMENT,
            token char(64) NOT NULL,
            file_seq int NOT NULL,
            abstract_seq int NOT NULL,
            year smallint NOT NULL,
            reg_date datetime NOT NULL,
            PRIMARY KEY (seq),
            UNIQUE KEY uq_video_link_token (token),
            UNIQUE KEY uq_video_link_file (file_seq),
            KEY idx_video_link_abstract (year, abstract_seq)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_mail_log (
            seq int NOT NULL AUTO_INCREMENT,
            year smallint NOT NULL,
            template_key varchar(40) NOT NULL,
            email varchar(190) NOT NULL,
            recipient_name varchar(190) NOT NULL DEFAULT '',
            subject varchar(255) NOT NULL,
            status varchar(20) NOT NULL,
            fail_reason varchar(255) NOT NULL DEFAULT '',
            reg_date datetime NOT NULL,
            PRIMARY KEY (seq),
            KEY idx_admin_mail_year (year, reg_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $column = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'abstract_info' AND COLUMN_NAME = 'mail_sent'"
    );
    $column->execute();
    if (!(int)$column->fetchColumn()) {
        $pdo->exec("ALTER TABLE abstract_info ADD COLUMN mail_sent tinyint NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE abstract_info ADD COLUMN mail_date datetime DEFAULT NULL");
    }
    $authorCountry = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'abstract_info' AND COLUMN_NAME = 'author_country'"
    );
    $authorCountry->execute();
    if (!(int)$authorCountry->fetchColumn()) {
        $pdo->exec("ALTER TABLE abstract_info ADD COLUMN author_country int NOT NULL DEFAULT 0");
    }
    $authorsLen = $pdo->prepare(
        "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'abstract_info' AND COLUMN_NAME = 'authors'"
    );
    $authorsLen->execute();
    $authorsWidth = (int)$authorsLen->fetchColumn();
    if ($authorsWidth > 0 && $authorsWidth < 1000) {
        $pdo->exec("ALTER TABLE abstract_info MODIFY authors varchar(1000) NOT NULL");
    }
    $finalType = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'abstract_info' AND COLUMN_NAME = 'final_type'"
    );
    $finalType->execute();
    if (!(int)$finalType->fetchColumn()) {
        $pdo->exec("ALTER TABLE abstract_info ADD COLUMN final_type varchar(40) NOT NULL DEFAULT 'pending'");
    }
    $authorMentor = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'abstract_author' AND COLUMN_NAME = 'mentor'"
    );
    $authorMentor->execute();
    if (!(int)$authorMentor->fetchColumn()) {
        $pdo->exec("ALTER TABLE abstract_author ADD COLUMN mentor tinyint NOT NULL DEFAULT 0");
    }
    foreach ([
        'update_date' => "ALTER TABLE abstract_info ADD COLUMN update_date datetime DEFAULT NULL",
        'agree_coauthor' => "ALTER TABLE abstract_info ADD COLUMN agree_coauthor tinyint NOT NULL DEFAULT 1",
        'agree_privacy' => "ALTER TABLE abstract_info ADD COLUMN agree_privacy tinyint NOT NULL DEFAULT 1",
        'agree_publication' => "ALTER TABLE abstract_info ADD COLUMN agree_publication tinyint NOT NULL DEFAULT 1",
    ] as $columnName => $alterSql) {
        $columnCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'abstract_info' AND COLUMN_NAME = ?"
        );
        $columnCheck->execute([$columnName]);
        if (!(int)$columnCheck->fetchColumn()) {
            $pdo->exec($alterSql);
        }
    }
    $ticketColumn = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auth_ticket' AND COLUMN_NAME = 'session_expires_unix'"
    );
    $ticketColumn->execute();
    if (!(int)$ticketColumn->fetchColumn()) {
        $pdo->exec("ALTER TABLE auth_ticket ADD COLUMN session_expires_unix int UNSIGNED DEFAULT NULL AFTER expires_at");
    }
    $paymentColumns = [
        ['fee_item', 'affiliation_type', "ALTER TABLE fee_item ADD COLUMN affiliation_type varchar(40) NOT NULL DEFAULT ''"],
        ['fee_item', 'currency', "ALTER TABLE fee_item ADD COLUMN currency char(3) NOT NULL DEFAULT 'KRW'"],
        ['fee_item', 'fee_code', "ALTER TABLE fee_item ADD COLUMN fee_code varchar(40) NOT NULL DEFAULT ''"],
        ['fee_item', 'period_code', "ALTER TABLE fee_item ADD COLUMN period_code varchar(20) NOT NULL DEFAULT ''"],
        ['fee_item', 'domestic_only', "ALTER TABLE fee_item ADD COLUMN domestic_only tinyint NOT NULL DEFAULT 0"],
        ['payment_order', 'currency', "ALTER TABLE payment_order ADD COLUMN currency char(3) NOT NULL DEFAULT 'KRW'"],
        ['event_setting', 'pay_date_from', "ALTER TABLE event_setting ADD COLUMN pay_date_from date DEFAULT NULL"],
        ['event_setting', 'pay_date_to', "ALTER TABLE event_setting ADD COLUMN pay_date_to date DEFAULT NULL"],
        ['event_setting', 'pay_unlimited', "ALTER TABLE event_setting ADD COLUMN pay_unlimited tinyint NOT NULL DEFAULT 1"],
        ['payment_order', 'tid', "ALTER TABLE payment_order ADD COLUMN tid varchar(80) NOT NULL DEFAULT ''"],
        ['payment_order', 'refund_account_label', "ALTER TABLE payment_order ADD COLUMN refund_account_label varchar(80) NOT NULL DEFAULT ''"],
        ['payment_order', 'refund_mail_sent', "ALTER TABLE payment_order ADD COLUMN refund_mail_sent tinyint NOT NULL DEFAULT 0"],
    ];
    $tidWidth = $pdo->prepare(
        "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_order' AND COLUMN_NAME = 'tid'"
    );
    $tidWidth->execute();
    $tidLength = $tidWidth->fetchColumn();
    if ($tidLength !== false && (int)$tidLength < 80) {
        $pdo->exec("ALTER TABLE payment_order MODIFY tid varchar(80) NOT NULL DEFAULT ''");
    }
    foreach ($paymentColumns as $paymentColumn) {
        $paymentCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $paymentCheck->execute([$paymentColumn[0], $paymentColumn[1]]);
        if (!(int)$paymentCheck->fetchColumn()) {
            $pdo->exec($paymentColumn[2]);
        }
    }
    $feeGrade = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fee_item' AND COLUMN_NAME = 'grade'"
    );
    $feeGrade->execute();
    if ((int)$feeGrade->fetchColumn()) {
        $pdo->exec('ALTER TABLE fee_item DROP COLUMN grade');
    }
    $pdo->exec("DELETE FROM code WHERE majr = 2");
    require_once __DIR__ . '/ksers_countries.php';
    ksers_ensure_countries($pdo);
    ksers_ensure_member_year($pdo);
    ksers_ensure_member_profile($pdo);
    ksers_ensure_participant_profile($pdo);
    require_once __DIR__ . '/ksers_glance.php';
    ksers_glance_ensure_tables($pdo);
    require_once __DIR__ . '/ksers_scientific.php';
    ksers_scientific_ensure_tables($pdo);
    require_once __DIR__ . '/ksers_gallery.php';
    ksers_gallery_ensure_tables($pdo);
    require_once __DIR__ . '/ksers_news.php';
    ksers_news_ensure_tables($pdo);
    require_once __DIR__ . '/ksers_mail.php';
    require_once __DIR__ . '/ksers_admin_mail.php';
    require_once __DIR__ . '/ksers_password.php';
    ksers_password_ensure_tables($pdo);
    $seed = $pdo->prepare("SELECT year FROM event_setting WHERE year = 2027");
    $seed->execute();
    if (!$seed->fetch(PDO::FETCH_ASSOC)) {
        $insert = $pdo->prepare(
            "INSERT INTO event_setting (year, title_ko, title_en, is_open) VALUES (2027, ?, ?, 1)"
        );
        $insert->execute(['2027 국제학술대회', '2027 International Conference']);
    }
    ksers_seed_abstract_topics($pdo, 2027);
}

function ksers_ensure_member_year($pdo) {
    $column = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_info' AND COLUMN_NAME = 'year'"
    );
    $column->execute();
    if (!(int)$column->fetchColumn()) {
        $pdo->exec("ALTER TABLE member_info ADD COLUMN year smallint NOT NULL DEFAULT 2027");
    }
    $pdo->exec("UPDATE member_info SET year = 2027 WHERE year IS NULL OR year < 2027 OR year > 2035");
    $index = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_info' AND INDEX_NAME = 'uq_member_email_year'"
    );
    $index->execute();
    if (!(int)$index->fetchColumn()) {
        $dup = $pdo->query(
            "SELECT COUNT(*) FROM (
                SELECT email, year FROM member_info GROUP BY email, year HAVING COUNT(*) > 1
             ) d"
        );
        if ($dup && (int)$dup->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE member_info ADD UNIQUE KEY uq_member_email_year (email, year)");
        }
    }
}

function ksers_ensure_member_profile($pdo) {
    $columns = [
        'telephone' => "ALTER TABLE member_info ADD COLUMN telephone varchar(45) NOT NULL DEFAULT ''",
        'salutation' => "ALTER TABLE member_info ADD COLUMN salutation varchar(20) NOT NULL DEFAULT ''",
        'affiliation_type' => "ALTER TABLE member_info ADD COLUMN affiliation_type varchar(40) NOT NULL DEFAULT ''",
        'department' => "ALTER TABLE member_info ADD COLUMN department varchar(45) NOT NULL DEFAULT ''",
        'agree_email' => "ALTER TABLE member_info ADD COLUMN agree_email tinyint NOT NULL DEFAULT 0",
        'user_class' => "ALTER TABLE member_info ADD COLUMN user_class char(2) NOT NULL DEFAULT 'M0'",
    ];
    foreach ($columns as $name => $sql) {
        $column = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_info' AND COLUMN_NAME = ?"
        );
        $column->execute([$name]);
        if (!(int)$column->fetchColumn()) {
            $pdo->exec($sql);
            if ($name === 'user_class') {
                ksers_backfill_user_class($pdo);
            }
        }
    }
    foreach (['department_file', 'department_stored'] as $name) {
        $column = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_info' AND COLUMN_NAME = ?"
        );
        $column->execute([$name]);
        if ((int)$column->fetchColumn()) {
            $pdo->exec('ALTER TABLE member_info DROP COLUMN ' . $name);
        }
    }
}

function ksers_ensure_participant_profile($pdo) {
    $columns = [
        'ksers_member' => "ALTER TABLE participant_info ADD COLUMN ksers_member varchar(20) NOT NULL DEFAULT ''",
        'category' => "ALTER TABLE participant_info ADD COLUMN category varchar(20) NOT NULL DEFAULT ''",
        'senior_lifetime' => "ALTER TABLE participant_info ADD COLUMN senior_lifetime tinyint NOT NULL DEFAULT 0",
        'license_no' => "ALTER TABLE participant_info ADD COLUMN license_no varchar(45) NOT NULL DEFAULT ''",
        'certificate_file' => "ALTER TABLE participant_info ADD COLUMN certificate_file varchar(255) NOT NULL DEFAULT ''",
        'certificate_stored' => "ALTER TABLE participant_info ADD COLUMN certificate_stored varchar(120) NOT NULL DEFAULT ''",
        'support_file' => "ALTER TABLE participant_info ADD COLUMN support_file varchar(255) NOT NULL DEFAULT ''",
        'support_stored' => "ALTER TABLE participant_info ADD COLUMN support_stored varchar(120) NOT NULL DEFAULT ''",
        'support_status' => "ALTER TABLE participant_info ADD COLUMN support_status varchar(20) NOT NULL DEFAULT ''",
        'support_reason' => "ALTER TABLE participant_info ADD COLUMN support_reason varchar(1000) NOT NULL DEFAULT ''",
        'support_handler' => "ALTER TABLE participant_info ADD COLUMN support_handler varchar(120) NOT NULL DEFAULT ''",
        'dietary' => "ALTER TABLE participant_info ADD COLUMN dietary varchar(20) NOT NULL DEFAULT ''",
        'dietary_detail' => "ALTER TABLE participant_info ADD COLUMN dietary_detail varchar(200) NOT NULL DEFAULT ''",
        'pay_confirm' => "ALTER TABLE participant_info ADD COLUMN pay_confirm char(1) NOT NULL DEFAULT 'N'",
        'cme_apply' => "ALTER TABLE participant_info ADD COLUMN cme_apply char(1) NOT NULL DEFAULT ''",
    ];
    foreach ($columns as $name => $sql) {
        $column = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_info' AND COLUMN_NAME = ?"
        );
        $column->execute([$name]);
        if (!(int)$column->fetchColumn()) {
            $pdo->exec($sql);
        }
    }
    $participantColumn = function ($name) use ($pdo) {
        $column = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_info' AND COLUMN_NAME = ?"
        );
        $column->execute([$name]);
        return (int)$column->fetchColumn() > 0;
    };
    if (!$participantColumn('support_date')) {
        $pdo->exec('ALTER TABLE participant_info ADD COLUMN support_date datetime NULL');
    }
    if (!$participantColumn('support_handled_at')) {
        $pdo->exec('ALTER TABLE participant_info ADD COLUMN support_handled_at datetime NULL');
    }
    if (!$participantColumn('cancelled_at')) {
        $pdo->exec('ALTER TABLE participant_info ADD COLUMN cancelled_at datetime NULL');
    }
    $stuckSupport = $pdo->query(
        "SELECT p.seq, p.member_seq, p.year
         FROM participant_info p
         JOIN member_info m ON m.seq = p.member_seq
         WHERE p.category IN ('student', 'nurse')
           AND UPPER(TRIM(m.country)) <> 'KR'
           AND p.support_status = 'approved'
           AND p.support_handler = ''
           AND p.support_handled_at IS NULL"
    );
    if ($stuckSupport) {
        $cancelFree = $pdo->prepare(
            "UPDATE payment_order o
             SET o.status = 'cancelled'
             WHERE o.member_seq = ? AND o.year = ? AND o.status = 'paid' AND o.amount = 0
               AND EXISTS (
                    SELECT 1 FROM payment_line l
                    LEFT JOIN fee_item f ON f.seq = l.fee_seq
                    WHERE l.order_seq = o.seq
                      AND (f.fee_code IS NULL OR f.fee_code NOT IN ('dinner_individual', 'dinner_group'))
               )"
        );
        $markReview = $pdo->prepare(
            "UPDATE participant_info
             SET support_status = 'review'
             WHERE seq = ? AND support_status = 'approved' AND support_handler = '' AND support_handled_at IS NULL"
        );
        foreach ($stuckSupport as $stuckRow) {
            $cancelFree->execute([(int)$stuckRow['member_seq'], (int)$stuckRow['year']]);
            $markReview->execute([(int)$stuckRow['seq']]);
            ksers_sync_pay_confirm($pdo, (int)$stuckRow['member_seq'], (int)$stuckRow['year']);
        }
    }
    $pdo->exec(
        "UPDATE participant_info p
         JOIN member_info m ON m.seq = p.member_seq
         SET p.support_status = 'review'
         WHERE p.category IN ('student', 'nurse') AND UPPER(TRIM(m.country)) <> 'KR'
           AND p.support_file <> '' AND p.support_status = ''"
    );
    if ($participantColumn('membership_type')) {
        $pdo->exec(
            "UPDATE participant_info
             SET ksers_member = 'lifetime'
             WHERE ksers_member = 'member' AND membership_type = 'lifetime'"
        );
        $pdo->exec(
            "UPDATE participant_info
             SET ksers_member = 'regular'
             WHERE ksers_member = 'member'"
        );
    }
    foreach (['membership_no', 'membership_type'] as $name) {
        if ($participantColumn($name)) {
            $pdo->exec('ALTER TABLE participant_info DROP COLUMN ' . $name);
        }
    }
    foreach (['grade', 'name_ko', 'first_name_eng', 'last_name_eng', 'affiliation_ko', 'affiliation_eng', 'country'] as $name) {
        $column = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_info' AND COLUMN_NAME = ?"
        );
        $column->execute([$name]);
        if ((int)$column->fetchColumn()) {
            $pdo->exec('ALTER TABLE participant_info DROP COLUMN ' . $name);
        }
    }
    $pdo->exec(
        "UPDATE participant_info p
         SET p.pay_confirm = 'Y'
         WHERE p.pay_confirm <> 'Y'
           AND EXISTS (
                SELECT 1 FROM payment_order o
                WHERE o.member_seq = p.member_seq AND o.year = p.year AND o.status = 'paid'
           )"
    );
    $pdo->exec(
        "UPDATE participant_info
         SET cme_apply = 'N'
         WHERE cme_apply = '' AND license_no = '0000'"
    );
    $pdo->exec(
        "UPDATE participant_info
         SET cme_apply = 'Y'
         WHERE cme_apply = '' AND license_no REGEXP '^[0-9]{1,7}$' AND license_no <> '0000'"
    );
}

function ksers_participant_cancelled($pdo, $memberSeq, $year) {
    $stmt = $pdo->prepare("SELECT cancelled_at FROM participant_info WHERE member_seq = ? AND year = ?");
    $stmt->execute([(int)$memberSeq, (int)$year]);
    $value = $stmt->fetchColumn();
    return $value !== false && $value !== null && trim((string)$value) !== '';
}

function ksers_sync_pay_confirm($pdo, $memberSeq, $year) {
    $paid = $pdo->prepare(
        "SELECT COUNT(*) FROM payment_order WHERE member_seq = ? AND year = ? AND status = 'paid'"
    );
    $paid->execute([(int)$memberSeq, (int)$year]);
    $flag = (int)$paid->fetchColumn() > 0 ? 'Y' : 'N';
    $update = $pdo->prepare(
        "UPDATE participant_info SET pay_confirm = ? WHERE member_seq = ? AND year = ?"
    );
    $update->execute([$flag, (int)$memberSeq, (int)$year]);
    ksers_sync_user_class($pdo, $memberSeq);
}

function ksers_user_class_label($class) {
    $labels = [
        'M0' => ['회원가입유저', 'Signed-up user'],
        'M1' => ['초록 접수자', 'Abstract submitter'],
        'M2' => ['초록접수 + 참가자', 'Abstract and participant'],
        'M3' => ['참가자', 'Participant'],
    ];
    $code = isset($labels[$class]) ? $class : 'M0';
    return t($labels[$code][0], $labels[$code][1]);
}

function ksers_member_user_class($pdo, $memberSeq, $year) {
    $abstract = $pdo->prepare("SELECT COUNT(*) FROM abstract_info WHERE member_seq = ? AND year = ?");
    $abstract->execute([(int)$memberSeq, (int)$year]);
    $hasAbstract = (int)$abstract->fetchColumn() > 0;
    $paid = $pdo->prepare(
        "SELECT COUNT(*) FROM payment_order WHERE member_seq = ? AND year = ? AND status = 'paid'"
    );
    $paid->execute([(int)$memberSeq, (int)$year]);
    $hasPaid = (int)$paid->fetchColumn() > 0;
    if (!$hasPaid) {
        $flag = $pdo->prepare(
            "SELECT COUNT(*) FROM participant_info WHERE member_seq = ? AND year = ? AND pay_confirm = 'Y'"
        );
        $flag->execute([(int)$memberSeq, (int)$year]);
        $hasPaid = (int)$flag->fetchColumn() > 0;
    }
    if ($hasAbstract && $hasPaid) {
        return 'M2';
    }
    if ($hasAbstract) {
        return 'M1';
    }
    if ($hasPaid) {
        return 'M3';
    }
    return 'M0';
}

function ksers_sync_user_class($pdo, $memberSeq) {
    $memberSeq = (int)$memberSeq;
    if ($memberSeq < 1) {
        return;
    }
    $member = $pdo->prepare("SELECT year FROM member_info WHERE seq = ?");
    $member->execute([$memberSeq]);
    $year = (int)$member->fetchColumn();
    if ($year < 1) {
        return;
    }
    $class = ksers_member_user_class($pdo, $memberSeq, $year);
    $update = $pdo->prepare("UPDATE member_info SET user_class = ? WHERE seq = ?");
    $update->execute([$class, $memberSeq]);
}

function ksers_backfill_user_class($pdo) {
    $members = $pdo->query("SELECT seq, year FROM member_info");
    if (!$members) {
        return;
    }
    $update = $pdo->prepare("UPDATE member_info SET user_class = ? WHERE seq = ?");
    foreach ($members as $member) {
        $update->execute([
            ksers_member_user_class($pdo, (int)$member['seq'], (int)$member['year']),
            (int)$member['seq'],
        ]);
    }
}

function ksers_registration_categories() {
    return [
        'specialist' => ['전문의', 'Specialist'],
        'resident' => ['전공의(군의관·공보의 포함)', 'Resident (including military and public health doctors)'],
        'student' => ['학생', 'Student'],
        'nurse' => ['간호사', 'Nurse'],
        'other' => ['기타', 'Other'],
    ];
}

function ksers_registration_member_choices() {
    return [
        'lifetime' => ['평생회원', 'Lifetime member'],
        'regular' => ['정회원', 'Regular member'],
        'non_member' => ['비회원', 'Non-member'],
    ];
}

function ksers_payment_fee_codes() {
    return [
        'physician_lifetime' => ['전문의 · 평생회원', 'Physician · Lifetime member'],
        'physician_regular' => ['전문의 · 정회원', 'Physician · Regular member'],
        'physician_non' => ['전문의 · 비회원', 'Physician · Non-member'],
        'physician' => ['전문의', 'Physician'],
        'trainee' => ['전공의·군의관·공보의', 'Resident, military, public health'],
        'student_nurse' => ['학생·간호사', 'Student, nurse'],
        'lifetime_65' => ['65세 이상 평생회원', 'Lifetime member aged 65 or older'],
        'dinner_individual' => ['갈라 디너 · 개인', 'Gala dinner · Individual'],
        'dinner_group' => ['갈라 디너 · 단체', 'Gala dinner · Group'],
    ];
}

function ksers_payment_period_codes() {
    return [
        'early' => ['얼리버드', 'Early-bird'],
        'pre' => ['사전등록', 'Pre-registration'],
        'onsite' => ['현장등록', 'On-site'],
        'any' => ['전기간', 'All periods'],
    ];
}

function ksers_payment_fee_optional($code) {
    return $code === 'dinner_individual' || $code === 'dinner_group';
}

function ksers_parse_program_day($label, $id, $year) {
    $year = (int)$year;
    foreach ([$label, str_replace('-', ' ', (string)$id)] as $text) {
        $text = trim((string)$text);
        if ($text === '') {
            continue;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $text, $match)) {
            return $match[1] . '-' . $match[2] . '-' . $match[3];
        }
        $stamp = strtotime($text . ' ' . $year);
        if ($stamp) {
            return date('Y-m-d', $stamp);
        }
    }
    return '';
}

function ksers_onsite_dates($pdo, $year) {
    $stmt = $pdo->prepare('SELECT id, label FROM program_days WHERE year = ? ORDER BY sort_order, id');
    $stmt->execute([(int)$year]);
    $dates = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $parsed = ksers_parse_program_day($row['label'], $row['id'], $year);
        if ($parsed !== '') {
            $dates[$parsed] = $parsed;
        }
    }
    $dates = array_values($dates);
    sort($dates);
    return $dates;
}

function ksers_seoul_today() {
    $now = new DateTime('now', new DateTimeZone('Asia/Seoul'));
    return $now->format('Y-m-d');
}

function ksers_bank_refund_from($pdo, $year) {
    $end = sprintf('%04d-04-24', (int)$year);
    $from = new DateTimeImmutable($end . ' 00:00:00', new DateTimeZone('Asia/Seoul'));
    return $from->modify('+1 month')->format('Y-m-d');
}

function ksers_onsite_range($year, $onsiteDates) {
    $year = (int)$year;
    if (!$onsiteDates) {
        $onsiteDates = [sprintf('%04d-04-22', $year), sprintf('%04d-04-24', $year)];
    }
    $first = strtotime($onsiteDates[0]);
    $last = strtotime($onsiteDates[count($onsiteDates) - 1]);
    $sameMonth = date('Y-m', $first) === date('Y-m', $last);
    $ko = $sameMonth
        ? (date('n', $first) . '월 ' . date('j', $first) . '~' . date('j', $last) . '일')
        : (date('n월 j일', $first) . '~' . date('n월 j일', $last));
    $enHeader = $sameMonth
        ? (date('F j', $first) . '-' . date('j', $last))
        : (date('M j', $first) . '-' . date('M j', $last));
    $enNotice = $sameMonth
        ? (date('F j', $first) . '–' . date('j, Y', $last))
        : (date('F j', $first) . '–' . date('F j, Y', $last));
    return [
        'start' => $onsiteDates[0],
        'ko' => $ko,
        'en_header' => $enHeader,
        'en_notice' => $enNotice,
    ];
}

function ksers_payment_period_label($code, $year, $onsiteDates) {
    $range = ksers_onsite_range($year, $onsiteDates);
    if ($code === 'early') {
        return t('얼리버드 등록 (2월 28일까지)', 'Early-bird Registration (by February 28)');
    }
    if ($code === 'pre') {
        return t('사전등록 (4월 11일까지)', 'Pre Registration (by April 11)');
    }
    if ($code === 'onsite') {
        return t('현장등록 (' . $range['ko'] . ')', 'On-site Registration (' . $range['en_header'] . ')');
    }
    return t('전기간', 'All periods');
}

function ksers_registration_period_notice($day, $year, $onsiteDates) {
    $year = (int)$year;
    $range = ksers_onsite_range($year, $onsiteDates);
    $earlyEnd = sprintf('%04d-02-28', $year);
    $preEnd = sprintf('%04d-04-11', $year);
    if ($day <= $earlyEnd) {
        return [
            'lead' => t('현재 ', ''),
            'emphasis' => t('얼리버드 등록', 'Early-bird Registration'),
            'tail' => t(
                ' 기간입니다. (~' . ksers_fee_date_label($earlyEnd, false) . '까지)',
                ' is now open (until ' . ksers_fee_date_label($earlyEnd, true) . ').'
            ),
        ];
    }
    if ($day <= $preEnd) {
        return [
            'lead' => t('현재 ', ''),
            'emphasis' => t('사전등록', 'Pre Registration'),
            'tail' => t(
                ' 기간입니다. (~' . ksers_fee_date_label($preEnd, false) . '까지)',
                ' is now open (until ' . ksers_fee_date_label($preEnd, true) . ').'
            ),
        ];
    }
    if ($day < $range['start']) {
        return [
            'lead' => t(
                '온라인 등록이 마감되었습니다. 현장 등록(' . $range['ko'] . ')을 이용해 주세요.',
                'Online registration is closed. Please register on-site (' . $range['en_notice'] . ').'
            ),
            'emphasis' => '',
            'tail' => '',
        ];
    }
    return [
        'lead' => t('현재 ', ''),
        'emphasis' => t('현장등록', 'On-site Registration'),
        'tail' => t(
            ' 기간입니다. 현장 등록 데스크를 이용해 주세요.',
            ' (' . $range['en_notice'] . '). Please register at the on-site registration desk.'
        ),
    ];
}

function ksers_payment_period_until($code, $year, $onsiteDates) {
    $year = (int)$year;
    if ($code === 'early') {
        return sprintf('%04d-02-28', $year);
    }
    if ($code === 'pre') {
        return sprintf('%04d-04-11', $year);
    }
    if ($code === 'onsite' && $onsiteDates) {
        return $onsiteDates[count($onsiteDates) - 1];
    }
    return '';
}

function ksers_fee_date_label($isoDate, $english) {
    $stamp = strtotime((string)$isoDate);
    if (!$stamp) {
        return '';
    }
    if ($english) {
        return date('F j, Y', $stamp);
    }
    return date('Y', $stamp) . '년 ' . date('n', $stamp) . '월 ' . date('j', $stamp) . '일';
}

function ksers_registration_fee_board($pdo, $year, $isKorea) {
    $year = (int)$year;
    ksers_seed_registration_fees($pdo, $year);
    $onsiteDates = ksers_onsite_dates($pdo, $year);
    $today = ksers_seoul_today();
    $current = ksers_payment_period_for($today, $year, $onsiteDates);
    $notice = ksers_registration_period_notice($today, $year, $onsiteDates);
    $periods = ['early', 'pre', 'onsite'];
    $stmt = $pdo->prepare("SELECT * FROM fee_item WHERE year = ? AND state = 1 AND fee_code <> '' ORDER BY seq");
    $stmt->execute([$year]);
    $grouped = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $grouped[$row['fee_code']][$row['period_code']][] = $row;
    }
    $catalog = ksers_payment_fee_codes();
    $codes = array_keys($grouped);
    usort($codes, function ($left, $right) use ($catalog) {
        $leftAt = array_search($left, array_keys($catalog), true);
        $rightAt = array_search($right, array_keys($catalog), true);
        $leftAt = $leftAt === false ? 1000 : $leftAt;
        $rightAt = $rightAt === false ? 1000 : $rightAt;
        if ($leftAt === $rightAt) {
            return strcmp($left, $right);
        }
        return $leftAt - $rightAt;
    });
    $rows = [];
    foreach ($codes as $feeCode) {
        if ($isKorea && $feeCode === 'physician') {
            continue;
        }
        if (!$isKorea && in_array($feeCode, ['physician_lifetime', 'physician_regular', 'physician_non'], true)) {
            continue;
        }
        $amounts = [];
        $visible = false;
        foreach ($periods as $period) {
            $candidates = isset($grouped[$feeCode][$period]) ? $grouped[$feeCode][$period] : [];
            if (!$candidates && isset($grouped[$feeCode]['any'])) {
                $candidates = $grouped[$feeCode]['any'];
            }
            $chosen = $candidates ? ksers_choose_fee_rows($candidates, $isKorea) : null;
            $amounts[$period] = $chosen;
            if ($chosen) {
                $visible = true;
            }
        }
        if (!$visible) {
            continue;
        }
        $sample = null;
        foreach ($grouped[$feeCode] as $periodRows) {
            if ($periodRows) {
                $sample = $periodRows[0];
                break;
            }
        }
        if (isset($catalog[$feeCode])) {
            $label = t($catalog[$feeCode][0], $catalog[$feeCode][1]);
        } else {
            $label = $sample ? ksers_text($sample, 'name_ko', 'name_en') : $feeCode;
        }
        $rows[] = ['code' => $feeCode, 'label' => $label, 'amounts' => $amounts];
    }
    if (!$isKorea) {
        $rows = ksers_merge_overseas_physician_rows($rows);
    }
    $headings = [];
    foreach ($periods as $period) {
        $headings[$period] = ksers_payment_period_label($period, $year, $onsiteDates);
    }
    return [
        'emphasis' => $notice['emphasis'],
        'notice_lead' => $notice['lead'],
        'notice_tail' => $notice['tail'],
        'current' => $current,
        'headings' => $headings,
        'rows' => $rows,
    ];
}

function ksers_fee_amount_signature($row) {
    $signature = [];
    foreach ($row['amounts'] as $chosen) {
        $signature[] = $chosen ? ((int)$chosen['amount'] . ksers_fee_currency($chosen)) : '-';
    }
    return implode('|', $signature);
}

function ksers_merge_overseas_physician_rows($rows) {
    $byCode = [];
    foreach ($rows as $index => $row) {
        $byCode[$row['code']] = $index;
    }
    $memberCodes = ['physician_lifetime', 'physician_regular'];
    $memberIndexes = [];
    foreach ($memberCodes as $code) {
        if (isset($byCode[$code])) {
            $memberIndexes[] = $byCode[$code];
        }
    }
    $skip = [];
    $replacements = [];
    if (count($memberIndexes) === 2
        && ksers_fee_amount_signature($rows[$memberIndexes[0]]) === ksers_fee_amount_signature($rows[$memberIndexes[1]])) {
        $signature = ksers_fee_amount_signature($rows[$memberIndexes[0]]);
        $group = $memberIndexes;
        $label = t('전문의 · KSERS 회원', 'Physician · KSERS member');
        if (isset($byCode['physician_non'])
            && ksers_fee_amount_signature($rows[$byCode['physician_non']]) === $signature) {
            $group[] = $byCode['physician_non'];
            $label = t('전문의', 'Physician');
        }
        foreach ($group as $index) {
            $skip[$index] = true;
        }
        $replacements[$group[0]] = [
            'code' => 'physician',
            'label' => $label,
            'amounts' => $rows[$group[0]]['amounts'],
        ];
    }
    $merged = [];
    foreach ($rows as $index => $row) {
        if (isset($replacements[$index])) {
            $merged[] = $replacements[$index];
            continue;
        }
        if (isset($skip[$index])) {
            continue;
        }
        $merged[] = $row;
    }
    return $merged;
}

function ksers_payment_period_for($date, $year, $onsiteDates) {
    $year = (int)$year;
    $day = substr((string)$date, 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        $day = date('Y-m-d');
    }
    if ($onsiteDates && in_array($day, $onsiteDates, true)) {
        return 'onsite';
    }
    if ($day <= sprintf('%04d-02-28', $year)) {
        return 'early';
    }
    if ($day <= sprintf('%04d-04-11', $year)) {
        return 'pre';
    }
    return 'onsite';
}

function ksers_participant_fee_code($participant, $isKorea = true) {
    if (!$participant) {
        return '';
    }
    $category = isset($participant['category']) ? $participant['category'] : '';
    $ksersMember = isset($participant['ksers_member']) ? $participant['ksers_member'] : '';
    if ($isKorea && $ksersMember === 'lifetime' && !empty($participant['senior_lifetime'])) {
        return 'lifetime_65';
    }
    if ($category === 'student' || $category === 'nurse') {
        return 'student_nurse';
    }
    if ($category === 'resident') {
        return 'trainee';
    }
    if ($category === 'specialist') {
        if (!$isKorea) {
            return 'physician';
        }
        if ($ksersMember === 'lifetime') {
            return 'physician_lifetime';
        }
        if ($ksersMember === 'regular') {
            return 'physician_regular';
        }
        return 'physician_non';
    }
    return '';
}

function ksers_choose_fee_rows($rows, $isKorea) {
    $open = [];
    foreach ($rows as $row) {
        if (!empty($row['domestic_only']) && !$isKorea) {
            continue;
        }
        $open[ksers_fee_currency($row)] = $row;
    }
    $hasKrw = isset($open['KRW']);
    $hasUsd = isset($open['USD']);
    if ($hasKrw && $hasUsd) {
        return $isKorea ? $open['KRW'] : $open['USD'];
    }
    if ($hasKrw) {
        return $open['KRW'];
    }
    if ($hasUsd) {
        return $open['USD'];
    }
    return null;
}

function ksers_registration_fee_schedule() {
    return [
        ['physician_lifetime', 'early', 'KRW', 250000, 0],
        ['physician_lifetime', 'pre', 'KRW', 300000, 0],
        ['physician_lifetime', 'onsite', 'KRW', 400000, 0],
        ['physician_regular', 'early', 'KRW', 270000, 0],
        ['physician_regular', 'pre', 'KRW', 320000, 0],
        ['physician_regular', 'onsite', 'KRW', 420000, 0],
        ['physician_non', 'early', 'KRW', 300000, 0],
        ['physician_non', 'pre', 'KRW', 350000, 0],
        ['physician_non', 'onsite', 'KRW', 450000, 0],
        ['physician', 'early', 'USD', 200, 0],
        ['physician', 'pre', 'USD', 300, 0],
        ['physician', 'onsite', 'USD', 400, 0],
        ['trainee', 'early', 'KRW', 120000, 0],
        ['trainee', 'early', 'USD', 120, 0],
        ['trainee', 'pre', 'KRW', 140000, 0],
        ['trainee', 'pre', 'USD', 160, 0],
        ['trainee', 'onsite', 'KRW', 170000, 0],
        ['trainee', 'onsite', 'USD', 170, 0],
        ['student_nurse', 'any', 'KRW', 0, 1],
        ['lifetime_65', 'any', 'KRW', 0, 1],
        ['dinner_individual', 'any', 'KRW', 70000, 0],
        ['dinner_individual', 'any', 'USD', 70, 0],
        ['dinner_group', 'any', 'KRW', 50000, 0],
        ['dinner_group', 'any', 'USD', 50, 0],
    ];
}

function ksers_seed_registration_fees($pdo, $year) {
    $year = (int)$year;
    $hasShared = $pdo->prepare("SELECT COUNT(*) FROM fee_item WHERE year = ? AND fee_code = 'physician' AND state = 1");
    $hasShared->execute([$year]);
    if ((int)$hasShared->fetchColumn() > 0) {
        return;
    }
    $codes = ksers_payment_fee_codes();
    $find = $pdo->prepare(
        "SELECT seq FROM fee_item WHERE year = ? AND fee_code = ? AND period_code = ? AND currency = ? ORDER BY seq DESC LIMIT 1"
    );
    $update = $pdo->prepare(
        "UPDATE fee_item SET name_ko = ?, name_en = ?, amount = ?, domestic_only = ?, state = 1, kind = 'schedule' WHERE seq = ?"
    );
    $insert = $pdo->prepare(
        "INSERT INTO fee_item
            (year, kind, name_ko, name_en, detail_ko, detail_en, amount, sort_no, state, affiliation_type, currency, fee_code, period_code, domestic_only)
         VALUES (?, 'schedule', ?, ?, '', '', ?, 0, 1, '', ?, ?, ?, ?)"
    );
    foreach (ksers_registration_fee_schedule() as $row) {
        $label = $codes[$row[0]];
        $find->execute([$year, $row[0], $row[1], $row[2]]);
        $seq = (int)$find->fetchColumn();
        if ($seq > 0) {
            $update->execute([$label[0], $label[1], $row[3], $row[4], $seq]);
        } else {
            $insert->execute([$year, $label[0], $label[1], $row[3], $row[2], $row[0], $row[1], $row[4]]);
        }
    }
    $hide = $pdo->prepare(
        "UPDATE fee_item SET state = 0
         WHERE year = ? AND currency = 'USD' AND fee_code IN ('physician_lifetime', 'physician_regular', 'physician_non')"
    );
    $hide->execute([$year]);
}

function ksers_support_status_label($status, $applicable) {
    if (!$applicable) {
        return '—';
    }
    if ($status === 'approved') {
        return t('등록 완료(무료)', 'Registered (free)');
    }
    if ($status === 'rejected') {
        return t('반려', 'Rejected');
    }
    return t('증빙 확인 중', 'Checking the document');
}

function ksers_registration_diets() {
    return [
        'none' => 'None',
        'vegetarian' => 'Vegetarian',
        'halal' => 'Halal',
        'kosher' => 'Kosher',
        'pescatarian' => 'Pescatarian',
        'others' => 'Others',
    ];
}

function ksers_affiliation_types() {
    return [
        'university_hospital' => ['대학병원', 'University hospital'],
        'general_hospital' => ['종합병원', 'General hospital'],
        'hospital_clinic' => ['병원·의원', 'Hospital · Clinic'],
        'public_health' => ['공공의료기관·보건소', 'Public medical institution · Health center'],
        'military_medical' => ['군 의료기관', 'Military medical institution'],
        'research_company' => ['연구기관·기업', 'Research institution · Company'],
        'school' => ['학교', 'School'],
        'other' => ['기타', 'Other'],
    ];
}

function ksers_ensure_countries($pdo) {
    $columns = [];
    $info = $pdo->query(
        "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'code'"
    );
    foreach ($info->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[$column['COLUMN_NAME']] = $column;
    }
    if (isset($columns['minr']) && $columns['minr']['DATA_TYPE'] === 'tinyint') {
        $pdo->exec('ALTER TABLE code MODIFY minr smallint NOT NULL');
    }
    if (!isset($columns['name_ko']) || (int)$columns['name_ko']['CHARACTER_MAXIMUM_LENGTH'] < 120) {
        $pdo->exec('ALTER TABLE code MODIFY name_ko varchar(120) NOT NULL');
    }
    if (!isset($columns['name_eng']) || (int)$columns['name_eng']['CHARACTER_MAXIMUM_LENGTH'] < 120) {
        $pdo->exec('ALTER TABLE code MODIFY name_eng varchar(120) DEFAULT NULL');
    }
    if (!isset($columns['iso'])) {
        $pdo->exec("ALTER TABLE code ADD COLUMN iso char(2) NOT NULL DEFAULT ''");
    }
    if (!isset($columns['dial'])) {
        $pdo->exec("ALTER TABLE code ADD COLUMN dial varchar(8) NOT NULL DEFAULT ''");
    }
    $filled = (int)$pdo->query("SELECT COUNT(*) FROM code WHERE majr = 1 AND iso <> ''")->fetchColumn();
    if ($filled < count(ksers_country_catalog())) {
    $next = (int)$pdo->query('SELECT COALESCE(MAX(minr), 0) FROM code WHERE majr = 1')->fetchColumn();
    $findIso = $pdo->prepare('SELECT seq FROM code WHERE majr = 1 AND iso = ? LIMIT 1');
    $findLegacy = $pdo->prepare("SELECT seq FROM code WHERE majr = 1 AND iso = '' AND name_eng = ? LIMIT 1");
    $update = $pdo->prepare('UPDATE code SET name_ko = ?, name_eng = ?, iso = ?, dial = ?, state = 1 WHERE seq = ?');
    $insert = $pdo->prepare(
        'INSERT INTO code (majr, minr, name_ko, name_eng, iso, dial, state, reg_date) VALUES (1, ?, ?, ?, ?, ?, 1, NOW())'
    );
    foreach (ksers_country_catalog() as $country) {
        $findIso->execute([$country['iso']]);
        $existing = $findIso->fetch(PDO::FETCH_ASSOC);
        if (!$existing && $country['iso'] === 'KR') {
            $findLegacy->execute(['Korea, Republic of']);
            $existing = $findLegacy->fetch(PDO::FETCH_ASSOC);
        }
        if (!$existing && ($country['iso'] === 'KR' || $country['iso'] === 'US')) {
            $findLegacy->execute([$country['en']]);
            $existing = $findLegacy->fetch(PDO::FETCH_ASSOC);
        }
        if ($existing) {
            $update->execute([$country['ko'], $country['en'], $country['iso'], $country['dial'], (int)$existing['seq']]);
            continue;
        }
        $next++;
        $insert->execute([$next, $country['ko'], $country['en'], $country['iso'], $country['dial']]);
    }
    }
    $pdo->exec("UPDATE code SET name_eng = 'Korea' WHERE majr = 1 AND iso = 'KR'");
    $talkTable = $pdo->query("SHOW TABLES LIKE 'program_talk'")->fetch();
    if ($talkTable) {
        $pdo->exec("UPDATE program_talk SET speaker_country = 'Korea' WHERE speaker_country = 'Korea, Republic of'");
    }
    ksers_ensure_member_country($pdo);
}

function ksers_ensure_member_country($pdo) {
    $type = $pdo->query(
        "SELECT DATA_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_info' AND COLUMN_NAME = 'country'"
    )->fetchColumn();
    if (!in_array($type, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint'], true)) {
        return;
    }
    $pdo->exec("ALTER TABLE member_info ADD COLUMN country_iso char(2) NOT NULL DEFAULT ''");
    $pdo->exec(
        "UPDATE member_info m
         JOIN code c ON c.majr = 1 AND c.minr = m.country AND c.iso <> ''
         SET m.country_iso = c.iso"
    );
    $pdo->exec('ALTER TABLE member_info DROP COLUMN country');
    $pdo->exec("ALTER TABLE member_info CHANGE country_iso country char(2) NOT NULL DEFAULT ''");
}

function ksers_country_rows($pdo) {
    return $pdo->query(
        "SELECT minr, name_ko, name_eng, dial, iso
         FROM code
         WHERE majr = 1 AND state = 1
         ORDER BY CASE WHEN iso = 'KR' THEN 0 ELSE 1 END, name_eng, name_ko"
    )->fetchAll(PDO::FETCH_ASSOC);
}

function ksers_country_profile($row) {
    $english = isset($row['name_eng']) ? $row['name_eng'] : '';
    $korean = isset($row['name_ko']) ? $row['name_ko'] : '';
    $dial = isset($row['dial']) ? trim((string)$row['dial']) : '';
    if ($dial === '') {
        $dial = '+';
    }
    $iso = isset($row['iso']) ? strtoupper(trim((string)$row['iso'])) : '';
    $isKorea = ($iso === 'KR' || $korean === '대한민국' || $english === 'Korea' || $english === 'Korea, Republic of');
    if ($isKorea) {
        $mobile = '010-1111-1111';
        $telephone = '02-1234-5678';
    } elseif ($english === 'United States' || $korean === '미국' || $iso === 'US') {
        $mobile = '201-555-0123';
        $telephone = '201-555-0199';
    } else {
        $mobile = '000-0000-0000';
        $telephone = '000-0000-0000';
    }
    return [
        'korea' => $isKorea,
        'dial' => $dial,
        'mobile_placeholder' => $mobile,
        'telephone_placeholder' => $telephone,
    ];
}

function ksers_phone_local($dial, $stored) {
    $stored = trim((string)$stored);
    $dial = trim((string)$dial);
    $prefix = $dial . ' ';
    if ($dial !== '' && $dial !== '+' && strpos($stored, $prefix) === 0) {
        return substr($stored, strlen($prefix));
    }
    return $stored;
}

function ksers_phone_store($dial, $local) {
    $local = trim((string)$local);
    if ($local === '') {
        return '';
    }
    if (substr_count($local, '-') !== 2) {
        return null;
    }
    $dial = trim((string)$dial);
    if ($dial === '' || $dial === '+') {
        return $local;
    }
    return $dial . ' ' . $local;
}

function ksers_valid_congress_year($year) {
    $year = (int)$year;
    if ($year < 2027 || $year > 2035) {
        return 0;
    }
    return $year;
}

function ksers_site_year($pdo = null) {
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }
    if (!empty($_SESSION['seq'])) {
        $memberYear = ksers_valid_congress_year(isset($_SESSION['member_year']) ? $_SESSION['member_year'] : 0);
        if (!$memberYear && $pdo) {
            $stmt = $pdo->prepare("SELECT year FROM member_info WHERE seq = ?");
            $stmt->execute([(int)$_SESSION['seq']]);
            $memberYear = ksers_valid_congress_year($stmt->fetchColumn());
            if ($memberYear) {
                $_SESSION['member_year'] = $memberYear;
            }
        }
        if ($memberYear) {
            $resolved = $memberYear;
            return $resolved;
        }
    }
    $next = '';
    if (!empty($_POST['next'])) {
        $next = (string)$_POST['next'];
    } elseif (!empty($_GET['next'])) {
        $next = (string)$_GET['next'];
    }
    if ($next !== '' && function_exists('ksers_homepage_return')) {
        $safe = ksers_homepage_return($next);
        $path = $safe !== '' ? parse_url($safe, PHP_URL_PATH) : '';
        if (is_string($path) && preg_match('#^/(20[0-9]{2})(?:/|$)#', $path, $match)) {
            $fromNext = ksers_valid_congress_year($match[1]);
            if ($fromNext) {
                $_SESSION['site_year'] = $fromNext;
                $resolved = $fromNext;
                return $resolved;
            }
        }
    }
    $fromRequest = 0;
    if (isset($_GET['year'])) {
        $fromRequest = ksers_valid_congress_year($_GET['year']);
    }
    if ($fromRequest) {
        $_SESSION['site_year'] = $fromRequest;
        $resolved = $fromRequest;
        return $resolved;
    }
    $fromSession = ksers_valid_congress_year(isset($_SESSION['site_year']) ? $_SESSION['site_year'] : 0);
    if ($fromSession) {
        $resolved = $fromSession;
        return $resolved;
    }
    if (isset($_POST['congress_year'])) {
        $fromPost = ksers_valid_congress_year($_POST['congress_year']);
        if ($fromPost) {
            $_SESSION['site_year'] = $fromPost;
            $resolved = $fromPost;
            return $resolved;
        }
    }
    $_SESSION['site_year'] = 2027;
    $resolved = 2027;
    return $resolved;
}

function ksers_open_year($pdo) {
    $stmt = $pdo->query("SELECT year FROM event_setting WHERE is_open = 1 ORDER BY year DESC LIMIT 1");
    $year = $stmt ? $stmt->fetchColumn() : 2027;
    return $year ? (int)$year : 2027;
}

function ksers_admin_year($pdo) {
    $year = isset($_SESSION['admin_year']) ? (int)$_SESSION['admin_year'] : 0;
    if ($year < 2027 || $year > 2035) {
        $year = ksers_open_year($pdo);
    }
    return $year;
}

function ksers_setting($pdo, $year) {
    $stmt = $pdo->prepare("SELECT * FROM event_setting WHERE year = ?");
    $stmt->execute([(int)$year]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row;
    }
    return [
        'year' => (int)$year,
        'title_ko' => $year . ' 국제학술대회',
        'title_en' => $year . ' International Conference',
        'notice_ko' => '',
        'notice_en' => '',
        'bank_name' => '',
        'bank_account' => '',
        'bank_holder' => '',
        'mail_from' => '',
        'is_open' => 0,
        'pay_date_from' => null,
        'pay_date_to' => null,
        'pay_unlimited' => 1,
    ];
}

function ksers_fee_in_period($row) {
    $from = isset($row['date_from']) && $row['date_from'] !== null ? substr((string)$row['date_from'], 0, 10) : '';
    $to = isset($row['date_to']) && $row['date_to'] !== null ? substr((string)$row['date_to'], 0, 10) : '';
    if ($from === '' && $to === '') {
        return true;
    }
    $today = date('Y-m-d');
    if ($from !== '' && $today < $from) {
        return false;
    }
    if ($to !== '' && $today > $to) {
        return false;
    }
    return true;
}

function ksers_seed_abstract_topics($pdo, $year) {
    $topics = [
        ['Upper Gastrointestinal', '상부위장관'],
        ['Bariatric & Metabolic', '비만 대사'],
        ['Endocrine (Thyroid, Adrenal gland) & Breast', '내분비(갑상선, 부신) 및 유방'],
        ['Lower Gastrointestinal', '하부위장관'],
        ['Hernia', '탈장'],
        ['Liver', '간'],
        ['Biliary & Pancreas', '담췌'],
        ['Pediatric', '소아'],
        ['Surgical Endoscopy (including NOTES)', '수술 내시경 (NOTES 포함)'],
        ['New Technology & Innovation', '신기술 및 혁신'],
        ['Others', '기타'],
    ];
    $find = $pdo->prepare("SELECT seq FROM abstract_topic WHERE year = ? AND name_en = ? AND state = 1 LIMIT 1");
    $insert = $pdo->prepare("INSERT INTO abstract_topic (year, name_ko, name_en, sort_no, state) VALUES (?, ?, ?, ?, 1)");
    $update = $pdo->prepare("UPDATE abstract_topic SET name_ko = ?, sort_no = ? WHERE seq = ?");
    foreach ($topics as $index => $topic) {
        $find->execute([(int)$year, $topic[0]]);
        $seq = $find->fetchColumn();
        if ($seq) {
            $update->execute([$topic[1], $index + 1, (int)$seq]);
        } else {
            $insert->execute([(int)$year, $topic[1], $topic[0], $index + 1]);
        }
    }
}

function ksers_abstract_review_statuses() {
    return [
        'received' => ['admin' => ['접수 완료', 'Submitted'], 'member' => ['접수 완료', 'Submitted'], 'class' => 'is-received'],
        'review_wait' => ['admin' => ['심사 대기', 'Awaiting review'], 'member' => ['접수 완료', 'Submitted'], 'class' => 'is-received'],
        'reviewing' => ['admin' => ['심사 중', 'Under review'], 'member' => ['접수 완료', 'Submitted'], 'class' => 'is-received'],
        'review_done' => ['admin' => ['심사 완료', 'Review complete'], 'member' => ['접수 완료', 'Submitted'], 'class' => 'is-received'],
        'accepted' => ['admin' => ['결과 통보', 'Result sent'], 'member' => ['채택', 'Accepted'], 'class' => 'is-accept'],
        'rejected' => ['admin' => ['결과 통보', 'Result sent'], 'member' => ['불채택', 'Not accepted'], 'class' => 'is-reject'],
        'withdrawn' => ['admin' => ['철회', 'Withdrawn'], 'member' => ['철회', 'Withdrawn'], 'class' => 'is-withdrawn'],
    ];
}

function ksers_abstract_member_status($code) {
    $map = ksers_abstract_review_statuses();
    $code = isset($map[$code]) ? (string)$code : 'received';
    return [
        'code' => $code,
        'label' => t($map[$code]['member'][0], $map[$code]['member'][1]),
        'admin' => t($map[$code]['admin'][0], $map[$code]['admin'][1]),
        'class' => $map[$code]['class'],
    ];
}

function ksers_member_preferred_name($row) {
    $korean = trim(isset($row['name_ko']) ? (string)$row['name_ko'] : '');
    $first = trim(isset($row['first_name_eng']) ? (string)$row['first_name_eng'] : '');
    $last = trim(isset($row['last_name_eng']) ? (string)$row['last_name_eng'] : '');
    $english = trim($first . ' ' . $last);
    if ($korean !== '' && $english !== '') {
        return $english;
    }
    return $english !== '' ? $english : $korean;
}

function ksers_text($row, $koKey, $enKey) {
    $ko = isset($row[$koKey]) ? $row[$koKey] : '';
    $en = isset($row[$enKey]) ? $row[$enKey] : '';
    if (ksers_lang() === 'en') {
        return $en !== '' ? $en : $ko;
    }
    return $ko !== '' ? $ko : $en;
}

function ksers_fee_currency($row) {
    return (isset($row['currency']) && $row['currency'] === 'USD') ? 'USD' : 'KRW';
}

function ksers_money($amount, $currency = 'KRW') {
    $formatted = number_format((int)$amount);
    if ($currency === 'USD') {
        return 'USD ' . $formatted;
    }
    return ksers_lang() === 'en' ? ($formatted . ' KRW') : ($formatted . '원');
}

function ksers_member_is_korea($pdo, $memberSeq) {
    $stmt = $pdo->prepare(
        "SELECT c.iso, c.name_ko, c.name_eng
         FROM member_info m
         LEFT JOIN code c ON c.majr = 1 AND c.iso = m.country
         WHERE m.seq = ?"
    );
    $stmt->execute([(int)$memberSeq]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return true;
    }
    $profile = ksers_country_profile($row);
    return !empty($profile['korea']);
}

function ksers_choose_currency_row($rows, $isKorea) {
    $open = [];
    foreach ($rows as $row) {
        if (!ksers_fee_in_period($row)) {
            continue;
        }
        $open[ksers_fee_currency($row)] = $row;
    }
    $hasKrw = isset($open['KRW']);
    $hasUsd = isset($open['USD']);
    if ($hasKrw && $hasUsd) {
        return $isKorea ? $open['KRW'] : $open['USD'];
    }
    if ($hasKrw) {
        return $open['KRW'];
    }
    if ($hasUsd) {
        return $open['USD'];
    }
    return null;
}

function ksers_member_is_admin($pdo) {
    static $known = null;
    if ($known !== null) {
        return $known;
    }
    $seq = isset($_SESSION['seq']) ? (int)$_SESSION['seq'] : 0;
    if ($seq < 1) {
        $known = false;
        return false;
    }
    $stmt = $pdo->prepare("SELECT is_admin FROM member_info WHERE seq = ?");
    $stmt->execute([$seq]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $known = $row && (int)$row['is_admin'] === 1;
    return $known;
}

function ksers_permission_denied() {
    http_response_code(403);
    $title = t('권한 없음', 'No permission');
    $message = t('이 페이지에 접근할 권한이 없습니다.', 'You do not have permission to open this page.');
    $back = t('마이페이지로 이동', 'Go to My Page');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="' . h(ksers_lang()) . '"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . h($title) . '</title>';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">';
    echo '<link href="/service/ksers.css" rel="stylesheet"></head><body>';
    echo '<main class="container py-5"><div class="row justify-content-center"><div class="col-lg-6">';
    echo '<div class="card border-0 shadow-sm"><div class="card-body p-4 p-md-5 bg-white text-center">';
    echo '<h1 class="h4 fw-bold mb-3">' . h($title) . '</h1>';
    echo '<p class="mb-4">' . h($message) . '</p>';
    echo '<a class="btn btn-primary-custom fw-bold" href="/service/mypage.php">' . h($back) . '</a>';
    echo '</div></div></div></div></main></body></html>';
    exit;
}

function ksers_require_participant($pdo) {
    if (ksers_member_is_admin($pdo)) {
        ksers_permission_denied();
    }
}

function ksers_require_admin($pdo) {
    $seq = isset($_SESSION['seq']) ? (int)$_SESSION['seq'] : 0;
    if ($seq < 1) {
        header('Location: /service/login.php');
        exit;
    }
    if (!ksers_member_is_admin($pdo)) {
        ksers_permission_denied();
    }
}

function ksers_program_dir() {
    $dir = getenv('KSERS_PROGRAM_DIR');
    return ($dir !== false && $dir !== '') ? $dir : '/data/program';
}

function ksers_public_base() {
    $base = getenv('KSERS_PUBLIC_BASE');
    if ($base !== false && $base !== '') {
        return rtrim($base, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    if ($host === '') {
        return '';
    }
    return ($https ? 'https://' : 'http://') . $host;
}

function ksers_program_file_url($talkSeq, $field) {
    $path = '/service/program_file.php?seq=' . (int)$talkSeq . '&f=' . rawurlencode($field);
    $base = ksers_public_base();
    return $base === '' ? $path : ($base . $path);
}

function ksers_payable_items($pdo, $year, $memberSeq) {
    ksers_seed_registration_fees($pdo, $year);
    $participantStmt = $pdo->prepare("SELECT * FROM participant_info WHERE member_seq = ? AND year = ?");
    $participantStmt->execute([(int)$memberSeq, (int)$year]);
    $participant = $participantStmt->fetch(PDO::FETCH_ASSOC);
    $isKorea = ksers_member_is_korea($pdo, $memberSeq);
    $onsiteDates = ksers_onsite_dates($pdo, $year);
    $stmt = $pdo->prepare(
        "SELECT * FROM fee_item
         WHERE year = ? AND state = 1
           AND kind <> 'affiliation'
           AND fee_code = ''
           AND (date_from IS NULL OR date_from <= CURDATE())
           AND (date_to IS NULL OR date_to >= CURDATE())
         ORDER BY sort_no, seq"
    );
    $stmt->execute([(int)$year]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $member = $pdo->prepare("SELECT affiliation_type FROM member_info WHERE seq = ?");
    $member->execute([(int)$memberSeq]);
    $affiliation = (string)$member->fetchColumn();
    $affRows = [];
    if ($affiliation !== '') {
        $affStmt = $pdo->prepare(
            "SELECT * FROM fee_item
             WHERE year = ? AND state = 1 AND kind = 'affiliation' AND affiliation_type = ?
             ORDER BY seq"
        );
        $affStmt->execute([(int)$year, $affiliation]);
        $affRows = $affStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $affFee = $affRows ? ksers_choose_currency_row($affRows, $isKorea) : null;
    $payCurrency = $affFee ? ksers_fee_currency($affFee) : 'KRW';
    $usedStmt = $pdo->prepare(
        "SELECT l.fee_seq FROM payment_line l
         JOIN payment_order o ON o.seq = l.order_seq
         WHERE o.member_seq = ? AND o.year = ? AND (o.status = 'paid' OR (o.status = 'pending' AND o.method = 'bank'))"
    );
    $usedStmt->execute([(int)$memberSeq, (int)$year]);
    $used = [];
    foreach ($usedStmt->fetchAll(PDO::FETCH_COLUMN) as $feeSeq) {
        $used[(int)$feeSeq] = true;
    }
    $fee = null;
    $extras = [];
    foreach ($rows as $row) {
        if (isset($used[(int)$row['seq']])) {
            continue;
        }
        if ($row['kind'] === 'fee') {
            if ($fee === null) {
                $fee = $row;
            }
        } elseif (ksers_fee_currency($row) === $payCurrency) {
            $extras[] = $row;
        }
    }
    $items = [];
    $feeCode = ksers_participant_fee_code($participant, $isKorea);
    $scheduleMatched = $feeCode !== '';
    $periodCode = $participant
        ? ksers_payment_period_for(isset($participant['reg_date']) ? $participant['reg_date'] : '', $year, $onsiteDates)
        : '';
    if ($feeCode !== '') {
        $matched = $pdo->prepare(
            "SELECT * FROM fee_item
             WHERE year = ? AND state = 1 AND fee_code = ? AND (period_code = ? OR period_code = 'any')
             ORDER BY seq"
        );
        $matched->execute([(int)$year, $feeCode, $periodCode]);
        $matchedRows = $matched->fetchAll(PDO::FETCH_ASSOC);
        $specific = [];
        $any = [];
        foreach ($matchedRows as $matchedRow) {
            if ($matchedRow['period_code'] === 'any') {
                $any[] = $matchedRow;
            } else {
                $specific[] = $matchedRow;
            }
        }
        $chosen = ksers_choose_fee_rows($specific ? $specific : $any, $isKorea);
        if ($chosen && !isset($used[(int)$chosen['seq']])) {
            $items[] = $chosen;
            $payCurrency = ksers_fee_currency($chosen);
        }
    }
    $dinner = $pdo->prepare(
        "SELECT * FROM fee_item
         WHERE year = ? AND state = 1 AND fee_code IN ('dinner_individual', 'dinner_group')
         ORDER BY seq"
    );
    $dinner->execute([(int)$year]);
    $dinnerGroups = [];
    foreach ($dinner->fetchAll(PDO::FETCH_ASSOC) as $dinnerRow) {
        $dinnerGroups[$dinnerRow['fee_code']][] = $dinnerRow;
    }
    foreach ($dinnerGroups as $dinnerRows) {
        $chosenDinner = ksers_choose_fee_rows($dinnerRows, $isKorea);
        if ($chosenDinner && !isset($used[(int)$chosenDinner['seq']])) {
            $chosenDinner['pay_optional'] = 1;
            $items[] = $chosenDinner;
        }
    }
    if (!$scheduleMatched && !$items) {
        if ($affRows) {
            if ($affFee && !isset($used[(int)$affFee['seq']])) {
                $items[] = $affFee;
            }
        } elseif ($fee) {
            $items[] = $fee;
        }
        foreach ($extras as $extra) {
            $items[] = $extra;
        }
    }
    return $items;
}

function ksers_send_mail($from, $to, $subject, $body, $html = '') {
    $to = trim((string)$to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $configuredFrom = trim(ksers_env('BREVO_FROM', ''));
    if ($configuredFrom !== '' && filter_var($configuredFrom, FILTER_VALIDATE_EMAIL)) {
        $from = $configuredFrom;
    }
    $from = trim((string)$from);
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        error_log('ksers mail: Brevo sender is not configured');
        return false;
    }
    if (trim((string)$html) !== '' && function_exists('ksers_mail_apply_logo')) {
        $html = ksers_mail_apply_logo($html);
    }
    $inlineLogo = strpos((string)$html, 'cid:ksers-logo') !== false;
    $apiKey = trim(ksers_env('BREVO_API_KEY', ''));
    if ($apiKey !== '' && !$inlineLogo) {
        return ksers_send_mail_api($from, $to, $subject, $body, $html, $apiKey);
    }
    $user = trim(ksers_env('BREVO_SMTP_USER', ''));
    $key = ksers_env('BREVO_SMTP_KEY', '');
    if ($user === '' || $key === '') {
        error_log('ksers mail: Brevo sender or SMTP login is not configured');
        return false;
    }
    $dir = __DIR__ . '/lib/PHPMailer/';
    if (!is_file($dir . 'PHPMailer.php') || !is_file($dir . 'SMTP.php') || !is_file($dir . 'Exception.php')) {
        error_log('ksers mail: PHPMailer files are missing');
        return false;
    }
    require_once $dir . 'Exception.php';
    require_once $dir . 'SMTP.php';
    require_once $dir . 'PHPMailer.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $port = (int)ksers_env('BREVO_SMTP_PORT', '587');
        if ($port <= 0) {
            $port = 587;
        }
        $mail->isSMTP();
        $mail->Host = ksers_env('BREVO_SMTP_HOST', 'smtp-relay.brevo.com');
        $mail->Port = $port;
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $key;
        $mail->SMTPSecure = $port === 465
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 20;
        $fromName = trim(ksers_env('BREVO_FROM_NAME', 'KSERS'));
        $mail->setFrom($from, $fromName !== '' ? $fromName : 'KSERS');
        $mail->addAddress($to);
        $mail->Subject = (string)$subject;
        if (trim((string)$html) !== '') {
            $mail->isHTML(true);
            $mail->Body = (string)$html;
            $mail->AltBody = (string)$body;
            $logoPath = __DIR__ . '/mail/KSERS2027_mail_logo_mark.png';
            if (strpos((string)$html, 'cid:ksers-logo') !== false && is_file($logoPath)) {
                $mail->addEmbeddedImage($logoPath, 'ksers-logo.png', 'KSERS2027_mail_logo_mark.png');
            }
        } else {
            $mail->isHTML(false);
            $mail->Body = (string)$body;
        }
        return $mail->send();
    } catch (Exception $e) {
        error_log('ksers mail: ' . $mail->ErrorInfo);
        return false;
    }
}

function ksers_send_mail_api($from, $to, $subject, $body, $html, $apiKey) {
    $fromName = trim(ksers_env('BREVO_FROM_NAME', 'KSERS'));
    $payload = [
        'sender' => [
            'email' => $from,
            'name' => $fromName !== '' ? $fromName : 'KSERS',
        ],
        'to' => [['email' => $to]],
        'subject' => (string)$subject,
        'textContent' => (string)$body,
    ];
    if (trim((string)$html) !== '') {
        $payload['htmlContent'] = (string)$html;
        $logoPath = __DIR__ . '/mail/KSERS2027_mail_logo_mark.png';
        if (strpos((string)$html, 'cid:ksers-logo') !== false && is_file($logoPath)) {
            $encoded = base64_encode((string)file_get_contents($logoPath));
            if ($encoded !== '') {
                $payload['attachment'] = [[
                    'content' => $encoded,
                    'name' => 'ksers-logo.png',
                ]];
            }
        }
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log('ksers mail: Brevo API payload could not be encoded');
        return false;
    }
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "accept: application/json\r\ncontent-type: application/json\r\napi-key: " . $apiKey . "\r\n",
            'content' => $json,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents('https://api.brevo.com/v3/smtp/email', false, $context);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) {
        $status = (int)$match[1];
    }
    if ($response === false || $status < 200 || $status >= 300) {
        error_log('ksers mail: Brevo API HTTP ' . $status);
        return false;
    }
    return true;
}

function ksers_csv_cell($value) {
    $value = (string)$value;
    if (preg_match('/^[=+\-@]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

function ksers_paid_registration($pdo, $memberSeq, $year) {
    $stmt = $pdo->prepare(
        "SELECT pay_confirm FROM participant_info WHERE member_seq = ? AND year = ?"
    );
    $stmt->execute([(int)$memberSeq, (int)$year]);
    return (string)$stmt->fetchColumn() === 'Y';
}

function ksers_registration_locked($pdo, $memberSeq, $year) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM payment_order o
         JOIN payment_line l ON l.order_seq = o.seq
         LEFT JOIN fee_item f ON f.seq = l.fee_seq
         WHERE o.member_seq = ? AND o.year = ?
           AND o.status IN ('pending', 'paid')
           AND (f.fee_code IS NULL OR f.fee_code NOT IN ('dinner_individual', 'dinner_group'))"
    );
    $stmt->execute([(int)$memberSeq, (int)$year]);
    return (int)$stmt->fetchColumn() > 0;
}

function ksers_payment_status_label($status, $method, $amount) {
    if ($status === 'paid' && (int)$amount === 0) {
        return t('등록 완료(무료)', 'Registered (free)');
    }
    if ($status === 'paid') {
        return t('결제 완료', 'Paid');
    }
    if ($status === 'pending' && $method === 'bank') {
        return t('입금 확인 중', 'Checking the transfer');
    }
    if ($status === 'pending') {
        return t('미결제', 'Unpaid');
    }
    if ($status === 'cancelled') {
        return t('취소', 'Cancelled');
    }
    if ($status === 'refunded') {
        return t('전액 환불', 'Refunded in full');
    }
    return (string)$status;
}

function ksers_record_free_registration($pdo, $memberSeq, $year) {
    $memberSeq = (int)$memberSeq;
    $year = (int)$year;
    $participantStmt = $pdo->prepare("SELECT * FROM participant_info WHERE member_seq = ? AND year = ?");
    $participantStmt->execute([$memberSeq, $year]);
    $participant = $participantStmt->fetch(PDO::FETCH_ASSOC);
    if (!$participant || !empty($participant['cancelled_at'])) {
        return false;
    }
    $isKorea = ksers_member_is_korea($pdo, $memberSeq);
    $category = isset($participant['category']) ? (string)$participant['category'] : '';
    $supportStatus = isset($participant['support_status']) ? (string)$participant['support_status'] : '';
    if (!$isKorea && ($category === 'student' || $category === 'nurse') && $supportStatus !== 'approved') {
        return false;
    }
    $feeCode = ksers_participant_fee_code($participant, $isKorea);
    if ($feeCode === '') {
        return false;
    }
    $onsiteDates = ksers_onsite_dates($pdo, $year);
    $periodCode = ksers_payment_period_for(
        isset($participant['reg_date']) ? $participant['reg_date'] : ksers_seoul_today(),
        $year,
        $onsiteDates
    );
    $matched = $pdo->prepare(
        "SELECT * FROM fee_item
         WHERE year = ? AND state = 1 AND fee_code = ? AND (period_code = ? OR period_code = 'any')
         ORDER BY seq"
    );
    $matched->execute([$year, $feeCode, $periodCode]);
    $specific = [];
    $any = [];
    foreach ($matched->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['period_code'] === 'any') {
            $any[] = $row;
        } else {
            $specific[] = $row;
        }
    }
    $chosen = ksers_choose_fee_rows($specific ? $specific : $any, $isKorea);
    if (!$chosen || (int)$chosen['amount'] !== 0) {
        return false;
    }
    $used = $pdo->prepare(
        "SELECT COUNT(*) FROM payment_line l
         JOIN payment_order o ON o.seq = l.order_seq
         WHERE l.fee_seq = ? AND o.member_seq = ? AND o.year = ? AND o.status IN ('pending', 'paid')"
    );
    $used->execute([(int)$chosen['seq'], $memberSeq, $year]);
    if ((int)$used->fetchColumn() > 0) {
        return false;
    }
    $pdo->beginTransaction();
    $order = $pdo->prepare(
        "INSERT INTO payment_order (member_seq, year, method, status, amount, currency, reg_date, paid_date)
         VALUES (?, ?, 'bank', 'paid', 0, ?, NOW(), NOW())"
    );
    $order->execute([$memberSeq, $year, ksers_fee_currency($chosen)]);
    $orderSeq = (int)$pdo->lastInsertId();
    $line = $pdo->prepare(
        "INSERT INTO payment_line (order_seq, fee_seq, name_ko, name_en, amount) VALUES (?, ?, ?, ?, 0)"
    );
    $line->execute([$orderSeq, (int)$chosen['seq'], $chosen['name_ko'], $chosen['name_en']]);
    ksers_sync_pay_confirm($pdo, $memberSeq, $year);
    $pdo->commit();
    return true;
}
