<?php

// Setting default server timezone to UTC
date_default_timezone_set('UTC');


/**
 * Store settings to run your application
 */
$application_config = [
    "settings" => [
        "displayErrorDetails" => APP_DEBUG,
        "addContentLengthHeader" => true,
        "determineRouteBeforeAppMiddleware" => true
    ]
];

/**
 * If request has come from commands (cron) then emulate request URI
 */
if (defined("SH_COMMANDS")) {
    $argv = $GLOBALS["argv"];

    $path_info = "/";
    if(!empty($argv[1])) {
        $path_info = $argv[1];
    }

    $env = \Slim\Http\Environment::mock(["PATH_INFO" => $path_info, "REQUEST_URI" => $path_info]);

    $application_config["environment"] = $env;
}