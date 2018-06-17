<?php


CLASS ACCOUNTS EXTENDS GENERALFUNCTIONS {
 	
	protected $objLogger;

	function ACCOUNTS ($objLogger) {
		if(DEBUG) {
			print "ACCOUNTS ()";
		}

		$this->objDBConNew = new COMMONDBFUNC();
		$this->objDBConOld = new COMMONDBFUNC(HOST_OLD, PASSWORD_OLD, USERNAME_OLD, DBNAME_OLD);

		$this->objLogger = $objLogger;
	}


	function createNewAccountForUser ($arrParams = array()) {
		if(DEBUG) {
			print "createNewAccountForUser ()";
		}
 		
 		$intReturn = 0;

		if(empty($arrParams)) {
			return $intReturn;
		}

		$accountNumber = $this->generateAccountNumber();

		$createdDate = $arrParams['created_at'];
		$convertedCreatedDate = self::convertDateTimeIntoTimeStamp($createdDate);
		$arrConfiguration = array();
		$strJsonConfig = json_encode($arrConfiguration);
 		
		$accountMasterId = 0;
		if(isset($arrParams['accountMasterId']) && is_numeric($arrParams['accountMasterId']) && $arrParams['accountMasterId'] > 0) {
			$accountMasterId = $arrParams['accountMasterId'];
		}

 		$this->objLogger->setMessage("Inserting record in account_master");


 		$strInsParams = "ac_number, source_id, configuration, status, created, modified";
 		$strInsertValue = " ('" . addslashes($accountNumber) . "',
							'1',
							'" . addslashes($strJsonConfig) . "',
						 	'1',
						 	'" . $convertedCreatedDate . "',
						 	'" . $convertedCreatedDate . "')";

 		
 		if($accountMasterId > 0) {
 			$strInsParams = "id, ac_number, source_id, configuration, status, created, modified";
 			$strInsertValue = " (	'" . addslashes($accountMasterId) . "',
 									'" . addslashes($accountNumber) . "',
									'1',
									'" . addslashes($strJsonConfig) . "',
								 	'1',
								 	'" . $convertedCreatedDate . "',
							 		'" . $convertedCreatedDate . "')";
 		}


		$qryIns = "	INSERT INTO account_master (" . $strInsParams . ")
					VALUES " . $strInsertValue;
				
		if(DEBUG) {
			print nl2br($qryIns);
		}
		
		$accountMasterId = $this->objDBConNew->insertAndGetId($qryIns);
		
		if(!$accountMasterId) {
			print "Cant insert into 'account_master', error occured.";
			$this->objLogger->setMessage("Error in creating account_master record");
		}
		else {
			$this->objLogger->setMessage("Account master record created with id : " . $accountMasterId);
			$intReturn = $accountMasterId;
		}
		return $intReturn;
	}



}

?>