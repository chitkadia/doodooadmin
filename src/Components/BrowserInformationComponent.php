<?php
/**
 * Library for get user Ip address, Location, Browser
 */
namespace App\Components;

use \App\Components\Browser;

class BrowserInformationComponent {

    const IP_GEOLOCATION_API_KEY = "2PypSAElGxnb82t";
    const GEOLOCATION_API_URL = "http://pro.ip-api.com/json/";
    /**
     * Function is used to get information like Browser, Location, OS platform
     */
    public static function getClientInformation() {
        $client_info = [];

        //get Location details
        $location_details =  self::getLocation();

        if (!empty($location_details) ) {

            $client_info = [
                "c" => $location_details["city"],
                "co" => $location_details["country"],
                "s" => $location_details["regionName"],
                "lat" => $location_details["lat"],
                "lon" => $location_details["lon"],
                "ip" => $location_details["query"]
            ];
        }
        //get Browser details
        $browser_details = self::getBrowserInfo();

        if(!empty($browser_details)) {

            $client_info["bn"] = $browser_details["browser_name"];
            $client_info["os"] = $browser_details["os_platform"];
            $client_info["ua"] = $browser_details["user_agent"];
        }

        return $client_info;
    }
    /**
     * Function is used to get the client ip address.
     */
    private static function getIpAddress() {
        $ip_address = "";
        
        if (getenv('HTTP_CF_CONNECTING_IP'))
            $ip_address = getenv('HTTP_CF_CONNECTING_IP');
        else if (getenv('HTTP_CLIENT_IP'))
            $ip_address = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ip_address = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ip_address = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ip_address = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
            $ip_address = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ip_address = getenv('REMOTE_ADDR');
        else
            $ip_address = 'UNKNOWN';

        return $ip_address;
    }
    /**
     * Function is used to get Location.
     */
    private static function getLocation() {

        $ip_address = self::getIpAddress();
        $url = self::GEOLOCATION_API_URL . "{$ip_address}?key=" . self::IP_GEOLOCATION_API_KEY;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $data = curl_exec($ch);
        curl_close($ch);

        $location_details = [];

        $details = json_decode($data, true);
        
        if ($details["status"] == "success") {
            $location_details = $details;
        }

        return $location_details;
    }
    /**
     * Function is used to get browser info.
     */
    private static function getBrowserInfo() {
        $browser_info = [];

        $browser = new Browser();

        $browser_info = [
            "browser_name" => $browser->getBrowser(),
            "os_platform" => $browser->getPlatform(),
            "browser_version" => $browser->getVersion(),
            "user_agent" => $browser->getUserAgent()
        ];

        return $browser_info;
    }
}