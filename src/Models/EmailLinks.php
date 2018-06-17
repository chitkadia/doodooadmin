<?php
/**
 * Model file for operations on email_links table
 */
namespace App\Models;

class EmailLinks extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "email_links";
        $this->_table_alias = "el";
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
            "account_link_id" => "Link",
            "redirect_key" => "Redirect Key",
            "total_clicked" => "Total Clicked",
            "last_clicked" => "Last Clicked At",
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
            "account_contacts" => ["account_contacts AS ac", $this->_table_alias . ".account_contact_id", "=", "ac.id", "INNER"],
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