<?php

class COMMONDBFUNC {
	
	protected $resourceId;
	protected $objDbConn;

	protected $strExcepetionDelimiter = '#$%#$%#$%';

	function COMMONDBFUNC ($host = HOST_NEW, $password = PASSWORD_NEW, $userName = USERNAME_NEW, $dbName = DBNAME_NEW) {
		
		if(DEBUG) {

			print "host :: " . $host . "\n";
			print "password :: " . $password . "\n";
			print "userName :: " . $userName . "\n";
			print "dbName :: " . $dbName . "\n";
		}

		$this->resourceId = false;
		
		$this->objDbConn = mysqli_connect($host, $userName, $password);
		
		if(!$this->objDbConn) {
			print "Connection Error.. :: " . mysql_error();
			die();
		}
		
		$selectedDb = mysqli_select_db($this->objDbConn, $dbName);

		if(!$selectedDb) {
			print "Cant select database :: " . $dbName;
			die();
		}
	}

	// This function is used to execute simple mysql queris.
	function executeQuery($query = NULL) {
		if(empty($query)) {
			return false;
		}


		try {
			$this->resourceId = @mysqli_query($this->objDbConn, $query);

			if(!$this->resourceId) {

				print("<pre>query :: ");
				print_r($query);
				print("</pre>");
				
				print("<pre>");
				print_r( mysqli_error($this->objDbConn));
				print("</pre>");

				print("<pre>Effected Rows :: ");
				print_r(mysqli_affected_rows($this->objDbConn));
				print("</pre>");
				
				$strQryError = mysqli_error($this->objDbConn);
				$strAffectedRows = mysqli_affected_rows($this->objDbConn);

				$strException = $query . $this->strExcepetionDelimiter . $strQryError . $this->strExcepetionDelimiter . $strAffectedRows;

				throw new Exception($strException, 1);
			}
		}
		catch(Exception $e) {

 			$arrException = explode($this->strExcepetionDelimiter, $e->getMessage());
 			
 			$arrParamsForQueryError = array();
			$arrParamsForQueryError['query'] = $arrException[0];
			$arrParamsForQueryError['strQryError'] = $arrException[1];
			$arrParamsForQueryError['strAffectedRows'] = $arrException[2];

			$this->insertQueryLog($arrParamsForQueryError);
 		}

 		return  $this;
	}
	
	// This function is used to fetch mysql result as associative array.
	function fetchAssoc() {
		
		if(empty($this->resourceId)) {
			return false;
		}

		return @mysqli_fetch_assoc($this->resourceId);
	}
		
	// This function is used to fetch from database and store it in an array.
	function fetchArray() {
		
		if(empty($this->resourceId)) {
			return false;
		}

		return @mysqli_fetch_array($this->resourceId);
	}
	
	// This function is used to fetch single row information from database.
	function fetchRow() {
		
		if(empty($this->resourceId)) {
			return false;
		}

		return @mysqli_fetch_row($this->resourceId);
	}
	
	// This function is used to get total number of rows returned in last resultset.
	function getNumRows() {
		if(empty($this->resourceId)) {
			return false;
		}

		return @mysqli_num_rows($this->resourceId);
	}

	function insertAndGetId($qry){
 		

 		try {

 			if(empty($qry)) {
				return false;
			}

			$resource = false;
			$resource = @mysqli_query($this->objDbConn, $qry);
			
			if(!$resource) {

				print("<pre>qry :: ");
				print_r($qry);
				print("</pre>");
				
				print("<pre>");
				print_r( mysqli_error($this->objDbConn));
				print("</pre>");
	 			
	 			$strMysqliError = mysqli_error($this->objDbConn);
	 			
	 			if(!isset($GLOBALS['MYSQLLOGFILEPATH'])) {
	 				$GLOBALS['MYSQLLOGFILEPATH'] =  __DIR__ . '/../logs/mysql_' . date('YMDHis') . '.log';
	 			}

				error_log(date('Y-M-D H:i:s') . "   |   " . $qry . "\n", 3, $GLOBALS['MYSQLLOGFILEPATH']);
				error_log(date('Y-M-D H:i:s') . "   |   " . $strMysqliError . "\n", 3, $GLOBALS['MYSQLLOGFILEPATH']);


				print("<pre>Effected Rows :: ");
				print_r(mysqli_affected_rows($this->objDbConn));
				print("</pre>");

				$strAffectedRows = mysqli_affected_rows($this->objDbConn);
 				

 				$strException = $qry . $this->strExcepetionDelimiter . mysqli_error($this->objDbConn) . $this->strExcepetionDelimiter . $strAffectedRows;


				throw new Exception($strException, 1);
				
			}

			return @mysqli_insert_id($this->objDbConn);
 		}
 		catch(Exception $e) {

 			$arrException = explode($this->strExcepetionDelimiter, $e->getMessage());

 			$arrParamsForQueryError = array();
			$arrParamsForQueryError['query'] = $arrException[0];
			$arrParamsForQueryError['strQryError'] = $arrException[1];
			$arrParamsForQueryError['strAffectedRows'] = $arrException[2];

			$this->insertQueryLog($arrParamsForQueryError);
 		}
	}


	function insertQueryLog($arrParams = array()) {

		$query = '';
		$strQryError = '';
		$strAffectedRows = 0;


		if(isset($arrParams['query']) && !empty($arrParams['query'])) {
			$query = $arrParams['query'];
		}

		if(isset($arrParams['strQryError']) && !empty($arrParams['strQryError'])) {
			$strQryError = $arrParams['strQryError'];
		}

		if(isset($arrParams['strAffectedRows']) && !empty($arrParams['strAffectedRows'])) {
			$strAffectedRows = $arrParams['strAffectedRows'];
		}

		if(!empty($query)) {
			
			$qryIns = "	INSERT INTO query_error_log (query_string, error, effected_rows, created)
						VALUES ('" . addslashes($query) . "',
								'" . addslashes($strQryError) . "',
								'" . addslashes($strAffectedRows) . "',
								'" . date('Y-m-d H:i:s') . "')";

 			$this->insertAndGetId($qryIns);
		}

	}
}
?>
