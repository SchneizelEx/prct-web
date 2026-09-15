<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

$db = get_db();
$academicYear = ADMISSION_ACADEMIC_YEAR;

$level = $_GET['level'] ?? '';
$programType = $_GET['program_type'] ?? '';
$status = $_GET['status'] ?? '';
$departmentId = (int) ($_GET['department_id'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$statuses = ['รอตรวจสอบ', 'ตรวจสอบแล้ว', 'ขอเอกสารเพิ่มเติม', 'อนุมัติ', 'ไม่อนุมัติ'];

$where = ['a.academic_year = ?'];
$params = [$academicYear];
$types = 'i';

if (in_array($level, ['ปวช.', 'ปวส.'], true)) {
    $where[] = 'a.level = ?';
    $params[] = $level;
    $types .= 's';
}
if (in_array($programType, ['ภาคปกติ', 'ภาคสมทบ'], true)) {
    $where[] = 'a.program_type = ?';
    $params[] = $programType;
    $types .= 's';
}
if (in_array($status, $statuses, true)) {
    $where[] = 'a.status = ?';
    $params[] = $status;
    $types .= 's';
}
if ($departmentId > 0) {
    $where[] = 'a.department_id = ?';
    $params[] = $departmentId;
    $types .= 'i';
}
if ($q !== '') {
    $where[] = '(a.application_no LIKE ? OR a.national_id LIKE ? OR CONCAT(a.first_name, " ", a.last_name) LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$whereSql = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) AS total FROM admission_applications a WHERE {$whereSql}");
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listSql = "SELECT a.application_no, a.prefix, a.first_name, a.last_name, a.level, a.program_type, a.status, a.created_at,
                   d.name AS department_name
            FROM admission_applications a
            JOIN admission_departments d ON d.id = a.department_id
            WHERE {$whereSql}
            ORDER BY a.id DESC
            LIMIT ? OFFSET ?";
$listStmt = $db->prepare($listSql);
$listParams = $params;
$listParams[] = $perPage;
$listParams[] = $offset;
$listStmt->bind_param($types . 'ii', ...$listParams);
$listStmt->execute();
$rows = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$listStmt->close();

$departmentGroups = get_department_groups($db);

function qs(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    return htmlspecialchars(http_build_query($params), ENT_QUOTES, 'UTF-8');
}

$pageTitle = 'รายชื่อผู้สมัคร';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
    <h2>ค้นหา/กรองรายชื่อผู้สมัคร</h2>
    <form method="get" action="applications.php" class="filter-bar">
        <input type="text" name="q" placeholder="ค้นหาเลขที่ใบสมัคร / เลขบัตร ปชช. / ชื่อ-นามสกุล" value="<?= h($q) ?>">
        <select name="level">
            <option value="">ทุกระดับ</option>
            <option value="ปวช." <?= $level === 'ปวช.' ? 'selected' : '' ?>>ปวช.</option>
            <option value="ปวส." <?= $level === 'ปวส.' ? 'selected' : '' ?>>ปวส.</option>
        </select>
        <select name="program_type">
            <option value="">ทุกภาค (ปวส.)</option>
            <option value="ภาคปกติ" <?= $programType === 'ภาคปกติ' ? 'selected' : '' ?>>ภาคปกติ</option>
            <option value="ภาคสมทบ" <?= $programType === 'ภาคสมทบ' ? 'selected' : '' ?>>ภาคสมทบ</option>
        </select>
        <select name="department_id">
            <option value="">ทุกสาขา</option>
            <?php foreach ($departmentGroups as $group): ?>
                <?php foreach ($group['departments'] as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= $departmentId === (int) $d['id'] ? 'selected' : '' ?>>
                        <?= h($group['label'] . ' ' . $d['name']) ?>
                    </option>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">ทุกสถานะ</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= h($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= h($s) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm">ค้นหา</button>
        <a class="btn btn-sm btn-outline" href="applications.php">ล้างตัวกรอง</a>
    </form>
</div>

<div class="card">
    <h2>รายชื่อผู้สมัคร (พบ <?= $totalRows ?> รายการ)</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>เลขที่ใบสมัคร</th><th>ชื่อ-นามสกุล</th><th>ระดับ/สาขา</th><th>สถานะ</th><th>วันที่สมัคร</th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= h($row['application_no']) ?></td>
                        <td><?= h($row['prefix'] . $row['first_name'] . ' ' . $row['last_name']) ?></td>
                        <td><?= h(format_level_label($row['level'], $row['program_type']) . ' ' . $row['department_name']) ?></td>
                        <td><span class="badge status-<?= h($row['status']) ?>"><?= h($row['status']) ?></span></td>
                        <td><?= h(date('d/m/Y H:i', strtotime((string) $row['created_at']))) ?></td>
                        <td><a class="btn btn-sm btn-outline" href="application_view.php?no=<?= urlencode($row['application_no']) ?>">ดูรายละเอียด</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="6">ไม่พบข้อมูลตามเงื่อนไขที่ค้นหา</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p === $page): ?>
                    <span class="current"><?= $p ?></span>
                <?php else: ?>
                    <a href="applications.php?<?= qs(['page' => $p]) ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
