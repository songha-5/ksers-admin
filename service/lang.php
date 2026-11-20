<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';

$lang = (isset($_GET['lang']) && $_GET['lang'] === 'en') ? 'en' : 'ko';
$_SESSION['lang'] = $lang;
setcookie('ksers_lang', $lang, [
    'expires' => time() + 60 * 60 * 24 * 365,
    'path' => '/',
    'samesite' => 'Lax',
]);

$ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$path = parse_url($ref, PHP_URL_PATH);
$query = parse_url($ref, PHP_URL_QUERY);
$host = strtolower((string)parse_url($ref, PHP_URL_HOST));
$requestHost = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
$requestHost = preg_replace('/:\d+$/', '', $requestHost);
$sameHost = ($host === '' || $host === $requestHost);
if (!$sameHost || !$path || !preg_match('#^/(service|admin)(/|$)#', $path)) {
    $path = '/service/login.php';
    $query = '';
}

header('Location: ' . $path . ($query ? '?' . $query : ''));
exit;
