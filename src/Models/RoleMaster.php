<?php
/**
 * Model file for operations on role_master table
 */
namespace App\Models;

class RoleMaster extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "role_master";
        $this->_table_alias = "rm";
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
            "user_id" => "Added By",
            "source_id" => "Source",
            "code" => "Code",
            "name" => "Name",
            "short_info" => "Short Description",
            "is_system" => "System Role",
            "for_customers" => "Is Role for Customers",
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
            "source_master" => ["source_master AS sm", $this->_table_alias . ".source_id", "=", "sm.id", "INNER"]
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
            $tables = "FROM role_master rm LEFT JOIN user_master um ON rm.user_id = um.id";

            // Fetching conditions and order
            $condition = "WHERE rm.id = " . $id . " AND (rm.account_id = " . $payload["account_id"] . " OR rm.account_id IS NULL) AND rm.for_customers = 1 AND rm.status <> " . $payload["deleted"];

            // Fetch total records
            $valid_row_sql = "SELECT rm.id " . $tables . " " . $condition;
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
            "rm.id",
            "rm.user_id",
            "rm.name",
            "um.first_name",
            "um.last_name",
            "rm.is_system",
            "(SELECT COUNT(um.id) FROM user_master um WHERE um.role_id = rm.id AND um.account_id = " . $payload["account_id"] . " AND um.status <> " . $payload["deleted"] . ") AS total_users"
        ];

        // Fetch from tables
        $tables = "FROM role_master rm LEFT JOIN user_master um ON rm.user_id = um.id";

        // Fetching conditions and order
        $condition = "WHERE (rm.account_id = " . $payload["account_id"] . " OR rm.account_id IS NULL) AND rm.for_customers = 1 AND rm.status <> " . $payload["deleted"];

        // Add filters
        if (isset($query_params["user"])) {
            $condition .= " AND rm.user_id = " . ((int) $query_params["user"]);
        }

        if (isset($query_params["query"])) {
            $search_condition = [
                "um.first_name LIKE '%" . $query_params["query"] . "%'",
                "um.last_name LIKE '%" . $query_params["query"] . "%'",
                "rm.name LIKE '%" . $query_params["query"] . "%'"
            ];

            $condition .= " AND (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "name":
                $order_by = " ORDER BY rm.name " . $query_params["order"];
                break;

            // case "created_by":
            //     $order_by = " ORDER BY um.first_name " . $query_params["order"] . ", um.last_name" . $query_params["order"];
            //     break;

            case "total_users":
                $order_by = " ORDER BY total_users " . $query_params["order"];
                break;

             case "created_by":
                $order_by = " ORDER BY rm.user_id " . $query_params["order"];
                break;

            default:
                $order_by = " ORDER BY rm.id DESC";
                break;
        }

        try {
            // Fetch total records
            $count_sql = "SELECT COUNT(rm.id) " . $tables . " " . $condition;
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

            $sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition . $order_by . " " . $limit;
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
     * Check row can be deleted or not
     *
     * @param $id (integer): Id of the record
     *
     * @return (boolean) Can be deleted or not
     */
    public function canDelete($id) {
        
    }

}