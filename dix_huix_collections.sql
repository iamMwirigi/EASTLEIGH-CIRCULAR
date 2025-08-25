-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 22, 2025 at 09:02 AM
-- Server version: 10.5.29-MariaDB-0+deb11u1
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dix_huix_collections`
--

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `comment` text NOT NULL,
  `date` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `member`
--

CREATE TABLE `member` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `phone_number` text NOT NULL,
  `number` int(11) NOT NULL,
  `entry_code` varchar(4) NOT NULL DEFAULT '0000'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `member_loan`
--

CREATE TABLE `member_loan` (
  `id` int(11) NOT NULL,
  `member` int(11) NOT NULL,
  `loan_supercategory` text NOT NULL,
  `loan_category` text NOT NULL,
  `loan_type` varchar(64) NOT NULL,
  `loan_amount` double NOT NULL,
  `repaid_amount` double NOT NULL,
  `accumulated_principle` double NOT NULL,
  `accumulated_interest` double NOT NULL,
  `payment_status` int(11) NOT NULL COMMENT '1=complete, 0=incomplete',
  `date_taken` varchar(32) NOT NULL,
  `expected_repayment` varchar(32) NOT NULL,
  `system_date` varchar(32) NOT NULL,
  `system_time` varchar(32) NOT NULL,
  `penalty` double NOT NULL,
  `repayment_duration` text NOT NULL,
  `repayment_time` text NOT NULL,
  `interest_rate` double NOT NULL,
  `interest_per` text NOT NULL,
  `number_plate` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Triggers `member_loan`
--
DELIMITER $$
CREATE TRIGGER `New Loan Trigger` AFTER INSERT ON `member_loan` FOR EACH ROW INSERT INTO loan_timeline (loan_id,loan_action, loan_type, amount, date_taken,  t_date, t_time, member) VALUES (NEW.id, 'NEW LOAN', NEW.loan_type, NEW.loan_amount, NEW.date_taken, NEW.system_date, NEW.system_time, NEW.member)
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `new_transaction`
--

CREATE TABLE `new_transaction` (
  `id` int(11) NOT NULL,
  `number_plate` varchar(64) NOT NULL,
  `sacco_fee` text NOT NULL,
  `investment` text NOT NULL,
  `savings` text NOT NULL,
  `tyres` text NOT NULL,
  `insurance` text NOT NULL,
  `welfare` text NOT NULL,
  `t_time` text NOT NULL,
  `t_date` text NOT NULL,
  `s_time` text NOT NULL,
  `s_date` text NOT NULL,
  `client_side_id` text NOT NULL,
  `receipt_no` text NOT NULL,
  `collected_by` text NOT NULL,
  `stage_name` text NOT NULL,
  `delete_status` int(11) NOT NULL DEFAULT 0,
  `for_date` text NOT NULL,
  `amount` double NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organization_details`
--

CREATE TABLE `organization_details` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `contacts` text NOT NULL,
  `motto_phrase` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `organization_details`
--

INSERT INTO `organization_details` (`id`, `name`, `contacts`, `motto_phrase`) VALUES
(2, 'DIX-HUIZ SUPREME SHUTTLE LIMITED', 'Management-0729690274,Komarock-0000000000,Eastleigh-0000000000', '');

-- --------------------------------------------------------

--
-- Table structure for table `sms`
--

