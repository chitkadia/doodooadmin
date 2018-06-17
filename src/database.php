<?php
/**
 * Database settings of application
 */

// Check environment of application and decide which database to connect
switch (APP_ENV) {
    // Local Development
    case 0:
    // Test Server
    case 1:
    // Development Server
    case 2:
    // Production Server
    case 3:
    // Default value
    default:
        $database_config = [
            "engine" => "mysql",
            "host" => "localhost",
            "port" => "3306",
            "username" => "root",
            "password" => "",
            "database" => "toystore",
            "charset" => "utf8mb4",
            "options" => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            ]
        ];
        break;
}

return $database_config;