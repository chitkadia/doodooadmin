<?php
/**
 * Log data at File level
 *
 * @param $text string Data to be logged
 * @param $include_date boolean Whether to include date in log text (true / false)
 * @param $log_file string File where to log data
 */
function logText($text, $include_date = false, $log_file = "") {
    if(empty($log_file)) return;

    if ($include_date) {
        $date = getLogFormatTime();
        $text = $date . $text;
    }

    @error_log($text . "\n", 3, $log_file);
}

/**
 * Get current system date to be added in logs
 *
 * @return string
 */
function getLogFormatTime() {
    return gmdate("[Y-m-d H:i:s] ");
}