<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
// $prevPage = $_SERVER['HTTP_REFERER'];

$id   = $_POST['email'];
$pass = $_POST['password'];


$param = null;

$loginYear = ksers_site_year($pdo);
$stmt = $pdo->prepare("SELECT * FROM member_info WHERE email = ? AND year = ? AND password = HEX(AES_ENCRYPT(?, ?))");
$stmt->execute([$id, $loginYear, $pass, ksers_aes_key()]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);


if($data) {
    session_regenerate_id(true);
    ksers_begin_member_session((int)$data['seq'], (int)$data['year']);

    header('Location: /service/mypage.php');
    exit;
    
} else { ?>
    <script>
        alert(<?= json_encode(t('로그인 정보를 확인하세요.', 'Check your email and password.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
        history.back();
    </script>
<?php }

?>