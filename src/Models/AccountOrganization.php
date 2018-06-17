<?php
/**
 * Model file for operations on account_organization table
 */
namespace App\Models;

class AccountOrganization extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "account_organization";
        $this->_table_alias = "ao";
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
            "name" => "Name",
            "address" => "Address",
            "city" => "City",
            "state" => "State",
            "country" => "Country",
            "zipcode" => "Zipcode",
            "logo" => "Logo",
            "website" => "Website",
            "contact_phone" => "Phone",
            "contact_fax" => "Fax",
            "short_info" => "Short Description",
            "modified" => "Modified At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "account_master" => ["account_master AS am", $this->_table_alias . ".account_id", "=", "am.id", "INNER"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }

}