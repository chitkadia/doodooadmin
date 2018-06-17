<?php
/**
 * Model file for operations on account_sending_methods table
 */
namespace App\Models;

use \App\Components\DateTimeComponent;
use \App\Components\SHAppComponent;
use \PDO as PDO;

class AccountSendingMethods extends AppModel {

    // Initialize model
    use ModelInitialize;

    const ENTERPRISE_PLAN_QUOTA = 2000;
    const OTHER_PLAN_QUOTA = 200;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "account_sending_methods";
        $this->_table_alias = "asm";
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
            "email_sending_method_id" => "Sending Method Type",
            "source_id" => "Source",
            "name" => "Name",
            "from_name" => "From Name",
            "from_email" => "From Email",
            "payload" => "Connection Information",
            "incoming_payload" => "Incoming Server Information",
            "connection_status" => "Connection Status",
            "connection_info" => "Connection Information",
            "last_connected_at" => "Last Connected At",
            "last_error" => "Last Error Message",
            "total_mail_sent" => "Total Mails Sent",
            "total_mail_failed" => "Total Mails Failed",
            "total_mail_replied" => "Total Mails Replied",
            "total_mail_bounced" => "Total Mails Bounced",
            "public" => "Public?",
            "total_limit" => "Total Limit",
            "credit_limit" => "Credit Limit",
            "last_reset" => "Last Reset",
            "next_reset" => "Next Reset",
            "status" => "Status",
			"is_default" => "Is Default",
			"is_outlook" => "Is Outlook",
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
            "email_sending_method_master" => ["email_sending_method_master AS esmm", $this->_table_alias . ".email_sending_method_id", "=", "esmm.id", "INNER"],
            "source_master" => ["source_master AS sm", $this->_table_alias . ".source_id", "=", "sm.id", "INNER"],
            "account_billing_master" => ["account_billing_master AS abm", $this->_table_alias . ".account_id", "=", "abm.account_id", "INNER"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }

    /**
     * Get single record of account sending method by ID
     * @param $id (integer): Id of the record
     * @param $payload (array): Payload of other required information
     *
     * @return Array of a record
     */

    public function getSendingMethodById($id, $payload = []) {
        if (empty($id) || empty($payload)) {
            return null;
        }

        try {
            // Fetch from tables
            $tables = "FROM account_sending_methods asm ";

            // Fetching conditions and order
            $condition = "WHERE asm.id = " . $id . " AND asm.status <> " . $payload["deleted"];

            // Fetch total records
            $valid_row_sql = "SELECT asm.* " . $tables . " " . $condition;
            $this->_query_string = $valid_row_sql;

            return $this->_db->query($valid_row_sql)->fetch();

        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }
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
            $tables = "FROM account_sending_methods asm INNER JOIN user_master um ON asm.user_id = um.id";

            // Fetching conditions and order
            $condition = "WHERE asm.id = " . $id . " AND asm.account_id = " . $payload["account_id"] . " AND asm.user_id = " . $payload["user_id"] . " AND asm.status <> " . $payload["deleted"];

            if (!empty($payload["type"])) {
                $condition .= " AND asm.email_sending_method_id = " . ((int) $payload["type"]);
            }

            // Fetch total records
            $valid_row_sql = "SELECT asm.id " . $tables . " " . $condition;
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
            "asm.id",
            "asm.user_id",
            "asm.name",
            "asm.from_name",
            "asm.from_email",
            "um.first_name",
            "um.last_name",
            "asm.email_sending_method_id",
            "asm.total_mail_sent",
            "asm.total_mail_failed",
            "asm.total_mail_bounced",
            "asm.last_error",
            "asm.status",
            "asm.credit_limit",
            "asm.next_reset",
            "asm.connection_status",
            "asm.last_connected_at",
            "asm.public",
			"asm.is_default"
        ];

        // Fetch from tables
        $tables = "FROM account_sending_methods asm INNER JOIN user_master um ON asm.user_id = um.id";

        // Fetching conditions and order
        $condition = "WHERE asm.account_id = " . $payload["account_id"] . " AND asm.user_id = " . $payload["user_id"] . " AND asm.status <> " . $payload["deleted"] . " AND asm.is_outlook <> " . $payload["outlook_flag_yes"];

        // Add filters
        if (isset($query_params["status"])) {
			$condition .= " AND asm.status = " . ((int) $query_params["status"]);
        }
        if (isset($query_params["type"])) {
            $condition .= " AND asm.email_sending_method_id = " . ((int) $query_params["type"]);
        }

