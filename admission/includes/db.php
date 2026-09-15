<?php
// การเชื่อมต่อฐานข้อมูล MySQL 8.4 (ใช้ mysqli แบบ prepared statement ทั้งระบบ)

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

date_default_timezone_set(ADMISSION_TIMEZONE);

// ให้ mysqli โยน exception เมื่อเกิดข้อผิดพลาด แทนที่จะต้องเช็ค return value ทุกครั้ง
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function get_db(): mysqli
{
    static $db = null;

    if ($db === null) {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $db->set_charset('utf8mb4');
    }

    return $db;
}
