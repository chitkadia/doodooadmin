<?php
/**
 * Forgot password related functionality
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
use \App\Models\UserPassResetRequests;
use \App\Models\UserMaster;
use \App\Components\Mailer\TransactionMailsComponent;

class ResetPasswordController extends AppController {

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Forgot password
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function forgotPassword(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch credential from request
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

        // Check if user is valid
        $model_user = new UserMaster();

        try {
            $condition = [
                "fields" => [
                    "um.id",
                    "um.first_name",
                    "um.email",
                    "um.status AS user_status"
                ],
                "where" => [
                    ["where" => ["um.email", "=", $request_data["email"]]]
                ]
            ];
            $row = $model_user->fetch($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $status_active = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
        $status_delete = SHAppComponent::getValue("app_constant/STATUS_DELETE");
        $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");

        if (empty($row)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/FP_ACCOUNT_NOT_FOUND");
        }

        if ($row["user_status"] == $status_delete) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/USER_DELETED");
        }

        if ($row["user_status"] != $status_active) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/USER_ACCOUNT_NOT_ACTIVE");
        }

        // Send reset password link
        $model_user_reset_password = new UserPassResetRequests();

        $user_id = $row["id"];
        $request_date = DateTimeComponent::getDateTime();
        $expires_at = $request_date + RESET_PASSWORD_LINK_EXPIRE;
        $token = StringComponent::encodeRowId([$user_id, $request_date, $expires_at]);

        try {
            // $save_data = [
            //     "user_id" => $user_id,
            //     "request_token" => $token,
            //     "request_date" => $request_date,
            //     "expires_at" => $expires_at,
            //     "modified" => DateTimeComponent::getDateTime()
            // ];

            // if ($model_user_reset_password->save($save_data) === false) {
            //     // Fetch error code & message and return the response
            //     return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            // }

            // // Set previous active reset password links to expire
            // $current_timestamp = DateTimeComponent::getDateTime();
            // $inserted_record_id = $model_user_reset_password->getLastInsertId();

            // $save_data = [
            //     "expires_at" => $current_timestamp,
            //     "modified" => $current_timestamp
            // ];
            // $condition = [
            //     "where" => [
            //         ["where" => ["user_id", "=", $user_id]],
            //         ["where" => ["id", "<>", $inserted_record_id]],
            //         ["where" => ["expires_at", ">=", $current_timestamp]],
            //         ["where" => ["password_reset", "<>", $flag_yes]]
            //     ]
            // ];
            // $updated = $model_user_reset_password->update($save_data, $condition);

            $request_origin_url = SHAppComponent::getRequestOrigin();

            //Send email to user to reset password
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
            $info["subject"] = "{FirstName}, here's the link to reset your password";
            $info["subject"] = str_replace("{FirstName}", $row["first_name"], $info["subject"]);
            $info["content"] = file_get_contents(\EMAIL_TEPLATES_FOLDER . "/forgot_password_email.html");
            $info["content"] = str_replace("{FirstName}", $row["first_name"], $info["content"]);
            $info["content"] = str_replace("{ConfirmEmailLink}", $request_origin_url . "/reset-password/".$token, $info["content"]);

            $result = TransactionMailsComponent::mailSendSmtp($info);
            //echo $result["message"];

            $output["code"] = $token;
            $output["message"] = "Reset password instructions have been sent to your registered email address.";

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * Resend reset password link mail
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function resendResetPasswordCode(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $route = $request->getAttribute("route");
        $code = $route->getArgument("code");

        // Validate request
        if (empty($code)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $decode_code = StringComponent::decodeRowId($code, true);
        list($user_id, $request_date, $expires_at) = $decode_code;

        $flag_no = SHAppComponent::getValue("app_constant/FLAG_NO");

        // Check if valid code
        $model_user_reset_password = new UserPassResetRequests();

        try {
            $condition = [
                "fields" => [
                    "uprr.id",
                    "uprr.expires_at"
                ],
                "where" => [
                    ["where" => ["uprr.user_id", "=", $user_id]],
                    ["where" => ["uprr.request_date", "=", $request_date]],
                    ["where" => ["uprr.expires_at", "=", $expires_at]],
                    ["where" => ["uprr.password_reset", "=", $flag_no]]
                ]
            ];
            $row = $model_user_reset_password->fetch($condition);

            if (empty($row["id"])) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/INVALID_FP_RESET_CODE");
            }

            $current_time = \App\Components\DateTimeComponent::getDateTime();
            if ($row["expires_at"] < $current_time) {
                // Fetch error code & message and return the response
                return \App\Components\ErrorComponent::outputError($response, "api_messages/FP_RESET_CODE_EXPIRE");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Check if user is valid
        $model_user = new UserMaster();

        try {
            $condition = [
                "fields" => [
                    "um.id",
                    "um.first_name",
                    "um.email",
                    "um.status AS user_status",
                    "am.status AS account_status"
                ],
                "where" => [
                    ["where" => ["um.id", "=", $user_id]],
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
        $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");

        if (empty($row)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/FP_ACCOUNT_NOT_FOUND");
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

        if ($row["user_status"] != $status_active) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/USER_ACCOUNT_NOT_ACTIVE");
        }

        $request_origin_url = SHAppComponent::getRequestOrigin();

        //Send email to user to reset password
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
        $info["subject"] = "{FirstName}, here's the link to reset your password";
        $info["subject"] = str_replace("{FirstName}", $row["first_name"], $info["subject"]);
        $info["content"] = file_get_contents(\EMAIL_TEPLATES_FOLDER . "/forgot_password_email.html");
        $info["content"] = str_replace("{FirstName}", $row["first_name"], $info["content"]);
        $info["content"] = str_replace("{ConfirmEmailLink}", $request_origin_url . "/user/reset-password/".$code, $info["content"]);

        $result = TransactionMailsComponent::mailSendSmtp($info);

        $output["message"] = "Reset password instructions have been sent to your registered email address.";

        return $response->withJson($output, 200);
    }

    /**
     * Check if reset password request is valid or not
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function isValidRequest(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $route = $request->getAttribute("route");
        $code = $route->getArgument("code");

        // Validate request
        if (empty($code)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $decode_code = StringComponent::decodeRowId($code, true);
        list($user_id, $request_date, $expires_at) = $decode_code;

        $flag_no = SHAppComponent::getValue("app_constant/FLAG_NO");

        // Check if valid code
        $model_user_reset_password = new UserPassResetRequests();

        try {
            $condition = [
                "fields" => [
                    "uprr.id",
                    "uprr.expires_at"
                ],
                "where" => [
                    ["where" => ["uprr.user_id", "=", $user_id]],
                    ["where" => ["uprr.request_date", "=", $request_date]],
                    ["where" => ["uprr.expires_at", "=", $expires_at]],
                    ["where" => ["uprr.password_reset", "=", $flag_no]]
                ]
            ];
            $row = $model_user_reset_password->fetch($condition);

            if (empty($row["id"])) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/INVALID_FP_RESET_CODE");
            }

            $current_time = \App\Components\DateTimeComponent::getDateTime();
            if ($row["expires_at"] < $current_time) {
                // Fetch error code & message and return the response
                return \App\Components\ErrorComponent::outputError($response, "api_messages/FP_RESET_CODE_EXPIRE");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Check if user is valid
        $model_user = new UserMaster();

        try {
            $condition = [
                "fields" => [
                    "um.id",
                    "um.status AS user_status",
                    "am.status AS account_status"
                ],
                "where" => [
                    ["where" => ["um.id", "=", $user_id]],
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
        $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");

        if (empty($row)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/FP_ACCOUNT_NOT_FOUND");
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

        if ($row["user_status"] != $status_active) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/USER_ACCOUNT_NOT_ACTIVE");
        }

        $output["valid"] = true;

        return $response->withJson($output, 200);
    }

    /**
     * Reset password
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function resetPassword(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $route = $request->getAttribute("route");
        $code = $route->getArgument("code");

        // Validate request
        if (empty($code)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        // Get request parameters (POST)
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];

        if (isset($request_params["password"])) {
            $request_data["password"] = $request_params["password"];
        }

        // Validate request (post data)
        $request_validations = [
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

        $decode_code = StringComponent::decodeRowId($code, true);
        list($user_id, $request_date, $expires_at) = $decode_code;

        $flag_no = SHAppComponent::getValue("app_constant/FLAG_NO");
   

        // Check if user is valid
        $model_user = new UserMaster();

        try {
            $condition = [
                "fields" => [
                    "um.id",
                    "um.status AS user_status"
                ],
                "where" => [
                    ["where" => ["um.id", "=", $user_id]],
                ]
            ];
            $row = $model_user->fetch($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $status_active = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
        $status_delete = SHAppComponent::getValue("app_constant/STATUS_DELETE");
        $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");

        if (empty($row)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/FP_ACCOUNT_NOT_FOUND");
        }

        if ($row["user_status"] == $status_delete) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/USER_DELETED");
        }

        if ($row["user_status"] != $status_active) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/USER_ACCOUNT_NOT_ACTIVE");
        }

        // Generate password
        $password_salt = StringComponent::generatePasswordSalt();
        $password = StringComponent::encryptPassword($request_data["password"], $password_salt);

        try {
            // Update user password
            $save_data = [
                "id" => $user_id,
                "password" => $password,
                "password_salt_key" => $password_salt,
                "modified_date" => DateTimeComponent::getDateTime()
            ];

            if ($model_user->save($save_data) !== false) {
                $output["message"] = "Your password has been reset successfully.";

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

}
