-------------------------------------------
campaign_stages table : 01_DEC_2017
-------------------------------------------
ALTER TABLE `campaign_stages` ADD `locked` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0-No, 1-Yes' AFTER `progress`;

-------------------------------------------
account_template_folders table : 29_DEC_2017
-------------------------------------------
ALTER TABLE `account_templates` 
ADD `account_folder_id` int(10) UNSIGNED DEFAULT NULL,
ADD KEY `account_folder_id` (`account_folder_id`);

UPDATE `account_templates` at SET `account_folder_id` = (select `account_folder_id` from `account_template_folders` atf where at.id = atf.account_template_id and atf.status<>2);

DROP TABLE `account_template_folders`;

-------------------------------------------
document_master_folders table : 30_DEC_2017
-------------------------------------------
ALTER TABLE `document_master` 
ADD `account_folder_id` int(10) UNSIGNED DEFAULT NULL,
ADD KEY `account_folder_id` (`account_folder_id`)

UPDATE `document_master` dm SET `account_folder_id` = (select `account_folder_id` from `document_folders` df where dm.id = df.document_id and df.status<>2);

-------------------------------------------
resource_master table : 01_JAN_2018
-------------------------------------------
UPDATE `resource_master` SET `api_endpoint` = '["/documents/{id}/update", "/documents/{id}/mark-as-public", "/documents/{id}/status-update", "/documents/{id}/move", "/documents/{id}/rename"]' WHERE `resource_master`.`id` = 38;

-------------------------------------------
campaign_stages table : 08_JAN_2017
-------------------------------------------
ALTER TABLE `campaign_master` MODIFY `other_data` varchar(500) CHARACTER SET utf8 NOT NULL DEFAULT '{}' COMMENT 'Campaign settings'

-------------------------------------------
resource_master table : 23_JAN_2018
-------------------------------------------
UPDATE `resource_master` SET `api_endpoint` = '["/emails/{id}/update", "/emails/{id}/update-status", "/emails/{id}/snooze"]' WHERE `resource_master`.`id` = 30;

-------------------------------------------
resource_master table : 6_FEB_2018
-------------------------------------------
UPDATE `resource_master` SET `api_endpoint` = '[\"/campaigns/list\", \"/campaigns/{id}/view\", \"/campaigns/{id}/view-stage/{stage_id}\",\"/campaigns/{id}/view-stage-contacts/{stage_id}\", \"/campaigns/{id}/sequence-mail/{seq_id}\", \"/campaigns/{id}/export-data/{stage_id}\",\"/campaigns/send-test-mail\", \"/campaigns/{id}/status-pause-resume\", \"/campaigns/{stage_id}/view-recipient-click-count/{seq_id}\"]' WHERE `resource_master`.`id` = 32;

-------------------------------------------
resource_master table : 7_FEB_2018
-------------------------------------------
UPDATE `resource_master` SET `api_endpoint` = '[\"/campaigns/list\", \"/campaigns/{id}/view\", \"/campaigns/{id}/view-stage/{stage_id}\",\"/campaigns/{id}/view-stage-contacts/{stage_id}\", \"/campaigns/{id}/sequence-mail/{seq_id}\", \"/campaigns/{id}/export-data/{stage_id}\",\"/campaigns/send-test-mail\", \"/campaigns/{id}/status-pause-resume\", \"/campaigns/{stage_id}/view-recipient-click-count/{seq_id}\", \"/campaigns/{id}/reply-check/{stage_id}\"]' WHERE `resource_master`.`id` = 32;
