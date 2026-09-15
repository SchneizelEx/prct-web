<?php
// ไฟล์เริ่มต้นที่ทุกหน้าของระบบรับสมัครต้อง require ก่อนใช้งานอย่างอื่น

declare(strict_types=1);

if (!file_exists(__DIR__ . '/../config.php')) {
    http_response_code(500);
    exit('ยังไม่ได้ตั้งค่าระบบ: กรุณาคัดลอกไฟล์ admission/config.sample.php เป็น admission/config.php แล้วกรอกข้อมูลฐานข้อมูลให้ครบถ้วน');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}
