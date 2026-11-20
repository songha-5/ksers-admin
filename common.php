<?php

function ksers_load_env($path) {
    if (!is_file($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $name = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        if ($name === '') {
            continue;
        }
        $current = getenv($name);
        if ($current !== false && $current !== '') {
            continue;
        }
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

ksers_load_env(__DIR__ . '/.env');

require_once __DIR__ . '/ksers_auth.php';
require_once __DIR__ . '/ksers_submission.php';

ksers_configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ksers_expire_member_session();

function ksers_lang() {
    if (!empty($_SESSION['lang']) && ($_SESSION['lang'] === 'ko' || $_SESSION['lang'] === 'en')) {
        return $_SESSION['lang'];
    }
    $cookie = isset($_COOKIE['ksers_lang']) ? $_COOKIE['ksers_lang'] : '';
    if ($cookie === 'ko' || $cookie === 'en') {
        $_SESSION['lang'] = $cookie;
        return $cookie;
    }
    $_SESSION['lang'] = 'ko';
    return 'ko';
}

function t($ko, $en) {
    return ksers_lang() === 'en' ? $en : $ko;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function code_name($row) {
    $ko = isset($row['name_ko']) ? $row['name_ko'] : '';
    $en = isset($row['name_eng']) ? $row['name_eng'] : '';
    if (ksers_lang() === 'en') {
        return $en !== '' ? $en : $ko;
    }
    return $en !== '' ? ($ko . ' (' . $en . ')') : $ko;
}

function ksers_aes_key() {
    $key = getenv('KSERS_AES_KEY');
    return ($key !== false && $key !== '') ? $key : '';
}

function ksers_env($name, $default = '') {
    $value = getenv($name);
    return ($value !== false && $value !== '') ? $value : $default;
}

function ksers_deploy_name() {
    return strtolower(trim(ksers_env('RAILWAY_ENVIRONMENT_NAME', '')));
}

function ksers_ensure_schema($pdo) {
    $hasMember = $pdo->query("SHOW TABLES LIKE 'member_info'")->fetch();
    if (!$hasMember) {
        $sql = file_get_contents(__DIR__ . '/schema.sql');
        $parts = preg_split('/;\s*\n/', $sql);
        foreach ($parts as $statement) {
            $statement = trim($statement);
            if ($statement === '' || strpos($statement, '--') === 0) {
                continue;
            }
            $pdo->exec($statement);
        }
        $code = $pdo->prepare("INSERT INTO code (majr, minr, name_ko, name_eng, state, reg_date) VALUES (?, ?, ?, ?, 1, NOW())");
        foreach ([
            [1, 1, '대한민국', 'Korea'],
            [1, 2, '미국', 'United States'],
        ] as $row) {
            $code->execute($row);
        }
    }

    $count = (int)$pdo->query("SELECT COUNT(*) FROM member_info")->fetchColumn();
    $email = ksers_env('KSERS_SEED_EMAIL');
    $pass = ksers_env('KSERS_SEED_PASSWORD');
    if ($count === 0 && $email !== '' && $pass !== '' && ksers_aes_key() !== '') {
        $stmt = $pdo->prepare(
            "INSERT INTO member_info
                (member_type, email, password, name_ko, first_name_eng, last_name_eng, affiliation_ko, affiliation_eng, mobile, country, year, reg_date, is_admin)
             VALUES (?, ?, HEX(AES_ENCRYPT(?, ?)), ?, ?, ?, ?, ?, ?, ?, 2027, NOW(), 1)"
        );
        $countryType = $pdo->query(
            "SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_info' AND COLUMN_NAME = 'country'"
        )->fetchColumn();
        $seedCountry = ($countryType === 'char' || $countryType === 'varchar') ? 'KR' : 1;
        $stmt->execute([
            2, $email, $pass, ksers_aes_key(),
            '김수', 'Modify', 'Kim', '홍길대학교', 'Honggil University', '010-1234-5678', $seedCountry,
        ]);
    }

    if ($email !== '') {
        $promote = $pdo->prepare("UPDATE member_info SET is_admin = 1 WHERE email = ? AND is_admin <> 1");
        $promote->execute([$email]);
    }
}

try {
    $dsn = 'mysql:host=' . ksers_env('MYSQLHOST', 'localhost')
        . ';port=' . ksers_env('MYSQLPORT', '3306')
        . ';dbname=' . ksers_env('MYSQLDATABASE', 'railway')
        . ';charset=utf8mb4';
    $pdo = new PDO($dsn, ksers_env('MYSQLUSER'), ksers_env('MYSQLPASSWORD'));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    ksers_ensure_schema($pdo);
} catch (PDOException $e) {
    error_log('database connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo '데이터베이스에 연결하지 못했습니다.';
    exit;
}

$param = null;
$param1 = null;
$eventYear = 2027;
include_once __DIR__ . '/ksers_app.php';
ksers_ensure_tables($pdo);
$eventYear = ksers_open_year($pdo);
if (!empty($_SESSION['seq']) && !ksers_member_is_admin($pdo)) {
    $eventYear = ksers_site_year($pdo);
}
