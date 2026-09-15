<?php
// ไฟล์ตัวอย่างการตั้งค่า: config.sample.php
// ให้คัดลอกไฟล์นี้เป็น config.php แล้วกรอกข้อมูลจริงของคุณก่อนใช้งาน
// (ไฟล์ config.php จะไม่ถูกส่งขึ้น Git ตามที่กำหนดไว้ใน .gitignore)

declare(strict_types=1);

// ---------- การตั้งค่าฐานข้อมูล MySQL 8.4 ----------
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_USER', 'root');             // เปลี่ยนเป็น Username จริง
define('DB_PASS', '');                 // เปลี่ยนเป็นรหัสผ่านจริง
define('DB_NAME', 'prct_admission');   // เปลี่ยนเป็นชื่อฐานข้อมูลจริง

// ---------- การตั้งค่าระบบรับสมัคร ----------
// ปีการศึกษาที่เปิดรับสมัคร (พ.ศ.)
define('ADMISSION_ACADEMIC_YEAR', 2570);

// เปิด/ปิดรับสมัครออนไลน์ (true = เปิดรับสมัคร, false = ปิดรับสมัครชั่วคราว)
define('ADMISSION_OPEN', true);

// ชื่อสถานศึกษา (แสดงในหัวเอกสาร/ใบสมัคร)
define('SCHOOL_NAME', 'วิทยาลัยเทคโนโลยีพณิชยการพลาญชัยร้อยเอ็ด');
define('SCHOOL_SHORT_NAME', 'PRCT');

// เขตเวลาที่ใช้ในระบบ
define('ADMISSION_TIMEZONE', 'Asia/Bangkok');

// ขนาดไฟล์อัปโหลดสูงสุดต่อไฟล์ (ไบต์) - ค่าเริ่มต้น 5 MB
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);

// นามสกุลไฟล์เอกสารที่อนุญาตให้อัปโหลด
define('UPLOAD_ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'pdf']);
