<?php
/**
 * Model file for operations on account_billing_master table
 */
namespace App\Models;

class AccountBillingMaster extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "account_billing_master";
        $this->_table_alias = "abm";
    }

    /**
     * @override
     */
    protected function _setPkColumn() {
        $this->_pk_column = "id";
    }

    /**
     * @override
     */
    protected function _setFields() {
        $this->_fields = [
            "id" => "Id",
            "account_id" => "Account",
            "plan_id" => "Plan",
            "team_size" => "Team Size",
            "email_acc_seats" => "Email Account Seats",
            "current_subscription_id" => "Current Subscription",
            "next_subscription_updates" => "Adjustments for Next Subscription",
			"configuration" => "Configuration",
            "credit_balance" => "Credit Balance",
            "status" => "Status",
            "created" => "Created At",
            "modified" => "Modified At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "account_master" => ["account_master AS am", $this->_table_alias . ".account_id", "=", "am.id", "INNER"],
            "user_master" => ["user_master AS um", $this->_table_alias . ".account_id", "=", "um.account_id", "INNER"],
            "plan_master" => ["plan_master AS pm", $this->_table_alias . ".plan_id", "=", "pm.id", "INNER"],
            "account_subscription_details" => ["account_subscription_details AS asd", $this->_table_alias . ".current_subscription_id", "=", "asd.id", "INNER"],
            "payment_method_master_left" => ["payment_method_master AS pmm", "asd.payment_method_id", "=", "pmm.id", "LEFT"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }
    
    /**
     * Get current active subscription
     *
     * @param $payload (array): Payload of other required information
     *
     * @return (array) Array of records | false (boolean)
     */
    public function getActiveBillingDetails($payload = []) {
        $valid = false;
        $data = [];

        if (empty($payload)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM account_billing_master";

            // Fetching conditions and order
            if ($payload["active_required"] == false) {
                $condition = "WHERE status != " . $payload["delete"] . " AND account_id = " . $payload["account_id"];
            } else {
                $condition = "WHERE status = " . $payload["active"] . " AND account_id = " . $payload["account_id"];
            }
            
            // Fetch total records
            $valid_row_sql = "SELECT current_subscription_id, credit_balance, plan_id, team_size, email_acc_seats, next_subscription_updates, configuration, status " . $tables . " " . $condition;
            $this->_query_string = $valid_row_sql;

            $stmt = $this->_db->query($valid_row_sql);
            if (count($stmt) > 0) {
                foreach ($stmt as $row) {
                    $data = $row;
                }
            } else {
                return false;
            }
        } catch (\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        if (count($data) > 0) {
            return $data;
        } else {
            return false;
        }
    }

    /**
     * Create the customer in stripe
     *
     * @param $payload (array): Payload of other required information
     *
     * @return (object) customer object
     */
    public function createCustomerStripe($payload = []) {
        $customer = \Stripe\Customer::create(array(
            "email" => $payload["email"],
            "source" => $payload["source"],
        ));
        return $customer;
    }

    /**
     * Calculate the proration
     *
     * @param $payload (array): Payload of other required information
     * @param $customer (object): Object of the customer
     *
     * @return (decimal) prorate amount | false (boolean)
     */
    public function calculateProrationAmount($payload = [], $customer) {
        $valid = false;
        $proration_date = time();

        if (!empty($customer->subscriptions->data)) {
            $subscription_id = $customer->subscriptions->data[0]->id;
            $subscribe = $customer->subscriptions->retrieve($subscription_id); //RETRIEVE SUBSCRIPTION

            $plan_start_date = $subscribe->current_period_start;
            $plan_end_date = $subscribe->current_period_end;
			
			$user_seats_total = $payload['team_size'] * $payload['user_seat_amount']; // User Account Total Amount
			$email_acc_seats_total = $payload['email_acc_seats'] * $payload['email_acc_amount']; // Email Account Total Amount
			$total_amount_sum = $user_seats_total + $email_acc_seats_total;
			
            $final_cost1 = ($plan_end_date - $proration_date) / ($plan_end_date - $plan_start_date) * $total_amount_sum;
            $final_cost = number_format((float) $final_cost1, 2, '.', '');
            return $final_cost;
        } else {
            return $valid;
        }
    }

    /**
     * Update the card details if card not same
     *
     * @param $stripeToken (string): StripeToken of the given card
     * @param $customer (object): Object of the customer
     */
    public function updateCardifNotSame($stripeToken, $customer) {
        $tokenData = \Stripe\Token::retrieve($stripeToken);
        $thisCard = $tokenData['card'];
        $custCards = $customer->sources->data;

        if ($custCards[0]['fingerprint'] != $thisCard['fingerprint']) {
            $customer->sources->retrieve($custCards[0]['id'])->delete();
            $customer->sources->create(array('source' => $stripeToken));
            $customer->save();
        }
    }

    /**
     * Check if customer is exist in stripe or not
     *
     * @param $payload (array): Payload of other required information
     *
     * @return (boolean) if found => true or false
     */
    public function checkStripeCustomerExist($payload = []) {
        $valid = false;

        if (empty($payload)) {
            return $valid;
        }

        try {
            $customer = \Stripe\Customer::retrieve($payload["tp_customer_id"]);
            if ($customer->deleted != null) {
                $valid = false;
            } else {
                $valid = true;
            }
        } catch (\Exception $e) {
            $valid = false;
        }
                
        return $valid;
    }
    
    /**
     * To get active plan prorate amount
     *
     * @param $payload (array): Payload of other required information
     *
     * @return (array): If valid then return prorate amount and if not then return message
     */
    public function getActivePlanProrationAmount($customer_id) {
		$valid = [];
		try {
			$customer = \Stripe\Customer::retrieve($customer_id); //RETRIEVE CUSTOMER
			$subscription_id = $customer->subscriptions->data[0]->id;
			$subscribe = $customer->subscriptions->retrieve($subscription_id); //RETRIEVE SUBSCRIPTION

			$proration_date = time();

			$plan_start_date = $subscribe->current_period_start;
			$plan_end_date = $subscribe->current_period_end;
			$main_plan_amount = $subscribe->items->data[0]->plan->amount;
			$email_plan_amount = $subscribe->items->data[1]->plan->amount;
			$main_plan_amount_quant = $subscribe->items->data[0]->quantity;
			$email_plan_amount_quant = $subscribe->items->data[1]->quantity;
			$main_total_amount_to_sum = $main_plan_amount * $main_plan_amount_quant;
			$email_total_amount_to_sum = $email_plan_amount * $email_plan_amount_quant;
			$total_amount_cent = $main_total_amount_to_sum + $email_total_amount_to_sum;
			$total_amount = $total_amount_cent/100;

			$proration_cost = ($plan_end_date - $proration_date) / ($plan_end_date - $plan_start_date) * $total_amount;

			$final_cost = number_format((float) $proration_cost, 2, '.', '');
			$valid = [
				"valid" => true,
				"prorate_amount" => $final_cost
			];
			
		} catch(\Exception $e) {
            $valid = [
				"valid" => false,
				"error_message" => $e->getMessage()
			];
        }
        
        return $valid;
    }
    
	/**
	 * Function used to fetch the records of subscription that are going to be cancel
	 * 
	 * @param type $payload
	 * @return array
	 * @throws \Exception
	 * 
	 */
    public function fetchCancelSubscription($payload = []) {
        $data = [];
		$current_time_stamp = $payload["current_time_stamp"];
		$before_five_minutes = $current_time_stamp - 300; // BEFORE FIVE MINUTE CHECK
		$adter_five_minute = $current_time_stamp + 300; // AFTER FIVE MINUTE CHECK
        try {
            // Fetch from tables
            $tables = "FROM account_billing_master as abm LEFT JOIN account_subscription_details as asd ON abm.current_subscription_id = asd.id";

            // Fetching conditions and order
            $condition = "WHERE abm.status <> " . $payload["status"] . " AND JSON_EXTRACT(abm.`next_subscription_updates`, '$.cancel_applied') > 0 AND abm.next_subscription_updates->'$.cancel_applied' = true AND asd.end_date >= " . $before_five_minutes . " AND asd.end_date < " . $adter_five_minute;

            // Fetch total records
            $valid_row_sql = "SELECT abm.current_subscription_id, abm.next_subscription_updates, asd.team_size, asd.email_acc_seats, asd.account_id " . $tables . " " . $condition;
            $this->_query_string = $valid_row_sql;

            $stmt = $this->_db->query($valid_row_sql);
            if (count($stmt) > 0) {
                foreach ($stmt as $row) {
                    $data[] = $row;
                }
            } else {
                return false;
            }
        } catch (\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        if (count($data) > 0) {
            return $data;
        } else {
            return false;
        }
    }

    /**
     * Function used to fetch the records of active billing
     * 
     * @param type $payload
     * @return array
     * @throws \Exception
     * 
     */
    public function fetchActiveBillingDetails($payload = []) {
        $data = [];
        $current_time_stamp = $payload["current_time_stamp"];
        $before_five_minutes = $current_time_stamp - 300; // BEFORE FIVE MINUTE CHECK
        $adter_five_minute = $current_time_stamp + 300; // AFTER FIVE MINUTE CHECK
        try {
            // Fetch from tables
            $tables = "FROM account_billing_master as abm INNER JOIN account_subscription_details as asd ON abm.current_subscription_id = asd.id";

            // Fetching conditions and order
            $condition = "WHERE abm.status <> " . $payload["status"] . " AND asd.end_date >= " . $before_five_minutes . " AND asd.end_date < " . $adter_five_minute . " AND abm.plan_id <> " . $payload["plan"] . " AND abm.plan_id <> " . $payload["trial_plan"];

            // Fetch total records
            $valid_row_sql = "SELECT abm.current_subscription_id, abm.next_subscription_updates, abm.team_size, abm.email_acc_seats, abm.account_id " . $tables . " " . $condition;
            $this->_query_string = $valid_row_sql;
            
            $stmt = $this->_db->query($valid_row_sql);
            if (count($stmt) > 0) {
                foreach ($stmt as $row) {
                    $data[] = $row;
                }
            } else {
                return false;
            }
        } catch (\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        if (count($data) > 0) {
            return $data;
        } else {
            return false;
        }
    }

    /**
     * Get Accounts and plan data for qwerty2 admin
     *
     * @param $payload (array): Payload of other required information
     *
     * @return (Array|null) Accounts and plan data
     */
    public function getUserAccountsData($query_params) {
        $data = [];

        // Fetch fields
        $fields = [
            "um.id as user_id",
            "um.email as admin_email",
            "am.ac_number",
            "abm.id as as_id",
            "abm.created",
            "asd.start_date",
            "asd.end_date",
            "pm.name as plan_name"
        ];

        // Fetch from tables
        $tables = " FROM account_billing_master abm 
                    INNER JOIN account_master am ON abm.account_id = am.id 
                    INNER JOIN user_master um ON am.id = um.account_id 
                    INNER JOIN account_subscription_details asd ON abm.current_subscription_id = asd.id 
                    INNER JOIN plan_master pm ON pm.id = abm.plan_id";

        // Add search
        $condition = "";
        /*if (isset($query_params["status"])) {
            $condition .= " WHERE um.status = " . ((int) $query_params["status"]);
             
        }*/
        if (isset($query_params["query"])) {
            $condition .= " WHERE (am.ac_number LIKE '" . $query_params["query"] ."' OR um.id LIKE '" . $query_params["query"] ."' OR um.email LIKE '%" . $query_params["query"] ."%' OR pm.name LIKE '%" . $query_params["query"] ."%')";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "user_id":
                $condition .= " ORDER BY um.id " . $query_params["order"];
                break;
            case "ac_number":
                $condition .= " ORDER BY am.ac_number " . $query_params["order"];
                break;
            case "admin_email":
                $condition .= " ORDER BY um.email " . $query_params["order"];
                break;
            case "created":
                $condition .= " ORDER BY asd.created " . $query_params["order"];
                break;
            case "plan_start_date":
                $condition .= " ORDER BY asd.start_date " . $query_params["order"];
                break;
            case "plan_end_date":
                $condition .= " ORDER BY asd.end_date " . $query_params["order"];
                break;
            default: 
                $condition .= " ORDER BY um.id DESC";
                break;
        }

        try {
            // Fetch total records
            $count_sql = "SELECT COUNT(um.id) " . $tables . " " . $condition;
            $this->_query_string = $count_sql;

            $number_of_rows = $this->_db->query($count_sql)->fetchColumn();
            $total_pages = ceil($number_of_rows / $query_params["per_page"]);

            // If page doesn't exists, then get first page data
            if ($query_params["page"] > $total_pages) {
                $query_params["page"] = 1;
            }
            $offset = ($query_params["page"] - 1) * $query_params["per_page"];

            // Add limit
            $limit = "LIMIT " . $offset . ", " . $query_params["per_page"];

            $sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition . " " . $limit;
           
            $this->_query_string = $sql;

            $stmt = $this->_db->query($sql);
            $data["rows"] = [];
            foreach ($stmt as $row) {
                $data["rows"][] = $row;
            }

            $data["total_records"] = $number_of_rows;
            $data["total_pages"] = $total_pages;
            $data["current_page"] = $query_params["page"];
            $data["per_page"] = $query_params["per_page"];

            $stmt = null;
           
        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }
        
        return $data;
    }
}