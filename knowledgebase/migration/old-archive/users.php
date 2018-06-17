<?php
// Log file
define("LOG_FILE", __DIR__ . "/logs/users.log");

// Include database and logger files
include_once __DIR__ . "/config/logger.php";
include_once __DIR__ . "/config/db.php";

$divider = "=========================================";

// Start script
logText($divider, true, LOG_FILE);
logText("Script started", true, LOG_FILE);

try {
    // Get starting point to resume / start import
    $get_already_imported_users = "SELECT COUNT(id) FROM user_master";
    $number_of_already_imported_users = $db_destination->query($get_already_imported_users)->fetchColumn();

    logText($number_of_already_imported_users." users are already imported", true, LOG_FILE);

    $get_already_imported_max_users = "SELECT MAX(id) FROM user_master";
    $start_import_from = $db_destination->query($get_already_imported_max_users)->fetchColumn();
    if (empty($start_import_from)) {
        $start_import_from = 0;
    }

    logText("Starting users import after user id ".$start_import_from, true, LOG_FILE);

    // Process records
    while (true) {
        $get_user_data = "SELECT * FROM user WHERE id > " . $start_import_from . " ORDER BY id LIMIT 100";
        $data_array = $db_source->query($get_user_data)->fetchAll();

        // Exit from loop once no data is found to be processed
        if (count($data_array) == 0) {
            break;
        }

        foreach($data_array as $row) {
            logText("Processing user id ".$row["id"], true, LOG_FILE);

            $start_import_from = $row["id"];
        }
    }

} catch (PDOException $e) {
    logText("Error: ".$e->getMessage(), true, LOG_FILE);
}

// End script
logText("Script ended", true, LOG_FILE);
logText($divider, true, LOG_FILE);

// Close database connections
$db_source = null;
$db_destination = null;