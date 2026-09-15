<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

$db = get_db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $level = (string) ($_POST['level'] ?? '');
        $programType = (string) ($_POST['program_type'] ?? '');
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $name = trim((string) ($_POST['name'] ?? ''));
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        if (!in_array($level, ['ปวช.', 'ปวส.'], true)) {
            $errors[] = 'กรุณาเลือกระดับ';
        }
        if ($level === 'ปวช.') {
            $programType = '';
        } elseif ($level === 'ปวส.' && !in_array($programType, ['ภาคปกติ', 'ภาคสมทบ'], true)) {
            $errors[] = 'กรุณาเลือกภาคของระดับ ปวส. (ภาคปกติ/ภาคสมทบ)';
        }
        if ($code === '' || !preg_match('/^[A-Z0-9_]{1,20}$/', $code)) {
            $errors[] = 'รหัสสาขาต้องเป็นตัวอักษร A-Z, 0-9 หรือ _ ความยาวไม่เกิน 20 ตัวอักษร';
        }
        if ($name === '') {
            $errors[] = 'กรุณากรอกชื่อสาขา';
        }

        if ($errors === []) {
            try {
                $stmt = $db->prepare('INSERT INTO admission_departments (level, program_type, code, name, sort_order) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('ssssi', $level, $programType, $code, $name, $sortOrder);
                $stmt->execute();
                $stmt->close();
                flash_set('success', 'เพิ่มสาขาวิชาเรียบร้อยแล้ว');
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    $errors[] = 'มีรหัสสาขานี้ในระดับ/ภาคเดียวกันอยู่แล้ว';
                } else {
                    throw $e;
                }
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('UPDATE admission_departments SET is_active = NOT is_active WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash_set('success', 'อัปเดตสถานะสาขาเรียบร้อยแล้ว');
    }

    if ($errors === []) {
        redirect('departments.php');
    }
}

$rows = $db->query('SELECT * FROM admission_departments ORDER BY level, program_type, sort_order, name')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'จัดการสาขาวิชา';
require __DIR__ . '/includes/layout_top.php';

$successMsg = flash_get('success');
?>

<?php if ($successMsg !== null): ?><div class="alert alert-success"><?= h($successMsg) ?></div><?php endif; ?>
<?php if ($errors !== []): ?>
    <div class="alert alert-error">
        <ul class="error-list"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card">
    <h2>เพิ่มสาขาวิชาใหม่</h2>
    <form method="post" action="departments.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label for="level">ระดับ</label>
                <select id="level" name="level" required>
                    <option value="ปวช.">ปวช.</option>
                    <option value="ปวส.">ปวส.</option>
                </select>
            </div>
            <div class="form-group">
                <label for="program_type">ภาค (เฉพาะ ปวส.)</label>
                <select id="program_type" name="program_type">
                    <option value="">-- ไม่มี (ปวช.) --</option>
                    <option value="ภาคปกติ">ภาคปกติ</option>
                    <option value="ภาคสมทบ">ภาคสมทบ</option>
                </select>
            </div>
            <div class="form-group">
                <label for="code">รหัสสาขา (A-Z, 0-9)</label>
                <input type="text" id="code" name="code" maxlength="20" required>
            </div>
            <div class="form-group">
                <label for="name">ชื่อสาขา</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="sort_order">ลำดับการแสดงผล</label>
                <input type="number" id="sort_order" name="sort_order" value="0">
            </div>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn">เพิ่มสาขาวิชา</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>สาขาวิชาทั้งหมด</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>ระดับ/ภาค</th><th>รหัส</th><th>ชื่อสาขา</th><th>ลำดับ</th><th>สถานะ</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= h(format_level_label($row['level'], $row['program_type'])) ?></td>
                        <td><?= h($row['code']) ?></td>
                        <td><?= h($row['name']) ?></td>
                        <td><?= (int) $row['sort_order'] ?></td>
                        <td><?= $row['is_active'] ? '<span class="badge status-อนุมัติ">เปิดใช้งาน</span>' : '<span class="badge status-ไม่อนุมัติ">ปิดใช้งาน</span>' ?></td>
                        <td>
                            <form method="post" action="departments.php" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline"><?= $row['is_active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
