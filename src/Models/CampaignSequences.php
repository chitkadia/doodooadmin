<?php
/**
 * Model file for operations on campaign_sequences table
 */
namespace App\Models;

class CampaignSequences extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "campaign_sequences";
        $this->_table_alias = "csq";
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
            "campaign_id" => "Campaign",
            "campaign_stage_id" => "Campaign Stage",
            "account_contact_id" => "Contact",
            "csv_payload" => "CSV Data",
            "progress" => "Progress",
            "is_bounce" => "Bounced?",
            "scheduled_at" => "Scheduled At",
            "sent_at" => "Sent At",
            "message_send_id" => "Message Id",
            "sent_response" => "Sent Response",
            "locked" => "Locked?",
            "locked_date" => "Locked At",
            "open_count" => "Total Opens",
            "last_opened" => "Last Opened At",
            "replied" => "Replied?",
            "last_replied" => "Last Replied At",
            "reply_check_count" => "Reply Check Count",
            "reply_last_checked" => "Last Reply Checked At",
            "click_count" => "Total Clicks",
            "last_clicked" => "Last Clicked At",
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
            "campaign_master" => ["campaign_master AS cm", $this->_table_alias . ".campaign_id", "=", "cm.id", "INNER"],
            "campaign_stages" => ["campaign_stages AS cst", $this->_table_alias . ".campaign_stage_id", "=", "cst.id", "INNER"],
            "account_contacts" => ["account_contacts AS ac", $this->_table_alias . ".account_contact_id", "=", "ac.id", "INNER"]
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
            $tables = "FROM campaign_sequences csq"
            ." INNER JOIN campaign_master cm ON cm.id = csq.campaign_id"
            ." INNER JOIN user_master um ON cm.user_id = um.id";

            // Fetching conditions and order
            $condition = "WHERE csq.id = " . $id . " AND cm.account_id = " . $payload["account_id"] 
                        . " AND csq.status <> " . $payload["deleted"]
                        . " AND cm.status <> " . $payload["deleted"];

            // Fetch total records
            $valid_row_sql = "SELECT csq.id " . $tables . " " . $condition;
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
     * Add delay in campaign sequences when quota exceeded
     *
     * @param $campaign_stage_id (int): 
     * @param $progress (int): 
     *
     */
    public function addDelayOnQuotaExceed($campaign_stage_id, $progress) {
        $data = [];

        try {
            $sql = "UPDATE campaign_sequences SET locked = 0, scheduled_at = (scheduled_at + (60*60)) WHERE campaign_stage_id = ".$campaign_stage_id." AND progress = ".$progress;

            $this->_query_string = $sql;

            $stmt = $this->_db->query($sql);

            $stmt = null;
        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

    }

    /**
     * Check if email is already added to queue
     *
     * @param $users (string): 
     * @param $email (string): 
     *
     */
    public function checkEmailExists($users, $email) {
        $data = [];

        try {
            $sql = "
                SELECT csq.id 
                FROM campaign_sequences csq 
                JOIN account_contacts aco ON csq.account_contact_id = aco.id
                WHERE 
                    EXISTS (SELECT cst.id FROM campaign_stages cst WHERE csq.campaign_stage_id = cst.id AND cst.user_id IN (" . $users . ") ) 
                    AND aco.email = '" . $email . "' ";

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

    /**
     * Check if email is already added to queue
     *
     * @param $users (string): 
     * @param $email (string): 
     *
     */
    public function getRecordsToCheckReply($req_campaign_id, $query_params) {
        $data       = [];
        $condition  = "";

        try {
            if (!empty($query_params["modulo"]) && !is_null($query_params["reminder"])) {
                $condition .= " AND csq.id % " . $query_params["modulo"] . " = " . $query_params["reminder"] . " ";
            }
            if (!empty($req_campaign_id)) {
                $condition .= " AND cst.id = " . $req_campaign_id . " ";
            }else{
                $condition .= " AND cm.is_bulk_campaign = '" . $query_params["is_bulk_campaign"]. "' ";
            }
                    
            $sql = "
                SELECT 
                    csq.id,
                    csq.campaign_id,
                    csq.campaign_stage_id,
                    csq.account_contact_id,
                    csq.message_send_id,
                    csq.sent_response,
                    csq.open_count,
                    csq.reply_check_count,
                    cm.user_id,
                    cm.account_sending_method_id
                FROM campaign_sequences csq
                JOIN campaign_stages cst ON cst.id = csq.campaign_stage_id
                JOIN campaign_master cm ON cm.id = csq.campaign_id
                WHERE cm.status = '" . $query_params["status_active"] . "'
                    AND cm.track_reply = '" . $query_params["flag_yes"] . "'
                    AND (cst.track_reply_max_date >= '" . $query_params["current_date"] . "'
                        OR cst.track_reply_max_date IS NULL)
                    AND csq.is_bounce = '0'
                    AND csq.replied = '0'
                    AND csq.progress = '" . $query_params["seq_progress_sent"] . "'
                    " . $condition . "
                ORDER BY csq.reply_last_checked
                LIMIT 200
            ";

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