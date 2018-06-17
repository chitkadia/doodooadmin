<?php


CLASS FOREIGNKEYCHECK EXTENDS GENERALFUNCTIONS {

	protected $objLogger;

	function __construct () {
		if(DEBUG) {
			print "__construct ()";
		}

		$this->objDBConNew = new COMMONDBFUNC();

		$this->objDBConOld = new COMMONDBFUNC(HOST_OLD, PASSWORD_OLD, USERNAME_OLD, DBNAME_OLD);
	}


	function removeForeignKeyChecks() {
		$qrySel = "	SET FOREIGN_KEY_CHECKS = 0 ";
print "qrySel :: " . $qrySel . "\n";		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
print "QUERY EXECUTED SUCCESSFULLY...";
		if(!$objDBResult) {
			print "Error occur.";
		}
		return;
	}



	function addForeignKeyChecks() {
		$qrySel = "	SET FOREIGN_KEY_CHECKS = 1 ";
print "qrySel :: " . $qrySel . "\n";		
		if(DEBUG) {
			print nl2br($qrySel);
		}
print "QUERY EXECUTED SUCCESSFULLY...";
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
		}
		return;
	}
}

?>