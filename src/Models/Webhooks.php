<?php
/**
 * Model file for operations on webhooks table
 */
namespace App\Models;

class Webhooks extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "webhooks";
        $this->_table_alias = "w";
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
            "user_id" => "User",
            "source_id" => "Source",
            "name" => "Name",
            "resource_id" => "Resource",
            "resource_condition" => "Condition",
            "post_url" => "URL",
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
            "user_master" => ["user_master AS um", $this->_table_alias . ".user_id", "=", "um.id", "INNER"],
            "source_master" => ["source_master AS sm", $this->_table_alias . ".source_id", "=", "sm.id", "INNER"],
            "resource_master" => ["resource_master AS rm", $this->_table_alias . ".resource_id", "=", "rm.id", "INNER"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
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
            $tables = "FROM webhooks wh INNER JOIN resource_master rm ON wh.resource_id = rm.id";

            // Fetching conditions and order
            $condition = "WHERE wh.id = " . $id . " AND wh.account_id = " . $payload["account_id"] . " AND wh.status <> " . $payload["deleted"];
            
            // Fetch total records
            $valid_row_sql = "SELECT wh.id " . $tables . " " . $condition;
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
            "wh.id",
            "wh.user_id",
            "wh.name",
            "wh.resource_id",
            "rm.resource_name",
            "wh.resource_condition",
            "wh.post_url",
            "wh.status"
        ];

        // Fetch from tables
        $tables = "FROM webhooks wh INNER JOIN resource_master rm ON wh.resource_id = rm.id";

        // Fetching conditions and order
        $condition = "WHERE wh.account_id = " . $payload["account_id"] . " AND wh.status <> " . $payload["deleted"];

        // Add filters
        if (isset($query_params["user"])) {
            $condition .= " AND wh.user_id = " . ((int) $query_params["user"]);
        }
        if (isset($query_params["status"])) {
            $condition .= " AND wh.status = " . ((int) $query_params["status"]);
        }
        if (isset($query_params["keyword"])) {
            $condition .= " AND (wh.name LIKE '%" . $query_params["keyword"] . "%' OR rm.resource_name LIKE '%" . $query_params["keyword"] . "%')";
        }
        

        // Add Order by
        switch ($query_params["order_by"]) {
            case "resource":
                $condition .= " ORDER BY rm.resource_name " . $query_params["order"];
                break;

            case "name":
                $condition .= " ORDER BY wh.name " . $query_params["order"];
                break;

            default: 
                $condition .= " ORDER BY wh.id DESC";
                break;
        }

        try {
            // Fetch total records
            $count_sql = "SELECT COUNT(wh.id) " . $tables . " " . $condition;
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