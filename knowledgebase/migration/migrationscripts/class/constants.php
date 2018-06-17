<?php


if(!defined('DEBUG')) {
	define('DEBUG', false);
}

if(!defined('HOST_NEW')) {
	define('HOST_NEW', 'localhost');
}

if(!defined('USERNAME_NEW')) {
	define('USERNAME_NEW', 'root');
}

if(!defined('PASSWORD_NEW')) {
	define('PASSWORD_NEW', 'root');
}

if(!defined('DBNAME_NEW')) {
	define('DBNAME_NEW', 'new_migrate');
}



if(!defined('HOST_OLD')) {
	define('HOST_OLD', 'localhost');
}

if(!defined('USERNAME_OLD')) {
	define('USERNAME_OLD', 'root');
}

if(!defined('PASSWORD_OLD')) {
	define('PASSWORD_OLD', 'root');
}

if(!defined('DBNAME_OLD')) {
	define('DBNAME_OLD', 'old_migrate');
}


if(!defined('STRIPE_KEY')) {
	define('STRIPE_KEY', 'sk_live_WYDEnjDIDTrGZTb4BheH13Q1');
}



define("TRACKING_PIXEL", [
	"http://localtrk1.saleshandy.com", 
	"http://localtrk2.saleshandy.com", 
	"http://localtrk3.saleshandy.com", 
	"http://localtrk4.saleshandy.com", 
	"http://localtrk5.saleshandy.com"
]);


?>