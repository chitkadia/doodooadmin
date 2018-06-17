-- ----------------------------------
-- Database Changes 03 October 2017
-- ----------------------------------

ALTER TABLE `email_master` CHANGE `snooze_notifications` `snooze_notifications` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-No, 1-Yes';

-- ----------------------------------
-- Database Changes 04 October 2017
-- ----------------------------------

INSERT INTO `api_messages` (`id`, `code`, `http_code`, `error_code`, `error_message`, `status`, `created`, `modified`) VALUES (NULL, 'MAIL_ALREADY_SENT', '400', '2121', 'This email is already sent.', '1', '0', '0');


-- ----------------------------------
-- Database Changes 12 October 2017
-- ----------------------------------

INSERT INTO `api_messages` (`id`, `code`, `http_code`, `error_code`, `error_message`, `status`, `created`, `modified`) VALUES (NULL, 'CSV_HEADER_ROW_MISSING', '400', '2141', 'CSV Header row can not be empty', '1', '0', '0'), (NULL, 'CSV_EMAIL_COLUMN_MISSING', '400', '2142', 'CSV file must contain email column', '1', '0', '0');

INSERT INTO `api_messages` (`id`, `code`, `http_code`, `error_code`, `error_message`, `status`, `created`, `modified`) VALUES (NULL, 'CSV_COULMN_LIMIT_EXCEED', '400', '2143', 'CSV file should not contain more than 20 columns', '1', '0', '0'), (NULL, 'CSV_NO_CONTACTS_FOUND', '400', '2144', 'No contacts found in CSV', '1', '0', '0'), (NULL, 'CSV_FILE_UPLOAD_FAIL', '500', '2145', 'CSV file could not be uploaded', '1', '0', '0'), (NULL, 'CSV_INVALID_FILE_TYPE', '415', '2146', 'CSV file could not be processed', '1', '0', '0');


-- ----------------------------------
-- Database Changes 13 October 2017
-- ----------------------------------

UPDATE `resource_master` SET `api_endpoint` = '["/campaigns/send-test-mail","/campaigns/preview-csv","/campaigns/import-csv-data"]' WHERE `resource_master`.`id` = 33;


UPDATE `resource_master` SET `api_endpoint` = '["/campaigns/send-test-mail","/campaigns/preview-csv","/campaigns/import-csv-data"]' WHERE `resource_master`.`id` = 34;

-- ----------------------------------
-- Database Changes 26 October 2017
-- ----------------------------------

UPDATE `resource_master` SET `api_endpoint` = '["/me", "/me/update-profile", "/me/change-password", "/me/set-password", "/me/update-preference", "/me/logout"]' WHERE `resource_master`.`id` = 5;

-- ----------------------------------
-- Database Changes 28 October 2017
-- ----------------------------------
ALTER TABLE `document_master` CHANGE `status` `status` TINYINT(2) UNSIGNED NOT NULL DEFAULT '3' COMMENT '0-Inactive, 1-Active, 2-Deleted, 3-In Process, 4-Failed Processing';

-- --------------------------------
-- Database Changes 30 July 2017
-- --------------------------------

INSERT INTO app_constant_vars (code, name, val, created, modified) VALUES 
('DEFAULT_LIST_PER_PAGE', 'Per Page', '25', '1496645339', '1496645339');

-- --------------------------------
-- Database Changes 31 July 2017
-- --------------------------------

CREATE TABLE `user_members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `has_access_of` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `user_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `has_access_of` (`has_access_of`);

