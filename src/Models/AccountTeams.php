<?php
/**
 * Model file for operations on account_teams table
 */
namespace App\Models;

class AccountTeams extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "account_teams";
        $this->_table_alias = "at";
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
            "source_id" => "Source",
            "name" => "Name",
            "user_id" => "Created By",
            "owner_id" => "Owned By",
            "manager_id" => "Managed By",
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
            "owner" => ["user_master AS um", $this->_table_alias . ".owner_id", "=", "um.id", "INNER"],
            "manager" => ["user_master AS umm", $this->_table_alias . ".manager_id", "=", "umm.id", "INNER"],
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
            $tables = "FROM account_teams at";

            // Fetching conditions and order
            $condition = "WHERE at.id = " . $id . " AND at.account_id = " . $payload["account_id"] . " AND at.status <> " . $payload["deleted"];

            // Fetch total records
            $valid_row_sql = "SELECT at.id " . $tables . " " . $condition;
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
            "at.id",
            "at.manager_id",
            "at.owner_id",
            "(SELECT CONCAT(umm.first_name, ' ', umm.last_name) FROM user_master umm WHERE at.manager_id = umm.id) AS managed_by",
            "(SELECT CONCAT(umo.first_name, ' ', umo.last_name) FROM user_master umo WHERE at.owner_id = umo.id) AS owned_by",
            "at.name",
            "(SELECT COUNT(atm.id) FROM account_team_members atm WHERE atm.account_team_id = at.id AND atm.status <> " . $payload["deleted"] . ") AS total_members"
        ];

        // Fetch from tables
        $tables = "FROM account_teams at";

        // Fetching conditions and order
        $condition = "WHERE at.account_id = " . $payload["account_id"] . " AND at.status <> " . $payload["deleted"];

        // Add filters
        if (isset($query_params["owned_by"])) {
            $condition .= " AND at.owner_id = " . ((int) $query_params["owned_by"]);
        }
        if (isset($query_params["managed_by"])) {
            $condition .= " AND at.manager_id = " . ((int) $query_params["managed_by"]);
        }

        if (isset($query_params["query"])) {
            $search_condition = [
                "managed_by LIKE '%" . $query_params["query"] . "%'",
                "owned_by LIKE '%" . $query_params["query"] . "%'",
                "at.name LIKE '%" . $query_params["query"] . "%'"
            ];

            $condition .= " HAVING (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "name":
                $order_by = " ORDER BY at.name " . $query_params["order"];
                break;

            case "owned_by":
                $order_by = " ORDER BY owned_by " . $query_params["order"];
                break;

            case "managed_by":
                $order_by = " ORDER BY managed_by " . $query_params["order"];
                break;

            case "total_members":
                $order_by = " ORDER BY total_members " . $query_params["order"];
                break;

            default:
                $order_by = " ORDER BY at.id DESC";
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
     * Get listing of teams
     *
     * @param $payload (array): Payload of other required information
     *
     * @return (array) Array of records
     */
    public function getTeamList($payload = []) {
        $data = [];

        // Fetch fields
        $fields = [
            "at.id",
            "at.name"
        ];

        // Fetch from tables
        $tables = "FROM account_teams at 
        INNER JOIN account_team_members atm ON at.id = atm.account_team_id ";

        // Fetching conditions and order
        $condition = "WHERE at.account_id = " . $payload["account_id"] . " 
        AND ( at.owner_id = " . $payload["user_id"] . " OR atm.user_id = " . $payload["user_id"] . " ) 
        AND at.status <> " . $payload["deleted"] . " 
        AND atm.status <> " . $payload["deleted"];

        $condition .= " GROUP BY at.id";

        try {

            $sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition;
            $this->_query_string = $sql;

            $stmt = $this->_db->query($sql);
            foreach ($stmt as $row) {
                $data[] = $row;
            }

            $stmt = null;

        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $data;
    }

}
