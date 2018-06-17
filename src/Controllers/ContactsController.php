<?php
/**
 * Contacts related functionality
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
use \App\Models\AccountContacts;
use \App\Models\UserMaster;

class ContactsController extends AppController {

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

        // Other values for condition
        $other_values = [
            "account_id" => $logged_in_account,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        // Get data
        $model_account_contacts = new AccountContacts();

        try {
            $data = $model_account_contacts->getListData($query_params, $other_values);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $app_constants = SHAppComponent::getValue("app_constant");

        // Process data and prepare for output
        $output["total_records"] = $data["total_records"];
        $output["total_pages"] = $data["total_pages"];
        $output["current_page"] = $data["current_page"];
        $output["per_page"] = $data["per_page"];
        $output["rows"] = [];

        foreach ($data["rows"] as $row) {
            $status = array_search($row["status"], $app_constants);

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "email" => $row["email"],
                "first_name" => $row["first_name"],
                "last_name" => $row["last_name"],
                "status" => $status,
                "is_blocked" => (bool) $row["is_blocked"],
                "company" => $row["name"],
                "mail_sent" => $row["total_mail_sent"],
                "mail_success" => $row["total_mail_sent"] - $row["total_mail_failed"],
                "mail_fail" => $row["total_mail_failed"],
                "mail_bounce" => $row["total_mail_bounced"],
                "mail_replied" => $row["total_mail_replied"],
                "link_clicks" => $row["total_link_clicks"],
                "document_viewed" => $row["total_document_viewed"],
                "document_facetime" => $row["total_document_facetime"]
            ];

            if (!empty($row["last_error"])) {
                $row_data["last_error"] = $row["last_error"];
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

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $logged_in_source = SHAppComponent::getRequestSource();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];

        if (isset($request_params["account_company_id"])) {
            $request_data["account_company_id"] = StringComponent::decodeRowId($request_params["account_company_id"]);
        } 

        if (isset($request_params["email"])) {
            $request_data["email"] = $request_params["email"];
        }
        if (isset($request_params["first_name"])) {
            $request_data["first_name"] = $request_params["first_name"];
        }
        if (isset($request_params["last_name"])) {
            $request_data["last_name"] = $request_params["last_name"];
        }
        if (isset($request_params["city"])) {
            $request_data["city"] = $request_params["city"];
        }
        if (isset($request_params["country"])) {
            $request_data["country"] = $request_params["country"];
        }
        if (isset($request_params["phone"])) {
            $request_data["phone"] = $request_params["phone"];
        }
        if (isset($request_params["notes"])) {
            $request_data["notes"] = $request_params["notes"];
        }
        if (isset($request_params["is_blocked"])) {
            $request_data["is_blocked"] = $request_params["is_blocked"];
        }
        
        // Validate request
        $request_validations = [
            "email" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY],
                ["type" => Validator::FIELD_EMAIL]
            ],
            "first_name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ]
            // "last_name" => [
            //     ["type" => Validator::FIELD_REQ_NOTEMPTY]
            // ]
        ];
        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        // Save record
        $model_account_contacts = new AccountContacts();

        $model_user_master = new UserMaster();

        try {

            // Check if contact is exist or not
            $condition = [
                "fields" => [
                    "id",
                    "status"
                ],
                "where" => [
                    ["where" => ["email", "LIKE",$request_data["email"]]],
                    ["where" => ["account_id", "=",$logged_in_account]],
                ]
            ];

            $check_contact = $model_account_contacts->fetch($condition);

            $get_logged_user_data = $model_user_master->getUserById($logged_in_user,$logged_in_account);
            

            if (!empty($check_contact)) {
                if ($check_contact["status"] != SHAppComponent::getValue("app_constant/STATUS_DELETE") ) {
                    return ErrorComponent::outputError($response, "api_messages/CONTACT_ALREADY_EXIST");
                }     
            }

            // Check if requested email address same as logged in user's email address
            /*if (!empty($get_logged_user_data["email"])) {

                if ($get_logged_user_data["email"] == $request_data["email"]) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/CONTACT_ALREADY_EXIST");       
                }
            }*/

            if (!empty($check_contact)) {
                if ($check_contact["status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE") ) {
                    $save_data = [
                        "id" => $check_contact["id"],
                        "account_company_id" => $request_data["account_company_id"],
                        "first_name" => trim($request_data["first_name"]),
                        "last_name" => trim($request_data["last_name"]),
                        "city" => trim($request_data["city"]),
                        "country" => trim($request_data["country"]),
                        "phone" => trim($request_data["phone"]),
                        "notes" => trim($request_data["notes"]),
                        "status" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                        "is_blocked" => (int) $request_data["is_blocked"],
                        "modified" => DateTimeComponent::getDateTime()
                    ];

                    $message = "Deleted contact retrieved successfully.";
                }     
            } else {
                $save_data = [
                    "account_id" => $logged_in_account,
                    "account_company_id" => $request_data["account_company_id"],
                    "source_id" => $logged_in_source,
                    "email" => trim($request_data["email"]),
                    "first_name" => trim($request_data["first_name"]),
                    "last_name" => trim($request_data["last_name"]),
                    "city" => trim($request_data["city"]),
                    "country" => trim($request_data["country"]),
                    "phone" => trim($request_data["phone"]),
                    "notes" => trim($request_data["notes"]),
                    "is_blocked" => (int) $request_data["is_blocked"],
                    "created" => DateTimeComponent::getDateTime(),
                    "modified" => DateTimeComponent::getDateTime()
                ];

                $message = "Contact created successfully.";
            }

            if ($model_account_contacts->save($save_data) !== false) {
                $output["message"] = $message;

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            echo $e->getMessage();exit;
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

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);
        //$row_id = $id;

        // Check if record is valid
        $model_account_contacts = new AccountContacts();

        try {
            // Other values for condition
            $other_values = [
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_contacts->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];

        if (isset($request_params["email"])) {
            $request_data["email"] = $request_params["email"];
        }
        if (isset($request_params["first_name"])) {
            $request_data["first_name"] = $request_params["first_name"];
        }
        if (isset($request_params["last_name"])) {
            $request_data["last_name"] = $request_params["last_name"];
        }
        if (isset($request_params["account_company_id"])) {
            $request_data["account_company_id"] = StringComponent::decodeRowId($request_params["account_company_id"]);
        }
        if (isset($request_params["city"])) {
            $request_data["city"] = $request_params["city"];
        }
        if (isset($request_params["phone"])) {
            $request_data["phone"] = $request_params["phone"];
        }
        if (isset($request_params["country"])) {
            $request_data["country"] = $request_params["country"];
        }
        if (isset($request_params["notes"])) {
            $request_data["notes"] = $request_params["notes"];
        }
        if (isset($request_params["is_blocked"])) {
            $request_data["is_blocked"] = $request_params["is_blocked"];
        }
        
        // Validate request
        $request_validations = [
            // "email" => [
            //     ["type" => Validator::FIELD_REQ_NOTEMPTY],
            //     ["type" => Validator::FIELD_EMAIL]
            // ],
            "first_name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            // "last_name" => [
            //     ["type" => Validator::FIELD_REQ_NOTEMPTY]
            // ]
        ];
        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        // Save data
        try {

            // Check if contact is exist or not
            $condition = [
                "fields" => [
                    "id"
                ],
                "where" => [
                    ["where" => ["email", "LIKE",$request_data["email"]]],
                    ["where" => ["id", "<>",$row_id]],
                    ["where" => ["account_id", "=",$logged_in_account]],
                ]
            ];

            $check_contact = $model_account_contacts->fetch($condition);

            if (!empty($check_contact)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/CONTACT_ALREADY_EXIST");     
            }

            $save_data = [
                "id" => $row_id,
                //"email" => trim($request_data["email"]),
                "first_name" => trim($request_data["first_name"]),
                "last_name" => trim($request_data["last_name"]),
                "account_company_id" => $request_data["account_company_id"],
                "city" => trim($request_data["city"]),
                "country" => trim($request_data["country"]),
                "phone" => trim($request_data["phone"]),
                "notes" => trim($request_data["notes"]),
                "is_blocked" => (int) $request_data["is_blocked"],
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($model_account_contacts->save($save_data) !== false) {
                $output["message"] = "Contact details updated successfully.";
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
        //$row_id = $id;

        // Check if record is valid
        $model_account_contacts = new AccountContacts();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_contacts->checkRowValidity($row_id, $other_values);

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
                    "aco.id",
                    "aco.account_id",
                    "aco.account_company_id",
                    "aco.source_id",
                    "aco.first_name",
                    "aco.last_name",
                    "aco.email",
                    "aco.city",
                    "aco.country",
                    "aco.phone",
                    "aco.notes",
                    "aco.total_mail_sent",
                    "aco.total_mail_failed",
                    "aco.total_mail_replied",
                    "aco.total_mail_bounced",
                    "aco.total_link_clicks",
                    "aco.total_document_viewed",
                    "aco.total_document_facetime",
                    "aco.status",
                    "aco.is_blocked"
                ],
                "where" => [
                    ["where" => ["aco.id", "=", $row_id]]
                ]
            ];
            $row = $model_account_contacts->fetch($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $app_constants = SHAppComponent::getValue("app_constant");

        $status = array_search($row["status"], $app_constants);
        $total_document_facetime = DateTimeComponent::convertDateTime($row["total_document_facetime"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);

        // Prepare output of data
        $output = [
            "id" => StringComponent::encodeRowId($row["id"]),
            "account_id" => $row["account_id"],
            "account_company_id" => StringComponent::encodeRowId($row["account_company_id"]),
            "source_id" => $row["source_id"],
            "first_name" => $row["first_name"],
            "last_name" => $row["last_name"],
            "email" => $row["email"],
            "city" => $row["city"],
            "country" => $row["country"],
            "phone" => $row["phone"],
            "notes" => $row["notes"],
            "total_mail_sent" => $row["total_mail_sent"],
            "total_mail_failed" => $row["total_mail_failed"],
            "total_mail_replied" => $row["total_mail_replied"],
            "total_mail_bounced" => $row["total_mail_bounced"],
            "total_link_clicks" => $row["total_link_clicks"],
            "total_document_viewed" => $row["total_document_viewed"],
            "total_document_facetime" => DateTimeComponent::showDateTime($total_document_facetime, $user_timezone),
            "status" => $status,
            "is_blocked" => (bool) $row["is_blocked"]
        ];

        if (!empty($row["last_error"])) {
            $output["last_error"] = $row["last_error"];
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

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);
        //$row_id = $id;

        // Check if record is valid
        $model_account_contacts = new AccountContacts();

        try {
            // Other values for condition
            $other_values = [
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_contacts->checkRowValidity($row_id, $other_values);

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
            if ($model_account_contacts->save($save_data) !== false) {
                $output["message"] = "Contact deleted successfully.";

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
        $model_account_contacts = new AccountContacts();
       
        try {
            // Other values for condition
            $other_values = [
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];
           
            $valid = $model_account_contacts->checkRowValidity($row_id, $other_values);

            if (!$valid) {              
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        // Get request parameters
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

        // Set value
        try {
            $save_data = [
                "id" => $row_id,
                "status" => SHAppComponent::getValue("app_constant/" . $request_data["status"]),
                "modified" => DateTimeComponent::getDateTime()
            ];
            if ($model_account_contacts->save($save_data) !== false) {
                $output["message"] = "Status updated successfully.";

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
     * Get contact feed
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

        return $response->withJson($output, 200);
    }

}