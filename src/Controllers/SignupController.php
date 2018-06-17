<?php
/**
 * Signup related functionality
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
use \App\Models\AccountMaster;
use \App\Models\UserResources;
use \App\Models\RoleDefaultResources;
use \App\Models\UserAuthenticationTokens;
use \App\Models\UserInvitations;
use \App\Models\PlanMaster;
use \App\Models\AccountSubscriptionDetails;
use \App\Models\AccountBillingMaster;
use \App\Components\Mailer\TransactionMailsComponent;
use \App\Models\UserSettings;

class SignupController extends AppController {

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Signup
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function signup(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [
            "last_name" => ""
        ];

        if (isset($request_params["first_name"])) {
            $request_data["first_name"] = $request_params["first_name"];
        }
        if (isset($request_params["last_name"])) {
            $request_data["last_name"] = $request_params["last_name"];
        }
        if (isset($request_params["email"])) {
            $request_data["email"] = $request_params["email"];
        }
        if (isset($request_params["password"])) {
            $request_data["password"] = $request_params["password"];
        }
        if (isset($request_params["admin_panel"])) {
            $request_data["admin_panel"] = $request_params["admin_panel"];
        }
        if (isset($request_params["address"])) {
            $request_data["address"] = $request_params["address"];
        }
        if (isset($request_params["city"])) {
            $request_data["city"] = $request_params["city"];
        }
        if (isset($request_params["contact_no"])) {
            $request_data["contact_no"] = $request_params["contact_no"];
        }
        
        // Validate request
        $request_validations = [
            "first_name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "email" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY],
                ["type" => Validator::FIELD_EMAIL]
            ],
            "password" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY],
                ["type" => Validator::FIELD_TEXT_MIN, "arg" => [8]]
            ]
        ];
        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        // User model
        $model_user = new UserMaster();
        

        // Check if user with email already exists
        try {
            $condition = [
                "where" => [
                    ["where" => ["um.email", "=", $request_data["email"]]]
                ]
            ];
            $already_exists = $model_user->fetch($condition);

            if (!empty($already_exists["id"])) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/USER_ALREADY_EXISTS");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $role_id = SHAppComponent::getDefaultCustomerAdminRole();
        $user_type_id = SHAppComponent::getDefaultCustomerAdminUserType();
        $source_id = SHAppComponent::getRequestSource();
        $account_id = null;
        $account_number = StringComponent::generateAccountNumber();

        // Generate password
        $password_salt = StringComponent::generatePasswordSalt();
        $password = StringComponent::encryptPassword($request_data["password"], $password_salt);

        $status_active = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");

        if ($request_data["admin_panel"] == true) {
            $user_type_id = 1;
            $role_id = 1;
        }

        // Save user
        try {
            
            // Create user
            $save_data = [
                "user_type_id" => $user_type_id,
                "role_id" => $role_id,
                "first_name" => trim($request_data["first_name"]),
                "last_name" => trim($request_data["last_name"]),
                "email" => trim($request_data["email"]),
                "password" => $password,
                "password_salt_key" => $password_salt,
                "address" => $request_data["address"],
                "city" => $request_data["city"],
                "contact_no" => $request_data["contact_no"],
                "created_date" => DateTimeComponent::getDateTime(),
                "modified_date" => DateTimeComponent::getDateTime()
            ];

            if ($model_user->save($save_data) !== false) {

                $inserted_user_id = $model_user->getLastInsertId();

                //get plan details
    //             $model_plan_master = new PlanMaster();
    //             //get plan id from app vars
    //             $plan_id = SHAppComponent::getValue("plan/PLUS_MONTHLY_TRIAL");

    //             $get_plan_data =  $model_plan_master->getPlanData($plan_id);

    //             $plan_validity_days_db = $get_plan_data["validity_in_days"];
    //             $plan_configuration = $get_plan_data["configuration"];
				// $plan_validity_days = $plan_validity_days_db - 1;

    //             //convert days to seconds
    //             $days_to_seconds = strtotime($plan_validity_days.' day', 0);
    //             $trial_plan_end_date = DateTimeComponent::getDateTime() + $days_to_seconds;

    //             //Assign trial plan to user
    //             $model_account_subscription = new AccountSubscriptionDetails();
    //             $save_plan_data = [
    //                 "account_id" => $account_id,
    //                 "plan_id" => $plan_id,
    //                 "team_size" => 1,
		  //           "start_date" => DateTimeComponent::getDateTime(),
    //                 "end_date" => $trial_plan_end_date,
    //                 "type" => 1,
    //                 "status" => 1,
    //                 "created" => DateTimeComponent::getDateTime(),
    //                 "modified" => DateTimeComponent::getDateTime()
    //             ];

     //            if($model_account_subscription->save($save_plan_data) !== false){
     //                $inserted_subscription_id = $model_account_subscription->getLastInsertId();

					// $model_for_abm = new AccountBillingMaster();

					// $save_billing_data = [
					// 	"account_id" => $account_id,
					// 	"plan_id" => $plan_id,
					// 	"team_size" => 1,
					// 	"current_subscription_id" => $inserted_subscription_id,
					// 	"configuration" => $plan_configuration,
					// 	"created" => DateTimeComponent::getDateTime(),
					// 	"modified" => DateTimeComponent::getDateTime()
					// ];

					// try {
					// 	$saved_bill = $model_for_abm->save($save_billing_data);

					// } catch(\Exception $e) {

					// 	return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
					// }
     //            }else{
     //                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
     //            }

                // Get user role resources
                // $model_role_resources = new RoleDefaultResources();

                // $condition = [
                //     "fields" => [
                //         "rds.resource_id"
                //     ],
                //     "where" => [
                //         ["where" => ["rds.role_id", "=", $role_id]],
                //         ["where" => ["rds.status", "=", $status_active]]
                //     ]
                // ];
                // $data = $model_role_resources->fetchAll($condition);

                // Assign resources to user
                // $model_user_resource = new UserResources();

                // foreach ($data as $resource) {
                //     $save_data = [
                //         "user_id" => $inserted_user_id,
                //         "resource_id" => $resource["resource_id"],
                //         "modified" => DateTimeComponent::getDateTime()
                //     ];

                //     try {
                //         $saved = $model_user_resource->save($save_data);

                //     } catch(\Exception $e) {}
                // }

                $output["code"] = StringComponent::encodeRowId($inserted_user_id);

                // $request_origin_url = SHAppComponent::getRequestOrigin();

                // //Send email to user to verify account
                // $info["smtp_details"]["host"] = HOST;
                // $info["smtp_details"]["port"] = PORT;
                // $info["smtp_details"]["encryption"] = ENCRYPTION;
                // $info["smtp_details"]["username"] = USERNAME;
                // $info["smtp_details"]["password"] = PASSWORD;

                // $info["from_email"] = FROM_EMAIL;
                // $info["from_name"] = FROM_NAME;

                // $info["to"] = $request_data["email"];
                // $info["cc"] = '';
                // $info["bcc"] = '';
                // $info["subject"] = "Please verify your email address";
                // $info["content"] = file_get_contents(\EMAIL_TEPLATES_FOLDER . "/signup_email.html");
                // $info["content"] = str_replace("{FirstName}", $request_data["name"], $info["content"]);
                // $info["content"] = str_replace("{ConfirmEmailLink}", $request_origin_url . "/user/signup-verification/".$output["code"], $info["content"]);

                // $result = TransactionMailsComponent::mailSendSmtp($info);


                $output["message"] = "Your account has been created successfully.";

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $source_id = SHAppComponent::getRequestSource();

        $auth_token = $this->generateAuthToken($inserted_user_id, $source_id);
        if (empty($auth_token)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $output["auth_token"] = $auth_token;

        return $response->withJson($output, 201);
    }

    /**
     * Verify account
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function verifyAccount(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $route = $request->getAttribute("route");
        $code = $route->getArgument("code");

        // Validate request
        if (empty($code)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $user_id = StringComponent::decodeRowId($code);

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        
        // User model
        $model_user = new UserMaster();

        try {
            $fields = [
                "um.verified",
                "um.status"
            ];
            $data = $model_user->fetchById($user_id, $fields);
            
            if (empty($data)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/INVALID_VERIFICATION_CODE");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $status_delete = SHAppComponent::getValue("app_constant/STATUS_DELETE");
        if ($data["status"] == $status_delete) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/USER_DELETED");
        }

        $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");
        if ($data["verified"] == $flag_yes) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/ACCOUNT_ALREADY_VERIFIED");
        }

        // Save user
        try {
            $save_data = [
                "id" => $user_id,
                "verified" => $flag_yes,
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($model_user->save($save_data) !== false) {
                $output["message"] = "Your account has been successfully verified.";
                if ($user_id != $logged_in_user) {
                    $output["loggend_in_flag"] = true;
                } else {
                    $output["loggend_in_flag"] = false;
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
     * Resend verification link email
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function resendVerificationCode(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $route = $request->getAttribute("route");
        $code = $route->getArgument("code");

        // Validate request
        if (empty($code)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $user_id = StringComponent::decodeRowId($code);
        
        // User model
        $model_user = new UserMaster();

        try {
            $fields = [
                "um.first_name",
                "um.email",
                "um.verified",
                "um.status"
            ];
            $data = $model_user->fetchById($user_id, $fields);

            if (empty($data)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/INVALID_VERIFICATION_CODE");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $status_delete = SHAppComponent::getValue("app_constant/STATUS_DELETE");
        if ($data["status"] == $status_delete) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/USER_DELETED");
        }

        $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");
        if ($data["verified"] == $flag_yes) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/ACCOUNT_ALREADY_VERIFIED");
        } else {
            $request_origin_url = SHAppComponent::getRequestOrigin();

            //Send email to user to verify account
            $info["smtp_details"]["host"] = HOST;
            $info["smtp_details"]["port"] = PORT;
            $info["smtp_details"]["encryption"] = ENCRYPTION;
            $info["smtp_details"]["username"] = USERNAME;
            $info["smtp_details"]["password"] = PASSWORD;

            $info["from_email"] = FROM_EMAIL;
            $info["from_name"] = FROM_NAME;

            $info["to"] = $data["email"];
            $info["cc"] = '';
            $info["bcc"] = '';
            $info["subject"] = "Please verify your email address";
            $info["content"] = file_get_contents(\EMAIL_TEPLATES_FOLDER . "/signup_email.html");
            $info["content"] = str_replace("{FirstName}", $data["first_name"], $info["content"]);
            $info["content"] = str_replace("{ConfirmEmailLink}", $request_origin_url . "/user/signup-verification/".$code, $info["content"]);

            $result = TransactionMailsComponent::mailSendSmtp($info);
            //echo $result["message"];
        }

        $output["message"] = "Verification email sent successfully.";

        return $response->withJson($output, 200);
    }

    /**
     * Resend verification email using email
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function resendVerification(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["email"])) {
            $request_data["email"] = $request_params["email"];
        }
        
        // Validate request
        $request_validations = [
            "email" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY],
                ["type" => Validator::FIELD_EMAIL]
            ]
        ];
        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        // User model
        $model_user = new UserMaster();

        // Check if user with email exists
        try {
            $condition = [
                "fields" => [
                    "um.id",
                    "um.first_name",
                    "um.email",
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

            if (empty($row["id"])) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/USER_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $status_active = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
        $status_delete = SHAppComponent::getValue("app_constant/STATUS_DELETE");
        $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");

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

        if ($row["verified"] == $flag_yes) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/ACCOUNT_ALREADY_VERIFIED");
        }

        if ($row["user_status"] != $status_active) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/USER_ACCOUNT_NOT_ACTIVE");
        }

        $code = StringComponent::encodeRowId($row["id"]);

        $request_origin_url = SHAppComponent::getRequestOrigin();

        //Send email to user to verify account
        $info["smtp_details"]["host"] = HOST;
        $info["smtp_details"]["port"] = PORT;
        $info["smtp_details"]["encryption"] = ENCRYPTION;
        $info["smtp_details"]["username"] = USERNAME;
        $info["smtp_details"]["password"] = PASSWORD;

        $info["from_email"] = FROM_EMAIL;
        $info["from_name"] = FROM_NAME;

        $info["to"] = $row["email"];
        $info["cc"] = '';
        $info["bcc"] = '';
        $info["subject"] = "Please verify your email address";
        $info["content"] = file_get_contents(\EMAIL_TEPLATES_FOLDER . "/signup_email.html");
        $info["content"] = str_replace("{FirstName}", $row["first_name"], $info["content"]);
        $info["content"] = str_replace("{ConfirmEmailLink}", $request_origin_url . "/user/signup-verification/".$code, $info["content"]);

        $result = TransactionMailsComponent::mailSendSmtp($info);
        //echo $result["message"];

        $output["message"] = "Verification email sent successfully.";

        return $response->withJson($output, 200);
    }

    /**
     * Check if account exists by email address
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function accountExists(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $route = $request->getAttribute("route");
        $email = $route->getArgument("email");

        // Validate request
        if (empty($email)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        // User model
        $model_user = new UserMaster();

        try {
            $condition = [
                "where" => [
                    ["where" => ["um.email", "=", $email]]
                ]
            ];
            $already_exists = $model_user->fetch($condition);

            if (empty($already_exists["id"])) {
                $output["exists"] = false;

            } else {
                $output["exists"] = true;
            }

            // If account already exists then check if the account is valid
            if ($output["exists"]) {
                $condition = [
                    "fields" => [
                        "um.verified",
                        "um.status AS user_status",
                        "am.status AS account_status"
                    ],
                    "where" => [
                        ["where" => ["um.id", "=", $already_exists["id"]]]
                    ],
                    "join" => [
                        "account_master"
                    ]
                ];
                $row = $model_user->fetch($condition);

                $status_active = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                $status_delete = SHAppComponent::getValue("app_constant/STATUS_DELETE");
                $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");

                if ($row["account_status"] == $status_delete) {
                    $error_details = SHAppComponent::getValue("api_messages/ACCOUNT_DELETED");

                    $output["error_code"] = $error_details["error_code"];
                    $output["error_message"] = $error_details["error_message"];
                }

                if ($row["account_status"] != $status_active) {
                    $error_details = SHAppComponent::getValue("api_messages/ACCOUNT_CLOSED");

                    $output["error_code"] = $error_details["error_code"];
                    $output["error_message"] = $error_details["error_message"];
                }

                if ($row["user_status"] == $status_delete) {
                    $error_details = SHAppComponent::getValue("api_messages/USER_DELETED");

                    $output["error_code"] = $error_details["error_code"];
                    $output["error_message"] = $error_details["error_message"];
                }

                if ($row["user_status"] != $status_active) {
                    $error_details = SHAppComponent::getValue("api_messages/USER_ACCOUNT_NOT_ACTIVE");

                    $output["error_code"] = $error_details["error_code"];
                    $output["error_message"] = $error_details["error_message"];
                }

                if ($row["verified"] != $flag_yes) {
                    $error_details = SHAppComponent::getValue("api_messages/USER_NOT_VERIFIED");

                    $output["error_code"] = $error_details["error_code"];
                    $output["error_message"] = $error_details["error_message"];
                }
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * Check for valid invitation link
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function checkInvitation(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $route = $request->getAttribute("route");
        $code = $route->getArgument("code");

        // Validate request
        if (empty($code)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        list($user_id, $account_id) = StringComponent::decodeRowId($code, true);

        // User model
        $model_user = new UserMaster();

        try {
            $condition = [
                "fields" => [
                    "um.id",
                    "um.email",
                    "um.first_name",
                    "um.last_name",
                    "um.status"
                ],
                "where" => [
                    ["where" => ["um.id", "=", $user_id]],
                    ["where" => ["um.account_id", "=", $account_id]]
                ]
            ];
            $data = $model_user->fetch($condition);

            if (empty($data)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/INVALID_INVITATION_CODE");
            }

            // Check invitation is not already accepted
            $model_user_invitations = new UserInvitations();

            $condition = [
                "fields" => [
                    "ui.joined_at"
                ],
                "where" => [
                    ["where" => ["ui.user_id", "=", $user_id]],
                    ["where" => ["ui.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $row_invitation = $model_user_invitations->fetch($condition);

            if (empty($row_invitation)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/INVALID_INVITATION_CODE");
            }

            if (!empty($row_invitation["joined_at"])) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/INVITE_ALREADY_ACCEPTED");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $status_delete = SHAppComponent::getValue("app_constant/STATUS_DELETE");
        if ($data["status"] == $status_delete) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/USER_DELETED");
        }

        $output = [
            "email" => $data["email"],
            "first_name" => $data["first_name"],
            "last_name" => $data["last_name"]
        ];

        return $response->withJson($output, 200);
    }

    /**
     * Accept invitation
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function acceptInvitation(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $route = $request->getAttribute("route");
        $code = $route->getArgument("code");

        // Validate request
        if (empty($code)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        list($user_id, $account_id) = StringComponent::decodeRowId($code, true);

        // User model
        $model_user = new UserMaster();

        try {
            $condition = [
                "fields" => [
                    "um.id",
                    "um.email",
                    "um.first_name",
                    "um.last_name",
                    "um.status"
                ],
                "where" => [
                    ["where" => ["um.id", "=", $user_id]],
                    ["where" => ["um.account_id", "=", $account_id]]
                ]
            ];
            $data = $model_user->fetch($condition);

            if (empty($data)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/INVALID_INVITATION_CODE");
            }

            // Check invitation is not already accepted
            $model_user_invitations = new UserInvitations();

            $condition = [
                "fields" => [
                    "ui.id",
                    "ui.joined_at"
                ],
                "where" => [
                    ["where" => ["ui.user_id", "=", $user_id]],
                    ["where" => ["ui.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $row_invitation = $model_user_invitations->fetch($condition);

            if (empty($row_invitation)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/INVALID_INVITATION_CODE");
            }

            if (!empty($row_invitation["joined_at"])) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/INVITE_ALREADY_ACCEPTED");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $status_delete = SHAppComponent::getValue("app_constant/STATUS_DELETE");
        if ($data["status"] == $status_delete) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/USER_DELETED");
        }

        // Get request parameters (post)
        $request_params = $request->getParsedBody();
       
        // Fetch request data
        $request_data = [
            "last_name" => ""
        ];

        if (isset($request_params["first_name"])) {
            $request_data["name"] = $request_params["first_name"];
        }
        if (isset($request_params["last_name"])) {
            $request_data["last_name"] = $request_params["last_name"];
        }
        if (isset($request_params["password"])) {
            $request_data["password"] = $request_params["password"];
        }

        // Validate request
        $request_validations = [
            "name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "password" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY],
                ["type" => Validator::FIELD_TEXT_MIN, "arg" => [8]]
            ]
        ];
        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        // User model
        $model_user = new UserMaster();

        // Generate password
        $password_salt = StringComponent::generatePasswordSalt();
        $password = StringComponent::encryptPassword($request_data["password"], $password_salt);

        // Save user
        try {
            $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");

            //get cookie referer
            $sh_referer_val = "";
            $client_cookie_header = $request->getHeaderLine("X-shreferer");
            if(!empty($client_cookie_header)){
                $sh_referer_val = $client_cookie_header;
            }

            $save_data = [
                "id" => $user_id,
                "first_name" => trim($request_data["name"]),
                "last_name" => trim($request_data["last_name"]),
                "password" => $password,
                "password_salt_key" => $password_salt,
                "sh_referer" => $sh_referer_val,
                "verified" => $flag_yes,
                "modified" => DateTimeComponent::getDateTime()
            ];
            if ($model_user->save($save_data) !== false) {
                
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
                    // array_push($settings_id,SHAppComponent::getValue("app_constant/PUSH_NOTF_EMAIL_TRACKING"));
                    // array_push($settings_id,SHAppComponent::getValue("app_constant/PUSH_NOTF_MAIL_MERGE"));
                    // array_push($settings_id,SHAppComponent::getValue("app_constant/PUSH_NOTF_DOC_TRACK"));
                  
                    // array_push($settings_id,SHAppComponent::getValue("app_constant/NUM_DEFAULT_MAIL_ACCOUNTS"));
                    
                    foreach ($settings_id as $value) {
                        $save_pre_val = null;
                        
                        if ($value == SHAppComponent::getValue("app_constant/U_TIMEZONE")) {
                            $save_pre_val = trim($request_params["timezone"]);
                        } 
                        // else if($value == SHAppComponent::getValue("app_constant/NUM_DEFAULT_MAIL_ACCOUNTS")) {
                        //     $save_pre_val = 1;
                        // } 
                        else {
                            $save_pre_val = true;
                        }
                        
                        $user_settting_data = [
                            "user_id" => $user_id,
                            "app_constant_var_id" => $value,
                            "value" => $save_pre_val,
                            "modified" => DateTimeComponent::getDateTime()
                        ];
                        
                        $model_user_settings->save($user_settting_data); 
                    }

                } catch(\Exception $e) { }   
            
                // Update invidation status
                $save_data = [
                    "id" => $row_invitation["id"],
                    "joined_at" => DateTimeComponent::getDateTime(),
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $saved = $model_user_invitations->save($save_data);

                $output["message"] = "Your account has been updated successfully.";

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $source_id = SHAppComponent::getRequestSource();

        $auth_token = $this->generateAuthToken($user_id, $source_id);
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
