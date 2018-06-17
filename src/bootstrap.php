<?php
/**
 * Set debug option if set to true
 */
if (\APP_DEBUG) {
    ini_set("error_reporting", E_ALL);
    ini_set("display_errors", true);
}

/**
 * Define app gloabl constants
 */
/** NEVER CHANGE THESE VALUES **/
define("APP_VARS_CONFIG_FILE", __DIR__ . "/app_vars.json");
define("ENCODE_DECODE_SALT", "SH2.0");
define("ENCODE_DECODE_ID_LENGTH", 16);
define('CLIENT_SECRET_PATH', __DIR__ . '/../client_secret.json');

/** CORS REQUEST DEFAUL VALUES **/
define("CORS_ALLOW_ORIGIN", "*");
define("CORS_ALLOW_HEADER", "X-SH-Source, X-Authorization-Token, X-shreferer, X-Requested-With, Content-Type, Accept, Origin, Authorization");
define("CORS_ALLOW_METHOD", "GET, POST, DELETE, OPTIONS");

/** Other required variables **/
define("AUTH_TOKEN_EXPIRE_INTERVAL", (7 * 24 * 60 * 60));
define("AUTH_TOKEN_EXPIRE_INTERVAL_CHROME", (365 * 24 * 60 * 60));
define("AUTH_TOKEN_EXPIRE_INTERVAL_OUTLOOK", (365 * 24 * 60 * 60));
define("AUTH_TOKEN_EXPIRE_INTERVAL_ADMIN", (60 * 60));
define("RESET_PASSWORD_LINK_EXPIRE", (24 * 60 * 60));
define("DEFAULT_REPLY_TRACK_DAYS",1);
define("DEFAULT_CAMPAIGN_SCHEDULE_INTERVAL_MINUTE", (15));
define("DEFAULT_CAMPAIGN_SCHEDULE_INTERVAL", (\DEFAULT_CAMPAIGN_SCHEDULE_INTERVAL_MINUTE * 60));
define("DEFAULT_TIMEZONE", "GMT+00:00");
define("USER_TIMEZONE", "GMT+05:30");
define("DEFAULT_TIME_FORMAT", "Y-m-d H:i:s");
define("DEFAULT_CAMPAIGN_MINIMUM_INTERVAL_BETWEEN_MAILS", 0);
define("DEFAULT_CAMPAIGN_MINIMUM_INTERVAL_BETWEEN_BULK_MAILS", 40);
define("DEFAULT_CAMPAIGN_MAXIMUM_INTERVAL_BETWEEN_MAILS", 240);
define("CAMPAIGN_DATE_TIME_FORMAT","D, M d Y h:i:s A");
define("APP_TIME_FORMAT", "M d Y h:i A");
//define("CAMPAIGN_DATE_FORMAT", "D, d M Y H:i");
define("CAMPAIGN_DATE_FORMAT", "M d Y h:i A");
//define CSS class
define("CAMPAIGN_CONDITION_CSS", "\"ChipText\"");
define("CAMPAIGN_MAX_REPLY_CHECK_TIME", 4 * 24 * 60 * 60);

define("DIFFERENCE_BETWEEN_LAST_MAIL_OPEN_SECOND", 10);
define("MAIL_OPEN_MARGIN_FIRST_TIME", 30);
define("OUTLOOK_MAIL_OPEN_MARGIN_FIRST_TIME", 60);

define("FIND_LINK_PATTERN", '/(href=")([^{#].*?)"/i');
define("FIND_DOCUMENT_PATTERN", '/(href=")([{#].*?)"/i');
    
define("TRACKING_UNIQUE_TEXT", "sh-trk-112p68p");

/** Campaign error condition messages **/
define("CONNECTION_NOT_ESTABLISH","Connection could not be established");
define("EXPECTED_CODE_250_BUT_GONE","Expected response code 250 but got code \"\"");
define("EXPECTED_CODE_250","Expected response code 250");
define("EXPECTED_CODE_220_BUT_GONE","Expected response code 220 but got code \"\"");
define("EXPECTED_CODE_220","Expected response code 220");
define("RATE_LIMIT_EXCEED","Rate Limit Exceeded");
define("COULD_NOT_AUTHENTICATE", "Could not authenticate");
define("SMTP_CONNECTION_FAILED", "SMTP connect() failed");

