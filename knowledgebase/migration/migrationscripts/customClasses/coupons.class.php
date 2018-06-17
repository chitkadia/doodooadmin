<?php


CLASS COUPONS EXTENDS GENERALFUNCTIONS {

	protected $objLogger;

	function COUPONS ($objLogger) {
		if(DEBUG) {
			print "COUPONS ()";
		}

		$this->objDBConNew = new COMMONDBFUNC();
		$this->objDBConOld = new COMMONDBFUNC(HOST_OLD, PASSWORD_OLD, USERNAME_OLD, DBNAME_OLD);

		$this->objLogger = $objLogger;

		// $this->getAndSetAccountNumberInformationForUsers();
	}

	function __destruct() {
		// $this->unsetUsersAccountMappingArray();
	}


	function migrateCoupon () {
		if(DEBUG) {
			print "migrateCoupon ()";
		}


		$arrOldCouponDetails = $this->getOldCouponDetails();

		$this->objLogger->setMessage("Fetched old coupon data");

		if(!empty($arrOldCouponDetails)) {

			$this->objLogger->setMessage("Old coupon data found. Processing counpon information.");

			$strInsQry = "";

			foreach ($arrOldCouponDetails as $key => $arrCouponInfo) {

				$couponId 		= $arrCouponInfo['id'];
				$couponCode 	= $arrCouponInfo['coupon_code'];
				$dicountType 	= $arrCouponInfo['discount_type'];
				$discount 		= $arrCouponInfo['discount'];
				$validFrom 		= $arrCouponInfo['valid_from'];
				$validTo 		= $arrCouponInfo['valid_to'];
				$minAmount 		= $arrCouponInfo['order_min_amount'];
				$currency 		= $arrCouponInfo['currency'];
				$status 		= $arrCouponInfo['status'];
				$created 		= $arrCouponInfo['created'];
				$modified 		= $arrCouponInfo['modified'];

				$this->objLogger->setMessage("Coupon found with code " . $couponCode);

				$convertedCreatedDate = $this->convertDateTimeIntoTimeStamp($created);
				$convertedModifiedDate = $this->convertDateTimeIntoTimeStamp($modified);
				$convertedValidFromDate = $this->convertDateTimeIntoTimeStamp($validFrom);
				$convertedValidToDate = $this->convertDateTimeIntoTimeStamp($validTo);

			 	
			 	$strInsQry .= " (
			 						'" . addslashes($couponCode) . "',
			 						'" . addslashes($convertedValidFromDate) . "',
			 						'" . addslashes($convertedValidToDate) . "',
			 						'" . addslashes($minAmount) . "',
			 						'" . addslashes($minAmount) . "',
			 						'" . addslashes($dicountType) . "',
			 						'" . addslashes($discount) . "',
			 						'" . addslashes($currency) . "',
			 						'Coupon Description',
			 						'" . addslashes($status) . "',
			 						'" . addslashes($convertedCreatedDate) . "',
			 						'" . addslashes($convertedModifiedDate) . "'
			 					), ";
			}

			if(!empty($strInsQry)) {

				$strInsQry = rtrim($strInsQry, ', ');

				$qrySel = "	INSERT INTO coupon_master (code, valid_from, valid_to, min_amount, max_amount, discount_type, discount_value, currency, short_info, status, created, modified) 
				 			VALUES " . $strInsQry;

				if(DEBUG) {
					print nl2br($qrySel);
				}
				
				$objDBResult = $this->objDBConNew->executeQuery($qrySel);
				
				if(!$objDBResult) {
					print "Error occur.";
					$this->objLogger->setMessage("Error occured while inserting into coupon table.");
					return false;
				}
				else {
					$this->objLogger->setMessage("Successfully migrated coupons.");
					return true;
				}
			}
		}
		else {
			$this->objLogger->setMessage("No old coupon data found.");
		}
		$this->objLogger->addLog();
	}


	function getOldCouponDetails () {
		if(DEBUG) {
			print "getOldCouponDetails ()";
		}

		$arrReturn = array();

		$qrySel = "	SELECT *
					FROM coupons_detail cd ";
		
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
	


}


?>