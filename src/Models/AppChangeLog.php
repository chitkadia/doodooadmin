<?php

/**
 * Model file for operations on plan_master table
 */

namespace App\Models;

class AppChangeLog extends AppModel {

     // Initialize model
     use ModelInitialize;

     /**
      * @override
      */
     protected function _setTable() {
         $this->_table = "app_change_log";
         $this->_table_alias = "acl";
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
            "release_name" => "Version",
            "package_path" => "Package Path",
            "release_note" => "Release Note",
            "source" => "Source", 
            "is_critical" => "Is Critical",
            "is_logout_required" => "Is Logout Required",                       
            "status" => "Status"
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
     * @return (boolean) Row valid or not
     */
    public function checkRowValidity($id, $payload = []) {
        $valid = false;
     
        if (empty($id)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM ".$this->_table." ".$this->_table_alias;

            // Fetching conditions and order
            $condition = "WHERE acl.id = " . $id ." AND acl.status <> 2";

            // Fetch records
            $valid_row_sql = "SELECT acl.id " . $tables . " " . $condition;
            $this->_query_string = $valid_row_sql;
           
            $id = $this->_db->query($valid_row_sql)->fetchColumn();
            
            if (!empty($id)) {
                $valid = true;
            }

        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }
        return $valid;
    }

    /**
     * Check row validity (has user access to this row or not)
     *
     * @param $releaseName (String): release name of the record
     * @param $payload (array): Payload of other required information
     * @return (boolean) Row valid or not
     */
    public function getPackageIdFromVersion($releaseName , $payload = []) {
        $package_id = 0;
     
        if (empty($releaseName)) {
            return $package_id;
        }

        try {
            // Fetch from tables
            $tables = "FROM ".$this->_table." ".$this->_table_alias;

            // Fetching conditions and order
            $condition = "WHERE acl.release_name = '" . $releaseName .
                        "' AND acl.status <> 2 AND acl.source = 3";

            // Fetch total records
            $sql = "SELECT acl.id " . $tables . " " . $condition;     
            $this->_query_string = $sql;
           
            $id = $this->_db->query($sql)->fetchColumn();
            
            if (!empty($id)) {
                $package_id = $id;
            }

        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }
        return $package_id;
    }

      /**
     * Check row validity (has user access to this row or not)
     *
     * @param $id (integer): Id of the record
     * @param $payload (array): Payload of other required information
     * @return (boolean) Row valid or not
     */
    public function getPackageUpdateDetails($id, $payload = []) {      
        $data =[];
        try {          
            $fields = [
                    "id",
                    "release_name",
                    "package_path",
                    "(select is_critical from app_change_log where id > ".$id." AND is_critical = 1 AND status = ". $payload["status"]." AND source = ". $payload["source"]." LIMIT 1) as critical",
                    "(select is_logout_required from app_change_log where id > ".$id." AND is_logout_required = 1 AND status = ". $payload["status"]." AND source = ". $payload["source"]." LIMIT 1) as logout_required",
                    "status"
            ];
      
            // Fetch from tables
            $tables = "FROM ".$this->_table." ".$this->_table_alias;

            // Fetching conditions and order
            $condition = "WHERE acl.id > " . $id ." AND acl.status = ". $payload["status"]
                        . " AND acl.source = ". $payload["source"] . " ORDER BY id DESC";

            // Fetch total records
            $sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition . " LIMIT 1";
           
            $this->_query_string = $sql;
         
            $data = $this->_db->query($sql)->fetch();            
            
        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;
            throw new \Exception($this->_failed_query_message);
        }
        return $data;
    }


    
    
}