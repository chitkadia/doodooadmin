<?php
/**
 * Model file for operations on coupon_master table
 */
namespace App\Models;

class CouponMaster extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "coupon_master";
        $this->_table_alias = "cm";
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
            "code" => "Code",
            "valid_from" => "Valid From",
            "valid_to" => "Valid Till",
            "min_amount" => "Min Amount",
            "max_amount" => "Max Amount",
            "discount_type" => "Discount Type",
            "discount_value" => "Discount Value",
            "currency" => "Currency",
            "short_info" => "Short Description",
            "status" => "Status",
            "created" => "Created At",
            "modified" => "Modified At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }

    /**
     * Get coupon data if available
     *
     * @param $payload (array): Payload of other required information
     *
     * @return (array) Array of records | false (boolean)
     */
    public function checkCouponAvailable($payload = []) {
        $valid = false;
        $data = [];

        if (empty($payload)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM coupon_master";

            // Fetching conditions and order
            $condition = "WHERE status <> " . $payload["inactive"] . " AND status <> " . $payload["deleted"] . " AND code = binary '" . $payload["coupon"] . "' AND valid_from <= " . $payload["current_timestamp"] . " AND valid_to >= " . $payload["current_timestamp"] . " AND min_amount <= " . $payload["amount"] . " AND (max_amount = 0 OR max_amount >= " . $payload["amount"] . ")";

            // Fetch total records
            $valid_row_sql = "SELECT id, discount_type, discount_value " . $tables . " " . $condition;
            $this->_query_string = $valid_row_sql;
            
            $stmt = $this->_db->query($valid_row_sql);
            foreach ($stmt as $row) {
                $data = $row;
            }

            $id = $this->_db->query($valid_row_sql)->fetchColumn(0);
            $discount_type = $this->_db->query($valid_row_sql)->fetchColumn(1);

            if (!empty($id)) {
                $valid = true;
            }

        } catch(\PDOException $e) {
            echo $e->getMessage();exit;
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
     * Get coupon data
     *
     * @param $payload (array): Payload of other required information
     *
     * @return (array) Array of records | false (boolean)
     */
    public function getCouponDetails($payload = []) {
        $valid = false;
        $data = [];
        
        if (empty($payload)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM coupon_master";

            // Fetching conditions and order
            $condition = "WHERE status = " . $payload["active"] . " AND code = '" . $payload["code"] . "'";

            // Fetch total records
            $valid_row_sql = "SELECT id, discount_type, discount_value " . $tables . " " . $condition;
            
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
     * Set coupon data if available
     *
     * @param $payload (array): Payload of other required information
     *
     * @return (array) Array of setted records
     */
    public function setCouponValue($payload = []) {
        $postData = [];
        $coupon_code_value = $this->getCouponDetails($payload);

        if (!$coupon_code_value) {
            $postData['coupon_id'] = null;
            $postData["discount_type"] = "AMT";
            $postData["discount_value"] = 0;
            $postData["discount_amount"] = 0;
            $postData["total_amount"] = $payload["amount_total"];
        } else {
            $postData["coupon_id"] = $coupon_code_value["id"];
            $postData["discount_type"] = $coupon_code_value["discount_type"];
            $postData["discount_value"] = $coupon_code_value["discount_value"];

            if ($coupon_code_value["discount_type"] == "PER") {
                // $discount_amount_value = ($payload["amount_total"] / $coupon_code_value["discount_value"]) % 100;
                $discount_amount_value = ($payload["amount_total"] / $coupon_code_value["discount_value"]);
            } else {
                $discount_amount_value = $coupon_code_value["discount_value"];
            }

            $postData["discount_amount"] = $discount_amount_value; // Discount Amount

            $total_amt_after_discount = $payload["amount_total"] - $discount_amount_value;

            $postData["total_amount"] = $total_amt_after_discount; // Total Amount Calculation
        }
        
        return $postData;
    }

}