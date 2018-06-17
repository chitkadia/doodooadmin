<?php
/**
 * Model file for operations on document_visits table
 */
namespace App\Models;

class DocumentLinkVisits extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "document_link_visits";
        $this->_table_alias = "dv";
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
            "document_link_id" => "Document Link Id",
            "account_contact_id" => "Account Contact Id",
            "location" => "Location",
            "download" => "DOwnload?",
            "viewed_mail_sent" => "Link Visited email sent?",
            "modified" => "Modified At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "document_links" => ["document_links AS dl", $this->_table_alias . ".document_link_id", "=", "dl.id", "INNER"],
            "user_master" => ["user_master AS um", "dl.user_id", "=", "um.id", "INNER"],
            "account_contacts" => ["account_contacts AS ac", $this->_table_alias . ".account_contact_id", "=", "ac.id", "LEFT"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }

    /**
     * Check doc space availability (is requested doc space available or not)
     *
     * @param $id (integer): Id of the record
     * @param $payload (array): Payload of other required information
     *
     * @return (boolean) Row valid or not
     */
    public function checkSpaceAvailability($id, $payload = []) {
        $valid = false;

        if (empty($id) || empty($payload)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM document_links dl";

            // Fetching conditions and order
            $condition = "WHERE dl.account_id = " . $payload["account_id"] . " AND dl.user_id = " . $payload["user_id"] . " AND dl.status <> " . $payload["deleted"] . " AND dl.id = " . $payload["space_id"];

            // Fetch total records
            $valid_row_sql = "SELECT dl.id " . $tables . " " . $condition;
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
     * Get details of particular document space
     *
     * @param $id (integer): Id of the record
     * @param $payload (array): Payload of other required information
     *
     * @return (boolean) Row valid or not
     */
    public function getOverallPerformance($id, $space_id) {
        $data = [];

        if (empty($id)) {
            return $data;
        }

        try {
            // Fetch from tables
            $tables = "FROM document_link_visits dlv 
                       INNER JOIN document_links dl ON dlv.document_link_id = dl.id 
                       LEFT JOIN account_contacts ac ON dlv.account_contact_id = ac.id";

            // Fetching conditions and order
            $condition = "WHERE dlv.id = " . $id . " AND dlv.document_link_id = " . $space_id;

            // Fetch total records
            $valid_row_sql = "SELECT 
                                dl.name, 
                                dlv.modified, dlv.account_contact_id, 
                                ac.first_name AS contact_first_name, ac.email AS contact_email, ac.status AS contact_status, 
                                (SELECT SUM(dm.file_pages) FROM document_master dm WHERE EXISTS (SELECT dlf.document_id FROM document_link_files dlf WHERE dlf.document_link_id = dlv.document_link_id AND dlf.document_id = dm.id)) AS total_space_pages, 
                                (SELECT SUM(dlvl.time_spent) FROM document_link_visit_logs dlvl WHERE dlvl.visit_id = " . $id . ") AS total_time_spent, 
                                (SELECT COUNT(DISTINCT dlvl.page_num) FROM document_link_visit_logs dlvl WHERE dlvl.visit_id = " . $id . ") AS total_page_viewed, 
                                (SELECT SUM(dlvl.download) FROM document_link_visit_logs dlvl WHERE dlvl.visit_id = " . $id . ") AS total_download 
                                " . $tables . " " . $condition;
            $this->_query_string = $valid_row_sql;
            
            $stmt = $this->_db->query($valid_row_sql);
            foreach ($stmt as $row) {
                $data = $row;
            }

            $stmt = null;

        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $data;
    }

    public function getDocumentwisePerformance($payload) {
        $data = [];

        if (empty($payload)) {
            return $data;
        }

        try {
            // Fetch from tables
            $tables = "FROM document_link_files dlf 
                       INNER JOIN document_master dm ON dlf.document_id = dm.id 
                       INNER JOIN document_link_visits dlv ON dlf.document_link_id = dlv.document_link_id 
                       LEFT JOIN account_contacts ac ON dlv.account_contact_id = ac.id";

            // Fetching conditions and order
            $condition = "WHERE dlf.document_link_id = " . $payload["document_link_id"] . " AND dlv.id = " . $payload["visit_id"] . " AND dlf.status <> " . $payload["deleted"];

            // Fetch total records
            $valid_row_sql = "SELECT 
                                dm.file_name, dm.file_pages, 
                                (SELECT SUM(dlvl.time_spent) FROM document_link_visit_logs dlvl WHERE EXISTS (SELECT dlv.id FROM document_link_visits dlv WHERE dlv.id = dlvl.visit_id AND dlvl.document_id = dm.id AND dlv.document_link_id = " . $payload["document_link_id"] . " AND dlv.id = " . $payload["visit_id"] . ")) AS total_time_spent, 
                                (SELECT COUNT(DISTINCT dlvl.page_num) FROM document_link_visit_logs dlvl WHERE EXISTS (SELECT dlv.id FROM document_link_visits dlv WHERE dlv.id = dlvl.visit_id AND dlvl.document_id = dm.id AND dlv.document_link_id = " . $payload["document_link_id"] . " AND dlv.id = " . $payload["visit_id"] . ")) AS total_viewed_page, 
                                (SELECT COUNT(DISTINCT dlvl.visit_id) FROM document_link_visit_logs dlvl WHERE EXISTS (SELECT dlv.id FROM document_link_visits dlv WHERE dlv.id = dlvl.visit_id AND dlvl.document_id = dm.id AND dlv.document_link_id = " . $payload["document_link_id"] . " AND dlv.id = " . $payload["visit_id"] . ")) AS total_visits, 
                                dlv.modified, 
                                ac.first_name AS contact_first_name, ac.email AS contact_email, ac.status AS contact_status 
                                " . $tables . " " . $condition;
            $this->_query_string = $valid_row_sql;

            $stmt = $this->_db->query($valid_row_sql);
            foreach ($stmt as $row) {
                $data[] = $row;
            }

            $stmt = null;

        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $data;
    }

    public function getSpaceAllVisitDetails($query_params, $payload = []) {
        $data = [];
        $data["rows"] = [];

        // Fetch fields
        $fields = [
            "ac.first_name AS contact_first_name",
            "ac.email AS contact_email",
            "ac.status AS contact_status",
            "dl.name",
            "(SELECT SUM(dm.file_pages) FROM document_master dm WHERE EXISTS (SELECT dlvl.document_id FROM document_link_visit_logs dlvl WHERE dlvl.visit_id = dlv.id AND dlv.document_link_id = " . $payload["document_link_id"] . " AND dm.id = dlvl.document_id)) AS file_pages",
            "(SELECT SUM(dlvl.time_spent) FROM document_link_visit_logs dlvl WHERE dlvl.visit_id = dlv.id) AS total_time_spent",
            "(SELECT COUNT(DISTINCT dlvl.page_num) FROM document_link_visit_logs dlvl WHERE dlvl.visit_id = dlv.id) AS total_viewed_page",
            "(SELECT SUM(dlvl.download) FROM document_link_visit_logs dlvl WHERE dlvl.visit_id = dlv.id) AS total_download",
            "dlv.location",
            "dlv.modified",
            "dlv.id"
        ];

        // Fetch from tables
        $tables = "FROM document_link_visits dlv 
        INNER JOIN document_links dl ON dlv.document_link_id = dl.id 
        LEFT JOIN account_contacts ac ON dlv.account_contact_id = ac.id AND dlv.account_contact_id = " . $payload["account_contact_id"];

        // Fetching conditions and order
        $condition = "WHERE dlv.document_link_id = " . $payload["document_link_id"] . " AND dl.status <> " . $payload["deleted"] . " AND dlv.account_contact_id = " . $payload["account_contact_id"] . " AND dlv.id <> " . $payload["visit_id"];

        if (isset($query_params["query"])) {
            $search_condition = [
                "dl.name LIKE '%" . addslashes($query_params["query"]) . "%'"
            ];

            $condition .= " AND (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "name":
                $order_by = " ORDER BY dl.name " . $query_params["order"];
                break;

            case "status":
                $order_by = " ORDER BY dl.status " . $query_params["order"];
                break;

            default:
                $order_by = " ORDER BY dlv.id DESC";
                break;
        }

        try {
            // Fetch total records
            $count_sql = "SELECT COUNT(dlv.id) " . $tables . " " . $condition;
            $this->_query_string = $count_sql;

            //echo "<pre>";print_r($count_sql);exit;
        
            $number_of_rows = $this->_db->query($count_sql)->fetchColumn();
            $total_pages = ceil($number_of_rows / $query_params["per_page"]);

            // If page doesn't exists, then get first page data
            if ($query_params["page"] > $total_pages) {
                $query_params["page"] = 1;
            }
            $offset = ($query_params["page"] - 1) * $query_params["per_page"];

            // Add limit
            $limit = "LIMIT " . $offset . ", " . $query_params["per_page"];

            $sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition . " " . $order_by . " " . $limit;
            $this->_query_string = $sql;

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