define("LIMIT_INVITE_MEMBER", 50);

define("FROM_EMAIL", "tkdev23@gmail.com");
define("FROM_NAME", "Tarak Kadiya");
define("SH_WEBSITE_URL", "https://www.tarakkadia.tk/");

//define Campaing CSV upload directory 
define("BASE_PATH",__DIR__);
define("CAMPAIGN_CSV_FILE_PATH", __DIR__."/../upload/campaignjson");
define("CAMPAIGN_CSV_DB_FILE_PATH", "campaignjson");

//define Documents Upload directory
define("DOCUMENT_UPLOADED_DIRECTORY", __DIR__."/../../media/upload/");
define("DOCUMENT_UPLOADED_DIRECTORY_SHOW", "http://media.sugarplate.in/upload/");
define("DOCUMENT_UPLOADED_SUB_DIRECTORY", "docs/");

define("EMAIL_TEPLATES_FOLDER", __DIR__ . "/../system_email_templates");

/** SMTP connection **/
define("HOST", "smtp.gmail.com");
define("USERNAME", "tkdev23@gmail.com");
define("PASSWORD", "C92121219k@");
define("PORT", 587);
define("ENCRYPTION", 'tls');

define("ALLOWED_UNLIMITED_COUNT","~");
define("REGULAR_CAMPAIGN_CSV_LIMIT","200");
define("BULK_CAMPAIGN_CSV_LIMIT","2000");

define("SOMEONE_TEXT", "Someone");
define("UNKNOWN_TEXT", "Unknown");

/**
 * Define constants based on the environment
 */
