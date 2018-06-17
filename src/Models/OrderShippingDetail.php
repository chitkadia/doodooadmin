<?php
/**
 * Model file for operations on account_billing_master table
 */
namespace App\Models;

class OrderShippingDetail extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "order_shipping_detail";
        $this->_table_alias = "osd";
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
            "user_id" => "User ID",
            "address" => "Address",
            "land_mark" => "Near By Land Mark",
            "city" => "City",
            "state" => "State",
            "country" => "Country",
            "zip_code" => "Zip Code",
            "status" => "Status",
            "created_date" => "Created Date",
            "modified_date" => "Modified Date"
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