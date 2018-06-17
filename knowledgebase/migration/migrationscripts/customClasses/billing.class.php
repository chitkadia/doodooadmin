<?php


CLASS BILLING EXTENDS GENERALFUNCTIONS {

	protected $objLogger;

	function BILLING ($objLogger) {
		if(DEBUG) {
			print "BILLING ()";
		}

		$this->objDBConNew = new COMMONDBFUNC();
		$this->objDBConOld = new COMMONDBFUNC(HOST_OLD, PASSWORD_OLD, USERNAME_OLD, DBNAME_OLD);

		$this->objLogger = $objLogger;

		$this->arrPricingPlanMapping = COMMONVARS::arrPricingPlanMapping();

		$this->arrPlanMappingWithInfo = $this->getPlanMappingWithInfo();

		$this->arrAccountAndSubscriptionMap = array();

		$this->arrStripeCustomerData = array();
		$this->LastCustomerId = "";

	}




	function getStripeCustomerList () {
    	if(DEBUG) {
    		print "getStripeCustomerList () \n";
    	}

    	$this->objLogger->setMessage('Fetching stripe customer from strip.');

		$stripeConfig = array(
			'limit'   =>  100
		);

		if(!empty($this->LastCustomerId)) {
			$stripeConfig['starting_after'] = $this->LastCustomerId;
		}

 		\Stripe\Stripe::setApiKey(STRIPE_KEY);
		$srObjStripeCustomerList = \Stripe\Customer::all($stripeConfig);

		$arrStripeCustomer = json_decode(json_encode($srObjStripeCustomerList), true);

		if(!empty($arrStripeCustomer['data'])) {
			foreach ($arrStripeCustomer['data'] as $key => $arrEachCustomerInfo) {
				array_push($this->arrStripeCustomerData, $arrEachCustomerInfo);
				$this->LastCustomerId = $arrEachCustomerInfo['id'];
			}

 			sleep(1);
 			return $this->getStripeCustomerList();
 			// return true;
		}
		else {
			return true;
		}
    }



    function migrateBillingInformation () {
    	if(DEBUG) {
    		print "migrateBillingInformation ()";
    	}
 		
    	$this->getStripeCustomerList();
 		
 		$this->objLogger->setMessage('Total number of customers fetched from the strip server : ' . COUNT($this->arrStripeCustomerData));

 		$this->objLogger->addLog();

    	if(!empty($this->arrStripeCustomerData)) {
    		foreach ($this->arrStripeCustomerData as $key => $arrEachStripeCustomerInfo) {

    			$stripeCustomerInfo_id = $arrEachStripeCustomerInfo['id'];
 				$stripeCustomerInfo_account_balance = $arrEachStripeCustomerInfo['account_balance'];
 				$stripeCustomerInfo_created = $arrEachStripeCustomerInfo['created'];
 				$stripeCustomerInfo_currency = $arrEachStripeCustomerInfo['currency'];
 				$stripeCustomerInfo_default_source = $arrEachStripeCustomerInfo['default_source'];
 				$stripeCustomerInfo_description = $arrEachStripeCustomerInfo['description'];
 				$stripeCustomerInfo_discount = $arrEachStripeCustomerInfo['discount'];
 				$stripeCustomerInfo_email = $arrEachStripeCustomerInfo['email'];
 				$stripeCustomerInfo_metadata = $arrEachStripeCustomerInfo['metadata']; // array,

				$stripeCustomerInfo_userId = 0;
				if(isset($stripeCustomerInfo_metadata['user_id'])) {
					$stripeCustomerInfo_userId = $stripeCustomerInfo_metadata['user_id'];
				}
 				
 				$stripeCustomerInfo_orderId = 0;
 				if(isset($stripeCustomerInfo_metadata['order_id'])) {
 					$stripeCustomerInfo_orderId = $stripeCustomerInfo_metadata['order_id'];
 				}

 				$stripeCustomerInfo_sources = $arrEachStripeCustomerInfo['sources'];// array, directly store in JSON format
 				$strSources = json_encode($stripeCustomerInfo_sources);
 				$stripeCustomerInfo_subscriptions = $arrEachStripeCustomerInfo['subscriptions'];// subscriptions, need to perform operation on this.

 				// if there is no current subscription for that customer, skip that here, will be taken care in free account entries.
 				if(empty($stripeCustomerInfo_subscriptions['data'])) {
 					continue;
 				}

 				$currentDateTime = self::convertDateTimeIntoTimeStamp();

 				$this->objLogger->setMessage('stripeCustomerInfo_userId :: ' . $stripeCustomerInfo_userId);


 				if(is_numeric($stripeCustomerInfo_userId) && $stripeCustomerInfo_userId > 0) {
 					$accountId = $this->getAccountNumberForUser($stripeCustomerInfo_userId);

 					$this->objLogger->setMessage('accountId :: ' . $accountId);

 					if(is_numeric($accountId) && $accountId > 0) {
 						$accountBillingMasterId = $this->getAccountBillingMasterIdForUser($stripeCustomerInfo_userId);

 						$this->objLogger->setMessage('accountBillingMasterId :: ' . $accountBillingMasterId);

 						// if account billing id not found then create one
 						if($accountBillingMasterId == 0) {
 							//creating account_billing_entry
							$qryIns = "	INSERT INTO account_billing_master(account_id, plan_id, team_size, current_subscription_id, next_subscription_updates, configuration, credit_balance, status, created, modified)
										VALUES ('" . addslashes($accountId) . "',
												NULL,
												'0',
												NULL,
												0,
												'{}',
												'0',
												'1',
												'" . addslashes($currentDateTime) . "',
												'" . addslashes($currentDateTime) . "'
											)";
									
							if(DEBUG) {
								print nl2br($qryIns);
							}
							
							$accountBillingMasterId = $this->objDBConNew->insertAndGetId($qryIns);
							
							$this->objLogger->setMessage('ACCOUNT BILLING MASTER ID GENERATED (accountBillingMasterId) : ' . $accountBillingMasterId);

							if(!$accountBillingMasterId) {
								print "Cant insert into 'account_billing_master', error occured.";
								return false;
							}
							else {

								// setting params to set add account information to the user
								$arrParamsForAccountMaster = array(
																	'USERID' 	=>	$stripeCustomerInfo_userId,
																	'KEY' 		=>	array(
																							'accountBillingMasterId' 	=> 	$accountBillingMasterId
																	 					)
								 								);
								$this->setAccountInformationForUser($arrParamsForAccountMaster);
							}
 						}


 						if(!empty($stripeCustomerInfo_subscriptions['data'])) {

 							$this->objLogger->setMessage('Customer\'s data found for this scuscription. ');

 							foreach ($stripeCustomerInfo_subscriptions['data'] as $key => $arrStripeCustomerSubscriptionInfo) {

 								
 								$subscription_id = $arrStripeCustomerSubscriptionInfo['id'];
	 							$billing = $arrStripeCustomerSubscriptionInfo['billing'];
	 							$cancel_at_period_end = $arrStripeCustomerSubscriptionInfo['cancel_at_period_end'];
	 							$canceled_at = $arrStripeCustomerSubscriptionInfo['canceled_at'];
	 							$created = $arrStripeCustomerSubscriptionInfo['created'];
	 							$current_period_end = $arrStripeCustomerSubscriptionInfo['current_period_end'];
	 							$current_period_start = $arrStripeCustomerSubscriptionInfo['current_period_start'];
	 							$customer = $arrStripeCustomerSubscriptionInfo['customer'];
	 							$subscriptionInitiatedDate = $arrStripeCustomerSubscriptionInfo['created'];
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
	 							$planQuantity = $arrStripeCustomerSubscriptionInfo['items']['data'][0]['quantity'];


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
	 							if(!is_numeric($planQuantity)) {
	 								$planQuantity = 0;
	 							}



	 							$asPerPlanPayable = (($planAmount / 100) * $planQuantity);
 								

 								// fetch ing invoices from the stripe for perticualar subscriptions.
	 							$arrStripeInvoiceFetchParams = array(
																		'subscription' 	=>	$subscription_id
								 									);
 								
 								\Stripe\Stripe::setApiKey(STRIPE_KEY);
								$arrStripeInvoicesList = \Stripe\Invoice::all($arrStripeInvoiceFetchParams);
								$arrStripeInvoicesList = json_decode(json_encode($arrStripeInvoicesList), true);
								
								if(!empty($arrStripeInvoicesList['data'])) {

 									$arrEachSubscriptionInvoiceInfo = $arrStripeInvoicesList['data'][0];
									// foreach ($arrStripeInvoicesList['data'] as $key => $arrEachSubscriptionInvoiceInfo) {

										$subscriptionInvoiceInfo_invoiceId 				= $arrEachSubscriptionInvoiceInfo['id'];
										$subscriptionInvoiceInfo_amount_due 			= $arrEachSubscriptionInvoiceInfo['amount_due'];
										$subscriptionInvoiceInfo_billing 				= $arrEachSubscriptionInvoiceInfo['billing'];
										$subscriptionInvoiceInfo_charge 				= $arrEachSubscriptionInvoiceInfo['charge'];
										$subscriptionInvoiceInfo_currency 				= $arrEachSubscriptionInvoiceInfo['currency'];
										$subscriptionInvoiceInfo_customer 				= $arrEachSubscriptionInvoiceInfo['customer'];
										$subscriptionInvoiceInfo_date 					= $arrEachSubscriptionInvoiceInfo['date'];
										$subscriptionInvoiceInfo_discount 				= $arrEachSubscriptionInvoiceInfo['discount'];
										$subscriptionInvoiceInfo_ending_balance 		= ( $arrEachSubscriptionInvoiceInfo['ending_balance'] / 100);
										$subscriptionInvoiceInfo_lines 					= $arrEachSubscriptionInvoiceInfo['lines'];
										$subscriptionInvoiceInfo_next_payment_attempt 	= $arrEachSubscriptionInvoiceInfo['next_payment_attempt'];
										$subscriptionInvoiceInfo_number 				= $arrEachSubscriptionInvoiceInfo['number'];
										$subscriptionInvoiceInfo_paid 					= $arrEachSubscriptionInvoiceInfo['paid'];
										$subscriptionInvoiceInfo_period_end 			= $arrEachSubscriptionInvoiceInfo['period_end'];
										$subscriptionInvoiceInfo_period_start 			= $arrEachSubscriptionInvoiceInfo['period_start'];
										$subscriptionInvoiceInfo_receipt_number 		= $arrEachSubscriptionInvoiceInfo['receipt_number'];
										$subscriptionInvoiceInfo_starting_balance 		= $arrEachSubscriptionInvoiceInfo['starting_balance'];
										$subscriptionInvoiceInfo_statement_descriptor 	= $arrEachSubscriptionInvoiceInfo['statement_descriptor'];
										$subscriptionInvoiceInfo_subscription 			= $arrEachSubscriptionInvoiceInfo['subscription'];
										$subscriptionInvoiceInfo_subtotal 				= ( $arrEachSubscriptionInvoiceInfo['subtotal'] / 100);
										$subscriptionInvoiceInfo_tax 					= $arrEachSubscriptionInvoiceInfo['tax'];
										$subscriptionInvoiceInfo_tax_percent 			= $arrEachSubscriptionInvoiceInfo['tax_percent'];
										$subscriptionInvoiceInfo_total 					= ( $arrEachSubscriptionInvoiceInfo['total'] / 100);
 										


										$discountCouponId = 0;
										$discountAmountOff = 0;
										$discountCreated = 0;
										$discountCurrency = 0;
										$discountDuration = 0;
										$discountDurationIdMonth = 0;
										$discountPercentageOff = 0;
										$discountRedeenBy = 0;
										$discountTimesRedeemed = 0;
										$discountCustomerId = '';
										$discountSubscriptionId = '';
										$discountStart = 0;


										$discountCouponDatabaseId = 'NULL';
										$discountType = '';
										$discountAmount = 0;
										$discountValue = 0;

 										$flagDiscountApplicable = false;
										if(!empty($subscriptionInvoiceInfo_discount)) {
											if(isset($subscriptionInvoiceInfo_discount['coupon']) && !empty($subscriptionInvoiceInfo_discount['coupon'])) {

												$arrDiscountOnInvoice = $subscriptionInvoiceInfo_discount['coupon'];

												$flagDiscountApplicable = true;

												$discountCouponId = $arrDiscountOnInvoice['id'];
												$discountAmountOff = $arrDiscountOnInvoice['amount_off'];
												$discountCreated = $arrDiscountOnInvoice['created'];
												$discountCurrency = $arrDiscountOnInvoice['currency'];
												$discountDuration = $arrDiscountOnInvoice['duration'];
												$discountDurationIdMonth = $arrDiscountOnInvoice['duration_in_months'];
												$discountPercentageOff = $arrDiscountOnInvoice['percent_off'];
												$discountRedeenBy = $arrDiscountOnInvoice['redeem_by'];
												$discountTimesRedeemed = $arrDiscountOnInvoice['times_redeemed'];
												// $discountCustomerId = $arrDiscountOnInvoice['customer'];
												// $discountSubscriptionId = $arrDiscountOnInvoice['subscription'];
												// $discountStart = $arrDiscountOnInvoice['start'];

												$discountCouponDatabaseId = $this->getDatabaseDiscountCouponId($discountCouponId);
												if($discountCouponDatabaseId == 0) {
													$discountCouponDatabaseId = 'NULL';
												}

												if(is_numeric($discountAmountOff) && $discountAmountOff > 0)  {
													$discountType = 'AMT';
													$discountAmount = $subscriptionInvoiceInfo_subtotal - $discountAmountOff;
													$discountValue = ($discountAmountOff / 100);
												}
												else if(is_numeric($discountPercentageOff) && $discountPercentageOff > 0) {
													$discountType = 'PER';
													$discountAmount = ($subscriptionInvoiceInfo_subtotal * ($discountPercentageOff / 100));
													$discountValue = $discountPercentageOff;
												}
											}
										}



 										$invoiceData_amount = 0;
										$invoiceData_currency = 0;
										$invoiceData_periodStart = 0;
										$invoiceData_periodEnd = 0;
										$invoiceData_planId = 0;
										$invoiceData_planAmount = 0;
										$invoiceData_planCreated = 0;
										$invoiceData_planCurrency = 0;
										$invoiceData_planInvertal = 0;
										$invoiceData_planInterValCount = 0;
										$invoiceData_planName = 0;
										$invoiceData_planStatementDescriptior = 0;
										$invoiceData_quantity = 0;

										if(!empty($subscriptionInvoiceInfo_lines['data'])) {
											if(isset($subscriptionInvoiceInfo_lines['data']) && !empty($subscriptionInvoiceInfo_lines['data'])) {
 												
 												$arrSubscriptionInvoiceData = $subscriptionInvoiceInfo_lines['data'][0];

												$invoiceData_amount = $arrSubscriptionInvoiceData['amount'];
												$invoiceData_currency = $arrSubscriptionInvoiceData['currency'];
												$invoiceData_periodStart = $arrSubscriptionInvoiceData['period']['start'];
												$invoiceData_periodEnd = $arrSubscriptionInvoiceData['period']['end'];
												$invoiceData_planId = $arrSubscriptionInvoiceData['plan']['id'];
												$invoiceData_planAmount = $arrSubscriptionInvoiceData['plan']['amount'];
												$invoiceData_planCreated = $arrSubscriptionInvoiceData['plan']['created'];
												$invoiceData_planCurrency = $arrSubscriptionInvoiceData['plan']['currency'];
												$invoiceData_planInvertal = $arrSubscriptionInvoiceData['plan']['interval'];
												$invoiceData_planInterValCount = $arrSubscriptionInvoiceData['plan']['interval_count'];
												$invoiceData_planName = $arrSubscriptionInvoiceData['plan']['name'];
												$invoiceData_planStatementDescriptior = $arrSubscriptionInvoiceData['plan']['statement_descriptor'];
												$invoiceData_quantity = $arrSubscriptionInvoiceData['quantity'];

											}
										}
 										

 										$arrNewPlanDetailsAccordingToOldPlan = $this->getNewPlanDetailsFromMapping($planId);
 										
 										$jsonPlanConfig = $arrNewPlanDetailsAccordingToOldPlan['configuration'];

 										$arrPlanConfiguration = json_decode($jsonPlanConfig, true);

 										$emailAccountId = $arrPlanConfiguration['ea_plan'];


 										$newPlanId = 0;
 										if(!empty($arrNewPlanDetailsAccordingToOldPlan)) {
 											$newPlanId = $arrNewPlanDetailsAccordingToOldPlan['id'];
 										}

 										if(!is_numeric($subscriptionInvoiceInfo_ending_balance)) {
 											$subscriptionInvoiceInfo_ending_balance = 0;
 										}

 										$asPerPlanPayable = (($invoiceData_planAmount / 100) * $invoiceData_quantity);

 										if(is_numeric($newPlanId) && $newPlanId > 0) {

 											$qryIns = "	INSERT INTO account_subscription_details (account_id, plan_id, team_size, email_acc_seats, currency, amount, credit_balance, coupon_id, discount_type, discount_value, discount_amount, total_amount, payment_method_id, start_date, end_date, next_subscription_id, tp_subscription_id, tp_customer_id, type, status, created, modified)
				 										VALUES ('" . addslashes($accountId) . "',
				 												'" . addslashes($newPlanId) . "',
				 												'" . addslashes($invoiceData_quantity) . "',
				 												0,
				 												'" . addslashes($invoiceData_planCurrency) . "',
				 												'" . addslashes($asPerPlanPayable) . "',
				 												'" . addslashes($stripeCustomerInfo_account_balance / 100) . "',
				 												" . addslashes($discountCouponDatabaseId) . ",
				 												'" . addslashes($discountType) . "',
				 												'" . addslashes($discountValue) . "',
				 												'" . addslashes($discountAmount) . "',
				 												'" . addslashes($subscriptionInvoiceInfo_total) . "',
				 												'2',
				 												'" . addslashes($subscriptionInvoiceInfo_period_start) . "',
				 												'" . addslashes($subscriptionInvoiceInfo_period_end) . "',
				 												NULL,
				 												'" . addslashes($subscription_id) . "',
				 												'" . addslashes($customer) . "',
				 												'1',
				 												'1',
				 												'" . addslashes($subscriptionInitiatedDate) . "',
				 												0
				 												)";
				 									
				 							if(DEBUG) {
				 								print nl2br($qryIns);
				 							}


				 							$accountSubscriptionId = $this->objDBConNew->insertAndGetId($qryIns);
				 							
				 							if(!$accountSubscriptionId) {
				 								print "Cant insert into 'account_subscription_details', error occured.";
				 								return false;
				 							}
				 							else {

				 								$qryIns = "	INSERT INTO account_subscription_line_items (user_account_plan_id, user_account_team_size, email_account_plan_id, email_account_seat, current_subscription_id, total_amount, created, modified)
				 											VALUES ('" . addslashes($newPlanId) . "',
				 													'" . addslashes($invoiceData_quantity) . "',
				 													'" . addslashes($emailAccountId) . "',
				 													0,
				 													'" . addslashes($accountSubscriptionId) . "',
				 													'" . addslashes($subscriptionInvoiceInfo_total) . "',
				 													'" . addslashes($subscriptionInitiatedDate) . "',
				 													0)";
				 										
				 								if(DEBUG) {
				 									print nl2br($qryIns);
				 								}
				 								
				 								$accountSubscriptionLineItemId = $this->objDBConNew->insertAndGetId($qryIns);
				 								
				 								if(!$accountSubscriptionLineItemId) {
				 									print "Cant insert into 'account_subscription_line_items', error occured.";
				 									return false;
				 								}


 												/**
 												 *
 												 * Below function call is to update the latest subscription into the account_billing_master table.
 												 *
 												 */
				 								$arrUpdateLatestSubscription = array(
				 																		'accountBillingMasterId' 	=>	$accountBillingMasterId,
				 																		'planId' 					=>	$newPlanId,
				 																		'currentSubscriptionId' 	=>	$accountSubscriptionId,
				 																		'teamSize' 					=>	$invoiceData_quantity,
				 																		'jsonPlanConfig'			=>	$jsonPlanConfig
				 								 									);
				 								$this->updateLatestSubscriptionPlanInformationInAccountBillingMaster($arrUpdateLatestSubscription);

 												
				 								/**
				 								 * inserting values in payment details table
				 								 */

				 								$qryIns = "	INSERT INTO account_payment_details (account_id, account_subscription_id, currency, amount_paid, payment_method_id, tp_payload, tp_payment_id, type, paid_at, status, created, modified)
				 											VALUES ('" . addslashes($accountId) . "',
				 													'" . addslashes($accountSubscriptionId) . "',
				 													'" . addslashes($invoiceData_planCurrency) . "',
				 													'" . addslashes($subscriptionInvoiceInfo_total) . "',
				 													2,
				 													'{}',
				 													'" . addslashes($subscriptionInvoiceInfo_charge) . "',
				 													1,
				 													'" . addslashes($subscriptionInvoiceInfo_date) . "',
				 													1,
				 													'" . addslashes($subscriptionInvoiceInfo_date) . "',
				 													0)";
				 										
				 								if(DEBUG) {
				 									print nl2br($qryIns);
				 								}
				 								
				 								$accountPaymentDetailId = $this->objDBConNew->insertAndGetId($qryIns);
				 								
				 								if(!$accountPaymentDetailId) {
				 									print "Cant insert into 'account_payment_details', error occured.";
				 									return false;
				 								}


				 								/**
				 								 * Insert into payment invoice table.
				 								 */
				 								$qryIns = "	INSERT INTO account_invoice_details (invoice_number, account_id, account_subscription_id, account_payment_id, currency, amount, discount_amount, credit_amount, total_amount, file_copy, created)
				 											VALUES ('" . addslashes($subscriptionInvoiceInfo_invoiceId) . "',
				 													'" . addslashes($accountId) . "',
				 													'" . addslashes($accountSubscriptionId) . "',
				 													'" . addslashes($accountPaymentDetailId) . "',
				 													'" . addslashes($invoiceData_planCurrency) . "',
				 													'" . addslashes($subscriptionInvoiceInfo_subtotal) . "',
				 													'" . addslashes($discountAmount) . "',
				 													'" . addslashes($stripeCustomerInfo_account_balance) . "',
				 													'" . addslashes($subscriptionInvoiceInfo_total) . "',
				 													'',
				 													'" . addslashes($subscriptionInvoiceInfo_date) . "')";
				 										
				 								if(DEBUG) {
				 									print nl2br($qryIns);
				 								}
				 								
				 								$accountInvoiceDetailsId = $this->objDBConNew->insertAndGetId($qryIns);
				 								
				 								if(!$accountInvoiceDetailsId) {
				 									print "Cant insert into 'account_invoice_details', error occured.";
				 									return false;
				 								}
				 							}
 										}
									// }
								}
 							}
 						}
 					}
 					else {
 						print "ACCOUNT ID NOT FOUND FOR THE USER " . $stripeCustomerInfo_userId . "\n";
 					}
 				}
 				else {
 					print "USERID NOT FOUND IN THE META DATA OF THE CUSTOMER ENTRY IN THE STRIPE PAYMENT GATEWAY\n";
 				}
    		}


    		/**
    		 * Data processing for the stripe customers finished..
    		 * Starting to enter free plan for remaining accounts.
    		 */
    		$this->updateRemainingUsersWithFreePlan();
    	}
    }


    function updateRemainingUsersWithFreePlan () {
    	if(DEBUG) {
    		print "updateRemainingUsersWithFreePlan ()";
    	}
 		
 		$this->objLogger->setMessage('Adding account billing information for the users with FREE plan.');

    	$arrFreePlanAccountIds = $this->getFreePlanAccountIds();

    	if(!empty($arrFreePlanAccountIds)) {

    		$this->objLogger->setMessage('Total number of free plan accounts found - ' . COUNT($arrFreePlanAccountIds));

    		$arrFreePlanInfo = $this->getFreePlanInfo();

    		$planId = $arrFreePlanInfo['id'];
    		$planConfiguration = $arrFreePlanInfo['configuration'];
    		$planAmount = $arrFreePlanInfo['amount'];

    		$counter = 0;
    		$logCount = 0;

    		foreach ($arrFreePlanAccountIds as $key => $accountMasterId) {
				$this->objLogger->setMessage('-----------------------------------');
$counter++;
print "counter :: " . $counter . "\n";

    			$qryIns = "	INSERT INTO account_billing_master (account_id, plan_id, team_size, email_acc_seats, current_subscription_id, next_subscription_updates, configuration, credit_balance, status, created, modified)
    						VALUES ('" . addslashes($accountMasterId) . "',
    								'" . addslashes($planId) . "',
    								1,
    								0,
    								NULL,
    								0,
    								'" . addslashes($planConfiguration) . "',
    								0,
    								1,
    								0,
    								0)";
    					
    			if(DEBUG) {
    				print nl2br($qryIns);
    			}
    			
    			$accountBillingMasterId = $this->objDBConNew->insertAndGetId($qryIns);

    			$this->objLogger->setMessage('Account billing master id inserted - ' . $accountBillingMasterId);
    			
    			if(!$accountBillingMasterId) {
    				print "Cant insert into 'account_billing_master', error occured.";
    				return false;
    			}



    			$qryIns = "	INSERT INTO account_subscription_details (account_id, plan_id, team_size, email_acc_seats, currency, amount, credit_balance, coupon_id, discount_type, discount_value, discount_amount, total_amount, payment_method_id, start_date, end_date, next_subscription_id, tp_subscription_id, tp_customer_id, type, status, created, modified)
    						VALUES ('" . addslashes($accountMasterId) . "',
    						 		'" . addslashes($planId) . "',
    						 		0,
    						 		0,
    						 		'usd',
    						 		'" . addslashes($planAmount) . "',
    						 		0.00,
    						 		NULL,
    						 		'PER',
    						 		0.00,
    						 		0.00,
    						 		0.00,
    						 		NULL,
    						 		'" . addslashes($planId) . "',
    						 		0,
    						 		NULL,
    						 		NULL,
    						 		NULL,
    						 		1,
    						 		1,
    						 		(SELECT created FROM user_master um WHERE um.account_id='" . addslashes($accountMasterId) . "' AND um.user_type_id = '4'),
    						 		0)";
    					
    			if(DEBUG) {
    				print nl2br($qryIns);
    			}
    			
    			$accountSubscriptionId = $this->objDBConNew->insertAndGetId($qryIns);

    			$this->objLogger->setMessage('Account subscription id inserted - ' . $accountSubscriptionId);
    			
    			if(!$accountSubscriptionId) {
    				print "Cant insert into 'account_subscription_details', error occured.";
    				return false;
    			}
				$this->objLogger->setMessage('-----------------------------------');

				$logCount++;

				if($logCount > 100) {
					$this->objLogger->addLog();
					$logCount = 0;
				}
    		}

    		/**
    		 * Updating all the current subscription
    		 */

    		$qryupd = "	UPDATE account_billing_master abm
							SET abm.current_subscription_id = ( SELECT asd.id FROM account_subscription_details asd WHERE asd.plan_id = 1 AND asd.account_id = abm.account_id )
						WHERE abm.plan_id = 1
						AND current_subscription_id IS NULL ";
    		
    		if(DEBUG) {
    			print nl2br($qryupd);
    		}
    		
    		$objDBResult = $this->objDBConNew->executeQuery($qryupd);	
    		
    		if(!$objDBResult) {
    			print "Cant Update into 'tableName', error occured.";
    			return false;
    		}
    		return true;
    	}
    }


    function getFreePlanInfo () {
    	if(DEBUG) {
    		print "getFreePlanInfo ()";
    	}

    	$arrReturn = array();

    	$qrySel = "	SELECT *
    				FROM plan_master
    				WHERE code = 'FREE'";
    	
    	if(DEBUG) {
    		print nl2br($qrySel);
    	}
    	
    	$objDBResult = $this->objDBConNew->executeQuery($qrySel);
    	
    	if(!$objDBResult) {
    		print "Error occur.";
    		return false;
    	}
    	else {
    		if($objDBResult->getNumRows() > 0) {
    			$rowGetInfo = $objDBResult->fetchAssoc();
    			$arrReturn = $rowGetInfo;
    		}
    	}
    	return $arrReturn;
    }


    function getFreePlanAccountIds () {
    	if(DEBUG) {
    		print "getFreePlanAccountIds ()";
    	}
 		
    	$arrReturn = array();

    	$qrySel = "	SELECT id FROM account_master WHERE id NOT IN (SELECT account_id FROM account_billing_master); ";
    	
    	if(DEBUG) {
    		print nl2br($qrySel);
    	}
    	
    	$objDBResult = $this->objDBConNew->executeQuery($qrySel);
    	
    	if(!$objDBResult) {
    		print "Error occur.";
    		return false;
    	}
    	else {
    		if($objDBResult->getNumRows() > 0) {
    			while($rowGetInfo = $objDBResult->fetchAssoc()) {
    				array_push($arrReturn, $rowGetInfo['id']);
    			}
    		}
    	}
    	return $arrReturn;
    }

 	
 	/**
 	 *
 	 * Updating account billing master for the could be latest subscriptions.
 	 *
 	 */
 	
    function updateLatestSubscriptionPlanInformationInAccountBillingMaster ($arrParams) {
    	if(DEBUG) {
    		print "updateLatestSubscriptionPlanInformationInAccountBillingMaster ()";
    	}

    	
    	$accountBillingMasterId = $arrParams['accountBillingMasterId'];
    	$planId = $arrParams['planId'];
    	$currentSubscriptionId = $arrParams['currentSubscriptionId'];
    	$teamSize = $arrParams['teamSize'];
    	$jsonPlanConfig = $arrParams['jsonPlanConfig'];


    	$qryupd = "	UPDATE account_billing_master
    				SET plan_id = '" . addslashes($planId) . "', 
    					team_size = '" . addslashes($teamSize) . "', 
    					current_subscription_id = '" . addslashes($currentSubscriptionId) . "',
    					configuration = '" . addslashes($jsonPlanConfig) . "'
    				WHERE id = '" . addslashes($accountBillingMasterId) . "'";
    	
    	if(DEBUG) {
    		print nl2br($qryupd);
    	}
    	
    	$objDBResult = $this->objDBConNew->executeQuery($qryupd);	
    	
    	if(!$objDBResult) {
    		print "Cant Update into 'account_billing_master', error occured.";
    		return false;
    	}
    	else {
    		return true;
    	}
    }



    function getDatabaseDiscountCouponId($couponCode = NULL) {
    	if(DEBUG) {
    		print "getDatabaseDiscountCouponId()";
    	}

    	$intReturn = 0;

    	if(empty($couponCode)) {
    		return $intReturn;
    	}

    	if(isset($this->oldAndNewCouponMapping[$couponCode])) {
    		$intReturn = $this->oldAndNewCouponMapping[$couponCode]['id'];
    	}
    	else {

    		$qrySel = "	SELECT *
    					FROM coupon_master
    					WHERE code = '" . addslashes($couponCode) . "'";
    		
    		if(DEBUG) {
    			print nl2br($qrySel);
    		}
    		
    		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
    		
    		if(!$objDBResult) {
    			print "Error occur.";
    			return false;
    		}
    		else {
    			if($objDBResult->getNumRows() > 0) {
    				$rowGetInfo = $objDBResult->fetchAssoc();
    				$this->oldAndNewCouponMapping[$couponCode] = $rowGetInfo;
    				$intReturn = $rowGetInfo['id'];
    			}
    		}

    	}

    	return $intReturn;
    }


	function getNewPlanDetailsFromMapping ($oldPlanId = NULL) {
		if(DEBUG) {
			print "getNewPlanDetailsFromMapping ()";
		}

		$arrReturn = array();

		if(empty($oldPlanId)) {
			return $arrReturn;
		}

		if(!empty($this->arrPlanMappingWithInfo)) {
			foreach ($this->arrPlanMappingWithInfo as $key => $arrPlanMappingInfo) {
				if($arrPlanMappingInfo['old']['plan_id'] == $oldPlanId) {
					$arrReturn = $arrPlanMappingInfo['new'];
					break;
				}
			}
		}

		return $arrReturn;
	}



	function getPlanMappingWithInfo () {
		if(DEBUG) {
			print "getPlanMappingWithInfo ()";
		}

		$arrReturn = array();

		$arrNewDbPlans = array();
		$arrOldDbPlans = array();

		$qrySel = "	SELECT *
					FROM plan_master ";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}
		else {
			if($objDBResult->getNumRows() > 0) {
				while($rowGetInfo = $objDBResult->fetchAssoc()) {
					array_push($arrNewDbPlans, $rowGetInfo);
				}
			}
		}



		$qrySel = "	SELECT *
					FROM plan_master ";
		
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
					array_push($arrOldDbPlans, $rowGetInfo);
				}
			}
		}

		foreach ($arrOldDbPlans as $key => $arrOldDbPlanInfo) {
			$planId = $arrOldDbPlanInfo['plan_id'];

			foreach ($arrNewDbPlans as $key => $arrNewDbPlanInfo) {
				if(isset($this->arrPricingPlanMapping[$planId]) && 
					$this->arrPricingPlanMapping[$planId] == $arrNewDbPlanInfo['code']) {
 					
 					$arrTmp = array();
 					$arrTmp['old'] = $arrOldDbPlanInfo;
 					$arrTmp['new'] = $arrNewDbPlanInfo;

 					$arrReturn[$planId] = $arrTmp;
				}
			}
		}
		return $arrReturn;
	}


}


?>