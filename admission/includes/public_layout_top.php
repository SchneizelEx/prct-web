<?php
declare(strict_types=1);
/** @var string $pageTitle ต้องกำหนดตัวแปรนี้ก่อน include ไฟล์นี้ */
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? 'ระบบรับสมัครนักเรียนใหม่') ?> | <?= h(SCHOOL_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admission.css">
</head>
<body>
<header class="site-header">
    <div class="container">
        <a class="brand" href="index.php">
            ระบบรับสมัครนักเรียนใหม่ ปีการศึกษา <?= ADMISSION_ACADEMIC_YEAR ?>
            <small><?= h(SCHOOL_NAME) ?></small>
        </a>
        <nav>
            <a href="../index.html">หน้าแรกวิทยาลัย</a>
            <a href="index.php">ระบบรับสมัคร</a>
            <a href="check_status.php">ตรวจสอบสถานะใบสมัคร</a>
        </nav>
    </div>
</header>
<main class="main-content">
    <div class="container">
