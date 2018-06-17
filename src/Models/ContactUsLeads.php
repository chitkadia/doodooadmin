<?php
/**
 * Model file for operations on account_billing_master table
 */
namespace App\Models;

class ContactUsLeads extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "contact_us_leads";
        $this->_table_alias = "cul";
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
            "name" => "Name",
            "email" => "Email",
            "address" => "Address",
            "city" => "City",
            "contact_no" => "Contact Number",
            "message" => "Message",
            "created_date" => "Created Date"
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
            "id",
            "name",
            "email",
            "address",
            "city",
            "contact_no",
            "message",
            "created_date"
        ];

        // Fetch from tables
        $tables = "FROM " . $this->_table;

        // Add filters
        
        if (isset($query_params["query"])) {
            $search_condition = [
                "name LIKE '%" . $query_params["query"] . "%'"
            ];

            $condition = " HAVING (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "name":
                $order_by = " ORDER BY name " . $query_params["order"];
                break;

            case "created_date":
                $order_by = " ORDER BY created_date " . $query_params["order"];
                break;

            default:
                $order_by = " ORDER BY id DESC";
                break;
        }

        try {
            // Fetch total records
            $count_sql = "SELECT " . implode(", ", $fields) . " " . $tables;
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

            $sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $order_by . " " . $limit;
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
    
}