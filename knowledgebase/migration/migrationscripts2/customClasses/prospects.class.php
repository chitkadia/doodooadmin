<?php


CLASS PROSPECTS EXTENDS GENERALFUNCTIONS {

	protected $objLogger;

	function PROSPECTS ($objLogger) {
		if(DEBUG) {
			print "PROSPECTS ()";
		}

		$this->objDBConNew = new COMMONDBFUNC();
		$this->objDBConOld = new COMMONDBFUNC(HOST_OLD, PASSWORD_OLD, USERNAME_OLD, DBNAME_OLD);

		$this->objLogger = $objLogger;

		$this->lastInsertedCompanyId = 0;


		$this->flagContactMigratedFinished = false;
	}


	function migrateCompaniesIncrementalScript () {
		if(DEBUG) {
			print "migrateCompaniesIncrementalScript ()";
		}


		$this->resetCountersForCompanies();

		$arrLastCompanyInformationInserted = $this->getLastCompanyInsertedInfo();

		$accountCompanyId = $arrLastCompanyInformationInserted['accountCompanyId'];
		$accountMasterId = $arrLastCompanyInformationInserted['accountMasterId'];

		if(is_numeric($accountCompanyId) && $accountCompanyId > 0) {
			$this->lastInsertedCompanyId = $accountCompanyId;
		}

		$this->migrateCompanies();
	}


	function getLastCompanyInsertedInfo () {
		if(DEBUG) {
			print "getLastCompanyInsertedInfo ()";
		}

		$arrReturn = array();
		$arrReturn['accountCompanyId'] = 0;
		$arrReturn['accountMasterId'] = 0;


		$qrySel = "	SELECT ac.id accountCompanyId, ac.account_id accountMasterId
					FROM account_companies ac
					ORDER BY ac.id DESC LIMIT 1";
		
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
				
				$arrReturn['accountCompanyId'] = $rowGetInfo['accountCompanyId'];
				$arrReturn['accountMasterId'] = $rowGetInfo['accountMasterId'];
			}
		}

		return $arrReturn;
	}


	function migrateCompanies () {
		if(DEBUG) {
			print "migrateCompanies ()";
		}
 		
 		$counter = 0;


 		$arrDemoCompanyInfomation = COMMONVARS::arrDemoCompanyInfomation();

 		$arrDemoCompanyName = $arrDemoCompanyInfomation['name'];
 		$arrDemoCompanyEmail = $arrDemoCompanyInfomation['email'];

 		while(true) {

 			$arrOldCompanies = $this->getListOfAllOldCompanies();

	 		if(!empty($arrOldCompanies)) {

	 			$strInsertValues = "";

	 			foreach ($arrOldCompanies as $key => $arrEachCompanyData) {

	 				$this->total++;
	$counter++;
	print "counter :: " . $counter . "\n";


	 				$id 			= $arrEachCompanyData['id'];
	 				$name 			= $arrEachCompanyData['name'];
	 				$phone 			= $arrEachCompanyData['phone'];
	 				$fax 			= $arrEachCompanyData['fax'];
	 				$website 		= $arrEachCompanyData['website'];
	 				$email 			= $arrEachCompanyData['email'];
	 				$street 		= $arrEachCompanyData['street'];
	 				$city 			= $arrEachCompanyData['city'];
	 				$postal_code 	= $arrEachCompanyData['postal_code'];
	 				$state 			= $arrEachCompanyData['state'];
	 				$country 		= $arrEachCompanyData['country'];
	 				$notes 			= $arrEachCompanyData['notes'];
	 				$user_id 		= $arrEachCompanyData['user_id'];
	 				$created_on 	= $arrEachCompanyData['created_on'];


	 				// if(in_array($name, $arrDemoCompanyName) && in_array($email, $arrDemoCompanyEmail)) {
	 				// 	$this->countDemoCompany++;
	 				// 	$this->objLogger->setMessage('Demo company name found, Ignored the company. id(' . $id . ') - name (' . $name . ')');
	 				// }
	 				// else {
	 					$convertedCreatedDate = self::convertDateTimeIntoTimeStamp($created_on);

		 				if($created_on == "0000-00-00 00:00:00") {
		 					$convertedCreatedDate = self::convertDateTimeIntoTimeStamp();
		 				}


		 				if(strlen($street) > 255) {
		 					$street = substr($street, 0, 254);
		 				}

		 				if(strlen($state) > 50) {
		 					$state = substr($state, 0, 49);
		 				}

		 				if(strlen($city) > 50) {
		 					$city = substr($city, 0, 49);
		 				}

		 				if(strlen($country) > 50) {
		 					$country = substr($country, 0, 49);
		 				}


		 				$accountId = $this->getAccountNumberForUser($user_id);


		 				if(is_numeric($accountId) && $accountId > 0) {
		 					$strInsertValues .= " ( '" . addslashes($id) . "',
		 											'" . addslashes($accountId) . "',
		 											'" . addslashes($user_id) . "',
													'1',
													'" . addslashes($name) . "',
													'" . addslashes($street) . "',
													'" . addslashes($city) . "',
													'" . addslashes($state) . "',
													'" . addslashes($country) . "',
													'" . addslashes($postal_code) . "',
													'',
													'" . addslashes($website) . "',
													'" . addslashes($phone) . "',
													'" . addslashes($fax) . "',
													'" . addslashes($notes) . "',
													0,
													0,
													0,
													0,
													0,
													0,
													0,
													1,
													'" . addslashes($convertedCreatedDate) . "',
													0), ";

							$this->successfullComplete++;
		 				}
		 				else {
		 					$this->failed++;
		 					// print "AccountId not found for the company id(" . $id . ") - name (" . $name . ")\n\n";
		 					$this->objLogger->setMessage('AccountId not found for the company id(' . $id . ') - name (' . $name . ')');
		 				}
	 				// }
	 			}

	 			unset($arrOldCompanies);

	 			if(!empty($strInsertValues)) {

	 				$strInsertValues = rtrim($strInsertValues, ', ');

	 				$qryIns = "	INSERT INTO account_companies (id, account_id, user_id, source_id, name, address, city, state, country, zipcode, logo, website, contact_phone, contact_fax, short_info, total_mail_sent, total_mail_failed,total_mail_replied, total_mail_bounced, total_link_clicks, total_document_viewed, total_document_facetime, status, created, modified)
								VALUES " . $strInsertValues;
							
					if(DEBUG) {
						print nl2br($qryIns);
					}
					
					$accountCompanyId = $this->objDBConNew->insertAndGetId($qryIns);
					
					if(!$accountCompanyId) {
						print "Cant insert into 'account_companies', error occured.";
						return false;
					}
					else {
					
					}
				}

				$this->objLogger->addLog();
	 		}
	 		else {

	 			$this->objLogger->setMessage("total :: " . $this->total);
		 		$this->objLogger->setMessage("total demo company found :: " . $this->countDemoCompany);
		 		$this->objLogger->setMessage("successfullComplete :: " . $this->successfullComplete);
		 		$this->objLogger->setMessage("failed :: " . $this->failed);

		 		$this->objLogger->addLog();

				print "\n\n";
				print "this->total :: " . $this->total . "\n";
				print "this->countDemoCompany :: " . $this->countDemoCompany . "\n";
				print "this->successfullComplete :: " . $this->successfullComplete . "\n";
				print "this->failed :: " . $this->failed . "\n";

	 			$this->objLogger->setMessage('No more records found for the companes..');
	 			$this->objLogger->setMessage('Terminating the script');
	 			$this->objLogger->addLog();
	 			break;
	 		}
 		}

	}

 	function resetCountersForCompanies () {
 		if(DEBUG) {
 			print "resetCountersForCompanies ()";
 		}


 		$this->perPageRecord = 50;
		$this->currentPage = 0;
 		

 		$this->successfullComplete = 0;
 		$this->failed = 0;
 		$this->total = 0;

 		$this->countDemoCompany = 0;
 		$this->countDemoContact = 0;
 	}


 	function resetCountersForContacts () {
 		if(DEBUG) {
 			print "resetCountersForContacts ()";
 		}


 		$this->perPageRecord = 500;
		$this->currentPage = 0;
 		

 		$this->successfullComplete = 0;
 		$this->failed = 0;
 		$this->total = 0;

 		$this->countDemoCompany = 0;
 		$this->countDemoContact = 0;
 	}

 	function resetCounterForDeletedContactMigration () {
 		if(DEBUG) {
 			print "resetCounterForDeletedContactMigration ()";
 		}

 		$this->perPageRecord = 500;
		$this->currentPage = 0;
 	}


 	function migrateContactsInsideCompaniesIncrementalScript () {
 		if(DEBUG) {
 			print "migrateContactsInsideCompaniesIncrementalScript ()";
 		}
		
		$this->resetCountersForContacts();
 		
 		$arrLastContactInsertedInformation = $this->getLastContactInformation();

		$accountContactId = $arrLastContactInsertedInformation['accountContactId'];

		if(is_numeric($accountContactId) && $accountContactId > 0) {
			$flagCheckForLastInsertIdFromContactOrDeletedContact = $this->getCheckForLastInsertIdFromContactOrDeletedContact($accountContactId);

			$this->intLastInsertedAccountContactId = $accountContactId;
 			/**
 			 * Contact table migration process is pending.. so continue with that only.
 			 */
			if(!$flagCheckForLastInsertIdFromContactOrDeletedContact) {
				$this->flagContactMigratedFinished = false;
			}
			/**
			 * Contacts table migration is completed Continue with the deleted contact migration.
			 */
			else {
				$this->resetCountersForContacts();
				$this->resetCounterForDeletedContactMigration();

				$this->flagContactMigratedFinished = true;
			}
		}
		
 		$this->migrateContactsInsideCompanies();
 	}


 	function getCheckForLastInsertIdFromContactOrDeletedContact ($accountContactId = NULL) {
 		if(DEBUG) {
 			print "getCheckForLastInsertIdFromContactOrDeletedContact ()";
 		}

 		$flagReturn = false;

 		$lastContactIdInContactTable = 0;

 		$qrySel = "	SELECT c.id contactId
 					FROM contact c
 					ORDER BY c.id DESC LIMIT 1 ";
 		
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

 				$lastContactIdInContactTable = $rowGetInfo['contactId'];
 			}

 			if($lastContactIdInContactTable > 0) {
 				
 				$qrySel = "	SELECT COUNT(*) totalCnt
 							FROM account_contacts ac
 							WHERE ac.id = '" . addslashes($lastContactIdInContactTable) . "'";
 				
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
 							$flagReturn = true;
 						}
 					}
 				}
 			}
 		}

 		return $flagReturn;
 	}


 	function getLastContactInformation () {
 		if(DEBUG) {
 			print "getLastContactInformation ()";
 		}

 		$arrReturn = array();
 		$arrReturn['accountContactId'] = 0;

 		$qrySel = "	SELECT ac.id accountContactId
 					FROM account_contacts ac
 					ORDER BY ac.id DESC LIMIT 1";
 		
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
 			}
 		}
 		return $arrReturn;
 	}

	function migrateContactsInsideCompanies () {
		if(DEBUG) {
			print "migrateContactsInsideCompanies ()";
		}


		// $this->createMigrateReferenceTables();
 		
		// $arrAllCompanyIds = $this->getAllCompanyIds();

 		// $arrDemoContactInformation = COMMONVARS::arrDemoContactInformation();
 		// $arrDemoContactName = $arrDemoContactInformation['name'];
 		// $arrDemoContactEmail = $arrDemoContactInformation['email'];

 		while(true) {

 			$arrListOfAllContacts = $this->getListOfAllContacts();
 
 			if(!empty($arrListOfAllContacts)) {

 				$strInsertValues = "";

 				// foreach ($arrListOfAllContacts as $key => $arrOldContactInfo) {
 				for($intI = 0; $intI < COUNT($arrListOfAllContacts); $intI++) {

$this->total++;
print "this->total :: " . $this->total . "\n";
 					$id 				= $arrListOfAllContacts[$intI]['id'];
 					$name 				= $arrListOfAllContacts[$intI]['name'];
 					$email 				= $arrListOfAllContacts[$intI]['email'];
 					$phone 				= $arrListOfAllContacts[$intI]['phone'];
 					$extension 			= $arrListOfAllContacts[$intI]['extension'];
 					$fax 				= $arrListOfAllContacts[$intI]['fax'];
 					$title 				= $arrListOfAllContacts[$intI]['title'];
 					$salutation 		= $arrListOfAllContacts[$intI]['salutation'];
 					$gender 			= $arrListOfAllContacts[$intI]['gender'];
 					$street 			= $arrListOfAllContacts[$intI]['street'];
 					$city 				= $arrListOfAllContacts[$intI]['city'];
 					$postal_code 		= $arrListOfAllContacts[$intI]['postal_code'];
 					$state 				= $arrListOfAllContacts[$intI]['state'];
 					$country 			= $arrListOfAllContacts[$intI]['country'];
 					$lead_source 		= $arrListOfAllContacts[$intI]['lead_source'];
 					$note 				= $arrListOfAllContacts[$intI]['note'];
 					$user_id 			= $arrListOfAllContacts[$intI]['user_id'];
 					$created_on 		= $arrListOfAllContacts[$intI]['created_on'];
 					$source 			= $arrListOfAllContacts[$intI]['source'];
 					$company_id 		= $arrListOfAllContacts[$intI]['company_id'];
 					$campaign_list_id 	= $arrListOfAllContacts[$intI]['campaign_list_id'];
 					$contactType 		= $arrListOfAllContacts[$intI]['contacttype'];


 					/**
 					 *
 					 * Array parameters for keeping track of which contacts havent been migrated.
 					 *
 					 */

 				// 	$arrParamsNotMigratedContacts = array();
					// $arrParamsNotMigratedContacts['id'] = $id;
 				// 	$arrParamsNotMigratedContacts['first_name'] = $name;
 				// 	$arrParamsNotMigratedContacts['last_name'] = '';
 				// 	$arrParamsNotMigratedContacts['email'] = $email;

	 				// $accountId = $this->getAccountNumberForUser($user_id);

	 				// if(is_numeric($accountId) && $accountId > 0) {

 						$accountId = NULL;

 						$contactStatus = 1;
		 				// if($contactType == "deleted_contact") {
		 				// 	$contactStatus = 2;
		 				// }
 						
 						// $flagDemoContact = false;
	 					// if($contactStatus == 1) {
	 					// 	if(in_array($name, $arrDemoContactName) && in_array($email, $arrDemoContactEmail)) {
	 					// 		$flagDemoContact = true;
	 					// 	}
	 					// }
	 					// else if($contactStatus == 2) {
	 					// 	if(in_array($name, $arrDemoContactName) || in_array($name, $arrDemoContactEmail)) {
	 					// 		$flagDemoContact = true;
	 					// 	}
	 					// }

	 					// if($flagDemoContact) {
							// $this->countDemoContact++;
							// $this->objLogger->setMessage('This is a deom contact name (' . $name . ') and email(' . $email . ') - id (' . $id . ')');
							// continue;
	 					// }

	 					// $arrParamsForCheckDuplicateContact = array(
	 					// 											'accountId' 	=>	$accountId,
	 					// 											'contactId' 	=>	$id,
	 					// 											'contactEmail' 	=>	$email
	 					//  										);

	 					// $arrContactDuplicateResponse = $this->checkDuplicateContactForAccount($arrParamsForCheckDuplicateContact);
	 					// $arrContactDuplicateResponse = $this->processDuplicateContact($arrParamsForCheckDuplicateContact);

	 					// $flagToContinue = $arrContactDuplicateResponse['flagToContinue'];

	 					// if(!$flagToContinue) {
		 				// 	$this->insertIntoNotMigratedContacts($arrParamsNotMigratedContacts);

	 					// 	$this->objLogger->setMessage('---------------------------------------------');
	 					// 	$this->objLogger->setMessage('Duplicate contact found in account. So nade entry into the referecen table and will skipping this contact.');
	 					// 	$this->objLogger->setMessage('This is a deom contact name (' . $name . ') and email(' . $email . ') - id (' . $id . ')');
	 					// 	$this->objLogger->setMessage('---------------------------------------------');
	 					// 	continue;
	 					// }

 						
 						$convertedCreatedDate = self::convertDateTimeIntoTimeStamp($created_on);
	 					if($created_on == "0000-00-00 00:00:00") {
		 					$convertedCreatedDate = self::convertDateTimeIntoTimeStamp();
		 				}

		 				// $companyId = 'NULL';
		 				// // if(in_array($company_id, $arrAllCompanyIds)) {
		 				// if(isset($arrAllCompanyIds[$company_id])) {
		 				// 	$companyId = $company_id;
		 				// }
		 				if($company_id == 0) {
		 					$company_id = 'NULL';
		 				}
		 				$companyId = $company_id;

		 				if(strlen($email) > 150) {
		 					$this->objLogger->setMessage('Email field have garbage value - (' . $email . ') id(' . $id . ') - name (' . $name . ')');
		 					continue;
		 				}

		 				if(strlen($city) > 50) {
		 					$this->objLogger->setMessage('City field size is greater then desired size. value - (' . $email . ') id(' . $id . ') - city (' . $city . ')');
		 					$city = substr($city, 0, 50);
		 				}

		 				if(strlen($country) > 50) {
		 					$this->objLogger->setMessage('Country field size is greater then desired size. value - (' . $email . ') id(' . $id . ') - country (' . $country . ')');
		 					$country = substr($country, 0, 50);
		 				}

		 				if(strlen($phone) > 20) {
		 					$this->objLogger->setMessage('Phone field size is greater then desired size. value - (' . $email . ') id(' . $id . ') - phone (' . $phone . ')');
		 					$phone = substr($phone, 0, 20);
		 				}


		 				if(strlen($note) > 500) {
		 					$this->objLogger->setMessage('Contact has note more than the desired size id(' . $id . ') - name (' . $name . ') - note (' . $note . ')');
		 					$note = substr($note, 0, 510) . "..";
		 				}


		 				// $qryIns = "	INSERT INTO account_contacts (id, account_id, account_company_id, source_id, email, phone, city, country, notes, first_name, last_name, total_mail_sent, total_mail_failed, total_mail_replied, total_mail_bounced, total_link_clicks, total_document_viewed, total_document_facetime, status, created, modified)
		 				// 			VALUES (	'" . addslashes($id) . "',
 						// 						NULL,
 						// 						" . addslashes($companyId) . ",
 						// 						'1',
 						// 						'" . addslashes($email) . "',
 						// 						'" . addslashes($phone) . "',
 						// 						'" . addslashes($city) . "',
 						// 						'" . addslashes($country) . "',
 						// 						'" . addslashes($note) . "',
 						// 						'" . addslashes($name) . "',
 						// 						'',
 						// 						'0',
 						// 						'0',
 						// 						'0',
 						// 						'0',
 						// 						'0',
 						// 						'0',
 						// 						'0',
 						// 						'" . addslashes($contactStatus) . "',
 						// 						'" . addslashes($convertedCreatedDate) . "',
 						// 						'" . addslashes($convertedCreatedDate) . "'
 						//  					)";
		 						
		 				// if(DEBUG) {
		 				// 	print nl2br($qryIns);
		 				// }
		 				
		 				// $insertedId = $this->objDBConNew->insertAndGetId($qryIns);
		 				
		 				// if(!$insertedId) {
		 				// 	print "Cant insert into 'account_contacts', error occured.";
		 				// 	// return false;
		 				// }

 						$strInsertValues .= " (	'" . addslashes($id) . "',
	 												NULL,
	 												" . addslashes($user_id) . ",
	 												" . addslashes($companyId) . ",
	 												'1',
	 												'" . addslashes($email) . "',
	 												'" . addslashes($phone) . "',
	 												'" . addslashes($city) . "',
	 												'" . addslashes($country) . "',
	 												'" . addslashes($note) . "',
	 												'" . addslashes($name) . "',
	 												'',
	 												'0',
	 												'0',
	 												'0',
	 												'0',
	 												'0',
	 												'0',
	 												'0',
	 												'" . addslashes($contactStatus) . "',
	 												'" . addslashes($convertedCreatedDate) . "',
	 												'" . addslashes($convertedCreatedDate) . "'
	 						 					), ";


 						$this->successfullComplete++;
	 				// }
	 				// else {

	 				// 	$this->insertIntoNotMigratedContacts($arrParamsNotMigratedContacts);

	 				// 	$this->failed++;
	 				// 	// account number not found in the system.
	 				// 	$this->objLogger->setMessage('AccountId not found for the contact id(' . $id . ') - name (' . $name . ')');
	 				// }
	 			}
	 			// 	unset($arrOldContactInfo);
 				// }

 				unset($arrListOfAllContacts);

 				if(!empty($strInsertValues)) {

 					$strInsertValues = rtrim($strInsertValues, ', ');

 					$qryIns = "	INSERT INTO account_contacts (id, account_id, user_id, account_company_id, source_id, email, phone, city, country, notes, first_name, last_name, total_mail_sent, total_mail_failed, total_mail_replied, total_mail_bounced, total_link_clicks, total_document_viewed, total_document_facetime, status, created, modified)
 								VALUES " . $strInsertValues;
 							
 					if(DEBUG) {
 						print nl2br($qryIns);
 					}

 					$accountContactId = $this->objDBConNew->insertAndGetId($qryIns);
 					
 					if(!$accountContactId) {
 						print "Cant insert into 'account_contacts', error occured.";
 						// return false;
 					}
 					else {
 						$this->objLogger->setMessage('batch of query ' . $this->perPageRecord . ' records done successfully. testing accountContactId :: ' . $accountContactId);
 					}
 				}
 				else {
 					$this->objLogger->setMessage('the insert string is empty');
 				}
 				$this->objLogger->addLog();
 			}
 			else {

 				// if(!$this->flagContactMigratedFinished) {

 				// 	$this->resetCounterForDeletedContactMigration();

 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('contact tables migration finished.......');
 				// 	// $this->objLogger->setMessage('Starting to migrate Deleted Contacts records.');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->objLogger->setMessage('***********************************************************');
 				// 	$this->flagContactMigratedFinished = true;

 				// 	$this->objLogger->addLog();
 				// }
 				// else {
 				// 	$this->objLogger->setMessage('Records Finished');
 				// 	break;
 				// }
 				$this->objLogger->setMessage('Records Finished');
 				break;
 			}
 			$this->objLogger->addLog();
 		}

 		// $this->dropContactMigrationTable();

 		$this->objLogger->setMessage("total :: " . $this->total);
 		$this->objLogger->setMessage("successfullComplete :: " . $this->successfullComplete);
 		$this->objLogger->setMessage("failed :: " . $this->failed);
 		$this->objLogger->setMessage("countDemoContact :: " . $this->countDemoContact);

 		$this->objLogger->addLog();

