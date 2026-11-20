<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';

ksers_require_admin($pdo);
?>
<!DOCTYPE html>
<html lang="<?= h(ksers_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(isset($page_title) ? $page_title : t('2027 국제학술대회 관리자', '2027 Conference Admin')); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/service/ksers.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #263238;
        }
        .sidebar .nav-link {
            color: #c2c7d0;
            margin-bottom: 5px;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            color: #fff;
            background-color: #09428f;
            border-radius: 4px;
        }
        main .table {
            width: max-content;
            min-width: 100%;
        }
        main .table th,
        main .table td {
            min-width: 8.5rem;
            white-space: nowrap;
        }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid">
    <div class="row">