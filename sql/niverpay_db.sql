-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 01, 2026 at 04:18 PM
-- Server version: 11.4.10-MariaDB
-- PHP Version: 8.4.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `niverpay_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `role` int(11) NOT NULL,
  `faculty` int(11) DEFAULT 0,
  `departments` text DEFAULT NULL COMMENT 'JSON array of allowed department IDs for role 6; NULL = all departments',
  `school` int(11) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` varchar(20) DEFAULT 'active',
  `profile_pic` varchar(255) DEFAULT 'user.jpg',
  `last_login` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_keys`
--

CREATE TABLE `admin_keys` (
  `_key` varchar(20) NOT NULL,
  `exp_date` datetime NOT NULL,
  `status` varchar(25) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_roles`
--

CREATE TABLE `admin_roles` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_support_tickets`
--

CREATE TABLE `admin_support_tickets` (
  `id` int(11) NOT NULL,
  `code` varchar(25) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `created_by_admin_id` int(11) NOT NULL,
  `status` enum('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `category` varchar(50) DEFAULT NULL,
  `assigned_admin_id` int(11) DEFAULT NULL,
  `assigned_role_id` int(11) DEFAULT NULL,
  `related_ticket_id` int(11) DEFAULT NULL,
  `last_message_at` datetime NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_support_ticket_attachments`
--

CREATE TABLE `admin_support_ticket_attachments` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_support_ticket_messages`
--

CREATE TABLE `admin_support_ticket_messages` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `sender_type` enum('user','admin','system') NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `body` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_update_configs`
--

