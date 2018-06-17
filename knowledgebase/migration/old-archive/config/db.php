<?php
// Source database credentials
$source_db_host = "localhost";
$source_db_port = "3306";
$source_db_user = "root";
$source_db_password = "root";
$source_db_name = "superslide";
$source_db_engine = "mysql";
$source_db_charset = "utf8";

// Destination database credentials
$destination_db_host = "localhost";
$destination_db_port = "3306";
$destination_db_user = "root";
$destination_db_password = "root";
$destination_db_name = "sh_app_rebuild";
$destination_db_engine = "mysql";
$destination_db_charset = "utf8";

// Connect to source database
try {
    $db_source = new PDO($source_db_engine . ":host=" . $source_db_host . ";dbname=" . $source_db_name . ";charset=" . $source_db_charset, $source_db_user, $source_db_password);

    $db_source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db_source->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die($e->getMessage());
}

// Connect to destination database
try {
    $db_destination = new PDO($destination_db_engine . ":host=" . $destination_db_host . ";dbname=" . $destination_db_name . ";charset=" . $destination_db_charset, $destination_db_user, $destination_db_password);

    $db_destination->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db_destination->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die($e->getMessage());
}