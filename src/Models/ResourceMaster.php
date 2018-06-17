<?php
/**
 * Model file for operations on resource_master table
 */
namespace App\Models;

class ResourceMaster extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "resource_master";
        $this->_table_alias = "rm";
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
            "resource_name" => "Name",
            "short_info" => "Short Description",
            "api_endpoint" => "API Endpoints",
            "parent_id" => "Parent Row",
            "position" => "Sort Order",
            "show_in_roles" => "Show In Roles",
            "show_in_webhooks" => "Show In Webhooks",
            "is_always_assigned" => "Default Resource",
            "is_secured" => "Secured?",
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