<?php
/**
 * QWERTY2  Accounts related data
 */
namespace App\Controllers;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Interop\Container\ContainerInterface;
use \App\Components\SHAppComponent;
use \App\Components\ModelValidationsComponent as Validator;
use \App\Components\StringComponent;
use \App\Components\DateTimeComponent;
use \App\Components\ErrorComponent;
use \App\Models\UserMaster;
use \App\Models\UserResources;
use \App\Models\PlanMaster;
use \App\Models\PaymentMethodMaster;
use \App\Models\AccountSubscriptionDetails;
use \App\Models\AccountBillingMaster;

class ManageAccountsController extends AppController {

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * List all data
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function lists(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $user_timezone = SHAppComponent::getUserTimeZone();

        // Get request parameters
        $params = $request->getQueryParams();

        // Set parameters
        $query_params = [
            "page" => 1,
            "per_page" => SHAppComponent::getValue("app_constant/DEFAULT_LIST_PER_PAGE"),
            "order_by" => "id",
            "order" => "DESC"
        ];

        if (!empty($params["page"])) {
            $query_params["page"] = (int) $params["page"];
        }
        if (!empty($params["per_page"])) {
            $query_params["per_page"] = (int) $params["per_page"];
        }
        if (!empty($params["order_by"])) {
            $query_params["order_by"] = trim($params["order_by"]);
        }
        if (!empty($params["order"])) {
            $query_params["order"] = trim($params["order"]);
        }
        if (!empty($params["query"])) {
            $query_params["query"] = SHAppComponent::prepareSearchText($params["query"]);
        }

        // Get data
        $model_account_billing_master = new AccountBillingMaster();

        try {
            $data = $model_account_billing_master->getUserAccountsData($query_params);
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        //$app_constants = SHAppComponent::getValue("app_constant");
        $default_timezone = \DEFAULT_TIMEZONE;
        $app_time_format = \APP_TIME_FORMAT;
        // Process data and prepare for output
        $output["total_records"] = $data["total_records"];
        $output["total_pages"] = $data["total_pages"];
        $output["current_page"] = $data["current_page"];
        $output["per_page"] = $data["per_page"];
        $output["rows"] = [];

        foreach ($data["rows"] as $row) {

            $created_date = 0;
            if($row["created"] > 0){
                $created_date = DateTimeComponent::convertDateTime($row["created"], true, $default_timezone, $user_timezone, $app_time_format);
            }
            $plan_start_date = 0;
            if($row["start_date"] > 0){
                $plan_start_date = DateTimeComponent::convertDateTime($row["start_date"], true, $default_timezone, $user_timezone, $app_time_format);
            }
            $plan_end_date = 0;
            if($row["end_date"] > 0){
                $plan_end_date = DateTimeComponent::convertDateTime($row["end_date"], true, $default_timezone, $user_timezone, $app_time_format);
            }
            
            $row_data = [
                "user_id" => $row["user_id"],
                "account_subscription_id" => StringComponent::encodeRowId($row["as_id"]),
                "admin_email" => $row["admin_email"],
                "ac_number" => $row["ac_number"],
                "created_at" => $created_date,
                "current_plan" => $row["plan_name"],
                "plan_start_date" => $plan_start_date,
                "plan_end_date" => $plan_end_date
            ];

            $output["rows"][] = $row_data;
        }
        
        return $response->withJson($output, 200);
    }

    /**
     * Update existing record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        
        $user_timezone = SHAppComponent::getUserTimeZone();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $abm_id = StringComponent::decodeRowId($id);
        // Get request parameters
        $request_params = $request->getParsedBody();
   
        $request_data = [];

        if (isset($request_params["plan_id"])) {
            $request_data["plan_id"] = StringComponent::decodeRowId($request_params["plan_id"]);
        }
        if (isset($request_params["plan_start_date"])) {
            $request_data["plan_start_date"] = $request_params["plan_start_date"];
        }
        if (isset($request_params["plan_end_date"])) {
            $request_data["plan_end_date"] = $request_params["plan_end_date"];
        }
        if (isset($request_params["payment_method_id"])) {
            $request_data["payment_method_id"] = StringComponent::decodeRowId($request_params["payment_method_id"]);
        } else {
            $request_data["payment_method_id"] = "";
        }
        if (isset($request_params["account_subscription_id"])) {
            $request_data["account_subscription_id"] = StringComponent::decodeRowId($request_params["account_subscription_id"]);
        }
        $current_subscription_id = $request_data["account_subscription_id"];
        $default_timezone = \DEFAULT_TIMEZONE;
        $app_time_format = \APP_TIME_FORMAT;
        try {
            $model_account_subscription = new AccountSubscriptionDetails();

            if (!empty($request_data["plan_start_date"])) {
                $plan_start_date = DateTimeComponent::convertDateTime($request_data["plan_start_date"], false, $user_timezone, $default_timezone, "");
            } else {
               $plan_start_date = 0; 
            }
            if (!empty($request_data["plan_id"])) {
                $plan_id = $request_data["plan_id"];
                
            } else {
                $plan_id = 1;
            }

            if(!empty($request_data["plan_end_date"])) {
                $plan_end_date = DateTimeComponent::convertDateTime($request_data["plan_end_date"], false, $user_timezone, $default_timezone, "");
            } else {
               $plan_end_date = 0; 
            }

            $save_data = [
                "id" => $current_subscription_id,
                "plan_id" => $plan_id,
                "start_date" => $plan_start_date,
                "end_date" => $plan_end_date,
                "payment_method_id" => $request_data["payment_method_id"],
                "modified" => DateTimeComponent::getDateTime()
            ];
            $model_account_subscription->save($save_data);
        
        } catch(\Exception $e) {
           // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Fetch plan related info
        try {
            $plan_master_model = new PlanMaster();

            // skip campaign if already sequence prepared
            $condition_of_plan_config = [
                "fields" => [
                    "configuration"
                ],
                "where" => [
                    ["where" => ["id", "=", $plan_id]]
                ]
            ];
            $plan_configuration = $plan_master_model->fetch($condition_of_plan_config);
       
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        try {
            $model_account_billing_master = new AccountBillingMaster();

            $billing_data_save = [
                "id" => $abm_id,
                "plan_id" => $plan_id,
                "configuration" => $plan_configuration["configuration"],
                "current_subscription_id" => $current_subscription_id,
                "modified" => DateTimeComponent::getDateTime()
            ];
            $acc_billing_update = $model_account_billing_master->save($billing_data_save);
            if ($acc_billing_update !== false) {
                $output["message"] = "Account details updated successfully.";    
            }
            
        } catch(\Exception $e) {
           // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * view api for account subcription data
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function view(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
        
        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        
        $user_timezone = SHAppComponent::getUserTimeZone();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");
        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $abm_id = StringComponent::decodeRowId($id);
        
        try {
            $model_account_billing_master = new AccountBillingMaster();
            
            $condition = [
                "fields" => [
                    "abm.id",
                    "abm.plan_id",
                    "am.ac_number",
                    "um.email as admin_email",
                    "pm.id as plan_id",
                    "pm.name as current_plan",
                    "asd.id as as_id",
                    "asd.payment_method_id",
                    "asd.start_date",
                    "asd.end_date"
                ],
                "where" => [
                    ["where" => ["abm.id", "=", $abm_id]]
                ],
                "join" => [
                    "account_master",
                    "user_master",
                    "plan_master",
                    "account_subscription_details",
                    "payment_method_master_left"
                ]
            ];
            $acc_subscription = $model_account_billing_master->fetch($condition);
            
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        
        // Fetch plan related info
        try {
            $plan_master_model = new PlanMaster();

            $other_values_plan = [
                "active" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                "order_by" => "mode",
                "order" => "ASC"
            ];
            $data = $plan_master_model->getAllPlanData($other_values_plan);
            
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        
        foreach ($data["rows"] as $row) {
            $row_data = [
                "plan_id" => StringComponent::encodeRowId($row["id"]),
                "plan_code" => trim($row["code"]),
                "plan_name" => trim($row["name"])
            ];

            $output["plan_data"][] = $row_data;
        }

        try {
            $model_plan_method_master = new PaymentMethodMaster();

            $condition_plan_method = [
                "fields" => [
                    "pmm.id",
                    "pmm.code",
                    "pmm.name"
                ],
                "where" => [
                    ["where" => ["pmm.status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                ]
            ];

            $plan_methods = $model_plan_method_master->fetchAll($condition_plan_method);
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        
        foreach ($plan_methods as $plan_method_row) {
            $plan_method_row_data = [
                "method_id" => StringComponent::encodeRowId($plan_method_row["id"]),
                "method_code" => trim($plan_method_row["code"]),
                "method_name" => trim($plan_method_row["name"])
            ];

            $output["plan_methods"][] = $plan_method_row_data;
        }
        //prepare data for output response
        $default_timezone = \DEFAULT_TIMEZONE;
        $app_time_format = \APP_TIME_FORMAT;
        $time_format = "Y-m-d H:i:s";

        $plan_start_date = 0;
        
        if ($acc_subscription["start_date"] > 0) {
            $plan_start_date = DateTimeComponent::convertDateTime($acc_subscription["start_date"], true, $default_timezone, $user_timezone, $time_format);
        }
        
        $plan_end_date = 0;
        
        if ($acc_subscription["end_date"] > 0) {
            $plan_end_date = DateTimeComponent::convertDateTime($acc_subscription["end_date"], true, $default_timezone, $user_timezone, $time_format);
        }
        
        $pay_method = "";
        
        if (!empty($acc_subscription["method_name"])) {
            $pay_method = $acc_subscription["method_name"];
        }

        $output_data = [
            "ac_number" => $acc_subscription["ac_number"],
            "admin_email" => $acc_subscription["admin_email"],
            "plans" => $output["plan_data"],
            "current_plan_id" => StringComponent::encodeRowId($acc_subscription["plan_id"]),
            "current_plan" => $acc_subscription["current_plan"],
            "payment_method_id" => StringComponent::encodeRowId($acc_subscription["payment_method_id"]),
            "plan_start_date" => $plan_start_date,
            "plan_end_date" => $plan_end_date,
            "payment_methods" => $output["plan_methods"],
            "account_subscription_id" => StringComponent::encodeRowId($acc_subscription["as_id"])
        ];

        $output = $output_data;

        return $response->withJson($output, 200);
    }
}
