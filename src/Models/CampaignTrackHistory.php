<?php
/**
 * Model file for operations on campaign_track_history table
 */
namespace App\Models;

class CampaignTrackHistory extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "campaign_track_history";
        $this->_table_alias = "cth";
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
            "campaign_sequence_id" => "Campaign Sequence",
            "type" => "Type",
            "acted_at" => "Acted At",
            "account_link_id" => "Link"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "campaign_sequences" => ["campaign_sequences AS csq", $this->_table_alias . ".campaign_sequence_id", "=", "csq.id", "INNER"],
            "account_link_master" => ["account_link_master AS alm", $this->_table_alias . ".account_link_id", "=", "alm.id", "INNER"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }

}