ALTER TABLE `user_members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `user_members`
  ADD CONSTRAINT `user_members_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_members_ibfk_2` FOREIGN KEY (`has_access_of`) REFERENCES `user_master` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('FP_ACCOUNT_NOT_FOUND', 404, 2071, 'Could not find account with given details.', 1496645339, 1496645339);

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('BAD_REQUEST', 400, 1021, 'Bad request. Requested URL is invalid.', 1496645339, 1496645339);

-- --------------------------------
-- Database Changes 02 August 2017
-- --------------------------------

ALTER TABLE  `user_master` DROP  `has_access_of` ;

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('INVALID_FP_RESET_CODE', 400, 2072, 'Reset password url is not valid.', 1496645339, 1496645339);

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('FP_RESET_CODE_EXPIRE', 400, 2073, 'Reset password link is expired. Please request for another reset password link.', 1496645339, 1496645339);

-- --------------------------------
-- Database Changes 02 August 2017
-- --------------------------------

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('INVALID_PREFERENCE_UPDATE', 400, 2091, 'Trying to update invalid setting which is not supported.', 1496645339, 1496645339);

-- --------------------------------
-- Database Changes 03 August 2017
-- --------------------------------

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('INVALID_CURRENT_PASS', 400, 2095, 'Current password is incorrect. Please enter your correct current password.', 1496645339, 1496645339);

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('ERROR_IMAGE_UPLOAD', 500, 1022, 'Error while uploading image: ', 1496645339, 1496645339);

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('INVALID_CONNECTION_METHOD', 400, 2101, 'Incorrect value passed for connection method.', 1496645339, 1496645339);

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('INVALID_STATUS_VALUE', 400, 1023, 'Incorrect value passed for status change.', 1496645339, 1496645339);

-- --------------------------------
-- Database Changes 04 August 2017
-- --------------------------------

UPDATE `resource_master` SET `api_endpoint` = '[\"/user/signup\", \"/user/verify/{code}\", \"/user/resend-verification/{code}\",\n \"/user/resend-verification\"]' WHERE `resource_master`.`id` = 2;

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('USER_NOT_FOUND', 400, 2033, 'User not found with given username or email.', 1496645339, 1496645339);

UPDATE `resource_master` SET `api_endpoint` = '[\"/me\", \"/me/update-profile\", \"/me/change-password\", \"/me/update-preference\", \"/me/update-signup-profile\", \"/me/logout\"]' WHERE `resource_master`.`id` = 5;

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('INVALID_SOCIAL_ACCOUNT', 400, 2002, 'Invalid value passed for social media account.', 1496645339, 1496645339);

ALTER TABLE `user_authentication_tokens` CHANGE `user_resources` `user_resources` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'Array of resources accessible to user';

-- --------------------------------
-- Database Changes 05 August 2017
-- --------------------------------

UPDATE `app_constant_vars` SET `val` = '9' WHERE `app_constant_vars`.`id` = 9;
UPDATE `app_constant_vars` SET `val` = '10' WHERE `app_constant_vars`.`id` = 10;
UPDATE `app_constant_vars` SET `val` = '11' WHERE `app_constant_vars`.`id` = 11;
UPDATE `app_constant_vars` SET `val` = '12' WHERE `app_constant_vars`.`id` = 12;
UPDATE `app_constant_vars` SET `val` = '13' WHERE `app_constant_vars`.`id` = 13;
UPDATE `app_constant_vars` SET `val` = '14' WHERE `app_constant_vars`.`id` = 14;
UPDATE `app_constant_vars` SET `val` = '15' WHERE `app_constant_vars`.`id` = 15;
UPDATE `app_constant_vars` SET `val` = '16' WHERE `app_constant_vars`.`id` = 16;
UPDATE `app_constant_vars` SET `val` = '17' WHERE `app_constant_vars`.`id` = 17;

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('UP_UPDATE_NOT_ALLOWED', 400, 2096, 'Username and password are already assigned to this account.', 1496645339, 1496645339);

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('USERNAME_ALREADY_TAKEN', 400, 2097, 'This username is already taken. Please enter different username.', 1496645339, 1496645339);

-- --------------------------------
-- Database Changes 06 August 2017
-- --------------------------------

ALTER TABLE `account_sending_methods` ADD `public` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes' AFTER `total_mail_bounced`;

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('INVALID_SOCIAL_TOKEN', 400, 2003, 'Token is invalid. Please try again later.', 1496645339, 1496645339);

UPDATE `resource_master` SET `api_endpoint` = '[\"/mail-accounts/list\", \"/mail-accounts/{id}/view\"]' WHERE `resource_master`.`id` = 24;
UPDATE `resource_master` SET `api_endpoint` = '[\"/emails/list\", \"/emails/{id}/view\"]' WHERE `resource_master`.`id` = 28;
UPDATE `resource_master` SET `api_endpoint` = '[\"/campaigns/list\", \"/campaigns/{id}/view\", \"/campaigns/{id}/view-stage/{stage_id}\", \"/campaigns/{id}/sequence-mail/{seq_id}\", \"/campaigns/{id}/export-data/{stage_id}\"]' WHERE `resource_master`.`id` = 32;
UPDATE `resource_master` SET `api_endpoint` = '[\"/documents/list\", \"/documents/{id}/view\", \"/documents/{id}/sharing\"]' WHERE `resource_master`.`id` = 36;
UPDATE `resource_master` SET `api_endpoint` = '[\"/folders/list\", \"/folders/{id}/view\", \"/folders/{id}/sharing\"]' WHERE `resource_master`.`id` = 41;
UPDATE `resource_master` SET `api_endpoint` = '[\"/links/list\", \"/links/{id}/view\"]' WHERE `resource_master`.`id` = 46;
UPDATE `resource_master` SET `api_endpoint` = '[\"/templates/list\", \"/templates/{id}/view\", \"/templates/{id}/sharing\"]' WHERE `resource_master`.`id` = 50;
UPDATE `resource_master` SET `api_endpoint` = '[\"/contacts/list\", \"/contacts/{id}/view\", \"/contacts/{id}/feed\"]' WHERE `resource_master`.`id` = 55;
UPDATE `resource_master` SET `api_endpoint` = '[\"/company/list\", \"/company/{id}/view\", \"/company/{id}/feed\", \"/company/{id}/contacts\"]' WHERE `resource_master`.`id` = 59;
UPDATE `resource_master` SET `api_endpoint` = '[\"/teams/list\", \"/teams/{id}/view\"]' WHERE `resource_master`.`id` = 63;
UPDATE `resource_master` SET `api_endpoint` = '[\"/members/list\", \"/members/{id}/view\", \"/members/{id}/resend-invitation\", \"/members/{id}/activity\", \"/members/{id}/resources\"]' WHERE `resource_master`.`id` = 67;
UPDATE `resource_master` SET `api_endpoint` = '[\"/roles/list\", \"/roles/{id}/view\", \"/roles/resources/list\"]' WHERE `resource_master`.`id` = 71;
UPDATE `resource_master` SET `api_endpoint` = '[\"/web-hooks/list\", \"/web-hooks/{id}/view\"]' WHERE `resource_master`.`id` = 81;

-- --------------------------------
-- Database Changes 08 August 2017
-- --------------------------------

ALTER TABLE `user_master` DROP `username`;

UPDATE `api_messages` SET `error_message` = 'User with this email address already exists.' WHERE `api_messages`.`id` = 22;

UPDATE `api_messages` SET `error_message` = 'User not found with given email address.' WHERE `api_messages`.`id` = 34;

-- --------------------------------
-- Database Changes 10 August 2017
-- --------------------------------

UPDATE `resource_master` SET `api_endpoint` = '[\"/me\", \"/me/update-profile\", \"/me/change-password\", \"/me/update-preference\", \"/me/logout\"]' WHERE `resource_master`.`id` = 5;

INSERT INTO `resource_master` (`id`, `resource_name`, `short_info`, `api_endpoint`, `parent_id`, `position`, `show_in_roles`, `show_in_webhooks`, `is_always_assigned`, `is_secured`, `status`, `created`, `modified`) VALUES (NULL, 'List', 'Access to list services.', '["/list/my-resources", "/list/my-members"]', NULL, '28', '0', '0', '1', '1', '1', '1496645339', '1496645339');

-- --------------------------------
-- Database Changes 11 August 2017
-- --------------------------------

ALTER TABLE `account_sending_methods` ADD `last_connected_at` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp' AFTER `connection_info`;

ALTER TABLE `account_templates` ADD `total_mail_usage` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Used in number of emails' AFTER `content`, ADD `total_mail_open` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Number of email opens (unique)' AFTER `total_mail_usage`, ADD `total_campaign_usage` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Used in number of campaigns' AFTER `total_mail_open`, ADD `total_campaign_mails` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Number of recipients sent' AFTER `total_campaign_usage`, ADD `total_campaign_open` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Number of recipients opened (unique)' AFTER `total_campaign_mails`;

ALTER TABLE `account_templates` ADD `last_used_at` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'GMT+00:00 Timestamp' AFTER `total_campaign_open`;

UPDATE `resource_master` SET `api_endpoint` = '[\"/folders/{id}/update\", \"/folders/{id}/mark-as-public\"]' WHERE `resource_master`.`id` = 43;

UPDATE `resource_master` SET `api_endpoint` = '[\"/mail-accounts/create\", \"/mail-accounts/{id}/copy\", \"/mail-accounts/connect\", \"/mail-accounts/connect-verify\"]' WHERE `resource_master`.`id` = 25;

UPDATE `resource_master` SET `api_endpoint` = '[\"/list/my-resources\", \"/list/my-members\", \"/list/timezones\", \"/list/all-members\", \"/list/app-vars\"]' WHERE `resource_master`.`id` = 86;

-- --------------------------------
-- Database Changes 12 August 2017
-- --------------------------------

ALTER TABLE `role_master` CHANGE `status` `status` TINYINT(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Active, 2-Delete';

UPDATE `resource_master` SET `api_endpoint` = '[\"/roles/{id}/update\"]' WHERE `resource_master`.`id` = 73;

UPDATE `resource_master` SET `api_endpoint` = '[\"/me\", \"/me/update-profile\", \"/me/change-password\", \"/me/update-preference\", \"/me/resend-verification\", \"/me/logout\"]' WHERE `resource_master`.`id` = 5;

UPDATE `resource_master` SET `api_endpoint` = '[\"/teams/{id}/update\"]' WHERE `resource_master`.`id` = 65;

UPDATE `resource_master` SET `api_endpoint` = '[\"/me\", \"/me/update-profile\", \"/me/change-password\", \"/me/update-preference\", \"/me/logout\"]' WHERE `resource_master`.`id` = 5;

UPDATE `resource_master` SET `api_endpoint` = '[\"/user/signup\", \"/user/verify/{code}\", \"/user/resend-verification/{code}\", \"/user/resend-verification\", \"/me/resend-verification\"]' WHERE `resource_master`.`id` = 2;

-- --------------------------------
-- Database Changes 13 August 2017
-- --------------------------------

ALTER TABLE  `user_invitations` ADD  `invited_by` INT( 10 ) UNSIGNED NULL DEFAULT NULL COMMENT  'Invited by' AFTER  `user_id` ;

-- --------------------------------
-- Database Changes 14 August 2017
-- --------------------------------

-- This query was mistakenly fired
UPDATE  `resource_master` SET  `api_endpoint` =  '["/teams/invite"]' WHERE  `resource_master`.`id` = 64;

-- --------------------------------
-- Database Changes 15 August 2017
-- --------------------------------

ALTER TABLE  `user_master` CHANGE  `status`  `status` TINYINT( 2 ) UNSIGNED NOT NULL DEFAULT  '1' COMMENT '0-Inactive, 1-Active, 2-Deleted, 5-Removed';

INSERT INTO  `app_constant_vars` (`code`, `name`, `val`, `created`, `modified`) VALUES ('STATUS_REMOVED',  'Removed',  '5',  '1496645339',  '1496645339');

UPDATE `resource_master` SET `api_endpoint` = '["/teams/create", "/teams/copy"]' WHERE `resource_master`.`id` = 64;

UPDATE `resource_master` SET `api_endpoint` = '["/members/invite"]' WHERE `resource_master`.`id` = 68;

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('INVALID_INVITATION_CODE', 400, 2034, 'Invitation code is not valid.', 1496645339, 1496645339);

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('INVITE_ALREADY_ACCEPTED', 400, 2035, 'Invitation already accepted.', 1496645339, 1496645339);

UPDATE  `resource_master` SET  `api_endpoint` = '["/user/signup", "/user/verify/{code}", "/user/resend-verification/{code}", "/user/resend-verification", "/me/resend-verification", "/user/check-invite/{code}", "/user/accept-invite/{code}"]' WHERE  `resource_master`.`id` =2;

UPDATE  `resource_master` SET  `api_endpoint` = '["/list/my-resources", "/list/my-members", "/list/timezones", "/list/all-members", "/list/app-vars", "/list/all-roles", "/list/all-teams"]' WHERE  `resource_master`.`id` =86;

-- --------------------------------
-- Database Changes 16 August 2017
-- --------------------------------

UPDATE `resource_master` SET `api_endpoint` = '[\"/list/my-resources\", \"/list/my-members\", \"/list/timezones\", \"/list/all-members\", \"/list/app-vars\", \"/list/all-roles\", \"/list/all-teams\", \"/list/all-contacts\", \"/list/email-accounts\", \"/list/all-templates\", \"/list/webhook-resources\"]' WHERE `resource_master`.`id` = 86;

-- --------------------------------
-- Database Changes 17 August 2017
-- --------------------------------

UPDATE `resource_master` SET `api_endpoint` = '[\"/list/my-resources\", \"/list/my-members\", \"/list/timezones\", \"/list/all-members\", \"/list/app-vars\", \"/list/all-roles\", \"/list/all-teams\", \"/list/all-contacts\", \"/list/email-accounts\", \"/list/all-templates\", \"/list/webhook-resources\", \"/list/all-role-resources\", \"/list/role/{id}/resources\"]' WHERE `resource_master`.`id` = 86;

-- --------------------------------
-- Database Changes 18 August 2017
-- --------------------------------

UPDATE `resource_master` SET `api_endpoint` = '[\"/user/login\", \"/user/loginwith/{method}\", \"/user/login-connect/{method}\"]' WHERE `resource_master`.`id` = 1;

-- --------------------------------
-- Database Changes 20 August 2017
-- --------------------------------

ALTER TABLE  `account_folders` ADD  `share_access` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  '{}' COMMENT  'Shared users access rights' AFTER  `public` ;

ALTER TABLE  `account_templates` ADD  `share_access` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  '{}' COMMENT  'Shared users access rights' AFTER  `public` ;

ALTER TABLE  `document_master` ADD  `share_access` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  '{}' COMMENT  'Shared users access rights' AFTER  `public` ;

UPDATE  `resource_master` SET  `api_endpoint` = '["/templates/folders/list", "/templates/folders/{id}/view", "/documents/folders/list", "/documents/folders/{id}/view"]' WHERE  `resource_master`.`id` = 41;

UPDATE  `resource_master` SET  `api_endpoint` =  '["/templates/folders/create", "/documents/folders/create"]' WHERE  `resource_master`.`id` = 42;

UPDATE  `resource_master` SET  `api_endpoint` = '["/templates/folders/{id}/update", "/templates/folders/{id}/mark-as-public", "/documents/folders/{id}/update", "/documents/folders/{id}/mark-as-public"]' WHERE `resource_master`.`id` = 43;

UPDATE  `resource_master` SET  `api_endpoint` =  '["/templates/folders/{id}/delete", "/documents/folders/{id}/delete"]' WHERE `resource_master`.`id` = 44;

UPDATE  `resource_master` SET  `api_endpoint` =  '["/templates/folders/{id}/share", "/documents/folders/{id}/share"]' WHERE `resource_master`.`id` = 45;

UPDATE  `resource_master` SET  `api_endpoint` =  '["/documents/list", "/documents/{id}/view"]' WHERE  `resource_master`.`id` = 36;

UPDATE  `resource_master` SET  `api_endpoint` =  '["/templates/list", "/templates/{id}/view"]' WHERE  `resource_master`.`id` = 50;

-- --------------------------------
-- Database Changes 22 August 2017
-- --------------------------------

UPDATE `resource_master` SET `api_endpoint` = '[\"/templates/folders/{id}/update\", \"/documents/folders/{id}/update\"]' WHERE `resource_master`.`id` = 43;

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('SHARED_EDIT_NA', 400, 1024, 'You are not allowed to edit this shared resource.', 1496645339, 1496645339);

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('SHARED_DELETE_NA', 400, 1025, 'You are not allowed to delete this shared resource.', 1496645339, 1496645339);

UPDATE `resource_master` SET `api_endpoint` = '[\"/list/my-resources\", \"/list/my-members\", \"/list/timezones\", \"/list/all-members\", \"/list/app-vars\", \"/list/all-roles\", \"/list/all-teams\", \"/list/all-contacts\", \"/list/email-accounts\", \"/list/all-templates\", \"/list/webhook-resources\", \"/list/all-role-resources\", \"/list/role/{id}/resources\", \"/list/my-teams\"]' WHERE `resource_master`.`id` = 86;

UPDATE `resource_master` SET `api_endpoint` = '[\"/templates/{id}/update\", \"/templates/{id}/status-update\", \"/templates/{id}/move\"]' WHERE `resource_master`.`id` = 52;

-- --------------------------------
-- Database Changes 24 August 2017
-- --------------------------------

INSERT INTO  `app_constant_vars` (`code`, `name`, `val`, `created`, `modified`) VALUES ('STATUS_DRAFT',  'Draft',  '0',  '1496645339',  '1496645339');

UPDATE `resource_master` SET `api_endpoint` = '[\"/teams/create\", \"/teams/{id}/copy\"]' WHERE `resource_master`.`id` = 64;

-- --------------------------------
-- Database Changes 25 August 2017
-- --------------------------------

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('INVALID_AUTH_TOKEN', 401, 1026, 'Invalid authentication token. Please login to your account.', 1496645339, 1496645339);

UPDATE `api_messages` SET `error_message` = 'Authentication token is missing. Please login to your account.' WHERE `api_messages`.`id` = 11;

-- --------------------------------
-- Database Changes 26 August 2017
-- --------------------------------

UPDATE `resource_master` SET `api_endpoint` = '[\"/list/my-resources\", \"/list/my-members\", \"/list/timezones\", \"/list/all-members\", \"/list/app-vars\", \"/list/all-roles\", \"/list/all-teams\", \"/list/all-contacts\", \"/list/email-accounts\", \"/list/all-templates\", \"/list/webhook-resources\", \"/list/all-role-resources\", \"/list/role/{id}/resources\", \"/list/my-teams\", \"/list/template-folders\"]' WHERE `resource_master`.`id` = 86;

INSERT INTO `resource_master` (`resource_name`, `short_info`, `api_endpoint`, `parent_id`, `position`, `show_in_roles`, `show_in_webhooks`, `is_always_assigned`, `is_secured`, `status`, `created`, `modified`) VALUES ('Get Data', 'Access to get details of resource.', '["/templates/{id}/get"]', NULL, '29', '0', '0', '1', '1', '1', '1496645339', '1496645339');

-- --------------------------------
-- Database Changes 29 August 2017
-- --------------------------------

UPDATE `api_messages` SET `error_message` = 'Verification url is not valid.' WHERE `api_messages`.`id` = 23;

-- --------------------------------
-- Database Changes 30 August 2017
-- --------------------------------

ALTER TABLE `document_master` CHANGE `status` `status` TINYINT(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0-Inactive, 1-Active, 2-Deleted, 3-In Process, 4-Failed Processing';

INSERT INTO `app_constant_vars` (`id`, `code`, `name`, `val`, `created`, `modified`) VALUES (NULL, 'STATUS_IN_PROCESS', 'In Processing', '3', '1496645339', '1496645339');

INSERT INTO `app_constant_vars` (`id`, `code`, `name`, `val`, `created`, `modified`) VALUES (NULL, 'STATUS_FAILED_PROCESS', 'Processing Failed', '4', '1496645339', '1496645339');

ALTER TABLE `document_master` ADD `locked` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes' AFTER `snooze_notifications`;

-- --------------------------------
-- Database Changes 01 September 2017
-- --------------------------------

ALTER TABLE `email_recipients` CHANGE `sent_message_id` `sent_message_id` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'Id of message sent (received from mail server)';

ALTER TABLE `webhooks` ADD `name` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `source_id`;

-- --------------------------------
-- Database Changes 26 October 2017
-- --------------------------------

UPDATE `resource_master` SET `api_endpoint` = '["/list/my-resources", "/list/my-members", "/list/timezones", "/list/all-members", "/list/app-vars", "/list/all-roles", "/list/all-teams", "/list/all-contacts", "/list/email-accounts", "/list/all-templates", "/list/webhook-resources", "/list/all-role-resources", "/list/role/{id}/resources", "/list/my-teams, "/list/all/plan"]' WHERE `resource_master`.`id` = 86;

INSERT INTO `api_messages` (`id`, `code`, `http_code`, `error_code`, `error_message`, `status`, `created`, `modified`) VALUES (NULL, 'INVALID_COUPON_CODE', '404', '2361', 'Invalid Coupon Code', '1', '1496645339', '1496645339');

UPDATE `app_constant_vars` SET `val` = '2' WHERE `app_constant_vars`.`id` = 26;

UPDATE `app_constant_vars` SET `val` = '4' WHERE `app_constant_vars`.`id` = 25;

UPDATE `app_constant_vars` SET `val` = '5' WHERE `app_constant_vars`.`id` = 24;

INSERT INTO `app_constant_vars` (`id`, `code`, `name`, `val`, `created`, `modified`) VALUES (NULL, 'PAYMENT_DOWNGRADE', 'Plan Downgrade', '3', '1496645339', '1496645339');

INSERT INTO `api_messages`(`id`, `code`, `http_code`, `error_code`, `error_message`, `status`, `created`, `modified`) VALUES (NULL,'STRIPE_RELATED','400','2362','','1','1496645339','1496645339');

INSERT INTO `api_messages`(`id`, `code`, `http_code`, `error_code`, `error_message`, `status`, `created`, `modified`) VALUES (NULL,'STRIPE_FETCH','404','2363','','1','1496645339','1496645339');

ALTER TABLE `account_payment_details` CHANGE `type` `type` TINYINT(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Plan Subscription, 2-Upgrade, 3-Downgrade, 4-Team Size Increase, 5-Recurring';

ALTER TABLE `account_subscription_details` ADD `type` TINYINT(2) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1-Plan Subscription, 2-Upgrade, 3-Downgrade, 4-Team Size Increase, 5-Recurring' AFTER `tp_customer_id`;

UPDATE `api_messages` SET `created` = '1496645339', `modified` = '1496645339'  WHERE `api_messages`.`id` = 44;

UPDATE `api_messages` SET `http_code` = '400' WHERE `api_messages`.`id` = 44;

INSERT INTO `api_messages` (`id`, `code`, `http_code`, `error_code`, `error_message`, `status`, `created`, `modified`) VALUES (NULL, 'STRIPE_NO_SUBSCRIPTION', '400', '2364', 'No active subscription. Please subscribe', '1', '1496645339', '1496645339');


-- --------------------------------
-- Database Changes 02 November 2017
-- --------------------------------

UPDATE `resource_master` SET `api_endpoint` = '["/account/plan/{code}/details", "/account/billing/check-coupon", "/account/buy", "/account/add-seat", "/account/upgrade", "/account/downgrade", "/account/subscription/cancel", "/account/information"]' WHERE `resource_master`.`id` = 75;

UPDATE `resource_master` SET `api_endpoint` = '["/account/billing/history", "/account/billing/{id}/view", "/account/billing/{id}/invoice", "/account/information"]' WHERE `resource_master`.`id` = 76;

-- --------------------------------
-- Database Changes 06 November 2017
-- --------------------------------

ALTER TABLE `campaign_master` ADD `is_bulk_campaign` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' AFTER `priority`;

-- --------------------------------
-- Database Changes 08 November 2017
-- --------------------------------

UPDATE `api_messages` SET `http_code` = '400', `error_message` = 'Invalid credentials.' WHERE `api_messages`.`id` = 21;

INSERT INTO api_messages (code, http_code, error_code, error_message, created, modified) VALUES 
('LOGIN_NO_ACCOUNT', 404, 2004, 'No account found.', 1496645339, 1496645339);