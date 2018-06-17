<?php
/**
 * Model file for operations on campaign_master table
 */
namespace App\Models;

class CampaignMaster extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "campaign_master";
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
            "account_id" => "Account",
            "user_id" => "Created By",
            "title" => "Title",
            "account_sending_method_id" => "Sent Via",
            "source_id" => "Source",
            "total_stages" => "Total Stages",
            "from_date" => "From Date",
            "to_date" => "To Date",
            "timezone" => "Timezone",
            "other_data" => "Other Information",
            "status_message" => "Status Change Message",
            "track_reply" => "Track Reply?",
            "track_click" => "Track Clicks?",
            "send_as_reply" => "Send as Reply?",
            "overall_progress" => "Progress",
            "priority" => "Priority",
            "is_bulk_campaign" => "Is Bulk Campaign",
            "snooze_notifications" => "Snooze Notifications?",
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
            "account_sending_methods" => ["account_sending_methods AS asm", $this->_table_alias . ".account_sending_method_id", "=", "asm.id", "LEFT"],
            "source_master" => ["source_master AS sm", $this->_table_alias . ".source_id", "=", "sm.id", "INNER"],
            "campaign_stages" => ["campaign_stages AS cst", $this->_table_alias . ".id", "=", "cst.campaign_id", "INNER"]
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
            $tables = "FROM campaign_master cm INNER JOIN user_master um ON cm.user_id = um.id";

            // Fetching conditions and order
            $condition = "WHERE cm.id = " . $id . " AND cm.account_id = " . $payload["account_id"] . " AND cm.status <> " . $payload["deleted"];

            // Fetch total records
            $valid_row_sql = "SELECT cm.id " . $tables . " " . $condition;
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
            "cm.id",
            "cm.title",
            "cm.user_id",
            "um.first_name",
            "um.last_name",
            "um.email as created_email",
            "cm.total_stages",
            "cm.timezone",
            "cm.status_message",
            "cm.priority",
            "cm.is_bulk_campaign",
            "cm.overall_progress",
            "cm.status",
            "cm.snooze_notifications",
            "cm.track_reply",
            "cm.track_click",
            "cm.account_sending_method_id",
            "asm.name AS sending_account",
            "asm.from_email AS sending_account_email",            
            "(SELECT code FROM email_sending_method_master esm WHERE esm.id = asm.email_sending_method_id) AS sending_method_name",
            "(SELECT SUM(cst.total_contacts - cst.total_deleted) FROM campaign_stages cst WHERE cm.id = cst.campaign_id AND cst.status <> " . $payload["deleted"] . ") AS total_contacts",
            "(SELECT COUNT(csq_s.id) FROM campaign_sequences csq_s WHERE cm.id = csq_s.campaign_id AND csq_s.progress = ". $payload["progress"] ." AND csq_s.status <> " . $payload["deleted"] . ") AS total_success",
            "(SELECT COUNT(csq_o.id) FROM campaign_sequences csq_o WHERE cm.id = csq_o.campaign_id AND csq_o.status <> " . $payload["deleted"] . " AND csq_o.open_count > 0 AND csq_o.is_bounce <> 1) AS total_open",
            "(SELECT COUNT(csq_r.id) FROM campaign_sequences csq_r WHERE cm.id = csq_r.campaign_id AND csq_r.status <> " . $payload["deleted"] . " AND csq_r.open_count > 0 AND csq_r.is_bounce <> 1 AND csq_r.replied <> 0) AS total_reply",
            "(SELECT COUNT(csq_c.id) FROM campaign_sequences csq_c WHERE cm.id = csq_c.campaign_id AND csq_c.status <> " . $payload["deleted"] . " AND csq_c.click_count > 0 AND csq_c.is_bounce <> 1) AS total_clicks",
            "(SELECT COUNT(csq_b.id) FROM campaign_sequences csq_b WHERE cm.id = csq_b.campaign_id AND csq_b.status <> " . $payload["deleted"] . " AND csq_b.is_bounce = 1) AS total_bounce"
        ];

        // Fetch from tables
        $tables = "FROM campaign_master cm INNER JOIN user_master um ON cm.user_id = um.id LEFT JOIN account_sending_methods asm ON cm.account_sending_method_id = asm.id";

         // Fetching conditions and order
         if ($payload["is_owner"] == 1) {
            $condition = "WHERE cm.account_id = " . $payload["account_id"] . " AND cm.status <> " . $payload["deleted"];
        } else {
            $condition = "WHERE cm.user_id = " . $payload["user_id"] . " AND cm.status <> " . $payload["deleted"];
        }

        // Add filters
        if (isset($query_params["user"])) {
            $condition .= " AND cm.user_id = " . ((int) $query_params["user"]);
        }
        if (isset($query_params["status"]) && isset($query_params["progress"])) {
            $condition .= " AND cm.status = " . ((int) $query_params["status"]);
            $condition .= " AND cm.overall_progress = " . ((int) $query_params["progress"]);
        }

        if (isset($query_params["query"])) {
            $search_condition = [
                "um.first_name LIKE '%" . $query_params["query"] . "%'",
                "um.last_name LIKE '%" . $query_params["query"] . "%'",
                "cm.title LIKE '%" . $query_params["query"] . "%'",
                //"asm.name LIKE '%" . $query_params["query"] . "%'",
                "asm.from_email LIKE '%" . $query_params["query"] . "%'"
            ];
            $condition .= " AND (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "title":
                $order_by = " ORDER BY cm.title " . $query_params["order"];
                break;

            case "created_by":
                $order_by = " ORDER BY um.first_name " . $query_params["order"] . ", um.last_name " . $query_params["order"];
                break;

            case "total_contacts":
                $order_by = " ORDER BY total_contacts " . $query_params["order"];
                break;

            case "total_open":
                $order_by = " ORDER BY total_open " . $query_params["order"];
                break;

            case "total_reply":
                $order_by = " ORDER BY total_reply " . $query_params["order"];
                break;

            case "total_clicks":
                $order_by = " ORDER BY total_clicks " . $query_params["order"];
                break;

            case "total_bounce":
                $order_by = " ORDER BY total_bounce " . $query_params["order"];
                break;

            case "progress":
                $order_by = " ORDER BY status " . $query_params["order"] . ",overall_progress " . $query_params["order"];
                break;

            case "sending_method":
                $order_by = " ORDER BY sending_method_name " . $query_params["order"];
                break;    

            case "modified":
                $order_by = " ORDER BY cm.modified " . $query_params["order"];
                break;    

            default:
                $order_by = " ORDER BY cm.id DESC";
                break;
        }
       
        try {
            // Fetch total records
            $count_sql = "SELECT COUNT(cm.id) " . $tables . " " . $condition;
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
     * Check plan wise restriction is allowed to create campaign
     *
     * @param $account_id (int): Account id.
     * @param $allowed_campaign_limit (string): Allowed Campaign Limit
     *
     * @return (true or false)
     */
    public function isAllowedToCreateCampaign($account_id, $allowed_campaign_limit) {         
        $response = false;


        try {

            if($allowed_campaign_limit.""==ALLOWED_UNLIMITED_COUNT){
                return true;
            }
            
            $sql = "SELECT count(id) as no_of_campaign FROM campaign_master WHERE account_id = ". $account_id;
            $this->_query_string = $sql;       
  
            $stmt = $this->_db->query($sql);
            $data =  $stmt->fetch();
           
            if($data["no_of_campaign"] < $allowed_campaign_limit){
                $response = true;
            }
            $stmt = null;
        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }
        return $response;    
    }

   /**
     * Get campaigns which are ACTIVE, in QUEUE, are within DURATION
     *
     * @param $query_params (array): Query parameters
     * @param $payload (array): Payload of other required information
     * 
     * @return (array) Array of records
     */
    public function getPendingCampaigns($query_params, $payload = []) {
        $data = [];

        // Fetch fields
        $fields = [
            "cm.id",
            "cm.account_id",
            "cm.user_id",
            "cm.title",
            "cm.other_data",
            "cm.total_stages",
            "cm.track_click",
            "cm.overall_progress",
            "cst.id as campaign_stage_id",
            "cst.content",
            "cst.stage",
            "cst.stage_defination",
            "cst.scheduled_on"
        ];

        // Fetch from tables
        $tables = "FROM campaign_master cm
        INNER JOIN campaign_stages cst ON cm.id = cst.campaign_id";

        // Fetching conditions and order
        $condition = "WHERE
            cm.status = " . $payload["active"] . "
            AND cst.progress = " . $payload["campaign_scheduled"] . "            
            AND ((cm.overall_progress <> " . $payload["campaign_paused"] . " AND cm.overall_progress <> " . $payload["campaign_finished"] . " ) OR cm.overall_progress IS NULL) 
            AND cm.is_bulk_campaign = " . $query_params["bulk_campaign"] . "
            AND cst.locked = 0
            AND cst.scheduled_on <= '" . $query_params["duration_window"] . "'";

        $order_by = " ORDER BY cst.scheduled_on ASC";
        $limit = " LIMIT " . $query_params["campaigns_limit"];

        try {
            $sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition . " " . $order_by . " " . $limit;
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
     * Get campaigns which are ACTIVE, in QUEUE, are within DURATION
     *
     * @param $query_params (array): Query parameters
     * @param $payload (array): Payload of other required information
     * @param $getAllActive (bool): Get all active true when call from email account (status change to inactive/delete)
     *                              and all active false when call from Camapaign Command.
     * @return (array) Array of records
     */
    public function getCampaignsToInactive($query_params, $payload = [], $getPaused) {
        $data = [];

        // Fetch fields
        $fields = [
            "cm.id",
            "cm.account_id",
            "cm.user_id",
            "cm.overall_progress"           
        ];

        // Fetch from tables
        $tables = "FROM campaign_master cm";

        // Fetching conditions and order
        $condition = "WHERE cm.status = " . $payload["active"]
            . " AND cm.user_id  = " . $payload["user_id"] 
            . " AND cm.overall_progress <> " . $payload["campaign_finished"]             
            . " AND cm.account_sending_method_id = ". $payload["account_sending_method_id"];
            
        if(!$getPaused){
            $condition = $condition. " AND cm.overall_progress <> " . $payload["campaign_paused"];
        }

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

    /**
     * Get campaigns which are Finished
     *
     * @param $query_params (array): Query parameters
     * @param $payload (array): Payload of other required information
     *
     * @return (array) Array of records
     */
    public function getFinishedCampaigns($query_params, $payload = []) {
        $data = [];

        // Fetch fields
        $fields = [
            "cm.id",
            "cm.total_stages",
            "cst.id as campaign_stage_id",
            "cst.stage"
        ];

        // Fetch from tables
        $tables = "FROM campaign_master cm
        INNER JOIN campaign_stages cst ON cm.id = cst.campaign_id";

        // Fetching conditions and order
        $condition = "WHERE
            cm.status = " . $payload["active"] . "
            AND cst.status = " . $payload["active"] . "
            AND cst.total_contacts <= (cst.total_success + cst.total_fail + cst.total_deleted)
            AND cst.progress <> " . $payload["campaign_finished"] . "
            AND cst.progress > " . $payload["campaign_scheduled"] . "
            AND cm.is_bulk_campaign = " . $query_params["bulk_campaign"] . "";

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

    /**
     * Get campaigns which are going to start after one hour
     *
     * @param $query_params (array): Query parameters
     * @param $payload (array): Payload of other required information
     *
     * @return (array) Array of records
     */
    public function getAboutToStartCampaigns($query_params, $payload = []) {
        $data = [];

        // Fetch fields
        $fields = [
            "cm.id",
            "cm.user_id",
            "cm.account_sending_method_id",
            "cst.scheduled_on",
            "cst.stage"
        ];

        // Fetch from tables
        $tables = "FROM campaign_master cm
        INNER JOIN campaign_stages cst ON cm.id = cst.campaign_id";

        // Fetching conditions and order
        $condition = "WHERE
            cm.status = " . $payload["active"] . "
            AND cst.progress = " . $payload["campaign_scheduled"] . "
            AND cst.stage > 1
            AND (cm.overall_progress <> " . $payload["campaign_paused"] . " OR cm.overall_progress IS NULL)
            AND cst.scheduled_on <= " . $query_params["duration_window"] . "
            AND cm.track_reply = 1
            AND cm.is_bulk_campaign = " . $query_params["is_bulk_campaign"] . "
            AND cst.track_reply_max_date >= " . $query_params["current_date"] . "";

        //$order_by = " ORDER BY cst.scheduled_on ASC";
        $order_by = "";
        $limit = " LIMIT 1";

        try {
            $sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition . " " . $order_by . " " . $limit;
            $this->_query_string = $sql;
          
            $stmt = $this->_db->query($sql);

            foreach ($stmt as $row) {
                $data = $row;
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
