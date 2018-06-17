<?php


CLASS DOCUMENTS EXTENDS GENERALFUNCTIONS {

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

		$this->lastInsertedDocumentMasterId = 0;

		// $this->getAndSetAccountNumberInformationForUsers();
	}

	// function __destruct() {
	// 	$this->unsetUsersAccountMappingArray();
	// }

	function migrateDocumentIncrementalScript() {
		$arrLastDocumentInserted = $this->getLastDocumentInserted();

		if(!empty($arrLastDocumentInserted)) {
			$this->lastInsertedDocumentMasterId = $arrLastDocumentInserted['id'];
			$this->deleteDocumentRelatedCompleteData($arrLastDocumentInserted);
		}

		$this->startMigrationFromPendingDocumentMaster();
	}


	function deleteDocumentRelatedCompleteData($arrParams = array()) {
		$document_master_id = 0;
		if(isset($arrParams['id']) && is_numeric($arrParams['id']) && $arrParams['id'] > 0) {
			$document_master_id = $arrParams['id'];
		}

		if($document_master_id <= 0) {
			return false;
		}

		$qrySel = "	DELETE FROM document_master where id = '" . addslashes($document_master_id) . "' ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
		}
		return true;
	}


	function getLastDocumentInserted () {
		if(DEBUG) {
			print "getLastDocumentInserted ()";
		}
 		
		$arrReturn = array();

		$qrySel = "	SELECT *
					FROM document_master dm
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
				$arrReturn = $rowGetInfo;
			}
		}
		return $arrReturn;
	}


	function startMigrationFromPendingDocumentMaster () {
		if(DEBUG) {
			print "startMigrationFromPendingDocumentMaster ()";
		}
 		
		$this->objLogger->setMessage('STARTING TO MIGRATE DOCUMENT');

		while(true) {

			$arrDocumentList = $this->getDocumentDataAsPerPagination();
 		
	 		if(!empty($arrDocumentList)) {

	 			$strQuery = "";
	 			foreach ($arrDocumentList as $key => $arrEachDocumentFromOldDB) {

					$id 				= $arrEachDocumentFromOldDB['id'];
					$user_id 			= $arrEachDocumentFromOldDB['user_id'];
					$name 				= $arrEachDocumentFromOldDB['name'];
					$pretty_name 		= $arrEachDocumentFromOldDB['pretty_name'];
					$password 			= $arrEachDocumentFromOldDB['password'];
					$folder 			= $arrEachDocumentFromOldDB['folder'];
					$folder_id 			= $arrEachDocumentFromOldDB['folder_id'];
					$link 				= $arrEachDocumentFromOldDB['link'];
					$opened 			= $arrEachDocumentFromOldDB['opened'];
					$date 				= $arrEachDocumentFromOldDB['date'];
					$group_id 			= $arrEachDocumentFromOldDB['group_id'];
					$is_gif_set 		= $arrEachDocumentFromOldDB['is_gif_set'];
					$s3_bucketname 		= $arrEachDocumentFromOldDB['s3_bucketname'];
					$is_converted 		= $arrEachDocumentFromOldDB['is_converted'];

					$accountId = $this->getAccountNumberForUser($user_id);

					if(!empty($accountId) && is_numeric($accountId) && $accountId > 0) {
						
						$ext = "." . strtolower(pathinfo($name, PATHINFO_EXTENSION));

						$s3FilePath = $folder . "/" . $pretty_name;

						$s3BucketPath = $folder . "/";
						if($ext === '.pdf') {
							$s3BucketPath .= "saleshandy.pdf";
						}
						else {
							$s3BucketPath .= "thispdf.pdf";
						}

						$flagPublic = "0";
						if($folder_id == 'teamfile') {
							$flagPublic = "1";
						}

						$arrShareAccess = array(
					 							'can_edit'	=> false,
					 							'can_delete'	=>	false
					 						);

						$convertedDateTime = self::convertDateTimeIntoTimeStamp();

						$strQuery .= "(
										'" . addslashes($id) . "',  
										'" . addslashes($accountId) . "',  
										'" . addslashes($user_id) . "',  
										'1',  
										'1',  
										'" . addslashes($pretty_name) . "',  
										'" . addslashes($s3FilePath) . "',  
										'" . addslashes($s3BucketPath) . "',  
										'" . addslashes($ext) . "',  
										'0',  
										'',  
										'',  
										'" . addslashes($flagPublic) . "',  
										'" . addslashes(json_encode($arrShareAccess)) . "',  
										'0',  
										'0',  
										'1',  
										'" . addslashes($convertedDateTime) . "',  
										'" . addslashes($convertedDateTime) . "',  
										NULL
						 				), ";
					}
					else {
						$this->objLogger->setMessage('No Account id found for user ' . $user_id);
					}
	 			}

	 			if(!empty($strQuery)) {

					$strQuery = rtrim($strQuery, ", ");

					$qryIns = "	INSERT INTO document_master (id, account_id, user_id, document_source_id, source_id, file_name, file_path, bucket_path, file_type, file_pages, source_document_id, source_document_link, public, share_access, snooze_notifications, locked, status, created, modified, account_folder_id)
								VALUES " . $strQuery;

					if(DEBUG) {
						print nl2br($qryIns);
					}
					
					$document_master_id = $this->objDBConNew->insertAndGetId($qryIns);
					
					if(!$document_master_id) {
						$this->objLogger->setMessage('Cant insert into "document_master", error occured.');
					}
					$strQuery = "";
				}
				$this->objLogger->addLog();
	 		}
	 		// all documnets migrated successfully.
	 		else {
	 			print 'all documnets migrated successfully.';
	 			$this->objLogger->setMessage('all documnets migrated successfully.');
	 			$this->objLogger->addLog();
	 			break;
	 		}
		}
	}


	function getDocumentDataAsPerPagination () {
		if(DEBUG) {
			print "getDocumentDataAsPerPagination ()";
		}

		$arrReturn = array();

		$qrySel = "	SELECT *
					FROM files
					ORDER BY id ASC ";

		$limit = " LIMIT " . ($this->lastInsertedDocumentMasterId + ($this->currentPage * $this->perPageRecord)) . ", " . $this->perPageRecord;

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




// 	function migrateDocument () {
// 		if(DEBUG) {
// 			print "migrateDocument ()";
// 		}

// 		$arrAllDocumentList = $this->getAllDocumentList();

// 		if(!empty($arrAllDocumentList)) {
 			
// 			$strInsertValues = "";

//  			foreach ($arrAllDocumentList as $key => $arrAllDocumentListInfo) {
// $this->counter++;
// print "this->counter :: " . $this->counter . "\n";

//  				$id 			= $arrAllDocumentListInfo['id'];
//  				$user_id 		= $arrAllDocumentListInfo['user_id'];
//  				$name 			= $arrAllDocumentListInfo['name'];
//  				$pretty_name 	= $arrAllDocumentListInfo['pretty_name'];
//  				$password 		= $arrAllDocumentListInfo['password'];
//  				$folder 		= $arrAllDocumentListInfo['folder'];
//  				$folder_id 		= $arrAllDocumentListInfo['folder_id'];
//  				$link 			= $arrAllDocumentListInfo['link'];
//  				$opened 		= $arrAllDocumentListInfo['opened'];
//  				$date 			= $arrAllDocumentListInfo['date'];
//  				$group_id 		= $arrAllDocumentListInfo['group_id'];
//  				$is_gif_set 	= $arrAllDocumentListInfo['is_gif_set'];
//  				$s3_bucketname 	= $arrAllDocumentListInfo['s3_bucketname'];
//  				$is_converted 	= $arrAllDocumentListInfo['is_converted'];

//  				$accountId = $this->getAccountNumberForUser($user_id);

//  				if(is_numeric($accountId) && $accountId > 0) {

// 	 				$arrShareAccess = array(
// 					 							'can_edit'	=> false,
// 					 							'can_delete'	=>	false
// 					 						);

// 	 				$convertedDateTime = self::convertDateTimeIntoTimeStamp();

// 	 				$ext = pathinfo($name, PATHINFO_EXTENSION);


// 	 				$strInsertValues .= "	('" . addslashes($id) . "',
// 		 									'" . addslashes($accountId) . "',
// 		 									'" . addslashes($user_id) . "',
// 		 									'1',
// 		 									'1',
// 		 									'" . addslashes($folder) . "',
// 		 									'" . strtoupper($ext) . "',
// 		 									0,
// 		 									'" . addslashes($name) . "',
// 		 									'" . addslashes($pretty_name) . "',
// 		 									0,
// 		 									'" . addslashes(json_encode($arrShareAccess)) . "',
// 		 									1,
// 		 									1,
// 		 									'" . $convertedDateTime . "',
// 		 									'" . $convertedDateTime . "'), ";

// 	 				// $qryIns = "	INSERT INTO document_master (id, account_id, user_id, document_source_id, source_id, file_path, file_type, file_pages, source_document_id, source_document_link, public, share_access, snooze_notifications, status, created, modified)
// 	 				// 			VALUES ('" . addslashes($id) . "',
// 	 				// 					'" . addslashes($accountId) . "',
// 	 				// 					'" . addslashes($user_id) . "',
// 	 				// 					'1',
// 	 				// 					'1',
// 	 				// 					'" . addslashes($folder) . "',
// 	 				// 					'" . strtoupper($ext) . "',
// 	 				// 					0,
// 	 				// 					'" . addslashes($name) . "',
// 	 				// 					'" . addslashes($pretty_name) . "',
// 	 				// 					0,
// 	 				// 					'" . addslashes(json_encode($arrShareAccess)) . "',
// 	 				// 					1,
// 	 				// 					1,
// 	 				// 					'" . $convertedDateTime . "',
// 	 				// 					'" . $convertedDateTime . "')";

// 	 				// if(DEBUG) {
// 	 				// 	print nl2br($qryIns);
// 	 				// }

// 	 				// $documentMasterId = $this->objDBConNew->insertAndGetId($qryIns);

// 	 				// if(!$documentMasterId) {
// 	 				// 	print "Cant insert into 'document_master', error occured.";
// 	 				// 	return false;
// 	 				// }
// 	 				// else {
 						

//  					// 	/**
//  					// 	 *
//  					// 	 * Below code is commented to remove documents go into the default folder as per discussion.
//  					// 	 	now all the documents will be remain in the root folder. 
//  					// 	 *
//  					// 	 */
 						
// 	 				// 	// // function call to fetch users accounts default folder id.
// 		 			// 	// $arrParamsToFindDefaultAccountFolder = array(
// 		 			// 	// 												'ACCOUNTID' 	=>	$accountId,
// 		 			// 	// 												'USERID' 		=>	$user_id
// 		 			// 	// 											);

// 		 			// 	// $defaultAccountFolderId = $this->getDefaultAccountFolderInfo($arrParamsToFindDefaultAccountFolder);


// 	 				// 	// $qryIns = "	INSERT INTO document_folders (document_id, account_folder_id, status, modified)
// 	 				// 	// 			VALUES ('" . $documentMasterId . "',
// 	 				// 	// 					'" . $defaultAccountFolderId . "',
// 	 				// 	// 					'1',
// 	 				// 	// 					'" . $convertedDateTime . "')";

// 	 				// 	// if(DEBUG) {
// 	 				// 	// 	print nl2br($qryIns);
// 	 				// 	// }

// 	 				// 	// $documentFolderId = $this->objDBConNew->insertAndGetId($qryIns);

// 	 				// 	// if(!$documentFolderId) {
// 	 				// 	// 	print "Cant insert into 'document_folders', error occured.";
// 	 				// 	// 	return false;
// 	 				// 	// }
// 	 				// }
//  				}
//  				else {
//  					$this->objLogger->setMessage("Account id not found for the user with id (" . $user_id . ") - old file id (" . $id . ")");
//  				}
//  			}


//  			if(!empty($strInsertValues)) {

//  				$strInsertValues = rtrim($strInsertValues, ', ');

//  				$qryIns = "	INSERT INTO document_master (id, account_id, user_id, document_source_id, source_id, file_path, file_type, file_pages, source_document_id, source_document_link, public, share_access, snooze_notifications, status, created, modified)
// 	 						VALUES " . $strInsertValues;
	 					
// 	 			if(DEBUG) {
// 	 				print nl2br($qryIns);
// 	 			}
	 			
// 	 			$insertedId = $this->objDBConNew->insertAndGetId($qryIns);
	 			
// 	 			if(!$insertedId) {
// 	 				print "Cant insert into 'document_master', error occured.";
// 	 				return false;
// 	 			}
// 	 			else {
// 	 				$this->objLogger->setMessage("Batch Insert for documnet successful.");
// 	 			}
//  			}

//  			$this->objLogger->addLog();

// 			// recursive function call to get and process next records
// 			return $this->migrateDocument();
// 		}
// 		else {
// 			return true;
// 		}
// 	}


// 	function getDefaultAccountFolderInfo ($arrParams = NULL) {
// 		if(DEBUG) {
// 			print "getDefaultAccountFolderInfo ()";
// 		}

// 		$defaultAccountFolderId = 0;

// 		$userId = 0;
// 		$accountId = 0;

// 		if(isset($arrParams['ACCOUNTID']) && $arrParams['ACCOUNTID'] > 0) {
// 			$accountId = $arrParams['ACCOUNTID'];
// 		}
// 		if(isset($arrParams['USERID']) && $arrParams['USERID'] > 0) {
// 			$userId = $arrParams['USERID'];
// 		}

// 		if($accountId > 0 && $userId > 0) {

// 			$defaultAccountFolderId = $this->getDefaultAccountFolderInfoFromArray($userId);

// 			if($defaultAccountFolderId > 0) {
// 				return $defaultAccountFolderId;
// 			}
// 			else {
// 				$arrShareAccess = array(
// 	 							'can_edit'	=> false,
// 	 							'can_delete'	=>	false
// 	 						);

// 	 			$convertedDateTime = self::convertDateTimeIntoTimeStamp();

// 				$qryIns = "	INSERT INTO account_folders (account_id, user_id, source_id, name, type, public, share_access, status, created, modified)
// 							VALUES ('" . addslashes($accountId) . "',
// 									'" . addslashes($userId) . "',
// 									'1',
// 									'Default Folder',
// 									2,
// 									0,
// 									'" . addslashes(json_encode($arrShareAccess)) . "',
// 									1,
// 									'" . $convertedDateTime . "',
// 									'" . $convertedDateTime . "')";

// 				if(DEBUG) {
// 					print nl2br($qryIns);
// 				}

// 				$defaultAccountFolderId = $this->objDBConNew->insertAndGetId($qryIns);

// 				if(!$defaultAccountFolderId) {
// 					print "Cant insert into 'account_folders', error occured.";
// 					return false;
// 				}
// 				else {

// 					$arrParamsForAccountMaster = array(
// 														'USERID' 	=>	$userId,
// 														'KEY' 		=>	array(
// 																				'defaultAccountFolderId' 	=> 	$defaultAccountFolderId
// 														 					)
// 					 								);
// 					$this->setAccountInformationForUser($arrParamsForAccountMaster);
// 				}
// 			}
// 		}
// 		return $defaultAccountFolderId;
// 	}




// 	function getAllDocumentList () {
// 		if(DEBUG) {
// 			print "getAllDocumentList ()";
// 		}

// 		$arrReturn = array();

// 		$qrySel = "	SELECT *
// 					FROM files f
// 					WHERE 1 ";

// 		$limit = " LIMIT " . ($this->currentPage * $this->perPageRecord) . ", " . $this->perPageRecord;

// 		$qrySel .= $limit;

// 		if(DEBUG) {
// 			print nl2br($qrySel);
// 		}

// 		$objDBResult = $this->objDBConOld->executeQuery($qrySel);

// 		if(!$objDBResult) {
// 			print "Error occur.";
// 			return false;
// 		}
// 		else {
// 			if($objDBResult->getNumRows() > 0) {
// 				while($rowGetInfo = $objDBResult->fetchAssoc()) {
// 					array_push($arrReturn, $rowGetInfo);
// 				}
// 			}
// 		}

// 		$this->currentPage++;

// 		return $arrReturn;
// 	}
}

?>
