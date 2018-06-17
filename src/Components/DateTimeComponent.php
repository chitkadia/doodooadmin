<?php
/**
 * Library for Date and Time related operations
 */
namespace App\Components;

class DateTimeComponent {

    /**
     * Get current time as per specified timezone
     *
     * @param $format (string): Format in which you need date and time
     * @param $timezone (string): Timezone in which you need date and time
     *
     * @return (string) Either timestamp or formatted date time string
     */
    public static function getDateTime($format = null, $timezone = \DEFAULT_TIMEZONE) {
        $date = new \DateTime("now", new \DateTimeZone($timezone));
      
        if (!empty($format)) {
            return $date->format($format);

        } else {
            return $date->getTimestamp();
        }
    }

    /**
     * Convert date time from one timezone to another timezone
     *
     * @param $datetime (string): Date Time value which needs to be converted
     * @param $is_timestamp (boolean): If $datetime is a timestamp then true, otherwise false
     * @param $stimezone (string): Timezone from which to convert date and time
     * @param $dtimezone (string): Timezone in which to convert date and time
     * @param $format (string): Format in which you need date and time
     *
     * @return (misc) Converted date time string, null on failure
     */
    public static function convertDateTime($datetime, $is_timestamp = false, $stimezone = \DEFAULT_TIMEZONE, $dtimezone = \DEFAULT_TIMEZONE, $format = null) {
        if ($is_timestamp) {
            $datetime = date("Y-m-d H:i:s", $datetime);

        } else {
            $datetime = date("Y-m-d H:i:s", strtotime($datetime));
        }

        try {
            $date = new \DateTime($datetime, new \DateTimeZone($stimezone)); 
            $date->setTimezone(new \DateTimeZone($dtimezone));

            if (!empty($format)) {
                return $date->format($format);

            } else {
                return $date->getTimestamp();
            }

        } catch (\Exception $e) {
            return null;
        }
    }


    /**
     * Convert date time from one timezone to another timezone for Campaign only
     *
     * @param $datetime (string): Date Time value which needs to be converted
     * @param $is_timestamp (boolean): If $datetime is a timestamp then true, otherwise false
     * @param $stimezone (string): Timezone from which to convert date and time
     * @param $dtimezone (string): Timezone in which to convert date and time
     * @param $format (string): Format in which you need date and time
     * @param $inputformat (string): Format from which you need date and time
     * 
     * @return (misc) Converted date time string, null on failure
     */
    public static function convertDateTimeCampaign($datetime, $is_timestamp = false, $stimezone = \DEFAULT_TIMEZONE, $dtimezone = \DEFAULT_TIMEZONE, $format = null, $inputformat = null) {
        
        if (empty($inputformat)) {
            $inputformat = "Y-m-d H:i:s";
        }
        if ($is_timestamp) {
            $datetime = date($inputformat, $datetime);

        } else {
            $datetime = date($inputformat, strtotime($datetime));
        }       
 
        try {
            $date = new \DateTime($datetime, new \DateTimeZone($stimezone)); // this line have changes           
            $date->setTimezone(new \DateTimeZone($dtimezone));
           
            if (!empty($format)) {
                return $date->format($format);

            } else {
                return $date->getTimestamp();
            }

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format given date time for display purpose
     *
     * @param $datetime (string): Datetime value
     * @param $timezone (string): Timezone value
     *
     * @return (string) Fromatted string
     */
    public static function showDateTime($datetime, $timezone = null) {
        return $datetime;
        
        if (!empty($timezone)) {
            return $datetime . " (" . $timezone . ")";
        } else {
            return $datetime;
        }
    }
    /**
     * function is used get difference of current time and given time and return timeAgo string.
     *
     * @param $datetime (string): DateTime value
     * @param $timezone (string): Timezone
     *
     * @return (string) time ago string
     */
    public static function getDateTimeAgo($time_ago = null, $timezone = "GMT+00:00") {
        $formatted_datetime = "";

        if (!empty($time_ago)) {
        
            $current_time = self::getDateTime(null, $timezone);
            $time_difference = $current_time - $time_ago;

            $seconds    = $time_difference ;
            $minutes    = round($time_difference / 60 );
            $hours      = round($time_difference / 3600);
            $days       = round($time_difference / 86400 );
            $weeks      = round($time_difference / 604800);
            $months     = round($time_difference / 2600640 );
            $years      = round($time_difference / 31207680 );

            //Seconds
            if($seconds <= 60) {  
                $formatted_datetime = "$seconds seconds ago";
            } 
            //Minutes 
            else if($minutes <= 60) {  
                if($minutes == 1) {  
                    $formatted_datetime = "one minute ago";  
                } else {  
                    $formatted_datetime = "$minutes minutes ago";  
                }  
            } 
            //Hours 
            else if($hours <= 24) {  
                if($hours == 1) {  
                   $formatted_datetime = "an hour ago";  
                } else {  
                   $formatted_datetime = "$hours hours ago";  
                }  
            }
            //Days  
            else if($days <= 7) {  
                if($days == 1) {  
                   $formatted_datetime = "yesterday";  
                } else {  
                   $formatted_datetime = "$days days ago";  
                }  
            }
            //Week - 4.3 == 52/12 
            else if($weeks <= 4.3) {  
                if($weeks == 1) {  
                    $formatted_datetime = "a week ago";  
                } else {  
                    $formatted_datetime = "$weeks weeks ago";  
                }  
            }
            //Month  
            else if($months <= 12) {  
                if($months == 1) {
                    $formatted_datetime = "a month ago"; 
                } else {
                    $formatted_datetime = "$months months ago";
                } 
            } 
            //Years
            else {
                if($years == 1) {
                    $formatted_datetime = "one year ago"; 
                } else {
                    $formatted_datetime = "$years years ago";
                }  
            }  
        }
        return $formatted_datetime;
    }


     /**
     * function is used get difference of current time and next reset time and return number of hours in string.
     *
     * @param $datetime (string): DateTime value
     * @param $timezone (string): Timezone
     *
     * @return (string) hours later string
     */
    public static function getQuotaResetHours($next_reset = null, $timezone = "GMT+00:00") {
        $hours = "";

        if (!empty($next_reset)) {
            $current_time = self::getDateTime(null, $timezone);
            $time_difference = $next_reset - $current_time;
            $hours     = round($time_difference / 3600);
        }
        return $hours;
    }
}