switch (\APP_ENV) {
    // Test
    case 1:
        define("DEFAULT_API_ENDPOINT", "http://doodooapi.sugarplate.in");
        define("BASE_URL", "http://doodooapp.sugarplate.in/");

        //define aws services config settings
        define("AWS_ACCESS_KEY", "AKIAIWVDV3253EGV7BYQ");
        define("AWS_ACCESS_SECRET_KEY", "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");

        //define aws s3 client constant
        define("AWS_BUCKET_NAME", "dev2sh");
        define("AWS_ACCESS_REGION", "us-west-2");
        define("AWS_ACCESS_VERSION", "latest");
        define("AWS_BUCKET_BASE_URL", "https://s3-us-west-2.amazonaws.com/");

        //When add or remove below tracking pixel url update count less 1 in "TRACKING_PIXEL_COUNT"
        define("TRACKING_PIXEL", [
            "https://track.saleshandy.net", 
            "https://go.saleshandy.net" 
        ]);
        define("TRACKING_PIXEL_COUNT", 1);

        /** Stripe keys **/
        define("PK_TEST_KEY", "pk_test_qlqGtkaN6iGkC8PFdTntrR1N");
        define("SK_TEST_KEY", "sk_test_CresSyU8wvn6gatJ5yV2myYE");

        /** Node Socket Related Constant **/
        define("NODE_SERVER_HOST","https://appsocket.cultofpassion.com");
        define("NODE_SERVER_PORT","8443");  
        define("NODE_SERVER_ENDPOINT", NODE_SERVER_HOST . ':'. NODE_SERVER_PORT);
        define("NODE_SERVER_PUSH_ENDPOINT",NODE_SERVER_ENDPOINT . '/sockets/postData');
        define("ROOT_PATH", dirname(__DIR__) . '/');

        /** Document viewer related constants **/
        define("DOC_VIEWER_BASE_URL", "https://present.saleshandy.net");
        define("DOC_VIEWER_SLUG_TIMEOUT", (90 * 24 * 60 * 60));
        define("DOC_VIEWER_VISIT_TIMEOUT", (30 * 60));
        define("DOC_S3_BUCKET_URL", "https://s3-us-west-2.amazonaws.com/");
        define("DOC_S3_BUCKET_NAME", "dev2sh");
        define("DOC_CLOUDFRONT_URL", "https://d3ckdsluzgyw5j.cloudfront.net/");

        define("PLUGIN_AUTH_URI", "https://auth.saleshandy.net");
        define("BILLING_INTERVAL", "month");
        break;

    // Development
    case 2:
        define("DEFAULT_API_ENDPOINT", "http://doodooapi.sugarplate.in");
        define("BASE_URL", "http://doodooapp.sugarplate.in/");

        //define aws services config settings
        define("AWS_ACCESS_KEY", "AKIAIWVDV3253EGV7BYQ");
        define("AWS_ACCESS_SECRET_KEY", "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");

        //define aws s3 client constant
        define("AWS_BUCKET_NAME", "dev2sh");
        define("AWS_ACCESS_REGION", "us-west-2");
        define("AWS_ACCESS_VERSION", "latest");
        define("AWS_BUCKET_BASE_URL", "https://s3-us-west-2.amazonaws.com/");

        //When add or remove below tracking pixel url update count less 1 in "TRACKING_PIXEL_COUNT"
        define("TRACKING_PIXEL", [
            "https://track1.cultofpassion.com", 
            "https://track2.cultofpassion.com" 
        ]);
        define("TRACKING_PIXEL_COUNT", 1);

        /** Stripe keys **/
        define("PK_TEST_KEY", "pk_test_37K6Tg6VKUCSZV7YetOSMTg5");
        define("SK_TEST_KEY", "sk_test_AB8X8kMLGvGiw5o9TvyuFOGV");

        /** Node Socket Related Constant **/
        define("NODE_SERVER_HOST","https://appsocket.cultofpassion.com");
        define("NODE_SERVER_PORT","8443");  
        define("NODE_SERVER_ENDPOINT", NODE_SERVER_HOST . ':'. NODE_SERVER_PORT);
        define("NODE_SERVER_PUSH_ENDPOINT",NODE_SERVER_ENDPOINT . '/sockets/postData');
        define("ROOT_PATH", dirname(__DIR__) . '/');

        /** Document viewer related constants **/
        define("DOC_VIEWER_BASE_URL", "https://shdocs.cultofpassion.com");
        define("DOC_VIEWER_SLUG_TIMEOUT", (90 * 24 * 60 * 60));
        define("DOC_VIEWER_VISIT_TIMEOUT", (30 * 60));
        define("DOC_S3_BUCKET_URL", "https://s3-us-west-2.amazonaws.com/");
        define("DOC_S3_BUCKET_NAME", "dev2sh");
        define("DOC_CLOUDFRONT_URL", "https://d3ckdsluzgyw5j.cloudfront.net/");

        define("PLUGIN_AUTH_URI", "https://qa-auth.saleshandy.com");
        define("BILLING_INTERVAL", "month");
        break;

    // Production
    case 3:
        define("DEFAULT_API_ENDPOINT", "http://doodooapi.sugarplate.in");
        define("BASE_URL", "http://doodooapp.sugarplate.in/");

        //define aws services config settings
        define("AWS_ACCESS_KEY", "AKIAIWVDV3253EGV7BYQ");
        define("AWS_ACCESS_SECRET_KEY", "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");

        //define aws s3 client constant
        define("AWS_BUCKET_NAME", "dev2sh");
        define("AWS_ACCESS_REGION", "us-west-2");
        define("AWS_ACCESS_VERSION", "latest");
        define("AWS_BUCKET_BASE_URL", "https://s3-us-west-2.amazonaws.com/");

        //When add or remove below tracking pixel url update count less 1 in "TRACKING_PIXEL_COUNT"
        define("TRACKING_PIXEL", [
            "https://track1.cultofpassion.com", 
            "https://track2.cultofpassion.com" 
        ]);
        define("TRACKING_PIXEL_COUNT", 1);

        /** Stripe keys **/
        define("PK_TEST_KEY", "pk_test_qlqGtkaN6iGkC8PFdTntrR1N");
        define("SK_TEST_KEY", "sk_test_CresSyU8wvn6gatJ5yV2myYE");

        /** Node Socket Related Constant **/
        define("NODE_SERVER_HOST","https://appsocket.cultofpassion.com");
        define("NODE_SERVER_PORT","8443");  
        define("NODE_SERVER_ENDPOINT", NODE_SERVER_HOST . ':'. NODE_SERVER_PORT);
        define("NODE_SERVER_PUSH_ENDPOINT",NODE_SERVER_ENDPOINT . '/sockets/postData');
        define("ROOT_PATH", dirname(__DIR__) . '/');

        /** Document viewer related constants **/
        define("DOC_VIEWER_BASE_URL", "https://shdocs.cultofpassion.com");
        define("DOC_VIEWER_SLUG_TIMEOUT", (90 * 24 * 60 * 60));
        define("DOC_VIEWER_VISIT_TIMEOUT", (30 * 60));
        define("DOC_S3_BUCKET_URL", "https://s3-us-west-2.amazonaws.com/");
        define("DOC_S3_BUCKET_NAME", "dev2sh");
        define("DOC_CLOUDFRONT_URL", "https://d3ckdsluzgyw5j.cloudfront.net/");

        define("PLUGIN_AUTH_URI", "https://auth.saleshandy.net");
        define("BILLING_INTERVAL", "month");
        break;

    default:
        define("DEFAULT_API_ENDPOINT", "http://doodooapi.sugarplate.in");
        define("BASE_URL", "http://doodooapp.sugarplate.in/");

        //define aws services config settings
        define("AWS_ACCESS_KEY", "AKIAIWVDV3253EGV7BYQ");
        define("AWS_ACCESS_SECRET_KEY", "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");

        //define aws s3 client constant
        define("AWS_BUCKET_NAME", "dev2sh");
        define("AWS_ACCESS_REGION", "us-west-2");
        define("AWS_ACCESS_VERSION", "latest");
        define("AWS_BUCKET_BASE_URL", "https://s3-us-west-2.amazonaws.com/");

        //When add or remove below tracking pixel url update count less 1 in "TRACKING_PIXEL_COUNT"
        define("TRACKING_PIXEL", [
            "https://track1.cultofpassion.com", 
            "https://track2.cultofpassion.com" 
        ]);
        define("TRACKING_PIXEL_COUNT", 1);

        /** Stripe keys **/
        define("PK_TEST_KEY", "pk_test_53NqYVW2YRrH5JcQyFdPyiVF");
        define("SK_TEST_KEY", "sk_test_8n4QyWFUmh6tMm2SSU8rJ9Kk");

        /** Node Socket Related Constant **/
        define("NODE_SERVER_HOST","https://appsocket.cultofpassion.com");
        define("NODE_SERVER_PORT","8443");  
        define("NODE_SERVER_ENDPOINT", NODE_SERVER_HOST . ':'. NODE_SERVER_PORT);
        define("NODE_SERVER_PUSH_ENDPOINT",NODE_SERVER_ENDPOINT . '/sockets/postData');
        define("ROOT_PATH", dirname(__DIR__) . '/');

        /** Document viewer related constants **/
        define("DOC_VIEWER_BASE_URL", "https://shdocs.cultofpassion.com");
        define("DOC_VIEWER_SLUG_TIMEOUT", (90 * 24 * 60 * 60));
        define("DOC_VIEWER_VISIT_TIMEOUT", (30 * 60));
        define("DOC_S3_BUCKET_URL", "https://s3-us-west-2.amazonaws.com/");
        define("DOC_S3_BUCKET_NAME", "dev2sh");
        define("DOC_CLOUDFRONT_URL", "https://d3ckdsluzgyw5j.cloudfront.net/");

        define("PLUGIN_AUTH_URI", "https://devapi3.cultofpassion.com/auth");
        define("BILLING_INTERVAL", "day");
        break;
}

/** Currently not in use **/
define("PATH_TO_MEDIA", __DIR__ . "/../media"); 
define("URL_TO_MEDIA", "http://test.slim3.com/media");
define("MEDIA_USER_PHOTOS", "/user_photos");
define("MEDIA_ORG_LOGOS", "/organisation_logo");
