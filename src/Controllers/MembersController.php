<?php
/**
 * Members related functionality
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
use \App\Models\UserInvitations;
use \App\Models\RoleDefaultResources;
use \App\Models\UserResources;
use \App\Models\UserSettings;
use \App\Models\AccountTeamMembers;
use \App\Models\AccountBillingMaster;
use \App\Models\AccountSubscriptionDetails;
use \App\Components\Mailer\TransactionMailsComponent;
use \App\Models\UserAuthenticationTokens;
use \App\Models\ResourceMaster;

class MembersController extends AppController {

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
        if (!empty($params["role"])) {
            $role_id = StringComponent::decodeRowId(trim($params["role"]));

            if (!empty($role_id)) {
                $query_params["role"] = $role_id;
            }
        }
        if (!empty($params["query"])) {
            $query_params["query"] = SHAppComponent::prepareSearchText($params["query"]);
        }

        // Other values for condition
        $other_values = [
            "user_id" => $logged_in_user,
            "account_id" => $logged_in_account,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        // Get data
        $model_user_master = new UserMaster();

        try {
            $data = $model_user_master->getListData($query_params, $other_values);

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
        
        $user_type = SHAppComponent::getValue("user_type");

        foreach ($data["rows"] as $row) {
            $resend_invitation = false;
            $last_login_val = 0;
            $logged_in_user_type = '';

            if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_INACTIVE")) {
                $status = "STATUS_INACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE")) {
                $status = "STATUS_ACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_REMOVED")) {
                $status = "STATUS_REMOVED";
            } else {
                $status = "NA";
            }
            
            $user_type_code = array_search($row["user_type_id"], $user_type);
            
            $client_admin = SHAppComponent::getValue("user_type/CLIENT_ADMIN");
            $sh_admin = SHAppComponent::getValue("user_type/SH_ADMIN");
            $sh_super_admin = SHAppComponent::getValue("user_type/SH_SUPER_ADMIN");
            
            $can_delete = true;
            if ($row["user_type_id"] == $client_admin || $row["user_type_id"] == $sh_admin || $row["user_type_id"] == $sh_super_admin) {
                $can_delete = false;
            }

            $invited_at = DateTimeComponent::convertDateTime($row["invited_at"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
            $last_login = DateTimeComponent::convertDateTime($row["last_login"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);

            if ($row["id"] == $logged_in_user) {
                $logged_in_user_type = array_search($row["user_type_id"], $user_type);
            } 

            if (isset($row["joined_at"]) && $row["joined_at"] == 0) {
                $resend_invitation = true; 
            } 

            if (isset($row["last_login"]) && $row["last_login"] != 0) {
                $last_login_val = DateTimeComponent::showDateTime($last_login, $user_timezone);
            }

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "name" => trim($row["first_name"] . " " . $row["last_name"]),
                "email" => $row["email"],
                "role_id" => StringComponent::encodeRowId($row["role_id"]),
                "user_type" => $user_type_code,
                "logged_in_user_type" => $logged_in_user_type,
                "can_delete" => $can_delete,
                "role" => $row["name"],
                "invited_at" => DateTimeComponent::showDateTime($invited_at, $user_timezone),
                "invited_at_timestamp" => $row["invited_at"],
                "resend_invitation" => $resend_invitation,
                "last_login" => $last_login_val,
                "last_login_timestamp" => $row["last_login"],
                "status" => $status
            ];

            $output["rows"][] = $row_data;
        }    

        return $response->withJson($output, 200);
    }

    /**
     * Invite new members
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function invite(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $logged_in_source = SHAppComponent::getRequestSource();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [
            "emails" => []
        ];
        
        if (isset($request_params["emails"])) {
            $request_data["emails"] = $request_params["emails"];
        }

        // Validate request
        $request_validations = [
            "emails" => [
                ["type" => Validator::FIELD_REQUIRED],
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
        //check for number invited members and restricted to invite per limit
        if (count($request_data["emails"]) > \LIMIT_INVITE_MEMBER) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/MAX_INVITE_MEMBER", " ".\LIMIT_INVITE_MEMBER);
        }
        $staff_user_type = SHAppComponent::getDefaultCustomerStaffUserType();
        $staff_user_role = SHAppComponent::getDefaultCustomerStaffRole();
        $admin_user_type = SHAppComponent::getDefaultCustomerAdminUserType();

        $model_user_master = new UserMaster();
        $model_user_invitations = new UserInvitations();
        $model_acc_billing = new AccountBillingMaster();
		$model_acc_subscription_details = new AccountSubscriptionDetails();

        // Check if invite memeber limit reached or not
        try {
            $condition = [
                "fields" => [
                    "id",
                    "account_id",
					"plan_id",
                    "team_size"
                ],
                "where" => [
                    ["where" => ["account_id", "=", $logged_in_account]]
                ]
            ];

            $list_acc_bill = $model_acc_billing->fetch($condition);

            $condition1 = [
                "fields" => [
                    "id"
                ],
                "where" => [
                    ["where" => ["account_id", "=", $logged_in_account]],
                    ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];

            $list_acc_members = $model_user_master->fetchAll($condition1);

            if (!empty($list_acc_bill)) {
				
				if ($list_acc_bill["plan_id"] == SHAppComponent::getValue("plan/FREE")) {
					return ErrorComponent::outputError($response, "api_messages/REMAIN_MEMBER_LIMIT", " 0");
				}

				$team_size = $list_acc_bill["team_size"];
				
				if ($list_acc_bill["plan_id"] != SHAppComponent::getValue("plan/PLUS_MONTHLY_TRIAL")) {
					$invited_member_count = count($list_acc_members);
					$remain_invite_count = $team_size - $invited_member_count;

					if ($remain_invite_count < count($request_data["emails"])) {

						return ErrorComponent::outputError($response, "api_messages/REMAIN_MEMBER_LIMIT", " ".$remain_invite_count);

					} else if ($team_size == $invited_member_count) {

						return ErrorComponent::outputError($response, "api_messages/INVITE_MEMBER_SEAT_EXCED", $additional_message);  
					}
				} else {
					
				}
            } else {
				$team_size = 0;
			}

        } catch(\Exception $e) {
            return SHAppComponent::getValue("api_messages/DB_OPERATION_FAIL");
        }

        try {
            $condition_admin_detail = [
                "fields" => [
                    "first_name",
                    "last_name",
                    "email"
                ],
                "where" => [
                    ["where" => ["account_id", "=", $logged_in_account]],
                    ["where" => ["user_type_id", "=", $admin_user_type]]
                ]
            ];

            $admin_data = $model_user_master->fetch($condition_admin_detail);

        } catch(\Exception $e) {
            return SHAppComponent::getValue("api_messages/DB_OPERATION_FAIL");
        }

		$processed_emails = [];
        foreach ($request_data["emails"] as $email) {
            $id = 0;
            $error_message = "";
            $email = trim($email);
            $code = "";
			
            try {
                // Validate request
                $request_data = ["email" => $email];
                $request_validations = [
                    "email" => [
                        ["type" => Validator::FIELD_REQ_NOTEMPTY],
                        ["type" => Validator::FIELD_EMAIL]
                    ]
                ];
                $error_message = Validator::validate($request_validations, $request_data);

                if (empty($error_message)) {
                    // Check if email already exists
                    $condition = [
                        "fields" => ["um.id"],
                        "where" => [
                            ["where" => ["um.email", "=", $email]]
                        ]
                    ];
                    $already_exists = $model_user_master->fetch($condition);

                    if (!empty($already_exists["id"])) {
                        $error_message = SHAppComponent::getValue("api_messages/USER_ALREADY_EXISTS/error_message");
                    }
                }

                if (empty($error_message)) {
                    // Save record
                    $save_data = [
                        "account_id" => $logged_in_account,
                        "user_type_id" => $staff_user_type,
                        "role_id" => $staff_user_role,
                        "source_id" => $logged_in_source,
                        "first_name" => "",
                        "last_name" => "",
                        "email" => $email,
                        "password" => "",
                        "password_salt_key" => "",
                        "photo" => "",
                        "phone" => "",
                        "created" => DateTimeComponent::getDateTime(),
                        "modified" => DateTimeComponent::getDateTime()
                    ];
                    if ($model_user_master->save($save_data) !== false) {
                        $inserted_user_id = $model_user_master->getLastInsertId();

                        // Get user role resources
                        $model_role_resources = new RoleDefaultResources();

                        $condition = [
                            "fields" => [
                                "rds.resource_id"
                            ],
                            "where" => [
                                ["where" => ["rds.role_id", "=", $staff_user_role]],
                                ["where" => ["rds.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                            ]
                        ];
                        $data = $model_role_resources->fetchAll($condition);

                        // Assign resources to user
                        $model_user_resource = new UserResources();

                        foreach ($data as $resource) {
                            $save_data = [
                                "user_id" => $inserted_user_id,
                                "resource_id" => $resource["resource_id"],
                                "modified" => DateTimeComponent::getDateTime()
                            ];

                            try {
                                $saved = $model_user_resource->save($save_data);

                            } catch(\Exception $e) {}
                        }
                        
                        // Insert invitation entry
                        try {
                            $save_data = [
                                "account_id" => $logged_in_account,
                                "user_id" => $inserted_user_id,
                                "invited_by" => $logged_in_user,
                                "invited_at" => DateTimeComponent::getDateTime(),
                                "created" => DateTimeComponent::getDateTime(),
                                "modified" => DateTimeComponent::getDateTime()
                            ];
                            $saved = $model_user_invitations->save($save_data);

                            $code = StringComponent::encodeRowId([$inserted_user_id, $logged_in_account]);
                            $id = $inserted_user_id;

                            $request_origin_url = SHAppComponent::getRequestOrigin();


                            //Send email to user to verify account
                            $info["smtp_details"]["host"] = HOST;
                            $info["smtp_details"]["port"] = PORT;
                            $info["smtp_details"]["encryption"] = ENCRYPTION;
                            $info["smtp_details"]["username"] = USERNAME;
                            $info["smtp_details"]["password"] = PASSWORD;

                            $info["from_email"] = FROM_EMAIL;
                            $info["from_name"] = FROM_NAME;

                            $info["to"] = $email;
                            $info["cc"] = '';
                            $info["bcc"] = '';
                            $info["subject"] = "You have been added to a SalesHandy team";
                            $info["content"] = file_get_contents(\EMAIL_TEPLATES_FOLDER . "/invite_email.html");
                            $info["content"] = str_replace("{ConfirmEmailLink}", $request_origin_url . "/user/accept-invitation/".$code, $info["content"]);
                            $info["content"] = str_replace("{FullName}", $admin_data["first_name"]." ".$admin_data["last_name"], $info["content"]);
                            $info["content"] = str_replace("{Email}", $admin_data["email"], $info["content"]);

                            $result = TransactionMailsComponent::mailSendSmtp($info);

                        } catch(\Exception $e) { }
						array_push($processed_emails, $email);
                    } else {
                        $error_message = SHAppComponent::getValue("api_messages/DB_OPERATION_FAIL/error_message");
                    }
                }

            } catch(\Exception $e) {
                $error_message = SHAppComponent::getValue("api_messages/DB_OPERATION_FAIL/error_message");
            }

            $output[] = [
                "email" => $email,
                "success" => (empty($id)) ? false : true,
                "error_message" => $error_message,
                "code" => $code,
                "id" => empty($id) ? null : StringComponent::encodeRowId($id)
            ];
        }
		
		if ($list_acc_bill["plan_id"] == SHAppComponent::getValue("plan/PLUS_MONTHLY_TRIAL")) {
			
			$total_team_member_after_invite = $team_size + count($processed_emails);
			
			try {
				$update_data_bill = [
					"team_size" => $total_team_member_after_invite,
					"modified" => DateTimeComponent::getDateTime()
				];
				$conditions_bill_update = [
					"where" => [
						["where" => ["account_id", "=", $logged_in_account]]
					]
				];

				$model_acc_billing->update($update_data_bill, $conditions_bill_update);

			} catch (\Exception $e) {
				$error_message = SHAppComponent::getValue("api_messages/DB_OPERATION_FAIL/error_message");
			}

			try {
				$update_data_sub = [
					"team_size" => $total_team_member_after_invite,
					"modified" => DateTimeComponent::getDateTime()
				];
				$conditions_sub_update = [
					"where" => [
						["where" => ["account_id", "=", $logged_in_account]]
					]
				];

				$model_acc_subscription_details->update($update_data_sub, $conditions_sub_update);

			} catch (\Exception $e) {
				$error_message = SHAppComponent::getValue("api_messages/DB_OPERATION_FAIL/error_message");
			}
		}
		
        return $response->withJson($output, 200);
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
        $model_user_master = new UserMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_user_master->checkRowValidity($row_id, $other_values);

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

        // To store request resource id
        $request_res_array = array();

        // To sotre all resource id which we have to insert
        $inserted_resource_ids = array();

        // To sotre all resource id which we have to delete
        $deleted_resource_ids = array();
        
        if (isset($request_params["first_name"])) {
            $request_data["first_name"] = $request_params["first_name"];
        }
        if (isset($request_params["last_name"])) {
            $request_data["last_name"] = $request_params["last_name"];
        }
        if (isset($request_params["phone"])) {
            $request_data["phone"] = $request_params["phone"];
        }
        if (isset($request_params["role"])) {
            $role = StringComponent::decodeRowId(trim($request_params["role"]));

            if (!empty($role)) {
                $request_data["role"] = $role;
            }
        }
        if (isset($request_params["teams"])) {
            $request_data["teams"] = $request_params["teams"];
        }
        if (isset($request_params["resources"])) {
            $request_data["resources"] = $request_params["resources"];

            foreach ($request_data["resources"] as $list_res) {
                array_push($request_res_array, StringComponent::decodeRowId($list_res)); 
            }
        }

        // Validate request
        $request_validations = [
            "first_name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "last_name" => [
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
                "first_name" => trim($request_data["first_name"]),
                "last_name" => trim($request_data["last_name"]),
                "modified" => DateTimeComponent::getDateTime()
            ];
            if (isset($request_data["phone"])) {
                $save_data["phone"] = trim($request_data["phone"]);
            }
            if (isset($request_data["role"])) {
                $save_data["role_id"] = $request_data["role"];
            }

            if ($model_user_master->save($save_data) !== false) {
                // Assign resources
                $model_user_resource = new UserResources();

                 // To get all active resources
                $condition = [
                    "fields" => [
                        "resource_id"
                    ],
                    "where" => [
                        ["where" => ["user_id","=",$row_id]],
                        ["where" => ["status","=",SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]],
                    ]
                ];

                $list_all_active_res =  $model_user_resource->fetchAll($condition);
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
                
                // Update all resources status to delete
                $condition = [
                    "where" => [
                        ["where" => ["user_id", "=", $row_id]]
                    ]
                ];
                $save_data = [
                    "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $updated = $model_user_resource->update($save_data, $condition);

                foreach ($request_data["resources"] as $resource) {
                    $resource_id = StringComponent::decodeRowId(trim($resource));

                    if (!empty($resource_id)) {
                        try {
                            // Fetch is data already exists
                            $condition = [
                                "fields" => [
                                    "ur.id"
                                ],
                                "where" => [
                                    ["where" => ["ur.user_id", "=", $row_id]],
                                    ["where" => ["ur.resource_id", "=", $resource_id]]
                                ]
                            ];
                            $already_exists = $model_user_resource->fetch($condition);

                            $save_data = [
                                "user_id" => $row_id,
                                "resource_id" => $resource_id,
                                "modified" => DateTimeComponent::getDateTime()
                            ];
                            if (!empty($already_exists["id"])) {
                                $save_data["id"] = $already_exists["id"];
                                $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                            }
                            $saved = $model_user_resource->save($save_data);

                        } catch(\Exception $e) { }
                    }
                }

                // Assign teams
                $model_account_team_members = new AccountTeamMembers();
                
                // Update all resources status to delete
                $condition = [
                    "where" => [
                        ["where" => ["user_id", "=", $row_id]]
                    ]
                ];
                $save_data = [
                    "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $updated = $model_account_team_members->update($save_data, $condition);

                foreach ($request_data["teams"] as $team) {
                    $team_id = StringComponent::decodeRowId(trim($team));

                    if (!empty($team_id)) {
                        try {
                            // Fetch is data already exists
                            $condition = [
                                "fields" => [
                                    "atm.id"
                                ],
                                "where" => [
                                    ["where" => ["atm.user_id", "=", $row_id]],
                                    ["where" => ["atm.account_team_id", "=", $team_id]]
                                ]
                            ];
                            $already_exists = $model_account_team_members->fetch($condition);

                            $save_data = [
                                "user_id" => $row_id,
                                "account_team_id" => $team_id,
                                "modified" => DateTimeComponent::getDateTime()
                            ];
                            if (!empty($already_exists["id"])) {
                                $save_data["id"] = $already_exists["id"];
                                $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                            }
                            $saved = $model_account_team_members->save($save_data);

                        } catch(\Exception $e) { }
                    }
                }

                  // Update all member's resource which belong to specific role
                if (!empty($deleted_resource_ids) || !empty($inserted_resource_ids)) {

                    if (!empty($row_id)) {

                        try {  
                                // Delete member resource for given role
                                if (!empty($deleted_resource_ids)) {

                                    foreach ($deleted_resource_ids as $list_delete) {

                                        $condition = [
                                            "where" => [
                                                ["where" => ["user_id", "=", $row_id]],
                                                ["where" => ["resource_id", "=", $list_delete]],
                                            ]
                                        ];
                                        $save_data = [
                                            "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                                            "modified" => DateTimeComponent::getDateTime()
                                        ];
                                        $model_user_resource->update($save_data, $condition);
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
                                                ["where" => ["user_id","=",$row_id]],
                                                ["where" => ["resource_id","=",$list_insert]],
                                            ]
                                        ];

                                        $resource_user_data = $model_user_resource->fetch($condition);

                                        // Update existing resource or Insert new resource
                                        if (!empty($resource_user_data)) {

                                            $save_data = [
                                                "id" => $resource_user_data['id'],
                                                "user_id" => $row_id,
                                                "resource_id" => $list_insert,
                                                "status" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                                                "modified" => DateTimeComponent::getDateTime()
                                            ];

                                            $model_user_resource->save($save_data);
                                        } else {

                                            $save_data = [
                                                "user_id" => $row_id,
                                                "role_id" => $role,
                                                "resource_id" => $list_insert,
                                                "status" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                                                "modified" => DateTimeComponent::getDateTime()
                                            ];

                                            $model_user_resource->save($save_data);
                                        }    
                                    }     
                                } 

                        // Update member auth token if it is active    
                        $model_user_auth_token = new UserAuthenticationTokens();
                        $current_timespan = DateTimeComponent::getDateTime();
                        $update_member_token = $model_user_auth_token->singleMemberTokenExpire($row_id,$logged_in_account,$current_timespan);

                        } catch(\Exception $e) { }
                    }
                }    

                $output["message"] = "Member details updated successfully.";

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

        // Check if record is valid
        $model_user_master = new UserMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_user_master->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get user information
        try {
            $condition = [
                "fields" => [
                    "um.id",
                    "um.first_name",
                    "um.last_name",
                    "um.email",
                    "um.phone",
                    "um.last_login",
                    "um.created",
                    "um.role_id",
                    "rm.name AS role_name"
                ],
                "where" => [
                    ["where" => ["um.id", "=", $row_id]]
                ],
                "join" => [
                    "role_master"
                ]
            ];
            $user_data = $model_user_master->fetch($condition);

            // Get users settings
            $model_user_settings = new UserSettings();


            $condition = [
                "fields" => [
                    "acv.code",
                    "us.value"
                ],
                "where" => [
                    ["where" => ["us.user_id", "=", $user_data["id"]]]
                ],
                "join" => [
                    "app_constant_vars"
                ]
            ];
            $preferences_data = $model_user_settings->fetchAll($condition);

            $user_preferences = null;
            foreach ($preferences_data as $setting) {
                $user_preferences[$setting["code"]] = $setting["value"];
            }

            // Get user resources
            $model_user_resource = new UserResources();

            // Get resources name
            $model_resource_master = new ResourceMaster();

            $condition = [
                "fields" => [
                    "ur.resource_id"
                ],
                "where" => [
                    ["where" => ["ur.user_id", "=", $row_id]],
                    ["where" => ["ur.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $resources_data = $model_user_resource->fetchAll($condition);

            $user_resources = [];
            $resources_names = [];
            foreach ($resources_data as $resource) {
                $user_resources[] = StringComponent::encodeRowId($resource["resource_id"]);

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

            // Get user teams
            $model_account_team_members = new AccountTeamMembers();

            $condition = [
                "fields" => [
                    "atm.account_team_id",
                    "at.manager_id",
                    "at.owner_id",
                    "at.name"
                ],
                "where" => [
                    ["where" => ["atm.user_id", "=", $row_id]],
                    ["where" => ["atm.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ],
                "join" => [
                    "account_teams"
                ]
            ];
            $teams_data = $model_account_team_members->fetchAll($condition);

            $user_teams = [];
            foreach ($teams_data as $team) {
                $user_teams[] = [
                    "team_id" => StringComponent::encodeRowId($team["account_team_id"]),
                    "managed_by" => StringComponent::encodeRowId($team["manager_id"]),
                    "owned_by" => StringComponent::encodeRowId($team["owner_id"]),
                    "team_name" => $team["name"]
                ];
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $last_login = DateTimeComponent::convertDateTime($user_data["last_login"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);
        $signup_date = DateTimeComponent::convertDateTime($user_data["created"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT);

        $output = [
            "first_name" => $user_data["first_name"],
            "last_name" => $user_data["last_name"],
            "email" => $user_data["email"],
            "phone" => $user_data["phone"],
            "role_id" => StringComponent::encodeRowId($user_data["role_id"]),
            "role" => $user_data["role_name"],
            "last_login" => DateTimeComponent::showDateTime($last_login, $user_timezone),
            "last_login_timestamp" => $user_data["last_login"],
            "member_since" => DateTimeComponent::showDateTime($signup_date, $user_timezone),
            "member_since_timestamp" => $user_data["created"],
            "preferences" => $user_preferences,
            "user_resources" => $user_resources,
            "resources_names" => $resources_names,
            "user_teams" => $user_teams
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
        $model_user_master = new UserMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_user_master->checkRowValidity($row_id, $other_values);

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
            if ($model_user_master->save($save_data) !== false) {
                $output["message"] = "Member deleted successfully.";

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
        $model_user_master = new UserMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_user_master->checkRowValidity($row_id, $other_values);

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
            "STATUS_ACTIVE",
            "STATUS_REMOVED"
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
            if ($model_user_master->save($save_data) !== false) {
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
     * Resent invitation mail to member
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function resendInvitation(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        $admin_user_type = SHAppComponent::getDefaultCustomerAdminUserType();

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_user_master = new UserMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_user_master->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Check for valid request
        $model_user_invitations = new UserInvitations();

        try {
            $condition = [
                "fields" => [
                    "ui.id"
                ],
                "where" => [
                    ["where" => ["ui.user_id", "=", $row_id]],
                    ["where" => ["ui.account_id", "=", $logged_in_account]],
                    ["where" => ["ui.joined_at", "=", 0]],
                    ["where" => ["ui.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $data = $model_user_invitations->fetch($condition);

            if (empty($data)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/USER_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get Account Admin Details
        try {
            $condition_admin_detail = [
                "fields" => [
                    "first_name",
                    "last_name",
                    "email"
                ],
                "where" => [
                    ["where" => ["account_id", "=", $logged_in_account]],
                    ["where" => ["user_type_id", "=", $admin_user_type]]
                ]
            ];

            $admin_data = $model_user_master->fetch($condition_admin_detail);

        } catch(\Exception $e) {
            return SHAppComponent::getValue("api_messages/DB_OPERATION_FAIL");
        }

        // Get User Email Address
        try {
            $condition_user = [
                "fields" => [
                    "um.email"
                ],
                "where" => [
                    ["where" => ["um.id", "=", $row_id]],
                    ["where" => ["um.account_id", "=", $logged_in_account]]
                ]
            ];
            $user_data = $model_user_master->fetch($condition_user);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $code = StringComponent::encodeRowId([$row_id, $logged_in_account]);
        
        $request_origin_url = SHAppComponent::getRequestOrigin();

        //Send email to user to verify account
        $info["smtp_details"]["host"] = HOST;
        $info["smtp_details"]["port"] = PORT;
        $info["smtp_details"]["encryption"] = ENCRYPTION;
        $info["smtp_details"]["username"] = USERNAME;
        $info["smtp_details"]["password"] = PASSWORD;

        $info["from_email"] = FROM_EMAIL;
        $info["from_name"] = FROM_NAME;

        $info["to"] = $user_data["email"];
        $info["cc"] = '';
        $info["bcc"] = '';
        $info["subject"] = "You have been added to a SalesHandy team";
        $info["content"] = file_get_contents(\EMAIL_TEPLATES_FOLDER . "/invite_email.html");
        $info["content"] = str_replace("{ConfirmEmailLink}", $request_origin_url . "/user/accept-invitation/".$code, $info["content"]);
        $info["content"] = str_replace("{FullName}", $admin_data["first_name"]." ".$admin_data["last_name"], $info["content"]);
        $info["content"] = str_replace("{Email}", $admin_data["email"], $info["content"]);

        $result = TransactionMailsComponent::mailSendSmtp($info);

        if ($result["success"] == true) {
            $result_message = "Invitation email sent successfully.";
        } else {
            $result_message = $result["message"];
        }

        $output["message"] = $result_message;

        return $response->withJson($output, 200);
    }

    /**
     * Get activity of member
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function activity(ServerRequestInterface $request, ResponseInterface $response, $args) {
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
        $model_user_master = new UserMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_user_master->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * Get resources assigned to member
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function resources(ServerRequestInterface $request, ResponseInterface $response, $args) {
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
        $model_user_master = new UserMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_user_master->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get list of resources
        $model_user_resource = new UserResources();

        $condition = [
            "fields" => [
                "ur.resource_id"
            ],
            "where" => [
                ["where" => ["ur.user_id", "=", $row_id]],
                ["where" => ["ur.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
            ]
        ];
        $resources = $model_user_resource->fetchAll($condition);

        foreach ($resources as $resource) {
            $output[] = StringComponent::encodeRowId($resource["resource_id"]);
        }

        return $response->withJson($output, 200);
    }

}
