<?php
/**
 * Model file for operations on campaign_logs table
 */
namespace App\Models;

class CampaignLogs extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "campaign_logs";
        $this->_table_alias = "clo";
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
            "campaign_sequence_id" => "Campaign Sequence",
            "log" => "Info",
            "log_type" => "Type",
            "created" => "Created At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "campaign_master" => ["campaign_master AS cm", $this->_table_alias . ".campaign_id", "=", "cm.id", "INNER"],
            "campaign_stages" => ["campaign_stages AS cs", $this->_table_alias . ".campaign_stage_id", "=", "cs.id", "INNER"],
            "campaign_sequences" => ["campaign_sequences AS csq", $this->_table_alias . ".campaign_sequence_id", "=", "csq.id", "INNER"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }

}