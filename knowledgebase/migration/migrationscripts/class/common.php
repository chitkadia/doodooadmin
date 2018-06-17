<?php


date_default_timezone_set('Asia/Kolkata');
require(__DIR__ . '/vendor/autoload.php');

require_once(__DIR__ . '/constants.php');
require_once(__DIR__ . '/commonvars.php');
require_once(__DIR__ . '/commondbfunc.class.php');
require_once(__DIR__ . '/commonpdo.class.php');
require_once(__DIR__ . '/errorlogs.class.php');
require_once(__DIR__ . '/generalfunctions.class.php');
require_once(__DIR__ . '/../customClasses/users.class.php');
require_once(__DIR__ . '/../customClasses/accounts.class.php');
require_once(__DIR__ . '/../customClasses/accountorganization.class.php');
require_once(__DIR__ . '/../customClasses/coupons.class.php');
require_once(__DIR__ . '/../customClasses/billing.class.php');
require_once(__DIR__ . '/../customClasses/stripedata.class.php');
require_once(__DIR__ . '/../customClasses/prospects.class.php');
require_once(__DIR__ . '/../customClasses/emailaccounts.class.php');
require_once(__DIR__ . '/../customClasses/documents.class.php');
require_once(__DIR__ . '/../customClasses/documentlinks.class.php');
require_once(__DIR__ . '/../customClasses/template.class.php');
require_once(__DIR__ . '/../customClasses/emails.class.php');
require_once(__DIR__ . '/../customClasses/migrateroledefaultresources.class.php');
require_once(__DIR__ . '/../customClasses/campaign.class.php');
require_once(__DIR__ . '/../customClasses/activities.class.php');

?>