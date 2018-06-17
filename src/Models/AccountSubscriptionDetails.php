<?php
/**
 * Model file for operations on account_subscription_details table
 */
namespace App\Models;

class AccountSubscriptionDetails extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "account_subscription_details";
        $this->_table_alias = "asd";
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
            "currency" => "Currency",
            "amount" => "Amount",
            "credit_balance" => "Credit Balance",
            "coupon_id" => "Coupon",
            "discount_type" => "Discount Type",
            "discount_value" => "Discount Value",
            "discount_amount" => "Discount Amount",
            "total_amount" => "Total Amount",
            "payment_method_id" => "Paid Through",
            "start_date" => "Subscription Start Date",
            "end_date" => "Subscription End Date",
            "next_subscription_id" => "Next Subscription",
            "tp_subscription_id" => "Subscription Id",
            "tp_customer_id" => "Customer Id",
			"type" => "Type",
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
            "plan_master" => ["plan_master AS pm", $this->_table_alias . ".plan_id", "=", "pm.id", "INNER"],
            "coupon_master" => ["coupon_master AS cm", $this->_table_alias . ".coupon_id", "=", "cm.id", "INNER"],
            "payment_method_master" => ["payment_method_master AS pmm", $this->_table_alias . ".payment_method_id", "=", "pmm.id", "INNER"],
			"account_subscription_line_items" => ["account_subscription_line_items AS asli", $this->_table_alias . ".id", "=", "asli.current_subscription_id", "INNER"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }
    
    /**
     * @param type $name Description
     */
    
    public function getActiveCustSubId($payload = []) {
        $valid = false;
        $data = [];
        
        if (empty($payload)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM account_subscription_details";

            // Fetching conditions and order
            $condition = "WHERE id = '" . $payload["id"] . "'";

            // Fetch total records
            $valid_row_sql = "SELECT start_date, end_date, tp_subscription_id, tp_customer_id " . $tables . " " . $condition;
            
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
     * Get customer details
     *
     * @param $payload (array): Payload of other required information
     *
     * @return (array) data of customer | false (boolean)
     */
    public function getUserExistDetails($payload = []) {
        $valid = false;
        $data = [];

        if (empty($payload)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM account_subscription_details";

            // Fetching conditions and order
            $condition = "WHERE account_id = " . $payload["account_id"];

            // Fetch total records
            $valid_row_sql = "SELECT id, tp_customer_id, tp_subscription_id " . $tables . " " . $condition . " ORDER BY id DESC LIMIT 1";
            
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
}