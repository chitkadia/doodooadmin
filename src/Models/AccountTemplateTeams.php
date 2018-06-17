<?php
/**
 * Model file for operations on account_template_teams table
 */
namespace App\Models;

class AccountTemplateTeams extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "account_template_teams";
        $this->_table_alias = "att";
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
            "account_template_id" => "Template",
            "account_team_id" => "Team",
            "status" => "Status",
            "modified" => "Modified At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "account_templates" => ["account_templates AS at", $this->_table_alias . ".account_template_id", "=", "at.id", "INNER"],
            "account_teams" => ["account_teams AS at", $this->_table_alias . ".account_team_id", "=", "at.id", "INNER"]
        ];
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
    public function checkTemplateTeamAvailable($id, $payload = []) {
        $valid = false;

        if (empty($id) || empty($payload)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM " . $this->_table . " " . $this->_table_alias;

            // Fetching conditions and order
            $condition = "WHERE " . $this->_table_alias . ".account_team_id = " . $id . " AND " . $this->_table_alias . ".status <> " . $payload["deleted"];

            // Fetch total records
            $valid_row_sql = "SELECT " . $this->_table_alias . ".id " . $tables . " " . $condition;
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