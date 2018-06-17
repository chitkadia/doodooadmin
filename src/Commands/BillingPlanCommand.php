<?php
/**
 * Used to retrieve and update expiring trial plan records to free plan
 */
namespace App\Commands;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Interop\Container\ContainerInterface;
use \App\Components\SHAppComponent;
use \App\Components\DateTimeComponent;
use \App\Components\LoggerComponent;
use \App\Components\Mailer\TransactionMailsComponent;
use \App\Models\AccountSubscriptionDetails;
use \App\Models\AccountBillingMaster;
use \App\Models\AccountSendingMethods;
use \App\Models\PlanMaster;
use \App\Models\UserMaster;

class BillingPlanCommand extends AppCommand {

    //define constant for document process cron execute limt 
    const AFTER_TWO_HOUR_TIME = 60*60*1; // Timestamp after an hours
    
    const LOG_FILE_TRIAL_EXPIRE = __DIR__ . "/../../logs/trial_expire.log";
    const LOG_FILE_CANCEL_TO_FREE_ERROR = __DIR__ . "/../../logs/cancel_to_free_error.log";
    const LOG_FILE_CANCEL_TO_FREE = __DIR__ . "/../../logs/cancel_to_free.log";
    const LOG_FILE_DEL_REMOVED_USER_REC = __DIR__ . "/../../logs/delete_removed_user_recurring.log";

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Retrieve and update expiring trial plan records to free plan
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     */
    public function actionCheckTrialExpire(ServerRequestInterface $request, ResponseInterface $response, $args) {

        $current_time = DateTimeComponent::getDateTime();
        $after_two_hours = $current_time+self::AFTER_TWO_HOUR_TIME; // Timestamp after an hours
        
        $model_account_subscription_details = new AccountSubscriptionDetails();
        $model_account_billing_master = new AccountBillingMaster();
        $model_plan_master = new PlanMaster();
		
		$condition_bill_data = [
            "fields" => [
				"current_subscription_id"
            ],
            "where" => [
                ["where" => ["plan_id", "=", SHAppComponent::getValue("plan/PLUS_MONTHLY_TRIAL")]],
                ["where" => ["status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
            ]
        ];
        
        try {
            $row_bill_data = $model_account_billing_master->fetchAll($condition_bill_data); // Get trial plan data from billing table
        } catch(\Exception $e) {
            LoggerComponent::log("Mysql error billing table:". $e->getMessage(), self::LOG_FILE_TRIAL_EXPIRE);
        }
		
		$condition_plan = [
            "fields" => [
                "configuration"
            ],
            "where" => [
                ["where" => ["id", "=", SHAppComponent::getValue("plan/FREE")]],
                ["where" => ["status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
            ]
        ];
        
        try {
            $row_data_plan = $model_plan_master->fetch($condition_plan);
        } catch(\Exception $e) {
            LoggerComponent::log("Mysql error plan table:". $e->getMessage(), self::LOG_FILE_TRIAL_EXPIRE);
        }
		
		foreach($row_bill_data as $data_row) {
			$condition_plan_sub_data = [
				"fields" => [
					"id",
					"account_id",
					"team_size",
					"email_acc_seats",
					"start_date"
				],
				"where" => [
					["where" => ["plan_id", "=", SHAppComponent::getValue("plan/PLUS_MONTHLY_TRIAL")]],
					["where" => ["id", "=", $data_row["current_subscription_id"]]],
					["where" => ["end_date", "<=", $after_two_hours]]
				]
			];
			
			try {
				$rowsub_data_fetch = $model_account_subscription_details->fetch($condition_plan_sub_data);
			} catch(\Exception $e) {
				LoggerComponent::log("Mysql error subscription table:". $e->getMessage(), self::LOG_FILE_TRIAL_EXPIRE);
			}
			if (!empty($rowsub_data_fetch)) {
				try {
					// Set postdata for subscription table
					$postData["account_id"] = $rowsub_data_fetch["account_id"];
					$postData["plan_id"] = SHAppComponent::getValue("plan/FREE");
					$postData["team_size"] = $rowsub_data_fetch["team_size"];
					$postData["email_acc_seats"] = $rowsub_data_fetch["email_acc_seats"];
					$postData['start_date'] = $rowsub_data_fetch["start_date"];
					$postData['next_subscription_id'] = $rowsub_data_fetch["id"];
					$postData['tp_subscription_id'] = null;
					$postData['tp_customer_id'] = null;
					$postData['type'] = SHAppComponent::getValue("app_constant/PAYMENT_DOWNGRADE");
					$postData['status'] = SHAppComponent::getValue("app_constant/STATUS_SUCCESS");
					$postData['created'] = DateTimeComponent::getDateTime();
					$postData['modified'] = DateTimeComponent::getDateTime();

					$sub_insert = $model_account_subscription_details->save($postData);

				} catch (\Exception $e) {
					// Fetch error code & message and return the response
					return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
				}

				try {
					$update_data = [
						"plan_id" => SHAppComponent::getValue("plan/FREE"),
						"current_subscription_id" => $sub_insert,
						"next_subscription_updates" => $row_data_plan["configuration"],
						"status" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
						"modified" => $current_time
					];
					$conditions_bill_update = [
						"where" => [
							["where" => ["current_subscription_id", "=", $rowsub_data_fetch["id"]]]
						],
					];
					$model_account_billing_master->update($update_data, $conditions_bill_update);
					
				} catch (\Exception $e) {
					// Fetch error code & message and return the response
					return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
				}
				
				if ($model_account_billing_master) {
					try {
						$update_data_sub = [
							"status" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
							"modified" => $current_time
						];
						$conditions_sub_update = [
							"where" => [
								["where" => ["id", "=", $rowsub_data_fetch["id"]]]
							],
						];
						$model_account_subscription_details->update($update_data_sub, $conditions_sub_update);

                        $account_sending_method = new AccountSendingMethods();

                        $plan_id = SHAppComponent::getValue("plan/FREE");
                        $account_sending_method->setQuota($plan_id, 0, $rowsub_data_fetch["account_id"]);

					} catch (\Exception $e) {
						// Fetch error code & message and return the response
						return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
					}
				}
			}
		}
		
    }
	
	/**
	 * This function is used to check if AC_ADMIN has requested to cancel the plan, if yes then it will cancel the plan on end date of the plan
	 * 
	 * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
	 */
	public function actionCheckPlanCancelled(ServerRequestInterface $request, ResponseInterface $response, $args) {
		$model_account_subscription_details = new AccountSubscriptionDetails();
        $model_account_billing_master = new AccountBillingMaster();
        $model_plan_master = new PlanMaster();
		$model_user_master = new UserMaster();
		
		$free_plan = SHAppComponent::getValue("plan/FREE");
        
        $conditions_to_check = [
            "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
			"current_time_stamp" => DateTimeComponent::getDateTime()
        ];
        
        try {
            $row_bill_data = $model_account_billing_master->fetchCancelSubscription($conditions_to_check); // Get trial plan data from billing table
		} catch(\Exception $e) {
            LoggerComponent::log("Mysql error Billing Fetch:". $e->getMessage(), self::LOG_FILE_CANCEL_TO_FREE_ERROR);
        }
		
		try {
			$condition_plan_detail = [
				"fields" => [
					"configuration"
				],
				"where" => [
					["where" => ["id", "=", $free_plan]],
					["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_INACTIVE")]]
				]
			];
			$row_plan_data = $model_plan_master->fetch($condition_plan_detail);
			
		} catch(\Exception $e) {
			LoggerComponent::log("Mysql error Plan Fetch:". $e->getMessage(), self::LOG_FILE_CANCEL_TO_FREE_ERROR);
		}
		
		if (!empty($row_bill_data)) {
			$i = 1;
			foreach($row_bill_data as $data_row) {
				try {
					// Insert data into account subscription line item table
					$sub_item_insert_data = [
						"account_id" => $data_row["account_id"],
						"plan_id" => $free_plan,
						"team_size" => $data_row["team_size"],
						"email_acc_seats" => $data_row["email_acc_seats"],
						"start_date" => DateTimeComponent::getDateTime(),
						"next_subscription_id" => $data_row["current_subscription_id"],
						"type" => SHAppComponent::getValue("app_constant/PAYMENT_DOWNGRADE"),
						"status" => SHAppComponent::getValue("app_constant/STATUS_SUCCESS"),
						"created" => DateTimeComponent::getDateTime(),
						"modified" => DateTimeComponent::getDateTime()
					];
					$subscription_insert = $model_account_subscription_details->save($sub_item_insert_data); // Insert data into account subscription table
					LoggerComponent::log("Fetched Row : ". $i ." : \r\n Account ID : ". $data_row["account_id"] ."\r\n Active Subscription ID : ". $data_row["current_subscription_id"] . "\r\n Inserted Subscription ID : " . $subscription_insert, self::LOG_FILE_CANCEL_TO_FREE);

				} catch (\Exception $e) {
					LoggerComponent::log("Mysql error Subscription Table insert:". $e->getMessage(), self::LOG_FILE_CANCEL_TO_FREE_ERROR);
				}

				$cancel_subscription_object = json_decode($data_row["next_subscription_updates"], true);
				if (!empty($cancel_subscription_object["cancel_applied"])) {
					if ($cancel_subscription_object["cancel_applied"] == 1) {
						unset($cancel_subscription_object["cancel_applied"]);
						$cancel_removed_json = json_encode($cancel_subscription_object);
					} else {
						$cancel_removed_json = $data_row["next_subscription_updates"];
					}
				} else {
					$cancel_removed_json = $data_row["next_subscription_updates"];
				}

				try {
					$update_data_bill = [
						"plan_id" => $free_plan,
						"current_subscription_id" => $subscription_insert,
						"team_size" => $data_row["team_size"],
						"email_acc_seats" => $data_row["email_acc_seats"],
						"next_subscription_updates" => $cancel_removed_json,
						"configuration" => $row_plan_data["configuration"],
						"status" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
						"modified" => DateTimeComponent::getDateTime()
					];
					$conditions_bill_update = [
						"where" => [
							["where" => ["current_subscription_id", "=", $data_row["current_subscription_id"]]]
						],
					];
					$model_account_billing_master->update($update_data_bill, $conditions_bill_update);
					LoggerComponent::log("==============================\r\nBilling Data Successfully updated for record : ". $i ."\r\n===================================", self::LOG_FILE_CANCEL_TO_FREE);

                    $account_sending_method = new AccountSendingMethods();

                    $plan_id = SHAppComponent::getValue("plan/FREE");
                    $account_sending_method->setQuota($plan_id, 0, $data_row["account_id"]);
					
					try {
						$condition_user_detail = [
							"fields" => [
								"first_name",
								"last_name",
								"email"
							],
							"where" => [
								["where" => ["account_id", "=", $data_row["account_id"]]],
								["where" => ["user_type_id", "=", SHAppComponent::getValue("user_type/CLIENT_ADMIN")]],
								["orWhere" => ["user_type_id", "=", SHAppComponent::getValue("user_type/SH_ADMIN")]],
								["orWhere" => ["user_type_id", "=", SHAppComponent::getValue("user_type/SH_SUPER_ADMIN")]]
							]
						];
						$row_user_data = $model_user_master->fetch($condition_user_detail);
					} catch (\Exception $e) {
						LoggerComponent::log("Mysql error User Table Fetch:". $e->getMessage(), self::LOG_FILE_CANCEL_TO_FREE_ERROR);
					}
					
					$subject_msg = "Your subscription was cancelled successfully.";
					$cancel_sub_content = "Your subscription was cancelled successfully."
                            . " Thank you for beign with us.";
                    //Send email to user to verify account
                    $info["smtp_details"]["host"] = HOST;
                    $info["smtp_details"]["port"] = PORT;
                    $info["smtp_details"]["encryption"] = ENCRYPTION;
                    $info["smtp_details"]["username"] = USERNAME;
                    $info["smtp_details"]["password"] = PASSWORD;

                    $info["from_email"] = FROM_EMAIL;
                    $info["from_name"] = FROM_NAME;

                    $info["to"] = $row_user_data["email"];
                    $info["cc"] = '';
                    $info["bcc"] = '';
                    $info["subject"] = $subject_msg;
                    $info["content"] = file_get_contents(\EMAIL_TEPLATES_FOLDER . "/cancel_subscription_email.html");
                    $info["content"] = str_replace("{FirstName}", $row_user_data["first_name"], $info["content"]);
                    $info["content"] = str_replace("{LastName}", $row_user_data["last_name"], $info["content"]);
                    $info["content"] = str_replace("{CancelContent}", $cancel_sub_content, $info["content"]);

                    TransactionMailsComponent::mailSendSmtp($info);

				} catch (\Exception $e) {
					LoggerComponent::log("Mysql error Billing Table Update:". $e->getMessage(), self::LOG_FILE_CANCEL_TO_FREE_ERROR);
				}
				$i++;
			}
		}
	}

	/**
	 * This function is used to check if there is any member available to delete on recurring
	 * If available then change it's status to deleted
	 * 
	 * @param $request (object): Request object
	 * @param $response (object): Response object
	 * @param $args (array): Route parameters
	 */
	public function actionCheckDeleteMemberList(ServerRequestInterface $request, ResponseInterface $response, $args) {
		// Get User Details
		$logged_in_user = SHAppComponent::getUserId();
		$logged_in_account = SHAppComponent::getAccountId();
		$is_owner = SHAppComponent::isAccountOwner();

		$model_account_billing_master = new AccountBillingMaster();
		$model_user_master = new UserMaster();

		$free_plan = SHAppComponent::getValue("plan/FREE");
		$trial_plan = SHAppComponent::getValue("plan/PLUS_MONTHLY_TRIAL");

		LoggerComponent::log("Removed Member Fetch List Process Start", self::LOG_FILE_DEL_REMOVED_USER_REC);
		$conditions_to_check = [
			"status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
			"current_time_stamp" => DateTimeComponent::getDateTime(),
			"plan" => $free_plan,
			"trial_plan" => $trial_plan
		];
		
		try {
			$row_bill_data = $model_account_billing_master->fetchActiveBillingDetails($conditions_to_check); // Get active billing details
		} catch(\Exception $e) {
			LoggerComponent::log("Mysql error Billing Fetch:". $e->getMessage(), self::LOG_FILE_DEL_REMOVED_USER_REC);
		}

		LoggerComponent::log("Removed Member Fetch List Process Complete", self::LOG_FILE_DEL_REMOVED_USER_REC);

		if (!empty($row_bill_data)) {
			foreach($row_bill_data as $user_data_update) {
				LoggerComponent::log("Process Start For : " . $user_data_update["account_id"], self::LOG_FILE_DEL_REMOVED_USER_REC);
				try {
					$update_data_user = [
						"status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
						"modified" => DateTimeComponent::getDateTime()
					];
					$conditions_user_update = [
						"where" => [
							["where" => ["account_id", "=", $user_data_update["account_id"]]],
							["where" => ["status", "=", SHAppComponent::getValue("app_constant/STATUS_REMOVED")]],
						],
					];
					if ($model_user_master->update($update_data_user, $conditions_user_update)) {
						LoggerComponent::log("Process Completed For : " . $user_data_update["account_id"], self::LOG_FILE_DEL_REMOVED_USER_REC);
					} else {
						LoggerComponent::log("No Record For : " . $user_data_update["account_id"], self::LOG_FILE_DEL_REMOVED_USER_REC);
					}
				} catch(\Exception $e) {
					LoggerComponent::log("User Table Update Error for " . $user_data_update["account_id"] . " :". $e->getMessage(), self::LOG_FILE_DEL_REMOVED_USER_REC);
				}
			}
		}
	}

}