<?php
/**
 * Model file for operations on action_master table
 */
namespace App\Models;

class ActionMaster extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "action_master";
        $this->_table_alias = "am";
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
            "code" => "Code",
            "name" => "Name",
            "parent_id" => "Action Group Id",
            "notify_template" => "Notification Template",
            "sort_order" => "Sort Order",
            "for_feed" => "Include in Feed?",
            "status" => "Status",
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