<?php
declare(strict_types=1);
/** @var string $pageTitle ต้องกำหนดก่อน include ไฟล์นี้ */
$admin = current_admin();
$currentFile = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? 'ระบบหลังบ้าน') ?> | หลังบ้านระบบรับสมัคร</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admission.css">
</head>
<body>
<div class="admin-body">
    <aside class="admin-sidebar">
        <div class="brand">
            หลังบ้านระบบรับสมัคร
            <small>ปีการศึกษา <?= ADMISSION_ACADEMIC_YEAR ?></small>
        </div>
        <nav>
            <a href="index.php" class="<?= $currentFile === 'index.php' ? 'active' : '' ?>">แดชบอร์ด</a>
            <a href="applications.php" class="<?= $currentFile === 'applications.php' ? 'active' : '' ?>">รายชื่อผู้สมัคร</a>
            <a href="departments.php" class="<?= $currentFile === 'departments.php' ? 'active' : '' ?>">สาขาวิชา</a>
            <a href="export_csv.php">ส่งออกข้อมูล (CSV)</a>
            <a href="logout.php">ออกจากระบบ</a>
        </nav>
    </aside>
    <div class="admin-main">
        <div class="admin-topbar">
            <div><?= h($pageTitle ?? '') ?></div>
            <div>สวัสดี, <?= h($admin['full_name'] ?? '') ?></div>
        </div>
        <div class="admin-content">
