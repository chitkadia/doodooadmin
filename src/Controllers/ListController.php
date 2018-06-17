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
use \App\Models\UserMaster;
use \App\Models\RoleMaster;
use \App\Models\AccountTeams;
use \App\Models\AccountContacts;
use \App\Models\AccountSendingMethods;
use \App\Models\AccountTemplates;
use \App\Models\ResourceMaster;
use \App\Models\RoleDefaultResources;
use \App\Models\AccountTeamMembers;
use \App\Models\AccountFolders;
use \App\Models\PlanMaster;

class ListController extends AppController {

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Get resource access
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

        $resources = SHAppComponent::getUserResources();
        foreach ($resources as $key => $val) {
            $output[] = $key;
        }

        return $response->withJson($output, 200);
    }

    /**
     * Get members which the user has access
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getMembers(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        return $response->withJson($output, 200);
    }

    /**
     * Get all members of account
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getAllMembers(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();

        if ($is_owner) {
            // Get all members
            $model_user_master = new UserMaster();

            try {
                // Fetch data
                $condition = [
                    "fields" => [
                        "um.id",
                        "um.first_name",
                        "um.last_name",
                        "um.email"
                    ],
                    "where" => [
                        ["where" => ["um.account_id", "=", $logged_in_account]],
                        ["where" => ["um.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]],
                        ["where" => ["um.verified", "<>", SHAppComponent::getValue("app_constant/FLAG_NO")]],
                        ["where" => ["um.last_login", "<>", SHAppComponent::getValue("app_constant/FLAG_NO")]]
                    ]
                ];
                $rows = $model_user_master->fetchAll($condition);

            } catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

            // Prepare output data
            foreach ($rows as $row) {
                $output[] = [
                    "id" => StringComponent::encodeRowId($row["id"]),
                    "name" => ($logged_in_user != $row["id"]) ? trim($row["first_name"] . " " . $row["last_name"]) : "Me",
                    "email" => $row["email"]
                ];
            }
        }

        return $response->withJson($output, 200);
    }

    /**
     * Get timezone list
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getTzList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [
            "GMT-11:30",
            "GMT-11:00",
            "GMT-10:30",
            "GMT-10:00",
            "GMT-09:30",
            "GMT-09:00",
            "GMT-08:30",
            "GMT-08:00",
            "GMT-07:30",
            "GMT-07:00",
            "GMT-06:30",
            "GMT-06:00",
            "GMT-05:30",
            "GMT-05:00",
            "GMT-04:30",
            "GMT-04:00",
            "GMT-03:30",
            "GMT-03:00",
            "GMT-02:30",
            "GMT-02:00",
            "GMT-01:30",
            "GMT-01:00",
            "GMT-00:30",
            "GMT+00:00",
            "GMT+00:30",
            "GMT+01:00",
            "GMT+01:30",
            "GMT+02:00",
            "GMT+02:30",
            "GMT+03:00",
            "GMT+03:30",
            "GMT+04:00",
            "GMT+04:30",
            "GMT+05:00",
            "GMT+05:30",
            "GMT+06:00",
            "GMT+06:30",
            "GMT+07:00",
            "GMT+07:30",
            "GMT+08:00",
            "GMT+08:30",
            "GMT+09:00",
            "GMT+09:30",
            "GMT+10:00",
            "GMT+10:30",
            "GMT+11:00",
            "GMT+11:30",
            "GMT+12:00",
            "GMT+12:30",
            "GMT+13:00",
            "GMT+13:30"
        ];

        return $response->withJson($output, 200);
    }

    /**
     * Get application variables
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getAppVars(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        $app_constant_labels = SHAppComponent::getValue("app_constant_label");
        $app_constant_values = SHAppComponent::getValue("app_constant");

        foreach ($app_constant_values as $key => $val) {
            $output[$key] = [
                "label" => $app_constant_labels[$key],
                "value" => $val
            ];
        }

        $output["action_groups"] = SHAppComponent::getValue("action_groups");

        return $response->withJson($output, 200);
    }

    /**
     * Get all roles
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getAllRoles(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        // Get all roles
        $model_role_master = new RoleMaster();

        try {
            // Fetch data
            $condition = [
                "fields" => [
                    "rm.id",
                    "rm.name"
                ],
                "where" => [
                    ["where" => ["rm.account_id", "=", $logged_in_account]],
                    ["where" => ["rm.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $rows = $model_role_master->fetchAll($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Prepare output data
        foreach ($rows as $row) {
            $output[] = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "name" => $row["name"]
            ];
        }

        // Get all common roles
        try {
            // Fetch data
            $condition = [
                "fields" => [
                    "rm.id",
                    "rm.name"
                ],
                "where" => [
                    ["whereNull" => "rm.account_id"],
                    ["where" => ["rm.for_customers", "=", SHAppComponent::getValue("app_constant/FLAG_YES")]],
                    ["where" => ["rm.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $rows = $model_role_master->fetchAll($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Prepare output data
        foreach ($rows as $row) {
            $output[] = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "name" => $row["name"]
            ];
        }

        return $response->withJson($output, 200);
    }

    /**
     * Get all resources for roles
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getResourcesForRoles(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

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

    /**
     * Get all resources for a role
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getResourcesByRole(ServerRequestInterface $request, ResponseInterface $response, $args) {
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

        // Fetch resources
        $model_role_default_resources = new RoleDefaultResources();

        try {
            $condition = [
                "fields" => [
                    "rds.resource_id"
                ],
                "where" => [
                    ["where" => ["rds.role_id", "=", $row_id]],
                    ["where" => ["rds.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $rows = $model_role_default_resources->fetchAll($condition);

            foreach ($rows as $row) {
                $output[] = StringComponent::encodeRowId($row["resource_id"]);
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * Get all teams
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getAllTeams(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        // Get all teams
        $model_account_teams = new AccountTeams();

        try {
            // Fetch data
            $condition = [
                "fields" => [
                    "at.id",
                    "at.name"
                ],
                "where" => [
                    ["where" => ["at.account_id", "=", $logged_in_account]],
                    ["where" => ["at.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $rows = $model_account_teams->fetchAll($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Prepare output data
        foreach ($rows as $row) {
            $output[] = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "name" => $row["name"]
            ];
        }

        return $response->withJson($output, 200);
    }

    /**
     * Get my teams
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getMyTeams(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        // Get my teams
        $model_account_team_members = new AccountTeamMembers();
        $model_account_teams = new AccountTeams();

        try {
            // Fetch data
            
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
            ];

            $rows = $model_account_teams->getTeamList($other_values);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Prepare output data
        foreach ($rows as $row) {
            $output[] = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "name" => $row["name"]
            ];
        }

        return $response->withJson($output, 200);
    }

    /**
     * Get all contacts
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getAllContacts(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        // Get all contacts
        $model_account_contacts = new AccountContacts();

        try {
            // Fetch data
            $condition = [
                "fields" => [
                    "aco.id",
                    "aco.first_name",
                    "aco.last_name",
                    "aco.email"
                ],
                "where" => [
                    ["where" => ["aco.account_id", "=", $logged_in_account]],
                    ["where" => ["aco.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $rows = $model_account_contacts->fetchAll($condition);

        } catch(\Exception $e) {
            echo $e->getMessage(); exit;
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Prepare output data
        foreach ($rows as $row) {
            $output[] = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "name" => trim($row["first_name"] . " " . $row["last_name"]),
                "email" => $row["email"]
            ];
        }

        return $response->withJson($output, 200);
    }

    /**
     * Get user's email accounts
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getMyEmailAccounts(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        $app_constants = SHAppComponent::getValue("app_constant");
        $sending_methods = SHAppComponent::getValue("email_sending_method");

        $user_timezone = SHAppComponent::getUserTimeZone();
        
        // Get all email accounts
        $model_account_sending_methods = new AccountSendingMethods();

        try {
            // Fetch data
            $condition = [
                "fields" => [
                    "asm.id",
                    "asm.name",
                    "asm.from_name",
                    "asm.from_email",
                    "asm.email_sending_method_id",
                    "asm.status",
                    "asm.credit_limit",
                    "asm.next_reset"
                ],
                "where" => [
                    ["where" => ["asm.account_id", "=", $logged_in_account]],
                    ["where" => ["asm.user_id", "=", $logged_in_user]],
                    ["where" => ["asm.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]],
                    ["where" => ["asm.status", "<>", SHAppComponent::getValue("app_constant/STATUS_INACTIVE")]]
                ]
            ];
            $rows = $model_account_sending_methods->fetchAll($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Prepare output data
        foreach ($rows as $row) {
            if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE")) {
                $status = "STATUS_ACTIVE";
            } else {
                $status = "STATUS_INACTIVE";
            }
            $type = array_search($row["email_sending_method_id"], $sending_methods);
            
            $next_reset = "";

            $hours = "";

            if ( $row["next_reset"] > 0 ) {
                $next_reset = DateTimeComponent::convertDateTime($row["next_reset"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);

                $hours = DateTimeComponent::getQuotaResetHours($row["next_reset"]);
            }

            $output[] = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "name" => $row["name"],
                "from_name" => $row["from_name"],
                "from_email" => $row["from_email"],
                "type" => $type,
                "status" => $status,
                "credit_limit" => $row["credit_limit"],
                "next_reset" => $next_reset,
                "hours"=> $hours
            ];
        }

        // Get all public accounts
        try {
            // Fetch data
            $condition = [
                "fields" => [
                    "asm.id",
                    "asm.name",
                    "asm.from_name",
                    "asm.from_email",
                    "asm.email_sending_method_id",
                    "asm.status",
                    "asm.credit_limit",
                    "asm.next_reset"
                ],
                "where" => [
                    ["where" => ["asm.account_id", "=", $logged_in_account]],
                    ["where" => ["asm.user_id", "<>", $logged_in_user]],
                    ["where" => ["asm.public", "=", SHAppComponent::getValue("app_constant/FLAG_YES")]],
                    ["where" => ["asm.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $rows = $model_account_sending_methods->fetchAll($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Prepare output data
        foreach ($rows as $row) {
            $status = array_search($row["status"], $app_constants);
            $type = array_search($row["email_sending_method_id"], $sending_methods);
            $next_reset = "";
            $hours = "";
            if ( $row["next_reset"] > 0 ) {
                $next_reset = DateTimeComponent::convertDateTime($row["next_reset"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
                $hours = DateTimeComponent::getQuotaResetHours($row["next_reset"]);
            }
            $output[] = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "name" => $row["name"],
                "from_name" => $row["from_name"],
                "from_email" => $row["from_email"],
                "type" => $type,
                "status" => $status,
                "credit_limit" => $row["credit_limit"],
                "next_reset" => $next_reset,
                "hours"=> $hours
            ];
        }

        return $response->withJson($output, 200);
    }

    /**
     * Get all templates
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getAllTemplates(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        // Get all email accounts
        $model_account_templates = new AccountTemplates();

        try {
            // Fetch data
            $condition = [
                "fields" => [
                    "at.id",
                    "at.title"
                ],
                "where" => [
                    ["where" => ["at.account_id", "=", $logged_in_account]],
                    ["where" => ["at.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $rows = $model_account_templates->fetchAll($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Prepare output data
        foreach ($rows as $row) {
            $output[] = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "title" => $row["title"]
            ];
        }

        return $response->withJson($output, 200);
    }

    /**
     * Get webhook resources
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getAllWebhookResources(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        $output = [
            [
                "id" => StringComponent::encodeRowId(1),
                "resource_name" => "Resource1",
                "conditions" => [
                    [
                        "id" => StringComponent::encodeRowId(1),
                        "name" => "Condition1"
                    ],
                    [
                        "id" => StringComponent::encodeRowId(2),
                        "name" => "Condition2"
                    ],
                    [
                        "id" => StringComponent::encodeRowId(3),
                        "name" => "Condition3"
                    ]
                ]
            ],
            [
                "id" => StringComponent::encodeRowId(2),
                "resource_name" => "Resource2",
                "conditions" => [
                    [
                        "id" => StringComponent::encodeRowId(1),
                        "name" => "Condition1"
                    ],
                    [
                        "id" => StringComponent::encodeRowId(2),
                        "name" => "Condition2"
                    ],
                    [
                        "id" => StringComponent::encodeRowId(3),
                        "name" => "Condition3"
                    ]
                ]
            ],
            [
                "id" => StringComponent::encodeRowId(3),
                "resource_name" => "Resource3",
                "conditions" => [
                    [
                        "id" => StringComponent::encodeRowId(1),
                        "name" => "Condition1"
                    ],
                    [
                        "id" => StringComponent::encodeRowId(2),
                        "name" => "Condition2"
                    ],
                    [
                        "id" => StringComponent::encodeRowId(3),
                        "name" => "Condition3"
                    ]
                ]
            ],
            [
                "id" => StringComponent::encodeRowId(4),
                "resource_name" => "Resource4",
                "conditions" => [
                    [
                        "id" => StringComponent::encodeRowId(1),
                        "name" => "Condition1"
                    ],
                    [
                        "id" => StringComponent::encodeRowId(2),
                        "name" => "Condition2"
                    ],
                    [
                        "id" => StringComponent::encodeRowId(3),
                        "name" => "Condition3"
                    ],
                    [
                        "id" => StringComponent::encodeRowId(4),
                        "name" => "Condition4"
                    ]
                ]
            ],
            [
                "id" => StringComponent::encodeRowId(5),
                "resource_name" => "Resource5",
                "conditions" => [
                    [
                        "id" => StringComponent::encodeRowId(1),
                        "name" => "Condition1"
                    ],
                    [
                        "id" => StringComponent::encodeRowId(2),
                        "name" => "Condition2"
                    ]
                ]
            ]
        ];

        return $response->withJson($output, 200);
    }

    /**
     * Get folders list (Templates)
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getTemplateFolders(ServerRequestInterface $request, ResponseInterface $response, $args) {

        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();

        // Other values for condition
        $other_values = [
            "user_id" => $logged_in_user,
            "account_id" => $logged_in_account,
            "is_owner" => $is_owner,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        // Set parameters
        $query_params = [
            "page" => 1,
            "per_page" => SHAppComponent::getValue("app_constant/DEFAULT_LIST_PER_PAGE"),
            "order_by" => "name",
            "order" => "ASC"
        ];

        // Get folders
        $model_account_folders = new AccountFolders();

        try {
            $data = $model_account_folders->getListDataTemplates($query_params, $other_values);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $output[] = [
            "id" => 0,
            "name" => "Root"
        ];
        if (!empty($data["rows"])) {
            foreach ($data["rows"] as $row) {
                $share_access = json_decode($row["share_access"], true);

                if ($row["user_id"] == $logged_in_user || $share_access["can_edit"] ||  $share_access["can_create"] ) {
                    $output[] = [
                        "id" => StringComponent::encodeRowId($row["id"]),
                        "name" => $row["name"]
                    ];
                }
            }
        }

        return $response->withJson($output, 200);
    }
    /**
     * Get folders list (Documents)
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getDocumentFolders(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();

        // Other values for condition
        $other_values = [
            "user_id" => $logged_in_user,
            "account_id" => $logged_in_account,
            "is_owner" => $is_owner,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        // Set parameters
        $query_params = [
            "page" => 1,
            "per_page" => SHAppComponent::getValue("app_constant/DEFAULT_LIST_PER_PAGE"),
            "order_by" => "name",
            "order" => "ASC"
        ];

        // Get folders
        $model_account_folders = new AccountFolders();

        try {
            $data = $model_account_folders->getListDataDocuments($query_params, $other_values);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $output[] = [
            "id" => 0,
            "name" => "Root"
        ];

        if (!empty($data["rows"])) {
            foreach ($data["rows"] as $row) {
                $share_access = json_decode($row["share_access"], true);

                if ($row["user_id"] == $logged_in_user || $share_access["can_edit"]) {
                    $output[] = [
                        "id" => StringComponent::encodeRowId($row["id"]),
                        "name" => $row["name"]
                    ];
                }
            }
        }
        return $response->withJson($output, 200);
    }

    /**
     * Get all plan details
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getAllPlans(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
                
        $plan_master_model = new PlanMaster();
        
        // Fetch data
        try {
            $other_values_plan = [
                "active" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                "order_by" => "mode",
                "order" => "ASC",
                "amount" => 0.00
            ];
            $data = $plan_master_model->getAllPlanData($other_values_plan);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        foreach ($data["rows"] as $row) {
            $row_data = [
                "plan_id" => StringComponent::encodeRowId($row["id"]),
                "plan_code" => trim($row["code"]),
                "plan_name" => trim($row["name"]),
                "plan_mode" => $row["mode"],
                "plan_amount" => $row["amount"]
            ];

            $output["rows"][] = $row_data;
        }

        return $response->withJson($output, 200);
    }

}
