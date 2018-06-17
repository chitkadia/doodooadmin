<?php
/**
 * Model file for operations on email_recipients table
 */
namespace App\Models;

class EmailRecipients extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "email_recipients";
        $this->_table_alias = "er";
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
            "email_id" => "Email",
            "account_contact_id" => "Contact",
            "type" => "Type",
            "open_count" => "Open Count",
            "last_opened" => "Last Opened At",
            "replied" => "Replied?",
            "replied_at" => "Replied At",
            "click_count" => "Total Clicks",
            "last_clicked" => "Last Clicked At",
            "is_bounce" => "Bounced?",
            "progress" => "Progress",
            "status" => "Status",
            "modified" => "Modified At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "account_master" => ["account_master AS am", $this->_table_alias . ".account_id", "=", "am.id", "INNER"],
            "email_master" => ["email_master AS em", $this->_table_alias . ".email_id", "=", "em.id", "INNER"],
            "account_contacts" => ["account_contacts AS ac", $this->_table_alias . ".account_contact_id", "=", "ac.id", "INNER"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }

}