CREATE TABLE `sms` (
  `id` int(11) NOT NULL,
  `sent_from` text NOT NULL,
  `sent_to` text NOT NULL,
  `package_id` text NOT NULL,
  `text_message` text DEFAULT NULL,
  `af_cost` double NOT NULL DEFAULT 0,
  `sent_time` text DEFAULT NULL,
  `sent_date` date DEFAULT NULL,
  `sms_characters` double NOT NULL DEFAULT 0,
  `sent_status` int(11) NOT NULL DEFAULT 0,
  `pages` int(11) NOT NULL,
  `page_cost` double NOT NULL DEFAULT 0.8,
  `cost` double NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stage`
--

CREATE TABLE `stage` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `prefix` text NOT NULL,
  `quota_start` int(11) NOT NULL,
  `quota_end` int(11) NOT NULL,
  `current_quota` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `vehicle`
--

CREATE TABLE `vehicle` (
  `id` int(11) NOT NULL,
  `number_plate` varchar(64) NOT NULL,
  `owner` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `_admin_`
--

CREATE TABLE `_admin_` (
  `id` int(11) NOT NULL,
  `username` text NOT NULL,
  `password` text NOT NULL,
  `name` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `_user_`
--

CREATE TABLE `_user_` (
  `id` int(11) NOT NULL,
  `username` text NOT NULL,
  `password` text NOT NULL,
  `name` text NOT NULL,
  `stage` text NOT NULL,
  `user_town` int(11) NOT NULL,
  `quota_start` int(11) NOT NULL,
  `quota_end` int(11) NOT NULL,
  `current_quota` int(11) NOT NULL,
  `delete_status` int(11) NOT NULL DEFAULT 0,
  `prefix` varchar(64) NOT NULL DEFAULT 'CHN-',
  `printer_name` varchar(64) NOT NULL DEFAULT 'InnerPrinter'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_comments_name` (`name`(100)),
  ADD KEY `idx_comments_date` (`date`(100));

--
-- Indexes for table `member`
--
ALTER TABLE `member`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `number` (`number`),
  ADD KEY `idx_member_name` (`name`(100)),
  ADD KEY `idx_member_phone` (`phone_number`(100)),
  ADD KEY `idx_member_entry_code` (`entry_code`);

--
-- Indexes for table `member_loan`
--
ALTER TABLE `member_loan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_memberloan_member` (`member`),
  ADD KEY `idx_memberloan_supercategory` (`loan_supercategory`(100)),
  ADD KEY `idx_memberloan_category` (`loan_category`(100)),
  ADD KEY `idx_memberloan_type` (`loan_type`),
  ADD KEY `idx_memberloan_date_taken` (`date_taken`),
  ADD KEY `idx_memberloan_expected` (`expected_repayment`),
  ADD KEY `idx_memberloan_number_plate` (`number_plate`);

--
-- Indexes for table `new_transaction`
--
ALTER TABLE `new_transaction`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_number_plate` (`number_plate`),
  ADD KEY `idx_t_date` (`t_date`(3072)),
  ADD KEY `idx_for_date` (`for_date`(3072)),
  ADD KEY `idx_receipt_no` (`receipt_no`(3072)),
  ADD KEY `idx_collected_by` (`collected_by`(3072)),
  ADD KEY `idx_stage_name` (`stage_name`(3072)),
  ADD KEY `idx_delete_status` (`delete_status`),
  ADD KEY `idx_client_side_id` (`client_side_id`(3072)),
  ADD KEY `idx_transaction_number_plate` (`number_plate`),
  ADD KEY `idx_transaction_t_date` (`t_date`(3072)),
  ADD KEY `idx_transaction_collected_by` (`collected_by`(3072)),
  ADD KEY `idx_transaction_receipt_no` (`receipt_no`(3072)),
  ADD KEY `idx_transaction_stage_name` (`stage_name`(3072)),
  ADD KEY `idx_transaction_for_date` (`for_date`(3072)),
  ADD KEY `idx_transaction_client_id` (`client_side_id`(3072)),
  ADD KEY `idx_transaction_delete_status` (`delete_status`),
  ADD KEY `idx_transaction_sacco_fee` (`sacco_fee`(100)),
  ADD KEY `idx_transaction_investment` (`investment`(100)),
  ADD KEY `idx_transaction_savings` (`savings`(100)),
  ADD KEY `idx_transaction_tyres` (`tyres`(100)),
  ADD KEY `idx_transaction_insurance` (`insurance`(100)),
  ADD KEY `idx_transaction_welfare` (`welfare`(100)),
  ADD KEY `idx_transaction_t_time` (`t_time`(100)),
  ADD KEY `idx_transaction_s_time` (`s_time`(100)),
  ADD KEY `idx_transaction_s_date` (`s_date`(100)),
  ADD KEY `idx_transaction_amount` (`amount`);

--
-- Indexes for table `organization_details`
--
ALTER TABLE `organization_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orgdetails_name` (`name`(100)),
  ADD KEY `idx_orgdetails_contacts` (`contacts`(100));

--
-- Indexes for table `sms`
--
ALTER TABLE `sms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sms_from` (`sent_from`(100)),
  ADD KEY `idx_sms_to` (`sent_to`(100)),
  ADD KEY `idx_sms_package` (`package_id`(100)),
  ADD KEY `idx_sms_sent_time` (`sent_time`(100)),
  ADD KEY `idx_sms_status` (`sent_status`);

--
-- Indexes for table `stage`
--
ALTER TABLE `stage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stage_name` (`name`(100)),
  ADD KEY `idx_stage_prefix` (`prefix`(100));

--
-- Indexes for table `vehicle`
--
ALTER TABLE `vehicle`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `number_plate` (`number_plate`),
  ADD KEY `owner` (`owner`),
  ADD KEY `number_plate_2` (`number_plate`);

--
-- Indexes for table `_admin_`
--
ALTER TABLE `_admin_`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `_user_`
--
ALTER TABLE `_user_`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_username` (`username`(100)),
  ADD KEY `idx_user_password` (`password`(100)),
  ADD KEY `idx_user_name` (`name`(100)),
  ADD KEY `idx_user_stage` (`stage`(100));

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `member`
--
ALTER TABLE `member`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=311;

--
-- AUTO_INCREMENT for table `member_loan`
--
ALTER TABLE `member_loan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `new_transaction`
--
ALTER TABLE `new_transaction`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=198198;

--
-- AUTO_INCREMENT for table `organization_details`
--
ALTER TABLE `organization_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sms`
--
ALTER TABLE `sms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6994;

--
-- AUTO_INCREMENT for table `stage`
--
ALTER TABLE `stage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `vehicle`
--
ALTER TABLE `vehicle`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=804;

--
-- AUTO_INCREMENT for table `_admin_`
--
ALTER TABLE `_admin_`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `_user_`
--
ALTER TABLE `_user_`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `vehicle`
--
ALTER TABLE `vehicle`
  ADD CONSTRAINT `vehicle_owner_constraint` FOREIGN KEY (`owner`) REFERENCES `member` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