print "\n\n";
print "this->total :: " . $this->total . "\n";
print "this->successfullComplete :: " . $this->successfullComplete . "\n";
print "this->failed :: " . $this->failed . "\n";
print "this->countDemoContact :: " . $this->countDemoContact . "\n";

	}


	function createMigrateReferenceTables () {
		if(DEBUG) {
			print "createMigrateReferenceTables ()";
		}
 		
 		/**
 		 * This function was implemented before incremental script.
 		 */

		// $this->dropContactMigrationTable();

		$qrySel = "	CREATE TABLE contact_mapping (
						id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
						main_contact_id BIGINT(20) UNSIGNED NOT NULL,
						referenced_contact_id BIGINT(20) UNSIGNED NOT NULL
					); ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
		}
	}

	function dropContactMigrationTable () {
		if(DEBUG) {
			print "dropContactMigrationTable ()";
		}

		$qrySel = "	DROP TABLE IF EXISTS contact_mapping; ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
		}
	}



	function getAllCompanyIds () {
		if(DEBUG) {
			print "getAllCompanyIds ()";
		}

		$arrReturn = array();

		$qrySel = "	SELECT id
					FROM account_companies ";
		
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
					// array_push($arrReturn, $rowGetInfo);
					$arrReturn[$rowGetInfo['id']] = $rowGetInfo['id'];
				}
			}
		}
		return $arrReturn;
	}


	function getListOfAllOldCompanies () {
		if(DEBUG) {
			print "getListOfAllOldCompanies ()";
		}

		$arrReturn = array();

		$qrySel = "	SELECT *
					FROM company 
					WHERE 1 ";

		if(is_numeric($this->lastInsertedCompanyId) && $this->lastInsertedCompanyId > 0) {
			$qrySel .= " AND id > '" . addslashes($this->lastInsertedCompanyId) . "' ";
		}
		
		$qrySel .= " ORDER BY id ASC ";

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


	function getListOfAllContacts () {
		if(DEBUG) {
			print "getListOfAllContacts ()";
		}

		$arrReturn = array();


		if(!$this->flagContactMigratedFinished) {
 			

 			$this->objLogger->setMessage('GETTING CONTACTS FROM THE CONTACT TABLE');

			$qrySel = "	SELECT c.id, c.`name`, c.email, c.phone, c.extension, c.fax, c.title, c.salutation, c.gender, c.street, c.city, c.postal_code, c.state, c.country, c.lead_source, c.note, c.user_id, c.created_on, c.source, c.company_id, c.campaign_list_id, 'active_contact' contacttype
						FROM contact  c
						WHERE c.user_id > 0 ";

			if(isset($this->intLastInsertedAccountContactId) && is_numeric($this->intLastInsertedAccountContactId) && $this->intLastInsertedAccountContactId > 0) {
				$qrySel .= " AND c.id > '" . addslashes($this->intLastInsertedAccountContactId) . "' ";
			}

			$qrySel .= " ORDER BY c.id ASC ";

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
		}
		else {

 		// 	$this->objLogger->setMessage('GETTING CONTACTS FROM THE DELETED CONTACT TABLE');

			// $qrySel = "	SELECT t1.contact_id id, t1.name name, '' email, '' phone, '' extension, '' fax, '' title, '' salutation, '' gender, '' street, '' city, '' postal_code, '' state, '' country, '' lead_source, '' note, t1.user_id user_id, t1.date created_on, '' source, '' company_id, '' campaign_list_id,  'deleted_contact' contacttype
			// 			FROM deleted_contact t1 LEFT JOIN deleted_contact t2 ON t1.contact_id = t2.contact_id AND t1.id < t2.id
			// 			WHERE t1.user_id  > 0
			// 			AND t2.id IS NULL ";
 			
 		// 	if(isset($this->intLastInsertedAccountContactId) && is_numeric($this->intLastInsertedAccountContactId) && $this->intLastInsertedAccountContactId > 0) {
			// 	$qrySel .= " AND t1.id > (	SELECT dc3.id 
			// 		 						FROM deleted_contact dc3 
			// 		 						WHERE dc3.contact_id = '" . addslashes($this->intLastInsertedAccountContactId) . "'
			// 		 				 	) ";
			// }

			// $qrySel .= " ORDER BY t1.contact_id ASC ";

 		// 	$limit = " LIMIT " . ($this->currentPage * $this->perPageRecord) . ", " . $this->perPageRecord;

			// $qrySel .= $limit; 			

			// if(DEBUG) {
			// 	print nl2br($qrySel);
			// }

			// $objDBResult = $this->objDBConOld->executeQuery($qrySel);
			
			// if(!$objDBResult) {
			// 	print "Error occur.";
			// 	return false;
			// }
			// else {
			// 	if($objDBResult->getNumRows() > 0) {
			// 		while($rowGetInfo = $objDBResult->fetchAssoc()) {
			// 			array_push($arrReturn, $rowGetInfo);
			// 		}
			// 	}
			// }
		}


		$this->currentPage++;
		return $arrReturn;



 		
		// $strContactIdIncrementalConditionForContactTable = "";
		// $strContactIdIncrementalConditionForDelatedContactTable = "";
		// if(isset($this->intLastInsertedAccountContactId) && is_numeric($this->intLastInsertedAccountContactId) && $this->intLastInsertedAccountContactId > 0) {
		// 	$strContactIdIncrementalConditionForContactTable = " AND c.id > '" . $this->intLastInsertedAccountContactId . "' ";
		// 	$strContactIdIncrementalConditionForDelatedContactTable = " AND c.id > '" . $this->intLastInsertedAccountContactId . "' ";
		// }

		// $qrySel = "	SELECT tmptable.id, tmptable.`name`, tmptable.email, tmptable.phone, tmptable.extension, tmptable.fax, tmptable.title, tmptable.salutation, tmptable.gender, tmptable.street, tmptable.city, tmptable.postal_code, tmptable.state, tmptable.country, tmptable.lead_source, tmptable.note, tmptable.user_id, tmptable.created_on, tmptable.source, tmptable.company_id, tmptable.campaign_list_id, tmptable.contacttype
		// 			FROM (
		// 					(	
		// 						SELECT c.id, c.`name`, c.email, c.phone, c.extension, c.fax, c.title, c.salutation, c.gender, c.street, c.city, c.postal_code, c.state, c.country, c.lead_source, c.note, c.user_id, c.created_on, c.source, c.company_id, c.campaign_list_id, 'active_contact' contacttype
		// 						FROM contact  c
		// 						WHERE c.user_id > 0
		// 					)
							
		// 					UNION 

		// 					(	
		// 						SELECT t1.contact_id, t1.`name`, '', '', '', '', '', '', '', '', '', '', '', '', '', '', t1.user_id, t1.date, '', '', '',  'deleted_contact' contacttype
		// 						FROM deleted_contact t1 LEFT JOIN deleted_contact t2 ON t1.contact_id = t2.contact_id AND t1.id < t2.id
		// 						WHERE t1.user_id  > 0
		// 						AND t2.id IS NULL
		// 					)
		// 				) AS tmptable

		// 			WHERE 1

		// 			ORDER BY tmptable.id ASC ";


		// $limit = " LIMIT " . ($this->currentPage * $this->perPageRecord) . ", " . $this->perPageRecord;

		// $qrySel .= $limit;

		// if(DEBUG) {
		// 	print nl2br($qrySel);
		// }

		// $objDBResult = $this->objDBConOld->executeQuery($qrySel);

		// if(!$objDBResult) {
		// 	print "Error occur.";
		// 	return false;
		// }
		// else {
		// 	if($objDBResult->getNumRows() > 0) {
		// 		while($rowGetInfo = $objDBResult->fetchAssoc()) {
		// 			array_push($arrReturn, $rowGetInfo);
		// 		}
		// 	}
		// // }
		// $this->currentPage++;
		// return $arrReturn;
	}
}

?>