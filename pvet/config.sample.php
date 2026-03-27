<?php
// ไฟล์ตัวอย่างการตั้งค่า: config.sample.php
// ให้คัดลอกไฟล์นี้เป็น config.php แล้วกรอกข้อมูลจริงของคุณ ก่อนใช้งาน

// การตั้งค่าฐานข้อมูล MySQL
define('DB_HOST', 'localhost');
define('DB_USER', 'root');         // เปลี่ยนเป็น Username จริง
define('DB_PASS', '');             // เปลี่ยนเป็นรหัสผ่านจริง
define('DB_NAME', 'database_name');// เปลี่ยนเป็นชื่อฐานข้อมูลจริง

// การตั้งค่า API Key สำหรับ Google Maps
// หากพร้อมแล้ว ให้นำ API Key มาใส่แทนที่ช่องว่างด้านล่าง
define('GOOGLE_MAPS_API_KEY', ''); // เว้นว่างไว้ก่อนระบบจะใช้ iframe ธรรมดาให้ก่อน
?>
