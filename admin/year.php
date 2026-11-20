<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$year = isset($_POST['year']) ? (int)$_POST['year'] : 0;
if ($year >= 2027 && $year <= 2035) {
    $_SESSION['admin_year'] = $year;
}
$back = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/admin/index.php';
$host = parse_url($back, PHP_URL_HOST);
$path = parse_url($back, PHP_URL_PATH);
$same = ($host === null || $host === '' || $host === $_SERVER['HTTP_HOST']);
if (!$same || !$path || strpos($path, '/admin/') !== 0) {
    $back = '/admin/index.php';
}
header('Location: ' . $back);
exit;
