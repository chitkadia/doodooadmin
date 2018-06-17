<?php
/**
 * Webhooks related functionality
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
use \App\Components\LoggerComponent;
use \App\Models\UserMaster;
use \App\Models\UserResources;
use \App\Models\UserAuthenticationTokens;
use \App\Models\AccountSubscriptionDetails;


class AdminController extends AppController {

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
        
        if (!empty($params["status"])) {
            $status = SHAppComponent::getValue("app_constant/" . trim($params["status"]));

            if (!empty($status)) {
                $query_params["status"] = $status;
            }
        }
        if (!empty($params["query"])) {
            $query_params["query"] = SHAppComponent::prepareSearchText($params["query"]);
        }

        // Get data
        $model_user_master = new UserMaster();

        // Other values for condition
        $other_values = [
            "inactive" => SHAppComponent::getValue("app_constant/STATUS_INACTIVE"),
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

         try {
            $data = $model_user_master->getUserListData($query_params, $other_values);
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

       
        $app_constants = SHAppComponent::getValue("app_constant");
        $user_timezone = SHAppComponent::getUserTimeZone();        
        // Process data and prepare for output
        $output["total_records"] = $data["total_records"];
        $output["total_pages"] = $data["total_pages"];
        $output["current_page"] = $data["current_page"];
        $output["per_page"] = $data["per_page"];
        $output["rows"] = [];

        foreach ($data["rows"] as $row) {
           $status = array_search($row["status"], $app_constants);
           $list_name = "";
            if(!empty($row["first_name"]) || !empty($row["last_name"])){
                $list_name = $row["first_name"]." ".$row["last_name"];
            }
           $last_active_list = "";
           if($row["last_login"] > 0){
            $last_active_list = DateTimeComponent::convertDateTime($row["last_login"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
           }
            $row_data = [
                "user_id" => $row["id"],
                "name" => $list_name,
                "email" => $row["email"],
                "ac_number" => $row["ac_number"],
                "signup_date" => DateTimeComponent::convertDateTime($row["created"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT),
                "last_active" => $last_active_list,
                "status" => $status,
                "current_plan" => $row["plan_name"]
            ];
            $output["rows"][] = $row_data;
        }
        
        return $response->withJson($output, 200);
    }

    /**
     * View user nformation data for view
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function viewByid(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
        
        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        // Check if record is valid
        $model_user_master = new UserMaster();

        try {

            $user_data = $model_user_master->getUserViewById($id);
            
            
            if (!$user_data) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

            $app_constants = SHAppComponent::getValue("app_constant");
            $user_timezone = SHAppComponent::getUserTimeZone();

                $row_data = [];
                $user_info_name = "";
                if(!empty($user_data["first_name"]) || !empty($user_data["last_name"])){
                    $user_info_name = $user_data["first_name"]." ".$user_data["last_name"];
                }

                $last_active = "";
                if($user_data["last_login"] > 0){
                    $last_active = DateTimeComponent::convertDateTime($user_data["last_login"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
                }
                $invited_at = "";
                if($user_data["invited_at"] > 0){
                  $invited_at = DateTimeComponent::convertDateTime($user_data["invited_at"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);  
                }
                $joined_at = "";
                if($user_data["joined_at"] > 0){
                    $joined_at = DateTimeComponent::convertDateTime($user_data["joined_at"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
                }
                    $row_data = [
                        "name" => $user_info_name,
                        "signup_date" => DateTimeComponent::convertDateTime($user_data["created"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT),
                        "last_active" => $last_active,
                        "verified" => $user_data["verified"],
                        "invited_at" => $invited_at,
                        "joined_at" => $joined_at
                    ];
                    $output = $row_data;
                    
                    $output["member_rows"] = [];
                    
                    foreach ($user_data["member_row"] as $row) {
                        $last_login = "";

                        if($row["last_login"] > 0){
                            $last_login = DateTimeComponent::convertDateTime($row["last_login"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
                        }
                        $view_name = "";
                        if(!empty($row["first_name"]) || !empty($row["last_name"])){
                            $view_name = $row["first_name"]." ".$row["last_name"];
                        }

                        $status = array_search($row["status"], $app_constants);
                        $data_member_row = [
                            "user_id" => $row["id"],
                            "name" => $view_name,
                            "user_type" => $row["user_type_id"],
                            "email" => $row["email"],
                            "ac_number" => $row["ac_number"],
                            "ac_id" => $row["account_id"],
                            "company_name" => $row["company_name"],
                            "signup_date" => DateTimeComponent::convertDateTime($row["created"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT),
                            "last_active" => $last_login,
                            "status" => $status,
                            "current_plan" => $row["plan_name"]
                        ];

                        $output["member_rows"][] = $data_member_row;
                    }
            
            
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        
        return $response->withJson($output, 200);
    }

    /**
     * View user information data for view
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function makeAdminLogin(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
        
        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }
    
        $source_id = SHAppComponent::getRequestSource();
        
        $auth_token = $this->generateAuthToken($id, $source_id);
        
        if (empty($auth_token)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $output["auth_token"] = $auth_token;
        return $response->withJson($output, 200);
    }

    /**
     * Generate user authorization token
     *
     * @param $user_id (integer): User Id
     * @param $source_id (integer): Source Id
     *
     * @return (string) Authorization token
     */
    private function generateAuthToken($user_id, $source_id) {
        $user_id = (int) $user_id;
        $source_id = (int) $source_id;
        $status_active = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
        $auth_token = "";

        try {
            // Fetch user resources
            $model_user_resources = new UserResources();

            $condition = [
                "fields" => [
                    "rm.api_endpoint"
                ],
                "where" => [
                    ["where" => ["ur.status", "=", $status_active]],
                    ["where" => ["ur.user_id", "=", $user_id]],
                    ["where" => ["rm.status", "=", $status_active]]
                ],
                "join" => [
                    "resource_master"
                ]
            ];
            $data = $model_user_resources->fetchAll($condition);

            $user_resources_array = [];
            foreach ($data as $resource) {
                $temp_arr = json_decode($resource["api_endpoint"], true);
                $cnt = count($temp_arr);

                for ($i = 0; $i < $cnt; $i++)
                    $user_resources_array[$temp_arr[$i]] = 1;
            }

            // Generate token
            $model_user_authentication = new UserAuthenticationTokens();

            $generated_at = DateTimeComponent::getDateTime();
            $expires_at = $generated_at + AUTH_TOKEN_EXPIRE_INTERVAL_ADMIN;
            $token = StringComponent::encodeRowId([$user_id, $source_id, $generated_at]);
            $user_resources = json_encode($user_resources_array);

            $save_data = [
                "user_id" => $user_id,
                "source_id" => $source_id,
                "token" => $token,
                "generated_at" => $generated_at,
                "expires_at" => $expires_at,
                "user_resources" => $user_resources
            ];

            if ($model_user_authentication->save($save_data) !== false) {
                $auth_token = $token;

                // Update user last login date time
                $model_user = new UserMaster();

                $save_data = [
                    "id" => $user_id,
                    "last_login" => DateTimeComponent::getDateTime(),
                    "modified" => DateTimeComponent::getDateTime()
                ];

                $saved = $model_user->save($save_data);
            }

        } catch(\Exception $e) {}

        return $auth_token;
    }


   /**
     * Expire user authorization token(s)
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function expireAuthToken(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

       // Get request parameters
        $request_params = $request->getParsedBody();

        if (isset($request_params["user_id"])) {
            $request_data["user_id"] = $request_params["user_id"];    
        }

        if (isset($request_params["account_id"])) {
            $request_data["account_id"] = $request_params["account_id"];
        }
        
        $model_user_master = new UserMaster();
        
        if (!empty($request_data["account_id"])) {
            if (!empty($request_data["user_id"])) {
                // fetch all user id's from account id
                try {
                    $condition = [
                        "fields" => [
                            "um.id"
                        ],
                        "where" => [
                            ["where" => ["um.account_id", "=", $request_data["account_id"]]]
                        ]
                    ];
                    $user_data = $model_user_master->fetch($condition);

                } catch (\Exception $e) {
                   // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

                if (!empty($user_data["id"])) {
                    $output = $this->setExpire($user_data["id"]);
                }

            } else {
                // fetch all user id's from account id
                try {
                    $condition = [
                        "fields" => [
                            "um.id"
                        ],
                        "where" => [
                            ["where" => ["um.account_id", "=", $request_data["account_id"]]]
                        ]
                    ];
                    $user_ids = $model_user_master->fetchAll($condition);
                    $number_of_records = count($user_ids);
                
                } catch (\Exception $e) {
                   // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

                for ($i=0; $i < $number_of_records; $i++) { 
                    $user_id = $user_ids[$i]['id'];
                    
                    $output = $this->setExpire($user_id);
                }
            }
        }

        return $response->withJson($output, 200);
    }

    /**
     * Generate user authorization token
     *
     * @param $user_id (integer): User Id
     * @param $source_id (integer): Source Id
     *
     * @return (string) Authorization token
     */
    private function setExpire($user_id) {
        $output = [];
        $model_user_authentication_tokens = new UserAuthenticationTokens();
        
        $user_id = (int) $user_id;
        
        try {
            $data_to_update = [
                "expires_at"  => DateTimeComponent::getDateTime()
            ];
            $condition = [
                "where" => [
                    ["where" => ["user_id", "=", $user_id]]
                ]
            ];

            $saved = $model_user_authentication_tokens->update($data_to_update, $condition);

            $output["message"] = "Account Token has been expired successfully";
        
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");  
        }

        return $output;
    }

}