CREATE TABLE `app_update_configs` (
  `id` int(11) NOT NULL,
  `android_latest_version` varchar(50) NOT NULL,
  `android_minimum_version` varchar(50) NOT NULL,
  `android_store_url` varchar(500) NOT NULL,
  `android_title` varchar(255) NOT NULL,
  `android_message` text NOT NULL,
  `android_required` tinyint(1) NOT NULL DEFAULT 0,
  `ios_latest_version` varchar(50) NOT NULL,
  `ios_minimum_version` varchar(50) NOT NULL,
  `ios_store_url` varchar(500) NOT NULL,
  `ios_title` varchar(255) NOT NULL,
  `ios_message` text NOT NULL,
  `ios_required` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `ref_id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `type` varchar(25) NOT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `gateway` varchar(20) DEFAULT 'FLUTTERWAVE' COMMENT 'Payment gateway used: flutterwave, paystack, or interswitch',
  `payment_channel` varchar(20) NOT NULL DEFAULT 'gateway',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `depts`
--

CREATE TABLE `depts` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `status` varchar(15) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_change_requests`
--

CREATE TABLE `email_change_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `new_email` varchar(255) NOT NULL,
  `otp` varchar(10) NOT NULL,
  `exp_date` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` text NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` varchar(100) NOT NULL DEFAULT 'public',
  `school` int(11) DEFAULT NULL,
  `event_link` text DEFAULT NULL,
  `location` text NOT NULL,
  `event_banner` text NOT NULL,
  `price` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `event_date` datetime NOT NULL,
  `event_time` time NOT NULL DEFAULT current_timestamp(),
  `quantity` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `currency` varchar(30) NOT NULL DEFAULT 'NGN',
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_tickets`
--

CREATE TABLE `event_tickets` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `seller` int(11) NOT NULL,
  `buyer` int(11) NOT NULL,
  `ref_id` varchar(50) NOT NULL,
  `status` varchar(15) NOT NULL DEFAULT 'successful',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculties`
--

CREATE TABLE `faculties` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `status` varchar(15) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fund_requests`
--

CREATE TABLE `fund_requests` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `organization` varchar(255) DEFAULT NULL,
  `amount_requested` decimal(15,2) NOT NULL,
  `purpose` text NOT NULL,
  `additional_details` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manuals`
--

CREATE TABLE `manuals` (
  `id` int(11) NOT NULL,
  `title` text NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `price` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `due_date` datetime NOT NULL,
  `quantity` int(11) NOT NULL,
  `dept` int(11) NOT NULL,
  `depts` longtext DEFAULT NULL,
  `coverage` enum('School','Faculty','Custom') NOT NULL DEFAULT 'Custom',
  `faculty` int(11) DEFAULT NULL,
  `host_faculty` int(11) NOT NULL DEFAULT 0,
  `level` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `currency` varchar(30) NOT NULL DEFAULT 'NGN',
  `school_id` int(11) NOT NULL DEFAULT 1,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manuals_bought`
--

CREATE TABLE `manuals_bought` (
  `id` int(11) NOT NULL,
  `manual_id` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `seller` int(11) NOT NULL,
  `buyer` int(11) NOT NULL,
  `payer_user_id` int(11) DEFAULT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `ref_id` varchar(50) NOT NULL,
  `status` varchar(15) NOT NULL DEFAULT 'successful',
  `copy_status` varchar(20) NOT NULL DEFAULT 'active' COMMENT 'active or lost',
  `lost_at` datetime DEFAULT NULL COMMENT 'When the copy was marked lost',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `grant_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = pending, 1 = granted',
  `export_id` int(11) DEFAULT NULL COMMENT 'manual_export_audits.id used to grant this row; NULL for single grant'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manual_bulk_payment_batches`
--

CREATE TABLE `manual_bulk_payment_batches` (
  `id` int(11) NOT NULL,
  `ref_id` varchar(64) NOT NULL,
  `manual_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `payer_user_id` int(11) NOT NULL,
  `payer_dept_id` int(11) NOT NULL DEFAULT 0,
  `manual_seller_id` int(11) DEFAULT NULL,
  `student_count` int(11) NOT NULL DEFAULT 0,
  `subtotal` int(11) NOT NULL DEFAULT 0,
  `fee_percent` decimal(5,2) NOT NULL DEFAULT 5.00,
  `fee_amount` int(11) NOT NULL DEFAULT 0,
  `total_amount` int(11) NOT NULL DEFAULT 0,
  `payment_status` varchar(32) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manual_bulk_payment_students`
--

CREATE TABLE `manual_bulk_payment_students` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `ref_id` varchar(64) NOT NULL,
  `manual_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `payer_user_id` int(11) NOT NULL,
  `payer_dept_id` int(11) NOT NULL DEFAULT 0,
  `placeholder_user_id` int(11) DEFAULT NULL,
  `matched_user_id` int(11) DEFAULT NULL,
  `manuals_bought_id` int(11) DEFAULT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `normalized_first_name` varchar(255) NOT NULL,
  `normalized_last_name` varchar(255) NOT NULL,
  `raw_matric_no` varchar(100) NOT NULL,
  `normalized_matric_no` varchar(100) NOT NULL,
  `pending_lookup_matric_no` varchar(100) NOT NULL,
  `claim_status` varchar(32) NOT NULL DEFAULT 'pending',
  `claimed_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manual_change_logs`
--

CREATE TABLE `manual_change_logs` (
  `id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `manuals_bought_id` int(11) DEFAULT NULL,
  `ref_id` varchar(50) NOT NULL,
  `old_manual_id` int(11) NOT NULL,
  `new_manual_id` int(11) NOT NULL,
  `old_seller_id` int(11) DEFAULT NULL,
  `new_seller_id` int(11) DEFAULT NULL,
  `old_manual_price` int(11) NOT NULL DEFAULT 0,
  `new_manual_price` int(11) NOT NULL DEFAULT 0,
  `source` varchar(20) NOT NULL DEFAULT 'web',
  `request_context` varchar(32) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manual_export_audits`
--

CREATE TABLE `manual_export_audits` (
  `id` int(11) NOT NULL,
  `code` varchar(25) NOT NULL,
  `manual_id` int(11) NOT NULL,
  `hoc_user_id` int(11) NOT NULL,
  `students_count` int(11) NOT NULL,
  `total_amount` int(11) NOT NULL,
  `from_bought_id` int(11) DEFAULT NULL COMMENT 'Start manuals_bought.id used for this export',
  `to_bought_id` int(11) DEFAULT NULL COMMENT 'End manuals_bought.id used for this export',
  `downloaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `grant_status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'Status of the grant: pending or granted',
  `granted_by` int(11) DEFAULT NULL COMMENT 'Admin ID who granted the export',
  `granted_at` datetime DEFAULT NULL COMMENT 'Timestamp when the export was granted',
  `last_student_id` int(11) DEFAULT NULL COMMENT 'Track last student granted for pagination',
  `bought_ids_json` longtext DEFAULT NULL COMMENT 'JSON array of manuals_bought.id included in this export'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manual_payment_batches`
--

CREATE TABLE `manual_payment_batches` (
  `id` int(11) NOT NULL,
  `manual_id` int(11) NOT NULL,
  `hoc_id` int(11) NOT NULL,
  `dept_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `total_students` int(11) NOT NULL,
  `total_amount` int(11) NOT NULL,
  `tx_ref` varchar(50) NOT NULL,
  `gateway` varchar(20) NOT NULL DEFAULT 'PAYSTACK',
  `paystack_subaccount_code` varchar(100) DEFAULT NULL,
  `flw_tx_id` varchar(100) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manual_payment_batch_items`
--

CREATE TABLE `manual_payment_batch_items` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `manual_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `student_matric` varchar(50) DEFAULT NULL,
  `price` int(11) NOT NULL,
  `ref_id` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `material_requests`
--

CREATE TABLE `material_requests` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `requester_user_id` int(11) NOT NULL,
  `requester_dept_id` int(11) NOT NULL DEFAULT 0,
  `requester_faculty_id` int(11) NOT NULL DEFAULT 0,
  `material_code` varchar(100) NOT NULL,
  `material_title` varchar(255) NOT NULL,
  `material_code_normalized` varchar(100) NOT NULL,
  `material_title_normalized` varchar(255) NOT NULL,
  `scope` enum('school','faculty','selected_faculties','selected_departments','my_department') NOT NULL DEFAULT 'my_department',
  `target_faculty_id` int(11) NOT NULL DEFAULT 0,
  `target_department_id` int(11) NOT NULL DEFAULT 0,
  `target_faculty_ids_json` longtext DEFAULT NULL,
  `target_dept_ids_json` longtext DEFAULT NULL,
  `expected_buyers_count` int(11) NOT NULL DEFAULT 0,
  `share_token` varchar(40) NOT NULL,
  `status` enum('open','under_review','resolved') NOT NULL DEFAULT 'open',
  `threshold_percent` decimal(5,2) NOT NULL DEFAULT 40.00,
  `resolved_manual_id` int(11) DEFAULT NULL,
  `resolution_note` text DEFAULT NULL,
  `resolved_by_admin_id` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `material_request_votes`
--

CREATE TABLE `material_request_votes` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mobile_experience_feedback`
--

CREATE TABLE `mobile_experience_feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_choice` enum('android','iphone') NOT NULL,
  `comfort_level` enum('love_it','its_cool','its_okay','kinda_stressful','not_good_experience') NOT NULL,
  `comfort_label` varchar(64) NOT NULL,
  `source_page` varchar(64) NOT NULL DEFAULT 'store',
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'general',
  `data` text DEFAULT NULL COMMENT 'JSON encoded data',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_devices`
--

CREATE TABLE `notification_devices` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `expo_push_token` varchar(255) NOT NULL,
  `platform` enum('android','ios','web') DEFAULT NULL,
  `app_version` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `disabled_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organisation`
--

CREATE TABLE `organisation` (
  `id` int(11) NOT NULL,
  `business_name` text NOT NULL,
  `business_address` text DEFAULT NULL,
  `web_url` text DEFAULT NULL,
  `work_email` varchar(100) DEFAULT NULL,
  `socials` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_manifests`
--

CREATE TABLE `payment_manifests` (
  `id` int(11) NOT NULL,
  `ref_id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `gateway` varchar(20) NOT NULL,
  `subtotal` int(11) NOT NULL DEFAULT 0,
  `charge` int(11) NOT NULL DEFAULT 0,
  `total_amount` int(11) NOT NULL DEFAULT 0,
  `items_json` longtext NOT NULL,
  `manifest_hash` varchar(64) NOT NULL,
  `signature` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_repair_audits`
--

CREATE TABLE `payment_repair_audits` (
  `id` int(11) NOT NULL,
  `ref_id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `gateway` varchar(20) NOT NULL,
  `reason` varchar(100) NOT NULL,
  `cart_snapshot_json` longtext DEFAULT NULL,
  `manifest_snapshot_json` longtext DEFAULT NULL,
  `action_taken` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quick_login_codes`
--

CREATE TABLE `quick_login_codes` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `code` varchar(64) NOT NULL,
  `expiry_datetime` datetime NOT NULL,
  `status` enum('active','expired','used','deleted') NOT NULL DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `ref_id` varchar(100) DEFAULT NULL,
  `materials` longtext DEFAULT NULL,
  `amount` int(11) NOT NULL,
  `remaining_amount` int(11) NOT NULL,
  `status` enum('pending','partially_applied','applied','cancelled') NOT NULL DEFAULT 'pending',
  `reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refund_reservations`
--

CREATE TABLE `refund_reservations` (
  `id` int(11) NOT NULL,
  `refund_id` int(11) NOT NULL,
  `ref_id` varchar(100) NOT NULL,
  `split_sequence` int(11) NOT NULL DEFAULT 1,
  `school_id` int(11) NOT NULL,
  `payer_user_id` int(11) NOT NULL,
  `gateway` varchar(50) NOT NULL,
  `amount` int(11) NOT NULL,
  `channel` enum('web','api') NOT NULL,
  `status` enum('reserved','consumed','released') NOT NULL DEFAULT 'reserved',
  `reserved_at` datetime NOT NULL DEFAULT current_timestamp(),
  `consumed_at` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `release_reason` varchar(255) DEFAULT NULL,
  `school_payable_ledger_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

CREATE TABLE `schools` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `code` varchar(200) NOT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `status` varchar(15) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_internal_wallets`
--

CREATE TABLE `school_internal_wallets` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `current_balance` int(11) NOT NULL DEFAULT 0,
  `pending_payout_balance` int(11) NOT NULL DEFAULT 0,
  `carry_forward_balance` int(11) NOT NULL DEFAULT 0,
  `currency` varchar(10) NOT NULL DEFAULT 'NGN',
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_payable_ledger`
--

CREATE TABLE `school_payable_ledger` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `source_ref_id` varchar(100) NOT NULL,
  `payer_user_id` int(11) NOT NULL,
  `source_medium` varchar(50) NOT NULL,
  `source_channel` varchar(20) NOT NULL DEFAULT 'web',
  `item_subtotal` int(11) NOT NULL DEFAULT 0,
  `collected_total` int(11) NOT NULL DEFAULT 0,
  `charge_amount` int(11) NOT NULL DEFAULT 0,
  `refund_consumption_source_ref_id` varchar(100) DEFAULT NULL,
  `refund_consumed_amount` int(11) NOT NULL DEFAULT 0,
  `refund_amount` int(11) NOT NULL DEFAULT 0,
  `payable_amount` int(11) NOT NULL DEFAULT 0,
  `settled_amount` int(11) NOT NULL DEFAULT 0,
  `carry_forward_amount` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','partially_settled','settled','reversed','carry_forward') NOT NULL DEFAULT 'pending',
  `metadata` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settlement_accounts`
--

CREATE TABLE `settlement_accounts` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `acct_name` text NOT NULL,
  `acct_number` varchar(15) NOT NULL,
  `bank` varchar(50) NOT NULL,
  `flw_id` varchar(9999) DEFAULT NULL,
  `subaccount_code` varchar(100) NOT NULL,
  `gateway` varchar(20) DEFAULT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'user',
  `currency` varchar(20) NOT NULL DEFAULT 'NGN',
  `status` varchar(15) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settlement_batches`
--

CREATE TABLE `settlement_batches` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `scheduled_for` date NOT NULL,
  `batch_reference` varchar(100) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `total_amount` int(11) NOT NULL DEFAULT 0,
  `total_records` int(11) NOT NULL DEFAULT 0,
  `transfer_provider` varchar(50) DEFAULT NULL,
  `provider_reference` varchar(100) DEFAULT NULL,
  `provider_response` longtext DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `failed_at` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settlement_batch_items`
--

CREATE TABLE `settlement_batch_items` (
  `id` int(11) NOT NULL,
  `settlement_batch_id` int(11) NOT NULL,
  `school_payable_ledger_id` int(11) NOT NULL,
  `source_ref_id` varchar(100) NOT NULL,
  `allocated_amount` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','settled','failed') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_contacts`
--

CREATE TABLE `support_contacts` (
  `id` int(11) NOT NULL,
  `whatsapp` varchar(20) DEFAULT NULL COMMENT 'WhatsApp number with country code (e.g., +2348012345678)',
  `email` varchar(255) DEFAULT NULL COMMENT 'Support email address',
  `phone` varchar(20) DEFAULT NULL COMMENT 'Support phone number with country code',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'Only active contact is shown in app',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Support contact information for mobile app';

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets_legacy`
--

CREATE TABLE `support_tickets_legacy` (
  `id` int(11) NOT NULL,
  `code` varchar(25) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(15) NOT NULL DEFAULT 'open',
  `response` text NOT NULL,
  `response_time` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets_v2`
--

CREATE TABLE `support_tickets_v2` (
  `id` int(11) NOT NULL,
  `code` varchar(25) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `category` varchar(50) DEFAULT NULL,
  `assigned_admin_id` int(11) DEFAULT NULL,
  `last_message_at` datetime NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_ticket_attachments`
--

CREATE TABLE `support_ticket_attachments` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_ticket_messages`
--

CREATE TABLE `support_ticket_messages` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `sender_type` enum('user','admin','system') NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `body` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_alerts`
--

CREATE TABLE `system_alerts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `alert_color` enum('red','green','info') NOT NULL DEFAULT 'red',
  `expiry_date` datetime NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `ref_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `amount` int(11) NOT NULL,
  `charge` int(11) DEFAULT NULL,
  `profit` int(11) DEFAULT NULL,
  `refund` int(11) NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `medium` varchar(50) NOT NULL DEFAULT 'FLUTTERWAVE',
  `payment_channel` varchar(20) NOT NULL DEFAULT 'gateway',
  `transaction_context` varchar(30) NOT NULL DEFAULT 'purchase',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `paystack_customer_code` varchar(100) DEFAULT NULL,
  `paystack_customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `wallet_pin_hash` varchar(255) DEFAULT NULL,
  `wallet_pin_updated_at` datetime DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `school` int(11) DEFAULT NULL,
  `dept` int(11) DEFAULT NULL,
  `matric_no` varchar(25) DEFAULT NULL,
  `role` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` varchar(20) DEFAULT 'unverified',
  `adm_year` varchar(255) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT 'user.jpg',
  `last_login` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `nivasity_2_intro_seen_at` datetime DEFAULT NULL,
  `mobile_experience_prompt_visits` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_wallets`
--

CREATE TABLE `user_wallets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `status` enum('active','suspended','closed') NOT NULL DEFAULT 'active',
  `balance` int(11) NOT NULL DEFAULT 0,
  `currency` varchar(10) NOT NULL DEFAULT 'NGN',
  `requested_via` varchar(20) NOT NULL DEFAULT 'web',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `verification_code`
--

CREATE TABLE `verification_code` (
  `user_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `exp_date` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_fee_thresholds`
--

CREATE TABLE `wallet_fee_thresholds` (
  `id` int(11) NOT NULL,
  `label` varchar(100) DEFAULT NULL,
  `min_subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `max_subtotal` decimal(12,2) DEFAULT NULL,
  `fee_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_funding_transactions`
--

CREATE TABLE `wallet_funding_transactions` (
  `id` int(11) NOT NULL,
  `wallet_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `provider` varchar(30) NOT NULL DEFAULT 'paystack',
  `provider_reference` varchar(100) NOT NULL,
  `provider_event` varchar(100) DEFAULT NULL,
  `provider_transaction_id` varchar(100) DEFAULT NULL,
  `provider_account_id` varchar(100) DEFAULT NULL,
  `account_number` varchar(30) DEFAULT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `provider_charge_amount` int(11) NOT NULL DEFAULT 0,
  `consumed_charge_amount` int(11) NOT NULL DEFAULT 0,
  `remaining_charge_amount` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','posted','ignored','failed','reversed') NOT NULL DEFAULT 'posted',
  `source` varchar(20) NOT NULL DEFAULT 'webhook',
  `description` varchar(255) DEFAULT NULL,
  `raw_payload` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `posted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_pre_credits`
--

CREATE TABLE `wallet_pre_credits` (
  `id` int(11) NOT NULL,
  `wallet_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `account_number` varchar(30) NOT NULL,
  `provider_customer_code` varchar(100) DEFAULT NULL,
  `provider_reference` varchar(100) NOT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `confirmed_amount` int(11) DEFAULT NULL,
  `receipt_path` varchar(255) NOT NULL,
  `receipt_name` varchar(255) NOT NULL,
  `receipt_mime_type` varchar(100) DEFAULT NULL,
  `receipt_size` int(11) NOT NULL DEFAULT 0,
  `created_by_admin_id` int(11) NOT NULL,
  `funding_transaction_id` int(11) DEFAULT NULL,
  `ledger_entry_id` int(11) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `confirmed_provider_transaction_id` varchar(100) DEFAULT NULL,
  `confirmation_source` varchar(30) DEFAULT NULL,
  `status` enum('pending_confirmation','confirmed','amount_disputed') NOT NULL DEFAULT 'pending_confirmation',
  `admin_note` text DEFAULT NULL,
  `reconciliation_note` text DEFAULT NULL,
  `confirmed_payload` longtext DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_ledger_entries`
--

CREATE TABLE `wallet_ledger_entries` (
  `id` int(11) NOT NULL,
  `wallet_id` int(11) NOT NULL,
  `entry_type` enum('credit','debit','refund','fee','adjustment') NOT NULL,
  `amount` int(11) NOT NULL,
  `balance_before` int(11) NOT NULL DEFAULT 0,
  `balance_after` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','posted','reversed','failed') NOT NULL DEFAULT 'posted',
  `reference` varchar(100) NOT NULL,
  `provider_reference` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_pin_tokens`
--

CREATE TABLE `wallet_pin_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `purpose` enum('create','update') NOT NULL DEFAULT 'create',
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `verification_token_hash` varchar(255) DEFAULT NULL,
  `verification_token_expires_at` datetime DEFAULT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transfers`
--

CREATE TABLE `wallet_transfers` (
  `id` int(11) NOT NULL,
  `transfer_reference` varchar(100) NOT NULL,
  `request_token` varchar(100) NOT NULL,
  `sender_wallet_id` int(11) NOT NULL,
  `recipient_wallet_id` int(11) NOT NULL,
  `sender_user_id` int(11) NOT NULL,
  `recipient_user_id` int(11) NOT NULL,
  `recipient_lookup_value` varchar(255) DEFAULT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `recipient_email` varchar(255) DEFAULT NULL,
  `recipient_matric_no` varchar(100) DEFAULT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `currency` varchar(10) NOT NULL DEFAULT 'NGN',
  `sender_balance_before` int(11) NOT NULL DEFAULT 0,
  `sender_balance_after` int(11) NOT NULL DEFAULT 0,
  `recipient_balance_before` int(11) NOT NULL DEFAULT 0,
  `recipient_balance_after` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','completed','failed','reversed') NOT NULL DEFAULT 'pending',
  `initiated_via` varchar(20) NOT NULL DEFAULT 'web',
  `description` varchar(255) DEFAULT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_virtual_accounts`
--

CREATE TABLE `wallet_virtual_accounts` (
  `id` int(11) NOT NULL,
  `wallet_id` int(11) NOT NULL,
  `provider` varchar(30) NOT NULL DEFAULT 'paystack',
  `provider_account_id` varchar(100) DEFAULT NULL,
  `provider_customer_code` varchar(100) DEFAULT NULL,
  `account_name` varchar(255) NOT NULL,
  `account_number` varchar(30) NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_slug` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `raw_response` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_roles`
--
ALTER TABLE `admin_roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_support_tickets`
--
ALTER TABLE `admin_support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_admin_ticket_code` (`code`),
  ADD KEY `idx_admin_ticket_status` (`status`),
  ADD KEY `idx_admin_ticket_assigned_admin` (`assigned_admin_id`),
  ADD KEY `idx_admin_ticket_assigned_role` (`assigned_role_id`),
  ADD KEY `idx_admin_ticket_related` (`related_ticket_id`),
  ADD KEY `fk_admin_ticket_created_by` (`created_by_admin_id`);

--
-- Indexes for table `admin_support_ticket_attachments`
--
ALTER TABLE `admin_support_ticket_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_attach_msg` (`message_id`);

--
-- Indexes for table `admin_support_ticket_messages`
--
ALTER TABLE `admin_support_ticket_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_msg_ticket` (`ticket_id`),
  ADD KEY `idx_admin_msg_user` (`user_id`),
  ADD KEY `idx_admin_msg_admin` (`admin_id`);

--
-- Indexes for table `app_update_configs`
--
ALTER TABLE `app_update_configs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `action` (`action`),
  ADD KEY `entity_type` (`entity_type`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gateway` (`gateway`),
  ADD KEY `idx_cart_status_type_ref_id` (`status`,`type`,`ref_id`);

--
-- Indexes for table `depts`
--
ALTER TABLE `depts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_change_requests`
--
ALTER TABLE `email_change_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_email_change_requests_user` (`user_id`),
  ADD KEY `idx_email_change_requests_email` (`new_email`),
  ADD KEY `idx_email_change_requests_exp_date` (`exp_date`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_tickets`
--
ALTER TABLE `event_tickets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `faculties`
--
ALTER TABLE `faculties`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fund_requests`
--
ALTER TABLE `fund_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `manuals`
--
ALTER TABLE `manuals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_manuals_coverage` (`coverage`);

--
-- Indexes for table `manuals_bought`
--
ALTER TABLE `manuals_bought`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_manuals_bought_grant_status` (`grant_status`),
  ADD KEY `idx_manuals_bought_export_id` (`export_id`),
  ADD KEY `idx_manuals_bought_manual_buyer_grant` (`manual_id`,`buyer`,`grant_status`),
  ADD KEY `idx_manuals_bought_ref_id` (`ref_id`),
  ADD KEY `idx_manuals_bought_copy_status` (`copy_status`),
  ADD KEY `idx_manuals_bought_manual_buyer_copy_status` (`manual_id`,`buyer`,`copy_status`);

--
-- Indexes for table `manual_bulk_payment_batches`
--
ALTER TABLE `manual_bulk_payment_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_manual_bulk_payment_ref` (`ref_id`),
  ADD KEY `idx_manual_bulk_payment_payer_created` (`payer_user_id`,`created_at`),
  ADD KEY `idx_manual_bulk_payment_manual_status` (`manual_id`,`payment_status`);

--
-- Indexes for table `manual_bulk_payment_students`
--
ALTER TABLE `manual_bulk_payment_students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_manual_bulk_payment_student` (`manual_id`,`school_id`,`normalized_matric_no`,`normalized_first_name`,`normalized_last_name`,`claim_status`),
  ADD KEY `idx_manual_bulk_payment_ref` (`ref_id`),
  ADD KEY `idx_manual_bulk_payment_batch` (`batch_id`),
  ADD KEY `idx_manual_bulk_payment_match` (`school_id`,`payer_dept_id`,`normalized_matric_no`,`normalized_first_name`,`normalized_last_name`);

--
-- Indexes for table `manual_change_logs`
--
ALTER TABLE `manual_change_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_manual_change_order` (`buyer_id`,`ref_id`,`old_manual_id`),
  ADD UNIQUE KEY `uniq_manual_change_bought` (`manuals_bought_id`),
  ADD KEY `idx_manual_change_new_manual` (`new_manual_id`),
  ADD KEY `idx_manual_change_buyer_created` (`buyer_id`,`created_at`);

--
-- Indexes for table `manual_export_audits`
--
ALTER TABLE `manual_export_audits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_manual_export_code` (`code`),
  ADD KEY `idx_manual_export_manual` (`manual_id`),
  ADD KEY `idx_manual_export_hoc` (`hoc_user_id`),
  ADD KEY `idx_manual_export_status` (`grant_status`),
  ADD KEY `idx_manual_export_bought_range` (`from_bought_id`,`to_bought_id`);

--
-- Indexes for table `manual_payment_batches`
--
ALTER TABLE `manual_payment_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tx_ref` (`tx_ref`),
  ADD KEY `manual_id` (`manual_id`),
  ADD KEY `dept_id` (`dept_id`),
  ADD KEY `school_id` (`school_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `gateway` (`gateway`);

--
-- Indexes for table `manual_payment_batch_items`
--
ALTER TABLE `manual_payment_batch_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ref_id` (`ref_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `manual_id` (`manual_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `material_requests`
--
ALTER TABLE `material_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_material_requests_share_token` (`share_token`),
  ADD KEY `idx_material_requests_school_status` (`school_id`,`status`),
  ADD KEY `idx_material_requests_lookup_code` (`school_id`,`material_code_normalized`),
  ADD KEY `idx_material_requests_lookup_title` (`school_id`,`material_title_normalized`);

--
-- Indexes for table `material_request_votes`
--
ALTER TABLE `material_request_votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_material_request_vote` (`request_id`,`user_id`),
  ADD KEY `idx_material_request_votes_request` (`request_id`),
  ADD KEY `idx_material_request_votes_user` (`user_id`);

--
-- Indexes for table `mobile_experience_feedback`
--
ALTER TABLE `mobile_experience_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mef_user_id` (`user_id`),
  ADD KEY `idx_mef_device_choice` (`device_choice`),
  ADD KEY `idx_mef_created_at` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `type` (`type`),
  ADD KEY `read_at` (`read_at`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_user_unread` (`user_id`,`read_at`,`created_at`);

--
-- Indexes for table `notification_devices`
--
ALTER TABLE `notification_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `expo_push_token` (`expo_push_token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `disabled_at` (`disabled_at`),
  ADD KEY `idx_user_active` (`user_id`,`disabled_at`);

--
-- Indexes for table `organisation`
--
ALTER TABLE `organisation`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_manifests`
--
ALTER TABLE `payment_manifests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_payment_manifests_ref_id` (`ref_id`),
  ADD KEY `idx_payment_manifests_user_id` (`user_id`),
  ADD KEY `idx_payment_manifests_gateway` (`gateway`);

--
-- Indexes for table `payment_repair_audits`
--
ALTER TABLE `payment_repair_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_repair_audits_ref_id` (`ref_id`),
  ADD KEY `idx_payment_repair_audits_user_id` (`user_id`);

--
-- Indexes for table `quick_login_codes`
--
ALTER TABLE `quick_login_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `status` (`status`),
  ADD KEY `expiry_datetime` (`expiry_datetime`),
  ADD KEY `fk_quick_login_admin` (`created_by`),
  ADD KEY `idx_code_status` (`code`,`status`),
  ADD KEY `idx_expiry_status` (`expiry_datetime`,`status`);

--
-- Indexes for table `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_refunds_ref_id` (`ref_id`),
  ADD KEY `idx_refunds_school_status_created` (`school_id`,`status`,`created_at`,`id`);

--
-- Indexes for table `refund_reservations`
--
ALTER TABLE `refund_reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ref_id_status` (`ref_id`,`status`),
  ADD KEY `idx_school_status` (`school_id`,`status`),
  ADD KEY `idx_refund_ref_status` (`refund_id`,`ref_id`,`status`),
  ADD KEY `idx_refund_reservations_school_payable_ledger_id` (`school_payable_ledger_id`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_schools_domain` (`domain`) USING HASH;

--
-- Indexes for table `school_internal_wallets`
--
ALTER TABLE `school_internal_wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_school_internal_wallet_school` (`school_id`);

--
-- Indexes for table `school_payable_ledger`
--
ALTER TABLE `school_payable_ledger`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_school_payable_source_ref` (`source_ref_id`),
  ADD KEY `idx_school_payable_school_status_created` (`school_id`,`status`,`created_at`);

--
-- Indexes for table `settlement_accounts`
--
ALTER TABLE `settlement_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gateway` (`gateway`);

--
-- Indexes for table `settlement_batches`
--
ALTER TABLE `settlement_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_settlement_batch_reference` (`batch_reference`),
  ADD KEY `idx_settlement_batches_school_date` (`school_id`,`scheduled_for`),
  ADD KEY `idx_settlement_batches_status` (`status`);

--
-- Indexes for table `settlement_batch_items`
--
ALTER TABLE `settlement_batch_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_settlement_batch_items_batch` (`settlement_batch_id`),
  ADD KEY `idx_settlement_batch_items_ledger` (`school_payable_ledger_id`);

--
-- Indexes for table `support_contacts`
--
ALTER TABLE `support_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `support_tickets_legacy`
--
ALTER TABLE `support_tickets_legacy`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `support_tickets_v2`
--
ALTER TABLE `support_tickets_v2`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_ticket_code` (`code`),
  ADD KEY `idx_ticket_user` (`user_id`),
  ADD KEY `idx_ticket_status` (`status`),
  ADD KEY `idx_ticket_assigned` (`assigned_admin_id`);

--
-- Indexes for table `support_ticket_attachments`
--
ALTER TABLE `support_ticket_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attach_msg` (`message_id`);

--
-- Indexes for table `support_ticket_messages`
--
ALTER TABLE `support_ticket_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_msg_ticket` (`ticket_id`),
  ADD KEY `idx_msg_user` (`user_id`),
  ADD KEY `idx_msg_admin` (`admin_id`);

--
-- Indexes for table `system_alerts`
--
ALTER TABLE `system_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `active` (`active`),
  ADD KEY `expiry_date` (`expiry_date`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medium` (`medium`),
  ADD KEY `idx_ref_id` (`ref_id`),
  ADD KEY `idx_refund` (`refund`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_wallets`
--
ALTER TABLE `user_wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_wallet_user_id` (`user_id`),
  ADD KEY `idx_user_wallet_school_status` (`school_id`,`status`);

--
-- Indexes for table `wallet_fee_thresholds`
--
ALTER TABLE `wallet_fee_thresholds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wallet_fee_thresholds_status` (`status`),
  ADD KEY `idx_wallet_fee_thresholds_range` (`min_subtotal`,`max_subtotal`);

--
-- Indexes for table `wallet_funding_transactions`
--
ALTER TABLE `wallet_funding_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_wallet_funding_provider_reference` (`provider_reference`),
  ADD KEY `idx_wallet_funding_wallet_created` (`wallet_id`,`created_at`),
  ADD KEY `idx_wallet_funding_status` (`status`);

--
-- Indexes for table `wallet_pre_credits`
--
ALTER TABLE `wallet_pre_credits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_wallet_pre_credits_reference` (`provider_reference`),
  ADD KEY `idx_wallet_pre_credits_status_created` (`status`,`created_at`),
  ADD KEY `idx_wallet_pre_credits_wallet_created` (`wallet_id`,`created_at`),
  ADD KEY `idx_wallet_pre_credits_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_wallet_pre_credits_account_number` (`account_number`);

--
-- Indexes for table `wallet_ledger_entries`
--
ALTER TABLE `wallet_ledger_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wallet_ledger_wallet_created` (`wallet_id`,`created_at`),
  ADD KEY `idx_wallet_ledger_reference` (`reference`),
  ADD KEY `idx_wallet_ledger_provider_reference` (`provider_reference`);

--
-- Indexes for table `wallet_pin_tokens`
--
ALTER TABLE `wallet_pin_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wallet_pin_tokens_user` (`user_id`),
  ADD KEY `idx_wallet_pin_tokens_code` (`code`);

--
-- Indexes for table `wallet_transfers`
--
ALTER TABLE `wallet_transfers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_wallet_transfers_reference` (`transfer_reference`),
  ADD UNIQUE KEY `uniq_wallet_transfers_request_token` (`request_token`),
  ADD KEY `idx_wallet_transfers_sender_wallet_created` (`sender_wallet_id`,`created_at`),
  ADD KEY `idx_wallet_transfers_recipient_wallet_created` (`recipient_wallet_id`,`created_at`),
  ADD KEY `idx_wallet_transfers_status_created` (`status`,`created_at`);

--
-- Indexes for table `wallet_virtual_accounts`
--
ALTER TABLE `wallet_virtual_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_wallet_virtual_account_wallet` (`wallet_id`),
  ADD UNIQUE KEY `uniq_wallet_virtual_account_number` (`account_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_roles`
--
ALTER TABLE `admin_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_support_tickets`
--
ALTER TABLE `admin_support_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_support_ticket_attachments`
--
ALTER TABLE `admin_support_ticket_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_support_ticket_messages`
--
ALTER TABLE `admin_support_ticket_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `app_update_configs`
--
ALTER TABLE `app_update_configs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `depts`
--
ALTER TABLE `depts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_change_requests`
--
ALTER TABLE `email_change_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_tickets`
--
ALTER TABLE `event_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faculties`
--
ALTER TABLE `faculties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fund_requests`
--
ALTER TABLE `fund_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manuals`
--
ALTER TABLE `manuals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manuals_bought`
--
ALTER TABLE `manuals_bought`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manual_bulk_payment_batches`
--
ALTER TABLE `manual_bulk_payment_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manual_bulk_payment_students`
--
ALTER TABLE `manual_bulk_payment_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manual_change_logs`
--
ALTER TABLE `manual_change_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manual_export_audits`
--
ALTER TABLE `manual_export_audits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manual_payment_batches`
--
ALTER TABLE `manual_payment_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manual_payment_batch_items`
--
ALTER TABLE `manual_payment_batch_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `material_requests`
--
ALTER TABLE `material_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `material_request_votes`
--
ALTER TABLE `material_request_votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mobile_experience_feedback`
--
ALTER TABLE `mobile_experience_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_devices`
--
ALTER TABLE `notification_devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organisation`
--
ALTER TABLE `organisation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_manifests`
--
ALTER TABLE `payment_manifests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_repair_audits`
--
ALTER TABLE `payment_repair_audits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quick_login_codes`
--
ALTER TABLE `quick_login_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refunds`
--
ALTER TABLE `refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refund_reservations`
--
ALTER TABLE `refund_reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_internal_wallets`
--
ALTER TABLE `school_internal_wallets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_payable_ledger`
--
ALTER TABLE `school_payable_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settlement_accounts`
--
ALTER TABLE `settlement_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settlement_batches`
--
ALTER TABLE `settlement_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settlement_batch_items`
--
ALTER TABLE `settlement_batch_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_contacts`
--
ALTER TABLE `support_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_tickets_legacy`
--
ALTER TABLE `support_tickets_legacy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_tickets_v2`
--
ALTER TABLE `support_tickets_v2`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_ticket_attachments`
--
ALTER TABLE `support_ticket_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_ticket_messages`
--
ALTER TABLE `support_ticket_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_alerts`
--
ALTER TABLE `system_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_wallets`
--
ALTER TABLE `user_wallets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wallet_fee_thresholds`
--
ALTER TABLE `wallet_fee_thresholds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wallet_funding_transactions`
--
ALTER TABLE `wallet_funding_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wallet_pre_credits`
--
ALTER TABLE `wallet_pre_credits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wallet_ledger_entries`
--
ALTER TABLE `wallet_ledger_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wallet_pin_tokens`
--
ALTER TABLE `wallet_pin_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wallet_transfers`
--
ALTER TABLE `wallet_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wallet_virtual_accounts`
--
ALTER TABLE `wallet_virtual_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_support_tickets`
--
ALTER TABLE `admin_support_tickets`
  ADD CONSTRAINT `fk_admin_ticket_assigned_admin` FOREIGN KEY (`assigned_admin_id`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `fk_admin_ticket_assigned_role` FOREIGN KEY (`assigned_role_id`) REFERENCES `admin_roles` (`id`),
  ADD CONSTRAINT `fk_admin_ticket_created_by` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `fk_admin_ticket_related` FOREIGN KEY (`related_ticket_id`) REFERENCES `support_tickets_v2` (`id`);

--
-- Constraints for table `admin_support_ticket_attachments`
--
ALTER TABLE `admin_support_ticket_attachments`
  ADD CONSTRAINT `fk_admin_attach_msg` FOREIGN KEY (`message_id`) REFERENCES `admin_support_ticket_messages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_support_ticket_messages`
--
ALTER TABLE `admin_support_ticket_messages`
  ADD CONSTRAINT `fk_admin_msg_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `fk_admin_msg_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `admin_support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_admin_msg_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `material_request_votes`
--
ALTER TABLE `material_request_votes`
  ADD CONSTRAINT `fk_material_request_votes_request` FOREIGN KEY (`request_id`) REFERENCES `material_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notification_devices`
--
ALTER TABLE `notification_devices`
  ADD CONSTRAINT `notification_devices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `quick_login_codes`
--
ALTER TABLE `quick_login_codes`
  ADD CONSTRAINT `fk_quick_login_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quick_login_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `refund_reservations`
--
ALTER TABLE `refund_reservations`
  ADD CONSTRAINT `fk_refund_reservations_refund` FOREIGN KEY (`refund_id`) REFERENCES `refunds` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `support_tickets_v2`
--
ALTER TABLE `support_tickets_v2`
  ADD CONSTRAINT `fk_ticket_assigned_admin` FOREIGN KEY (`assigned_admin_id`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `fk_ticket_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `support_ticket_attachments`
--
ALTER TABLE `support_ticket_attachments`
  ADD CONSTRAINT `fk_attach_msg` FOREIGN KEY (`message_id`) REFERENCES `support_ticket_messages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_ticket_messages`
--
ALTER TABLE `support_ticket_messages`
  ADD CONSTRAINT `fk_msg_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `fk_msg_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets_v2` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_msg_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `wallet_funding_transactions`
--
ALTER TABLE `wallet_funding_transactions`
  ADD CONSTRAINT `fk_wallet_funding_transactions_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `user_wallets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `wallet_ledger_entries`
--
ALTER TABLE `wallet_ledger_entries`
  ADD CONSTRAINT `fk_wallet_ledger_entries_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `user_wallets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `wallet_transfers`
--
ALTER TABLE `wallet_transfers`
  ADD CONSTRAINT `fk_wallet_transfers_recipient_wallet` FOREIGN KEY (`recipient_wallet_id`) REFERENCES `user_wallets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_wallet_transfers_sender_wallet` FOREIGN KEY (`sender_wallet_id`) REFERENCES `user_wallets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `wallet_virtual_accounts`
--
ALTER TABLE `wallet_virtual_accounts`
  ADD CONSTRAINT `fk_wallet_virtual_accounts_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `user_wallets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
