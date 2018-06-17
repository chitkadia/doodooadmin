<?php


CLASS STRIPEDATA EXTENDS GENERALFUNCTIONS {

	protected $objLogger;

	function STRIPEDATA ($objLogger) {
		if(DEBUG) {
			print "STRIPEDATA ()";
		}

		$this->objDBConNew = new COMMONDBFUNC();
		$this->objDBConOld = new COMMONDBFUNC(HOST_OLD, PASSWORD_OLD, USERNAME_OLD, DBNAME_OLD);

		$this->objLogger = $objLogger;

		$this->LastCustomerId = "";

		$this->countCustomer = 0;
		$this->customerCounter = 0;


		$this->arrStripeCustomerData = array();
		
		$this->totalCustomerCount = 0;


		\Stripe\Stripe::setApiKey(STRIPE_KEY);
    }


    function getStripeData() {

 		$this->getchargeinfo();
    	// $this->getStripeSubscriptionsInvoices();

    	// die('invoices');

    	$this->getStripeCustomerList();
 		
 		if(!empty($this->arrStripeCustomerData)) {

 			/**
 				LOG FILE
 			*/
 			$this->filehandle = fopen('customer_1.txt', 'w');
	    	fwrite($this->filehandle, json_encode($this->arrStripeCustomerData));
			fclose($this->filehandle);

 			foreach ($this->arrStripeCustomerData as $key => $arrEachStripeCusomterInfo) {

 				$id = $arrEachStripeCusomterInfo['id'];
 				$account_balance = $arrEachStripeCusomterInfo['account_balance'];
 				$created = $arrEachStripeCusomterInfo['created'];
 				$currency = $arrEachStripeCusomterInfo['currency'];
 				$default_source = $arrEachStripeCusomterInfo['default_source'];
 				$description = $arrEachStripeCusomterInfo['description'];
 				$discount = $arrEachStripeCusomterInfo['discount'];
 				$email = $arrEachStripeCusomterInfo['email'];
 				$metadata = $arrEachStripeCusomterInfo['metadata']; // array,

				$userId = 0;
				if(isset($metadata['user_id'])) {
					$userId = $metadata['user_id'];
				}
 				
 				$orderId = 0;
 				if(isset($metadata['order_id'])) {
 					$orderId = $metadata['order_id'];
 				}

 				$sources = $arrEachStripeCusomterInfo['sources'];// array, directly store in JSON format
 				$strSources = json_encode($sources);
 				$subscriptions = $arrEachStripeCusomterInfo['subscriptions'];// subscriptions, need to perform operation on this.
 				

 				$qryIns = "	INSERT INTO STRIPE_customer(customer_id, user_id, order_id, account_balance, currency, default_source, description, discount, email, metadata, source, created)
 							VALUES ('" . addslashes($id) . "',
 									'" . addslashes($userId) . "',
 									'" . addslashes($orderId) . "',
 									'" . addslashes($account_balance) . "',
 									'" . addslashes($currency) . "',
 									'" . addslashes($default_source) . "',
 									'" . addslashes($description) . "',
 									'" . addslashes($discount) . "',
 									'" . addslashes($email) . "',
 									'" . addslashes(json_encode($metadata)) . "',
 									'" . addslashes($strSources) . "',
 									'" . addslashes($created) . "'
 								 )";
 						
 				if(DEBUG) {
 					print nl2br($qryIns);
 				}
 				
 				$stripeCustomerId = $this->objDBConOld->insertAndGetId($qryIns);
 				
 				if(!$stripeCustomerId) {
 					print "Cant insert into 'STRIPE_customer', error occured.";
 					return false;
 				}
 				else {
print "stripeCustomerId :: " . $stripeCustomerId . "\n";
 					if(!empty($subscriptions['data'])) {
 						foreach ($subscriptions['data'] as $key => $arrStripeCustomerSubscriptionInfo) {
 							
 							$subscription_id = $arrStripeCustomerSubscriptionInfo['id'];
 							$billing = $arrStripeCustomerSubscriptionInfo['billing'];
 							$cancel_at_period_end = $arrStripeCustomerSubscriptionInfo['cancel_at_period_end'];
 							$canceled_at = $arrStripeCustomerSubscriptionInfo['canceled_at'];
 							$created = $arrStripeCustomerSubscriptionInfo['created'];
 							$current_period_end = $arrStripeCustomerSubscriptionInfo['current_period_end'];
 							$current_period_start = $arrStripeCustomerSubscriptionInfo['current_period_start'];
 							$customer = $arrStripeCustomerSubscriptionInfo['customer'];
 							$discount = $arrStripeCustomerSubscriptionInfo['discount'];
 							$ended_at = $arrStripeCustomerSubscriptionInfo['ended_at'];
 							$planId = $arrStripeCustomerSubscriptionInfo['items']['data'][0]['plan']['id'];
 							$planAmount = $arrStripeCustomerSubscriptionInfo['items']['data'][0]['plan']['amount'];
 							$planCreated = $arrStripeCustomerSubscriptionInfo['items']['data'][0]['plan']['created'];
 							$planCurrency = $arrStripeCustomerSubscriptionInfo['items']['data'][0]['plan']['currency'];
 							$planInterval = $arrStripeCustomerSubscriptionInfo['items']['data'][0]['plan']['interval'];
 							$planIntervalCount = $arrStripeCustomerSubscriptionInfo['items']['data'][0]['plan']['interval_count'];
 							$planname = $arrStripeCustomerSubscriptionInfo['items']['data'][0]['plan']['name'];
 							$planStatementDescriptior = $arrStripeCustomerSubscriptionInfo['items']['data'][0]['plan']['statement_descriptor'];

 							if(!is_numeric($cancel_at_period_end)) {
 								$cancel_at_period_end = 0;
 							}
 							if(!is_numeric($canceled_at)) {
 								$canceled_at = 0;
 							}
 							if(!is_numeric($created)) {
 								$created = 0;
 							}
 							if(!is_numeric($current_period_end)) {
 								$current_period_end = 0;
 							}
 							if(!is_numeric($current_period_start)) {
 								$current_period_start = 0;
 							}
 							if(!is_numeric($ended_at)) {
 								$ended_at = 0;
 							}
 							if(!is_numeric($planCreated)) {
 								$planCreated = 0;
 							}
 							if(!is_numeric($planInterval)) {
 								$planInterval = 0;
 							}
 							if(!is_numeric($planIntervalCount)) {
 								$planIntervalCount = 0;
 							}


 							$qryIns = "	INSERT INTO STRIPE_subscription (stripe_customer_id, subscription_id, billing, cancel_at_period_end, canceled_at, created, current_period_end, current_period_start, customer, discount, ended_at, plan_id, plan_amount, plan_created, plan_currency, plan_interval, plan_interval_count, pan_name, plan_statement_descriptor)
 										VALUES ('" . addslashes($stripeCustomerId) . "',
 												'" . addslashes($subscription_id) . "',
 												'" . addslashes($billing) . "',
 												'" . addslashes($cancel_at_period_end) . "',
 												'" . addslashes($canceled_at) . "',
 												'" . addslashes($created) . "',
 												'" . addslashes($current_period_end) . "',
 												'" . addslashes($current_period_start) . "',
 												'" . addslashes($customer) . "',
 												'" . addslashes($discount) . "',
 												'" . addslashes($ended_at) . "',
 												'" . addslashes($planId) . "',
 												'" . addslashes($planAmount) . "',
 												'" . addslashes($planCreated) . "',
 												'" . addslashes($planCurrency) . "',
 												'" . addslashes($planInterval) . "',
 												'" . addslashes($planIntervalCount) . "',
 												'" . addslashes($planname) . "',
 												'" . addslashes($planStatementDescriptior) . "'
 												)";
 									
 							if(DEBUG) {
 								print nl2br($qryIns);
 							}
 							
 							$stripeSubscriptionId = $this->objDBConOld->insertAndGetId($qryIns);
 							
 							if(!$stripeSubscriptionId) {
 								print "Cant insert into 'STRIPE_subscription', error occured.";
 								return false;
 							}
 							else {
 							
 							}
 						}
 					}

 				}
 			}
 		}

		$this->getStripeSubscriptionsInvoices();
		
		print "STRIPE CUSTOMER FETCHED";
    }


    function getStripeSubscriptionsInvoices () {
    	if(DEBUG) {
    		print "getStripeSubscriptionsInvoices ()";
    	}

 		$arrReturn = array();

    	$qrySel = "	SELECT *
    				FROM STRIPE_subscription ";
    	
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
 		
 		
 		$arrMainTmp = array();

    	if(!empty($arrReturn)) {
    		foreach ($arrReturn as $key => $arrEachSubscriptionInfo) {

				$subscriptionId = $arrEachSubscriptionInfo['subscription_id'];
print "subscriptionId :: " . $subscriptionId . "\n";
				$arrStripeInvoiceFetchParams = array(
														'subscription' 	=>	$subscriptionId
				 									);

				$arrStripeInvoicesList = \Stripe\Invoice::all($arrStripeInvoiceFetchParams);
				$arrStripeInvoicesList = json_decode(json_encode($arrStripeInvoicesList), true);
				array_push($arrMainTmp, $arrStripeInvoicesList);

    		}
    	}

    	$invoiceFile = fopen('invoices.txt', 'w');
    	fwrite($invoiceFile, json_encode($arrMainTmp));
		fclose($invoiceFile);
    }


    function getStripeCustomerList () {
    	if(DEBUG) {
    		print "getStripeCustomerList ()";
    	}

		$stripeConfig = array(
			'limit'   =>  100
		);

		if(!empty($this->LastCustomerId)) {
			$stripeConfig['starting_after'] = $this->LastCustomerId;
		}


		$srObjStripeCustomerList = \Stripe\Customer::all($stripeConfig);

		$arrStripeCustomer = json_decode(json_encode($srObjStripeCustomerList), true);

		if(!empty($arrStripeCustomer['data'])) {
			foreach ($arrStripeCustomer['data'] as $key => $arrEachCustomerInfo) {

				array_push($this->arrStripeCustomerData, $arrEachCustomerInfo);

				$this->LastCustomerId = $arrEachCustomerInfo['id'];

			}

print "this->LastCustomerId :: " . $this->LastCustomerId . "\n";

 			if(true || $this->customerCounter++ < $this->countCustomer) {
 				sleep(1);
 				return $this->getStripeCustomerList();
 			}
 			else {
 				return true;
 			}
		}
		else {
			return true;
		}


    }


    function getchargeinfo () {
    	if(DEBUG) {
    		print "getchargeinfo ()";
    	}

    	$arrChargeata = \Stripe\Charge::retrieve("ch_1B993KB9sp0sypR2Rt60wpov");

    	$arrChargeata = json_decode(json_encode($arrChargeata), true);

    	print "<pre>arrChargeata :: ";
    	print_r($arrChargeata);
    	print "</pre>";
    	die('arrChargeata');


    }
}

