<?php
/**
 * APP_ENV
 * 
 * Environment variable of the web app
 * Possible values are: 0 (Local), 1 (Test), 2 (Development), 3 (Production)
 */
define("APP_ENV", 0);

/**
 * APP_DEBUG
 *
 * Debug mode on or off
 * Possible values are: false (off), true (on)
 */
define("APP_DEBUG", false);

/**
 * SH_COMMANDS
 *
 * Define which section this app belongs to
 * Used to identify which settings to apply
 *
 * DO NOT CHANGE / REMOVE THIS VARIABLE
 */
define("SH_COMMANDS", true);

/**
 * Load bootstrap file
 */
include_once __DIR__ . "/../src/bootstrap.php";

/**
 * Load (vendor) auto loader file
 */
include_once __DIR__ . "/../vendor/autoload.php";

/**
 * Load your app settings and create app instance
 */
include_once __DIR__ . "/../src/settings.php";

/**
 * Initialize application
 */
$sh_app = new \Slim\App($application_config);

\App\Components\SHAppComponent::initialize();

/**
 * Setup application DIC
 */
include_once __DIR__ . "/../src/dependencies.php";

/**
 * Register application middlewares
 */
include_once __DIR__ . "/../src/middleware.php";

/**
 * Register application routes
 */
include_once __DIR__ . "/../src/command_routes.php";

// Run application
$sh_app->run();