        if (isset($query_params["query"])) {
            $search_condition = [
                //"um.first_name LIKE '%" . $query_params["query"] . "%'",
                //"um.last_name LIKE '%" . $query_params["query"] . "%'",
                "asm.name LIKE '%" . $query_params["query"] . "%'",
                "asm.from_email LIKE '%" . $query_params["query"] . "%'"
            ];

            $condition .= " AND (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "name":
                $condition .= " ORDER BY asm.name " . $query_params["order"];
                break;

            case "created_by":
                $condition .= " ORDER BY um.first_name " . $query_params["order"] . ", um.last_name" . $query_params["order"];
                break;

            case "status":
                $condition .= " ORDER BY asm.status " . $query_params["order"];
                break;

            case "type":
                $condition .= " ORDER BY asm.email_sending_method_id " . $query_params["order"];
                break;

            case "mail_success":
                $condition .= " ORDER BY (asm.total_mail_sent - asm.total_mail_failed) " . $query_params["order"];
                break;

            case "mail_fail":
                $condition .= " ORDER BY asm.total_mail_failed " . $query_params["order"];
                break;

            case "mail_bounce":
                $condition .= " ORDER BY asm.total_mail_bounced " . $query_params["order"];
                break;

            case "from_email":
                $condition .= " ORDER BY asm.from_email " . $query_params["order"];
                break;

            default: 
                $condition .= " ORDER BY asm.id DESC";
                break;
        }

