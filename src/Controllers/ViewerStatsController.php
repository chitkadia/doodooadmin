<?php
/**
 * Listing resources
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
use \App\Models\DocumentMaster;
use \App\Models\DocumentLinks;
use \App\Models\DocumentLinkFiles;
use \App\Models\DocumentLinkVisits;
use \App\Models\DocumentLinkVisitLogs;
use \App\Models\AccountContacts;
use \App\Models\AccountCompanies;

class ViewerStatsController extends AppController {

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Post usage of document viewer
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function postUsage(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");
        $link_id = $route->getArgument("link_id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $visit_id = StringComponent::decodeRowId($id);
        $link_id = StringComponent::decodeRowId($link_id);
        $status_active = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");

        try {
            // Check document visit
            $model_document_link_visits = new DocumentLinkVisits();

            $condition = [
                "fields" => [
                    "account_contact_id"
                ],
                "where" => [
                    ["where" => ["id", "=", $visit_id]],
                    ["where" => ["document_link_id", "=", $link_id]]
                ]
            ];
            $row_data = $model_document_link_visits->fetch($condition);

            if (empty($row_data)) {
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
        $request_data = [
            "payload" => ""
        ];

        if (!empty($request_params)) {
            $request_data["payload"] = $request_params;
        }

        //print_r($request_data["payload"]); exit;

        // Validate request
        $request_validations = [
            "payload" => [
                ["type" => Validator::FIELD_JSON_ARRAY]
            ]
        ];
        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        try {
            // Get link documents
            $model_document_link_files = new DocumentLinkFiles();

            $condition = [
                "fields" => [
                    "dm.id"
                ],
                "where" => [
                    ["where" => ["dlf.document_link_id", "=", $link_id]],
                    ["where" => ["dm.status", "=", $status_active]],
                    ["where" => ["dlf.status", "=", $status_active]]
                ],
                "join" => [
                    "document_master"
                ]
            ];
            $link_documents = $model_document_link_files->fetchAll($condition);

            // Prepare data to insert for visit logs
            $visit_logs_data = [];
            $document_visits_data = [];
            $o_d = -1;
            $o_p = -1;
            $total_spent_time = 0;
            for ($r=0; $r<count($request_data["payload"]); $r++) {
                $d = !empty($request_data["payload"][$r]["d"]) ? StringComponent::decodeRowId(trim($request_data["payload"][$r]["d"])) : 0;
                $p = isset($request_data["payload"][$r]["p"]) ? (int) $request_data["payload"][$r]["p"] : 0;
                $i = isset($request_data["payload"][$r]["i"]) ? strtotime($request_data["payload"][$r]["i"]) : 0;
                $o = isset($request_data["payload"][$r]["o"]) ? strtotime($request_data["payload"][$r]["o"]) : 0;
                
                if (!empty($d) && !empty($p) && !empty($i) && !empty($o)) {
                    if ($o_d != $d || $o_p != $p) {
                        if ($o_d > 0 && $o_p > 0) {
                            $visit_logs_data[] = [
                                "document" => $o_d,
                                "page" => $o_p,
                                "time" => $total_spent_time
                            ];

                            $document_visits_data[$o_d] = [
                                "contact_id" => $row_data["account_contact_id"],
                                "visited_at" => $o
                            ];
                        }

                        $total_spent_time = 0;
                        $o_d = $d;
                        $o_p = $p;
                    }

                    $total_spent_time += (($o - $i) > 0) ? $o - $i : 0;
                }
            }
            // log last entry
            if (!empty($o_d) && !empty($o_p) && !empty($total_spent_time)) {
                $visit_logs_data[] = [
                    "document" => $o_d,
                    "page" => $o_p,
                    "time" => $total_spent_time
                ];

                $document_visits_data[$o_d] = [
                    "contact_id" => $row_data["account_contact_id"],
                    "visited_at" => $o
                ];
            }

            // Insert visit logs
            $model_document_link_visit_logs = new DocumentLinkVisitLogs();

            foreach ($visit_logs_data as $logs) {
                try {
                    $save_data = [
                        "visit_id" => $visit_id,
                        "document_id" => $logs["document"],
                        "page_num" => $logs["page"],
                        "time_spent" => $logs["time"],
                        "modified" => DateTimeComponent::getDateTime()
                    ];
                    $model_document_link_visit_logs->save($save_data);

                } catch(\Exception $e) { }
            }

            // Update who visited the document last
            if (!empty($document_visits_data)) {
                $model_document_master = new DocumentMaster();

                foreach ($document_visits_data as $doc_id => $doc_visit) {
                    try {
                        $save_data = [
                            "id" => $doc_id,
                            "last_opened_by" => $doc_visit["contact_id"],
                            "last_opened_at" => $doc_visit["visited_at"]
                        ];
                        $model_document_master->save($save_data);

                    } catch(\Exception $e) { }
                }
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $output["error"] = false;

        return $response->withJson($output, 200);
    }

    /**
     * Verify visitor info while visiting the link
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function verifyVisitor(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $id = StringComponent::decodeRowId($id);
        $status_active = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
        $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");

        try {
            $model_document_links = new DocumentLinks();

            $condition = [
                "fields" => [
                    "id",
                    "account_id",
                    "ask_visitor_info",
                    "visitor_info_payload",
                    "password_protected",
                    "access_password",
                    "visitor_slug"
                ],
                "where" => [
                    ["where" => ["id", "=", $id]],
                    ["where" => ["status", "=", $status_active]]
                ]
            ];
            $row_data = $model_document_links->fetch($condition);

            if (empty($row_data)) {
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
        $request_data = [
            "email" => "",
            "name" => "",
            "company" => "",
            "phone" => "",
            "password" => "",
            "r" => "",
            "m" => $flag_yes
        ];

        if (isset($request_params["name"])) {
            $request_data["name"] = $request_params["name"];
        }
        if (isset($request_params["email"])) {
            $request_data["email"] = $request_params["email"];
        }
        if (isset($request_params["company"])) {
            $request_data["company"] = $request_params["company"];
        }
        if (isset($request_params["phone"])) {
            $request_data["phone"] = $request_params["phone"];
        }
        if (isset($request_params["password"])) {
            $request_data["password"] = $request_params["password"];
        }
        if (isset($request_params["r"])) {
            $request_data["r"] = $request_params["r"];
        }
        if (isset($request_params["m"])) {
            $request_data["m"] = (int) $request_params["m"];
        }

        // Validate request
        $request_validations = [
            "r" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ]
        ];
        if ($row_data["ask_visitor_info"] == $flag_yes) {
            $request_validations["email"] = [
                ["type" => Validator::FIELD_REQ_NOTEMPTY],
                ["type" => Validator::FIELD_EMAIL]
            ];
        }
        if ($row_data["password_protected"] == $flag_yes) {
            $request_validations["password"] = [
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

        try {
            if ($row_data["password_protected"] == $flag_yes && $request_data["password"] != $row_data["access_password"]) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DV_WRONG_PASSWORD");
            }

            $company_id = $contact_id = null;
            if ($row_data["ask_visitor_info"] == $flag_yes && empty($request_data["m"])) {
                $company_name = trim($request_data["company"]);
                if (!empty($company_name)) {
                    try {
                        $model_account_companies = new AccountCompanies();
                        $company_id = $model_account_companies->getCompanyIdByName($company_name, $row_data["account_id"]);

                    } catch(\Exception $e) { }
                }

                try {
                    $contact_email = trim($request_data["email"]);
                    $names_arr = explode(" ", trim($request_data["name"]));

                    $first_name = isset($names_arr[0]) ? $names_arr[0] : "";
                    $last_name = trim(substr($contact_name, strlen($first_name)));

                    $payload = [
                        "first_name" => $first_name,
                        "last_name" => $last_name,
                        "phone" => $request_data["phone"]
                    ];

                    $model_account_contacts = new AccountContacts();
                    $contact_id = $model_account_contacts->getContactIdByEmail($contact_email, $row_data["account_id"], $payload);

                } catch(\Exception $e) { }
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $output = [
            "success" => true,
            "t" => \DOC_VIEWER_SLUG_TIMEOUT,
            "data" => StringComponent::encodeRowId($contact_id)
        ];

        return $response->withJson($output, 200);
    }

}
