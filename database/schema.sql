-- Registrar Queueing Management System; compatible with MySQL 8+ / modern MariaDB.
-- CREATE DATABASE IF NOT EXISTS registrar_queue_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE registrar_queue_db;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL UNIQUE,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 full_name VARCHAR(160) NOT NULL,
 role ENUM('admin','staff','student') NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 must_change_password TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_users_role_status (role,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS students (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL UNIQUE,
 student_number VARCHAR(45) NOT NULL UNIQUE,
 program VARCHAR(150) NOT NULL,
 year_level VARCHAR(30) NOT NULL,
 verification_status ENUM('pending','verified') NOT NULL DEFAULT 'pending',
 CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS staff (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL UNIQUE,
 employee_number VARCHAR(45) NOT NULL UNIQUE,
 CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 service_code VARCHAR(30) NOT NULL UNIQUE,
 service_name VARCHAR(160) NOT NULL,
 description TEXT NULL,
 queue_prefix VARCHAR(8) NOT NULL,
 estimated_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 10,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS counters (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 counter_number VARCHAR(20) NOT NULL UNIQUE,
 counter_name VARCHAR(120) NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS counter_services (
 counter_id BIGINT UNSIGNED NOT NULL,
 service_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(counter_id,service_id),
 CONSTRAINT fk_cs_counter FOREIGN KEY (counter_id) REFERENCES counters(id),
 CONSTRAINT fk_cs_service FOREIGN KEY (service_id) REFERENCES services(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS staff_counter_assignments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 staff_id BIGINT UNSIGNED NOT NULL,
 counter_id BIGINT UNSIGNED NOT NULL,
 assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 ended_at DATETIME NULL,
 CONSTRAINT fk_sca_staff FOREIGN KEY (staff_id) REFERENCES staff(id),
 CONSTRAINT fk_sca_counter FOREIGN KEY (counter_id) REFERENCES counters(id),
 KEY idx_sca_current (staff_id,ended_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS queue_sequences (
 queue_date DATE NOT NULL,
 service_id BIGINT UNSIGNED NOT NULL,
 last_number INT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY(queue_date,service_id),
 CONSTRAINT fk_sequence_service FOREIGN KEY(service_id) REFERENCES services(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS queues (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 student_id BIGINT UNSIGNED NOT NULL,
 service_id BIGINT UNSIGNED NOT NULL,
 counter_id BIGINT UNSIGNED NULL,
 staff_id BIGINT UNSIGNED NULL,
 queue_date DATE NOT NULL,
 queue_sequence INT UNSIGNED NOT NULL,
 queue_number VARCHAR(32) NOT NULL,
 status ENUM('waiting','called','serving','completed','skipped','cancelled') NOT NULL DEFAULT 'waiting',
 priority_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
 remarks TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 called_at DATETIME NULL,
 started_at DATETIME NULL,
 completed_at DATETIME NULL,
 CONSTRAINT fk_queues_student FOREIGN KEY(student_id) REFERENCES students(id),
 CONSTRAINT fk_queues_service FOREIGN KEY(service_id) REFERENCES services(id),
 CONSTRAINT fk_queues_counter FOREIGN KEY(counter_id) REFERENCES counters(id),
 CONSTRAINT fk_queues_staff FOREIGN KEY(staff_id) REFERENCES staff(id),
 UNIQUE KEY uq_queue_sequence (queue_date,service_id,queue_sequence),
 KEY idx_queue_next (queue_date,status,service_id,priority_level,created_at),
 KEY idx_queue_student (student_id,status,created_at),
 KEY idx_queue_counter (counter_id,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 queue_id BIGINT UNSIGNED NOT NULL UNIQUE,
 staff_id BIGINT UNSIGNED NOT NULL,
 counter_id BIGINT UNSIGNED NOT NULL,
 outcome ENUM('completed','skipped') NOT NULL,
 remarks TEXT NULL,
 processing_duration_minutes INT UNSIGNED NULL,
 closed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_transactions_queue FOREIGN KEY(queue_id) REFERENCES queues(id),
 CONSTRAINT fk_transactions_staff FOREIGN KEY(staff_id) REFERENCES staff(id),
 CONSTRAINT fk_transactions_counter FOREIGN KEY(counter_id) REFERENCES counters(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS queue_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 queue_id BIGINT UNSIGNED NOT NULL,
 actor_user_id BIGINT UNSIGNED NULL,
 previous_status VARCHAR(30) NULL,
 new_status VARCHAR(30) NOT NULL,
 event_type VARCHAR(50) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_qe_queue FOREIGN KEY(queue_id) REFERENCES queues(id),
 CONSTRAINT fk_qe_user FOREIGN KEY(actor_user_id) REFERENCES users(id),
 KEY idx_qe_queue (queue_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(160) NOT NULL,
 message TEXT NOT NULL,
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_notifications_user FOREIGN KEY(user_id) REFERENCES users(id),
 KEY idx_notifications_user(user_id,is_read,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS announcements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(180) NOT NULL,
 message TEXT NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_announcements_creator FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NULL,
 action VARCHAR(120) NOT NULL,
 entity_type VARCHAR(50) NULL,
 entity_id BIGINT UNSIGNED NULL,
 details TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_audit_user FOREIGN KEY(user_id) REFERENCES users(id),
 KEY idx_audit_created(created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 status ENUM('pending','resolved') NOT NULL DEFAULT 'pending',
 requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 resolved_at DATETIME NULL,
 CONSTRAINT fk_pr_user FOREIGN KEY(user_id) REFERENCES users(id),
 KEY idx_pr_status(status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS system_settings (
 setting_key VARCHAR(100) PRIMARY KEY,
 setting_value TEXT NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
