<?php
/**
 * Model file for operations on email_track_history table
 */
namespace App\Models;

class EmailTrackHistory extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "email_track_history";
        $this->_table_alias = "eth";
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
            "email_id" => "Email Id",
            "email_recipient_id" => "Recipient",
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
            "email_master" => ["email_master AS em", $this->_table_alias . ".email_id", "=", "em.id", "INNER"],
            "email_recipients" => ["email_recipients AS er", $this->_table_alias . ".email_recipient_id", "=", "er.id", "INNER"],
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