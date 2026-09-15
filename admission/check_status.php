<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$db = get_db();
$applicationNo = '';
$nationalId = '';
$error = null;
$application = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $applicationNo = trim((string) ($_POST['application_no'] ?? ''));
    $nationalId = trim((string) ($_POST['national_id'] ?? ''));

    if ($applicationNo === '' || $nationalId === '') {
        $error = 'กรุณากรอกเลขที่ใบสมัครและเลขบัตรประจำตัวประชาชนให้ครบถ้วน';
    } else {
        $stmt = $db->prepare(
            'SELECT a.application_no, a.level, a.program_type, d.name AS department_name, a.prefix, a.first_name, a.last_name,
                    a.status, a.admin_note, a.created_at, a.updated_at
             FROM admission_applications a
             JOIN admission_departments d ON d.id = a.department_id
             WHERE a.application_no = ? AND a.national_id = ?'
        );
        $stmt->bind_param('ss', $applicationNo, $nationalId);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($application === null) {
            $error = 'ไม่พบใบสมัครที่ตรงกับข้อมูลที่ระบุ กรุณาตรวจสอบเลขที่ใบสมัครและเลขบัตรประจำตัวประชาชนอีกครั้ง';
        } else {
            $_SESSION['authorized_print_app'] = $application['application_no'];
        }
    }
}

$pageTitle = 'ตรวจสอบสถานะใบสมัคร';
require __DIR__ . '/includes/public_layout_top.php';
?>

<div class="card">
    <h2>ตรวจสอบสถานะใบสมัคร</h2>

    <?php if ($error !== null): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" action="check_status.php">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group">
                <label for="application_no">เลขที่ใบสมัคร <span class="required">*</span></label>
                <input type="text" id="application_no" name="application_no" value="<?= h($applicationNo) ?>" placeholder="เช่น PRCT70-000001" required>
            </div>
            <div class="form-group">
                <label for="national_id">เลขบัตรประจำตัวประชาชน 13 หลัก <span class="required">*</span></label>
                <input type="text" id="national_id" name="national_id" inputmode="numeric" maxlength="13" value="<?= h($nationalId) ?>" required>
            </div>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn">ตรวจสอบสถานะ</button>
        </div>
    </form>
</div>

<?php if ($application !== null): ?>
    <div class="card">
        <h2>ผลการตรวจสอบ</h2>
        <div class="detail-grid">
            <div class="item">
                <div class="label">เลขที่ใบสมัคร</div>
                <div class="value"><?= h($application['application_no']) ?></div>
            </div>
            <div class="item">
                <div class="label">ชื่อ-นามสกุล</div>
                <div class="value"><?= h($application['prefix'] . $application['first_name'] . ' ' . $application['last_name']) ?></div>
            </div>
            <div class="item">
                <div class="label">ระดับ/สาขา</div>
                <div class="value"><?= h(format_level_label($application['level'], $application['program_type']) . ' สาขา' . $application['department_name']) ?></div>
            </div>
            <div class="item">
                <div class="label">วันที่สมัคร</div>
                <div class="value"><?= h(date('d/m/Y H:i', strtotime((string) $application['created_at']))) ?></div>
            </div>
            <div class="item">
                <div class="label">สถานะปัจจุบัน</div>
                <div class="value"><span class="badge status-<?= h($application['status']) ?>"><?= h($application['status']) ?></span></div>
            </div>
            <div class="item">
                <div class="label">ปรับปรุงล่าสุด</div>
                <div class="value"><?= h(date('d/m/Y H:i', strtotime((string) $application['updated_at']))) ?></div>
            </div>
        </div>
        <?php if (!empty($application['admin_note'])): ?>
            <div class="alert alert-info" style="margin-top:16px;">
                <strong>หมายเหตุจากเจ้าหน้าที่:</strong> <?= nl2br(h($application['admin_note'])) ?>
            </div>
        <?php endif; ?>
        <div class="btn-row">
            <a class="btn btn-outline" href="print.php?app=<?= urlencode($application['application_no']) ?>">พิมพ์ใบสมัคร</a>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/public_layout_bottom.php'; ?>
