<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';

$db = get_db();
$hasAdmin = (int) ($db->query('SELECT COUNT(*) AS total FROM admin_users')->fetch_assoc()['total'] ?? 0) > 0;

if ($hasAdmin) {
    exit('มีบัญชีผู้ดูแลระบบอยู่แล้ว หน้านี้ใช้ได้เฉพาะการตั้งค่าครั้งแรกเท่านั้น กรุณาเข้าสู่ระบบที่หน้า <a href="login.php">login.php</a>');
}

$username = '';
$fullName = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim((string) ($_POST['username'] ?? ''));
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9_.]{4,50}$/', $username)) {
        $errors[] = 'ชื่อผู้ใช้ต้องมีความยาว 4-50 ตัวอักษร ใช้ได้เฉพาะ a-z, 0-9, _ และ .';
    }
    if ($fullName === '') {
        $errors[] = 'กรุณากรอกชื่อ-นามสกุลผู้ดูแลระบบ';
    }
    if (strlen($password) < 8) {
        $errors[] = 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
    } elseif ($password !== $passwordConfirm) {
        $errors[] = 'ยืนยันรหัสผ่านไม่ตรงกัน';
    }

    if ($errors === []) {
        // ตรวจซ้ำอีกครั้งกันกรณี race condition ระหว่างสองคนเปิดหน้านี้พร้อมกัน
        $hasAdminNow = (int) ($db->query('SELECT COUNT(*) AS total FROM admin_users')->fetch_assoc()['total'] ?? 0) > 0;
        if ($hasAdminNow) {
            $errors[] = 'มีการสร้างบัญชีผู้ดูแลระบบไปแล้ว กรุณาเข้าสู่ระบบตามปกติ';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO admin_users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)');
            $role = 'admin';
            $stmt->bind_param('ssss', $username, $hash, $fullName, $role);
            $stmt->execute();
            $stmt->close();

            flash_set('success', 'สร้างบัญชีผู้ดูแลระบบเรียบร้อยแล้ว กรุณาเข้าสู่ระบบ');
            redirect('login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าบัญชีผู้ดูแลระบบชุดแรก</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admission.css">
</head>
<body>
<div class="container" style="max-width:480px; padding-top:60px;">
    <div class="card">
        <h2>ตั้งค่าบัญชีผู้ดูแลระบบชุดแรก</h2>
        <p class="hint">หน้านี้จะใช้งานได้เพียงครั้งเดียวตอนติดตั้งระบบเท่านั้น</p>

        <?php if ($errors !== []): ?>
            <div class="alert alert-error">
                <ul class="error-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="setup.php">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group full">
                    <label for="full_name">ชื่อ-นามสกุล</label>
                    <input type="text" id="full_name" name="full_name" value="<?= h($fullName) ?>" required>
                </div>
                <div class="form-group full">
                    <label for="username">ชื่อผู้ใช้ (สำหรับเข้าสู่ระบบ)</label>
                    <input type="text" id="username" name="username" value="<?= h($username) ?>" required>
                </div>
                <div class="form-group full">
                    <label for="password">รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group full">
                    <label for="password_confirm">ยืนยันรหัสผ่าน</label>
                    <input type="password" id="password_confirm" name="password_confirm" required>
                </div>
            </div>
            <div class="btn-row">
                <button type="submit" class="btn">สร้างบัญชีผู้ดูแลระบบ</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
