<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

$db = get_db();
$admin = current_admin();

$applicationNo = trim((string) ($_GET['no'] ?? ''));
if ($applicationNo === '') {
    redirect('applications.php');
}

$statuses = ['รอตรวจสอบ', 'ตรวจสอบแล้ว', 'ขอเอกสารเพิ่มเติม', 'อนุมัติ', 'ไม่อนุมัติ'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $newStatus = (string) ($_POST['status'] ?? '');
    $note = trim((string) ($_POST['admin_note'] ?? ''));

    if (!in_array($newStatus, $statuses, true)) {
        flash_set('error', 'สถานะไม่ถูกต้อง');
    } else {
        $current = $db->prepare('SELECT id, status FROM admission_applications WHERE application_no = ?');
        $current->bind_param('s', $applicationNo);
        $current->execute();
        $row = $current->get_result()->fetch_assoc();
        $current->close();

        if ($row === null) {
            redirect('applications.php');
        }

        $update = $db->prepare('UPDATE admission_applications SET status = ?, admin_note = ? WHERE id = ?');
        $noteOrNull = $note !== '' ? $note : null;
        $update->bind_param('ssi', $newStatus, $noteOrNull, $row['id']);
        $update->execute();
        $update->close();

        if ($row['status'] !== $newStatus) {
            $log = $db->prepare(
                'INSERT INTO admission_status_logs (application_id, old_status, new_status, note, changed_by)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $changedBy = $admin['full_name'] . ' (' . $admin['username'] . ')';
            $log->bind_param('issss', $row['id'], $row['status'], $newStatus, $noteOrNull, $changedBy);
            $log->execute();
            $log->close();
        }

        flash_set('success', 'บันทึกการเปลี่ยนแปลงเรียบร้อยแล้ว');
    }

    redirect('application_view.php?no=' . urlencode($applicationNo));
}

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
    flash_set('error', 'ไม่พบใบสมัครนี้');
    redirect('applications.php');
}

$logStmt = $db->prepare('SELECT * FROM admission_status_logs WHERE application_id = ? ORDER BY id DESC');
$logStmt->bind_param('i', $app['id']);
$logStmt->execute();
$logs = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$logStmt->close();

$documents = [
    'photo_path' => 'รูปถ่ายหน้าตรง',
    'id_card_path' => 'สำเนาบัตรประจำตัวประชาชน',
    'transcript_path' => 'ระเบียนแสดงผลการเรียน (ปพ.1)',
    'house_reg_path' => 'สำเนาทะเบียนบ้าน',
];

$pageTitle = 'รายละเอียดใบสมัคร ' . $app['application_no'];
require __DIR__ . '/includes/layout_top.php';

$successMsg = flash_get('success');
$errorMsg = flash_get('error');
?>

<?php if ($successMsg !== null): ?><div class="alert alert-success"><?= h($successMsg) ?></div><?php endif; ?>
<?php if ($errorMsg !== null): ?><div class="alert alert-error"><?= h($errorMsg) ?></div><?php endif; ?>

<div class="card">
    <h2>ใบสมัครเลขที่ <?= h($app['application_no']) ?> <span class="badge status-<?= h($app['status']) ?>"><?= h($app['status']) ?></span></h2>

    <div class="detail-grid">
        <div class="item"><div class="label">ชื่อ-นามสกุล</div><div class="value"><?= h($app['prefix'] . $app['first_name'] . ' ' . $app['last_name']) ?></div></div>
        <div class="item"><div class="label">เลขบัตรประจำตัวประชาชน</div><div class="value"><?= h($app['national_id']) ?></div></div>
        <div class="item"><div class="label">เพศ</div><div class="value"><?= h($app['gender']) ?></div></div>
        <div class="item"><div class="label">วันเกิด</div><div class="value"><?= h(date('d/m/Y', strtotime((string) $app['birth_date']))) ?></div></div>
        <div class="item"><div class="label">โทรศัพท์</div><div class="value"><?= h($app['phone']) ?></div></div>
        <div class="item"><div class="label">อีเมล</div><div class="value"><?= h($app['email'] ?? '-') ?></div></div>
        <div class="item"><div class="label">ระดับ/สาขา</div><div class="value"><?= h(format_level_label($app['level'], $app['program_type']) . ' สาขา' . $app['department_name']) ?></div></div>
        <div class="item"><div class="label">วันที่สมัคร</div><div class="value"><?= h(date('d/m/Y H:i', strtotime((string) $app['created_at']))) ?></div></div>
    </div>
</div>

<div class="card">
    <h2>ที่อยู่ปัจจุบัน</h2>
    <p><?= h($app['address']) ?> ตำบล/แขวง<?= h($app['subdistrict']) ?> อำเภอ/เขต<?= h($app['district']) ?> จังหวัด<?= h($app['province']) ?> <?= h($app['zipcode']) ?></p>

    <h2>ประวัติการศึกษา</h2>
    <div class="detail-grid">
        <div class="item"><div class="label">สถานศึกษาเดิม</div><div class="value"><?= h($app['previous_school']) ?></div></div>
        <div class="item"><div class="label">เกรดเฉลี่ยสะสม</div><div class="value"><?= $app['gpa'] !== null ? h((string) $app['gpa']) : '-' ?></div></div>
    </div>

    <h2>ข้อมูลผู้ปกครอง</h2>
    <div class="detail-grid">
        <div class="item"><div class="label">ชื่อ-นามสกุล</div><div class="value"><?= h($app['guardian_name']) ?></div></div>
        <div class="item"><div class="label">โทรศัพท์</div><div class="value"><?= h($app['guardian_phone']) ?></div></div>
        <div class="item"><div class="label">ความเกี่ยวข้อง</div><div class="value"><?= h($app['guardian_relation'] ?? '-') ?></div></div>
    </div>
</div>

<div class="card">
    <h2>เอกสารแนบ</h2>
    <div class="doc-list">
        <?php foreach ($documents as $field => $label): ?>
            <?php if (!empty($app[$field])): ?>
                <a href="download.php?no=<?= urlencode($app['application_no']) ?>&field=<?= urlencode($field) ?>" target="_blank" rel="noopener">📎 <?= h($label) ?></a>
            <?php else: ?>
                <span class="doc-list" style="color:#999;">— <?= h($label) ?> (ไม่ได้แนบ)</span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <h2>ปรับสถานะการพิจารณา</h2>
    <form method="post" action="application_view.php?no=<?= urlencode($app['application_no']) ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group">
                <label for="status">สถานะ</label>
                <select id="status" name="status">
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= h($s) ?>" <?= $app['status'] === $s ? 'selected' : '' ?>><?= h($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full">
                <label for="admin_note">หมายเหตุ (ผู้สมัครจะเห็นข้อความนี้ในหน้าตรวจสอบสถานะ)</label>
                <textarea id="admin_note" name="admin_note"><?= h($app['admin_note'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn">บันทึกการเปลี่ยนแปลง</button>
            <a class="btn btn-outline" href="applications.php">กลับไปรายชื่อผู้สมัคร</a>
        </div>
    </form>
</div>

<?php if ($logs !== []): ?>
<div class="card">
    <h2>ประวัติการเปลี่ยนสถานะ</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>วันที่</th><th>จาก</th><th>เป็น</th><th>โดย</th><th>หมายเหตุ</th></tr></thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= h(date('d/m/Y H:i', strtotime((string) $log['changed_at']))) ?></td>
                        <td><?= h($log['old_status'] ?? '-') ?></td>
                        <td><?= h($log['new_status']) ?></td>
                        <td><?= h($log['changed_by'] ?? '-') ?></td>
                        <td><?= h($log['note'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
