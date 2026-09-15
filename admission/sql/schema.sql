-- =====================================================================
-- ระบบรับสมัครนักเรียนใหม่ ปีการศึกษา 2570 - PRCT Admission System
-- โครงสร้างฐานข้อมูล สำหรับ MySQL 8.4
--
-- วิธีใช้:
--   mysql -u root -p < schema.sql
-- หรือ import ไฟล์นี้ผ่าน phpMyAdmin / MySQL Workbench
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `prct_admission`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_0900_ai_ci;

USE `prct_admission`;

-- ---------------------------------------------------------------------
-- ตารางสาขาวิชาที่เปิดรับสมัคร แยกตามระดับชั้น (ปวช. / ปวส.)
-- ระดับ ปวส. แยกย่อยเป็นภาคปกติ/ภาคสมทบอีกชั้นหนึ่งผ่านคอลัมน์ program_type
-- (ปวช. ไม่มีภาค จึงเก็บเป็นค่าว่าง '')
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admission_departments` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `level`        ENUM('ปวช.', 'ปวส.') NOT NULL,
    `program_type` ENUM('', 'ภาคปกติ', 'ภาคสมทบ') NOT NULL DEFAULT '',
    `code`         VARCHAR(20)  NOT NULL,
    `name`         VARCHAR(150) NOT NULL,
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`   INT          NOT NULL DEFAULT 0,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_department_level_program_code` (`level`, `program_type`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- ตารางผู้ดูแลระบบ (เจ้าหน้าที่ฝ่ายรับสมัคร)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(50)  NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name`     VARCHAR(150) NOT NULL,
    `role`          ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `last_login_at` DATETIME     NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- ตารางใบสมัครนักเรียน/นักศึกษาใหม่
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admission_applications` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_no`     VARCHAR(20)  NOT NULL,
    `academic_year`      SMALLINT UNSIGNED NOT NULL,
    `level`              ENUM('ปวช.', 'ปวส.') NOT NULL,
    `program_type`       ENUM('', 'ภาคปกติ', 'ภาคสมทบ') NOT NULL DEFAULT '',
    `department_id`      INT UNSIGNED NOT NULL,

    -- ข้อมูลผู้สมัคร
    `prefix`             VARCHAR(20)  NOT NULL,
    `first_name`         VARCHAR(100) NOT NULL,
    `last_name`          VARCHAR(100) NOT NULL,
    `national_id`        CHAR(13)     NOT NULL,
    `birth_date`         DATE         NOT NULL,
    `gender`             ENUM('ชาย', 'หญิง') NOT NULL,
    `phone`              VARCHAR(20)  NOT NULL,
    `email`              VARCHAR(150) NULL,

    -- ที่อยู่ปัจจุบัน
    `address`            VARCHAR(255) NOT NULL,
    `subdistrict`        VARCHAR(100) NOT NULL,
    `district`           VARCHAR(100) NOT NULL,
    `province`           VARCHAR(100) NOT NULL,
    `zipcode`            VARCHAR(10)  NOT NULL,

    -- ประวัติการศึกษา
    `previous_school`    VARCHAR(200) NOT NULL,
    `gpa`                DECIMAL(3,2) NULL,

    -- ผู้ปกครอง
    `guardian_name`      VARCHAR(150) NOT NULL,
    `guardian_phone`     VARCHAR(20)  NOT NULL,
    `guardian_relation`  VARCHAR(50)  NULL,

    -- เอกสารแนบ (เก็บ path ไฟล์ที่อัปโหลด)
    `photo_path`         VARCHAR(255) NULL,
    `id_card_path`       VARCHAR(255) NULL,
    `transcript_path`    VARCHAR(255) NULL,
    `house_reg_path`     VARCHAR(255) NULL,

    -- สถานะการพิจารณา
    `status`             ENUM('รอตรวจสอบ', 'ตรวจสอบแล้ว', 'ขอเอกสารเพิ่มเติม', 'อนุมัติ', 'ไม่อนุมัติ')
                         NOT NULL DEFAULT 'รอตรวจสอบ',
    `admin_note`         TEXT NULL,

    `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_application_no` (`application_no`),
    UNIQUE KEY `uq_year_national_id` (`academic_year`, `national_id`),
    KEY `idx_department` (`department_id`),
    KEY `idx_status` (`status`),
    KEY `idx_academic_year` (`academic_year`),
    CONSTRAINT `fk_application_department`
        FOREIGN KEY (`department_id`) REFERENCES `admission_departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- ตารางประวัติการเปลี่ยนสถานะใบสมัคร (audit log)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admission_status_logs` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_id` INT UNSIGNED NOT NULL,
    `old_status`     VARCHAR(50)  NULL,
    `new_status`     VARCHAR(50)  NOT NULL,
    `note`           VARCHAR(255) NULL,
    `changed_by`     VARCHAR(150) NULL,
    `changed_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_log_application` (`application_id`),
    CONSTRAINT `fk_log_application`
        FOREIGN KEY (`application_id`) REFERENCES `admission_applications` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- ข้อมูลตั้งต้น: สาขาวิชาที่เปิดรับสมัคร (แก้ไข/เพิ่มเติมได้ภายหลังผ่านหลังบ้าน)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `admission_departments` (`level`, `program_type`, `code`, `name`, `sort_order`) VALUES
-- ปวช. (ไม่แยกภาค)
('ปวช.', '', 'ACC',    'บัญชี', 1),
('ปวช.', '', 'GAME',   'เกมและแอนิเมชัน', 2),
('ปวช.', '', 'RETAIL', 'ธุรกิจค้าปลีก', 3),
-- ปวส. ภาคปกติ
('ปวส.', 'ภาคปกติ', 'ACC',    'การบัญชี', 1),
('ปวส.', 'ภาคปกติ', 'DBIZ',   'เทคโนโลยีธุรกิจดิจิทัล', 2),
('ปวส.', 'ภาคปกติ', 'RETAIL', 'การจัดการธุรกิจค้าปลีก', 3),
-- ปวส. ภาคสมทบ
('ปวส.', 'ภาคสมทบ', 'ACC',    'การบัญชี', 1),
('ปวส.', 'ภาคสมทบ', 'DBIZ',   'เทคโนโลยีธุรกิจดิจิทัล', 2),
('ปวส.', 'ภาคสมทบ', 'RETAIL', 'การจัดการธุรกิจค้าปลีก', 3),
('ปวส.', 'ภาคสมทบ', 'ELEC',   'ไฟฟ้า', 4);

-- หมายเหตุ: บัญชีผู้ดูแลระบบชุดแรกให้สร้างผ่านหน้า admin/setup.php
-- หลังจากตั้งค่าฐานข้อมูลเสร็จ (ระบบจะอนุญาตให้สร้างได้เฉพาะตอนที่ยังไม่มี
-- ผู้ดูแลระบบอยู่ในตาราง admin_users เท่านั้น เพื่อความปลอดภัย)
