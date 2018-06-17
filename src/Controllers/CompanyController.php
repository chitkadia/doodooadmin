<?php
/**
 * Company related functionality
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
use \App\Models\AccountCompanies;
use \App\Models\AccountContacts;

class CompanyController extends AppController {

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
            "user_id" => $logged_in_user,
            "account_id" => $logged_in_account,
            "is_owner" => $is_owner,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        // Get data
        $model_account_companies = new AccountCompanies();

        try {
            $data = $model_account_companies->getListData($query_params, $other_values);

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
                "name" => $row["name"],
                "phone" => $row["contact_phone"],
                "website" => $row["website"],
                "city" => $row["city"],
                "status" => $status,
                "contacts" => $row["contacts"],
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

        if (isset($request_params["name"])) {
            $request_data["name"] = $request_params["name"];
        }
        if (isset($request_params["address"])) {
            $request_data["address"] = $request_params["address"];
        }
        if (isset($request_params["city"])) {
            $request_data["city"] = $request_params["city"];
        }
        if (isset($request_params["state"])) {
            $request_data["state"] = $request_params["state"];
        }
        if (isset($request_params["country"])) {
            $request_data["country"] = $request_params["country"];
        }
        if (isset($request_params["zipcode"])) {
            $request_data["zipcode"] = $request_params["zipcode"];
        }
        if (isset($request_params["logo"])) {
            $request_data["logo"] = $request_params["logo"];
        }
        if (isset($request_params["website"])) {
            $request_data["website"] = $request_params["website"];
        }
        if (isset($request_params["contact_phone"])) {
            $request_data["contact_phone"] = $request_params["contact_phone"];
        }
        if (isset($request_params["contact_fax"])) {
            $request_data["contact_fax"] = $request_params["contact_fax"];
        }
        if (isset($request_params["short_info"])) {
            $request_data["short_info"] = $request_params["short_info"];
        }
        if (isset($request_params["status"])) {
            $request_data["status"] = $request_params["status"];
        }

        // Validate request
        $request_validations = [
            "name" => [
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

        // Save record
        $model_account_companies = new AccountCompanies();

        try {
            // Check if company is exist or not
            $condition = [
                "fields" => [
                    "id"
                ],
                "where" => [
                    ["where" => ["name", "LIKE",$request_data["name"]]],
                    ["where" => ["account_id", "=",$logged_in_account]],
                ]
            ];

            $check_contact = $model_account_companies->fetch($condition);

            if (!empty($check_contact)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/COMPANY_ALREADY_EXIST");     
            }

            $save_data = [
                "account_id" => $logged_in_account,
                "source_id" => $logged_in_source,
                "name" => trim($request_data["name"]),
                "address" => trim($request_data["address"]),
                "city" => trim($request_data["city"]),
                "state" => trim($request_data["state"]),
                "country" => trim($request_data["country"]),
                "zipcode" => trim($request_data["zipcode"]),
                "logo" => trim($request_data["logo"]),
                "website" => trim($request_data["website"]),
                "contact_phone" => trim($request_data["contact_phone"]),
                "contact_fax" => trim($request_data["contact_fax"]),
                "short_info" => trim($request_data["short_info"]),
                "status" => $request_data["status"],
                "created" => DateTimeComponent::getDateTime(),
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($model_account_companies->save($save_data) !== false) {
                $output["message"] = "Company created successfully.";

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
        $model_account_companies = new AccountCompanies();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_companies->checkRowValidity($row_id, $other_values);

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

        if (isset($request_params["name"])) {
            $request_data["name"] = $request_params["name"];
        }
        if (isset($request_params["address"])) {
            $request_data["address"] = $request_params["address"];
        }
        if (isset($request_params["city"])) {
            $request_data["city"] = $request_params["city"];
        }
        if (isset($request_params["state"])) {
            $request_data["state"] = $request_params["state"];
        }
        if (isset($request_params["country"])) {
            $request_data["country"] = $request_params["country"];
        }
        if (isset($request_params["zipcode"])) {
            $request_data["zipcode"] = $request_params["zipcode"];
        }
        if (isset($request_params["website"])) {
            $request_data["website"] = $request_params["website"];
        }
        if (isset($request_params["contact_phone"])) {
            $request_data["contact_phone"] = $request_params["contact_phone"];
        }
        if (isset($request_params["contact_fax"])) {
            $request_data["contact_fax"] = $request_params["contact_fax"];
        }
        if (isset($request_params["short_info"])) {
            $request_data["short_info"] = $request_params["short_info"];
        }
        if (isset($request_params["notes"])) {
            $request_data["notes"] = $request_params["notes"];
        }
        if (isset($request_params["status"])) {
            $request_data["status"] = $request_params["status"];
        }

        // Validate request
        $request_validations = [
            "name" => [
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

        // Save data
        try {

            // Check if company is exist or not
            $condition = [
                "fields" => [
                    "id"
                ],
                "where" => [
                    ["where" => ["name", "LIKE",$request_data["name"]]],
                    ["where" => ["id", "<>",$row_id]],
                    ["where" => ["account_id", "=",$logged_in_account]],
                ]
            ];

            $check_contact = $model_account_companies->fetch($condition);

            if (!empty($check_contact)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/COMPANY_ALREADY_EXIST");     
            }


            $save_data = [
                "id" => $row_id,
                "name" => trim($request_data["name"]),
                "address" => trim($request_data["address"]),
                "city" => trim($request_data["city"]),
                "state" => trim($request_data["state"]),
                "country" => trim($request_data["country"]),
                "zipcode" => trim($request_data["zipcode"]),
                "website" => trim($request_data["website"]),
                "notes" => trim($request_data["notes"]),
                "contact_phone" => trim($request_data["contact_phone"]),
                "contact_fax" => trim($request_data["contact_fax"]),
                "short_info" => trim($request_data["short_info"]),
                "status" => $request_data["status"]
            ];

            if ($model_account_companies->save($save_data) !== false) {
                $output["message"] = "Company details updated successfully.";
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
        $model_account_companies = new AccountCompanies();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_companies->checkRowValidity($row_id, $other_values);

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
                    "ac.id",
                    "ac.account_id",
                    "ac.source_id",
                    "ac.name",
                    "ac.address",
                    "ac.city",
                    "ac.state",
                    "ac.country",
                    "ac.zipcode",
                    "ac.logo",
                    "ac.website",
                    "ac.contact_phone",
                    "ac.contact_fax",
                    "ac.short_info",
                    "ac.total_mail_sent",
                    "ac.total_mail_failed",
                    "ac.total_mail_replied",
                    "ac.total_mail_bounced",
                    "ac.total_link_clicks",
                    "ac.total_document_viewed",
                    "ac.total_document_facetime",
                    "ac.status"                ],
                "where" => [
                    ["where" => ["ac.id", "=", $row_id]]
                ]
            ];
            $row = $model_account_companies->fetch($condition);

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
            "source_id" => $row["source_id"],
            "name" => $row["name"],
            "address" => $row["address"],
            "city" => $row["city"],
            "state" => $row["state"],
            "country" => $row["country"],
            "zipcode" => $row["zipcode"],
            "logo" => $row["logo"],
            "website" => $row["website"],
            "contact_phone" => $row["contact_phone"],
            "contact_fax" => $row["contact_fax"],
            "short_info" => $row["short_info"],
            "total_mail_sent" => $row["total_mail_sent"],
            "total_mail_failed" => $row["total_mail_failed"],
            "total_mail_replied" => $row["total_mail_replied"],
            "total_mail_bounced" => $row["total_mail_bounced"],
            "total_link_clicks" => $row["total_link_clicks"],
            "total_document_viewed" => $row["total_document_viewed"],
            "total_document_facetime" => DateTimeComponent::showDateTime($total_document_facetime, $user_timezone),
            "status" => $status
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
        $model_account_companies = new AccountCompanies();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_companies->checkRowValidity($row_id, $other_values);

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
            if ($model_account_companies->save($save_data) !== false) {
                try {
                    // Update contact company to null
                    $model_account_contacts = new AccountContacts();

                    $save_data = [
                        "account_company_id" => null,
                        "modified" => DateTimeComponent::getDateTime()
                    ];
                    $condition = [
                        "where" => [
                            ["where" => ["account_company_id", "=", $row_id]],
                            ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                        ]
                    ];
                    $model_account_contacts->update($save_data, $condition);

                } catch(\Exception $e) { }

                $output["message"] = "Company deleted successfully.";

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

        //$row_id = StringComponent::decodeRowId($id);
        $row_id = $id;

        // Check if record is valid
        $model_account_companies = new AccountCompanies();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_companies->checkRowValidity($row_id, $other_values);

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
            if ($model_account_companies->save($save_data) !== false) {
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
     * Get company feed
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

    /**
     * Get company contacts
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getContacts(ServerRequestInterface $request, ResponseInterface $response, $args) {
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

        //$row_id = StringComponent::decodeRowId($id);
        $row_id = $id;

        // Set parameters
        $query_params = [
            "order_by" => "id",
            "order" => "DESC"
        ];

        // Other values for condition
        $other_values = [
            "account_company_id" => $row_id,
            "account_id" => $logged_in_account,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        // Get Data
        $model_account_contacts = new AccountContacts();

        try {
            $data = $model_account_contacts->getListDataByCompany($query_params, $other_values);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $app_constants = SHAppComponent::getValue("app_constant");
        
        // Process data and prepare for output
        $output["rows"] = [];

        foreach ($data["rows"] as $row) {
            $status = array_search($row["status"], $app_constants);
            
            $total_document_facetime = DateTimeComponent::convertDateTime($row["total_document_facetime"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);

            $row_data = [
                "id" => $row["id"],
                "source_id" => $row["name"],
                "email" => trim($row["email"]),
                "first_name" => trim($row["first_name"]),
                "last_name" => trim($row["last_name"]),
                "total_mail_sent" => $row["total_mail_sent"],
                "total_mail_failed" => $row["total_mail_failed"],
                "total_mail_replied" => $row["total_mail_replied"],
                "total_mail_bounced" => $row["total_mail_bounced"],
                "total_link_clicks" => $row["total_link_clicks"],
                "total_document_viewed" => $row["total_document_viewed"],
                "total_document_facetime" => DateTimeComponent::showDateTime($total_document_facetime, $user_timezone),
                "status" => $status
            ];

            $output["rows"][] = $row_data;
        }

        return $response->withJson($output, 200);
    }

}