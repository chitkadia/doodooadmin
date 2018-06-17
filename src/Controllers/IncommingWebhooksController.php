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
use \App\Models\AccountSubscriptionLineItems;
use \App\Models\PlanMaster;

class IncommingWebhooksController extends AppController {

    const LOG_FILE_PATH =  __DIR__ . "/../../logs/webhooks_events.log";

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

	/**
	 * 
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @param type $args
	 * @throws \Exception
	 */
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
		
		$current_user_id;
		$current_account_id;
		$current_active_subscription_id;

        $status_active_vars = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
        $status_inactive_vars = SHAppComponent::getValue("app_constant/STATUS_INACTIVE");

        switch($event_json->type) {

            case 'customer.created':
				$meta_data = $event_json->data->object->metadata;
				$user_id = $meta_data->user_id;
				$account_id = $meta_data->account_id;
				$active_subscription_id = $meta_data->active_subscription_id;
				
				$current_user_id = $user_id;
				$current_account_id = $account_id;
				$current_active_subscription_id = $active_subscription_id;
                
                break;

            case 'customer.source.created':
                
                break;

            case 'customer.source.deleted':

                break;

            case 'customer.updated':
                $meta_data = $event_json->data->object->metadata;
				$user_id = $meta_data->user_id;
				$account_id = $meta_data->account_id;
				$active_subscription_id = $meta_data->active_subscription_id;
				
				$current_period_start = $event_json->data->object->subscriptions->data[0]->current_period_start;
				$current_period_end = $event_json->data->object->subscriptions->data[0]->current_period_end;
				
				$customer_id = $event_json->data->object->id;
				$subscription_id = $event_json->data->object->subscriptions->data[0]->id;
				
				$update_data = [
                    "start_date" => $current_period_start,
                    "end_date" => $current_period_end,
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
				
				$current_user_id = $user_id;
				$current_account_id = $account_id;
				$current_active_subscription_id = $active_subscription_id;
				
                break;

            case 'invoice.payment_succeeded':
                $customer_id = $event_json->data->object->customer;
                $credit_balance = abs($event_json->data->object->ending_balance/100);
                $charge_id = $event_json->data->object->charge;
                $paid_at = $event_json->data->object->date;
                $invoice_id = $event_json->data->object->id;
                $total_amount = $event_json->data->object->total/100;
				$paid_or_not = $event_json->data->object->paid;
                $plan_start_date = $event_json->data->object->period_start;
                $plan_end_date = $event_json->data->object->period_end;
                $plan_code = $event_json->data->object->lines->data[0]->plan->id;
                $team_size = $event_json->data->object->lines->data[0]->quantity;
                $email_acc_seats = $event_json->data->object->lines->data[1]->quantity;
				
				$customer_obj = $this->retrieveCustomerDetailsObj($customer_id);
				
				$active_subscription_id = $customer_obj->metadata->active_subscription_id;
                $account_id = $customer_obj->metadata->account_id;
                $user_id = $customer_obj->metadata->user_id;
                $customer_key = $customer_obj->metadata->customer_key;
				$subscription_id = $customer_obj->subscriptions->data[0]->id;

                $event_message  = "Event ID : " . $event_json->data->object->id;
                $event_message .= "\nActive Subscription Data START";
                $event_message .= "\nEvent Name : invoice.payment_succeeded";
                $event_message .= "\nCustomer Object Meta Data : ". json_encode($customer_obj->metadata);
                $event_message .= "\nActive Subscription Data END";

                $get_act_sub_data = $this->getActiveSubscriptionDetailsb4rEvent($active_subscription_id);

                if ($get_act_sub_data != false) {
                    $fetched_act_plan_start_date = $get_act_sub_data["start_date"];
                    $fetched_act_plan_end_date = $get_act_sub_data["end_date"];
                    $fetched_act_plan_code = $get_act_sub_data["code"];
                    $fetched_plan_type = $get_act_sub_data["type"];
                    $fetched_status = $get_act_sub_data["status"];

                    $event_message .= "\nACTIVE Start Date : " . $fetched_act_plan_start_date;
                    $event_message .= "\nACTIVE End Date : " . $fetched_act_plan_end_date;
                    $event_message .= "\nACTIVE Plan Code : " . $fetched_act_plan_code;
                    $event_message .= "\nACTIVE Plan Type : " . $fetched_plan_type;
                    $event_message .= "\nEnd Of Active Subscription Details";

                    // Recurring plan code start (Insert new record in every billing table except account_billing_master) => 12-03-2018 (D-M-YYYY)
                    if (($fetched_act_plan_start_date != $plan_start_date && $fetched_act_plan_end_date != $plan_end_date) && $fetched_act_plan_code == $plan_code && $fetched_status != SHAppComponent::getValue("app_constant/STATUS_PENDING")) {

                        $event_message .= "\nRecurring Process Start for : " . $account_id;

                        $payment_method = SHAppComponent::getValue("payment_method/STRIPE");
                        $action_type = SHAppComponent::getValue("app_constant/PAYMENT_RECURRING");
                        $email_acc_plan_id = $this->getEmailAccountPlanId($get_act_sub_data["id"]);

                        $postData['coupon_id'] = null;
                        $postData["discount_type"] = SHAppComponent::getValue("app_constant/DISCOUNT_TYPE_AMT");
                        $postData["discount_value"] = 0;
                        $postData["discount_amount"] = 0;
                        $postData["total_amount"] = $total_amount;

                        $postData["account_id"] = $account_id;
                        $postData["plan_id"] = $get_act_sub_data["id"];
                        $postData["team_size"] = $team_size;
                        $postData["email_acc_seats"] = $email_acc_seats;
                        $postData["credit_balance"] = $credit_balance;
                        $postData["amount"] = $total_amount;
                        $postData["payment_method_id"] = $payment_method;
                        $postData['start_date'] = $plan_start_date;
                        $postData['end_date'] = $plan_end_date;
                        $postData['next_subscription_id'] = $active_subscription_id;
                        $postData['tp_subscription_id'] = $subscription_id;
                        $postData['tp_customer_id'] = $customer_id;
                        $postData['type'] = SHAppComponent::getValue("app_constant/PAYMENT_RECURRING");
                        $postData['status'] = 1;
                        $postData['created'] = DateTimeComponent::getDateTime();
                        $postData['modified'] = DateTimeComponent::getDateTime();

                        $last_insert_id = $this->insertSubscriptionTable($postData, "subscription"); // Insert data into subscription table

                        $line_item_insert_data = [
                            "user_account_plan_id" => $get_act_sub_data["id"],
                            "user_account_team_size" => $team_size,
                            "email_account_plan_id" => $email_acc_plan_id,
                            "email_account_seat" => $email_acc_seats,
                            "current_subscription_id" => $last_insert_id,
                            "total_amount" => $total_amount,
                            "created" => DateTimeComponent::getDateTime(),
                            "modified" => DateTimeComponent::getDateTime()
                        ];

                        $this->insertSubscriptionTable($line_item_insert_data, "sub_line_item"); // Insert data into subscription line item table

                        $payment_insert_data = [
                            "account_id" => $account_id,
                            "account_subscription_id" => $last_insert_id,
                            "amount_paid" => $total_amount,
                            "payment_method_id" => $payment_method,
                            "tp_payload" => 0,
                            "tp_payment_id" => 0,
                            "type" => $action_type,
                            "paid_at" => $paid_at,
                            "created" => DateTimeComponent::getDateTime(),
                            "modified" => DateTimeComponent::getDateTime()
                        ];

                        $payment_insert_id = $this->insertSubscriptionTable($payment_insert_data, "payment"); // Insert data into payment table

                        $invoice_insert_data = [
                            "invoice_number" => 0,
                            "account_id" => $account_id,
                            "account_subscription_id" => $last_insert_id,
                            "account_payment_id" => $payment_insert_id,
                            "amount" => $total_amount,
                            "discount_amount" => 0,
                            "credit_amount" => $credit_balance,
                            "total_amount" => $total_amount,
                            "file_copy" => 0,
                            "created" => DateTimeComponent::getDateTime()
                        ];

                        $this->insertSubscriptionTable($invoice_insert_data, "invoice"); // Insert data into invoice table

                        $customer_obj->metadata = array("user_id" => $user_id, "account_id" => $account_id, "active_subscription_id" => $last_insert_id, "customer_key" => $customer_key);
                        
                        $update_data_billing_recur = [
                            "current_subscription_id" => $last_insert_id,
                            "modified" => DateTimeComponent::getDateTime()
                        ];
                        $conditions_sub_update_billing_recur = [
                            "where" => [
                                ["where" => ["current_subscription_id", "=", $active_subscription_id]],
                                ["where" => ["account_id", "=", $account_id]]
                            ],
                        ];
                        $this->updateSubscriptionTable($update_data_billing_recur, $conditions_sub_update_billing_recur, "billing", $response);

                        $customer_obj->save();

                        $active_subscription_id = $last_insert_id;

                    }
                    // Recurring plan code end
                    $event_message .= "\nLast Insert Id : " . $active_subscription_id;
                }
                
				$status_val = SHAppComponent::getValue("app_constant/STATUS_PENDING");
				if ($paid_or_not == true) {
					$status_val = SHAppComponent::getValue("app_constant/STATUS_SUCCESS");
				} else {
					$status_val = SHAppComponent::getValue("app_constant/STATUS_PENDING");
				}
                                
                $update_data = [
                    "credit_balance" => $credit_balance,
                    "total_amount" => $total_amount,
					"status" => $status_val,
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
//                    "next_subscription_updates" => $postdata,
					"status" => $status_val,
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $conditions_sub_update_billing = [
                    "where" => [
                        // ["where" => ["current_subscription_id", "=", $active_subscription_id]],
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
                $this->updateSubscriptionTable($update_data_invc_data, $conditions_sub_update_invc_data, "invoice", $response); // update data into invoice table

                $event_message .= "\nEvent End\n==========================================================";

                LoggerComponent::log($event_message, self::LOG_FILE_PATH);
                
                break;

            case 'charge.succeeded':
				$status = $event_json->data->object->status;
				$customer_id = $event_json->data->object->customer;
				
				$customer_obj = $this->retrieveCustomerDetailsObj($customer_id);
				
				$active_subscription_id = $customer_obj->metadata->active_subscription_id;
                $account_id = $customer_obj->metadata->account_id;
                $subscription_id = $customer_obj->subscriptions->data[0]->id;
				                
                if ($status == "succeeded") {
                    $status_code = SHAppComponent::getValue("app_constant/STATUS_SUCCESS");
                    $billing_code = $status_active_vars;
                } else if ($status == "pending") {
                    $status_code = SHAppComponent::getValue("app_constant/STATUS_PENDING");
                    $billing_code = $status_inactive_vars;
                } else if ($status == "failed") {
                    $status_code = SHAppComponent::getValue("app_constant/STATUS_FAIL");
                    $billing_code = $status_inactive_vars;
                } else {
                    $status_code = SHAppComponent::getValue("app_constant/STATUS_PENDING");
                    $billing_code = $status_inactive_vars;
                }
                
                // $update_data = [
                //     "status" => $status_code,
                //     "modified" => DateTimeComponent::getDateTime()
                // ];
                // $conditions_sub_update = [
                //     "where" => [
                //         ["where" => ["id", "=", $active_subscription_id]],
                //         ["where" => ["account_id", "=", $account_id]],
                //         ["where" => ["tp_subscription_id", "=", $subscription_id]],
                //         ["where" => ["tp_customer_id", "=", $customer_id]]
                //     ],
                // ];
                // $this->updateSubscriptionTable($update_data, $conditions_sub_update, "subscription", $response); // update data into subscription table
                
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
				
				$customer_obj = $this->retrieveCustomerDetailsObj($customer_id);
				
				$active_subscription_id = $customer_obj->metadata->active_subscription_id;
                $account_id = $customer_obj->metadata->account_id;
								                                
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
				
				$customer_obj = $this->retrieveCustomerDetailsObj($customer_id);
				
				$active_subscription_id = $customer_obj->metadata->active_subscription_id;
                $account_id = $customer_obj->metadata->account_id;
								                
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
				$customer_id = $event_json->data->object->customer;
				
				$customer_obj = $this->retrieveCustomerDetailsObj($customer_id);
				
				$active_subscription_id = $customer_obj->metadata->active_subscription_id;
                $account_id = $customer_obj->metadata->account_id;
				
                if ($status == "succeeded") {
                    $status_code = SHAppComponent::getValue("app_constant/STATUS_SUCCESS");
                    $billing_code = $status_active_vars;
                } else if ($status == "pending") {
                    $status_code = SHAppComponent::getValue("app_constant/STATUS_PENDING");
                    $billing_code = $status_inactive_vars;
                } else if ($status == "failed") {
                    $status_code = SHAppComponent::getValue("app_constant/STATUS_FAIL");
                    $billing_code = $status_inactive_vars;
                } else {
                    $status_code = SHAppComponent::getValue("app_constant/STATUS_PENDING");
                    $billing_code = $status_inactive_vars;
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
	
	public function retrieveCustomerDetailsObj($customer_id) {
		\Stripe\Stripe::setApiKey(\SK_TEST_KEY); //SET API KEY
		$customer_obj = \Stripe\Customer::retrieve($customer_id);
		return $customer_obj;
	}

    public function getActiveSubscriptionDetailsb4rEvent($subscription_pk_id) {
        $model = new AccountSubscriptionDetails();
        $row = false;

        try {
            $condition = [
                "fields" => [
                    "asd.start_date",
                    "asd.end_date",
                    "asd.type",
                    "asd.status",
                    "pm.id",
                    "pm.code"
                ],
                "where" => [
                    ["where" => ["asd.id", "=", $subscription_pk_id]]
                ],
                "join" => [
                    "plan_master"
                ]
            ];
            $row = $model->fetch($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $row;
    }

    public function insertSubscriptionTable($data, $table) {
        $last_insert_id = false;
        if ($table == "subscription") {
            $model = new AccountSubscriptionDetails();
        } else if ($table == "billing") {
            $model = new AccountBillingMaster();
        } else if ($table == "payment") {
            $model = new AccountPaymentDetails();
        } else if ($table == "sub_line_item") {
            $model = new AccountSubscriptionLineItems();
        } else {
            $model = new AccountInvoiceDetails();
        }
        try {
            $last_insert_id = $model->save($data);
        } catch (\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $last_insert_id;
    }

    public function getEmailAccountPlanId($plan_id) {
        $model = new PlanMaster();
        $row = false;
        $email_acc_plan_id = 1;

        try {
            $condition = [
                "fields" => [
                    "configuration"
                ],
                "where" => [
                    ["where" => ["id", "=", $plan_id]]
                ]
            ];
            $row = $model->fetch($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $json_decode_conf = json_decode($row["configuration"], true);
        if (count($json_decode_conf) > 0) {
            $email_acc_plan_id = $json_decode_conf["ea_plan"];
        }

        return $email_acc_plan_id;
    }

}
