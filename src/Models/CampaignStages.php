<?php
/**
 * Model file for operations on campaign_stages table
 */
namespace App\Models;
use \App\Components\DateTimeComponent;
use \PDO as PDO;

class CampaignStages extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "campaign_stages";
        $this->_table_alias = "cst";
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
            "campaign_id" => "Campaign",
            "subject" => "Subject",
            "content" => "Content",
            "account_template_id" => "Template",
            "stage" => "Stage Number",
            "stage_defination" => "Stage Modifications",
            "scheduled_on" => "Scheduled At",
            "track_reply_max_date" => "Track Reply Max Date",
            "locked" => "Locked",
            "progress" => "Progress",
            "total_contacts" => "Total Contacts",
            "total_success" => "Total Sent",
            "total_fail" => "Total Failed",
            "total_deleted" => "Total Deleted",
            "started_on" => "Started At",
            "finished_on" => "Finished At",
            "report_sent" => "Report Sent?",            
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
            "campaign_master" => ["campaign_master AS cm", $this->_table_alias . ".campaign_id", "=", "cm.id", "INNER"],
            "account_templates" => ["account_templates AS at", $this->_table_alias . ".account_template_id", "=", "at.id", "INNER"]
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
            $tables = "FROM campaign_stages cst"
            ." INNER JOIN user_master um ON cst.user_id = um.id"
            ." INNER JOIN campaign_master cm ON cm.id = cst.campaign_id";

            // Fetching conditions and order
            $condition = "WHERE cst.id = " . $id . " AND cst.account_id = " . $payload["account_id"] 
                        . " AND cst.status <> " . $payload["deleted"]
                        . " AND cm.status <> " . $payload["deleted"];

            // Fetch total records
            $valid_row_sql = "SELECT cst.id " . $tables . " " . $condition;
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
            "cst.id",
            "cst.campaign_id",
            "cst.stage",
            "cm.total_stages"
        ];

        // Fetch from tables
        $tables = "FROM campaign_stages cst
        INNER JOIN campaign_master cm ON cm.id = cst.campaign_id";

        // Fetching conditions and order
        $condition = "WHERE
            cst.total_contacts <= (cst.total_success + cst.total_fail + cst.total_deleted) 
            AND cm.is_bulk_campaign = " . $query_params["bulk_campaign"] . "
            AND cm.status = " . $payload["active"] . "
            AND cst.status = " . $payload["active"] . "
            AND cst.progress > " . $payload["progress"] . "
            AND cst.id IN (" . $payload["campaign_finished"] . ") ";

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
     * Get success and fail counts by campaign stage id
     *
     * @param $campaign_stage_id (int): ID of campaign stage
     * @param $payload (array): Payload of other required information
     *
     * @return (array) Array of single record
     */
    public function getSuccessCountById($campaign_stage_id=0, $payload = []) {
        $data = [];

        try {
            $sql = "SELECT
                        (SELECT COUNT(csq1.id) 
                            FROM campaign_sequences csq1 
                            WHERE csq1.campaign_stage_id = cst.id AND csq1.progress = " . $payload["sequence_progress_sent"] . "
                        ) AS cnt_success,
                        (SELECT COUNT(csq2.id) 
                            FROM campaign_sequences csq2 
                            WHERE csq2.campaign_stage_id = cst.id AND csq2.progress = " . $payload["sequence_progress_fail"] . "
                        ) AS cnt_fail
                        FROM campaign_stages cst 
                        WHERE cst.id = " . $campaign_stage_id;
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
     * Function is used to update total_success,total_fail and total_delete value.
     */
    public function updateStageData($campaign_stage_id, $field_name, $value = 1) {
        $result = false;

        if (empty($campaign_stage_id) ) {
            return $result;
        } else if (empty($field_name)) {
            return $result;    
        }

        $operation_val = (int) $value;
  
        try {

            if ($field_name == 'total_success') {

                $sql = "UPDATE " . $this->_table . " SET total_success = total_success + ?, modified = ? WHERE " . $this->_pk_column . " = ?";
                $this->_query_string = $sql;
                $stmt = $this->_db->prepare($sql);

                $stmt->bindValue(1, $operation_val, PDO::PARAM_INT);
                $stmt->bindValue(2, DateTimeComponent::getDateTime(), PDO::PARAM_STR);
                $stmt->bindValue(3, $campaign_stage_id, PDO::PARAM_INT);

            } else if ($field_name == 'total_fail') {

                $sql = "UPDATE " . $this->_table . " SET total_fail = total_fail - ?, modified = ? WHERE " . $this->_pk_column . " = ?";
                $this->_query_string = $sql;
                $stmt = $this->_db->prepare($sql);

                $stmt->bindValue(1, $operation_val, PDO::PARAM_INT);
                $stmt->bindValue(2, DateTimeComponent::getDateTime(), PDO::PARAM_STR);
                $stmt->bindValue(3, $campaign_stage_id, PDO::PARAM_INT);

            }
          
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

}