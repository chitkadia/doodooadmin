-- --------------------------------
-- Database Changes 07 March 2018
-- --------------------------------
ALTER TABLE `activity` ADD `sub_record_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER `record_id`;

UPDATE `action_master` SET `notify_template` = '{contact} clicked <a href=\"{link}\" target=\"_blank\">Link</a> from mail {entity}' WHERE `action_master`.`id` = 7;

UPDATE `action_master` SET `notify_template` = '{contact} clicked <a href=\"{link}\" target=\"_blank\">Link</a> from stage {stage} email of campaign {entity}' WHERE `action_master`.`id` = 159;
-------------------------------------------
api_messages table : 07_MAR_2017
-------------------------------------------
INSERT INTO `api_messages` (`id`, `code`, `http_code`, `error_code`, `error_message`, `status`, `created`, `modified`) VALUES (NULL, 'CAMPAIGN_QUOTA_OVER', '402', '1028', 'Your create campaign quota is over as per your current plan. Please upgrade to PLUS plan to access this feature.', '1', '0', '0');
-------------------------------------------
api_messages table : 08_MAR_2017
-------------------------------------------
INSERT INTO `api_messages` (`id`, `code`, `http_code`, `error_code`, `error_message`, `status`, `created`, `modified`) VALUES (NULL, 'BULK_CAMPAIGN_NOT_ALLOWED', '402', '1029', 'Your current plan does not support bulk campaign. Please upgrade to PLUS plan to access this feature.', '1', '0', '0')
-------------------------------------------
app_change_log table : 08_MAR_2017
-------------------------------------------
CREATE TABLE `app_change_log` (
  `id` int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `release_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'release version ',
  `package_path` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'package storage path',
  `release_note` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Release Note (Changes in particular version)',
  `source` smallint(6) NOT NULL COMMENT '1: WEB_APP, 2: CHROME_PLUGIN, 3: OUTLOOK_PLUGIN',
  `is_critical` tinyint(4) NOT NULL COMMENT 'Set true if update is critical',
  `is_logout_required` tinyint(4) NOT NULL COMMENT 'Set it true if it affects api and we want to clear all user login session.',
  `status` tinyint(1) NOT NULL COMMENT '0 : Inactive, 1 : Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-------------------------------------------
resource_master table : 08_MAR_2017
-------------------------------------------
INSERT INTO `resource_master` (`id`, `resource_name`, `short_info`, `api_endpoint`, `parent_id`, `position`, `show_in_roles`, `show_in_webhooks`, `is_always_assigned`, `is_secured`, `status`, `created`, `modified`) VALUES (NULL, 'AppChangeLog', 'Use to store release details.', '[\"/appchangelog/get-package-details\"]\r\n', NULL, '31', '0', '0', '1', '0', '1', '1520426665', '1520426665');

-------------------------------------------
api_messages table : 22_MAR_2017
-------------------------------------------


INSERT INTO `api_messages` (`id`, `code`, `http_code`, `error_code`, `error_message`, `status`, `created`, `modified`) VALUES (NULL, 'MULTI_STAGE_NOT_ALLOWED', '402', '1030', 'Your current plan does not support multi stage campaign. Please upgrade to PLUS plan to access this feature.', '1', '1521727989', '1521727989')