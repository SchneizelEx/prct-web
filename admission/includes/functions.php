<?php
// ฟังก์ชันช่วยเหลือที่ใช้ร่วมกันทั้งระบบรับสมัคร

declare(strict_types=1);

/** แปลงข้อความให้ปลอดภัยก่อนแสดงผลใน HTML (กัน XSS) */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** พาไปหน้าอื่นแล้วหยุดการทำงานทันที */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ---------------------------------------------------------------------
// CSRF Token
// ---------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

/** ตรวจสอบ CSRF token ของฟอร์มที่ส่งมาด้วย POST หากไม่ถูกต้องจะหยุดการทำงานทันที */
function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || $token === '' || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        exit('คำขอไม่ถูกต้อง (CSRF token ไม่ตรงกัน) กรุณาโหลดหน้านี้ใหม่แล้วลองอีกครั้ง');
    }
}

// ---------------------------------------------------------------------
// ข้อความแจ้งเตือนชั่วคราว (Flash message)
// ---------------------------------------------------------------------

function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (empty($_SESSION['flash'][$key])) {
        return null;
    }

    $message = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $message;
}

// ---------------------------------------------------------------------
// ตรวจสอบเลขบัตรประจำตัวประชาชน 13 หลัก (mod-11 checksum)
// ---------------------------------------------------------------------

function validate_thai_national_id(string $id): bool
{
    if (!preg_match('/^\d{13}$/', $id)) {
        return false;
    }

    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int) $id[$i] * (13 - $i);
    }

    $checkDigit = (11 - ($sum % 11)) % 10;

    return $checkDigit === (int) $id[12];
}

// ---------------------------------------------------------------------
// เลขที่ใบสมัคร
// ---------------------------------------------------------------------

/** สร้างเลขที่ใบสมัครในรูปแบบ PRCT<ปี พ.ศ. 2 หลักท้าย>-<เลขลำดับ 6 หลัก> */
function generate_application_no(mysqli $db, int $academicYear): string
{
    $yearSuffix = substr((string) $academicYear, -2);

    $stmt = $db->prepare('SELECT COUNT(*) AS total FROM admission_applications WHERE academic_year = ?');
    $stmt->bind_param('i', $academicYear);
    $stmt->execute();
    $total = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $sequence = $total + 1;

    return sprintf('%s%s-%06d', SCHOOL_SHORT_NAME, $yearSuffix, $sequence);
}

// ---------------------------------------------------------------------
// สาขาวิชา
// ---------------------------------------------------------------------

/** แสดงระดับรวมภาค เช่น "ปวช." หรือ "ปวส. ภาคปกติ" */
function format_level_label(string $level, string $programType): string
{
    return $programType !== '' ? $level . ' ' . $programType : $level;
}

/**
 * รายการกลุ่มระดับ/ภาคที่เปิดรับสมัครทั้งหมด (ใช้สร้าง dropdown แบบจัดกลุ่ม)
 * ปวช. ไม่มีภาค (program_type = ''), ปวส. แยกเป็นภาคปกติ/ภาคสมทบ
 *
 * @return array<int, array{level:string, program_type:string, label:string, departments:array}>
 */
function get_department_groups(mysqli $db): array
{
    $groups = [
        ['level' => 'ปวช.', 'program_type' => '', 'label' => 'ระดับ ปวช.'],
        ['level' => 'ปวส.', 'program_type' => 'ภาคปกติ', 'label' => 'ระดับ ปวส. ภาคปกติ'],
        ['level' => 'ปวส.', 'program_type' => 'ภาคสมทบ', 'label' => 'ระดับ ปวส. ภาคสมทบ'],
    ];

    foreach ($groups as &$group) {
        $group['departments'] = get_active_departments($db, $group['level'], $group['program_type']);
    }
    unset($group);

    return $groups;
}

/** @return array<int, array{id:int, code:string, name:string, level:string, program_type:string}> */
function get_active_departments(mysqli $db, string $level, string $programType = ''): array
{
    $stmt = $db->prepare(
        'SELECT id, code, name, level, program_type FROM admission_departments
         WHERE level = ? AND program_type = ? AND is_active = 1
         ORDER BY sort_order ASC, name ASC'
    );
    $stmt->bind_param('ss', $level, $programType);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

/** ค้นหาสาขาวิชาที่ยังเปิดใช้งานจาก id (ใช้ตรวจสอบตอนบันทึกใบสมัคร) */
function find_active_department(mysqli $db, int $departmentId): ?array
{
    $stmt = $db->prepare('SELECT id, code, name, level, program_type FROM admission_departments WHERE id = ? AND is_active = 1');
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

// ---------------------------------------------------------------------
// อัปโหลดไฟล์เอกสารประกอบการสมัคร
// ---------------------------------------------------------------------

/** นามสกุลไฟล์ที่แท้จริงตาม MIME type (ตรวจจากเนื้อไฟล์ ไม่พึ่งชื่อไฟล์ที่ผู้ใช้ตั้ง) */
function upload_ext_from_mime(string $mime): ?string
{
    return match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
        default => null,
    };
}

/**
 * ตรวจสอบและย้ายไฟล์ที่อัปโหลดไปยังโฟลเดอร์ปลายทาง (destDir ต้องเป็น absolute path)
 *
 * @return string|null ชื่อไฟล์ที่จัดเก็บ (ไม่รวม path โฟลเดอร์) หรือ null ถ้าไม่ได้แนบไฟล์
 * @throws RuntimeException ถ้าไฟล์ไม่ผ่านการตรวจสอบ
 */
function handle_document_upload(string $fieldName, string $destDir, string $filePrefix, bool $required): ?string
{
    $file = $_FILES[$fieldName] ?? null;

    if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            throw new RuntimeException('กรุณาแนบไฟล์เอกสารให้ครบถ้วน');
        }

        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('เกิดข้อผิดพลาดระหว่างอัปโหลดไฟล์ กรุณาลองใหม่อีกครั้ง');
    }

    if ($file['size'] <= 0 || $file['size'] > UPLOAD_MAX_SIZE) {
        $maxMb = (int) (UPLOAD_MAX_SIZE / (1024 * 1024));
        throw new RuntimeException("ขนาดไฟล์ต้องไม่เกิน {$maxMb} MB");
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('ไฟล์ที่อัปโหลดไม่ถูกต้อง');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $ext = upload_ext_from_mime((string) $mime);
    if ($ext === null || !in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
        throw new RuntimeException('รองรับเฉพาะไฟล์ภาพ (JPG, PNG) หรือ PDF เท่านั้น');
    }

    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์จัดเก็บไฟล์ได้');
    }

    $storedName = $filePrefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('ไม่สามารถบันทึกไฟล์ที่อัปโหลดได้');
    }

    return $storedName;
}
