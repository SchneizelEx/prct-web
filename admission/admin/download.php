<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

$allowedFields = ['photo_path', 'id_card_path', 'transcript_path', 'house_reg_path'];

$applicationNo = trim((string) ($_GET['no'] ?? ''));
$field = (string) ($_GET['field'] ?? '');

if ($applicationNo === '' || !in_array($field, $allowedFields, true)) {
    http_response_code(400);
    exit('คำขอไม่ถูกต้อง');
}

$db = get_db();
$stmt = $db->prepare("SELECT `{$field}` AS file_path FROM admission_applications WHERE application_no = ?");
$stmt->bind_param('s', $applicationNo);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row === null || empty($row['file_path'])) {
    http_response_code(404);
    exit('ไม่พบไฟล์เอกสาร');
}

// path ที่เก็บในฐานข้อมูลเป็น relative path ที่ระบบสร้างขึ้นเองเท่านั้น (uploads/<ปี>/<เลขที่ใบสมัคร>/<ไฟล์>)
$baseDir = realpath(__DIR__ . '/../uploads');
$fullPath = realpath(__DIR__ . '/../' . $row['file_path']);

if ($baseDir === false || $fullPath === false || !str_starts_with($fullPath, $baseDir . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('ไม่พบไฟล์เอกสาร');
}

$mime = match (strtolower((string) pathinfo($fullPath, PATHINFO_EXTENSION))) {
    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'pdf' => 'application/pdf',
    default => 'application/octet-stream',
};

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($fullPath));
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
