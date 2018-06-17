<?php

/**
 * Model file for operations on plan_master table
 */

namespace App\Models;

class PlanMaster extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "plan_details";
        $this->_table_alias = "pmd";
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
            "plan_name" => "Plan Name",
            "plan_interval" => "Plan Interval",
            "plan_price" => "Plan Price",
            "plan_deposite" => "Plan Deposite",
            "configuration" => "Custom Configuration",
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

    /**
     * Check row validity (has user access to this row or not)
     *
     * @param $id (integer): Id of the record
     * @param $payload (array): Payload of other required information
     *
     * @return (boolean) Row valid or not
     */
    public function checkRowValidity($payload = []) {
        $valid = false;

        if (empty($payload)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM plan_master";

            // Fetching conditions and order
            $condition = "WHERE status <> " . $payload["inactive"];

            // Fetch total records
            $valid_row_sql = "SELECT id " . $tables . " " . $condition;
            $this->_query_string = $valid_row_sql;

            $id = $this->_db->query($valid_row_sql)->fetchColumn();

            if (!empty($id)) {
                $valid = true;
            }
        } catch (\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $valid;
    }

    /**
     * @param type $name Description
     */
    public function getPlanDetails($payload = []) {
        $valid = false;
        $data = [];

        if (empty($payload)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM plan_master";

            // Fetching conditions and order
            $condition = "WHERE status <> " . $payload["inactive"] . " AND id = '" . $payload["plan_id"] . "'";

            // Fetch total records
            $valid_row_sql = "SELECT code, amount, mode, configuration " . $tables . " " . $condition;
            $this->_query_string = $valid_row_sql;

            $stmt = $this->_db->query($valid_row_sql);
            foreach ($stmt as $row) {
                $data = $row;
            }
        } catch (\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;
            throw new \Exception($this->_failed_query_message);
        }

        if (count($data) > 0) {
            return $data;
        } else {
            return false;
        }
    }

    /**
     * Get all plan details
     *
     * @param $payload (array): Payload of other required information
     *
     * @return Array | false (boolean)
     */
    public function getAllPlanData($payload = []) {
        $valid = false;
        $data = [];

        if (empty($payload)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM plan_master";

            // Fetching conditions and order
            $condition = "WHERE status = " . $payload["active"];

            // Fetch total records
            $valid_row_sql = "SELECT id, code, mode, name " . $tables . " " . $condition . " ORDER BY " . $payload["order_by"] . " " . $payload["order"];
            $this->_query_string = $valid_row_sql;

            $stmt = $this->_db->query($valid_row_sql);
            foreach ($stmt as $row) {
                $data["rows"][] = $row;
            }
        } catch (\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        if (count($data) > 0) {
            return $data;
        } else {
            return false;
        }
    }
    
    /**
     * Get plan details
     *
     * @param id (int) : plan id from app vars of trial plan
     *
     * @return data (array) data of trial plan (validity days and plan configuration) 
     */
    public function getPlanData($plan_id) {

        $data = [];
        $plan_id = $plan_id;


        try {
            // Fetch from tables
            $tables = "FROM plan_master";

            // Fetching conditions and order
            $condition = "WHERE id = ".$plan_id;

            // Fetch total records
            $valid_row_sql = "SELECT name, validity_in_days, configuration " . $tables . " " . $condition;
            $this->_query_string = $valid_row_sql;

            $stmt = $this->_db->query($valid_row_sql);
            $data = $stmt->fetch();

        } catch (\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $data;
    }

}
