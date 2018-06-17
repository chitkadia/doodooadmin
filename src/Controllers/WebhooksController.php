<?php
/**
 * Webhooks related functionality
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
use \App\Models\Webhooks;
use \App\Models\AccountSubscriptionDetails;
use \App\Models\AccountBillingMaster;
use \App\Models\AccountPaymentDetails;
use \App\Models\AccountInvoiceDetails;

class WebhooksController extends AppController {

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
        if (!empty($params["keyword"])) {
            $query_params["query"] = SHAppComponent::prepareSearchText($params["keyword"]);
        }

        // Other values for condition
        $other_values = [
            "user_id" => $logged_in_user,
            "account_id" => $logged_in_account,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        // Get data
        $model_webhooks = new Webhooks();

        try {
            $data = $model_webhooks->getListData($query_params, $other_values);
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
            $status = array_search($row["status"], $app_constants);

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "name" => $row["name"],
                "resource_id" => StringComponent::encodeRowId($row["resource_id"]),
                "resource_name" => $row["resource_name"],
                "resource_condition" => $row["resource_condition"],
                "post_url" => $row["post_url"],
                "status" => $status
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
        if (isset($request_params["resource_id"])) {
            $request_data["resource_id"] = $request_params["resource_id"];
        }
        if (isset($request_params["resource_condition"])) {
            $request_data["resource_condition"] = $request_params["resource_condition"];
        }
        if (isset($request_params["post_url"])) {
            $request_data["post_url"] = $request_params["post_url"];
        }
        if (isset($request_params["status"])) {
            $request_data["status"] = $request_params["status"];
        }
        
        // Validate request
        $request_validations = [
            "resource_id" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "resource_condition" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "post_url" => [
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
        $model_webhooks = new Webhooks();

        try {
            $save_data = [
                "account_id" => $logged_in_account,
                "user_id" => $logged_in_user,
                "source_id" => $logged_in_source,
                "name" => $request_data["name"],
                "resource_id" => StringComponent::decodeRowId($request_data["resource_id"]),
                "resource_condition" => trim($request_data["resource_condition"]),
                "post_url" => trim($request_data["post_url"]),
                "status" => $request_data["status"],
                "created" => DateTimeComponent::getDateTime(),
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($model_webhooks->save($save_data) !== false) {
                $inserted_webhook_id = $model_webhooks->getLastInsertId();

                $output["id"] = StringComponent::encodeRowId($inserted_webhook_id);
                $output["message"] = "Data saved successfully.";

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            echo $e->getMessage(); exit;
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
        $model_webhooks = new Webhooks();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_webhooks->checkRowValidity($row_id, $other_values);

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
        if (isset($request_params["resource_id"])) {
            $request_data["resource_id"] = $request_params["resource_id"];
        }
        if (isset($request_params["resource_condition"])) {
            $request_data["resource_condition"] = $request_params["resource_condition"];
        }
        if (isset($request_params["post_url"])) {
            $request_data["post_url"] = $request_params["post_url"];
        }
        if (isset($request_params["status"])) {
            $request_data["status"] = $request_params["status"];
        }

        // Validate request
        $request_validations = [
            "resource_id" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "resource_condition" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "post_url" => [
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
                "name" => $request_data["name"],
                "resource_id" => StringComponent::decodeRowId($request_data["resource_id"]),
                "resource_condition" => trim($request_data["resource_condition"]),
                "post_url" => trim($request_data["post_url"]),
                "status" => $request_data["status"],
                "modified" => DateTimeComponent::getDateTime()
            ];

            if ($model_webhooks->save($save_data) !== false) {
                $output["message"] = "Data saved successfully.";

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
        $model_webhooks = new Webhooks();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_webhooks->checkRowValidity($row_id, $other_values);

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
                    "w.id",
                    "w.name",
                    "w.resource_id",
                    "w.resource_condition",
                    "w.post_url",
                    "w.status"
                ],
                "where" => [
                    ["where" => ["w.id", "=", $row_id]]
                ]
            ];
            $output = $model_webhooks->fetch($condition);

            if (!empty($output["resource_id"])) {
                $output["resource_id"] = StringComponent::encodeRowId($output["resource_id"]);
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

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
        $model_webhooks = new Webhooks();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_webhooks->checkRowValidity($row_id, $other_values);

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
            if ($model_webhooks->save($save_data) !== false) {
                $output["message"] = "Record deleted successfully.";

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
        $model_webhooks = new Webhooks();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_webhooks->checkRowValidity($row_id, $other_values);

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
            "STATUS_BLOCKED"
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
            if ($model_webhooks->save($save_data) !== false) {
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

    public function stripeWebhooks(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $route = $request->getAttribute("route");

        \Stripe\Stripe::setApiKey(\SK_TEST_KEY); //SET API KEY
        $postdata = file_get_contents("php://input");
        $event_json = json_decode($postdata);

        switch($event_json->type) {

            case 'customer.created':
                
                break;

            case 'customer.source.created':
                
                break;

            case 'customer.source.deleted':

                break;

            case 'customer.updated':
                
                break;

            case 'invoice.created':
                
                break;

            case 'invoice.payment_succeeded':
                $subscription_id = $event_json->data->object->subscription;
                $customer_id = $event_json->data->object->customer;
                $active_subscription_id = $event_json->data->object->metadata->active_subscription_id;
                $account_id = $event_json->data->object->metadata->account_id;
                $credit_balance = $event_json->data->object->ending_balance;
                $charge_id = $event_json->data->object->charge;
                $paid_at = $event_json->data->object->date;
                $invoice_id = $event_json->data->object->id;
                $total_amount = $event_json->data->object->total;
                                
                $update_data = [
                    "credit_balance" => $credit_balance,
                    "total_amount" => $total_amount,
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $conditions_sub_update = [
                    "where" => [
                        ["where" => ["id", "=", $active_subscription_id]],
                        ["where" => ["account_id", "=", $account_id]],
                        ["where" => ["tp_subscription_id", "=", $subscription_id]],
                        ["where" => ["tp_customer_id", "=", $customer_id]]
                    ],
                ];
                $this->updateSubscriptionTable($update_data, $conditions_sub_update, "subscription", $response); // update data into subscription table
                
                $update_data_billing = [
                    "credit_balance" => $credit_balance,
                    "next_subscription_updates" => $postdata,
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $conditions_sub_update_billing = [
                    "where" => [
                        ["where" => ["current_subscription_id", "=", $active_subscription_id]],
                        ["where" => ["account_id", "=", $account_id]]
                    ],
                ];
                $this->updateSubscriptionTable($update_data_billing, $conditions_sub_update_billing, "billing", $response); // update data into billing table
                
                $update_data_pay_data = [
                    "tp_payload" => $postdata,
                    "tp_payment_id" => $charge_id,
                    "paid_at" => $paid_at,
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $conditions_sub_update_pay_data = [
                    "where" => [
                        ["where" => ["account_subscription_id", "=", $active_subscription_id]],
                        ["where" => ["account_id", "=", $account_id]]
                    ],
                ];
                $this->updateSubscriptionTable($update_data_pay_data, $conditions_sub_update_pay_data, "payment", $response); // update data into payment table
                                
                $update_data_invc_data = [
                    "invoice_number" => $invoice_id,
                    "credit_amount" => $credit_balance
                ];
                $conditions_sub_update_invc_data = [
                    "where" => [
                        ["where" => ["account_subscription_id", "=", $active_subscription_id]],
                        ["where" => ["account_id", "=", $account_id]]
                    ],
                ];
                $this->updateSubscriptionTable($update_data_invc_data, $conditions_sub_update_invc_data, "invoice", $response); // update data into subscription table
                
                break;

            case 'charge.succeeded':
                $status = $event_json->data->object->status;
                $active_subscription_id = $event_json->data->object->metadata->active_subscription_id;
                $account_id = $event_json->data->object->metadata->account_id;
                
                if ($status == "succeeded") {
                    $status_code = SHAppComponent::getValue("app_constant/STATUS_SUCCESS");
                    $billing_code = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                } else if ($status == "pending") {
                    $status_code = SHAppComponent::getValue("app_constant/STATUS_PENDING");
                    $billing_code = SHAppComponent::getValue("app_constant/STATUS_INACTIVE");
                } else if ($status == "failed") {
                    $status_code = SHAppComponent::getValue("app_constant/STATUS_FAIL");
                    $billing_code = SHAppComponent::getValue("app_constant/STATUS_INACTIVE");
                } else {
                    $status_code = SHAppComponent::getValue("app_constant/STATUS_PENDING");
                    $billing_code = SHAppComponent::getValue("app_constant/STATUS_INACTIVE");
                }
                
                $update_data = [
                    "status" => $status_code,
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $conditions_sub_update = [
                    "where" => [
                        ["where" => ["id", "=", $active_subscription_id]],
                        ["where" => ["account_id", "=", $account_id]],
                        ["where" => ["tp_subscription_id", "=", $subscription_id]],
                        ["where" => ["tp_customer_id", "=", $customer_id]]
                    ],
                ];
                $this->updateSubscriptionTable($update_data, $conditions_sub_update, "subscription", $response); // update data into subscription table
                
                $update_data_bill = [
                    "status" => $billing_code,
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $conditions_sub_update_bill = [
                    "where" => [
                        ["where" => ["current_subscription_id", "=", $active_subscription_id]],
                        ["where" => ["account_id", "=", $account_id]]
                    ],
                ];
                $this->updateSubscriptionTable($update_data_bill, $conditions_sub_update_bill, "billing", $response); // update data into billing table
                
                break;

            case 'customer.subscription.created':
                $subscription_id = $event_json->data->object->id;
                $customer_id = $event_json->data->object->customer;
                $current_subscription_start_date = $event_json->data->object->current_period_start;
                $current_subscription_end_date = $event_json->data->object->current_period_end;
                $active_subscription_id = $event_json->data->object->metadata->active_subscription_id;
                $account_id = $event_json->data->object->metadata->account_id;
                                
                $update_data = [
                    "start_date" => $current_subscription_start_date,
                    "end_date" => $current_subscription_end_date,
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $conditions_sub_update = [
                    "where" => [
                        ["where" => ["id", "=", $active_subscription_id]],
                        ["where" => ["account_id", "=", $account_id]],
                        ["where" => ["tp_subscription_id", "=", $subscription_id]],
                        ["where" => ["tp_customer_id", "=", $customer_id]]
                    ],
                ];
                $this->updateSubscriptionTable($update_data, $conditions_sub_update, "subscription", $response); // update data into subscription table
                
                break;

            case 'invoiceitem.created':

                break;

            case 'customer.subscription.updated':
                $subscription_id = $event_json->data->object->id;
                $customer_id = $event_json->data->object->customer;
                $current_subscription_start_date = $event_json->data->object->current_period_start;
                $current_subscription_end_date = $event_json->data->object->current_period_end;
                $active_subscription_id = $event_json->data->object->metadata->active_subscription_id;
                $account_id = $event_json->data->object->metadata->account_id;
                                
                $update_data = [
                    "start_date" => $current_subscription_start_date,
                    "end_date" => $current_subscription_end_date,
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $conditions_sub_update = [
                    "where" => [
                        ["where" => ["id", "=", $active_subscription_id]],
                        ["where" => ["account_id", "=", $account_id]],
                        ["where" => ["tp_subscription_id", "=", $subscription_id]],
                        ["where" => ["tp_customer_id", "=", $customer_id]]
                    ],
                ];
                $this->updateSubscriptionTable($update_data, $conditions_sub_update, "subscription", $response); // update data into subscription table
                
                break;

            case 'invoiceitem.updated':

                break;

            case 'invoice.created':

                break;
            
            case 'charge.failed':
                $status = $event_json->data->object->status;
                $active_subscription_id = $event_json->data->object->metadata->active_subscription_id;
                $account_id = $event_json->data->object->metadata->account_id;
                
                if ($status == "succeeded") {
                    $status_code = SHAppComponent::getValue("app_constant/STATUS_SUCCESS");
                    $billing_code = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                } else if ($status == "pending") {
                    $status_code = SHAppComponent::getValue("app_constant/STATUS_PENDING");
                    $billing_code = SHAppComponent::getValue("app_constant/STATUS_INACTIVE");
                } else if ($status == "failed") {
                    $status_code = SHAppComponent::getValue("app_constant/STATUS_FAIL");
                    $billing_code = SHAppComponent::getValue("app_constant/STATUS_INACTIVE");
                } else {
                    $status_code = SHAppComponent::getValue("app_constant/STATUS_PENDING");
                    $billing_code = SHAppComponent::getValue("app_constant/STATUS_INACTIVE");
                }
                
                $update_data = [
                    "status" => $status_code,
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $conditions_sub_update = [
                    "where" => [
                        ["where" => ["id", "=", $active_subscription_id]],
                        ["where" => ["account_id", "=", $account_id]],
                        ["where" => ["tp_subscription_id", "=", $subscription_id]],
                        ["where" => ["tp_customer_id", "=", $customer_id]]
                    ],
                ];
                $this->updateSubscriptionTable($update_data, $conditions_sub_update, "subscription", $response); // update data into subscription table
                
                $update_data_bill = [
                    "status" => $billing_code,
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $conditions_sub_update_bill = [
                    "where" => [
                        ["where" => ["current_subscription_id", "=", $active_subscription_id]],
                        ["where" => ["account_id", "=", $account_id]]
                    ],
                ];
                $this->updateSubscriptionTable($update_data_bill, $conditions_sub_update_bill, "billing", $response); // update data into billing table
                
                break;

            default:
                throw new \Exception('Unexpected webhook type form Stripe! ' . $event_json->type);
        }
    }
    
    public function updateSubscriptionTable($data, $condition, $table, $response) {
        if ($table == "subscription") {
            $model = new AccountSubscriptionDetails();
        } else if ($table == "billing") {
            $model = new AccountBillingMaster();
        } else if ($table == "payment") {
            $model = new AccountPaymentDetails();
        } else {
            $model = new AccountInvoiceDetails();
        }
        try {
            $model->update($data, $condition);
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
    }

}
