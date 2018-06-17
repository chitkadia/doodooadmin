<?php
/**
 * Templates related functionality
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
use \App\Models\AccountTemplates;
use \App\Models\AccountTemplateTeams;
use \App\Models\AccountFolders;


class TemplatesController extends AppController {

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
            $user_id = StringComponent::decodeRowId(trim($params["user"]));

            if (!empty($user_id)) {
                $query_params["user"] = $user_id;
            }
        }
        if (!empty($params["status"])) {
            $status = SHAppComponent::getValue("app_constant/" . trim($params["status"]));

            if (!empty($status)) {
                $query_params["status"] = $status;
            }
        }
        if (!empty($params["folder"])) {
            $folder = StringComponent::decodeRowId(trim($params["folder"]));

            if (!empty($folder)) {
                $query_params["folder"] = $folder;

                $model_account_folder = new AccountFolders();

                $folder_data = $model_account_folder->fetchById($folder,"share_access");
                
                if (!empty($folder_data)) {
                    $output["folder_share_access"] = trim($folder_data["share_access"]);
                }
            }
        }
        if (!empty($params["shared"])) {
            $query_params["shared"] = 1;
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
        $model_account_templates = new AccountTemplates();
       
        try {
            $data = $model_account_templates->getListData($query_params, $other_values);
        
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
        $folder_share_access = [];
        $template_share_access = [];

        foreach ($data["rows"] as $row) {
            $status = array_search($row["status"], $app_constants);

            $last_used_at = DateTimeComponent::convertDateTime($row["last_used_at"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);

            $shared = false;
            if (!empty($row["total_teams"]) || $row["public"]) {
                $shared = true;
            }

            // set sharing opertaion option for folder and templates
            $folder_share_access = json_decode($row["folder_share_access"], true);
            $template_share_access = json_decode($row["share_access"], true);

            if ($folder_share_access["can_edit"] == true && $template_share_access["can_edit"] == false) {
                $share_access["can_edit"] = (bool) $folder_share_access["can_edit"];
            } else if ($template_share_access["can_edit"] == true && $folder_share_access["can_edit"] == false) {
                $share_access["can_edit"] =  (bool) $template_share_access["can_edit"];
            } else if ($template_share_access["can_edit"] == true) {
                 $share_access["can_edit"] =  (bool) $template_share_access["can_edit"];
            } else {
                $share_access["can_edit"] =  false;  
            }

            if ($folder_share_access["can_delete"] == true && $template_share_access["can_delete"] == false) {
                $share_access["can_delete"] = (bool) $folder_share_access["can_delete"];
            } else if ($template_share_access["can_delete"] == true && $folder_share_access["can_delete"] == false) {
                $share_access["can_delete"] =  (bool) $template_share_access["can_delete"];
            } else if ($template_share_access["can_delete"] == true) {
                 $share_access["can_delete"] =  (bool) $template_share_access["can_delete"];
            } else {
                $share_access["can_delete"] =  false;  
            }

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "title" => $row["title"],
                "created_by" => trim($row["first_name"] . " " . $row["last_name"]),
                "created_by_id" => StringComponent::encodeRowId($row["user_id"]),
                "status" => $status,
                "total_mail_usage" => $row["total_mail_usage"],
                "total_mail_open" => $row["total_mail_open"],
                "email_performance" => empty($row["email_performance"]) ? 0 : $row["email_performance"],
                "total_campaign_mails" => $row["total_campaign_mails"],
                "total_campaign_open" => $row["total_campaign_open"],
                "campaign_performance" => empty($row["campaign_performance"]) ? 0 : $row["campaign_performance"],
                "public" => (bool) $row["public"],
                "shared" => $shared,
                "shared_access" => [
                    "can_edit" => $share_access["can_edit"],
                    "can_delete" => $share_access["can_delete"]
                ],
                "folder_name" => $row["folder_name"],
                "last_used_at" => DateTimeComponent::showDateTime($last_used_at, $user_timezone),
                "last_used_at_timestamp" => $row["last_used_at"]
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
            "folder" => null
        ];
        
        if (isset($request_params["title"])) {
            $request_data["title"] = $request_params["title"];
        }
        if (isset($request_params["subject"])) {
            $request_data["subject"] = $request_params["subject"];
        }
        if (isset($request_params["content"])) {
            $request_data["content"] = $request_params["content"];
        }
        if (isset($request_params["folder"])) {
            $folder_id = StringComponent::decodeRowId($request_params["folder"]);

            if (!empty($folder_id)) {
                $request_data["folder"] = $folder_id;
            }
        }

        // Validate request
        $request_validations = [
            "title" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "subject" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "content" => [
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
        $model_account_templates = new AccountTemplates();

        try {

            // Check if template exist for logged in user
            $condition = [
                "fields" => [
                    "id"
                ],
                "where" => [
                    ["where" => ["user_id","=",$logged_in_user]],
                    ["where" => ["title","=",$request_data["title"]]],
                    ["where" => ["status","<>",SHAppComponent::getValue("app_constant/STATUS_DELETE")]],
                ]
            ];

            $check_template_record = $model_account_templates->fetch($condition);

            if (!empty($check_template_record)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/TEMPLATE_ALREADY_EXIST");     
            }

            $share_access = json_encode(["can_edit" => false, "can_delete" => false]);

            $save_data = [
                "account_id" => $logged_in_account,
                "user_id" => $logged_in_user,
                "source_id" => $logged_in_source,
                "title" => trim($request_data["title"]),
                "subject" => trim($request_data["subject"]),
                "content" => trim($request_data["content"]),
                "share_access" => $share_access,
                "created" => DateTimeComponent::getDateTime(),
                "modified" => DateTimeComponent::getDateTime()
            ];
            if (!empty($request_data["folder"])) {
                $save_data["account_folder_id"] = $request_data["folder"];
            }

            if ($model_account_templates->save($save_data) !== false) {
                $template_id = $model_account_templates->getLastInsertId();

                $output["id"] = StringComponent::encodeRowId($template_id);
                $output["message"] = "Template created successfully.";

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

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_account_templates = new AccountTemplates();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_templates->checkRowValidity($row_id, $other_values);

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
        $request_data = [
            "folder" => null
        ];
        
        if (isset($request_params["title"])) {
            $request_data["title"] = $request_params["title"];
        }
        if (isset($request_params["subject"])) {
            $request_data["subject"] = $request_params["subject"];
        }
        if (isset($request_params["content"])) {
            $request_data["content"] = $request_params["content"];
        }
        if (isset($request_params["folder"])) {
            $folder_id = StringComponent::decodeRowId($request_params["folder"]);

            if (!empty($folder_id)) {
                $request_data["folder"] = $folder_id;
            }
        }

        // Validate request
        $request_validations = [
            "title" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "subject" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "content" => [
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

            // Check if template exist for logged in user
            $condition = [
                "fields" => [
                    "id"
                ],
                "where" => [
                    ["where" => ["id","<>",$row_id]],
                    ["where" => ["title","=",$request_data["title"]]],
                    ["where" => ["user_id","=",$logged_in_user]],
                    ["where" => ["status","<>",SHAppComponent::getValue("app_constant/STATUS_DELETE")]],
                ]
            ];

            $check_template_record = $model_account_templates->fetch($condition);

            if (!empty($check_template_record)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/TEMPLATE_ALREADY_EXIST");     
            }

            $save_data = [
                "id" => $row_id,
                "title" => trim($request_data["title"]),
                "subject" => trim($request_data["subject"]),
                "content" => trim($request_data["content"]),
                "modified" => DateTimeComponent::getDateTime()
            ];
            
            if (!empty($request_data["folder"])) {                
                $save_data["account_folder_id"] = $request_data["folder"];
            }
            if ($model_account_templates->save($save_data) !== false) {
               
                $output["message"] = "Template updated successfully.";

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
        $model_account_templates = new AccountTemplates();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_templates->checkRowValidity($row_id, $other_values);
         
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
                    "at.id",
                    "at.title",
                    "at.subject",
                    "at.content",
                    "at.last_used_at",
                    "at.status",
                    "at.public",
                    "at.share_access",
                    "af.id as folder_id",
                    "af.name as folder_name"
                ],
                "where" => [
                    ["where" => ["at.id", "=", $row_id]]
                ],
                "join" => [
                    "account_folders"
                ]
            ];
            $row = $model_account_templates->fetch($condition);
            
            // Get teams with which template is shared
            $model_account_template_teams = new AccountTemplateTeams();

            $condition = [
                "fields" => [
                    "at.id",
                    "at.name"
                ],
                "where" => [
                    ["where" => ["att.account_template_id", "=", $row_id]],
                    ["where" => ["att.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]],
                    ["where" => ["at.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ],
                "join" => [
                    "account_teams"
                ]
            ];
            $teams_data = $model_account_template_teams->fetchAll($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $app_constants = SHAppComponent::getValue("app_constant");
    
        $status = array_search($row["status"], $app_constants);
        $last_used_at = DateTimeComponent::convertDateTime($row["last_used_at"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);

        // Prepare output of data
        $share_access = json_decode($row["share_access"], true);
        if (isset($share_access["can_edit"])) {
            $share_access["can_edit"] = (bool) $share_access["can_edit"];
        } else {
            $share_access["can_edit"] = false;
        }
        if (isset($share_access["can_delete"])) {
            $share_access["can_delete"] = (bool) $share_access["can_delete"];
        } else {
            $share_access["can_delete"] = false;
        }

        $teams_list = [];
        foreach ($teams_data as $team) {
            $teams_list[] = [
                "id" => StringComponent::encodeRowId($team["id"]),
                "name" => $team["name"]
            ];
        }
      
        // Prepare output of data
        $output = [
            "id" => StringComponent::encodeRowId($row["id"]),
            "title" => $row["title"],
            "subject" => $row["subject"],
            "content" => $row["content"],
            "status" => $status,
            "public" => (bool) $row["public"],
            "shared_access" => [
                "can_edit" => $share_access["can_edit"],
                "can_delete" => $share_access["can_delete"]
            ],
            "folder_id" => StringComponent::encodeRowId($row["folder_id"]),
            "folder_name" =>$row["folder_name"],
            "last_used_at" => DateTimeComponent::showDateTime($last_used_at, $user_timezone),
            "last_used_at_timestamp" => $row["last_used_at"],
            "teams" => $teams_list
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
        $folder_id = $route->getArgument("folder_id");
        
        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);
        $folder_id = StringComponent::decodeRowId($folder_id);

        // Check if record is valid
        $model_account_templates = new AccountTemplates();
        $model_account_folder = new AccountFolders();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_templates->checkRowValidity($row_id, $other_values);

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
                    "at.title",
                    "at.subject",
                    "at.content"
                ],
                "where" => [
                    ["where" => ["at.id", "=", $row_id]]
                ]
            ];
            $row = $model_account_templates->fetch($condition);

            // Save data
            $flag_no = SHAppComponent::getValue("app_constant/FLAG_NO");

            $share_access = json_encode(["can_edit" => false, "can_delete" => false]);

            $save_data = [
                "account_id" => $logged_in_account,
                "user_id" => $logged_in_user,
                "source_id" => $logged_in_source,
                "title" => "Copy of " . $row["title"],
                "subject" => $row["subject"],
                "content" => $row["content"],
                "share_access" => $share_access,
                "created" => DateTimeComponent::getDateTime(),
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($folder_id != "") {
                // Check if folder have can_create access or not
                $condition = [
                    "fields"=>[
                        "id",
                        "user_id",
                        "share_access"
                    ],
                    "where" => [
                        ["where" => ["id","=",$folder_id]],
                        ["where" => ["status" , "<>",SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                    ]
                ];

                $getFolderData = $model_account_folder->fetch($condition);
    
                if (!empty($getFolderData)) {
                    $getAccess = json_decode($getFolderData["share_access"],true);

                    if ($getAccess["can_create"] == true || $getFolderData["user_id"] == $logged_in_user) {
                        $save_data["account_folder_id"] = $folder_id; 
                    } else {
                        $save_data["account_folder_id"] = NULL;   
                    }
                }
                $save_data["account_template_id"] = $template_id;             
            }

            if ($model_account_templates->save($save_data) !== false) {
                $template_id = $model_account_templates->getLastInsertId();

                $output["id"] = StringComponent::encodeRowId($template_id);
                $output["message"] = "Template copied successfully.";

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
        $model_account_templates = new AccountTemplates();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_templates->checkRowValidity($row_id, $other_values);

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
            if ($model_account_templates->save($save_data) !== false) {
                $output["message"] = "Template deleted successfully.";

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
        $model_account_templates = new AccountTemplates();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_templates->checkRowValidity($row_id, $other_values);

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
            "STATUS_ACTIVE"
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
            if ($model_account_templates->save($save_data) !== false) {
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
     * Move template to folder
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function move(ServerRequestInterface $request, ResponseInterface $response, $args) {
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
        $model_account_templates = new AccountTemplates();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_templates->checkRowValidity($row_id, $other_values);

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
        $request_data = [
            "folder" => null
        ];
        
        if (isset($request_params["folder"])) {
            $folder_id = StringComponent::decodeRowId($request_params["folder"]);

            if (!empty($folder_id)) {
                $request_data["folder"] = $folder_id;
            }
        }

        // Set value
        try {
         
            $condition = [
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];

            $save_data = [
                "account_folder_id" => Null,
                "modified" => DateTimeComponent::getDateTime()
            ];
            
            $updated = $model_account_templates->update($save_data, $condition);
            // Move to folder
            if (!empty($request_data["folder"])) {

                $save_data = [
                    "id" => $row_id,
                    "account_folder_id" => $request_data["folder"],
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $saved = $model_account_templates->save($save_data);
             
            }
            $output["message"] = "Template moved successfully.";

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * Share template with team
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function share(ServerRequestInterface $request, ResponseInterface $response, $args) {
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
        $model_account_templates = new AccountTemplates();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_templates->checkRowValidity($row_id, $other_values);

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
        $request_data = [
            "can_edit" => false,
            "can_delete" => false,
        ];
        
        if (isset($request_params["teams"])) {
            $request_data["teams"] = (array) $request_params["teams"];
        }
        if (isset($request_params["public"])) {
            $request_data["public"] = (bool) $request_params["public"];
        }
        if (isset($request_params["can_edit"])) {
            $request_data["can_edit"] = (bool) $request_params["can_edit"];
        }
        if (isset($request_params["can_delete"])) {
            $request_data["can_delete"] = (bool) $request_params["can_delete"];
        }

        try {
            // Save data
            $share_access = json_encode(["can_edit" => $request_data["can_edit"], "can_delete" => $request_data["can_delete"]]);

            $save_data = [
                "id" => $row_id,
                "public" => (int) $request_data["public"],
                "share_access" => $share_access,
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($model_account_templates->save($save_data) == false) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

            $model_account_template_teams = new AccountTemplateTeams();

            // Update all teams status to delete
            $condition = [
                "where" => [
                    ["where" => ["account_template_id", "=", $row_id]]
                ]
            ];
            $save_data = [
                "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "modified" => DateTimeComponent::getDateTime()
            ];
            $updated = $model_account_template_teams->update($save_data, $condition);

            foreach ($request_data["teams"] as $team) {
                $team_id = StringComponent::decodeRowId(trim($team));

                if (!empty($team_id)) {
                    try {
                        // Fetch is data already exists
                        $condition = [
                            "fields" => [
                                "att.id"
                            ],
                            "where" => [
                                ["where" => ["att.account_template_id", "=", $row_id]],
                                ["where" => ["att.account_team_id", "=", $team_id]]
                            ]
                        ];
                        $already_exists = $model_account_template_teams->fetch($condition);

                        $save_data = [
                            "account_template_id" => $row_id,
                            "account_team_id" => $team_id,
                            "modified" => DateTimeComponent::getDateTime()
                        ];
                        if (!empty($already_exists["id"])) {
                            $save_data["id"] = $already_exists["id"];
                            $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                        }
                        $saved = $model_account_template_teams->save($save_data);

                    } catch(\Exception $e) { }
                }
            }

            $output["message"] = "Template sharing access updated successfully.";

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * Get template subject and content
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getTemplateData(ServerRequestInterface $request, ResponseInterface $response, $args) {
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
        $model_account_templates = new AccountTemplates();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_templates->checkRowValidity($row_id, $other_values);

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
                    "at.subject",
                    "at.content"
                ],
                "where" => [
                    ["where" => ["at.id", "=", $row_id]]
                ]
            ];
            $row = $model_account_templates->fetch($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Prepare output of data
        $output = [
            "subject" => $row["subject"],
            "content" => $row["content"]
        ];

        return $response->withJson($output, 200);
    }

}
