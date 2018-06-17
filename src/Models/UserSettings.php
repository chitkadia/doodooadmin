<?php
/**
 * Model file for operations on user_settings table
 */
namespace App\Models;

class UserSettings extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "user_settings";
        $this->_table_alias = "us";
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
            "user_id" => "User",
            "app_constant_var_id" => "Constant",
            "value" => "Value",
            "modified" => "Modified At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "user_master" => ["user_master AS um", $this->_table_alias . ".user_id", "=", "um.id", "INNER"],
            "app_constant_vars" => ["app_constant_vars AS acv", $this->_table_alias . ".app_constant_var_id", "=", "acv.id", "INNER"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }

}