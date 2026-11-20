<?php

function ksers_request_is_local() {
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
    $host = preg_replace('/:\d+$/', '', $host);
    return $host === 'localhost' || $host === '127.0.0.1';
}

function ksers_congress_origin() {
    static $origin = null;
    if ($origin !== null) {
        return $origin;
    }
    $origin = 'https://ksers.kr';
    if (function_exists('ksers_deploy_name') && ksers_deploy_name() === 'staging') {
        $origin = 'https://dev.ksers.kr';
    }
    if (!ksers_request_is_local()) {
        return $origin;
    }
    $socket = @fsockopen('127.0.0.1', 3000, $errno, $errstr, 0.2);
    if (is_resource($socket)) {
        fclose($socket);
        $origin = 'http://localhost:3000';
    }
    return $origin;
}

function ksers_member_logged_in() {
    return !empty($_SESSION['seq']) && !empty($_SESSION['login_at']);
}

function ksers_congress_href($path) {
    if (!is_string($path) || $path === '' || $path[0] !== '/') {
        $year = 2027;
        if (!empty($_SESSION['member_year'])) {
            $year = (int)$_SESSION['member_year'];
        } elseif (!empty($_SESSION['site_year'])) {
            $year = (int)$_SESSION['site_year'];
        }
        $path = '/' . $year;
    }
    $absolute = ksers_congress_origin() . $path;
    if (ksers_member_logged_in() && function_exists('ksers_homepage_return') && ksers_homepage_return($absolute) !== '') {
        return '/service/login.php?next=' . rawurlencode($absolute);
    }
    return $absolute;
}

function ksers_dday_label($startDate) {
    $startDate = trim((string)$startDate);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        return '';
    }
    $zone = new DateTimeZone('Asia/Seoul');
    $today = new DateTime('now', $zone);
    $today->setTime(0, 0, 0);
    $start = new DateTime($startDate, $zone);
    $days = (int)$today->diff($start)->format('%r%a');
    if ($days > 0) {
        return '-' . $days;
    }
    return (string)$days;
}

function ksers_site_breadcrumb() {
    $page = basename(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
    $trails = [
        'index.php' => ['My Page'],
        'mypage.php' => ['My Page'],
        'registration.php' => ['My Page', 'Registration & Payment'],
        'abstract.php' => ['My Page', 'Abstract Submission'],
        'materials.php' => ['My Page', 'Video Submission'],
        'submission_upload.php' => ['My Page', 'Video Submission'],
        'login.php' => ['Login'],
        'join.php' => ['Sign Up'],
        'find_password.php' => ['Forgot password'],
        'reset_password.php' => ['Forgot password'],
    ];
    return isset($trails[$page]) ? $trails[$page] : ['My Page'];
}
