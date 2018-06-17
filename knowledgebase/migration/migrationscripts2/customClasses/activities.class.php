<?php


CLASS ACTIVITIES EXTENDS GENERALFUNCTIONS {

	protected $objLogger;

	function __construct ($objLogger) {
		if(DEBUG) {
			print "__construct ()";
		}

		$this->objDBConNew = new COMMONDBFUNC();

		$this->objDBConOld = new COMMONDBFUNC(HOST_OLD, PASSWORD_OLD, USERNAME_OLD, DBNAME_OLD);

		$this->objLogger = $objLogger;

		$this->perPageRecord = 3000;
		$this->currentPage = 0;

		$this->batchToExecute = 1000;

		$this->intLastActivityIdInserted = 0;
	}



	function migrateIncrementalActivities () {
		if(DEBUG) {
			print "migrateIncrementalActivities ()";
		}
 		
 		$this->objLogger->setMessage('START ACTIVITY MIGRATION PROCESS.');
 		
 		$this->objLogger->setMessage('removing entries from activity table that needs to be ignored.');
 		// $this->removeUnwantedEntriesFromActivity();

 		$intLastActivityIdInserted = $this->getLastInsertedActivityId();
 		$this->objLogger->setMessage('Last activity inserted id found on the new system activity table :: ' . $intLastActivityIdInserted);

 		if($intLastActivityIdInserted > 0) {
 			$this->intLastActivityIdInserted = $intLastActivityIdInserted;
 		}
 		
 		$this->objLogger->setMessage('STARTING MIGRATION PROCESS');
 		$this->startActivityMigrationProcess();
 		$this->objLogger->addLog();
	}


	function startActivityMigrationProcess () {
		if(DEBUG) {
			print "startActivityMigrationProcess ()";
		}
 		

 		$arrTypeGroupMapping = array(
 			"email" => array(
 								"action_id" => "2",
 								"action_group_id" => "1"
 							),
 			"email_scheduled" => array(
 								"action_id" => "3",
 								"action_group_id" => "1"
 							),
 			"email_text_open" => array(
 								"action_id" => "5",
 								"action_group_id" => "1"
 							)
 		);

		while(true) {

			$arrActivityDataFromOldDB = $this->getActivityDataFromOldDB();

			$this->objLogger->setMessage('TOTAL RECORDS FETCHED FORM THE OLD DB :: ' . COUNT($arrActivityDataFromOldDB));

			$arrTypesToIgnore = array('Campaign', 'direct_filelink', 'file-convert', 'filelink', 'filelink_email', 'join_meeting', 'meeting', 'Multi-Campaign');

			if(!empty($arrActivityDataFromOldDB)) {
				$strInsertQuery = "";
				$queryInsertCounter = 0;
				// foreach ($arrActivityDataFromOldDB as $key => $arrEachActivityRecords) {
 				for($intI = 0; $intI < COUNT($arrActivityDataFromOldDB); $intI++) {
					$this->objLogger->setMessage('---------------------------------------------------------------------');
					
					$activityId 				= $arrActivityDataFromOldDB[$intI]['activityId'];
					$activityUserId 			= $arrActivityDataFromOldDB[$intI]['activityUserId'];
					$activityModelId 			= $arrActivityDataFromOldDB[$intI]['activityModelId'];
					$activityContactId 			= $arrActivityDataFromOldDB[$intI]['activityContactId'];
					$activityType 				= $arrActivityDataFromOldDB[$intI]['activityType'];
					$activityDetails 			= $arrActivityDataFromOldDB[$intI]['activityDetails'];
					$activityFileName 			= $arrActivityDataFromOldDB[$intI]['activityFileName'];
					$activityCreatedDate 		= $arrActivityDataFromOldDB[$intI]['activityCreatedDate'];
					$activityIpAddress 			= $arrActivityDataFromOldDB[$intI]['activityIpAddress'];
					$activityCity 				= $arrActivityDataFromOldDB[$intI]['activityCity'];
					$activityState 				= $arrActivityDataFromOldDB[$intI]['activityState'];
					$activityCountry 			= $arrActivityDataFromOldDB[$intI]['activityCountry'];
					$activityUserAgent 			= $arrActivityDataFromOldDB[$intI]['activityUserAgent'];
					$activityBrowserName 		= $arrActivityDataFromOldDB[$intI]['activityBrowserName'];
					$activityVersion 			= $arrActivityDataFromOldDB[$intI]['activityVersion'];
					$activityOsPlatform 		= $arrActivityDataFromOldDB[$intI]['activityOsPlatform'];
					$gmailGroupFromEmail 		= $arrActivityDataFromOldDB[$intI]['gmailGroupFromEmail'];
					$isGmailSentFromEmail 		= $arrActivityDataFromOldDB[$intI]['isGmailSentFromEmail'];



					// check if processing any unwanted type
					$this->objLogger->setMessage('TYPE ::: ' . $activityType);
					if(in_array($activityType, $arrTypesToIgnore)) {
						$this->objLogger->setMessage('THIS ACTIVITY NO NEED TO IMPORT.. JUST IGNORE. TYPE :: ' . $activityType);
						continue;
					}
					
 					// $accountId = $this->getAccountNumberForUser($activityUserId);

 					// if(is_numeric($accountId) && $accountId > 0) {
 						
						/**
						 * Setting contact id
						 */
						$contactId = (int) $activityContactId;
						if($isGmailSentFromEmail == 1) {
							if(strpos($gmailGroupFromEmail, ',')) {
								$contactId = 'NULL';
							}
						}

						// if($contactId != 'NULL') {
						// 	$this->objLogger->setMessage('Checking for contact reference in contact mapping table :: ' . $contactId);
						// 	$arrParamsToFindContact = array();
						// 	$arrParamsToFindContact['contactId'] = $contactId;
						// 	$contactId = $this->getContactRefernecedFromTheSystemForFutherModules($arrParamsToFindContact);
							
						// 	$this->objLogger->setMessage('Result of contact id from contact reference table :: ' . $contactId);

						// 	if($contactId <= 0) {
						// 		$this->objLogger->setMessage('This contact id not found in the system :: ' . $contactId);
						// 		continue;
						// 	}
						// }

						/**
	 					 * setting loation json
	 					 */
						$arrLocation = array();
						$arrLocation['ip'] = $activityIpAddress;
						$arrLocation['c'] = $activityCity;
						$arrLocation['s'] = $activityState;
						$arrLocation['co'] = $activityCountry;
						$arrLocation['ua'] = $activityUserAgent;
						$arrLocation['bn'] = $activityBrowserName;
						$arrLocation['bv'] = $activityVersion;
						$arrLocation['os'] = $activityOsPlatform;
						$strLocation = json_encode($arrLocation);

						$convertedCreatedDate = 0;
						if(strtotime($activityCreatedDate)) {
							$convertedCreatedDate = self::convertDateTimeIntoTimeStamp($activityCreatedDate);
						}
						
						$strInsertQuery .= "	(
													" . $activityId . ",
													'" . addslashes($activityUserId) . "',
													" . addslashes($contactId) . ",
													'" . addslashes($activityModelId) . "',
													'" . addslashes($arrTypeGroupMapping[$activityType]['action_id']) . "',
													'" . addslashes($arrTypeGroupMapping[$activityType]['action_group_id']) . "',
													'" . addslashes($strLocation) . "', '" . ($convertedCreatedDate) . "'
							 					), ";

 						$queryInsertCounter++;
 						
 						if(!empty($strInsertQuery) && $queryInsertCounter > $this->batchToExecute) {
	 						$this->insertIntoActivity($strInsertQuery);

	 						$strInsertQuery = "";
	 						$queryInsertCounter = 0;
	 						$this->objLogger->addLog();
	 					}
 					// }
 					// else {
 					// 	$this->objLogger->setMessage('No account id found for user with id :: ' . $activityUserId);
 					// }
 					$this->objLogger->addLog();

				}

				unset($arrActivityDataFromOldDB);
 				
 				if(!empty($strInsertQuery)) {
 					$this->insertIntoActivity($strInsertQuery);
 				}


				$this->objLogger->addLog();
			}
			else {
				// no records found
				$this->objLogger->setMessage('No Records found on the old DB.');
				$this->objLogger->setMessage('Terminating the scrip for activity.');
				$this->objLogger->addLog();
				break;
			}
			$this->objLogger->addLog();
		}
		$this->objLogger->addLog();

		return true;
	}


	function insertIntoActivity ($strInsertQuery = '') {
		if(DEBUG) {
			print "insertIntoActivity ()";
		}

		if(!empty($strInsertQuery)) {

			$strInsertQuery = rtrim($strInsertQuery, ', ');

			$qryIns = "	INSERT INTO activity (account_id, user_id, account_contact_id, record_id, action_id, action_group_id, other_data, created)
						VALUES " . $strInsertQuery;

			if(DEBUG) {
				print nl2br($qryIns);
			}
			
			$activityInserId = $this->objDBConNew->insertAndGetId($qryIns);
			
			if(!$activityInserId) {
				print "Cant insert into 'activity', error occured."; 							
			}
		}
	}


	function checkGmailGroupMail ($arrParams = array()) {
		if(DEBUG) {
			print "checkGmailGroupMail ()";
		}
 		
 		$arrReturn = array();
 		$arrReturn['flagToContinue'] = true;
 		$arrReturn['flagDuplicateGmailActivity'] = false;

		$modelId 		= $arrParams['modelId'];
		$type 			= $arrParams['type'];
		$dateTimex 		= $arrParams['dateTimex'];


		$qrySel = "	SELECT COUNT(*) totalCount
					FROM duplicate_gmail_activity
					WHERE email_id = '" . addslashes($modelId) . "'
					AND type = '" . addslashes($type) . "'
					AND datex = '" . addslashes($dateTimex) . "'";


		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
		}
		else {
			if($objDBResult->getNumRows() > 0) {
				$rowGetInfo = $objDBResult->fetchAssoc();
				if($rowGetInfo['totalCount'] > 0) {
					$arrReturn['flagToContinue'] = false;
 					$arrReturn['flagDuplicateGmailActivity'] = true;
				}
			}
		}

		// if entry not found in duplicate email, then check in old activity table for activty records, if duplicate then enter into duplicate gmail activity table.
		if(!$arrReturn['flagDuplicateGmailActivity']) {

			$qrySel = "	SELECT COUNT(*) totalCnt
						FROM activity
						WHERE model_id = '" . addslashes($modelId) . "'
						AND type = '" . addslashes($type) . "'
						AND created_at = '" . addslashes($dateTimex) . "'";
			
			if(DEBUG) {
				print nl2br($qrySel);
			}
			
			$objDBResult = $this->objDBConOld->executeQuery($qrySel);
			
			if(!$objDBResult) {
				print "Error occur.";
			}
			else {
				if($objDBResult->getNumRows() > 0) {
					$rowGetInfo = $objDBResult->fetchAssoc();
					// this is duplicate entry
					if($rowGetInfo['totalCnt'] > 1) {

						$qryIns = "	INSERT INTO duplicate_gmail_activity (email_id, type, datex)
									VALUES ('" . addslashes($modelId) . "',
											'" . addslashes($type) . "',
											'" . addslashes($dateTimex) . "')";
								
						if(DEBUG) {
							print nl2br($qryIns);
						}
						
						$duplicateEmailActivityId = $this->objDBConNew->insertAndGetId($qryIns);
						
						if(!$duplicateEmailActivityId) {
							print "Cant insert into 'duplicate_gmail_activity', error occured.";
						}
						else {
							$arrReturn['flagToContinue'] = true;
 							$arrReturn['flagDuplicateGmailActivity'] = true;
						}
					}
				}
			}
		}
		return $arrReturn;		
	}


	function getActivityDataFromOldDB () {
		if(DEBUG) {
			print "getActivityDataFromOldDB ()";
		}

		$arrReturn = array();
 		
 		$qrySel = "	SELECT a.id activityId, a.user_id activityUserId, a.model_id activityModelId, a.contact_id activityContactId, a.type activityType, a.details activityDetails, a.file_name activityFileName, a.created_at activityCreatedDate, a.ipaddress activityIpAddress, a.city activityCity, a.state activityState, a.country activityCountry, a.user_agent activityUserAgent, a.browser_name activityBrowserName, a.version activityVersion, a.os_platform activityOsPlatform, e.gmail_group gmailGroupFromEmail, e.is_gmail_sent isGmailSentFromEmail
					FROM activity a, email e
					WHERE a.model_id = e.id
					AND a.contact_id = e.contact_id
					ORDER BY e.id ASC ";
 		
 		$limit = " LIMIT " . ($this->currentPage * $this->perPageRecord) . ", " . $this->perPageRecord;

		$qrySel .= $limit;

 		if(DEBUG) {
 			print nl2br($qrySel);
 		}

		$this->objLogger->setMessage($qrySel);

		$objDBResult = $this->objDBConOld->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
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


	function getLastInsertedActivityId () {
		if(DEBUG) {
			print "getLastInsertedActivityId ()";
		}

		$intReturn = 0;

		$qrySel = "	SELECT id
					FROM activity
					ORDER BY id DESC LIMIT 1";
		
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
				$intReturn = $rowGetInfo['id'];
			} else {
				$intReturn = 40000000;
			}
		}
		return $intReturn;
	}


	function removeUnwantedEntriesFromActivity () {
		if(DEBUG) {
			print "removeUnwantedEntriesFromActivity ()";
		}

		$qrySel = "	DELETE FROM activity WHERE type IN ('Campaign', 'direct_filelink', 'file-convert', 'filelink', 'filelink_email', 'join_meeting', 'meeting', 'Multi-Campaign') ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConOld->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
		}
	}




	function activityDump () {
		if(DEBUG) {
			print "activityDump ()";
		}

		return false;

		$activtyPerPageRecord = 5000;

		$qrySel = "	DROP TABLE IF EXISTS activity_dump ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConOld->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}



		$qrySel = "	CREATE TABLE activity_dump LIKE activity ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConOld->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}



		$counterLoop = 0;
		while(true) {
 			
 			
 			$qrySel = "	SELECT id totalCnt from activity LIMIT " . ($counterLoop * $activtyPerPageRecord) . ", " . $activtyPerPageRecord;
 			
 			if(DEBUG) {
 				print nl2br($qrySel);
 			}
 			$this->objLogger->setMessage($qrySel);

 			$objDBResult = $this->objDBConOld->executeQuery($qrySel);
 			
 			if(!$objDBResult) {
 				print "Error occur.";
 			}
 			else {
 				if($objDBResult->getNumRows() > 0) {
 					$rowGetInfo = $objDBResult->fetchAssoc();
 					
 					$this->objLogger->setMessage('Total number of records found for the ::: ' . $objDBResult->getNumRows());

 					if($rowGetInfo['totalCnt'] > 0) {

 						$qrySel = "	INSERT INTO activity_dump SELECT * from activity LIMIT " . ($counterLoop * $activtyPerPageRecord) . ", " . $activtyPerPageRecord;
			
						if(DEBUG) {
							print nl2br($qrySel);
						}
						
						$objDBResult = $this->objDBConOld->executeQuery($qrySel);
						
						if(!$objDBResult) {
							print "Error occur.";
						}
 					}
 					else {
 						$this->objLogger->setMessage("NOT ACTIVITY RECORDS FOUND.");
 						$this->objLogger->addLog();
 						break;
 					}
 				}
 				else {
					$this->objLogger->setMessage("NOT ACTIVITY RECORDS FOUND STEP 2.");
					$this->objLogger->addLog();
					break;
				}
 			}
			

 		// 	$arrTmp = array();

			// $qrySel = "	SELECT *
			// 			FROM activity a
			// 			LIMIT " . ($counterLoop * $activtyPerPageRecord) . ", " . $activtyPerPageRecord;
			
			// if(DEBUG) {
			// 	print nl2br($qrySel);
			// }
			
			// $objDBResult = $this->objDBConOld->executeQuery($qrySel);
			
			// if(!$objDBResult) {
			// 	print "Error occur.";
			// }
			// else {
			// 	if($objDBResult->getNumRows() > 0) {
			// 		while($rowGetInfo = $objDBResult->fetchAssoc()) {
			// 			array_push($arrTmp, $rowGetInfo);
			// 		}
			// 	}
 				
 		// 		$strInsertQuery = "";
			// 	if(!empty($arrTmp)) {

			// 	}

			// 	if(!empty($strInsertQuery)) {
			// 		$qryIns = "	INSERT INTO activity_dump ()
			// 					VALUES ('" . UTCDATE . "',
			// 							'" . UTCDATE . "')";
							
			// 		if(DEBUG) {
			// 			print nl2br($qryIns);
			// 		}
					
			// 		$insertedId = $this->objDBConOld->insertAndGetId($qryIns);
					
			// 		if(!$insertedId) {
			// 			print "Cant insert into 'activity_dump', error occured.";
			// 			return false;
			// 		}
			// 		else {
					
			// 		}
			// 	}
			// }
			$counterLoop++;
		}
	}

	
}
