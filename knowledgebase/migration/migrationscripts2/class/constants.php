<?php


if(!defined('DEBUG')) {
	define('DEBUG', false);
}

if(!defined('HOST_NEW')) {
	define('HOST_NEW', 'prod-migration-full-testrun.csqq9ri7wypp.us-west-2.rds.amazonaws.com');
}

if(!defined('USERNAME_NEW')) {
	define('USERNAME_NEW', 'dbroot');
}

if(!defined('PASSWORD_NEW')) {
	define('PASSWORD_NEW', 'prod1800#34migrate');
}

if(!defined('DBNAME_NEW')) {
	define('DBNAME_NEW', 'sh_app_rebuild');
}



if(!defined('HOST_OLD')) {
	define('HOST_OLD', 'prod-migration-full-testrun.csqq9ri7wypp.us-west-2.rds.amazonaws.com');
}

if(!defined('USERNAME_OLD')) {
	define('USERNAME_OLD', 'dbroot');
}

if(!defined('PASSWORD_OLD')) {
	define('PASSWORD_OLD', 'prod1800#34migrate');
}

if(!defined('DBNAME_OLD')) {
	define('DBNAME_OLD', 'dev_salehandy');
}


if(!defined('STRIPE_KEY')) {
	define('STRIPE_KEY', 'sk_live_WYDEnjDIDTrGZTb4BheH13Q1');
}

define("SH_DOCUMENT_VIEWER_LINK", "https://qapresent2.saleshandy.com");