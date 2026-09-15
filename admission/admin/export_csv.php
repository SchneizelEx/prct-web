<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

$db = get_db();
$academicYear = ADMISSION_ACADEMIC_YEAR;

$level = $_GET['level'] ?? '';
$status = $_GET['status'] ?? '';
$statuses = ['รอตรวจสอบ', 'ตรวจสอบแล้ว', 'ขอเอกสารเพิ่มเติม', 'อนุมัติ', 'ไม่อนุมัติ'];

$where = ['a.academic_year = ?'];
$params = [$academicYear];
$types = 'i';

if (in_array($level, ['ปวช.', 'ปวส.'], true)) {
    $where[] = 'a.level = ?';
    $params[] = $level;
    $types .= 's';
}
if (in_array($status, $statuses, true)) {
    $where[] = 'a.status = ?';
    $params[] = $status;
    $types .= 's';
}

$whereSql = implode(' AND ', $where);
$stmt = $db->prepare(
    "SELECT a.application_no, a.level, d.name AS department_name, a.prefix, a.first_name, a.last_name,
            a.national_id, a.birth_date, a.gender, a.phone, a.email, a.address, a.subdistrict, a.district,
            a.province, a.zipcode, a.previous_school, a.gpa, a.guardian_name, a.guardian_phone,
            a.guardian_relation, a.status, a.created_at
     FROM admission_applications a
     JOIN admission_departments d ON d.id = a.department_id
     WHERE {$whereSql}
     ORDER BY a.id ASC"
);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="admission_' . $academicYear . '_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM เพื่อให้ Excel แสดงภาษาไทยถูกต้อง

fputcsv($out, [
    'เลขที่ใบสมัคร', 'ระดับ', 'สาขา', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'เลขบัตรประชาชน',
    'วันเกิด', 'เพศ', 'โทรศัพท์', 'อีเมล', 'ที่อยู่', 'ตำบล', 'อำเภอ', 'จังหวัด', 'รหัสไปรษณีย์',
    'สถานศึกษาเดิม', 'เกรดเฉลี่ย', 'ชื่อผู้ปกครอง', 'โทรศัพท์ผู้ปกครอง', 'ความเกี่ยวข้อง', 'สถานะ', 'วันที่สมัคร',
]);

while ($row = $result->fetch_assoc()) {
    fputcsv($out, [
        $row['application_no'], $row['level'], $row['department_name'], $row['prefix'], $row['first_name'],
        $row['last_name'], $row['national_id'], $row['birth_date'], $row['gender'], $row['phone'],
        $row['email'], $row['address'], $row['subdistrict'], $row['district'], $row['province'],
        $row['zipcode'], $row['previous_school'], $row['gpa'], $row['guardian_name'], $row['guardian_phone'],
        $row['guardian_relation'], $row['status'], $row['created_at'],
    ]);
}

fclose($out);
$stmt->close();
