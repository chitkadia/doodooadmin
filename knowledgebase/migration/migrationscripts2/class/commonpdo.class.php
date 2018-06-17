<?php



CLASS COMMONPDO {

	protected $PDOConnection = null;

	function COMMONPDO ($host = HOST_NEW, $password = USERNAME_NEW, $userName = PASSWORD_NEW, $dbName = DBNAME_NEW) {
		if(DEBUG) {
			print "COMMONPDO ({$host}, {$password}, {$password}, {$dbName})";
		}

		if(DEBUG) {
			print "<pre>host :: ";
			print_r($host);
			print "</pre>";
 			
 			print "<pre>userName :: ";
 			print_r($userName);
 			print "</pre>";

			print "<pre>password :: ";
			print_r($password);
			print "</pre>";

			print "<pre>dbName :: ";
			print_r($dbName);
			print "</pre>";
		}

		$dns = "mysql:host=" . $host . ";dbname=" . $dbName . ";charset=utf8";

		$arrDbCnfigOptions = array(
									PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
		 						);

		$this->PDOConnection = new PDO($dns, $userName, $password, $arrDbCnfigOptions);
	}

	function getConnectionInstance() {
		return $this->PDOConnection;
	}

}


?>