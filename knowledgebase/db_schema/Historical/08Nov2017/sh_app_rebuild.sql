-- phpMyAdmin SQL Dump
-- version 4.6.6
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Oct 02, 2017 at 01:40 PM
-- Server version: 5.7.19-0ubuntu0.16.04.1
-- PHP Version: 7.0.22-0ubuntu0.16.04.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sh_app_rebuild`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_billing_master`
--

CREATE TABLE `account_billing_master` (
  `id` mediumint(8) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `plan_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `team_size` smallint(5) UNSIGNED NOT NULL DEFAULT '1',
  `current_subscription_id` int(10) UNSIGNED DEFAULT NULL,
  `next_subscription_updates` varchar(1024) CHARACTER SET utf8 NOT NULL DEFAULT '{}' COMMENT 'JSON data of changes from next subscription',
  `credit_balance` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Credit amount of account',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active, 2-Deleted',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `account_companies`
--

CREATE TABLE `account_companies` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8 NOT NULL,
  `address` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `city` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `state` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `country` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `zipcode` varchar(10) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `logo` varchar(40) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `website` varchar(150) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `contact_phone` varchar(20) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `contact_fax` varchar(20) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `short_info` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `total_mail_sent` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `total_mail_failed` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `total_mail_replied` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `total_mail_bounced` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `total_link_clicks` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `total_document_viewed` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `total_document_facetime` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Number of seconds',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active, 2-Deleted, 3-Blocked',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `account_contacts`
--

CREATE TABLE `account_contacts` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `account_company_id` int(10) UNSIGNED DEFAULT NULL,
  `source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `email` varchar(150) CHARACTER SET utf8 NOT NULL,
  `first_name` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `last_name` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `phone` varchar(20) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `city` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `country` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `notes` varchar(512) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `total_mail_sent` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `total_mail_failed` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `total_mail_replied` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
  `total_mail_bounced` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `total_link_clicks` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `total_document_viewed` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `total_document_facetime` mediumint(8) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Number of seconds',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active, 2-Deleted, 3-Blocked',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `account_folders`
--

CREATE TABLE `account_folders` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `name` varchar(50) CHARACTER SET utf8 NOT NULL,
  `type` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Templates, 2-Documents',
  `public` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `share_access` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '{}' COMMENT 'Shared users access rights',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Deleted',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `account_folder_teams`
--

CREATE TABLE `account_folder_teams` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_folder_id` int(10) UNSIGNED DEFAULT NULL,
  `account_team_id` int(10) UNSIGNED DEFAULT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Deleted',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `account_invoice_details`
--

CREATE TABLE `account_invoice_details` (
  `id` int(10) UNSIGNED NOT NULL,
  `invoice_number` varchar(50) CHARACTER SET utf8 NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `account_subscription_id` int(10) UNSIGNED DEFAULT NULL,
  `account_payment_id` int(10) UNSIGNED DEFAULT NULL,
  `currency` varchar(5) CHARACTER SET utf8 NOT NULL DEFAULT 'USD',
  `amount` decimal(10,2) UNSIGNED NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(10,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT 'Discount amount (if any)',
  `credit_amount` decimal(10,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT 'Credit amount used (if any)',
  `total_amount` decimal(10,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT 'Paid amount',
  `file_copy` varchar(40) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'Path of PDF copy of invoice',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `account_link_master`
--

CREATE TABLE `account_link_master` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `url` varchar(255) CHARACTER SET utf8 NOT NULL,
  `redirect_key` varchar(25) CHARACTER SET utf8 NOT NULL,
  `total_clicked` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `last_clicked` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Latest url clicked date time (GMT+00:00 Timestamp)',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Deleted, 3-Blocked',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `account_master`
--

CREATE TABLE `account_master` (
  `id` mediumint(8) UNSIGNED NOT NULL,
  `ac_number` varchar(25) CHARACTER SET utf8 NOT NULL,
  `source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `configuration` varchar(1024) CHARACTER SET utf8 NOT NULL DEFAULT '{}',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active, 2-Deleted',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `account_master`
--

INSERT INTO `account_master` (`id`, `ac_number`, `source_id`, `configuration`, `status`, `created`, `modified`) VALUES
(1, 'SHAPP-20171002133154-374', 1, '{}', 1, 1506931314, 1506931314);

-- --------------------------------------------------------

--
-- Table structure for table `account_organization`
--

CREATE TABLE `account_organization` (
  `id` mediumint(8) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8 NOT NULL,
  `address` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `city` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `state` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `country` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `zipcode` varchar(10) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `logo` varchar(40) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `website` varchar(150) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `contact_phone` varchar(20) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `contact_fax` varchar(20) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `short_info` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `account_payment_details`
--

CREATE TABLE `account_payment_details` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `account_subscription_id` int(10) UNSIGNED DEFAULT NULL,
  `currency` varchar(5) CHARACTER SET utf8 NOT NULL DEFAULT 'USD',
  `amount_paid` decimal(10,2) UNSIGNED NOT NULL DEFAULT '0.00',
  `payment_method_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `tp_payload` text CHARACTER SET utf8 NOT NULL COMMENT 'Payment gateway response',
  `tp_payment_id` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'Payment gateway payment id',
  `type` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Plan Subscription, 2-Recurring, 3-Team Size Increase, 4-Upgrade',
  `paid_at` int(10) UNSIGNED DEFAULT '0' COMMENT 'Payment date time (GMT+00:00 Timestamp)',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Pending, 1-Success, 2-Fail, 3-Fraud',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `account_sending_methods`
--

CREATE TABLE `account_sending_methods` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `email_sending_method_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8 NOT NULL,
  `from_name` varchar(50) CHARACTER SET utf8 NOT NULL,
  `from_email` varchar(150) CHARACTER SET utf8 NOT NULL,
  `payload` varchar(1024) CHARACTER SET utf8 NOT NULL,
  `incoming_payload` varchar(1024) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `connection_status` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Invalid, 1-Valid',
  `connection_info` varchar(1024) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `last_connected_at` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `last_error` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'Last error description',
  `total_mail_sent` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `total_mail_failed` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `total_mail_replied` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `total_mail_bounced` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `public` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `total_limit` int(10) NOT NULL DEFAULT '0',
  `credit_limit` int(10) NOT NULL DEFAULT '0',
  `last_reset` int(10) NOT NULL DEFAULT '0',
  `next_reset` int(10) NOT NULL DEFAULT '0',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active, 2-Deleted, 3-Blocked',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `account_subscription_details`
--

CREATE TABLE `account_subscription_details` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `plan_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `team_size` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `currency` varchar(5) CHARACTER SET utf8 NOT NULL DEFAULT 'USD',
  `amount` decimal(10,2) UNSIGNED NOT NULL DEFAULT '0.00',
  `credit_balance` decimal(10,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT 'Credit used (if any)',
  `coupon_id` smallint(5) UNSIGNED DEFAULT NULL,
  `discount_type` varchar(10) CHARACTER SET utf8 NOT NULL DEFAULT 'AMT' COMMENT 'AMT-Fix Amount, PER-Percentage of Amount',
  `discount_value` decimal(10,2) UNSIGNED NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(10,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT 'Calculated based on amount and discount fields',
  `total_amount` decimal(10,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT 'Total payable amount (amount - credit_balance - discount_amount)',
  `payment_method_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `start_date` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Subscription start date time(GMT+00:00 Timestamp)',
  `end_date` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Subscription end date time (GMT+00:00 Timestamp)',
  `next_subscription_id` int(10) UNSIGNED DEFAULT NULL,
  `tp_subscription_id` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'Payment gateway subscription id',
  `tp_customer_id` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'Payment gateway customer id',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '3' COMMENT '0-Pending, 1-Success, 2-Fail',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `account_teams`
--

CREATE TABLE `account_teams` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `name` varchar(50) CHARACTER SET utf8 NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `owner_id` int(10) UNSIGNED DEFAULT NULL,
  `manager_id` int(10) UNSIGNED DEFAULT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Delete',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `account_team_members`
--

CREATE TABLE `account_team_members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_team_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Deleted',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `account_templates`
--

CREATE TABLE `account_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8 NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8 NOT NULL,
  `content` text CHARACTER SET utf8 NOT NULL,
  `total_mail_usage` mediumint(8) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Used in number of emails',
  `total_mail_open` mediumint(8) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Number of email opens (unique)',
  `total_campaign_usage` mediumint(8) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Used in number of campaigns',
  `total_campaign_mails` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Number of recipients sent',
  `total_campaign_open` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Number of recipients opened (unique)',
  `last_used_at` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `public` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `share_access` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '{}' COMMENT 'Shared users access rights',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active, 2-Deleted',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `account_template_folders`
--

CREATE TABLE `account_template_folders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_template_id` int(10) UNSIGNED DEFAULT NULL,
  `account_folder_id` int(10) UNSIGNED DEFAULT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Deleted',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `account_template_teams`
--

CREATE TABLE `account_template_teams` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_template_id` int(10) UNSIGNED DEFAULT NULL,
  `account_team_id` int(10) UNSIGNED DEFAULT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Deleted',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `activity`
--

CREATE TABLE `activity` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `record_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-Unknown',
  `other_data` varchar(512) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'Location and other information',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `api_messages`
--

CREATE TABLE `api_messages` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `code` varchar(25) NOT NULL,
  `http_code` smallint(5) UNSIGNED NOT NULL COMMENT 'HTTP status code',
  `error_code` smallint(5) UNSIGNED NOT NULL COMMENT 'Error code',
  `error_message` varchar(255) NOT NULL COMMENT 'API response message',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `api_messages`
--

INSERT INTO `api_messages` (`id`, `code`, `http_code`, `error_code`, `error_message`, `status`, `created`, `modified`) VALUES
(1, 'DB_CONNECTION_FAIL', 500, 1001, 'Could not connect with database.', 1, 1496645339, 1496645339),
(2, 'DB_OPERATION_FAIL', 500, 1002, 'Database operation failed. Please try again.', 1, 1496645339, 1496645339),
(3, 'SERVER_EXCEPTION', 500, 1003, 'Server encountered an error. Please try again.', 1, 1496645339, 1496645339),
(4, 'REQUEST_NOT_FOUND', 404, 1004, 'Resource not found.', 1, 1496645339, 1496645339),
(5, 'INVALID_HEADER_VALUE', 406, 1005, 'Incorrect value passed for header(s): ', 1, 1496645339, 1496645339),
(6, 'AUTH_TOKEN_EXPIRED', 401, 1006, 'Authorization token is expired. Please login again.', 1, 1496645339, 1496645339),
(7, 'SOURCE_MISSING', 406, 1007, 'Source header is missing from request.', 1, 1496645339, 1496645339),
(8, 'INVALID_SOURCE', 406, 1008, 'Invalid value passed for source header.', 1, 1496645339, 1496645339),
(9, 'REQUIRED_HEADER_MISSING', 406, 1009, 'Required headers missing from request: ', 1, 1496645339, 1496645339),
(10, 'INVALID_HTTP_METHOD', 405, 1010, 'Invalid HTTP method.', 1, 1496645339, 1496645339),
(11, 'LOGIN_REQUIRED', 401, 1011, 'Authentication token is missing. Please login to your account.', 1, 1496645339, 1496645339),
(12, 'NOT_AUTHORIZED', 403, 1012, 'You are not authorized to access this resource.', 1, 1496645339, 1496645339),
(13, 'UPGRADE_ACCOUNT', 402, 1013, 'Your plan does not include access to this resource.', 1, 1496645339, 1496645339),
(14, 'INVALID_REQUEST_BODY', 400, 1014, 'Invalid request. Please correct following errors:\\n', 1, 1496645339, 1496645339),
(15, 'USER_ACCOUNT_NOT_ACTIVE', 401, 1015, 'Your account is not active.', 1, 1496645339, 1496645339),
(16, 'USER_DELETED', 401, 1016, 'Your account is deleted.', 1, 1496645339, 1496645339),
(17, 'ACCOUNT_CLOSED', 401, 1017, 'Your account has been closed.', 1, 1496645339, 1496645339),
(18, 'ACCOUNT_DELETED', 401, 1018, 'Your account has been deleted.', 1, 1496645339, 1496645339),
(19, 'USER_NOT_VERIFIED', 400, 1019, 'Your account is not verified.', 1, 1496645339, 1496645339),
(20, 'RECORD_NOT_FOUND', 404, 1020, 'Data which you are trying to access was not found.', 1, 1496645339, 1496645339),
(21, 'LOGIN_WRONG_CREDENTIALS', 404, 2001, 'Invalid credentials. No account found.', 1, 1496645339, 1496645339),
(22, 'USER_ALREADY_EXISTS', 400, 2026, 'User with this email address already exists.', 1, 1496645339, 1496645339),
(23, 'INVALID_VERIFICATION_CODE', 400, 2031, 'Verification url is not valid.', 1, 1496645339, 1496645339),
(24, 'ACCOUNT_ALREADY_VERIFIED', 400, 2032, 'Your account is already verified.', 1, 1496645339, 1496645339),
(25, 'FP_ACCOUNT_NOT_FOUND', 404, 2071, 'Could not find account with given details.', 1, 1496645339, 1496645339),
(26, 'BAD_REQUEST', 400, 1021, 'Bad request. Requested URL is invalid.', 1, 1496645339, 1496645339),
(27, 'INVALID_FP_RESET_CODE', 400, 2072, 'Reset password url is not valid.', 1, 1496645339, 1496645339),
(28, 'FP_RESET_CODE_EXPIRE', 400, 2073, 'Reset password link is expired. Please request for another reset password link.', 1, 1496645339, 1496645339),
(29, 'INVALID_PREFERENCE_UPDATE', 400, 2091, 'Trying to update invalid setting which is not supported.', 1, 1496645339, 1496645339),
(30, 'INVALID_CURRENT_PASS', 400, 2095, 'Current password is incorrect. Please enter your correct current password.', 1, 1496645339, 1496645339),
(31, 'ERROR_IMAGE_UPLOAD', 500, 1022, 'Error while uploading image: ', 1, 1496645339, 1496645339),
(32, 'INVALID_CONNECTION_METHOD', 400, 2101, 'Incorrect value passed for connection method.', 1, 1496645339, 1496645339),
(33, 'INVALID_STATUS_VALUE', 400, 1023, 'Incorrect value passed for status change.', 1, 1496645339, 1496645339),
(34, 'USER_NOT_FOUND', 400, 2033, 'User not found with given email address.', 1, 1496645339, 1496645339),
(35, 'INVALID_SOCIAL_ACCOUNT', 400, 2002, 'Invalid value passed for social media account.', 1, 1496645339, 1496645339),
(36, 'UP_UPDATE_NOT_ALLOWED', 400, 2096, 'Username and password are already assigned to this account.', 1, 1496645339, 1496645339),
(37, 'USERNAME_ALREADY_TAKEN', 400, 2097, 'This username is already taken. Please enter different username.', 1, 1496645339, 1496645339),
(38, 'INVALID_SOCIAL_TOKEN', 400, 2003, 'Token is invalid. Please try again later.', 1, 1496645339, 1496645339),
(39, 'INVALID_INVITATION_CODE', 400, 2034, 'Invitation code is not valid.', 1, 1496645339, 1496645339),
(40, 'INVITE_ALREADY_ACCEPTED', 400, 2035, 'Invitation already accepted.', 1, 1496645339, 1496645339),
(41, 'SHARED_EDIT_NA', 400, 1024, 'You are not allowed to edit this shared resource.', 1, 1496645339, 1496645339),
(42, 'SHARED_DELETE_NA', 400, 1025, 'You are not allowed to delete this shared resource.', 1, 1496645339, 1496645339),
(43, 'INVALID_AUTH_TOKEN', 401, 1026, 'Invalid authentication token. Please login to your account.', 1, 1496645339, 1496645339),
(44, 'INVALID_DELETE_REQUEST', 400, 2141, 'Campaign with In Progress status can not be deleted.', 1, 1496645339, 1496645339),
(45, 'SYSTEM_ROLE', 400, 2341, 'This is a system role and cannot be deleted.', 1, 0, 0),
(46, 'ROLE_ASSIGNED_TO_USER', 400, 2342, 'This role is assigned to user and cannot be deleted.', 1, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `app_constant_vars`
--

CREATE TABLE `app_constant_vars` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `code` varchar(25) CHARACTER SET utf8 NOT NULL,
  `name` varchar(50) CHARACTER SET utf8 NOT NULL,
  `val` varchar(50) CHARACTER SET utf8 NOT NULL,
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `app_constant_vars`
--

INSERT INTO `app_constant_vars` (`id`, `code`, `name`, `val`, `created`, `modified`) VALUES
(1, 'STATUS_INACTIVE', 'Inactive', '0', 1496645339, 1496645339),
(2, 'STATUS_ACTIVE', 'Active', '1', 1496645339, 1496645339),
(3, 'STATUS_DELETE', 'Deleted', '2', 1496645339, 1496645339),
(4, 'FLAG_YES', 'Yes', '1', 1496645339, 1496645339),
(5, 'FLAG_NO', 'No', '0', 1496645339, 1496645339),
(6, 'STATUS_BLOCKED', 'Blocked', '3', 1496645339, 1496645339),
(7, 'FOLDER_TYPE_TEMPLATE', 'Template Folders', '1', 1496645339, 1496645339),
(8, 'FOLDER_TYPE_DOCS', 'Document Folders', '2', 1496645339, 1496645339),
(9, 'U_SIGNATURE', 'Signature', '9', 1496645339, 1496645339),
(10, 'U_TRACK_EMAILS', 'Track Emails', '10', 1496645339, 1496645339),
(11, 'U_TRACK_REPLY', 'Reply Tracking', '11', 1496645339, 1496645339),
(12, 'U_TRACK_CLICKS', 'Link Tracking', '12', 1496645339, 1496645339),
(13, 'U_POWERED_BY_SH', 'Powered By Saleshandy', '13', 1496645339, 1496645339),
(14, 'U_BCC', 'Default BCC', '14', 1496645339, 1496645339),
(15, 'U_CC', 'Default CC', '15', 1496645339, 1496645339),
(16, 'U_TIMEZONE', 'Timezone', '16', 1496645339, 1496645339),
(17, 'U_SNOOZE_NOTIFICATIONS', 'Snooze All Notifications', '17', 1496645339, 1496645339),
(18, 'DISCOUNT_TYPE_PER', 'Percentage', 'PER', 1496645339, 1496645339),
(19, 'DISCOUNT_TYPE_AMT', 'Fix Amount', 'AMT', 1496645339, 1496645339),
(20, 'STATUS_SUCCESS', 'Success', '1', 1496645339, 1496645339),
(21, 'STATUS_FAIL', 'Fail', '2', 1496645339, 1496645339),
(22, 'STATUS_PENDING', 'Pending', '0', 1496645339, 1496645339),
(23, 'PAYMENT_SUBSCRIPTION', 'Plan Subscribed', '1', 1496645339, 1496645339),
(24, 'PAYMENT_RECURRING', 'Recurring', '2', 1496645339, 1496645339),
(25, 'PAYMENT_TEAM_INCREASE', 'Team Increase', '3', 1496645339, 1496645339),
(26, 'PAYMENT_UPGRADE', 'Plan Upgrade', '4', 1496645339, 1496645339),
(27, 'STATUS_FRAUD', 'Fraud', '3', 1496645339, 1496645339),
(28, 'PROGRESS_SCHEDULED', 'Scheduled', '0', 1496645339, 1496645339),
(29, 'PROGRESS_SENT', 'Sent', '1', 1496645339, 1496645339),
(30, 'PROGRESS_FAILED', 'Failed', '2', 1496645339, 1496645339),
(31, 'EMAIL_TRACK_OPEN', 'Email Open', '1', 1496645339, 1496645339),
(32, 'EMAIL_TRACK_REPLY', 'Email Reply', '2', 1496645339, 1496645339),
(33, 'EMAIL_TRACK_CLICK', 'Email Link Click', '3', 1496645339, 1496645339),
(34, 'CAMP_PROGRESS_SCHEDULED', 'Scheduled', '0', 1496645339, 1496645339),
(35, 'CAMP_PROGRESS_QUEUED', 'Queued', '1', 1496645339, 1496645339),
(36, 'CAMP_PROGRESS_IN_PROGRESS', 'In Progress', '2', 1496645339, 1496645339),
(37, 'CAMP_PROGRESS_PAUSED', 'Paused', '3', 1496645339, 1496645339),
(38, 'CAMP_PROGRESS_WAITING', 'Waiting', '4', 1496645339, 1496645339),
(39, 'CAMP_PROGRESS_HALT', 'Halted', '5', 1496645339, 1496645339),
(40, 'CAMP_PROGRESS_FINISH', 'Finished', '6', 1496645339, 1496645339),
(41, 'STATUS_DRAFT', 'Draft', '0', 1496645339, 1496645339),
(42, 'PRIORITY_LOW', 'Low', '0', 1496645339, 1496645339),
(43, 'PRIORITY_MEDIUM', 'Medium', '1', 1496645339, 1496645339),
(44, 'PRIORITY_HIGH', 'High', '2', 1496645339, 1496645339),
(45, 'CAMP_REPORT_NOT_SENT', 'Report Not Sent', '0', 1496645339, 1496645339),
(46, 'CAMP_REPORT_SENT', 'Report Sent', '1', 1496645339, 1496645339),
(47, 'CAMP_STATS_REPORT_SENT', 'Performance Report Sent', '2', 1496645339, 1496645339),
(48, 'LOG_TYPE_INFO', 'Info', '0', 1496645339, 1496645339),
(49, 'LOG_TYPE_WARNING', 'Warning', '1', 1496645339, 1496645339),
(50, 'LOG_TYPE_ERROR', 'Error', '2', 1496645339, 1496645339),
(51, 'SEQ_PROGRESS_SCHEDULED', 'Scheduled', '0', 1496645339, 1496645339),
(52, 'SEQ_PROGRESS_SENT', 'Sent', '1', 1496645339, 1496645339),
(53, 'SEQ_PROGRESS_FAILED', 'Failed', '2', 1496645339, 1496645339),
(54, 'DEFAULT_LIST_PER_PAGE', 'Per Page', '25', 1496645339, 1496645339),
(55, 'STATUS_REMOVED', 'Removed', '5', 1496645339, 1496645339),
(56, 'STATUS_DRAFT', 'Draft', '0', 1496645339, 1496645339),
(57, 'STATUS_IN_PROCESS', 'In Processing', '3', 1496645339, 1496645339),
(58, 'STATUS_FAILED_PROCESS', 'Processing Failed', '4', 1496645339, 1496645339);

-- --------------------------------------------------------

--
-- Table structure for table `branding`
--

CREATE TABLE `branding` (
  `id` mediumint(8) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `subdomain` varchar(50) CHARACTER SET utf8 NOT NULL,
  `custom_url` varchar(100) CHARACTER SET utf8 NOT NULL,
  `branding_payload` text CHARACTER SET utf8 NOT NULL,
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `campaign_links`
--

CREATE TABLE `campaign_links` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `campaign_id` int(10) UNSIGNED DEFAULT NULL,
  `campaign_stage_id` bigint(20) UNSIGNED DEFAULT NULL,
  `campaign_sequence_id` bigint(20) UNSIGNED DEFAULT NULL,
  `account_link_id` bigint(20) UNSIGNED DEFAULT NULL,
  `redirect_key` varchar(25) CHARACTER SET utf8 NOT NULL,
  `total_clicked` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `last_clicked` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Latest url clicked date time (GMT+00:00 Timestamp)',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `campaign_logs`
--

CREATE TABLE `campaign_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `campaign_id` int(10) UNSIGNED DEFAULT NULL,
  `campaign_stage_id` bigint(20) UNSIGNED DEFAULT NULL,
  `campaign_sequence_id` bigint(20) UNSIGNED DEFAULT NULL,
  `log` varchar(512) CHARACTER SET utf8 NOT NULL,
  `log_type` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Info, 2-Warning, 3-Error',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `campaign_master`
--

CREATE TABLE `campaign_master` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8 NOT NULL,
  `account_sending_method_id` int(10) UNSIGNED DEFAULT NULL,
  `source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `total_stages` tinyint(2) UNSIGNED NOT NULL DEFAULT '1',
  `from_date` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Campaign start date time (GMT+00:00 Timestamp)',
  `to_date` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Campaign end date time (GMT+00:00 Timestamp)',
  `timezone` varchar(25) CHARACTER SET utf8 NOT NULL DEFAULT 'GMT+05:30',
  `other_data` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '{}' COMMENT 'Campaign settings',
  `status_message` varchar(255) CHARACTER SET utf8 NOT NULL COMMENT 'Message describing status change of campaign',
  `track_reply` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-No, 1-Yes',
  `track_click` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-No, 1-Yes',
  `send_as_reply` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-No, 1-Yes',
  `overall_progress` tinyint(2) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-Scheduled, 1-Queued, 2-In Progress, 3-Paused, 4-Waiting, 5-Hault, 6-Finish',
  `priority` tinyint(2) NOT NULL DEFAULT '1' COMMENT '0-Low, 1-Medium, 2-High',
  `snooze_notifications` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Draft, 1-Active, 2-Deleted',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `campaign_sequences`
--

CREATE TABLE `campaign_sequences` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `campaign_id` int(10) UNSIGNED DEFAULT NULL,
  `campaign_stage_id` bigint(20) UNSIGNED DEFAULT NULL,
  `account_contact_id` int(10) UNSIGNED DEFAULT NULL,
  `csv_payload` varchar(512) CHARACTER SET utf8 NOT NULL,
  `progress` tinyint(2) NOT NULL DEFAULT '0' COMMENT '0-Queued, 1-Sent, 2-Failed',
  `is_bounce` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `scheduled_at` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Mail schedule date time (GMT+00:00 Timestamp)',
  `sent_at` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Mail sent date time (GMT+00:00 Timestamp)',
  `message_send_id` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'Id of message sent (received from mail server)',
  `sent_response` varchar(512) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'Mail sending attempt response from mail server',
  `locked` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `locked_date` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Last queue process date time (GMT+00:00 Timestamp)',
  `open_count` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `last_opened` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Last date time of mail open (GMT+00:00 Timestamp)',
  `replied` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `last_replied` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '	Replied date time (GMT+00:00 Timestamp)',
  `reply_check_count` smallint(5) NOT NULL DEFAULT '0' COMMENT 'How many times reply has been checked',
  `reply_last_checked` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Last date time when reply was checked (GMT+00:00 Timestamp)',
  `click_count` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `last_clicked` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Last clicked date time (GMT+00:00 Timestamp)',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Deleted',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `campaign_stages`
--

CREATE TABLE `campaign_stages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `campaign_id` int(10) UNSIGNED DEFAULT NULL,
  `subject` varchar(512) CHARACTER SET utf8 NOT NULL,
  `content` text CHARACTER SET utf8 NOT NULL,
  `account_template_id` int(10) UNSIGNED DEFAULT NULL,
  `stage` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT 'Stage number of campaign',
  `stage_defination` varchar(512) CHARACTER SET utf8 NOT NULL DEFAULT '{}' COMMENT 'Stage related definations',
  `scheduled_on` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Stage execution date time (GMT+00:00 Timestamp)',
  `progress` tinyint(2) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-Scheduled, 1-Queued, 2-In Progress, 3-Paused, 4-Waiting, 5-Hault, 6-Finish',
  `total_contacts` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Total recipients',
  `total_success` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Total recipients successfully message sent',
  `total_fail` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Total recipients message failed',
  `total_deleted` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Total recipients removed',
  `started_on` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Stage started at date time (GMT+00:00 Timestamp)',
  `finished_on` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Stage ended at date time (GMT+00:00 Timestamp)',
  `report_sent` tinyint(2) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-Not Sent, 1-Stage Finish Report Sent, 2-Stage Performance Report Sent',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Deleted',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `campaign_track_history`
--

CREATE TABLE `campaign_track_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `campaign_sequence_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Mail Open, 2-Mail Reply, 3-Link Click',
  `acted_at` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Action date time (GMT+00:00 Timestamp)',
  `account_link_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `coupon_master`
--

CREATE TABLE `coupon_master` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `code` varchar(25) CHARACTER SET utf8 NOT NULL,
  `valid_from` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Coupon valid from date (GMT+00:00 Timestamp)',
  `valid_to` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Coupon valid till date (GMT+00:00 Timestamp)',
  `min_amount` decimal(10,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT 'Minimum order amount to apply coupon code',
  `max_amount` decimal(10,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT 'Maximum amount to apply coupon (0 if no limit)',
  `discount_type` varchar(10) CHARACTER SET utf8 NOT NULL DEFAULT 'AMT' COMMENT 'AMT-Fix Amount, PER-Percentage of Amount',
  `discount_value` decimal(10,2) UNSIGNED NOT NULL DEFAULT '0.00',
  `currency` varchar(5) CHARACTER SET utf8 NOT NULL DEFAULT 'USD',
  `short_info` varchar(255) CHARACTER SET utf8 NOT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active, 2-Deleted',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `document_folders`
--

CREATE TABLE `document_folders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `document_id` bigint(20) UNSIGNED DEFAULT NULL,
  `account_folder_id` int(10) UNSIGNED DEFAULT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Deleted',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `document_links`
--

CREATE TABLE `document_links` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `account_company_id` int(10) UNSIGNED DEFAULT NULL,
  `account_contact_id` int(10) DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8 NOT NULL,
  `short_description` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `link_domain` varchar(100) CHARACTER SET utf8 NOT NULL,
  `link_code` varchar(25) CHARACTER SET utf8 NOT NULL,
  `type` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Document, 2-Folder',
  `is_set_expiration_date` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `expires_at` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Link expires at date time (GMT+00:00 Timestamp)',
  `allow_download` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `password_protected` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `access_password` varchar(25) CHARACTER SET utf8 NOT NULL COMMENT 'Password to access file',
  `ask_visitor_info` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-No, 1-Yes',
  `visitor_info_payload` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '{}' COMMENT 'Information to be asked',
  `snooze_notifications` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `remind_not_viewed` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `remind_at` int(10) NOT NULL DEFAULT '0' COMMENT 'Reminds at date time (GMT+00:00 Timestamp)',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active, 2-Deleted, 3-Blocked',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `document_link_files`
--

CREATE TABLE `document_link_files` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `document_link_id` bigint(20) UNSIGNED DEFAULT NULL,
  `document_id` bigint(20) UNSIGNED DEFAULT NULL,
  `account_folder_id` int(10) UNSIGNED DEFAULT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Deleted',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `document_master`
--

CREATE TABLE `document_master` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `document_source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `file_path` varchar(150) CHARACTER SET utf8 NOT NULL,
  `file_type` varchar(5) NOT NULL,
  `file_pages` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `source_document_id` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'ID of document if imported from any source',
  `source_document_link` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'Link of document if imported from any source',
  `public` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `share_access` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '{}' COMMENT 'Shared users access rights',
  `snooze_notifications` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `locked` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active, 2-Deleted, 3-In Process, 4-Failed Processing',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `document_source_master`
--

CREATE TABLE `document_source_master` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(25) CHARACTER SET utf8 NOT NULL,
  `name` varchar(50) CHARACTER SET utf8 NOT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `document_source_master`
--

INSERT INTO `document_source_master` (`id`, `code`, `name`, `status`, `created`, `modified`) VALUES
(1, 'UPLOAD', 'Uploaded from Client', 1, 1496645339, 1496645339),
(2, 'DROPBOX', 'Imported from Dropbox', 1, 1496645339, 1496645339),
(3, 'GDRIVE', 'Imported from Google Drive', 1, 1496645339, 1496645339);

-- --------------------------------------------------------

--
-- Table structure for table `document_teams`
--

CREATE TABLE `document_teams` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `document_id` bigint(20) UNSIGNED DEFAULT NULL,
  `account_team_id` int(10) UNSIGNED DEFAULT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Deleted',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `email_links`
--

CREATE TABLE `email_links` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `email_id` bigint(20) UNSIGNED DEFAULT NULL,
  `account_contact_id` int(10) UNSIGNED DEFAULT NULL,
  `account_link_id` bigint(20) UNSIGNED DEFAULT NULL,
  `redirect_key` varchar(25) CHARACTER SET utf8 NOT NULL,
  `total_clicked` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `last_clicked` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Latest url clicked date time (GMT+00:00 Timestamp)',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `email_master`
--

CREATE TABLE `email_master` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `account_template_id` int(10) UNSIGNED DEFAULT NULL,
  `account_sending_method_id` int(10) UNSIGNED DEFAULT NULL,
  `source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `subject` varchar(512) CHARACTER SET utf8 NOT NULL,
  `content` text CHARACTER SET utf8 NOT NULL,
  `is_scheduled` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `scheduled_at` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Schedule date time (GMT+00:00 Timestamp)',
  `timezone` varchar(25) CHARACTER SET utf8 NOT NULL DEFAULT 'GMT+05:30',
  `sent_at` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Sent date time (GMT+00:00 Timestamp)',
  `track_reply` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-No, 1-Yes',
  `track_click` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-No, 1-Yes',
  `cc` varchar(512) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `bcc` varchar(512) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `total_recipients` tinyint(3) UNSIGNED NOT NULL DEFAULT '1' COMMENT 'Total number of recipients of email',
  `snooze_notifications` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `progress` tinyint(2) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-Scheduled, 1-Sent, 2-Fail',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Draft, 1-Active, 2-Deleted',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `email_recipients`
--

CREATE TABLE `email_recipients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `email_id` bigint(20) UNSIGNED DEFAULT NULL,
  `account_contact_id` int(10) UNSIGNED DEFAULT NULL,
  `open_count` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `last_opened` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Last date time of mail open (GMT+00:00 Timestamp)',
  `replied` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `replied_at` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Replied date time (GMT+00:00 Timestamp)',
  `click_count` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `last_clicked` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Last clicked date time (GMT+00:00 Timestamp)',
  `sent_message_id` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'Id of message sent (received from mail server)',
  `sent_response` varchar(512) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'Mail sending attempt response from mail server',
  `is_bounce` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `progress` tinyint(2) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-Scheduled, 1-Sent, 2-Fail',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Deleted',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `email_sending_method_master`
--

CREATE TABLE `email_sending_method_master` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(25) CHARACTER SET utf8 NOT NULL,
  `name` varchar(50) CHARACTER SET utf8 NOT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `email_sending_method_master`
--

INSERT INTO `email_sending_method_master` (`id`, `code`, `name`, `status`, `created`, `modified`) VALUES
(1, 'GMAIL', 'Gmail Account', 1, 1496645339, 1496645339),
(2, 'SMTP', 'SMTP Method', 1, 1496645339, 1496645339);

-- --------------------------------------------------------

--
-- Table structure for table `email_track_history`
--

CREATE TABLE `email_track_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email_recipient_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Mail Open, 2-Mail Reply, 3-Link Click',
  `acted_at` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Action date time (GMT+00:00 Timestamp)',
  `account_link_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `payment_method_master`
--

CREATE TABLE `payment_method_master` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(25) CHARACTER SET utf8 NOT NULL,
  `name` varchar(50) CHARACTER SET utf8 NOT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `payment_method_master`
--

INSERT INTO `payment_method_master` (`id`, `code`, `name`, `status`, `created`, `modified`) VALUES
(1, '2CO', '2Checkout', 1, 1496645339, 1496645339),
(2, 'STRIPE', 'Stripe', 1, 1496645339, 1496645339);

-- --------------------------------------------------------

--
-- Table structure for table `plan_master`
--

CREATE TABLE `plan_master` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(25) CHARACTER SET utf8 NOT NULL,
  `name` varchar(50) CHARACTER SET utf8 NOT NULL,
  `amount` decimal(10,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT 'Plan price per user',
  `currency` varchar(5) CHARACTER SET utf8 NOT NULL DEFAULT 'USD',
  `mode` tinyint(3) NOT NULL DEFAULT '1' COMMENT '0-Unlimited, 1-Monthly, 3-3 Months, 6-6 Months, 12-Yearly',
  `validity_in_days` smallint(5) NOT NULL DEFAULT '0' COMMENT '0-Unlimited Days',
  `configuration` varchar(1024) CHARACTER SET utf8 NOT NULL DEFAULT '{}' COMMENT 'JSON data to store plan technical configurations',
  `short_info` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `description` text CHARACTER SET utf8 NOT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `plan_master`
--

INSERT INTO `plan_master` (`id`, `code`, `name`, `amount`, `currency`, `mode`, `validity_in_days`, `configuration`, `short_info`, `description`, `status`, `created`, `modified`) VALUES
(1, 'FREE', 'Free Plan', '0.00', 'USD', 0, 0, '{}', 'Free Plan', 'Free Plan', 1, 1496645339, 1496645339),
(2, 'REGULAR_MONTHLY', 'Regular Monthly', '9.00', 'USD', 1, 30, '{}', 'Regular Monthly', 'Regular Monthly', 1, 1496645339, 1496645339),
(3, 'PLUS_MONTHLY', 'Plus Monthly', '20.00', 'USD', 1, 30, '{}', 'Plus Monthly', 'Plus Monthly', 1, 1496645339, 1496645339),
(4, 'ENTERPRISE_MONTHLY', 'Enterprise Monthly', '50.00', 'USD', 1, 30, '{}', 'Enterprise Monthly', 'Enterprise Monthly', 1, 1496645339, 1496645339),
(5, 'REGULAR_MONTHLY_TRIAL', 'Regular Monthly Trial', '0.00', 'USD', 1, 14, '{}', 'Regular Monthly Trial', 'Regular Monthly Trial', 1, 1496645339, 1496645339),
(6, 'PLUS_MONTHLY_TRIAL', 'Plus Monthly Trial', '0.00', 'USD', 1, 14, '{}', 'Plus Monthly Trial', 'Plus Monthly Trial', 1, 1496645339, 1496645339),
(7, 'ENTERPRISE_MONTHLY_TRIAL', 'Enterprise Monthly Trial', '0.00', 'USD', 1, 14, '{}', 'Enterprise Monthly Trial', 'Enterprise Monthly Trial', 1, 1496645339, 1496645339),
(8, 'REGULAR_YEARLY', 'Regular Yearly', '7.00', 'USD', 12, 365, '{}', 'Regular Yearly', 'Regular Yearly', 1, 1496645339, 1496645339),
(9, 'PLUS_YEARLY', 'Plus Yearly', '16.00', 'USD', 12, 365, '{}', 'Plus Yearly', 'Plus Yearly', 1, 1496645339, 1496645339),
(10, 'ENTERPRISE_YEARLY', 'Enterprise Yearly', '40.00', 'USD', 12, 365, '{}', 'Enterprise Yearly', 'Enterprise Yearly', 1, 1496645339, 1496645339),
(11, 'REGULAR_YEARLY_TRIAL', 'Regular Yearly Trial', '0.00', 'USD', 12, 14, '{}', 'Regular Yearly Trial', 'Regular Yearly Trial', 1, 1496645339, 1496645339),
(12, 'PLUS_YEARLY_TRIAL', 'Plus Yearly Trial', '0.00', 'USD', 12, 14, '{}', 'Plus Yearly Trial', 'Plus Yearly Trial', 1, 1496645339, 1496645339),
(13, 'ENTERPRISE_YEARLY_TRIAL', 'Enterprise Yearly Trial', '0.00', 'USD', 12, 14, '{}', 'Enterprise Yearly Trial', 'Enterprise Yearly Trial', 1, 1496645339, 1496645339);

-- --------------------------------------------------------

--
-- Table structure for table `resource_master`
--

CREATE TABLE `resource_master` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `resource_name` varchar(50) CHARACTER SET utf8 NOT NULL,
  `short_info` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `api_endpoint` varchar(512) CHARACTER SET utf8 DEFAULT NULL COMMENT 'API resource name',
  `parent_id` smallint(5) UNSIGNED DEFAULT NULL,
  `position` tinyint(3) UNSIGNED NOT NULL DEFAULT '1' COMMENT 'Position in which to display the records',
  `show_in_roles` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-No, 1-Yes',
  `show_in_webhooks` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-No, 1-Yes',
  `is_always_assigned` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes (Whether this resource is default assigned or not)',
  `is_secured` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT 'Requires login?',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `resource_master`
--

INSERT INTO `resource_master` (`id`, `resource_name`, `short_info`, `api_endpoint`, `parent_id`, `position`, `show_in_roles`, `show_in_webhooks`, `is_always_assigned`, `is_secured`, `status`, `created`, `modified`) VALUES
(1, 'Login', 'Access to login to the SalesHandy account.', '[\"/user/login\", \"/user/loginwith/{method}\", \"/user/login-connect/{method}\"]', NULL, 1, 0, 0, 1, 0, 1, 1496645339, 1496645339),
(2, 'Signup', 'Access to signup for the SalesHandy account.', '[\"/user/signup\", \"/user/verify/{code}\", \"/user/resend-verification/{code}\", \"/user/resend-verification\", \"/me/resend-verification\", \"/user/check-invite/{code}\", \"/user/accept-invite/{code}\", \"/user/account-exists/{email}\"]', NULL, 2, 0, 0, 1, 0, 1, 1496645339, 1496645339),
(3, 'Forgot Password', 'Access to reset password for the SalesHandy account.', '[\"/user/forgot-password\", \"/user/resend-password-reset/{code}\", \"/user/valid-code/{code}\", \"/user/reset-password/{code}\"]', NULL, 3, 0, 0, 1, 0, 1, 1496645339, 1496645339),
(4, 'Dashboard', 'Access to dashboard section.', '[\"/dashboard\"]', NULL, 4, 0, 0, 1, 1, 1, 1496645339, 1496645339),
(5, 'My Profile', 'Access to user profile and preferences section.', '[\"/me\", \"/me/update-profile\", \"/me/change-password\", \"/me/update-preference\", \"/me/logout\"]', NULL, 5, 0, 0, 1, 1, 1, 1496645339, 1496645339),
(6, 'Feed', 'Access to user activity feed section.', '[\"/me/feed\"]', NULL, 6, 0, 0, 1, 1, 1, 1496645339, 1496645339),
(7, 'Email Accounts', 'Access to email accounts section.', NULL, NULL, 11, 1, 1, 0, 1, 1, 1496645339, 1496645339),
(8, 'Emails', 'Access to emails section.', NULL, NULL, 12, 1, 1, 0, 1, 1, 1496645339, 1496645339),
(9, 'Campaigns', 'Access to campaigns section.', NULL, NULL, 13, 1, 1, 0, 1, 1, 1496645339, 1496645339),
(10, 'Documents', 'Access to documents section.', NULL, NULL, 14, 1, 1, 0, 1, 1, 1496645339, 1496645339),
(11, 'Folders', 'Access to folders section.', NULL, NULL, 15, 1, 1, 0, 1, 1, 1496645339, 1496645339),
(12, 'Links', 'Access to links section.', NULL, NULL, 16, 1, 1, 0, 1, 1, 1496645339, 1496645339),
(13, 'Templates', 'Access to templates section.', NULL, NULL, 17, 1, 1, 0, 1, 1, 1496645339, 1496645339),
(14, 'Contacts', 'Access to contacts section.', NULL, NULL, 18, 1, 1, 0, 1, 1, 1496645339, 1496645339),
(15, 'Company', 'Access to company section.', NULL, NULL, 19, 1, 1, 0, 1, 1, 1496645339, 1496645339),
(16, 'Teams', 'Access to teams section.', NULL, NULL, 20, 1, 1, 0, 1, 1, 1496645339, 1496645339),
(17, 'Members', 'Access to members section.', NULL, NULL, 21, 1, 1, 0, 1, 1, 1496645339, 1496645339),
(18, 'Roles', 'Access to roles section.', NULL, NULL, 22, 1, 1, 0, 1, 1, 1496645339, 1496645339),
(19, 'Accounts & Billing', 'Access to accounts and billing section.', NULL, NULL, 23, 1, 1, 0, 1, 1, 1496645339, 1496645339),
(20, 'Organisation', 'Access to account organization section.', NULL, NULL, 24, 1, 1, 0, 1, 1, 1496645339, 1496645339),
(21, 'Branding', 'Access to account branding section.', NULL, NULL, 25, 1, 1, 0, 1, 1, 1496645339, 1496645339),
(22, 'Webhooks', 'Access to webhooks section.', NULL, NULL, 26, 1, 1, 0, 1, 1, 1496645339, 1496645339),
(23, 'Reports', 'Access to reports section.', NULL, NULL, 27, 1, 1, 0, 1, 1, 1496645339, 1496645339),
(24, 'Email Accounts / View', 'Access to view email accounts data.', '[\"/mail-accounts/list\", \"/mail-accounts/{id}/view\"]', 7, 1, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(25, 'Email Accounts / Create', 'Access to create email accounts.', '[\"/mail-accounts/create\", \"/mail-accounts/{id}/copy\", \"/mail-accounts/connect\", \"/mail-accounts/connect-verify\"]', 7, 2, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(26, 'Email Accounts / Update', 'Access to update email accounts data.', '[\"/mail-accounts/{id}/update\", \"/mail-accounts/{id}/mark-as-public\", \"/mail-accounts/{id}/status-update\"]', 7, 3, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(27, 'Email Accounts / Delete', 'Access to delete email accounts.', '[\"/mail-accounts/{id}/delete\"]', 7, 4, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(28, 'Emails / View', 'Access to view emails data.', '[\"/emails/list\", \"/emails/{id}/view\"]', 8, 1, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(29, 'Emails / Create', 'Access to send emails.', '[\"/emails/create\", \"/emails/{id}/copy\"]', 8, 2, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(30, 'Emails / Update', 'Access to update emails data.', '[\"/emails/{id}/update\", \"/emails/{id}/snooze\"]', 8, 3, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(31, 'Emails / Delete', 'Access to delete emails.', '[\"/emails/{id}/delete\"]', 8, 4, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(32, 'Campaigns / View', 'Access to view campaigns data.', '[\"/campaigns/list\", \"/campaigns/{id}/view\", \"/campaigns/{id}/view-stage/{stage_id}\", \"/campaigns/{id}/sequence-mail/{seq_id}\", \"/campaigns/{id}/export-data/{stage_id}\"]', 9, 1, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(33, 'Campaigns / Create', 'Access to run campaigns.', '[\"/campaigns/create\", \"/campaigns/{id}/copy\"]', 9, 2, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(34, 'Campaigns / Update', 'Access to update campaigns data.', '[\"/campaigns/{id}/update\", \"/campaigns/{id}/snooze\", \"/campaigns/{id}/status-update\", \"/campaigns/{id}/sequence-delete/{seq_id}\"]', 9, 3, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(35, 'Campaigns / Delete', 'Access to delete campaigns.', '[\"/campaigns/{id}/delete\"]', 9, 4, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(36, 'Documents / View', 'Access to view documents data.', '[\"/documents/list\", \"/documents/{id}/view\"]', 10, 1, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(37, 'Documents / Create', 'Access to upload documents.', '[\"/documents/create\", \"/documents/{id}/{folder_id}/copy\"]', 10, 2, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(38, 'Documents / Update', 'Access to update documents data.', '[\"/documents/{id}/update\", \"/documents/{id}/mark-as-public\", \"/documents/{id}/status-update\", \"/documents/{id}/move\"]', 10, 3, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(39, 'Documents / Delete', 'Access to delete documents.', '[\"/documents/{id}/delete\"]', 10, 4, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(40, 'Documents / Share', 'Access to share documents.', '[\"/documents/{id}/share\"]', 10, 5, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(41, 'Folders / View', 'Access to view folders data.', '[\"/templates/folders/list\", \"/templates/folders/{id}/view\", \"/documents/folders/list\", \"/documents/folders/{id}/view\"]', 11, 1, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(42, 'Folders / Create', 'Access to create new folders.', '[\"/templates/folders/create\", \"/documents/folders/create\"]', 11, 2, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(43, 'Folders / Update', 'Access to update folders data.', '[\"/templates/folders/{id}/update\", \"/documents/folders/{id}/update\"]', 11, 3, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(44, 'Folders / Delete', 'Access to delete folders.', '[\"/templates/folders/{id}/delete\", \"/documents/folders/{id}/delete\"]', 11, 4, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(45, 'Folders / Share', 'Access to share folders.', '[\"/templates/folders/{id}/share\", \"/documents/folders/{id}/share\"]', 11, 5, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(46, 'Links / View', 'Access to view links.', '[\"/links/list\", \"/links/{id}/view\"]', 12, 1, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(47, 'Links / Create', 'Access to create links.', '[\"/links/create\", \"/links/{id}/copy\"]', 12, 2, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(48, 'Links / Update', 'Access to update links.', '[\"/links/{id}/update\", \"/links/{id}/status-update\"]', 12, 3, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(49, 'Links / Delete', 'Access to delete links.', '[\"/links/{id}/delete\"]', 12, 4, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(50, 'Templates / View', 'Access to view templates data.', '[\"/templates/list\", \"/templates/{id}/view\"]', 13, 1, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(51, 'Templates / Create', 'Access to create new templates.', '[\"/templates/create\", \"/templates/{id}/{folder_id}/copy\"]', 13, 2, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(52, 'Templates / Update', 'Access to update templates data.', '[\"/templates/{id}/update\", \"/templates/{id}/status-update\", \"/templates/{id}/move\"]', 13, 3, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(53, 'Templates / Delete', 'Access to delete templates.', '[\"/templates/{id}/delete\"]', 13, 4, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(54, 'Templates / Share', 'Access to share templates.', '[\"/templates/{id}/share\"]', 13, 5, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(55, 'Contacts / View', 'Access to view contacts data.', '[\"/contacts/list\", \"/contacts/{id}/view\", \"/contacts/{id}/feed\"]', 14, 1, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(56, 'Contacts / Create', 'Access to create contacts.', '[\"/contacts/create\", \"/contacts/{id}/copy\"]', 14, 2, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(57, 'Contacts / Update', 'Access to update contacts data.', '[\"/contacts/{id}/update\", \"/contacts/{id}/status-update\"]', 14, 3, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(58, 'Contacts / Delete', 'Access to delete contacts.', '[\"/contacts/{id}/delete\"]', 14, 4, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(59, 'Company / View', 'Access to view company data.', '[\"/company/list\", \"/company/{id}/view\", \"/company/{id}/feed\", \"/company/{id}/contacts\"]', 15, 1, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(60, 'Company / Create', 'Access to create new company.', '[\"/company/create\", \"/company/{id}/copy\"]', 15, 2, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(61, 'Company / Update', 'Access to update company details.', '[\"/company/{id}/update\", \"/company/{id}/status-update\"]', 15, 3, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(62, 'Company / Delete', 'Access to delete company.', '[\"/company/{id}/delete\"]', 15, 4, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(63, 'Teams / View', 'Access to view teams data.', '[\"/teams/list\", \"/teams/{id}/view\"]', 16, 1, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(64, 'Teams / Create', 'Access to create new team.', '[\"/teams/create\", \"/teams/{id}/copy\"]', 16, 2, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(65, 'Teams / Update', 'Access to update teams data.', '[\"/teams/{id}/update\"]', 16, 3, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(66, 'Teams / Delete', 'Access to delete teams data.', '[\"/teams/{id}/delete\"]', 16, 4, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(67, 'Members / View', 'Access to view members information.', '[\"/members/list\", \"/members/{id}/view\", \"/members/{id}/resend-invitation\", \"/members/{id}/activity\", \"/members/{id}/resources\"]', 17, 1, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(68, 'Members / Create', 'Access to create or invite new members.', '[\"/members/invite\"]', 17, 2, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(69, 'Members / Update', 'Access to update members information.', '[\"/members/{id}/update\", \"/members/{id}/status-update\"]', 17, 3, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(70, 'Members / Delete', 'Access to delete a member.', '[\"/members/{id}/delete\"]', 17, 4, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(71, 'Roles / View', 'Access to view roles.', '[\"/roles/list\", \"/roles/{id}/view\", \"/roles/resources/list\"]', 18, 1, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(72, 'Roles / Create', 'Access to create new role.', '[\"/roles/create\", \"/roles/{id}/copy\"]', 18, 2, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(73, 'Roles / Update', 'Access to update roles.', '[\"/roles/{id}/update\"]', 18, 3, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(74, 'Roles / Delete', 'Access to delete role.', '[\"/roles/{id}/delete\"]', 18, 4, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(75, 'Accounts / Full', 'Access to billing related actions.', '[\"/account/plan/{code}/details\", \"/account/billing/check-coupon\", \"/account/buy\", \"/account/add-seat\", \"/account/upgrade\", \"/account/downgrade\", \"/account/subscription/cancel\"]', 19, 1, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(76, 'Accounts / View', 'Access to view billing information.', '[\"/account/billing/history\", \"/account/billing/{id}/view\", \"/account/billing/{id}/invoice\"]', 19, 2, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(77, 'Organisation / View', 'Access to view organisation information.', '[\"/account/organisation/get\"]', 20, 1, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(78, 'Organisation / Update', 'Access to update organisation information.', '[\"/account/organisation/update\"]', 20, 2, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(79, 'Branding / View', 'Access to view account branding information.', '[\"/branding/get\"]', 21, 1, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(80, 'Branding / Update', 'Access to update account branding information.', '[\"/branding/update\", \"/branding/status-update\"]', 21, 2, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(81, 'Webhooks / View', 'Access to view webhooks information.', '[\"/web-hooks/list\", \"/web-hooks/{id}/view\"]', 22, 1, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(82, 'Webhooks / Create', 'Access to create new webhooks.', '[\"/web-hooks/create\", \"/web-hooks/{id}/copy\"]', 22, 2, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(83, 'Webhooks / Update', 'Access to update webhooks information.', '[\"/web-hooks/{id}/update\", \"/web-hooks/{id}/status-update\"]', 22, 3, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(84, 'Webhooks / Delete', 'Access to delete webhooks.', '[\"/web-hooks/{id}/delete\"]', 22, 4, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(85, 'Reports / View', 'Access to view reports.', '[\"/reports\"]', 23, 1, 1, 0, 0, 1, 1, 1496645339, 1496645339),
(86, 'List', 'Access to list services.', '[\"/list/my-resources\", \"/list/my-members\", \"/list/timezones\", \"/list/all-members\", \"/list/app-vars\", \"/list/all-roles\", \"/list/all-teams\", \"/list/all-contacts\", \"/list/email-accounts\", \"/list/all-templates\", \"/list/webhook-resources\", \"/list/all-role-resources\", \"/list/role/{id}/resources\", \"/list/my-teams\", \"/list/template-folders\"]', NULL, 28, 0, 0, 1, 1, 1, 1496645339, 1496645339),
(87, 'Get Data', 'Access to get details of resource.', '[\"/templates/{id}/get\"]', NULL, 29, 0, 0, 1, 1, 1, 1496645339, 1496645339);

-- --------------------------------------------------------

--
-- Table structure for table `role_default_resources`
--

CREATE TABLE `role_default_resources` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED DEFAULT NULL,
  `resource_id` smallint(5) UNSIGNED DEFAULT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Deleted',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `role_default_resources`
--

INSERT INTO `role_default_resources` (`id`, `role_id`, `resource_id`, `status`, `modified`) VALUES
(1, 1, 24, 1, 1496645339),
(2, 1, 25, 1, 1496645339),
(3, 1, 26, 1, 1496645339),
(4, 1, 27, 1, 1496645339),
(5, 1, 28, 1, 1496645339),
(6, 1, 29, 1, 1496645339),
(7, 1, 30, 1, 1496645339),
(8, 1, 31, 1, 1496645339),
(9, 1, 32, 1, 1496645339),
(10, 1, 33, 1, 1496645339),
(11, 1, 34, 1, 1496645339),
(12, 1, 35, 1, 1496645339),
(13, 1, 36, 1, 1496645339),
(14, 1, 37, 1, 1496645339),
(15, 1, 38, 1, 1496645339),
(16, 1, 39, 1, 1496645339),
(17, 1, 40, 1, 1496645339),
(18, 1, 41, 1, 1496645339),
(19, 1, 42, 1, 1496645339),
(20, 1, 43, 1, 1496645339),
(21, 1, 44, 1, 1496645339),
(22, 1, 45, 1, 1496645339),
(23, 1, 46, 1, 1496645339),
(24, 1, 47, 1, 1496645339),
(25, 1, 48, 1, 1496645339),
(26, 1, 49, 1, 1496645339),
(27, 1, 50, 1, 1496645339),
(28, 1, 51, 1, 1496645339),
(29, 1, 52, 1, 1496645339),
(30, 1, 53, 1, 1496645339),
(31, 1, 54, 1, 1496645339),
(32, 1, 55, 1, 1496645339),
(33, 1, 56, 1, 1496645339),
(34, 1, 57, 1, 1496645339),
(35, 1, 58, 1, 1496645339),
(36, 1, 59, 1, 1496645339),
(37, 1, 60, 1, 1496645339),
(38, 1, 61, 1, 1496645339),
(39, 1, 62, 1, 1496645339),
(40, 1, 63, 1, 1496645339),
(41, 1, 64, 1, 1496645339),
(42, 1, 65, 1, 1496645339),
(43, 1, 66, 1, 1496645339),
(44, 1, 67, 1, 1496645339),
(45, 1, 68, 1, 1496645339),
(46, 1, 69, 1, 1496645339),
(47, 1, 70, 1, 1496645339),
(48, 1, 71, 1, 1496645339),
(49, 1, 72, 1, 1496645339),
(50, 1, 73, 1, 1496645339),
(51, 1, 74, 1, 1496645339),
(52, 1, 75, 1, 1496645339),
(53, 1, 76, 1, 1496645339),
(54, 1, 77, 1, 1496645339),
(55, 1, 78, 1, 1496645339),
(56, 1, 79, 1, 1496645339),
(57, 1, 80, 1, 1496645339),
(58, 1, 81, 1, 1496645339),
(59, 1, 82, 1, 1496645339),
(60, 1, 83, 1, 1496645339),
(61, 1, 84, 1, 1496645339),
(62, 1, 85, 1, 1496645339),
(63, 2, 24, 1, 1496645339),
(64, 2, 25, 1, 1496645339),
(65, 2, 26, 1, 1496645339),
(66, 2, 27, 1, 1496645339),
(67, 2, 28, 1, 1496645339),
(68, 2, 29, 1, 1496645339),
(69, 2, 30, 1, 1496645339),
(70, 2, 31, 1, 1496645339),
(71, 2, 32, 1, 1496645339),
(72, 2, 33, 1, 1496645339),
(73, 2, 34, 1, 1496645339),
(74, 2, 35, 1, 1496645339),
(75, 2, 36, 1, 1496645339),
(76, 2, 37, 1, 1496645339),
(77, 2, 38, 1, 1496645339),
(78, 2, 39, 1, 1496645339),
(79, 2, 40, 1, 1496645339),
(80, 2, 41, 1, 1496645339),
(81, 2, 42, 1, 1496645339),
(82, 2, 43, 1, 1496645339),
(83, 2, 44, 1, 1496645339),
(84, 2, 45, 1, 1496645339),
(85, 2, 46, 1, 1496645339),
(86, 2, 47, 1, 1496645339),
(87, 2, 48, 1, 1496645339),
(88, 2, 49, 1, 1496645339),
(89, 2, 50, 1, 1496645339),
(90, 2, 51, 1, 1496645339),
(91, 2, 52, 1, 1496645339),
(92, 2, 53, 1, 1496645339),
(93, 2, 54, 1, 1496645339),
(94, 2, 55, 1, 1496645339),
(95, 2, 56, 1, 1496645339),
(96, 2, 57, 1, 1496645339),
(97, 2, 58, 1, 1496645339),
(98, 2, 59, 1, 1496645339),
(99, 2, 60, 1, 1496645339),
(100, 2, 61, 1, 1496645339),
(101, 2, 62, 1, 1496645339),
(102, 2, 63, 1, 1496645339),
(103, 2, 64, 1, 1496645339),
(104, 2, 65, 1, 1496645339),
(105, 2, 66, 1, 1496645339),
(106, 2, 67, 1, 1496645339),
(107, 2, 68, 1, 1496645339),
(108, 2, 69, 1, 1496645339),
(109, 2, 70, 1, 1496645339),
(110, 2, 71, 1, 1496645339),
(111, 2, 72, 1, 1496645339),
(112, 2, 73, 1, 1496645339),
(113, 2, 74, 1, 1496645339),
(114, 2, 75, 1, 1496645339),
(115, 2, 76, 1, 1496645339),
(116, 2, 77, 1, 1496645339),
(117, 2, 78, 1, 1496645339),
(118, 2, 79, 1, 1496645339),
(119, 2, 80, 1, 1496645339),
(120, 2, 81, 1, 1496645339),
(121, 2, 82, 1, 1496645339),
(122, 2, 83, 1, 1496645339),
(123, 2, 84, 1, 1496645339),
(124, 2, 85, 1, 1496645339),
(125, 3, 24, 1, 1496645339),
(126, 3, 25, 1, 1496645339),
(127, 3, 26, 1, 1496645339),
(128, 3, 27, 1, 1496645339),
(129, 3, 28, 1, 1496645339),
(130, 3, 29, 1, 1496645339),
(131, 3, 30, 1, 1496645339),
(132, 3, 31, 1, 1496645339),
(133, 3, 32, 1, 1496645339),
(134, 3, 33, 1, 1496645339),
(135, 3, 34, 1, 1496645339),
(136, 3, 35, 1, 1496645339),
(137, 3, 36, 1, 1496645339),
(138, 3, 37, 1, 1496645339),
(139, 3, 38, 1, 1496645339),
(140, 3, 39, 1, 1496645339),
(141, 3, 40, 1, 1496645339),
(142, 3, 41, 1, 1496645339),
(143, 3, 42, 1, 1496645339),
(144, 3, 43, 1, 1496645339),
(145, 3, 44, 1, 1496645339),
(146, 3, 45, 1, 1496645339),
(147, 3, 46, 1, 1496645339),
(148, 3, 47, 1, 1496645339),
(149, 3, 48, 1, 1496645339),
(150, 3, 49, 1, 1496645339),
(151, 3, 50, 1, 1496645339),
(152, 3, 51, 1, 1496645339),
(153, 3, 52, 1, 1496645339),
(154, 3, 53, 1, 1496645339),
(155, 3, 54, 1, 1496645339),
(156, 3, 55, 1, 1496645339),
(157, 3, 56, 1, 1496645339),
(158, 3, 57, 1, 1496645339),
(159, 3, 58, 1, 1496645339),
(160, 3, 59, 1, 1496645339),
(161, 3, 60, 1, 1496645339),
(162, 3, 61, 1, 1496645339),
(163, 3, 62, 1, 1496645339),
(164, 3, 63, 1, 1496645339),
(165, 3, 64, 1, 1496645339),
(166, 3, 65, 1, 1496645339),
(167, 3, 66, 1, 1496645339),
(168, 3, 67, 1, 1496645339),
(169, 3, 68, 1, 1496645339),
(170, 3, 69, 1, 1496645339),
(171, 3, 70, 1, 1496645339),
(172, 3, 71, 1, 1496645339),
(173, 3, 72, 1, 1496645339),
(174, 3, 73, 1, 1496645339),
(175, 3, 74, 1, 1496645339),
(176, 3, 75, 1, 1496645339),
(177, 3, 76, 1, 1496645339),
(178, 3, 77, 1, 1496645339),
(179, 3, 78, 1, 1496645339),
(180, 3, 79, 1, 1496645339),
(181, 3, 80, 1, 1496645339),
(182, 3, 81, 1, 1496645339),
(183, 3, 82, 1, 1496645339),
(184, 3, 83, 1, 1496645339),
(185, 3, 84, 1, 1496645339),
(186, 3, 85, 1, 1496645339),
(187, 4, 24, 1, 1496645339),
(188, 4, 25, 1, 1496645339),
(189, 4, 26, 1, 1496645339),
(190, 4, 27, 1, 1496645339),
(191, 4, 28, 1, 1496645339),
(192, 4, 29, 1, 1496645339),
(193, 4, 30, 1, 1496645339),
(194, 4, 31, 1, 1496645339),
(195, 4, 32, 1, 1496645339),
(196, 4, 33, 1, 1496645339),
(197, 4, 34, 1, 1496645339),
(198, 4, 35, 1, 1496645339),
(199, 4, 36, 1, 1496645339),
(200, 4, 37, 1, 1496645339),
(201, 4, 38, 1, 1496645339),
(202, 4, 39, 1, 1496645339),
(203, 4, 40, 1, 1496645339),
(204, 4, 41, 1, 1496645339),
(205, 4, 42, 1, 1496645339),
(206, 4, 43, 1, 1496645339),
(207, 4, 44, 1, 1496645339),
(208, 4, 45, 1, 1496645339),
(209, 4, 46, 1, 1496645339),
(210, 4, 47, 1, 1496645339),
(211, 4, 48, 1, 1496645339),
(212, 4, 49, 1, 1496645339),
(213, 4, 50, 1, 1496645339),
(214, 4, 51, 1, 1496645339),
(215, 4, 52, 1, 1496645339),
(216, 4, 53, 1, 1496645339),
(217, 4, 54, 1, 1496645339),
(218, 4, 55, 1, 1496645339),
(219, 4, 56, 1, 1496645339),
(220, 4, 57, 1, 1496645339),
(221, 4, 58, 1, 1496645339),
(222, 4, 59, 1, 1496645339),
(223, 4, 60, 1, 1496645339),
(224, 4, 61, 1, 1496645339),
(225, 4, 62, 1, 1496645339),
(226, 4, 63, 1, 1496645339),
(227, 4, 64, 1, 1496645339),
(228, 4, 65, 1, 1496645339),
(229, 4, 66, 1, 1496645339),
(230, 4, 67, 1, 1496645339),
(231, 4, 68, 1, 1496645339),
(232, 4, 69, 1, 1496645339),
(233, 4, 70, 1, 1496645339),
(234, 4, 71, 1, 1496645339),
(235, 4, 72, 1, 1496645339),
(236, 4, 73, 1, 1496645339),
(237, 4, 74, 1, 1496645339),
(238, 4, 75, 1, 1496645339),
(239, 4, 76, 1, 1496645339),
(240, 4, 77, 1, 1496645339),
(241, 4, 78, 1, 1496645339),
(242, 4, 79, 1, 1496645339),
(243, 4, 80, 1, 1496645339),
(244, 4, 81, 1, 1496645339),
(245, 4, 82, 1, 1496645339),
(246, 4, 83, 1, 1496645339),
(247, 4, 84, 1, 1496645339),
(248, 4, 85, 1, 1496645339),
(249, 5, 24, 1, 1496645339),
(250, 5, 25, 1, 1496645339),
(251, 5, 26, 1, 1496645339),
(252, 5, 27, 1, 1496645339),
(253, 5, 28, 1, 1496645339),
(254, 5, 29, 1, 1496645339),
(255, 5, 30, 1, 1496645339),
(256, 5, 31, 1, 1496645339),
(257, 5, 32, 1, 1496645339),
(258, 5, 33, 1, 1496645339),
(259, 5, 34, 1, 1496645339),
(260, 5, 35, 1, 1496645339),
(261, 5, 36, 1, 1496645339),
(262, 5, 37, 1, 1496645339),
(263, 5, 38, 1, 1496645339),
(264, 5, 39, 1, 1496645339),
(265, 5, 40, 1, 1496645339),
(266, 5, 41, 1, 1496645339),
(267, 5, 42, 1, 1496645339),
(268, 5, 43, 1, 1496645339),
(269, 5, 44, 1, 1496645339),
(270, 5, 45, 1, 1496645339),
(271, 5, 46, 1, 1496645339),
(272, 5, 47, 1, 1496645339),
(273, 5, 48, 1, 1496645339),
(274, 5, 49, 1, 1496645339),
(275, 5, 50, 1, 1496645339),
(276, 5, 51, 1, 1496645339),
(277, 5, 52, 1, 1496645339),
(278, 5, 53, 1, 1496645339),
(279, 5, 54, 1, 1496645339),
(280, 5, 55, 1, 1496645339),
(281, 5, 56, 1, 1496645339),
(282, 5, 57, 1, 1496645339),
(283, 5, 58, 1, 1496645339),
(284, 5, 59, 1, 1496645339),
(285, 5, 60, 1, 1496645339),
(286, 5, 61, 1, 1496645339),
(287, 5, 62, 1, 1496645339),
(288, 5, 63, 1, 1496645339),
(289, 5, 64, 1, 1496645339),
(290, 5, 65, 1, 1496645339),
(291, 5, 66, 1, 1496645339),
(292, 5, 67, 1, 1496645339),
(293, 5, 68, 1, 1496645339),
(294, 5, 69, 1, 1496645339),
(295, 5, 70, 1, 1496645339),
(296, 5, 71, 1, 1496645339),
(297, 5, 72, 1, 1496645339),
(298, 5, 73, 1, 1496645339),
(299, 5, 74, 1, 1496645339),
(300, 5, 75, 1, 1496645339),
(301, 5, 76, 1, 1496645339),
(302, 5, 77, 1, 1496645339),
(303, 5, 78, 1, 1496645339),
(304, 5, 79, 1, 1496645339),
(305, 5, 80, 1, 1496645339),
(306, 5, 81, 1, 1496645339),
(307, 5, 82, 1, 1496645339),
(308, 5, 83, 1, 1496645339),
(309, 5, 84, 1, 1496645339),
(310, 5, 85, 1, 1496645339),
(311, 6, 24, 1, 1496645339),
(312, 6, 25, 1, 1496645339),
(313, 6, 26, 1, 1496645339),
(314, 6, 27, 1, 1496645339),
(315, 6, 28, 1, 1496645339),
(316, 6, 29, 1, 1496645339),
(317, 6, 30, 1, 1496645339),
(318, 6, 31, 1, 1496645339),
(319, 6, 32, 1, 1496645339),
(320, 6, 33, 1, 1496645339),
(321, 6, 34, 1, 1496645339),
(322, 6, 35, 1, 1496645339),
(323, 6, 36, 1, 1496645339),
(324, 6, 37, 1, 1496645339),
(325, 6, 38, 1, 1496645339),
(326, 6, 39, 1, 1496645339),
(327, 6, 40, 1, 1496645339),
(328, 6, 41, 1, 1496645339),
(329, 6, 42, 1, 1496645339),
(330, 6, 43, 1, 1496645339),
(331, 6, 44, 1, 1496645339),
(332, 6, 45, 1, 1496645339),
(333, 6, 46, 1, 1496645339),
(334, 6, 47, 1, 1496645339),
(335, 6, 48, 1, 1496645339),
(336, 6, 49, 1, 1496645339),
(337, 6, 50, 1, 1496645339),
(338, 6, 51, 1, 1496645339),
(339, 6, 52, 1, 1496645339),
(340, 6, 53, 1, 1496645339),
(341, 6, 54, 1, 1496645339),
(342, 6, 55, 1, 1496645339),
(343, 6, 56, 1, 1496645339),
(344, 6, 57, 1, 1496645339),
(345, 6, 58, 1, 1496645339),
(346, 6, 59, 1, 1496645339),
(347, 6, 60, 1, 1496645339),
(348, 6, 61, 1, 1496645339),
(349, 6, 62, 1, 1496645339),
(350, 6, 63, 1, 1496645339),
(351, 6, 64, 1, 1496645339),
(352, 6, 65, 1, 1496645339),
(353, 6, 66, 1, 1496645339),
(354, 6, 67, 1, 1496645339),
(355, 6, 68, 1, 1496645339),
(356, 6, 69, 1, 1496645339),
(357, 6, 70, 1, 1496645339),
(358, 6, 71, 1, 1496645339),
(359, 6, 72, 1, 1496645339),
(360, 6, 73, 1, 1496645339),
(361, 6, 74, 1, 1496645339),
(362, 6, 75, 1, 1496645339),
(363, 6, 76, 1, 1496645339),
(364, 6, 77, 1, 1496645339),
(365, 6, 78, 1, 1496645339),
(366, 6, 79, 1, 1496645339),
(367, 6, 80, 1, 1496645339),
(368, 6, 81, 1, 1496645339),
(369, 6, 82, 1, 1496645339),
(370, 6, 83, 1, 1496645339),
(371, 6, 84, 1, 1496645339),
(372, 6, 85, 1, 1496645339),
(373, 7, 24, 1, 1496645339),
(374, 7, 25, 1, 1496645339),
(375, 7, 26, 1, 1496645339),
(376, 7, 27, 1, 1496645339),
(377, 7, 28, 1, 1496645339),
(378, 7, 29, 1, 1496645339),
(379, 7, 30, 1, 1496645339),
(380, 7, 31, 1, 1496645339),
(381, 7, 32, 1, 1496645339),
(382, 7, 33, 1, 1496645339),
(383, 7, 34, 1, 1496645339),
(384, 7, 35, 1, 1496645339),
(385, 7, 36, 1, 1496645339),
(386, 7, 37, 1, 1496645339),
(387, 7, 38, 1, 1496645339),
(388, 7, 39, 1, 1496645339),
(389, 7, 40, 1, 1496645339),
(390, 7, 41, 1, 1496645339),
(391, 7, 42, 1, 1496645339),
(392, 7, 43, 1, 1496645339),
(393, 7, 44, 1, 1496645339),
(394, 7, 45, 1, 1496645339),
(395, 7, 46, 1, 1496645339),
(396, 7, 47, 1, 1496645339),
(397, 7, 48, 1, 1496645339),
(398, 7, 49, 1, 1496645339),
(399, 7, 50, 1, 1496645339),
(400, 7, 51, 1, 1496645339),
(401, 7, 52, 1, 1496645339),
(402, 7, 53, 1, 1496645339),
(403, 7, 54, 1, 1496645339),
(404, 7, 55, 1, 1496645339),
(405, 7, 56, 1, 1496645339),
(406, 7, 57, 1, 1496645339),
(407, 7, 58, 1, 1496645339),
(408, 7, 59, 1, 1496645339),
(409, 7, 60, 1, 1496645339),
(410, 7, 61, 1, 1496645339),
(411, 7, 62, 1, 1496645339),
(412, 7, 63, 1, 1496645339),
(413, 7, 64, 1, 1496645339),
(414, 7, 65, 1, 1496645339),
(415, 7, 66, 1, 1496645339),
(416, 7, 67, 1, 1496645339),
(417, 7, 68, 1, 1496645339),
(418, 7, 69, 1, 1496645339),
(419, 7, 70, 1, 1496645339),
(420, 7, 71, 1, 1496645339),
(421, 7, 72, 1, 1496645339),
(422, 7, 73, 1, 1496645339),
(423, 7, 74, 1, 1496645339),
(424, 7, 75, 1, 1496645339),
(425, 7, 76, 1, 1496645339),
(426, 7, 77, 1, 1496645339),
(427, 7, 78, 1, 1496645339),
(428, 7, 79, 1, 1496645339),
(429, 7, 80, 1, 1496645339),
(430, 7, 81, 1, 1496645339),
(431, 7, 82, 1, 1496645339),
(432, 7, 83, 1, 1496645339),
(433, 7, 84, 1, 1496645339),
(434, 7, 85, 1, 1496645339),
(435, 8, 24, 1, 1496645339),
(436, 8, 25, 1, 1496645339),
(437, 8, 26, 1, 1496645339),
(438, 8, 27, 1, 1496645339),
(439, 8, 28, 1, 1496645339),
(440, 8, 29, 1, 1496645339),
(441, 8, 30, 1, 1496645339),
(442, 8, 31, 1, 1496645339),
(443, 8, 32, 1, 1496645339),
(444, 8, 33, 1, 1496645339),
(445, 8, 34, 1, 1496645339),
(446, 8, 35, 1, 1496645339),
(447, 8, 36, 1, 1496645339),
(448, 8, 37, 1, 1496645339),
(449, 8, 38, 1, 1496645339),
(450, 8, 39, 1, 1496645339),
(451, 8, 40, 1, 1496645339),
(452, 8, 41, 1, 1496645339),
(453, 8, 42, 1, 1496645339),
(454, 8, 43, 1, 1496645339),
(455, 8, 44, 1, 1496645339),
(456, 8, 45, 1, 1496645339),
(457, 8, 46, 1, 1496645339),
(458, 8, 47, 1, 1496645339),
(459, 8, 48, 1, 1496645339),
(460, 8, 49, 1, 1496645339),
(461, 8, 50, 1, 1496645339),
(462, 8, 51, 1, 1496645339),
(463, 8, 52, 1, 1496645339),
(464, 8, 53, 1, 1496645339),
(465, 8, 54, 1, 1496645339),
(466, 8, 55, 1, 1496645339),
(467, 8, 56, 1, 1496645339),
(468, 8, 57, 1, 1496645339),
(469, 8, 58, 1, 1496645339),
(470, 8, 59, 1, 1496645339),
(471, 8, 60, 1, 1496645339),
(472, 8, 61, 1, 1496645339),
(473, 8, 62, 1, 1496645339),
(474, 8, 63, 1, 1496645339),
(475, 8, 64, 1, 1496645339),
(476, 8, 65, 1, 1496645339),
(477, 8, 66, 1, 1496645339),
(478, 8, 67, 1, 1496645339),
(479, 8, 68, 1, 1496645339),
(480, 8, 69, 1, 1496645339),
(481, 8, 70, 1, 1496645339),
(482, 8, 71, 1, 1496645339),
(483, 8, 72, 1, 1496645339),
(484, 8, 73, 1, 1496645339),
(485, 8, 74, 1, 1496645339),
(486, 8, 75, 1, 1496645339),
(487, 8, 76, 1, 1496645339),
(488, 8, 77, 1, 1496645339),
(489, 8, 78, 1, 1496645339),
(490, 8, 79, 1, 1496645339),
(491, 8, 80, 1, 1496645339),
(492, 8, 81, 1, 1496645339),
(493, 8, 82, 1, 1496645339),
(494, 8, 83, 1, 1496645339),
(495, 8, 84, 1, 1496645339),
(496, 8, 85, 1, 1496645339),
(497, 9, 24, 1, 1496645339),
(498, 9, 25, 1, 1496645339),
(499, 9, 26, 1, 1496645339),
(500, 9, 28, 1, 1496645339),
(501, 9, 29, 1, 1496645339),
(502, 9, 30, 1, 1496645339),
(503, 9, 36, 1, 1496645339),
(504, 9, 37, 1, 1496645339),
(505, 9, 38, 1, 1496645339),
(506, 9, 40, 1, 1496645339),
(507, 9, 41, 1, 1496645339),
(508, 9, 42, 1, 1496645339),
(509, 9, 43, 1, 1496645339),
(510, 9, 45, 1, 1496645339),
(511, 9, 46, 1, 1496645339),
(512, 9, 47, 1, 1496645339),
(513, 9, 48, 1, 1496645339),
(514, 9, 50, 1, 1496645339),
(515, 9, 51, 1, 1496645339),
(516, 9, 52, 1, 1496645339),
(517, 9, 54, 1, 1496645339),
(518, 9, 55, 1, 1496645339),
(519, 9, 56, 1, 1496645339),
(520, 9, 57, 1, 1496645339),
(521, 9, 59, 1, 1496645339),
(522, 9, 60, 1, 1496645339),
(523, 9, 61, 1, 1496645339),
(524, 10, 24, 1, 1496645339),
(525, 10, 25, 1, 1496645339),
(526, 10, 26, 1, 1496645339),
(527, 10, 27, 1, 1496645339),
(528, 10, 28, 1, 1496645339),
(529, 10, 29, 1, 1496645339),
(530, 10, 30, 1, 1496645339),
(531, 10, 31, 1, 1496645339),
(532, 10, 36, 1, 1496645339),
(533, 10, 37, 1, 1496645339),
(534, 10, 38, 1, 1496645339),
(535, 10, 39, 1, 1496645339),
(536, 10, 40, 1, 1496645339),
(537, 10, 41, 1, 1496645339),
(538, 10, 42, 1, 1496645339),
(539, 10, 43, 1, 1496645339),
(540, 10, 44, 1, 1496645339),
(541, 10, 45, 1, 1496645339),
(542, 10, 46, 1, 1496645339),
(543, 10, 47, 1, 1496645339),
(544, 10, 48, 1, 1496645339),
(545, 10, 49, 1, 1496645339),
(546, 10, 50, 1, 1496645339),
(547, 10, 51, 1, 1496645339),
(548, 10, 52, 1, 1496645339),
(549, 10, 53, 1, 1496645339),
(550, 10, 54, 1, 1496645339),
(551, 10, 55, 1, 1496645339),
(552, 10, 56, 1, 1496645339),
(553, 10, 57, 1, 1496645339),
(554, 10, 58, 1, 1496645339),
(555, 10, 59, 1, 1496645339),
(556, 10, 60, 1, 1496645339),
(557, 10, 61, 1, 1496645339),
(558, 10, 62, 1, 1496645339),
(559, 10, 63, 1, 1496645339),
(560, 10, 64, 1, 1496645339),
(561, 10, 65, 1, 1496645339),
(562, 10, 66, 1, 1496645339),
(563, 10, 85, 1, 1496645339),
(564, 11, 24, 1, 1496645339),
(565, 11, 25, 1, 1496645339),
(566, 11, 26, 1, 1496645339),
(567, 11, 28, 1, 1496645339),
(568, 11, 29, 1, 1496645339),
(569, 11, 30, 1, 1496645339),
(570, 11, 32, 1, 1496645339),
(571, 11, 33, 1, 1496645339),
(572, 11, 34, 1, 1496645339),
(573, 11, 36, 1, 1496645339),
(574, 11, 37, 1, 1496645339),
(575, 11, 38, 1, 1496645339),
(576, 11, 40, 1, 1496645339),
(577, 11, 41, 1, 1496645339),
(578, 11, 42, 1, 1496645339),
(579, 11, 43, 1, 1496645339),
(580, 11, 45, 1, 1496645339),
(581, 11, 46, 1, 1496645339),
(582, 11, 47, 1, 1496645339),
(583, 11, 48, 1, 1496645339),
(584, 11, 50, 1, 1496645339),
(585, 11, 51, 1, 1496645339),
(586, 11, 52, 1, 1496645339),
(587, 11, 54, 1, 1496645339),
(588, 11, 55, 1, 1496645339),
(589, 11, 56, 1, 1496645339),
(590, 11, 57, 1, 1496645339),
(591, 11, 59, 1, 1496645339),
(592, 11, 60, 1, 1496645339),
(593, 11, 61, 1, 1496645339),
(594, 12, 24, 1, 1496645339),
(595, 12, 25, 1, 1496645339),
(596, 12, 26, 1, 1496645339),
(597, 12, 27, 1, 1496645339),
(598, 12, 28, 1, 1496645339),
(599, 12, 29, 1, 1496645339),
(600, 12, 30, 1, 1496645339),
(601, 12, 31, 1, 1496645339),
(602, 12, 32, 1, 1496645339),
(603, 12, 33, 1, 1496645339),
(604, 12, 34, 1, 1496645339),
(605, 12, 35, 1, 1496645339),
(606, 12, 36, 1, 1496645339),
(607, 12, 37, 1, 1496645339),
(608, 12, 38, 1, 1496645339),
(609, 12, 39, 1, 1496645339),
(610, 12, 40, 1, 1496645339),
(611, 12, 41, 1, 1496645339),
(612, 12, 42, 1, 1496645339),
(613, 12, 43, 1, 1496645339),
(614, 12, 44, 1, 1496645339),
(615, 12, 45, 1, 1496645339),
(616, 12, 46, 1, 1496645339),
(617, 12, 47, 1, 1496645339),
(618, 12, 48, 1, 1496645339),
(619, 12, 49, 1, 1496645339),
(620, 12, 50, 1, 1496645339),
(621, 12, 51, 1, 1496645339),
(622, 12, 52, 1, 1496645339),
(623, 12, 53, 1, 1496645339),
(624, 12, 54, 1, 1496645339),
(625, 12, 55, 1, 1496645339),
(626, 12, 56, 1, 1496645339),
(627, 12, 57, 1, 1496645339),
(628, 12, 58, 1, 1496645339),
(629, 12, 59, 1, 1496645339),
(630, 12, 60, 1, 1496645339),
(631, 12, 61, 1, 1496645339),
(632, 12, 62, 1, 1496645339),
(633, 12, 63, 1, 1496645339),
(634, 12, 64, 1, 1496645339),
(635, 12, 65, 1, 1496645339),
(636, 12, 66, 1, 1496645339),
(637, 12, 85, 1, 1496645339),
(638, 13, 24, 1, 1496645339),
(639, 13, 25, 1, 1496645339),
(640, 13, 26, 1, 1496645339),
(641, 13, 27, 1, 1496645339),
(642, 13, 36, 1, 1496645339),
(643, 13, 37, 1, 1496645339),
(644, 13, 38, 1, 1496645339),
(645, 13, 39, 1, 1496645339),
(646, 13, 40, 1, 1496645339),
(647, 14, 24, 1, 1496645339),
(648, 14, 25, 1, 1496645339),
(649, 14, 26, 1, 1496645339),
(650, 14, 27, 1, 1496645339),
(651, 14, 71, 1, 1496645339),
(652, 14, 72, 1, 1496645339),
(653, 14, 73, 1, 1496645339),
(654, 14, 74, 1, 1496645339),
(655, 14, 81, 1, 1496645339),
(656, 14, 82, 1, 1496645339),
(657, 14, 83, 1, 1496645339),
(658, 14, 84, 1, 1496645339),
(659, 15, 75, 1, 1496645339),
(660, 15, 76, 1, 1496645339),
(661, 16, 24, 1, 1496645339),
(662, 16, 25, 1, 1496645339),
(663, 16, 26, 1, 1496645339),
(664, 16, 27, 1, 1496645339),
(665, 16, 28, 1, 1496645339),
(666, 16, 29, 1, 1496645339),
(667, 16, 30, 1, 1496645339),
(668, 16, 31, 1, 1496645339),
(669, 16, 32, 1, 1496645339),
(670, 16, 33, 1, 1496645339),
(671, 16, 34, 1, 1496645339),
(672, 16, 35, 1, 1496645339),
(673, 16, 36, 1, 1496645339),
(674, 16, 37, 1, 1496645339),
(675, 16, 38, 1, 1496645339),
(676, 16, 39, 1, 1496645339),
(677, 16, 40, 1, 1496645339),
(678, 16, 41, 1, 1496645339),
(679, 16, 42, 1, 1496645339),
(680, 16, 43, 1, 1496645339),
(681, 16, 44, 1, 1496645339),
(682, 16, 45, 1, 1496645339),
(683, 16, 46, 1, 1496645339),
(684, 16, 47, 1, 1496645339),
(685, 16, 48, 1, 1496645339),
(686, 16, 49, 1, 1496645339),
(687, 16, 50, 1, 1496645339),
(688, 16, 51, 1, 1496645339),
(689, 16, 52, 1, 1496645339),
(690, 16, 53, 1, 1496645339),
(691, 16, 54, 1, 1496645339),
(692, 16, 55, 1, 1496645339),
(693, 16, 56, 1, 1496645339),
(694, 16, 57, 1, 1496645339),
(695, 16, 58, 1, 1496645339),
(696, 16, 59, 1, 1496645339),
(697, 16, 60, 1, 1496645339),
(698, 16, 61, 1, 1496645339),
(699, 16, 62, 1, 1496645339),
(700, 16, 63, 1, 1496645339),
(701, 16, 64, 1, 1496645339),
(702, 16, 65, 1, 1496645339),
(703, 16, 66, 1, 1496645339),
(704, 16, 67, 1, 1496645339),
(705, 16, 68, 1, 1496645339),
(706, 16, 69, 1, 1496645339),
(707, 16, 70, 1, 1496645339),
(708, 16, 71, 1, 1496645339),
(709, 16, 72, 1, 1496645339),
(710, 16, 73, 1, 1496645339),
(711, 16, 74, 1, 1496645339),
(712, 16, 85, 1, 1496645339);

-- --------------------------------------------------------

--
-- Table structure for table `role_master`
--

CREATE TABLE `role_master` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `code` varchar(50) CHARACTER SET utf8 NOT NULL,
  `name` varchar(50) CHARACTER SET utf8 NOT NULL,
  `short_info` varchar(255) CHARACTER SET utf8 NOT NULL,
  `is_system` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `for_customers` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Delete',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `role_master`
--

INSERT INTO `role_master` (`id`, `account_id`, `user_id`, `source_id`, `code`, `name`, `short_info`, `is_system`, `for_customers`, `status`, `created`, `modified`) VALUES
(1, NULL, NULL, NULL, 'SH_SUPER_ADMIN', 'Saleshandy Super Admin', '', 1, 0, 1, 1496645339, 1496645339),
(2, NULL, NULL, NULL, 'SH_ADMIN', 'Saleshandy Admin', '', 1, 0, 1, 1496645339, 1496645339),
(3, NULL, NULL, NULL, 'SH_IT', 'Saleshandy IT Person', '', 1, 0, 1, 1496645339, 1496645339),
(4, NULL, NULL, NULL, 'SH_QA', 'Saleshandy QA', '', 1, 0, 1, 1496645339, 1496645339),
(5, NULL, NULL, NULL, 'SH_SALES', 'Saleshandy Sales Person', '', 1, 0, 1, 1496645339, 1496645339),
(6, NULL, NULL, NULL, 'SH_MARKETING', 'Saleshandy Marketing Person', '', 1, 0, 1, 1496645339, 1496645339),
(7, NULL, NULL, NULL, 'SH_HR', 'Saleshandy Human Resources Manager', '', 1, 0, 1, 1496645339, 1496645339),
(8, NULL, NULL, NULL, 'AC_OWNER', 'Account Owner', '', 1, 1, 1, 1496645339, 1496645339),
(9, NULL, NULL, NULL, 'AC_SALES_PERSON', 'Sales Executive', '', 1, 1, 1, 1496645339, 1496645339),
(10, NULL, NULL, NULL, 'AC_SALES_MANAGER', 'Sales Manager', '', 1, 1, 1, 1496645339, 1496645339),
(11, NULL, NULL, NULL, 'AC_MARKETING_PERSON', 'Marketing Executive', '', 1, 1, 1, 1496645339, 1496645339),
(12, NULL, NULL, NULL, 'AC_MARKETING_MANAGER', 'Marketing Manager', '', 1, 1, 1, 1496645339, 1496645339),
(13, NULL, NULL, NULL, 'AC_CUSTOMER_SUPPORT', 'Customer Support', '', 1, 1, 1, 1496645339, 1496645339),
(14, NULL, NULL, NULL, 'AC_IT_ENGINEER', 'IT Engineer', '', 1, 1, 1, 1496645339, 1496645339),
(15, NULL, NULL, NULL, 'AC_FINANCE_MANAGER', 'Finance Manager', '', 1, 1, 1, 1496645339, 1496645339),
(16, NULL, NULL, NULL, 'AC_TEAM_MEMBER', 'Team Member', '', 1, 1, 1, 1496645339, 1496645339);

-- --------------------------------------------------------

--
-- Table structure for table `social_login_master`
--

CREATE TABLE `social_login_master` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(25) CHARACTER SET utf8 NOT NULL,
  `name` varchar(50) CHARACTER SET utf8 NOT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `social_login_master`
--

INSERT INTO `social_login_master` (`id`, `code`, `name`, `status`, `created`, `modified`) VALUES
(1, 'GMAIL', 'GMail Account', 1, 1496645339, 1496645339),
(2, 'FACEBOOK', 'Facebook Account', 1, 1496645339, 1496645339);

-- --------------------------------------------------------

--
-- Table structure for table `source_master`
--

CREATE TABLE `source_master` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(25) CHARACTER SET utf8 NOT NULL,
  `name` varchar(50) CHARACTER SET utf8 NOT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `source_master`
--

INSERT INTO `source_master` (`id`, `code`, `name`, `status`, `created`, `modified`) VALUES
(1, 'WEB_APP', 'Web Application', 1, 1496645339, 1496645339),
(2, 'CHROME_PLUGIN', 'Chrome Plugin', 1, 1496645339, 1496645339),
(3, 'OUTLOOK_PLUGIN', 'Outlook Plugin', 1, 1496645339, 1496645339);

-- --------------------------------------------------------

--
-- Table structure for table `user_actions`
--

CREATE TABLE `user_actions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `resource_id` smallint(5) UNSIGNED DEFAULT NULL,
  `model` varchar(100) CHARACTER SET utf8 NOT NULL,
  `record_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_authentication_tokens`
--

CREATE TABLE `user_authentication_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `token` varchar(50) CHARACTER SET utf8 NOT NULL,
  `generated_at` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Token generated date time (GMT+00:00 Timestamp)',
  `expires_at` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Token expires at date time (GMT+00:00 Timestamp)',
  `user_resources` text CHARACTER SET utf8 NOT NULL COMMENT 'Array of resources accessible to user'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `user_authentication_tokens`
--

INSERT INTO `user_authentication_tokens` (`id`, `user_id`, `source_id`, `token`, `generated_at`, `expires_at`, `user_resources`) VALUES
(1, 1, 1, 'VJ8VYiMivOZkYOyM', 1506931322, 1507536122, '{\"\\/mail-accounts\\/list\":1,\"\\/mail-accounts\\/{id}\\/view\":1,\"\\/mail-accounts\\/create\":1,\"\\/mail-accounts\\/{id}\\/copy\":1,\"\\/mail-accounts\\/connect\":1,\"\\/mail-accounts\\/connect-verify\":1,\"\\/mail-accounts\\/{id}\\/update\":1,\"\\/mail-accounts\\/{id}\\/mark-as-public\":1,\"\\/mail-accounts\\/{id}\\/status-update\":1,\"\\/mail-accounts\\/{id}\\/delete\":1,\"\\/emails\\/list\":1,\"\\/emails\\/{id}\\/view\":1,\"\\/emails\\/create\":1,\"\\/emails\\/{id}\\/copy\":1,\"\\/emails\\/{id}\\/update\":1,\"\\/emails\\/{id}\\/snooze\":1,\"\\/emails\\/{id}\\/delete\":1,\"\\/campaigns\\/list\":1,\"\\/campaigns\\/{id}\\/view\":1,\"\\/campaigns\\/{id}\\/view-stage\\/{stage_id}\":1,\"\\/campaigns\\/{id}\\/sequence-mail\\/{seq_id}\":1,\"\\/campaigns\\/{id}\\/export-data\\/{stage_id}\":1,\"\\/campaigns\\/create\":1,\"\\/campaigns\\/{id}\\/copy\":1,\"\\/campaigns\\/{id}\\/update\":1,\"\\/campaigns\\/{id}\\/snooze\":1,\"\\/campaigns\\/{id}\\/status-update\":1,\"\\/campaigns\\/{id}\\/sequence-delete\\/{seq_id}\":1,\"\\/campaigns\\/{id}\\/delete\":1,\"\\/documents\\/list\":1,\"\\/documents\\/{id}\\/view\":1,\"\\/documents\\/create\":1,\"\\/documents\\/{id}\\/{folder_id}\\/copy\":1,\"\\/documents\\/{id}\\/update\":1,\"\\/documents\\/{id}\\/mark-as-public\":1,\"\\/documents\\/{id}\\/status-update\":1,\"\\/documents\\/{id}\\/move\":1,\"\\/documents\\/{id}\\/delete\":1,\"\\/documents\\/{id}\\/share\":1,\"\\/templates\\/folders\\/list\":1,\"\\/templates\\/folders\\/{id}\\/view\":1,\"\\/documents\\/folders\\/list\":1,\"\\/documents\\/folders\\/{id}\\/view\":1,\"\\/templates\\/folders\\/create\":1,\"\\/documents\\/folders\\/create\":1,\"\\/templates\\/folders\\/{id}\\/update\":1,\"\\/documents\\/folders\\/{id}\\/update\":1,\"\\/templates\\/folders\\/{id}\\/delete\":1,\"\\/documents\\/folders\\/{id}\\/delete\":1,\"\\/templates\\/folders\\/{id}\\/share\":1,\"\\/documents\\/folders\\/{id}\\/share\":1,\"\\/links\\/list\":1,\"\\/links\\/{id}\\/view\":1,\"\\/links\\/create\":1,\"\\/links\\/{id}\\/copy\":1,\"\\/links\\/{id}\\/update\":1,\"\\/links\\/{id}\\/status-update\":1,\"\\/links\\/{id}\\/delete\":1,\"\\/templates\\/list\":1,\"\\/templates\\/{id}\\/view\":1,\"\\/templates\\/create\":1,\"\\/templates\\/{id}\\/{folder_id}\\/copy\":1,\"\\/templates\\/{id}\\/update\":1,\"\\/templates\\/{id}\\/status-update\":1,\"\\/templates\\/{id}\\/move\":1,\"\\/templates\\/{id}\\/delete\":1,\"\\/templates\\/{id}\\/share\":1,\"\\/contacts\\/list\":1,\"\\/contacts\\/{id}\\/view\":1,\"\\/contacts\\/{id}\\/feed\":1,\"\\/contacts\\/create\":1,\"\\/contacts\\/{id}\\/copy\":1,\"\\/contacts\\/{id}\\/update\":1,\"\\/contacts\\/{id}\\/status-update\":1,\"\\/contacts\\/{id}\\/delete\":1,\"\\/company\\/list\":1,\"\\/company\\/{id}\\/view\":1,\"\\/company\\/{id}\\/feed\":1,\"\\/company\\/{id}\\/contacts\":1,\"\\/company\\/create\":1,\"\\/company\\/{id}\\/copy\":1,\"\\/company\\/{id}\\/update\":1,\"\\/company\\/{id}\\/status-update\":1,\"\\/company\\/{id}\\/delete\":1,\"\\/teams\\/list\":1,\"\\/teams\\/{id}\\/view\":1,\"\\/teams\\/create\":1,\"\\/teams\\/{id}\\/copy\":1,\"\\/teams\\/{id}\\/update\":1,\"\\/teams\\/{id}\\/delete\":1,\"\\/members\\/list\":1,\"\\/members\\/{id}\\/view\":1,\"\\/members\\/{id}\\/resend-invitation\":1,\"\\/members\\/{id}\\/activity\":1,\"\\/members\\/{id}\\/resources\":1,\"\\/members\\/invite\":1,\"\\/members\\/{id}\\/update\":1,\"\\/members\\/{id}\\/status-update\":1,\"\\/members\\/{id}\\/delete\":1,\"\\/roles\\/list\":1,\"\\/roles\\/{id}\\/view\":1,\"\\/roles\\/resources\\/list\":1,\"\\/roles\\/create\":1,\"\\/roles\\/{id}\\/copy\":1,\"\\/roles\\/{id}\\/update\":1,\"\\/roles\\/{id}\\/delete\":1,\"\\/account\\/plan\\/{code}\\/details\":1,\"\\/account\\/billing\\/check-coupon\":1,\"\\/account\\/buy\":1,\"\\/account\\/add-seat\":1,\"\\/account\\/upgrade\":1,\"\\/account\\/downgrade\":1,\"\\/account\\/subscription\\/cancel\":1,\"\\/account\\/billing\\/history\":1,\"\\/account\\/billing\\/{id}\\/view\":1,\"\\/account\\/billing\\/{id}\\/invoice\":1,\"\\/account\\/organisation\\/get\":1,\"\\/account\\/organisation\\/update\":1,\"\\/branding\\/get\":1,\"\\/branding\\/update\":1,\"\\/branding\\/status-update\":1,\"\\/web-hooks\\/list\":1,\"\\/web-hooks\\/{id}\\/view\":1,\"\\/web-hooks\\/create\":1,\"\\/web-hooks\\/{id}\\/copy\":1,\"\\/web-hooks\\/{id}\\/update\":1,\"\\/web-hooks\\/{id}\\/status-update\":1,\"\\/web-hooks\\/{id}\\/delete\":1,\"\\/reports\":1}');

-- --------------------------------------------------------

--
-- Table structure for table `user_invitations`
--

CREATE TABLE `user_invitations` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `invited_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'Invited by',
  `invited_at` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Invitation date time (GMT+00:00 Timestamp)',
  `joined_at` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Joined date time (GMT+00:00 Timestamp)',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Deleted',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_master`
--

CREATE TABLE `user_master` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `user_type_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `role_id` int(10) UNSIGNED DEFAULT NULL,
  `source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `first_name` varchar(50) CHARACTER SET utf8 NOT NULL,
  `last_name` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `email` varchar(150) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `password` varchar(100) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `password_salt_key` varchar(40) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `photo` varchar(40) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `phone` varchar(15) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `last_login` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Last login date time (GMT+00:00 Timestamp)',
  `verified` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active, 2-Deleted, 5-Removed',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `user_master`
--

INSERT INTO `user_master` (`id`, `account_id`, `user_type_id`, `role_id`, `source_id`, `first_name`, `last_name`, `email`, `password`, `password_salt_key`, `photo`, `phone`, `last_login`, `verified`, `status`, `created`, `modified`) VALUES
(1, 1, 4, 8, 1, 'Kamlesh', 'Chetnani', 'kamlesh@saleshandy.com', '$2a$10$M4/62RPkoBGoH2/Wv22iM.eem1a/6Z.yP0ks0FCBx/szFXUizRphO', '$2a$10$M4/62RPkoBGoH2/Wv22iMA==', '', '', 1506931322, 0, 1, 1506931314, 1506931322);

-- --------------------------------------------------------

--
-- Table structure for table `user_members`
--

CREATE TABLE `user_members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `has_access_of` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `user_pass_reset_requests`
--

CREATE TABLE `user_pass_reset_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `request_token` varchar(50) CHARACTER SET utf8 NOT NULL,
  `request_date` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Reset initiated date time (GMT+00:00 Timestamp)',
  `expires_at` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Link expiry date time (GMT+00:00 Timestamp)',
  `password_reset` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_resources`
--

CREATE TABLE `user_resources` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `resource_id` smallint(5) UNSIGNED DEFAULT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Deleted',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `user_resources`
--

INSERT INTO `user_resources` (`id`, `user_id`, `resource_id`, `status`, `modified`) VALUES
(1, 1, 24, 1, 1506931315),
(2, 1, 25, 1, 1506931315),
(3, 1, 26, 1, 1506931315),
(4, 1, 27, 1, 1506931315),
(5, 1, 28, 1, 1506931315),
(6, 1, 29, 1, 1506931315),
(7, 1, 30, 1, 1506931315),
(8, 1, 31, 1, 1506931315),
(9, 1, 32, 1, 1506931315),
(10, 1, 33, 1, 1506931315),
(11, 1, 34, 1, 1506931315),
(12, 1, 35, 1, 1506931315),
(13, 1, 36, 1, 1506931315),
(14, 1, 37, 1, 1506931316),
(15, 1, 38, 1, 1506931316),
(16, 1, 39, 1, 1506931316),
(17, 1, 40, 1, 1506931316),
(18, 1, 41, 1, 1506931316),
(19, 1, 42, 1, 1506931316),
(20, 1, 43, 1, 1506931316),
(21, 1, 44, 1, 1506931316),
(22, 1, 45, 1, 1506931316),
(23, 1, 46, 1, 1506931316),
(24, 1, 47, 1, 1506931316),
(25, 1, 48, 1, 1506931316),
(26, 1, 49, 1, 1506931316),
(27, 1, 50, 1, 1506931316),
(28, 1, 51, 1, 1506931316),
(29, 1, 52, 1, 1506931316),
(30, 1, 53, 1, 1506931316),
(31, 1, 54, 1, 1506931316),
(32, 1, 55, 1, 1506931316),
(33, 1, 56, 1, 1506931316),
(34, 1, 57, 1, 1506931316),
(35, 1, 58, 1, 1506931316),
(36, 1, 59, 1, 1506931316),
(37, 1, 60, 1, 1506931316),
(38, 1, 61, 1, 1506931317),
(39, 1, 62, 1, 1506931317),
(40, 1, 63, 1, 1506931317),
(41, 1, 64, 1, 1506931317),
(42, 1, 65, 1, 1506931317),
(43, 1, 66, 1, 1506931317),
(44, 1, 67, 1, 1506931317),
(45, 1, 68, 1, 1506931317),
(46, 1, 69, 1, 1506931317),
(47, 1, 70, 1, 1506931317),
(48, 1, 71, 1, 1506931317),
(49, 1, 72, 1, 1506931317),
(50, 1, 73, 1, 1506931317),
(51, 1, 74, 1, 1506931317),
(52, 1, 75, 1, 1506931317),
(53, 1, 76, 1, 1506931317),
(54, 1, 77, 1, 1506931317),
(55, 1, 78, 1, 1506931317),
(56, 1, 79, 1, 1506931317),
(57, 1, 80, 1, 1506931317),
(58, 1, 81, 1, 1506931317),
(59, 1, 82, 1, 1506931317),
(60, 1, 83, 1, 1506931317),
(61, 1, 84, 1, 1506931317),
(62, 1, 85, 1, 1506931317);

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `app_constant_var_id` smallint(5) UNSIGNED DEFAULT NULL,
  `value` text CHARACTER SET utf8 NOT NULL,
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_social_login`
--

CREATE TABLE `user_social_login` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `social_login_id` tinyint(2) UNSIGNED DEFAULT NULL,
  `signin_info` varchar(1024) CHARACTER SET utf8 NOT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Deleted',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_type_master`
--

CREATE TABLE `user_type_master` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(25) CHARACTER SET utf8 NOT NULL,
  `name` varchar(50) CHARACTER SET utf8 NOT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `user_type_master`
--

INSERT INTO `user_type_master` (`id`, `code`, `name`, `status`, `created`, `modified`) VALUES
(1, 'SH_SUPER_ADMIN', 'Saleshandy Super Admin', 1, 1496645339, 1496645339),
(2, 'SH_ADMIN', 'Saleshandy Admin', 1, 1496645339, 1496645339),
(3, 'SH_STAFF', 'Saleshandy Staff', 1, 1496645339, 1496645339),
(4, 'CLIENT_ADMIN', 'Client Admin', 1, 1496645339, 1496645339),
(5, 'CLIENT_STAFF', 'Client Team Members', 1, 1496645339, 1496645339);

-- --------------------------------------------------------

--
-- Table structure for table `valid_plan_coupons`
--

CREATE TABLE `valid_plan_coupons` (
  `id` mediumint(8) UNSIGNED NOT NULL,
  `plan_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `coupon_id` smallint(5) UNSIGNED DEFAULT NULL,
  `recurring_validity` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT 'How many times coupon can be applied in recurring',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Deleted',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `webhooks`
--

CREATE TABLE `webhooks` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_id` mediumint(8) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `source_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `name` varchar(150) CHARACTER SET utf8 NOT NULL,
  `resource_id` smallint(5) UNSIGNED DEFAULT NULL,
  `resource_condition` varchar(512) CHARACTER SET utf8 NOT NULL DEFAULT '{}' COMMENT 'Conditions',
  `post_url` varchar(150) CHARACTER SET utf8 NOT NULL COMMENT 'URL where to post the data',
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active, 2-Deleted',
  `created` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp',
  `modified` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_billing_master`
--
ALTER TABLE `account_billing_master`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `current_subscription_id` (`current_subscription_id`);

--
-- Indexes for table `account_companies`
--
ALTER TABLE `account_companies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `account_contacts`
--
ALTER TABLE `account_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `account_company_id` (`account_company_id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `account_folders`
--
ALTER TABLE `account_folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `account_folders_ibfk_2` (`user_id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `account_folder_teams`
--
ALTER TABLE `account_folder_teams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_folder_id` (`account_folder_id`),
  ADD KEY `account_team_id` (`account_team_id`);

--
-- Indexes for table `account_invoice_details`
--
ALTER TABLE `account_invoice_details`
  ADD KEY `account_id` (`account_id`),
  ADD KEY `account_subscription_id` (`account_subscription_id`),
  ADD KEY `account_payment_id` (`account_payment_id`);

--
-- Indexes for table `account_link_master`
--
ALTER TABLE `account_link_master`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `account_master`
--
ALTER TABLE `account_master`
  ADD PRIMARY KEY (`id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `account_organization`
--
ALTER TABLE `account_organization`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `account_payment_details`
--
ALTER TABLE `account_payment_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `account_subscription_id` (`account_subscription_id`),
  ADD KEY `payment_method_id` (`payment_method_id`);

--
-- Indexes for table `account_sending_methods`
--
ALTER TABLE `account_sending_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `email_sending_method_id` (`email_sending_method_id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `account_subscription_details`
--
ALTER TABLE `account_subscription_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `coupon_id` (`coupon_id`),
  ADD KEY `next_subscription_id` (`next_subscription_id`),
  ADD KEY `payment_method_id` (`payment_method_id`);

--
-- Indexes for table `account_teams`
--
ALTER TABLE `account_teams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `owner_id` (`owner_id`),
  ADD KEY `managed_by` (`manager_id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `account_team_members`
--
ALTER TABLE `account_team_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_team_id` (`account_team_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `account_templates`
--
ALTER TABLE `account_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `account_template_folders`
--
ALTER TABLE `account_template_folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_template_id` (`account_template_id`),
  ADD KEY `account_folder_id` (`account_folder_id`);

--
-- Indexes for table `account_template_teams`
--
ALTER TABLE `account_template_teams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_template_id` (`account_template_id`),
  ADD KEY `account_team_id` (`account_team_id`);

--
-- Indexes for table `activity`
--
ALTER TABLE `activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `api_messages`
--
ALTER TABLE `api_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `app_constant_vars`
--
ALTER TABLE `app_constant_vars`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `branding`
--
ALTER TABLE `branding`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `campaign_links`
--
ALTER TABLE `campaign_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `campaign_id` (`campaign_id`),
  ADD KEY `campaign_stage_id` (`campaign_stage_id`),
  ADD KEY `campaign_sequence_id` (`campaign_sequence_id`),
  ADD KEY `account_link_id` (`account_link_id`);

--
-- Indexes for table `campaign_logs`
--
ALTER TABLE `campaign_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `campaign_id` (`campaign_id`),
  ADD KEY `campaign_stage_id` (`campaign_stage_id`),
  ADD KEY `campaign_sequence_id` (`campaign_sequence_id`);

--
-- Indexes for table `campaign_master`
--
ALTER TABLE `campaign_master`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `account_sending_method_id` (`account_sending_method_id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `campaign_sequences`
--
ALTER TABLE `campaign_sequences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `campaign_id` (`campaign_id`),
  ADD KEY `campaign_stage_id` (`campaign_stage_id`),
  ADD KEY `account_contact_id` (`account_contact_id`);

--
-- Indexes for table `campaign_stages`
--
ALTER TABLE `campaign_stages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `campaign_id` (`campaign_id`),
  ADD KEY `account_template_id` (`account_template_id`);

--
-- Indexes for table `campaign_track_history`
--
ALTER TABLE `campaign_track_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `campaign_sequence_id` (`campaign_sequence_id`),
  ADD KEY `account_link_id` (`account_link_id`);

--
-- Indexes for table `coupon_master`
--
ALTER TABLE `coupon_master`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `document_folders`
--
ALTER TABLE `document_folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `account_folder_id` (`account_folder_id`);

--
-- Indexes for table `document_links`
--
ALTER TABLE `document_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `source_id` (`source_id`),
  ADD KEY `account_contact_id` (`account_contact_id`);

--
-- Indexes for table `document_link_files`
--
ALTER TABLE `document_link_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_link_id` (`document_link_id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `account_folder_id` (`account_folder_id`);

--
-- Indexes for table `document_master`
--
ALTER TABLE `document_master`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `document_source_id` (`document_source_id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `document_source_master`
--
ALTER TABLE `document_source_master`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `document_teams`
--
ALTER TABLE `document_teams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `account_team_id` (`account_team_id`);

--
-- Indexes for table `email_links`
--
ALTER TABLE `email_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `email_id` (`email_id`),
  ADD KEY `account_contact_id` (`account_contact_id`),
  ADD KEY `account_link_id` (`account_link_id`);

--
-- Indexes for table `email_master`
--
ALTER TABLE `email_master`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `account_template_id` (`account_template_id`),
  ADD KEY `account_sending_method_id` (`account_sending_method_id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `email_recipients`
--
ALTER TABLE `email_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `email_id` (`email_id`),
  ADD KEY `account_contact_id` (`account_contact_id`);

--
-- Indexes for table `email_sending_method_master`
--
ALTER TABLE `email_sending_method_master`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_track_history`
--
ALTER TABLE `email_track_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email_recipient_id` (`email_recipient_id`),
  ADD KEY `account_link_id` (`account_link_id`);

--
-- Indexes for table `payment_method_master`
--
ALTER TABLE `payment_method_master`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `plan_master`
--
ALTER TABLE `plan_master`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `resource_master`
--
ALTER TABLE `resource_master`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `role_default_resources`
--
ALTER TABLE `role_default_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `resource_id` (`resource_id`);

--
-- Indexes for table `role_master`
--
ALTER TABLE `role_master`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `social_login_master`
--
ALTER TABLE `social_login_master`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `source_master`
--
ALTER TABLE `source_master`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_actions`
--
ALTER TABLE `user_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `source_id` (`source_id`),
  ADD KEY `resource_id` (`resource_id`);

--
-- Indexes for table `user_authentication_tokens`
--
ALTER TABLE `user_authentication_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `user_invitations`
--
ALTER TABLE `user_invitations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_master`
--
ALTER TABLE `user_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `user_type_id` (`user_type_id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `user_members`
--
ALTER TABLE `user_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `has_access_of` (`has_access_of`);

--
-- Indexes for table `user_pass_reset_requests`
--
ALTER TABLE `user_pass_reset_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_resources`
--
ALTER TABLE `user_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `resource_id` (`resource_id`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `app_constant_var_id` (`app_constant_var_id`);

--
-- Indexes for table `user_social_login`
--
ALTER TABLE `user_social_login`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `social_login_id` (`social_login_id`);

--
-- Indexes for table `user_type_master`
--
ALTER TABLE `user_type_master`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `valid_plan_coupons`
--
ALTER TABLE `valid_plan_coupons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `coupon_id` (`coupon_id`);

--
-- Indexes for table `webhooks`
--
ALTER TABLE `webhooks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `resource_id` (`resource_id`),
  ADD KEY `source_id` (`source_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_billing_master`
--
ALTER TABLE `account_billing_master`
  MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `account_companies`
--
ALTER TABLE `account_companies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `account_contacts`
--
ALTER TABLE `account_contacts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `account_folders`
--
ALTER TABLE `account_folders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `account_folder_teams`
--
ALTER TABLE `account_folder_teams`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `account_link_master`
--
ALTER TABLE `account_link_master`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `account_master`
--
ALTER TABLE `account_master`
  MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
--
-- AUTO_INCREMENT for table `account_organization`
--
ALTER TABLE `account_organization`
  MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `account_payment_details`
--
ALTER TABLE `account_payment_details`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `account_sending_methods`
--
ALTER TABLE `account_sending_methods`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `account_subscription_details`
--
ALTER TABLE `account_subscription_details`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `account_teams`
--
ALTER TABLE `account_teams`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `account_team_members`
--
ALTER TABLE `account_team_members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `account_templates`
--
ALTER TABLE `account_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `account_template_folders`
--
ALTER TABLE `account_template_folders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `account_template_teams`
--
ALTER TABLE `account_template_teams`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `activity`
--
ALTER TABLE `activity`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `api_messages`
--
ALTER TABLE `api_messages`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;
--
-- AUTO_INCREMENT for table `app_constant_vars`
--
ALTER TABLE `app_constant_vars`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;
--
-- AUTO_INCREMENT for table `branding`
--
ALTER TABLE `branding`
  MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `campaign_links`
--
ALTER TABLE `campaign_links`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `campaign_logs`
--
ALTER TABLE `campaign_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `campaign_master`
--
ALTER TABLE `campaign_master`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `campaign_sequences`
--
ALTER TABLE `campaign_sequences`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `campaign_stages`
--
ALTER TABLE `campaign_stages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `campaign_track_history`
--
ALTER TABLE `campaign_track_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `coupon_master`
--
ALTER TABLE `coupon_master`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `document_folders`
--
ALTER TABLE `document_folders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `document_links`
--
ALTER TABLE `document_links`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `document_link_files`
--
ALTER TABLE `document_link_files`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `document_master`
--
ALTER TABLE `document_master`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `document_source_master`
--
ALTER TABLE `document_source_master`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
--
-- AUTO_INCREMENT for table `document_teams`
--
ALTER TABLE `document_teams`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `email_links`
--
ALTER TABLE `email_links`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `email_master`
--
ALTER TABLE `email_master`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `email_recipients`
--
ALTER TABLE `email_recipients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `email_sending_method_master`
--
ALTER TABLE `email_sending_method_master`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `email_track_history`
--
ALTER TABLE `email_track_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `payment_method_master`
--
ALTER TABLE `payment_method_master`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `plan_master`
--
ALTER TABLE `plan_master`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
--
-- AUTO_INCREMENT for table `resource_master`
--
ALTER TABLE `resource_master`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;
--
-- AUTO_INCREMENT for table `role_default_resources`
--
ALTER TABLE `role_default_resources`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=713;
--
-- AUTO_INCREMENT for table `role_master`
--
ALTER TABLE `role_master`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
--
-- AUTO_INCREMENT for table `social_login_master`
--
ALTER TABLE `social_login_master`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `source_master`
--
ALTER TABLE `source_master`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
--
-- AUTO_INCREMENT for table `user_actions`
--
ALTER TABLE `user_actions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `user_authentication_tokens`
--
ALTER TABLE `user_authentication_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
--
-- AUTO_INCREMENT for table `user_invitations`
--
ALTER TABLE `user_invitations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `user_master`
--
ALTER TABLE `user_master`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
--
-- AUTO_INCREMENT for table `user_members`
--
ALTER TABLE `user_members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `user_pass_reset_requests`
--
ALTER TABLE `user_pass_reset_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `user_resources`
--
ALTER TABLE `user_resources`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;
--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `user_social_login`
--
ALTER TABLE `user_social_login`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `user_type_master`
--
ALTER TABLE `user_type_master`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
--
-- AUTO_INCREMENT for table `valid_plan_coupons`
--
ALTER TABLE `valid_plan_coupons`
  MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `webhooks`
--
ALTER TABLE `webhooks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `account_billing_master`
--
ALTER TABLE `account_billing_master`
  ADD CONSTRAINT `account_billing_master_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_billing_master_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `plan_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_billing_master_ibfk_3` FOREIGN KEY (`current_subscription_id`) REFERENCES `account_subscription_details` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `account_companies`
--
ALTER TABLE `account_companies`
  ADD CONSTRAINT `account_companies_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_companies_ibfk_2` FOREIGN KEY (`source_id`) REFERENCES `source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `account_contacts`
--
ALTER TABLE `account_contacts`
  ADD CONSTRAINT `account_contacts_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_contacts_ibfk_2` FOREIGN KEY (`account_company_id`) REFERENCES `account_companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_contacts_ibfk_3` FOREIGN KEY (`source_id`) REFERENCES `source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `account_folders`
--
ALTER TABLE `account_folders`
  ADD CONSTRAINT `account_folders_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_folders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_folders_ibfk_3` FOREIGN KEY (`source_id`) REFERENCES `source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `account_folder_teams`
--
ALTER TABLE `account_folder_teams`
  ADD CONSTRAINT `account_folder_teams_ibfk_1` FOREIGN KEY (`account_folder_id`) REFERENCES `account_folders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_folder_teams_ibfk_2` FOREIGN KEY (`account_team_id`) REFERENCES `account_teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `account_invoice_details`
--
ALTER TABLE `account_invoice_details`
  ADD CONSTRAINT `account_invoice_details_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_invoice_details_ibfk_2` FOREIGN KEY (`account_subscription_id`) REFERENCES `account_subscription_details` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_invoice_details_ibfk_3` FOREIGN KEY (`account_payment_id`) REFERENCES `account_payment_details` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `account_link_master`
--
ALTER TABLE `account_link_master`
  ADD CONSTRAINT `account_link_master_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `account_master`
--
ALTER TABLE `account_master`
  ADD CONSTRAINT `account_master_ibfk_1` FOREIGN KEY (`source_id`) REFERENCES `source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `account_organization`
--
ALTER TABLE `account_organization`
  ADD CONSTRAINT `account_organization_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `account_payment_details`
--
ALTER TABLE `account_payment_details`
  ADD CONSTRAINT `account_payment_details_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_payment_details_ibfk_2` FOREIGN KEY (`account_subscription_id`) REFERENCES `account_subscription_details` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_payment_details_ibfk_3` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_method_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `account_sending_methods`
--
ALTER TABLE `account_sending_methods`
  ADD CONSTRAINT `account_sending_methods_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_sending_methods_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_sending_methods_ibfk_3` FOREIGN KEY (`email_sending_method_id`) REFERENCES `email_sending_method_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_sending_methods_ibfk_4` FOREIGN KEY (`source_id`) REFERENCES `source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `account_subscription_details`
--
ALTER TABLE `account_subscription_details`
  ADD CONSTRAINT `account_subscription_details_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_subscription_details_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `plan_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_subscription_details_ibfk_3` FOREIGN KEY (`coupon_id`) REFERENCES `coupon_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_subscription_details_ibfk_4` FOREIGN KEY (`next_subscription_id`) REFERENCES `account_subscription_details` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_subscription_details_ibfk_5` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_method_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `account_teams`
--
ALTER TABLE `account_teams`
  ADD CONSTRAINT `account_teams_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_teams_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_teams_ibfk_3` FOREIGN KEY (`owner_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_teams_ibfk_4` FOREIGN KEY (`manager_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_teams_ibfk_5` FOREIGN KEY (`source_id`) REFERENCES `source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `account_team_members`
--
ALTER TABLE `account_team_members`
  ADD CONSTRAINT `account_team_members_ibfk_1` FOREIGN KEY (`account_team_id`) REFERENCES `account_teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_team_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `account_templates`
--
ALTER TABLE `account_templates`
  ADD CONSTRAINT `account_templates_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_templates_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_templates_ibfk_3` FOREIGN KEY (`source_id`) REFERENCES `source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `account_template_folders`
--
ALTER TABLE `account_template_folders`
  ADD CONSTRAINT `account_template_folders_ibfk_1` FOREIGN KEY (`account_template_id`) REFERENCES `account_templates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_template_folders_ibfk_2` FOREIGN KEY (`account_folder_id`) REFERENCES `account_folders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `account_template_teams`
--
ALTER TABLE `account_template_teams`
  ADD CONSTRAINT `account_template_teams_ibfk_1` FOREIGN KEY (`account_template_id`) REFERENCES `account_templates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `account_template_teams_ibfk_2` FOREIGN KEY (`account_team_id`) REFERENCES `account_teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `activity`
--
ALTER TABLE `activity`
  ADD CONSTRAINT `activity_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `activity_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `activity_ibfk_3` FOREIGN KEY (`source_id`) REFERENCES `source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `branding`
--
ALTER TABLE `branding`
  ADD CONSTRAINT `branding_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `campaign_links`
--
ALTER TABLE `campaign_links`
  ADD CONSTRAINT `campaign_links_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `campaign_links_ibfk_2` FOREIGN KEY (`campaign_id`) REFERENCES `campaign_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `campaign_links_ibfk_3` FOREIGN KEY (`campaign_stage_id`) REFERENCES `campaign_stages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `campaign_links_ibfk_4` FOREIGN KEY (`campaign_sequence_id`) REFERENCES `campaign_sequences` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `campaign_links_ibfk_5` FOREIGN KEY (`account_link_id`) REFERENCES `account_link_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `campaign_logs`
--
ALTER TABLE `campaign_logs`
  ADD CONSTRAINT `campaign_logs_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaign_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `campaign_logs_ibfk_2` FOREIGN KEY (`campaign_stage_id`) REFERENCES `campaign_stages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `campaign_logs_ibfk_3` FOREIGN KEY (`campaign_sequence_id`) REFERENCES `campaign_sequences` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `campaign_master`
--
ALTER TABLE `campaign_master`
  ADD CONSTRAINT `campaign_master_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `campaign_master_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `campaign_master_ibfk_3` FOREIGN KEY (`account_sending_method_id`) REFERENCES `account_sending_methods` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `campaign_master_ibfk_4` FOREIGN KEY (`source_id`) REFERENCES `source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `campaign_sequences`
--
ALTER TABLE `campaign_sequences`
  ADD CONSTRAINT `campaign_sequences_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaign_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `campaign_sequences_ibfk_2` FOREIGN KEY (`campaign_stage_id`) REFERENCES `campaign_stages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `campaign_sequences_ibfk_3` FOREIGN KEY (`account_contact_id`) REFERENCES `account_contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `campaign_stages`
--
ALTER TABLE `campaign_stages`
  ADD CONSTRAINT `campaign_stages_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `campaign_stages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `campaign_stages_ibfk_3` FOREIGN KEY (`campaign_id`) REFERENCES `campaign_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `campaign_stages_ibfk_4` FOREIGN KEY (`account_template_id`) REFERENCES `account_templates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `campaign_track_history`
--
ALTER TABLE `campaign_track_history`
  ADD CONSTRAINT `campaign_track_history_ibfk_1` FOREIGN KEY (`campaign_sequence_id`) REFERENCES `campaign_sequences` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `campaign_track_history_ibfk_2` FOREIGN KEY (`account_link_id`) REFERENCES `account_link_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `document_folders`
--
ALTER TABLE `document_folders`
  ADD CONSTRAINT `document_folders_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `document_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `document_folders_ibfk_2` FOREIGN KEY (`account_folder_id`) REFERENCES `account_folders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `document_links`
--
ALTER TABLE `document_links`
  ADD CONSTRAINT `document_links_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `document_links_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `document_links_ibfk_3` FOREIGN KEY (`source_id`) REFERENCES `source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `document_link_files`
--
ALTER TABLE `document_link_files`
  ADD CONSTRAINT `document_link_files_ibfk_1` FOREIGN KEY (`document_link_id`) REFERENCES `document_links` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `document_link_files_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `document_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `document_link_files_ibfk_3` FOREIGN KEY (`account_folder_id`) REFERENCES `account_folders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `document_master`
--
ALTER TABLE `document_master`
  ADD CONSTRAINT `document_master_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `document_master_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `document_master_ibfk_3` FOREIGN KEY (`document_source_id`) REFERENCES `document_source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `document_master_ibfk_4` FOREIGN KEY (`source_id`) REFERENCES `source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `document_teams`
--
ALTER TABLE `document_teams`
  ADD CONSTRAINT `document_teams_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `document_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `document_teams_ibfk_2` FOREIGN KEY (`account_team_id`) REFERENCES `account_teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `email_links`
--
ALTER TABLE `email_links`
  ADD CONSTRAINT `email_links_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `email_links_ibfk_2` FOREIGN KEY (`email_id`) REFERENCES `email_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `email_links_ibfk_3` FOREIGN KEY (`account_contact_id`) REFERENCES `account_contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `email_links_ibfk_4` FOREIGN KEY (`account_link_id`) REFERENCES `account_link_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `email_master`
--
ALTER TABLE `email_master`
  ADD CONSTRAINT `email_master_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `email_master_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `email_master_ibfk_3` FOREIGN KEY (`account_template_id`) REFERENCES `account_templates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `email_master_ibfk_4` FOREIGN KEY (`account_sending_method_id`) REFERENCES `account_sending_methods` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `email_master_ibfk_5` FOREIGN KEY (`source_id`) REFERENCES `source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `email_recipients`
--
ALTER TABLE `email_recipients`
  ADD CONSTRAINT `email_recipients_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `email_recipients_ibfk_2` FOREIGN KEY (`email_id`) REFERENCES `email_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `email_recipients_ibfk_3` FOREIGN KEY (`account_contact_id`) REFERENCES `account_contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `email_track_history`
--
ALTER TABLE `email_track_history`
  ADD CONSTRAINT `email_track_history_ibfk_1` FOREIGN KEY (`email_recipient_id`) REFERENCES `email_recipients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `email_track_history_ibfk_2` FOREIGN KEY (`account_link_id`) REFERENCES `account_link_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `resource_master`
--
ALTER TABLE `resource_master`
  ADD CONSTRAINT `resource_master_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `resource_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `role_default_resources`
--
ALTER TABLE `role_default_resources`
  ADD CONSTRAINT `role_default_resources_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `role_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `role_default_resources_ibfk_2` FOREIGN KEY (`resource_id`) REFERENCES `resource_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `role_master`
--
ALTER TABLE `role_master`
  ADD CONSTRAINT `role_master_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `role_master_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `role_master_ibfk_3` FOREIGN KEY (`source_id`) REFERENCES `source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_actions`
--
ALTER TABLE `user_actions`
  ADD CONSTRAINT `user_actions_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_actions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_actions_ibfk_3` FOREIGN KEY (`source_id`) REFERENCES `source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_actions_ibfk_4` FOREIGN KEY (`resource_id`) REFERENCES `resource_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_authentication_tokens`
--
ALTER TABLE `user_authentication_tokens`
  ADD CONSTRAINT `user_authentication_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_authentication_tokens_ibfk_2` FOREIGN KEY (`source_id`) REFERENCES `source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_invitations`
--
ALTER TABLE `user_invitations`
  ADD CONSTRAINT `user_invitations_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_invitations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_master`
--
ALTER TABLE `user_master`
  ADD CONSTRAINT `user_master_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_master_ibfk_2` FOREIGN KEY (`user_type_id`) REFERENCES `user_type_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_master_ibfk_3` FOREIGN KEY (`role_id`) REFERENCES `role_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_master_ibfk_4` FOREIGN KEY (`source_id`) REFERENCES `source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_members`
--
ALTER TABLE `user_members`
  ADD CONSTRAINT `user_members_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_members_ibfk_2` FOREIGN KEY (`has_access_of`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_pass_reset_requests`
--
ALTER TABLE `user_pass_reset_requests`
  ADD CONSTRAINT `user_pass_reset_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_resources`
--
ALTER TABLE `user_resources`
  ADD CONSTRAINT `user_resources_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_resources_ibfk_2` FOREIGN KEY (`resource_id`) REFERENCES `resource_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_settings_ibfk_2` FOREIGN KEY (`app_constant_var_id`) REFERENCES `app_constant_vars` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_social_login`
--
ALTER TABLE `user_social_login`
  ADD CONSTRAINT `user_social_login_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_social_login_ibfk_2` FOREIGN KEY (`social_login_id`) REFERENCES `social_login_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `valid_plan_coupons`
--
ALTER TABLE `valid_plan_coupons`
  ADD CONSTRAINT `valid_plan_coupons_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `plan_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `valid_plan_coupons_ibfk_2` FOREIGN KEY (`coupon_id`) REFERENCES `coupon_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `webhooks`
--
ALTER TABLE `webhooks`
  ADD CONSTRAINT `webhooks_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `webhooks_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `webhooks_ibfk_3` FOREIGN KEY (`resource_id`) REFERENCES `resource_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `webhooks_ibfk_4` FOREIGN KEY (`source_id`) REFERENCES `source_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
