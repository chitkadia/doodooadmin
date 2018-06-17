<?php
/**
 * Emails related functionality
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
use \App\Components\SHActivity;
use \App\Models\EmailMaster;
use \App\Models\EmailRecipients;
use \App\Models\AccountContacts;
use \App\Models\AccountSendingMethods;
use \App\Models\AccountLinkMaster;
use \App\Models\EmailLinks;
use \App\Components\Mailer\GmailComponent;
use \App\Components\Mailer\SMTPComponent;

class EmailsController extends AppController {

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
        if (!empty($params["user"])) {
            if ( strtolower($params["user"]) != "all" ) {
                $user_id = StringComponent::decodeRowId(trim($params["user"]));

                if (!empty($user_id)) {
                    $query_params["user"] = $user_id;
                }
            }
        }
        if (!empty($params["status"])) {
            if ( strtolower($params["status"]) != "all" ) {
                $status_arr = explode("_", $params["status"]);
                $status = SHAppComponent::getValue("app_constant/" . trim($params["status"]));

                $query_params[strtolower($status_arr[0])] = $status;
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
            "draft" => SHAppComponent::getValue("app_constant/STATUS_DRAFT")
        ];

        // Get data
        $model_email_master = new EmailMaster();
        $model_email_recipients = new EmailRecipients();

        try {
            $data = $model_email_master->getListData($query_params, $other_values);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $app_constants = SHAppComponent::getValue("app_constant");
        $default_timezone = \DEFAULT_TIMEZONE;
        
        // Process data and prepare for output
        $output["total_records"] = $data["total_records"];
        $output["total_pages"] = $data["total_pages"];
        $output["current_page"] = $data["current_page"];
        $output["per_page"] = $data["per_page"];
        $output["rows"] = [];

        foreach ($data["rows"] as $row) {
            if ($row["status"] == $app_constants["STATUS_ACTIVE"]) {
                $status = "STATUS_ACTIVE";
            } else if ($row["status"] == $app_constants["STATUS_DRAFT"]) {
                $status = "STATUS_DRAFT";
            } else {
                $status = "NA";
            }
            
            if ($row["progress"] == $app_constants["PROGRESS_SCHEDULED"]) {
                $progress = "PROGRESS_SCHEDULED";
            } else if ($row["progress"] == $app_constants["PROGRESS_SENT"]) {
                $progress = "PROGRESS_SENT";
            } else if ($row["progress"] == $app_constants["PROGRESS_FAILED"]) {
                $progress = "PROGRESS_FAILED";
            } else {
                $progress = "NA";
            }

            $last_activity = "NA";
            $last_activity_at = "";
            
            if ($row["status"] != $app_constants["STATUS_DRAFT"] && $row["progress"] != $app_constants["PROGRESS_SCHEDULED"] ) {
                if(!empty($row["last_activity_date"])) {
                    $last_activity = DateTimeComponent::getDateTimeAgo($row["last_activity_date"], $user_timezone);
                    $last_activity_at = DateTimeComponent::convertDateTime($row["last_activity_date"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
                    
                    $activity_arr = [
                        "last_replied" => $row["last_replied"],
                        "last_opened" => $row["last_opened"],
                        "last_clicked" => $row["last_clicked"]
                    ];
                    $max_date = max($activity_arr);
                    $activity = array_keys($activity_arr, $max_date);
                    
                    $last_activity_text = "";

                    if($activity[0] == "last_opened") {
                        $last_activity_text = "Open Mail";
                    } else if($activity[0] == "last_clicked") {
                        $last_activity_text = "Link Clicked";
                    } else {
                        $last_activity_text = "Reply Mail";
                    }
                    $last_activity_at = $last_activity_text . "  " . $last_activity_at;
                }
            }

            $scheduled_at = "NA";
            $sent_at = "";
            $timezone = "";

            if ( $row["scheduled_at"] != "" && $row["scheduled_at"] > 0 ) {
                $scheduled_at = DateTimeComponent::convertDateTime($row["scheduled_at"], true, \DEFAULT_TIMEZONE, $row["timezone"], \APP_TIME_FORMAT);
                $timezone = $row["timezone"];
            }
            if ( $row["sent_at"] != "" && $row["sent_at"] > 0 ) {
                $sent_at = DateTimeComponent::convertDateTime($row["sent_at"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
                $timezone = $user_timezone;
            }
            if ( $row["scheduled_at"] > 0 && $row["sent_at"] > 0) {
                $sent_at = DateTimeComponent::convertDateTime($row["sent_at"], true, \DEFAULT_TIMEZONE, $row["timezone"], \APP_TIME_FORMAT);
                $timezone = $row["timezone"];
            }

            $last_opened_at = 0;
            if ($row["last_opened"] > 0) {
                $last_opened_at = DateTimeComponent::convertDateTime($row["last_opened"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
            }
            $last_clicked_at = 0;
            if ($row["last_clicked"] > 0) {
                $last_clicked_at = DateTimeComponent::convertDateTime($row["last_clicked"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
            }
            $last_replied_at = 0;
            if ($row["last_replied"] > 0) {
                $last_replied_at = DateTimeComponent::convertDateTime($row["last_replied"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
            }

            // Get email contacts
            $email_recipients = [];

            $condition = [
                "fields" => [
                    "ac.id",
                    "ac.first_name",
                    "ac.last_name",
                    "ac.email",
                    "er.type",
                    "ac.status"
                ],
                "where" => [
                    ["where" => ["er.email_id", "=", $row["id"]]],
                    ["where" => ["er.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ],
                "join" => [
                    "account_contacts"
                ]
            ];
            $contacts_array = $model_email_recipients->fetchAll($condition);

            foreach ($contacts_array as $contact) {
                $name = trim($contact["first_name"] . " " . $contact["last_name"]);
                $name = empty($name) ? $contact["email"] : $name;
                $deleted = SHAppComponent::getValue("app_constant/FLAG_NO");

                if ($contact["status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
                    $name .= "(Deleted)";
                    $deleted = SHAppComponent::getValue("app_constant/FLAG_YES");
                }
                $email_recipients[] = [
                    "id" => StringComponent::encodeRowId($contact["id"]),
                    "name" => $name,
                    "type" => $contact["type"],
                    "is_deleted" => $deleted
                ];
            }

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "subject" => $row["subject"],
                "account_sending_method_id" => StringComponent::encodeRowId($row["account_sending_method_id"]),
                "email_sending_account" => $row["email_sending_account"],
                "sending_type" => $row["code"],
                "created_by" => trim($row["first_name"]),
                "created_by_id" => StringComponent::encodeRowId($row["user_id"]),
                "status" => $status,
                "progress" => $progress,
                "snooze" => (bool) $row["snooze_notifications"],
                "scheduled_at" => $scheduled_at,
                "scheduled_at_timestamp" => $row["scheduled_at"],
                "sent_at" => $sent_at,
                "sent_at_timestamp" => $row["sent_at"],
                "timezone" => $timezone,
                "total_recipients" => $row["total_recipients"],
                "open_count" => $row["open_count"],
                "last_opened_at" => $last_opened_at,
                "reply_count" => $row["reply_count"],
                "bounce_count" => $row["bounce_count"],
                "click_count" => $row["click_count"],
                "last_clicked_at" => $last_clicked_at,
                "last_replied_at" => $last_replied_at,
                "last_activity" => $last_activity,
                "last_activity_at" => $last_activity_at,
                "recipients" => $email_recipients,
                "source" => $row["source_id"]
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
       
        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [
            "subject" => "",
            "sending_account" => null,
            "content" => "",
            "template" => null,
            "is_scheduled" => SHAppComponent::getValue("app_constant/FLAG_NO"),
            "scheduled_at" => DateTimeComponent::getDateTime(),
            "timezone" => \DEFAULT_TIMEZONE,
            "track_reply" => SHAppComponent::getValue("app_constant/FLAG_YES"),
            "track_click" => SHAppComponent::getValue("app_constant/FLAG_YES"),
            "is_draft" => SHAppComponent::getValue("app_constant/FLAG_NO"),
            "cc" => "",
            "bcc" => ""
        ];
        
        if (isset($request_params["sending_account"])) {
            $sending_account = StringComponent::decodeRowId(trim($request_params["sending_account"]));

            if (!empty($sending_account)) {
                $request_data["sending_account"] = $sending_account;

            } else {
                $request_data["sending_account"] = null;
            }
        }
        
        if (isset($request_params["template"])) {
            $template = StringComponent::decodeRowId(trim($request_params["template"]));

            if (!empty($template)) {
                $request_data["template"] = $template;
            }
        }
        if (isset($request_params["subject"])) {
            $request_data["subject"] = $request_params["subject"];
        }
        if (isset($request_params["content"])) {
            $request_data["content"] = $request_params["content"];
        }
        if (isset($request_params["is_scheduled"])) {
            $request_data["is_scheduled"] = $request_params["is_scheduled"];
        }
        if (isset($request_params["scheduled_at"])) {
            $request_data["scheduled_at"] = $request_params["scheduled_at"];
        }
        if (isset($request_params["timezone"])) {
            $request_data["timezone"] = $request_params["timezone"];
        }
        if (isset($request_params["track_reply"])) {
            $request_data["track_reply"] = $request_params["track_reply"];
        }
        if (isset($request_params["track_click"])) {
            $request_data["track_click"] = $request_params["track_click"];
        }
        if (isset($request_params["cc"])) {
            $request_data["cc"] = $request_params["cc"];
        }
        if (isset($request_params["bcc"])) {
            $request_data["bcc"] = $request_params["bcc"];
        }
        if (isset($request_params["to"])) {
            $request_data["to"] = $request_params["to"];
        }
        if (isset($request_params["is_draft"])) {
            $request_data["is_draft"] = $request_params["is_draft"];
        }
        if (isset($request_params["sent_message_id"])) {
            $request_data["sent_message_id"] = $request_params["sent_message_id"];
        }
        if (isset($request_params["track_links"])) {
            $request_data["track_links"] = $request_params["track_links"];
        }
        if (isset($request_params["track_docs"])) {
            $request_data["track_docs"] = (array) $request_params["track_docs"];
        }
        
        // Validate request
        $request_validations = [];
        $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");
        if ($request_data["is_draft"] != $flag_yes) {
            $request_validations = [
                "sending_account" => [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ]
            ];
            if(empty($request_data["to"])) {
                //error
                $validation_errors[] = "Invalid Parameter: " .  "to is required.";
            } else {
                foreach ($request_data["to"] as $email) {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        //error
                        $validation_errors[] = "Invalid Parameter: 'To' email is not a valid email.";
                    }
                }
            }
            if (!empty($request_data["cc"])) {
                $cc_contacts = explode(",", $request_data["cc"]);
                foreach ($cc_contacts as $email) {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        //error
                        $validation_errors[] = "Invalid Parameter: 'Cc' email is not a valid email.";
                    }
                }
            }
            if (!empty($request_data["bcc"])) {
                $bcc_contacts = explode(",", $request_data["bcc"]);
                foreach ($bcc_contacts as $email) {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        //error
                        $validation_errors[] = "Invalid Parameter: 'Bcc' email is not a valid email.";
                    }
                }
            }
        } else {
            if (empty($request_data["subject"]) && empty($request_data["content"]) && empty($request_data["to"]) && empty($request_data["cc"]) && empty($request_data["bcc"])) {
                //error
                $validation_errors[] = "Please specify at least one field";
            }
        }

        if ($request_data["is_scheduled"] == SHAppComponent::getValue("app_constant/FLAG_YES")) {
            $request_validations["scheduled_at"] = [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ];
            $request_validations["timezone"] = [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ];
        }

        // For Outlook and Chrome Request
        $source_chrome = SHAppComponent::getValue("source/CHROME_PLUGIN");
        $source_outlook = SHAppComponent::getValue("source/OUTLOOK_PLUGIN");
        if ($logged_in_source == $source_chrome || $logged_in_source == $source_outlook) {
            // Set validations and valid sending account
            // $request_validations["sending_account"] = [
            //     ["type" => Validator::FIELD_REQ_NOTEMPTY]
            // ];

            $request_data["sending_account"] = null;

            $model_account_sending_method = new AccountSendingMethods();

            if ($logged_in_source == $source_chrome) {
                $request_from_email = "";
                if (isset($request_params["from_account"])) {
                    $request_from_email = $request_params["from_account"];
                }

                if (!empty($request_from_email)) {
                    $condition_gmail = [
                        "fields" => [
                            "id"
                        ],
                        "where" => [
                            ["where" => ["user_id", "=", $logged_in_user]],
                            ["where" => ["account_id", "=", $logged_in_account]],
                            ["where" => ["from_email", "=", $request_from_email]],
                            ["where" => ["status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                        ]
                    ];
                    $data = $model_account_sending_method->fetch($condition_gmail);
                    
                    if (!empty($data)) {
                        $request_data["sending_account"] = $data["id"];
                    } else {
                        return ErrorComponent::outputError($response, "api_messages/MAIL_ACCOUNT_REVOKED");
                    }
                } else {
                    return ErrorComponent::outputError($response, "api_messages/MAIL_ACCOUNT_REVOKED");
                }

            } else {
                $condition_outlook = [
                    "fields" => [
                        "id"
                    ],
                    "where" => [
                        ["whereNull" => "account_id"],
                        ["whereNull" => "user_id"],
                        ["where" => ["is_default", "=", $flag_yes]],
                        ["where" => ["is_outlook", "=", $flag_yes]]
                    ]
                ];
                $data = $model_account_sending_method->fetch($condition_outlook);

                if (!empty($data)) {
                    $request_data["sending_account"] = $data["id"];
                }
            }
        }

        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        // Save record
        $model_email_master = new EmailMaster();

        try {
            if ($request_data["is_scheduled"]) {
                $scheduled_at = DateTimeComponent::convertDateTime($request_data["scheduled_at"], false, $request_data["timezone"], \DEFAULT_TIMEZONE);
            } else {
                $scheduled_at = 0;
            }

            if(!empty($request_data["sent_message_id"])){
                $sent_message_id_first = $request_data["sent_message_id"];
            } else{
                $sent_message_id_first = "";
            }

            $save_data = [
                "account_id" => $logged_in_account,
                "user_id" => $logged_in_user,
                "account_template_id" => $request_data["template"],
                "account_sending_method_id" => $request_data["sending_account"],
                "source_id" => $logged_in_source,
                "subject" => trim($request_data["subject"]),
                "content" => trim($request_data["content"]),
                "is_scheduled" => trim($request_data["is_scheduled"]),
                "scheduled_at" => $scheduled_at,
                "timezone" => trim($request_data["timezone"]),
                "sent_message_id" => $sent_message_id_first,
                "sent_response" => '',
                "track_reply" => trim($request_data["track_reply"]),
                "track_click" => trim($request_data["track_click"]),
                "created" => DateTimeComponent::getDateTime(),
                "modified" => DateTimeComponent::getDateTime()
            ];
            
            if ($request_data["is_draft"] == SHAppComponent::getValue("app_constant/FLAG_YES")) {
                $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_DRAFT");
                $save_data["progress"] = SHAppComponent::getValue("app_constant/PROGRESS_SCHEDULED");
            }

            if ($model_email_master->save($save_data) !== false) {
                $inserted_email_id = $model_email_master->getLastInsertId();

                // Insert email recipients
                $model_email_recipients = new EmailRecipients();
                $model_account_contacts = new AccountContacts();
                $total_recipients = 0;
                $link_for_contact_id = null;

                $save_data = [
                    "account_id" => $logged_in_account,
                    "email_id" => $inserted_email_id,
                    "modified" => DateTimeComponent::getDateTime()
                ];
                
                $email_send_to = [];
                if (!empty($request_data["to"])) {
                    $save_data["type"] = SHAppComponent::getValue("app_constant/TO_EMAIL_RECIPIENTS");
                    $contactId = null;
                    foreach ($request_data["to"] as $to) {
                        $payload = [];

                        if(is_array($to)) {
                            if (!empty($to["name"])) {
                                $names_arr = explode(" ", trim($to["name"]));
                                $first_name = isset($names_arr[0]) ? $names_arr[0] : "";
                                $last_name = trim(substr($to["name"], strlen($first_name)));

                                $payload = [
                                    "first_name" => $first_name,
                                    "last_name" => $last_name,
                                ];
                            }
                            $to = $to["emailAddress"];
                        }
                        $email_send_to[] = $to;
                        
                        $contact_id = $model_account_contacts->getContactIdByEmail($to, $logged_in_account, $payload);
                      
                        if (!empty($contact_id)) {
                            try {
                                $save_data["account_contact_id"] = $contact_id;
                                $saved = $model_email_recipients->save($save_data);
                                $inserted_emailrecipient_id = $model_email_recipients->getLastInsertId();

                                $total_recipients++;

                                if (empty($link_for_contact_id)) {
                                    $link_for_contact_id = $contact_id;
                                }
                                
                            } catch(\Exception $e) { }
                        }
                    }
                }
                if ($total_recipients > 1) {
                    $link_for_contact_id = null;
                }

                $email_send_cc = [];
                if (!empty($request_data["cc"])) {
                    $save_data["type"] = SHAppComponent::getValue("app_constant/CC_EMAIL_RECIPIENTS");

                    if (is_array($request_data["cc"])) {
                        $request_data_cc = $request_data["cc"];
                    } else {
                        $request_data_cc = explode(",", $request_data["cc"]);
                    }

                    foreach ($request_data_cc as $cc) {
                        $payload = [];

                        if(is_array($cc)) {
                            if (!empty($cc["name"])) {
                                $names_arr = explode(" ", trim($cc["name"]));
                                $first_name = isset($names_arr[0]) ? $names_arr[0] : "";
                                $last_name = trim(substr($cc["name"], strlen($first_name)));

                                $payload = [
                                    "first_name" => $first_name,
                                    "last_name" => $last_name,
                                ];
                            }
                            $cc = $cc["emailAddress"];
                        }
                        $email_send_cc[] = $cc;
                        
                        $contact_id = $model_account_contacts->getContactIdByEmail($cc, $logged_in_account, $payload);

                        if (!empty($contact_id)) {
                            try {
                                $save_data["account_contact_id"] = $contact_id;
                                $saved = $model_email_recipients->save($save_data);
                                $inserted_emailrecipient_id = $model_email_recipients->getLastInsertId();

                                $total_recipients++;

                            } catch(\Exception $e) { }
                        }
                    }
                }

                $email_send_bcc = [];
                if (!empty($request_data["bcc"])) {
                    $save_data["type"] = SHAppComponent::getValue("app_constant/BCC_EMAIL_RECIPIENTS");

                    if (is_array($request_data["bcc"])) {
                        $request_data_bcc = $request_data["bcc"];
                    } else {
                        $request_data_bcc = explode(",", $request_data["bcc"]);
                    }

                    foreach ($request_data_bcc as $bcc) {
                        $payload = [];

                        if(is_array($bcc)) {
                            if (!empty($bcc["name"])) {
                                $names_arr = explode(" ", trim($bcc["name"]));
                                $first_name = isset($names_arr[0]) ? $names_arr[0] : "";
                                $last_name = trim(substr($bcc["name"], strlen($first_name)));

                                $payload = [
                                    "first_name" => $first_name,
                                    "last_name" => $last_name,
                                ];
                            }
                            $bcc = $bcc["emailAddress"];
                        }
                        $email_send_bcc[] = $bcc;
                        
                        $contact_id = $model_account_contacts->getContactIdByEmail($bcc, $logged_in_account, $payload);

                        if (!empty($contact_id)) {
                            try {
                                $save_data["account_contact_id"] = $contact_id;
                                $saved = $model_email_recipients->save($save_data);
                                $inserted_emailrecipient_id = $model_email_recipients->getLastInsertId();

                                $total_recipients++;

                            } catch(\Exception $e) { }
                        }
                    }
                }

                if (!empty($request_data["track_docs"])) {
                    foreach ($request_data["track_docs"] as $trackDocLink) {
                        $document_payload = [
                            "from_email" => "1",
                            "account_id" => $logged_in_account,
                            "user_id" => $logged_in_user,
                            "source_id" => $logged_in_source,
                            "contact_id" => $link_for_contact_id,
                            "document_id" => StringComponent::decodeRowId($trackDocLink),
                            "name"  => "Link for " .trim($request_data["subject"])
                        ];

                        $trackDocConvertedUrl = LinksController::generateDocumentLink($document_payload);
                        $output["track_docs"][$trackDocLink] = $trackDocConvertedUrl;
                    }
                }

                // Update total recipients
                try {
                    $save_data = [
                        "id" => $inserted_email_id,
                        "total_recipients" => $total_recipients
                    ];
                    $saved = $model_email_master->save($save_data);

                } catch(\Exception $e) { }

                // Prepare tracking pixel
                $encrypted_pixel_id = StringComponent::encodeRowId($inserted_email_id);
                $encrypted_user_id = StringComponent::encodeRowId($logged_in_user);
                $encrypted_account_id = StringComponent::encodeRowId($logged_in_account);

                $tracking_url = TRACKING_PIXEL[rand(0, TRACKING_PIXEL_COUNT)];
                $tracking_pixel_image = '<img width="1" height="1" id="shtracking_1s2" src="' . $tracking_url . '/'. TRACKING_UNIQUE_TEXT . '/e/' . $encrypted_pixel_id . '/' . $encrypted_user_id . '/' . $encrypted_account_id . '" style="display:none">';

                // Send email if not schedule
                if($request_data["is_scheduled"] == 0 && $request_data["is_draft"] == SHAppComponent::getValue("app_constant/FLAG_NO")) {
                    $model_account_sending_method = new AccountSendingMethods();

                    $other_values = [
                        "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
                    ];

                    $row = $model_account_sending_method->getSendingMethodById($request_data["sending_account"], $other_values);
                    if(!empty($row)) {
                        $email_sending_method_id = $row["email_sending_method_id"];
                        $payload = json_decode($row["payload"]);

                        //Link click track code
                        if ( $request_data["track_click"] == 1 ) {

                            $regex = FIND_LINK_PATTERN;
                            preg_match_all($regex, $request_data["content"], $attachment_urls);
                            
                            $model_account_link_master = new AccountLinkMaster();
                            $model_email_links = new EmailLinks();

                            // If link exists in content
                            if (!empty($attachment_urls[2])) {
                                foreach ($attachment_urls[2] as $url) {
                                    $link_id = $model_account_link_master->getLinkIdByUrl($url, $logged_in_account);
                                    $redirect_key = StringComponent::encodeRowId([$logged_in_account, $inserted_email_id, $link_id]);
                                    
                                    $condition = [
                                        "fields" => ["id"],
                                        "where"  => [["where" => ["redirect_key", "=", $redirect_key]]]
                                    ];
                                    try {
                                        $already_exist = $model_email_links->fetch($condition);
                                        if (empty($already_exist["id"])) {
                                            $save_data = [
                                                "account_id" => $logged_in_account,
                                                "email_id" => $inserted_email_id,
                                                "account_link_id" => $link_id,
                                                "redirect_key" => $redirect_key
                                            ];
                                            $model_email_links->save($save_data);
                                        }
                                        $new_url = $tracking_url . "/". TRACKING_UNIQUE_TEXT . "/r/e/" . $redirect_key."?r=".$url;
                                        $request_data["content"] = str_replace('"' . $url . '"', '"' . $new_url . '"', $request_data["content"]);
                                    
                                    } catch(\Exception $e) {}
                                }
                            }
                        }

                        $regex = FIND_DOCUMENT_PATTERN;
                        preg_match_all($regex, $request_data["content"], $document_urls);     
                        
                        if (!empty($document_urls[2])) {
                            $documentUrls = array_unique($document_urls[2]);
                            
                            foreach ($documentUrls as $url) {
                                $docUrl = str_replace("{","",$url);
                                $docUrl = str_replace("}","",$docUrl);    
                                $docUrl = str_replace("file:","",$docUrl);
                                $document_payload = [
                                    "from_email" => "1",
                                    "account_id" => $logged_in_account,
                                    "user_id" => $logged_in_user,
                                    "source_id" => $logged_in_source,
                                    "contact_id" => $link_for_contact_id,
                                    "document_id" =>StringComponent::decodeRowId($docUrl),
                                    "name"  => "Link for " .trim($request_data["subject"])
                                ];
                                
                                $new_url = LinksController::generateDocumentLink($document_payload);

                                $request_data["content"] = str_replace('"' . $url . '"', '"' . $new_url . '"', $request_data["content"]);
                            }
                        }

                        $info["subject"] = $request_data["subject"];
                        $info["content"] = $request_data["content"] . $tracking_pixel_image;
                        $info["to"] = $email_send_to;
                        $info["cc"] = implode(",", $email_send_cc);
                        $info["bcc"] = implode(",", $email_send_bcc);
                        $info["from_email"] = $row["from_email"];
                        $info["from_name"] = $row["from_name"];
                        $info["reply_to"] = "";

                        // Send email with Gmail
                        if($email_sending_method_id == SHAppComponent::getValue("email_sending_method/GMAIL")) {
                            if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE") && $row["connection_status"] == SHAppComponent::getValue("app_constant/FLAG_YES") ) {
                                $info["refresh_token"] = $payload->refresh_token;
                                $info["thread_id"] = "";

                                $result = GmailComponent::sendMailGmail($info, false);
                                if(isset($result["gmail_message_id"]) && $result["gmail_message_id"] != "") {
                                    //mail sent successfully
                                    $progress = SHAppComponent::getValue("app_constant/PROGRESS_SENT");
                                    $sent_message_id = $result["gmail_message_id"];
                                    $sent_response = "";
                                    $sent_at = DateTimeComponent::getDateTime();
                                } else {
                                    //mail not sent
                                    $progress = SHAppComponent::getValue("app_constant/PROGRESS_FAILED");
                                    $sent_message_id = "0";
                                    $sent_response = $result["error_message"];
                                    $sent_at = DateTimeComponent::getDateTime();

                                    if(!$result["success"]) {
                                        if (json_decode($result["error_message"], true) === NULL) {
                                            $attempt_error_arr = array(
                                                "message"   => $result["error_message"],
                                                "code"      => null
                                            );
                                        } else {
                                            $attempt_error_arr = json_decode($result["error_message"], true);

                                            if (isset($attempt_error_arr["error"]["message"])) {
                                                $attempt_error_arr["message"] = $attempt_error_arr["error"]["message"];
                                            }
                                        }

                                        if (isset($attempt_error_arr["error"])) {
                                                if ($attempt_error_arr["error"]["code"] == 401) {
                                                // Update user Gmail account as disconnected
                                                $update_data = [
                                                    "id" => $row["id"],
                                                    "error_message" => "401 - Error in connecting your Gmail account."
                                                ];
                                                $model_account_sending_method->disConnectMailAccount($update_data);
                                            }
                                        }
                                    }
                                }
                            } else {
                                //mail not sent
                                $progress = SHAppComponent::getValue("app_constant/PROGRESS_FAILED");
                                $sent_message_id = "0";
                                $sent_response = "Your Gmail account has been disconnected";
                                $sent_at = DateTimeComponent::getDateTime(); 
                            }

                            try {
                                $condition = [
                                    "where" => [
                                        ["where" => ["email_id", "=", $inserted_email_id]]
                                    ]
                                ];
                                $save_data = [
                                    "progress" => $progress
                                ];
                                $updated = $model_email_recipients->update($save_data, $condition);
                            } catch(\Exception $e) {
                                // Fetch error code & message and return the response
                                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                            }

                            try {
                                $save_data = [
                                    "id" => $inserted_email_id,
                                    "sent_at" => $sent_at,
                                    "sent_message_id" => $sent_message_id,
                                    "sent_response" => $sent_response,
                                    "progress" => $progress
                                ];
                                $model_email_master->save($save_data);
                            } catch(\Exception $e) {
                                // Fetch error code & message and return the response
                                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                            }
                        }
                        
                        // Send email with user's SMTP
                        if($email_sending_method_id == SHAppComponent::getValue("email_sending_method/SMTP")) {
                            if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE") && $row["connection_status"] == SHAppComponent::getValue("app_constant/FLAG_YES") ) {
                                $info["smtp_details"]["host"] = $payload->host;
                                $info["smtp_details"]["username"] = $payload->username;
                                $info["smtp_details"]["password"] = $payload->password;
                                $info["smtp_details"]["port"] = $payload->port;
                                $info["smtp_details"]["encryption"] = $payload->encryption;

                                $result = SMTPComponent::mailSendUserSmtp($info);
                                if(isset($result["message_id"]) && $result["message_id"] != "") {
                                    //mail sent successfully
                                    $progress = SHAppComponent::getValue("app_constant/PROGRESS_SENT");
                                    $sent_message_id = $result["message_id"];
                                    $sent_response = "";
                                    $sent_at = DateTimeComponent::getDateTime();
                                } else {
                                    //mail not sent
                                    $progress = SHAppComponent::getValue("app_constant/PROGRESS_FAILED");
                                    $sent_message_id = "0";
                                    $sent_response = $result["error_message"];
                                    $sent_at = DateTimeComponent::getDateTime();
                                  
                                    if(!$result["success"]) {

                                        if (isset($result["error_message"])) {
                                            if (json_decode($result["error_message"], true) === NULL) {
                                                $attempt_error_arr = array(
                                                    "message"   => $result["error_message"],
                                                    "code"      => null
                                                );
                                            } else {
                                                $attempt_error_arr = json_decode($result["error_message"], true);

                                                if (isset($attempt_error_arr["error"]["message"])) {
                                                    $attempt_error_arr["message"] = $attempt_error_arr["error"]["message"];
                                                }
                                            }

                                            if (stristr($attempt_error_arr["message"],CONNECTION_NOT_ESTABLISH) !== false || 
                                                stristr($attempt_error_arr["message"], EXPECTED_CODE_250_BUT_GONE) !== false || 
                                                stristr($attempt_error_arr["message"], EXPECTED_CODE_220_BUT_GONE) !== false) {
                                               
                                                //Update user SMTP account as disconnected
                                                $update_data = [
                                                    "id" => $row["id"],                                      
                                                    "error_message" => "Error in connecting your SMTP account. Please reconnect your account.",
                                                ];
                                                $model_account_sending_method->disConnectMailAccount($update_data);
                                            }
                                        } else if (isset($result["message"])) {
                                            if (stristr($result["message"],SMTP_CONNECTION_FAILED) !== false || 
                                                stristr($result["message"], COULD_NOT_AUTHENTICATE) !== false ) {
                                                
                                                //Update user SMTP account as disconnected
                                                $update_data = [
                                                    "id" => $row["id"],                                      
                                                    "error_message" => "Error in connecting your SMTP account. Please reconnect your account.",
                                                ];
                                                $model_account_sending_method->disConnectMailAccount($update_data);
                                            } 
                                        }
                                    }
                                }
                            } else {
                                //mail not sent
                                $progress = SHAppComponent::getValue("app_constant/PROGRESS_FAILED");
                                $sent_message_id = "0";
                                $sent_response = "Your SMTP account has been disconnected";
                                $sent_at = DateTimeComponent::getDateTime(); 
                            }
                            try {
                                $condition = [
                                    "where" => [
                                        ["where" => ["email_id", "=", $inserted_email_id]]
                                    ]
                                ];
                                
                                $save_data = [
                                    "progress" => $progress
                                ];
                                $updated = $model_email_recipients->update($save_data, $condition);
                            } catch(\Exception $e) {
                                // Fetch error code & message and return the response
                                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                            }
                            try {
                                $save_data = [
                                    "id" => $inserted_email_id,
                                    "sent_at" => $sent_at,
                                    "sent_message_id" => $sent_message_id,
                                    "sent_response" => $sent_response,
                                    "progress" => $progress
                                ];
                                $model_email_master->save($save_data);
                            } catch(\Exception $e) {

                                // Fetch error code & message and return the response
                                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                            }
                        }
                    }
                }
        
                //code for plugin request custom link tracker (chrome/outlook)
                if (($logged_in_source == $source_chrome || $logged_in_source == $source_outlook) && $request_data["track_click"] == SHAppComponent::getValue("app_constant/FLAG_YES")){
                    if (!empty($request_data["track_links"])) {
                        $model_account_link_master = new AccountLinkMaster();
                        $model_email_links = new EmailLinks();

                        foreach ($request_data["track_links"] as $url) {
                            $link_domain = \DOC_VIEWER_BASE_URL;
                            $link_domain = $link_domain."/view/";
                            $findDocSpace = strpos($url, $link_domain);   
                                                        
                            if($findDocSpace===false){    
                                $link_id = $model_account_link_master->getLinkIdByUrl($url, $logged_in_account);
                                $redirect_key = StringComponent::encodeRowId([$logged_in_account, $inserted_email_id, $link_id]);
                                
                                $condition = [
                                    "fields" => ["id"],
                                    "where"  => [["where" => ["redirect_key", "=", $redirect_key]]]
                                ];
                                try {
                                    $already_exist = $model_email_links->fetch($condition);
                                    if (empty($already_exist["id"])) {
                                        $save_data = [
                                            "account_id" => $logged_in_account,
                                            "email_id" => $inserted_email_id,
                                            "account_link_id" => $link_id,
                                            "redirect_key" => $redirect_key
                                        ];
                                        $model_email_links->save($save_data);
                                    }
                                    $custom_url = $tracking_url . "/". TRACKING_UNIQUE_TEXT . "/r/e/" . $redirect_key . "?r=".$url;
                                    $output["tracked_links"][$url] = $custom_url;

                                } catch(\Exception $e) {
                                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                                }
                            }else{
                                $output["tracked_links"][$url] = $url;
                            }       
                        }
                    }
                }
                $output["id"] = StringComponent::encodeRowId($inserted_email_id);
                if ($request_data["is_draft"]) {
                    $output["message"] = "Email saved successfully.";
                    $output["tracking_pixel"] = $tracking_pixel_image;
                    
                } else if ($request_data["is_scheduled"]) {
                    $sh_activity = new SHActivity();
                    $activity_params = [
                        "user_id" => $logged_in_user,
                        "account_id" => $logged_in_account,
                        "action" => SHAppComponent::getValue("actions/EMAILS/SCHEDULED"),
                        "record_id" => $inserted_email_id
                    ];
                    $sh_activity->addActivity($activity_params);

                    $output["message"] = "Email scheduled successfully.";
                    $output["tracking_pixel"] = $tracking_pixel_image;

                } else {
                    if ( $result["success"] ) {
                        $sh_activity = new SHActivity();
                        $activity_params = [
                            "user_id" => $logged_in_user,
                            "account_id" => $logged_in_account,
                            "action" => SHAppComponent::getValue("actions/EMAILS/SENT"),
                            "record_id" => $inserted_email_id
                        ];
                        $sh_activity->addActivity($activity_params);

                        $output["message"] = "Email sent successfully.";        
                    } else {
                        $output["message"] = "Email could not be sent.";
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
        $model_email_master = new EmailMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_email_master->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Check previous status if mail already sent
        try {
            $fields = ["progress"];
            $row = $model_email_master->fetchById($row_id, $fields);

            if ( $row["progress"] > 0 ) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/MAIL_ALREADY_SENT");
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [
            "subject" => "",
            "sending_account" => null,
            "content" => "",
            "template" => null,
            "is_scheduled" => SHAppComponent::getValue("app_constant/FLAG_NO"),
            "scheduled_at" => DateTimeComponent::getDateTime(),
            "timezone" => \DEFAULT_TIMEZONE,
            "track_reply" => SHAppComponent::getValue("app_constant/FLAG_YES"),
            "track_click" => SHAppComponent::getValue("app_constant/FLAG_YES"),
            "is_draft" => SHAppComponent::getValue("app_constant/FLAG_NO"),
            "cc" => "",
            "bcc" => ""
        ];
        
        if (isset($request_params["sending_account"])) {
            $sending_account = StringComponent::decodeRowId(trim($request_params["sending_account"]));

            if (!empty($sending_account)) {
                $request_data["sending_account"] = $sending_account;
            } else {
                $request_data["sending_account"] = null;
            }
        }
        if (isset($request_params["template"])) {
            $template = StringComponent::decodeRowId(trim($request_params["template"]));

            if (!empty($template)) {
                $request_data["template"] = $template;
            }
        }
        if (isset($request_params["subject"])) {
            $request_data["subject"] = $request_params["subject"];
        }
        if (isset($request_params["content"])) {
            $request_data["content"] = $request_params["content"];
        }
        if (isset($request_params["is_scheduled"])) {
            $request_data["is_scheduled"] = $request_params["is_scheduled"];
        }
        if (isset($request_params["scheduled_at"])) {
            $request_data["scheduled_at"] = $request_params["scheduled_at"];
        }
        if (isset($request_params["timezone"])) {
            $request_data["timezone"] = $request_params["timezone"];
        }
        if (isset($request_params["track_reply"])) {
            $request_data["track_reply"] = $request_params["track_reply"];
        }
        if (isset($request_params["track_click"])) {
            $request_data["track_click"] = $request_params["track_click"];
        }
        if (isset($request_params["cc"])) {
            $request_data["cc"] = $request_params["cc"];
        }
        if (isset($request_params["bcc"])) {
            $request_data["bcc"] = $request_params["bcc"];
        }
        if (isset($request_params["to"])) {
            $request_data["to"] = $request_params["to"];
        }
        if (isset($request_params["is_draft"])) {
            $request_data["is_draft"] = $request_params["is_draft"];
        }
        if (isset($request_params["sent_message_id"])) {
            $request_data["sent_message_id"] = $request_params["sent_message_id"];
        }
        if (isset($request_params["track_links"])) {
            $request_data["track_links"] = $request_params["track_links"];
        }
        if (isset($request_params["track_docs"])) {
            $request_data["track_docs"] = (array) $request_params["track_docs"];

            if (!empty($request_data["track_docs"])) {
                foreach ($request_data["track_docs"] as $trdv) {
                    $output["track_docs"][$trdv] = $trdv;
                }
            }
        }
        
        // Validate request
        $request_validations = [];
        if ($request_data["is_draft"] != SHAppComponent::getValue("app_constant/FLAG_YES")) {
            $request_validations = [
                "sending_account" => [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ]
            ];
            if(empty($request_data["to"])) {
                //error
                $validation_errors[] = "Invalid Parameter: " .  "to is required.";
            } else {
                foreach ($request_data["to"] as $email) {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        //error
                        $validation_errors[] = "Invalid Parameter: 'To' email is not a valid email.";
                    }
                }
            }
            if (!empty($request_data["cc"])) {
                $cc_contacts = explode(",", $request_data["cc"]);
                foreach ($cc_contacts as $email) {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        //error
                        $validation_errors[] = "Invalid Parameter: 'Cc' email is not a valid email.";
                    }
                }
            }
            if (!empty($request_data["bcc"])) {
                $bcc_contacts = explode(",", $request_data["bcc"]);
                foreach ($bcc_contacts as $email) {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        //error
                        $validation_errors[] = "Invalid Parameter: 'Bcc' email is not a valid email.";
                    }
                }
            }
        } else {
            if (empty($request_data["subject"]) && empty($request_data["content"]) && empty($request_data["to"]) && empty($request_data["cc"]) && empty($request_data["bcc"])) {
                //error
                $validation_errors[] = "Please specify at least one field";
            }
        }

        if ($request_data["is_scheduled"] == SHAppComponent::getValue("app_constant/FLAG_YES")) {
            $request_validations["scheduled_at"] = [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ];
            $request_validations["timezone"] = [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ];
        }

        // For Outlook and Chrome Request
        $source_chrome = SHAppComponent::getValue("source/CHROME_PLUGIN");
        $source_outlook = SHAppComponent::getValue("source/OUTLOOK_PLUGIN");
        if ($logged_in_source == $source_chrome || $logged_in_source == $source_outlook) {
            // Set validations and valid sending account
            $request_validations["sending_account"] = [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ];

            $request_data["sending_account"] = null;
            
            $model_account_sending_method = new AccountSendingMethods();

            if ($logged_in_source == $source_chrome) {
                $request_from_email = "";
                if (isset($request_params["from_account"])) {
                    $request_from_email = $request_params["from_account"];
                }
               
                if (!empty($request_from_email)) {
                    $condition_gmail = [
                        "fields" => [
                            "id"
                        ],
                        "where" => [
                            ["where" => ["user_id", "=", $logged_in_user]],
                            ["where" => ["account_id", "=", $logged_in_account]],
                            ["where" => ["from_email", "=", $request_from_email]],
                            ["where" => ["status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                        ]
                    ];
                    $data = $model_account_sending_method->fetch($condition_gmail);

                    if (!empty($data)) {
                        $request_data["sending_account"] = $data["id"];
                    }

                }

            } else {
                $condition_outlook = [
                    "fields" => [
                        "id"
                    ],
                    "where" => [
                        ["whereNull" => "account_id"],
                        ["whereNull" => "user_id"],
                        ["where" => ["is_default", "=", SHAppComponent::getValue("app_constant/FLAG_YES")]],
                        ["where" => ["is_outlook", "=", SHAppComponent::getValue("app_constant/FLAG_YES")]]
                    ]
                ];
                $data = $model_account_sending_method->fetch($condition_outlook);

                if (!empty($data)) {
                    $request_data["sending_account"] = $data["id"];
                }
            }
        }

        $validation_errors = Validator::validate($request_validations, $request_data);
        
        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        // Save record
        try {
            if ($request_data["is_scheduled"]) {
                $scheduled_at = DateTimeComponent::convertDateTime($request_data["scheduled_at"], false, $request_data["timezone"], \DEFAULT_TIMEZONE);
            } else {
                $scheduled_at = 0;
            }
            if(!empty($request_data["sent_message_id"])){
                $sent_message_id_first = $request_data["sent_message_id"];
            } else {
                $sent_message_id_first = "";
            }
            
            $save_data = [
                "id" => $row_id,
                "account_template_id" => $request_data["template"],
                "account_sending_method_id" => $request_data["sending_account"],
                "subject" => trim($request_data["subject"]),
                "content" => trim($request_data["content"]),
                "is_scheduled" => trim($request_data["is_scheduled"]),
                "scheduled_at" => $scheduled_at,
                "timezone" => trim($request_data["timezone"]),
                "sent_message_id" => $sent_message_id_first,
                "sent_response" => '',
                "track_reply" => trim($request_data["track_reply"]),
                "track_click" => trim($request_data["track_click"]),
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($request_data["is_draft"] == SHAppComponent::getValue("app_constant/FLAG_YES")) {
                $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_DRAFT");
                $save_data["progress"] = SHAppComponent::getValue("app_constant/PROGRESS_SCHEDULED");
            } else {
                $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
            }

            if ($model_email_master->save($save_data) !== false) {
                // Insert email recipients
                $model_email_recipients = new EmailRecipients();
                $model_account_contacts = new AccountContacts();
                $total_recipients = 0;

                // Update all recipients status to delete
                $condition = [
                    "where" => [
                        ["where" => ["email_id", "=", $row_id]]
                    ]
                ];
                $save_data = [
                    "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                    "modified" => DateTimeComponent::getDateTime()
                ];

                $updated = $model_email_recipients->update($save_data, $condition);

                $email_send_to = [];
                if (!empty($request_data["to"])) {
                    $type = SHAppComponent::getValue("app_constant/TO_EMAIL_RECIPIENTS");

                    foreach ($request_data["to"] as $to) {
                        $payload = [];

                        if(is_array($to)) {
                            if (!empty($to["name"])) {
                                $names_arr = explode(" ", trim($to["name"]));
                                $first_name = isset($names_arr[0]) ? $names_arr[0] : "";
                                $last_name = trim(substr($to["name"], strlen($first_name)));

                                $payload = [
                                    "first_name" => $first_name,
                                    "last_name" => $last_name,
                                ];
                            }
                            $to = $to["emailAddress"];
                        }
                        $email_send_to[] = $to;

                        $contact_id = $model_account_contacts->getContactIdByEmail($to, $logged_in_account, $payload);

                        if (!empty($contact_id)) {
                            try {
                                // Fetch is data already exists
                                $condition = [
                                    "fields" => [
                                        "er.id"
                                    ],
                                    "where" => [
                                        ["where" => ["er.email_id", "=", $row_id]],
                                        ["where" => ["er.account_contact_id", "=", $contact_id]],
                                        ["where" => ["er.type", "=", $type]],
                                    ]
                                ];
                                $already_exists = $model_email_recipients->fetch($condition);

                                $save_data = [
                                    "account_id" => $logged_in_account,
                                    "account_contact_id" => $contact_id,
                                    "type" => $type,
                                    "email_id" => $row_id,
                                    "modified" => DateTimeComponent::getDateTime()
                                ];
                                   
                                if (!empty($already_exists["id"])) {
                                    $save_data["id"] = $already_exists["id"];
                                    $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                                }
                                $saved = $model_email_recipients->save($save_data);

                                if (!empty($already_exists["id"])) {
                                    $inserted_emailrecipient_id = $already_exists["id"];
                                } else {
                                    $inserted_emailrecipient_id = $model_email_recipients->getLastInsertId();
                                }
                                $total_recipients++;

                            } catch(\Exception $e) { }
                        }
                    }
                }

                $email_send_cc = [];
                if (!empty($request_data["cc"])) {
                    $type = SHAppComponent::getValue("app_constant/CC_EMAIL_RECIPIENTS");

                    if (is_array($request_data["cc"])) {
                        $request_data_cc = $request_data["cc"];
                    } else {
                        $request_data_cc = explode(",", $request_data["cc"]);
                    }

                    foreach ($request_data_cc as $cc) {
                        $payload = [];

                        if(is_array($cc)) {
                            if (!empty($cc["name"])) {
                                $names_arr = explode(" ", trim($cc["name"]));
                                $first_name = isset($names_arr[0]) ? $names_arr[0] : "";
                                $last_name = trim(substr($cc["name"], strlen($first_name)));

                                $payload = [
                                    "first_name" => $first_name,
                                    "last_name" => $last_name,
                                ];
                            }
                            $cc = $cc["emailAddress"];
                        }
                        $email_send_cc[] = $cc;

                        $contact_id = $model_account_contacts->getContactIdByEmail($cc, $logged_in_account, $payload);

                        if (!empty($contact_id)) {
                            try {
                                // Fetch is data already exists
                                $condition = [
                                    "fields" => [
                                        "er.id"
                                    ],
                                    "where" => [
                                        ["where" => ["er.email_id", "=", $row_id]],
                                        ["where" => ["er.account_contact_id", "=", $contact_id]],
                                        ["where" => ["er.type", "=", $type]],
                                    ]
                                ];
                                $already_exists = $model_email_recipients->fetch($condition);

                                $save_data = [
                                    "account_id" => $logged_in_account,
                                    "account_contact_id" => $contact_id,
                                    "type" => $type,
                                    "email_id" => $row_id,
                                    "modified" => DateTimeComponent::getDateTime()
                                ];
                                   
                                if (!empty($already_exists["id"])) {
                                    $save_data["id"] = $already_exists["id"];
                                    $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                                }
                                $saved = $model_email_recipients->save($save_data);

                                if (!empty($already_exists["id"])) {
                                    $inserted_emailrecipient_id = $already_exists["id"];
                                } else {
                                    $inserted_emailrecipient_id = $model_email_recipients->getLastInsertId();
                                }
                                $total_recipients++;

                            } catch(\Exception $e) { }
                        }
                    }
                }

                $email_send_bcc = [];
                if (!empty($request_data["bcc"])) {
                    $type = SHAppComponent::getValue("app_constant/BCC_EMAIL_RECIPIENTS");

                    if (is_array($request_data["bcc"])) {
                        $request_data_bcc = $request_data["bcc"];
                    } else {
                        $request_data_bcc = explode(",", $request_data["bcc"]);
                    }

                    foreach ($request_data_bcc as $bcc) {
                        $payload = [];

                        if(is_array($bcc)) {
                            if (!empty($bcc["name"])) {
                                $names_arr = explode(" ", trim($bcc["name"]));
                                $first_name = isset($names_arr[0]) ? $names_arr[0] : "";
                                $last_name = trim(substr($bcc["name"], strlen($first_name)));

                                $payload = [
                                    "first_name" => $first_name,
                                    "last_name" => $last_name,
                                ];
                            }
                            $bcc = $bcc["emailAddress"];
                        }
                        $email_send_bcc[] = $bcc;

                        $contact_id = $model_account_contacts->getContactIdByEmail($bcc, $logged_in_account, $payload);

                        if (!empty($contact_id)) {
                            try {
                                // Fetch is data already exists
                                $condition = [
                                    "fields" => [
                                        "er.id"
                                    ],
                                    "where" => [
                                        ["where" => ["er.email_id", "=", $row_id]],
                                        ["where" => ["er.account_contact_id", "=", $contact_id]],
                                        ["where" => ["er.type", "=", $type]],
                                    ]
                                ];
                                $already_exists = $model_email_recipients->fetch($condition);

                                $save_data = [
                                    "account_id" => $logged_in_account,
                                    "account_contact_id" => $contact_id,
                                    "type" => $type,
                                    "email_id" => $row_id,
                                    "modified" => DateTimeComponent::getDateTime()
                                ];
                                   
                                if (!empty($already_exists["id"])) {
                                    $save_data["id"] = $already_exists["id"];
                                    $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                                }
                                $saved = $model_email_recipients->save($save_data);

                                if (!empty($already_exists["id"])) {
                                    $inserted_emailrecipient_id = $already_exists["id"];
                                } else {
                                    $inserted_emailrecipient_id = $model_email_recipients->getLastInsertId();
                                }
                                $total_recipients++;

                            } catch(\Exception $e) { }
                        }
                    }
                }

                // Update total recipients
                try {
                    $save_data = [
                        "id" => $row_id,
                        "total_recipients" => $total_recipients
                    ];
                    $saved = $model_email_master->save($save_data);
                    
                } catch(\Exception $e) { }

                // Prepare tracking pixel
                $encrypted_pixel_id = StringComponent::encodeRowId($row_id);
                $encrypted_user_id = StringComponent::encodeRowId($logged_in_user);
                $encrypted_account_id = StringComponent::encodeRowId($logged_in_account);

                $tracking_url = TRACKING_PIXEL[rand(0, TRACKING_PIXEL_COUNT)];
                $tracking_pixel_image = '<img width="1" height="1" id="shtracking_1s2" src="' . $tracking_url . '/'. TRACKING_UNIQUE_TEXT . '/e/' . $encrypted_pixel_id . '/' . $encrypted_user_id . '/' . $encrypted_account_id . '" style="display:none">';
                
                // Send email if not schedule
                if($request_data["is_scheduled"] == 0 && $request_data["is_draft"] == SHAppComponent::getValue("app_constant/FLAG_NO")) {
                    $model_account_sending_method = new AccountSendingMethods();

                    $other_values = [
                        "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
                    ];

                    $row = $model_account_sending_method->getSendingMethodById($request_data["sending_account"], $other_values);
                    if(!empty($row)) {
                        $email_sending_method_id = $row["email_sending_method_id"];
                        $payload = json_decode($row["payload"]);

                        if ( $request_data["track_click"] == 1 ) {

                            $regex = FIND_LINK_PATTERN;
                            preg_match_all($regex, $request_data["content"], $attachment_urls);

                            $model_account_link_master = new AccountLinkMaster();
                            $model_email_links = new EmailLinks();

                            // If link exists in content
                            if (!empty($attachment_urls[2])) {
                                foreach ($attachment_urls[2] as $url) {
                                    $link_id = $model_account_link_master->getLinkIdByUrl($url, $logged_in_account);
                                    $redirect_key = StringComponent::encodeRowId([$logged_in_account, $row_id, $link_id]);
                     
                                    $condition = [
                                        "fields" => ["id"],
                                        "where"  => [["where" => ["redirect_key", "=", $redirect_key]]]
                                    ];
                                    try {
                                        $already_exist = $model_email_links->fetch($condition);
                                        if (empty($already_exist["id"])) {
                                            $save_data = [
                                                "account_id" => $logged_in_account,
                                                "email_id" => $row_id,
                                                "account_link_id" => $link_id,
                                                "redirect_key" => $redirect_key
                                            ];
                                            $model_email_links->save($save_data);
                                        }
                                        $new_url = $tracking_url . "/". TRACKING_UNIQUE_TEXT . "/r/e/" . $redirect_key ."?r=".$url;
                                        $request_data["content"] = str_replace('"' . $url . '"', '"' . $new_url . '"', $request_data["content"]);
                                    
                                    } catch(\Exception $e) {}
                                }
                            }
                        }

                        $info["subject"] = $request_data["subject"];
                        $info["content"] = $request_data["content"] . $tracking_pixel_image;
                        $info["to"] = $email_send_to;
                        $info["cc"] = implode(",", $email_send_cc);
                        $info["bcc"] = implode(",", $email_send_bcc);
                        $info["from_email"] = $row["from_email"];
                        $info["from_name"] = $row["from_name"];
                        $info["reply_to"] = "";
                        
                        // Send email with Gmail
                        if($email_sending_method_id == SHAppComponent::getValue("email_sending_method/GMAIL")) {
                            $info["refresh_token"] = $payload->refresh_token;
                            $info["thread_id"] = "";

                            $result = GmailComponent::sendMailGmail($info, false);
                            if(isset($result["gmail_message_id"]) && $result["gmail_message_id"] != "") {
                                //mail sent successfully
                                $progress = SHAppComponent::getValue("app_constant/PROGRESS_SENT");
                                $sent_message_id = $result["gmail_message_id"];
                                $sent_response = "";
                                $sent_at = DateTimeComponent::getDateTime();
                            } else {
                                //mail not sent
                                $progress = SHAppComponent::getValue("app_constant/PROGRESS_FAILED");
                                $sent_message_id = "0";
                                $sent_response = $result["error_message"];
                                $sent_at = DateTimeComponent::getDateTime();
                            }

                            try {
                                $condition = [
                                    "where" => [
                                        ["where" => ["email_id", "=", $row_id]]
                                    ]
                                ];
                                $save_data = [
                                    "progress" => $progress
                                ];
                                $updated = $model_email_recipients->update($save_data, $condition);
                            } catch(\Exception $e) {
                                // Fetch error code & message and return the response
                                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                            }

                            try {
                                $save_data = [
                                    "id" => $row_id,
                                    "sent_at" => $sent_at,
                                    "sent_message_id" => $sent_message_id,
                                    "sent_response" => $sent_response,
                                    "progress" => $progress
                                ];
                                $model_email_master->save($save_data);
                            } catch(\Exception $e) {
                                // Fetch error code & message and return the response
                                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                            }
                        }

                        // Send email with user's SMTP
                        if($email_sending_method_id == SHAppComponent::getValue("email_sending_method/SMTP")) {
                            $info["smtp_details"]["host"] = $payload->host;
                            $info["smtp_details"]["username"] = $payload->username;
                            $info["smtp_details"]["password"] = $payload->password;
                            $info["smtp_details"]["port"] = $payload->port;
                            $info["smtp_details"]["encryption"] = $payload->encryption;

                            $result = SMTPComponent::mailSendUserSmtp($info);
                            if(isset($result["message_id"]) && $result["message_id"] != "") {
                                //mail sent successfully
                                $progress = SHAppComponent::getValue("app_constant/PROGRESS_SENT");
                                $sent_message_id = $result["message_id"];
                                $sent_response = "";
                                $sent_at = DateTimeComponent::getDateTime();
                            } else {
                                //mail not sent
                                $progress = SHAppComponent::getValue("app_constant/PROGRESS_FAILED");
                                $sent_message_id = "0";
                                $sent_response = $result["message"];
                                $sent_at = DateTimeComponent::getDateTime();
                            }
                            try {
                                $condition = [
                                    "where" => [
                                        ["where" => ["email_id", "=", $row_id]]
                                    ]
                                ];
                                
                                $save_data = [
                                    "progress" => $progress
                                ];
                                $updated = $model_email_recipients->update($save_data, $condition);
                            } catch(\Exception $e) {
                                // Fetch error code & message and return the response
                                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                            }
                            try {
                                $save_data = [
                                    "id" => $row_id,
                                    "sent_at" => $sent_at,
                                    "sent_message_id" => $sent_message_id,
                                    "sent_response" => $sent_response,
                                    "progress" => $progress
                                ];
                                $model_email_master->save($save_data);
                            } catch(\Exception $e) {
                                // Fetch error code & message and return the response
                                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                            }
                        }
                    }
                }
                //code for plugin request custom link tracker (chrome/outlook)
                if (($logged_in_source == $source_chrome || $logged_in_source == $source_outlook) && $request_data["track_click"] == SHAppComponent::getValue("app_constant/FLAG_YES")){
                    if (!empty($request_data["track_links"])) {
                        
                        $model_account_link_master = new AccountLinkMaster();                                              
                        $model_email_links = new EmailLinks();

                        $condition = [
                            "where" => [
                                ["where" => ["email_id", "=", $row_id]],
                                ["where" => ["account_id", "=", $logged_in_account]]
                            ]
                        ];
                        $save_data = [
                            "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                            "modified" => DateTimeComponent::getDateTime()
                        ];
                        $model_email_links->update($save_data, $condition);
            
                        foreach ($request_data["track_links"] as $url) {
                            
                            $link_domain = \DOC_VIEWER_BASE_URL;
                            $link_domain = $link_domain."/view/";
                            $findDocSpace = strpos($url, $link_domain);   
                                                            
                            if($findDocSpace===false){    

                                $parse_url = parse_url($url);

                                if (!empty($parse_url["host"] && $parse_url["scheme"])) {
                                    $domain_name = $parse_url["scheme"] . "://" . $parse_url["host"];
                                    
                                    if ($domain_name != TRACKING_PIXEL[0] && $domain_name != TRACKING_PIXEL[1]) {
                                        $link_id = $model_account_link_master->getLinkIdByUrl($url, $logged_in_account);
                                        $redirect_key = StringComponent::encodeRowId([$logged_in_account, $row_id, $link_id]);

                                        $condition = [
                                            "fields" => ["id"],
                                            "where"  => [["where" => ["redirect_key", "=", $redirect_key]]]
                                        ];
                                        try {
                                            $row_data = $model_email_links->fetch($condition);
                                            if (empty($row_data["id"])) {
                                                $save_data = [
                                                    "account_id" => $logged_in_account,
                                                    "email_id" => $row_id,
                                                    "account_link_id" => $link_id,
                                                    "redirect_key" => $redirect_key
                                                ];
                                                $model_email_links->save($save_data);                                          
                                            } else {
                                                $condition["where"]["where"] = ["where" => ["redirect_key", "=", $redirect_key]];
                                                $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                                                $save_data["modified"] = DateTimeComponent::getDateTime();
                                        
                                                $model_email_links->update($save_data, $condition);
                                            }
                                            $custom_url = $tracking_url . "/". TRACKING_UNIQUE_TEXT . "/r/e/" . $redirect_key . "?r=".$url;
                                            $output["tracked_links"][$url] = $custom_url;

                                        } catch(\Exception $e) {
                                            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                                        }         
                                    } else {
                                        $redirect_key = substr($parse_url["path"], strrpos($parse_url["path"], '/') + 1);
                                        
                                        $condition["where"]["where"] = ["where" => ["redirect_key", "=", $redirect_key]];
                                        $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                                        $save_data["modified"] = DateTimeComponent::getDateTime();
                                        
                                        $model_email_links->update($save_data, $condition);
                                        $output["tracked_links"][$url] = $url;  
                                    }
                                }
                            }else{
                                $output["tracked_links"][$url] = $url;
                            } 
                        }
                    }
                }
                $output["id"] = StringComponent::encodeRowId($row_id);
                if ($request_data["is_draft"]) {
                    $output["message"] = "Email saved successfully."; 
                } else if ($request_data["is_scheduled"]) {
                    /*$sh_activity = new SHActivity();
                    $activity_params = [
                        "user_id" => $logged_in_user,
                        "account_id" => $logged_in_account,
                        "action" => SHAppComponent::getValue("actions/EMAILS/SCHEDULED"),
                        "record_id" => $row_id
                    ];
                    $sh_activity->addActivity($activity_params);*/
                    
                    $output["message"] = "Email scheduled successfully.";
                } else {
                    if ( $result["success"] ) {
                        $activity_params = [
                            "user_id" => $logged_in_user,
                            "account_id" => $logged_in_account,
                            "action" => SHAppComponent::getValue("actions/EMAILS/SENT"),
                            "record_id" => $row_id
                        ];
                        $sh_activity = new SHActivity();
                        $sh_activity->addActivity($activity_params);

                        $output["message"] = "Email sent successfully.";
                    } else {
                        $output["message"] = "Email could not be sent.";
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
     * Update status of existing record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function updateStatus(ServerRequestInterface $request, ResponseInterface $response, $args) {
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
        $model_email_master = new EmailMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_email_master->checkRowValidity($row_id, $other_values);
          
            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Check previous status if mail already sent
        try {
            $fields = ["progress"];
            $row = $model_email_master->fetchById($row_id, $fields);
            $row["progress"] =0;
            if ( $row["progress"] > 0 ) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/MAIL_ALREADY_SENT");
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get request parameters
        $request_params = $request->getParsedBody();
   
        $request_data = [];

        if (isset($request_params["progress"])) {
            $request_data["progress"] = $request_params["progress"];
        }
        if (isset($request_params["sent_message_id"])) {
            $request_data["sent_message_id"] = $request_params["sent_message_id"];
        }

        // Validate request
        $request_validations = [
            "progress" => [
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
        try {
           
            $scheduled_at = 0;
           
            $save_data = [
                "id" => $row_id,
                "modified" => DateTimeComponent::getDateTime(),
                "status"   => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                "progress" => SHAppComponent::getValue("app_constant/PROGRESS_SCHEDULED")
            ];

            if ($model_email_master->save($save_data) !== false) {

                // Insert email recipients
                $model_email_recipients = new EmailRecipients();
                $model_account_contacts = new AccountContacts();
                $total_recipients = 0;

                // Update all recipients status to delete
                $condition = [
                    "where" => [
                        ["where" => ["email_id", "=", $row_id]]
                    ]
                ];
                          
                $progress = $request_data["progress"];
                $sent_at = DateTimeComponent::getDateTime();
                try {
                        $condition = [
                                ["where" => ["email_id", "=", $row_id]]
                        ];
                        $save_data = [
                            "progress" => $progress
                        ];
             
                        $updated = $model_email_recipients->update($save_data, $condition);
                    } catch(\Exception $e) {

                        // Fetch error code & message and return the response
                        return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                    }

                    try {
                        
                        if(!empty($request_data["sent_message_id"])){
                            $sent_message_id_var = $request_data["sent_message_id"];
                        } else {
                            $sent_message_id_var = "";
                        }

                        $save_data = [
                            "id" => $row_id,
                            "sent_at" => $sent_at,
                            "sent_message_id" => $sent_message_id_var,
                            "progress" => $progress
                        ];
                        $model_email_master->save($save_data);
                    } catch(\Exception $e) {
                        // Fetch error code & message and return the response
                        return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                    }

                    $progress = SHAppComponent::getValue("app_constant/PROGRESS_SENT");
                    $sent_response = "";
                    $sent_at = DateTimeComponent::getDateTime();
               
                    try {
                        $condition = [
                            "where" => [
                                ["where" => ["email_id", "=", $row_id]]
                            ]
                        ];
                        
                        $save_data = [
                            "progress" => $progress
                        ];
                        $updated = $model_email_recipients->update($save_data, $condition);
                    } catch(\Exception $e) {
                        // Fetch error code & message and return the response
                        return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                    }

                    try {
                        $save_data = [
                            "id" => $row_id,
                            "sent_at" => $sent_at,
                            "progress" => $progress
                        ];
                        $model_email_master->save($save_data);
                        if($progress == SHAppComponent::getValue("app_constant/PROGRESS_SENT")) {
                             $activity_params = [
                                "user_id" => $logged_in_user,
                                "account_id" => $logged_in_account,
                                "action" => SHAppComponent::getValue("actions/EMAILS/SENT"),
                                "record_id" => $row_id
                            ];

                            $sh_activity = new SHActivity();
                            $sh_activity->addActivity($activity_params);
                        }
                    } catch(\Exception $e) {
                        // Fetch error code & message and return the response
                        return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                    }
                

                $output["message"] = "Email updated successfully.";

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
        $model_email_master = new EmailMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_email_master->checkRowValidity($row_id, $other_values);

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
                    "em.id",
                    "em.account_template_id",
                    "em.subject",
                    "em.content",
                    "em.source_id",
                    "em.sent_message_id",
                    "em.is_scheduled",
                    "em.scheduled_at",
                    "em.timezone",
                    "em.sent_at",
                    "em.track_reply",
                    "em.track_click",
                    "em.total_recipients",
                    "em.snooze_notifications",
                    "em.progress",
                    "em.status",
                    "em.account_sending_method_id",
                    "asm.name",
                    "asm.payload",
                    "asm.from_email",
                    "em.user_id",
                    "um.first_name",
                    "um.last_name"
                ],
                "where" => [
                    ["where" => ["em.id", "=", $row_id]]
                ],
                "join" => [
                    "user_master",
                    "account_sending_methods_left"
                ]
            ];
            $row = $model_email_master->fetch($condition);

            if ($row["source_id"] == SHAppComponent::getValue("source/CHROME_PLUGIN")) {
                $url = "https://mail.google.com/mail/u/?authuser=" . $row["from_email"] . "#all/" . $row["sent_message_id"];
                if (isset($row["payload"])) {
                    $payload = json_decode($row["payload"], true);
                    if (!empty($payload["refresh_token"])) {
                        
                        $content = GmailComponent::getMessage($row["sent_message_id"], $payload["refresh_token"]);
                        $regex = '(<img[^>]*(?:height|width)\s*=\s*"[1]?[1]"[^>]*>)';
                        $content = preg_replace($regex, "", $content);
                        $pattern = '/(<img.*?)(app.saleshandy.com)(.*?[^>]*>)/i';
                        $content = preg_replace($pattern, "", $content);

                        $row["content"] = empty($content) ? "Content not found" : $content;
                    } else {
                        $row["content"] = "<a href='".$url."' target='_blank'>Click here to Redirect to Gmail</a>";
                    }
                } else {
                    $row["content"] = "<a href='".$url."' target='_blank'>Click here to Redirect to Gmail</a>";
                }
            } else if ($row["source_id"] == SHAppComponent::getValue("source/OUTLOOK_PLUGIN")) {
                $row["content"] = "Mail send using Outlook plugin."; 
            }
            // Get recipients
            $to_array = [];
            $model_email_recipients = new EmailRecipients();

            $condition = [
                "fields" => [
                    "ac.id",
                    "ac.first_name",
                    "ac.last_name",
                    "ac.email",
                    "ac.status",
                    "er.type"
                ],
                "where" => [
                    ["where" => ["er.email_id", "=", $row_id]],
                    ["where" => ["er.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ],
                "join" => [
                    "account_contacts"
                ],
                "order_by" => [
                    "er.type"
                ]
            ];
            $contacts_array = $model_email_recipients->fetchAll($condition);

            foreach ($contacts_array as $contact) {
                $deleted = "";

                if ($contact["status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
                    $deleted = "(Deleted)";
                }
                $to_array[] = [
                    "id" => StringComponent::encodeRowId($contact["id"]),
                    "name" => trim($contact["first_name"] . " " . $contact["last_name"]),
                    "email" => $contact["email"] . $deleted,
                    "type" => $contact["type"]
                ];
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $default_timezone = \DEFAULT_TIMEZONE;
        
        $scheduled_at = "NA";
        $sent_at = "";

        if ( $row["scheduled_at"] != "" && $row["scheduled_at"] > 0 ) {
            $scheduled_at = DateTimeComponent::convertDateTime($row["scheduled_at"], true, \DEFAULT_TIMEZONE, $row["timezone"], \APP_TIME_FORMAT);
        }
        if ( $row["sent_at"] != "" && $row["sent_at"] > 0 ) {
            $sent_at = DateTimeComponent::convertDateTime($row["sent_at"], true, \DEFAULT_TIMEZONE, $row["timezone"], \APP_TIME_FORMAT);
        }

        // Prepare output of data
        $output = [
            "id" => StringComponent::encodeRowId($row["id"]),
            "subject" => $row["subject"],
            "source" => $row["source_id"],
            "content" => $row["content"],
            "template" => StringComponent::encodeRowId($row["account_template_id"]),
            "is_scheduled" => (bool) $row["is_scheduled"],
            "scheduled_at" => DateTimeComponent::showDateTime($scheduled_at, $row["timezone"]),
            "scheduled_at_timestamp" => $row["scheduled_at"],
            "timezone" => $row["timezone"],
            "sent_at" => DateTimeComponent::showDateTime($sent_at, $row["timezone"]),
            "sent_at_timestamp" => $row["sent_at"],
            "track_reply" => (bool) $row["track_reply"],
            "track_click" => (bool) $row["track_click"],
            "total_recipients" => $row["total_recipients"],
            "snooze" => (bool) $row["snooze_notifications"],
            "account_sending_method_id" => StringComponent::encodeRowId($row["account_sending_method_id"]),
            "email_sending_account" => $row["name"],
            "created_by" => trim($row["first_name"] . " " . $row["last_name"]),
            "created_by_id" => StringComponent::encodeRowId($row["user_id"]),
            "to" => $to_array
        ];

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
        $model_email_master = new EmailMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_email_master->checkRowValidity($row_id, $other_values);

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
                    "em.account_template_id",
                    "em.account_sending_method_id",
                    "em.subject",
                    "em.content",
                    "em.track_reply",
                    "em.track_click",
                    "em.total_recipients",
                    "em.snooze_notifications"
                ],
                "where" => [
                    ["where" => ["em.id", "=", $row_id]]
                ]
            ];
            $row = $model_email_master->fetch($condition);

            $save_data = [
                "account_id" => $logged_in_account,
                "user_id" => $logged_in_user,
                "account_template_id" => $row["account_template_id"],
                "account_sending_method_id" => $row["account_sending_method_id"],
                "source_id" => $logged_in_source,
                "subject" => "Copy of " . $row["subject"],
                "content" => $row["content"],
                "track_reply" => $row["track_reply"],
                "track_click" => $row["track_click"],
                "total_recipients" => $row["total_recipients"],
                "snooze_notifications" => $row["snooze_notifications"],
                "progress" => SHAppComponent::getValue("app_constant/PROGRESS_SCHEDULED"),
                "status" => SHAppComponent::getValue("app_constant/STATUS_DRAFT"),
                "created" => DateTimeComponent::getDateTime(),
                "modified" => DateTimeComponent::getDateTime()
            ];
            if ($model_email_master->save($save_data) !== false) {
                $inserted_email_id = $model_email_master->getLastInsertId();

                // Copy recipients
                $model_email_recipients = new EmailRecipients();

                $condition = [
                    "fields" => [
                        "er.account_contact_id"
                    ],
                    "where" => [
                        ["where" => ["er.email_id", "=", $row_id]],
                        ["where" => ["er.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                    ]
                ];
                $email_contacts = $model_email_recipients->fetchAll();

                foreach ($email_contacts as $contact) {
                    try {
                        $save_data = [
                            "account_id" => $logged_in_account,
                            "email_id" => $inserted_email_id,
                            "account_contact_id" => $contact["account_contact_id"],
                            "type" => $contact["type"],
                            "modified" => DateTimeComponent::getDateTime()
                        ];
                        $saved = $model_email_recipients->save($save_data);

                    } catch(\Exception $e) { }
                }

                $output["id"] = StringComponent::encodeRowId($inserted_email_id);
                $output["message"] = "Email copied successfully.";

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
        $model_email_master = new EmailMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_email_master->checkRowValidity($row_id, $other_values);

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
            if ($model_email_master->save($save_data) !== false) {
                $output["message"] = "Email deleted successfully.";

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
     * Snooze notifications (toggle)
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function snooze(ServerRequestInterface $request, ResponseInterface $response, $args) {
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
        $model_email_master = new EmailMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_email_master->checkRowValidity($row_id, $other_values);

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
                    "em.snooze_notifications"
                ],
                "where" => [
                    ["where" => ["em.id", "=", $row_id]]
                ]
            ];
            $row = $model_email_master->fetch($condition);

            // Update flag
            $save_data = [
                "id" => $row_id,
                "snooze_notifications" => (int) !((bool) $row["snooze_notifications"]),
                "modified" => DateTimeComponent::getDateTime()
            ];
            if ($model_email_master->save($save_data) !== false) {
                $output["snooze"] = (bool) $save_data["snooze_notifications"];
                if ($output["snooze"]) {
                    $output["message"] = "Snooze set to ON.";
                } else {
                    $output["message"] = "Snooze set to OFF.";
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
     * details of scheduled email from plugin
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function viewScheduledEmail(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        
        $header_source = SHAppComponent::getRequestSource();

        // Get request parameters
        $route = $request->getAttribute("route");
        $sche_sent_message_id = $route->getArgument("id");
        
        // Validate request
        if (empty($sche_sent_message_id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }
        
        if($header_source != SHAppComponent::getValue("source/CHROME_PLUGIN")){
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }
        
        $model_email_master = new EmailMaster();

        try {
            // Fetch data
            $condition = [
                "fields" => [
                    "em.id",
                    "em.scheduled_at",
                    "em.timezone",
                    "em.track_click",
                    "em.snooze_notifications"
                ],
                "where" => [
                    ["where" => ["em.source_id", "=", SHAppComponent::getValue("source/CHROME_PLUGIN")]],
                    ["where" => ["em.progress", "=", SHAppComponent::getValue("app_constant/PROGRESS_SCHEDULED")]],
                    ["where" => ["em.status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]],
                    ["where" => ["em.sent_message_id", "=", $sche_sent_message_id]],
                    ["where" => ["em.account_id", "=", $logged_in_account]],
                    ["where" => ["em.user_id", "=", $logged_in_user]]
                ]
            ];
            $row = $model_email_master->fetch($condition);
            $row_count = count($row);
            if($row_count > 0){

                $scheduled_at = "";                
                if ( $row["scheduled_at"] != "" && $row["scheduled_at"] > 0 ) {
                    $scheduled_at = DateTimeComponent::convertDateTime($row["scheduled_at"], true, \DEFAULT_TIMEZONE, $row["timezone"], \APP_TIME_FORMAT);
                }
                
                // Prepare output of data
                $output = [
                    "id" => StringComponent::encodeRowId($row["id"]),
                    "scheduled_at" => DateTimeComponent::showDateTime($scheduled_at, $row["timezone"]),
                    "scheduled_at_timestamp" => $row["scheduled_at"],
                    "timezone" => $row["timezone"],
                    "track_click" => $row["track_click"],
                    "snooze_notifications" => $row["snooze_notifications"]
                ];

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
            
        return $response->withJson($output, 200);

    }
}
