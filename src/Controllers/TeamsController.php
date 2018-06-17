<?php
/**
 * Teams related functionality
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
use \App\Models\AccountTeams;
use \App\Models\AccountTeamMembers;
use \App\Models\UserMaster;
use \App\Models\AccountTemplateTeams;
use \App\Models\AccountFolderTeams;
use \App\Models\DocumentTeams;

class TeamsController extends AppController {

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
        if (!empty($params["owned_by"])) {
            $owned_by = StringComponent::decodeRowId(trim($params["owned_by"]));

            if (!empty($owned_by)) {
                $query_params["owned_by"] = $owned_by;
            }
        }
		
        if (!empty($params["managed_by"])) {
            $managed_by = StringComponent::decodeRowId(trim($params["managed_by"]));
			
            if (!empty($managed_by)) {
                $query_params["managed_by"] = $managed_by;
            }
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
        $model_account_teams = new AccountTeams();

        try {
            $data = $model_account_teams->getListData($query_params, $other_values);

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
            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "name" => $row["name"],
                "managed_by" => trim($row["managed_by"]),
                "managed_by_id" => StringComponent::encodeRowId($row["manager_id"]),
                "owned_by" => trim($row["owned_by"]),
                "owned_by_id" => StringComponent::encodeRowId($row["owner_id"]),
                "total_members" => $row["total_members"]
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
        $request_data = [];
        
        if (isset($request_params["name"])) {
            $request_data["name"] = $request_params["name"];
        }
        if (isset($request_params["managed_by"])) {
            $request_data["managed_by"] = $request_params["managed_by"];
        }
        if (isset($request_params["members"])) {
            $request_data["members"] = $request_params["members"];
        }

        // check if user try to create team with only one member
        if (isset($request_data["members"])) {

            if (count($request_data["members"]) == 1) {
                return ErrorComponent::outputError($response, "api_messages/INVALID_TEAM_MEMBER");   
            }
        }

        // Validate request
        $request_validations = [
            "name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "managed_by" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ]
        ];
        $validation_errors = Validator::validate($request_validations, $request_data);

        //Check for validity of managed_by id
        if (isset($request_params["managed_by"]) && $request_params["managed_by"] != "") {
            $managed_by = StringComponent::decodeRowId(trim($request_params["managed_by"]));

            if (!empty($managed_by)) {
                $model_user = new UserMaster();

                $condition = [
                    "fields" => [
                        "um.id"
                    ],
                    "where" => [
                        ["where" => ["um.id", "=", $managed_by]],
                        ["where" => ["um.account_id", "=", $logged_in_account]]
                    ]
                ];
                $user_row = $model_user->fetch($condition);
                if (!empty($user_row["id"])) {
                    $request_data["managed_by"] = $managed_by;
                } else {
                    $validation_errors[] = "Invalid Parameter : managed_by";//#TNSH-151
                }              
            } else { //Invalid text entered
                $validation_errors[] = "Invalid Parameter : Require invited user id";
            }
        }

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        // Save record
        $model_account_teams = new AccountTeams();

        try {
            $save_data = [
                "account_id" => $logged_in_account,
                "user_id" => $logged_in_user,
                "source_id" => $logged_in_source,
                "name" => trim($request_data["name"]),
                "owner_id" => $logged_in_user,
                "manager_id" => $request_data["managed_by"],
                "created" => DateTimeComponent::getDateTime(),
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($model_account_teams->save($save_data) !== false) {
                $team_id = $model_account_teams->getLastInsertId();

                // Save team members
                $model_team_members = new AccountTeamMembers();

                foreach ($request_data["members"] as $member) {
                    $member_id = StringComponent::decodeRowId(trim($member));

                    if (!empty($member_id)) {
                        try {
                            $save_data = [
                                "account_team_id" => $team_id,
                                "user_id" => $member_id,
                                "modified" => DateTimeComponent::getDateTime()
                            ];
                            $saved = $model_team_members->save($save_data);

                        } catch(\Exception $e) { }
                    }
                }

                $output["id"] = StringComponent::encodeRowId($team_id);
                $output["message"] = "Team created successfully.";

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

        // Check if record is valid
        $model_account_teams = new AccountTeams();

        try {
            // Other values for condition
            $other_values = [
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_teams->checkRowValidity($row_id, $other_values);

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
        if (isset($request_params["managed_by"])) {
            $managed_by = StringComponent::decodeRowId(trim($request_params["managed_by"]));

            if (!empty($managed_by)) {
                $request_data["managed_by"] = $managed_by;
            }
        }
        if (isset($request_params["members"])) {
            $request_data["members"] = $request_params["members"];
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
        $model_account_teams = new AccountTeams();

        try {
            $save_data = [
                "id" => $row_id,
                "name" => trim($request_data["name"]),
                "manager_id" => $request_data["managed_by"],
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($model_account_teams->save($save_data) !== false) {
                // Save team members
                $model_team_members = new AccountTeamMembers();

                // Update all members status to delete
                $condition = [
                    "where" => [
                        ["where" => ["account_team_id", "=", $row_id]]
                    ]
                ];
                $save_data = [
                    "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $updated = $model_team_members->update($save_data, $condition);

                foreach ($request_data["members"] as $member) {
                    $member_id = StringComponent::decodeRowId(trim($member));

                    if (!empty($member_id)) {
                        try {
                            // Fetch is data already exists
                            $condition = [
                                "fields" => [
                                    "atm.id"
                                ],
                                "where" => [
                                    ["where" => ["atm.account_team_id", "=", $row_id]],
                                    ["where" => ["atm.user_id", "=", $member_id]]
                                ]
                            ];
                            $already_exists = $model_team_members->fetch($condition);

                            $save_data = [
                                "account_team_id" => $row_id,
                                "user_id" => $member_id,
                                "modified" => DateTimeComponent::getDateTime()
                            ];
                            if (!empty($already_exists["id"])) {
                                $save_data["id"] = $already_exists["id"];
                                $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                            }
                            $saved = $model_team_members->save($save_data);

                        } catch(\Exception $e) { }
                    }
                }

                $output["message"] = "Team updated successfully.";

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
        $model_account_teams = new AccountTeams();

        try {
            // Other values for condition
            $other_values = [
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_teams->checkRowValidity($row_id, $other_values);

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
                    "at.name",
                    "at.manager_id"
                ],
                "where" => [
                    ["where" => ["at.id", "=", $row_id]]
                ]
            ];
            $row = $model_account_teams->fetch($condition);

            // Fetch members of teams and copy them
            $model_team_members = new AccountTeamMembers();

            $condition = [
                "fields" => [
                    "atm.user_id",
                    "um.first_name",
                    "um.last_name",
                    "um.email"
                ],
                "where" => [
                    ["where" => ["atm.account_team_id", "=", $row_id]],
                    ["where" => ["atm.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ],
                "join" => [
                    "user_master"
                ]
            ];
            $members = $model_team_members->fetchAll($condition);

            $members_list = [];
            foreach ($members as $member) {
                $members_list[] = [
                    "id" => StringComponent::encodeRowId($member["user_id"]),
                    "name" => trim($member["first_name"] . " " . $member["last_name"]),
                    "email" => $member["email"]
                ];
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Prepare output of data
        $output = [
            "id" => StringComponent::encodeRowId($row["id"]),
            "name" => $row["name"],
            "manager_id" => StringComponent::encodeRowId($row["manager_id"]),
            "members" => $members_list
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
        $model_account_teams = new AccountTeams();

        try {
            // Other values for condition
            $other_values = [
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_teams->checkRowValidity($row_id, $other_values);

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
                    "at.name",
                    "at.manager_id"
                ],
                "where" => [
                    ["where" => ["at.id", "=", $row_id]]
                ]
            ];
            $row = $model_account_teams->fetch($condition);

            // Save data
            $save_data = [
                "account_id" => $logged_in_account,
                "user_id" => $logged_in_user,
                "source_id" => $logged_in_source,
                "name" => "Copy of " . $row["name"],
                "owner_id" => $logged_in_user,
                "manager_id" => $row["manager_id"],
                "created" => DateTimeComponent::getDateTime(),
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($model_account_teams->save($save_data) !== false) {
                $team_id = $model_account_teams->getLastInsertId();

                // Fetch members of teams and copy them
                $model_team_members = new AccountTeamMembers();

                $condition = [
                    "fields" => [
                        "atm.user_id"
                    ],
                    "where" => [
                        ["where" => ["atm.account_team_id", "=", $row_id]],
                        ["where" => ["atm.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                    ]
                ];
                $members = $model_team_members->fetchAll($condition);

                foreach ($members as $member) {
                    try {
                        $save_data = [
                            "account_team_id" => $team_id,
                            "user_id" => $member["user_id"],
                            "modified" => DateTimeComponent::getDateTime()
                        ];
                        $saved = $model_team_members->save($save_data);

                    } catch(\Exception $e) { }
                }

                $output["id"] = StringComponent::encodeRowId($team_id);
                $output["message"] = "Team copied successfully.";

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
        $model_account_teams = new AccountTeams();

        try {
            // Other values for condition
            $other_values = [
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_account_teams->checkRowValidity($row_id, $other_values);

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
            if ($model_account_teams->save($save_data) !== false) {
                $output["message"] = "Team deleted successfully.";

                $model_account_template_team = new AccountTemplateTeams();

                try {
                    $other_values_team_check = [
                        "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
                    ];

                    $team_available = $model_account_template_team->checkTemplateTeamAvailable($row_id, $other_values_team_check);

                } catch (\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

                if ($team_available) {
                    try {
                        $update_data_template_team = [
                            "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                            "modified" => DateTimeComponent::getDateTime()
                        ];

                        $conditions_template_team_update = [
                            "where" => [
                                ["where" => ["account_team_id", "=", $row_id]]
                            ],
                        ];

                        $model_account_template_team->update($update_data_template_team, $conditions_template_team_update);

                    } catch(\Exception $e) {
                        // Fetch error code & message and return the response
                        return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                    }
                }

                // Folder Share Check
                $model_account_folder_team = new AccountFolderTeams();

                try {
                    $other_values_folder_check = [
                        "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
                    ];

                    $folder_available = $model_account_folder_team->checkShareFolderAvailable($row_id, $other_values_folder_check);

                } catch (\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

                if ($folder_available) {
                    try {
                        $update_data_folder_team = [
                            "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                            "modified" => DateTimeComponent::getDateTime()
                        ];

                        $conditions_folder_team_update = [
                            "where" => [
                                ["where" => ["account_team_id", "=", $row_id]]
                            ],
                        ];

                        $model_account_folder_team->update($update_data_folder_team, $conditions_folder_team_update);

                    } catch(\Exception $e) {
                        // Fetch error code & message and return the response
                        return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                    }
                }

                // Document Share Check
                $model_document_team = new DocumentTeams();

                try {
                    $other_values_doc_share_check = [
                        "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
                    ];

                    $doc_share_available = $model_document_team->checkDocumentShareTeam($row_id, $other_values_doc_share_check);

                } catch (\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

                if ($doc_share_available) {
                    try {
                        $update_data_doc_share_team = [
                            "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                            "modified" => DateTimeComponent::getDateTime()
                        ];

                        $conditions_doc_share_team_update = [
                            "where" => [
                                ["where" => ["account_team_id", "=", $row_id]]
                            ],
                        ];

                        $model_document_team->update($update_data_doc_share_team, $conditions_doc_share_team_update);

                    } catch(\Exception $e) {
                        // Fetch error code & message and return the response
                        return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
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

}
