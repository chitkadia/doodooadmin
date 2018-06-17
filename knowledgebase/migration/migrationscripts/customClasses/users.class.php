<?php


CLASS USERS EXTENDS GENERALFUNCTIONS {

	protected $objLogger;
	protected $accountMasterId = 0;
	
	// constructor
	function USERS ($objLogger) {
		if(DEBUG) {
			print "USERS ()";
		}

		$this->objDBConNew = new COMMONDBFUNC();
		$this->objDBConOld = new COMMONDBFUNC(HOST_OLD, PASSWORD_OLD, USERNAME_OLD, DBNAME_OLD);

		$this->objLogger = $objLogger;

		$this->perPageRecord = 3;
		$this->currentPage = 0;
		$this->userMasterId = 0;

		$this->objAccount = new ACCOUNTS($this->objLogger);
		$this->objAccountOrganization = new ACCOUNTORGANIZATION($this->objLogger);

		$this->counterVariable = 1;

		$this->arrUserSettingAppVars = $this->getUserSettingsAppVars();

		$this->arrEmailsProcessed = array();
		
		$this->arrUserIdAndAccountMasterIdMap = array();


		$this->intLastAccountMasterInsertedPosition = 0;

		$this->lastAccountMasterIdGenerated = 0;
		$this->flagLastAccountMasterIdGenerated = false;
 		



 		$this->lastIndexOfTeamMemberMigrated = 0;
 		$this->perPageRecordTeamMemberMigratedAccountMaster = 1000;
		$this->currentPageAccountMaster = 0;

		$this->arrAccountsHavingTeamMembers = array();
	}
 	


 	function checkIncrementalAndResumeScriptForTeamMemberUser () {
 		if(DEBUG) {
 			print "checkIncrementalAndResumeScriptForTeamMemberUser ()";
 		}

 		$arrLastInsertedTeamMemberUser = $this->getLastInsertedTeamMemberUser();

 		$indexCounter = $arrLastInsertedTeamMemberUser['indexCounter'];
 		$userMasterId = $arrLastInsertedTeamMemberUser['userMasterId'];
 		$accountId = $arrLastInsertedTeamMemberUser['accountId'];
 		
 		$this->objLogger->setMessage('STARTING SCRIPT FOR TEAM MEMBER MIGRATAION.');
 		$this->objLogger->setMessage('indexCounter ' . $indexCounter);
 		$this->objLogger->setMessage('userMasterId ' . $userMasterId);
 		$this->objLogger->setMessage('accountId ' . $accountId);

 		$this->objLogger->setMessage('Check for previous entries present for team member migration.');

 		if(is_numeric($userMasterId) && $userMasterId > 0) {

 			$this->objLogger->setMessage('SEEMS LIKE PREVIOUS ENTRIES ARE ALREADY MADE FOR THE TEAM USERS.');

 			$this->objLogger->setMessage('Found previous entries for team member migration.');
 			$this->lastIndexOfTeamMemberMigrated = ($indexCounter - 1);

 			$this->objLogger->setMessage('Deleting all the team members of account master except the account owner account. account id (' . $accountId . ')');
 			$this->deleteAllTeamMemberInfoForSpecificAccountExceptOwnerUser($accountId);
 			$this->objLogger->setMessage('Deleting of all the members of perticular account finished. account id (' . $accountId . ')');
 		}

 		$this->setAccountsHavingTeamMembers();
 		$this->startImportingTeamMembers($accountId);
 	}


 	function setAccountsHavingTeamMembers () {
 		if(DEBUG) {
 			print "setAccountsHavingTeamMembers ()";
 		}

 		$qrySel = "	SELECT group_id adminUserId
 					FROM user
 					WHERE group_id > 0
 					GROUP BY group_id
 					HAVING COUNT(*) > 1";
 		
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
 					array_push($this->arrAccountsHavingTeamMembers, $rowGetInfo['adminUserId']);
 				}
 			}
 		}
 	}


 	function startImportingTeamMembers ($accountMasterId = NULL, $falgExclusive = false) {
 		if(DEBUG) {
 			print "startImportingTeamMembers ()";
 		}

 		if(empty($accountMasterId) || !is_numeric($accountMasterId) || $accountMasterId <= 0) {
 			$accountMasterId = 0;
 		}
 		
 		$this->objLogger->setMessage('Function call for importing team users migration with and after account id (' . $accountMasterId . ')');

 		$arrGetListOfNextAccountsToImport = $this->getListOfAccountsToImportTeamMembers($accountMasterId, $falgExclusive);

 		$this->objLogger->setMessage('Total number of account found after account id (' . $accountMasterId . ') are (' . COUNT($arrGetListOfNextAccountsToImport) . ')');

 		$roleId = 16;
	 	$userTypeId = 5;

 		if(!empty($arrGetListOfNextAccountsToImport)) {

 			$arrNextUserMigrationAccountMasterId = 0;

 			foreach ($arrGetListOfNextAccountsToImport as $key => $arrAccountUserMasterInfo) {

 				$arrNextUserMigrationAccountMasterId = $arrAccountUserMasterInfo['accountMasterId'];
 				$arrNextUserMigrationUserMasterId = $arrAccountUserMasterInfo['userMasterId'];

 				$this->objLogger->setMessage('Account master id (' . $arrNextUserMigrationAccountMasterId . ') with account owner id i.e user master id (' . $arrNextUserMigrationUserMasterId . ')');

 				$arrTeamMembersListForPerticularAccount = $this->getTeamMembersOfAccountOwners($arrNextUserMigrationAccountMasterId);

 				if(!empty($arrTeamMembersListForPerticularAccount)) {

 					// function call to create new team
					$arrParamsForTeamCreation = array(
														'teamName' 			=>	'Default Team',
														'accountMasterId' 	=>	$arrNextUserMigrationAccountMasterId,
														'userId' 			=>	$arrNextUserMigrationUserMasterId
													);

					$this->objLogger->setMessage('Process start to create  default team for account (' . $arrNextUserMigrationAccountMasterId . ') because team members found for this user account (' . $arrNextUserMigrationUserMasterId . '), total team members found are (' . COUNT($arrTeamMembersListForPerticularAccount) . ')');
					
					$defaultTeamIdForThisTeamIteration = $this->createDefaultTeam($arrParamsForTeamCreation);

					$this->objLogger->setMessage('Default team created for account master (' . $arrNextUserMigrationAccountMasterId . ') and default team id is (' . $defaultTeamIdForThisTeamIteration . ')');

 					foreach ($arrTeamMembersListForPerticularAccount as $key => $arrTeamMembersListForPerticularAccountInfo) {


						$id 					= $arrTeamMembersListForPerticularAccountInfo['id'];
						$username 				= $arrTeamMembersListForPerticularAccountInfo['username'];
						$fname 					= $arrTeamMembersListForPerticularAccountInfo['fname'];
						$lname 					= $arrTeamMembersListForPerticularAccountInfo['lname'];
						$email 					= $arrTeamMembersListForPerticularAccountInfo['email'];
						$phone 					= $arrTeamMembersListForPerticularAccountInfo['phone'];
						$companyname 			= $arrTeamMembersListForPerticularAccountInfo['companyname'];
						$password 				= $arrTeamMembersListForPerticularAccountInfo['password'];
						$signature 				= $arrTeamMembersListForPerticularAccountInfo['signature'];
						$role 					= $arrTeamMembersListForPerticularAccountInfo['role'];
						$team_access 			= $arrTeamMembersListForPerticularAccountInfo['team_access'];
						$type 					= $arrTeamMembersListForPerticularAccountInfo['type'];
						$company_logo 			= $arrTeamMembersListForPerticularAccountInfo['company_logo'];
						$last_login 			= $arrTeamMembersListForPerticularAccountInfo['last_login'];
						$branding_status 		= $arrTeamMembersListForPerticularAccountInfo['branding_status'];
						$is_verfied 			= $arrTeamMembersListForPerticularAccountInfo['is_verfied'];
						$is_delete_invite 		= $arrTeamMembersListForPerticularAccountInfo['is_delete_invite'];
						$parent_id 				= $arrTeamMembersListForPerticularAccountInfo['parent_id'];
						$activation_key 		= $arrTeamMembersListForPerticularAccountInfo['activation_key'];
						$is_active 				= $arrTeamMembersListForPerticularAccountInfo['is_active'];
						$group_id 				= $arrTeamMembersListForPerticularAccountInfo['group_id'];
						$tour 					= $arrTeamMembersListForPerticularAccountInfo['tour'];
						$refresh_token 			= $arrTeamMembersListForPerticularAccountInfo['refresh_token'];
						$access_token 			= $arrTeamMembersListForPerticularAccountInfo['access_token'];
						$access_updated 		= $arrTeamMembersListForPerticularAccountInfo['access_updated'];
						$access_limit 			= $arrTeamMembersListForPerticularAccountInfo['access_limit'];
						$access_gmail 			= $arrTeamMembersListForPerticularAccountInfo['access_gmail'];
						$gmail_address 			= $arrTeamMembersListForPerticularAccountInfo['gmail_address'];
						$gmail_failure 			= $arrTeamMembersListForPerticularAccountInfo['gmail_failure'];
						$gmail_failure_error 	= $arrTeamMembersListForPerticularAccountInfo['gmail_failure_error'];
						$gmail_error_code 		= $arrTeamMembersListForPerticularAccountInfo['gmail_error_code'];
						$list_upload_limit 		= $arrTeamMembersListForPerticularAccountInfo['list_upload_limit'];
						$quota_email_today 		= $arrTeamMembersListForPerticularAccountInfo['quota_email_today'];
						$created_at 			= $arrTeamMembersListForPerticularAccountInfo['created_at'];
						$last_online_time 		= $arrTeamMembersListForPerticularAccountInfo['last_online_time'];
						$order_number 			= $arrTeamMembersListForPerticularAccountInfo['order_number'];
						$is_premium 			= $arrTeamMembersListForPerticularAccountInfo['is_premium'];
						$no_user 				= $arrTeamMembersListForPerticularAccountInfo['no_user'];
						$current_plan 			= $arrTeamMembersListForPerticularAccountInfo['current_plan'];
						$time_zone 				= $arrTeamMembersListForPerticularAccountInfo['time_zone'];
						$gmail_label 			= $arrTeamMembersListForPerticularAccountInfo['gmail_label'];
						$plugin_choice 			= $arrTeamMembersListForPerticularAccountInfo['plugin_choice'];
						$deleted_date 			= $arrTeamMembersListForPerticularAccountInfo['deleted_date'];
						$subscribe_report 		= $arrTeamMembersListForPerticularAccountInfo['subscribe_report'];
						$gmail_app_status 		= $arrTeamMembersListForPerticularAccountInfo['gmail_app_status'];
						$gmail_app_last_seen 	= $arrTeamMembersListForPerticularAccountInfo['gmail_app_last_seen'];
						$shrefere 				= $arrTeamMembersListForPerticularAccountInfo['shrefere'];
						$is_deleted 			= $arrTeamMembersListForPerticularAccountInfo['is_deleted'];
						$user_id 				= $arrTeamMembersListForPerticularAccountInfo['user_id'];
						$access_level 			= $arrTeamMembersListForPerticularAccountInfo['access_level'];
						$always_on 				= $arrTeamMembersListForPerticularAccountInfo['always_on'];
						$bcc_to_crm 			= $arrTeamMembersListForPerticularAccountInfo['bcc_to_crm'];
						$show_popup 			= $arrTeamMembersListForPerticularAccountInfo['show_popup'];
						$popup_content 			= $arrTeamMembersListForPerticularAccountInfo['popup_content'];
						$userId 				= $arrTeamMembersListForPerticularAccountInfo['userId'];
						$brandingStatus 		= $arrTeamMembersListForPerticularAccountInfo['brandingStatus'];





						$this->objLogger->setMessage('Processing start for the team member user (' . $userId . ')');

 						// insert entry in user_master
		 				$arrParamsToAddUserMaster = array(
															'userId' 			=>	$userId,
															'accountId' 		=>	$arrNextUserMigrationAccountMasterId,
															'userTypeId' 		=>	$userTypeId,
															'roleId' 			=>	$roleId,
															'sourceId' 			=>	'1',
															'first_name' 		=>	$fname,
															'last_name' 		=>	$lname,
															'email' 			=>	$email,
															'password' 			=>	$password,
															'lastLogin' 		=>	$last_login,
															'createdAt' 		=>	$created_at,
															'phone'				=>	$phone,
															'isDeleted' 		=>	$is_deleted,
															'isActive' 			=>	$is_active,
															'isVerified' 		=>	$is_verfied,
															'alwaysOn'			=>	$always_on,
															'bccToCrm'			=>	$bcc_to_crm,
															'signature'			=>	$signature,
															'timeZone'			=>	$time_zone,
															'brandingStatus'	=>	$brandingStatus,
															'gmailLabel'		=>	$gmail_label
														);

						$teamUserMasterId = $this->addUserMasterEntry($arrParamsToAddUserMaster);

						$this->objLogger->setMessage('New Team user created for the account master (' . $arrNextUserMigrationAccountMasterId . ') is team user id (' . $teamUserMasterId . ')');

						// function call to add user ot it's default team.
						$arrParamsToAddMemberToTeam = array(
																'teamId' 		=>	$defaultTeamIdForThisTeamIteration,
																'userId' 		=>	$teamUserMasterId
															);
						$accountTeamMemberId = $this->addMemberToTeam($arrParamsToAddMemberToTeam);

						$this->objLogger->setMessage('Team user adde to the default team with account team member id (' . $accountTeamMemberId . ') team member id (' . $id . ') account master id (' . $arrNextUserMigrationAccountMasterId . ')');

						$this->objLogger->setMessage('Team member migration complete.');
						$this->objLogger->setMessage('========================================================================================');
 					}
 				}
 				else {
 					$this->objLogger->setMessage('Process start to create  default team for account (' . $arrNextUserMigrationAccountMasterId . ') because team members found for this user account (' . $arrNextUserMigrationUserMasterId . '), total team members found are (' . COUNT($arrTeamMembersListForPerticularAccount) . ')');
 				}
 				$this->objLogger->addLog();
 			}
 			
 			if($arrNextUserMigrationAccountMasterId > 0) {
 				$this->startImportingTeamMembers($arrNextUserMigrationAccountMasterId, true);
 			}
 			else {
 				$this->objLogger->setMessage('All the records have been processed.');
 			}
 		}
 		else {
 			$this->objLogger->setMessage('No records found for the team members.');
 			$this->objLogger->setMessage('Ending the script for team member migration script..');
 			$this->objLogger->addLog();
 		}
 	}


 	function getTeamMembersOfAccountOwners ($usersParentId = NULL) {
 		if(DEBUG) {
 			print "getTeamMembersOfAccountOwners ()";
 		}

 		$arrReturn = array();

 		if(empty($usersParentId) || !is_numeric($usersParentId) || $usersParentId <= 0) {
 			return $arrReturn;
 		}

 		$qrySel = "	SELECT *, u.id userId, us.branding_status brandingStatus
 					FROM user u, user_settings us 
 					WHERE u.id = us.user_id 
 					AND u.parent_id = '" . addslashes($usersParentId) . "'
 					AND u.id <> u.parent_id ";

 		if(DEBUG) {
 			print nl2br($qrySel);
 		}
print "qrySel :: " . $qrySel . "\n";
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


 	function getListOfAccountsToImportTeamMembers ($accountMasterId = NULL, $falgExclusive = false) {
 		if(DEBUG) {
 			print "getListOfAccountsToImportTeamMembers ()";
 		}

 		if(empty($accountMasterId) || !is_numeric($accountMasterId) || $accountMasterId <= 0) {
 			$accountMasterId = 0;
 		}
 		

 		$strAccountIdsHavingTeamMembers = '';
 		if(!empty($this->arrAccountsHavingTeamMembers)) {
 			$strAccountIdsHavingTeamMembers = implode(',', $this->arrAccountsHavingTeamMembers);
 		}

 		$arrReturn = array();

 		$qrySel = "	SELECT am.id accountMasterId, um.id userMasterId
 					FROM account_master am, user_master um
 					WHERE um.account_id = am.id
 					AND um.user_type_id = 4
 					AND um.role_id = 8 ";

 		if(!empty($strAccountIdsHavingTeamMembers)) {
 			$qrySel .= " AND am.id IN (" . $strAccountIdsHavingTeamMembers . ") ";
 		}

 		if($accountMasterId > 0 && !$falgExclusive) {
 			$qrySel .= " AND am.id >= '" . addslashes($accountMasterId) . "' ";
 		}
 		else if($accountMasterId > 0 && $falgExclusive) {
 			$qrySel .= " AND am.id > '" . addslashes($accountMasterId) . "' ";
 		}

 		$qrySel .= " ORDER BY am.id ASC ";
 		
 		// $limit = " LIMIT " . ($this->currentPageAccountMaster * $this->perPageRecordTeamMemberMigratedAccountMaster) . ", " . $this->perPageRecordTeamMemberMigratedAccountMaster;
 		$limit = " LIMIT " . $this->perPageRecordTeamMemberMigratedAccountMaster;

 		$qrySel .= $limit;
 		
 		$this->objLogger->setMessage('In function getListOfAccountsToImportTeamMembers(' . $accountMasterId . ', ' . $falgExclusive . ')');
 		$this->objLogger->setMessage($qrySel);

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

 		$this->currentPageAccountMaster++;
 		return $arrReturn;
 	}


 	function getTeamMembersData () {
 		if(DEBUG) {
 			print "getTeamMembersData ()";
 		}

 		$arrReturn = array();
 		

 		$qrySel = " SELECT *, u.id userId, us.branding_status brandingStatus 
		 			FROM `user` u, user_settings us 
		 			WHERE u.id = us.user_id 
		 			AND ( u.id <> u.group_id OR u.parent_id <> 0) ";

		$limit = " LIMIT " . ($this->lastIndexOfTeamMemberMigrated + ($this->currentPage * $this->perPageRecord)) . ", " . $this->perPageRecord;

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

 		return $arrReturn;
 	}


 	function deleteAllTeamMemberInfoForSpecificAccountExceptOwnerUser ($accountMasterId = NULL) {
 		if(DEBUG) {
 			print "deleteAllTeamMemberInfoForSpecificAccountExceptOwnerUser ()";
 		}

 		if(empty($accountMasterId) || !is_numeric($accountMasterId) || $accountMasterId <= 0) {
 			return false;
 		}


		/**
		 *
		 * Deleting all the users resources of the users who are associated with the 
		 * It is NOT NEEDED still executing the query to make sure not data will remain.
		 *
		 */
		$qrySel = "	DELETE FROM user_resources where user_id IN (SELECT um.id FROM user_master um WHERE um.account_id >= '" . addslashes($accountMasterId) . "' AND um.user_type_id <> 4 AND um.role_id <> 8)";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
print "qrySel :: " . $qrySel . "\n";
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}


		/**
		 *
		 * Deleting users settings to those users who are associtated with the users with that accountmaster.
		 * It is NOT NEEDED still executing the query to make sure not data will remain.
		 *
		 */
		$qrySel = "	DELETE FROM user_settings where user_id IN (SELECT um.id FROM user_master um WHERE um.account_id >= '" . addslashes($accountMasterId) . "' AND um.user_type_id <> 4 AND um.role_id <> 8)";
print "qrySel :: " . $qrySel . "\n";
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}


		/**
		 *
		 * Deleting account team members table from the account_team_members, of the users associated with the that account master.
		 * It is NOT NEEDED still executing the query to make sure not data will remain.
		 *
		 */
		$qrySel = "	DELETE FROM account_team_members where user_id IN (SELECT um.id FROM user_master um WHERE um.account_id >= '" . addslashes($accountMasterId) . "' AND um.user_type_id <> 4 AND um.role_id <> 8)";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
