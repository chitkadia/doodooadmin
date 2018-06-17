<?php
/**
 * Custom model
 */
namespace App\Models;

class AppVarsModel extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = null;
        $this->_table_alias = null;
    }

    /**
     * @override
     */
    protected function _setPkColumn() {
        $this->_pk_column = null;
    }

    /**
     * @override
     */
    protected function _setFields() {
        $this->_fields = [];
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

    /**
     * Set table name dynamically to bind model to any table
     *
     * @param $tablename (string): Name of the table
     * @param $alias (string): Alias of the table
     */
    public function setTableName($tablename, $alias = "t") {
        $this->_table = $tablename;
        $this->_table_alias = $alias;
    }

}