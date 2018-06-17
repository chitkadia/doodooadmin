<?php
/**
 * Documents related functionality
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
use \App\Models\AccountFolders;
use \App\Models\DocumentTeams;
use \App\Models\DocumentLinks;
use \App\Models\DocumentLinkVisitLogs;

class DocumentsController extends AppController {

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
        $model_document_master = new DocumentMaster();

        try {
            $data = $model_document_master->getListData($query_params, $other_values);
            
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
        $document_share_access = [];
       
        foreach ($data["rows"] as $row) {
            $status = "NA";
            switch($row["status"]) {
                case SHAppComponent::getValue("app_constant/STATUS_INACTIVE") :
                    $status = "STATUS_INACTIVE";
                    break;
                case SHAppComponent::getValue("app_constant/STATUS_ACTIVE") : 
                    $status = "STATUS_ACTIVE";
                    break;
                case SHAppComponent::getValue("app_constant/STATUS_CONV_PENDING") : 
                    $status = "STATUS_CONV_PENDING";
                    break;
                case SHAppComponent::getValue("app_constant/STATUS_IN_PROCESS") : 
                    $status = "STATUS_IN_PROCESS";
                    break;
                case SHAppComponent::getValue("app_constant/STATUS_FAILED_PROCESS") : 
                    $status = "STATUS_FAILED_PROCESS";
                    break;
                default : 
                    $status = "NA";
                    break;
            }

            $shared = false;
            // if (!empty($row["total_teams"]) || $row["public"]) {
            if (!empty($row["total_teams"])) {
                $shared = true;
            }

            // set sharing opertaion option for folder and templates
            $folder_share_access = json_decode($row["folder_share_access"], true);
            $template_share_access = json_decode($row["share_access"], true);

            if ($folder_share_access["can_edit"] == true && $template_share_access["can_edit"] == false) {
                $share_access["can_edit"] = (bool) $folder_share_access["can_edit"];
            } else if ($template_share_access["can_edit"] == true && $folder_share_access["can_edit"] == false) {
                $share_access["can_edit"] =  (bool) $template_share_access["can_edit"];
            } 
            if ($template_share_access["can_edit"] == true) {
                 $share_access["can_edit"] =  (bool) $template_share_access["can_edit"];
            } else {
                $share_access["can_edit"] =  false;  
            }

            if ($folder_share_access["can_delete"] == true && $template_share_access["can_delete"] == false) {
                $share_access["can_delete"] = (bool) $folder_share_access["can_delete"];
            } else if ($template_share_access["can_delete"] == true && $folder_share_access["can_delete"] == false) {
                $share_access["can_delete"] =  (bool) $template_share_access["can_delete"];
            }
            if ($template_share_access["can_delete"] == true) {
                 $share_access["can_delete"] =  (bool) $template_share_access["can_delete"];
            } else {
                $share_access["can_delete"] =  false;  
            }

            // $file_path = "";
            // if(!empty($row["bucket_path"])) {
            //     $bucket_base_path = AWS_BUCKET_BASE_URL . AWS_BUCKET_NAME;
            //     $pathinfo  = pathinfo($row["bucket_path"]);
            //     $file_path = $bucket_base_path . "/" . $pathinfo["dirname"] . "/" . $pathinfo["filename"] . "_0000.jpg";
            // } else {
            //     $file_path = $row["file_path"];
            // }

            if ($row["contact_first_name"] != "") {
                $last_opened_by_contact = $row["contact_first_name"];
            } else {
                $last_opened_by_contact = $row["contact_email"];
            }

            if ($row["contact_status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
                $last_opened_by = $last_opened_by_contact . " (Deleted)";
            } else {
                $last_opened_by = $last_opened_by_contact;
            }

            if ($row["total_visits"] != 0) {
                $average_time_spent = ($row["face_time"]/$row["total_visits"]);
            } else {
                $average_time_spent = 0;
            }

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "created_by" => trim($row["first_name"] . " " . $row["last_name"]),
                "created_by_id" => StringComponent::encodeRowId($row["user_id"]),
                // "file_path" => $file_path,
                "file_type" => $row["file_type"],
                "file_pages" => (int)$row["file_pages"],
                // "source_document_id" => $row["source_document_id"],
                // "source_document_link" => $row["source_document_link"],
                "public" => (bool) $row["public"],
                "shared" => $shared,
                "shared_access" => [
                    "can_edit" => $share_access["can_edit"],
                    "can_delete" => $share_access["can_delete"]
                ],
                "snooze_notifications" => $row["snooze_notifications"],
                "status" => $status,
                "folder_name" => $row["folder_name"],
                "name" => $row["file_name"],
                "facetime" => (int)$row["face_time"],
                "formatted_facetime" => gmdate("H:i:s", $row["face_time"]),
                "last_opened_by" => $last_opened_by,
                "last_opened_date" => DateTimeComponent::convertDateTime($row["last_opened_at"], true, \DEFAULT_TIMEZONE, $user_time_zone, \APP_TIME_FORMAT),
                "formatted_last_open_date" => DateTimeComponent::getDateTimeAgo($row["last_opened_at"], $user_time_zone),
                "total_visit" => (int)$row["total_visits"],
                "average_facetime" => $average_time_spent,
                "formatted_average_facetime" => gmdate("H:i:s", $average_time_spent)
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

        if (isset($request_params["document_source_id"])) {
            $request_data["document_source_id"] = $request_params["document_source_id"];
        }
        if (isset($request_params["file_path"])) {
            $request_data["file_path"] = $request_params["file_path"];
        }
        if (isset($request_params["file_type"])) {
            $request_data["file_type"] = $request_params["file_type"];
        }
        if (isset($request_params["file_pages"])) {
            $request_data["file_pages"] = $request_params["file_pages"];
        }
        if (isset($request_params["source_document_id"])) {
            $request_data["source_document_id"] = $request_params["source_document_id"];
        }
        if (isset($request_params["source_document_link"])) {
            $request_data["source_document_link"] = $request_params["source_document_link"];
        }
        if (isset($request_params["public"])) {
            $request_data["public"] = $request_params["public"];
        }
        if (isset($request_params["snooze_notifications"])) {
            $request_data["snooze_notifications"] = $request_params["snooze_notifications"];
        }
        if (isset($request_params["status"])) {
            $request_data["status"] = $request_params["status"];
        }
        
        $file_type_array = [
            "application/msword",
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "application/vnd.ms-excel",
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "application/vnd.ms-powerpoint",
            "application/vnd.openxmlformats-officedocument.presentationml.presentation",
            "application/pdf"
        ];
        if (!in_array($_FILES["file"]["type"], $file_type_array)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/INVALID_FILE_TYPE", ".xls,.xlsx,.pdf,.ppt,.pptx,.doc,.docx");
        }

        if(!empty($_FILES["file"])) {
            //get the name of file
            $file_name = $_FILES['file']['name'];

            //get the extension of file
            $file_extension = "." .pathinfo($file_name, PATHINFO_EXTENSION);

            //generate unique name of file
            $generated_file_name = strtotime("now") . rand(11111, 99999) . $file_extension;

            //destination path of file
            $destination_file_path = DOCUMENT_UPLOADED_DIRECTORY . DOCUMENT_UPLOADED_SUB_DIRECTORY . $logged_in_user. "/" .$generated_file_name;

            $directory_path = DOCUMENT_UPLOADED_DIRECTORY . DOCUMENT_UPLOADED_SUB_DIRECTORY . $logged_in_user . "/";

            //Make directory if does not exists
            if (!is_dir($directory_path)) {
                mkdir($directory_path, 0777, true);
            }

            //Move file in uploaded directory
            if(move_uploaded_file($_FILES['file']['tmp_name'], $destination_file_path)) {
                   try { 

                        $share_access = json_encode(["can_edit" => false, "can_delete" => false]);
              
                        $document_data = [
                            "user_id" => $logged_in_user,
                            "account_id" => $logged_in_account,
                            "document_source_id" => 1, //upload from client
                            "source_id" => $logged_in_source,
    		                "file_name" => $file_name,
                            "file_path" => DOCUMENT_UPLOADED_SUB_DIRECTORY . $logged_in_user . "/" . $generated_file_name,
                            "bucket_name" => AWS_BUCKET_NAME,
                            "source_document_id" => '',
                            "source_document_link" => '',
                            "file_type" => $file_extension,
                            "share_access" => $share_access,
                            "created" => DateTimeComponent::getDateTime(),
                            "modified" => DateTimeComponent::getDateTime()
                            //"status" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE")
                        ];
                        
                        if (isset($request_params["folder"])) {
                            $document_data["account_folder_id"] = $request_data["folder"];
                        }  

                        $model_document_master = new DocumentMaster();
                       
                        if ($model_document_master->save($document_data) !== false) {
                            $document_id = $model_document_master->getLastInsertId();

                            $output = [
                                "id" => StringComponent::encodeRowId($document_id),
                                "status" => true,
                                "message" => "Document Uploaded successfully"
                            ];
                        } else {
                            // Fetch error code & message and return the response
                            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                        }
                } catch(\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }
            } else {
                $output = [ "status" => false, "message" => "Document Upload Fail"];
            }
        }
        return $response->withJson($output, 201);

        // Get request parameters
        /*$request_params = $request->getParsedBody();

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
        if (isset($request_params["document_source_id"])) {
            $request_data["document_source_id"] = $request_params["document_source_id"];
        }
        if (isset($request_params["file_path"])) {
            $request_data["file_path"] = $request_params["file_path"];
        }
        if (isset($request_params["file_type"])) {
            $request_data["file_type"] = $request_params["file_type"];
        }
        if (isset($request_params["file_pages"])) {
            $request_data["file_pages"] = $request_params["file_pages"];
        }
        if (isset($request_params["source_document_id"])) {
            $request_data["source_document_id"] = $request_params["source_document_id"];
        }
        if (isset($request_params["source_document_link"])) {
            $request_data["source_document_link"] = $request_params["source_document_link"];
        }
        if (isset($request_params["public"])) {
            $request_data["public"] = $request_params["public"];
        }
        if (isset($request_params["snooze_notifications"])) {
            $request_data["snooze_notifications"] = $request_params["snooze_notifications"];
        }
        if (isset($request_params["status"])) {
            $request_data["status"] = $request_params["status"];
        }
        

        // Validate request
        $request_validations = [
            "file_path" => [
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
        $model_document_master = new DocumentMaster();


        try {
            $share_access = json_encode(["can_edit" => false, "can_delete" => false]);

            $save_data = [
                "account_id" => $logged_in_account,
                "user_id" => $logged_in_user,
                "document_source_id" => $request_data["document_source_id"],
                "source_id" => $logged_in_source,
                "file_path" => $request_data["file_path"],
                "file_type" => $request_data["file_type"],
                "file_pages" => $request_data["file_pages"],
                "source_document_id" => $request_data["source_document_id"],
                "source_document_link" => $request_data["source_document_link"],
                "public" => $request_data["public"],
                "share_access" => $share_access,
                "snooze_notifications" => $request_data["snooze_notifications"],
                "status" => $request_data["status"],
                "created" => DateTimeComponent::getDateTime(),
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($model_document_master->save($save_data) !== false) {
                $document_id = $model_document_master->getLastInsertId();

                // Move to folder
                if (!empty($request_data["folder"])) {
                    try {
                        $model_document_folders = new DocumentFolders();

                        $save_data = [
                            "document_id" => $document_id,
                            "account_folder_id" => $request_data["folder"],
                            "modified" => DateTimeComponent::getDateTime()
                        ];
                        $saved = $model_document_folders->save($save_data);

                    } catch(\Exception $e) { }
                }

                $output["id"] = StringComponent::encodeRowId($document_id);
                $output["message"] = "Document created successfully.";

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            echo $e->getMessage(); die;
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 201);*/
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
        $model_document_master = new DocumentMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_document_master->checkRowValidity($row_id, $other_values);

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
        if (isset($request_params["document_source_id"])) {
            $request_data["document_source_id"] = $request_params["document_source_id"];
        }
        if (isset($request_params["file_path"])) {
            $request_data["file_path"] = $request_params["file_path"];
        }
        if (isset($request_params["file_type"])) {
            $request_data["file_type"] = $request_params["file_type"];
        }
        if (isset($request_params["file_pages"])) {
            $request_data["file_pages"] = $request_params["file_pages"];
        }
        if (isset($request_params["source_document_id"])) {
            $request_data["source_document_id"] = $request_params["source_document_id"];
        }
        if (isset($request_params["source_document_link"])) {
            $request_data["source_document_link"] = $request_params["source_document_link"];
        }
        if (isset($request_params["public"])) {
            $request_data["public"] = $request_params["public"];
        }
        if (isset($request_params["snooze_notifications"])) {
            $request_data["snooze_notifications"] = $request_params["snooze_notifications"];
        }
        if (isset($request_params["status"])) {
            $request_data["status"] = $request_params["status"];
        }

        // Validate request
        $request_validations = [
            "file_path" => [
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
            $save_data = [
                "id" => $row_id,
                "document_source_id" => $request_data["document_source_id"],
                "file_path" => $request_data["file_path"],
                "file_type" => $request_data["file_type"],
                "file_pages" => $request_data["file_pages"],
                "source_document_id" => $request_data["source_document_id"],
                "source_document_link" => $request_data["source_document_link"],
                "public" => $request_data["public"],
                "snooze_notifications" => $request_data["snooze_notifications"],
                "modified" => DateTimeComponent::getDateTime()
            ];
            
            if ($model_document_master->save($save_data) !== false) {
                // Save folder
                try {
               
                    // Remove from previous folder
                    $condition = [
                        "where" => [
                            ["where" => ["id", "=", $row_id]]
                        ]
                    ];
                    $save_data = [
                        "account_folder_id" => NULL,
                        "modified" => DateTimeComponent::getDateTime()
                    ];
                    $updated = $model_document_master->update($save_data, $condition);

                    // Move to folder
                    if (!empty($request_data["folder"])) {
                     
                        $save_data = [
                            "id" => $row_id,
                            "account_folder_id" => $request_data["folder"],
                            "modified" => DateTimeComponent::getDateTime()
                        ];
                        $saved = $model_document_master->save($save_data);
                    }

                } catch(\Exception $e) { }

                $output["message"] = "Document updated successfully.";

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
        $model_document_master = new DocumentMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_document_master->checkRowValidity($row_id, $other_values);
           
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
                    "dm.id",
		            "dm.file_name",
                    "dm.file_path",
                    "dm.file_type",
                    "dm.file_pages",
                    "dm.source_document_id",
                    "dm.source_document_link",
                    "dm.public",
                    "dm.share_access",
                    "dm.snooze_notifications",
                    "dm.status",
                    "af.id as folder_id",
                    "af.name as folder_name"
                ],
                "where" => [
                    ["where" => ["dm.id", "=", $row_id]]
                ],
                "join" => [
                    "account_folders"
                ]
            ];
            $row = $model_document_master->fetch($condition);
            
            // Get teams with which document is shared
            $model_document_teams = new DocumentTeams();

            $condition = [
                "fields" => [
                    "at.id",
                    "at.name"
                ],
                "where" => [
                    ["where" => ["dt.document_id", "=", $row_id]],
                    ["where" => ["dt.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]],
                    ["where" => ["at.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ],
                "join" => [
                    "account_teams"
                ]
            ];
            $teams_data = $model_document_teams->fetchAll($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $app_constants = SHAppComponent::getValue("app_constant");
        $default_timezone = \DEFAULT_TIMEZONE;

        $status = array_search($row["status"], $app_constants);
        
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
	        "file_name" => $row["file_name"],
            "file_path" => $row["file_path"],
            "file_type" => $row["file_type"],
            "file_pages" => $row["file_pages"],
            "status" => $status,
            "public" => (bool) $row["public"],
            "snooze_notifications" => (bool) $row["snooze_notifications"],
            "shared_access" => [
                "can_edit" => $share_access["can_edit"],
                "can_delete" => $share_access["can_delete"]
            ],
            // "folder_id" => $folder_id,
            // "folder_name" => $folder_name,
            "folder_id" => StringComponent::encodeRowId($row["folder_id"]),
            "folder_name" =>$row["folder_name"],
            "teams" => $teams_list
        ];

        return $response->withJson($output, 200);
    }

    // Commented unused code.
//  /**
//      * Copy single record
//      *
//      * @param $request (object): Request object
//      * @param $response (object): Response object
//      * @param $args (array): Route parameters
//      *
//      * @return (object) Response object
//      */
//     public function copy(ServerRequestInterface $request, ResponseInterface $response, $args) {
//         $output = [];

//         // Get logged in user details
//         $logged_in_user = SHAppComponent::getUserId();
//         $logged_in_account = SHAppComponent::getAccountId();
//         $logged_in_source = SHAppComponent::getRequestSource();
//         $is_owner = SHAppComponent::isAccountOwner();

//         // Get request parameters
//         $route = $request->getAttribute("route");
//         $id = $route->getArgument("id");
//         $folder_id = $route->getArgument("folder_id");

//         // Validate request
//         if (empty($id)) {
//             // Fetch error code & message and return the response
//             return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
//         }

//         $row_id = StringComponent::decodeRowId($id);
//         $folder_id = StringComponent::decodeRowId($folder_id);

//         // Check if record is valid
//         $model_document_master = new DocumentMaster();

//         try {
//             // Other values for condition
//             $other_values = [
//                 "user_id" => $logged_in_user,
//                 "account_id" => $logged_in_account,
//                 "is_owner" => $is_owner,
//                 "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
//             ];

//             $valid = $model_document_master->checkRowValidity($row_id, $other_values);

//             if (!$valid) {
//                 // Fetch error code & message and return the response
//                 return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
//             }

//         } catch(\Exception $e) {
//             // Fetch error code & message and return the response
//             return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
//         }

//         // Copy record
//         try {
//             // Fetch data
//             $condition = [
//                 "fields" => [
//                     "dm.document_source_id",
//                     "dm.file_path",
//                     "dm.file_type",
//                     "dm.file_pages",
//                     "dm.source_document_id",
//                     "dm.source_document_link",
//                     "dm.public",
//                     "dm.snooze_notifications",
//                     "dm.status"
//                 ],
//                 "where" => [
//                     ["where" => ["dm.id", "=", $row_id]]
//                 ]
//             ];
//             $row = $model_document_master->fetch($condition);

//             // Save data
//             $share_access = json_encode(["can_edit" => false, "can_delete" => false]);

//             $save_data = [
//                 "account_id" => $logged_in_account,
//                 "user_id" => $logged_in_user,
//                 "source_id" => $logged_in_source,
//                 "document_source_id" => $row["document_source_id"],
//                 "file_path" => $row["file_path"],
//                 "file_type" => $row["file_type"],
//                 "file_pages" => $row["file_pages"],
//                 "source_document_id" => $row["source_document_id"],
//                 "source_document_link" => $row["source_document_link"],
//                 "public" => $row["public"],
//                 "snooze_notifications" => $row["snooze_notifications"],
//                 "status" => $row["status"],
//                 "share_access" => $share_access,
//                 "created" => DateTimeComponent::getDateTime(),
//                 "modified" => DateTimeComponent::getDateTime()
//             ];

//             if ($model_document_master->save($save_data) !== false) {
//                 $document_id = $model_document_master->getLastInsertId();

//                 if ($folder_id != "") {

//                     // Move to folder
//                     $model_document_folders = new DocumentFolders();

//                     $condition = [
//                         "fields" => [
//                             "df.id"
//                         ],
//                         "where" => [
//                             ["where" => ["df.document_id", "=", $row_id]],
//                             ["where" => ["df.account_folder_id", "=", $folder_id]]
//                         ]
//                     ];
//                     $row = $model_document_folders->fetch($condition);
//                     if (!empty($row["id"])) {
//                         try {
//                             $save_data = [
//                                 "document_id" => $document_id,
//                                 "account_folder_id" => $folder_id,
//                                 "modified" => DateTimeComponent::getDateTime()
//                             ];
//                             $saved = $model_document_folders->save($save_data);

//                         } catch(\Exception $e) { }
//                     }
//                 }

//                 $output["id"] = StringComponent::encodeRowId($document_id);
//                 $output["message"] = "Document copied successfully.";

//             } else {
//                 // Fetch error code & message and return the response
//                 return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
//             }

//         } catch(\Exception $e) {
//             // Fetch error code & message and return the response
//             return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
//         }

//         return $response->withJson($output, 201);
//     }

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
        $model_document_master = new DocumentMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_document_master->checkRowValidity($row_id, $other_values);

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
            if ($model_document_master->save($save_data) !== false) {
                $output["message"] = "Document deleted successfully.";

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
        $model_document_master = new DocumentMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_document_master->checkRowValidity($row_id, $other_values);

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
            if ($model_document_master->save($save_data) !== false) {
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
     * Move document to folder
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
        $model_document_master = new DocumentMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_document_master->checkRowValidity($row_id, $other_values);
           
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
          
            // Remove from previous folder
            $conditions = [
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];
           
            $save_data = [
                "account_folder_id" => NULL,
                "modified" => DateTimeComponent::getDateTime()
            ];
            $updated = $model_document_master->update($save_data, $conditions);

            // Move to folder
            if (!empty($request_data["folder"])) {

                $save_data = [
                    "id" => $row_id,
                    "account_folder_id" => $request_data["folder"],
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $saved = $model_document_master->save($save_data);
             
            }
         
            $output["message"] = "Document moved successfully.";

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * Rename existing record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function rename(ServerRequestInterface $request, ResponseInterface $response, $args) {
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
        $model_document_master = new DocumentMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_document_master->checkRowValidity($row_id, $other_values);

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
      
         if (isset($request_params["file_name"])) {
            $request_data["file_name"] = $request_params["file_name"];
        }
        // Validate request
        $request_validations = [
            "file_name" => [
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

            $conditions = [
                "fields" => [
                    "dm.file_type"
                ],
                "where" => [
                    ["where" => ["dm.id", "=", $row_id]],
                    ["where" => ["dm.status", "<>",SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            
            $data = $model_document_master->fetch($conditions);
            //$request_data["file_name"] = $request_data["file_name"].$data["file_type"];
            $save_data = [
                "id" => $row_id,
                "file_name" => $request_data["file_name"],
                "modified" => DateTimeComponent::getDateTime()
            ];
            
            if ($model_document_master->save($save_data) !== false) {
                $output["message"] = "Document renamed successfully.";

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
     * Share document with team
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
        $model_document_master = new DocumentMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_document_master->checkRowValidity($row_id, $other_values);
           
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
          
            if ($model_document_master->save($save_data) == false) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

            $model_document_teams = new DocumentTeams();

            // Update all teams status to delete
            $condition = [
                "where" => [
                    ["where" => ["document_id", "=", $row_id]]
                ]
            ];
            $save_data = [
                "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "modified" => DateTimeComponent::getDateTime()
            ];
            $updated = $model_document_teams->update($save_data, $condition);

            foreach ($request_data["teams"] as $team) {
                $team_id = StringComponent::decodeRowId(trim($team));

                if (!empty($team_id)) {
                    try {
                        // Fetch is data already exists
                        $condition = [
                            "fields" => [
                                "dt.id"
                            ],
                            "where" => [
                                ["where" => ["dt.document_id", "=", $row_id]],
                                ["where" => ["dt.account_team_id", "=", $team_id]]
                            ]
                        ];
                        $already_exists = $model_document_teams->fetch($condition);

                        $save_data = [
                            "document_id" => $row_id,
                            "account_team_id" => $team_id,
                            "modified" => DateTimeComponent::getDateTime()
                        ];
                        if (!empty($already_exists["id"])) {
                            $save_data["id"] = $already_exists["id"];
                            $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                        }
                        $saved = $model_document_teams->save($save_data);

                    } catch(\Exception $e) { }
                }
            }

            $output["message"] = "Document sharing access updated successfully.";

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * Get links details of perticular document
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getDocLinks(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $user_time_zone = SHAppComponent::getUserTimeZone();

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
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $doc_links_data = $model_document_links->checkRowAvailable($row_id, $other_values);
            
            if (!$doc_links_data) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        try {
            $doc_data_file_name = $model_document_links->getFileDetails($row_id, $other_values);
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $doc_links_data = $model_document_links->getDocLinkData($row_id, $other_values);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $app_constants = SHAppComponent::getValue("app_constant");

        $view_link = SHAppComponent::getValue("app_constant/DOC_VIEWER_VIEW");
        $preview_link = SHAppComponent::getValue("app_constant/DOC_VIEWER_PREVIEW");
        $doc_preview_link = SHAppComponent::getValue("app_constant/DOC_PREVIEW");

        $document_id_for_preview = StringComponent::encodeRowId($doc_data_file_name["id"]);
       
        $output["preview_link"] = \DOC_VIEWER_BASE_URL . "/" . $doc_preview_link . "/" . $document_id_for_preview;
        $output["file_name"] = $doc_data_file_name["file_name"];

        $output["rows"] = [];
        
        if (!empty($doc_links_data)) {
            foreach($doc_links_data as $link_data) {
                if ($link_data["status"] == 0) {
                    $status = "STATUS_INACTIVE";
                } else if ($link_data["status"] == 1) {
                    $status = "STATUS_ACTIVE";
                } else if ($link_data["status"] == 2) {
                    $status = "STATUS_DELETE";
                } else {
                    $status = "STATUS_BLOCKED";
                }
                
                if ($link_data["type"] == 1) {
                    $type = "DOCUMENT_SPACE";
                } else if ($link_data["type"] == 2) {
                    $type = "CUSTOM_DOCUMENT_SPACE";
                } else if ($link_data["type"] == 3) {
                    $type = "DOCUMENT_SPACE_EMAIL";
                } else {
                    $type = "DOCUMENT_SPACE_CAMPAIGN";
                }
                if ($link_data["user_first_name"] != "") {
                    $created_by = $link_data["user_first_name"];
                } else {
                    $created_by = $link_data["user_email"];
                }
                if ($link_data["contact_first_name"] != "") {
                    $opened_by_contact = $link_data["contact_first_name"];
                } else {
                    $opened_by_contact = $link_data["contact_email"];
                }

                if ($link_data["contact_status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
                    $opened_by = $opened_by_contact . " (Deleted)";
                } else {
                    $opened_by = $opened_by_contact;
                }

                $row_data_link_data = [
                    "link_id" => StringComponent::encodeRowId($link_data["id"]),
                    "link_name" => $link_data["name"],
                    "document_domain" => $link_data["link_domain"],
                    "document_link_code" => $link_data["link_code"],
                    "document_view_link" => $link_data["link_domain"]."/".$view_link."/".$link_data["link_code"],
                    "document_preview_link" => $link_data["link_domain"]."/".$preview_link."/".$link_data["link_code"],
                    "status" => $status,
                    "opened_by" => $opened_by,
                    "type" => $type,
                    "formatted_last_open" => DateTimeComponent::getDateTimeAgo($link_data["last_opened_at"], $user_time_zone),
                    "modified" => $link_data["last_opened_at"],
                    "total_visit" => $link_data["total_visit"],
                    "total_time_spent" => (int)$link_data["total_time_spent"],
                    "formatted_total_time_spent" => gmdate("H:i:s", $link_data["total_time_spent"]),
                    "created_by" => $created_by
                ];
                $output["rows"][] = $row_data_link_data;
            }
        }

        // Prepare output of data

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
    public function getDocPerformance(ServerRequestInterface $request, ResponseInterface $response, $args) {
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

        $model_document_logs = new DocumentLinkVisitLogs();

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
                // "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_document_logs->checkDocAvailability($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Other values for condition
        $other_values_list = [
            "doc_id" => $row_id,
            "account_id" => $logged_in_account,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        try {
            $data = $model_document_logs->getListData($query_params, $other_values_list);
            
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $other_value_visits = [
            "doc_id" => $row_id,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
            "account_id" => $logged_in_account
        ];
        try {
            $row_data = $model_document_logs->getDocumentTotalVisit($query_params, $other_value_visits);

        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $output["total_time_spent"] = (int)$data["total_time_spent"];
        $output["file_pages"] = $data["file_pages"];
        $output["total_visit"] = $data["total_visit"];
        $output["total_download"] = (int)$data["total_download"];
        $output["file_name"] = $data["file_name"];
        $output["total_viewed_page"] = $data["total_viewed_page"];

        // Process data and prepare for output
        $output["total_records"] = $row_data["total_records"];
        $output["total_pages"] = $row_data["total_pages"];
        $output["current_page"] = $row_data["current_page"];
        $output["per_page"] = $row_data["per_page"];
        $output["rows"] = [];

        foreach ($row_data["rows"] as $row) {
            if ($row["contact_first_name"] != "") {
                $opened_by_contact = $row["contact_first_name"];
            } else {
                $opened_by_contact = $row["contact_email"];
            }

            if ($row["contact_status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
                $opened_by = $opened_by_contact . " (Deleted)";
            } else {
                $opened_by = $opened_by_contact;
            }

            if ($row["type"] == 1) {
                $type = "DOCUMENT_SPACE";
            } else if ($row["type"] == 2) {
                $type = "CUSTOM_DOCUMENT_SPACE";
            } else if ($row["type"] == 3) {
                $type = "DOCUMENT_SPACE_EMAIL";
            } else {
                $type = "DOCUMENT_SPACE_CAMPAIGN";
            }

            $link_name_to_show = $row["link_name"];
            if ($row["link_name"] == "") {
                $link_name_to_show = "Direct link to " . $row["direct_link_to"];
            }

            $row_data_foreach = [
                "visit_id" => StringComponent::encodeRowId($row["id"]),
                "opened_by" => $opened_by,
                "link_name" => $link_name_to_show,
                "type" => $type,
                "time_spent" => gmdate("H:i:s", $row["time_spent"]),
                "location" => json_decode($row["location"]),
                // "total_pages" => $row["total_file_pages"],
                "link_last_visited" => $row["visited_at"],
                "formatted_link_last_visited" => DateTimeComponent::getDateTimeAgo($row["visited_at"], $user_time_zone),
                "total_viewed_pages" => $row["total_viewed_page"]
            ];

            $output["rows"][] = $row_data_foreach;
        }

        return $response->withJson($output, 200);
    }

    /**
     * Get document visit details
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getDocVisit(ServerRequestInterface $request, ResponseInterface $response, $args) {
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
        $doc_id_param = $route->getArgument("doc_id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);
        $doc_id = StringComponent::decodeRowId($doc_id_param);

        $model_document_logs = new DocumentLinkVisitLogs();

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
            "visit_id" => $row_id,
            "doc_id" => $doc_id
        ];

        try {
            $file_data = $model_document_logs->getDocData($doc_id);
            
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        try {
            $overall_data = $model_document_logs->getOverallVisitDetails($other_values);
            
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        try {
            $data = $model_document_logs->getPagewiseDetails($other_values);
            
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if ($overall_data["account_contact_id"] != null) {
            $other_values_visit = [
                "visit_id" => $row_id,
                "doc_id" => $doc_id,
                "account_contact_id" => $overall_data["account_contact_id"],
                "account_id" => $logged_in_account
            ];

            try {
                $row_data = $model_document_logs->getDocumentVisitDetails($query_params, $other_values_visit);
            } catch (\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
        } else {
            $row_data["total_records"] = 0;
            $row_data["total_pages"] = 1;
            $row_data["current_page"] = $query_params["page"];
            $row_data["per_page"] = $query_params["per_page"];
            $output["rows"] = [];
        }

        // foreach($data as $page_array) {
        //     $page_data = [
        //         "page_num" => $page_array["page_num"],
        //         "total_time_spent" => (int)$page_array["total_time_spent"]
        //     ];
        //     $output["page_wise_data"] = $page_data;
        // }

        $output["page_wise_data"] = $data;

        $output["total_sum_time_spent"] = (int)$overall_data["total_time_spent"];
        $output["total_download"] = (int)$overall_data["download"];
        
        if ($overall_data["contact_first_name"] != "") {
            if ($overall_data["contact_status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
                $output["visiter_name"] = $overall_data["contact_first_name"] . " (Deleted)";
            } else {
                if ($overall_data["contact_status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
                    $output["visiter_name"] = $overall_data["contact_first_name"] . " (Deleted)";
                } else {
                    $output["visiter_name"] = $overall_data["contact_first_name"];
                }
            }
        } else {
            $output["visiter_name"] = $overall_data["contact_email"];
        }
        $output["total_file_pages"] = (int)$file_data["total_file_pages"];
        $output["file_name"] = $file_data["file_name"];
        $output["visit_date"] = DateTimeComponent::convertDateTime($overall_data["modified"], true, \DEFAULT_TIMEZONE, $user_time_zone, \APP_TIME_FORMAT);
        $output["total_viewed_pages"] = $overall_data["total_viewed_pages"];
        $output["account_contact_id"] = StringComponent::encodeRowId($overall_data["account_contact_id"]);
        $output["total_visit"] = $overall_data["total_visit"];
        
        // Process data and prepare for output
        $output["total_records"] = $row_data["total_records"];
        $output["total_pages"] = $row_data["total_pages"];
        $output["current_page"] = $row_data["current_page"];
        $output["per_page"] = $row_data["per_page"];
        $output["rows"] = [];

        if ($overall_data["account_contact_id"] != null) {
            foreach ($row_data["rows"] as $row) {
                if ($row["contact_first_name"] != "") {
                    $visiter_name_visit_contact = $row["contact_first_name"];
                } else {
                    $visiter_name_visit_contact = $row["contact_email"];
                }

                if ($row["contact_status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
                    $visiter_name_visit = $visiter_name_visit_contact . " (Deleted)";
                } else {
                    $visiter_name_visit = $visiter_name_visit_contact;
                }

                $link_to_show = $row["link_name"];
                if ($row["link_name"] == "") {
                    $link_to_show = "Direct link to " . $row["direct_link_to"];
                }

                $row_data_foreach = [
                    "visit_id" => StringComponent::encodeRowId($row["visit_id"]),
                    "visiter_name_visit" => $visiter_name_visit,
                    "link_name" => $link_to_show,
                    "location" => json_decode($row["location"]),
                    "time_spent" => (int)$row["total_time_spent"],
                    "formatted_time_spent" => gmdate("H:i:s", (int)$row["total_time_spent"]),
                    "visited_at" => $row["modified"],
                    "formatted_link_last_visited" => DateTimeComponent::getDateTimeAgo($row["modified"], $user_time_zone),
                    "total_page_viewed" => $row["total_viewed_pages"]
                ];

                $output["rows"][] = $row_data_foreach;
            }
        }

        return $response->withJson($output, 200);
    }

}
