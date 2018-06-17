<?php


CLASS ACCOUNTORGANIZATION EXTENDS GENERALFUNCTIONS {

	protected $objLogger;

	function ACCOUNTORGANIZATION ($objLogger) {
		if(DEBUG) {
			print "ACCOUNTORGANIZATION ()";
		}

		$this->objDBConNew = new COMMONDBFUNC();
		$this->objDBConOld = new COMMONDBFUNC(HOST_OLD, PASSWORD_OLD, USERNAME_OLD, DBNAME_OLD);

		$this->objLogger = $objLogger;
	}


	function createAccountOrganization ($arrParams = array()) {
		if(DEBUG) {
			print "createAccountOrganization ()";
		}

		$intReturn = 0;

		if(empty($arrParams)) {
			return $intReturn;
		}

		$createdDate = $arrParams['createdAt'];
		$convertedCreatedDate = self::convertDateTimeIntoTimeStamp($createdDate);
		$accountId = $arrParams['accountId'];
		$companyName = $arrParams['companyName'];
		$logo = $arrParams['logo'];
		$accountId = $arrParams['accountId'];


 		$this->objLogger->setMessage("Inserting record in account_organization");

		$qryIns = "	INSERT INTO account_organization (account_id, name, address, city, state, country, zipcode, logo, website, contact_phone, contact_fax, short_info, modified)
					VALUES ('" . addslashes($accountId) . "',
							'" . addslashes($companyName) . "',
							'',
							'',
							'',
							'',
							'',
							'" . addslashes($logo) . "',
							'',
							'',
							'',
							'',
						 	'" . $convertedCreatedDate . "')";

		if(DEBUG) {
			print nl2br($qryIns);
		}
		
		$accountOrganizationId = $this->objDBConNew->insertAndGetId($qryIns);
		
		if(!$accountOrganizationId) {
			print "Cant insert into 'account_organization', error occured.";
			$this->objLogger->setMessage("Error in creating account_organization record");
		}
		else {
			$this->objLogger->setMessage("Account master record created with id : " . $accountOrganizationId);
			$intReturn = $accountOrganizationId;
		}
		return $intReturn;
	}
	


}


?>