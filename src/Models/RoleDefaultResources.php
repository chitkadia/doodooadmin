<?php
/**
 * Model file for operations on role_default_resources table
 */
namespace App\Models;

class RoleDefaultResources extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "role_default_resources";
        $this->_table_alias = "rds";
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
            "role_id" => "Role",
            "resource_id" => "Resource",
            "status" => "Status",
            "modified" => "Modified At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "role_master" => ["role_master AS rm", $this->_table_alias . ".role_id", "=", "rm.id", "INNER"],
            "resource_master" => ["resource_master AS rmm", $this->_table_alias . ".resource_id", "=", "rmm.id", "INNER"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }

}