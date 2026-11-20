<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';

$no = isset($_SESSION['seq']) ? $_SESSION['seq'] : null;

if(!isset($no)){
        echo "<meta http-equiv='refresh' content='0;url=/service/login.php'>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= h(ksers_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(isset($page_title) ? $page_title : ('KSERS ' . (int)$eventYear)); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/service/ksers.css" rel="stylesheet">
    <style>
        body { display: flex; flex-direction: column; min-height: 100vh; }
        main.main-content { flex: 1; }
    </style>
</head>
<body>