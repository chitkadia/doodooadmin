<?php


CLASS DOCUMENTLINKS EXTENDS GENERALFUNCTIONS {

	protected $objLogger;

	function __construct ($objLogger) {
		if(DEBUG) {
			print "__construct ()";
		}

		$this->objDBConNew = new COMMONDBFUNC();

		$this->objDBConOld = new COMMONDBFUNC(HOST_OLD, PASSWORD_OLD, USERNAME_OLD, DBNAME_OLD);

		$this->objLogger = $objLogger;

		$this->perPageRecord = 1000;
		$this->currentPage = 0;

		$this->counterOldLink = 0;
		$this->counterdirectLink = 0;

		$this->arrDocumentPresentInSystem = array();


		$this->indexForDirectGenerateLink = 0;

		$this->linkDomain = 'https://shdocs.cultofpassion.com';

		$this->currentPageDocumentVisit = 0;
		$this->perPageRecordDocumentVisit = 10;

		$this->getIdOfAllDocumentPresentInTheSystem();
	}

	function startMigarationForDocumentVists () {
		if(DEBUG) {
			print "startMigarationForDocumentVists ()";
		}
 		
 		$this->objLogger->setMessage('STARTING SCRIPT FOR DOCUMENT LINK VISIT');
		$this->lastDocumentLinkVisitId = $this->getLastDocumentLinkVistId();

		$this->objLogger->setMessage('LAST INSERTED DOCUMENT VISIT LINK :::: ' . $this->lastDocumentLinkVisitId);
		if($this->lastDocumentLinkVisitId > 0) {
			$this->objLogger->setMessage('DELETING LAST RECORD FOR THE DOCUMENT LINK VIST');
			$this->deleteLastDocumentLinkVistRecord($this->lastDocumentLinkVisitId);
		}
 		$this->objLogger->setMessage('STARTING THE MIGRATION FOR DOCUMENT LINK VISIT');
 		$this->objLogger->addLog();
		$this->startMigrationForDocumentLinkVisit();
		$this->objLogger->addLog();
	}


	function startMigrationForDocumentLinkVisit () {
		if(DEBUG) {
			print "startMigrationForDocumentLinkVisit ()";
		}

		while(true) {
			$arrFileLinkLocationInformation = $this->getDataForDocumentLinkvisit();

			if(!empty($arrFileLinkLocationInformation)) {
				foreach ($arrFileLinkLocationInformation as $key => $arrEachViewedFileDataFromOldDB) {

					$this->objLogger->setMessage('==============================================================================');
					$this->objLogger->setMessage('==============================================================================');
					$this->objLogger->setMessage('==============================================================================');


					$id 				= $arrEachViewedFileDataFromOldDB['id'];
					$file_id 			= $arrEachViewedFileDataFromOldDB['file_id'];
					$open_datetime 		= $arrEachViewedFileDataFromOldDB['open_datetime'];
					$view_duration 		= $arrEachViewedFileDataFromOldDB['view_duration'];
					$filelink_link 		= $arrEachViewedFileDataFromOldDB['filelink_link'];
					$contact_id 		= $arrEachViewedFileDataFromOldDB['contact_id'];
					$stamp 				= $arrEachViewedFileDataFromOldDB['stamp'];
					$slide_number 		= $arrEachViewedFileDataFromOldDB['slide_number'];
					$ipaddress 			= $arrEachViewedFileDataFromOldDB['ipaddress'];
					$city 				= $arrEachViewedFileDataFromOldDB['city'];
					$state 				= $arrEachViewedFileDataFromOldDB['state'];
					$country 			= $arrEachViewedFileDataFromOldDB['country'];
					$browser_name 		= $arrEachViewedFileDataFromOldDB['browser_name'];
					$os_platform 		= $arrEachViewedFileDataFromOldDB['os_platform'];
					$filelink_id 		= $arrEachViewedFileDataFromOldDB['filelink_id'];
					$counter 			= $arrEachViewedFileDataFromOldDB['counter'];
					$type 				= $arrEachViewedFileDataFromOldDB['type'];
 					
 					

 					// NEED TO CHECK IF FILE PRESENT IN THE NEW SYSTEM OR NOT
					$flagFileIdPresent = $this->checkDocumentIdPresentInSystem($file_id);

					if(!$flagFileIdPresent) {
						$this->objLogger->setMessage('No File found in the new system with id ::: ' . $file_id);
						continue;
					}


 					$arrParamsForGettingDocumentLink = array();
 					$arrParamsForGettingDocumentLink['fileId'] = $file_id;
 					$arrParamsForGettingDocumentLink['fileLinkCode'] = $filelink_link;
 					$arrParamsForGettingDocumentLink['type'] = $type;

					$arrCheckForFileLink = $this->checkForFileExistsInNewSystemAnd($arrParamsForGettingDocumentLink);

					$flagFileLinkInSystem = $arrCheckForFileLink['flagFileLinkInSystem'];
					$documentLinkIdForThisLinkVisit = $arrCheckForFileLink['documentLinkIdForThisLinkVisit'];

					if(!$flagFileLinkInSystem) {
						$this->objLogger->setMessage('No File link found in the new system for id :: ' . $file_id . ' and type :: ' . $type);
						continue;
					}

					$flagCheckContactExists = $this->checkContactExists($contact_id);
					if(!$flagCheckContactExists) {
						$this->objLogger->setMessage('Contact id not found in the new system for contact id :: ' . $contact_id);
						continue;
					}

					$arrLocation = array(
						"c" => $city,
						"s" => $state,
						"co" => $country,
						"ip" => $ipaddress,
						"bn" => $browser_name,
						"os" => $os_platform
					);
					$strJsonLocation = json_encode($arrLocation);

					$qryIns = "	INSERT INTO document_link_visits (id, document_link_id, account_contact_id, location, download, modified)
								VALUES ('" . addslashes($id) . "',
										'" . addslashes($documentLinkIdForThisLinkVisit) . "',
										'" . addslashes($contact_id) . "',
										'" . addslashes($strJsonLocation) . "',
										0,
										0)";
							
					if(DEBUG) {
						print nl2br($qryIns);
					}
					
					$documentLinkVisitId = $this->objDBConNew->insertAndGetId($qryIns);

					if(!$documentLinkVisitId) {
						$this->objLogger->setMessage("Cant insert into 'document_link_visits', error occured.");
					}
					else {
						$this->objLogger->setMessage('documentLinkVisit entry made with id :::: ' . $documentLinkVisitId);
 						
 						$arrParamsToEnterDocumentLinkVisitLogs = array();
 						$arrParamsToEnterDocumentLinkVisitLogs['stamp'] = $stamp;
 						$arrParamsToEnterDocumentLinkVisitLogs['documentLinkVisitId'] = $documentLinkVisitId;
 						$arrParamsToEnterDocumentLinkVisitLogs['documentId'] = $file_id;
 						$arrParamsToEnterDocumentLinkVisitLogs['type'] = $type;

 						$this->insertIntoDocumentLinkVisitLogs($arrParamsToEnterDocumentLinkVisitLogs);

 						$this->objLogger->setMessage('Entries are made for the document link visit logs.');

					}
				}
				$this->objLogger->addLog();
			}
			else {
				$this->objLogger->setMessage('No more records found for filelinks_location');
				$this->objLogger->setMessage('Terminating script');
				$this->objLogger->addLog();
				break;
			}
		}
		$this->objLogger->addLog();
		return true;
	}

	function insertIntoDocumentLinkVisitLogs ($arrParams = array()) {
		if(DEBUG) {
			print "insertIntoDocumentLinkVisitLogs ()";
		}

		$stamp = 0;
		if(isset($arrParams['stamp'])) {
			$stamp = $arrParams['stamp'];
		}

		$documentLinkVisitId = 0;
		if(isset($arrParams['documentLinkVisitId'])) {
			$documentLinkVisitId = $arrParams['documentLinkVisitId'];
		}

		$documentId = 0;
		if(isset($arrParams['documentId'])) {
			$documentId = $arrParams['documentId'];
		}

		$type = 0;
		if(isset($arrParams['type'])) {
			$type = $arrParams['type'];
		}

		if($stamp == 0 || $documentId == 0 || $documentLinkVisitId == 0) {
			return false;
		}
 		
 		$arrListOfLogs = array();
 		/**
 		 * Get all the logs option
 		 */
		$qrySel = "	SELECT *
					FROM viewed_file 
					WHERE stamp = '" . addslashes($stamp) . "'
					ORDER BY id ASC";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConOld->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
		}
		else {
			if($objDBResult->getNumRows() > 0) {
				while($rowGetInfo = $objDBResult->fetchAssoc()) {
					array_push($arrListOfLogs, $rowGetInfo);
				}
			}
		}

		if(!empty($arrListOfLogs)) {
			foreach ($arrListOfLogs as $key => $arrEachLogEntry) {
				$id 				= 	$arrEachLogEntry['id'];
				$file_id 			= 	$arrEachLogEntry['file_id'];
				$open_datetime 		= 	$arrEachLogEntry['open_datetime'];
				$view_duration 		= 	$arrEachLogEntry['view_duration'];
				$filelink_link 		= 	$arrEachLogEntry['filelink_link'];
				$contact_id 		= 	$arrEachLogEntry['contact_id'];
				$stamp 				= 	$arrEachLogEntry['stamp'];
				$slide_number 		= 	$arrEachLogEntry['slide_number'];
				$ipaddress 			= 	$arrEachLogEntry['ipaddress'];
				$city 				= 	$arrEachLogEntry['city'];
				$state 				= 	$arrEachLogEntry['state'];
				$country 			= 	$arrEachLogEntry['country'];
				$browser_name 		= 	$arrEachLogEntry['browser_name'];
				$os_platform 		= 	$arrEachLogEntry['os_platform'];
				$filelink_id 		= 	$arrEachLogEntry['filelink_id'];
				$counter 			= 	$arrEachLogEntry['counter'];
				$type 				= 	$arrEachLogEntry['type'];
 				


 				$qryIns = "	INSERT INTO document_link_visit_logs (id, visit_id, document_id, doc_version, page_num, time_spent, download, modified)
 							VALUES ('" . addslashes($id) . "',
 									'" . addslashes($documentLinkVisitId) . "',
 									'" . addslashes($documentId) . "',
 									1,
 									'" . addslashes($slide_number) . "',
 									'" . addslashes($view_duration) . "',
 									0,
 									0)";
 						
 				if(DEBUG) {
 					print nl2br($qryIns);
 				}
 				
 				$documentLinkVisitLogId = $this->objDBConNew->insertAndGetId($qryIns);
 				
 				if(!$documentLinkVisitLogId) {
 					print "Cant insert into 'document_link_visit_logs', error occured.";
 				}
 				else {
 					$this->objLogger->setMessage('Document link visit logs entry made ::: ' . $documentLinkVisitLogId);
 				}
			}
		}

	}


	function checkContactExists ($contactId = NULL) {
		if(DEBUG) {
			print "checkContactExists ()";
		}

		$flagReturn = false;

		if(empty($contactId)) {
			return $flagReturn;
		}

		if(!isset($this->arrContactExistCheck)) {
			$this->arrContactExistCheck = array();
		}
 		
		if(COUNT($this->arrContactExistCheck) > 2000) {
			array_slice($this->arrContactExistCheck, 200);
		}

		if(isset($this->arrContactExistCheck[$contactId])) {
			return $this->arrContactExistCheck[$contactId];
		}
		else {
			$qrySel = "	SELECT *
						FROM account_contacts
						WHERE id = '" . addslashes($contactId) . "'";
			
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
					// $rowGetInfo = $objDBResult->fetchAssoc();
					$flagReturn = true;
					$this->arrContactExistCheck[$contactId] = $flagReturn;
				}
			}
		}

		return $flagReturn;
	}


	function checkForFileExistsInNewSystemAnd ($arrParams = array()) {
		if(DEBUG) {
			print "checkForFileExistsInNewSystemAnd ()";
		}
 		
		$this->objLogger->setMessage('inside checkForFileExistsInNewSystemAnd function ::: ');
		$this->objLogger->setMessage('data for this function :: ' . json_encode($arrParams));
		
		$arrReturn = array();
		$arrReturn['flagFileLinkInSystem'] = false;
		$arrReturn['documentLinkIdForThisLinkVisit'] = 0;

		$fileId = 0;
		if(isset($arrParams['fileId']) && $arrParams['fileId'] > 0) {
			$fileId = $arrParams['fileId'];
		}

		$fileLinkCode = '';
		if(isset($arrParams['fileLinkCode']) && !empty($arrParams['fileLinkCode'])) {
			$fileLinkCode = $arrParams['fileLinkCode'];
		}

		$type = 0;
		if(isset($arrParams['type']) && $arrParams['type'] > 0) {
			$type = $arrParams['type'];
		}
 		
 		$linkCode = $fileId . '--||--' . $fileLinkCode;

 		if($fileId <= 0 || empty($fileLinkCode)) {
 			return $arrReturn;
 		}

 		if(!isset($this->arrFileLinkIdMappingWithDocumentLinks)) {
 			$this->arrFileLinkIdMappingWithDocumentLinks = array();
 		}

 		if(COUNT($this->arrFileLinkIdMappingWithDocumentLinks) > 2000) {
 			array_slice($this->arrFileLinkIdMappingWithDocumentLinks, 200);
 		}

 		if(isset($this->arrFileLinkIdMappingWithDocumentLinks[$linkCode])) {
 			$this->objLogger->setMessage('Returning data from cache');
 			$this->objLogger->setMessage('return ::: ' . json_encode($this->arrFileLinkIdMappingWithDocumentLinks[$linkCode]));

 			return $this->arrFileLinkIdMappingWithDocumentLinks[$linkCode];
 		}
 		else {

 			$qrySel = "	SELECT dl.id documentLinkId
 						FROM document_links dl, document_link_files dlf
 						WHERE dlf.document_link_id = dl.id
 						AND dlf.document_id = '" . addslashes($fileId) . "'
 						AND dl.link_code = '" . addslashes($fileLinkCode) . "'";

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

 					$arrReturn['flagFileLinkInSystem'] = true;
					$arrReturn['documentLinkIdForThisLinkVisit'] = $rowGetInfo['documentLinkId'];

					$this->arrFileLinkIdMappingWithDocumentLinks[$linkCode] = $arrReturn;
 				}
 			}
 		}
 		
 		$this->objLogger->setMessage('return ::: ' . json_encode($arrReturn));

 		return $arrReturn;
	}

	function getDataForDocumentLinkvisit () {
		if(DEBUG) {
			print "getDataForDocumentLinkvisit ()";
		}

		$arrReturn = array();

		$qrySel = "	SELECT * 
					FROM viewed_file 
					WHERE 1 ";

		if($this->lastDocumentLinkVisitId > 0) {
			$qrySel .= " AND id > '" . addslashes($this->lastDocumentLinkVisitId) . "' ";
		}

		$qrySel .= " 	GROUP BY stamp 
		 				ORDER BY id ";

		$limit = " LIMIT " . $this->currentPageDocumentVisit * $this->perPageRecordDocumentVisit . ", " . $this->perPageRecordDocumentVisit;

		$qrySel .= $limit;

		if(DEBUG) {
			print nl2br($qrySel);
		}
		
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

		$this->currentPageDocumentVisit++;
		return $arrReturn;
	}

	function deleteLastDocumentLinkVistRecord ($lastDocumentLinkVisitId = NULL) {
		if(DEBUG) {
			print "deleteLastDocumentLinkVistRecord ()";
		}

		if(empty($lastDocumentLinkVisitId)) {
			return false;
		}


		$qrySel = "	DELETE FROM document_link_visit_logs WHERE visit_id = '" . addslashes($lastDocumentLinkVisitId) . "'";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		$this->objLogger->setMessage($qrySel);
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
		}


		$qrySel = "	DELETE FROM document_link_visits WHERE id = '" . addslashes($lastDocumentLinkVisitId) . "'";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		$this->objLogger->setMessage($qrySel);
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
		}

		return true;
	}

	function getLastDocumentLinkVistId () {
		if(DEBUG) {
			print "getLastDocumentLinkVistId ()";
		}

		$intReturn = 0;

		$qrySel = "	SELECT id
					FROM document_link_visits dlv
					ORDER BY id DESC";
		
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
			}
		}

		return $intReturn;
	}



 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
 	/*******************************************************************************************************************************/
	function startMigarationForDirectGenerationLinks () {
		if(DEBUG) {
			print "startMigarationForDirectGenerationLinks ()";
		}
 		
 		$this->objLogger->setMessage('STARTING DIRECT GENERATED FILE LINKS. IN FUNCTION startMigarationForDirectGenerationLinks');
		$arrResponseForLastDirectLinkGeneratedId = $this->geLastDirectGenerationLinkId();

		$lastDocumentGeneratedLinkId = $arrResponseForLastDirectLinkGeneratedId['lastDocumentGeneratedLinkId'];
print "lastDocumentGeneratedLinkId :: " . $lastDocumentGeneratedLinkId . "\n";
		$lastDocumentNormalLinkId = $arrResponseForLastDirectLinkGeneratedId['lastDocumentNormalLinkId'];
print "lastDocumentNormalLinkId :: " . $lastDocumentNormalLinkId . "\n";
		$indexForDirectGenerateLink = $arrResponseForLastDirectLinkGeneratedId['indexForDirectGenerateLink'];
print "indexForDirectGenerateLink :: " . $indexForDirectGenerateLink . "\n";

		if($lastDocumentGeneratedLinkId > 0) {
			$this->objLogger->setMessage('LAST DIRECTED LINK ID FOUND.....');
			$this->deleteDocumentLinkEntry($lastDocumentGeneratedLinkId);
			$this->indexForDirectGenerateLink = $indexForDirectGenerateLink;
		}

		return $this->functionalityForDirectGeneratedFileLinkMigration();
	}


	function functionalityForDirectGeneratedFileLinkMigration () {
		if(DEBUG) {
			print "functionalityForDirectGeneratedFileLinkMigration ()";
		}

		$this->objLogger->setMessage('STARTING MIGRATION OF DIRECTED GENERATED LINKS.');
		$this->objLogger->addLog();

		while(true) {

			$arrDirectGeneratedLink = $this->getDirectGeneratedLinkFromOldDb();
$this->objLogger->setMessage('TOTAL RECORDS FOUND :: ', COUNT($arrDirectGeneratedLink));
			if(!empty($arrDirectGeneratedLink)) {

				foreach ($arrDirectGeneratedLink as $key => $arrEachRecordForDirectGeneratedLink) {

					$id 					= $arrEachRecordForDirectGeneratedLink['id'];
					$user_id 				= $arrEachRecordForDirectGeneratedLink['user_id'];
					$file_id 				= $arrEachRecordForDirectGeneratedLink['file_id'];
					$link 					= $arrEachRecordForDirectGeneratedLink['link'];
					$position 				= $arrEachRecordForDirectGeneratedLink['position'];
					$contact_id 			= $arrEachRecordForDirectGeneratedLink['contact_id'];
					$title 					= $arrEachRecordForDirectGeneratedLink['title'];
					$opened_links 			= $arrEachRecordForDirectGeneratedLink['opened_links'];
					$opened_date 			= $arrEachRecordForDirectGeneratedLink['opened_date'];
					$visitor_info 			= $arrEachRecordForDirectGeneratedLink['visitor_info'];
					$allowed_download 		= $arrEachRecordForDirectGeneratedLink['allowed_download'];
					$track_forwad 			= $arrEachRecordForDirectGeneratedLink['track_forwad'];
					$password 				= $arrEachRecordForDirectGeneratedLink['password'];
					$created_date 			= $arrEachRecordForDirectGeneratedLink['created_date'];
 					
 					$accountId = $this->getAccountNumberForUser($user_id);

 					if(is_numeric($accountId) && $accountId > 0) {
 						
 						$accountCompanyId = 'NULL';
 						$accountContactId = 'NULL';
 						if(COUNT(explode(',', $contact_id)) == 1) {

 							$arrResponseForCompayInfo = $this->getCompanyInformation($contact_id);
 							$accountContactIdFromCache 	= $arrResponseForCompayInfo['accountContactId'];
 							$accountIdFromCache 			= $arrResponseForCompayInfo['accountId'];
 							$accountCompanyIdFromCache 	= $arrResponseForCompayInfo['accountCompanyId'];

 							$accountCompanyId = 'NULL';
 							if(!empty($accountCompanyIdFromCache) && $accountCompanyIdFromCache > 0) {
 								$accountCompanyId = $accountCompanyIdFromCache;
 							}
 							$accountContactId = trim($contact_id);
 						}

 						$flagPasswordProtected = 0;
 						$passwordToSave = "";

 						if(!empty($password)) {
 							$flagPasswordProtected = 1;
 							$passwordToSave = $password;
 						}

 						$convertdLastOpenedDate = self::convertDateTimeIntoTimeStamp($opened_date);
 						$convertdCreatedDate = self::convertDateTimeIntoTimeStamp($created_date);
 						 
						$arrFieldsToAsk = array();
 						if($visitor_info > 0) {
 							$arrFieldsToAsk = array(
								"email",
								"company",
								"name"
							);
 						}

 						$qryIns = "	INSERT INTO document_links (account_id, user_id, source_id, account_company_id, account_contact_id, name, link_domain, link_code, type, is_set_expiration_date, expires_at, forward_tracking, email_me_when_viewed, allow_download, password_protected, access_password, ask_visitor_info, visitor_info_payload, snooze_notifications, remind_not_viewed, remind_at, visitor_slug, last_opened_by, last_opened_at, status, created, modified)
 									VALUES ('" . addslashes($accountId) . "',
 											'" . addslashes($user_id) . "',
 											1,
 											" . $accountCompanyId . ",
 											" . $accountContactId . ",
 											'" . addslashes($title) . "',
 											'" . addslashes($this->linkDomain) . "',
 											'" . addslashes($link) . "',
 											2,
 											0,
 											0,
 											'" . addslashes($track_forwad) . "',
 											0,
 											'" . addslashes($allowed_download) . "',
 											'" . addslashes($flagPasswordProtected) . "',
 											'" . addslashes($passwordToSave) . "',
 											'" . addslashes($visitor_info) . "',
 											'" . addslashes(json_encode($arrFieldsToAsk)) . "',
 											0,
 											0,
 											0,
 											'',
 											NULL,
 											'" . addslashes($convertdLastOpenedDate) . "',
 											1,
 											'" . addslashes($convertdCreatedDate) . "',
 											0)";
 								
 						if(DEBUG) {
 							print nl2br($qryIns);
 						}

 						$documentLinkId = $this->objDBConNew->insertAndGetId($qryIns);
 						
 						if(!$documentLinkId) {
 							$this->objLogger->setMessage("Cant insert into 'document_links', error occured.");
 						}
 						else {
 							$this->objLogger->setMessage('Document inserted with id ' . $documentLinkId . ' and link code ' . $link);

							$arrParamsForInsertingInLinkFile = array();
							$arrParamsForInsertingInLinkFile['documentLinkId'] = $documentLinkId;
							$arrParamsForInsertingInLinkFile['linkCode'] = $link;
							$arrParamsForInsertingInLinkFile['fileId'] = $file_id;
							$arrParamsForInsertingInLinkFile['flagDirectLinkGenerate'] = true;

							$this->insertIntoDocumentLinkFile($arrParamsForInsertingInLinkFile);
 						}
 					}
 					else {
 						$this->objLogger->setMessage('No Account id found for user id ' . $user_id);
 					}
				}
				$this->objLogger->addLog();
			}
			// if all the records for direct link generation is finished then terminate the script execution.
			else {
				$this->objLogger->setMessage('All the records for direct generated links are finished.');
				$this->objLogger->setMessage('Exiting from the loop for insert new data.');
				$this->objLogger->addLog();
				break;
			}
		}

		$this->objLogger->setMessage('Script Execution finished.');
		$this->objLogger->setMessage('Terminating the script');
		$this->objLogger->addLog();
		return true;
	}


	function getCompanyInformation ($contactId = NULL) {
		if(DEBUG) {
			print "getCompanyInformation ()";
		}


		$arrReturn = array();
		$arrReturn['accountContactId'] = 0;
		$arrReturn['accountId'] = 0;
		$arrReturn['accountCompanyId'] = 0;
 		
 		if(empty($contactId)) {
 			return $arrReturn;
 		}

		if(!isset($this->arrContactCompanyAccountMapping)) {
			$this->arrContactCompanyAccountMapping = array();
		}

		if(COUNT($this->arrContactCompanyAccountMapping) > 2000) {
			array_slice($this->arrContactCompanyAccountMapping, 200);
		}

		if(isset($this->arrContactCompanyAccountMapping[$contactId])) {
			return $this->arrContactCompanyAccountMapping[$contactId];
		}
		else {
			$qrySel = "	SELECT ac.id accountContactId, ac.account_id accountId, ac.account_company_id accountCompanyId
	 					FROM account_contacts ac
	 					WHERE ac.id = '" . addslashes($contactId) . "'";
	 		
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

	 				$arrReturn['accountContactId'] = $rowGetInfo['accountContactId'];
					$arrReturn['accountId'] = $rowGetInfo['accountId'];
					$arrReturn['accountCompanyId'] = $rowGetInfo['accountCompanyId'];

					$this->arrContactCompanyAccountMapping[$contactId] = $arrReturn;
	 			}
	 		}
		}
		return $arrReturn;
	}


	function getDirectGeneratedLinkFromOldDb () {
		if(DEBUG) {
			print "getDirectGeneratedLinkFromOldDb ()";
		}

		$arrReturn = array();

		$qrySel = "	SELECT *
					FROM generate_direct_filelink gdf ";

		$limit = " LIMIT " . ($this->indexForDirectGenerateLink + ($this->currentPage * $this->perPageRecord)) . ", " . $this->perPageRecord;

		$qrySel .= $limit;

$this->objLogger->setMessage('Query generated for getting direct link records');
$this->objLogger->setMessage($qrySel);

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


	function geLastDirectGenerationLinkId () {
		if(DEBUG) {
			print "geLastDirectGenerationLinkId ()";
		}
 		
		$this->objLogger->setMessage('INSIDE geLastDirectGenerationLinkId');

		$arrReturn = array();
		$arrReturn['lastDocumentGeneratedLinkId'] = 0;
		$arrReturn['lastDocumentNormalLinkId'] = 0;
		$arrReturn['indexForDirectGenerateLink'] = 0;

		$intReturn = 0 ;
		$lastDocumentGeneratedLinkId = 0;

		$qrySel = "	SELECT id
					FROM document_links
					ORDER BY id DESC
					LIMIT 1";
		
		$this->objLogger->setMessage('query to execute');
		$this->objLogger->setMessage($qrySel);

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

$this->objLogger->setMessage('LAST RECORD FOUND FROM THE DATABASE USING QUERY.');
print "<pre>rowGetInfo :: ";
print_r($rowGetInfo);
print "</pre>";
				$lastDocumentGeneratedLinkId = $rowGetInfo['id'];
print "lastDocumentGeneratedLinkId :: " . $lastDocumentGeneratedLinkId . "\n";
			}
		}

		if($lastDocumentGeneratedLinkId > 0) {
$this->objLogger->setMessage('FOUND ID IN DOCUMENT_LINKS TABLE.');
			$lastDocumentNormalLinkId = $this->getLastDocumentLinkIdBeforeGoingToDirectLinkGeneration();
print "lastDocumentNormalLinkId :: " . $lastDocumentNormalLinkId . "\n";
$this->objLogger->setMessage('LASTID OF THE DOCUMENTLINKS FROM THE FILE ::::::: ' . $lastDocumentNormalLinkId);

			if($lastDocumentGeneratedLinkId > $lastDocumentNormalLinkId) {
$this->objLogger->setMessage('SEEMS LIKE DIRECT LINK MIGRATION SCRIPT STARTED BEFORE, GETTING THE LATEST ID.');
				$intReturn = $lastDocumentGeneratedLinkId;

				$arrReturn['lastDocumentGeneratedLinkId'] = $lastDocumentGeneratedLinkId;
				$arrReturn['lastDocumentNormalLinkId'] = $lastDocumentNormalLinkId;
				$arrReturn['indexForDirectGenerateLink'] = (($lastDocumentGeneratedLinkId) - ($lastDocumentNormalLinkId));

			}
		}
		return $arrReturn;
	}


	/*-------------------------------------------------------------------------------------------------------------------------*/
	/*-------------------------------------------------------------------------------------------------------------------------*/
	/*-------------------------------------------------------------------------------------------------------------------------*/
	/*-------------------------------------------------------------------------------------------------------------------------*/
	/*-------------------------------------------------------------------------------------------------------------------------*/
	/*-------------------------------------------------------------------------------------------------------------------------*/
	/*-------------------------------------------------------------------------------------------------------------------------*/





	function migrateDocumentLinksIncremental () {
		if(DEBUG) {
			print "migrateDocumentLinksIncremental ()";
		}

		$this->lastDocumentLinkInsertId = $this->getLastInsertedDocumentLink();

		if(is_numeric($this->lastDocumentLinkInsertId) && $this->lastDocumentLinkInsertId > 0) {
			$this->deleteDocumentLinkEntry($this->lastDocumentLinkInsertId);
		}

		$this->migrateDocumentLinksNewFlow();
	}

	function deleteDocumentLinkEntry ($documentLinkId = NULL) {
		if(DEBUG) {
			print "deleteDocumentLinkEntry ()";
		}

		if(empty($documentLinkId) || !is_numeric($documentLinkId) || $documentLinkId <= 0) {
			return false;
		}

		$qrySel = "	DELETE FROM document_link_files where document_link_id = '" . addslashes($documentLinkId) . "' ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
		}



		$qrySel = "	DELETE FROM document_links where id = '" . addslashes($documentLinkId) . "' ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
		}

		return true;
	}


	function migrateDocumentLinksNewFlow () {
		if(DEBUG) {
			print "migrateDocumentLinksNewFlow ()";
		}

		$this->objLogger->setMessage('STARTING IMPORTING DOCUEMNT LINK MODULE');

		// while(true) {
		$flagAllLinksMigrated = false;
		do {
			$arrFileLinkData = $this->getFilelinksMasterDataFromOldDB();

			if(!empty($arrFileLinkData)) {

				foreach ($arrFileLinkData as $key => $arrEachFileLinkFromOldDB) {

					$id							=	$arrEachFileLinkFromOldDB['id'];
					$user_id					=	$arrEachFileLinkFromOldDB['user_id'];
					$file_id					=	$arrEachFileLinkFromOldDB['file_id'];
					$link						=	$arrEachFileLinkFromOldDB['link'];
					$title						=	$arrEachFileLinkFromOldDB['title'];
					$opened_links				=	$arrEachFileLinkFromOldDB['opened_links'];
					$created_date				=	$arrEachFileLinkFromOldDB['created_date'];
					$opened_date				=	$arrEachFileLinkFromOldDB['opened_date'];
					$is_email					=	$arrEachFileLinkFromOldDB['is_email'];
					$is_campaign				=	$arrEachFileLinkFromOldDB['is_campaign'];
					$template_id				=	$arrEachFileLinkFromOldDB['template_id'];
					$campaign_template_id		=	$arrEachFileLinkFromOldDB['campaign_template_id'];
					$position					=	$arrEachFileLinkFromOldDB['position'];
					$visitor_info				=	$arrEachFileLinkFromOldDB['visitor_info'];
					$allow_download				=	$arrEachFileLinkFromOldDB['allow_download'];
					$password					=	$arrEachFileLinkFromOldDB['password'];
					$status						=	$arrEachFileLinkFromOldDB['status'];

					$accountId = $this->getAccountNumberForUser($user_id);

					if(is_numeric($accountId) && $accountId > 0) {

						$linkType = 1;

						if($is_email == "1") {
							$linkType = 3;
						}
						else if($is_campaign == "1") {
							$linkType = 4;
						}

						$flagAllowDownload = 0;
						if(is_numeric($allow_download) && $allow_download == 1) {
							$flagAllowDownload = 1;
						}

						$flagPasswordProtected = 0;
						$accessPassword = "";
						if(!empty($password)) {
							$flagPasswordProtected = 1;
							$accessPassword = $password;
						}

						$flagVisitorInfo = 0;
						$arrFieldsToAsk = array();
						if(is_numeric($visitor_info) && $visitor_info == 1) {
							$flagVisitorInfo = 1;
							$arrFieldsToAsk = array(
								"email",
								"company",
								"name"
							);
						}

						$lastOpenedAt = self::convertDateTimeIntoTimeStamp($opened_date);
						$createdDate = self::convertDateTimeIntoTimeStamp($created_date);


						$qryIns = "	INSERT INTO document_links (account_id, user_id, source_id, account_company_id, account_contact_id, name, link_domain, link_code, type, is_set_expiration_date, expires_at, forward_tracking, email_me_when_viewed, allow_download, password_protected, access_password, ask_visitor_info, visitor_info_payload, snooze_notifications, remind_not_viewed, remind_at, visitor_slug, last_opened_by, last_opened_at, status, created, modified)
									VALUES ('" . addslashes($accountId) . "',
									 		'" . addslashes($user_id) . "',
									 		'1',
									 		NULL,
									 		NULL,
									 		'" . addslashes($title) . "',
									 		'" . addslashes($this->linkDomain) . "',
									 		'" . addslashes($link) . "',
									 		'" . addslashes($linkType) . "',
									 		'0',
									 		'0',
									 		'0',
									 		'0',
									 		'" . addslashes($flagAllowDownload) . "',
									 		'" . addslashes($flagPasswordProtected) . "',
									 		'" . addslashes($accessPassword) . "',
									 		'" . addslashes($flagVisitorInfo) . "',
									 		'" . addslashes(json_encode($arrFieldsToAsk)) . "',
									 		0,
									 		0,
									 		0,
									 		'',
									 		NULL,
									 		'" . addslashes($lastOpenedAt) . "',
									 		1,
									 		'" . addslashes($createdDate) . "',
											0)";
								
						if(DEBUG) {
							print nl2br($qryIns);
						}
						
						$documentLinkId = $this->objDBConNew->insertAndGetId($qryIns);
						
						if(!$documentLinkId) {
							print "Cant insert into 'document_links', error occured.";
						}
						else {

							$this->objLogger->setMessage('Document inserted with id ' . $documentLinkId . ' and link code ' . $link);

							$arrParamsForInsertingInLinkFile = array();
							$arrParamsForInsertingInLinkFile['documentLinkId'] = $documentLinkId;
							$arrParamsForInsertingInLinkFile['linkCode'] = $link;
							$arrParamsForInsertingInLinkFile['fileId'] = $file_id;

							$this->insertIntoDocumentLinkFile($arrParamsForInsertingInLinkFile);
						}
					}
					else {
						$this->objLogger->setMessage('No account master entry found for user ' . $user_id);
					}
				}
				$this->objLogger->setMessage('BATCH EXECUTION FINISHED.....................');
				$this->objLogger->addLog();

			}
			else {
				$this->objLogger->setMessage('No more data found in the old database for migration.');
				$this->objLogger->setMessage('Terminating script');
				$this->objLogger->addLog();

				$this->saveLastDocumentLinkIdBeforeGoingToDirectLinkGeneration();

				$flagAllLinksMigrated = true;
			}
		} while(!$flagAllLinksMigrated);

		$this->objLogger->setMessage('FINISHED IMPORTING DOCUEMNT LINK MODULE');
		$this->objLogger->addLog();


		return true;
		// $this->objLogger->setMessage('---------------------------------------');
		// $this->objLogger->setMessage('---------------------------------------');
		// $this->objLogger->setMessage('---------------------------------------');
		// $this->objLogger->setMessage('---------------------------------------');
		// $this->objLogger->setMessage('---------------------------------------');
		// $this->objLogger->setMessage('---------------------------------------');
		// $this->objLogger->setMessage('---------------------------------------');
		// $this->objLogger->setMessage('STARTING TO IMPORT DIRECT LINK GENERATE');
		// $this->objLogger->setMessage('---------------------------------------');
		// $this->objLogger->setMessage('---------------------------------------');
		// $this->objLogger->setMessage('---------------------------------------');
		// $this->objLogger->setMessage('---------------------------------------');
		// $this->objLogger->setMessage('---------------------------------------');
		// $this->objLogger->setMessage('---------------------------------------');
		// $this->objLogger->setMessage('---------------------------------------');
		// $this->objLogger->addLog();

		// return $this->startMigarationForDirectGenerationLinks();
	}

	function getLastDocumentLinkIdBeforeGoingToDirectLinkGeneration () {
		if(DEBUG) {
			print "getLastDocumentLinkIdBeforeGoingToDirectLinkGeneration ()";
		}
 		
 		$lastDocumentLinkId = 0;


		$fiePath = __DIR__ . '/../logs/lastdocuemtnlinkidinserted.txt';
print "<pre>fiePath :: ";
print_r($fiePath);
print "</pre>\n\n";
		if(file_exists($fiePath)) {
			$lastDocumentLinkId = (int) file_get_contents($fiePath);
print 'lastDocumentLinkId :: ' . $lastDocumentLinkId . "\n";

print 'empty($lastDocumentLinkId) :: ' . empty($lastDocumentLinkId) . "\n";
print '!is_numeric($lastDocumentLinkId) :: ' . !is_numeric($lastDocumentLinkId) . "\n";
print '$lastDocumentLinkId <= 0 :: ' . $lastDocumentLinkId <= 0 . "\n";

			if(empty($lastDocumentLinkId) || !is_numeric($lastDocumentLinkId) || $lastDocumentLinkId <= 0) {
				$lastDocumentLinkId = 0;
			}
		}
print "lastDocumentLinkId TO RETURN  :: " . $lastDocumentLinkId . "\n";
		return $lastDocumentLinkId;
	}


	function saveLastDocumentLinkIdBeforeGoingToDirectLinkGeneration () {
		if(DEBUG) {
			print "saveLastDocumentLinkIdBeforeGoingToDirectLinkGeneration ()";
		}

		$qrySel = "	SELECT id
					FROM document_links
					ORDER BY id DESC
					LIMIT 1";
		
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
				$lastDocumentLinkId = $rowGetInfo['id'];
				$fiePath = __DIR__ . '/../logs/lastdocuemtnlinkidinserted.txt';
				file_put_contents($fiePath, $lastDocumentLinkId);
			}
		}
	}


	function insertIntoDocumentLinkFile ($arrParams = array()) {
		if(DEBUG) {
			print "insertIntoDocumentLinkFile ()";
		}

		if(!isset($arrParams['documentLinkId']) || !is_numeric($arrParams['documentLinkId']) || $arrParams['documentLinkId'] <= 0) {
			return false;
		}

		if(!isset($arrParams['linkCode']) || empty($arrParams['linkCode'])) {
			return false;
		}

		if(!isset($arrParams['fileId']) || empty($arrParams['fileId'])) {
			return false;
		}

		$flagDirectLinkGenerate = false;
		if(isset($arrParams['flagDirectLinkGenerate']) && $arrParams['flagDirectLinkGenerate'] == true) {
			$flagDirectLinkGenerate = $arrParams['flagDirectLinkGenerate'];
		}
 		
 		$arrFileRecordsForLinkCode = array(); 		
 		// get file records for normal file links
		if(!$flagDirectLinkGenerate) {
			$arrFileRecordsForLinkCode = $this->getFileLinkRecordsForAPerticularLinks($arrParams['linkCode']);
		}
		// get file records from the params for directedgenerated file link
		else {
			$arrFileRecordsForLinkCode[] = array('file_id' => $arrParams['fileId']);
		}

		if(!empty($arrFileRecordsForLinkCode)) {
			foreach ($arrFileRecordsForLinkCode as $key => $arrEachFileInfoFromOldDb) {

				if(!empty($arrEachFileInfoFromOldDb['file_id'])) {

					$arrFilesInLink = explode(",", $arrEachFileInfoFromOldDb['file_id']);

					if(!empty($arrFilesInLink)) {
						$strDocumentFileLinkQuery = "";
						foreach ($arrFilesInLink as $key => $eachDocumentId) {
							$eachDocumentId = trim($eachDocumentId);

							if(is_numeric($eachDocumentId) && $eachDocumentId > 0) {

								$flagFileExists = $this->checkDocumentIdPresentInSystem($eachDocumentId);

								if($flagFileExists) {
									$strDocumentFileLinkQuery .= "(
				 													'" . addslashes($arrParams['documentLinkId']) . "',
				 													'" . addslashes($eachDocumentId) . "',
				 													1,
				 													0
				 					 								), ";
								}
								else {
									$this->objLogger->setMessage('file not found in new system i.e document_master for docuemnt link id ' . $arrParams['documentLinkId'] . ' and link code ' . $arrParams['linkCode']);
								}
							}
							else {
								$this->objLogger->setMessage('Invalid file id for docuemnt link id ' . $arrParams['documentLinkId'] . ' and link code ' . $arrParams['linkCode']);
							}
						}

						if(!empty($strDocumentFileLinkQuery)) {
							$strDocumentFileLinkQuery = rtrim($strDocumentFileLinkQuery, ', ');

							$qryIns = "	INSERT INTO document_link_files (document_link_id, document_id, status, modified)
										VALUES " . $strDocumentFileLinkQuery;
									
							if(DEBUG) {
								print nl2br($qryIns);
							}
							
							$insertedId = $this->objDBConNew->insertAndGetId($qryIns);
							
							if(!$insertedId) {
								print "Cant insert into 'document_link_files', error occured.";
							}
						}
					}
					else {
						$this->objLogger->setMessage('No File found for docuemnt link id ' . $arrParams['documentLinkId'] . ' and link code ' . $arrParams['linkCode']);
					}
				}
			}
		}
	}


	function getFileLinkRecordsForAPerticularLinks ($linkCode = NULL) {
		if(DEBUG) {
			print "getFileLinkRecordsForAPerticularLinks ()";
		}

		$arrReturn = array();

		if(empty($linkCode)) {
			return $arrReturn;
		}

		$qrySel = "	SELECT *
					FROM filelinks
					WHERE link = '" . addslashes($linkCode) . "'";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
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

		return $arrReturn;
	}

	function checkDocumentIdPresentInSystem ($documentId = NULL) {
		if(DEBUG) {
			print "checkDocumentIdPresentInSystem ()";
		}

		$flagReturn = false;

		if(empty($documentId)) {
			return $flagReturn;
		}

		if(isset($this->arrDocumentPresentInSystem[$documentId])) {
			$flagReturn = true;
		}

		return $flagReturn;
	}


	function getFilelinksMasterDataFromOldDB () {
		if(DEBUG) {
			print "getFilelinksMasterDataFromOldDB ()";
		}
 		
		$arrReturn = array();

		$qrySel = "	SELECT *
					FROM filelinks
					GROUP BY link
					ORDER BY id ASC ";
		
		$limit = " LIMIT " . ($this->lastDocumentLinkInsertId + ($this->currentPage * $this->perPageRecord)) . ", " . $this->perPageRecord;

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


	function getLastInsertedDocumentLink () {
		if(DEBUG) {
			print "getLastInsertedDocumentLink ()";
		}
 		
		$intReturn = 0;

		$qrySel = "	SELECT *
					FROM document_links dl
					ORDER BY id DESC
					LIMIT 1";
		
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

				$intReturn = $rowGetInfo['id'];
			}
		}
		return $intReturn;
	}


	function migrateDocumentLinks () {
		if(DEBUG) {
			print "migrateDocumentLinks ()";
		}


		// print "\n-----------------------------------------------------------------------------------";
		// print "\n------------------------DIRECT LINK MIGRATION START--------------------------------";
		// print "\n-----------------------------------------------------------------------------------";
		
		
		// $this->currentPage = 0;
		// $this->objLogger->setMessage("Direct link generation LOG START");

		// return $this->migrateDirectLinkGenration();

		$arrOldMigrationLink = $this->getOldMigrationLink();

		if(!empty($arrOldMigrationLink)) {

			foreach ($arrOldMigrationLink as $key => $arrOldMigrationInfo) {

				$fileIdExists 			= $arrOldMigrationInfo['fileIdExists'];
				$linkCount 				= $arrOldMigrationInfo['linkCount'];
				$id 					= $arrOldMigrationInfo['id'];
				$user_id 				= $arrOldMigrationInfo['user_id'];
				$file_id 				= $arrOldMigrationInfo['file_id'];
				$link 					= $arrOldMigrationInfo['link'];
				$title 					= $arrOldMigrationInfo['title'];
				$opened_links 			= $arrOldMigrationInfo['opened_links'];
				$created_date 			= $arrOldMigrationInfo['created_date'];
				$opened_date 			= $arrOldMigrationInfo['opened_date'];
				$is_email 				= $arrOldMigrationInfo['is_email'];
				$is_campaign 			= $arrOldMigrationInfo['is_campaign'];
				$template_id 			= $arrOldMigrationInfo['template_id'];
				$campaign_template_id 	= $arrOldMigrationInfo['campaign_template_id'];
				$position 				= $arrOldMigrationInfo['position'];
				$visitor_info 			= $arrOldMigrationInfo['visitor_info'];
				$allow_download 		= $arrOldMigrationInfo['allow_download'];
				$password 				= $arrOldMigrationInfo['password'];
				$status 				= $arrOldMigrationInfo['status'];

				$accountId = $this->getAccountNumberForUser($user_id);

				$passwordProtected = 0;
				if(!empty($password)) {
					$passwordProtected = 1;
				}

				if($fileIdExists == 1 && is_numeric($accountId) && $accountId > 0) {
print "this->counterOldLink :: " . $this->counterOldLink++ . "\n";
 					$convertedCreated = self::convertDateTimeIntoTimeStamp();
 					
 					$docLinkPrefix = 's';
 					if($linkCount > 1) {
 						$docLinkPrefix = 'm';
 					}


					$arrParamsToInsertDocumentLinks = array(
																'accountId' 			=>	$accountId,
																'userId' 				=>	$user_id,
																'name' 					=>	$title,
																'shortDescription' 		=>	'',
																'docLinkPrefix' 		=>	$docLinkPrefix,
																'linkCode' 				=>	$link,
																'type' 					=>	1,
																'isSetExpirationDate'	=>	0,
																'expiresAt' 			=>	0,
																'allowedDownload' 		=>	$allow_download,
																'passwordProtected' 	=>	$passwordProtected,
																'accessPassword' 		=>	$password,
																'askVisitorInfo' 		=>	$visitor_info,
																'visitorInfoPayload' 	=>	json_encode(array()),
																'snoozeNotifications' 	=>	1,
																'remindNotViewed' 		=>	0,
																'remindAt' 				=>	0,
																'status' 				=>	$status,
																'created' 				=>	$convertedCreated,
																'modified' 				=>	$convertedCreated
															);

					$documentLinkid = $this->insertIntoDocumentLinkTable($arrParamsToInsertDocumentLinks);

 					// if document link generated
					if($documentLinkid > 0) {
						// map the document files with the document link.
						if(is_numeric($file_id) && $file_id > 0) {
 							$documentLinkFileStatus = 1;
							if($status != 0) {
								$documentLinkFileStatus = 2;
							}
 							
							$arrParamsInsertIntoDocumentLinkAndFileMapping = array(
																					'documentLinkId'	=>	$documentLinkid, 
																					'documentId'		=>	$file_id, 
																					'openedLinks' 		=>	$opened_links,
																					'openedDate' 		=>	NULL,
																					'position' 			=>	$position,
																					'status'			=>	$documentLinkFileStatus, 
																					'modified'			=>	$convertedCreated
																				);

							$documentLinkFileId = $this->insertIntoDocuemtLinkFileTable($arrParamsInsertIntoDocumentLinkAndFileMapping);
						}
					}
				}
				else {
					if($fileIdExists != 1) {
						$this->objLogger->setMessage("Document File dosent exists in our system. but present in old database - (" . $id . ")");
					}
					else {
						// no account found for this user
						$this->objLogger->setMessage("Account id not found for the user with id (" . $user_id . ") - old file id (" . $id . ")");
					}
				}
			}

			$this->objLogger->addLog();

			return $this->migrateDocumentLinks();
		}
		else {
 			print "\n-----------------------------------------------------------------------------------";
 			print "\n------------------------DIRECT LINK MIGRATION START--------------------------------";
 			print "\n-----------------------------------------------------------------------------------";
 			
 			$this->currentPage = 0;
 			$this->objLogger->setMessage("Direct link generation LOG START");

 			return $this->migrateDirectLinkGenration();

			// return true;
		}
	}


	function migrateDirectLinkGenration () {
		if(DEBUG) {
			print "migrateDirectLinkGenration ()";
		}


		$arrDirectLink = $this->getDirectLinks();

		if(!empty($arrDirectLink)) {
			foreach ($arrDirectLink as $key => $arrDirectLinkInfo) {

print "this->counterdirectLink :: " . $this->counterdirectLink++ . "\n";

				$fileIdExists 		= $arrDirectLinkInfo['fileIdExists'];
				$id 				= $arrDirectLinkInfo['id'];
				$user_id 			= $arrDirectLinkInfo['user_id'];
				$file_id 			= $arrDirectLinkInfo['file_id'];
				$link 				= $arrDirectLinkInfo['link'];
				$position 			= $arrDirectLinkInfo['position'];
				$contact_id 		= $arrDirectLinkInfo['contact_id'];
				$title 				= $arrDirectLinkInfo['title'];
				$opened_links 		= $arrDirectLinkInfo['opened_links'];
				$opened_date 		= $arrDirectLinkInfo['opened_date'];
				$visitor_info 		= $arrDirectLinkInfo['visitor_info'];
				$allowed_download 	= $arrDirectLinkInfo['allowed_download'];
				$track_forwad 		= $arrDirectLinkInfo['track_forwad'];
				$password 			= $arrDirectLinkInfo['password'];
				$created_date 		= $arrDirectLinkInfo['created_date'];


				$accountId = $this->getAccountNumberForUser($user_id);

				if($fileIdExists == 1 && !empty($contact_id) && is_numeric($accountId) && $accountId > 0) {
					$convertedCreated = self::convertDateTimeIntoTimeStamp();
					$convertedOpenedDate = self::convertDateTimeIntoTimeStamp($opened_date);

					$passwordProtected = 0;
					if(!empty($password)) {
						$passwordProtected = 1;
					}

					$arrParamsToInsertDocumentLinks = array(
																'accountId' 			=>	$accountId,
																'userId' 				=>	$user_id,
																'name' 					=>	$title,
																'shortDescription' 		=>	$title,
																'docLinkPrefix'			=>	's',
																'linkCode' 				=>	$link,
																'type' 					=>	1,
																'isSetExpirationDate'	=>	0,
																'expiresAt' 			=>	0,
																'allowedDownload' 		=>	$allowed_download,
																'passwordProtected' 	=>	$passwordProtected,
																'accessPassword' 		=>	$password,
																'askVisitorInfo' 		=>	$visitor_info,
																'visitorInfoPayload' 	=>	json_encode(array()),
																'snoozeNotifications' 	=>	1,
																'remindNotViewed' 		=>	0,
																'remindAt' 				=>	0,
																'status' 				=>	1,
																'created' 				=>	$convertedCreated,
																'modified' 				=>	$convertedCreated
															);

					$documentLinkid = $this->insertIntoDocumentLinkTable($arrParamsToInsertDocumentLinks);

						// if document link generated
					if($documentLinkid > 0) {
						// map the document files with the document link.
						// if(is_numeric($file_id) && $file_id > 0) {
						if(!empty($file_id)) {
							$arrEachFileId = explode(',', $file_id);
							if(COUNT($arrEachFileId) > 0) {
								foreach ($arrEachFileId as $key => $eachFileIdValue) {
									$documentLinkFileStatus = 1;
									$arrParamsInsertIntoDocumentLinkAndFileMapping = array(
																							'documentLinkId'	=>	$documentLinkid, 
																							'documentId'		=>	$eachFileIdValue, 
																							'openedLinks' 		=>	$opened_links,
																							'openedDate' 		=>	$convertedOpenedDate,
																							'position' 			=>	$position,
																							'status'			=>	$documentLinkFileStatus, 
																							'modified'			=>	$convertedCreated
																						);
									$documentLinkFileId = $this->insertIntoDocuemtLinkFileTable($arrParamsInsertIntoDocumentLinkAndFileMapping);
								}
							}
						}					
					}

					// adding contacts related to the links.
					if(!empty($contact_id)) {
						$arrContactId = explode(',', $contact_id);

						if(!empty($arrContactId)) {

							$arrParamsToInsertDocumentLinkContact = array(
																			'arrContact' 		=>	$arrContactId,
																			'documentLinkid' 	=>	$documentLinkid
																		);

							$this->insertIntoDocumentLinkContactTable($arrParamsToInsertDocumentLinkContact);
						}
					}
				}
				else {
					
					if($fileIdExists != 1) {
						$this->objLogger->setMessage("Document File dosent exists in our system. but present in old database - (" . $id . ")");
					}
					else if(empty($contact_id) || $contact_id <= 0){
						// account number not found for the user
						$this->objLogger->setMessage("Contact Id is empty in direct link generation table contact_id - (" . $contact_id . ")");
					}
					else {
						// no account found for this user
						$this->objLogger->setMessage("Account id not found for the user with id (" . $user_id . ") - old file id (" . $id . ")");
					}
				}
			}

			$this->objLogger->addLog();

			return $this->migrateDirectLinkGenration();
		}
		else {
			return true;
		}
	}


	function insertIntoDocumentLinkContactTable ($arrParams) {
		if(DEBUG) {
			print "insertIntoDocumentLinkContactTable ()";
		}

		if((isset($arrParams['arrContact']) && !empty($arrParams['arrContact'])) && 
		 	(isset($arrParams['documentLinkid']) && is_numeric($arrParams['documentLinkid']))) {

			$arrContact = $arrParams['arrContact'];
 			
			$strInsQry = "";
 			
 			$convertedCreated = self::convertDateTimeIntoTimeStamp();

			foreach ($arrContact as $key => $contactId) {
				$strInsQry .= " (
			 						'" . addslashes($contactId) . "',
			 						'" . addslashes($arrParams['documentLinkid']) . "',
			 						1,
			 						'" . addslashes($convertedCreated) . "'
			 					), ";
			}
 			
			if(!empty($strInsQry)) {
				$strInsQry = rtrim($strInsQry, ', ');

				$qryIns = "	INSERT INTO document_link_contact (contact_id, document_link_id, status, modified)
							VALUES " . $strInsQry;
						
				if(DEBUG) {
					print nl2br($qryIns);
				}

				$directDocuemtLinkId = $this->objDBConNew->insertAndGetId($qryIns);
				
				if(!$directDocuemtLinkId) {
					print "Cant insert into 'document_link_contact', error occured.";
					return false;
				}
				else {
					return true;
				}
			}
		}
	}


	function getDirectLinks () {
		if(DEBUG) {
			print "getDirectLinks ()";
		}

		$arrReturn = array();

		$qrySel = "	SELECT (SELECT COUNT(*) FROM files f WHERE f.id = gdf.file_id) fileIdExists, gdf.*
					FROM generate_direct_filelink gdf
					WHERE 1 ";

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


	function insertIntoDocuemtLinkFileTable ($arrParams = array()) {
		if(DEBUG) {
			print "insertIntoDocuemtLinkFileTable ()";
		}

		$intReturn = 0;

		$arrKeysMandatory = array(
									'documentLinkId', 
									'documentId', 
									'openedLinks', 
									'openedDate', 
									'position', 
									'status', 
									'modified'
								);

		if(empty($arrParams)) {
			return $intReturn;
		}

		foreach ($arrParams as $key => $arrParamsValues) {
			if(!in_array($key, $arrKeysMandatory)) {
				return $intReturn;
			}
		}

		$flagDocumentIdPresent = $this->checkDocumentIdPresentInSystem($arrParams['documentId']);

		if($flagDocumentIdPresent) {
			if(!is_numeric($arrParams['position'])) {
				$arrParams['position'] = 1;
			}

			if(empty($arrParams['openedDate']) || $arrParams['openedDate'] == NULL) {
				$arrParams['openedDate'] = 0;
			}

			$qryIns = "	INSERT INTO document_link_files (document_link_id, document_id, opened_links, opened_date, position, status, modified)
						VALUES ('" . addslashes($arrParams['documentLinkId']) . "',
								'" . addslashes($arrParams['documentId']) . "',
								'" . addslashes($arrParams['openedLinks']) . "',
								'" . addslashes($arrParams['openedDate']) . "',
								'" . addslashes($arrParams['position']) . "',
								'" . addslashes($arrParams['status']) . "',
								'" . addslashes($arrParams['modified']) . "'
								)";

			if(DEBUG) {
				print nl2br($qryIns);
			}
			
			$documentLinkFileId = $this->objDBConNew->insertAndGetId($qryIns);
			
			if(!$documentLinkFileId) {
				print "Cant insert into 'document_link_files', error occured.";
				return false;
			}
			else {
				$intReturn = $documentLinkFileId;
			}
		}
		else {
			// document ID not present in the system.
		}

		return $intReturn;
	}


	



	function insertIntoDocumentLinkTable ($arrParams) {
		if(DEBUG) {
			print "insertIntoDocumentLinkTable ()";
		}

		$intReturn = 0;

		$arrKeysMandatory = array( 
									'accountId', 
									'userId', 
									'name', 
									'shortDescription', 
									'linkCode', 
									'type', 
									'isSetExpirationDate', 
									'expiresAt', 
									'allowedDownload', 
									'passwordProtected', 
									'accessPassword', 
									'askVisitorInfo', 
									'visitorInfoPayload', 
									'snoozeNotifications', 
									'remindNotViewed', 
									'remindAt', 
									'status', 
									'created', 
									'modified'
								);

		if(empty($arrParams)) {
			return $intReturn;						
		}

		foreach ($arrParams as $key => $arrParamsValues) {
			if(!in_array($key, $arrKeysMandatory)) {
				return $intReturn;
			}
		}

		$randomDomain = TRACKING_PIXEL[rand(0, 4)];

		$qryIns = "	INSERT INTO document_links (account_id, user_id, source_id, name, short_description, link_domain, link_code, type, is_set_expiration_date, expires_at, allow_download, password_protected, access_password, ask_visitor_info, visitor_info_payload, snooze_notifications, remind_not_viewed, remind_at, status, created, modified)
					VALUES ('" . addslashes($arrParams['accountId']) . "',
							'" . addslashes($arrParams['userId']) . "',
							1,
							'" . addslashes($arrParams['name']) . "',
							'" . addslashes($arrParams['shortDescription']) . "',
							'" . addslashes($randomDomain) . "',
							'" . addslashes($arrParams['linkCode']) . "',
							'" . addslashes($arrParams['type']) . "',
							'" . addslashes($arrParams['isSetExpirationDate']) . "',
							'" . addslashes($arrParams['expiresAt']) . "',
							'" . addslashes($arrParams['allowedDownload']) . "',
							'" . addslashes($arrParams['passwordProtected']) . "',
							'" . addslashes($arrParams['accessPassword']) . "',
							'" . addslashes($arrParams['askVisitorInfo']) . "',
							'" . addslashes($arrParams['visitorInfoPayload']) . "',
							'" . addslashes($arrParams['snoozeNotifications']) . "',
							'" . addslashes($arrParams['remindNotViewed']) . "',
							'" . addslashes($arrParams['remindAt']) . "',
							'" . addslashes($arrParams['status']) . "',
							'" . addslashes($arrParams['created']) . "',
							'" . addslashes($arrParams['modified']) . "'
							)";
				
		if(DEBUG) {
			print nl2br($qryIns);
		}
		
		$documentLinkId = $this->objDBConNew->insertAndGetId($qryIns);
		
		if(!$documentLinkId) {
			print "Cant insert into 'document_links', error occured.";
		}
		else {
			$intReturn = $documentLinkId;
		}

		return $intReturn;
	}


	function getOldMigrationLink () {
		if(DEBUG) {
			print "getOldMigrationLink ()";
		}

		$arrReturn = array();

		// $qrySel = "	SELECT *
		// 			FROM filelinks fl
		// 			WHERE 1 ";

		$qrySel = "	SELECT (SELECT COUNT(*) 
							FROM filelinks fls 
							WHERE fls.link = fl1.link 
							GROUP BY fls.link) linkCount, 
							(SELECT COUNT(*) FROM files f WHERE f.id = fl1.file_id) fileIdExists, 
							fl1.* 
					FROM filelinks fl1 
					WHERE 1 ";

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


	function getIdOfAllDocumentPresentInTheSystem () {
		if(DEBUG) {
			print "getIdOfAllDocumentPresentInTheSystem ()";
		}

		$arrReturn = array();

		$qrySel = "	SELECT dm.id
					FROM document_master dm ";
		
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
				while($rowGetInfo = $objDBResult->fetchAssoc()) {
					$this->arrDocumentPresentInSystem[$rowGetInfo['id']] = $rowGetInfo['id'];
				}
			}
		}
		return $arrReturn;
	}




}