<?php
/**
 * Model file for operations on user_master table
 */
namespace App\Models;

class UserMaster extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "user_master";
        $this->_table_alias = "um";
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
            "user_type_id" => "User Type Id",
            "role_id" => "Role Id",
            "first_name" => "First Name",
            "last_name" => "Last Name",
            "email" => "Email",
            "password" => "Password",
            "password_salt_key" => "Password Salt Key",
            "address" => "Address",
            "city" => "City",
            "contact_no" => "Contact Number",
            "status" => "Status",
            "created_date" => "Created At",
            "modified_date" => "Modified At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "account_master" => ["account_master AS am", $this->_table_alias . ".account_id", "=", "am.id", "INNER"],
            "user_type_master" => ["user_type_master AS utm", $this->_table_alias . ".user_type_id", "=", "utm.id", "INNER"],
            "role_master" => ["role_master AS rm", $this->_table_alias . ".role_id", "=", "rm.id", "INNER"],
            "source_master" => ["source_master AS sm", $this->_table_alias . ".source_id", "=", "sm.id", "INNER"],
            "account_billing_master" => ["account_billing_master AS abm", $this->_table_alias . ".account_id", "=", "abm.account_id", "INNER"],
            "account_subscription_details" => ["account_subscription_details AS asd", "abm.current_subscription_id", "=", "asd.id", "INNER"]
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
            $tables = "FROM user_master um";

            // Fetching conditions and order
            $condition = "WHERE um.id = " . $id . " AND um.status <> " . $payload["deleted"];

            // Fetch total records
            $valid_row_sql = "SELECT um.id " . $tables . " " . $condition;
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
            "um.id",
            "um.first_name",
            "um.last_name",
            "um.email",
            "um.last_login",
            "um.role_id",
            "um.user_type_id",
            "um.created",
            "rm.name",
            "ui.invited_at",
            "ui.invited_by",
            "ui.joined_at",
            "um.status"
        ];

        // Fetch from tables
        $tables = "FROM user_master um LEFT JOIN user_invitations ui ON um.id = ui.user_id INNER JOIN role_master rm ON um.role_id = rm.id";

        // Fetching conditions and order
        $condition = "WHERE um.account_id = " . $payload["account_id"] . " AND um.status <> " . $payload["deleted"];

        // Add filters
        if (isset($query_params["role"])) {
            $condition .= " AND um.role_id = " . ((int) $query_params["role"]);
        }

        if (isset($query_params["query"])) {
            $search_condition = [
                "um.first_name LIKE '%" . $query_params["query"] . "%'",
                "um.last_name LIKE '%" . $query_params["query"] . "%'",
                "um.email LIKE '%" . $query_params["query"] . "%'",
                "rm.name LIKE '%" . $query_params["query"] . "%'"
            ];

            $condition .= " AND (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "name":
                $condition .= " ORDER BY um.first_name " . $query_params["order"] . ", um.last_name " . $query_params["order"];
                break;

            case "role_name":
                $condition .= " ORDER BY rm.name " . $query_params["order"];
                break;

            case "email":
                $condition .= " ORDER BY um.email " . $query_params["order"];
                break;

            case "last_login":
                $condition .= " ORDER BY um.last_login " . $query_params["order"];
                $condition .= ", um.created " . $query_params["order"];
                $condition .= ", ui.invited_at " . $query_params["order"];
                break;

            case "status":
                $condition .= " ORDER BY um.status " . $query_params["order"];
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
     * Get User by id
     *
     * @param $id (int): ID
     * @param $account_id (integer): Account Id
     *
     * @return (Array|null) User
     */
    public function getUserById($id, $account_id) {
        $user = [];

        $condition = [
            "fields" => [
                "um.var_first_name",
                "um.var_last_name",
                "um.var_email"
            ],
            "where" => [
                ["where" => ["um.id", "=", $id]]
            ]
        ];
        $user = $this->fetch($condition);

        return $user;
    }

    /**
     * Get User data by account_id, account_type_id, status
     *
     * @param $payload (array): Payload of other required information
     *
     * @return (Array|null) User data
     */
    public function getAccountUserData($payload = []) {
        $valid = false;
        $data = [];

        if (empty($payload)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM user_master as um LEFT JOIN account_master as am ON am.id = um.account_id";

            // Fetching conditions and order
            $condition = "WHERE um.status = " . $payload["active"] . " AND um.account_id = " . $payload["account_id"] . " AND um.user_type_id = " . $payload["user_type_id"];

            // Fetch total records
            $valid_row_sql = "SELECT um.id, um.first_name, um.last_name, um.email, am.ac_number " . $tables . " " . $condition;
            $this->_query_string = $valid_row_sql;

            $stmt = $this->_db->query($valid_row_sql);
            foreach ($stmt as $row) {
                $data = $row;
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
     * Get Users data for qwerty2 admin
     *
     * @param $payload (array): Payload of other required information
     *
     * @return (Array|null) User data
     */
    public function getUserListData($query_params, $payload = []) {
        $data = [];

        // Fetch fields
        $fields = [
            "um.id",
            "am.ac_number",
            "um.first_name",
            "um.last_name",
            "um.email",
            "um.last_login",
            "um.status",
            "um.created",
            "(SELECT pm.name FROM account_subscription_details asd INNER JOIN plan_master pm ON pm.id = asd.plan_id WHERE um.account_id = asd.account_id ORDER BY asd.id DESC LIMIT 1) AS plan_name"
        ];

        // Fetch from tables
        $tables = " FROM user_master um 
                    INNER JOIN account_master am ON um.account_id = am.id";

        // Add filters
        
        $condition = "";
        if (isset($query_params["status"])) {
            $condition .= " WHERE um.status = " . ((int) $query_params["status"]);
             
        }
        if (isset($query_params["query"])) {
            $condition .= " WHERE (um.first_name LIKE '%" . $query_params["query"] . "%' OR um.first_name LIKE '%" . $query_params["query"] ."%' OR um.email LIKE '%" . $query_params["query"] . "%' OR am.ac_number LIKE '" . $query_params["query"] ."' OR um.id LIKE '" . $query_params["query"] ."')";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "user_id":
                $condition .= " ORDER BY um.id " . $query_params["order"];
                break;
            case "name":
                $condition .= " ORDER BY um.first_name " . $query_params["order"];
                break;
            case "email":
                $condition .= " ORDER BY um.email " . $query_params["order"];
                break;
            case "ac_number":
                $condition .= " ORDER BY am.ac_number " . $query_params["order"];
                break;
            case "signup_date":
                $condition .= " ORDER BY um.created " . $query_params["order"];
                break;
            case "last_active":
                $condition .= " ORDER BY um.last_login " . $query_params["order"];
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

    /**
     * Get User by user id for qwerty2
     *
     * @param $id (int): ID
     *
     * @return (Array|null) User
     */
    public function getUserViewById($id) {
        $user = [];
        $user_id = $id;
        // Fetch fields
        $fields = [
            "um.first_name",
            "um.last_name",
            "um.created",
            "um.last_login",
            "um.verified",
            "ui.invited_at",
            "ui.joined_at",
            "um.account_id"

        ];

        // Fetch from tables
        $tables = " FROM user_master um
                    LEFT JOIN user_invitations ui ON ui.user_id = um.id
                    WHERE um.id = ".$user_id;
        try {
                $sql = "SELECT " . implode(", ", $fields) . " " . $tables;
                   
                    $this->_query_string = $sql;

                    $stmt = $this->_db->query($sql);
                    
                    foreach ($stmt as $row) {
                        $user = $row;
                    }
            } catch(\PDOException $e) {
                $this->_failed_query_message = $e->getMessage();
                $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        $account_id = $user['account_id'];
        $fields2 = [
            "um.id",
            "um.user_type_id",
            "um.first_name",
            "um.last_name",
            "um.email",
            "um.created",
            "um.last_login",
            "um.status",
            "um.outlook_allow_all",
            "am.id as account_id",
            "am.ac_number",
            "ao.name as company_name",
            "(SELECT pm.name FROM account_subscription_details asd INNER JOIN plan_master pm ON pm.id = asd.plan_id WHERE um.account_id = asd.account_id ORDER BY asd.id DESC LIMIT 1) as plan_name",
            "(SELECT id FROM account_billing_master abm WHERE account_id =". $account_id .") as abm_id"
        ];

        $tables2 = "FROM user_master um 
                    INNER JOIN account_master am ON am.id = um.account_id
                    LEFT JOIN account_organization ao ON ao.account_id = am.id
                    WHERE um.account_id = ".$account_id;

        try {
            $sql2 = "SELECT " . implode(", ", $fields2) . " " . $tables2;
               
                $this->_query_string = $sql2;
                
                $stmt2 = $this->_db->query($sql2);

                foreach ($stmt2 as $row2) {
                    $user["member_row"][] = $row2;
                }
        } catch(\PDOException $e) {
                $this->_failed_query_message = $e->getMessage();
                $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $user;
    }
	
	/**
	 * 
	 * @param int $query_params
	 * @param type $payload
	 * @return array of members
	 * @throws \Exception
	 */
	public function getMemberListBilling($payload = []) {
        $data = [];

        // Fetch fields
        $fields = [
            "um.id",
            "um.first_name",
            "um.last_name",
            "um.email",
			"um.user_type_id",
            "um.status",
			"ui.joined_at"
        ];

        // Fetch from tables
        $tables = "FROM ".$this->_table." AS um LEFT JOIN user_invitations AS ui ON ui.user_id=um.id";

        // Fetching conditions and order
        $condition = "WHERE um.account_id = " . $payload["account_id"] . " AND um.status <> " . $payload["deleted"];

        try {
            // Fetch total records
            
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