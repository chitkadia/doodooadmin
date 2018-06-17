<?php
/**
 * Links related functionality
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
use \App\Components\Mailer\TransactionMailsComponent;
use \App\Models\DocumentLinks;
use \App\Models\DocumentLinkFiles;
use \App\Models\AccountContacts;
use \App\Models\AccountCompanies;
use \App\Models\DocumentMaster;
use \App\Models\DocumentLinkVisits;
use \App\Models\UserMaster;

class LinksController extends AppController {

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
        $user_time_zone = SHAppComponent::getUserTimeZone();

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
        if (!empty($params["user"])) {
            $user_id = StringComponent::decodeRowId(trim($params["user"]));

            if (!empty($user_id)) {
                $query_params["user"] = $user_id;
            }
        }
        if (!empty($params["type"])) {
            $query_params["type"] = trim($params["type"]);
        }
        if (!empty($params["status"])) {
            $query_params["status"] = trim($params["status"]);
        }
        if (!empty($params["query"])) {
            $query_params["query"] = SHAppComponent::prepareSearchText($params["query"]);
        }

        // Other values for condition
        $other_values = [
            "user_id" => $logged_in_user,
            "account_id" => $logged_in_account,
            "is_owner" => $is_owner,
            "type" => SHAppComponent::getValue("app_constant/DOCUMENT_SPACE"),
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        // Get data
        $model_document_links = new DocumentLinks();

        try {
            $data = $model_document_links->getListData($query_params, $other_values);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $app_constants = SHAppComponent::getValue("app_constant");

        $view_link = SHAppComponent::getValue("app_constant/DOC_VIEWER_VIEW");
        $preview_link = SHAppComponent::getValue("app_constant/DOC_VIEWER_PREVIEW");

        // Process data and prepare for output
        $output["total_records"] = $data["total_records"];
        $output["total_pages"] = $data["total_pages"];
        $output["current_page"] = $data["current_page"];
        $output["per_page"] = $data["per_page"];
        $output["rows"] = [];

        foreach ($data["rows"] as $row) {
            $status = array_search($row["status"], $app_constants);

            if ($row["contact_first_name"] == "") {
                $opened_by_contact = $row["contact_email"];
            } else {
                $opened_by_contact = $row["contact_first_name"];
            }

            if ($row["contact_status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
                $opened_by = $opened_by_contact . " (Deleted)";
            } else {
                $opened_by = $opened_by_contact;
            }

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "name" => $row["name"],
                // "type" => $row["type"],
                "status" => $status,
                "doc_domain" => $row["link_domain"],
                "doc_link_code" => $row["link_code"],
                "doc_view_link" => $row["link_domain"]."/".$view_link."/".$row["link_code"],
                "doc_preview_link" => $row["link_domain"]."/".$preview_link."/".$row["link_code"],
                "total_visits" => $row["total_visit"],
                "total_time_spent" => (int)$row["total_time_spent"],
                "opened_by" => $opened_by,
                "opened_at" => $row["last_opened_at"],
                "formatted_opened_at" => DateTimeComponent::getDateTimeAgo($row["last_opened_at"], $user_time_zone),
                "created_by" => $row["created_by_fn"]
            ];

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
        $user_timezone = SHAppComponent::getUserTimeZone();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];

        if (isset($request_params["link_type"])) {
            $request_data["link_type"] = $request_params["link_type"];
        }
        if (isset($request_params["indi_cust"])) {
            $request_data["indi_cust"] = $request_params["indi_cust"];
        }
        if (isset($request_params["documents"])) {
            $request_data["documents"] = $request_params["documents"];
        }
        // if (isset($request_params["account_folder_id"])) {
        //     $request_data["account_folder_id"] = $request_params["account_folder_id"];
        // }
        if (isset($request_params["email"])) {
            $request_data["email"] = $request_params["email"];
        }
        if (isset($request_params["company"])) {
            $request_data["company"] = $request_params["company"];
        }
        if (isset($request_params["name"])) {
            $request_data["name"] = $request_params["name"];
        }
        // if (isset($request_params["short_description"])) {
        //     $request_data["short_description"] = $request_params["short_description"];
        // }
        // if (isset($request_params["type"])) {
        //     $request_data["type"] = $request_params["type"];
        // }
        if (isset($request_params["is_set_expiration_date"])) {
            $request_data["is_set_expiration_date"] = $request_params["is_set_expiration_date"];
        }
        if (isset($request_params["expires_at"])) {
            $request_data["expires_at"] = $request_params["expires_at"];
        }
        if (isset($request_params["allow_download"])) {
            $request_data["allow_download"] = $request_params["allow_download"];
        }
        if (isset($request_params["forward_tracking"])) {
            $request_data["forward_tracking"] = $request_params["forward_tracking"];
        }
        if (isset($request_params["email_me_when_viewed"])) {
            $request_data["email_me_when_viewed"] = $request_params["email_me_when_viewed"];
        }
        if (isset($request_params["password_protected"])) {
            $request_data["password_protected"] = $request_params["password_protected"];
        }
        if (isset($request_params["access_password"])) {
            $request_data["access_password"] = $request_params["access_password"];
        }
        if (isset($request_params["ask_visitor_info"])) {
            $request_data["ask_visitor_info"] = $request_params["ask_visitor_info"];
        }
        if (isset($request_params["visitor_info_payload"])) {
            $request_data["visitor_info_payload"] = $request_params["visitor_info_payload"];
        }
        if (isset($request_params["snooze_notifications"])) {
            $request_data["snooze_notifications"] = $request_params["snooze_notifications"];
        }
        if (isset($request_params["remind_not_viewed"])) {
            $request_data["remind_not_viewed"] = $request_params["remind_not_viewed"];
        }
        if (isset($request_params["remind_at"])) {
            $request_data["remind_at"] = $request_params["remind_at"];
        }
        if (isset($request_params["status"])) {
            $request_data["status"] = $request_params["status"];
        }
        if (isset($request_params["time_zone"])) {
            $request_data["time_zone"] = $request_params["time_zone"];
        }

        // Validate request
        $request_validations = [];
        if($request_data["link_type"] == 1) {
            $request_validations = [
                "name" => [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ]
            ];
            if($request_data["ask_visitor_info"] == 1) {
                $request_validations["visitor_info_payload"] = [
                    ["type" => Validator::FIELD_JSON_ARRAY]
                ];
            }
        } elseif ($request_data["link_type"] == 2) {
            if ($request_data["indi_cust"] == 1) {
                $request_validations = [
                    "email" => [
                        ["type" => Validator::FIELD_REQ_NOTEMPTY]
                    ]
                ];
            } else {
                $request_validations = [
                    "name" => [
                        ["type" => Validator::FIELD_REQ_NOTEMPTY]
                    ]
                ];
                if($request_data["ask_visitor_info"] == 1) {
                    $request_validations["visitor_info_payload"] = [
                        ["type" => Validator::FIELD_JSON_ARRAY]
                    ];
                }
            }
        }
        if($request_data["is_set_expiration_date"] == 1) {
            $request_validations["expires_at"] = [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ];
        }
        if($request_data["password_protected"] == 1) {
            $request_validations["access_password"] = [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ];
        }
        if($request_data["remind_not_viewed"] == 1) {
            $request_validations["remind_at"] = [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ];
        }
        if($request_data["is_set_expiration_date"] == 1) {
            $request_validations["expires_at"] = [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ];
        }
        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        // $account_company_id = "";
        $account_company_id = NULL;
        // it looks for company and if not found then create new company
        if(isset($request_data["company"]) && $request_data["company"] != "") {
            // Check company exists or not
            $model_account_company = new AccountCompanies();

            try {
                $condition = [
                    "fields" => [
                        "ac.id"
                    ],
                    "where" => [
                        ["where" => ["ac.name", "=", $request_data["company"]]]
                    ]
                ];
                $row = $model_account_company->fetch($condition);
            } catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

            if(!empty($row["id"])) {
                $account_company_id = $row["id"];
            } else {
                try {
                    // Create new company
                    $save_data = [
                        "account_id" => $logged_in_account,
                        "source_id" => $logged_in_source,
                        "name" => $request_data["company"],
                        "address" => "",
                        "city" => "",
                        "state" => "",
                        "country" => "",
                        "zipcode" => "",
                        "logo" => "",
                        "website" => "",
                        "contact_phone" => "",
                        "contact_fax" => "",
                        "short_info" => "",
                        "status" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                        "created" => DateTimeComponent::getDateTime(),
                        "modified" => DateTimeComponent::getDateTime()
                    ];
                    if($model_account_company->save($save_data) !== false) {
                        $account_company_id = $model_account_company->getLastInsertId();
                    } else {
                        // Fetch error code & message and return the response
                        return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                    }
                } catch(\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }
            }
        }

        $account_contact_id = "";
        // if created for single contact
        if(isset($request_data["email"]) && $request_data["email"] != "") {
            // Check contact exists or not
            $model_account_contacts = new AccountContacts();

            try {
                $condition = [
                    "fields" => [
                        "aco.id"
                    ],
                    "where" => [
                        ["where" => ["aco.email", "=", $request_data["email"]]]
                    ]
                ];
                $row = $model_account_contacts->fetch($condition);
            } catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

            if(!empty($row["id"])) {
                $account_contact_id = $row["id"];
            } else {
                try {
                    // Create new contact
                    $save_data = [
                        "account_id" => $logged_in_account,
                        "source_id" => $logged_in_source,
                        "account_company_id" => $account_company_id,
                        "email" => $request_data["email"],
                        "first_name" => "",
                        "last_name" => "",
                        "status" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                        "created" => DateTimeComponent::getDateTime(),
                        "modified" => DateTimeComponent::getDateTime()
                    ];
                    if($model_account_contacts->save($save_data) !== false) {
                        $account_contact_id = $model_account_contacts->getLastInsertId();
                    } else {
                        // Fetch error code & message and return the response
                        return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                    }
                } catch(\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }
            }
        }

        // Save record
        $model_document_links = new DocumentLinks();

        $link_domain = \DOC_VIEWER_BASE_URL;
        $link_code = "";
        $visitor_slug = "";
        $payload = json_encode($request_data["visitor_info_payload"]);
        $view_link_code = "";
        $view_link = SHAppComponent::getValue("app_constant/DOC_VIEWER_VIEW");

        try {
            $default_timezone = \DEFAULT_TIMEZONE;
            $expires_at = 0;
            $remind_at = 0;

            if ($request_data["is_set_expiration_date"] == 1) {
                if (isset($request_data["expires_at"]) && $request_data["expires_at"] != "") {
                    $expires_at = DateTimeComponent::convertDateTime($request_data["expires_at"], false, $request_data["time_zone"], $default_timezone, "");
                }
            }
            
            if ($request_data["remind_not_viewed"] == 1) {
                if (isset($request_data["remind_at"]) && $request_data["remind_at"] != "") {
                    $remind_at = DateTimeComponent::convertDateTime($request_data["remind_at"], false, $request_data["time_zone"], $default_timezone, "");
                }
            }
            
            $save_data = [
                "account_id" => $logged_in_account,
                "user_id" => $logged_in_user,
                "source_id" => $logged_in_source,
                "account_company_id" => $account_company_id,
                "name" => trim($request_data["name"]),
                // "short_description" => trim($request_data["short_description"]),
                "link_domain" => $link_domain,
                "link_code" => $link_code,
                "type" => trim($request_data["link_type"]),
                "is_set_expiration_date" => trim($request_data["is_set_expiration_date"]),
                "expires_at" => $expires_at,
                "forward_tracking" => trim($request_data["forward_tracking"]),
                "email_me_when_viewed" => trim($request_data["email_me_when_viewed"]),
                "allow_download" => trim($request_data["allow_download"]),
                "password_protected" => trim($request_data["password_protected"]),
                "access_password" => trim($request_data["access_password"]),
                "ask_visitor_info" => trim($request_data["ask_visitor_info"]),
                "visitor_info_payload" => $payload,
                "snooze_notifications" => trim($request_data["snooze_notifications"]),
                "remind_not_viewed" => trim($request_data["remind_not_viewed"]),
                "remind_at" => $remind_at,
                "visitor_slug" => $visitor_slug,
                "status" => $request_data["status"],
                "created" => DateTimeComponent::getDateTime(),
                "modified" => DateTimeComponent::getDateTime()
            ];

            if( $account_contact_id != "" ) {
                $save_data["account_contact_id"] = $account_contact_id;    
            }           

            if ($model_document_links->save($save_data) !== false) {
                $document_link_id = $model_document_links->getLastInsertId();
                $generate_link_code_array = [
                    $document_link_id,
                    $logged_in_user,
                    $logged_in_account
                ];
                $document_link_parameter_encoded = StringComponent::encodeRowId($generate_link_code_array);
                $document_link_id_encoded = StringComponent::encodeRowId($document_link_id);

                $visitor_slug_insert = $document_link_id_encoded."me".DateTimeComponent::getDateTime();

                // if( $request_data["type"] == 1 ) {
                //     $link_code = "/s/" . $document_link_id_encoded;
                // } else {
                //     $link_code = "/m/" . $document_link_id_encoded;
                // }
                $link_code = $document_link_parameter_encoded;

                $save_data = [
                    "id" => $document_link_id,
                    "link_code" => $link_code,
                    "visitor_slug" => $visitor_slug_insert
                ];
                $model_document_links->save($save_data);

                $view_link_code = $link_domain."/".$view_link."/".$link_code;

                // Fetch Link Creator Details
                $model_user_master = new UserMaster();

                try {
                    $condition_user_master = [
                        "fields" => [
                            "um.email"
                        ],
                        "where" => [
                            ["where" => ["um.id", "=", $logged_in_user]]
                        ]
                    ];
                    $row_user_master = $model_user_master->fetch($condition_user_master);

                } catch(\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

                if ($request_data["link_type"] == 2 && $request_data["indi_cust"] == 1) {

                    $admin_email = $row_user_master["email"];
                    $email_to_link_send = $request_data["email"];
                    $subject_msg = $admin_email." just shared a document";
                    $document_share_content = "<a href='".$admin_email."'>" . $admin_email . "</a> just share a document with you.<br/>To view that document please click on view document.";
                    
                    //Send email to user to verify account
                    $info["smtp_details"]["host"] = HOST;
                    $info["smtp_details"]["port"] = PORT;
                    $info["smtp_details"]["encryption"] = ENCRYPTION;
                    $info["smtp_details"]["username"] = USERNAME;
                    $info["smtp_details"]["password"] = PASSWORD;

                    $info["from_email"] = FROM_EMAIL;
                    $info["from_name"] = FROM_NAME;

                    $info["to"] = $email_to_link_send;
                    $info["cc"] = '';
                    $info["bcc"] = '';
                    $info["subject"] = $subject_msg;
                    $info["content"] = file_get_contents(\EMAIL_TEPLATES_FOLDER . "/doc_set_share.html");
                    $info["content"] = str_replace("{Subject}", $subject_msg, $info["content"]);
                    $info["content"] = str_replace("{Content}", $document_share_content, $info["content"]);
                    $info["content"] = str_replace("{AdminEmail}", $admin_email, $info["content"]);
                    $info["content"] = str_replace("{Email}", $email_to_link_send, $info["content"]);
                    $info["content"] = str_replace("{Link_Code}", $view_link_code, $info["content"]);
                    $info["content"] = str_replace("{Button_Text}", "View Document", $info["content"]);

                    $result = TransactionMailsComponent::mailSendSmtp($info);
                }

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Save document link files
        $model_document_link_files = new DocumentLinkFiles();
        try {
            // if documets shared
            if( count($request_data["documents"]) > 0 ) {
                foreach ($request_data["documents"] as $document_id) {
                    $decoded_doc_id = StringComponent::decodeRowId($document_id);
                    $save_data = [
                        "document_link_id" => $document_link_id,
                        "document_id" => $decoded_doc_id,
                        "status" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                        "modified" => DateTimeComponent::getDateTime()
                    ];
                    $model_document_link_files->save($save_data);
                }
            }
            // if folder shared
            // if($request_data["account_folder_id"] != '') {
            //     $save_data = [
            //         "document_link_id" => $document_link_id,
            //         "account_folder_id" => $request_data["account_folder_id"],
            //         "status" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
            //         "modified" => DateTimeComponent::getDateTime()
            //     ];
            //     $model_document_link_files->save($save_data);
            // }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $output["view_link_code"] = $view_link_code;
        $output["message"] = "Space created successfully.";

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
        $user_timezone = SHAppComponent::getUserTimeZone();
        $current_datetime = DateTimeComponent::getDateTime();

        //constant variavbles
        $status_delete = SHAppComponent::getValue("app_constant/STATUS_DELETE");
        $flag_no = SHAppComponent::getValue("app_constant/FLAG_NO");

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
        $model_document_links = new DocumentLinks();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => $status_delete
            ];

            $valid = $model_document_links->checkRowValidity($row_id, $other_values);

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

        if (isset($request_params["link_type"])) {
            $request_data["link_type"] = $request_params["link_type"];
        }
        if (isset($request_params["documents"])) {
            $request_data["documents"] = $request_params["documents"];
        }
        if (isset($request_params["account_folder_id"])) {
            $request_data["account_folder_id"] = $request_params["account_folder_id"];
        }
        if (isset($request_params["email"])) {
            $request_data["email"] = $request_params["email"];
        }
        if (isset($request_params["company"])) {
            $request_data["company"] = $request_params["company"];
        }
        if (isset($request_params["name"])) {
            $request_data["name"] = $request_params["name"];
        }
        // if (isset($request_params["short_description"])) {
        //     $request_data["short_description"] = $request_params["short_description"];
        // }
        // if (isset($request_params["type"])) {
        //     $request_data["type"] = $request_params["type"];
        // }
        if (isset($request_params["is_set_expiration_date"])) {
            $request_data["is_set_expiration_date"] = $request_params["is_set_expiration_date"];
        }
        if (isset($request_params["expires_at"])) {
            $request_data["expires_at"] = $request_params["expires_at"];
        }
        if (isset($request_params["allow_download"])) {
            $request_data["allow_download"] = $request_params["allow_download"];
        }
        if (isset($request_params["forward_tracking"])) {
            $request_data["forward_tracking"] = $request_params["forward_tracking"];
        }
        if (isset($request_params["email_me_when_viewed"])) {
            $request_data["email_me_when_viewed"] = $request_params["email_me_when_viewed"];
        }
        if (isset($request_params["password_protected"])) {
            $request_data["password_protected"] = $request_params["password_protected"];
        }
        if (isset($request_params["access_password"])) {
            $request_data["access_password"] = $request_params["access_password"];
        }
        if (isset($request_params["ask_visitor_info"])) {
            $request_data["ask_visitor_info"] = $request_params["ask_visitor_info"];
        }
        if (isset($request_params["visitor_info_payload"])) {
            $request_data["visitor_info_payload"] = $request_params["visitor_info_payload"];
        }
        if (isset($request_params["snooze_notifications"])) {
            $request_data["snooze_notifications"] = $request_params["snooze_notifications"];
        }
        if (isset($request_params["remind_not_viewed"])) {
            $request_data["remind_not_viewed"] = $request_params["remind_not_viewed"];
        }
        if (isset($request_params["remind_at"])) {
            $request_data["remind_at"] = $request_params["remind_at"];
        }
        if (isset($request_params["status"])) {
            $request_data["status"] = $request_params["status"];
        }
        if (isset($request_params["only_doc"])) {
            $request_data["only_doc"] = $request_params["only_doc"];
        }
        if (isset($request_params["time_zone"])) {
            $request_data["time_zone"] = $request_params["time_zone"];
        }

        if ($request_data["only_doc"] == $flag_no) {
            // Validate request
            $request_validations = [];
            if($request_data["link_type"] == 1 || $request_data["link_type"] == 3) {
                $request_validations = [
                    "name" => [
                        ["type" => Validator::FIELD_REQ_NOTEMPTY]
                    ]
                ];
                if($request_data["ask_visitor_info"] == 1) {
                    $request_validations["visitor_info_payload"] = [
                        ["type" => Validator::FIELD_JSON_ARRAY]
                    ];
                }
            } elseif ($request_data["link_type"] == 2) {
                $request_validations = [
                    "email" => [
                        ["type" => Validator::FIELD_REQ_NOTEMPTY]
                    ]
                ];
            }
            if($request_data["is_set_expiration_date"] == 1) {
                $request_validations["expires_at"] = [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ];
            }
            if($request_data["password_protected"] == 1) {
                $request_validations["access_password"] = [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ];
            }
            if($request_data["remind_not_viewed"] == 1) {
                $request_validations["remind_at"] = [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ];
            }
            if($request_data["is_set_expiration_date"] == 1) {
                $request_validations["expires_at"] = [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ];
            }
            $validation_errors = Validator::validate($request_validations, $request_data);

            // If request is invalid
            if (!empty($validation_errors)) {
                // Fetch error code & message and return the response
                $additional_message = implode("\n", $validation_errors);
                return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
            }
        }

        // Save record
        $model_document_links = new DocumentLinks();

        try {
            $condition_previous_data = [
                "fields" => [
                    "password_protected",
                    "access_password",
                    "ask_visitor_info",
                    "remind_at",
                    "visitor_info_payload"
                ],
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];
            $previous_data_row = $model_document_links->fetch($condition_previous_data);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if ($request_data["only_doc"] == $flag_no) {
            $payload = json_encode($request_data["visitor_info_payload"]);
            try {
                $default_timezone = \DEFAULT_TIMEZONE;
                $expires_at = $flag_no;
                $remind_at = $flag_no;
                
                if ($request_data["is_set_expiration_date"] == 1) {
                    if (isset($request_data["expires_at"]) && $request_data["expires_at"] != "") {
                        $expires_at = DateTimeComponent::convertDateTime($request_data["expires_at"], false, $request_data["time_zone"], $default_timezone, "");
                    }
                }
                
                if ($request_data["remind_not_viewed"] == 1) {
                    if (isset($request_data["remind_at"]) && $request_data["remind_at"] != "") {
                        $remind_at = DateTimeComponent::convertDateTime($request_data["remind_at"], false, $request_data["time_zone"], $default_timezone, "");
                    }
                }
                
                $save_data = [
                    "id" => $row_id,
                    "name" => trim($request_data["name"]),
                    // "short_description" => trim($request_data["short_description"]),
                    "is_set_expiration_date" => trim($request_data["is_set_expiration_date"]),
                    "expires_at" => $expires_at,
                    "forward_tracking" => trim($request_data["forward_tracking"]),
                    "email_me_when_viewed" => trim($request_data["email_me_when_viewed"]),
                    "allow_download" => trim($request_data["allow_download"]),
                    "password_protected" => trim($request_data["password_protected"]),
                    "access_password" => trim($request_data["access_password"]),
                    "ask_visitor_info" => trim($request_data["ask_visitor_info"]),
                    "visitor_info_payload" => $payload,
                    "snooze_notifications" => trim($request_data["snooze_notifications"]),
                    "remind_not_viewed" => trim($request_data["remind_not_viewed"]),
                    "remind_at" => $remind_at,
                    "status" => $request_data["status"],
                    "modified" => $current_datetime
                ];

                // If visotor info payload changed or password changed, then update the visitor slug value
                if ($previous_data_row["access_password"] != trim($request_data["access_password"]) || $previous_data_row["visitor_info_payload"] != $payload) {
                    $save_data["visitor_slug"] = $id."me".$current_datetime;
                }

                // If not viewed time is updated then update the not_viewed_mail_sent flag
                if ($previous_data_row["remind_at"] != $remind_at) {
                    $save_data["not_viewed_mail_sent"] = $flag_no;
                }

                if ($model_document_links->save($save_data) == false) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

            } catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
        }

        // Save document link files
        $model_document_link_files = new DocumentLinkFiles();
        try {
            // if documets shared
            if( count($request_data["documents"]) > $flag_no ) {

                // Update all records status to delete
                $condition = [
                    "where" => [
                        ["where" => ["document_link_id", "=", $row_id]]
                    ]
                ];
                $save_data = [
                    "status" => $status_delete,
                    "modified" => $current_datetime
                ];
                $updated = $model_document_link_files->update($save_data, $condition);

                // Set inserted records' status to active or add new records
                $itk = 0;
                foreach ($request_data["documents"] as $document_id) {
                    $decoded_row_id = StringComponent::decodeRowId($document_id);
                    // Fetch is data already exists
                    $condition = [
                        "fields" => [
                            "dlf.id"
                        ],
                        "where" => [
                            ["where" => ["dlf.document_link_id", "=", $row_id]],
                            ["where" => ["dlf.document_id", "=", $decoded_row_id]]
                        ]
                    ];
                    $already_exists = $model_document_link_files->fetch($condition);

                    $save_data_doc_link_file = [
                        "document_link_id" => $row_id,
                        "document_id" => $decoded_row_id,
                        "status" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                        "modified" => $current_datetime
                    ];
                    if (!empty($already_exists["id"])) {
                        $save_data_doc_link_file["id"] = $already_exists["id"];
                    }
                    $saved = $model_document_link_files->save($save_data_doc_link_file);
                }
            }
            
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if ($request_data["only_doc"] == $flag_no) {
            $output["message"] = "Link updated successfully.";
        } else {
            $output["message"] = "Document updated successfully.";
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
    public function oldview(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $user_timezone = SHAppComponent::getUserTimeZone();
        $default_timezone = \DEFAULT_TIMEZONE;

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");
        $decryptId = StringComponent::encryptor("decrypt",$id);
        $row_id = StringComponent::encodeRowId($decryptId);
        $app->response->redirect($app->urlFor('/emails'), 303);
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
        $default_timezone = \DEFAULT_TIMEZONE;

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
        $model_document_links = new DocumentLinks();
        $model_document_master = new DocumentMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_document_links->checkRowValidity($row_id, $other_values);

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
                    "dl.id",
                    "dl.name",
                    // "dl.short_description",
                    "dl.type",
                    "dl.is_set_expiration_date",
                    "dl.expires_at",
                    "dl.allow_download",
                    "dl.password_protected",
                    "dl.access_password",
                    "dl.forward_tracking",
                    "dl.email_me_when_viewed",
                    "dl.ask_visitor_info",
                    "dl.visitor_info_payload",
                    "dl.snooze_notifications",
                    "dl.remind_not_viewed",
                    "dl.remind_at",
                    "dl.status",
                    "dl.link_domain",
                    "dl.link_code",
                    "dl.last_opened_at",
                    "um.first_name",
                    "um.last_name"
                ],
                "where" => [
                    ["where" => ["dl.id", "=", $row_id]]
                ],
                "join" => [
                    "user_master"
                ]
            ];
            $row = $model_document_links->fetch($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get documents list of given document link id
        $condition_filename = [
            "doc_link_id" => $row_id,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
            "active" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE")
        ];
        $all_doc_data = $model_document_master->getAllDocuments($condition_filename);

        $app_constants = SHAppComponent::getValue("app_constant");

        $expires_at = "NA";
        if ( $row["expires_at"] != "" && $row["expires_at"] > 0 ) {
            $expires_at = DateTimeComponent::convertDateTime($row["expires_at"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
        }

        $remind_at = "NA";
        if ( $row["remind_at"] != "" && $row["remind_at"] > 0 ) {
            $remind_at = DateTimeComponent::convertDateTime($row["remind_at"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
        }

        $view_link = SHAppComponent::getValue("app_constant/DOC_VIEWER_VIEW");
        $preview_link = SHAppComponent::getValue("app_constant/DOC_VIEWER_PREVIEW");

        // Prepare output of data
        $output = [
            "id" => StringComponent::encodeRowId($row["id"]),
            "name" => $row["name"],
            // "short_description" => $row["short_description"],
            "type" => $row["type"],
            "is_set_expiration_date" => $row["is_set_expiration_date"],
            "expires_at" => $expires_at,
            "forward_tracking" => $row["forward_tracking"],
            "email_me_when_viewed" => $row["email_me_when_viewed"],
            "allow_download" => $row["allow_download"],
            "password_protected" => $row["password_protected"],
            "access_password" => $row["access_password"],
            "ask_visitor_info" => $row["ask_visitor_info"],
            "visitor_info_payload" => $row["visitor_info_payload"],
            "snooze_notifications" => $row["snooze_notifications"],
            "remind_not_viewed" => $row["remind_not_viewed"],
            "remind_at" => $remind_at,
            "status" => $row["status"],
            "first_name" => $row["first_name"],
            "last_name" => $row["last_name"],
            "document_domain" => $row["link_domain"],
            "document_link_code" => $row["link_code"],
            "document_view_link" => $row["link_domain"]."/".$view_link."/".$row["link_code"],
            "document_preview_link" => $row["link_domain"]."/".$preview_link."/".$row["link_code"],
            "document_last_opened_at" => $row["last_opened_at"]
            // "documents_list" => $all_doc_data
        ];

        $output["documents_list"] = [];
        foreach($all_doc_data as $doc_data) {
            $status = array_search($doc_data["status"], $app_constants);
            $row_data = [
                "document_id" => StringComponent::encodeRowId($doc_data["document_id"]),
                "file_name" => $doc_data["file_name"],
                "file_path" => $doc_data["file_path"],
                "file_type" => $doc_data["file_type"],
                "status" => $status
            ];
            $output["documents_list"][] = $row_data;
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
        $model_document_links = new DocumentLinks();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_document_links->checkRowValidity($row_id, $other_values);

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
                    "dl.account_company_id",
                    "dl.account_contact_id",
                    "dl.name",
                    // "dl.short_description",
                    "dl.link_domain",
                    "dl.link_code",
                    // "dl.type",
                    "dl.is_set_expiration_date",
                    "dl.expires_at",
                    "dl.allow_download",
                    "dl.password_protected",
                    "dl.access_password",
                    "dl.ask_visitor_info",
                    "dl.visitor_info_payload",
                    "dl.snooze_notifications",
                    "dl.remind_not_viewed",
                    "dl.remind_at",
                    "dl.status"
                ],
                "where" => [
                    ["where" => ["dl.id", "=", $row_id]]
                ]
            ];
            $row = $model_document_links->fetch($condition);

            // Save data
            $save_data = [
                "account_id" => $logged_in_account,
                "user_id" => $logged_in_user,
                "source_id" => $logged_in_source,
                "account_company_id" => $row["account_company_id"],
                "account_contact_id" => $row["account_contact_id"],
                "name" => $row["name"],
                // "short_description" => $row["short_description"],
                "link_domain" => $row["link_domain"],
                "link_code" => $row["link_code"],
                // "type" => $row["type"],
                "is_set_expiration_date" => $row["is_set_expiration_date"],
                "expires_at" => $row["expires_at"],
                "allow_download" => $row["allow_download"],
                "password_protected" => $row["password_protected"],
                "access_password" => $row["access_password"],
                "ask_visitor_info" => $row["ask_visitor_info"],
                "visitor_info_payload" => $row["visitor_info_payload"],
                "snooze_notifications" => $row["snooze_notifications"],
                "remind_not_viewed" => $row["remind_not_viewed"],
                "remind_at" => $row["remind_at"],
                "status" => $row["status"],
                "created" => DateTimeComponent::getDateTime(),
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($model_document_links->save($save_data) !== false) {
                $document_link_id = $model_document_links->getLastInsertId();
                $document_link_id_encoded = StringComponent::encodeRowId($document_link_id);

                if( $row["type"] == 1 ) {
                    $link_code = "/s/" . $document_link_id_encoded;
                } else {
                    $link_code = "/m/" . $document_link_id_encoded;
                }

                $save_data = [
                    "id" => $document_link_id,
                    "link_code" => $link_code
                ];
                $model_document_links->save($save_data);
            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Save document link files
        $model_document_link_files = new DocumentLinkFiles();
        try {
            //Get documents in link
            $condition = [
                "fields" => [
                    "dlf.document_id",
                    // "dlf.account_folder_id",
                    "dlf.status"
                ],
                "where" => [
                    ["where" => ["dlf.document_link_id", "=", $row_id]]
                ]
            ];
            $row = $model_document_link_files->fetchAll($condition);
            if(count($row) > 0) {
                foreach ($row as $document) {
                    $save_data = [
                        "document_link_id" => $document_link_id,
                        "document_id" => $document["document_id"],
                        // "account_folder_id" => $document["account_folder_id"],
                        "status" => $document["status"],
                        "modified" => DateTimeComponent::getDateTime()
                    ];
                    $model_document_link_files->save($save_data);
                }
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $output["message"] = "Link copied successfully.";

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

        // Check if record is valid
        $model_document_links = new DocumentLinks();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_document_links->checkRowValidity($row_id, $other_values);

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
            if ($model_document_links->save($save_data) !== false) {
                $output["message"] = "Link deleted successfully.";

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
        $model_document_links = new DocumentLinks();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_document_links->checkRowValidity($row_id, $other_values);

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
            if ($model_document_links->save($save_data) !== false) {
                $output["message"] = "Link updated successfully.";

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
     * Update status for document
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function docStatusUpdate(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");
        $link_id = $route->getArgument("link");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);
        $link_decoded_id = StringComponent::decodeRowId($link_id);

        // Check if record is valid
        $model_document_link_files = new DocumentLinkFiles();

        try {
            // Other values for condition
            $other_values = [
                "link_id" => $link_decoded_id,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_document_link_files->checkRowValidity($row_id, $other_values);

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
            "STATUS_DELETE"
        ];
        if (!in_array($request_data["status"], $valid_status_values)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/INVALID_STATUS_VALUE");
        }

        // Set value
        try {
            $condition_dlf_update = [
                "where" => [
                    ["where" => ["document_link_id", "=", $link_decoded_id]],
                    ["where" => ["document_id", "=", $row_id]]
                ]
            ];
            $update_dlf_data = [
                "status" => SHAppComponent::getValue("app_constant/" . $request_data["status"]),
                "modified" => DateTimeComponent::getDateTime()
            ];
            $updated_dlf = $model_document_link_files->update($update_dlf_data, $condition_dlf_update);

            if ($updated_dlf !== false) {
                if ($request_data["status"] == "STATUS_DELETE") {
                    $output["message"] = "Document successfully removed from link.";
                } else {
                    $output["message"] = "Document status updated successfully.";
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
     * Get document performance details
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getSpacePerformance(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $user_time_zone = SHAppComponent::getUserTimeZone();

        // Get request parameters
        $params = $request->getQueryParams();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        $model_document_links = new DocumentLinks();

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

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_document_links->checkSpaceAvailability($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Other values for condition
        $other_values_all_doc = [
            "space_id" => $row_id,
            "user_id" => $logged_in_user,
            "account_id" => $logged_in_account,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        try {
            $data = $model_document_links->getOverallPerformance($row_id);
            
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        try {
            $row_data = $model_document_links->getDocumentwisePerformance($other_values_all_doc);
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        try {
            $row_data_visits = $model_document_links->getSpacePerformanceDetails($query_params, $other_values_all_doc);
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if ($data["name"] == "") {
            if ($data["contact_first_name"] == "") {
                $direct_link_title = $data["contact_email"];
            } else {
                $direct_link_title = $data["contact_first_name"];
            }
            $link_name_main_title = "Direct link to " . $direct_link_title;
        } else {
            $link_name_main_title = $data["name"];
        }

        $output["total_download"] = (int)$data["total_download"];
        $output["total_time_spent"] = (int)$data["total_time_spent"];
        $output["formatted_total_time_spent"] = gmdate("H:i:s", $data["total_time_spent"]);
        $output["total_visit"] = (int)$data["total_visit"];
        $output["link_name"] = $link_name_main_title;
        $output["total_space_pages"] = (int)$data["total_space_pages"];
        $output["total_viewed_pages"] = (int)$data["total_viewed_pages"];
        $output["doc_summary"] = [];

        foreach ($row_data as $row) {
            if ($row["contact_first_name"] != "") {
                $visiter_name_contact = $row["contact_first_name"];
            } else {
                $visiter_name_contact = $row["contact_email"];
            }

            if ($row["contact_status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
                $visiter_name = $visiter_name_contact . " (Deleted)";
            } else {
                $visiter_name = $visiter_name_contact;
            }

            $row_data_file_list = [
                "doc_name" => $row["file_name"],
                "total_time_spent" => (int)$row["total_time_spent"],
                "formatted_total_time_spent" => gmdate("H:i:s", $row["total_time_spent"]),
                "file_pages" => (int)$row["file_pages"],
                "visiter_name" => $visiter_name,
                "last_opened_at" => $row["last_opened_at"],
                "last_visit_at" => DateTimeComponent::getDateTimeAgo($row["last_opened_at"], $user_time_zone),
                "total_visit" => $row["total_visits"],
                "total_viewed_page" => $row["total_viewed_pages"]
            ];

            $output["doc_summary"][] = $row_data_file_list;
        }

        // Process data and prepare for output
        $output["total_records"] = $row_data_visits["total_records"];
        $output["total_pages"] = $row_data_visits["total_pages"];
        $output["current_page"] = $row_data_visits["current_page"];
        $output["per_page"] = $row_data_visits["per_page"];
        $output["rows"] = [];

        foreach ($row_data_visits["rows"] as $row_visit) {
            
            if ($row_visit["contact_first_name"] != "") {
                $visiter_name_cn = $row_visit["contact_first_name"];
            } else {
                $visiter_name_cn = $row_visit["contact_email"];
            }

            if ($row_visit["contact_status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
                $visiter_name = $visiter_name_cn . " (Deleted)";
            } else {
                $visiter_name = $visiter_name_cn;
            }

            $link_name_to_show = $row_visit["link_name"];
            if ($row_visit["link_name"] == "") {
                $link_name_to_show = "Direct link to " . $row_visit["direct_link_to"];
            }

            $row_data_foreach = [
                "visit_id" => StringComponent::encodeRowId($row_visit["visit_id"]),
                // "visiter_id" => $visiter_name,
                "opened_by" => $visiter_name,
                "link_name" => $link_name_to_show,
                "formatted_time_spent" => gmdate("H:i:s", $row_visit["time_spent"]),
                "time_spent" => (int)$row_visit["time_spent"],
                "location" => json_decode($row_visit["location"]),
                "link_last_visited" => $row_visit["visited_at"],
                "formatted_link_last_visited" => DateTimeComponent::getDateTimeAgo($row_visit["visited_at"], $user_time_zone),
                "file_pages" => (int)$row_visit["total_space_pages"],
                "total_page_viewed" => (int)$row_visit["total_visited_pages"]
            ];
            
            $output["rows"][] = $row_data_foreach;
        }

        return $response->withJson($output, 200);
    }

    /**
     * Get link visit details
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getSpaceVisit(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $user_time_zone = SHAppComponent::getUserTimeZone();

        // Get request parameters
        $params = $request->getQueryParams();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");
        $link_id = $route->getArgument("link_id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }
        
        $row_id = StringComponent::decodeRowId($id);
        $space_id = StringComponent::decodeRowId($link_id);

        $model_document_link_visits = new DocumentLinkVisits();

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

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "space_id" => $space_id,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_document_link_visits->checkSpaceAvailability($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        try {
            $data = $model_document_link_visits->getOverallPerformance($row_id, $space_id);
            
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Other values for condition
        $other_values = [
            "visit_id" => $row_id,
            "document_link_id" => $space_id,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        try {
            $row_data = $model_document_link_visits->getDocumentwisePerformance($other_values);
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if ($data["account_contact_id"] != null) {
            $other_values_space_visit = [
                "account_contact_id" => $data["account_contact_id"],
                "visit_id" => $row_id,
                "document_link_id" => $space_id,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            try {
                $row_data_visits = $model_document_link_visits->getSpaceAllVisitDetails($query_params, $other_values_space_visit);
            } catch (\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
        } else {
            $row_data_visits["total_records"] = 0;
            $row_data_visits["total_pages"] = 1;
            $row_data_visits["current_page"] = $query_params["page"];
            $row_data_visits["per_page"] = $query_params["per_page"];
        }

        if ($data["contact_first_name"] != "") {
            $visiter_name_cnn = $data["contact_first_name"];
        } else {
            $visiter_name_cnn = $data["contact_email"];
        }

        if ($data["contact_status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
            $visiter_name = $visiter_name_cnn . " (Deleted)";
        } else {
            $visiter_name = $visiter_name_cnn;
        }

        $output["visitor_name"] = $visiter_name;
        $output["formatted_visit_date"] = DateTimeComponent::convertDateTime($data["modified"], true, \DEFAULT_TIMEZONE, $user_time_zone, \APP_TIME_FORMAT);
        $output["visit_date"] = $data["modified"];
        $output["total_download"] = (int)$data["total_download"];
        $output["total_time_spent"] = (int)$data["total_time_spent"];
        $output["link_name"] = $data["name"];
        $output["total_space_pages"] = (int)$data["total_space_pages"];
        $output["total_page_viewed"] = (int)$data["total_page_viewed"];
        $output["doc_summary"] = [];

        foreach ($row_data as $row) {
            if ($row["contact_first_name"] != "") {
                $visiter_name_doc_contact = $row["contact_first_name"];
            } else {
                $visiter_name_doc_contact = $row["contact_email"];
            }

            if ($row["contact_status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
                $visiter_name_doc = $visiter_name_doc_contact . " (Deleted)";
            } else {
                $visiter_name_doc = $visiter_name_doc_contact;
            }

            $row_data_file_list = [
                "doc_name" => $row["file_name"],
                "total_time_spent" => (int)$row["total_time_spent"],
                "formatted_total_time_spent" => gmdate("H:i:s", $row["total_time_spent"]),
                "file_pages" => (int)$row["file_pages"],
                "visiter_name" => $visiter_name_doc,
                "modified" => $row["modified"],
                "last_visit_at" => DateTimeComponent::getDateTimeAgo($row["modified"], $user_time_zone),
                "total_viewed_page" => $row["total_viewed_page"]
            ];

            $output["doc_summary"][] = $row_data_file_list;
        }

        // Process data and prepare for output
        $output["total_records"] = $row_data_visits["total_records"];
        $output["total_pages"] = $row_data_visits["total_pages"];
        $output["current_page"] = $row_data_visits["current_page"];
        $output["per_page"] = $row_data_visits["per_page"];
        $output["rows"] = [];

        if ($data["account_contact_id"] != null) {
            foreach ($row_data_visits["rows"] as $row_visit) {
                if ($row_visit["contact_first_name"] != "") {
                    $row_visit_visitor_name_cnn = $row_visit["contact_first_name"];
                } else {
                    $row_visit_visitor_name_cnn = $row_visit["contact_email"];
                }

                if ($row_visit["contact_status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
                    $row_visit_visitor_name = $row_visit_visitor_name_cnn . " (Deleted)";
                } else {
                    $row_visit_visitor_name = $row_visit_visitor_name_cnn;
                }

                $row_data_foreach = [
                    "visit_id" => StringComponent::encodeRowId($row_visit["id"]),
                    "visiter_name_visit" => $row_visit_visitor_name,
                    // "first_name" => $row_visit["contact_first_name"],
                    // "email" => $row_visit["contact_email"],
                    "link_name" => $row_visit["name"],
                    "formatted_time_spent" => gmdate("H:i:s", $row_visit["total_time_spent"]),
                    "time_spent" => (int)$row_visit["total_time_spent"],
                    "location" => json_decode($row_visit["location"]),
                    "link_last_visited" => $row_visit["modified"],
                    "formatted_link_last_visited" => DateTimeComponent::getDateTimeAgo($row_visit["modified"], $user_time_zone),
                    "file_pages" => (int)$row_visit["file_pages"],
                    "total_page_viewed" => (int)$row_visit["total_viewed_page"]
                ];

                $output["rows"][] = $row_data_foreach;
            }
        }

        return $response->withJson($output, 200);
    }

    /**
     * Generate link for the document
     *
     * @param $payload (array): Array of information
     *
     * @return (string) Generated link
     */
    public static function generateDocumentLink($payload = []) {
        $document_generated_url = "";

        $account_id = (isset($payload["account_id"])) ? (int) $payload["account_id"] : null;
        $user_id = (isset($payload["user_id"])) ? (int) $payload["user_id"] : null;
        $source_id = (isset($payload["source_id"])) ? (int) $payload["source_id"] : null;
        $account_contact_id = (isset($payload["contact_id"])) ? (int) $payload["contact_id"] : null;
        $document_id = (isset($payload["document_id"])) ? (int) $payload["document_id"] : null;
        $name = (isset($payload["name"])) ? $payload["name"] : "";

        $link_domain = \DOC_VIEWER_BASE_URL;
        $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");
        $flag_no = SHAppComponent::getValue("app_constant/FLAG_NO");

        $forward_tracking = $flag_yes;
        $email_me_when_viewed = $flag_yes;
        $view_link = SHAppComponent::getValue("app_constant/DOC_VIEWER_VIEW");

        if (isset($payload["from_email"])) {
            $type = SHAppComponent::getValue("app_constant/DOCUMENT_SPACE_EMAIL");

        } else if (isset($payload["from_campaign"])) {
            $type = SHAppComponent::getValue("app_constant/DOCUMENT_SPACE_CAMPAIGN");

        } else {
            $type = SHAppComponent::getValue("app_constant/CUSTOM_DOCUMENT_SPACE");
        }

        // Check if document is valid or not
        try {
            $model_document_master = new DocumentMaster();

            $condition = [
                "fields" => [
                    "id"
                ],
                "condition" => [
                    ["where" => ["id", "=", $document_id]],
                    ["where" => ["account_id", "=", $account_id]],
                    ["where" => ["status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                ]
            ];
            $row = $model_document_master->fetch($condition);

            if (empty($row["id"])) {
                $document_id = 0;
            }

            $model_account_contacts = new AccountContacts();

        } catch(\Exception $e) {
            $document_id = 0;
        }

        if (!empty($document_id) && !empty($user_id) && !empty($account_id)) {
            try {
                // Add document link
                $model_document_links = new DocumentLinks();

                $save_data = [
                    "account_id" => $account_id,
                    "user_id" => $user_id,
                    "source_id" => $source_id,
                    "account_contact_id" => $account_contact_id,
                    "name" => $name,
                    "link_domain" => $link_domain,
                    "link_code" => "",
                    "type" => $type,
                    "forward_tracking" => $forward_tracking,
                    "email_me_when_viewed" => $email_me_when_viewed,
                    "access_password" => "",
                    "ask_visitor_info" => $flag_no,
                    "visitor_slug" => "",
                    "created" => DateTimeComponent::getDateTime(),
                    "modified" => DateTimeComponent::getDateTime()
                ];

                if (empty($account_contact_id)) {
                    $save_data["ask_visitor_info"] = $flag_yes;
                    $save_data["visitor_info_payload"] = json_encode(["email", "name"]);
                }

                if($model_document_links->save($save_data) !== false) {
                    // Update document link data
                    $document_link_id = $model_document_links->getLastInsertId();

                    $generate_link_code_array = [$document_link_id, $user_id, $account_id];
                    $link_code = StringComponent::encodeRowId($generate_link_code_array);

                    $document_link_id_encoded = StringComponent::encodeRowId($document_link_id);
                    $visitor_slug = $document_link_id_encoded . "me" . DateTimeComponent::getDateTime();

                    $save_data = [
                        "id" => $document_link_id,
                        "link_code" => $link_code,
                        "visitor_slug" => $visitor_slug
                    ];
                    
                    $model_document_links->save($save_data);

                    // Save document file links
                    $model_document_link_files = new DocumentLinkFiles();

                    $save_data = [
                        "document_link_id" => $document_link_id,
                        "document_id" => $document_id,
                        "modified" => DateTimeComponent::getDateTime()
                    ];
                    $saved = $model_document_link_files->save($save_data);

                    $document_generated_url = $link_domain . "/" . $view_link . "/". $link_code;
                }

            } catch(\Exception $e) {}
        }

        return $document_generated_url;
    }

}
