-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 08, 2025 at 04:01 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `apex`
--
CREATE DATABASE IF NOT EXISTS `apex` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `apex`;

-- --------------------------------------------------------

--
-- Table structure for table `accident_media`
--

DROP TABLE IF EXISTS `accident_media`;
CREATE TABLE `accident_media` (
  `Id` int(20) UNSIGNED NOT NULL,
  `accident_report_id` int(20) UNSIGNED NOT NULL,
  `media_type` enum('Photo','Video','Document') NOT NULL,
  `media_url` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `accident_report`
--

DROP TABLE IF EXISTS `accident_report`;
CREATE TABLE `accident_report` (
  `Id` int(20) UNSIGNED NOT NULL,
  `user_id` int(20) UNSIGNED NOT NULL,
  `policy_id` int(20) UNSIGNED NOT NULL,
  `car_id` int(11) NOT NULL,
  `accident_date` datetime NOT NULL,
  `location` varchar(255) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `description` text NOT NULL,
  `other_parties_involved` text DEFAULT NULL,
  `police_report_number` varchar(50) DEFAULT NULL,
  `police_station` varchar(100) DEFAULT NULL,
  `witness_details` text DEFAULT NULL,
  `status` enum('Reported','Assigned','UnderReview','Approved','Rejected','Paid') NOT NULL DEFAULT 'Reported',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `car`
--

DROP TABLE IF EXISTS `car`;
CREATE TABLE `car` (
  `Id` int(11) NOT NULL,
  `user_id` int(20) UNSIGNED NOT NULL,
  `policy_id` int(20) UNSIGNED DEFAULT NULL,
  `year` year(4) DEFAULT NULL,
  `make` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `number_plate` varchar(20) NOT NULL,
  `color` varchar(30) DEFAULT NULL,
  `engine_number` varchar(50) NOT NULL,
  `chassis_number` varchar(50) NOT NULL,
  `value` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `claim_payment`
--

DROP TABLE IF EXISTS `claim_payment`;
CREATE TABLE `claim_payment` (
  `Id` int(20) UNSIGNED NOT NULL,
  `accident_report_id` int(20) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Mpesa','BankTransfer','Cheque','DirectToRepair') NOT NULL,
  `payment_reference` varchar(100) NOT NULL,
  `payment_date` datetime NOT NULL,
  `approved_by` int(20) UNSIGNED NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
  `Id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `status` enum('New','Read','Replied') DEFAULT 'New',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `damage_assessment`
--

DROP TABLE IF EXISTS `damage_assessment`;
CREATE TABLE `damage_assessment` (
  `Id` int(20) UNSIGNED NOT NULL,
  `accident_report_id` int(20) UNSIGNED NOT NULL,
  `assigned_to` int(20) UNSIGNED NOT NULL,
  `assessment_date` datetime NOT NULL,
  `repair_center_id` int(20) UNSIGNED DEFAULT NULL,
  `estimated_cost` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `assessment_notes` text DEFAULT NULL,
  `status` enum('Pending','InProgress','Completed') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `damage_items`
--

DROP TABLE IF EXISTS `damage_items`;
CREATE TABLE `damage_items` (
  `Id` int(20) UNSIGNED NOT NULL,
  `damage_assessment_id` int(20) UNSIGNED NOT NULL,
  `part_name` varchar(100) NOT NULL,
  `part_description` varchar(255) DEFAULT NULL,
  `repair_or_replace` enum('Repair','Replace') NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emergency_request`
--

DROP TABLE IF EXISTS `emergency_request`;
CREATE TABLE `emergency_request` (
  `Id` int(20) UNSIGNED NOT NULL,
  `accident_report_id` int(20) UNSIGNED NOT NULL,
  `service_id` int(20) UNSIGNED NOT NULL,
  `request_time` datetime NOT NULL,
  `response_time` datetime DEFAULT NULL,
  `status` enum('Requested','Dispatched','OnSite','Completed','Cancelled') NOT NULL DEFAULT 'Requested',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emergency_service`
--

DROP TABLE IF EXISTS `emergency_service`;
CREATE TABLE `emergency_service` (
  `Id` int(20) UNSIGNED NOT NULL,
  `user_id` int(20) UNSIGNED NOT NULL,
  `service_type` enum('Towing','Medical','Police','Legal') NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `contact_phone` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

DROP TABLE IF EXISTS `feedback`;
CREATE TABLE `feedback` (
  `Id` int(20) UNSIGNED NOT NULL,
  `user_id` int(20) UNSIGNED NOT NULL,
  `related_id` int(20) UNSIGNED NOT NULL,
  `related_type` enum('AccidentReport','EmergencyService','RepairService','AdjusterService') NOT NULL,
  `rating` int(1) NOT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fraud_alert`
--

DROP TABLE IF EXISTS `fraud_alert`;
CREATE TABLE `fraud_alert` (
  `Id` int(20) UNSIGNED NOT NULL,
  `accident_report_id` int(20) UNSIGNED NOT NULL,
  `flagged_by` int(20) UNSIGNED NOT NULL,
  `reason` text NOT NULL,
  `severity` enum('Low','Medium','High') NOT NULL,
  `status` enum('Pending','Investigated','Confirmed','FalseAlarm') NOT NULL DEFAULT 'Pending',
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

DROP TABLE IF EXISTS `notification`;
CREATE TABLE `notification` (
  `Id` int(20) UNSIGNED NOT NULL,
  `user_id` int(20) UNSIGNED NOT NULL,
  `related_id` int(20) UNSIGNED NOT NULL,
  `related_type` enum('AccidentReport','DamageAssessment','EmergencyRequest','RepairJob','ClaimPayment') NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `policy`
--

DROP TABLE IF EXISTS `policy`;
CREATE TABLE `policy` (
  `Id` int(20) UNSIGNED NOT NULL,
  `user_id` int(20) UNSIGNED NOT NULL,
  `policy_number` varchar(50) NOT NULL,
  `policy_type` enum('Comprehensive','ThirdParty','TheftOnly') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `premium_amount` decimal(10,2) NOT NULL,
  `coverage_details` text DEFAULT NULL,
  `status` enum('Active','Expired','Cancelled') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `policy_type`
--

DROP TABLE IF EXISTS `policy_type`;
CREATE TABLE `policy_type` (
  `Id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `base_premium_rate` decimal(5,2) NOT NULL,
  `minimum_premium` decimal(10,2) NOT NULL DEFAULT 5000.00,
  `coverage_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`coverage_details`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `policy_type`
--

INSERT INTO `policy_type` (`Id`, `name`, `description`, `base_premium_rate`, `minimum_premium`, `coverage_details`, `is_active`, `created_at`) VALUES
(1, 'Comprehensive', 'Complete coverage including theft, fire, and third party', 8.50, 15000.00, '[\"Accident damage\", \"Theft protection\", \"Fire damage\", \"Third party liability\", \"Medical expenses\", \"Legal liability\"]', 1, '2025-06-08 13:21:04'),
(2, 'Third Party', 'Basic third party liability coverage only', 3.50, 5000.00, '[\"Third party liability\", \"Legal expenses\", \"Emergency assistance\"]', 1, '2025-06-08 13:21:04'),
(3, 'Theft Only', 'Protection against vehicle theft only', 2.50, 3000.00, '[\"Theft protection\", \"Hijacking coverage\", \"Keys and locks replacement\"]', 1, '2025-06-08 13:21:04');

-- --------------------------------------------------------

--
-- Table structure for table `repair_center`
--

DROP TABLE IF EXISTS `repair_center`;
CREATE TABLE `repair_center` (
  `Id` int(20) UNSIGNED NOT NULL,
  `user_id` int(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `contact_person` varchar(100) NOT NULL,
  `contact_phone` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `repair_job`
--

DROP TABLE IF EXISTS `repair_job`;
CREATE TABLE `repair_job` (
  `Id` int(20) UNSIGNED NOT NULL,
  `damage_assessment_id` int(20) UNSIGNED NOT NULL,
  `repair_center_id` int(20) UNSIGNED NOT NULL,
  `start_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `actual_cost` decimal(10,2) DEFAULT NULL,
  `status` enum('Pending','InProgress','Completed','Delivered') NOT NULL DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_log`
--

DROP TABLE IF EXISTS `system_log`;
CREATE TABLE `system_log` (
  `Id` int(20) UNSIGNED NOT NULL,
  `user_id` int(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `Id` int(20) UNSIGNED NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `second_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone_number` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `national_id` varchar(20) NOT NULL,
  `user_type` enum('Policyholder','Adjuster','Admin','RepairCenter','EmergencyService') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accident_media`
--
ALTER TABLE `accident_media`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `accident_report_id` (`accident_report_id`);

--
-- Indexes for table `accident_report`
--
ALTER TABLE `accident_report`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `policy_id` (`policy_id`),
  ADD KEY `car_id` (`car_id`);

--
-- Indexes for table `car`
--
ALTER TABLE `car`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `number_plate` (`number_plate`),
  ADD UNIQUE KEY `engine_number` (`engine_number`),
  ADD UNIQUE KEY `chassis_number` (`chassis_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `policy_id` (`policy_id`);

--
-- Indexes for table `claim_payment`
--
ALTER TABLE `claim_payment`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `accident_report_id` (`accident_report_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`Id`);

--
-- Indexes for table `damage_assessment`
--
ALTER TABLE `damage_assessment`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `accident_report_id` (`accident_report_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `repair_center_id` (`repair_center_id`);

--
-- Indexes for table `damage_items`
--
ALTER TABLE `damage_items`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `damage_assessment_id` (`damage_assessment_id`);

--
-- Indexes for table `emergency_request`
--
ALTER TABLE `emergency_request`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `accident_report_id` (`accident_report_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `emergency_service`
--
ALTER TABLE `emergency_service`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `fraud_alert`
--
ALTER TABLE `fraud_alert`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `accident_report_id` (`accident_report_id`),
  ADD KEY `flagged_by` (`flagged_by`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `policy`
--
ALTER TABLE `policy`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `policy_number` (`policy_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `policy_type`
--
ALTER TABLE `policy_type`
  ADD PRIMARY KEY (`Id`);

--
-- Indexes for table `repair_center`
--
ALTER TABLE `repair_center`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `repair_job`
--
ALTER TABLE `repair_job`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `damage_assessment_id` (`damage_assessment_id`),
  ADD KEY `repair_center_id` (`repair_center_id`);

--
-- Indexes for table `system_log`
--
ALTER TABLE `system_log`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `phone_number` (`phone_number`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `national_id` (`national_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accident_media`
--
ALTER TABLE `accident_media`
  MODIFY `Id` int(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `accident_report`
--
ALTER TABLE `accident_report`
  MODIFY `Id` int(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `car`
--
ALTER TABLE `car`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `claim_payment`
--
ALTER TABLE `claim_payment`
  MODIFY `Id` int(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `damage_assessment`
--
ALTER TABLE `damage_assessment`
  MODIFY `Id` int(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `damage_items`
--
ALTER TABLE `damage_items`
  MODIFY `Id` int(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `emergency_request`
--
ALTER TABLE `emergency_request`
  MODIFY `Id` int(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `emergency_service`
--
ALTER TABLE `emergency_service`
  MODIFY `Id` int(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `Id` int(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fraud_alert`
--
ALTER TABLE `fraud_alert`
  MODIFY `Id` int(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification`
--
ALTER TABLE `notification`
  MODIFY `Id` int(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `policy`
--
ALTER TABLE `policy`
  MODIFY `Id` int(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `policy_type`
--
ALTER TABLE `policy_type`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `repair_center`
--
ALTER TABLE `repair_center`
  MODIFY `Id` int(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `repair_job`
--
ALTER TABLE `repair_job`
  MODIFY `Id` int(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_log`
--
ALTER TABLE `system_log`
  MODIFY `Id` int(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `Id` int(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accident_media`
--
ALTER TABLE `accident_media`
  ADD CONSTRAINT `accident_media_ibfk_1` FOREIGN KEY (`accident_report_id`) REFERENCES `accident_report` (`Id`) ON DELETE CASCADE;

--
-- Constraints for table `accident_report`
--
ALTER TABLE `accident_report`
  ADD CONSTRAINT `accident_report_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`Id`) ON DELETE CASCADE,
  ADD CONSTRAINT `accident_report_ibfk_2` FOREIGN KEY (`policy_id`) REFERENCES `policy` (`Id`) ON DELETE CASCADE,
  ADD CONSTRAINT `accident_report_ibfk_3` FOREIGN KEY (`car_id`) REFERENCES `car` (`Id`) ON DELETE CASCADE;

--
-- Constraints for table `car`
--
ALTER TABLE `car`
  ADD CONSTRAINT `car_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`Id`) ON DELETE CASCADE,
  ADD CONSTRAINT `car_ibfk_2` FOREIGN KEY (`policy_id`) REFERENCES `policy` (`Id`) ON DELETE SET NULL;

--
-- Constraints for table `claim_payment`
--
ALTER TABLE `claim_payment`
  ADD CONSTRAINT `claim_payment_ibfk_1` FOREIGN KEY (`accident_report_id`) REFERENCES `accident_report` (`Id`) ON DELETE CASCADE,
  ADD CONSTRAINT `claim_payment_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `user` (`Id`) ON DELETE CASCADE;

--
-- Constraints for table `damage_assessment`
--
ALTER TABLE `damage_assessment`
  ADD CONSTRAINT `damage_assessment_ibfk_1` FOREIGN KEY (`accident_report_id`) REFERENCES `accident_report` (`Id`) ON DELETE CASCADE,
  ADD CONSTRAINT `damage_assessment_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `user` (`Id`) ON DELETE CASCADE,
  ADD CONSTRAINT `damage_assessment_ibfk_3` FOREIGN KEY (`repair_center_id`) REFERENCES `repair_center` (`Id`) ON DELETE SET NULL;

--
-- Constraints for table `damage_items`
--
ALTER TABLE `damage_items`
  ADD CONSTRAINT `damage_items_ibfk_1` FOREIGN KEY (`damage_assessment_id`) REFERENCES `damage_assessment` (`Id`) ON DELETE CASCADE;

--
-- Constraints for table `emergency_request`
--
ALTER TABLE `emergency_request`
  ADD CONSTRAINT `emergency_request_ibfk_1` FOREIGN KEY (`accident_report_id`) REFERENCES `accident_report` (`Id`) ON DELETE CASCADE,
  ADD CONSTRAINT `emergency_request_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `emergency_service` (`Id`) ON DELETE CASCADE;

--
-- Constraints for table `emergency_service`
--
ALTER TABLE `emergency_service`
  ADD CONSTRAINT `emergency_service_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`Id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`Id`) ON DELETE CASCADE;

--
-- Constraints for table `fraud_alert`
--
ALTER TABLE `fraud_alert`
  ADD CONSTRAINT `fraud_alert_ibfk_1` FOREIGN KEY (`accident_report_id`) REFERENCES `accident_report` (`Id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fraud_alert_ibfk_2` FOREIGN KEY (`flagged_by`) REFERENCES `user` (`Id`) ON DELETE CASCADE;

--
-- Constraints for table `notification`
--
ALTER TABLE `notification`
  ADD CONSTRAINT `notification_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`Id`) ON DELETE CASCADE;

--
-- Constraints for table `policy`
--
ALTER TABLE `policy`
  ADD CONSTRAINT `policy_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`Id`) ON DELETE CASCADE;

--
-- Constraints for table `repair_center`
--
ALTER TABLE `repair_center`
  ADD CONSTRAINT `repair_center_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`Id`) ON DELETE CASCADE;

--
-- Constraints for table `repair_job`
--
ALTER TABLE `repair_job`
  ADD CONSTRAINT `repair_job_ibfk_1` FOREIGN KEY (`damage_assessment_id`) REFERENCES `damage_assessment` (`Id`) ON DELETE CASCADE,
  ADD CONSTRAINT `repair_job_ibfk_2` FOREIGN KEY (`repair_center_id`) REFERENCES `repair_center` (`Id`) ON DELETE CASCADE;

--
-- Constraints for table `system_log`
--
ALTER TABLE `system_log`
  ADD CONSTRAINT `system_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`Id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
