<?php


CLASS EMAILACCOUNT EXTENDS GENERALFUNCTIONS {

	protected $objLogger;

	function EMAILACCOUNT ($objLogger) {
		if(DEBUG) {
			print "EMAILACCOUNT ()";
		}

		$this->objDBConNew = new COMMONDBFUNC();
		$this->objDBConOld = new COMMONDBFUNC(HOST_OLD, PASSWORD_OLD, USERNAME_OLD, DBNAME_OLD);

		$this->objLogger = $objLogger;

		
 		
 		$this->initQueryLimit();
 		

 		$this->arrUsersGmailSettings = array();
 		$this->arrUserSMTPSettings = array();
 		$this->arrUsersEmailSettingsMerge = array();
	}

	function initQueryLimit () {
		if(DEBUG) {
			print "initQueryLimit ()";
		}

		$this->perPageRecord = 1000;
		$this->currentPage = 0;

		$this->successfullComplete = 0;
 		$this->failed = 0;
 		$this->total = 0;
	}


	function migrateEmailAccountsIncrementalScript () {
		if(DEBUG) {
			print "migrateEmailAccountsIncrementalScript ()";
		}
 		

 		/**
 		 * Resetting account_sending_methods table.
 		 * 
 		 * */
		$this->truncateAccountEmailSendingMethods();
 		

 		/**
 		 * Starting the migration process again.
 		 */
 		$this->migrateEmailAccounts();
	}


	function truncateAccountEmailSendingMethods () {
		if(DEBUG) {
			print "truncateAccountEmailSendingMethods ()";
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

		$qrySel = "	TRUNCATE account_sending_methods ";
		
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


	function migrateEmailAccounts () {
		if(DEBUG) {
			print "migrateEmailAccounts ()";
		}

		$arrUserEmailAccountMigrationInfo = $this->getUserMigrationInfo();

	}



	function getUserMigrationInfo () {
		if(DEBUG) {
			print "getUserMigrationInfo ()";
		}

		$arrReturn = array();

 		$this->initQueryLimit();
		$this->getUsersGmailSettings();

		$this->initQueryLimit();
		$this->getUsersSmtpSettings();


		$arrAllGmailKeys = array_keys($this->arrUsersGmailSettings);
		$totarrAllGmailKeys = COUNT($arrAllGmailKeys);


		$arrAllSMTPKeys = array_keys($this->arrUserSMTPSettings);
		$totarrAllSMTPKeys = COUNT($arrAllSMTPKeys);

		$arrAllCombineUsers = array_merge($arrAllGmailKeys, $arrAllSMTPKeys);
		$arrAllCombineUsers = array_unique($arrAllCombineUsers);
		$totarrAllCombineUsers = COUNT($arrAllCombineUsers);

 		$arrUsersHaveBothSetting = array_intersect($arrAllGmailKeys, $arrAllSMTPKeys);
 		$totarrUsersHaveBothSetting = COUNT($arrUsersHaveBothSetting);

		$arrOnlyGmail = array_diff($arrAllGmailKeys, $arrUsersHaveBothSetting);
		$totarrOnlyGmail = COUNT($arrOnlyGmail);

		$arrOnlySMTP = array_diff($arrAllSMTPKeys, $arrUsersHaveBothSetting);
		$totarrOnlySMTP = COUNT($arrOnlySMTP);

 		foreach ($this->arrUsersGmailSettings as $userId => $arrGmailInfo) {
 			if(!isset($this->arrUsersEmailSettingsMerge[$userId])) {
 				$this->arrUsersEmailSettingsMerge[$userId] = array();
 			}
 			$this->arrUsersEmailSettingsMerge[$userId]['GMAIL'] = $arrGmailInfo;
 		}

 		foreach ($this->arrUserSMTPSettings as $userId => $arrSMTPSetting) {
 			if(!isset($this->arrUsersEmailSettingsMerge[$userId])) {
 				$this->arrUsersEmailSettingsMerge[$userId] = array();
 			}
 			$this->arrUsersEmailSettingsMerge[$userId]['SMTP'] = $arrSMTPSetting;
 		}

		$overallCount = COUNT($this->arrUsersEmailSettingsMerge);
 		
 		$this->startInsertingValuesIntoEmailAccounts();
	}



	function startInsertingValuesIntoEmailAccounts ($page = 0) {
		if(DEBUG) {
			print "startInsertingValuesIntoEmailAccounts ()";
		}
 		
 		$qrygen = 0;
 		$qryInsValue = "";
 		$totcount = COUNT($this->arrUsersEmailSettingsMerge);
print "totcount :: " . $totcount . "\n";

		foreach ($this->arrUsersEmailSettingsMerge as $userId => $arrEmailAccountSetting) {
			$accountId = $this->getAccountNumberForUser($userId);

			if($accountId > 0) {
				// set query for gmail account
				if(isset($arrEmailAccountSetting['GMAIL'])) {

					$arrGmailInfo = $arrEmailAccountSetting['GMAIL'];

					$payLoad = json_encode($arrGmailInfo['payload']);

					$qryInsValue .= " (
										'" . addslashes($accountId) . "',
										'" . addslashes($userId) . "',
										'1',
										'1',
										'" . addslashes($arrGmailInfo['usersName']) . "',
										'" . addslashes($arrGmailInfo['usersName']) . "',
										'" . addslashes($arrGmailInfo['googleEmail']) . "',
										'" . addslashes($payLoad) . "',
										'{}',
										'1',
										'',
										0,
										'',
										0,
										0,
										0,
										0,
										0,
										0,
										0,
										0,
										0,
										1,
										0,
										0
										), ";
				}
				
				// set query for SMTP account
				if(isset($arrEmailAccountSetting['SMTP'])) {

					$arrSMTPInfo = $arrEmailAccountSetting['SMTP'];

					$payLoad = json_encode($arrSMTPInfo['payload']);

					$connection_status = $arrSMTPInfo['tested'];
					$last_connected_at = 0;
 					$connection_info = "";
 					if($connection_status == "1") {
 						$connection_info = $arrSMTPInfo['error'];
 						$last_connected_at = self::convertDateTimeIntoTimeStamp($arrSMTPInfo['last_test_date']);
 					}




					$qryInsValue .= " (
										'" . addslashes($accountId) . "',
										'" . addslashes($userId) . "',
										'2',
										'1',
										'" . addslashes($arrSMTPInfo['payload']['username']) . "',
										'" . addslashes($arrSMTPInfo['payload']['username']) . "',
										'" . addslashes($arrSMTPInfo['payload']['username']) . "',
										'" . addslashes($payLoad) . "',
										'{}',
										'" . addslashes($connection_status) . "',
										'',
										" . addslashes($last_connected_at) . ",
										'" . addslashes($connection_info) . "',
										0,
										0,
										0,
										0,
										0,
										0,
										0,
										0,
										0,
										1,
										0,
										0
										), ";
				}

				$qrygen++;
print "qrygen :: " . $qrygen . "\n";
				if($qrygen > 50) {

					$qryInsValue = rtrim($qryInsValue, ', ');

					$qryIns = "	INSERT INTO account_sending_methods (account_id, user_id, email_sending_method_id, source_id, name, from_name, from_email, payload, incoming_payload, connection_status, connection_info, last_connected_at, last_error, total_mail_sent, total_mail_failed, total_mail_replied, total_mail_bounced, public, total_limit, credit_limit, last_reset, next_reset, status, created, modified)
								VALUES " . $qryInsValue;

					if(DEBUG) {
						print nl2br($qryIns);
					}
					
					$accountSendingMethodId = $this->objDBConNew->insertAndGetId($qryIns);
					
					if(!$accountSendingMethodId) {
						print "Cant insert into 'account_sending_methods', error occured.";
						return false;
					}
					$qryInsValue = "";
					$qrygen = 0;
				}
			}
			else {
				// no account found
			}
		}


		/**
		 * updating is_default to 1 for all the email accounts.
		 */

		$qryupd = "	UPDATE account_sending_methods
					SET is_default = 1 ";
		
		if(DEBUG) {
			print nl2br($qryupd);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qryupd);	
		
		if(!$objDBResult) {
			print "Cant Update into 'account_sending_methods', error occured.";
			return false;
		}
	}

	function getUsersGmailSettings () {
		if(DEBUG) {
			print "getUsersGmailSettings ()";
		}

		// $qrySel = "	SELECT u.id userId, u.refresh_token refreshToken, u.email googleEmail, CONCAT_WS(' ', u.fname, u.lname) usersName, ugs.access_token accessToken, ugs.expires_on accessTokenExpireOn
		// 			FROM user u LEFT JOIN user_gmail_settings ugs ON u.id = ugs.user_id
		// 			WHERE u.refresh_token IS NOT NULL ";


		$qrySel = "	SELECT u.id userId, u.refresh_token refreshToken, u.email googleEmail, CONCAT_WS(' ', u.fname, u.lname) usersName, u.access_token accessToken
					FROM user u
					WHERE u.refresh_token IS NOT NULL ";

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
			$gmailNum = $objDBResult->getNumRows();

			if($objDBResult->getNumRows() > 0) {
				while($rowGetInfo = $objDBResult->fetchAssoc()) {
					$arrTmp = array(
						 				'payload' 	=>	array(
						 					 					'code' 					=>	'',
						 					 					'name' 					=>	$rowGetInfo['usersName'],
						 					 					'email'					=>	$rowGetInfo['googleEmail'],
						 					 					'hd'					=>	'',
						 					 					'picture'				=>	'',
						 										'refresh_token' 		=>	$rowGetInfo['refreshToken'],
																'access_token' 			=>	$rowGetInfo['accessToken']/*,
																'access_token_expire' 	=>	$rowGetInfo['accessTokenExpireOn']*/
						 									),
						 				'googleEmail' 	=> 	$rowGetInfo['googleEmail'],
						 				'usersName' 	=>	$rowGetInfo['usersName']
									);

					$this->arrUsersGmailSettings[$rowGetInfo['userId']] = $arrTmp;
				}
				$this->currentPage++;
				return $this->getUsersGmailSettings();
			}
			else {
				return true;
			}
		}
	}


	function getUsersSmtpSettings () {
		if(DEBUG) {
			print "getUsersSmtpSettings ()";
		}


		$qrySel = "	SELECT *
					FROM user_smtp ";
		
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
			$totalSMTP = $objDBResult->getNumRows();

			if($objDBResult->getNumRows() > 0) {
				while($rowGetInfo = $objDBResult->fetchAssoc()) {

					$arrTmp = array(
									'payload' 	=>	array(
															'host'			=>	$rowGetInfo['host'],
															'username'		=>	$rowGetInfo['username'],
															'password'		=>	$rowGetInfo['password'],
															'encryption'	=>	$rowGetInfo['encryption'],
															'port'			=>	$rowGetInfo['port']
														),
									'tested' 	=>	$rowGetInfo['tested'],
									'last_test_date'	=>	$rowGetInfo['last_test_date'],
									'error'		=>	$rowGetInfo['error'],
									'full_name'	=>	$rowGetInfo['full_name']
								);

					$this->arrUserSMTPSettings[$rowGetInfo['user_id']] = $arrTmp;
				}
				$this->currentPage++;
				return $this->getUsersSmtpSettings();
			}
			else {
				return true;
			}
		}
	}

}