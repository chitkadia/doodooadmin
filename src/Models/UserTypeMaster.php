<?php
/**
 * Model file for operations on user_type_master table
 */
namespace App\Models;

class UserTypeMaster extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "user_type_master";
        $this->_table_alias = "utm";
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