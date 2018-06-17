<?php
/**
 * Model file for operations on account_folders table
 */
namespace App\Models;

class AccountFolders extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "account_folders";
        $this->_table_alias = "af";
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
            "user_id" => "Created By",
            "source_id" => "Source",
            "name" => "Name",
            "type" => "Type",
            "public" => "Is Public",
            "share_access" => "Shared Access",
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
            $tables = "FROM account_folders af INNER JOIN user_master um ON af.user_id = um.id";

            // Fetching conditions and order
            $condition = "WHERE af.id = " . $id . " AND af.account_id = " . $payload["account_id"] . " AND af.status <> " . $payload["deleted"];

            //if (!$payload["is_owner"]) {
                $condition .= " AND (af.user_id = " . $payload["user_id"] . " OR af.public = 1)";
            //}

            // Fetch total records
            $valid_row_sql = "SELECT af.id " . $tables . " " . $condition;
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
    public function getListDataTemplates($query_params, $payload = []) {
        $data = [];

        // Fetch fields
        $fields = [
            "af.id",
            "af.user_id",
            "um.first_name",
            "um.last_name",
            "af.name",
            "af.share_access",
            "af.public",
            "(SELECT COUNT(at.id) 
                FROM account_templates at
                WHERE at.account_folder_id = af.id 
                AND at.status <> " . $payload["deleted"] . "
            ) AS total_templates",
            "(SELECT COUNT(aft.id) FROM account_folder_teams aft WHERE aft.account_folder_id = af.id AND aft.status <> " . $payload["deleted"] . ") AS total_teams"
        ];

        // Fetch from tables
        $tables = "FROM account_folders af INNER JOIN user_master um ON af.user_id = um.id";

        // Fetching conditions and order
        $condition = "WHERE af.account_id = " . $payload["account_id"] . " AND af.status <> " . $payload["deleted"] . " AND af.type = 1";

        $team_join = "SELECT atm.account_team_id FROM account_team_members atm INNER JOIN account_folder_teams aft ON aft.account_team_id = atm.account_team_id WHERE atm.user_id = " . $payload["user_id"] . " AND atm.status <> " . $payload["deleted"] . " AND aft.status <> " . $payload["deleted"] . " AND af.id = aft.account_folder_id";
        $condition .= " AND (EXISTS (" . $team_join . ") OR af.user_id = " . $payload["user_id"] . " OR af.public = 1)";

        // Add filters
        if (isset($query_params["user"])) {
            $condition .= " AND af.user_id = " . ((int) $query_params["user"]);
        }
        if (isset($query_params["type"])) {
            $condition .= " AND af.type = " . ((int) $query_params["type"]);
        }

        if (isset($query_params["query"])) {
            $search_condition = [
                "um.first_name LIKE '%" . $query_params["query"] . "%'",
                "um.last_name LIKE '%" . $query_params["query"] . "%'",
                "af.name LIKE '%" . $query_params["query"] . "%'"
            ];

            $condition .= " AND (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "name":
                $order_by = " ORDER BY af.name " . $query_params["order"];
                break;

            case "created_by":
                $order_by = " ORDER BY um.first_name " . $query_params["order"] . ", um.last_name" . $query_params["order"];
                break;

            case "total_templates":
                $order_by = " ORDER BY total_templates " . $query_params["order"];
                break;

            default:
                $order_by = " ORDER BY af.id DESC";
                break;
        }

        try {
            // Fetch total records
            $count_sql = "SELECT COUNT(af.id) " . $tables . " " . $condition;
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
     * Get listing of records
     *
     * @param $query_params (array): Query parameters
     * @param $payload (array): Payload of other required information
     *
     * @return (array) Array of records
     */
    public function getListDataDocuments($query_params, $payload = []) {
        $data = [];
        $data["rows"] = [];

        // Fetch fields
        $fields = [
            "af.id",
            "af.user_id",
            "um.first_name",
            "um.last_name",
            "af.name",
            "af.share_access",
            "af.public",
            "(SELECT COUNT(dm.id) 
                FROM document_master dm 
                WHERE dm.account_folder_id = af.id 
                AND dm.status <> " . $payload["deleted"] . "
            ) AS total_documents",
            "(SELECT COUNT(aft.id) 
                FROM account_folder_teams aft 
                WHERE aft.account_folder_id = af.id 
                AND aft.status <> " . $payload["deleted"] . "
            ) AS total_teams"
        ];
     
        // Fetch from tables
        $tables = "FROM account_folders af INNER JOIN user_master um ON af.user_id = um.id";

        // Fetching conditions and order
        $condition = "WHERE af.account_id = " . $payload["account_id"] . " AND af.status <> " . $payload["deleted"] . " AND type = 2 ";

        $team_join = "SELECT atm.account_team_id FROM account_team_members atm INNER JOIN account_folder_teams aft ON aft.account_team_id = atm.account_team_id WHERE atm.user_id = " . $payload["user_id"] . " AND atm.status <> " . $payload["deleted"] . " AND aft.status <> " . $payload["deleted"] . " AND af.id = aft.account_folder_id";
        $condition .= " AND (EXISTS (" . $team_join . ") OR af.user_id = " . $payload["user_id"] . " OR af.public = 1)";

        // Add filters
        if (isset($query_params["user"])) {
            $condition .= " AND af.user_id = " . ((int) $query_params["user"]);
        }
        if (isset($query_params["type"])) {
            $condition .= " AND af.type = " . ((int) $query_params["type"]);
        }

        if (isset($query_params["query"])) {
            $search_condition = [
                "um.first_name LIKE '%" . $query_params["query"] . "%'",
                "um.last_name LIKE '%" . $query_params["query"] . "%'",
                "af.name LIKE '%" . $query_params["query"] . "%'"
            ];

            $condition .= " AND (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "name":
                $order_by = " ORDER BY af.name " . $query_params["order"];
                break;

            case "created_by":
                $order_by = " ORDER BY um.first_name " . $query_params["order"] . ", um.last_name" . $query_params["order"];
                break;

            case "total_documents":
                $order_by = " ORDER BY total_documents " . $query_params["order"];
                break;

            default:
                $order_by = " ORDER BY af.id DESC";
                break;
        }

        try {
            // Fetch total records
            $count_sql = "SELECT COUNT(af.id) " . $tables . " " . $condition;
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

}