print "qrySel :: " . $qrySel . "\n";		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}


		/**
		 *
		 * Deleting all the users associated with the account master
		 * It is NOT NEEDED still executing the query to make sure not data will remain.
		 *
		 */
		$qrySel = "	DELETE FROM user_master where account_id >= '" . addslashes($accountMasterId) . "' AND user_type_id <> 4 AND role_id <> 8 ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
print "qrySel :: " . $qrySel . "\n";
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}


		/**
		 *
		 * Deleting account organization table from the account_organization,
		 * It is NOT NEEDED still executing the query to make sure not data will remain.
		 *
		 */
		$qrySel = "	DELETE FROM account_teams where account_id >= '" . addslashes($accountMasterId) . "' ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
print "qrySel :: " . $qrySel . "\n";
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}
 	}

 	function getLastInsertedTeamMemberUser () {
 		if(DEBUG) {
 			print "getLastInsertedTeamMemberUser ()";
 		}
 		
 		$arrReturn = array();
 		$arrReturn['indexCounter'] = 0;
 		$arrReturn['userMasterId'] = 0;
 		$arrReturn['accountId'] = 0;

 		$qrySel = "	SELECT (@counter := @counter + 1) indexCounter, um.id userMasterId, um.account_id accountId
 					FROM user_master um, (SELECT @counter := 1) as c
 					WHERE um.user_type_id <> 4
 					AND um.role_id <> 8
 					ORDER BY id DESC LIMIT 1";
 		
 		if(DEBUG) {
 			print nl2br($qrySel);
 		}
 		
 		$this->objLogger->setMessage('getLastInsertedTeamMemberUser');
 		$this->objLogger->setMessage($qrySel);

 		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
 		
 		if(!$objDBResult) {
 			print "Error occur.";
 			return false;
 		}
 		else {
 			if($objDBResult->getNumRows() > 0) {
 				$rowGetInfo = $objDBResult->fetchAssoc();
				$arrReturn['indexCounter'] = $rowGetInfo['indexCounter'];
 				$arrReturn['userMasterId'] = $rowGetInfo['userMasterId'];
 				$arrReturn['accountId'] = $rowGetInfo['accountId'];
 			}
 		}

 		return $arrReturn;
 	}

	function checkIncrementalAndResumeScriptForAdminUser () {
		if(DEBUG) {
			print "checkIncrementalAndResumeScriptForAdminUser ()";
		}

 		/**
 		 *
 		 * Checking for the last inserted account master id, as accountmaster is the first related entry to the admin user.
 		 *
 		 */
		$this->arrLastAccountMasterIdInsertedData = $this->checkLastInsertedAccountMasterIdPosition();

 		/**
 		 *
 		 * If found an already inserted account master id, Then delete all it's related entries and start importing from there.
 		 *
 		 */

		if($this->arrLastAccountMasterIdInsertedData['indexCounter'] > 0) {
			
			$this->intLastAccountMasterInsertedPosition = ($this->arrLastAccountMasterIdInsertedData['indexCounter'] - 1);

			// $this->flagLastAccountMasterIdGenerated = true;
			// $this->lastAccountMasterIdGenerated = $this->arrLastAccountMasterIdInsertedData['accountMasterId'];

			$this->deleteAllRelatedDataForThisAccount($this->arrLastAccountMasterIdInsertedData['accountMasterId']);
		}
 		

 		/**
 		 *
 		 * Once all the related entry for the last inserted account master entry is delted then import from that account master.
 		 *
 		 */
 		
		$this->createAdminUser();
	}


	function deleteAllRelatedDataForThisAccount ($accountMasterId = NULL) {
		if(DEBUG) {
			print "deleteAllRelatedDataForThisAccount ()";
		}

		if(empty($accountMasterId) || !is_numeric($accountMasterId) || $accountMasterId <= 0) {
			return false;
		}



		/**
		 *
		 * Deleting account master entry from account_master table.
		 *
		 */
		
		$qrySel = "	DELETE FROM account_master where id = '" . addslashes($accountMasterId) . "' ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}



		/**
		 *
		 * Deleting account organization table from the account_organization,
		 * It is NOT NEEDED still executing the query to make sure not data will remain.
		 *
		 */
		$qrySel = "	DELETE FROM account_organization where account_id = '" . addslashes($accountMasterId) . "' ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}



		/**
		 *
		 * Deleting all the users resources of the users who are associated with the 
		 * It is NOT NEEDED still executing the query to make sure not data will remain.
		 *
		 */
		$qrySel = "	DELETE FROM user_resources where user_id IN (SELECT um.id FROM user_master um WHERE um.account_id = '" . addslashes($accountMasterId) . "')";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}


		/**
		 *
		 * Deleting users settings to those users who are associtated with the users with that accountmaster.
		 * It is NOT NEEDED still executing the query to make sure not data will remain.
		 *
		 */
		$qrySel = "	DELETE FROM user_settings where user_id IN (SELECT um.id FROM user_master um WHERE um.account_id = '" . addslashes($accountMasterId) . "')";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}


		/**
		 *
		 * Deleting account team members table from the account_team_members, of the users associated with the that account master.
		 * It is NOT NEEDED still executing the query to make sure not data will remain.
		 *
		 */
		$qrySel = "	DELETE FROM account_team_members where user_id IN (SELECT um.id FROM user_master um WHERE um.account_id = '" . addslashes($accountMasterId) . "')";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}


		/**
		 *
		 * Deleting all the users associated with the account master
		 * It is NOT NEEDED still executing the query to make sure not data will remain.
		 *
		 */
		$qrySel = "	DELETE FROM user_master where account_id = '" . addslashes($accountMasterId) . "' ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}


		/**
		 *
		 * Deleting account organization table from the account_organization,
		 * It is NOT NEEDED still executing the query to make sure not data will remain.
		 *
		 */
		$qrySel = "	DELETE FROM account_teams where account_id = '" . addslashes($accountMasterId) . "' ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}
	}

	function checkLastInsertedAccountMasterIdPosition () {
		if(DEBUG) {
			print "checkLastInsertedAccountMasterIdPosition ()";
		}

		$arrReturn = array();
		$arrReturn['indexCounter'] = 0;
		$arrReturn['accountMasterId'] = 0;


		$qrySel = "	SELECT (@counter := @counter + 1) AS indexCounter, ac.id accountMasterId
					FROM account_master ac, (SELECT @counter := 0) AS c
					ORDER BY indexCounter DESC LIMIT 1";
		
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

				$arrReturn['indexCounter'] = $rowGetInfo['indexCounter'];
				$arrReturn['accountMasterId'] = $rowGetInfo['accountMasterId'];
			}
		}

		return $arrReturn;
	}


	function createAdminUser () {
		if(DEBUG) {
			print "createAdminUser()";
		}

 	 	$arrOldUsersInfo = $this->getOldDatabaseAdminUsersUsers();

 	 	if(!empty($arrOldUsersInfo)) {

			foreach ($arrOldUsersInfo as $key => $arrOldUserDbInfo) {

print "counterVariable :: ";
print_r($this->counterVariable);
print "\n";
$this->counterVariable++;
				$id = $arrOldUserDbInfo['userId'];
				$fname = $arrOldUserDbInfo['fname'];
				$lname = $arrOldUserDbInfo['lname'];
				$email = $arrOldUserDbInfo['email'];
				$phone = $arrOldUserDbInfo['phone'];
				$companyname = $arrOldUserDbInfo['companyname'];
				$password = $arrOldUserDbInfo['password'];
				$signature = $arrOldUserDbInfo['signature'];
				$company_logo = $arrOldUserDbInfo['company_logo'];
				$is_verfied = $arrOldUserDbInfo['is_verfied'];
				$is_active = $arrOldUserDbInfo['is_active'];
				$refresh_token = $arrOldUserDbInfo['refresh_token'];
				$access_token = $arrOldUserDbInfo['access_token'];
				$gmail_address = $arrOldUserDbInfo['gmail_address'];
				$created_at = $arrOldUserDbInfo['created_at'];
				$order_number = $arrOldUserDbInfo['order_number'];
				$time_zone = $arrOldUserDbInfo['time_zone'];
				$gmail_label = $arrOldUserDbInfo['gmail_label'];
				$is_deleted = $arrOldUserDbInfo['is_deleted'];
				$parent_id = $arrOldUserDbInfo['parent_id'];
				$group_id = $arrOldUserDbInfo['group_id'];
				$last_login = $arrOldUserDbInfo['last_login'];

				$is_deleted = $arrOldUserDbInfo['is_deleted'];
				
				$always_on = $arrOldUserDbInfo['always_on'];
				$bcc_to_crm = $arrOldUserDbInfo['bcc_to_crm'];
				$last_login = $arrOldUserDbInfo['last_login'];
				$brandingStatus = $arrOldUserDbInfo['brandingStatus'];


 				$this->objLogger->setMessage("Starting migration of user (" . $id . ") - " . $fname . " " . $lname);
 				

 				if(!in_array($email, $this->arrEmailsProcessed)) {
 					array_push($this->arrEmailsProcessed, $email);
 				}
 				else {
 					$this->objLogger->setMessage("Email alreasy exists for the user (" . $id . ") - " . $fname . " " . $lname . " - " . $email);
 					continue;
 				}

 				$accountMasterId = 0;
 				$falgOwnerAccount = false;
				
 				//to check users account type, Owner or team member.
 				if($id == $group_id && $parent_id == 0) {

 					// $falgOwnerAccount = true;

 					$this->objLogger->setMessage($id . " User is owner type. Starting process to create new account");

 					//create account for the owner account
 					$arrParamsForAccountCreation = array(
				 										'created_at' 	=> 	$created_at,
				 									);

 					if($this->flagLastAccountMasterIdGenerated) {
 						$this->flagLastAccountMasterIdGenerated = false;
 						$arrParamsForAccountCreation['accountMasterId'] = $this->lastAccountMasterIdGenerated;
 					}
 					$this->accountMasterId = $this->objAccount->createNewAccountForUser($arrParamsForAccountCreation);

 					if(!is_numeric($this->accountMasterId) || $this->accountMasterId <= 0) {
 						$this->objLogger->setMessage('Account Master NOT inserted. user id (' . $id . ')');
 						break;
 					}

 					$accountMasterId = $this->accountMasterId;
 					$roleId = 8;
 					$userTypeId = 4;
 					
 					$this->objLogger->setMessage("Creating account organization for user - (" . $id . ")");

 					// create account origanizations
 					$arrParamsForAccountOrganization = array(
 																'accountId' 	=>	$this->accountMasterId,
 																'companyName' 	=>	$companyname,
 																'logo'			=>	$company_logo,
 																'createdAt' 	=>	$created_at
 															);
 					$this->accountOrganizationId = $this->objAccountOrganization->createAccountOrganization($arrParamsForAccountOrganization);
 				}
 				// else the user is team member
 				else {
 					$this->objLogger->setMessage($id . " User is a team member type.");
 					
 					if(!isset($this->arrUserIdAndAccountMasterIdMap[$parent_id])) {
 						// error
 						$this->objLogger->setMessage("Invalid account, No Parent Id match with any user (" . $id . ") - " . $fname . " " . $lname . " - " . $email);
 						continue;
 					}
 					// accountId found, adding as team member.
 					else {
 						$accountMasterId = $this->arrUserIdAndAccountMasterIdMap[$parent_id]['accountMasterId'];

	 					$roleId = 16;
	 					$userTypeId = 5;
 					}
 				}

 				if($accountMasterId > 0) {
 					// insert entry in user_master
	 				$arrParamsToAddUserMaster = array(
														'userId' 			=>	$id,
														'accountId' 		=>	$accountMasterId,
														'userTypeId' 		=>	$userTypeId,
														'roleId' 			=>	$roleId,
														'sourceId' 			=>	'1',
														'first_name' 		=>	$fname,
														'last_name' 		=>	$lname,
														'email' 			=>	$email,
														'password' 			=>	$password,
														'lastLogin' 		=>	$last_login,
														'createdAt' 		=>	$created_at,
														'phone'				=>	$phone,
														'isDeleted' 		=>	$is_deleted,
														'isActive' 			=>	$is_active,
														'isVerified' 		=>	$is_verfied,
														'alwaysOn'			=>	$always_on,
														'bccToCrm'			=>	$bcc_to_crm,
														'signature'			=>	$signature,
														'timeZone'			=>	$time_zone,
														'brandingStatus'	=>	$brandingStatus,
														'gmailLabel'		=>	$gmail_label
													);

					$this->userMasterId = $this->addUserMasterEntry($arrParamsToAddUserMaster);

					// TEAM RELATED QUERIES
					$intDefaultTeamId = 0;
 					// if owner's account then  create new default team and save it's id in array
					if($falgOwnerAccount) {
						// function call to create new team
						$arrParamsForTeamCreation = array(
															'teamName' 			=>	'Default Team',
															'accountMasterId' 	=>	$this->accountMasterId,
															'userId' 			=>	$this->userMasterId
														);

						$intDefaultTeamId = $this->createDefaultTeam($arrParamsForTeamCreation);

						$arrTmp = array(
										'accountMasterId' 	=>	$this->accountMasterId,
										'userId' 			=>	$id,
										'defaultTeamId' 	=> 	$intDefaultTeamId
										);

						$this->arrUserIdAndAccountMasterIdMap[$id] = $arrTmp;
					}
					// if account type is of team member, then just take the default team id of the owners team and add this member into it.
					else {
						if(isset($this->arrUserIdAndAccountMasterIdMap[$parent_id])) {
							$intDefaultTeamId = $this->arrUserIdAndAccountMasterIdMap[$parent_id]['defaultTeamId'];
						}
					}

					if(is_numeric($intDefaultTeamId) && $intDefaultTeamId > 0) {
						// function call to add user ot it's default team.
						$arrParamsToAddMemberToTeam = array(
																'teamId' 		=>	$intDefaultTeamId,
																'userId' 		=>	$this->userMasterId
															);
						$this->addMemberToTeam($arrParamsToAddMemberToTeam);
					}


 				}
 				else {

 					$arrParamsForNotMigratedUsers = array();
 					$arrParamsForNotMigratedUsers['id'] = $id;
 					$arrParamsForNotMigratedUsers['first_name'] = $fname;
 					$arrParamsForNotMigratedUsers['last_name'] = $lname;
 					$arrParamsForNotMigratedUsers['email'] = $email;
 					$this->insertIntoNotMigratedUsers($arrParamsForNotMigratedUsers);
 					
 					$this->objLogger->setMessage("Some error occured while searcing for accountmaster (" . $id . ") - " . $fname . " " . $lname . " - " . $email);
 				}

 				$this->objLogger->addLog();
			}

 	 		// recursion function calling.
 	 		return $this->createAdminUser();
 	 	}
 	 	else {
 	 		return true;
 	 	}
	}


	function addMemberToTeam ($arrParams) {
		if(DEBUG) {
			print "addMemberToTeam ()";
		}
 		
		$intReturn = 0;

 		$currentDateTimeStamp = self::convertDateTimeIntoTimeStamp();

		$userId = $arrParams['userId'];
		$teamId = $arrParams['teamId'];


		$qryIns = "	INSERT INTO account_team_members (account_team_id, user_id, status, modified)
					VALUES ('" . addslashes($teamId) . "',
							'" . addslashes($userId) . "',
							'1',
							'" . $currentDateTimeStamp . "')";
				
		if(DEBUG) {
			print nl2br($qryIns);
		}
		
		$teamMemberId = $this->objDBConNew->insertAndGetId($qryIns);
		
		if(!$teamMemberId) {
			print "Cant insert into 'account_team_members', error occured.";			
		}
		else {
			$intReturn = $teamMemberId;
		}
		return $intReturn;
	}


	function createDefaultTeam ($arrParams = array()) {
		if(DEBUG) {
			print "createDefaultTeam ()";
		}

 		
 		$currentDateTimeStamp = self::convertDateTimeIntoTimeStamp();

		$teamName = $arrParams['teamName'];
		$accountMasterId = $arrParams['accountMasterId'];
		$userId = $arrParams['userId'];


		$qryIns = "	INSERT INTO account_teams (account_id, source_id, name, user_id, owner_id, manager_id, status, created, modified)
					VALUES ('" . addslashes($accountMasterId) . "',
							'1',
							'" . addslashes($teamName) . "',
							" . addslashes($userId) . ",
							" . addslashes($userId) . ",
							" . addslashes($userId) . ",
							'1',
							'" . addslashes($currentDateTimeStamp) . "',
							'" . addslashes($currentDateTimeStamp) . "'
						)";
				
		if(DEBUG) {
			print nl2br($qryIns);
		}
		
		$defaultTeamId = $this->objDBConNew->insertAndGetId($qryIns);
		
		if(!$defaultTeamId) {
			print "Cant insert into 'account_teams', error occured.";
			$this->objLogger->setMessage("Unable to create team for account master (" . $accountMasterId . ")");
		}
		else {
			$this->objLogger->setMessage("Default team created team for account master (" . $accountMasterId . ")");
		}

		return $defaultTeamId;
	}


	function addUserMasterEntry ($arrParams = array()) {
		if(DEBUG) {
			print "addUserMasterEntry ()";
		}

		$intReturn = 0;

		if(empty($arrParams)) {
			return $intReturn;
		}


		$userId = $arrParams['userId'];
		$accountId = $arrParams['accountId'];
		$userTypeId = $arrParams['userTypeId'];
		$roleId = $arrParams['roleId'];
		$sourceId = $arrParams['sourceId'];
		$first_name = $arrParams['first_name'];
		$last_name = $arrParams['last_name'];
		$email = $arrParams['email'];
		$password = $arrParams['password'];
		$lastLogin = $arrParams['lastLogin'];
		$createdAt = $arrParams['createdAt'];
		$phone = $arrParams['phone'];
		$isDeleted = $arrParams['isDeleted'];
		$isVerified = $arrParams['isVerified'];
		$isActive = $arrParams['isActive'];


		$alwaysOn 		= $arrParams['alwaysOn'];
		$bccToCrm 		= $arrParams['bccToCrm'];
		$signature 		= $arrParams['signature'];
		$timeZone 		= $arrParams['timeZone'];
		$brandingStatus = $arrParams['brandingStatus'];
		$gmailLabel 	= $arrParams['gmailLabel'];

		// $intDefaultTeamId 	= $arrParams['intDefaultTeamId'];

		


		$randomSalt = $this->generateRandomSalt();
		$encryptedPassword = $this->encryptPassword($password, $randomSalt);

		$convertedLastLogin = self::convertDateTimeIntoTimeStamp($lastLogin);
		$convertedCreatedAt = self::convertDateTimeIntoTimeStamp($createdAt);


 		$userStatus = 0;
		if($isDeleted == "2") {
			$userStatus = 5;
		}
		else if($isDeleted == "1") {
			$userStatus = 2;
		}
		else if($isDeleted == "0" && $isActive == "1") {
			$userStatus = 1;
		}
		else if($isDeleted == "0" && $isActive == "0") {
			$userStatus = 0;
		}


		$qryIns = "	INSERT INTO user_master (id, account_id, user_type_id, role_id, source_id, first_name, last_name, email, password, password_salt_key, phone, photo, last_login, verified, status, created, modified)
					VALUES ('" . addslashes($userId) . "',
							'" . addslashes($accountId) . "',
							'" . addslashes($userTypeId) . "',
							'" . addslashes($roleId) . "',
							'" . addslashes('1') . "',
							'" . addslashes($first_name) . "',
							'" . addslashes($last_name) . "',
							'" . addslashes($email) . "',
							'" . addslashes($encryptedPassword) . "',
							'" . addslashes($randomSalt) . "',
							'" . addslashes($phone) . "',
							'',
							'" . addslashes($convertedLastLogin) . "',
							'" . addslashes($isVerified) . "',
							'" . addslashes($userStatus) . "',
							'" . addslashes($convertedCreatedAt) . "',
							'" . addslashes($convertedCreatedAt) . "'
						 )";
				
		if(DEBUG) {
			print nl2br($qryIns);
		}
		
		$userMasterId = $this->objDBConNew->insertAndGetId($qryIns);

		if(!$userMasterId) {
			print "Cant insert into 'user_master', error occured.";
			return false;
		}
		else {

			$intReturn = $userMasterId;

			// Assining roles and resources to the users.
			$arrParamsForAssignRolesAndResources = array(
								'USERID' 		=>	$userMasterId,
								'ROLEID' 		=>	$roleId,
								'USERTYPEID' 	=>	$userTypeId
							);

			$flagUserRoleAssign = $this->assignUserRolesAndResources($arrParamsForAssignRolesAndResources);

			if(!$flagUserRoleAssign) {
				$this->objLogger->setMessage($userMasterId . " Some error occured while assigning role to user.");
			}



			//Importing user's settings from the old version.
			$arrParamsForUserSettings = array(
												'signature' 		=>	$signature,
												'alwaysOn'			=>	$alwaysOn,
												'bccToCrm' 			=>	$bccToCrm,
												'timeZone' 			=>	$timeZone,
												'brandingStatus'	=>	$brandingStatus,
												'gmailLabel'		=>	$gmailLabel,
												'userMasterId'		=>	$userMasterId
			 								);
			$this->addUsersSettings($arrParamsForUserSettings);

		}

		return $intReturn;
	}


	function addUsersSettings ($arrParams = array()) {
		if(DEBUG) {
			print "addUsersSettings ()";
		}


		$signature 		= $arrParams['signature'];
		$alwaysOn 		= $arrParams['alwaysOn'];
		$bccToCrm 		= $arrParams['bccToCrm'];
		$timeZone 		= $arrParams['timeZone'];
		$brandingStatus = $arrParams['brandingStatus'];
		$gmailLabel 	= $arrParams['gmailLabel'];
		$userMasterId 	= $arrParams['userMasterId'];


		$currentDateTimeStamp = self::convertDateTimeIntoTimeStamp();

		$strInsertQry = "";
		
		if(!empty($signature)) {
			$appVarId = $this->getAppContantVarId('U_SIGNATURE');
			$strInsertQry .= " (" . addslashes($userMasterId) . ", " . $appVarId . ", '" . addslashes($signature) . "', " . $currentDateTimeStamp . "), ";
		}

		if(!empty($alwaysOn)) {
			$appVarId = $this->getAppContantVarId('U_TRACK_EMAILS');
			$strInsertQry .= " (" . addslashes($userMasterId) . ", " . $appVarId . ", '" . addslashes($alwaysOn) . "', " . $currentDateTimeStamp . "), ";
		}

		if(!empty($bccToCrm)) {
			$appVarId = $this->getAppContantVarId('U_BCC');
			$strInsertQry .= " (" . addslashes($userMasterId) . ", " . $appVarId . ", '" . addslashes($bccToCrm) . "', " . $currentDateTimeStamp . "), ";
		}
		if(!empty($timeZone)) {
			$appVarId = $this->getAppContantVarId('U_TIMEZONE');
			$strInsertQry .= " (" . addslashes($userMasterId) . ", " . $appVarId . ", '" . addslashes($timeZone) . "', " . $currentDateTimeStamp . "), ";
		}

		if(!empty($brandingStatus)) {
			$appVarId = $this->getAppContantVarId('U_POWERED_BY_SH');
			$strInsertQry .= " (" . addslashes($userMasterId) . ", " . $appVarId . ", '" . addslashes($brandingStatus) . "', " . $currentDateTimeStamp . "), ";
		}

		if(!empty($gmailLabel)) {
			$appVarId = $this->getAppContantVarId('U_GMAIL_LABEL');
			$strInsertQry .= " (" . addslashes($userMasterId) . ", " . $appVarId . ", '" . addslashes($gmailLabel) . "', " . $currentDateTimeStamp . "), ";
		}

		if(!empty($strInsertQry)) {
			$strInsertQry = rtrim($strInsertQry, ', ');
		}

		if(!empty($strInsertQry)) {

			$qrySel = "	INSERT INTO user_settings(user_id, app_constant_var_id, value, modified)
						VALUES " . $strInsertQry;

			if(DEBUG) {
				print nl2br($qrySel);
			}
			
			$objDBResult = $this->objDBConNew->executeQuery($qrySel);
			
			if(!$objDBResult) {
				print "Error occur.";
				
			}
		}
	}

	function getAppContantVarId ($appVarConstant = NULL) {
		if(DEBUG) {
			print "getAppContantVarId ()";
		}

		$intReturn = 0;
		if(empty($appVarConstant)) {
			return $appVarConstant;
		}

		foreach ($this->arrUserSettingAppVars as $key => $arrAppVarInfo) {
			if($arrAppVarInfo['code'] == $appVarConstant) {
				$intReturn = $arrAppVarInfo['id'];
				break;
			}
		}

		return $intReturn;
	}


	function assignUserRolesAndResources ($arrParams = array()) {
		if(DEBUG) {
			print "assignUserRolesAndResources ()";
		}

		$flagReturn = false;

		$currentDateTimeStamp = self::convertDateTimeIntoTimeStamp();

 		$qryIns = "	INSERT INTO user_resources (user_id, resource_id, status, modified) 
 						SELECT '" . addslashes($arrParams['USERID']) . "', rdr.resource_id, '1', '" . addslashes($currentDateTimeStamp) . "'
 						FROM role_default_resources rdr 
 						WHERE rdr.status = '1'
 						AND rdr.role_id = '" . addslashes($arrParams['ROLEID']) . "' ";

 		if(DEBUG) {
 			print nl2br($qryIns);
 		}
 		
 		$insertedId = $this->objDBConNew->executeQuery($qryIns);
 		
 		if(!$insertedId) {
 			print "Cant insert into 'user_resources', error occured.";
 		}
 		else {
 			$flagReturn = true;
 		}

 		return $flagReturn;
	}





	function getOldDatabaseAdminUsersUsers () {
		if(DEBUG) {
			print "getOldDatabaseAdminUsersUsers ()";
		}

		$arrReturn = array();

		// $qrySel = "	SELECT u.id userId, *, us.branding_status brandingStatus FROM user u LEFT JOIN user_settings us ON u.id = us.user_id ORDER BY u.id ASC ";

		$qrySel = " SELECT *, u.id userId, us.branding_status brandingStatus 
		 			FROM `user` u, user_settings us 
		 			WHERE u.id = us.user_id 
		 			AND u.id = u.group_id
		 			AND u.parent_id = 0
		 			ORDER BY u.id ASC ";

		$limit = " LIMIT " . ($this->intLastAccountMasterInsertedPosition + ($this->currentPage * $this->perPageRecord)) . ", " . $this->perPageRecord;

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



	function generateRandomSalt () {
		if(DEBUG) {
			print "generateRandomSalt ()";
		}


		// A higher "cost" is more secure but consumes more processing power
        $cost = 10;

        // Create a random salt
        $salt = strtr(base64_encode(mcrypt_create_iv(16, MCRYPT_DEV_URANDOM)), "+", ".");

        // Prefix information about the hash so PHP knows how to verify it later.
        // "$2a$" Means we're using the Blowfish algorithm. The following two digits are the cost parameter.
        $salt = sprintf("$2a$%02d$", $cost) . $salt;

        return $salt;
	}

	function encryptPassword ($password, $salt) {
		if(DEBUG) {
			print "encryptPassword ()";
		}

		$hash = crypt($password, $salt);

		return $hash;
	}
}

?>