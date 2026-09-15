<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$applicationNo = trim((string) ($_GET['app'] ?? ''));

if ($applicationNo === '' || $applicationNo !== ($_SESSION['last_application_no'] ?? null)) {
    redirect('index.php');
}

$pageTitle = 'ส่งใบสมัครสำเร็จ';
require __DIR__ . '/includes/public_layout_top.php';
?>

<div class="card" style="text-align:center;">
    <div style="font-size:3rem;">✅</div>
    <h2 style="border:none;">ส่งใบสมัครเรียบร้อยแล้ว</h2>
    <p>เลขที่ใบสมัครของท่านคือ</p>
    <p style="font-size:1.8rem; font-weight:700; color:var(--primary); letter-spacing:1px;"><?= h($applicationNo) ?></p>
    <p class="hint">กรุณาจดบันทึกเลขที่ใบสมัครนี้ไว้ ใช้สำหรับตรวจสอบสถานะและพิมพ์ใบสมัครในภายหลัง</p>

    <div class="btn-row" style="justify-content:center;">
        <a class="btn" href="print.php?app=<?= urlencode($applicationNo) ?>">พิมพ์ใบสมัคร</a>
        <a class="btn btn-outline" href="check_status.php">ตรวจสอบสถานะ</a>
        <a class="btn btn-outline" href="index.php">กลับหน้าแรก</a>
    </div>
</div>

<div class="alert alert-info">
    ขั้นตอนถัดไป: พิมพ์ใบสมัคร ติดรูปถ่าย และนำใบสมัครพร้อมเอกสารฉบับจริงมายื่นที่ฝ่ายทะเบียนของวิทยาลัยตามวันเวลาที่ประกาศ
</div>

<?php require __DIR__ . '/includes/public_layout_bottom.php'; ?>
