<?php
/**
 * Used to generate application configuration variables file
 */
namespace App\Commands;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Interop\Container\ContainerInterface;
use \App\Models\AppVarsModel;

class AppVarsConfigCommand extends AppCommand {

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Generate application configuration file
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $args) {
        // Store data
        $json_data_array = [];

        $model = new AppVarsModel();

        // Get global constant vars
        try {
            $model->setTableName("app_constant_vars", "acv");

            $data = $model->fetchList("acv.code", "acv.val");

            $json_data_array["app_constant"] = $data;

            // Set labels values
            $data = $model->fetchList("acv.code", "acv.name");

            $json_data_array["app_constant_label"] = $data;

        } catch(\Exception $e) {
            echo "Error: " . $e->getMessage();
            echo "\n";
        }

        // Set required variables
        $status_active = 1;
        $flag_yes = 1;
        $flag_no = 0;

        if (isset($json_data_array["app_constant"]["STATUS_ACTIVE"])) {
            $status_active = (int) $json_data_array["app_constant"]["STATUS_ACTIVE"];
        }
        if (isset($json_data_array["app_constant"]["FLAG_YES"])) {
            $flag_yes = (int) $json_data_array["app_constant"]["FLAG_YES"];
        }
        if (isset($json_data_array["app_constant"]["FLAG_NO"])) {
            $flag_no = (int) $json_data_array["app_constant"]["FLAG_NO"];
        }

        // Get default resources
        try {
            $model->setTableName("resource_master", "rm");

            $condition = [
                "where" => [
                    ["where" => ["rm.status", "=", $status_active]],
                    ["where" => ["rm.is_always_assigned", "=", $flag_yes]]
                ]
            ];
            $data = $model->fetchList("rm.id", "rm.api_endpoint", $condition);

            foreach ($data as $row) {
                $row_endpoints = json_decode($row, true);

                for ($i = 0; $i < count($row_endpoints); $i++) {
                    $json_data_array["default_resources"][$row_endpoints[$i]] = true;
                }
            }

        } catch(\Exception $e) {
            echo "Error: " . $e->getMessage();
            echo "\n";
        }

        // Get public resources
        try {
            $model->setTableName("resource_master", "rm");

            $condition = [
                "where" => [
                    ["where" => ["rm.status", "=", $status_active]],
                    ["where" => ["rm.is_secured", "=", $flag_no]]
                ]
            ];
            $data = $model->fetchList("rm.id", "rm.api_endpoint", $condition);

            foreach ($data as $row) {
                $row_endpoints = json_decode($row, true);

                for ($i = 0; $i < count($row_endpoints); $i++) {
                    $json_data_array["public_resources"][$row_endpoints[$i]] = true;
                }
            }

        } catch(\Exception $e) {
            echo "Error: " . $e->getMessage();
            echo "\n";
        }

        // Get roles
        try {
            $model->setTableName("role_master", "rm");

            $condition = [
                "where" => [
                    ["where" => ["rm.status", "=", $status_active]],
                    ["where" => ["rm.for_customers", "=", $flag_yes]]
                ]
            ];
            $data = $model->fetchList("rm.code", "rm.id", $condition);

            $json_data_array["role"] = $data;

        } catch(\Exception $e) {
            echo "Error: " . $e->getMessage();
            echo "\n";
        }

        // Get API messages and codes
        try {
            $model->setTableName("api_messages", "am");

            $condition = [
                "fields" => [
                    "am.code",
                    "am.http_code",
                    "am.error_code",
                    "am.error_message"
                ],
                "where" => [
                    ["where" => ["am.status", "=", $status_active]]
                ]
            ];
            $data = $model->fetchAll($condition);

            foreach ($data as $d) {
                $json_data_array["api_messages"][$d["code"]] = [
                    "http_code" => $d["http_code"],
                    "error_code" => $d["error_code"],
                    "error_message" => $d["error_message"]
                ];
            }

        } catch(\Exception $e) {
            echo "Error: " . $e->getMessage();
            echo "\n";
        }

        // Get master table records
        $master_tables = [
            "source" => "source_master",
            "social_login" => "social_login_master",
            "user_type" => "user_type_master",
            "payment_method" => "payment_method_master",
            "email_sending_method" => "email_sending_method_master",
            "document_source" => "document_source_master",
            "plan" => "plan_master"
        ];

        foreach ($master_tables as $key => $val) {
            try {
                $model->setTableName($val, "t");

                $condition = [
                    "where" => [
                        ["where" => ["t.status", "=", $status_active]]
                    ]
                ];
                $data = $model->fetchList("t.code", "t.id", $condition);

                $json_data_array[$key] = $data;

            } catch(\Exception $e) {
                echo "Error: " . $e->getMessage();
                echo "\n";
            }
        }

        // Get data of actions
        try {
            $action_groups = [];
            $model->setTableName("action_master", "am");

            $condition = [
                "fields" => [
                    "am.id",
                    "am.code",
                    "am.name"
                ],
                "where" => [
                    ["where" => ["am.status", "=", $status_active]],
                    ["whereNull" => "am.parent_id"]
                ],
                "order_by" => [
                    "am.sort_order ASC"
                ]
            ];
            $group_data = $model->fetchAll($condition);

            // Get sub actions of action groups
            foreach ($group_data as $data) {
                $condition = [
                    "fields" => [
                        "am.code",
                        "am.name"
                    ],
                    "where" => [
                        ["where" => ["am.status", "=", $status_active]],
                        ["where" => ["am.parent_id", "=", $data["id"]]]
                    ],
                    "order_by" => [
                        "am.sort_order ASC"
                    ]
                ];
                $rows = $model->fetchAll($condition);

                $action_groups[] = [
                    "code" => $data["code"],
                    "name" => $data["name"],
                    "sub_actions" => $rows
                ];
            }
            $json_data_array["action_groups"] = $action_groups;

        } catch(\Exception $e) {
            echo "Error: " . $e->getMessage();
            echo "\n";
        }

        //prepare sub_action and action grouping
        try {
            $actions = [];
            $action_list = [];
            $activity_template = [];

            $model->setTableName("action_master", "am");

            $condition = [
                "fields" => [
                    "am.id",
                    "am.code"
                ],
                "where" => [
                    ["whereNull" => "am.parent_id"]
                ],
                "order_by" => [
                    "am.sort_order ASC"
                ]
            ];
            $group_data = $model->fetchAll($condition);

            // Get sub actions of action groups
            foreach ($group_data as $data) {                
                $condition = [
                    "where" => [
                        ["where" => ["am.parent_id", "=", $data["id"]]]
                    ],
                    "order_by" => [
                        "am.sort_order ASC"
                    ]
                ];

                $rows = $model->fetchList("am.code", "am.id", $condition);
               
                $rows["ACTION_GROUP_ID"] = $data["id"];
                $actions[$data["code"]] = $rows;

                $rows[$data["code"]] = $data["id"];
                $action_lists [] = array_flip($rows);

                $templates = $model->fetchList("am.id", "am.notify_template", $condition);
                $activity_template [] = $templates;
            }

            $action_lists = array_reduce($action_lists, function($last, $row) {
               return $last + $row;
            }, array());

            $activity_template = array_reduce($activity_template, function($last, $row) {
               return $last + $row;
            }, array());

            $json_data_array["actions"] = $actions;
            $json_data_array["actions_list"] = $action_lists;
            $json_data_array["activity_template"] = $activity_template;

        } catch(\Exception $e) {
            echo "Error: " . $e->getMessage();
            echo "\n";
        }
        
        // Write data into file
        try {
            file_put_contents(APP_VARS_CONFIG_FILE, json_encode($json_data_array));

        } catch(\Exception $e) {
            echo "Error: " . $e->getMessage();
            echo "\n";
        }
    }
}
