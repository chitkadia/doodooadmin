<?php
/**
 * Roles related functionality
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
use \App\Models\RoleMaster;
use \App\Models\RoleDefaultResources;
use \App\Models\ResourceMaster;
use \App\Models\UserMaster;
use \App\Models\UserResources;
use \App\Models\UserAuthenticationTokens;

class RolesController extends AppController {

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
        $logged_in_source = SHAppComponent::getRequestSource();

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
            if (is_numeric($params["page"])) {
                $query_params["page"] = (int) $params["page"];
            }
        }
        if (!empty($params["per_page"])) {
            if (is_numeric($params["per_page"])) {
                $query_params["per_page"] = (int) $params["per_page"];
            }
        }
        if (!empty($params["order_by"])) {
            $query_params["order_by"] = trim($params["order_by"]);
        }
        if (!empty($params["order"])) {
            $query_params["order"] = (trim($params["order"]) == "DESC") ? "DESC" : "ASC";
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

        // Other values for condition
        $other_values = [
            "user_id" => $logged_in_user,
            "account_id" => $logged_in_account,
            "is_owner" => $is_owner,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        // Get data
        $model_role_master = new RoleMaster();

        try {
            $data = $model_role_master->getListData($query_params, $other_values);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Process data and prepare for output
        $output["total_records"] = $data["total_records"];
        $output["total_pages"] = $data["total_pages"];
        $output["current_page"] = $data["current_page"];
        $output["per_page"] = $data["per_page"];
        $output["rows"] = [];

        foreach ($data["rows"] as $row) {
            $created_by = null;
            $created_by_id = null;

            // If not system based, then
            if (!$row["is_system"]) {
                $created_by = (($row["id"] == $logged_in_user) ? "Me" : trim($row["first_name"] . " " . $row["last_name"]));
                $created_by_id = StringComponent::encodeRowId($row["user_id"]);
            }

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "name" => $row["name"],
                "created_by" => $created_by,
                "created_by_id" => $created_by_id,
                "total_users" => $row["total_users"],
                "is_system" => (bool) $row["is_system"]
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
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [
            "short_info" => "",
            "resources" => []
        ];
        
        if (isset($request_params["name"])) {
            $request_data["name"] = $request_params["name"];
        }
        if (isset($request_params["short_info"])) {
            $request_data["short_info"] = $request_params["short_info"];
        }
        if (isset($request_params["resources"])) {
            $request_data["resources"] = $request_params["resources"];
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
        $model_role_master = new RoleMaster();

        try {
            $flag_no = SHAppComponent::getValue("app_constant/FLAG_NO");
            $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");

            $save_data = [
                "account_id" => $logged_in_account,
                "user_id" => $logged_in_user,
                "source_id" => $logged_in_source,
                "name" => trim($request_data["name"]),
                "code" => trim($request_data["name"]),
                "short_info" => trim($request_data["short_info"]),
                "is_system" => $flag_no,
                "for_customers" => $flag_yes,
                "created" => DateTimeComponent::getDateTime(),
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($model_role_master->save($save_data) !== false) {
                $inserted_role_id = $model_role_master->getLastInsertId();

                // Add resources
                $model_role_default_resources = new RoleDefaultResources();
                
                foreach ($request_data["resources"] as $resource) {
                    $resource_id = StringComponent::decodeRowId(trim($resource));

                    if (!empty($resource_id)) {
                        try {
                            $save_data = [
                                "role_id" => $inserted_role_id,
                                "resource_id" => $resource_id,
                                "modified" => DateTimeComponent::getDateTime()
                            ];
                            $saved = $model_role_default_resources->save($save_data);

                        } catch(\Exception $e) { }
                    }
                }

                $output["id"] = StringComponent::encodeRowId($inserted_role_id);
                $output["message"] = "Role created successfully.";

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
        $model_role_master = new RoleMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_role_master->checkRowValidity($row_id, $other_values);

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

        // To store request resource id
        $request_res_array = array();

        // To sotre all resource id which we have to insert
        $inserted_resource_ids = array();

        // To sotre all resource id which we have to delete
        $deleted_resource_ids = array();

        // Fetch request data
        $request_data = [
            "short_info" => "",
            "resources" => []
        ];
        
        if (isset($request_params["name"])) {
            $request_data["name"] = $request_params["name"];
        }
        if (isset($request_params["short_info"])) {
            $request_data["short_info"] = $request_params["short_info"];
        }
        if (isset($request_params["resources"])) {
            $request_data["resources"] = $request_params["resources"];

            foreach ($request_data["resources"] as $list_res) {
                array_push($request_res_array, StringComponent::decodeRowId($list_res)); 
            }
        }
        if (isset($request_params["update_members"])) {
            $request_data["update_members"] = $request_params["update_members"];
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
                "short_info" => trim($request_data["short_info"]),
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($model_role_master->save($save_data) !== false) {

                // Add resources
                $model_role_default_resources = new RoleDefaultResources();

                // To get all active resources
                $condition = [
                    "fields" => [
                        "resource_id"
                    ],
                    "where" => [
                        ["where" => ["role_id","=",$row_id]],
                        ["where" => ["status","=",SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]],
                    ]
                ];

                $list_all_active_res =  $model_role_default_resources->fetchAll($condition);
                $orignal_res_array = array();

                if(!empty($list_all_active_res)) {
                    foreach ($list_all_active_res as $key => $value) {
                        array_push($orignal_res_array, $value["resource_id"]);
                    }
                }

                // To get deleted Resource Id
                $deleted_resource_ids = array_diff($orignal_res_array,$request_res_array);

                // To get new inserted Resource Id
                $inserted_resource_ids = array_diff($request_res_array,$orignal_res_array);
            
                // Update all members status to delete
                $condition = [
                    "where" => [
                        ["where" => ["role_id", "=", $row_id]]
                    ]
                ];
                $save_data = [
                    "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $model_role_default_resources->update($save_data, $condition);

                foreach ($request_data["resources"] as $resource) {
                    $resource_id = StringComponent::decodeRowId(trim($resource));

                    if (!empty($resource_id)) {
                        try {
                            // Fetch is data already exists
                            $condition = [
                                "fields" => [
                                    "rds.id"
                                ],
                                "where" => [
                                    ["where" => ["rds.role_id", "=", $row_id]],
                                    ["where" => ["rds.resource_id", "=", $resource_id]]
                                ]
                            ];
                            $already_exists = $model_role_default_resources->fetch($condition);

                            $save_data = [
                                "role_id" => $row_id,
                                "resource_id" => $resource_id,
                                "modified" => DateTimeComponent::getDateTime()
                            ];
                            if (!empty($already_exists["id"])) {
                                $save_data["id"] = $already_exists["id"];
                                $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                            }
                            $saved = $model_role_default_resources->save($save_data);

                        } catch(\Exception $e) { }
                    }
                }

               
                // Update all member's resource which belong to specific role
                if ((!empty($deleted_resource_ids) || !empty($inserted_resource_ids)) && $request_data["update_members"] == 1) {

                    $model_user_master = new UserMaster();

                    $condition = [
                        "fields" => [
                            "id",
                            "role_id",
                        ],
                        "where" => [
                            ["where" => ["role_id","=",$row_id]],
                            ["where" => ["status","<>",SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                        ]    
                    ];

                    // Get all member list form given role id
                    $member_ids_list = $model_user_master->fetchAll($condition);


                    if (!empty($member_ids_list)) {

                        $model_user_resources = new UserResources();

                        try {
                            foreach ($member_ids_list as $list) {
                                
                                // Delete member resource for given role
                                if (!empty($deleted_resource_ids)) {

                                    foreach ($deleted_resource_ids as $list_delete) {

                                        $condition = [
                                            "where" => [
                                                ["where" => ["user_id", "=", $list["id"]]],
                                                ["where" => ["resource_id", "=", $list_delete]],
                                            ]
                                        ];
                                        $save_data = [
                                            "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                                            "modified" => DateTimeComponent::getDateTime()
                                        ];
                                        $model_user_resources->update($save_data, $condition);
                                    }
                                }

                                // Update/Insert member resource for given role
                                if (!empty($inserted_resource_ids)) {

                                    foreach ($inserted_resource_ids as $list_insert) {

                                        // Check if user resource record exist or not
                                        $condition = [
                                            "fields" => [
                                                "id"
                                            ],
                                            "where" => [
                                                ["where" => ["user_id","=",$list["id"]]],
                                                ["where" => ["resource_id","=",$list_insert]],
                                            ]
                                        ];

                                        $resource_user_data = $model_user_resources->fetch($condition);

                                        // Update existing resource or Insert new resource
                                        if (!empty($resource_user_data)) {

                                            $save_data = [
                                                "id" => $resource_user_data['id'],
                                                "user_id" => $list["id"],
                                                "resource_id" => $list_insert,
                                                "status" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                                                "modified" => DateTimeComponent::getDateTime()
                                            ];

                                            $model_user_resources->save($save_data);

                                        } else {

                                            $save_data = [
                                                "user_id" => $list["id"],
                                                "role_id" => $row_id,
                                                "resource_id" => $list_insert,
                                                "status" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                                                "modified" => DateTimeComponent::getDateTime()
                                            ];

                                            $model_user_resources->save($save_data);
                                        }    
                                    }
                                }
                            } 

                        // Update member auth token if it is active    
                        $model_user_auth_token = new UserAuthenticationTokens();
                        $current_timespan = DateTimeComponent::getDateTime();
                        $update_member_token = $model_user_auth_token->memberTokenExpire($member_ids_list,$logged_in_account,$current_timespan);

                        } catch(\Exception $e) { }
                    }
                }

                $output["message"] = "Role updated successfully.";

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
        $model_role_master = new RoleMaster();

        // Model object of usermaster
        $model_user_master = new UserMaster();

        // Role member's list
        $member_lists = [];

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_role_master->checkRowValidity($row_id, $other_values);

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
                    "um.first_name",
                    "um.last_name",
                    "um.email"
                ],
                "where" => [
                    ["where" => ["um.role_id", "=", $row_id]],
		    ["where" => ["um.account_id", "=", $logged_in_account]],
                    ["where" => ["um.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]],
                ]
            ];
            // Get all member's list for current role
            $member_list_data = $model_user_master->fetchAll($condition);

            if (!empty($member_list_data)) {
                $member_lists = $member_list_data;
            }

            $condition = [
                "fields" => [
                    "rm.id",
                    "rm.name",
                    "rm.short_info"
                ],
                "where" => [
                    ["where" => ["rm.id", "=", $row_id]]
                ]
            ];
            $row = $model_role_master->fetch($condition);

            // Fetch resources
            $model_role_default_resources = new RoleDefaultResources();

            // Fetch resources name
            $model_resource_master = new ResourceMaster();


            $condition = [
                "fields" => [
                    "rds.resource_id",
                ],
                "where" => [
                    ["where" => ["rds.role_id", "=", $row_id]],
                    ["where" => ["rds.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $resources = $model_role_default_resources->fetchAll($condition);

            $resources_list = [];
            $resources_names = [];
            foreach ($resources as $resource) {
                $resources_list[] = StringComponent::encodeRowId($resource["resource_id"]);

                // Fetch resources name from id
                $condition = [
                    "fields" => [
                        "rm.resource_name"
                    ],
                    "where" => [
                        ["where" => ["rm.id", "=", $resource["resource_id"]]],
                        ["where" => ["rm.status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                    ]
                ];

                $res_name = $model_resource_master->fetch($condition);

                if (!empty($res_name)) {
                    array_push($resources_names, $res_name["resource_name"]); 
                }
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Prepare output of data
        $output = [
            "id" => StringComponent::encodeRowId($row["id"]),
            "name" => $row["name"],
            "short_info" => $row["short_info"],
            "resources" => $resources_list,
            "resources_names" => $resources_names,
            "member_lists" => $member_lists,
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
        $model_role_master = new RoleMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_role_master->checkRowValidity($row_id, $other_values);

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
                    "rm.name",
                    "rm.short_info"
                ],
                "where" => [
                    ["where" => ["rm.id", "=", $row_id]]
                ]
            ];
            $row = $model_account_folders->fetch($condition);

            // Save data
            $flag_no = SHAppComponent::getValue("app_constant/FLAG_NO");

            $save_data = [
                "account_id" => $logged_in_account,
                "user_id" => $logged_in_user,
                "source_id" => $logged_in_source,
                "name" => "Copy of " . $row["name"],
                "code" => "Copy of " . $row["name"],
                "short_info" => $row["short_info"],
                "is_system" => $row["is_system"],
                "created" => DateTimeComponent::getDateTime(),
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($model_role_master->save($save_data) !== false) {
                $inserted_role_id = $model_role_master->getLastInsertId();

                // Get resources
                $model_role_default_resources = new RoleDefaultResources();

                $condition = [
                    "fields" => [
                        "rds.resource_id"
                    ],
                    "where" => [
                        ["where" => ["rds.role_id", "=", $row_id]],
                        ["where" => ["rds.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                    ]
                ];
                $resources = $model_role_default_resources->fetchAll($condition);

                foreach ($resources as $resource) {
                    try {
                        $save_data = [
                            "role_id" => $inserted_role_id,
                            "resource_id" => $resource,
                            "modified" => DateTimeComponent::getDateTime()
                        ];
                        $saved = $model_role_default_resources->save($save_data);

                    } catch(\Exception $e) { }
                }

                $output["id"] = StringComponent::encodeRowId($inserted_role_id);
                $output["message"] = "Role copied successfully.";

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
        $model_role_master = new RoleMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_role_master->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Check if role is system role
        try {
            $condition = [
                "fields" => [
                    "rm.is_system"
                ],
                "where" => [
                    ["where" => ["rm.id", "=", $row_id]]
                ]
            ];
            $row = $model_role_master->fetch($condition);
            if ($row["is_system"] == 1) {
                return ErrorComponent::outputError($response, "api_messages/SYSTEM_ROLE");
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Check if role is assigned to any user
        $model_user_master = new UserMaster();
        try {
            $condition = [
                "fields" => [
                    "um.id"
                ],
                "where" => [
                    ["where" => ["um.role_id", "=", $row_id]]
                ]
            ];
            $row = $model_user_master->fetch($condition);
            
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Role assigned to user
        if (!empty($row["id"])) {

            // Get request parameters
            $new_row_id = 0;
            $request_params = $request->getParsedBody();
            if (isset($request_params["role"])) {
                $new_row_id = StringComponent::decodeRowId($request_params["role"]);
                try {
                    // Other values for condition
                    $other_values = [
                        "user_id" => $logged_in_user,
                        "account_id" => $logged_in_account,
                        "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
                    ];

                    $valid = $model_role_master->checkRowValidity($new_row_id, $other_values);

                    if (!$valid) {
                        // Fetch error code & message and return the response
                        return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
                    }

                } catch(\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }
            }

            if ( $new_row_id > 0 ) {
                try {
                    $condition = [
                        "where" => [
                            ["where" => ["role_id", "=", $row_id]]
                        ]
                    ];
                    $save_data = [
                        "role_id" => $new_row_id,
                        "modified" => DateTimeComponent::getDateTime()
                    ];
                    $model_user_master->update($save_data, $condition);
                } catch (\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }
            } else {
                return ErrorComponent::outputError($response, "api_messages/ROLE_ASSIGNED_TO_USER");
            }                
        }

        // Update status
        try {
            $save_data = [
                "id" => $row_id,
                "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "modified" => DateTimeComponent::getDateTime()
            ];
            if ($model_role_master->save($save_data) !== false) {
                $output["message"] = "Role deleted successfully.";

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
     * Get all resources to display in role assignment
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getResources(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();

        // Fetch resources
        $model_resource_master = new ResourceMaster();

        try {
            $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");

            $condition = [
                "fields" => [
                    "rm.id",
                    "rm.resource_name"
                ],
                "where" => [
                    ["where" => ["rm.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]],
                    ["where" => ["rm.show_in_roles", "=", $flag_yes]],
                    ["whereNull" => "rm.parent_id"]
                ],
                "order_by" => [
                    "rm.position ASC"
                ]
            ];
            $parent_resources = $model_resource_master->fetchAll($condition);

            foreach ($parent_resources as $key => $resource) {
                // Fetch child resources
                $condition = [
                    "fields" => [
                        "rm.id",
                        "rm.resource_name"
                    ],
                    "where" => [
                        ["where" => ["rm.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]],
                        ["where" => ["rm.show_in_roles", "=", $flag_yes]],
                        ["where" => ["rm.parent_id", "=", $resource["id"]]]
                    ],
                    "order_by" => [
                        "rm.position ASC"
                    ]
                ];
                $child_resources = $model_resource_master->fetchAll($condition);

                $resources_array = [];
                foreach ($child_resources as $child) {
                    $child_name = explode("/", $child["resource_name"]);

                    $resources_array[] = [
                        "id" => StringComponent::encodeRowId($child["id"]),
                        "name" => trim($child_name[1])
                    ];
                }

                $output[$key] = [
                    "name" => $resource["resource_name"],
                    "resources" => $resources_array
                ];
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

}
