<?php

function ksers_session_seconds() {
    return 1800;
}

function ksers_request_is_https() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    $forwarded = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) : '';
    return $forwarded === 'https';
}

function ksers_configure_session() {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $seconds = ksers_session_seconds();
    ini_set('session.gc_maxlifetime', (string)$seconds);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_set_cookie_params([
        'lifetime' => $seconds,
        'path' => '/',
        'secure' => ksers_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function ksers_begin_member_session($seq, $year = 0) {
    $_SESSION['seq'] = (int)$seq;
    $_SESSION['login_at'] = time();
    $year = (int)$year;
    if ($year >= 2027 && $year <= 2035) {
        $_SESSION['member_year'] = $year;
    } else {
        unset($_SESSION['member_year']);
    }
}

function ksers_expire_member_session() {
    if (empty($_SESSION['seq'])) {
        return;
    }
    $started = isset($_SESSION['login_at']) ? (int)$_SESSION['login_at'] : 0;
    if ($started <= 0 || (time() - $started) >= ksers_session_seconds()) {
        unset($_SESSION['seq'], $_SESSION['login_at'], $_SESSION['member_year']);
    }
}

function ksers_homepage_return($url) {
    $url = trim((string)$url);
    if ($url === '' || strlen($url) > 500) {
        return '';
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }
    if (!empty($parts['user']) || !empty($parts['pass'])) {
        return '';
    }
    $scheme = strtolower($parts['scheme']);
    $host = strtolower($parts['host']);
    $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);
    $path = isset($parts['path']) ? $parts['path'] : '/';
    if (!preg_match('#^/(20[0-9]{2})(?:/|$)#', $path, $yearMatch)) {
        return '';
    }
    $pathYear = (int)$yearMatch[1];
    if ($pathYear < 2027 || $pathYear > 2035) {
        return '';
    }
    $origin = $scheme . '://' . $host;
    if (!(($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80))) {
        $origin .= ':' . $port;
    }
    if (!ksers_allowed_homepage_origin($origin)) {
        return '';
    }
    $rebuilt = $scheme . '://' . $host;
    if (!(($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80))) {
        $rebuilt .= ':' . $port;
    }
    $rebuilt .= $path;
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
        unset($query['code'], $query['logged_out'], $query['probed']);
        $qs = http_build_query($query);
        if ($qs !== '') {
            $rebuilt .= '?' . $qs;
        }
    }
    return $rebuilt;
}

function ksers_with_query($url, $key, $value) {
    $sep = (strpos($url, '?') === false) ? '?' : '&';
    return $url . $sep . rawurlencode($key) . '=' . rawurlencode((string)$value);
}

function ksers_allowed_homepage_origin($origin) {
    $parts = parse_url((string)$origin);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }
    if (!empty($parts['user']) || !empty($parts['pass'])) {
        return false;
    }
    $scheme = strtolower($parts['scheme']);
    $host = strtolower($parts['host']);
    $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);
    if ($scheme === 'http' && ($host === 'localhost' || $host === '127.0.0.1') && $port === 3000) {
        return true;
    }
    if ($scheme !== 'https' || $port !== 443) {
        return false;
    }
    $suffix = '.ksers.kr';
    return $host === 'ksers.kr' || (strlen($host) > strlen($suffix) && substr($host, -strlen($suffix)) === $suffix);
}

function ksers_auth_cors() {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    if (ksers_allowed_homepage_origin($origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Cache-Control: no-store');
}

function ksers_issue_auth_code($pdo, $memberSeq, $loginAt) {
    $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $hash = hash('sha256', $raw);
    $sessionExpires = (int)$loginAt + ksers_session_seconds();
    $pdo->prepare("DELETE FROM auth_ticket WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)")->execute();
    $stmt = $pdo->prepare(
        "INSERT INTO auth_ticket (token_hash, member_seq, expires_at, session_expires_unix, reg_date)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), ?, NOW())"
    );
    $stmt->execute([$hash, (int)$memberSeq, $sessionExpires]);
    return $raw;
}

function ksers_consume_auth_code($pdo, $raw) {
    $raw = trim((string)$raw);
    if ($raw === '' || strlen($raw) > 200) {
        return null;
    }
    $hash = hash('sha256', $raw);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT seq, member_seq, session_expires_unix FROM auth_ticket
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
             FOR UPDATE"
        );
        $stmt->execute([$hash]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) {
            $pdo->rollBack();
            return null;
        }
        $upd = $pdo->prepare("UPDATE auth_ticket SET used_at = NOW() WHERE seq = ? AND used_at IS NULL");
        $upd->execute([(int)$ticket['seq']]);
        if ($upd->rowCount() !== 1) {
            $pdo->rollBack();
            return null;
        }
        $member = $pdo->prepare(
            "SELECT is_admin, name_ko, first_name_eng, last_name_eng FROM member_info WHERE seq = ?"
        );
        $member->execute([(int)$ticket['member_seq']]);
        $row = $member->fetch(PDO::FETCH_ASSOC);
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('auth ticket consume failed');
        return null;
    }
    if (!$row) {
        return null;
    }
    $name = trim((string)$row['name_ko']);
    if ($name === '') {
        $name = trim(trim((string)$row['first_name_eng']) . ' ' . trim((string)$row['last_name_eng']));
    }
    $expiresAt = isset($ticket['session_expires_unix']) ? (int)$ticket['session_expires_unix'] : 0;
    if ($expiresAt <= 0) {
        $expiresAt = time() + ksers_session_seconds();
    }
    return [
        'loggedIn' => true,
        'role' => ((int)$row['is_admin'] === 1) ? 'admin' : 'member',
        'name' => $name,
        'seq' => (int)$ticket['member_seq'],
        'expiresAt' => $expiresAt,
    ];
}

function ksers_auth_denied() {
    return [
        'loggedIn' => false,
        'role' => null,
        'name' => null,
        'seq' => null,
        'expiresAt' => null,
    ];
}
