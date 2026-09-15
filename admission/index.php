<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'หน้าแรกระบบรับสมัคร';
require __DIR__ . '/includes/public_layout_top.php';
?>

<div class="page-hero" style="margin: -32px -16px 32px; border-radius: 10px;">
    <h1>เปิดรับสมัครนักเรียน-นักศึกษาใหม่ ปีการศึกษา <?= ADMISSION_ACADEMIC_YEAR ?></h1>
    <p><?= h(SCHOOL_NAME) ?></p>
</div>

<?php if (!ADMISSION_OPEN): ?>
    <div class="alert alert-info">
        ขณะนี้ปิดรับสมัครออนไลน์ชั่วคราว กรุณาติดต่อฝ่ายทะเบียนของวิทยาลัยโดยตรง หรือกลับมาตรวจสอบใหม่อีกครั้งภายหลัง
    </div>
<?php endif; ?>

<div class="choice-grid">
    <?php if (ADMISSION_OPEN): ?>
    <a class="choice-card" href="apply.php">
        <div class="icon">📝</div>
        <h3>สมัครเรียนออนไลน์</h3>
        <p>กรอกใบสมัครระดับ ปวช. หรือ ปวส. พร้อมแนบเอกสารประกอบการสมัคร</p>
    </a>
    <?php endif; ?>
    <a class="choice-card" href="check_status.php">
        <div class="icon">🔍</div>
        <h3>ตรวจสอบสถานะใบสมัคร</h3>
        <p>ใช้เลขที่ใบสมัครและเลขบัตรประจำตัวประชาชนเพื่อตรวจสอบผล</p>
    </a>
</div>

<div class="card">
    <h2>ขั้นตอนการสมัคร</h2>
    <ol>
        <li>กรอกใบสมัครออนไลน์ให้ครบถ้วนและแนบเอกสารประกอบ</li>
        <li>ระบบจะออกเลขที่ใบสมัคร ให้จดบันทึกไว้เพื่อใช้ตรวจสอบสถานะ</li>
        <li>พิมพ์ใบสมัครจากระบบ ติดรูปถ่าย และเตรียมเอกสารฉบับจริง</li>
        <li>นำใบสมัครและเอกสารมายื่นที่ฝ่ายทะเบียนของวิทยาลัยตามวันเวลาที่ประกาศ</li>
        <li>ติดตามผลการพิจารณาผ่านหน้าตรวจสอบสถานะ</li>
    </ol>
</div>

<?php require __DIR__ . '/includes/public_layout_bottom.php'; ?>
