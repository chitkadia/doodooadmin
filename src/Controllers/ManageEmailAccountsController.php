<?php
/**
 * QWERTY2  Email Accounts related data
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
use \App\Models\AccountSendingMethods;

class ManageEmailAccountsController extends AppController {

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
        //$logged_in_user = SHAppComponent::getUserId();
        //$logged_in_account = SHAppComponent::getAccountId();
        
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
        $model_account_sending_methods = new AccountSendingMethods();

        try {
            $data = $model_account_sending_methods->getEmailAccountsData($query_params);
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        //$app_constants = SHAppComponent::getValue("app_constant");
        $sending_methods = SHAppComponent::getValue("email_sending_method");
        
        // Process data and prepare for output
        $output["total_records"] = $data["total_records"];
        $output["total_pages"] = $data["total_pages"];
        $output["current_page"] = $data["current_page"];
        $output["per_page"] = $data["per_page"];
        $output["rows"] = [];

        foreach ($data["rows"] as $row) {
            //$status = array_search($row["status"], $app_constants);
            $type = array_search($row["email_sending_method_id"], $sending_methods);
            
            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "user_id" => $row["user_id"],
                "ac_name" => $row["name"],
                "from_email" => $row["from_email"],
                "type" => $type,
                "current_quota" => $row["credit_limit"]
                
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
        
        //$user_timezone = SHAppComponent::getUserTimeZone();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $asm_id = StringComponent::decodeRowId($id);
        
        // Get request parameters
        $request_params = $request->getParsedBody();
   
        $request_data = [];

        if (isset($request_params["next_reset_date"])) {
            $request_data["next_reset_date"] = $request_params["next_reset_date"];
        }
        if (isset($request_params["credit_limit"])) {
            $request_data["credit_limit"] = $request_params["credit_limit"];
        }
    
        $default_timezone = \DEFAULT_TIMEZONE;
        $app_time_format = \APP_TIME_FORMAT;
        
        try {
            $model_account_sending_methods = new AccountSendingMethods();

            if (!empty($request_data["next_reset_date"])) {
                $next_reset_date = DateTimeComponent::convertDateTime($request_data["next_reset_date"], false, $default_timezone, $default_timezone, "");
            } else {
               $next_reset_date = 0; 
            }
            if (!empty($request_data["credit_limit"])) {
                $credit_limit = $request_data["credit_limit"];
            } else {
                $credit_limit = 0;
            }

            $save_data = [
                "id" => $asm_id,
                "next_reset" => $next_reset_date,
                "credit_limit" => $credit_limit,
                "modified" => DateTimeComponent::getDateTime()
            ];
            
            $data_to_update = $model_account_sending_methods->save($save_data);
            
            if ($data_to_update !== false) {
                $output["message"] = "Quota Details Updated Successfully.";    
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
        
        //$user_timezone = SHAppComponent::getUserTimeZone();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");


        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }
        $asm_id = StringComponent::decodeRowId($id);
        
        // Get data
        $model_account_sending_methods = new AccountSendingMethods();

        try {
            //$data = $model_account_sending_methods->getEmailAccountsData($other_values);
            $condition = [
                "fields" => [
                    "asm.id",
                    "asm.account_id",
                    "asm.user_id",
                    "asm.email_sending_method_id",
                    "asm.source_id",
                    "asm.name as eac_name",
                    "asm.from_name",
                    "asm.from_email",
                    "asm.payload",
                    "asm.incoming_payload",
                    "asm.connection_status",
                    "asm.connection_info",
                    "asm.last_connected_at",
                    "asm.last_error",
                    "asm.total_mail_sent",
                    "asm.total_mail_failed",
                    "asm.total_mail_bounced",
                    "asm.total_mail_replied",
                    "asm.public",
                    "asm.total_limit",
                    "asm.credit_limit",
                    "asm.last_reset",
                    "asm.next_reset",
                    "um.first_name",
                    "um.last_name",
                    "am.ac_number",
                    "asm.status",
                    "asm.is_default",
                    "asm.is_outlook",
                    "asm.created",
                    "asm.modified"
                ],
                "where" => [
                    ["where" => ["asm.id", "=", $asm_id]]
                ],
                "join" => [
                    "user_master",
                    "account_master"
                ]
            ];

        $data = $model_account_sending_methods->fetch($condition);
        
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
       
        //prepare data for output response
        $default_timezone = \DEFAULT_TIMEZONE;
        $app_time_format = \APP_TIME_FORMAT;
        $time_format = "Y-m-d H:i:s";

        $next_reset_at = 0;
        if ($data["next_reset"] > 0) {
            $next_reset_at = DateTimeComponent::convertDateTime($data["next_reset"], true, $default_timezone, $default_timezone, $time_format);
        }
        
        $last_reset_at = 0;
        if ($data["last_reset"] > 0) {
            $last_reset_at = DateTimeComponent::convertDateTime($data["last_reset"], true, $default_timezone, $default_timezone, $app_time_format);
        }
        
        $created_at = 0;
        if ($data["created"] > 0) {
            $created_at = DateTimeComponent::convertDateTime($data["created"], true, $default_timezone, $default_timezone, $app_time_format);
        }

        $modified_at = 0;
        if ($data["modified"] > 0) {
            $modified_at = DateTimeComponent::convertDateTime($data["modified"], true, $default_timezone, $default_timezone, $app_time_format);
        }

        $sending_methods = SHAppComponent::getValue("email_sending_method");
        $type = array_search($data["email_sending_method_id"], $sending_methods);
        $user_name = "";
        if(!empty($data["first_name"])){
            $user_name = $data["first_name"]." ".$data["last_name"];
        }

        //prepare text for output
        $output = [
            "esm_id" => $asm_id,
            "user_id" =>  $data["user_id"],
            "ac_number" => $data["ac_number"],
            "account_name" => $data["eac_name"],
            "from_name" => $data["from_name"],
            "from_email" => $data["from_email"],
            "type" => $type,
            "last_reset_at" => $last_reset_at,
            "next_reset_at" => $next_reset_at,
            "payload" => json_decode($data["payload"]),
            "total_mail_sent" => $data["total_mail_sent"],
            "total_mail_failed" => $data["total_mail_failed"],
            "total_mail_bounced" => $data["total_mail_bounced"],
            "total_mail_replied" => $data["total_mail_replied"],
            "public" => $data["public"],
            "total_limit" => $data["total_limit"],
            "credit_limit" => $data["credit_limit"],
            "user_name" => $user_name,
            "is_default" => $data["public"],
            "is_outlook" => $data["public"],
            "created_at" => $created_at,
            "modified_at" => $modified_at
        ];

        return $response->withJson($output, 200);
    }
}
