-- Bosch Checklist System Backup
-- Generated: 2026-02-02 04:38:44
-- Database: bosch_checklist_system

DROP TABLE IF EXISTS `checklist_answers`;
CREATE TABLE `checklist_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer` enum('yes','no','na') NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_answer` (`checklist_id`,`question_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `checklist_answers_ibfk_1` FOREIGN KEY (`checklist_id`) REFERENCES `checklists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `checklist_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `checklist_questions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `checklist_answers` (`id`, `checklist_id`, `question_id`, `answer`, `comment`, `created_at`) VALUES
('1', '3', '6', 'yes', '', '2026-01-31 10:11:11'),
('2', '3', '1', 'yes', '', '2026-01-31 10:11:11'),
('3', '3', '2', 'yes', '', '2026-01-31 10:11:11'),
('4', '3', '3', 'yes', '', '2026-01-31 10:11:11'),
('5', '3', '4', 'yes', '', '2026-01-31 10:11:11'),
('6', '3', '5', 'yes', '', '2026-01-31 10:11:11'),
('7', '4', '6', 'yes', '', '2026-02-02 10:10:04'),
('8', '4', '1', 'yes', '', '2026-02-02 10:10:04'),
('9', '4', '2', 'yes', '', '2026-02-02 10:10:04'),
('10', '4', '3', 'yes', '', '2026-02-02 10:10:04'),
('11', '4', '4', 'yes', '', '2026-02-02 10:10:04'),
('12', '4', '5', 'yes', '', '2026-02-02 10:10:04');

DROP TABLE IF EXISTS `checklist_questions`;
CREATE TABLE `checklist_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_type` enum('5s','am') NOT NULL,
  `section` varchar(100) NOT NULL,
  `question_number` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `equipment` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_question` (`checklist_type`,`section`,`question_number`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `checklist_questions` (`id`, `checklist_type`, `section`, `question_number`, `question_text`, `equipment`, `is_active`, `created_at`) VALUES
('1', '5s', 'Zone A', '1', 'Is the area clean and free from debris?', 'Magazine Loader', '1', '2026-01-31 08:30:40'),
('2', '5s', 'Zone A', '2', 'Are magazines properly organized and labeled?', 'Magazine Loader', '1', '2026-01-31 08:30:40'),
('3', '5s', 'Zone A', '3', 'Is the destacker free from dust and contamination?', 'PCB Destacker', '1', '2026-01-31 08:30:40'),
('4', '5s', 'Zone B', '1', 'Is the camera lens clean and free from smudges?', 'SPI', '1', '2026-01-31 08:30:40'),
('5', '5s', 'Zone B', '2', 'Is solder paste at correct temperature and consistency?', 'EKRA Printer', '1', '2026-01-31 08:30:40'),
('6', '5s', 'Oven Area', '1', 'Are inspection cameras clean and calibrated?', 'AOI', '1', '2026-01-31 08:30:40');

DROP TABLE IF EXISTS `checklist_tasks`;
CREATE TABLE `checklist_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_type` enum('5s','am') NOT NULL,
  `task_group` varchar(100) NOT NULL,
  `task_code` varchar(20) NOT NULL,
  `task_title` varchar(255) NOT NULL,
  `task_description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_task` (`checklist_type`,`task_code`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `checklist_tasks` (`id`, `checklist_type`, `task_group`, `task_code`, `task_title`, `task_description`, `is_active`, `created_at`) VALUES
('1', 'am', 'T1-T5', 'T1', 'Visual Inspection of SMT Machine', 'Check for physical damage, loose parts, or abnormal conditions', '1', '2026-01-31 08:30:40'),
('2', 'am', 'T1-T5', 'T2', 'Check Magazine Loader Operation', 'Verify smooth loading and alignment of PCBs', '1', '2026-01-31 08:30:40'),
('3', 'am', 'T1-T5', 'T3', 'PCB Destacker Verification', 'Ensure destacker is functioning properly without jams', '1', '2026-01-31 08:30:40'),
('4', 'am', 'T1-T5', 'T4', 'PCB Cleaner Inspection', 'Check cleaning solution levels and filter condition', '1', '2026-01-31 08:30:40'),
('5', 'am', 'T1-T5', 'T5', 'Conveyor System Check', 'Verify smooth movement and alignment of conveyor belts', '1', '2026-01-31 08:30:40'),
('6', 'am', 'T6-T10', 'T6', 'SPI Machine Calibration Check', 'Verify solder paste inspection accuracy and calibration', '1', '2026-01-31 08:30:40'),
('7', 'am', 'T6-T10', 'T7', 'EKRA Printer Maintenance', 'Check stencil alignment and solder paste consistency', '1', '2026-01-31 08:30:40'),
('8', 'am', 'T6-T10', 'T8', 'Pick & Place Machine Verification', 'Check component placement accuracy and nozzle condition', '1', '2026-01-31 08:30:40'),
('9', 'am', 'T6-T10', 'T9', 'Reflow Oven Temperature Profile', 'Verify temperature zones and profile accuracy', '1', '2026-01-31 08:30:40'),
('10', 'am', 'T6-T10', 'T10', 'Cooling System Check', 'Inspect cooling fans and temperature control', '1', '2026-01-31 08:30:40');

DROP TABLE IF EXISTS `checklists`;
CREATE TABLE `checklists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('5s','am') NOT NULL,
  `title` varchar(255) NOT NULL,
  `shift` varchar(10) DEFAULT NULL,
  `line` varchar(50) DEFAULT NULL,
  `zone` varchar(50) DEFAULT NULL,
  `date` date NOT NULL,
  `total_questions` int(11) DEFAULT 0,
  `yes_answers` int(11) DEFAULT 0,
  `no_answers` int(11) DEFAULT 0,
  `na_answers` int(11) DEFAULT 0,
  `completed_tasks` int(11) DEFAULT 0,
  `total_tasks` int(11) DEFAULT 0,
  `status` enum('completed','in_progress','draft') DEFAULT 'draft',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_date` (`user_id`,`date`),
  KEY `idx_type` (`type`),
  CONSTRAINT `checklists_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `checklists` (`id`, `user_id`, `type`, `title`, `shift`, `line`, `zone`, `date`, `total_questions`, `yes_answers`, `no_answers`, `na_answers`, `completed_tasks`, `total_tasks`, `status`, `submitted_at`, `created_at`, `updated_at`) VALUES
('1', '2', 'am', 'SMT Production Line - AM Checklist', '42C', NULL, NULL, '2026-01-31', '0', '0', '0', '0', '4', '4', 'completed', '2026-01-31 09:58:37', '2026-01-31 09:58:37', '2026-01-31 09:58:37'),
('2', '2', 'am', 'SMT Production Line - AM Checklist', '42C', NULL, NULL, '2026-01-31', '0', '0', '0', '0', '4', '4', 'completed', '2026-01-31 10:02:43', '2026-01-31 10:02:43', '2026-01-31 10:02:43'),
('3', '2', '5s', 'SMT MACHINE - 5S CHECKLIST', '42A', 'SMT 6', 'Zone B', '2026-01-31', '6', '6', '0', '0', '0', '0', 'completed', '2026-01-31 10:11:11', '2026-01-31 10:11:11', '2026-01-31 10:11:11'),
('4', '6', '5s', 'SMT MACHINE - 5S CHECKLIST', '42C', 'SMT 18', 'Zone A', '2026-02-02', '6', '6', '0', '0', '0', '0', 'completed', '2026-02-02 10:10:04', '2026-02-02 10:10:04', '2026-02-02 10:10:04'),
('5', '6', 'am', 'SMT Production Line - AM Checklist', '42B', NULL, NULL, '2026-02-02', '0', '0', '0', '0', '4', '4', 'completed', '2026-02-02 10:29:43', '2026-02-02 10:29:43', '2026-02-02 10:29:43');

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json') DEFAULT 'string',
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `category`, `description`, `created_at`, `updated_at`) VALUES
('1', 'system_name', 'Bosch Digital Checklist System', 'string', 'general', 'System display name', '2026-01-31 08:30:40', '2026-01-31 08:30:40'),
('2', 'company_name', 'Robert Bosch', 'string', 'general', 'Company name', '2026-01-31 08:30:40', '2026-02-02 11:37:55'),
('3', 'default_language', 'en', 'string', 'general', 'Default system language', '2026-01-31 08:30:40', '2026-01-31 08:30:40'),
('4', 'time_zone', 'Asia/Kuala_Lumpur', 'string', 'general', 'System time zone', '2026-01-31 08:30:40', '2026-01-31 08:30:40'),
('5', 'checklist_due_time', '17:00', 'string', 'checklist', 'Default checklist due time', '2026-01-31 08:30:40', '2026-01-31 08:30:40'),
('6', 'auto_save_interval', '5', 'number', 'checklist', 'Auto-save interval in minutes', '2026-01-31 08:30:40', '2026-01-31 08:30:40'),
('7', 'enable_reminders', '1', 'boolean', 'checklist', 'Enable checklist reminders', '2026-01-31 08:30:40', '2026-01-31 08:30:40'),
('8', 'password_expiry_days', '90', 'number', 'security', 'Password expiry in days', '2026-01-31 08:30:40', '2026-02-02 11:38:31'),
('9', 'session_timeout_minutes', '30', 'number', 'security', 'Session timeout in minutes', '2026-01-31 08:30:40', '2026-02-02 11:38:31'),
('10', 'max_login_attempts', '5', 'number', 'security', 'Maximum login attempts', '2026-01-31 08:30:40', '2026-02-02 11:38:31'),
('12', 'company_email', 'support@bosch.com', 'string', 'general', NULL, '2026-02-02 11:24:47', '2026-02-02 11:37:55'),
('13', 'system_timezone', 'UTC', 'string', 'general', NULL, '2026-02-02 11:24:47', '2026-02-02 11:37:55'),
('14', 'date_format', 'Y-m-d', 'string', 'general', NULL, '2026-02-02 11:24:47', '2026-02-02 11:37:55'),
('15', 'time_format', 'H:i:s', 'string', 'general', NULL, '2026-02-02 11:24:47', '2026-02-02 11:37:55'),
('16', 'maintenance_mode', '0', 'string', 'general', NULL, '2026-02-02 11:24:47', '2026-02-02 11:37:55'),
('17', 'system_language', 'en', 'string', 'general', NULL, '2026-02-02 11:24:47', '2026-02-02 11:37:55'),
('25', 'password_min_length', '8', 'string', 'security', NULL, '2026-02-02 11:38:31', '2026-02-02 11:38:31'),
('26', 'password_require_complexity', '1', 'string', 'security', NULL, '2026-02-02 11:38:31', '2026-02-02 11:38:31'),
('29', 'lockout_duration_minutes', '30', 'string', 'security', NULL, '2026-02-02 11:38:31', '2026-02-02 11:38:31'),
('31', 'two_factor_auth', '0', 'string', 'security', NULL, '2026-02-02 11:38:31', '2026-02-02 11:38:31'),
('32', 'ip_whitelist', '', 'string', 'security', NULL, '2026-02-02 11:38:31', '2026-02-02 11:38:31'),
('33', 'audit_log_retention_days', '365', 'string', 'security', NULL, '2026-02-02 11:38:31', '2026-02-02 11:38:31');

DROP TABLE IF EXISTS `task_completions`;
CREATE TABLE `task_completions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_completion` (`checklist_id`,`task_id`),
  KEY `task_id` (`task_id`),
  CONSTRAINT `task_completions_ibfk_1` FOREIGN KEY (`checklist_id`) REFERENCES `checklists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_completions_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `checklist_tasks` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `task_completions` (`id`, `checklist_id`, `task_id`, `completed`, `completed_at`, `notes`) VALUES
('1', '1', '1', '1', '2026-01-31 02:58:37', NULL),
('2', '1', '2', '1', '2026-01-31 02:58:37', NULL),
('3', '1', '10', '1', '2026-01-31 02:58:37', NULL),
('4', '1', '6', '1', '2026-01-31 02:58:37', NULL),
('5', '2', '1', '1', '2026-01-31 03:02:43', NULL),
('6', '2', '2', '1', '2026-01-31 03:02:43', NULL),
('7', '2', '10', '1', '2026-01-31 03:02:43', NULL),
('8', '2', '6', '1', '2026-01-31 03:02:43', NULL),
('9', '5', '1', '1', '2026-02-02 03:29:43', NULL),
('10', '5', '2', '1', '2026-02-02 03:29:43', NULL),
('11', '5', '10', '1', '2026-02-02 03:29:43', NULL),
('12', '5', '8', '1', '2026-02-02 03:29:43', NULL);

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','supervisor','operator','manager') DEFAULT 'operator',
  `department` varchar(50) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `department`, `employee_id`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
('1', 'System Admin', 'admin@bosch.com', 'Admin1234567', 'admin', 'IT', 'BOSCH-ADMIN-001', 'active', NULL, '2026-01-31 08:30:40', '2026-01-31 09:03:55'),
('2', 'Nur Irdina Izzati', 'irdinaizzati@gmail.com', '$2y$10$kpVlSb/fIFPQz/7VwTz8IOKcvslPJMKlNtWn0G28gmLOdP8k40yn6', 'operator', 'it', '123456', 'active', '2026-01-31 10:14:09', '2026-01-31 09:06:15', '2026-01-31 10:14:09'),
('3', 'Admin', 'admin1@bosch.com', '$2y$10$snDFam1P1aqLJyPCGe0QieDLpXRXwvBykEVaUEyDEYWc9wyhKGGTu', 'admin', 'management', '1234567', 'active', '2026-01-31 10:24:39', '2026-01-31 10:24:24', '2026-01-31 10:24:39'),
('4', 'rifaa_nifail', 'rifaanifail24@bosch.com', '$2y$10$QgCEBStECv/ps9RvXGXK/e.km1DxeRxnyAVnJR0o8iPCZpbHRishe', 'admin', 'it', '212121', 'active', NULL, '2026-01-31 10:29:38', '2026-01-31 10:29:49'),
('5', 'Testing', 'testingoperator@bosch.com', '$2y$10$emfwuPgznJ6ZK9U5EbtdDOi.to17PlzLzcskK4TcyvZnwMxYpOB6a', 'operator', 'production', 'OP123', 'active', '2026-01-31 14:34:06', '2026-01-31 14:32:54', '2026-01-31 14:34:06'),
('6', 'AdminTesting', 'admintesting@bosch.com', '$2y$10$bhwBoegm1yOS4vZq513uVeJDc0Zhg4OQ7qbnyxm9zgTD8vEz6aA/G', 'admin', 'management', 'AD123', 'active', '2026-01-31 14:35:39', '2026-01-31 14:35:02', '2026-01-31 14:35:39');

