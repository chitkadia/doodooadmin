<?php
/**
 * User profile related functionality (Dashbaord, Profile, Feed)
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
use \App\Components\ImageUploaderComponent;
use \App\Components\SHActivity;
use \App\Components\Mailer\TransactionMailsComponent;
use \App\Models\UserMaster;
use \App\Models\UserSettings;
use \App\Models\PlanMaster;
use \App\Models\AccountSubscriptionDetails;
use \App\Models\AccountBillingMaster;
use \App\Models\UserAuthenticationTokens;
use \App\Models\AccountSendingMethods;
use \App\Models\Activity;
use \App\Models\EmailMaster;
use \App\Models\CampaignMaster;
use \App\Models\EmailRecipients;
use \App\Models\AccountLinkMaster;
use \App\Models\DocumentLinkFiles;
use \App\Models\AppConstantVars;
use \App\Models\ActiveplanDetails;

class ProfileController extends AppController {

    protected $email_prefences_const_array = ["U_EMAIL_PREF_NEWS_BLOG","U_EMAIL_PREF_PR_ENGAGE","U_EMAIL_PREF_PR_ANNOUCE","U_EMAIL_PREF_PROMO","U_EMAIL_PREF_REPORTS","U_EMAIL_PREF_OPTOUT"];

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Get dashboard details
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function dashboard(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();

        $output["message"] = "Dashboard page accessed.";

        return $response->withJson($output, 200);
    }

    /**
     * Get profile details
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function profile(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();
        // $logged_in_source = SHAppComponent::getRequestSource();
        // $is_account_owner = SHAppComponent::isAccountOwner();

        //get user current plan
        $plan_id = SHAppComponent::getPlanId();

        $user_timezone = SHAppComponent::getUserTimeZone();

        // User model
        $model_user = new UserMaster();

        // Check if user exists
        try {
            $condition = [
                "fields" => [
                    "um.id",
                    "um.first_name",
                    "um.last_name",
                    "um.email",
                    "um.password",
                    "um.address",
                    "um.city",
                    "um.contact_no",
                    "um.created_date",
                    "um.modified_date"
                ],
                "where" => [
                    ["where" => ["um.id", "=", $logged_in_user]],
                ]
            ];
            $user_data = $model_user->fetch($condition);

            if (empty($user_data["id"])) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

            
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $signup_date = DateTimeComponent::convertDateTime($user_data["created_date"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);

        // Get Current Plan Details
        $account_billing_model = new ActiveplanDetails();
        try {
            $condition_billing_fetch = [
                "fields" => [
                    "apd.plan_id",
                    "apd.total_quantity",
                    "apd.toy_quantity",
                    "apd.book_quantity",
                    "apd.quantity_configuration",
                    "apd.active_current_purchase_detail",
                    "apd.deposite",
                    "apd.deposite_refunded",
                    "apd.status",
                    "pd.start_date",
                    "pd.end_date",
                    "pmd.plan_name"
                ],
                "where" => [
                    ["where" => ["apd.user_id", "=", $logged_in_user]],
                    ["where" => ["apd.status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                ],
                "join" => [
                    "purchase_details",
                    "plan_details"
                ]
            ];
            $row_billing_data = $account_billing_model->fetch($condition_billing_fetch);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $output = [
            "id" => StringComponent::encodeRowId($user_data["id"]),
            "first_name" => $user_data["first_name"],
            "last_name" => $user_data["last_name"],
            "email" => $user_data["email"],
            "contact_no" => $user_data["contact_no"],
            "member_since" => DateTimeComponent::showDateTime($signup_date, $user_timezone),
            "address" => $user_data["address"],
            "city" => $user_data["city"],
            "plan_id" => $row_billing_data["plan_id"],
            "total_quantity" => $row_billing_data["total_quantity"],
            "toy_quantity" => $row_billing_data["toy_quantity"],
            "book_quantity" => $row_billing_data["book_quantity"],
            "quantity_configuration" => json_decode($row_billing_data["quantity_configuration"]),
            "status" => $row_billing_data["status"],
            "start_date" => $row_billing_data["start_date"],
            "end_date" => $row_billing_data["end_date"]
        ];

        $current_timestamp = DateTimeComponent::getDateTime();
        $plan_end_date = $row_billing_data["end_date"];
        $time_difference = $plan_end_date - $current_timestamp;
        $plan_days_value = round($time_difference / (60 * 60 * 24));
        if ($plan_days_value <= 0) {
            $plan_days = 0;
        } else {
            $plan_days = $plan_days_value;
        }

        $has_plan = false;
        if (!empty($row_billing_data)) {
            $has_plan = true;
        }

        $output["has_plan"] = $has_plan;
        $output["current_plan"] = $row_billing_data["plan_name"];
        
        return $response->withJson($output, 200);
    }

    /**
     * Update profile details
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function updateProfile(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["first_name"])) {
            $request_data["first_name"] = $request_params["first_name"];
        }
        if (isset($request_params["last_name"])) {
            $request_data["last_name"] = $request_params["last_name"];
        }
        if (isset($request_params["photo"])) {
            $request_data["photo"] = $request_params["photo"];
        }

        // Validate request
        $request_validations = [
            "first_name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "last_name" => [
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

        // Update information
        $model_user = new UserMaster();

        try {
            $save_data = [
                "id" => $logged_in_user,
                "first_name" => trim($request_data["first_name"]),
                "last_name" => trim($request_data["last_name"])
            ];
            if (!empty($request_data["photo"])) {
                // Upload image
                $upload_path = \PATH_TO_MEDIA . \MEDIA_USER_PHOTOS;
                $uploaded_image = ImageUploaderComponent::uploadImage($request_data["photo"], $upload_path);

                if (!empty($uploaded_image["error"])) {
                    // Fetch error code & message and return the response
                    $additional_message = "Could not upload user image.";
                    return ErrorComponent::outputError($response, "api_messages/ERROR_IMAGE_UPLOAD", $additional_message);
                }

                $save_data["photo"] = $uploaded_image["name"];

            } else if (isset($request_data["photo"])) {
                // If photo is passed empty string
                $condition = [
                    "fields" => [
                        "um.photo"
                    ],
                    "where" => [
                        ["where" => ["um.id", "=", $logged_in_user]],
                    ]
                ];
                $user_data = $model_user->fetch($condition);
                $save_data["photo"] = $user_data["photo"];
            }

            if ($model_user->save($save_data) !== false) {
                $output["message"] = "Profile updated successfully.";

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
     * Set password for sign in with google
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object

     */
    public function setPassword(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];

        if (isset($request_params["password"])) {
            $request_data["password"] = $request_params["password"];
        }
        if (isset($request_params["confirm_password"])) {
            $request_data["confirm_password"] = $request_params["confirm_password"];
        }

        // Validate request
        $request_validations = [
            "confirm_password" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY],
                ["type" => Validator::FIELD_MATCH, "arg" => ["password"]]
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

        // Generate password
        $password_salt = StringComponent::generatePasswordSalt();
        $password = StringComponent::encryptPassword($request_data["password"], $password_salt);

        // Save new password
        $model_user = new UserMaster();
        
        try {
            $save_data = [
                "id" => $logged_in_user,
                "password" => $password,
                "password_salt_key" => $password_salt,
                "modified" => DateTimeComponent::getDateTime()
            ];
            if ($model_user->save($save_data) !== false) {
                $output["message"] = "Password saved successfully.";
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
     * Change password
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function changePassword(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["current_password"])) {
            $request_data["current_password"] = $request_params["current_password"];
        }
        if (isset($request_params["new_password"])) {
            $request_data["new_password"] = $request_params["new_password"];
        }

        // Validate request
        $request_validations = [
            "current_password" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "new_password" => [
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

        // Fetch current user information
        $model_user = new UserMaster();

        try {
            $fields = [
                "um.password",
                "um.password_salt_key"
            ];
            $user_data = $model_user->fetchById($logged_in_user, $fields);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Check if current password is correct
        $valid_password = StringComponent::decryptPassword($request_data["current_password"], $user_data["password_salt_key"], $user_data["password"]);
        if (!$valid_password) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/INVALID_CURRENT_PASS");
        }


        // To check if old and new password are same
        $check_old_new_password = StringComponent::decryptPassword($request_data["new_password"], $user_data["password_salt_key"], $user_data["password"]);

        if (!empty($check_old_new_password)) {
            return ErrorComponent::outputError($response,"api_messages/INVALID_NEW_PASS");
        } 
           
        // Generate password
        $password_salt = StringComponent::generatePasswordSalt();
        $password = StringComponent::encryptPassword($request_data["new_password"], $password_salt);

        // Save new password
        try {
            $save_data = [
                "id" => $logged_in_user,
                "password" => $password,
                "password_salt_key" => $password_salt,
                "modified" => DateTimeComponent::getDateTime()
            ];
            if ($model_user->save($save_data) !== false) {
                $output["message"] = "Password changed successfully.";

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
     * Update preferences (single preference)
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function updatePreferences(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [
            "value" => ""
        ];

        $check_if_pref_exist = 0;
        
        if (isset($request_params["setting"])) {
            $request_data["setting"] = $request_params["setting"];
        }
        if (isset($request_params["value"])) {
            $request_data["value"] = $request_params["value"];
        }

        // Validate request
        $request_validations = [
            "setting" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "value" => [
                ["type" => Validator::FIELD_REQUIRED]
            ]
        ];
        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        $app_constants = SHAppComponent::getValue("app_constant");
        if (!isset($app_constants[$request_data["setting"]])) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/INVALID_PREFERENCE_UPDATE");
        }

        // Save preference value
        try {
            $settings_id = $app_constants[$request_data["setting"]];

            $model_user_settings = new UserSettings();

            // Check if preference already exists
            $condition = [
                "fields" => [
                    "us.id"
                ],
                "where" => [
                    ["where" => ["us.user_id", "=", $logged_in_user]],
                    ["where" => ["us.app_constant_var_id", "=", $settings_id]]
                ]
            ];
            $row = $model_user_settings->fetch($condition);

            // Save settings
            $save_data = [
                "user_id" => $logged_in_user,
                "app_constant_var_id" => $settings_id,
                "value" => trim($request_data["value"]),
                "modified" => DateTimeComponent::getDateTime()
            ];
            if (!empty($row["id"])) {
                $save_data["id"] = $row["id"];
                $check_if_pref_exist = 1;
            }

            if ($model_user_settings->save($save_data) !== false) {
                $output["message"] = "Preferences saved successfully.";

                // Send mail after user accept terms & condition settings
                if ($settings_id == SHAppComponent::getValue("app_constant/TERM_CONDITION_STATUS") && $check_if_pref_exist == 0) {

                    $user_master = new UserMaster();
                    $logged_in_account = SHAppComponent::getAccountId();
                    $get_user_data = $user_master->getUserById($logged_in_user,$logged_in_account);

                    if (!empty($get_user_data)) {

                        $mail_sender = new TransactionMailsComponent();
                        $mail_data = [];

                        // smtp details
                        $mail_data["smtp_details"]["host"] = HOST;
                        $mail_data["smtp_details"]["username"] = USERNAME;
                        $mail_data["smtp_details"]["password"] = PASSWORD;
                        $mail_data["smtp_details"]["port"] = PORT;
                        $mail_data["smtp_details"]["encryption"] = ENCRYPTION;

                        // varibles required for sending email
                        $mail_data["to"] = trim($get_user_data["email"]);
                        $mail_data["cc"] = trim(DPA_ACCEPT_SH_EMAILS);
                        $mail_data["bcc"] = trim($info["bcc"]);
                        $mail_data["subject"] = trim("This is all about GDPR");
                        $mail_data["content"] = trim("Welcome to Saleshandy<br/>we have implemented GDPR criteria");
                        $mail_data["from_email"] = FROM_EMAIL;
                        $mail_data["from_name"]  = FROM_NAME;

                        $mail_sender::mailSendSmtp($mail_data);
                    }    
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
     * Update Push Notification preferences (single preference)
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function updatePushNotificationPreferences(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [
            "value" => ""
        ];
        
        if (isset($request_params["setting"])) {
            $request_data["setting"] = $request_params["setting"];
        }
        if (isset($request_params["value"])) {
            $request_data["value"] = $request_params["value"];
        }

        // Validate request
        $request_validations = [
            "setting" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "value" => [
                ["type" => Validator::FIELD_REQUIRED]
            ]
        ];
        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        $app_constants = SHAppComponent::getValue("app_constant");
        if (!isset($app_constants[$request_data["setting"]])) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/INVALID_PREFERENCE_UPDATE");
        }

        // Save preference value
        try {
            $settings_id = $app_constants[$request_data["setting"]];

            $model_user_settings = new UserSettings();

            // Check if preference already exists
            $condition = [
                "fields" => [
                    "us.id"
                ],
                "where" => [
                    ["where" => ["us.user_id", "=", $logged_in_user]],
                    ["where" => ["us.app_constant_var_id", "=", $settings_id]]
                ]
            ];
            $row = $model_user_settings->fetch($condition);

            // Save settings
            $save_data = [
                "user_id" => $logged_in_user,
                "app_constant_var_id" => $settings_id,
                "value" => trim($request_data["value"]),
                "modified" => DateTimeComponent::getDateTime()
            ];
            if (!empty($row["id"])) {
                $save_data["id"] = $row["id"];
            }

            if ($model_user_settings->save($save_data) !== false) {
                $output["message"] = "Preferences saved successfully.";

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
     * Update preferences (multiple preferences)
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function updatePreferencesMulti(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [
            "settings" => ""
        ];
        
        if (!empty($request_params)) {
            $request_data["settings"] = $request_params;
        }

        // Validate request
        $request_validations = [
            "settings" => [
                ["type" => Validator::FIELD_REQUIRED],
                ["type" => Validator::FIELD_JSON_ARRAY]
            ]
        ];
        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = "No preferences passed in request.";
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        // Get valid preferences constants
        $app_constants = SHAppComponent::getValue("app_constant");

        // Process request
        foreach ($request_data["settings"] as $setting_data) {
            $error_message = "";
            $success = false;

            if (!isset($app_constants[$setting_data["setting"]])) {
                $error_message = SHAppComponent::getValue("api_messages/INVALID_PREFERENCE_UPDATE/error_message");

                $output[] = [
                    "setting" => $setting_data["setting"],
                    "success" => $success,
                    "error_message" => $error_message
                ];

                continue;
            }

            try {
                $settings_id = $app_constants[$setting_data["setting"]];

                $model_user_settings = new UserSettings();

                // Check if preference already exists
                $condition = [
                    "fields" => [
                        "us.id"
                    ],
                    "where" => [
                        ["where" => ["us.user_id", "=", $logged_in_user]],
                        ["where" => ["us.app_constant_var_id", "=", $settings_id]]
                    ]
                ];
                $row = $model_user_settings->fetch($condition);

                // Save settings
                $save_data = [
                    "user_id" => $logged_in_user,
                    "app_constant_var_id" => $settings_id,
                    "value" => trim($setting_data["value"]),
                    "modified" => DateTimeComponent::getDateTime()
                ];
                if (!empty($row["id"])) {
                    $save_data["id"] = $row["id"];
                }

                if ($model_user_settings->save($save_data) !== false) {
                    $success = true;

                } else {
                    $error_message = SHAppComponent::getValue("api_messages/DB_OPERATION_FAIL/error_message");
                }

            } catch(\Exception $e) {
                $error_message = SHAppComponent::getValue("api_messages/DB_OPERATION_FAIL/error_message");
            }

            if ($success) {
                $output[] = [
                    "setting" => $setting_data["setting"],
                    "success" => $success
                ];

            } else {
                $output[] = [
                    "setting" => $setting_data["setting"],
                    "success" => $success,
                    "error_message" => $error_message
                ];
            }
        }

        return $response->withJson($output, 200);
    }

    /**
     * Get user feed
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function feed(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
        
        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
       
        // Get request parameters
        $params = $request->getQueryParams();

        // Set parameters
        $query_params = [
            "page" => 1,
            "per_page" => SHAppComponent::getValue("app_constant/DEFAULT_LIST_PER_PAGE")
        ];

        if (!empty($params["per_page"])) {
            $query_params["per_page"] = (int) $params["per_page"];
        }
        if (!empty($params["page"])) {
            $query_params["page"] = (int) $params["page"];
        }
        if(!empty($params["action_group"])) {
            
            if (strtolower($params["action_group"]) != "all" ) {
                $action_group_id = SHAppComponent::getValue("actions/" . $params["action_group"] . "/ACTION_GROUP_ID");

                if (!empty($action_group_id)) {
                    $query_params["action_group"] = $action_group_id;
                }
            }
        }
        if(!empty($params["action"])) {
            if (strtolower($params["action"]) != "all" ) {
                $action_id = SHAppComponent::getValue("actions/" . $params["action_group"] . "/" . $params["action"]);

                if (!empty($action_id)) {
                    $query_params["action"] = $action_id;
                }
            }
        }
        if(!empty($params["user"])) {
            if (strtolower($params["user"]) != "all" ) {
                $user_id = StringComponent::decodeRowId(trim($params["user"]));

                if (!empty($user_id)) {
                    $query_params["user"] = $user_id;
                }
            }
        }
        if(!empty($params["query"])) {
            $query_params["query"] = SHAppComponent::prepareSearchText($params["query"]);
        }
        
        // Other values for condition
        $other_values = [
            "user_id" => $logged_in_user,
            "account_id" => $logged_in_account
        ];

        // Get data
        $model_activity = new Activity();

        try {
            $data = $model_activity->getListData($query_params, $other_values);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $email_recipient_model = new EmailRecipients();

        $condition = [
            "fields" => [
                "ac.first_name",
                "ac.email"
            ],
            "where" => [
                ["where" => ["er.type", "=", SHAppComponent::getValue("app_constant/TO_EMAIL_RECIPIENTS")]],
                ["where" => ["er.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]           
            ],
            "join" => [
                "account_contacts"
            ]
        ];
        //fetch url for email and campaign link click
        $account_link_master = new AccountLinkMaster();

        $fetch_url = [
            "fields" => [
                "url"
            ],
            "where" => [
                ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
            ]
        ];
        // Process data and prepare for output
        if (!empty($data["is_user_free"]) && $data["total_pages"] > 1) {
            $output["subscription_button"] = $data["subscription_button"]; 
        }
        $output["total_records"] = $data["total_records"];
        $output["total_pages"] = $data["total_pages"];
        $output["current_page"] = $data["current_page"];
        $output["per_page"] = $data["per_page"];
        $output["is_user_free"] = $data["is_user_free"];
        $output["rows"] = [];

        foreach($data["rows"] as $row) {
            
            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "created_by_id" => StringComponent::encodeRowId($row["user_id"]),
                "contact_id" => null,
                "cname" => null,
                "action" => SHAppComponent::getValue("actions_list/" .$row["action_group_id"]),
                "sub_action" => SHAppComponent::getValue("actions_list/" .$row["action_id"]),
                "entity_id" => StringComponent::encodeRowId($row["record_id"]),
                "entity" => $row["entity"],
                "text_to_display" => null,
                "text_to_display_tooltip" => null,
                "date_timestamp" => $row["created"]
            ];

            if ($row["action_group_id"] == SHAppComponent::getValue("actions/EMAILS/ACTION_GROUP_ID")) {
                $row_data["entity"] = empty($row["entity"]) ? "(no subject)" : $row["entity"];
            }

            $name = trim($row["user_firstname"] . " " . $row["user_lastname"]);
            $name = empty($name) ? $row["user_email"] : $name;
            $row_data["created_by"] = $name;
            
            $location = "";
            if(!empty($row["other_data"])) {
                $other_data = json_decode($row["other_data"], true);

                if (preg_match("/via ggpht\.com GoogleImageProxy/", $other_data["ua"])) {
                    $location = "Read using gmail client";
                } else {
                    if (!empty($other_data["c"])) {
                        $location = $other_data["c"] . ", ";
                    }
                    if (!empty($other_data["s"])) {
                        $location .= $other_data["s"] . ", ";
                    }
                    if (!empty($other_data["co"])) {
                        $location .= $other_data["co"] . ", ";
                    }
                    if (!empty($other_data["bn"])) {
                        $location .= $other_data["bn"] . ", ";
                    }
                    if (!empty($other_data["os"])) {
                        $location .= $other_data["os"];
                    }
                }
                $row_data["location"] = $location;
            }
            if(!empty($row["created"]) && $row["created"] > 0) {
                $user_time_zone = SHAppComponent::getUserTimeZone();
                $datetime = DateTimeComponent::convertDateTime($row["created"], true, DEFAULT_TIMEZONE, $user_time_zone, APP_TIME_FORMAT);
                $row_data["created_at"] = $datetime;
                $row_data["date_to_display"] = DateTimeComponent::getDateTimeAgo($row["created"], $user_time_zone);
            }

            $template_data = [
                "action_id" => $row["action_id"],
                "entity" => "<b>" . htmlspecialchars(html_entity_decode($row_data["entity"], ENT_QUOTES)) . "</b>",
            ];

            if(!empty($row["account_contact_id"])) {
                $name = trim($row["contact_firstname"]);
                $name = empty($name) ? $row["contact_email"] : $name;
                $template_data["cname"] = $name;

            } else if ($row["action_group_id"] == SHAppComponent::getValue("actions/EMAILS/ACTION_GROUP_ID")) {
                
                $condition["where"]["where"] = ["where" => ["er.email_id", "=", $row["record_id"] ]];
                $recipients_data = $email_recipient_model->fetchAll($condition);
                
                if (!empty($recipients_data)) {
                    $contacts = [];
                    foreach($recipients_data as $recipient) {
                        $name = trim($recipient["first_name"]);
                        $name = empty($name) ? $recipient["email"] : $name;
                        $contacts[] = $name;
                    }
                    $template_data["cname"] = $contacts;
                }
            }
            
            if(!empty($row["stage"])) {
                $template_data["stage"] = $row["stage"];
            }

            if(!empty($row["sub_record_id"])) {
                $fetch_url["where"]["where"] = ["where" => ["id", "=", $row["sub_record_id"] ]];
                
                $link_data = $account_link_master->fetch($fetch_url);
                if(!empty($link_data["url"])) {
                    $template_data["link"] = $link_data["url"];
                }
            }
            //prepare activity text to display.
            $sh_activity = new SHActivity();
            $text_to_display = $sh_activity->prepareDisplayText($template_data);

            if(!empty($text_to_display)) {
                $row_data["text_to_display"] = $text_to_display;
                $row_data["text_to_display_tooltip"] = strip_tags($text_to_display);
            }

            $output["rows"][] = $row_data;
        }
        return $response->withJson($output, 200);
    }

    /**
     * Logout user
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function logout(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();

        // Check if token is valid
        $model = new \App\Models\UserAuthenticationTokens();

        try {
            // Decode auth token to set it as expired
            $header_auth_token = $request->getHeaderLine("X-Authorization-Token");
            $token_info = \App\Components\StringComponent::decodeRowId($header_auth_token, true);

            $condition = [
                "fields" => [
                    "uat.id"
                ],
                "where" => [
                    ["where" => ["uat.user_id", "=", $token_info[0]]],
                    ["where" => ["uat.source_id", "=", $token_info[1]]],
                    ["where" => ["uat.generated_at", "=", $token_info[2]]]
                ]
            ];
            $row = $model->fetch($condition);

            if (!empty($row["id"])) {
                // Set token as expired
                $save_data = [
                    "id" => $row["id"],
                    "expires_at" => DateTimeComponent::getDateTime()
                ];

                $saved = $model->save($save_data);
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $output["message"] = "You have been logged out successfully.";

        return $response->withJson($output, 200);
    }

    /**
     * Resend verification mail
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function resendVerificationEmail(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();

        // Fetch current user information
        $model_user = new UserMaster();

        try {
            $fields = [
                "um.verified"
            ];
            $row = $model_user->fetchById($logged_in_user, $fields);

            if (empty($row["id"])) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/USER_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");
        if ($row["verified"] == $flag_yes) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/ACCOUNT_ALREADY_VERIFIED");
        }

        $output["message"] = "Verification email sent successfully.";

        return $response->withJson($output, 200);
    }

    /**
     * Activity feed history for goruping activity
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function activityFeedHistory(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        //Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $user_timezone = SHAppComponent::getUserTimeZone();

        //Get request parameters
        $route = $request->getAttribute("route");
        $record_id = $route->getArgument("id");
        $params = $request->getQueryParams();

        if (empty($record_id)) {
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $record_id = StringComponent::decodeRowId($record_id);
        $action = SHAppComponent::getValue("actions/" . $params["action"] . "/ACTION_GROUP_ID");
        
        switch($action) {
            case SHAppComponent::getValue("actions/EMAILS/ACTION_GROUP_ID") :
                $condition = [
                    "fields" => [
                        "em.subject as entity"
                    ],
                    "where" => [
                        ["where" => ["em.id", "=", (int) $record_id]]
                    ]
                ];
                $model = new EmailMaster();
                break;

            case SHAppComponent::getValue("actions/CAMPAIGNS/ACTION_GROUP_ID") :
                $condition = [
                    "fields" => [
                        "cm.title as entity"
                    ],
                    "where" => [
                        ["where" => ["cm.id", "=", (int) $record_id]]
                    ]
                ];
                $model = new CampaignMaster();
                break;

            default :
                break;
        }
        try {
            $output["entity"] = "";

            $entity_data = $model->fetch($condition);
            if (!empty($entity_data["entity"])) {
                $output["entity"] = $entity_data["entity"];
            }

            if ($action == SHAppComponent::getValue("actions/EMAILS/ACTION_GROUP_ID")) {
                $output["entity"] = empty($entity_data["entity"]) ? "(no subject)" : $entity_data["entity"];
                $output["entity"] = htmlspecialchars(html_entity_decode($output["entity"], ENT_QUOTES));
            }

        } catch(\Exception $e) { }

        $model_activity = new Activity();

        $condition = [
            "fields" => [
                "a.id",
                "a.action_id",
                "a.created",
                "a.other_data",
                "a.sub_record_id"
            ],
            "where" => [
                ["where" => ["a.account_id", "=", $logged_in_account]],
                ["where" => ["a.user_id", "=", $logged_in_user]],
                ["where" => ["a.action_group_id", "=", $action]],
                ["where" => ["a.record_id", "=", $record_id]]
            ],
            "order_by" => "a.id DESC",
            "limit" => 100
        ];

        //fetch url for email and campaign link click
        $account_link_master = new AccountLinkMaster();

        $fetch_url = [
            "fields" => [
                "url"
            ],
            "where" => [
                ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
            ]
        ];

        if(!empty($params["sub_action"])) {
            $sub_action_id = SHAppComponent::getValue("actions/" . $params["action"] . "/" . $params["sub_action"]);
            $condition["where"][] = ["where" => ["a.action_id", "=", $sub_action_id]]; 
        }

        if(!empty($params["sub_record_id"])) {
            $sub_record_id = StringComponent::decodeRowId($params["sub_record_id"]);
            $condition["where"][] = ["where" => ["a.sub_record_id", "=", $sub_record_id]]; 
        }

        try {
            $activity_rows = $model_activity->fetchAll($condition);
            if (count($activity_rows) > 0) {
                foreach ($activity_rows as $row) {

                    $actions = SHAppComponent::getValue("actions/" . $params["action"]);
                    $action_code = array_search($row["action_id"], $actions);
                    $actions_group = SHAppComponent::getValue("action_groups");
                    $action_index = array_search($params["action"] ,array_column($actions_group, "code"));
                    $sub_action_group = $actions_group[$action_index]["sub_actions"];

                    $sub_group_index = array_search($action_code ,array_column($sub_action_group, "code"));
                    
                    $action = $sub_action_group[$sub_group_index]["name"];
                    $acted_at = ""; 
                    $acted_on = "";
                    $location = "";
                    $browser_name = "";
                    $os_name = "";

                    if(!empty($row["other_data"])) {
                        $other_data = json_decode($row["other_data"], true);

                        if (preg_match("/via ggpht\.com GoogleImageProxy/", $other_data["ua"])) {
                            $location = "Read using gmail client";
                        } else {
                            $browser_name = $other_data["bn"];
                            $os_name = $other_data["os"];

                            if (!empty($other_data["c"]) && !empty($other_data["s"]) && !empty($other_data["co"])) {
                               $location = $other_data["c"] . ", " . $other_data["s"] . ", " . $other_data["co"];
                            }
                        }
                    }

                    if ( $row["created"] > 0 ) {
                        $acted_on = DateTimeComponent::getDateTimeAgo($row["created"], $user_timezone);
                        $acted_at = DateTimeComponent::convertDateTime($row["created"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
                    }

                    $row_data = [
                        "id" => StringComponent::encodeRowId($row["id"]),
                        "action" => $action,
                        "acted_at" => $acted_at,
                        "acted_timeago" => $acted_on,
                        "location" => $location,
                        "bs_name" => $browser_name,
                        "os_name" => $os_name
                    ];

                    if (!empty($row["sub_record_id"])) {
                        $fetch_url["where"]["where"] = ["where" => ["id", "=", $row["sub_record_id"] ]];
                        $link_data = $account_link_master->fetch($fetch_url);
                        if(!empty($link_data["url"])) {
                            $row_data["url"] = $link_data["url"];
                        }
                    }

                    $output["rows"][] = $row_data;
                }
            } else {
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        
        return $response->withJson($output, 200);
    }

    /**
    * get all email communication preferences for user 
    *
    * @param $request (object): Request object
    * @param $response (object): Response object
    * @param $args (array): Route parameters
    *
    * @return (object) Response object    
    */
    public function GetEmailPreferences(ServerRequestInterface $request, ResponseInterface $response, $args){

        $output = $email_comm_pref_array = $email_const_vars_data = $email_comm_pref_const_id = $email_comm_pref_const_default_val = $email_preferences_userdata = [];
        $email_comm_pref_const_str = "";

        // Get logged in user details
        $logged_in_userid = SHAppComponent::getUserId();        

        $email_comm_pref_array = $this->email_prefences_const_array;

        //get const id from communication preferences.
        try{
            $app_const_vars = new AppConstantVars();
            $app_const_data = [
                "fields" => [  
                    "acv.id",  
                    "acv.code",            
                    "acv.val"
                ],
                "where" => [                    
                    ["whereIn" => ["acv.code",$email_comm_pref_array]]
                ]
            ];            
            $email_const_vars_data = $app_const_vars->fetchAll($app_const_data);
        }catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        //get email preference const id
        if(is_array($email_const_vars_data) && count($email_const_vars_data) > 0){
             foreach($email_const_vars_data as $email_const_var_data){
                $email_comm_pref_const_id_code[$email_const_var_data["code"]]= $email_const_var_data["id"];
                $email_comm_pref_const_id[]= $email_const_var_data["id"];
                $email_comm_pref_const_default_val[$email_const_var_data["code"]] = $email_const_var_data["val"];
             }             
        }        

        //get user setting for email commnuication preferences
        try{
            $user_settings = new UserSettings();
            $condition_setting = [
                "fields" => [  
                    "us.app_constant_var_id",              
                    "us.value"
                ],
                "where" => [
                    ["where" => ["us.user_id", "=", $logged_in_userid]],
                    ["whereIn" => ["us.app_constant_var_id",$email_comm_pref_const_id]]
                ]
            ];         
           
            $email_preferences_userdata = $user_settings->fetchAll($condition_setting);
        }catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }       

        if(is_array($email_preferences_userdata) && count($email_preferences_userdata) == 0){

            //no record found so insert default values for all preferences for all.
            foreach($email_comm_pref_array as $email_comm_pref_code){
                try{                                        
                    $save_data = [
                        "user_id" => $logged_in_userid,
                        "app_constant_var_id" => $email_comm_pref_const_id_code[$email_comm_pref_code],
                        "value" => $email_comm_pref_const_default_val[$email_comm_pref_code],
                        "modified" => DateTimeComponent::getDateTime()
                    ];     

                    $user_settings->save($save_data);                

                    $output[$email_comm_pref_code] = $email_comm_pref_const_default_val[$email_comm_pref_code];

                }catch(\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                } 
            }
            
        }else{
           //iterate each record and send appropiate value to output 
           foreach($email_preferences_userdata as $email_preferences_info){

               $setting_code_val = array_search($email_preferences_info["app_constant_var_id"], $email_comm_pref_const_id_code);

               if($setting_code_val !== false){
                 $output[$setting_code_val] = $email_preferences_info["value"];
               } 
           }
        }   
        return $response->withJson($output, 200);
    }

    /**
    * update all email communication preferences for user 
    *
    * @param $request (object): Request object
    * @param $response (object): Response object
    * @param $args (array): Route parameters
    *
    * @return (object) Response object 
    */

    public function UpdateEmailPreferences(ServerRequestInterface $request, ResponseInterface $response, $args){

        $output = [];  
        $success = false;      

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();

        // Get request parameters
        $request_params = $request->getParsedBody();

        if(is_array($request_params) && count($request_params) > 0){

            $email_comm_pref_array = $this->email_prefences_const_array;

            //get const id from communication preferences.
            try{
                $app_const_vars = new AppConstantVars();
                $app_const_data = [
                    "fields" => [  
                        "acv.id",  
                        "acv.code",            
                        "acv.val"
                    ],
                    "where" => [                    
                        ["whereIn" => ["acv.code",$email_comm_pref_array]]
                    ]
                ];            
                $email_const_vars_data = $app_const_vars->fetchAll($app_const_data);
            }catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

            //get email preference const id
            if(is_array($email_const_vars_data) && count($email_const_vars_data) > 0){
                 foreach($email_const_vars_data as $email_const_var_data){
                    $email_comm_pref_const_id_code[$email_const_var_data["code"]]= $email_const_var_data["id"];            
                 }             
            }

            //convert each key in uppercase and cast value in integer
            foreach ($request_params as $email_pref_code => $email_pref_value) {
                $email_pref_request_data[strtoupper($email_pref_code)] = (int) $email_pref_value;   
            }    

            //check opt out option value
            $email_opt_out_value = $email_pref_request_data["U_EMAIL_PREF_OPTOUT"];

            //if it is checked means 1 then uncheck all other email preferences means make it 0
            if($email_opt_out_value == 1){
              foreach ($request_params as $email_pref_code => $email_pref_value) {
                //do not reset for opt out option
                if($email_pref_code != "U_EMAIL_PREF_OPTOUT"){
                    $email_pref_request_data[$email_pref_code] = (int) 0;   
                }
               }  
            }            

            $user_settings = new UserSettings();

            //iterate each update value
            foreach ($email_pref_request_data as $email_pref_code => $email_pref_value) {

                $update_data = array("value"=> $email_pref_value);

                $email_pref_id = $email_comm_pref_const_id_code[$email_pref_code];

                $conditions_email_pref_update = [
                    "where" => [
                        ["where" => ["user_id", "=", $logged_in_user]],
                        ["where" => ["app_constant_var_id", "=", $email_pref_id]],
                    ]
                ];                
                //$user_settings
                try{
                    //update user settings
                    $user_settings->update($update_data,$conditions_email_pref_update);
                    $success = true;                    
                }catch(\Exception $e) {
                    $success = false;     
                }
            }

        }

        $output["success"] = $success;

        return $response->withJson($output, 200);
    }

}
