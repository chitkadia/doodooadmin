<?php
/**
 * Folders related functionality (Documents)
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
use \App\Models\AccountFolders;
use \App\Models\AccountFolderTeams;
use \App\Models\DocumentFolders;
use \App\Models\DocumentMaster;

class DocsFoldersController extends AppController {

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
        if (!empty($params["user"])) {
            $user_id = StringComponent::decodeRowId(trim($params["user"]));

            if (!empty($user_id)) {
                $query_params["user"] = $user_id;
            }
        }
        if (!empty($params["query"])) {
            $query_params["query"] = SHAppComponent::prepareSearchText($params["query"]);
        }
        $query_params["type"] = SHAppComponent::getValue("app_constant/FOLDER_TYPE_DOCS");

        // Other values for condition
        $other_values = [
            "user_id" => $logged_in_user,
            "account_id" => $logged_in_account,
            "is_owner" => $is_owner,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        // Get data
        $model_account_folders = new AccountFolders();

        try {
            $data = $model_account_folders->getListDataDocuments($query_params, $other_values);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Process data and prepare for output
        $output["total_records"] = $data["total_records"];
        $output["total_pages"] = $data["total_pages"];
        $output["current_page"] = $data["current_page"];
        $output["per_page"] = $data["per_page"];
        $output["rows"] = [
            "my_folders" => [],
            "shared_folders" => []
        ];

        foreach ($data["rows"] as $row) {
            $key = "shared_folders";
            $shared = false;
            if (!empty($row["total_teams"]) || $row["public"]) {
                $shared = true;
            }

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

            // If own folder
            if ($row["user_id"] == $logged_in_user) {
                $key = "my_folders";
            }

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "name" => $row["name"],
                "created_by" => trim($row["first_name"] . " " . $row["last_name"]),
                "created_by_id" => StringComponent::encodeRowId($row["user_id"]),
                "public" => (bool) $row["public"],
                "shared" => $shared,
                "shared_access" => [
                    "can_edit" => $share_access["can_edit"],
                    "can_delete" => $share_access["can_delete"]
                ],
                "total_documents" => $row["total_documents"]
            ];

            $output["rows"][$key][] = $row_data;
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
        $model_account_folders = new AccountFolders();

        try {
            $share_access = json_encode(["can_edit" => false, "can_delete" => false]);

            $save_data = [
                "account_id" => $logged_in_account,
                "user_id" => $logged_in_user,
                "source_id" => $logged_in_source,
                "name" => trim($request_data["name"]),
                "type" => SHAppComponent::getValue("app_constant/FOLDER_TYPE_DOCS"),
                "share_access" => $share_access,
                "created" => DateTimeComponent::getDateTime(),
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($model_account_folders->save($save_data) !== false) {
                $folder_id = $model_account_folders->getLastInsertId();

                $output["id"] = StringComponent::encodeRowId($folder_id);
                $output["message"] = "Folder created successfully.";

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
        $model_account_folders = new AccountFolders();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_folders->checkRowValidity($row_id, $other_values);

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

        if (isset($request_params["name"])) {
            $request_data["name"] = $request_params["name"];
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
        try {
            $save_data = [
                "id" => $row_id,
                "name" => trim($request_data["name"]),
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($model_account_folders->save($save_data) !== false) {
                $output["message"] = "Folder renamed successfully.";

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
        $model_account_folders = new AccountFolders();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_folders->checkRowValidity($row_id, $other_values);
          
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
                    "af.id",
                    "af.name",
                    "af.public",
                    "af.share_access",
                    "af.user_id"
                ],
                "where" => [
                    ["where" => ["af.id", "=", $row_id]]
                ]
            ];            
            $row = $model_account_folders->fetch($condition);
           
            $shared_with_teams = [];
            $folder_documents = [];
            $shared = false;

            // Fetch to which teams folder is shared
            if ($row["public"] == SHAppComponent::getValue("app_constant/FLAG_NO")) {
                $model_account_folder_teams = new AccountFolderTeams();

                $condition = [
                    "fields" => [
                        "at.id",
                        "at.name"
                    ],
                    "where" => [
                        ["where" => ["aft.account_folder_id", "=", $row_id]],
                        ["where" => ["aft.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]],
                        ["where" => ["at.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                    ],
                    "join" => [
                        "account_teams"
                    ]
                ]; 
                $shared_list = $model_account_folder_teams->fetchAll($condition);
             
                foreach ($shared_list as $team) {
                    $shared_with_teams[] = [
                        "id" => StringComponent::encodeRowId($team["id"]),
                        "name" => $team["name"]
                    ];
                }
               
                if (!empty($shared_with_teams)) {
                    $shared = true;
                }
            }
            
            // Fetch documents of the folder
            $model_document_master = new DocumentMaster();

            $condition = [
                "fields" => [
                    "dm.id",
                    "dm.file_path"
                ],
                "where" => [
                    ["where" => ["dm.account_folder_id", "=", $row_id]],
                    ["where" => ["dm.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            
            $documents_list = $model_document_master->fetchAll($condition);
      
            foreach ($documents_list as $document) {
                $folder_documents[] = [
                    "id" => StringComponent::encodeRowId($document["id"]),
                    "name" => $document["file_path"]
                ];
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

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

        $output = [
            "id" => StringComponent::encodeRowId($row["id"]),
            "name" => $row["name"],
            "public" => (bool) $row["public"],
            "shared" => $shared,
            "shared_access" => [
                "can_edit" => $share_access["can_edit"],
                "can_delete" => $share_access["can_delete"]
            ],
            "shared_with_teams" => $shared_with_teams,
            "documents" => $folder_documents
        ];

        return $response->withJson($output, 200);
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
        $model_account_folders = new AccountFolders();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_folders->checkRowValidity($row_id, $other_values);

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
            // Delete documents
           
            $model_document_master = new DocumentMaster();
            $save_data = [
                "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "modified" => DateTimeComponent::getDateTime()
            ];
            $condition = [
                "where" => [
                    ["where" => ["account_folder_id", "=", $row_id]],
                    ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
           
            if($model_document_master->update($save_data, $condition) !== false)
            {
                try {
                    // Delete document folder
                    $save_data = [
                        "id" => $row_id,
                        "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                        "modified" => DateTimeComponent::getDateTime()
                    ];
             
                    $model_account_folders->save($save_data);

                } catch(\Exception $e) { 
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

                $output["message"] = "Folder deleted successfully.";

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
     * Share folder with team
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
        $model_account_folders = new AccountFolders();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "is_owner" => $is_owner,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_folders->checkRowValidity($row_id, $other_values);

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

            if ($model_account_folders->save($save_data) == false) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

            $model_account_folder_teams = new AccountFolderTeams();

            // Update all teams status to delete
            $condition = [
                "where" => [
                    ["where" => ["account_folder_id", "=", $row_id]]
                ]
            ];
            $save_data = [
                "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "modified" => DateTimeComponent::getDateTime()
            ];
            $updated = $model_account_folder_teams->update($save_data, $condition);

            foreach ($request_data["teams"] as $team) {
                $team_id = StringComponent::decodeRowId(trim($team));

                if (!empty($team_id)) {
                    try {
                        // Fetch is data already exists
                        $condition = [
                            "fields" => [
                                "aft.id"
                            ],
                            "where" => [
                                ["where" => ["aft.account_folder_id", "=", $row_id]],
                                ["where" => ["aft.account_team_id", "=", $team_id]]
                            ]
                        ];
                        $already_exists = $model_account_folder_teams->fetch($condition);

                        $save_data = [
                            "account_folder_id" => $row_id,
                            "account_team_id" => $team_id,
                            "modified" => DateTimeComponent::getDateTime()
                        ];
                        if (!empty($already_exists["id"])) {
                            $save_data["id"] = $already_exists["id"];
                            $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                        }
                        $saved = $model_account_folder_teams->save($save_data);

                    } catch(\Exception $e) { }
                }
            }

            $output["message"] = "Folder sharing access updated successfully.";

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

}
