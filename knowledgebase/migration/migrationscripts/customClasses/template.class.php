<?php


CLASS TEMPLATE EXTENDS GENERALFUNCTIONS {

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
		$this->counter = 0;


		$this->templateIdAlreadyInserted = 0;
	}


	function migrateTemplatesIncrementalScript () {
		if(DEBUG) {
			print "migrateTemplatesIncrementalScript ()";
		}
 		
		$this->objLogger->setMessage('Starting script for template migration.');

		$this->objLogger->setMessage('Checking for any older migrated records.');

 		$arrLastInsertedTemplate = $this->getLastInsertedTemplate();
		
		$lastInsertedTemplateId 		= $arrLastInsertedTemplate['lastInsertedTemplateId'];
		$lastInsertedTemplatedAccountId = $arrLastInsertedTemplate['lastInsertedTemplatedAccountId'];
		$lastInsertedTemplatedUserId 	= $arrLastInsertedTemplate['lastInsertedTemplatedUserId'];

		if(is_numeric($lastInsertedTemplateId) && $lastInsertedTemplateId > 0) {

			$this->objLogger->setMessage('Seems like previously migrated records are found with details below');
			$this->objLogger->setMessage('=================');
			$this->objLogger->setMessage('lastInsertedTemplateId :: '. $lastInsertedTemplateId);
			$this->objLogger->setMessage('lastInsertedTemplatedAccountId :: '. $lastInsertedTemplatedAccountId);
			$this->objLogger->setMessage('lastInsertedTemplatedUserId :: '. $lastInsertedTemplatedUserId);
			$this->objLogger->setMessage('=================');


			$this->templateIdAlreadyInserted = $lastInsertedTemplateId;
 			
 			$this->objLogger->setMessage('Preparing to delte all the records for the account template with id (' . $lastInsertedTemplateId . ')');
			$this->deleteAllTemplateRecords($lastInsertedTemplateId);
		}

		$this->objLogger->addLog();

		$this->migrateTemplates();
	}

	function deleteAllTemplateRecords ($lastInsertedTemplateId = NULL) {
		if(DEBUG) {
			print "deleteAllTemplateRecords ()";
		}

		$this->objLogger->setMessage('Starting to delete records of last accountemplate inserted.');

		
 		/**
 		 * Deleting the template inforamtion if template is mark as public.
 		 */
 		$qrySel = "	DELETE FROM account_template_teams WHERE account_template_id = '" . addslashes($lastInsertedTemplateId) . "' ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}

 		
 		/**
 		 * Deleting template information from the account template master table.
 		 */
		$qrySel = "	DELETE FROM account_templates WHERE id = '" . addslashes($lastInsertedTemplateId) . "' ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}

		$this->objLogger->setMessage('All the information about the account template is deleted.(' . $lastInsertedTemplateId . ')');
		return true;
	}


	function getLastInsertedTemplate () {
		if(DEBUG) {
			print "getLastInsertedTemplate ()";
		}

		$arrReturn = array();
		$arrReturn['lastInsertedTemplateId'] = 0;
		$arrReturn['lastInsertedTemplatedAccountId'] = 0;
		$arrReturn['lastInsertedTemplatedUserId'] = 0;

		$qrySel = "	SELECT at.id accountTemplateId, at.account_id accountId, at.user_id userId
					FROM account_templates at
					ORDER BY at.id DESC
					LIMIT 1";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
print "qrySel :: " . $qrySel . "\n";
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}
		else {
			if($objDBResult->getNumRows() > 0) {
				$rowGetInfo = $objDBResult->fetchAssoc();

				$arrReturn['lastInsertedTemplateId'] = $rowGetInfo['accountTemplateId'];
				$arrReturn['lastInsertedTemplatedAccountId'] = $rowGetInfo['accountId'];
				$arrReturn['lastInsertedTemplatedUserId'] = $rowGetInfo['userId'];
			}
		}
		return $arrReturn;
	}


	function migrateTemplates () {
		if(DEBUG) {
			print "migrateTemplates ()";
		}

		$arrOldTemplateData = $this->getOldTemaplteData();

		$this->objLogger->setMessage("Fetched old tempalte data for page - " . $this->currentPage);

 		if(!empty($arrOldTemplateData)) {
 			$qryCounter = 0;
 			foreach ($arrOldTemplateData as $key => $arrOldTemplateInfoData) {

print "this->counter :: " . $this->counter++ . "\n";

 				$id 						= $arrOldTemplateInfoData['id'];
 				$title 						= $arrOldTemplateInfoData['title'];
 				$subject 					= $arrOldTemplateInfoData['subject'];
 				$content 					= $arrOldTemplateInfoData['content'];
 				$categories 				= $arrOldTemplateInfoData['categories'];
 				$template_categories_id 	= $arrOldTemplateInfoData['template_categories_id'];
 				$template_type_id 			= $arrOldTemplateInfoData['template_type_id'];
 				$type 						= $arrOldTemplateInfoData['type']; 				
 				$user_id 					= $arrOldTemplateInfoData['user_id'];
 				$created_date 				= $arrOldTemplateInfoData['created_date'];
 				$updated_date 				= $arrOldTemplateInfoData['updated_date'];
 				$total_opened_email 		= $arrOldTemplateInfoData['total_opened_email'];
 				$group_id 					= $arrOldTemplateInfoData['group_id'];
 				$total_opened_campaign 		= $arrOldTemplateInfoData['total_opened_campaign'];
 				$used_with_campaign 		= $arrOldTemplateInfoData['used_with_campaign'];
 				$performance 				= $arrOldTemplateInfoData['performance'];
 				
 				// not using anywhere
 				$attachment 				= $arrOldTemplateInfoData['attachment'];
 				$attachement_url 			= $arrOldTemplateInfoData['attachement_url'];
 				$template_id 				= $arrOldTemplateInfoData['template_id'];
 				$total_sent_campaign 		= $arrOldTemplateInfoData['total_sent_campaign'];
 				$ctr_campaign 				= $arrOldTemplateInfoData['ctr_campaign'];

 				// fetching account id of the user whose template we are migrating
 				$accountId = $this->getAccountNumberForUser($user_id);

print "user_id :: " . $user_id . "\n";
print "accountId :: " . $accountId . "\n";

 				if(is_numeric($accountId) && $accountId > 0) {
 					
 					$convertedCreatedDate = self::convertDateTimeIntoTimeStamp($created_date);
 					$convertedModifiedDate = self::convertDateTimeIntoTimeStamp($updated_date);

 					// function call to fetch users default template folder id.
 					// $arrParamsDefaultTemplateFolderId = array(
 					// 											'USERID' 		=>	$user_id,
 					// 											'ACCOUNTID'		=>	$accountId
 					// 										);

 					// $defaultTemplateFolderId = $this->getDefaultTemplateFolderId($arrParamsDefaultTemplateFolderId);
 					
 					$flagPublic = 0;
 					if(is_numeric($group_id) && $group_id > 0 && $group_id != $user_id) {
 						$flagPublic = 1;
 					}

 					$arrShareAccess = array(
				 							'can_edit'	=> false,
				 							'can_delete'	=>	false
				 						);

 					/**
 					 *
 					 * template id is required, because it is used in email migration.
 					 *
 					 */	

 					$qryIns = "	INSERT INTO account_templates (id, account_id, user_id, source_id, title, subject, content, total_mail_usage, total_mail_open, total_campaign_usage, total_campaign_mails, total_campaign_open, last_used_at, public, share_access, status, created, modified)
 								VALUES ('" . addslashes($id) . "',
 										'" . addslashes($accountId) . "',
 										'" . addslashes($user_id) . "',
 										1,
 										'" . addslashes($title) . "',
 										'" . addslashes($subject) . "',
 										'" . addslashes($content) . "',
 										'" . addslashes($total_opened_email) . "',
 										'" . addslashes($total_opened_email) . "',
 										'" . addslashes($used_with_campaign) . "',
 										'" . addslashes($total_opened_campaign) . "',
 										'" . addslashes($total_opened_campaign) . "',
 										0,
 										'" . addslashes($flagPublic) . "',
 										'" . addslashes(json_encode($arrShareAccess)) . "',
 										1,
 										'" . addslashes($convertedCreatedDate) . "',
 										'" . addslashes($convertedModifiedDate) . "')";
 							
 					if(DEBUG) {
 						print nl2br($qryIns);
 					}
 					
 					$accountTemplateId = $this->objDBConNew->insertAndGetId($qryIns);
 					
 					if(!$accountTemplateId) {
 						print "Cant insert into 'account_templates', error occured.";
 						return false;
 					}
 					else {


 						/**
 						 *
 						 * Below section is to add template in defule folder. It is commited as per discussion.
 						 	Now the templates will be saved in root folder and will be open to all.
 						 *
 						 */
 						// function call to add template with the folder
	 					// $arrParamsForSettingAccountTemplateFolder = array(
	 					// 													'accountTemplateId'	=>	$accountTemplateId,
	 					// 													'accountFolderId' 	=>	$defaultTemplateFolderId
	 					// 												);
	 					// $this->setAccountTemplateFolder($arrParamsForSettingAccountTemplateFolder);

 						
	 					// if temaplte is shared within the team. make entry in the mapping table also.
	 					if($flagPublic == 1) {

	 						$arrParamsForMakingTemplatePublic = array(
	 																	'userId' 			=>	$user_id,
	 																	'accountTemplateId'	=>	$accountTemplateId
	 																);
	 						$this->makeThisTemplatePublic($arrParamsForMakingTemplatePublic);
	 					}
 					}
 				}
 				else {
 					$this->objLogger->setMessage("Account Id not found for the user (" . $user_id . ")");
 				}
 			}

 			$this->objLogger->addLog();

 			return $this->migrateTemplates();
 		}
 		else {
 			$this->objLogger->setMessage("Old records end");
 			$this->objLogger->addLog();
 			return true;
 		}
	}


	function makeThisTemplatePublic ($arrParams) {
		if(DEBUG) {
			print "makeThisTemplatePublic ()";
		}

		$intReturn = 0;

		$accountTemplateId = 0;
		$userId = 0;

		if(isset($arrParams['accountTemplateId']) && $arrParams['accountTemplateId'] > 0) {
			$accountTemplateId = $arrParams['accountTemplateId'];
		}

		if(isset($arrParams['userId']) && $arrParams['userId'] > 0) {
			$userId = $arrParams['userId'];
		}

		if($accountTemplateId > 0 && $userId > 0) {


			$accountTeamId = $this->getUsersAccountTeamId($userId);

			if(is_numeric($accountTeamId) && $accountTeamId > 0) {

				$currentConvertedDate = self::convertDateTimeIntoTimeStamp();

				$qryIns = "	INSERT INTO account_template_teams (account_template_id, account_team_id, status, modified)
							VALUES ('" . addslashes($accountTemplateId) . "',
									'" . addslashes($accountTeamId) . "',
									1,
									'" . $currentConvertedDate . "')";
						
				if(DEBUG) {
					print nl2br($qryIns);
				}
				
				$accountTemplateTeamId = $this->objDBConNew->insertAndGetId($qryIns);
				
				if(!$accountTemplateTeamId) {
					print "Cant insert into 'account_template_teams', error occured.";
					return false;
				}
				else {
					$intReturn = $accountTemplateTeamId;
				}
			}
		}
		return $intReturn;
	}


	function getUsersAccountTeamId ($userId = NULL) {
		if(DEBUG) {
			print "getUsersAccountTeamId ()";
		}

		$intReturn = 0;

		if(empty($userId)) {
			return $intReturn;
		}

		$accountTeamId = $this->getAccountTeamIdFromArray($userId);

		if($accountTeamId > 0) {
			$intReturn = $accountTeamId;
		}
		else {
			$qrySel = "	SELECT atm.account_team_id accountTeamId
						FROM account_team_members atm
						WHERE atm.user_id = '" . addslashes($userId) . "'";
			
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

					if(isset($rowGetInfo['accountTeamId']) && $rowGetInfo['accountTeamId'] > 0) {
						$intReturn = $rowGetInfo['accountTeamId'];
					}
				}
			}

			$arrParamsForAccountMaster = array(
												'USERID' 	=>	$userId,
												'KEY' 		=>	array(
																		'accountTeamId' 	=> 	$intReturn
												 					)
			 								);
			$this->setAccountInformationForUser($arrParamsForAccountMaster);
		}

		return $intReturn;
	}


	function setAccountTemplateFolder ($arrParams = NULL) {
		if(DEBUG) {
			print "setAccountTemplateFolder ()";
		}

		$intReturn = 0;

		$accountTemplateId = 0;
		$accountFolderId = 0;

		if(isset($arrParams['accountTemplateId']) && $arrParams['accountTemplateId'] > 0) {
			$accountTemplateId = $arrParams['accountTemplateId'];
		}

		if(isset($arrParams['accountFolderId']) && $arrParams['accountFolderId'] > 0) {
			$accountFolderId = $arrParams['accountFolderId'];
		}

		if($accountTemplateId > 0 && $accountFolderId > 0) {

			$currentConvertedDate = self::convertDateTimeIntoTimeStamp();

			$qryIns = "	INSERT INTO account_template_folders (account_template_id, account_folder_id, status, modified)
						VALUES ('" . addslashes($accountTemplateId) . "',
								'" . addslashes($accountFolderId) . "',
								1,
								'" . addslashes($currentConvertedDate) . "')";
					
			if(DEBUG) {
				print nl2br($qryIns);
			}
			
			$accountTemplateFolderId = $this->objDBConNew->insertAndGetId($qryIns);
			
			if(!$accountTemplateFolderId) {
				print "Cant insert into 'account_template_folders', error occured.";
				return false;
			}
			else {
				$intReturn = $accountTemplateFolderId;
			}
		}
		return $intReturn;
	}


	function getDefaultTemplateFolderId ($arrParams = array()) {
		if(DEBUG) {
			print "getDefaultTemplateFolderId ()";
		}
 		
 		$intReturn = 0;
		$userId = 0;
		$accountId = 0;

		if(isset($arrParams['USERID']) && $arrParams['USERID'] > 0) {
			$userId = $arrParams['USERID'];
		}

		if(isset($arrParams['ACCOUNTID']) && $arrParams['ACCOUNTID'] > 0) {
			$accountId = $arrParams['ACCOUNTID'];
		}

		if($userId > 0 && $accountId > 0) {

			$defaultTemplateFolderId = $this->getDefaultTemplateFolderIdFromArray($userId);

			if($defaultTemplateFolderId > 0) {
				$intReturn = $defaultTemplateFolderId;
			}
			else {
				$arrShareAccess = array(
	 							'can_edit'	=> false,
	 							'can_delete'	=>	false
	 						);

	 			$convertedDateTime = self::convertDateTimeIntoTimeStamp();

				$qryIns = "	INSERT INTO account_folders (account_id, user_id, source_id, name, type, public, share_access, status, created, modified)
							VALUES ('" . addslashes($accountId) . "',
									'" . addslashes($userId) . "',
									'1',
									'Default Template Folder',
									1,
									0,
									'" . addslashes(json_encode($arrShareAccess)) . "',
									1,
									'" . $convertedDateTime . "',
									'" . $convertedDateTime . "')";

				if(DEBUG) {
					print nl2br($qryIns);
				}

				$defaultTemplateFolderId = $this->objDBConNew->insertAndGetId($qryIns);

				if(!$defaultTemplateFolderId) {
					print "Cant insert into 'account_folders', error occured.";
					return false;
				}
				else {

					$arrParamsForAccountMaster = array(
														'USERID' 	=>	$userId,
														'KEY' 		=>	array(
																				'defaultTemplateFolderId' 	=> 	$defaultTemplateFolderId
														 					)
					 								);
					$this->setAccountInformationForUser($arrParamsForAccountMaster);

					$intReturn = $defaultTemplateFolderId;
				}
			}
		}
		return $intReturn;
	}


	function getOldTemaplteData () {
		if(DEBUG) {
			print "getOldTemaplteData ()";
		}

		$arrReturn = array();

		$qrySel = "	SELECT t.*
					FROM template t
					WHERE 1 ";
print "<pre>this->templateIdAlreadyInserted :: ";
print_r($this->templateIdAlreadyInserted);
print "</pre>";
		if($this->templateIdAlreadyInserted > 0) {
			$qrySel .= "	AND t.id >= '" . addslashes($this->templateIdAlreadyInserted) . "' ";
		}
		
		$qrySel .= "	ORDER BY t.id ASC ";

		$limit = " LIMIT " . ($this->currentPage * $this->perPageRecord) . ", " . $this->perPageRecord;

		$qrySel .= $limit;

print "qrySel :: " . $qrySel . "\n";
		
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


?>