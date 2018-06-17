<?php
/**
 * Model file for operations on document_teams table
 */
namespace App\Models;

class DocumentLinkFiles extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "document_link_files";
        $this->_table_alias = "dlf";
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
            "document_link_id" => "Links",
            "document_id" => "Document",
            "account_folder_id" => "Folder",
            "status" => "Status",
            "modified" => "Modified At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "document_master" => ["document_master AS dm", $this->_table_alias . ".document_id", "=", "dm.id", "INNER"],
            "document_links" => ["document_links AS dl", $this->_table_alias . ".document_link_id", "=", "dl.id", "INNER"],
            "account_folders" => ["account_folders AS af", $this->_table_alias . ".account_folder_id", "=", "af.id", "INNER"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }

    /**
     * Check row validity (is data available or not)
     *
     * @param $id (integer): Id of the record
     * @param $payload (array): Payload of other required information
     *
     * @return (boolean) Row valid or not
     */
    public function checkRowValidity($id, $payload = []) {
        $valid = false;

        if (empty($id) || empty($payload)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM " . $this->_table;

            // Fetching conditions and order
            $condition = "WHERE document_id = " . $id . " AND document_link_id = " . $payload["link_id"] . " AND status <> " . $payload["deleted"];

            // Fetch total records
            $valid_row_sql = "SELECT id " . $tables . " " . $condition;
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

}