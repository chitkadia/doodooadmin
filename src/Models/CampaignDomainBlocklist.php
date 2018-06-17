<?php
/**
 * Model file for operations on webhooks table
 */
namespace App\Models;

class CampaignDomainBlocklist extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "campaign_domain_blocklist";
        $this->_table_alias = "cdb";
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
            "user_id" => "User",
            "domain" => "Domain",
            "status" => "Status",
            "modified" => "Modified At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "user_master" => ["user_master AS um", $this->_table_alias . ".user_id", "=", "um.id", "INNER"],
            "account_master" => ["account_master AS am", $this->_table_alias . ".account_id", "=", "am.id", "INNER"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }

    /**
     * Get listing of records
     *
     * @param $query_params (array): Query parameters
     * @param $payload (array): Payload of other required information
     *
     * @return (array) Array of records
     */
    public function getListData($query_params, $payload = []) {
        $data = [];

        // Fetch fields
        $fields = [
            "cdb.id",
            "cdb.account_id",
            "cdb.user_id",
            "cdb.domain",
            "cdb.status",
            "cdb.modified"
        ];

        // Fetch from tables
        $tables = "FROM campaign_domain_blocklist cdb";

        // Fetching conditions and order
        $condition = "WHERE cdb.account_id = " . $payload["account_id"] . " AND cdb.status <> " . $payload["deleted"];

        // Add filters
        if (isset($query_params["user"])) {
            $condition .= " AND cdb.user_id = " . ((int) $query_params["user"]);
        }
        /*if (isset($query_params["status"])) {
            $condition .= " AND cdb.status = " . ((int) $query_params["status"]);
        }*/
        if (isset($query_params["query"])) {
            $condition .= " AND (cdb.domain LIKE '%" . $query_params["query"] . "%')";
        }
        

        // Add Order by
        switch ($query_params["order_by"]) {
            case "domain":
                $condition .= " ORDER BY cdb.domain " . $query_params["order"];
                break;
            case "modified_at":
                $condition .= " ORDER BY cdb.modified " . $query_params["order"];
            break;

            default: 
                $condition .= " ORDER BY cdb.id DESC";
                break;
        }

        try {
            // Fetch total records
            $count_sql = "SELECT COUNT(cdb.id) " . $tables . " " . $condition;
            $this->_query_string = $count_sql;
            /*var_dump($this->_query_string);
            exit;*/

            $number_of_rows = $this->_db->query($count_sql)->fetchColumn();
            $total_pages = ceil($number_of_rows / $query_params["per_page"]);

            // If page doesn't exists, then get first page data
            if ($query_params["page"] > $total_pages) {
                $query_params["page"] = 1;
            }
            $offset = ($query_params["page"] - 1) * $query_params["per_page"];

            // Add limit
            $limit = "LIMIT " . $offset . ", " . $query_params["per_page"];

            $sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition . " " . $limit;
            $this->_query_string = $sql;
            /*echo $sql;
            exit;*/

            $stmt = $this->_db->query($sql);
            foreach ($stmt as $row) {
                $data["rows"][] = $row;
            }

            $data["total_records"] = $number_of_rows;
            $data["total_pages"] = $total_pages;
            $data["current_page"] = $query_params["page"];
            $data["per_page"] = $query_params["per_page"];

            $stmt = null;

        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $data;
    }

}