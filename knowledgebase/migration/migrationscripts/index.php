<?php


// including all the modules into this script
require_once('class/common.php');


CLASS SCRIPTINITIATE {
 	
	protected $logFilePrefix;

	function SCRIPTINITIATE() {
		$this->logFilePrefix = date('YmdHis');
		$this->initiateTheScript();
	}

	function initiateTheScript() {
		if(DEBUG) {
			print "initiateTheScript ()";
		}

		while(true) {

			print "Please choose your module to migrate \n\n";
			print "1.	Import Admin Users.\n";
			print "2.	Import Team member Users.\n";
			print "3.	Import Billing Information.\n";
			print "4.	Import Email Accounts.\n";
			print "5.	Import Companies.\n";
			print "6.	Import Contacts.\n";
			print "7.	Import Document.\n";
			print "8.	Import Document Links.\n";
			print "9.	Import Document Links of Direct Generated link.\n";
			print "10.	Import Document Visit Logs.\n";
			print "11.	Import Templates.\n";
			print "12.	Import Emails.\n";
			print "13.	Import Campaigns.\n";
			print "14.	Import Activities.\n";
			print "15.	Exit.\n";
			print "16.	Import Stripe Data.\n";
			print "17.	Users roles default resource migrate from qaapi to local.\n";
			print "18.	Activity table dump.\n";

			$handle = fopen ("php://stdin","r");
			$input = fgets($handle);

			$input = trim($input);

 			$objLogger = new ERRORLOGS();

 			$objLogger->setLogFilePath($input, $this->logFilePrefix);

			switch ($input) {
				case '1':
				 	// function call to start users migration
					$objUser = new USERS($objLogger);
					$objUser->checkIncrementalAndResumeScriptForAdminUser();
					break;
				case '2':
				 	// function call to start users migration
					$objUser = new USERS($objLogger);
					$objUser->checkIncrementalAndResumeScriptForTeamMemberUser();
					break;
				case '3':
 					
 					// function call to migrate all the coupons.
					$objCoupon = new COUPONS($objLogger);
					$flagCouponMigrated = $objCoupon->migrateCoupon();

					$flagCouponMigrated = true;
 					
 					// if coupons migrated successfully, then proceed to the billing and subscription.
					if($flagCouponMigrated) {
						
						// function call to migrate billing.
						$objBilling = new BILLING($objLogger);
						$objBilling->migrateBillingInformation();
					}

					break;
				case '4':
					$objLogger->setMessage("Email accounts");
					$objLogger->addLog();


					$objEmailAccount = new EMAILACCOUNT($objLogger);
					$objEmailAccount->migrateEmailAccountsIncrementalScript();

					break;


				case '5':

					$objProspects = new PROSPECTS($objLogger);
					$objProspects->migrateCompaniesIncrementalScript();

					break;
				case '6':
					$objLogger->setMessage("contacts");
					$objLogger->addLog();

					$objProspects = new PROSPECTS($objLogger);
					
					$objProspects->migrateContactsInsideCompaniesIncrementalScript();

					break;
				case '7':
					// $objLogger->setMessage("document");
					// $objLogger->addLog();

					$objDocument = new DOCUMENTS($objLogger);
					$objDocument->migrateDocumentIncrementalScript();

					break;
				case '8':
					// $objLogger->setMessage("document link");
					// $objLogger->addLog();

					$objDocumentlist = new DOCUMENTLINKS($objLogger);
					$objDocumentlist->migrateDocumentLinksIncremental();
					
					break;

				case '9':
					// $objLogger->setMessage("document link");
					// $objLogger->addLog();

					$objDocumentlist = new DOCUMENTLINKS($objLogger);
					$objDocumentlist->startMigarationForDirectGenerationLinks();
					
					break;

				case '10':
					// $objLogger->setMessage("document link");
					// $objLogger->addLog();

					$objDocumentlist = new DOCUMENTLINKS($objLogger);
					$objDocumentlist->startMigarationForDocumentVists();
					
					break;

				case '11':
					$objLogger->setMessage("template");
					$objLogger->addLog();

					$objTemplate = new TEMPLATE($objLogger);
					$objTemplate->migrateTemplatesIncrementalScript();

					break;
				case '12':
					// $objLogger->setMessage("email");
					// $objLogger->addLog();

					$objEmails = new EMAILS($objLogger);
					$objEmails->migrateEmailsOfAllUsersIncremental();
					break;

				case '13':
					$objLogger->setMessage("campaign");
					$objLogger->addLog();
					$objCampaign = new CAMPAIGN($objLogger);
					$objCampaign->migrateCampaignsIncrementalScript();
					break;

				case '14':
					$objLogger->setMessage("Activites");
					$objLogger->addLog();
					$objCampaign = new ACTIVITIES($objLogger);
					$objCampaign->migrateIncrementalActivities();
					break;

				case '15':
				 	print "Exiting the program\n\n";
				 	exit();
					break;
				case '16':
					
					$objStripeData = new STRIPEDATA($objLogger);
					$objStripeData->getStripeData();

					break;

				case '17':

				 	$objMigrateRoleDefaultResource = new MIGRATEROLEDEFAULTRESOURCE();
				 	$objMigrateRoleDefaultResource->migrate();
				 	break;

				 case '18':

				  	$objCampaign = new ACTIVITIES($objLogger);
					$objCampaign->activityDump();

				default:
					print "Invalid value passed. Please choose correct option.\n\n";
					break;
			}
		}
	}
}


$objScr = new SCRIPTINITIATE();

?>