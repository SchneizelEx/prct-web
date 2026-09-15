<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (!ADMISSION_OPEN) {
    flash_set('error', 'ขณะนี้ปิดรับสมัครออนไลน์ชั่วคราว');
    redirect('index.php');
}

$db = get_db();
$departmentsByLevel = [
    'ปวช.' => get_active_departments($db, 'ปวช.'),
    'ปวส.' => get_active_departments($db, 'ปวส.'),
];

$prefixOptions = ['นาย', 'นาง', 'นางสาว', 'เด็กชาย', 'เด็กหญิง'];

/** ค่าฟอร์มเดิม (ใช้แสดงซ้ำเมื่อกรอกไม่ผ่านการตรวจสอบ ไม่รวมรหัสบัตร/ไฟล์เพื่อความปลอดภัย) */
$old = [
    'department_id' => '',
    'prefix' => '',
    'first_name' => '',
    'last_name' => '',
    'national_id' => '',
    'birth_date' => '',
    'gender' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'subdistrict' => '',
    'district' => '',
    'province' => '',
    'zipcode' => '',
    'previous_school' => '',
    'gpa' => '',
    'guardian_name' => '',
    'guardian_phone' => '',
    'guardian_relation' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ($old as $key => $_) {
        $old[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    // ---------------- ตรวจสอบข้อมูล ----------------
    $departmentId = (int) $old['department_id'];
    $department = $departmentId > 0 ? find_active_department($db, $departmentId) : null;
    if ($department === null) {
        $errors[] = 'กรุณาเลือกระดับ/สาขาที่ต้องการสมัคร';
    }

    if ($old['prefix'] === '' || !in_array($old['prefix'], $prefixOptions, true)) {
        $errors[] = 'กรุณาเลือกคำนำหน้าชื่อ';
    }
    if ($old['first_name'] === '') {
        $errors[] = 'กรุณากรอกชื่อ';
    }
    if ($old['last_name'] === '') {
        $errors[] = 'กรุณากรอกนามสกุล';
    }
    if (!validate_thai_national_id($old['national_id'])) {
        $errors[] = 'เลขบัตรประจำตัวประชาชนไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
    }
    $birthDateObj = $old['birth_date'] !== '' ? DateTime::createFromFormat('Y-m-d', $old['birth_date']) : false;
    if ($birthDateObj === false || $birthDateObj->format('Y-m-d') !== $old['birth_date']) {
        $errors[] = 'กรุณาระบุวันเดือนปีเกิดให้ถูกต้อง';
    }
    if (!in_array($old['gender'], ['ชาย', 'หญิง'], true)) {
        $errors[] = 'กรุณาเลือกเพศ';
    }
    if (!preg_match('/^0\d{8,9}$/', $old['phone'])) {
        $errors[] = 'กรุณากรอกเบอร์โทรศัพท์ผู้สมัครให้ถูกต้อง (เช่น 0812345678)';
    }
    if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
    }
    if ($old['address'] === '') {
        $errors[] = 'กรุณากรอกที่อยู่ปัจจุบัน';
    }
    if ($old['subdistrict'] === '') {
        $errors[] = 'กรุณากรอกตำบล/แขวง';
    }
    if ($old['district'] === '') {
        $errors[] = 'กรุณากรอกอำเภอ/เขต';
    }
    if ($old['province'] === '') {
        $errors[] = 'กรุณากรอกจังหวัด';
    }
    if (!preg_match('/^\d{5}$/', $old['zipcode'])) {
        $errors[] = 'กรุณากรอกรหัสไปรษณีย์ 5 หลัก';
    }
    if ($old['previous_school'] === '') {
        $errors[] = 'กรุณากรอกชื่อสถานศึกษาเดิม';
    }
    $gpaValue = null;
    if ($old['gpa'] !== '') {
        if (!is_numeric($old['gpa']) || (float) $old['gpa'] < 0 || (float) $old['gpa'] > 4) {
            $errors[] = 'เกรดเฉลี่ยต้องอยู่ระหว่าง 0.00 - 4.00';
        } else {
            $gpaValue = round((float) $old['gpa'], 2);
        }
    }
    if ($old['guardian_name'] === '') {
        $errors[] = 'กรุณากรอกชื่อ-นามสกุลผู้ปกครอง';
    }
    if (!preg_match('/^0\d{8,9}$/', $old['guardian_phone'])) {
        $errors[] = 'กรุณากรอกเบอร์โทรศัพท์ผู้ปกครองให้ถูกต้อง';
    }

    // เช็คว่าเคยสมัครในปีการศึกษานี้ด้วยเลขบัตรประชาชนเดียวกันแล้วหรือไม่
    if ($errors === [] && validate_thai_national_id($old['national_id'])) {
        $academicYearCheck = ADMISSION_ACADEMIC_YEAR;
        $nationalIdCheck = $old['national_id'];
        $checkStmt = $db->prepare('SELECT application_no FROM admission_applications WHERE academic_year = ? AND national_id = ?');
        $checkStmt->bind_param('is', $academicYearCheck, $nationalIdCheck);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        if ($existing !== null) {
            $errors[] = 'เลขบัตรประจำตัวประชาชนนี้เคยสมัครในปีการศึกษา ' . ADMISSION_ACADEMIC_YEAR . ' แล้ว (เลขที่ใบสมัคร ' . $existing['application_no'] . ') หากต้องการแก้ไขข้อมูล กรุณาติดต่อฝ่ายทะเบียน';
        }
    }

    // ---------------- อัปโหลดเอกสาร (ทำหลังผ่านการตรวจสอบข้อมูลอื่นแล้วเท่านั้น) ----------------
    $uploadedFiles = [];
    if ($errors === []) {
        $tempDir = __DIR__ . '/uploads/tmp_' . session_id();
        try {
            $uploadedFiles['photo_path'] = handle_document_upload('photo', $tempDir, 'photo', true);
            $uploadedFiles['id_card_path'] = handle_document_upload('id_card', $tempDir, 'idcard', true);
            $uploadedFiles['transcript_path'] = handle_document_upload('transcript', $tempDir, 'transcript', true);
            $uploadedFiles['house_reg_path'] = handle_document_upload('house_reg', $tempDir, 'housereg', false);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    // ---------------- บันทึกข้อมูล ----------------
    if ($errors === []) {
        $applicationNo = null;
        $attempts = 0;

        while ($applicationNo === null && $attempts < 5) {
            $attempts++;
            $candidate = generate_application_no($db, ADMISSION_ACADEMIC_YEAR);

            try {
                $stmt = $db->prepare(
                    'INSERT INTO admission_applications
                        (application_no, academic_year, level, department_id, prefix, first_name, last_name,
                         national_id, birth_date, gender, phone, email, address, subdistrict, district, province,
                         zipcode, previous_school, gpa, guardian_name, guardian_phone, guardian_relation,
                         photo_path, id_card_path, transcript_path, house_reg_path)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $academicYear = ADMISSION_ACADEMIC_YEAR;
                $email = $old['email'] !== '' ? $old['email'] : null;
                $guardianRelation = $old['guardian_relation'] !== '' ? $old['guardian_relation'] : null;

                $stmt->bind_param(
                    'sisissssssssssssssdsssssss',
                    $candidate,
                    $academicYear,
                    $department['level'],
                    $departmentId,
                    $old['prefix'],
                    $old['first_name'],
                    $old['last_name'],
                    $old['national_id'],
                    $old['birth_date'],
                    $old['gender'],
                    $old['phone'],
                    $email,
                    $old['address'],
                    $old['subdistrict'],
                    $old['district'],
                    $old['province'],
                    $old['zipcode'],
                    $old['previous_school'],
                    $gpaValue,
                    $old['guardian_name'],
                    $old['guardian_phone'],
                    $guardianRelation,
                    $uploadedFiles['photo_path'],
                    $uploadedFiles['id_card_path'],
                    $uploadedFiles['transcript_path'],
                    $uploadedFiles['house_reg_path']
                );
                $stmt->execute();
                $applicationNo = $candidate;
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    // เลขที่ใบสมัครชนกันพอดี (แข่งกันสมัครในเวลาเดียวกัน) ลองสร้างใหม่
                    $applicationNo = null;
                    continue;
                }
                throw $e;
            }
        }

        if ($applicationNo === null) {
            $errors[] = 'ไม่สามารถออกเลขที่ใบสมัครได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง';
        } else {
            // ย้ายไฟล์จากโฟลเดอร์ชั่วคราวไปยังโฟลเดอร์ถาวรตามเลขที่ใบสมัคร
            $finalDir = __DIR__ . '/uploads/' . ADMISSION_ACADEMIC_YEAR . '/' . $applicationNo;
            if (!is_dir($finalDir)) {
                mkdir($finalDir, 0755, true);
            }
            $relativeDir = 'uploads/' . ADMISSION_ACADEMIC_YEAR . '/' . $applicationNo;
            $finalPaths = [];
            foreach (['photo_path', 'id_card_path', 'transcript_path', 'house_reg_path'] as $field) {
                if ($uploadedFiles[$field] !== null) {
                    rename($tempDir . '/' . $uploadedFiles[$field], $finalDir . '/' . $uploadedFiles[$field]);
                    $finalPaths[$field] = $relativeDir . '/' . $uploadedFiles[$field];
                } else {
                    $finalPaths[$field] = null;
                }
            }
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }

            $update = $db->prepare(
                'UPDATE admission_applications
                 SET photo_path = ?, id_card_path = ?, transcript_path = ?, house_reg_path = ?
                 WHERE application_no = ?'
            );
            $update->bind_param(
                'sssss',
                $finalPaths['photo_path'],
                $finalPaths['id_card_path'],
                $finalPaths['transcript_path'],
                $finalPaths['house_reg_path'],
                $applicationNo
            );
            $update->execute();
            $update->close();

            $_SESSION['last_application_no'] = $applicationNo;
            $_SESSION['authorized_print_app'] = $applicationNo;
            redirect('success.php?app=' . urlencode($applicationNo));
        }
    }

    // ลบไฟล์ชั่วคราวถ้ามีข้อผิดพลาดเกิดขึ้นหลังอัปโหลด
    if (isset($tempDir) && is_dir($tempDir)) {
        foreach ($uploadedFiles as $storedName) {
            if ($storedName !== null && file_exists($tempDir . '/' . $storedName)) {
                unlink($tempDir . '/' . $storedName);
            }
        }
        @rmdir($tempDir);
    }
}

$pageTitle = 'แบบฟอร์มสมัครเรียน';
require __DIR__ . '/includes/public_layout_top.php';
?>

<div class="card">
    <h2>ใบสมัครเรียนออนไลน์ ปีการศึกษา <?= ADMISSION_ACADEMIC_YEAR ?></h2>

    <?php if ($errors !== []): ?>
        <div class="alert alert-error">
            <strong>กรุณาตรวจสอบข้อมูลต่อไปนี้:</strong>
            <ul class="error-list">
                <?php foreach ($errors as $error): ?>
                    <li><?= h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="apply.php" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>

        <div class="form-section">
            <h3>1. ระดับและสาขาที่สมัคร</h3>
            <div class="form-row">
                <div class="form-group full">
                    <label for="department_id">ระดับ/สาขาวิชาที่ต้องการสมัคร <span class="required">*</span></label>
                    <select id="department_id" name="department_id" required>
                        <option value="">-- กรุณาเลือก --</option>
                        <?php foreach ($departmentsByLevel as $level => $departments): ?>
                            <?php if ($departments !== []): ?>
                                <optgroup label="ระดับ <?= h($level) ?>">
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= (int) $d['id'] ?>" <?= (string) $d['id'] === $old['department_id'] ? 'selected' : '' ?>>
                                            <?= h($level . ' สาขา' . $d['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>2. ข้อมูลผู้สมัคร</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="prefix">คำนำหน้าชื่อ <span class="required">*</span></label>
                    <select id="prefix" name="prefix" required>
                        <option value="">-- เลือก --</option>
                        <?php foreach ($prefixOptions as $p): ?>
                            <option value="<?= h($p) ?>" <?= $old['prefix'] === $p ? 'selected' : '' ?>><?= h($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="first_name">ชื่อ <span class="required">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="<?= h($old['first_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name">นามสกุล <span class="required">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="<?= h($old['last_name']) ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="national_id">เลขบัตรประจำตัวประชาชน 13 หลัก <span class="required">*</span></label>
                    <input type="text" id="national_id" name="national_id" inputmode="numeric" maxlength="13"
                           pattern="\d{13}" value="<?= h($old['national_id']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="birth_date">วัน/เดือน/ปีเกิด <span class="required">*</span></label>
                    <input type="date" id="birth_date" name="birth_date" value="<?= h($old['birth_date']) ?>" required>
                </div>
                <div class="form-group">
                    <label>เพศ <span class="required">*</span></label>
                    <div class="radio-group">
                        <label><input type="radio" name="gender" value="ชาย" <?= $old['gender'] === 'ชาย' ? 'checked' : '' ?> required> ชาย</label>
                        <label><input type="radio" name="gender" value="หญิง" <?= $old['gender'] === 'หญิง' ? 'checked' : '' ?>> หญิง</label>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">เบอร์โทรศัพท์ผู้สมัคร <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" value="<?= h($old['phone']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">อีเมล (ถ้ามี)</label>
                    <input type="email" id="email" name="email" value="<?= h($old['email']) ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>3. ที่อยู่ปัจจุบัน</h3>
            <div class="form-row">
                <div class="form-group full">
                    <label for="address">บ้านเลขที่ / หมู่ / ถนน <span class="required">*</span></label>
                    <input type="text" id="address" name="address" value="<?= h($old['address']) ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="subdistrict">ตำบล/แขวง <span class="required">*</span></label>
                    <input type="text" id="subdistrict" name="subdistrict" value="<?= h($old['subdistrict']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="district">อำเภอ/เขต <span class="required">*</span></label>
                    <input type="text" id="district" name="district" value="<?= h($old['district']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="province">จังหวัด <span class="required">*</span></label>
                    <input type="text" id="province" name="province" value="<?= h($old['province']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="zipcode">รหัสไปรษณีย์ <span class="required">*</span></label>
                    <input type="text" id="zipcode" name="zipcode" inputmode="numeric" maxlength="5" value="<?= h($old['zipcode']) ?>" required>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>4. ประวัติการศึกษา</h3>
            <div class="form-row">
                <div class="form-group full">
                    <label for="previous_school">สถานศึกษาเดิม <span class="required">*</span></label>
                    <input type="text" id="previous_school" name="previous_school" value="<?= h($old['previous_school']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="gpa">เกรดเฉลี่ยสะสม (ถ้ามี)</label>
                    <input type="number" id="gpa" name="gpa" step="0.01" min="0" max="4" value="<?= h($old['gpa']) ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>5. ข้อมูลผู้ปกครอง</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="guardian_name">ชื่อ-นามสกุลผู้ปกครอง <span class="required">*</span></label>
                    <input type="text" id="guardian_name" name="guardian_name" value="<?= h($old['guardian_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="guardian_phone">เบอร์โทรศัพท์ผู้ปกครอง <span class="required">*</span></label>
                    <input type="tel" id="guardian_phone" name="guardian_phone" value="<?= h($old['guardian_phone']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="guardian_relation">ความเกี่ยวข้อง (เช่น บิดา/มารดา)</label>
                    <input type="text" id="guardian_relation" name="guardian_relation" value="<?= h($old['guardian_relation']) ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>6. เอกสารประกอบการสมัคร</h3>
            <p class="hint">รองรับไฟล์ JPG, PNG หรือ PDF ขนาดไม่เกิน <?= (int) (UPLOAD_MAX_SIZE / (1024 * 1024)) ?> MB ต่อไฟล์</p>
            <div class="form-row">
                <div class="form-group">
                    <label for="photo">รูปถ่ายหน้าตรง <span class="required">*</span></label>
                    <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>
                <div class="form-group">
                    <label for="id_card">สำเนาบัตรประจำตัวประชาชน <span class="required">*</span></label>
                    <input type="file" id="id_card" name="id_card" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>
                <div class="form-group">
                    <label for="transcript">ระเบียนแสดงผลการเรียน (ปพ.1) <span class="required">*</span></label>
                    <input type="file" id="transcript" name="transcript" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>
                <div class="form-group">
                    <label for="house_reg">สำเนาทะเบียนบ้าน (ถ้ามี)</label>
                    <input type="file" id="house_reg" name="house_reg" accept=".jpg,.jpeg,.png,.pdf">
                </div>
            </div>
        </div>

        <div class="btn-row">
            <button type="submit" class="btn">ส่งใบสมัคร</button>
            <a class="btn btn-outline" href="index.php">ยกเลิก</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/public_layout_bottom.php'; ?>
