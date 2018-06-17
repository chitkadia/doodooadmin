<?php


CLASS ERRORLOGS {
 	
 	// constructor
 	function ERRORLOGS() {
 		$this->message = [];
 		$this->logFilePath = __DIR__ . '/../logs/errors.log';

 		$this->stopLog = false;
 	}
 	
 	// set log message
 	function setMessage ($message = NULL) {
 		if(DEBUG) {
 			print "setMessage ()";
 		}

 		if($this->stopLog) {
 			return false;
 		}

 		if(!empty($message)) {
 			$arrTmp = array(
 								'timeStamp' 	=>	date('Y-m-d H:i:s'),
 								'message' 		=>	$message
 			 				);
 			array_push($this->message, $arrTmp);

 			print $arrTmp['timeStamp'] . "   |   " . $arrTmp['message'] . "\n";
 		}
 	}
 	
 	// clearing the messages for the logs.
 	function clearMessages () {
 		if(DEBUG) {
 			print "clearMessages ()";
 		}

 		if($this->stopLog) {
 			return false;
 		}

 		$this->message = [];
 	}

 	// set log message file path
 	function setLogFilePath ($optionSelected = NULL, $filePrefix = "") {
 		if(DEBUG) {
 			print "setLogFilePath ()";
 		}

 		if($this->stopLog) {
 			return false;
 		}

 		$arrLogFileNames = COMMONVARS::arrLogFileNames();

 		$fileBaseName = 'default';
 		if(isset($arrLogFileNames[$optionSelected])) {
 			$fileBaseName = $arrLogFileNames[$optionSelected];
 		}

 		$fileBaseName .= $filePrefix . '.log';
 		
 		$this->logFilePath = __DIR__ . '/../logs/' . $fileBaseName;
 	}


 	// function call to add logs into the log file.
	public function addLog () {
		if(DEBUG) {
			print "addLog ()";
		}

		if($this->stopLog) {
 			return false;
 		}

 		if(!empty($this->message)) {
 			foreach ($this->message as $key => $arrMessageInfo) {
				error_log($arrMessageInfo['timeStamp'] . "   |   " . $arrMessageInfo['message'] . "\n", 3, $this->logFilePath);
			}
 		}

 		$this->clearMessages();
	}
}

?>