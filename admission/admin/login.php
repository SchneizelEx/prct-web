<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';

if (current_admin() !== null) {
    redirect('index.php');
}

$db = get_db();
$hasAdmin = (int) ($db->query('SELECT COUNT(*) AS total FROM admin_users')->fetch_assoc()['total'] ?? 0) > 0;

$username = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $admin = admin_attempt_login($db, $username, $password);
        if ($admin === null) {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        } else {
            admin_login_session($admin);
            redirect('index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบหลังบ้าน | ระบบรับสมัครนักเรียนใหม่</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admission.css">
</head>
<body>
<div class="container" style="max-width:420px; padding-top:60px;">
    <div class="card">
        <h2>เข้าสู่ระบบเจ้าหน้าที่</h2>

        <?php if (!$hasAdmin): ?>
            <div class="alert alert-info">
                ยังไม่มีบัญชีผู้ดูแลระบบ กรุณา <a href="setup.php">สร้างบัญชีผู้ดูแลระบบชุดแรก</a> ก่อนเข้าใช้งาน
            </div>
        <?php endif; ?>

        <?php if ($error !== null): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group full">
                    <label for="username">ชื่อผู้ใช้</label>
                    <input type="text" id="username" name="username" value="<?= h($username) ?>" autofocus required>
                </div>
                <div class="form-group full">
                    <label for="password">รหัสผ่าน</label>
                    <input type="password" id="password" name="password" required>
                </div>
            </div>
            <div class="btn-row">
                <button type="submit" class="btn">เข้าสู่ระบบ</button>
            </div>
        </form>
    </div>
    <p style="text-align:center;"><a href="../index.php">&larr; กลับหน้าระบบรับสมัคร</a></p>
</div>
</body>
</html>
