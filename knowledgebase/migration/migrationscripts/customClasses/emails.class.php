<?php


CLASS EMAILS EXTENDS GENERALFUNCTIONS {

	protected $objLogger;

	function __construct ($objLogger) {
		if(DEBUG) {
			print "__construct ()";
		}

		$this->objDBConNew = new COMMONDBFUNC();

		$this->objDBConOld = new COMMONDBFUNC(HOST_OLD, PASSWORD_OLD, USERNAME_OLD, DBNAME_OLD);

		$this->objLogger = $objLogger;

		$this->perPageRecord = 500;
		$this->currentPage = 0;

		$this->counterOldLink = 0;
		$this->counterdirectLink = 0;

		$this->intLastEmailInsertId = 0;
	}

	function migrateEmailsOfAllUsersIncremental () {
		if(DEBUG) {
			print "migrateEmailsOfAllUsersIncremental ()";
		}

		$this->setLastEmailInsertId();
		if($this->intLastEmailInsertId > 0) {
			$this->deleteAllEmailRelatedDataForEmail($this->intLastEmailInsertId);
		}

		$this->migrateEmailsOfAllUsers();
	}


	function deleteAllEmailRelatedDataForEmail ($emailId = NULL) {
		if(DEBUG) {
			print "deleteAllEmailRelatedDataForEmail ()";
		}

		if(empty($emailId) || !is_numeric($emailId) || $emailId <= 0) {
			return false;
		}


		$qrySel = "	SET FOREIGN_KEY_CHECKS = 0 ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}

		// $qrySel = "	TRUNCATE TABLE " . COMMONVARS::contactGroupTableName();
		
		// if(DEBUG) {
		// 	print nl2br($qrySel);
		// }
		
		// $objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		// if(!$objDBResult) {
		// 	print "Error occur.";
		// 	return false;
		// }



		// $qrySel = "	TRUNCATE TABLE " . COMMONVARS::contactGroupMasterTableName();
		
		// if(DEBUG) {
		// 	print nl2br($qrySel);
		// }
		
		// $objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		// if(!$objDBResult) {
		// 	print "Error occur.";
		// 	return false;
		// }



		$qrySel = "	DELETE FROM email_recipients WHERE email_id = '" . addslashes($emailId) . "' ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}


		$qrySel = "	DELETE FROM email_master WHERE id = '" . addslashes($emailId) . "' ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}


		$qrySel = "	SET FOREIGN_KEY_CHECKS = 1 ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}
	}


	function setLastEmailInsertId () {
		if(DEBUG) {
			print "setLastEmailInsertId ()";
		}

		$this->intLastEmailInsertId = 0;

		$qrySel = "	SELECT id
					FROM email_master
					WHERE 1
					ORDER BY id DESC
					LIMIT 1";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}
		else {
			if($objDBResult->getNumRows() > 0) {
				$rowGetInfo = $objDBResult->fetchAssoc();
				$this->intLastEmailInsertId = $rowGetInfo['id'];
			}
		}
	}


	function migrateEmailsOfAllUsers () {
		if(DEBUG) {
			print "migrateEmailsOfAllUsers ()";
		}

		$arrEmails = $this->getEmails();

		if(!empty($arrEmails)) {

			foreach ($arrEmails as $key => $arrEmailsInfo) {
// print "<pre>arrEmailsInfo :: ";
// print_r($arrEmailsInfo);
// print "</pre>";
// die('arrEmailsInfo');

				if(!is_numeric($arrEmailsInfo['reply_at'])) {
					$arrEmailsInfo['reply_at'] = 0;
				}
				if(!is_numeric($arrEmailsInfo['schedule_date'])) {
					$arrEmailsInfo['schedule_date'] = 0;
				}

				$id 						= $arrEmailsInfo['id'];
				$contact_id 				= $arrEmailsInfo['contact_id'];
				$to_email 					= $arrEmailsInfo['to_email'];
				$subject 					= $arrEmailsInfo['subject'];
				$content 					= $arrEmailsInfo['content'];
				$template_id 				= $arrEmailsInfo['template_id'];
				$content_file_id 			= $arrEmailsInfo['content_file_id'];
				$files_id 					= $arrEmailsInfo['files_id'];
				$file_links 				= $arrEmailsInfo['file_links'];
				$file_urls 					= $arrEmailsInfo['file_urls'];
				$opened 					= $arrEmailsInfo['opened'];
				$user_id 					= $arrEmailsInfo['user_id'];
				$sent_date 					= $arrEmailsInfo['sent_date'];
				$opned_date 				= $arrEmailsInfo['opned_date'];
				$is_gmail 					= $arrEmailsInfo['is_gmail'];
				$gmail_group 				= $arrEmailsInfo['gmail_group'];
				$is_gmail_sent 				= $arrEmailsInfo['is_gmail_sent'];
				$gmail_sent_id 				= $arrEmailsInfo['gmail_sent_id'];
				$draft_id 					= $arrEmailsInfo['draft_id'];
				$draft_temp_id 				= $arrEmailsInfo['draft_temp_id'];
				$schedule_date 				= $arrEmailsInfo['schedule_date'];
				$schedule_timezone 			= $arrEmailsInfo['schedule_timezone'];
				$draft_locked 				= $arrEmailsInfo['draft_locked'];
				$is_mute 					= $arrEmailsInfo['is_mute'];
				$is_gmail_reply 			= $arrEmailsInfo['is_gmail_reply'];
				$reply_at 					= $arrEmailsInfo['reply_at'];
				$sent_with_gmail_email_id 	= $arrEmailsInfo['sent_with_gmail_email_id'];
				$schedule_email 			= $arrEmailsInfo['schedule_email'];
				$send_via 					= $arrEmailsInfo['send_via'];

				$accountId = $this->getAccountNumberForUser($user_id);

				if(is_numeric($accountId) && $accountId > 0) {
 					
 					$convertedScheduledEmailDateTime = 0;
 					if(strtotime($schedule_date)) {
 						$convertedScheduledEmailDateTime = self::convertDateTimeIntoTimeStamp($schedule_date);
 					}

 					$convertedSentDate = 0;
 					if(strtotime($sent_date)) {
 						$convertedSentDate = self::convertDateTimeIntoTimeStamp($sent_date);
 					}

 					$convertedOpenDate = 0;
 					if(strtotime($opned_date)) {
 						$convertedOpenDate = self::convertDateTimeIntoTimeStamp($opned_date);
 					}

					$convertedReplyTimeStamp = 0;
					if(strtotime($reply_at)) {
						$convertedReplyTimeStamp = self::convertDateTimeIntoTimeStamp($reply_at);
					}

 					$strTimeZone = '';

 					if(!empty($schedule_timezone)) {
 						$strTimeZone = 'GMT' . $schedule_timezone;
					}
 					

					$arrEmailEngagementFileLinks = $this->getEmailEngagementFileLinks($id);

					if(is_numeric($template_id) && $template_id > 0) {

					}
					else {
						$template_id = NULL;
					}

					// enter email entry into master.
					$arrParamsToEnterIntoEmailMaster = array(
																'emailId' 						=>	$id,
																'accountId'						=>	$accountId,
																'userId' 						=>	$user_id,
																'contactId' 					=>	$contact_id,
																'receipientEmail' 				=>	$to_email,
																'accountTemplateId' 			=>	$template_id,
																'accountSendingMethodId' 		=>	'1',
																'sourceId'						=>	'1',
																'subject' 						=>	$subject,
																'content'						=>	$content,
																'isScheduled'					=>	$schedule_email,
																'scheduledAt'					=>	$convertedScheduledEmailDateTime,
																'timezone'						=>	$strTimeZone,
																'sentAt' 						=>	$convertedSentDate,
																'trackReply' 					=>	0,
																'trackClick' 					=>	0,
																'cc'							=>	'',
																'bcc'							=>	'',
																'totalReceipient'				=>	1,
																'snoozeNotification'			=>	$is_mute,
																'progress' 						=>	'',
																'status' 						=>	'',
																'sendVia' 						=>	$send_via,
																'isGmailSent' 					=>	$is_gmail_sent,
																'gmailSentId' 					=>	$gmail_sent_id,
																'draftId' 						=>	$draft_id,
																'draftTempId' 					=>	$draft_temp_id,
																'gmailGroup' 					=>	$gmail_group, 
																'arrEmailEngagementFileLinks'	=>	$arrEmailEngagementFileLinks,
																'arrEmailsInfo'					=>	$arrEmailsInfo,
																'openCount'						=>	$opened,
																'openedDate'					=>	$convertedOpenDate,
																'replyAt'						=>	$convertedReplyTimeStamp
															);
// print "<pre>arrParamsToEnterIntoEmailMaster :: ";
// print_r($arrParamsToEnterIntoEmailMaster);
// print "</pre>";					
					$this->insertIntoEmail($arrParamsToEnterIntoEmailMaster);
				}
				else {
					// no auser account id found.
					$this->objLogger->setMessage("Account id not found for the user with id (" . $user_id . ") - email id (" . $id . ")");
				}
			}
 			
 			// adding log for current batch
 			$this->objLogger->addLog();

			// all processing done for the batch now call next batch
			return $this->migrateEmailsOfAllUsers();
		}
		// no records found
		else {
			$this->objLogger->setMessage('No more email records found for paging : ' . $this->currentPage);
			return true;
		}
	}


	function insertIntoEmail ($arrParams = array()) {
		if(DEBUG) {
			print "insertIntoEmail ()";
		}

		$intReturn = 0;

 		$arrImpParams = array(
 								'emailId', 
								'accountId', 
								'userId', 
								'contactId', 
								'receipientEmail', 
								'accountTemplateId', 
								'accountSendingMethodId', 
								'sourceId', 
								'subject', 
								'content', 
								'isScheduled', 
								'scheduledAt', 
								'timezone', 
								'sentAt', 
								'trackReply', 
								'trackClick', 
								'cc', 
								'bcc', 
								'totalReceipient', 
								'snoozeNotification', 
 								'progress', 
								'status', 
								'sendVia', 
								'isGmailSent', 
								'gmailSentId', 
								'draftId', 
								'draftTempId', 
								'gmailGroup', 
								'arrEmailEngagementFileLinks',
								'arrEmailsInfo',
								'openCount',
								'openedDate',
								'replyAt'
 							);

 		foreach ($arrParams as $key => $value) {
 			if(!in_array($key, $arrImpParams)) {
 				return $intReturn;
 			}
 		}

 		$emailId 						= $arrParams['emailId'];
 		$accountId 						= $arrParams['accountId'];
 		$userId 						= $arrParams['userId'];
 		$contactId 						= $arrParams['contactId'];
 		$receipientEmail 				= $arrParams['receipientEmail'];
 		$accountTemplateId 				= $arrParams['accountTemplateId'];
 		$accountSendingMethodId 		= $arrParams['accountSendingMethodId'];
 		$sourceId 						= $arrParams['sourceId'];
 		$subject 						= $arrParams['subject'];
 		$content 						= $arrParams['content'];
 		$isScheduled 					= $arrParams['isScheduled'];
 		$scheduledAt 					= $arrParams['scheduledAt'];
 		$timezone 						= $arrParams['timezone'];
 		$sentAt 						= $arrParams['sentAt'];
 		$trackReply 					= $arrParams['trackReply'];
 		$trackClick 					= $arrParams['trackClick'];
 		$cc 							= $arrParams['cc'];
 		$bcc 							= $arrParams['bcc'];
 		$totalReceipient 				= $arrParams['totalReceipient'];
 		$snoozeNotification 			= $arrParams['snoozeNotification'];
 		$progress 						= $arrParams['progress'];
 		$status 						= $arrParams['status'];
 		$sendVia 						= $arrParams['sendVia'];
 		$isGmailSent 					= $arrParams['isGmailSent'];
 		$gmailSentId 					= $arrParams['gmailSentId'];
 		$draftId 						= $arrParams['draftId'];
 		$draftTempId 					= $arrParams['draftTempId'];
 		$gmailGroup 					= $arrParams['gmailGroup'];
 		$arrEmailEngagementFileLinks 	= $arrParams['arrEmailEngagementFileLinks'];
 		$arrEmailsInfo 					= $arrParams['arrEmailsInfo'];
 		$openCount 						= $arrParams['openCount'];
 		$openedDate 					= $arrParams['openedDate'];
 		$replyAt  						= $arrParams['replyAt'];
 		
 		$arrParamsForFindingEmailSendingMethod = array(
 														'userId' 		=>	$userId,
 														'sendVia' 		=>	$sendVia,
 		 											);

 		$accountSendingId = $this->getEmailSendingMethod($arrParamsForFindingEmailSendingMethod);

 		$currentDateTimeStamp = self::convertDateTimeIntoTimeStamp();

 		/**
 		 *
 		 * Below is the condition for the progess of this email.
 		 *
 		 */
 		$progress = 0;
 		if($sentAt > 0) {
 			if($currentDateTimeStamp > $sentAt && $isScheduled == 0) {
	 			$progress = 1;
	 		}
	 		else {
	 			if($isScheduled = 1) {
	 				$progress = 0;
	 			}
	 			else if($isScheduled = 2){
	 				$progress = 2;
	 			}
	 		}
 		}

 		/**
 		 *
 		 * Check Account Template id exists
 		 *
 		 */
 		$arrParamsCheckForTemplate = array();
 		$arrParamsCheckForTemplate['accountId'] = $accountId;
 		$arrParamsCheckForTemplate['accountTemplateId'] = $accountTemplateId;

 		$accountTemplateIdToUsed = $this->checkAccountTempalteIdExisted($arrParamsCheckForTemplate);

 		if(is_null($accountTemplateIdToUsed)) {
 			$accountTemplateIdToUsed = 'NULL';
 		}


 		/**
 		 *
 		 * Condition check for the status of the email.
 		 *
 		 */
 		// 0 - draft
 		// 1 - Active
 		// 2 - Delete

 		$emailStatus = 1;
 		if(empty($gmailSentId) && !empty($draftId)) {
 			$emailStatus = 0;
 		}

 		
 		if(is_numeric($accountSendingId) && $accountSendingId > 0) {

 			$arrParamsForContactGroupCreation = array();
			/**
			 *
			 * email send from the saleshandy application.
			 *
			 */

			if($isGmailSent == '10' || $isGmailSent == '0') {
				$arrContactIds = array($contactId);
				
			}
			/**
			 *
			 * email sent fom the gmail plugin
			 *
			 */
			elseif($isGmailSent == '1') {
				$arrContactIds = explode(',', $gmailGroup);
			}

 			$arrNewContacts = array();
 			if(!empty($arrContactIds)) {
 				foreach ($arrContactIds as $key => $eachContactId) {

 					$arrParamsToCheckReferencendContact = array(
 																	'contactId' 	=>	$eachContactId
 					 											);
					$contactIdToBeUsed = $this->getContactRefernecedFromTheSystemForFutherModules($arrParamsToCheckReferencendContact);

					if(is_null($contactIdToBeUsed)) {
						$contactIdToBeUsed = 0;
					}
 					
 					// this contact cannot be used.
					if($contactIdToBeUsed == 0) {
						$this->objLogger->setMessage("Contact not migrated (" . $eachContactId . ") ");
					}
					// use this new contact id
					else {
						array_push($arrNewContacts, $contactIdToBeUsed);
					}
 				}
 			}

 			// if contacts ids are present
 			if(!empty($arrNewContacts)) {
 				$arrParamsForContactGroupCreation['arrContactIds'] = $arrNewContacts;
				// $contactGroupMasterId = $this->insertIntoContactGroup($arrParamsForContactGroupCreation);
 				 
 				$totalReceipientCount = COUNT($arrNewContacts);


 				if(filter_var($cc, FILTER_VALIDATE_EMAIL)) {
 					$totalReceipientCount++;
 				}
 				else {
 					$cc = "";
 				}

 				if(filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
 					$totalReceipientCount++;
 				}
 				else {
 					$bcc = "";
 				}


 				// inserting into email master.
 				$qryIns = "	INSERT INTO email_master (id, account_id, user_id, account_template_id, account_sending_method_id, source_id, subject, content, open_count, last_opened, reply_count, last_replied, reply_check_count, reply_last_checked, click_count, last_clicked, is_scheduled, is_bounce, scheduled_at, timezone, sent_at, sent_message_id, sent_response, track_reply, track_click, total_recipients, snooze_notifications, progress, status, created, modified)
 							VALUES ('" . addslashes($emailId) . "',
 									'" . addslashes($accountId) . "',
 									'" . addslashes($userId) . "',
 									" . addslashes($accountTemplateIdToUsed) . ",
 									'" . addslashes($accountSendingId) . "',
 									'1',
 									'" . addslashes($subject) . "',
 									'" . addslashes($content) . "',
 									'" . addslashes($openCount) . "',
 									'" . addslashes($openedDate) . "',
 									1,
 									'" . addslashes($replyAt) . "',
 									1,
 									'" . addslashes($replyAt) . "',
 									'0',
 									'0',
 									'" . addslashes($isScheduled) . "',
 									'0',
 									'" . addslashes($scheduledAt) . "',
 									'" . addslashes($timezone) . "',
 									'" . addslashes($sentAt) . "',
 									'" . addslashes($gmailSentId) . "',
 									'',
 									" . addslashes($trackReply) . ",
 									" . addslashes($trackClick) . ",
 									'" . addslashes($totalReceipientCount) . "',
 									'" . addslashes($snoozeNotification) . "',
 									'" . addslashes($progress) . "',
 									'1',
 									'" . addslashes($currentDateTimeStamp) . "',
 									'" . addslashes($currentDateTimeStamp) . "'
 								)";

 				if(DEBUG) {
 					print nl2br($qryIns);
 				}
// print "qryIns :: " . $qryIns . "\n";
		 		$emailMasterId = $this->objDBConNew->insertAndGetId($qryIns);
		 		
		 		if(!$emailMasterId) {
		 			
		 			$this->objLogger->setMessage('Unalbe to insert into email_master table, qry (' . $qryIns . ')');
		 			print "Cant insert into 'email_master', error occured.";
		 			// return false;
		 		}
		 		else {

		 			$this->objLogger->setMessage('email_master entry made with id (' .  $emailMasterId. ')');

		 			$this->objLogger->setMessage('Making entries for email_receipient tables.');

	 				if(!empty($arrParamsForContactGroupCreation['arrContactIds']) && count($arrParamsForContactGroupCreation['arrContactIds']) > 0) {
	 					$arrContactIds = $arrParamsForContactGroupCreation['arrContactIds'];

	 					foreach ($arrContactIds as $key => $contactValue) {

	 						$contactOldEmail = $this->getOldContactEmail($contactValue);

	 						$arrParamsToEnterIntoEmailRecipient = array(
				 															'emailMasterId' 		=>	$emailMasterId,
				 															'accountId' 			=>	$accountId,
				 															'userId'  				=> 	$userId,
				 															'receipientEmail' 		=>	$contactOldEmail,
				 															'arrEmailsInfo'			=>	$arrEmailsInfo,
				 															'emailRecipientType'	=>	1,
				 															'openedDate'			=>	$openedDate,
				 															'replyAt'				=> 	$replyAt
																		);

				 			$emailRecipientId = $this->insertIntoEmailRecipientEntry($arrParamsToEnterIntoEmailRecipient);

				 			if(is_numeric($emailRecipientId) && $emailRecipientId > 0) {
				 				$this->objLogger->setMessage('Email recipient entry made successfull emailRecipientId : ' . $emailRecipientId);
				 			}
				 			else {
				 				$this->objLogger->setMessage('Email recipient entry not successfull. Error Occured');
				 			}
	 					}

	 					if(!empty($cc)) {
	 						$arrParamsToEnterIntoEmailRecipient = array(
				 															'emailMasterId' 		=>	$emailMasterId,
				 															'accountId' 			=>	$accountId,
				 															'userId'  				=> 	$userId,
				 															'receipientEmail' 		=>	$cc,
				 															'arrEmailsInfo'			=>	$arrEmailsInfo,
				 															'emailRecipientType'	=>	2,
				 															'openedDate'			=>	$openedDate,
				 															'replyAt'				=> 	$replyAt
																		);

				 			$emailRecipientId = $this->insertIntoEmailRecipientEntry($arrParamsToEnterIntoEmailRecipient);

				 			if(is_numeric($emailRecipientId) && $emailRecipientId > 0) {
				 				$this->objLogger->setMessage('Email recipient entry made successfull FOR CC emailRecipientId : ' . $emailRecipientId);
				 			}
				 			else {
				 				$this->objLogger->setMessage('Email recipient entry not successfull FOR CC. Error Occured : ' . $emailRecipientId);
				 			}
	 					}
	 					if(!empty($bcc)) {
	 						$arrParamsToEnterIntoEmailRecipient = array(
				 															'emailMasterId' 		=>	$emailMasterId,
				 															'accountId' 			=>	$accountId,
				 															'userId'  				=> 	$userId,
				 															'receipientEmail' 		=>	$bcc,
				 															'arrEmailsInfo'			=>	$arrEmailsInfo,
				 															'emailRecipientType'	=> 	3,
				 															'openedDate'			=>	$openedDate,
				 															'replyAt'				=> 	$replyAt
																		);

				 			$emailRecipientId = $this->insertIntoEmailRecipientEntry($arrParamsToEnterIntoEmailRecipient);

				 			if(is_numeric($emailRecipientId) && $emailRecipientId > 0) {
				 				$this->objLogger->setMessage('Email recipient entry made successfull FOR BCC emailRecipientId : ' . $emailRecipientId);
				 			}
				 			else {
				 				$this->objLogger->setMessage('Email recipient entry not successfull FOR BCC. Error Occured :: ' . $emailRecipientId);
				 			}
	 					}
	 				}
		 		}
 			}
 			// if no contacts id found
 			else {
 				$this->objLogger->setMessage("No contacts are found for email id (" . $emailId . ") - userId (" . $userId . ") ");
 			}
 		}
 		else {
 			// account sending method not found.
 			$this->objLogger->setMessage('Account Sending id not found for the email with id (' . $emailId . ') - userId (' . $userId . ') ');
 		}
	}


	function insertIntoEmailEngagementLink ($arrParams) {
		if(DEBUG) {
			print "insertIntoEmailEngagementLink ()";
		}

		$fileLinkEngagement_Id 					= $arrEmailEngagementFileLinksInfo['fileLinkEngagement_Id'];
		$fileLinkEngagement_fileLinkId 			= $arrEmailEngagementFileLinksInfo['fileLinkEngagement_fileLinkId'];
		$fileLinkEngagement_contactId 			= $arrEmailEngagementFileLinksInfo['fileLinkEngagement_contactId'];
		$fileLinkEngagement_emailId 			= $arrEmailEngagementFileLinksInfo['fileLinkEngagement_emailId'];
		$fileLinkEngagement_uniqueCode 			= $arrEmailEngagementFileLinksInfo['fileLinkEngagement_uniqueCode'];
		$fileLinkEngagement_totalViews 			= $arrEmailEngagementFileLinksInfo['fileLinkEngagement_totalViews'];
		$fileLinkEngagement_attachedUniqueCode 	= $arrEmailEngagementFileLinksInfo['fileLinkEngagement_attachedUniqueCode'];
		$fileLinkEngagement_isOpenEmail 		= $arrEmailEngagementFileLinksInfo['fileLinkEngagement_isOpenEmail'];
		$fileLinkEngagement_forward 			= $arrEmailEngagementFileLinksInfo['fileLinkEngagement_forward'];
		$fileLinkEngagement_openedFirstTime 	= $arrEmailEngagementFileLinksInfo['fileLinkEngagement_openedFirstTime'];
		$fileLinkEngagement_groupUniqueCode 	= $arrEmailEngagementFileLinksInfo['fileLinkEngagement_groupUniqueCode'];
		$fileLinkEngagement_contactGroup 		= $arrEmailEngagementFileLinksInfo['fileLinkEngagement_contactGroup'];
		$fileLinkEngagement_isEmailApp 			= $arrEmailEngagementFileLinksInfo['fileLinkEngagement_isEmailApp'];
		$fileLinkEngagement_openWithEmail 		= $arrEmailEngagementFileLinksInfo['fileLinkEngagement_openWithEmail'];
		$fileLinkEngagement_isAuthPlugin 		= $arrEmailEngagementFileLinksInfo['fileLinkEngagement_isAuthPlugin'];

		$fileLink_id 							= $arrEmailEngagementFileLinksInfo['fileLink_id'];
		$fileLink_userId 						= $arrEmailEngagementFileLinksInfo['fileLink_userId'];
		$fileLink_fileId 						= $arrEmailEngagementFileLinksInfo['fileLink_fileId'];
		$fileLink_link 							= $arrEmailEngagementFileLinksInfo['fileLink_link'];
		$fileLink_title 						= $arrEmailEngagementFileLinksInfo['fileLink_title'];
		$fileLink_openedLinks 					= $arrEmailEngagementFileLinksInfo['fileLink_openedLinks'];
		$fileLink_createdDate 					= $arrEmailEngagementFileLinksInfo['fileLink_createdDate'];
		$fileLink_openedDate 					= $arrEmailEngagementFileLinksInfo['fileLink_openedDate'];
		$fileLink_isEmail 						= $arrEmailEngagementFileLinksInfo['fileLink_isEmail'];
		$fileLink_isCampaign 					= $arrEmailEngagementFileLinksInfo['fileLink_isCampaign'];
		$fileLink_templateId 					= $arrEmailEngagementFileLinksInfo['fileLink_templateId'];
		$fileLink_campaignTemplateId 			= $arrEmailEngagementFileLinksInfo['fileLink_campaignTemplateId'];
		$fileLink_position 						= $arrEmailEngagementFileLinksInfo['fileLink_position'];
		$fileLink_visitorInfo 					= $arrEmailEngagementFileLinksInfo['fileLink_visitorInfo'];
		$fileLink_allowedDownload 				= $arrEmailEngagementFileLinksInfo['fileLink_allowedDownload'];
		$fileLink_password 						= $arrEmailEngagementFileLinksInfo['fileLink_password'];
		$fileLink_status 						= $arrEmailEngagementFileLinksInfo['fileLink_status'];

		$accountId 								= $arrEmailEngagementFileLinksInfo['accountId'];
		$emailRecipientId 						= $arrEmailEngagementFileLinksInfo['emailRecipientId'];
		$emailMasterId 							= $arrEmailEngagementFileLinksInfo['emailMasterId'];


		$documentLinkId = $this->getDocumentLinkId($fileLinkEngagement_attachedUniqueCode);

		if(is_numeric($documentLinkId) && $documentLinkId > 0) {

			$qryIns = "	INSERT INTO email_document_link (engagement_type, account_id, email_recipient_id, email_id, document_link_id, unique_code_1, unique_code_2, open_count, open_first_time, generated_from_plugin)
						VALUES ('1',
								'" . addslashes($arrParams['accountId']) . "',
								'" . addslashes($arrParams['emailRecipientId']) . "',
								'" . addslashes($arrParams['emailMasterId']) . "',
								'" . addslashes($documentLinkId) . "',
								'" . addslashes($fileLinkEngagement_uniqueCode) . "',
								'" . addslashes($fileLinkEngagement_attachedUniqueCode) . "',
								'" . addslashes($fileLinkEngagement_uniqueCode) . "',
								'" . addslashes($fileLinkEngagement_uniqueCode) . "',
								'" . addslashes($fileLinkEngagement_uniqueCode) . "',
								'" . UTCDATE . "')";
					
			if(DEBUG) {
				print nl2br($qryIns);
			}
			
			$emailDocumentLinkId = $this->objDBConNew->insertAndGetId($qryIns);
			
			if(!$emailDocumentLinkId) {
				print "Cant insert into 'email_document_link', error occured.";
				return false;
			}
			else {
				
			}
		}
		else {
			$this->objLogger->setMessage('Document link id not found for the record. DATASET : ' . json_encode($arrEmailEngagementFileLinksInfo));
		}
	}

	function getDocumentLinkId ($strLinkCode = '') {
		if(DEBUG) {
			print "getDocumentLinkId ()";
		}

		$intReturn = 0;

 		if(empty($strLinkCode)) {
 			return $intReturn;
 		}

//, dl.account_id accountId, dl.user_id userId, dl.source_id sourceId, dl.name name, dl.short_description shortDescription, dl.link_domain linkDomain, dl.link_code linkCode, dl.type type, dl.is_set_expiration_date isSetExpirationDate, dl.expires_at expiresAt, dl.allow_download allowDownload, dl.password_protected passwordProtected, dl.access_password accessPassword, dl.ask_visitor_info askVisitorInfo, dl.visitor_info_payload visitorInfoPayload, dl.snooze_notifications snoozeNotifications, dl.remind_not_viewed remindNotViewed, dl.remind_at remindAt, dl.status status, dl.created created, dl.modified modified

 		$qrySel = "	SELECT dl.id documentLinkId
 		 			FROM document_links dl
 		 			WHERE  SUBSTR(dl.link_code, 4) = '" . addslashes($strLinkCode) . "' ";
 		
 		if(DEBUG) {
 			print nl2br($qrySel);
 		}
 		
 		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
 		
 		if(!$objDBResult) {
 			print "Error occur.";
 			return false;
 		}
 		else {
 			if($objDBResult->getNumRows() >0) {
 				$rowGetInfo = $objDBResult->fetchAssoc();
 				$intReturn = $rowGetInfo['documentLinkId'];
 			}
 		}
 		return $intReturn;
	}


	function insertIntoEmailRecipientEntry ($arrParams) {
		if(DEBUG) {
			print "insertIntoEmailRecipientEntry ()";
		}
		
		$intReturn = 0;

		$emailMasterId 		= $arrParams['emailMasterId'];
		$accountId 			= $arrParams['accountId'];
		$userId 			= $arrParams['userId'];
		$receipientEmail 	= $arrParams['receipientEmail'];
		$emailRecipientType = $arrParams['emailRecipientType'];
		$openedDate 		= $arrParams['openedDate'];
		$replyAt 			= $arrParams['replyAt'];
		$arrEmailsInfo  	= $arrParams['arrEmailsInfo'];


		$id 						= $arrEmailsInfo['id'];
		$contact_id 				= $arrEmailsInfo['contact_id'];
		$to_email 					= $arrEmailsInfo['to_email'];
		$subject 					= $arrEmailsInfo['subject'];
		$content 					= $arrEmailsInfo['content'];
		$template_id 				= $arrEmailsInfo['template_id'];
		$content_file_id 			= $arrEmailsInfo['content_file_id'];
		$files_id 					= $arrEmailsInfo['files_id'];
		$file_links 				= $arrEmailsInfo['file_links'];
		$file_urls 					= $arrEmailsInfo['file_urls'];
		$opened 					= $arrEmailsInfo['opened'];
		$user_id 					= $arrEmailsInfo['user_id'];
		$sent_date 					= $arrEmailsInfo['sent_date'];
		$opned_date 				= $arrEmailsInfo['opned_date'];
		$is_gmail 					= $arrEmailsInfo['is_gmail'];
		$gmail_group 				= $arrEmailsInfo['gmail_group'];
		$is_gmail_sent 				= $arrEmailsInfo['is_gmail_sent'];
		$gmail_sent_id 				= $arrEmailsInfo['gmail_sent_id'];
		$draft_id 					= $arrEmailsInfo['draft_id'];
		$draft_temp_id 				= $arrEmailsInfo['draft_temp_id'];
		$schedule_date 				= $arrEmailsInfo['schedule_date'];
		$schedule_timezone 			= $arrEmailsInfo['schedule_timezone'];
		$draft_locked 				= $arrEmailsInfo['draft_locked'];
		$is_mute 					= $arrEmailsInfo['is_mute'];
		$is_gmail_reply 			= $arrEmailsInfo['is_gmail_reply'];
		$reply_at 					= $arrEmailsInfo['reply_at'];
		$sent_with_gmail_email_id 	= $arrEmailsInfo['sent_with_gmail_email_id'];
		$schedule_email 			= $arrEmailsInfo['schedule_email'];
		$send_via 					= $arrEmailsInfo['send_via'];


		$accountContactId = $this->getRecipientsAccountContactId($arrParams);


		if(is_numeric($accountContactId) && $accountContactId > 0) {


			$convertedOpenedDate = self::convertDateTimeIntoTimeStamp($opned_date);	
			$convertedCurrentDate = self::convertDateTimeIntoTimeStamp();
			$convertedScheduledDate = self::convertDateTimeIntoTimeStamp($schedule_date);

 			
 			// default scheduled in future
			$flagProgress = 0;
			if($convertedCurrentDate > $convertedScheduledDate) {
				// default success
				$flagProgress = 1;
				if(empty($gmail_sent_id)) {
					// fail
					$flagProgress = 2;
				}
			}
 			
 			$flagReply = 0;
 			if(is_numeric($replyAt) && $replyAt > 0) {
 				$flagReply = 1;
 			}

			
			$qryIns = "	INSERT INTO email_recipients (account_id, email_id, account_contact_id, type, open_count, last_opened, replied, replied_at, click_count, last_clicked, is_bounce, progress, status, modified)
						VALUES ('" . addslashes($accountId) . "',
								'" . addslashes($emailMasterId) . "',
								'" . addslashes($accountContactId) . "',
								'" . addslashes($emailRecipientType) . "',
								'" . addslashes($opened) . "',
								'" . addslashes($openedDate) . "',
								'" . addslashes($flagReply) . "',
								'" . addslashes($replyAt) . "',
								0,
								0,
								0,
								'" . addslashes($flagProgress) . "',
								1,
								'" . addslashes($convertedCurrentDate) . "'
								)";

			if(DEBUG) {
				print nl2br($qryIns);
			}
// print "qryIns :: " . $qryIns . "\n";
			$emailRecipientId = $this->objDBConNew->insertAndGetId($qryIns);
			
			if(!$emailRecipientId) {
				// print "Cant insert into 'email_recipients', error occured.";
				$this->objLogger->setMessage("Cant insert into 'email_recipients', error occured. for emailmasterid : " . $emailMasterId . ", account contact id :: " . $accountContactId);
				// return false;
			}
			else {
				$intReturn = $emailRecipientId;
			}

		}
		return $intReturn;
	}



	function getRecipientsAccountContactId ($arrParams) {
		if(DEBUG) {
			print "getRecipientsAccountContactId ()";
		}

		$intReturn = 0;

		$emailMasterId 		= $arrParams['emailMasterId'];
		$accountId 			= $arrParams['accountId'];
		$userId 			= $arrParams['userId'];
		$receipientEmail 	= $arrParams['receipientEmail'];

		$qrySel = "	SELECT ac.id accountContactId
					FROM account_contacts ac
					WHERE ac.account_id = '" . addslashes($accountId) . "'
					AND ac.email = '" . addslashes($receipientEmail) . "' ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}
		else {
			if($objDBResult->getNumRows() > 0) {
				$rowGetInfo = $objDBResult->fetchAssoc();
				$intReturn = $rowGetInfo['accountContactId'];
			}
			// if email not present in account_contact table. then create one and return.
			else {
 				
 				$currentDateTimeStamp = self::convertDateTimeIntoTimeStamp();

				$qryIns = "	INSERT INTO account_contacts (account_id, account_company_id, source_id, email, first_name, last_name, phone, city, country, notes, total_mail_sent, total_mail_failed, total_mail_replied, total_mail_bounced, total_link_clicks, total_document_viewed, total_document_facetime, status, created, modified)
							VALUES ('" . addslashes($accountId) . "',
									NULL,
									'1',
									'" . addslashes($receipientEmail) . "',
									'', 
									'', 
									'', 
									'', 
									'', 
									'', 
									0, 
									0, 
									0, 
									0, 
									0, 
									0, 
									0, 
									'1', 
									'" . addslashes($currentDateTimeStamp) . "', 
									'" . addslashes($currentDateTimeStamp) . "'
								)";
						
				if(DEBUG) {
					print nl2br($qryIns);
				}
				
				$accountContactId = $this->objDBConNew->insertAndGetId($qryIns);
				
				if(!$accountContactId) {
					$this->objLogger->setMessage("Cant insert into 'account_contacts', error occured.");
					// print "Cant insert into 'account_contacts', error occured.";
				}
				else {
					$intReturn = $accountContactId;
				}
			}
		}
		return $intReturn;
	}


	function getEmailEngagementFileLinks ($emailId = NULL) {
		if(DEBUG) {
			print "getEmailEngagementFileLinks ({$emailId})";
		}

		$arrReturn = array();

		if(empty($emailId) || 
	 		!is_numeric($emailId) ||
	 	 	$emailId <= 0) {

			return $arrReturn;
		}


		$qrySel = "	SELECT fee.id fileLinkEngagement_Id, fee.filelinks_id fileLinkEngagement_fileLinkId, fee.contact_id fileLinkEngagement_contactId, fee.email_id fileLinkEngagement_emailId, fee.unique_code fileLinkEngagement_uniqueCode, fee.total_views fileLinkEngagement_totalViews, fee.attached_unique_code fileLinkEngagement_attachedUniqueCode, fee.is_opened_email fileLinkEngagement_isOpenEmail, fee.forward fileLinkEngagement_forward, fee.opened_first_time fileLinkEngagement_openedFirstTime, fee.group_unique_code fileLinkEngagement_groupUniqueCode, fee.contact_group fileLinkEngagement_contactGroup, fee.is_email_app fileLinkEngagement_isEmailApp, fee.opened_with_email fileLinkEngagement_openWithEmail, fee.is_auth_plugin fileLinkEngagement_isAuthPlugin, fl.id fileLink_id, fl.user_id fileLink_userId, fl.file_id fileLink_fileId, fl.link fileLink_link, fl.title fileLink_title, fl.opened_links fileLink_openedLinks, fl.created_date fileLink_createdDate, fl.opened_date fileLink_openedDate, fl.is_email fileLink_isEmail, fl.is_campaign fileLink_isCampaign, fl.template_id fileLink_templateId, fl.campaign_template_id fileLink_campaignTemplateId, fl.position fileLink_position, fl.visitor_info fileLink_visitorInfo, fl.allow_download fileLink_allowedDownload, fl.password fileLink_password, fl.status fileLink_status
					FROM filelinks_email_engagement fee, filelinks fl
					WHERE fee.attached_unique_code = fl.link
					AND fee.email_id = '" . addslashes($emailId) . "' ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConOld->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}
		else {
			if($objDBResult->getNumRows() > 0) {
				while($rowGetInfo = $objDBResult->fetchAssoc()) {
					array_push($arrReturn, $rowGetInfo);
				}
			}
		}

		return $arrReturn;
	}


	function getEmails () {
		if(DEBUG) {
			print "getEmails ()";
		}

		$arrReturn = array();

		$qrySel = "	SELECT *
					FROM email
					WHERE 1 ";

		if(is_numeric($this->intLastEmailInsertId) && $this->intLastEmailInsertId > 0) {
			$qrySel .= "	AND id >= '" . addslashes($this->intLastEmailInsertId) . "'";
		}

		$limit = " LIMIT " . ($this->currentPage * $this->perPageRecord) . ", " . $this->perPageRecord;

		$qrySel .= $limit;
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConOld->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}
		else {
			if($objDBResult->getNumRows() > 0) {
				while($rowGetInfo = $objDBResult->fetchAssoc()) {
					array_push($arrReturn, $rowGetInfo);
				}
			}
		}

		$this->currentPage++;
		return $arrReturn;
	}
}