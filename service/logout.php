<?php
require_once dirname(__DIR__) . '/ksers_auth.php';
ksers_configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$next = ksers_homepage_return(isset($_GET['next']) ? $_GET['next'] : '');
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => isset($params['samesite']) ? $params['samesite'] : 'Lax',
    ]);
}
session_destroy();

if ($next !== '') {
    header('Location: ' . ksers_with_query($next, 'logged_out', '1'));
    exit;
}

header('Location: /service/login.php');
exit;
