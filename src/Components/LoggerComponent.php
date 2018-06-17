<?php
/**
 * Library for logging data
 */
namespace App\Components;

class LoggerComponent {

    const DEFAULT_LOG_FILE = __DIR__."/../../logs/app.log";

    /**
     * Log data
     *
     * @param $data (misc): Data to log
     * @param $file_path (string): Path to file
     */
    public static function log($data, $file_path = self::DEFAULT_LOG_FILE) {
        if (is_array($data)) {
            $text = json_encode($data);

        } else {
            $text = $data;
        }

        $text = gmdate("[Y-m-d H:i:s] ") . $text;

        @error_log($text . "\n", 3, $file_path);
    }

}