        try {
            // Fetch total records
            $count_sql = "SELECT COUNT(asm.id) " . $tables . " " . $condition;
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
	
	public function getEmailAccListBilling($payload = []) {
		$data = [];

        // Fetch fields
        $fields = [
            "asm.id",
            "asm.name",
            "asm.from_email",
			"um.first_name",
            "um.last_name",
			"asm.email_sending_method_id",
			"asm.status",
			"is_default"
        ];

        // Fetch from tables
        $tables = "FROM ".$this->_table." asm INNER JOIN user_master um ON asm.user_id = um.id";

        // Fetching conditions and order
        $condition = "WHERE asm.account_id = " . $payload["account_id"] . " AND asm.status <> " . $payload["deleted"] . " AND asm.is_outlook <> " . $payload["active"];

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
	
    /**
     * Function is used to set email quota based on plan
     */
    public function setQuota($plan_id, $email_account_id = 0, $account_id = 0) {
        $result = false;
        if (!empty($plan_id)) {

            $current_time = DateTimeComponent::getDateTime();

            $total_limit = 0;
            $credit_limit = 0;
            $next_reset = $current_time + (24 * 60 * 60);

            $model_account_sending_methods = new AccountSendingMethods();

            if ($plan_id == SHAppComponent::getValue("plan/ENTERPRISE_MONTHLY") || $plan_id == SHAppComponent::getValue("plan/ENTERPRISE_YEARLY")) {
                $total_limit = self::ENTERPRISE_PLAN_QUOTA;
                $credit_limit = self::ENTERPRISE_PLAN_QUOTA;
            } 
            if ($plan_id == SHAppComponent::getValue("plan/PLUS_MONTHLY") || $plan_id == SHAppComponent::getValue("plan/PLUS_YEARLY") || $plan_id == SHAppComponent::getValue("plan/PLUS_MONTHLY_TRIAL")) {
                $total_limit = self::OTHER_PLAN_QUOTA;
                $credit_limit = self::OTHER_PLAN_QUOTA;
                $next_reset = $current_time + (3 * 60 * 60);
            }
            if ($plan_id == SHAppComponent::getValue("plan/REGULAR_MONTHLY") || $plan_id == SHAppComponent::getValue("plan/REGULAR_YEARLY") || $plan_id == SHAppComponent::getValue("plan/FREE")) {
                $total_limit = self::OTHER_PLAN_QUOTA;
                $credit_limit = self::OTHER_PLAN_QUOTA;
            }
           
            $save_data = [
                "total_limit" => $total_limit,
                "credit_limit" => $credit_limit,
                "last_reset" => $current_time,
                "next_reset" => $next_reset
            ];

            try {
                if ($email_account_id != 0) {
                    $save_data["id"] = $email_account_id;
                    if ($this->save($save_data)) {
                        $result = true;
                    }
                } else {
                    $condition = [
                        "where" => [
                            ["where" => ["account_id", "=", $account_id]]
                        ],
                    ];

                    if ($this->update($save_data, $condition)) {
                        $result = true;
                    }
                }
            } catch(\Exception $e) {} 
        }
        return $result;
    }
	
    /**
     * Function is used to get remaining email quota.
     */
    public function getRemainingQuota($mail_account_id) {
        $return_array = [];

        if(empty($mail_account_id)) {
            return $return_array;
        }
        try {
            $condition = [
                "fields" => [
                    "id",
                    "credit_limit",
                    "last_reset",
                    "next_reset"
                ],
                "where" => [
                    ["where" => ["id", "=", (int) $mail_account_id ]],
                    ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ],
            ];

            $row_data = $this->fetch($condition);

            if (!empty($row_data["id"])) {
                $return_array["remaining_quota"] = $row_data["credit_limit"];
                $return_array["last_reset_date"] = $row_data["last_reset"];
                $return_array["next_reset_date"] = $row_data["next_reset"]; 
            }
        } catch(\Exception $e) {}

        return $return_array;
    }
	
    /**
     * Function is used to decrease email quota.
     */
    public function decreaseQuota($mail_account_id, $decrease_value = 1) {
        $result = false;

        if ( empty($mail_account_id) ) {
            return $result;
        }

        $decrease_value = (int) $decrease_value;
        if ($decrease_value < 0) {
            $decrease_value = 1;
        }

        try {
            $sql = "UPDATE " . $this->_table . " SET credit_limit = credit_limit - ?, modified = ? WHERE " . $this->_pk_column . " = ? AND credit_limit >= 1";
            
            $this->_query_string = $sql;
            $stmt = $this->_db->prepare($sql);

            $stmt->bindValue(1, $decrease_value, PDO::PARAM_INT);
            $stmt->bindValue(2, DateTimeComponent::getDateTime(), PDO::PARAM_STR);
            $stmt->bindValue(3, $mail_account_id, PDO::PARAM_INT);
            if ($stmt->execute()) {
                $result = true;
            }

            $stmt = null;

        } catch(\Exception $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $result;
    }
    /**
     * Update user mail account as disconnected
     */
    public function disConnectMailAccount($payload) {
        if(!empty($payload)) {
            try {
                $update_data = [
                    "id" => $payload["id"],
                    "connection_status" => 0,
                    "status" => SHAppComponent::getValue("app_constant/STATUS_INACTIVE"),
                    "last_connected_at" => DateTimeComponent::getDateTime(),
                    "last_error" => $payload["error_message"],
                    "modified" => DateTimeComponent::getDateTime()
                ];
                $this->save($update_data);

            } catch(\Exception $e) {
                //Do nothing
            }
        }
    }

    /**
     * check default account and count of non-default account
     */
    public function checkDefaultEmailAccount($payload = []) {
        $result = null;

        if (!empty($payload)) {

            $flag_no = SHAppComponent::getValue("app_constant/FLAG_NO");
            $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");
            $status_delete = SHAppComponent::getValue("app_constant/STATUS_DELETE");

            $condition = [
                "fields" => [
                    "asm.id"
                ],
                "where" => [
                    ["where" => ["asm.account_id", "=", $payload["account_id"]]],
                    ["where" => ["asm.status", "<>", $status_delete]]
                ]
            ];

            if ($payload["default_check"]) {
                $condition["where"][]["where"] = ["asm.user_id", "=", $payload["user_id"]];
                $condition["where"][]["where"] = ["asm.is_default", "=", $flag_yes];
            } else {
                $condition["fields"] = ["COUNT(asm.id) as total_count"];
                $condition["where"][]["where"] = ["asm.is_default", "=", $flag_no];
            }
                       
            try {            
                $result = $this->fetch($condition);
            } catch(\Exception $e) {}
        }

        return $result;
    }

    /**
     * Get listing of records
     *
     * @param $query_params (array): Query parameters
     *
     * @return (array) Array of records
     */
    public function getEmailAccountsData($query_params) {
        $data = [];

        // Fetch fields
        $fields = [
            "asm.id",
            "asm.user_id",
            "asm.email_sending_method_id",
            "asm.name",
            "asm.credit_limit",
            "asm.from_email",
            "asm.status",
            "asm.modified"
        ];

        // Fetch from tables
        $tables = "FROM account_sending_methods asm";
        
        // Fetching conditions and order
        //$condition = "WHERE asm.account_id = " . $payload["account_id"] . " AND asm.user_id = " . $payload["user_id"] . " AND asm.status <> " . $payload["deleted"] . " AND asm.is_outlook <> " . $payload["outlook_flag_yes"];

        // Add filters
        /*if (isset($query_params["status"])) {
            $condition .= " AND asm.status = " . ((int) $query_params["status"]);
        }
        if (isset($query_params["type"])) {
            $condition .= " AND asm.email_sending_method_id = " . ((int) $query_params["type"]);
        }*/
        $condition = "";
        if (isset($query_params["query"])) {
            $search_condition = [
                "asm.name LIKE '%" . $query_params["query"] . "%'",
                "asm.from_email LIKE '%" . $query_params["query"] . "%'"
            ];

            $condition .= " WHERE (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "name":
                $condition .= " ORDER BY asm.name " . $query_params["order"];
                break;

            case "type":
                $condition .= " ORDER BY asm.email_sending_method_id " . $query_params["order"];
                break;

            /*case "mail_success":
                $condition .= " ORDER BY (asm.total_mail_sent - asm.total_mail_failed) " . $query_params["order"];
                break;

            case "mail_fail":
                $condition .= " ORDER BY asm.total_mail_failed " . $query_params["order"];
                break;*/

            /*case "mail_bounce":
                $condition .= " ORDER BY asm.total_mail_bounced " . $query_params["order"];
                break;*/

            case "from_email":
                $condition .= " ORDER BY asm.from_email " . $query_params["order"];
                break;

            default: 
                $condition .= "ORDER BY asm.id DESC";
                break;
        }

        try {
            // Fetch total records
            $count_sql = "SELECT COUNT(asm.id) " . $tables . " " . $condition;
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

            /*echo $sql;
            exit;*/
            
            $stmt = $this->_db->query($sql);
            $data["rows"] = [];
            foreach ($stmt as $row) {
                $data["rows"][] = $row;
            }
            /*print_r($data);
            exit;*/
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