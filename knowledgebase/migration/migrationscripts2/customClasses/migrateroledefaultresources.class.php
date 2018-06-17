<?php


CLASS MIGRATEROLEDEFAULTRESOURCE EXTENDS GENERALFUNCTIONS {
 	
	protected $objLogger;

	function __construct() {

// TRUNCATE TABLE role_default_resources;
// SET FOREIGN_KEY_CHECKS = 0; 
// TRUNCATE TABLE role_master;
// SET FOREIGN_KEY_CHECKS = 0; 

// SELECT * FROM role_master;
// SELECT * FROM role_default_resources;

		$this->objDBConNew = new COMMONDBFUNC();
		$this->objDBConOld = new COMMONDBFUNC(HOST_OLD, PASSWORD_OLD, USERNAME_OLD, DBNAME_OLD);

		$this->objDBConnQaapi = new COMMONDBFUNC('tnsh-staging-rds.coniqkl3pdcw.us-west-2.rds.amazonaws.com:9350', 'tNsHDB+toor$100#', 'tnshdbroot', 'api.cultofpassion.com');
	}


	function migrate () {
		if(DEBUG) {
			print "migrate ()";
		}



		$this->migrateRoleMaster();

		$arrQaapiRoleDefaultResource = $this->getRoleDefaultResource();

		if(!empty($arrQaapiRoleDefaultResource)) {

			$strInsQry = "";
			foreach ($arrQaapiRoleDefaultResource as $key => $roleDefaultResourceInfo) {
				
				$id = $roleDefaultResourceInfo['id'];
				$role_id = $roleDefaultResourceInfo['role_id'];
				$resource_id = $roleDefaultResourceInfo['resource_id'];
				$status = $roleDefaultResourceInfo['status'];
				$modified = $roleDefaultResourceInfo['modified'];

 				$qryIns = "	INSERT INTO role_default_resources (id, role_id, resource_id, status, modified)
 							VALUES ('" . addslashes($id) . "',
 									'" . addslashes($role_id) . "',
 									'" . addslashes($resource_id) . "',
 									'" . addslashes($status) . "',
 									'" . addslashes($modified) . "')";
 						
 				if(DEBUG) {
 					print nl2br($qryIns);
 				}
 				
 				$insertedId = $this->objDBConNew->insertAndGetId($qryIns);
 				
 				if(!$insertedId) {
 					print "Cant insert into 'role_default_resources', error occured.";
 					return false;
 				}
 				else {
 				
 				}
			}

			if(!empty($strInsQry)) {

				$strInsQry = rtrim($strInsQry, ', ');

				$qryIns = "	INSERT INTO role_default_resources (role_id, resource_id, status, modified)
							VALUES " . $strInsQry;
						
				if(DEBUG) {
					print nl2br($qryIns);
				}
				
				$insertedId = $this->objDBConNew->insertAndGetId($qryIns);
				
				if(!$insertedId) {
					print "Cant insert into 'role_default_resources', error occured.";
					return false;
				}
				else {
					return true;
				}
			}
		}

	}



	function getRoleDefaultResource () {
		if(DEBUG) {
			print "getRoleDefaultResource ()";
		}

		$arrReturn = array();

		$qrySel = "	SELECT *
					FROM role_default_resources
					WHERE 1";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConnQaapi->executeQuery($qrySel);
		
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


	function migrateRoleMaster () {
		if(DEBUG) {
			print "migrateRoleMaster ()";
		}

		$arrRoleMaster = $this->getRoleMaster();

		if(!empty($arrRoleMaster)) {

			$strQryIns = "";

			foreach ($arrRoleMaster as $key => $arrRoleMasterInfo) {

				$id 			= $arrRoleMasterInfo['id'];
				$account_id 	= $arrRoleMasterInfo['account_id'];
				$user_id 		= $arrRoleMasterInfo['user_id'];
				$source_id 		= $arrRoleMasterInfo['source_id'];
				$code 			= $arrRoleMasterInfo['code'];
				$name 			= $arrRoleMasterInfo['name'];
				$short_info 	= $arrRoleMasterInfo['short_info'];
				$is_system 		= $arrRoleMasterInfo['is_system'];
				$for_customers 	= $arrRoleMasterInfo['for_customers'];
				$status 		= $arrRoleMasterInfo['status'];
				$created 		= $arrRoleMasterInfo['created'];
				$modified 		= $arrRoleMasterInfo['modified'];

 				if(!is_numeric($account_id)) {
 					$account_id = 1;
 				}

 				if(!is_numeric($user_id)) {
 					$user_id = 15;
 				}

 				if(!is_numeric($source_id)) {
 					$source_id = 1;
 				}

				$strQryIns .= "	(
									'" . addslashes($id) . "',
									'" . addslashes($account_id) . "',
									'" . addslashes($user_id) . "',
									'" . addslashes($source_id) . "',
									'" . addslashes($code) . "',
									'" . addslashes($name) . "',
									'" . addslashes($short_info) . "',
									'" . addslashes($is_system) . "',
									'" . addslashes($for_customers) . "',
									'" . addslashes($status) . "',
									'" . addslashes($created) . "',
									'" . addslashes($modified) . "'
								), ";
			}

			if(!empty($strQryIns)) {
				$strQryIns = rtrim($strQryIns, ', ');

				$qryIns = "	INSERT INTO role_master (id, account_id, user_id, source_id, code, name, short_info, is_system, for_customers, status, created, modified)
							VALUES " . $strQryIns;
						
				if(DEBUG) {
					print nl2br($qryIns);
				}
				
				$insertedId = $this->objDBConNew->insertAndGetId($qryIns);
				
				if(!$insertedId) {
					print "Cant insert into 'role_master', error occured.";
					return false;
				}
				else {
					return true;
				}
			}
		}
	}


	function getRoleMaster () {
		if(DEBUG) {
			print "getRoleMaster ()";
		}
		$arrReturn = array();

		$qrySel = "	SELECT *
					FROM role_master
					WHERE 1";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConnQaapi->executeQuery($qrySel);
		
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



}

?>