<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

$db = get_db();
$academicYear = ADMISSION_ACADEMIC_YEAR;

$totalStmt = $db->prepare('SELECT COUNT(*) AS total FROM admission_applications WHERE academic_year = ?');
$totalStmt->bind_param('i', $academicYear);
$totalStmt->execute();
$total = (int) ($totalStmt->get_result()->fetch_assoc()['total'] ?? 0);
$totalStmt->close();

$statusCounts = [];
$stmt = $db->prepare('SELECT status, COUNT(*) AS total FROM admission_applications WHERE academic_year = ? GROUP BY status');
$stmt->bind_param('i', $academicYear);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $statusCounts[$row['status']] = (int) $row['total'];
}
$stmt->close();

$levelCounts = [];
$stmt = $db->prepare('SELECT level, COUNT(*) AS total FROM admission_applications WHERE academic_year = ? GROUP BY level');
$stmt->bind_param('i', $academicYear);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $levelCounts[$row['level']] = (int) $row['total'];
}
$stmt->close();

$deptStmt = $db->prepare(
    'SELECT d.level, d.name, COUNT(a.id) AS total
     FROM admission_departments d
     LEFT JOIN admission_applications a ON a.department_id = d.id AND a.academic_year = ?
     WHERE d.is_active = 1
     GROUP BY d.id, d.level, d.name
     ORDER BY d.level, d.sort_order'
);
$deptStmt->bind_param('i', $academicYear);
$deptStmt->execute();
$deptRows = $deptStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$deptStmt->close();

$recentStmt = $db->prepare(
    'SELECT application_no, prefix, first_name, last_name, level, status, created_at
     FROM admission_applications
     WHERE academic_year = ?
     ORDER BY id DESC LIMIT 10'
);
$recentStmt->bind_param('i', $academicYear);
$recentStmt->execute();
$recent = $recentStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recentStmt->close();

$statuses = ['รอตรวจสอบ', 'ตรวจสอบแล้ว', 'ขอเอกสารเพิ่มเติม', 'อนุมัติ', 'ไม่อนุมัติ'];

$pageTitle = 'แดชบอร์ด';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="summary-cards">
    <div class="summary-card">
        <div class="num"><?= $total ?></div>
        <div class="label">ผู้สมัครทั้งหมด</div>
    </div>
    <?php foreach ($statuses as $status): ?>
        <div class="summary-card">
            <div class="num"><?= $statusCounts[$status] ?? 0 ?></div>
            <div class="label"><?= h($status) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2>จำนวนผู้สมัครแยกตามระดับ</h2>
    <div class="summary-cards">
        <div class="summary-card">
            <div class="num"><?= $levelCounts['ปวช.'] ?? 0 ?></div>
            <div class="label">ปวช.</div>
        </div>
        <div class="summary-card">
            <div class="num"><?= $levelCounts['ปวส.'] ?? 0 ?></div>
            <div class="label">ปวส.</div>
        </div>
    </div>
</div>

<div class="card">
    <h2>จำนวนผู้สมัครแยกตามสาขา</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>ระดับ</th><th>สาขา</th><th>จำนวนผู้สมัคร</th></tr>
            </thead>
            <tbody>
                <?php foreach ($deptRows as $row): ?>
                    <tr>
                        <td><?= h($row['level']) ?></td>
                        <td><?= h($row['name']) ?></td>
                        <td><?= (int) $row['total'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>ผู้สมัครล่าสุด</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>เลขที่ใบสมัคร</th><th>ชื่อ-นามสกุล</th><th>ระดับ</th><th>สถานะ</th><th>วันที่สมัคร</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $row): ?>
                    <tr>
                        <td><?= h($row['application_no']) ?></td>
                        <td><?= h($row['prefix'] . $row['first_name'] . ' ' . $row['last_name']) ?></td>
                        <td><?= h($row['level']) ?></td>
                        <td><span class="badge status-<?= h($row['status']) ?>"><?= h($row['status']) ?></span></td>
                        <td><?= h(date('d/m/Y H:i', strtotime((string) $row['created_at']))) ?></td>
                        <td><a class="btn btn-sm btn-outline" href="application_view.php?no=<?= urlencode($row['application_no']) ?>">ดูรายละเอียด</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recent === []): ?>
                    <tr><td colspan="6">ยังไม่มีผู้สมัคร</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
