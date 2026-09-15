-- =====================================================================
-- Migration: เพิ่มการแยกภาคปกติ/ภาคสมทบ สำหรับระดับ ปวส.
-- ใช้เฉพาะกรณีที่เคย import schema.sql เวอร์ชันเก่าไปแล้วเท่านั้น
-- (ติดตั้งใหม่ให้ใช้ schema.sql ตัวล่าสุดได้เลย ไม่ต้องรันไฟล์นี้)
--
-- วิธีใช้:
--   mysql --default-character-set=utf8mb4 -u root -p prct_admission < migrate_program_type.sql
-- =====================================================================

USE `prct_admission`;

-- 1) เพิ่มคอลัมน์ program_type ให้ตารางสาขาวิชา และปรับ unique key
ALTER TABLE `admission_departments`
    ADD COLUMN `program_type` ENUM('', 'ภาคปกติ', 'ภาคสมทบ') NOT NULL DEFAULT '' AFTER `level`;

ALTER TABLE `admission_departments`
    DROP INDEX `uq_department_level_code`,
    ADD UNIQUE KEY `uq_department_level_program_code` (`level`, `program_type`, `code`);

-- 2) เพิ่มคอลัมน์ program_type ให้ตารางใบสมัคร (บันทึกภาค ณ วันที่สมัคร)
ALTER TABLE `admission_applications`
    ADD COLUMN `program_type` ENUM('', 'ภาคปกติ', 'ภาคสมทบ') NOT NULL DEFAULT '' AFTER `level`;

-- 3) ปิดใช้งานสาขาวิชาเดิมที่ไม่ได้เปิดรับสมัครแล้ว (ไม่ลบทิ้ง เพราะอาจมีใบสมัครอ้างอิงอยู่)
UPDATE `admission_departments` SET `is_active` = 0
WHERE `code` IN ('IT', 'ELECTRONICS', 'AUTO')
   OR (`level` = 'ปวช.' AND `code` = 'ELEC');

-- 4) เพิ่มสาขาวิชาใหม่ตามโครงสร้างล่าสุด (ข้ามรายการที่มีอยู่แล้ว)
INSERT IGNORE INTO `admission_departments` (`level`, `program_type`, `code`, `name`, `sort_order`) VALUES
('ปวช.', '', 'ACC',    'บัญชี', 1),
('ปวช.', '', 'GAME',   'เกมและแอนิเมชัน', 2),
('ปวช.', '', 'RETAIL', 'ธุรกิจค้าปลีก', 3),
('ปวส.', 'ภาคปกติ', 'ACC',    'การบัญชี', 1),
('ปวส.', 'ภาคปกติ', 'DBIZ',   'เทคโนโลยีธุรกิจดิจิทัล', 2),
('ปวส.', 'ภาคปกติ', 'RETAIL', 'การจัดการธุรกิจค้าปลีก', 3),
('ปวส.', 'ภาคสมทบ', 'ACC',    'การบัญชี', 1),
('ปวส.', 'ภาคสมทบ', 'DBIZ',   'เทคโนโลยีธุรกิจดิจิทัล', 2),
('ปวส.', 'ภาคสมทบ', 'RETAIL', 'การจัดการธุรกิจค้าปลีก', 3),
('ปวส.', 'ภาคสมทบ', 'ELEC',   'ไฟฟ้า', 4);

-- หมายเหตุ: แถวเดิมของ ปวส. ที่ใช้รหัส ACC/DBIZ/RETAIL (โค้ดเดียวกับที่เพิ่มใหม่)
-- แต่ยังไม่มีภาค (program_type = '') จะเหลือเป็นสาขาซ้ำที่ปิดใช้งานไม่ได้อัตโนมัติ
-- เพราะ unique key เปลี่ยนไปแล้ว ให้เข้าไปปิดใช้งาน (ปุ่ม "ปิดใช้งาน") ที่หน้า
-- admin/departments.php ด้วยตนเองอีกครั้งหากยังเห็นรายการซ้ำ
