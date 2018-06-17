<?php
/**
 * Login related functionality
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
use \App\Models\UserAuthenticationTokens;
use \App\Models\UserResources;
use \App\Models\UserSocialLogin;
use \App\Models\AccountMaster;
use \App\Models\PlanMaster;
use \App\Models\AccountSubscriptionDetails;
use \App\Models\AccountBillingMaster;
use \App\Models\RoleDefaultResources;
use \App\Models\AccountSendingMethods;
use \App\Models\UserSettings;

class LoginController extends AppController {

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Login
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function login(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get server parameters
        $server_params = $request->getServerParams();

        // Fetch credential from request
        $request_data = [];

        if (isset($server_params["PHP_AUTH_USER"])) {
            $request_data["email"] = $server_params["PHP_AUTH_USER"];
        }
        if (isset($server_params["PHP_AUTH_PW"])) {
            $request_data["password"] = $server_params["PHP_AUTH_PW"];
        }

        // Validate request
        $request_validations = [
            "email" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "password" => [
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

        // Check if user is valid
        $model_user = new UserMaster();

        try {
            $condition = [
                "fields" => [
                    "um.id",
                    "um.password",
                    "um.password_salt_key",
                    "um.verified",
                    "um.status AS user_status",
                    "am.status AS account_status"
                ],
                "where" => [
                    ["where" => ["um.email", "=", $request_data["email"]]]
                ],
                "join" => [
                    "account_master"
                ]
            ];
            $row = $model_user->fetch($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $status_active = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
        $status_delete = SHAppComponent::getValue("app_constant/STATUS_DELETE");
        $status_removed = SHAppComponent::getValue("app_constant/STATUS_REMOVED");
        $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");

        if (empty($row)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/LOGIN_NO_ACCOUNT");
        }

        if ($row["account_status"] == $status_delete) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/ACCOUNT_DELETED");
        }

        if ($row["account_status"] != $status_active) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/ACCOUNT_CLOSED");
        }

        if ($row["user_status"] == $status_delete) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/USER_DELETED");
        }

        if ($row["user_status"] != $status_active && $row["user_status"] != $status_removed) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/USER_ACCOUNT_NOT_ACTIVE");
        }

        $valid_password = StringComponent::decryptPassword($request_data["password"], $row["password_salt_key"], $row["password"]);
        if (!$valid_password) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/LOGIN_WRONG_CREDENTIALS");
        }

        $source_id = SHAppComponent::getRequestSource();
        if ($source_id == SHAppComponent::getValue("source/OUTLOOK_PLUGIN")) {
            if ($row["verified"] == SHAppComponent::getValue("app_constant/FLAG_NO")) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/OUTLOOK_USER_NOT_VERIFIED");
            } 
        }

        $auth_token = $this->generateAuthToken($row["id"], $source_id);
        if (empty($auth_token)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $output["auth_token"] = $auth_token;

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
    public function getLoginURL(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        //Google login
        $request_origin_url = SHAppComponent::getRequestOrigin();
        $redirectUrl = $request_origin_url . "/gmail-auth-callback";

        $source_id = SHAppComponent::getRequestSource();
        if ($source_id == SHAppComponent::getValue("source/CHROME_PLUGIN")) {
            $redirectUrl = \PLUGIN_AUTH_URI . "/index.php?method=GMAIL&source=CHROME_PLUGIN";
        }
        
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
     * Login with social media account
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function loginWith(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
        
        // Get request parameters
        $route = $request->getAttribute("route");
        $method = $route->getArgument("method");

        // Validate request
        if (empty($method)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        // Check if method is valid
        $valid_social_accounts = SHAppComponent::getValue("social_login");
        if (!isset($valid_social_accounts[$method])) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/INVALID_SOCIAL_ACCOUNT");
        }

        // Get request parameters
        $request_params = $request->getParsedBody();

        $code = $request_params["code"];
        $timezone = null;
        if (isset($request_params["timezone"])) {
            $timezone = $request_params["timezone"];     
        }
        

        //Google login
        $request_origin_url = SHAppComponent::getRequestOrigin();
        $redirectUrl = $request_origin_url . "/gmail-auth-callback";

        $source_id = SHAppComponent::getRequestSource();
        if ($source_id == SHAppComponent::getValue("source/CHROME_PLUGIN")) {
            $redirectUrl = \PLUGIN_AUTH_URI . "/index.php?method=GMAIL&source=CHROME_PLUGIN";
        }
        
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

        // Check if google code is present or not
        if (!isset($code) && empty($code)) {
            $output["cancel"] = true;
            return $response->withJson($output, 200);
        }    
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

        $name_arr = explode(" ", $gname);
        $first_name = isset($name_arr[0]) ? $name_arr[0] : "";
        $last_name = isset($name_arr[1]) ? $name_arr[1] : "";
        $signin_info = [
            "name" => $gname,
            "email" => $gemail
        ];

        // User model
        $model_user = new UserMaster();

        // Check if user with email already exists
        try {
            $condition = [
                "fields" => [
                    "um.id",
                    "um.account_id",
                    "um.source_id",
                    "um.verified",
                    "um.status AS user_status",
                    "am.status AS account_status",
                    "abm.plan_id"
                ],
                "where" => [
                    ["where" => ["um.email", "=", $gemail]]
                ],
                "join" => [
                    "account_master",
                    "account_billing_master"
                ]
            ];
            $data = $model_user->fetch($condition);

        } catch(\Exception $e) {
            
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // HTTP status code will be 200 for login, 201 for signup
        $output_status_code = 200;

        // If user is already exists
        if (!empty($data["id"])) {
            $status_active = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
            $status_delete = SHAppComponent::getValue("app_constant/STATUS_DELETE");
            $status_removed = SHAppComponent::getValue("app_constant/STATUS_REMOVED");
            $flag_no = SHAppComponent::getValue("app_constant/FLAG_NO");
            $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");

            if ($data["account_status"] == $status_delete) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/ACCOUNT_DELETED");
            }

            if ($data["account_status"] != $status_active) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/ACCOUNT_CLOSED");
            }

            if ($data["user_status"] == $status_delete) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/USER_DELETED");
            }

            if ($data["user_status"] != $status_active && $data["user_status"] != $status_removed) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/USER_ACCOUNT_NOT_ACTIVE");
            }

            // Verify user if not verified
            if ($data["verified"] == $flag_no) {
                try {
                    $save_data = [
                        "id" => $data["id"],
                        "verified" => $flag_yes,
                        "modified" => DateTimeComponent::getDateTime()
                    ];
                    $saved = $model_user->save($save_data);

                } catch (\Exception $e) {}
            }

        } else {
            $output_status_code = 201;

            $role_id = SHAppComponent::getDefaultCustomerAdminRole();
            $user_type_id = SHAppComponent::getDefaultCustomerAdminUserType();
            $source_id = SHAppComponent::getRequestSource();
            $account_id = null;
            $account_number = StringComponent::generateAccountNumber();

            $status_active = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
            $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");

            // Save user
            try {
                // Create new account
                $model_account = new AccountMaster();

                $save_data = [
                    "ac_number" => $account_number,
                    "source_id" => $source_id,
                    "created" => DateTimeComponent::getDateTime(),
                    "modified" => DateTimeComponent::getDateTime()
                ];

                if ($model_account->save($save_data) !== false) {
                    $account_id = $model_account->getLastInsertId();

                } else {
                    
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

                //get cookie referer
                $sh_referer_val = "";
                $client_cookie_header = $request->getHeaderLine("X-shreferer");
                if(!empty($client_cookie_header)){
                    $sh_referer_val = $client_cookie_header;
                }

                // Create user
                $save_data = [
                    "account_id" => $account_id,
                    "user_type_id" => $user_type_id,
                    "role_id" => $role_id,
                    "source_id" => $source_id,
                    "first_name" => $first_name,
                    "last_name" => $last_name,
                    "email" => $gemail,
                    "password" => "",
                    "password_salt_key" => "",
                    "photo" => "",
                    "phone" => "",
                    "sh_referer" => $sh_referer_val,
                    "verified" => $flag_yes,
                    "created" => DateTimeComponent::getDateTime(),
                    "modified" => DateTimeComponent::getDateTime()
                ];

                if ($model_user->save($save_data) !== false) {
                    $inserted_user_id = $model_user->getLastInsertId();

                    if (!empty($inserted_user_id)) {
                        try {
                            // Get users settings
                            $model_user_settings = new UserSettings();

                            // Get valid preferences constants
                            $settings_id = [];

                            // Get valid preferences constants
                            array_push($settings_id,SHAppComponent::getValue("app_constant/U_TIMEZONE"));
                            array_push($settings_id,SHAppComponent::getValue("app_constant/U_POWERED_BY_SH"));
                            array_push($settings_id,SHAppComponent::getValue("app_constant/U_TRACK_EMAILS"));
                            array_push($settings_id,SHAppComponent::getValue("app_constant/U_TRACK_CLICKS"));
                          
                            foreach ($settings_id as $value) {
                                $save_pre_val = null;
                                if($value == SHAppComponent::getValue("app_constant/U_TIMEZONE")) {
                                    $save_pre_val = trim($request_params["timezone"]);
                                } else {
                                    $save_pre_val = true;
                                }
                                $save_data = [
                                    "user_id" => $inserted_user_id,
                                    "app_constant_var_id" => $value,
                                    "value" => $save_pre_val,
                                    "modified" => DateTimeComponent::getDateTime()
                                ];

                                $model_user_settings->save($save_data); 
                            }

                        } catch(\Exception $e) {}    
                    }   
                    
                    //get plan details
                    $model_plan_master = new PlanMaster();

                    //get plan id from app vars
                    $plan_id = SHAppComponent::getValue("plan/PLUS_MONTHLY_TRIAL");
                    
                    $get_plan_data =  $model_plan_master->getPlanData($plan_id);

                    $plan_validity_days_db = $get_plan_data["validity_in_days"];
                    $plan_configuration = $get_plan_data["configuration"];

                    //convert days to seconds
					$plan_validity_days = $plan_validity_days_db - 1;
                    $days_to_seconds = strtotime($plan_validity_days.' day', 0);
                    $trial_plan_end_date = DateTimeComponent::getDateTime() + $days_to_seconds;

                    //Assign trial plan to user
                    $model_account_subscription = new AccountSubscriptionDetails();
                    $save_plan_data = [
                        "account_id" => $account_id,
                        "plan_id" => $plan_id,
                        "team_size" => 1,
			            "start_date" => DateTimeComponent::getDateTime(), 
                        "end_date" => $trial_plan_end_date,
                        "type" => 1,
                        "status" => 1,
                        "created" => DateTimeComponent::getDateTime(),
                        "modified" => DateTimeComponent::getDateTime()
                    ];

                    if($model_account_subscription->save($save_plan_data) !== false){
                        $inserted_subscription_id = $model_account_subscription->getLastInsertId();
						$model_for_abm = new AccountBillingMaster();

						$save_billing_data = [
							"account_id" => $account_id,
							"plan_id" => $plan_id,
							"team_size" => 1,
							"current_subscription_id" => $inserted_subscription_id,
							"configuration" => $plan_configuration,
							"created" => DateTimeComponent::getDateTime(),
							"modified" => DateTimeComponent::getDateTime()
						];

						try {
							$saved_bill = $model_for_abm->save($save_billing_data);

						} catch(\Exception $e) {

							return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
						}
                    }else{
                        
                        return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                    }
                    // Get user role resources
                    $model_role_resources = new RoleDefaultResources();

                    $condition = [
                        "fields" => [
                            "rds.resource_id"
                        ],
                        "where" => [
                            ["where" => ["rds.role_id", "=", $role_id]],
                            ["where" => ["rds.status", "=", $status_active]]
                        ]
                    ];
                    $data = $model_role_resources->fetchAll($condition);

                    // Assign resources to user
                    $model_user_resource = new UserResources();

                    foreach ($data as $resource) {
                        $save_data = [
                            "user_id" => $inserted_user_id,
                            "resource_id" => $resource["resource_id"],
                            "modified" => DateTimeComponent::getDateTime()
                        ];

                        try {
                            $saved = $model_user_resource->save($save_data);

                        } catch(\Exception $e) {}
                    }

                    // Set user id to newly inserted one
                    $data["id"] = $inserted_user_id;
                    $data["account_id"] = $account_id;
                    $data["source_id"] = $source_id;
                    $data["plan_id"] = $plan_id;

                } else {
                    
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

            } catch(\Exception $e) {
                
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
        }

        // Link user account with social media profile
        try {
            $model_user_social_login = new UserSocialLogin();
            $condition = [
                "fields" => [
                    "usl.id"
                ],
                "where" => [
                    ["where" => ["usl.user_id", "=", $data["id"]]]
                ]
            ];
            $already_exists = $model_user_social_login->fetch($condition);

            $save_data = [
                "user_id" => $data["id"],
                "social_login_id" => $valid_social_accounts[$method],
                "signin_info" => json_encode($signin_info),
                "modified" => DateTimeComponent::getDateTime()
            ];
            if (!empty($already_exists["id"])) {
                $save_data["id"] = $already_exists["id"];
            }
            $saved = $model_user_social_login->save($save_data);

        } catch(\Exception $e) {}

        // Save record in email accounts, if not exists
        $model_account_sending_methods = new AccountSendingMethods();
        try {
            $condition = [
                "fields" => [
                    "asm.id"
                ],
                "where" => [
                    ["where" => ["asm.account_id", "=", $data["account_id"]]],
                    ["where" => ["asm.user_id", "=", $data["id"]]],
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
        try {
            $condition_default = [
                "fields" => [
                    "asm.id"
                ],
                "where" => [
                    ["where" => ["asm.account_id", "=", $data["account_id"]]],
                    ["where" => ["asm.user_id", "=", $data["id"]]],
                    ["where" => ["asm.is_default", "=", SHAppComponent::getValue("app_constant/FLAG_YES")]],
                    ["where" => ["asm.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $row_default_count = $model_account_sending_methods->fetch($condition_default);
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if (empty($email_account_data["id"])) {
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

                $save_data = [
                    "account_id" => $data["account_id"],
                    "user_id" => $data["id"],
                    "email_sending_method_id" => SHAppComponent::getValue("email_sending_method/GMAIL"),
                    "source_id" => $data["source_id"],
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

                if (!empty($row_default_count["id"])) {
                    $save_data["is_default"] = SHAppComponent::getValue("app_constant/FLAG_NO");
                } else {
                    $save_data["is_default"] = SHAppComponent::getValue("app_constant/FLAG_YES");
                }

                if ($model_account_sending_methods->save($save_data) !== false) {

                    $email_account_id = $model_account_sending_methods->getLastInsertId();
                    $model_account_sending_methods->setQuota($data["plan_id"], $email_account_id);
                    
                } else {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

            } catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
        }


        // Generate authentication token
        $source_id = SHAppComponent::getRequestSource();

        $auth_token = $this->generateAuthToken($data["id"], $source_id);
        if (empty($auth_token)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $output["auth_token"] = $auth_token;

        return $response->withJson($output, $output_status_code);
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
        $expires_in = 0;

        switch ($source_id) {
            case SHAppComponent::getValue("source/WEB_APP"): $expires_in = \AUTH_TOKEN_EXPIRE_INTERVAL; break;
            case SHAppComponent::getValue("source/CHROME_PLUGIN"): $expires_in = \AUTH_TOKEN_EXPIRE_INTERVAL_CHROME; break;
            case SHAppComponent::getValue("source/OUTLOOK_PLUGIN"): $expires_in = \AUTH_TOKEN_EXPIRE_INTERVAL_OUTLOOK; break;
            default: break;
        }

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
            $expires_at = $generated_at + $expires_in;
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

}
