<?php
/**
 * Library for storing application variables, user access variables
 */
namespace App\Components;

class SHAppComponent {

    private static $_app_vars;
    private static $_user_id;
    private static $_account_id;
    private static $_plan_id;
    private static $_user_role_id;
    private static $_user_type_id;
    private static $_request_source_id;
    private static $_resources;
    private static $_plan_configuration;
    private static $_user_timezone;
    private static $_email_account_seats;

    public static function initialize() {
        // Load application configuration variables
        self::$_app_vars = json_decode(file_get_contents(APP_VARS_CONFIG_FILE), true);
    }

    /**
     * Get application variable
     *
     * @param $path (string): Path to access application variable
     *
     * @return (misc) Value at path, otherwise null
     */
    public static function getValue($path = null) {
        $return = null;

        if (!empty($path)) {
            $path_arr = explode("/", $path);
            $count_depth = count($path_arr);

            switch ($count_depth) {
                case 1:
                    if (isset(self::$_app_vars[$path_arr[0]])) {
                        $return = self::$_app_vars[$path_arr[0]];
                    }
                    break;

                case 2:
                    if (isset(self::$_app_vars[$path_arr[0]][$path_arr[1]])) {
                        $return = self::$_app_vars[$path_arr[0]][$path_arr[1]];
                    }
                    break;

                case 3:
                    if (isset(self::$_app_vars[$path_arr[0]][$path_arr[1]][$path_arr[2]])) {
                        $return = self::$_app_vars[$path_arr[0]][$path_arr[1]][$path_arr[2]];
                    }
                    break;

                default:
                    break;
            }
        }

        return $return;
    }

    /**
     * Get default admin customer user type
     *
     * @return (integer) User type
     */
    public static function getDefaultCustomerAdminUserType() {
        return self::getValue("user_type/CLIENT_ADMIN");
    }

    /**
     * Get default customer staff user type
     *
     * @return (integer) User type
     */
    public static function getDefaultCustomerStaffUserType() {
        return self::getValue("user_type/CLIENT_STAFF");
    }

    /**
     * Get default admin customer role
     *
     * @return (integer) User role
     */
    public static function getDefaultCustomerAdminRole() {
        return self::getValue("role/AC_OWNER");
    }

    /**
     * Get default customer staff user type
     *
     * @return (integer) User role
     */
    public static function getDefaultCustomerStaffRole() {
        return self::getValue("role/AC_TEAM_MEMBER");
    }

    /**
     * Get logged in user id
     *
     * @return (integer) User id
     */
    public static function getUserId() {
        return self::$_user_id;
    }

    /**
     * Set logged in user id
     *
     * @param $id (integer): User id
     */
    public static function setUserId($id) {
        self::$_user_id = (int) $id;
    }

    /**
     * Get logged in user's account id
     *
     * @return (integer) Account id
     */
    public static function getAccountId() {
        return self::$_account_id;
    }

    /**
     * Set logged in user's account id
     *
     * @param $id (integer): Account id
     */
    public static function setAccountId($id) {
        self::$_account_id = (int) $id;
    }
    /**
     * Set logged in user's plan_id
     *
     * @param $id (integer) : Plan id
     */
    public static function setPlanId($plan_id) {
        self::$_plan_id = (int) $plan_id;
    }
    /**
     * Get logged in user's plan_id
     *
     * @return $id (integer) : Plan id
     */
    public static function getPlanId() {
        return self::$_plan_id;
    }
    /**
     * Get logged in user's role id
     *
     * @return (integer) Role id
     */
    public static function getUserRole() {
        return self::$_user_role_id;
    }

    /**
     * Set logged in user's role id
     *
     * @param $id (integer): Role id
     */
    public static function setUserRole($id) {
        self::$_user_role_id = (int) $id;
    }

    /**
     * Get logged in user's type id
     *
     * @return (integer) Type id
     */
    public static function getUserType() {
        return self::$_user_type_id;
    }

    /**
     * Set logged in user's type id
     *
     * @param $id (integer): Type id
     */
    public static function setUserType($id) {
        self::$_user_type_id = (int) $id;
    }

    /**
     * Get request source
     *
     * @return (integer) Source id
     */
    public static function getRequestSource() {
        return self::$_request_source_id;
    }

    /**
     * Set request source
     *
     * @param $id (integer): Source id
     */
    public static function setRequestSource($id) {
        self::$_request_source_id = (int) $id;
    }

    /**
     * Get logged in user's allowed resources
     *
     * @return (array) Array of resources
     */
    public static function getUserResources() {
        return self::$_resources;
    }

    /**
     * Set logged in user's allowed resources
     *
     * @param $resources (array): Array of resources
     */
    public static function setUserResources($resources) {
        self::$_resources = (array) $resources;
    }

    /**
     * If logged in user is account owner
     *
     * @return (boolean) True if user is account owner, false otherwise
     */
    public static function isAccountOwner() {
        $customer_admin_type = self::getDefaultCustomerAdminUserType();

        return ($customer_admin_type == self::$_user_type_id);
    }

    /**
     * Get percentage value by input and compare values
     *
     * @param $input (number): Value against comparison
     * @param $max (number): Max value with wich comparison should be done
     *
     * @return (number) Ratio value
     */
    public static function getRatioValue($input=0, $max=0) {
        $return = 0;

        if (!empty($max)) {
            $return = round(($input * 100) / $max);
            $return = ($return > 100) ? 100 : $return;
        }

        return $return;
    }

    /**
     * Get request origin
     * Identify the domain url by origin
     *
     * @return (string) Origin domain url
     */
    public static function getRequestOrigin() {
        if (!empty($_SERVER["HTTP_ORIGIN"])) {
            return trim($_SERVER["HTTP_ORIGIN"]);

        } else {
            return \DEFAULT_API_ENDPOINT;
        }
    }
	
    /**
     * Set user's plan configuration array
     *
     * @param $configuration (array): Configuration Array
     */
    public static function setPlanConfiguration($configuration) {
        self::$_plan_configuration = (array) $configuration;
    }

    /**
     * Get user's plan configuration
     *
     * @return (array) User's Plan Configuration
     */
    public static function getPlanConfiguration() {
        return self::$_plan_configuration;
    }
    
    /**
     * Set user's time zone
     *
     * @param $timezone (string): timezone string
     */
    public static function setUserTimeZone($timezone) {
        self::$_user_timezone = (string) $timezone;
    }

    /**
     * Get user's time zone
     *
     * @return (string) User's Time Zone
     */
    public static function getUserTimeZone() {
        return self::$_user_timezone;
    }

    /**
     * Set additional email account seats
     *
     * @param $seats (int): additional email account seats
     */
    public static function setEmailaccountSeat($seats) {
        self::$_email_account_seats = (int) $seats;
    }

    /**
     * Get additional email account seats
     *
     * @return (int) email account additional seats
     */
    public static function getEmailaccountSeat() {
        return self::$_email_account_seats;
    }
    

    /**
     * Used to prepare serach text for special character
     *
     * @param $search_value (string) : Search text
     *
     * @return (string) : Return search text with slashes
     */
    public static function prepareSearchText($search_value) {
        $search_string = "";

        if (!empty($search_value)) { 
            $search_value = addslashes(trim($search_value));
            $search_value = str_replace("_", "\_", $search_value);
            $search_value = str_replace("%", "\%", $search_value);

            $search_string = $search_value;
        }

        return $search_string;
    }

}