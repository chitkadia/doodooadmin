<?php
/**
 * Model file for operations on api_messages table
 */
namespace App\Models;

class ApiMessages extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "api_messages";
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
            "http_code" => "HTTP Status Code",
            "error_code" => "Error Code",
            "error_message" => "Error Description",
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