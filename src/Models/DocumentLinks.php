<?php
/**
 * Model file for operations on document_teams table
 */
namespace App\Models;

class DocumentLinks extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "document_links";
        $this->_table_alias = "dl";
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
            "source_id" => "Source",
            "account_company_id" => "Company",
            "account_contact_id" => "Contact",
            "name" => "Name",
            // "short_description" => "Short Description",
            "link_domain" => "At Domain",
            "link_code" => "Slug",
            "type" => "Type",
            "is_set_expiration_date" => "Set Expiration Date?",
            "expires_at" => "Expires At",
            "forward_tracking" => "Forward Tracking",
            "not_viewed_mail_sent" => "Not Viewed Reminder Email Sent?",
            "email_me_when_viewed" => "Email Me When Viewed",
            "allow_download" => "Allow Download?",
            "password_protected" => "Password Protected?",
            "access_password" => "Password",
            "ask_visitor_info" => "Ask Visitor Info?",
            "visitor_info_payload" => "Visitor Info Fields",
            "snooze_notifications" => "Snooze Notifications?",
            "remind_not_viewed" => "Remind if not viewed",
            "remind_at" => "Remind At",
            "visitor_slug" => "Visitor Slug",
            "last_opened_by" => "Last Opened By",
            "last_opened_at" => "Last Opened At",
            "status" => "Status",
            "created" => "Created At",
            "modified" => "Modified At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "account_master" => ["account_master AS am", $this->_table_alias . ".account_id", "=", "am.id", "INNER"],
            "user_master" => ["user_master AS um", $this->_table_alias . ".user_id", "=", "um.id", "INNER"],
            "source_master" => ["source_master AS sm", $this->_table_alias . ".source_id", "=", "sm.id", "INNER"],
            "document_link_files" => ["document_link_files AS dlf", $this->_table_alias . ".id", "=", "dlf.document_link_id", "INNER"]

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
    public function checkRowValidity($id, $payload = []) {
        $valid = false;

        if (empty($id) || empty($payload)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM document_links dl INNER JOIN user_master um ON dl.user_id = um.id";

            // Fetching conditions and order
            $condition = "WHERE dl.id = " . $id . " AND dl.account_id = " . $payload["account_id"] . " AND dl.user_id = " . $payload["user_id"] . " AND dl.status <> " . $payload["deleted"];

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
     * Get list of records
     * @param $query_params (array): Query parameters
     * @param $payload (array): Payload of other required information
     *
     * @return (array) Array of records
     */
    public function  getListData($query_params, $payload = []) {
        $data = [];

        // Fetch fields
        $fields = [
            "dl.id",
            "dl.name",
            "dl.status",
            "dl.last_opened_at",
            "dl.last_opened_by",
            "ac.first_name AS contact_first_name",
            "ac.email AS contact_email",
            "ac.status AS contact_status",
            "link_domain",
            "link_code",
            "um.first_name AS created_by_fn",
            "(SELECT COUNT(dlv.id) FROM document_link_visits dlv WHERE dlv.document_link_id = dl.id) AS total_visit",
            "(SELECT SUM(dlg.time_spent) FROM document_link_visit_logs dlg WHERE EXISTS (SELECT dlv.id FROM document_link_visits dlv WHERE dlv.document_link_id = dl.id AND dlg.visit_id = dlv.id)) AS total_time_spent"
        ];

        // Fetch from tables
        $tables = "FROM document_links dl 
                   LEFT JOIN account_contacts ac ON dl.last_opened_by = ac.id 
                   INNER JOIN user_master um ON dl.user_id = um.id";

        // Fetching conditions and order
        $condition = "WHERE dl.account_id = " . $payload["account_id"] . " AND dl.user_id = " . $payload["user_id"] . " AND dl.status <> " . $payload["deleted"] . " AND dl.type = " . $payload["type"];

        // Add filters
        // if (isset($query_params["user"])) {
        //     $condition .= " AND dl.user_id = " . ((int) $query_params["user"]);
        // }
        // if (isset($query_params["name"])) {
        //     $condition .= " AND dl.name = " . ($query_params["name"]);
        // }
        // if (isset($query_params["status"])) {
        //     $condition .= " AND dl.status = " . ((int) $query_params["status"]);
        // }

        if (isset($query_params["query"])) {
            $search_condition = [
                "ac.first_name LIKE '%" . $query_params["query"] . "%'",
                "ac.email LIKE '%" . $query_params["query"] . "%'",
                "dl.name LIKE '%" . $query_params["query"] . "%'"
            ];

            $condition .= " AND (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "name":
                $condition .= " ORDER BY dl.name " . $query_params["order"];
                break;

            case "created_by":
                $condition .= " ORDER BY um.first_name " . $query_params["order"];
                break;

            default: 
                $condition .= " ORDER BY dl.id DESC";
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

    public function checkRowAvailable($id, $payload = []) {
        $data = false;

        if (empty($id) || empty($payload)) {
            return $data;
        }

        try {
            // Fetch from tables
            $tables = "FROM document_master";

            // Fetching conditions and order
            $condition = "WHERE status <> " . $payload["deleted"] . " AND id = " . $id . " AND account_id = " . $payload["account_id"];

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

    public function getFileDetails($id, $payload = []) {
        $data = [];

        if (empty($id)) {
            return $data;
        }

        try {
            // Fetch from tables
            $tables = "FROM document_master dm";

            // Fetching conditions and order
            $condition = "WHERE dm.id = " . $id . " AND dm.status <> " . $payload["deleted"] . " AND dm.account_id = " . $payload["account_id"];

            // Fetch total records
            $valid_row_sql = "SELECT dm.id, dm.file_name " . $tables . " " . $condition;
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

    public function getDocLinkData($id, $payload = []) {
        $data = [];

        if (empty($id) || empty($payload)) {
            return $data;
        }

        try {
            // Fetch from tables
            $tables = "FROM document_links dl 
                       INNER JOIN document_link_files dlf ON dlf.document_link_id = dl.id AND dl.id 
                       INNER JOIN user_master um ON dl.user_id = um.id 
                       LEFT JOIN account_contacts ac ON CASE WHEN dl.type = 2 THEN dl.account_contact_id ELSE dl.last_opened_by END = ac.id";

            // Fetching conditions and order
            $condition = "WHERE dlf.document_id = " . $id . " AND dlf.status <> " . $payload["deleted"] . " AND dl.account_id = " . $payload["account_id"] . " AND dl.status <> " . $payload["deleted"] . " ORDER BY dl.id DESC";

            // Fetch total records
            $valid_row_sql = "SELECT 
                                dl.id, dl.name, dl.link_domain, dl.link_code, dl.status, dl.type, dl.last_opened_at, 
                                um.first_name AS user_first_name, um.email AS user_email, 
                                ac.first_name AS contact_first_name, ac.email AS contact_email, ac.status AS contact_status, 
                                (SELECT COUNT(DISTINCT dlvl.visit_id) FROM document_link_visit_logs dlvl WHERE EXISTS (SELECT dlv.id FROM document_link_visits dlv WHERE dlv.id = dlvl.visit_id AND dlvl.document_id = " . $id . " AND dlv.document_link_id = dl.id)) AS total_visit, 
                                (SELECT SUM(dlvl.time_spent) FROM document_link_visit_logs dlvl WHERE EXISTS (SELECT dlvl.id FROM document_link_visits dlv WHERE dlv.id = dlvl.visit_id AND dlvl.document_id = " . $id . " AND dlv.document_link_id = dl.id)) AS total_time_spent 
                                " . $tables . " " . $condition;
            $this->_query_string = $valid_row_sql;
            
            $stmt = $this->_db->query($valid_row_sql);
            foreach ($stmt as $row) {
                $data[] = $row;
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

    /**
     * Check doc availability (is requested doc available or not)
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
            $tables = "FROM " . $this->_table;

            // Fetching conditions and order
            $condition = "WHERE id = " . $id . " AND account_id = " . $payload["account_id"] . " AND user_id = " . $payload["user_id"] . " AND status <> " . $payload["deleted"];

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

    public function getOverallPerformance($id) {
        $data = [];

        if (empty($id)) {
            return $data;
        }

        try {
            // Fetch from tables
            $tables = "FROM document_links dl 
                       INNER JOIN document_link_visits dlv ON dl.id = dlv.document_link_id 
                       INNER JOIN document_link_visit_logs dlg ON dlv.id = dlg.visit_id
                       LEFT JOIN document_master dm ON dm.id = dlg.document_id
                       LEFT JOIN account_contacts ac ON ac.id = dl.account_contact_id";

            // Fetching conditions and order
            $condition = "WHERE dlv.document_link_id = " . $id . " ORDER BY dlv.id DESC";

            // Fetch total records
            $valid_row_sql = "SELECT 
                                SUM(dlg.download) as total_download, 
                                SUM(dlg.time_spent) as total_time_spent, 
                                (SELECT COUNT(dlv.id) FROM document_link_visits dlv WHERE dlv.document_link_id = " . $id . ") as total_visit, dl.name, 
                                SUM(DISTINCT dm.file_pages) as total_space_pages, 
                                ac.first_name AS contact_first_name, ac.email AS contact_email, 
                                COUNT(DISTINCT dlg.page_num) as total_viewed_pages " . $tables . " " . $condition;
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
                       INNER JOIN document_master dm ON dlf.document_id = dm.id AND dm.account_id = " . $payload["account_id"] . " AND dm.user_id = " . $payload["user_id"] . " 
                       LEFT JOIN account_contacts ac ON dm.last_opened_by = ac.id";

            // Fetching conditions and order
            $condition = "WHERE dlf.document_link_id = " . $payload["space_id"] . " AND dlf.status <> " . $payload["deleted"] . " ORDER BY dlf.id DESC";

            // Fetch total records
            $valid_row_sql = "SELECT 
                                dm.file_name, dm.file_pages, dm.last_opened_at, 
                                ac.first_name AS contact_first_name, ac.email AS contact_email, ac.status AS contact_status, 
                                (SELECT SUM(dlvl.time_spent) FROM document_link_visit_logs dlvl WHERE EXISTS (SELECT dlv.id FROM document_link_visits dlv WHERE dlv.id = dlvl.visit_id AND dlvl.document_id = dlf.document_id AND dlv.document_link_id = " . $payload["space_id"] . ")) AS total_time_spent, 
                                (SELECT COUNT(DISTINCT dlvl.page_num) FROM document_link_visit_logs dlvl WHERE EXISTS (SELECT dlv.id FROM document_link_visits dlv WHERE dlv.id = dlvl.visit_id AND dlvl.document_id = dlf.document_id AND dlv.document_link_id = " . $payload["space_id"] . ")) AS total_viewed_pages, 
                                (SELECT COUNT(DISTINCT dlvl.visit_id) FROM document_link_visit_logs dlvl WHERE EXISTS (SELECT dlv.id FROM document_link_visits dlv WHERE dlv.id = dlvl.visit_id AND dlvl.document_id = dlf.document_id AND dlv.document_link_id = " . $payload["space_id"] . ")) AS total_visits 
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

    public function getSpacePerformanceDetails($query_params, $payload = []) {
        $data = [];

        // Fetch fields
        $fields = [
            "dlv.id AS visit_id",
            "dlv.modified AS visited_at",
            "dlv.location",
            "ac.first_name AS contact_first_name",
            "ac.email AS contact_email",
            "ac.status AS contact_status",
            "dl.name AS link_name",
            "(SELECT SUM(dm.file_pages) FROM document_master dm WHERE EXISTS (SELECT dlf.document_id FROM document_link_files dlf WHERE dm.id = dlf.document_id AND dlf.document_link_id = " . $payload["space_id"] . ")) AS total_space_pages",
            "(SELECT COUNT(DISTINCT dlvl.page_num) FROM document_link_visit_logs dlvl WHERE dlvl.visit_id = dlv.id) AS total_visited_pages",
            "(SELECT SUM(dlvl.time_spent) FROM document_link_visit_logs dlvl WHERE dlvl.visit_id = dlv.id) AS time_spent",
            "(SELECT COALESCE(NULLIF(ac.first_name,''), ac.email) FROM account_contacts ac WHERE ac.id = dl.account_contact_id) AS direct_link_to"
        ];

        // Fetch from tables
        $tables = "FROM document_link_visits dlv 
                   INNER JOIN document_links dl ON dlv.document_link_id = dl.id AND dlv.document_link_id = " . $payload["space_id"] . " 
                   LEFT JOIN account_contacts ac ON dlv.account_contact_id = ac.id";

        // Fetching conditions and order
        $condition = "WHERE dlv.document_link_id = " . $payload["space_id"] . " AND dl.status <> " . $payload["deleted"];

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
            case "name":
                $condition .= " ORDER BY dl.name " . $query_params["order"];
                break;

            default: 
                $condition .= " ORDER BY dlv.id DESC";
                break;
        }

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
            $data["rows"] = [];
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

}