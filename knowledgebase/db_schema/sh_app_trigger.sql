------------------------------------------
Email : template_perfromance_trigger : 27_FEB_2018
-------------------------------------------
DELIMITER $$

CREATE 
	TRIGGER `template_usage_count` AFTER INSERT 
	ON `email_master`
	FOR EACH ROW 
	BEGIN
	  UPDATE `account_templates`
    SET total_mail_usage = total_mail_usage+1
    WHERE id = new.account_template_id;	
	END$$
DELIMITER ;	

DELIMITER $$

CREATE
  TRIGGER `template_open_count` AFTER UPDATE
  ON `email_master`
  FOR EACH ROW 
   
  BEGIN
     
    UPDATE `account_templates`
    SET total_mail_open = total_mail_open+1
    WHERE id = new.account_template_id and new.open_count = 1 AND old.open_count=0;
   
    UPDATE `account_templates`
    SET total_mail_usage = total_mail_usage-1	
    WHERE id = new.account_template_id and new.progress = 1 AND new.status = 2 AND old.status=1 AND new.progress =1  and new.status =1;   
       
    UPDATE `account_templates`
    SET total_mail_open = total_mail_open-1
    WHERE id = new.account_template_id and new.progress = 1 AND new.status = 2 AND old.status=1 and new.open_count>0 ; 

  END$$

DELIMITER ;

------------------------------------------
Email : template_perfromance_trigger  : 5_MAR_2018
-------------------------------------------
DROP TRIGGER IF EXISTS `template_usage_count`; 

DELIMITER $$

CREATE 
	TRIGGER `email_template_usage_count` AFTER INSERT 
	ON `email_master`
	FOR EACH ROW 
	BEGIN
	  UPDATE `account_templates`
    SET total_mail_usage = total_mail_usage+1
    WHERE id = new.account_template_id;	
	END$$
DELIMITER ;	

DROP TRIGGER IF EXISTS `template_open_count`; 
DELIMITER $$

CREATE
  TRIGGER `email_template_open_count` AFTER UPDATE
  ON `email_master`
  FOR EACH ROW 
   
  BEGIN
     
    UPDATE `account_templates`
    SET total_mail_open = total_mail_open+1
    WHERE id = new.account_template_id and new.open_count = 1 AND old.open_count=0;

  END$$

DELIMITER ;
------------------------------------------
Campaign : template_perfromance_trigger  : 27_FEB_2018
-------------------------------------------

DELIMITER $$

CREATE 
	TRIGGER `campaign_template_usage_count` AFTER UPDATE 
	ON `campaign_sequences`
	FOR EACH ROW 
	BEGIN
	DECLARE template_id int(10);
	
	SELECT cs.account_template_id into template_id from `campaign_sequences` csq 
	INNER JOIN `campaign_stages` cs ON csq.campaign_stage_id = cs.id
	WHERE csq.id = new.id;
	
	UPDATE `account_templates`
    SET total_mail_usage = total_mail_usage+1
    WHERE id = template_id and new.progress = 1 and old.progress = 0;		
	
	UPDATE `account_templates`
    SET total_mail_open = total_mail_open+1
    WHERE id = template_id and new.progress = 1 and new.open_count = 1 AND old.open_count=0;	
	
	UPDATE `account_templates`
    SET total_mail_usage = total_mail_usage-1
    WHERE id = template_id and new.progress = 2 and old.progress = 1;
	
	UPDATE `account_templates`
    SET total_mail_open = total_mail_open-1
    WHERE id = template_id and new.progress = 2 and old.progress = 1 and old.open_count>0;
	
	END$$
	
DELIMITER ;
