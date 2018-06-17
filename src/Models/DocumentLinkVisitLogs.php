<?php
/**
 * Model file for operations on document_logs table
 */
namespace App\Models;

class DocumentLinkVisitLogs extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "document_link_visit_logs";
        $this->_table_alias = "dlg";
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
            "visit_id" => "Visit Id",
            "document_id" => "Document Id",
            "doc_version" => "Document Version",
            "page_num" => "Page Number",
            "time_spent" => "Time Spent",
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

    /**
     * Check doc availability (is requested doc available or not)
     *
     * @param $id (integer): Id of the record
     * @param $payload (array): Payload of other required information
     *
     * @return (boolean) Row valid or not
     */
    public function checkDocAvailability($id, $payload = []) {
        $valid = false;

        if (empty($id) || empty($payload)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM document_master";

            // Fetching conditions and order
            $condition = "WHERE id = " . $id . " AND account_id = " . $payload["account_id"] . " AND status <> " . $payload["deleted"];

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

    /**
     * Get list of records
     * @param $query_params (array): Query parameters
     * @param $payload (array): Payload of other required information
     *
     * @return (array) Array of records
     */
    public function  getListData($query_params, $payload = []) {
        $data = [];

        try {
            // Fetch from tables
            $tables = "FROM document_link_visits dlv 
                       INNER JOIN document_link_files dlf ON dlv.document_link_id = dlf.document_link_id AND dlf.document_id = " . $payload["doc_id"] . " 
                       INNER JOIN document_master dm ON dlf.document_id = dm.id AND dm.account_id = " . $payload["account_id"] . " 
                       INNER JOIN document_links dl ON dlf.document_link_id = dl.id AND dl.account_id = " . $payload["account_id"];

            // Fetching conditions and order
            $condition = "WHERE dm.id = " . $payload["doc_id"];

            // Fetch total records
            $valid_row_sql = "SELECT 
                                dm.file_pages, dm.file_name, 
                                -- COUNT(dlv.id) AS total_visit,
                                SUM(dlv.download) AS total_download, 
                                (SELECT COUNT(DISTINCT dlg.visit_id) FROM document_link_visit_logs dlg WHERE EXISTS (SELECT dlv.id FROM document_link_visits dlv WHERE dlv.id = dlg.visit_id AND dlg.document_id = " . $payload["doc_id"] . ")) AS total_visit, 
                                (SELECT SUM(dlg.time_spent) FROM document_link_visit_logs dlg WHERE dlg.document_id = " . $payload["doc_id"] . ") AS total_time_spent, 
                                (SELECT COUNT(DISTINCT dlg.page_num) FROM document_link_visit_logs dlg WHERE dlg.document_id = " . $payload["doc_id"] . ") AS total_viewed_page  
                                " . $tables . " " . $condition;
            $this->_query_string = $valid_row_sql;
            
            $stmt = $this->_db->query($valid_row_sql);
            foreach ($stmt as $row) {
                $data = $row;
            }

            $stmt = null;

        } catch(\PDOException $e) {
            echo $e->getMessage();exit;
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $data;
    }

    public function getDocumentTotalVisit($query_params, $payload = []) {
        $data = [];
        $data["rows"] = [];

        // Fetch fields
        $fields = [
            "dlv.id",
            "dlv.account_contact_id",
            "dlv.location",
            "dlv.modified AS visited_at",
            "ac.first_name AS contact_first_name",
            "ac.email AS contact_email",
            "ac.status AS contact_status",
            "dl.name AS link_name",
            "dl.type",
            "(SELECT SUM(dlvl.time_spent) FROM document_link_visit_logs dlvl WHERE dlvl.visit_id = dlv.id AND dlvl.document_id = " . $payload["doc_id"] . ") AS time_spent",
            "(SELECT COUNT(DISTINCT dlvl.page_num) FROM document_link_visit_logs dlvl WHERE dlvl.visit_id = dlv.id AND dlvl.document_id = " . $payload["doc_id"] . ") AS total_viewed_page",
            "(SELECT COUNT(DISTINCT dlvl.visit_id) FROM document_link_visit_logs dlvl WHERE dlvl.document_id = " . $payload["doc_id"] . ") AS total_visit",
            "(SELECT COALESCE(NULLIF(ac.first_name,''), ac.email) FROM account_contacts ac WHERE ac.id = dl.account_contact_id) AS direct_link_to"
        ];

        // Fetch from tables
        $tables = "FROM document_link_visits dlv 
                   INNER JOIN document_links dl ON dlv.document_link_id = dl.id 
                   LEFT JOIN account_contacts ac ON dlv.account_contact_id = ac.id";

        // Fetching conditions and order
        $condition = "WHERE EXISTS (SELECT dlvl.id FROM document_link_visit_logs dlvl WHERE dlv.id = dlvl.visit_id AND dlvl.document_id = " . $payload["doc_id"] . ") AND dl.account_id = " . $payload["account_id"] . " AND dl.status <> " . $payload["deleted"];

        // Add filters
        // if (isset($query_params["user"])) {
        //     $condition .= " AND dl.user_id = " . ((int) $query_params["user"]);
        // }
        // if (isset($query_params["type"])) {
        //     $condition .= " AND dl.type = " . ((int) $query_params["type"]);
        // }
        // if (isset($query_params["status"])) {
        //     $condition .= " AND dl.status = " . ((int) $query_params["status"]);
        // }

        // if (isset($query_params["query"])) {
        //     $search_condition = [
        //         "um.first_name LIKE '%" . $query_params["query"] . "%'",
        //         "um.last_name LIKE '%" . $query_params["query"] . "%'",
        //         "dl.name LIKE '%" . $query_params["query"] . "%'"
        //     ];

        //     $condition .= " AND (" . implode(" OR ", $search_condition) . ")";
        // }

        // Add Order by
        // switch ($query_params["order_by"]) {
        //     case "name":
        //         $condition .= " ORDER BY dl.name " . $query_params["order"];
        //         break;

        //     default: 
        //         $condition .= " ORDER BY dl.id DESC";
        //         break;
        // }

        try {
            // Fetch total records
            $count_sql = "SELECT COUNT(dlv.id) " . $tables . " " . $condition;
            $this->_query_string = $count_sql;

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
            $this->_failes_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $data;
    }

    public function getDocData($doc_id) {
        $data = [];

        try {
            // Fetch from tables
            $tables = "FROM document_master dm";

            // Fetching conditions and order
            $condition = "WHERE dm.id = " . $doc_id;

            // Fetch total records
            $valid_row_sql = "SELECT 
                                dm.file_pages AS total_file_pages, dm.file_name AS file_name 
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

    public function getOverallVisitDetails($payload = []) {
        $data = [];

        try {
            // Fetch from tables
            $tables = "FROM " . $this->_table . " dlvl 
                       INNER JOIN document_link_visits dlv ON dlvl.visit_id = dlv.id AND dlvl.document_id = " . $payload["doc_id"] . " 
                       LEFT JOIN account_contacts ac ON dlv.account_contact_id = ac.id";

            // Fetching conditions and order
            $condition = "WHERE dlvl.visit_id = " . $payload["visit_id"];

            // Fetch total records
            $valid_row_sql = "SELECT 
                                SUM(dlvl.time_spent) AS total_time_spent, COUNT(DISTINCT dlvl.page_num) AS total_viewed_pages, 
                                dlv.download, dlv.modified, dlv.account_contact_id, 
                                (SELECT COUNT(DISTINCT dlvl.visit_id) FROM document_link_visit_logs dlvl WHERE dlvl.document_id = " . $payload["doc_id"] . ") AS total_visit, 
                                ac.first_name AS contact_first_name, ac.email AS contact_email, ac.status AS contact_status 
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

    public function getPagewiseDetails($payload = []) {
        $data = [];

        try {
            // Fetch from tables
            $tables = "FROM " . $this->_table;

            // Fetching conditions and order
            $condition = "WHERE visit_id = " . $payload["visit_id"] . " AND document_id = " . $payload["doc_id"] . " GROUP BY page_num";

            // Fetch total records
            $valid_row_sql = "SELECT SUM(time_spent) AS total_time_spent, page_num " . $tables . " " . $condition;
            $this->_query_string = $valid_row_sql;
            
            $stmt = $this->_db->query($valid_row_sql);
            foreach ($stmt as $row) {
                $row["formatted_total_time_spent"] = gmdate("H:i:s", (int)$row["total_time_spent"]);
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

    public function getDocumentVisitDetails($query_params, $payload = []) {
        $data = [];
        $data["rows"] = [];

        // Fetch fields
        $fields = [
            "dl.name AS link_name",
            "ac.first_name AS contact_first_name",
            "ac.email AS contact_email",
            "ac.status AS contact_status",
            "dlv.id AS visit_id",
            "dlv.location",
            "dlv.modified",
            "(SELECT SUM(dlvl.time_spent) FROM document_link_visit_logs dlvl WHERE dlv.id = dlvl.visit_id AND dlvl.document_id = " . $payload["doc_id"] . ") AS total_time_spent",
            "(SELECT COUNT(DISTINCT dlvl.page_num) FROM document_link_visit_logs dlvl WHERE dlv.id = dlvl.visit_id AND dlvl.document_id = " . $payload["doc_id"] . ") AS total_viewed_pages",
            "(SELECT COALESCE(NULLIF(ac.first_name,''), ac.email) FROM account_contacts ac WHERE ac.id = dl.account_contact_id) AS direct_link_to"
        ];

        // Fetch from tables
        $tables = "FROM document_link_visits dlv 
                   INNER JOIN document_links dl ON dlv.document_link_id = dl.id 
                   LEFT JOIN account_contacts ac ON dlv.account_contact_id = ac.id";

        // Fetching conditions and order
        $condition = "WHERE EXISTS (SELECT dlvl.id FROM document_link_visit_logs dlvl WHERE dlv.id = dlvl.visit_id AND dlvl.document_id = " . $payload["doc_id"] . ") AND dlv.account_contact_id = " . $payload["account_contact_id"] . " AND dl.account_id = " . $payload["account_id"] . " AND dlv.id <> " . $payload["visit_id"];

        // Add filters
        // if (isset($query_params["user"])) {
        //     $condition .= " AND dl.user_id = " . ((int) $query_params["user"]);
        // }
        // if (isset($query_params["type"])) {
        //     $condition .= " AND dl.type = " . ((int) $query_params["type"]);
        // }
        // if (isset($query_params["status"])) {
        //     $condition .= " AND dl.status = " . ((int) $query_params["status"]);
        // }

        // if (isset($query_params["query"])) {
        //     $search_condition = [
        //         "um.first_name LIKE '%" . $query_params["query"] . "%'",
        //         "um.last_name LIKE '%" . $query_params["query"] . "%'",
        //         "dl.name LIKE '%" . $query_params["query"] . "%'"
        //     ];

        //     $condition .= " AND (" . implode(" OR ", $search_condition) . ")";
        // }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "account_contact_id":
                $condition .= " ORDER BY dlv.account_contact_id " . $query_params["order"];
                break;

            default: 
                $condition .= " ORDER BY dlv.id DESC";
                break;
        }

        try {
            // Fetch total records
            $count_sql = "SELECT COUNT(dl.id) " . $tables . " " . $condition;
            $this->_query_string = $count_sql;

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
            echo $e->getMessage();exit;
            $this->_failes_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $data;
    }

}