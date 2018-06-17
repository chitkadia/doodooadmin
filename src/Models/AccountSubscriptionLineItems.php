<?php
/**
 * Model file for operations on account_subscription_details table
 */
namespace App\Models;

class AccountSubscriptionLineItems extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "account_subscription_line_items";
        $this->_table_alias = "asli";
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
            "user_account_plan_id" => "User Account Plan Id",
            "user_account_team_size" => "User Account Team Size",
            "email_account_plan_id" => "Email Account Plan Id",
			"email_account_seat" => "Email Account Seat",
            "current_subscription_id" => "Current Subscription Id",
            "total_amount" => "Total Amount",
            "created" => "Created At",
            "modified" => "Modified At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }

}