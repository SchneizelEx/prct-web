<?php
// การยืนยันตัวตนสำหรับเจ้าหน้าที่ฝ่ายรับสมัคร (หลังบ้าน)

declare(strict_types=1);

/** ตรวจสอบชื่อผู้ใช้/รหัสผ่าน คืนค่าข้อมูลผู้ดูแลระบบถ้าถูกต้อง (ไม่รวม password_hash) */
function admin_attempt_login(mysqli $db, string $username, string $password): ?array
{
    $stmt = $db->prepare(
        'SELECT id, username, password_hash, full_name, role
         FROM admin_users
         WHERE username = ? AND is_active = 1'
    );
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($admin === null || !password_verify($password, $admin['password_hash'])) {
        return null;
    }

    $update = $db->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?');
    $update->bind_param('i', $admin['id']);
    $update->execute();
    $update->close();

    unset($admin['password_hash']);

    return $admin;
}

function admin_login_session(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_full_name'] = $admin['full_name'];
    $_SESSION['admin_role'] = $admin['role'];
}

function admin_logout(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }

    return [
        'id' => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'],
        'full_name' => $_SESSION['admin_full_name'],
        'role' => $_SESSION['admin_role'],
    ];
}

/** เรียกที่หัวไฟล์ทุกหน้าในหลังบ้านที่ต้องล็อกอินก่อนใช้งาน */
function require_admin(): void
{
    if (current_admin() === null) {
        redirect('login.php');
    }
}
