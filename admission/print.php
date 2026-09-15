<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$applicationNo = trim((string) ($_GET['app'] ?? ''));

if ($applicationNo === '' || $applicationNo !== ($_SESSION['authorized_print_app'] ?? null)) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์เข้าถึงใบสมัครนี้ กรุณาเข้าผ่านหน้าตรวจสอบสถานะใบสมัครก่อน');
}

$db = get_db();
$stmt = $db->prepare(
    'SELECT a.*, d.name AS department_name
     FROM admission_applications a
     JOIN admission_departments d ON d.id = a.department_id
     WHERE a.application_no = ?'
);
$stmt->bind_param('s', $applicationNo);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($app === null) {
    redirect('index.php');
}

$fullName = $app['prefix'] . $app['first_name'] . ' ' . $app['last_name'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบสมัคร <?= h($app['application_no']) ?> | <?= h(SCHOOL_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admission.css">
</head>
<body>

<div class="no-print">
    <button class="btn" onclick="window.print()">พิมพ์ใบสมัคร</button>
    <a class="btn btn-outline" href="check_status.php">กลับหน้าตรวจสอบสถานะ</a>
</div>

<div class="print-form">
    <div class="print-photo-box">ติดรูปถ่าย<br>ขนาด 1 นิ้ว</div>
    <div class="head">
        <h1><?= h(SCHOOL_NAME) ?></h1>
        <h2>ใบสมัครเข้าศึกษาต่อ ระดับ<?= h($app['level']) ?> ปีการศึกษา <?= h((string) $app['academic_year']) ?></h2>
        <p>เลขที่ใบสมัคร: <strong><?= h($app['application_no']) ?></strong></p>
    </div>

    <div class="section-title">1. ข้อมูลผู้สมัคร</div>
    <table class="info">
        <tr>
            <td width="50%">ชื่อ-นามสกุล: <strong><?= h($fullName) ?></strong></td>
            <td>เพศ: <?= h($app['gender']) ?></td>
        </tr>
        <tr>
            <td>เลขบัตรประจำตัวประชาชน: <?= h($app['national_id']) ?></td>
            <td>วันเกิด: <?= h(date('d/m/Y', strtotime((string) $app['birth_date']))) ?></td>
        </tr>
        <tr>
            <td>โทรศัพท์: <?= h($app['phone']) ?></td>
            <td>อีเมล: <?= h($app['email'] ?? '-') ?></td>
        </tr>
        <tr>
            <td colspan="2">สาขาที่สมัคร: ระดับ<?= h($app['level']) ?> สาขา<?= h($app['department_name']) ?></td>
        </tr>
    </table>

    <div class="section-title">2. ที่อยู่ปัจจุบัน</div>
    <table class="info">
        <tr>
            <td colspan="2"><?= h($app['address']) ?> ตำบล/แขวง<?= h($app['subdistrict']) ?> อำเภอ/เขต<?= h($app['district']) ?> จังหวัด<?= h($app['province']) ?> <?= h($app['zipcode']) ?></td>
        </tr>
    </table>

    <div class="section-title">3. ประวัติการศึกษา</div>
    <table class="info">
        <tr>
            <td width="50%">สถานศึกษาเดิม: <?= h($app['previous_school']) ?></td>
            <td>เกรดเฉลี่ยสะสม: <?= $app['gpa'] !== null ? h((string) $app['gpa']) : '-' ?></td>
        </tr>
    </table>

    <div class="section-title">4. ข้อมูลผู้ปกครอง</div>
    <table class="info">
        <tr>
            <td width="50%">ชื่อ-นามสกุล: <?= h($app['guardian_name']) ?></td>
            <td>ความเกี่ยวข้อง: <?= h($app['guardian_relation'] ?? '-') ?></td>
        </tr>
        <tr>
            <td>โทรศัพท์: <?= h($app['guardian_phone']) ?></td>
            <td></td>
        </tr>
    </table>

    <div class="section-title">5. เอกสารที่ต้องนำมายื่นฉบับจริง</div>
    <table class="info">
        <tr><td>&#9744; รูปถ่ายหน้าตรง 1 นิ้ว จำนวน 1 รูป</td></tr>
        <tr><td>&#9744; สำเนาบัตรประจำตัวประชาชน</td></tr>
        <tr><td>&#9744; ระเบียนแสดงผลการเรียน (ปพ.1) ฉบับจริง</td></tr>
        <tr><td>&#9744; สำเนาทะเบียนบ้าน (ถ้ามี)</td></tr>
    </table>

    <div class="signature">
        <p>ลงชื่อ ....................................................... ผู้สมัคร</p>
        <p>( <?= h($fullName) ?> )</p>
        <p>วันที่สมัคร: <?= h(date('d/m/Y', strtotime((string) $app['created_at']))) ?></p>
    </div>
</div>

</body>
</html>
