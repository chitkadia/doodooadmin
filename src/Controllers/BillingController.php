<?php

/**
 * Accounts & Billing related functionality
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
use \App\Models\PlanMaster;
use \App\Models\CouponMaster;
use \App\Models\ValidPlanCoupons;
use \App\Models\AccountPaymentDetails;
use \App\Models\AccountInvoiceDetails;
use \App\Models\AccountSubscriptionDetails;
use \App\Models\AccountBillingMaster;
use \App\Models\AccountSendingMethods;
use \App\Models\UserMaster;
use \App\Models\AccountSubscriptionLineItems;
use \App\Components\Mailer\TransactionMailsComponent;

class BillingController extends AppController {

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Get plan details
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function planDetails(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $route = $request->getAttribute("route");
        $code = $route->getArgument("code");

        // Validate request
        if (empty($code)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        // Check if record is valid
        $model_plan_master = new PlanMaster();

        try {
            // Other values for condition
            $other_values = [
                "inactive" => SHAppComponent::getValue("app_constant/STATUS_INACTIVE")
            ];

            $valid = $model_plan_master->checkRowValidity($other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Fetch data
        try {
            $condition = [
                "fields" => [
                    "id",
                    "code",
                    "name",
                    "amount",
                    "currency",
                    "mode",
                    "validity_in_days",
                    "configuration"
                ],
                "where" => [
                    ["where" => ["code", "=", $code]],
                    ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_INACTIVE")]]
                ]
            ];
            $row = $model_plan_master->fetch($condition);

            if (!$row) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $plan_configuration = json_decode($row["configuration"], true);
        $row_email_acc = [];
        if (count($plan_configuration) > 0) {
            $email_acc_plan_id = $plan_configuration["ea_plan"];
            try {
                $condition_email_acc = [
                    "fields" => [
                        "id",
                        "code",
                        "name",
                        "amount",
                        "currency",
                        "mode",
                        "validity_in_days"
                    ],
                    "where" => [
                        ["where" => ["id", "=", $email_acc_plan_id]],
                        ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_INACTIVE")]]
                    ]
                ];
                $row_email_acc = $model_plan_master->fetch($condition_email_acc);
            } catch (\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
        }

        // Prepare output of data
        $output = [
            "id" => StringComponent::encodeRowId($row["id"]),
            "code" => $row["code"],
            "name" => $row["name"],
            "amount" => $row["amount"],
            "currency" => $row["currency"],
            "mode" => $row["mode"],
            "validity_in_days" => $row["validity_in_days"],
            "email_acc_plan_id" => StringComponent::encodeRowId($row_email_acc["id"]),
            "email_acc_plan_code" => $row_email_acc["code"],
            "email_acc_plan_name" => $row_email_acc["name"],
            "email_acc_plan_amount" => $row_email_acc["amount"],
            "email_acc_plan_currency" => $row_email_acc["currency"],
            "email_acc_plan_mode" => $row_email_acc["mode"],
            "email_acc_plan_validity_in_days" => $row_email_acc["validity_in_days"],
            "configuration" => $plan_configuration
        ];

        return $response->withJson($output, 200);
    }

    /**
     * Check coupon validity
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function checkCoupon(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];

        if (isset($request_params["coupon"])) {
            $request_data["coupon"] = $request_params["coupon"];
        }

        if (isset($request_params["amount"])) {
            $request_data["amount"] = $request_params["amount"];
        }

        if (isset($request_params["plan_id"])) {
            $request_data["plan_id"] = $request_params["plan_id"];
        }

        // Validate request
        $request_validations = [
            "coupon" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "amount" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "plan_id" => [
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

        // Check coupon
        $model_coupon_master = new CouponMaster();
        $model_obj = new ValidPlanCoupons();

        try {
            // Other values for condition
            $other_values = [
                "inactive" => SHAppComponent::getValue("app_constant/STATUS_INACTIVE"),
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "coupon" => $request_data["coupon"],
                "amount" => $request_data["amount"],
                "current_timestamp" => DateTimeComponent::getDateTime()
            ];

            $valid_return = $model_coupon_master->checkCouponAvailable($other_values);
            $valid = $valid_return["id"];
            $discount_type = $valid_return["discount_type"];
            $discount_value = $valid_return["discount_value"];

            if (empty($valid_return)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/INVALID_COUPON_CODE");
            } else {
                $check_coupon_condition = [
                    "fields" => [
                        "id"
                    ],
                    "where" => [
                        ["where" => ["plan_id", "=", StringComponent::decodeRowId($request_data["plan_id"])]],
                        ["where" => ["coupon_id", "=", $valid]],
                        ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                    ],
                ];
                $fetch_coupon_valid_or_not = $model_obj->fetch($check_coupon_condition);

                if (!$fetch_coupon_valid_or_not) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/INVALID_COUPON_CODE");
                }
                $output["discount_type"] = $discount_type;
                $output["discount_value"] = $discount_value;
                $output["message"] = "Coupon Successfully Applied.";
            }
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * Purchase plan
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function buy(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
        $api_key = \Stripe\Stripe::setApiKey(\SK_TEST_KEY); //SET API PUBLIC TEST KEY
        $customer_id = "";
        $subscription_id = "";
        $proration_timestamp = time();

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $plan_configuration_array = SHAppComponent::getPlanConfiguration();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Get app constants
        $subscription_action = SHAppComponent::getValue("app_constant/PAYMENT_SUBSCRIPTION");
        $upgrade_action = SHAppComponent::getValue("app_constant/PAYMENT_UPGRADE");
        $downgrade_action = SHAppComponent::getValue("app_constant/PAYMENT_DOWNGRADE");
        $add_seat_action = SHAppComponent::getValue("app_constant/PAYMENT_TEAM_INCREASE");
        $em_acc_purchase_action = SHAppComponent::getValue("app_constant/EMAIL_ACCOUNT_PURCHASE");
        $em_acc_team_size_incr_action = SHAppComponent::getValue("app_constant/EM_ACC_TEAM_SIZE_INCREASE");

        // Free user can't do anything related to subscribe, upgrade, downgrade or add-seats
        if (!isset($plan_configuration_array["ea_plan"]) && $request_params["action"] != $subscription_action) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/UPGRADE_ACCOUNT");
        }

        // Get model objects
        $model = new AccountSubscriptionDetails();
        $plan_class_obj = new PlanMaster();
        $billing_model = new AccountBillingMaster();
        $payment_model = new AccountPaymentDetails();
        $invoice_model = new AccountInvoiceDetails();
        $user_master = new UserMaster();
        $acc_sub_line_item = new AccountSubscriptionLineItems();

        // Fetch request data
        $request_data = [];

        if (isset($request_params["amount"])) {
            $request_data["amount"] = $request_params["amount"];
        }

        if (isset($request_params["stripeToken"])) {
            $stripeToken = $request_params["stripeToken"];
        }

        if (isset($request_params["coupon_id"])) {
            $coupon_id = $request_params["coupon_id"];
        } else {
            $coupon_id = "";
        }

        if (isset($request_params["plan_id"])) {
            $request_data["plan_id"] = StringComponent::decodeRowId($request_params["plan_id"]);
        }

        // Set payment method id
        if (isset($request_params["payment_method"])) {
            if ($request_params["payment_method"] == 1) {
                $payment_method = SHAppComponent::getValue("payment_method/2CO");
            } else {
                $payment_method = SHAppComponent::getValue("payment_method/STRIPE");
            }
        } else {
            $payment_method = SHAppComponent::getValue("payment_method/STRIPE");
        }

        if ($request_params["action"] == $subscription_action || $request_params["action"] == $add_seat_action) {
            if (isset($request_params["team_size"])) {
                $request_data["team_size"] = $request_params["team_size"];
                $request_total_seat = $request_params["team_size"];
            }
        }

        // Validate request
        if ($request_params["action"] == $subscription_action || $request_params["action"] == $add_seat_action) {
            $request_validations = [
                "team_size" => [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ],
                "amount" => [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ],
                "plan_id" => [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ]
            ];
        } else {
            $request_validations = [
                "amount" => [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ],
                "plan_id" => [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ]
            ];
        }
        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        // Other values for condition for plan details
        $other_values = [
            "inactive" => SHAppComponent::getValue("app_constant/STATUS_INACTIVE"),
            "plan_id" => $request_data["plan_id"]
        ];
        $plan_amt_value = $plan_class_obj->getPlanDetails($other_values); // Get plan details

        $plan_configuration = json_decode($plan_amt_value["configuration"], true);

        if ($request_params["action"] == $subscription_action) {
            $team_size_requested = $request_params["team_size"];
            $configured_team_size = $plan_configuration["team_member_size"];
            if (isset($plan_configuration["team_member_size"]) && $plan_configuration["team_member_size"] != "~") {
                if ($team_size_requested > $configured_team_size) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/INVITE_MEMBER_MAX_SEAT", $plan_configuration["team_member_size"]);
                }
            }
        }

        // Set action performed
        switch ($request_params["action"]) {
            case $add_seat_action:
                $action_perform = SHAppComponent::getValue("app_constant_label/PAYMENT_TEAM_INCREASE");
                break;
            case $upgrade_action:
                $action_perform = SHAppComponent::getValue("app_constant_label/PAYMENT_UPGRADE");
                break;
            case $downgrade_action:
                $action_perform = SHAppComponent::getValue("app_constant_label/PAYMENT_DOWNGRADE");
                break;
            case $em_acc_purchase_action:
                $action_perform = SHAppComponent::getValue("app_constant_label/EMAIL_ACCOUNT_PURCHASE");
                break;
            case $em_acc_team_size_incr_action:
                $action_perform = SHAppComponent::getValue("app_constant_label/EM_ACC_TEAM_SIZE_INCREASE");
                break;
            default:
                $action_perform = SHAppComponent::getValue("app_constant_label/PAYMENT_SUBSCRIPTION");
                break;
        }

        try {
            $model->beginTransaction(); // Begin the transaction and set autocommit to 0
            // Other values for condition to get owner email
            $owner_values = [
                "active" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                "account_id" => $logged_in_account,
                "user_type_id" => SHAppComponent::getValue("user_type/CLIENT_ADMIN")
            ];
            $account_owner_details = $user_master->getAccountUserData($owner_values); // Get Account Owner Email Address

            if (isset($request_params["email"])) {
                $request_email = $request_params["email"];
            } else {
                $request_email = $account_owner_details["email"];
            }

            $em_acc_plan_id = $plan_configuration["ea_plan"];

            // Other values for condition
            $em_acc_other_values = [
                "inactive" => SHAppComponent::getValue("app_constant/STATUS_INACTIVE"),
                "plan_id" => $em_acc_plan_id
            ];
            $em_acc_plan_amt_value = $plan_class_obj->getPlanDetails($em_acc_other_values); // Get email account plan details
            // Other values payload for condition
            $other_values_billing = [
                "delete" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "account_id" => $logged_in_account,
                "active_required" => false
            ];
            $act_sub_val = $billing_model->getActiveBillingDetails($other_values_billing); // Get Billing Data From Billing Table

            if (!empty($act_sub_val)) {
                // Other values payload for plan selection
                $other_values_act_plan = [
                    "inactive" => SHAppComponent::getValue("app_constant/STATUS_INACTIVE"),
                    "plan_id" => $act_sub_val["plan_id"]
                ];
                $active_plan_amt_value = $plan_class_obj->getPlanDetails($other_values_act_plan); // Get active plan details
            }

            $other_values_subscription = [
                "active" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                "account_id" => $logged_in_account
            ];
            $user_cust_sub_id = $model->getUserExistDetails($other_values_subscription); // Get Subscription Data From Subscription Table If Available
            // Customer Create OR Retrieve Start
            if (empty($act_sub_val) && empty($user_cust_sub_id)) {
                try {
                    //CREATE CUSTOMER IN STRIPE START
                    $other_values_customer = [
                        "email" => $account_owner_details["email"],
                        "source" => $stripeToken
                    ];
                    $customer = $billing_model->createCustomerStripe($other_values_customer);
                } catch (\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/STRIPE_RELATED", $e->getMessage());
                }

                $subscription_details = null;
            } else {
                if ($user_cust_sub_id["tp_customer_id"] != "") {
                    $customer_check_payload = [
                        "tp_customer_id" => $user_cust_sub_id["tp_customer_id"]
                    ];
                    $customer_id = $user_cust_sub_id["tp_customer_id"];
                    $customer_check = $billing_model->checkStripeCustomerExist($customer_check_payload);
                } else {
                    $customer_check = false;
                }

                if (!$customer_check) {
                    try {
                        //CREATE CUSTOMER IN STRIPE START
                        $other_values_customer_Stripe = [
                            "email" => $account_owner_details["email"],
                            "source" => $stripeToken
                        ];
                        $customer = $billing_model->createCustomerStripe($other_values_customer_Stripe);
                    } catch (\Exception $e) {
                        // Fetch error code & message and return the response
                        return ErrorComponent::outputError($response, "api_messages/STRIPE_RELATED", $e->getMessage());
                    }

                    $subscription_details = null;
                } else {
                    try {
                        $customer = \Stripe\Customer::retrieve($customer_id); //RETRIEVE CUSTOMER
                    } catch (\Exception $e) {
                        // Fetch error code & message and return the response
                        return ErrorComponent::outputError($response, "api_messages/STRIPE_FETCH", $e->getMessage());
                    }

                    if (!empty($user_cust_sub_id["tp_subscription_id"])) {
                        try {
                            $subscription_details = \Stripe\Subscription::retrieve($user_cust_sub_id["tp_subscription_id"]);
                        } catch (\Exception $e) {
                            $subscription_details = null;
                        }
                    } else {
                        $subscription_details = null;
                    }
                }
            }
            // Customer Create OR Retrieve End

            if (empty($subscription_details)) {
                $subscription_check = null;
            } else {
                if ($subscription_details->customer == $user_cust_sub_id["tp_customer_id"] && $subscription_details->status != "canceled") {
                    $subscription_check = $subscription_details;
                } else {
                    $subscription_check = null;
                }
            }

            // If => Subscription Empty , Else => Subscription Available
            if (empty($subscription_check)) {
                if ($request_params["action"] == $upgrade_action || $request_params["action"] == $downgrade_action || $request_params["action"] == $add_seat_action) {
                    if ($request_params["action"] == $upgrade_action) {
                        $action_text = SHAppComponent::getValue("app_constant_label/PAYMENT_SUBSCRIPTION");
                    } else if ($request_params["action"] == $add_seat_action) {
                        $action_text = SHAppComponent::getValue("app_constant_label/PAYMENT_TEAM_INCREASE");
                    } else {
                        $action_text = SHAppComponent::getValue("app_constant_label/PAYMENT_DOWNGRADE");
                    }
                    return ErrorComponent::outputError($response, "Error while " . $action_text . ", as you have no active subscription.");
                }

                $total_plan_amount = $plan_amt_value["amount"] * $plan_amt_value["mode"];
                if (isset($request_params["email_account_seats"])) {
                    $total_seat_count_val = $request_total_seat + $request_params["email_account_seats"];
                    $amount_total = $total_plan_amount * $total_seat_count_val;
                } else {
                    $amount_total = $total_plan_amount * $request_total_seat;
                }


                $subscription_array = [];
                $email_account_subscription_array = [];
                $items_array = [];

                // If Coupon Available Calculate Discount & Total Amount Start
                if (!empty($coupon_id)) {
                    $coupon_code_value = $this->getCouponData($coupon_id, $amount_total); // Get Coupon Data from Coupon Code

                    $post_coupon_id = $coupon_code_value["coupon_id"];
                    $post_discount_type = $coupon_code_value["discount_type"];
                    $post_discount_value = $coupon_code_value["discount_value"];
                    $post_discount_amount = $coupon_code_value["discount_amount"];
                    $post_total_amount = $coupon_code_value["total_amount"];

                    $subscription_array = [
                        "plan" => $plan_amt_value["code"],
                        "quantity" => $request_params["team_size"]
                    ];

                    if (isset($request_params["email_account_seats"])) {
                        if (count($plan_configuration) > 0) {
                            $em_acc_plan_code = $em_acc_plan_amt_value["code"];
                            $email_account_subscription_array = [
                                "plan" => $em_acc_plan_code,
                                "quantity" => $request_params["email_account_seats"]
                            ];
                            $items_array = [
                                "coupon" => $request_params["coupon_id"],
                                "items" => [
                                    $subscription_array,
                                    $email_account_subscription_array
                                ]
                            ];
                        } else {
                            return ErrorComponent::outputError($response, "No plan details available.");
                        }
                    } else {
                        $items_array = [
                            $subscription_array
                        ];
                    }
                } else {
                    $post_coupon_id = null;
                    $post_discount_type = SHAppComponent::getValue("app_constant/DISCOUNT_TYPE_AMT");
                    $post_discount_value = 0;
                    $post_discount_amount = 0;
                    $post_total_amount = $amount_total;

                    $subscription_array = [
                        "plan" => $plan_amt_value["code"],
                        "quantity" => $request_params["team_size"]
                    ];

                    if (isset($request_params["email_account_seats"])) {
                        if (count($plan_configuration) > 0) {
                            $em_acc_plan_code = $em_acc_plan_amt_value["code"];
                            $email_account_subscription_array = [
                                "plan" => $em_acc_plan_code,
                                "quantity" => $request_params["email_account_seats"]
                            ];

                            $items_array = [
                                "items" => [
                                    $subscription_array,
                                    $email_account_subscription_array
                                ]
                            ];
                        } else {
                            return ErrorComponent::outputError($response, "No plan details available.");
                        }
                    } else {
                        $items_array = [
                            $subscription_array
                        ];
                    }
                }
                // If Coupon Available Calculate Discount & Total Amount End
                // Create a subscription Start
                try {
                    $subscribe = $customer->subscriptions->create($items_array); // Create subscription
                } catch (\Exception $e) {
                    return ErrorComponent::outputError($response, "api_messages/STRIPE_RELATED", $e->getMessage());
                }
                // Create a subscription End

                if (!empty($subscribe)) {
                    $post_plan_id = $request_data["plan_id"];
                    $post_team_size = $request_params["team_size"];
                    $post_email_acc_seats = $request_params["email_account_seats"];
                    $post_credit_balance = 0.00;
                    $post_amount = $amount_total;
                    $post_next_subscription_id = null;
                    $post_tp_subscription_id = $subscribe->id;
                    $post_tp_customer_id = $customer->id;
                }

                $post_billing_credit_balance = 0.00;
                $post_billing_plan_id = $request_data["plan_id"];
                $post_billing_team_size = $request_params["team_size"];
                $post_billing_email_account_seat = $request_params["email_account_seats"];
                $post_billing_next_subscription_updates = $plan_amt_value["configuration"];

                $post_payment_amount_paid = $post_total_amount;
                $post_payment_type = $request_params["action"];

                $post_patment_amount = $amount_total;
                $post_patment_discount_amount = $post_discount_amount;
                $post_patment_total_amount = $post_total_amount;
            } else {
                if ($request_params["action"] == $subscription_action) {
                    return ErrorComponent::outputError($response, "You already have an active subscription. You can't subscribe to new plan.");
                }

                if ($request_params["action"] == $add_seat_action) {
                    $team_size_requested = $request_params["team_size"] + $act_sub_val["team_size"];
                    $configured_team_size = $plan_configuration_array["team_member_size"];
                    if (isset($plan_configuration_array["team_member_size"]) && $plan_configuration_array["team_member_size"] != "~") {
                        if ($team_size_requested > $configured_team_size) {
                            // Fetch error code & message and return the response
                            return ErrorComponent::outputError($response, "api_messages/INVITE_MEMBER_MAX_SEAT", $plan_configuration_array["team_member_size"]);
                        }
                    }
                }

                $total_plan_amount = $plan_amt_value["amount"] * $plan_amt_value["mode"];
                $email_acc_total_plan_amount = $em_acc_plan_amt_value["amount"] * $em_acc_plan_amt_value["mode"];
                $user_team_size = $act_sub_val["team_size"];
                $email_acc_seats = $act_sub_val["email_acc_seats"];

                $get_selected_prorate = [
                    "team_size" => $user_team_size,
                    "email_acc_seats" => $email_acc_seats,
                    "user_seat_amount" => $total_plan_amount,
                    "email_acc_amount" => $email_acc_total_plan_amount,
                ];
                $selected_prorate_amount = $billing_model->calculateProrationAmount($get_selected_prorate, $customer); // Get Selected Plan Unused Amount

                if ($request_params["action"] == $upgrade_action || $request_params["action"] == $downgrade_action) {
                    try {
                        $total_seat_acount_sum = $act_sub_val["team_size"] + $act_sub_val["email_acc_seats"];

                        $total_amt_user_seat = $total_plan_amount * $act_sub_val["team_size"];
                        $total_amt_email_seat = $email_acc_total_plan_amount * $act_sub_val["email_acc_seats"];
                        $amount_total_updown = $total_amt_user_seat + $total_amt_email_seat;

                        $total_team_members = $act_sub_val["team_size"];
                        $sub_update_quant = $act_sub_val["team_size"];
                        $total_email_account_seats = $act_sub_val["email_acc_seats"];
                        $sub_update_quant_email_acc = $act_sub_val["email_acc_seats"];
                        $total_email_account_seats_sub = $act_sub_val["email_acc_seats"];

                        $current_prorate_calc_arr = $billing_model->getActivePlanProrationAmount($customer->id); // Get Current Plan Unused Amount
                        if (!$current_prorate_calc_arr["valid"]) {
                            return ErrorComponent::outputError($response, "api_messages/STRIPE_RELATED", $current_prorate_calc_arr["error_message"]);
                        } else {
                            $current_prorate_amount = $current_prorate_calc_arr["prorate_amount"];
                        }

                        if ($plan_amt_value["mode"] == $active_plan_amt_value["mode"]) {
                            $total_amount_to_pay_calc = $selected_prorate_amount - $current_prorate_amount;
                        } else {
                            $total_amount_to_pay_calc = $amount_total_updown - $current_prorate_amount;
                        }

                        if ($total_amount_to_pay_calc < 0) {
                            $total_amount_to_pay = 0.00;
                        } else {
                            $total_amount_to_pay = $total_amount_to_pay_calc;
                        }
                    } catch (\Exception $e) {
                        return ErrorComponent::outputError($response, "api_messages/STRIPE_RELATED", $e->getMessage());
                    }

                    $updated_plan_id = $request_data["plan_id"];

                    $team_size_insert = $act_sub_val["team_size"];

                    $post_main_amount = $selected_prorate_amount;
                } else {
                    if (isset($request_params["email_account_seats"]) && isset($request_params["team_size"])) {
                        $total_amt_user_seat_add_seat = $total_plan_amount * $request_params["team_size"];
                        $total_amt_email_seat_add_seat = $email_acc_total_plan_amount * $request_params["email_account_seats"];
                        $total_amount_to_pay = $total_amt_user_seat_add_seat + $total_amt_email_seat_add_seat;
                    } else if (!isset($request_params["email_account_seats"]) && isset($request_params["team_size"])) {
                        $total_amount_to_pay = $total_plan_amount * $request_params["team_size"];
                    } else if (isset($request_params["email_account_seats"]) && !isset($request_params["team_size"])) {
                        $total_amount_to_pay = $email_acc_total_plan_amount * $request_params["email_account_seats"];
                    }

                    $post_main_amount = $total_amount_to_pay;
                    if (isset($request_params["team_size"])) {
                        $total_team_members = $act_sub_val["team_size"] + $request_params["team_size"];
                        $sub_update_quant = $request_params["team_size"];
                        $team_size_insert = $request_params["team_size"];
                    } else {
                        $total_team_members = $act_sub_val["team_size"];
                        $sub_update_quant = 0;
                        $team_size_insert = 0;
                    }
                    if (isset($request_params["email_account_seats"])) {
                        $total_email_account_seats = $act_sub_val["email_acc_seats"] + $request_params["email_account_seats"];
                        $sub_update_quant_email_acc = $request_params["email_account_seats"];
                        $total_email_account_seats_sub = $request_params["email_account_seats"];
                    } else {
                        $total_email_account_seats = $act_sub_val["email_acc_seats"];
                        $sub_update_quant_email_acc = 0;
                        $total_email_account_seats_sub = 0;
                    }
                    $updated_plan_id = $act_sub_val["plan_id"];
                }

                if ($plan_amt_value["mode"] == $subscription_action) {
                    if (\BILLING_INTERVAL == "month") {
                        $interval = "month";
                    } else {
                        $interval = "day";
                    }
                } else {
                    if (\BILLING_INTERVAL == "month") {
                        $interval = "year";
                    } else {
                        $interval = "month";
                    }
                }

                if ($request_params["action"] != $downgrade_action) {
                    if ($act_sub_val["credit_balance"] != "") {
                        if ($total_amount_to_pay - $act_sub_val["credit_balance"] < 0) {
                            $total_amount_value = $selected_prorate_amount;
                        } else {
                            $total_amount_value = $total_amount_to_pay - $act_sub_val["credit_balance"];
                        }
                    } else {
                        $total_amount_value = $total_amount_to_pay;
                    }
                } else {
                    $total_amount_value = 0.00;
                }

                // If Coupon Available Calculate Discount & Total Amount Start
                if (!empty($coupon_id) && $request_params["action"] == $upgrade_action) {
                    $coupon_code_value = $this->getCouponData($coupon_id, $total_amount_value); // Get Coupon Data from Coupon Code
                    $post_coupon_id = $coupon_code_value["coupon_id"];
                    $post_discount_type = $coupon_code_value["discount_type"];
                    $post_discount_value = $coupon_code_value["discount_value"];
                    $post_discount_amount = $coupon_code_value["discount_amount"];
                    $post_total_amount = $coupon_code_value["total_amount"];
                } else {
                    $post_coupon_id = null;
                    $post_discount_type = SHAppComponent::getValue("app_constant/DISCOUNT_TYPE_AMT");
                    $post_discount_value = 0;
                    $post_discount_amount = 0;
                    $post_total_amount = $total_amount_to_pay;
                }
                // If Coupon Available Calculate Discount & Total Amount End

                $credit_bal = $act_sub_val["credit_balance"];

                // Update subscription Start
                try {
                    $subscription_id = $user_cust_sub_id["tp_subscription_id"];
                    $subscribe = $customer->subscriptions->retrieve($subscription_id);
                    $itemID = $subscribe->items->data[0]->id;
                    $itemID_second_sub = $subscribe->items->data[1]->id;

                    if (count($plan_configuration) > 0) {
                        $em_acc_plan_code = $em_acc_plan_amt_value["code"];
                    } else {
                        return ErrorComponent::outputError($response, "No plan details available.");
                    }

                    if (!empty($coupon_id) && $request_params["action"] == $upgrade_action) {
                        $items_array = [
                            "coupon" => $request_params["coupon_id"],
                            "items" => array(
                                array(
                                    "id" => $itemID,
                                    "plan" => $plan_amt_value["code"],
                                    "quantity" => $total_team_members
                                ),
                                array(
                                    "id" => $itemID_second_sub,
                                    "plan" => $em_acc_plan_code,
                                    "quantity" => $total_email_account_seats
                                )
                            )
                        ];
                    } else {
                        $items_array = [
                            "items" => array(
                                array(
                                    "id" => $itemID,
                                    "plan" => $plan_amt_value["code"],
                                    "quantity" => $total_team_members
                                ),
                                array(
                                    "id" => $itemID_second_sub,
                                    "plan" => $em_acc_plan_code,
                                    "quantity" => $total_email_account_seats
                                )
                            )
                        ];
                    }

                    if ($interval == $subscribe->items->data[0]->plan->interval) {
                        \Stripe\Subscription::update($subscription_id, array(
                            $items_array
                        ));
                    } else {
                        \Stripe\Subscription::update($subscription_id, array(
                            $items_array,
                            "proration_date" => $proration_timestamp
                        ));
                    }
                    // Update subscription End
                    // If plan interval is same then generate invoice and pay it
                    if ($interval == $subscribe->items->data[0]->plan->interval) {
                        $invoice1 = \Stripe\Invoice::create(array("customer" => $customer->id));
                        $invoicepay = \Stripe\Invoice::retrieve($invoice1->id);
                        $invoicepay->pay();
                    }
                } catch (\Exception $e) {
                    return ErrorComponent::outputError($response, "api_messages/STRIPE_RELATED", $e->getMessage());
                }

                $post_plan_id = $updated_plan_id;
                $post_team_size = $team_size_insert;
                $post_email_acc_seats = $total_email_account_seats_sub;
                $post_credit_balance = $credit_bal;
                $post_amount = $post_main_amount;
                $post_next_subscription_id = $act_sub_val["current_subscription_id"];
                $post_tp_subscription_id = $subscribe->id;
                $post_tp_customer_id = $customer->id;

                $post_billing_credit_balance = $credit_bal;
                $post_billing_plan_id = $updated_plan_id;
                $post_billing_team_size = $total_team_members;
                $post_billing_email_account_seat = $total_email_account_seats;

                $post_payment_amount_paid = $total_amount_value;
                $post_payment_type = $request_params["action"];

                $post_patment_amount = $post_main_amount;
                $post_patment_discount_amount = 0;
                $post_patment_total_amount = $total_amount_value;
            }

            // Set postdata for subscription table
            $postData['coupon_id'] = $post_coupon_id;
            $postData["discount_type"] = $post_discount_type;
            $postData["discount_value"] = $post_discount_value;
            $postData["discount_amount"] = $post_discount_amount;
            $postData["total_amount"] = $post_total_amount;

            $postData["account_id"] = $logged_in_account;
            $postData["plan_id"] = $post_plan_id;
            $postData["team_size"] = $post_team_size;
            $postData["email_acc_seats"] = $post_email_acc_seats;
            $postData["credit_balance"] = $post_credit_balance;
            $postData["amount"] = $post_amount;
            $postData["payment_method_id"] = $payment_method;
            $postData['start_date'] = DateTimeComponent::getDateTime();
            $postData['next_subscription_id'] = $post_next_subscription_id;
            $postData['tp_subscription_id'] = $post_tp_subscription_id;
            $postData['tp_customer_id'] = $post_tp_customer_id;
            $postData['type'] = $post_payment_type;
            $postData['status'] = 0;
            $postData['created'] = DateTimeComponent::getDateTime();
            $postData['modified'] = DateTimeComponent::getDateTime();

            try {
                $sub_insert = $model->save($postData); // Insert data into subscription table and return last insert id
            } catch (\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

            try {
                // Insert data into account subscription line item table
                $line_item_insert_data = [
                    "user_account_plan_id" => $post_plan_id,
                    "user_account_team_size" => $post_team_size,
                    "email_account_plan_id" => $em_acc_plan_id,
                    "email_account_seat" => $post_email_acc_seats,
                    "current_subscription_id" => $sub_insert,
                    "total_amount" => $post_total_amount,
                    "created" => DateTimeComponent::getDateTime(),
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $acc_sub_line_item->save($line_item_insert_data); // Insert data into account subscription line item table
            } catch (\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

            // Set postdata for billing table insertion
            if (empty($subscription_check)) {
                $update_data = [
                    "credit_balance" => $post_billing_credit_balance,
                    "account_id" => $logged_in_account,
                    "plan_id" => $post_billing_plan_id,
                    "team_size" => $post_billing_team_size,
                    "email_acc_seats" => $post_billing_email_account_seat,
                    "current_subscription_id" => $sub_insert,
                    "configuration" => $post_billing_next_subscription_updates,
                    "status" => 0,
                    "created" => DateTimeComponent::getDateTime(),
                    "modified" => DateTimeComponent::getDateTime()
                ];
            } else {
                if ($request_params["action"] == $upgrade_action || $request_params["action"] == $downgrade_action) {
                    $update_data = [
                        "plan_id" => $post_billing_plan_id,
                        "team_size" => $post_billing_team_size,
                        "email_acc_seats" => $post_billing_email_account_seat,
                        "current_subscription_id" => $sub_insert,
                        "configuration" => $plan_amt_value["configuration"],
                        "credit_balance" => $post_billing_credit_balance,
                        "status" => 0,
                        "modified" => DateTimeComponent::getDateTime()
                    ];
                } else {
                    $update_data = [
                        "plan_id" => $post_billing_plan_id,
                        "team_size" => $post_billing_team_size,
                        "email_acc_seats" => $post_billing_email_account_seat,
                        "current_subscription_id" => $sub_insert,
                        "credit_balance" => $post_billing_credit_balance,
                        "status" => 0,
                        "modified" => DateTimeComponent::getDateTime()
                    ];
                }
            }
            $conditions_bill_update = [
                "where" => [
                    ["where" => ["account_id", "=", $logged_in_account]],
                    ["where" => ["current_subscription_id", "=", $act_sub_val["current_subscription_id"]]],
                ],
            ];

//            if ($user_cust_sub_id["tp_customer_id"] == "") {
//                try {
//                    $billing_insert = $billing_model->save($postData_Billing); // Insert data into billing table
//                } catch (\Exception $e) {
//                    // Fetch error code & message and return the response
//                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
//                }
//            } else {
            try {
                $updated_data = $billing_model->update($update_data, $conditions_bill_update); // update data into billing table
            } catch (\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
//            }

            try {
                $customer->email = $account_owner_details["email"];
                if ($request_params["action"] == $add_seat_action) {
                    $action_to_show = $post_team_size;
                } else {
                    $action_to_show = $plan_amt_value["code"];
                }
                $customer->description = $request_email . " " . $action_perform . " to " . $action_to_show;
                if ($sub_insert != null) {
                    $customer->metadata = array("user_id" => $account_owner_details["id"], "account_id" => $logged_in_account, "active_subscription_id" => $sub_insert, "customer_key" => $account_owner_details["email"]);
                } else {
                    $customer->metadata = array("user_id" => $account_owner_details["id"], "account_id" => $logged_in_account, "customer_key" => $account_owner_details["email"]);
                }
                $customer->save();
            } catch (\Exception $e) {
                return ErrorComponent::outputError($response, "api_messages/STRIPE_RELATED", $e->getMessage());
            }

            if ($request_params["action"] != $downgrade_action) {
                if (isset($request_params["stripeToken"]) && $request_params["stripeToken"] != "") {
                    // CHECK IF CARD IS SAME OR NOT
                    try {
                        $billing_model->updateCardifNotSame($request_params["stripeToken"], $customer);
                    } catch (\Exception $e) {
                        return ErrorComponent::outputError($response, "api_messages/STRIPE_RELATED", $e->getMessage());
                    }
                }
            }

            if ($request_params["action"] != $downgrade_action) {
                try {
                    // Insert data into payment table
                    $payment_insert_data = [
                        "account_id" => $logged_in_account,
                        "account_subscription_id" => $sub_insert,
                        "amount_paid" => $post_payment_amount_paid,
                        "payment_method_id" => $payment_method,
                        "tp_payload" => 0,
                        "tp_payment_id" => 0,
                        "type" => $post_payment_type,
                        "paid_at" => DateTimeComponent::getDateTime(),
                        "created" => DateTimeComponent::getDateTime(),
                        "modified" => DateTimeComponent::getDateTime()
                    ];
                    $payment_insert = $payment_model->save($payment_insert_data); // Insert data into payment table and return last insert id
                } catch (\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

                try {
                    // Insert data into invoice table
                    $invoice_insert_data = [
                        "invoice_number" => 0,
                        "account_id" => $logged_in_account,
                        "account_subscription_id" => $sub_insert,
                        "account_payment_id" => $payment_insert,
                        "amount" => $post_patment_amount,
                        "discount_amount" => $post_patment_discount_amount,
                        "credit_amount" => $post_credit_balance,
                        "total_amount" => $post_patment_total_amount,
                        "file_copy" => 0,
                        "created" => DateTimeComponent::getDateTime()
                    ];
                    $invoice_model->save($invoice_insert_data); // Insert data into invoice table
                } catch (\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
                }
            }

            if ($model->commit()) {
                if ($request_params["action"] == $subscription_action) {
                    $resp_message = "Subscription successfully created";
                } else if ($request_params["action"] == $upgrade_action) {
                    $resp_message = "Plan upgraded successfully";
                } else if ($request_params["action"] == $downgrade_action) {
                    $resp_message = "Plan downgraded successfully";
                } else {
                    $resp_message = "Seat(s) added successfully";
                }
                $output["message"] = $resp_message;

                if ($request_params["action"] == $subscription_action || $request_params["action"] == $upgrade_action || $request_params["action"] == $downgrade_action) {
                    $account_sending_method = new AccountSendingMethods();
                    $account_sending_method->setQuota($post_billing_plan_id, 0, $logged_in_account);
                }

                if ($request_params["action"] == $subscription_action || $request_params["action"] == $upgrade_action || $request_params["action"] == $add_seat_action || $request_params["action"] == $em_acc_purchase_action || $request_params["action"] == $em_acc_team_size_incr_action) {
                    $subject_msg;
                    if ($request_params["action"] == $subscription_action) {
                        $subject_msg = "Subscription successfully created.";
                    } else if ($request_params["action"] == $upgrade_action) {
                        $subject_msg = "Plan upgraded successfully.";
                    } else if ($request_params["action"] == $add_seat_action || $request_params["action"] == $em_acc_purchase_action || $request_params["action"] == $em_acc_team_size_incr_action) {
                        $subject_msg = "Seat(s) added successfully.";
                    }

                    $billing_content = "Your " . $subject_msg . ""
                        . " You will get your receipt shortly.";
                    //Send email to user to verify account
                    $info["smtp_details"]["host"] = HOST;
                    $info["smtp_details"]["port"] = PORT;
                    $info["smtp_details"]["encryption"] = ENCRYPTION;
                    $info["smtp_details"]["username"] = USERNAME;
                    $info["smtp_details"]["password"] = PASSWORD;

                    $info["from_email"] = FROM_EMAIL;
                    $info["from_name"] = FROM_NAME;

                    $info["to"] = $account_owner_details["email"];
                    $info["cc"] = '';
                    $info["bcc"] = '';
                    $info["subject"] = $subject_msg;
                    $info["content"] = file_get_contents(\EMAIL_TEPLATES_FOLDER . "/billing_email.html");
                    $info["content"] = str_replace("{FirstName}", $account_owner_details["first_name"], $info["content"]);
                    $info["content"] = str_replace("{BillingContent}", $billing_content, $info["content"]);

                    $result = TransactionMailsComponent::mailSendSmtp($info);
                }
            }
        } catch (Exception $e) {
            $model->rollBack(); // Rollback the current transaction
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * 
     */
    public function getCouponData($coupon_id, $amount_total) {
        $coupon_model = new CouponMaster();
        // Other values payload for coupon condition
        $other_values_coupon = [
            "active" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
            "code" => $coupon_id,
            "amount_total" => $amount_total
        ];
        $coupon_code_value = $coupon_model->setCouponValue($other_values_coupon); // Get Coupon Data From Coupon Table
        return $coupon_code_value;
    }

    /**
     * Purchase additional seats
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function addSeat(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        return $response->withJson($output, 200);
    }

    /**
     * Upgrade account
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function upgrade(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        return $response->withJson($output, 200);
    }

    /**
     * Downgrade account
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function downgrade(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        return $response->withJson($output, 200);
    }

    /**
     * Cancel subscription of account
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function cancelSubscription(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
        $api_key = \Stripe\Stripe::setApiKey(\SK_TEST_KEY); //SET API PUBLIC TEST KEY
        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        $account_billing_master = new AccountBillingMaster();
        $account_subscription_model = new AccountSubscriptionDetails();

        try {
            $other_values_billing = [
                "active" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                "account_id" => $logged_in_account,
                "active_required" => true
            ];
            $current_subscription_id = $account_billing_master->getActiveBillingDetails($other_values_billing);
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if (!$current_subscription_id) {
            $output["message"] = SHAppComponent::getValue("api_messages/STRIPE_NO_SUBSCRIPTION");
        } else {
            $billing_model = new AccountBillingMaster();
            try {
                $condition = [
                    "fields" => [
                        "tp_subscription_id",
                        "tp_customer_id"
                    ],
                    "where" => [
                        ["where" => ["id", "=", $current_subscription_id["current_subscription_id"]]]
                    ]
                ];
                $row_subscription = $account_subscription_model->fetch($condition);

                if ($row_subscription['tp_customer_id'] == "") {
                    return ErrorComponent::outputError($response, "api_messages/STRIPE_NO_SUBSCRIPTION");
                }

                try {
                    $customer = \Stripe\Customer::retrieve($row_subscription['tp_customer_id']); //RETRIEVE CUSTOMER
                } catch (\Exception $e) {
                    return ErrorComponent::outputError($response, "api_messages/STRIPE_RELATED", $e->getMessage());
                }

                $cancel_subscription_object = json_decode($current_subscription_id["next_subscription_updates"], true);
                if (!empty($cancel_subscription_object["cancel_applied"])) {
                    if ($cancel_subscription_object["cancel_applied"] == 1) {
                        unset($cancel_subscription_object["cancel_applied"]);
                        $cancel_removed_json = json_encode($cancel_subscription_object);
                        $model_plan_master = new PlanMaster();
                        $subscription_id = $row_subscription["tp_subscription_id"];
                        $subscribe = $customer->subscriptions->retrieve($subscription_id);
                        $itemID = $subscribe->items->data[0]->id;
                        $itemID_second_sub = $subscribe->items->data[1]->id;

                        try {
                            $condition_plan_detail = [
                                "fields" => [
                                    "code",
                                    "configuration"
                                ],
                                "where" => [
                                    ["where" => ["id", "=", $current_subscription_id["plan_id"]]],
                                    ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_INACTIVE")]]
                                ]
                            ];
                            $row_plan_data = $model_plan_master->fetch($condition_plan_detail);

                            $plan_configuration = json_decode($row_plan_data["configuration"], true);
                            $email_acc_plan_id = $plan_configuration["ea_plan"];

                            $condition_plan_detail_1 = [
                                "fields" => [
                                    "code"
                                ],
                                "where" => [
                                    ["where" => ["id", "=", $email_acc_plan_id]],
                                    ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_INACTIVE")]]
                                ]
                            ];
                            $row_ea_plan_data = $model_plan_master->fetch($condition_plan_detail_1);
                        } catch (\Exception $e) {
                            // Fetch error code & message and return the response
                            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                        }

                        $subscription = \Stripe\Subscription::update($subscription_id, array(
                                "items" => array(
                                    array(
                                        "id" => $itemID,
                                        "plan" => $row_plan_data["code"],
                                        "quantity" => $current_subscription_id["team_size"]
                                    ),
                                    array(
                                        "id" => $itemID_second_sub,
                                        "plan" => $row_ea_plan_data["code"],
                                        "quantity" => $current_subscription_id["email_acc_seats"]
                                    )
                                )
                        ));

                        if ($subscription) {
                            $update_data_bill = [
                                "next_subscription_updates" => $cancel_removed_json
                            ];
                            $condition_bill_update = [
                                "where" => [
                                    ["where" => ["account_id", "=", $logged_in_account]],
                                    ["where" => ["status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                                ],
                            ];
                            try {
                                $billing_model->update($update_data_bill, $condition_bill_update); // update data into billing table
                            } catch (\Exception $e) {
                                // Fetch error code & message and return the response
                                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                            }
                            $output["message"] = "Subscription activated successfully.";
                        }
                    } else {
                        if ($subscription) {
                            $output["message"] = "Cancel subscription request already submitted.";
                        }
                    }
                } else {
                    $subscription = $customer->subscriptions->retrieve($row_subscription['tp_subscription_id'])->cancel(array('at_period_end' => true)); //CANCEL THE SUBSCRIPTION AT PLAN END

                    $cancel_subscription_object["cancel_applied"] = true;
                    $cancel_subscription_obj_json = json_encode($cancel_subscription_object);
                    if ($subscription) {
                        $update_data_bill = [
                            "next_subscription_updates" => $cancel_subscription_obj_json
                        ];
                        $condition_bill_update = [
                            "where" => [
                                ["where" => ["account_id", "=", $logged_in_account]],
                                ["where" => ["status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                            ],
                        ];
                        try {
                            $billing_model->update($update_data_bill, $condition_bill_update); // update data into billing table
                        } catch (\Exception $e) {
                            // Fetch error code & message and return the response
                            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                        }
                        $output["message"] = "Subscription cancelled successfully.";
                    }
                }
            } catch (\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
        }

        return $response->withJson($output, 200);
    }

    /**
     * Payment history
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function history(ServerRequestInterface $request, ResponseInterface $response, $args) {
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

        if (isset($params["page"])) {
            $query_params["page"] = (int) $params["page"];
        }
        if (isset($params["per_page"])) {
            $query_params["per_page"] = (int) $params["per_page"];
        }
        if (isset($params["order_by"])) {
            $query_params["order_by"] = trim($params["order_by"]);
        }
        if (isset($params["order"])) {
            $query_params["order"] = trim($params["order"]);
        }
        if (isset($params["query"])) {
            $query_params["query"] = SHAppComponent::prepareSearchText($params["query"]);
        }

        // Other values for condition
        $other_values = [
            "account_id" => $logged_in_account
        ];

        // Get data
        $model_account_bill_mst = new AccountPaymentDetails();

        try {
            $data = $model_account_bill_mst->getListData($query_params, $other_values);

            if (empty($data)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }
        } catch (\Exception $e) {
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
            if ($row["type"] == 1) {
                $subscription_type = SHAppComponent::getValue("app_constant_label/PAYMENT_SUBSCRIPTION");
            } else if ($row["type"] == 2) {
                $subscription_type = SHAppComponent::getValue("app_constant_label/PAYMENT_UPGRADE");
            } else if ($row["type"] == 3) {
                $subscription_type = SHAppComponent::getValue("app_constant_label/PAYMENT_DOWNGRADE");
            } else if ($row["type"] == 4) {
                $subscription_type = SHAppComponent::getValue("app_constant_label/PAYMENT_TEAM_INCREASE");
            } else if ($row["type"] == 5) {
                $subscription_type = SHAppComponent::getValue("app_constant_label/PAYMENT_RECURRING");
            } else if ($row["type"] == 6) {
                $subscription_type = SHAppComponent::getValue("app_constant_label/EMAIL_ACCOUNT_PURCHASE");
            } else {
                $subscription_type = SHAppComponent::getValue("app_constant_label/EM_ACC_TEAM_SIZE_INCREASE");
            }
            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "plan_name" => trim($row["plan_name"]),
                "plan_id" => $row["plan_id"],
                "payment_method" => trim($row["payment_method"]),
                "payment_method_id" => $row["payment_method_id"],
                "team_size" => $row["team_size"],
                "amount_paid" => $row["amount_paid"],
                "currency" => $row["currency"],
                "subscription_type" => $subscription_type,
                "invoice_id" => StringComponent::encodeRowId($row["invoice_id"]),
                "status" => $row["status"],
                "paid_at" => DateTimeComponent::convertDateTime($row["paid_at"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT),
                "created" => DateTimeComponent::convertDateTime($row["created"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT),
                "email_acc_size" => $row["email_acc_seat"]
            ];

            $output["rows"][] = $row_data;
        }

        return $response->withJson($output, 200);
    }

    /**
     * View payment details
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
        $model_account_pay_details = new AccountPaymentDetails();

        try {
            // Other values for condition
            $other_values = [
                "account_id" => $logged_in_account
            ];

            $valid = $model_account_pay_details->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Fetch data
        try {
            $condition = [
                "fields" => [
                    "apd.currency",
                    "apd.amount_paid",
                    "apd.payment_method_id",
                    "apd.tp_payment_id",
                    "apd.type",
                    "apd.paid_at",
                    "apd.status",
                    "apd.created",
                    "asd.plan_id",
                    "asd.team_size",
                    "asd.credit_balance",
                    "asd.coupon_id",
                    "asd.discount_type",
                    "asd.discount_value",
                    "asd.discount_amount",
                    "asd.total_amount",
                    "asd.start_date",
                    "asd.end_date",
                    "asd.tp_subscription_id",
                    "asd.tp_customer_id",
                    "cm.code AS coupon_code",
                    "pm.amount AS plan_amount",
                    "pm.mode AS plan_mode",
                    "aid.invoice_number",
                    "um.email"
                ],
                "where" => [
                    ["where" => ["apd.id", "=", $row_id]]
                ],
                "join" => [
                    "account_subscription_details",
                    // "payment_method_master",
                    "coupon_master",
                    "plan_master",
                    "account_invoice_details",
                    "user_master"
                ]
            ];
            $row = $model_account_pay_details->fetch($condition);
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if ($row["discount_type"] == SHAppComponent::getValue("app_constant/DISCOUNT_TYPE_AMT")) {
            $discount_show = SHAppComponent::getValue("app_constant_label/DISCOUNT_TYPE_AMT");
        } else {
            $discount_show = SHAppComponent::getValue("app_constant_label/DISCOUNT_TYPE_PER");
        }

        if ($row["payment_method_id"] == SHAppComponent::getValue("payment_method/2CO")) {
            $payment_method = "2Checkout";
        } else {
            $payment_method = "Stripe";
        }

        // Get plan name 
        switch ($row["plan_id"]) {
            case SHAppComponent::getValue("plan/FREE"):
                $plan_name = "Free Plan";
                break;
            case SHAppComponent::getValue("plan/REGULAR_MONTHLY"):
                $plan_name = "Regular Monthly";
                break;
            case SHAppComponent::getValue("plan/PLUS_MONTHLY"):
                $plan_name = "Plus Monthly";
                break;
            case SHAppComponent::getValue("plan/ENTERPRISE_MONTHLY"):
                $plan_name = "Enterprise Monthly";
                break;
            case SHAppComponent::getValue("plan/REGULAR_MONTHLY_TRIAL"):
                $plan_name = "Regular Monthly Trial";
                break;
            case SHAppComponent::getValue("plan/PLUS_MONTHLY_TRIAL"):
                $plan_name = "Plus Monthly Trial";
                break;
            case SHAppComponent::getValue("plan/ENTERPRISE_MONTHLY_TRIAL"):
                $plan_name = "Enterprise Monthly Trial";
                break;
            case SHAppComponent::getValue("plan/REGULAR_YEARLY"):
                $plan_name = "Regular Yearly";
                break;
            case SHAppComponent::getValue("plan/PLUS_YEARLY"):
                $plan_name = "Plus Yearly";
                break;
            case SHAppComponent::getValue("plan/ENTERPRISE_YEARLY"):
                $plan_name = "Enterprise Yearly";
                break;
            case SHAppComponent::getValue("plan/REGULAR_YEARLY_TRIAL"):
                $plan_name = "Regular Yearly Trial";
                break;
            case SHAppComponent::getValue("plan/PLUS_YEARLY_TRIAL"):
                $plan_name = "Plus Yearly Trial";
                break;
            case SHAppComponent::getValue("plan/ENTERPRISE_YEARLY_TRIAL"):
                $plan_name = "Enterprise Yearly Trial";
                break;
            default:
                $plan_name = "Free Plan";
        }

        // Get Payment made for
        switch ($row["type"]) {
            case SHAppComponent::getValue("app_constant/PAYMENT_SUBSCRIPTION"):
                $type = SHAppComponent::getValue("app_constant_label/PAYMENT_SUBSCRIPTION");
                break;
            case SHAppComponent::getValue("app_constant/PAYMENT_RECURRING"):
                $type = SHAppComponent::getValue("app_constant_label/PAYMENT_RECURRING");
                break;
            case SHAppComponent::getValue("app_constant/PAYMENT_TEAM_INCREASE"):
                $type = SHAppComponent::getValue("app_constant_label/PAYMENT_TEAM_INCREASE");
                break;
            case SHAppComponent::getValue("app_constant/PAYMENT_UPGRADE"):
                $type = SHAppComponent::getValue("app_constant_label/PAYMENT_UPGRADE");
                break;
            default:
                $type = SHAppComponent::getValue("app_constant_label/PAYMENT_SUBSCRIPTION");
        }

        // Get status of payment
        switch ($row["status"]) {
            case SHAppComponent::getValue("app_constant/STATUS_PENDING"):
                $status = SHAppComponent::getValue("app_constant_label/STATUS_PENDING");
                break;
            case SHAppComponent::getValue("app_constant/STATUS_SUCCESS"):
                $status = SHAppComponent::getValue("app_constant_label/STATUS_SUCCESS");
                break;
            case SHAppComponent::getValue("app_constant/STATUS_FAIL"):
                $status = SHAppComponent::getValue("app_constant_label/STATUS_FAIL");
                break;
            default:
                $status = SHAppComponent::getValue("app_constant_label/STATUS_PENDING");
        }

        // Prepare output of data
        $output = [
            "plan_name" => $plan_name,
            "plan_amount" => $row["plan_amount"],
            "plan_mode" => $row["plan_mode"],
            "team_size" => $row["team_size"],
            "credit_balance" => $row["credit_balance"],
            "coupon_code" => $row["coupon_code"],
            "discount_type" => $discount_show,
            "discount_value" => $row["discount_value"],
            "discount_amount" => $row["discount_amount"],
            "total_amount" => $row["total_amount"],
            "currency" => $row["currency"],
            "amount_paid" => $row["amount_paid"],
            "payment_method_name" => $payment_method,
            "tp_payment_id" => $row["tp_payment_id"],
            "type" => $type,
            "paid_at" => DateTimeComponent::convertDateTime($row["paid_at"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT),
            "status" => $status,
            "created" => DateTimeComponent::convertDateTime($row["created"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT),
            "plan_id" => $row["plan_id"],
            "start_date" => DateTimeComponent::convertDateTime($row["start_date"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT),
            "end_date" => DateTimeComponent::convertDateTime($row["end_date"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT),
            "subscription_id" => $row["tp_subscription_id"],
            "customer_id" => $row["tp_customer_id"],
            "payee_email" => $row["email"],
            "invoice_number" => $row["invoice_number"]
        ];

        return $response->withJson($output, 200);
    }

    /**
     * View invoice of payment
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function invoice(ServerRequestInterface $request, ResponseInterface $response, $args) {
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
        $model_account_invc_details = new AccountInvoiceDetails();

        try {
            // Other values for condition
            $other_values = [
                "account_id" => $logged_in_account,
                "invc_id" => $row_id
            ];

            $valid = $model_account_invc_details->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Fetch data
        try {
            $condition = [
                "fields" => [
                    "aid.invoice_number",
                    "aid.currency",
                    "aid.amount",
                    "aid.discount_amount",
                    "aid.credit_amount",
                    "aid.total_amount",
                    "aid.created",
                    "asd.plan_id",
                    "asd.team_size",
                    "asd.payment_method_id",
                    "cm.code AS coupon_code",
                    "cm.discount_type",
                    "cm.discount_value"
                ],
                "where" => [
                    ["where" => ["aid.id", "=", $row_id]]
                ],
                "join" => [
                    "account_subscription_details",
                    "coupon_master"
                ]
            ];
            $row = $model_account_invc_details->fetch($condition);

            if (!$row) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if ($row["discount_type"] == SHAppComponent::getValue("app_constant/DISCOUNT_TYPE_AMT")) {
            $discount_show_invc = SHAppComponent::getValue("app_constant_label/DISCOUNT_TYPE_AMT");
        } else {
            $discount_show_invc = SHAppComponent::getValue("app_constant_label/DISCOUNT_TYPE_PER");
        }

        if ($row["payment_method_id"] == SHAppComponent::getValue("payment_method/2CO")) {
            $payment_method_invc = "2Checkout";
        } else {
            $payment_method_invc = "Stripe";
        }

        // Get plan name 
        switch ($row["plan_id"]) {
            case SHAppComponent::getValue("plan/FREE"):
                $plan_name_invc = "Free Plan";
                break;
            case SHAppComponent::getValue("plan/REGULAR_MONTHLY"):
                $plan_name_invc = "Regular Monthly";
                break;
            case SHAppComponent::getValue("plan/PLUS_MONTHLY"):
                $plan_name_invc = "Plus Monthly";
                break;
            case SHAppComponent::getValue("plan/ENTERPRISE_MONTHLY"):
                $plan_name_invc = "Enterprise Monthly";
                break;
            case SHAppComponent::getValue("plan/REGULAR_MONTHLY_TRIAL"):
                $plan_name_invc = "Regular Monthly Trial";
                break;
            case SHAppComponent::getValue("plan/PLUS_MONTHLY_TRIAL"):
                $plan_name_invc = "Plus Monthly Trial";
                break;
            case SHAppComponent::getValue("plan/ENTERPRISE_MONTHLY_TRIAL"):
                $plan_name_invc = "Enterprise Monthly Trial";
                break;
            case SHAppComponent::getValue("plan/REGULAR_YEARLY"):
                $plan_name_invc = "Regular Yearly";
                break;
            case SHAppComponent::getValue("plan/PLUS_YEARLY"):
                $plan_name_invc = "Plus Yearly";
                break;
            case SHAppComponent::getValue("plan/ENTERPRISE_YEARLY"):
                $plan_name_invc = "Enterprise Yearly";
                break;
            case SHAppComponent::getValue("plan/REGULAR_YEARLY_TRIAL"):
                $plan_name_invc = "Regular Yearly Trial";
                break;
            case SHAppComponent::getValue("plan/PLUS_YEARLY_TRIAL"):
                $plan_name_invc = "Plus Yearly Trial";
                break;
            case SHAppComponent::getValue("plan/ENTERPRISE_YEARLY_TRIAL"):
                $plan_name_invc = "Enterprise Yearly Trial";
                break;
            default:
                $plan_name_invc = "Free Plan";
        }

        // Prepare output of data
        $output = [
            "invoice_number" => $row["invoice_number"],
            "currency" => $row["currency"],
            "amount" => $row["amount"],
            "discount_amount" => $row["discount_amount"],
            "credit_amount" => $row["credit_amount"],
            "total_amount" => $row["total_amount"],
            "created" => DateTimeComponent::convertDateTime($row["created"], true, \DEFAULT_TIMEZONE, $user_timezone, \APP_TIME_FORMAT),
            "plan_name" => $plan_name_invc,
            "team_size" => $row["team_size"],
            "payment_method_name" => $payment_method_invc,
            "coupon_code" => $row["coupon_code"],
            "discount_type" => $discount_show_invc,
            "discount_value" => $row["discount_value"]
        ];

        return $response->withJson($output, 200);
    }

    /**
     * Get active plan details
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function activePlan(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $api_key = \Stripe\Stripe::setApiKey(\SK_TEST_KEY); //SET API PUBLIC TEST KEY
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $user_timezone = SHAppComponent::getUserTimeZone();

        // Get request parameters
        $route = $request->getAttribute("route");

        // Validate request
        if (empty($logged_in_user)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        
        // Check if record is valid
        $account_billing_master = new AccountBillingMaster();
        $user_master = new UserMaster();
        $model_plan_master = new PlanMaster();

        try {
            // Other values for condition to get owner email
            $owner_values = [
                "active" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                "account_id" => $logged_in_account,
                "user_type_id" => SHAppComponent::getValue("user_type/CLIENT_ADMIN")
            ];
            $account_owner_details = $user_master->getAccountUserData($owner_values); // Get Account Owner Email Address
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        try {
            $other_values_billing = [
                "delete" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "account_id" => $logged_in_account,
                "active_required" => false
            ];
            $current_subscription_id = $account_billing_master->getActiveBillingDetails($other_values_billing);
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if (!$current_subscription_id) {
            $output["message"] = SHAppComponent::getValue("api_messages/STRIPE_NO_SUBSCRIPTION");
//			return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        } else {
            $account_subscription_model = new AccountSubscriptionDetails();
            $get_sub_update_json = $current_subscription_id["next_subscription_updates"];
            $cancel_subscription_val = json_decode($get_sub_update_json, true);
            $cancel_applied = false;

            if (!empty($cancel_subscription_val["cancel_applied"]) && $cancel_subscription_val["cancel_applied"] == 1) {
                $cancel_applied = true;
            }

            try {
                $condition_get_cust_id = [
                    "fields" => [
                        "tp_customer_id",
                        "tp_subscription_id"
                    ],
                    "where" => [
                        ["where" => ["id", "=", $current_subscription_id["current_subscription_id"]]]
                    ]
                ];
                $row_cust_id = $account_subscription_model->fetch($condition_get_cust_id);
            } catch (\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

            if (!empty($row_cust_id["tp_subscription_id"])) {
                try {
                    $subscription_details = \Stripe\Subscription::retrieve($row_cust_id["tp_subscription_id"]);
                    if (!empty($subscription_details)) {
                        if ($subscription_details->customer == $row_cust_id["tp_subscription_id"] && $subscription_details->status != "canceled") {
                            $subscription_avail = $subscription_details;
                        }
                    } else {
                        $subscription_avail = null;
                    }
                } catch (\Exception $e) {
                    $subscription_avail = null;
                }
            } else {
                $subscription_avail = null;
            }

//			if ($row_cust_id["tp_customer_id"] != "" && ($current_subscription_id["plan_id"] != SHAppComponent::getValue("plan/PLUS_MONTHLY_TRIAL") || $current_subscription_id["plan_id"] != SHAppComponent::getValue("plan/FREE"))) {
            if (!empty($subscription_details)) {
                $condition = [
                    "fields" => [
                        "pm.name as plan_name",
                        "pm.code as plan_code",
                        "pm.id as plan_id",
                        "pm.amount as plan_amount",
                        "pm.mode as plan_mode",
                        "pm.validity_in_days as validity_in_days",
                        "asd.credit_balance as credit_balance",
                        "asd.start_date as start_date",
                        "asd.end_date as end_date",
                        "asd.tp_subscription_id as tp_subscription_id",
                        "asd.tp_customer_id as tp_customer_id",
                        "asd.total_amount as total_amount_paid",
                        "pmm.name as payment_method_name",
                        "asli.user_account_team_size as team_size",
                        "asli.email_account_seat as email_account_seat",
                        "asli.email_account_plan_id as email_account_plan_id"
                    ],
                    "join" => [
                        "plan_master",
                        "payment_method_master",
                        "account_subscription_line_items"
                    ],
                    "where" => [
                        ["where" => ["asd.id", "=", $current_subscription_id["current_subscription_id"]]]
                    ]
                ];
            } else {
                $condition = [
                    "fields" => [
                        "pm.name as plan_name",
                        "pm.code as plan_code",
                        "pm.id as plan_id",
                        "pm.amount as plan_amount",
                        "pm.mode as plan_mode",
                        "pm.validity_in_days as validity_in_days",
                        "asd.credit_balance as credit_balance",
                        "asd.start_date as start_date",
                        "asd.end_date as end_date",
                        "asd.tp_subscription_id as tp_subscription_id",
                        "asd.tp_customer_id as tp_customer_id",
                        "asd.total_amount as total_amount_paid"
                    ],
                    "join" => [
                        "plan_master"
                    ],
                    "where" => [
                        ["where" => ["asd.id", "=", $current_subscription_id["current_subscription_id"]]]
                    ]
                ];
            }
            // Fetch data
            try {
                $row = $account_subscription_model->fetch($condition);
            } catch (\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

            if ($row["tp_customer_id"] != "" && ($current_subscription_id["plan_id"] != SHAppComponent::getValue("plan/PLUS_MONTHLY_TRIAL") && $current_subscription_id["plan_id"] != SHAppComponent::getValue("plan/FREE"))) {
                $prorated_amount = $account_billing_master->getActivePlanProrationAmount($row["tp_customer_id"]);
                if (!$prorated_amount["valid"]) {
                    return ErrorComponent::outputError($response, "api_messages/STRIPE_RELATED", $prorated_amount["error_message"]);
                } else {
                    $cur_plan_prorate = $prorated_amount["prorate_amount"];
                }

                $email_acc_plan_id = StringComponent::encodeRowId($row["email_account_plan_id"]);
                $payment_method_name = $row["payment_method_name"];

                try {
                    $condition_email_acc = [
                        "fields" => [
                            "name",
                            "code",
                            "id",
                            "amount",
                            "mode",
                            "validity_in_days"
                        ],
                        "where" => [
                            ["where" => ["id", "=", $row["email_account_plan_id"]]],
                            ["where" => ["status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                        ]
                    ];
                    $row_email_acc_plan = $model_plan_master->fetch($condition_email_acc);
                } catch (\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

                $trial_remain_days = 0;
            } else {
                if ($row["plan_id"] == SHAppComponent::getValue("plan/PLUS_MONTHLY_TRIAL")) {
                    $row_email_acc_plan["code"] = "";
                    $row_email_acc_plan["name"] = "";
                    $row_email_acc_plan["amount"] = 0.00;
                    $row_email_acc_plan["mode"] = 1;
                    $row_email_acc_plan["validity_in_days"] = 14;

                    $current_date_timestamp = DateTimeComponent::getDateTime(); // GMT 00:00 TIMESTAMP
                    //get seconds between two dates timestamp value
                    $no_of_seconds = ($row["end_date"] - $current_date_timestamp);

                    // divides total seconds by hour minute second to get number of days
                    $trial_remain_days_1 = round($no_of_seconds / (24 * 60 * 60));
                    if ($trial_remain_days_1 < 0) {
                        $trial_remain_days = 0;
                    } else {
                        $trial_remain_days = $trial_remain_days_1;
                    }
                } else {
                    $row_email_acc_plan["code"] = "FREE";
                    $row_email_acc_plan["name"] = "Free Plan";
                    $row_email_acc_plan["amount"] = 0.00;
                    $row_email_acc_plan["mode"] = 0;
                    $row_email_acc_plan["validity_in_days"] = 0;
                    $trial_remain_days = 0;
                }
                $email_acc_plan_id = "";
                $payment_method_name = "";
                $cur_plan_prorate = 0.00;
            }

            if (empty($row)) {
//	            return ErrorComponent::outputError($response, "api_messages/STRIPE_NO_SUBSCRIPTION");
                $output = ["message" => SHAppComponent::getValue("api_messages/STRIPE_NO_SUBSCRIPTION")];
            } else {
                switch ($row["plan_id"]) {
                    case SHAppComponent::getValue("plan/FREE"):
                        $plan_type = "FREE";
                        break;
                    case SHAppComponent::getValue("plan/REGULAR_MONTHLY_TRIAL"):
                        $plan_type = "TRIAL";
                        break;
                    case SHAppComponent::getValue("plan/PLUS_MONTHLY_TRIAL"):
                        $plan_type = "TRIAL";
                        break;
                    case SHAppComponent::getValue("plan/ENTERPRISE_MONTHLY_TRIAL"):
                        $plan_type = "TRIAL";
                        break;
                    case SHAppComponent::getValue("plan/REGULAR_YEARLY_TRIAL"):
                        $plan_type = "TRIAL";
                        break;
                    case SHAppComponent::getValue("plan/PLUS_YEARLY_TRIAL"):
                        $plan_type = "TRIAL";
                        break;
                    case SHAppComponent::getValue("plan/ENTERPRISE_YEARLY_TRIAL"):
                        $plan_type = "TRIAL";
                        break;
                    case SHAppComponent::getValue("plan/REGULAR_MONTHLY"):
                        $plan_type = "PAID";
                        break;
                    case SHAppComponent::getValue("plan/PLUS_MONTHLY"):
                        $plan_type = "PAID";
                        break;
                    case SHAppComponent::getValue("plan/ENTERPRISE_MONTHLY"):
                        $plan_type = "PAID";
                        break;
                    case SHAppComponent::getValue("plan/REGULAR_YEARLY"):
                        $plan_type = "PAID";
                        break;
                    case SHAppComponent::getValue("plan/PLUS_YEARLY"):
                        $plan_type = "PAID";
                        break;
                    case SHAppComponent::getValue("plan/ENTERPRISE_YEARLY"):
                        $plan_type = "PAID";
                        break;
                    default:
                        $plan_type = "FREE";
                }

                $app_constants = SHAppComponent::getValue("app_constant");
                $status = array_search($current_subscription_id["status"], $app_constants);

                // Prepare output of data
                $output = [
                    "owner_email" => $account_owner_details["email"],
                    "owner_name" => $account_owner_details["first_name"] . " " . $account_owner_details["last_name"],
                    "plan_name" => $row["plan_name"],
                    "plan_code" => $row["plan_code"],
                    "plan_id" => StringComponent::encodeRowId($row["plan_id"]),
                    "plan_amount" => $row["plan_amount"],
                    "plan_mode" => $row["plan_mode"],
                    "validity_in_days" => $row["validity_in_days"],
                    "team_size" => $current_subscription_id["team_size"],
                    "credit_balance" => $current_subscription_id["credit_balance"],
                    "start_date" => $row["start_date"],
                    "end_date" => $row["end_date"],
                    "start_date_formated" => DateTimeComponent::convertDateTime($row["start_date"], true, "GMT+00:00", $user_timezone, "M d Y h:i:s a"),
                    "end_date_formated" => DateTimeComponent::convertDateTime($row["end_date"], true, "GMT+00:00", $user_timezone, "M d Y h:i:s a"),
                    "tp_subscription_id" => $row["tp_subscription_id"],
                    "tp_customer_id" => $row["tp_customer_id"],
                    "payment_method_name" => $payment_method_name,
                    "total_amount_paid" => $row["total_amount_paid"],
                    "current_plan_prorate_amount" => $cur_plan_prorate,
                    "email_acc_plan_id" => $email_acc_plan_id,
                    "email_acc_plan_code" => $row_email_acc_plan["code"],
                    "email_acc_plan_name" => $row_email_acc_plan["name"],
                    "email_acc_plan_amount" => $row_email_acc_plan["amount"],
                    "email_acc_plan_mode" => $row_email_acc_plan["mode"],
                    "email_acc_plan_validity_in_days" => $row_email_acc_plan["validity_in_days"],
                    "email_acc_seats" => $current_subscription_id["email_acc_seats"],
                    "cancel_applied" => $cancel_applied,
                    "plan_type" => $plan_type,
                    "status" => $status,
                    "configuration" => json_decode($current_subscription_id["configuration"], true),
                    "trial_remain_days" => $trial_remain_days,
                    "ac_number" => $account_owner_details["ac_number"]
                ];
            }
        }

        return $response->withJson($output, 200);
    }

    /**
     * Update card in stripe
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (string) Response message as string
     */
    public function cardUpdate(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
        $api_key = \Stripe\Stripe::setApiKey(\SK_TEST_KEY); //SET API PUBLIC TEST KEY
        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Get request parameters
        $route = $request->getAttribute("route");

        // Validate request
        if (empty($logged_in_user)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $model = new AccountSubscriptionDetails();

        $other_values_subscription = [
            "active" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
            "account_id" => $logged_in_account
        ];
        $user_cust_sub_id = $model->getUserExistDetails($other_values_subscription); // Get Subscription Data From Subscription Table If Available

        if (!empty($user_cust_sub_id)) {
            $customer_id = $user_cust_sub_id["tp_customer_id"];

            try {
                $customer = \Stripe\Customer::retrieve($customer_id); //RETRIEVE CUSTOMER
                // CHECK IF CARD IS CHANGES OR NOT START
                $tokenData = \Stripe\Token::retrieve($request_params["stripeToken"]);
                $thisCard = $tokenData['card'];
                $custCards = $customer->sources->data;

                if (empty($custCards)) {
                    $customer->sources->create(array('source' => $request_params["stripeToken"]));
                    $output["message"] = SHAppComponent::getValue("api_messages/STRIPE_CARD_UPDATED");
                } else {
                    // if ($custCards[0]['fingerprint'] != $thisCard['fingerprint']) {
                    //     $customer->sources->retrieve($custCards[0]['id'])->delete();
                    //     $customer->sources->create(array('source' => $request_params["stripeToken"]));
                    //     $output["message"] = "Your card is successfully updated with stripe";
                    // } else {
                    //     $output["message"] = "Provided card is same as the previous card. Please check";
                    // }
                    $customer->sources->retrieve($custCards[0]['id'])->delete();
                    $customer->sources->create(array('source' => $request_params["stripeToken"]));

                    $output["message"] = "Your card is successfully updated with stripe";
                }
            } catch (Stripe_CardError $e) {
                return ErrorComponent::outputError($response, "api_messages/STRIPE_RELATED", $e->getMessage());
            }
        } else {
            return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
        }

        return $response->withJson($output, 200);
    }

    /**
     * 
     * @param ServerRequestInterface $request (object) : Request Object
     * @param ResponseInterface $response (object) : Response Object
     * @param type $args (array) : Route Parameters
     * 
     * @return Array of members
     */
    public function getBillingMemberList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $params = $request->getQueryParams();

        // Other values for condition
        $other_values = [
            "user_id" => $logged_in_user,
            "account_id" => $logged_in_account,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        // Get data
        $model_user_master = new UserMaster();

        try {
            $data = $model_user_master->getMemberListBilling($other_values);
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $app_constants = SHAppComponent::getValue("app_constant");

        $user_type = SHAppComponent::getValue("user_type");

        foreach ($data as $row) {

            $resend_invitation = false;

            switch($row["status"]) {
                case "1":
                    $status = "STATUS_ACTIVE";
                break;
                case "2":
                    $status = "STATUS_DELETE";
                break;
                case "5":
                    $status = "STATUS_REMOVED";
                break;
                default:
                    $status = "STATUS_INACTIVE";
                break;
            }

            $user_type_code = array_search($row["user_type_id"], $user_type);

            $client_admin = SHAppComponent::getValue("user_type/CLIENT_ADMIN");
            $sh_admin = SHAppComponent::getValue("user_type/SH_ADMIN");
            $sh_super_admin = SHAppComponent::getValue("user_type/SH_SUPER_ADMIN");

            $can_delete = true;
            if ($row["user_type_id"] == $client_admin || $row["user_type_id"] == $sh_admin || $row["user_type_id"] == $sh_super_admin) {
                $can_delete = false;
            }

            if (isset($row["joined_at"]) && $row["joined_at"] == 0) {
                $resend_invitation = true;
            }

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "name" => trim($row["first_name"] . " " . $row["last_name"]),
                "email" => $row["email"],
                "user_type" => $user_type_code,
                "can_delete" => $can_delete,
                "status" => $status,
                "resend_invitation" => $resend_invitation
            ];

            $output[] = $row_data;
        }

        return $response->withJson($output, 200);
    }

    /**
     * 
     * @param ServerRequestInterface $request (object) : Request Object
     * @param ResponseInterface $response (object) : Response Object
     * @param type $args (array) : Route Parameters
     * 
     * @return Array of members
     */
    public function getBillingEmailAccountList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $params = $request->getQueryParams();

        // Other values for condition
        $other_values = [
            "user_id" => $logged_in_user,
            "account_id" => $logged_in_account,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
            "active" => SHAppComponent::getValue("app_constant/FLAG_YES")
        ];

        // Get data
        $model_acc_sending_method = new AccountSendingMethods();

        try {
            $data = $model_acc_sending_method->getEmailAccListBilling($other_values);
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $app_constants = SHAppComponent::getValue("app_constant");
        $sending_methods = SHAppComponent::getValue("email_sending_method");
        $app_constants_label = SHAppComponent::getValue("app_constant_label");

        foreach ($data as $row) {
            $status = array_search($row["status"], $app_constants);
            $type = array_search($row["email_sending_method_id"], $sending_methods);
            if ($row["is_default"] == SHAppComponent::getValue("app_constant/FLAG_NO")) {
                $is_default = "FLAG_NO";
            } else {
                $is_default = "FLAG_YES";
            }

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "name" => trim($row["name"]),
                "from_email" => trim($row["from_email"]),
                "created_by" => $row["first_name"],
                "type" => $type,
                "is_default" => $is_default,
                "status" => $status
            ];

            $output[] = $row_data;
        }

        return $response->withJson($output, 200);
    }

}
