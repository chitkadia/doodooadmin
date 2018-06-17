<?php
/**
 * Model file for operations on user_invitations table
 */
namespace App\Models;

class UserInvitations extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "user_invitations";
        $this->_table_alias = "ui";
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
            "user_id" => "User",
            "invited_by" => "Invited By",
            "invited_at" => "Invited At",
            "joined_at" => "Joined At",
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
            "user_master" => ["user_master AS um", $this->_table_alias . ".user_id", "=", "um.id", "INNER"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }

}