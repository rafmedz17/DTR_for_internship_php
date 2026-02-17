CREATE DATABASE IF NOT EXISTS `dtr_system`;
USE `dtr_system`;

CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `department` VARCHAR(100) DEFAULT NULL,
    `company_name` VARCHAR(100) DEFAULT NULL,
    `position` VARCHAR(100) DEFAULT 'Intern',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `time_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `time_in` TIME DEFAULT NULL,
    `am_time_out` TIME DEFAULT NULL,
    `pm_time_in` TIME DEFAULT NULL,
    `time_out` TIME DEFAULT NULL,
    `hours_worked` DECIMAL(5,2) DEFAULT NULL,
    `under_time` DECIMAL(5,2) DEFAULT NULL,
    `status` ENUM('present', 'half-day', 'absent') DEFAULT 'present',
    `remarks` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_date` (`user_id`, `date`)
) ENGINE=InnoDB;

-- Migration for existing installations:
-- ALTER TABLE time_records
--   ADD COLUMN am_time_out TIME DEFAULT NULL AFTER time_in,
--   ADD COLUMN pm_time_in  TIME DEFAULT NULL AFTER am_time_out,
--   ADD COLUMN under_time  DECIMAL(5,2) DEFAULT NULL AFTER hours_worked;
-- ALTER TABLE users
--   ADD COLUMN company_name VARCHAR(100) DEFAULT NULL AFTER department;
