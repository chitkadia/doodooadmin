<?php


CLASS GENERALFUNCTIONS {
	function GENERALFUNCTIONS () {
		if(DEBUG) {
			print "GENERALFUNCTIONS ()";
		}

		$this->objDBConNew = new COMMONDBFUNC();
		$this->objDBConOld = new COMMONDBFUNC(HOST_OLD, PASSWORD_OLD, USERNAME_OLD, DBNAME_OLD);

        $this->arrUserAccountMapping = array();
	}


	public static function convertDateTimeIntoTimeStamp($dateTime = NULL) {
		if(DEBUG) {
			print "convertDateTimeIntoTimeStamp ({$dateTime})";
		}

		$timeStamp = 0;
		
		if(empty($dateTime)) {
			$dateTime = gmdate('Y-m-d H:i:s');
		}

		$date = new DateTime($dateTime, new DateTimeZone('Asia/Kolkata'));
		$date->setTimeZone(new DateTimeZone('UTC'));
		$convertedDate = $date->format('Y-m-d H:i:s');
        
        if(strtotime($convertedDate) > 0) {
            $timeStamp = strtotime($convertedDate);
        }

		return $timeStamp;
	}


	public function generateAccountNumber() {
        $random_string = "SHAPP-" . date("YmdHis") . "-" . rand("111", "999");

        $qrySel = "	SELECT COUNT(*) totalCnt
        			FROM account_master
        			WHERE ac_number = '" . addslashes($random_string) . "'";
        
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
        		if($rowGetInfo['totalCnt'] > 0) {
        			return $this->generateAccountNumber();
        		}
        	}
        }

        return $random_string;
    }


    function getUserSettingsAppVars () {
    	if(DEBUG) {
    		print "getUserSettingsAppVars ()";
    	}

    	$arrReturn = array();

    	$qrySel = "	SELECT av.id, av.code, av.name, av.val
    				FROM app_constant_vars av
    				WHERE av.code LIKE 'U_%' ";
    	
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
    				array_push($arrReturn, $rowGetInfo);
    			}
    		}
    	}

    	return $arrReturn;
    }

    function getAccountNumberForUser ($userId = NULL) {
        if(DEBUG) {
            print "getAccountNumberForUser ()";
        }

        $intReturn = 0;
        if(empty($userId)) {
            return $intReturn;
        }
        
        //  if array arrUserAccountMapping gets stored more than 1000 records, 
        //  then flush the array. So that number of keys reduce which take 
        //  less time to search in array

        if(!isset($this->arrUserAccountMapping)) {
            $this->arrUserAccountMapping = array();
        }

        if(COUNT($this->arrUserAccountMapping) > 1000) {
            // array_slice($this->arrUserAccountMapping, 100, COUNT($this->arrUserAccountMapping), true);
            // $this->arrUserAccountMapping = array();
            // array_shift($this->arrUserAccountMapping);
            $this->arrUserAccountMapping = array();
        }

        if(isset($this->arrUserAccountMapping[$userId])) {
            $intReturn = $this->arrUserAccountMapping[$userId]['accountMasterId'];
        }
        else {
            $arrAccountDetail = $this->getAccountInformationForPerticularUser($userId);

            if(!empty($arrAccountDetail)) {
                $intReturn = $arrAccountDetail['accountMasterId'];
            }
        }

        return $intReturn;
    }


    function getAndSetAccountNumberInformationForUsers () {
        if(DEBUG) {
            print "getAndSetAccountNumberInformationForUsers ()";
        }

        $arrReturn = array();

        $qrySel = " SELECT um.id userId, am.id accountMasterId
                    FROM account_master am, user_master um
                    WHERE um.account_id = am.id ";
        
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
                    $this->arrUserAccountMapping[$rowGetInfo['userId']] = $rowGetInfo;
                }
            }
        }
    }


    function unsetUsersAccountMappingArray () {
        if(DEBUG) {
            print "unsetUsersAccountMappingArray ()";
        }

        unset($this->arrUserAccountMapping);
    }

    function getAccountInformationForPerticularUser ($userId = NULL) {
        if(DEBUG) {
            print "getAccountInformationForPerticularUser ()";
        }

        $arrReturn = array();

        if(empty($userId)) {
            return $arrReturn;
        }

        $qrySel = " SELECT um.account_id accountMasterId
                    FROM user_master um
                    WHERE um.id = '" . addslashes($userId) . "'";
        
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
                $this->arrUserAccountMapping[$userId] = $rowGetInfo;
                $arrReturn = $rowGetInfo;
            }
        }

        return $arrReturn;
    }

    function setAccountInformationForUser ($arrParams = NULL) {
        if(DEBUG) {
            print "setAccountInformationForUser ()";
        }

        if(!isset($arrParams['USERID']) || !is_numeric($arrParams['USERID']) || $arrParams['USERID'] <= 0) {
            return false;
        }


        if(!isset($arrParams['KEY']) || empty($arrParams['KEY'])) {
            return false;
        }

        if(!isset($this->arrUserAccountMapping[$arrParams['USERID']])) {
            $this->arrUserAccountMapping[$arrParams['USERID']] = array();
        }

        foreach ($arrParams['KEY'] as $key => $arrKeyValue) {
            $this->arrUserAccountMapping[$arrParams['USERID']][$key] = $arrKeyValue;
        }
        
    }


    function getAccountBillingMasterIdForUser ($userId = NULL) {
        if(DEBUG) {
            print "getAccountBillingMasterIdForUser ()";
        }
        
        $intReturn = 0;

        if(empty($userId)) {
            return $intReturn;
        }

        if(isset($this->arrUserAccountMapping[$userId])) {
            if(isset($this->arrUserAccountMapping[$userId]['accountBillingMasterId'])) {
                $intReturn = $this->arrUserAccountMapping[$userId]['accountBillingMasterId'];
            }
        }
        return $intReturn;
    }

    function getDefaultAccountFolderInfoFromArray ($userId = NULL) {
        if(DEBUG) {
            print "getDefaultAccountFolderInfoFromArray ()";
        }
        
        $intReturn = 0;

        if(empty($userId)) {
            return $intReturn;
        }

        if(isset($this->arrUserAccountMapping[$userId])) {
            if(isset($this->arrUserAccountMapping[$userId]['defaultAccountFolderId'])) {
                $intReturn = $this->arrUserAccountMapping[$userId]['defaultAccountFolderId'];
            }
        }
        return $intReturn;
    }

    function getDefaultTemplateFolderIdFromArray ($userId = NULL) {
        if(DEBUG) {
            print "getDefaultTemplateFolderIdFromArray ()";
        }
        $intReturn = 0;

        if(empty($userId)) {
            return $intReturn;
        }

        if(isset($this->arrUserAccountMapping[$userId])) {
            if(isset($this->arrUserAccountMapping[$userId]['defaultTemplateFolderId'])) {
                $intReturn = $this->arrUserAccountMapping[$userId]['defaultTemplateFolderId'];
            }
        }
        return $intReturn;
    }


    function getAccountTeamIdFromArray ($userId = NULL) {
        if(DEBUG) {
            print "getAccountTeamIdFromArray ()";
        }
        $intReturn = 0;

        if(empty($userId)) {
            return $intReturn;
        }

        if(isset($this->arrUserAccountMapping[$userId])) {
            if(isset($this->arrUserAccountMapping[$userId]['accountTeamId'])) {
                $intReturn = $this->arrUserAccountMapping[$userId]['accountTeamId'];
            }
        }
        return $intReturn;
    }


    function checkDuplicateContactForAccount ($arrParams = array()) {
        if(DEBUG) {
            print "checkDuplicateContactForAccount ()";
        }


        $arrReturn = array();
        $arrReturn['flagToContinue'] = false;
        $arrReturn['contactIdNeedToUse'] = 0;


        $accountId = 0;
        if(!isset($arrParams['accountId']) || (isset($arrParams['accountId']) && (!is_numeric($arrParams['accountId']) || $arrParams['accountId'] <= 0) ) ) {
            return $arrReturn;
        }
        else {
            $accountId = $arrParams['accountId'];
        }

        $contactId = 0;
        if(!isset($arrParams['contactId']) || (isset($arrParams['contactId']) && (!is_numeric($arrParams['contactId']) || $arrParams['contactId'] <= 0) ) ) {
            return $arrReturn;
        }
        else {
            $contactId = $arrParams['contactId'];
        }

        $contactEmail = 0;
        if(!isset($arrParams['contactEmail']) || (isset($arrParams['contactEmail']) && (!empty($arrParams['contactEmail']) && filter_var($arrParams['contactEmail'], FILTER_VALIDATE_EMAIL) ) ) ) {
            return $arrReturn;
        }
        else {
            $contactEmail = $arrParams['contactEmail'];
        }

        /**
         *
         * If addContactIdRefer array is not present, then initialize the array.
         *
         */
        if(!isset($this->arrContactIdRefer)) {
            $this->arrContactIdRefer = array();
        }
        
        /**
         *
         * If contact id is present in the key of arrContactIdRefer then returned the mapped actual contactid then needs to be used.
         *
         */
        if(isset($this->arrContactIdRefer[$contactId])) {
            $arrReturn['flagToContinue'] = true;
            $arrReturn['contactIdNeedToUse'] = $this->arrContactIdRefer[$contactId];
        }
        /**
         *
         * If contact id is not present in the arrContactIdRefer then search in the database.
         *
         */
        else {

            /**
             *
             * First search in the reference mapping table, for the mainContactId
             *
             */
            $arrParamsForCheckInReferTable = array(
                                                    'contactId'     =>  $contactId
                                                );
            $referedContactId = $this->checkContactReferDatabaseOnlyFromContactMappingTable($arrParamsForCheckInReferTable);
            
            /**
             *
             * If mainContactid not found in the contact reference table, then search in the account_contacts table.
             *
             */
            if($referedContactId <= 0) {
                
                /**
                 *
                 * function call to account_contacts table for the search of mainContactId
                 *
                 */
                $arrParamsForContactReferInDatabase = array(
                                                            'accountId'         =>      $accountId,
                                                            'contactEmail'      =>      $contactEmail
                                                        );
                $newDatabaseContactId = $this->checkContactReferIsPresentInAccountMasterTable($arrParamsForContactReferInDatabase);
                
                /**
                 *
                 * If found contact in account_contact, then add that in reference table and return newDatabaseContactId.
                 *
                 */
                if(is_numeric($newDatabaseContactId) && $newDatabaseContactId > 0) {

                    $arrParamsToInsertIntoReferenceTable = array(
                                                                    'mainContactId'         =>  $newDatabaseContactId,
                                                                    'referenceContactId'    =>  $contactId
                                                                );
                    $this->insertIntoContactReferenceTable($arrParamsToInsertIntoReferenceTable);

                    $arrReturn['flagToContinue'] = true;
                    $arrReturn['contactIdNeedToUse'] = $newDatabaseContactId;
                }
                /**
                 *
                 * If mainContactId not found in the account_contact table. then dont do any thing the record has come is the mainContactId, so just return that contactId.
                 *
                 */
                else {
                    $arrReturn['flagToContinue'] = true;
                    $arrReturn['contactIdNeedToUse'] = $contactId;
                }
            }
            /**
             *
             * If mainContactId found in the reference table then, set that value in arrContactIdRefer and return that value.
             *
             */
            else {
                $this->arrContactIdRefer[$contactId] = $referedContactId;

                $arrReturn['flagToContinue'] = true;
                $arrReturn['contactIdNeedToUse'] = $referedContactId;
            }
        }

        return $arrReturn;
    }


    function checkContactReferDatabaseOnlyFromContactMappingTable ($arrReturn = array()) {
        if(DEBUG) {
            print "checkContactReferDatabaseOnlyFromContactMappingTable ()";
        }

        $intReturn = 0;

        $contactId = 0;
        if(isset($arrReturn['contactId']) && is_numeric($arrReturn['contactId']) && $arrReturn['contactId'] > 0) {
            $contactId = $arrReturn['contactId'];
        }

        if($contactId == 0) {
            return $intReturn;
        }


        $qrySel = " SELECT cm.main_contact_id mainContactId
                    FROM contact_mapping cm
                    WHERE cm.referenced_contact_id = '" . addslashes($contactId) . "'";
        
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

                $intReturn = $rowGetInfo['mainContactId'];
            }
        }


        return $intReturn;
    }


    function checkContactReferIsPresentInAccountMasterTable ($arrParams = array()) {
        if(DEBUG) {
            print "checkContactReferIsPresentInAccountMasterTable ()";
        }
        
        $arrReturn = array();

        $accountId = 0;
        if(isset($arrParams['accountId']) && is_numeric($arrParams['accountId']) && $arrParams['accountId'] > 0) {
            $accountId = $arrParams['accountId'];
        }

        $contactEmail = 0;
        if(isset($arrParams['contactEmail']) && !empty($arrParams['contactEmail'])) {
            $contactEmail = $arrParams['contactEmail'];
        }


        $qrySel = " SELECT ac.id accountContactId, account_company_id accountCompanyId
                    FROM account_contacts ac
                    WHERE ac.account_id = '" . addslashes($accountId) . "'
                    AND ac.email = '" . addslashes($contactEmail) . "'";
        
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
                    array_push($arrReturn, $rowGetInfo);
                }
            }
        }

        return $arrReturn;
    }





    function processDuplicateContact ($arrParams = array()) {
        if(DEBUG) {
            print "processDuplicateContact ()";
        }

        $arrReturn = array();
        $arrReturn['flagToContinue'] = false;
        $arrReturn['contactIdNeedToUse'] = 0;


        $accountId = 0;
        if(!isset($arrParams['accountId']) || (isset($arrParams['accountId']) && (!is_numeric($arrParams['accountId']) || $arrParams['accountId'] <= 0) ) ) {
            return $arrReturn;
        }
        else {
            $accountId = $arrParams['accountId'];
        }

        $contactId = 0;
        if(!isset($arrParams['contactId']) || (isset($arrParams['contactId']) && (!is_numeric($arrParams['contactId']) || $arrParams['contactId'] <= 0) ) ) {
            return $arrReturn;
        }
        else {
            $contactId = $arrParams['contactId'];
        }

        $contactEmail = 0;
        // if(!isset($arrParams['contactEmail']) || (isset($arrParams['contactEmail']) && (!empty($arrParams['contactEmail']) && !filter_var($arrParams['contactEmail'], FILTER_VALIDATE_EMAIL) ) ) ) {
        if(!isset($arrParams['contactEmail']) || (isset($arrParams['contactEmail']) && empty($arrParams['contactEmail']))) {
            return $arrReturn;
        }
        else {
            $contactEmail = $arrParams['contactEmail'];
        }


        $strDuplicateContactProcessKey = $arrParams['accountId'] . "##" . $arrParams['contactId'] . "##" . $arrParams['contactEmail'];

        if(!isset($this->arrDuplicateContactResultResponse)) {
            $this->arrDuplicateContactResultResponse = array();
        }

        if(COUNT($this->arrDuplicateContactResultResponse) > 2000) {
            // array_slice($this->arrDuplicateContactResultResponse, 5, COUNT($this->arrDuplicateContactResultResponse), true);
            $this->arrDuplicateContactResultResponse = array();
        }

        if(isset($this->arrDuplicateContactResultResponse[$strDuplicateContactProcessKey])) {
            return $this->arrDuplicateContactResultResponse[$strDuplicateContactProcessKey];
        }


        $qrySel = " SELECT ac.id accountContactId
                    FROM account_contacts ac
                    WHERE ac.account_id = '" . addslashes($accountId) . "'
                    AND ac.email = '" . addslashes($contactEmail) . "'";
        
        if(DEBUG) {
            print ($qrySel);
        }
        
        $objDBResult = $this->objDBConNew->executeQuery($qrySel);
        
        if(!$objDBResult) {
            print "Error occur.";
            return false;
        }
        else {
            if($objDBResult->getNumRows() > 0) {
                $rowGetInfo = $objDBResult->fetchAssoc();

                $accountContactId = $rowGetInfo['accountContactId'];

                $arrParamsToInsertIntoReferenceTable = array(
                                                                'mainContactId'         =>  $contactId,
                                                                'referenceContactId'    =>  $accountContactId
                                                            );
                $this->insertIntoContactReferenceTable($arrParamsToInsertIntoReferenceTable);

                $arrReturn = array();
                $arrReturn['flagToContinue'] = false;
                $arrReturn['contactIdNeedToUse'] = $rowGetInfo['accountContactId'];
            }
            else {
                $arrReturn = array();
                $arrReturn['flagToContinue'] = true;
                $arrReturn['contactIdNeedToUse'] = $contactId;
            }
        }

        $this->arrDuplicateContactResultResponse[$strDuplicateContactProcessKey] = $arrReturn;

        return $arrReturn;
    }


    function insertIntoContactReferenceTable ($arrParams = array()) {
        if(DEBUG) {
            print "insertIntoContactReferenceTable ()";
        }

        $qryIns = " INSERT INTO contact_mapping (main_contact_id, referenced_contact_id)
                    VALUES ('" . $arrParams['mainContactId'] . "',
                            '" . $arrParams['referenceContactId'] . "')";
                
        if(DEBUG) {
            print ($qryIns);
        }
        
        $contactMapping = $this->objDBConNew->insertAndGetId($qryIns);
        
        if(!$contactMapping) {
            print "Cant insert into 'contact_mapping', error occured.";
            return false;
        }
        else {

        }
    }


    function getContactRefernecedFromTheSystemForFutherModules($arrParams = array()) {
        if(DEBUG) {
            print "getContactRefernecedFromTheSystemForFutherModules ()";
        }

        $intReturn = NULL;

        $contactId = 0;
        if(isset($arrParams['contactId']) && is_numeric($arrParams['contactId']) && $arrParams['contactId'] > 0) {
            $contactId = $arrParams['contactId'];
        }

        if($contactId <= 0) {
            return $intReturn;
        }

        if(!isset($this->arrReferencedContactIds)) {
            $this->arrReferencedContactIds = array();
        }

        if(COUNT($this->arrReferencedContactIds) > 5000) {
            // $this->arrReferencedContactIds = array();
            array_slice($this->arrReferencedContactIds, 1000, NULL, true);
        } 

        if(isset($this->arrReferencedContactIds[$contactId])) {
            $intReturn = $this->arrReferencedContactIds[$contactId];
        }
        else {
            $qrySel = " SELECT cm.referenced_contact_id referencedContactId
                        FROM contact_mapping cm
                        WHERE cm.main_contact_id = '" . addslashes($contactId) . "'";
            
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
                    $referencedContactId = $rowGetInfo['referencedContactId'];

                    $intReturn = $referencedContactId;

                    $this->arrReferencedContactIds[$contactId] = $intReturn;

                }
                else {
                    
                    $qrySel = " SELECT COUNT(*) totalCnt
                                FROM  account_contacts ac
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

                            if($rowGetInfo['totalCnt'] > 0) {
                                $intReturn = $contactId;
                                $this->arrReferencedContactIds[$contactId] = $intReturn;
                            }
                        }
                    }

                    //                     if(!isset($this->arrContactsHardDeletedFromTheOldSystem)) {
                    //                         $this->arrContactsHardDeletedFromTheOldSystem = array();
                    //                     }
                                        
                    //                     if(count($this->arrContactsHardDeletedFromTheOldSystem) > 2000) {
                    //                         $this->arrContactsHardDeletedFromTheOldSystem = array();
                    //                     }

                    //                     if(isset($this->arrContactsHardDeletedFromTheOldSystem[$contactId])) {
                    //                         $intReturn = 0;
                    //                     }

                    //                     $arrContactInfo = $this->checkForContactDeletedInOldSystem($contactId);
                    //                     ----------  If arrContactInfo array is not empty means that the contact is present in the  ----------

                    //                     if(empty($arrContactInfo)) {
                    //                         $intReturn = 0;
                    //                         $this->arrContactsHardDeletedFromTheOldSystem[$contactId] = 1;
                    //                     }
                    //                     else {
                    //                         $intReturn = $contactId;
                    //                     }

                    //                     // $arrContactInfoNotMigrated = $this->checkContactInNotMigrated($contactId);
                    //                     // if(empty($arrContactInfoNotMigrated)) {
                    //                     //     $intReturn = $contactId;
                    //                     //     $this->arrReferencedContactIds[$contactId] = $intReturn;
                    //                     // }
                    //                     // else {
                    //                     //     $intReturn = 0;
                    //                     // }
                }
            }
        }

        return $intReturn;
    }


    function checkForContactDeletedInOldSystem($contactId = NULL) {
        $arrReturn = array();

        if(empty($contactId)) {
            return $arrReturn;
        }

        $qrySel = " SELECT c.id contactId, c.name contactName, c.email contactEmail
                    FROM contact c
                    WHERE c.id = '" . addslashes($contactId) . "'";
        
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
                $rowGetInfo = $objDBResult->fetchAssoc();
                $arrReturn = $rowGetInfo;
            }
        }

        return $arrReturn;
    }


    function checkContactInNotMigrated($contactId = NULL) {
        $arrReturn = array();

        if(empty($contactId)) {
            return $arrReturn;
        }

        $qrySel = " SELECT unm.id userNotMigratedId, unm.contact_id contactId, unm.first_name firstName, unm.last_name lastName, unm.email email
                    FROM REM_contacts_not_migrated unm
                    WHERE unm.contact_id = '" . $contactId . "'";
        
        if(DEBUG) {
            print nl2br($qrySel);
        }
        
        $objDBResult = $this->objDBConNew->executeQuery($qrySel);
        
        if(!$objDBResult) {
            print "Error occur.";
            return false;
        }
        else {
        }
    }


    function getOldContactEmail($contactId = null) {
        
        $strReturn = '';
        if(empty($contactId)){
            return $strReturn;
        }

        if(!isset($this->arrOldContactEmail) || count($this->arrOldContactEmail) > 5000) {
            $this->arrOldContactEmail = array();
        }

        if(isset($this->arrOldContactEmail[$contactId])) {
            $strReturn = $this->arrOldContactEmail[$contactId];
        }

        if(empty($strReturn)) {
            $qrySel = " SELECT c.email
                        FROM contact c
                        WHERE c.id = '" . addslashes($contactId) . "'";
            
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
                    $rowGetInfo = $objDBResult->fetchAssoc();
                    $email = $rowGetInfo['email'];
                    $this->arrOldContactEmail[$contactId] = $email;
                    $strReturn = $email;
                }
            }
        }
        
        return $strReturn;
    }


    function getEmailSendingMethod($arrParams = array()) {
        $userId = 0;
        $sendVia = 0;
        $intReturn = 0;

        if(isset($arrParams['userId']) && !empty($arrParams['userId']) && is_numeric($arrParams['userId']) && $arrParams['userId'] > 0) {
            $userId = $arrParams['userId'];
        }

        if(isset($arrParams['sendVia']) && !empty($arrParams['sendVia']) && is_numeric($arrParams['sendVia']) && $arrParams['sendVia'] > 0) {
            $sendVia = $arrParams['sendVia'];
        }

        if($userId == 0 || $sendVia == 0) {
            return $intReturn;
        }

        $arrEmailSendingMethods = COMMONVARS::arrEmailSendingMethods();

        if(isset($arrEmailSendingMethods[$sendVia])) {
            $intReturn = $arrEmailSendingMethods[$sendVia];
        }
        
        return $intReturn;
    }


    function insertIntoNotMigratedContacts($arrParams) {
        

        $id = 0;
        if(isset($arrParams['id']) && is_numeric($arrParams['id']) && $arrParams['id'] > 0) {
            $id = $arrParams['id'];
        }

        $first_name = '';
        if(isset($arrParams['first_name']) && !empty($arrParams['first_name'])) {
            $first_name     = $arrParams['first_name'];
        }


        $last_name = '';
        if(isset($arrParams['last_name']) && !empty($arrParams['last_name'])) {
            $last_name     = $arrParams['last_name'];
        }

        $email = '';
        if(isset($arrParams['email']) && !empty($arrParams['email'])) {
            $email     = $arrParams['email'];
        }

        $qryIns = "    INSERT INTO REM_contacts_not_migrated (contact_id, first_name, last_name, email)
                    VALUES ('" . addslashes($id) . "',
                            '" . addslashes($first_name) . "',
                            '" . addslashes($last_name) . "',
                            '" . addslashes($email) . "'
                            )";
                
        if(DEBUG) {
            print nl2br($qryIns);
        }
        
        $insertedId = $this->objDBConNew->insertAndGetId($qryIns);
        
        if(!$insertedId) {
            print "Cant insert into 'users_not_migrated', error occured.";
            return false;
        }
        return true;
    }




    function insertIntoNotMigratedUsers($arrParams) {
        

        $id = 0;
        if(isset($arrParams['id']) && is_numeric($arrParams['id']) && $arrParams['id'] > 0) {
            $id = $arrParams['id'];
        }

        $first_name = '';
        if(isset($arrParams['first_name']) && !empty($arrParams['first_name'])) {
            $first_name     = $arrParams['first_name'];
        }


        $last_name = '';
        if(isset($arrParams['last_name']) && !empty($arrParams['last_name'])) {
            $last_name     = $arrParams['last_name'];
        }

        $email = '';
        if(isset($arrParams['email']) && !empty($arrParams['email'])) {
            $email     = $arrParams['email'];
        }

        $qryIns = "    INSERT INTO REM_users_not_migrated (contact_id, first_name, last_name, email)
                    VALUES ('" . addslashes($id) . "',
                            '" . addslashes($first_name) . "',
                            '" . addslashes($last_name) . "',
                            '" . addslashes($email) . "'
                            )";
                
        if(DEBUG) {
            print nl2br($qryIns);
        }
        
        $insertedId = $this->objDBConNew->insertAndGetId($qryIns);
        
        if(!$insertedId) {
            print "Cant insert into 'users_not_migrated', error occured.";
            return false;
        }
        return true;
    }


    function checkAccountTempalteIdExisted($arrParams = array()) {
        $intReturn = NULL;
        

        $accountTemplateId = NULL;
        if(isset($arrParams['accountTemplateId']) && !empty($arrParams['accountTemplateId'])) {
            $accountTemplateId = $arrParams['accountTemplateId'];
        }

        $accountId = NULL;
        if(isset($arrParams['accountId']) && !empty($arrParams['accountId'])) {
            $accountId = $arrParams['accountId'];
        }

        if(empty($accountTemplateId) || !is_numeric($accountTemplateId)) {
             return $intReturn;
        }

        if(empty($accountId) || !is_numeric($accountId)) {
             return $intReturn;
        }

        if(!isset($this->arrAccountTemplateNotMigrated) || COUNT($this->arrAccountTemplateNotMigrated) > 2000) {
            $this->arrAccountTemplateNotMigrated = array();
        }


        if(isset($this->arrAccountTemplateNotMigrated[$accountTemplateId])) {
            return $this->arrAccountTemplateNotMigrated[$accountTemplateId];
        }
        

        $qrySel = "     SELECT COUNT(*) totalCnt
                        FROM account_templates at
                        WHERE at.id = '" . addslashes($accountTemplateId) . "'
                        AND at.account_id = '" . addslashes($accountId) . "'";
        
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
                if($rowGetInfo['totalCnt'] > 0) {
                    $intReturn = $accountTemplateId;
                }
            }
        }
        return $intReturn;
    }


    function insertIntoContactGroup($arrParams = array()) {
        if(empty($arrParams)) {
            return false;
        }

        if(!isset($arrParams['arrContactIds']) || (isset($arrParams['arrContactIds']) && empty($arrParams['arrContactIds']))) {
            return false;
        }

        $arrContactIds = $arrParams['arrContactIds'];
        // $emailMasterId = $arrParams['emailMasterId'];
        
        $currDate = GENERALFUNCTIONS::convertDateTimeIntoTimeStamp();

        
        /*===============================================================================
        =            Inserting the master entry of the contacty group master            =
        ===============================================================================*/
        
        $qryIns = " INSERT INTO " . COMMONVARS::contactGroupMasterTableName() . " (created)
                    VALUES ('" . $currDate . "')";
                
        if(DEBUG) {
            print nl2br($qryIns);
        }
        
        $contactGroupMasterId = $this->objDBConNew->insertAndGetId($qryIns);
        
        if(!$contactGroupMasterId) {
            print "Cant insert into '" . COMMONVARS::contactGroupMasterTableName() . "', error occured.";
            return false;
        }
        else {

            /**
             *
             * Inserting all the contact group entry for each contact.
             *
             */
            
            foreach ($arrContactIds as $key => $eachContactId) {

                $qryIns = " INSERT INTO " . COMMONVARS::contactGroupTableName() . " (document_email_contact_group_master_id, contact_id)
                            VALUES ('" . $contactGroupMasterId . "',
                                    '" . $eachContactId . "')";
                        
                if(DEBUG) {
                    print nl2br($qryIns);
                }
                
                $contactGroupId = $this->objDBConNew->insertAndGetId($qryIns);
                
                if(!$contactGroupId) {
                    print "Cant insert into '" . COMMONVARS::contactGroupTableName() . "', error occured.";
                    return false;
                }
            }
        }
        return $contactGroupMasterId;
    }


    function verifyAccountTemplateIdPresentOrNot ($arrParams = array()) {
        if(DEBUG) {
            print "verifyAccountTemplateIdPresentOrNot ()";
        }

        $strReturn = 'NULL';

        $accountTemplateId = 0;
        if(isset($arrParams['accountTemplateId']) && is_numeric($arrParams['accountTemplateId']) && $arrParams['accountTemplateId'] > 0) {
            $accountTemplateId = $arrParams['accountTemplateId'];
        }

        if($accountTemplateId <= 0) {
            return $strReturn;
        }
        
        if(!isset($this->arrAccountTemplateIdNotPresentInTheSystem)) {
            $this->arrAccountTemplateIdNotPresentInTheSystem = array();
        }

        if(COUNT($this->arrAccountTemplateIdNotPresentInTheSystem) > 2000) {
            array_slice($this->arrAccountTemplateIdNotPresentInTheSystem, 5, COUNT($this->arrAccountTemplateIdNotPresentInTheSystem), true);
        }

        if(isset($this->arrAccountTemplateIdNotPresentInTheSystem[$accountTemplateId])) {
            return $this->arrAccountTemplateIdNotPresentInTheSystem[$accountTemplateId];
        }


        $qrySel = "     SELECT COUNT(*) totalCnt
                        FROM account_templates at
                        WHERE at.id = '" . addslashes($accountTemplateId) . "'";
        
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
                if($rowGetInfo['totalCnt'] > 0) {
                    $strReturn = $accountTemplateId;
                    $this->arrAccountTemplateIdNotPresentInTheSystem[$accountTemplateId] = 1;
                }
            }
        }

        return $strReturn;
    }



    function getAccountContactIdFromEmailAndAccountId ($arrParams = array()) {
        if(DEBUG) {
            print "getAccountContactIdFromEmailAndAccountId ()";
        }

        $intReturn = 0;
        $accountId = 0;
        $strEmail = "";

        if(isset($arrParams['email']) && !empty($arrParams['email'])) {
            $strEmail = $arrParams['email'];
        }

        if(isset($arrParams['accountId']) && is_numeric($arrParams['accountId']) && $arrParams['accountId'] > 0) {
            $accountId = $arrParams['accountId'];
        }

        if($accountId == 0 || empty($strEmail)) {
            return $intReturn;
        }

        if(!isset($this->arrContactCreatedForCampaignMigration)) {
            $this->arrContactCreatedForCampaignMigration = array();
        }

        if(COUNT($this->arrContactCreatedForCampaignMigration) > 2000) {
            array_slice($this->arrContactCreatedForCampaignMigration, 10, COUNT($this->arrContactCreatedForCampaignMigration), true);
        }

        $strAccountEmailKey = $accountId . "##" . $strEmail;

        if(isset($this->arrContactCreatedForCampaignMigration[$strAccountEmailKey])) {
            return $this->arrContactCreatedForCampaignMigration[$strAccountEmailKey];
        }



        $qrySel = "     SELECT ac.id accountContactId
                        FROM account_contacts ac
                        WHERE ac.account_id = '" . addslashes($accountId) . "'
                        AND ac.email = '" . addslashes($strEmail) . "'";
        
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
            else {
                
                $currentConvertedTime = self::convertDateTimeIntoTimeStamp();

                $qryIns = "    INSERT INTO account_contacts (account_id, account_company_id, source_id, email, phone, city, country, notes, first_name, last_name, total_mail_sent, total_mail_failed, total_mail_replied, total_mail_bounced, total_link_clicks, total_document_viewed, total_document_facetime, status, created, modified)
                            VALUES ('" . addslashes($accountId) . "',
                                    NULL,
                                    '1',
                                    '" . addslashes($strEmail) . "',
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
                                    1,
                                    '" . $currentConvertedTime . "',
                                    0
                                   )";
                if(DEBUG) {
                    print nl2br($qryIns);
                }
                
                $accountContactIdInserted = $this->objDBConNew->insertAndGetId($qryIns);
                
                if(!$accountContactIdInserted) {
                    print "Cant insert into 'account_contacts', error occured.";
                    return false;
                }
                else {
                    $intReturn = $accountContactIdInserted;
                    $this->arrContactCreatedForCampaignMigration[$strAccountEmailKey] = $accountContactIdInserted;
                }
            }
        }

        return $intReturn;
    }
}


?>