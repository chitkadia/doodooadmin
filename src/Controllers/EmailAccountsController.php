<?php
/**
 * Email accounts related functionality
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
use \App\Components\Mailer\SMTPComponent;
use \App\Models\AccountSendingMethods;
use \App\Models\EmailMaster;
use \App\Models\UserMaster;
use \App\Models\CampaignMaster;

class EmailAccountsController extends AppController {

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
        if (!empty($params["status"])) {
            $status = SHAppComponent::getValue("app_constant/" . trim($params["status"]));

            if ($status != "") {
                $query_params["status"] = $status;
            }
        }
        if (!empty($params["type"])) {
            if ( strtolower($params["type"]) != "all" ) {
                $type = SHAppComponent::getValue("email_sending_method/" . trim($params["type"]));

                if (!empty($type)) {
                    $query_params["type"] = $type;
                }
            }
        }
        if (!empty($params["query"])) {
            $query_params["query"] = SHAppComponent::prepareSearchText($params["query"]);
        }

        // Other values for condition
        $other_values = [
            "user_id" => $logged_in_user,
            "account_id" => $logged_in_account,
            "is_owner" => $is_owner,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
            "outlook_flag_yes" => SHAppComponent::getValue("app_constant/FLAG_YES")
        ];

        // Get data
        $model_account_sending_methods = new AccountSendingMethods();

        try {
            $data = $model_account_sending_methods->getListData($query_params, $other_values);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $app_constants = SHAppComponent::getValue("app_constant");
        $sending_methods = SHAppComponent::getValue("email_sending_method");
		
        // Process data and prepare for output
        $output["total_records"] = $data["total_records"];
        $output["total_pages"] = $data["total_pages"];
        $output["current_page"] = $data["current_page"];
        $output["per_page"] = $data["per_page"];
        $output["rows"] = [];
		
        foreach ($data["rows"] as $row) {
            $status = array_search($row["status"], $app_constants);
            $type = array_search($row["email_sending_method_id"], $sending_methods);
            
			if ($row["is_default"] == SHAppComponent::getValue("app_constant/FLAG_YES")) {
				$is_defaut = "FLAG_YES";
			} else {
				$is_defaut = "FLAG_NO";
			}

            if ( $row["last_connected_at"] > 0 ) {
                $last_connected_at = DateTimeComponent::convertDateTime($row["last_connected_at"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
            }
            
            $next_reset = 0;
            if ( $row["next_reset"] > 0 ) {
                $next_reset = DateTimeComponent::convertDateTime($row["next_reset"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
            }

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "name" => $row["name"],
                "from_name" => $row["from_name"],
                "from_email" => $row["from_email"],
                "created_by" => trim($row["first_name"]),
                "created_by_id" => StringComponent::encodeRowId($row["user_id"]),
                "type" => $type,
                "status" => $status,
                "mail_success" => $row["total_mail_sent"] - $row["total_mail_failed"],
                "mail_fail" => $row["total_mail_failed"],
                "mail_bounce" => $row["total_mail_bounced"],
                "public" => (bool) $row["public"],
                "credit_limit" => $row["credit_limit"],
                "next_reset" => $next_reset,
                "connection_status" => (bool) $row["connection_status"],
                "last_connected_at" => $last_connected_at,
                "last_connected_at_timestamp" => $row["last_connected_at"],
				"is_default" => $is_defaut
            ];

            if (!empty($row["last_error"])) {
                $row_data["last_error"] = strip_tags($row["last_error"]);
            }

            $output["rows"][] = $row_data;
        }

        return $response->withJson($output, 200);
    }

    /**
     * Create new record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
		$headers_data = $request->getHeaders();
		$X_SH_Source = $headers_data["HTTP_X_SH_SOURCE"][0]; // get X-SH-Source
		
        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $logged_in_source = SHAppComponent::getRequestSource();
        $additional_seats = SHAppComponent::getEmailaccountSeat();

        // Get request parameters
        $request_params = $request->getParsedBody();

        $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");
        $flag_no = SHAppComponent::getValue("app_constant/FLAG_NO");
        $status_delete = SHAppComponent::getValue("app_constant/STATUS_DELETE");
        $sending_method_smtp = SHAppComponent::getValue("email_sending_method/SMTP");
        $current_datetime = DateTimeComponent::getDateTime();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["name"])) {
            $request_data["name"] = $request_params["name"];
        }
        if (isset($request_params["from_name"])) {
            $request_data["from_name"] = $request_params["from_name"];
        }
        if (isset($request_params["from_email"])) {
            $request_data["from_email"] = $request_params["from_email"];
        }
        if (isset($request_params["host"])) {
            $request_data["host"] = $request_params["host"];
        }
        if (isset($request_params["username"])) {
            $request_data["username"] = $request_params["username"];
        }
        if (isset($request_params["password"])) {
            $request_data["password"] = $request_params["password"];
        }
        if (isset($request_params["encryption"])) {
            $request_data["encryption"] = $request_params["encryption"];
        }
        if (isset($request_params["port"])) {
            $request_data["port"] = $request_params["port"];
        }
        if (isset($request_params["smtp_provider"])) {
            $request_data["smtp_provider"] = $request_params["smtp_provider"];
        }

        $smtp_host_name = trim($request_data["host"]);
        $smtp_user_name = trim($request_data["username"]);
        $smtp_password = trim($request_data["password"]);
        $smtp_port = trim($request_data["port"]);
        $smtp_encryption = trim($request_data["encryption"]);
        $smtp_from_name = trim($request_data["from_name"]);
        $smtp_from_email = trim($request_data["from_email"]);

        // Validate request
        $request_validations = [
            "name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "from_name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "from_email" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY],
                ["type" => Validator::FIELD_EMAIL]
            ],
            "host" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "username" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "password" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "encryption" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "port" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY],
                ["type" => Validator::FIELD_NUMBER]
            ],
            "smtp_provider" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ]
        ];
        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        // Check if any email account is connected and is default
        $model_account_sending_methods = new AccountSendingMethods();

        $payload = [
            "user_id" => $logged_in_user,
            "account_id" => $logged_in_account,
            "default_check" => true
        ]; 

        $result = $model_account_sending_methods->checkDefaultEmailAccount($payload);
        $default_exist = false;

        if (!empty($result["id"])) {
            $default_exist = true;
        }

        //Check if any additional seats available
        if ($default_exist) {
            $payload["default_check"] = false;
            $result = $model_account_sending_methods->checkDefaultEmailAccount($payload);
            
            if (isset($result["total_count"]) && $result["total_count"] >= $additional_seats) {
                return ErrorComponent::outputError($response, "api_messages/EA_SEATS_NOT_AVAILABLE");
            }
        }

        // Check if user is already saved
        try {
            $condition = [
                "fields" => [
                    "asm.id"
                ],
                "where" => [
                    ["where" => ["asm.account_id", "=", $logged_in_account]],
                    ["where" => ["asm.email_sending_method_id", "=", $sending_method_smtp]],
                    ["where" => ["asm.from_email", "=", $smtp_from_email]],
                    ["where" => ["asm.status", "<>", $status_delete]]
                ]
            ];
            
            $row = $model_account_sending_methods->fetch($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if (!empty($row["id"])) {
            return ErrorComponent::outputError($response, "api_messages/ACCOUNT_ALREADY_CONNECTED");
        }
				
        try {
            $model_user_master = new UserMaster();
            $user_email = "";
            $user = $model_user_master->getUserById($logged_in_user, $logged_in_account);
            
            if (!empty($user)) {
                $user_email = $user["email"];
            }

            // Send test mail to check SMTP connection
            $info["smtp_details"]["host"] = $host_name;
            $info["smtp_details"]["username"] = $smtp_user_name;
            $info["smtp_details"]["password"] = $smtp_password;
            $info["smtp_details"]["port"] = $smtp_port;
            $info["smtp_details"]["encryption"] = $smtp_encryption;

            $info["to"] = trim($user_email);
            $info["cc"] = "";
            $info["bcc"] = "";
            $info["subject"] = "[Test Email] SMTP setting test by SalesHandy";
            $info["content"] = "You have successfully connnected your SMTP with SalesHandy."."<br />"."Host Name: ".$smtp_host_name;

            $info["from_email"] = $smtp_from_email;
            $info["from_name"] = $smtp_from_name;

            $result = SMTPComponent::mailSendUserSmtp($info);

            if (isset($result["message"]) && $result["message"] == "Message sent successfully!") {
                $flag_status = $flag_yes;
                $error_msg = "";
                $last_connected_at = $current_datetime;
            } else {
                $flag_status = $flag_no;
                $error_msg = $result["message"];
                $last_connected_at = $current_datetime;
            }

            $payload = [
                "host" => $smtp_host_name,
                "username" => $smtp_user_name,
                "password" => $smtp_password,
                "encryption" => $smtp_encryption,
                "port" => $smtp_port,
                "smtp_provider" => trim($request_data["smtp_provider"])

            ];

            $save_data = [
                "account_id" => $logged_in_account,
                "user_id" => $logged_in_user,
                "email_sending_method_id" => $sending_method_smtp,
                "source_id" => $logged_in_source,
                "name" => trim($request_data["name"]),
                "from_name" => $smtp_from_name,
                "from_email" => $smtp_from_email,
                "payload" => json_encode($payload),
                "connection_status" => $flag_status,
                "connection_info" => $error_msg,
                "last_connected_at" => $last_connected_at,
                "last_error" => $error_msg,
                "created" => $current_datetime,
                "modified" => $current_datetime
            ];

            if ($flag_status == $flag_no) {
                $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_INACTIVE");
            }
			
            if ($default_exist) {
                $save_data["is_default"] = $flag_no;
            } else {
                $save_data["is_default"] = $flag_yes;
            }
            			
			if ($X_SH_Source == "OUTLOOK_PLUGIN") {
				$save_data["is_outlook"] = $flag_yes;
			} else {
				$save_data["is_outlook"] = $flag_no;
			}

            if ($model_account_sending_methods->save($save_data) !== false) {
                $inserted_account_id = $model_account_sending_methods->getLastInsertId();

                $plan_id = SHAppComponent::getPlanId();
                $model_account_sending_methods->setQuota($plan_id, $inserted_account_id);

                $output["id"] = StringComponent::encodeRowId($inserted_account_id);
                $output["message"] = "Account created successfully.";
                $output["connected"] = true;

                if ($flag_status == $flag_no) {
                    $output["message"] = "Account created successfully, but there was an error connecting with your SMTP account.";
                    $output["connected"] = false;
                }

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 201);
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
        $is_owner = SHAppComponent::isAccountOwner();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");
        $flag_no = SHAppComponent::getValue("app_constant/FLAG_NO");
        $status_inactive = SHAppComponent::getValue("app_constant/STATUS_INACTIVE");
        $status_delete = SHAppComponent::getValue("app_constant/STATUS_DELETE");
        
        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_account_sending_methods = new AccountSendingMethods();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => $status_delete
            ];

            $valid = $model_account_sending_methods->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get request parameters (post)
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];

        if (isset($request_params["name"])) {
            $request_data["name"] = $request_params["name"];
        }
        if (isset($request_params["from_name"])) {
            $request_data["from_name"] = $request_params["from_name"];
        }
        if (isset($request_params["from_email"])) {
            $request_data["from_email"] = $request_params["from_email"];
        }
        if (isset($request_params["host"])) {
            $request_data["host"] = $request_params["host"];
        }
        if (isset($request_params["username"])) {
            $request_data["username"] = $request_params["username"];
        }
        if (isset($request_params["password"])) {
            $request_data["password"] = $request_params["password"];
        }
        if (isset($request_params["encryption"])) {
            $request_data["encryption"] = $request_params["encryption"];
        }
        if (isset($request_params["port"])) {
            $request_data["port"] = $request_params["port"];
        }
        if (isset($request_params["smtp_provider"])) {
            $request_data["smtp_provider"] = $request_params["smtp_provider"];
        }

        $smtp_host_name = trim($request_data["host"]);
        $smtp_user_name = trim($request_data["username"]);
        $smtp_password = trim($request_data["password"]);
        $smtp_port = trim($request_data["port"]);
        $smtp_encryption = trim($request_data["encryption"]);
        $smtp_from_name = trim($request_data["from_name"]);
        $smtp_from_email = trim($request_data["from_email"]);
        $current_datetime = DateTimeComponent::getDateTime();
        
        // Validate request
        $request_validations = [
            "name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "from_name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "from_email" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY],
                ["type" => Validator::FIELD_EMAIL]
            ],
            "host" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "username" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "password" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "encryption" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "port" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY],
                ["type" => Validator::FIELD_NUMBER]
            ],
            "smtp_provider" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ]
        ];
        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        // Check if user is already saved
        try {
            $condition = [
                "fields" => [
                    "asm.id"
                ],
                "where" => [
                    ["where" => ["asm.id", "<>", $row_id]],
                    ["where" => ["asm.account_id", "=", $logged_in_account]],
                    ["where" => ["asm.email_sending_method_id", "=", SHAppComponent::getValue("email_sending_method/SMTP")]],
                    ["where" => ["asm.from_email", "=", $smtp_from_email]],
                    ["where" => ["asm.status", "<>", $status_delete]]
                ]
            ];
            $row = $model_account_sending_methods->fetch($condition);
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if (!empty($row["id"])) {
            return ErrorComponent::outputError($response, "api_messages/ACCOUNT_ALREADY_CONNECTED");
        }
        
        // Save record
        try {
            $model_user_master = new UserMaster();
            $user_email = "";
            $user = $model_user_master->getUserById($logged_in_user, $logged_in_account);
            if (!empty($user)) {
                $user_email = $user["email"];
            }
            // Send test mail to check SMTP connection
            $info["smtp_details"]["host"] = $smtp_host_name;
            $info["smtp_details"]["username"] = $smtp_user_name;
            $info["smtp_details"]["password"] = $smtp_password;
            $info["smtp_details"]["port"] = $smtp_port;
            $info["smtp_details"]["encryption"] = $smtp_encryption;
            
            $info["to"] = trim($user_email);
            $info["cc"] = "";
            $info["bcc"] = "";
            $info["subject"] = "[Test Email] SMTP setting test by SalesHandy";
            $info["content"] = "You have successfully connnected your SMTP with SalesHandy."."<br />"."Host Name: ".$smtp_host_name;

            $info["from_email"] = $smtp_from_email;
            $info["from_name"] = $smtp_from_name;

            $result = SMTPComponent::mailSendUserSmtp($info);
            
            if(isset($result["message"]) && $result["message"] == "Message sent successfully!") {               
                $flag_status = $flag_yes;
                $error_msg = "";
                $last_connected_at = $current_datetime;
                $message = "";
            } else {
                $flag_status = $flag_no;
                $error_msg = $result["message"];
                $last_connected_at = $current_datetime;
                $message = "Email account not connected.";
            }


            $payload = [
                "host" => $smtp_host_name,
                "username" => $smtp_user_name,
                "password" => $smtp_password,
                "encryption" => $smtp_encryption,
                "port" => $smtp_port,
                "smtp_provider" => trim($request_data["smtp_provider"])
            ];

            $save_data = [
                "id" => $row_id,
                "name" => trim($request_data["name"]),
                "from_name" => $smtp_from_name,
                "from_email" => $smtp_from_email,
                "payload" => json_encode($payload),
                "connection_status" => $flag_status,
                "connection_info" => $error_msg,
                "last_connected_at" => $last_connected_at,
                "last_error" => $error_msg,
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($flag_status == $flag_no) {
                $save_data["status"] = $status_inactive;
            } else {
                $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
            }

            if ($model_account_sending_methods->save($save_data) !== false) {
                $output["message"] = "Account updated successfully.";
                $output["connected"] = true;

                if ($flag_status == $flag_no) {
                    $output["message"] = "Account updated successfully, but there was an error connecting with your SMTP account.";
                    $output["connected"] = false;
                }

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
            
            // Set campaign status to finish            
            try {                                
                if($save_data["status"] == $status_inactive){
                   
                    $result = $this->changeCampaignStatusForSendingMetod($logged_in_user, $row_id, SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH"), false);
                    if(!$result){
                        return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                    }
                }
                            
            } catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * Update email account for gmail
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function GmailAccountUpdate(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
       
        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_account_sending_methods = new AccountSendingMethods();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "type" => SHAppComponent::getValue("email_sending_method/GMAIL")
            ];

            $valid = $model_account_sending_methods->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get request parameters (post)
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];

        if (isset($request_params["name"])) {
            $request_data["name"] = $request_params["name"];
        }
        if (isset($request_params["from_name"])) {
            $request_data["from_name"] = $request_params["from_name"];
        }
        
        // Validate request
        $request_validations = [
            "name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "from_name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ]
        ];
        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        // Save record
        try {
            $save_data = [
                "id" => $row_id,
                "name" => trim($request_data["name"]),
                "from_name" => trim($request_data["from_name"]),
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($model_account_sending_methods->save($save_data) !== false) {
                $output["message"] = "Account updated successfully.";
                $output["connected"] = true;
            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200); 
    } 
    /**
     * View single record
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
        $is_owner = SHAppComponent::isAccountOwner();
        $user_timezone = SHAppComponent::getUserTimeZone();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_account_sending_methods = new AccountSendingMethods();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_sending_methods->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Fetch data
        try {
            $condition = [
                "fields" => [
                    "asm.id",
                    "asm.email_sending_method_id",
                    "asm.name",
                    "asm.from_name",
                    "asm.from_email",
                    "asm.payload",
                    "asm.incoming_payload",
                    "asm.status",
                    "asm.public",
                    "asm.connection_status",
                    "asm.last_connected_at",
                    "asm.last_error"
                ],
                "where" => [
                    ["where" => ["asm.id", "=", $row_id]]
                ]
            ];
            $row = $model_account_sending_methods->fetch($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $app_constants = SHAppComponent::getValue("app_constant");
        $sending_methods = SHAppComponent::getValue("email_sending_method");
        
        $status = array_search($row["status"], $app_constants);
        $type = array_search($row["email_sending_method_id"], $sending_methods);

        if ( $row["last_connected_at"] > 0 ) {
            $last_connected_at = DateTimeComponent::convertDateTime($row["last_connected_at"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
        }

        // Prepare output of data
        $output = [
            "id" => StringComponent::encodeRowId($row["id"]),
            "name" => $row["name"],
            "from_name" => $row["from_name"],
            "from_email" => $row["from_email"],
            "type" => $type,
            "status" => $status,
            "public" => (bool) $row["public"],
            "connection_status" => (bool) $row["connection_status"],
            "last_connected_at" => $last_connected_at,
            "last_connected_at_timestamp" => $row["last_connected_at"]
        ];

        if (!empty($row["last_error"])) {
            $output["last_error"] = $row["last_error"];
        }

        if ($row["email_sending_method_id"] == $sending_methods["SMTP"]) {
            $payload = json_decode($row["payload"], true);

            $output["host"] = $payload["host"];
            $output["username"] = $payload["username"];
            $output["password"] = $payload["password"];
            $output["encryption"] = $payload["encryption"];
            $output["port"] = $payload["port"];

            $output["smtp_provider"] = "other";
            if (isset($payload["smtp_provider"])) {
                $output["smtp_provider"] = $payload["smtp_provider"];
            }
        }

        return $response->withJson($output, 200);
    }

    /**
     * Copy single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function copy(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $logged_in_source = SHAppComponent::getRequestSource();
        $is_owner = SHAppComponent::isAccountOwner();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_account_sending_methods = new AccountSendingMethods();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_sending_methods->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Copy record
        try {
            // Fetch data
            $condition = [
                "fields" => [
                    "asm.email_sending_method_id",
                    "asm.name",
                    "asm.from_name",
                    "asm.from_email",
                    "asm.payload",
                    "asm.incoming_payload",
                    "asm.last_error",
                    "asm.public"
                ],
                "where" => [
                    ["where" => ["asm.id", "=", $row_id]]
                ]
            ];
            $row = $model_account_sending_methods->fetch($condition);

            // Save data
            $flag_no = SHAppComponent::getValue("app_constant/FLAG_NO");

            $save_data = [
                "account_id" => $logged_in_account,
                "user_id" => $logged_in_user,
                "email_sending_method_id" => $row["email_sending_method_id"],
                "source_id" => $logged_in_source,
                "name" => "Copy of " . $row["name"],
                "from_name" => $row["from_name"],
                "from_email" => $row["from_email"],
                "payload" => $row["payload"],
                "incoming_payload" => $row["incoming_payload"],
                "connection_status" => $flag_no,
                "connection_info" => "",
                "last_error" => "",
                "public" => $row["public"],
                "status" => SHAppComponent::getValue("app_constant/STATUS_INACTIVE"),
                "created" => DateTimeComponent::getDateTime(),
                "modified" => DateTimeComponent::getDateTime()
            ];
            
            if ($model_account_sending_methods->save($save_data) !== false) {
                $inserted_account_id = $model_account_sending_methods->getLastInsertId();

                $output["id"] = StringComponent::encodeRowId($inserted_account_id);
                $output["message"] = "Account copied successfully.";

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 201);
    }

    /**
     * Delete single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_account_sending_methods = new AccountSendingMethods();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_sending_methods->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Update status
        try {
            $save_data = [
                "id" => $row_id,
                "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "modified" => DateTimeComponent::getDateTime()
            ];
            if ($model_account_sending_methods->save($save_data) !== false) {
                $output["message"] = "Account deleted successfully.";

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
      
        // Set campaign status to finish            
        try { 
            $result = $this->changeCampaignStatusForSendingMetod($logged_in_user, $row_id, SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH"), true);           

            if(!$result){
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        //get scheduled emails and change status scheduled to draft
        try {
            $result = $this->getScheduledEmailstoDraft($logged_in_user, $row_id);
            if (!$result) {
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
        } catch (\Exception $e) {
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
 
        return $response->withJson($output, 200);
    }

    /**
     * Update status for single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function statusUpdate(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
       
        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_account_sending_methods = new AccountSendingMethods();

        try {
            // Other values for condition
            // $other_values = [
            //     "user_id" => $logged_in_user,
            //     "account_id" => $logged_in_account,
            //     "is_owner" => $is_owner,
            //     "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            // ];

            // $valid = $model_account_sending_methods->checkRowValidity($row_id, $other_values);
              // Fetch data
              $condition = [
                "fields" => [
                    "asm.id",
                    "asm.email_sending_method_id",
                    "asm.status"
                ],
                "where" => [
                    ["where" => ["asm.id", "=", $row_id]],
                    ["where" => ["asm.account_id", "=", $logged_in_account]],
                    ["where" => ["asm.user_id", "=", $logged_in_user]],
                    ["where" => ["asm.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ],
                "join" => [
                    "user_master"
                ]
            ];
         
            $account_sending_data = $model_account_sending_methods->fetch($condition);
       
            if (empty($account_sending_data)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get request parameters (post)
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["status"])) {
            $request_data["status"] = $request_params["status"];
        }

        // Validate request
        $request_validations = [
            "status" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ]
        ];
        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        // Valid status values
        $valid_status_values = [
            "STATUS_INACTIVE",
            "STATUS_ACTIVE",
            "STATUS_BLOCKED"
        ];
        if (!in_array($request_data["status"], $valid_status_values)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/INVALID_STATUS_VALUE");
        }

        $isSuccess = false;
        // Set value
        try {
            $save_data = [
                "id" => $row_id,
                "status" => SHAppComponent::getValue("app_constant/" . $request_data["status"]),
                "modified" => DateTimeComponent::getDateTime()
            ];

            if($account_sending_data["email_sending_method_id"] == SHAppComponent::getValue("email_sending_method/GMAIL")){
                $gmail_payload = [
                    "code" => "",
                    "access_token" => "",
                    "refresh_token" => "",
                    "name" => "",
                    "email" => "",
                    "hd" => "",
                    "picture" => ""
                ];
                $save_data["payload"] = json_encode($gmail_payload);
            }

            if ($model_account_sending_methods->save($save_data) !== false) {
                $output["message"] = "Status updated successfully.";
                $isSuccess = true;
            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Set campaign status to inactive
        try {            
            if($isSuccess && $request_data["status"] == "STATUS_INACTIVE"){
              
                $result = $this->changeCampaignStatusForSendingMetod($logged_in_user, $row_id, SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED"), false);                
                if(!$result){
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }
            } 
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        //get scheduled emails and change status scheduled to draft
        try {
            if($isSuccess && $request_data["status"] == "STATUS_INACTIVE") {
                $result = $this->getScheduledEmailstoDraft($logged_in_user, $row_id);
                if (!$result) {
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }
            }
        } catch (\Exception $e) {
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * Mark record as public (toggle)
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function markAsPublic(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_account_sending_methods = new AccountSendingMethods();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_sending_methods->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Toggle flag
        try {
            // Fetch data
            $condition = [
                "fields" => [
                    "asm.public"
                ],
                "where" => [
                    ["where" => ["asm.id", "=", $row_id]]
                ]
            ];
            $row = $model_account_sending_methods->fetch($condition);

            // Update flag
            $save_data = [
                "id" => $row_id,
                "public" => (int) !((bool) $row["public"]),
                "modified" => DateTimeComponent::getDateTime()
            ];
            if ($model_account_sending_methods->save($save_data) !== false) {
                $output["public"] = (bool) $save_data["public"];
                if ($output["public"]) {
                    $output["message"] = "Account set as public.";
                } else {
                    $output["message"] = "Account set as private.";
                }

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * Connect google account
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function connect(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        //Google login
        $request_origin_url = SHAppComponent::getRequestOrigin();
        $redirectUrl = $request_origin_url . "/mail-accounts/connect-verify";
        
        $client = new \Google_Client();
        $client->addScope("email");
        $client->addScope("profile");
        $client->addScope("https://mail.google.com");
        
        //$client->addScope("https://www.googleapis.com/auth/gmail.settings.basic"); 
        // to do : reference => https://developers.google.com/gmail/api/auth/scopes
        $client->setAuthConfig(\CLIENT_SECRET_PATH);
        $client->setRedirectUri($redirectUrl);
        $client->setAccessType('offline');
        $client->setIncludeGrantedScopes(true);
        $client->setApprovalPrompt('force');
        //create Google Service object
        $service = new \Google_Service_Oauth2($client);
        $authUrl = $client->createAuthUrl();

        $output = [
            "connect_url" => $authUrl
        ];

        return $response->withJson($output, 200);
    }

    /**
     * Create google account sending method
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function connectVerify(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $logged_in_source = SHAppComponent::getRequestSource();
        $additional_seats = SHAppComponent::getEmailaccountSeat();

        // Get request parameters
        $request_params = $request->getParsedBody();
        $code = $request_params["code"];

        //Google login
        $request_origin_url = SHAppComponent::getRequestOrigin();
        $redirectUrl = $request_origin_url . "/mail-accounts/connect-verify";
        
        $client = new \Google_Client();
        $client->addScope("email");
        $client->addScope("profile");
        $client->addScope("https://mail.google.com");
        
        /*$client->addScope("https://www.googleapis.com/auth/gmail.settings.basic");
         to do : 
         Manage your basic mail settings : View primary email address, View and manage primary Reply-To, display name and signature,    View "Send mail as" aliases  
         reference => https://developers.google.com/gmail/api/auth/scopes */

        $client->setAuthConfig(\CLIENT_SECRET_PATH);
        $client->setRedirectUri($redirectUrl);
        $client->setAccessType('offline');
        $client->setIncludeGrantedScopes(true);
        $client->setApprovalPrompt('force');
        //create Google Service object
        $service = new \Google_Service_Oauth2($client);
        $authUrl = $client->createAuthUrl();

        //Get google info
        $client->authenticate($code);
        $resp_data = $client->getAccessToken();
        $access_token = $resp_data["access_token"];
        $refresh_token = $resp_data["refresh_token"];

        $gpUserProfile = $service->userinfo->get();
        $gname = $gpUserProfile->name;
        $gemail = $gpUserProfile->email;
        $ghd = $gpUserProfile->hd;
        $gpicture = $gpUserProfile->picture;

        $model_account_sending_methods = new AccountSendingMethods();
        
        try {
            $condition = [
                "fields" => [
                    "asm.id",
                    "asm.status"                    
                ],
                "where" => [
                    ["where" => ["asm.account_id", "=", $logged_in_account]],
                    ["where" => ["asm.user_id", "=", $logged_in_user]],
                    ["where" => ["asm.email_sending_method_id", "=", SHAppComponent::getValue("email_sending_method/GMAIL")]],
                    ["where" => ["asm.from_email", "=", $gemail]],
                    ["where" => ["asm.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $email_account_data = $model_account_sending_methods->fetch($condition);
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Check if any email account is connected and is default
        $payload = [
            "user_id" => $logged_in_user,
            "account_id" => $logged_in_account,
            "default_check" => true
        ]; 

        $row_default_count = $model_account_sending_methods->checkDefaultEmailAccount($payload);
        $default_exist = false;

        if (!empty($row_default_count["id"]) && ($row_default_count["id"] != $email_account_data["id"])) {
            $default_exist = true;
        }

        //Check if any additional seats available
        if ($default_exist) {
            $payload["default_check"] = false;
            $row_default_count = $model_account_sending_methods->checkDefaultEmailAccount($payload);
            
            if (isset($row_default_count["total_count"]) && ($row_default_count["total_count"] >= $additional_seats) && (empty($email_account_data["id"]))) {
                return ErrorComponent::outputError($response, "api_messages/EA_SEATS_NOT_AVAILABLE");
            }
        }
        if (!empty($email_account_data["id"])) {
            $status = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
            if($email_account_data["status"] == $status) {
                return ErrorComponent::outputError($response, "api_messages/ACCOUNT_ALREADY_CONNECTED");
            }
        }

        try {
            $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");

            $payload = [
                "code" => $code,
                "access_token" => $access_token,
                "refresh_token" => $refresh_token,
                "name" => $gname,
                "email" => $gemail,
                "hd" => $ghd,
                "picture" => $gpicture
            ];

            if (!empty($email_account_data["id"])) {
                $save_data = [
                    "id" => $email_account_data["id"],
                    "from_email" => $gemail,
                    "payload" => json_encode($payload),
                    "status" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                    "incoming_payload" => "",
                    "connection_status" => $flag_yes,
                    "connection_info" => "",
                    "last_connected_at" => DateTimeComponent::getDateTime(),
                    "modified" => DateTimeComponent::getDateTime()
                ];
            } else {
                $save_data = [
                    "account_id" => $logged_in_account,
                    "user_id" => $logged_in_user,
                    "email_sending_method_id" => SHAppComponent::getValue("email_sending_method/GMAIL"),
                    "source_id" => $logged_in_source,
                    "name" => "Gmail Account",
                    "from_name" => $gname,
                    "from_email" => $gemail,
                    "payload" => json_encode($payload),
                    "incoming_payload" => "",
                    "connection_status" => $flag_yes,
                    "connection_info" => "",
                    "last_connected_at" => DateTimeComponent::getDateTime(),
                    "last_error" => "",
                    "created" => DateTimeComponent::getDateTime(),
                    "modified" => DateTimeComponent::getDateTime()
                ];
            }
            
            if ($default_exist) {
                $save_data["is_default"] = SHAppComponent::getValue("app_constant/FLAG_NO");
            } else {
                $save_data["is_default"] = SHAppComponent::getValue("app_constant/FLAG_YES");
            }

            if ($model_account_sending_methods->save($save_data) !== false) {

                if (empty($email_account_data["id"])) {
                    $inserted_account_id = $model_account_sending_methods->getLastInsertId();
                    $plan_id = SHAppComponent::getPlanId();

                    $model_account_sending_methods->setQuota($plan_id, $inserted_account_id);
                }
                $output["message"] = "Account connected successfully.";

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 201);
    }

    public function changeCampaignStatusForSendingMetod($logged_in_user, $row_id, $status, $isDelete){
        $query_params = [                    
            "campaigns_limit"   => ""
        ];
     
        // Other values for condition
        $other_values = [
            "active"                => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
            "campaign_finished"    => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH"),
            "campaign_paused"       => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED"),
            "user_id"               => $logged_in_user,
            "account_sending_method_id"=> $row_id
        ];

        $getPaused = true;
        if($status == SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED")){
            $getPaused = false;
        }
        // Get campaigns which are ACTIVE
        $model_campaign_master      = new CampaignMaster();
        
        $rows_campaigns = $model_campaign_master->getCampaignsToInactive($query_params, $other_values, $getPaused);
        
        for($i = 0; $i<count($rows_campaigns); $i++){
            $campaign_record = [
                "id"    => $rows_campaigns[$i]["id"],                                   
                "modified" => DateTimeComponent::getDateTime()
            ];
        
            if($status == SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED")){
                $campaign_record["status_message"]   = "Your account sending method is inactive.";
                $campaign_record["overall_progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED");
            }else if($status == SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH")){
                if($isDelete){
                    $campaign_record["status_message"]   = "Your campaign is finished. Because your account sending method was deleted.";
                }else{
                    $campaign_record["status_message"]   = "Your campaign is finished. Because your account sending method was updated.";
                }
                $campaign_record["overall_progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH");
              
            }
          
            if(!$model_campaign_master->save($campaign_record)){
                // Fetch error code & message and return the response
                return false;
            }
        }
        return true;        
    }
    /**
     * Function is used to change status of all schduled mail to draft
     * @param $user_id (Int) Id of user
     * @param $sending_method_id (Int) Id of sending method
     * 
     * @return (boolean) return true/false 
     */
    public function getScheduledEmailstoDraft($user_id = null, $sending_method_id = null) {
        $success = false;
        if (!empty($sending_method_id) && !empty($user_id)) {

            $update_data = [
                "status" => SHAppComponent::getValue("app_constant/STATUS_DRAFT"),
                "modified" => DateTimeComponent::getDateTime()
            ];

            $condition = [
                "where" => [
                    ["where" => ["account_sending_method_id", "=", (int) $sending_method_id ]],
                    ["where" => ["user_id", "=", (int) $user_id ]],
                    ["where" => ["status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE") ]],
                    ["where" => ["is_scheduled", "=", SHAppComponent::getValue("app_constant/FLAG_YES") ]],
                    ["where" => ["progress", "=", SHAppComponent::getValue("app_constant/PROGRESS_SCHEDULED") ]]
                ]
            ];

            try {
                $email_master_model = new EmailMaster();
                $email_master_model->update($update_data, $condition);
                $success = true;
            } catch(\Exception $e) {
                $success = false;
            }
        }

        return $success;
    }
    /**
     * Function is used to check plan restriction of user for email account
     */
    public function checkPlanRestriction(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [
            "is_allowed" => true,
            "message" => "this user is allow to connect new email account"
        ];

        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $logged_in_source = SHAppComponent::getRequestSource();
        $additional_seats = SHAppComponent::getEmailaccountSeat();

        // Check if any email account is connected and is default
        $model_account_sending_methods = new AccountSendingMethods();

        $payload = [
            "user_id" => $logged_in_user,
            "account_id" => $logged_in_account,
            "default_check" => true
        ]; 

        $result = $model_account_sending_methods->checkDefaultEmailAccount($payload);
        $default_exist = false;

        if (!empty($result["id"])) {
            $default_exist = true;
        }

        //Check if any additional seats available
        if ($default_exist) {
            $payload["default_check"] = false;
            $result = $model_account_sending_methods->checkDefaultEmailAccount($payload);
            
            if (isset($result["total_count"]) && $result["total_count"] >= $additional_seats) {
                $output["is_allowed"] = false;
                $output["message"] = "this user is not allow to connect new email account";
            }
        }

        return $response->withJson($output, 200);
    }
}
