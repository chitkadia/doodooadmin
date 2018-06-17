<?php
/**
 * Model file for operations on account_billing_master table
 */
namespace App\Models;

class PaymentDetails extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "payment_details";
        $this->_table_alias = "pds";
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
            "purchase_detail_id" => "Purchase Detail ID",
            "user_id" => "User ID",
            "order_id" => "Order ID",
            "customer_id" => "Customer ID",
            "transaction_id" => "Transaction ID",
            "transaction_amount" => "Transaction Amount",
            "transaction_date" => "Transaction Date",
            "bank_transaction_id" => "Bank Transaction ID",
            "bank_name" => "Bank Name",
            "payment_mode" => "Payment Mode",
            "checksumhash" => "Checksumhash",
            "status" => "Status",
            "created_date" => "Created Date"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "product_master" => ["product_master AS pm", $this->_table_alias . ".product_id", "=", "pm.id", "INNER"]
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
            "pds.id",
            "pds.user_id",
            "pds.order_id",
            "pds.customer_id",
            "pds.transaction_id",
            "pds.transaction_amount",
            "pds.transaction_date",
            "pds.bank_transaction_id",
            "pds.bank_name",
            "pds.payment_mode",
            "pds.status"
        ];

        // Fetch from tables
        $tables = "FROM " . $this->_table . " AS " . $this->_table_alias;

        // Fetching conditions and order
        $condition = "WHERE pds.status <> " . $payload["deleted"];

        // Add filters
        
        if (isset($query_params["query"])) {
            $search_condition = [
                "pds.order_id LIKE '%" . $query_params["query"] . "%'"
            ];

            $condition .= " HAVING (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "pds.transaction_date":
                $order_by = " ORDER BY pds.transaction_date " . $query_params["order"];
                break;

            default:
                $order_by = " ORDER BY pds.id DESC";
                break;
        }

        try {
            // Fetch total records
            $count_sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition;
            $this->_query_string = $count_sql;

            $number_of_rows = $this->_db->query($count_sql)->rowCount();
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
            $data["rows"] = [];
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
            $tables = "FROM " . $this->_table;

            // Fetching conditions and order
            $condition = "WHERE id = " . $id . " AND status <> " . $payload["deleted"];

            // Fetch total records
            $valid_row_sql = "SELECT id " . $tables . " " . $condition;
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

    public function getSubCategoryList($conditions) {
        $data = [];

        // Fetch fields
        $fields = [
            "id",
            "sub_category_name"
        ];

        // Fetch from tables
        $tables = "FROM product_sub_category";

        // Fetching conditions and order
        $condition = "WHERE id IN (" . $conditions["ids"] . ")";

        $sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition;
        $this->_query_string = $sql;
        $data = [];
        $stmt = $this->_db->query($sql);
        foreach ($stmt as $row) {
            $data[] = $row;
        }

        $stmt = null;

        return $data;
    }
    
}