<?php
/**
 * Model file for operations on account_payment_details table
 */
namespace App\Models;

class AccountPaymentDetails extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "account_payment_details";
        $this->_table_alias = "apd";
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
            "account_subscription_id" => "Subscription",
            "currency" => "Currency",
            "amount_paid" => "Amount Paid",
            "payment_method_id" => "Paid Through",
            "tp_payload" => "Other Information",
            "tp_payment_id" => "Payment Id",
            "type" => "Type",
            "paid_at" => "Paid At",
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
            "account_subscription_details" => ["account_subscription_details AS asd", $this->_table_alias . ".account_subscription_id", "=", "asd.id", "INNER"],
            // "payment_method_master" => ["payment_method_master AS pmm", $this->_table_alias . ".payment_method_id", "=", "pmm.id", "INNER"],
            "coupon_master" => ["coupon_master AS cm", "asd.coupon_id", "=", "cm.id", "LEFT"],
            "plan_master" => ["plan_master AS pm", "asd.plan_id", "=", "pm.id", "INNER"],
            "account_invoice_details" => ["account_invoice_details AS aid", $this->_table_alias.".id", "=", "aid.account_payment_id", "INNER"],
            "user_master" => ["user_master AS um", "asd.account_id", "=", "um.id", "INNER"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }

    /**
     * Get listing of records
     *
     * @param $query_params (array): Query parameters
     * @param $payload (array): Payload of other required information
     *
     * @return (array) Array of records
     */
    public function getListData($query_params, $payload = []) {
        $data = [];

        // Fetch fields
        $fields = [
            "apd.id, apd.currency, apd.amount_paid, apd.type, apd.paid_at, apd.status, apd.created",
            "asd.plan_id, asd.payment_method_id",
            "pm.name AS plan_name",
            "pmm.name AS payment_method",
            "aid.id AS invoice_id",
			"asli.user_account_team_size AS team_size, asli.email_account_seat AS email_acc_seat"
        ];

        // Fetch from tables
        $tables = "FROM account_payment_details apd INNER JOIN account_subscription_details asd ON asd.id = apd.account_subscription_id INNER JOIN plan_master pm ON pm.id = asd.plan_id INNER JOIN payment_method_master pmm ON pmm.id = asd.payment_method_id LEFT JOIN account_invoice_details aid ON apd.account_subscription_id = aid.id LEFT JOIN account_subscription_line_items asli ON asli.current_subscription_id = asd.id";

        // Fetching conditions and order
        $condition = "WHERE apd.account_id = " . $payload["account_id"];

        if (isset($query_params["query"])) {
            $search_condition = [
                "apd.amount_paid LIKE '%" . $query_params["query"] . "%'",
                "asd.team_size LIKE '%" . $query_params["query"] . "&'",
                "pm.name LIKE '%" . $query_params["query"] . "&'",
                "pmm.name LIKE '%" . $query_params["query"] . "&'"
            ];

            $condition .= " AND (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "plan_name":
                $order_by = " ORDER BY pm.name " . $query_params["order"];
                break;
            
            case "team_size":
                $order_by = " ORDER BY asd.team_size " . $query_params["order"];
                break;
            
            case "amount_paid":
                $order_by = " ORDER BY apd.amount_paid " . $query_params["order"];
                break;
            
            case "payment_method":
                $order_by = " ORDER BY pmm.name " . $query_params["order"];
                break;
            
            case "paid_at":
                $order_by = " ORDER BY apd.paid_at " . $query_params["order"];
                break;

            default:
                $order_by = " ORDER BY apd.id DESC";
                break;
        }

        try {
            // Fetch total records
            $count_sql = "SELECT COUNT(apd.id) " . $tables . " " . $condition;
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

            $sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition . " " . $order_by . " " . $limit;
            
            $this->_query_string = $sql;
            
            $stmt = $this->_db->query($sql);
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

    /**
     * Check row validity (has user access to this row or not)
     *
     * @param $id (integer): Id of the record
     * @param $payload (array): Payload of other required information
     *
     * @return (boolean) Row valid or not
     */
    public function checkRowValidity($id, $payload = []) {
        $valid = false;

        if (empty($id) || empty($payload)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM account_payment_details apd";

            // Fetching conditions and order
            $condition = "WHERE apd.id = " . $id;

            // Fetch total records
            $valid_row_sql = "SELECT apd.id " . $tables . " " . $condition;
            $this->_query_string = $valid_row_sql;

            $id = $this->_db->query($valid_row_sql)->fetchColumn();

            if (!empty($id)) {
                $valid = true;
            }

        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $valid;
    }

}