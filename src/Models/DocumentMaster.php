<?php
/**
 * Model file for operations on document_master table
 */
namespace App\Models;

class DocumentMaster extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "document_master";
        $this->_table_alias = "dm";
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
            "user_id" => "Created By",
            "document_source_id" => "Uploaded From",
            "source_id" => "Source",
	        "file_name" => "File Name",
            "file_path" => "Path",
            "account_folder_id" => "Folder",
            "bucket_name" => "Bucket Name",
	        "bucket_path" => "Bucket Path",
            "file_type" => "Type",
            "file_pages" => "Total Pages",
            "source_document_id" => "Imported Document Id",
            "source_document_link" => "Imported Document Link",
            "public" => "Public",
            "share_access" => "Shared Access",
            "snooze_notifications" => "Snooze Notifications?",
            "locked" => "Locked?",
            "last_opened_by" => "Last Opened By",
            "last_opened_at" => "Last Opened At",
            "status" => "Status",
            "created" => "Created At",
            "modified" => "Modified"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "account_master" => ["account_master AS am", $this->_table_alias . ".account_id", "=", "am.id", "INNER"],
            "user_master" => ["user_master AS um", $this->_table_alias . ".user_id", "=", "um.id", "INNER"],
            "document_source_master" => ["document_source_master AS dsm", $this->_table_alias . ".document_source_id", "=", "dsm.id", "INNER"],
            "source_master" => ["source_master AS sm", $this->_table_alias . ".source_id", "=", "sm.id", "INNER"],
            "account_folders" => ["account_folders AS af", $this->_table_alias . ".account_folder_id", "=", "af.id", "LEFT"]            
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
            $tables = "FROM document_master dm INNER JOIN user_master um ON dm.user_id = um.id";

            // Fetching conditions and order
            $condition = "WHERE dm.id = " . $id . " AND dm.account_id = " . $payload["account_id"] . " AND dm.status <> " . $payload["deleted"];

            // Fetch total records
            $valid_row_sql = "SELECT dm.id " . $tables . " " . $condition;
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
     * Get listing of records
     *
     * @param $query_params (array): Query parameters
     * @param $payload (array): Payload of other required information
     *
     * @return (array) Array of records
     */
    public function getListData($query_params, $payload = []) {
        $data = [];
        $data["rows"] = [];

        // Fetch fields
        $fields = [
            "dm.id",
            "dm.user_id",
            "um.first_name",
            "um.last_name",
            "dm.document_source_id",
            "dm.file_name",
            "dm.last_opened_by",
            "dm.last_opened_at",
            "dm.file_path",
            "dm.bucket_path",
            "dm.file_type",
            "dm.file_pages",
            "dm.source_document_id",
            "dm.source_document_link",
            "dm.public",
            "dm.share_access",
            "dm.snooze_notifications",
            "dm.status",
            "af.name AS folder_name",
            "af.share_access AS folder_share_access",
            "ac.first_name AS contact_first_name",
            "ac.email AS contact_email",
            "ac.status AS contact_status",
            "(SELECT COUNT(dt.id) FROM document_teams dt WHERE dt.document_id = dm.id AND dt.status <> " . $payload["deleted"] . ") AS total_teams",
            "(SELECT SUM(time_spent) FROM document_link_visit_logs dlvl WHERE dlvl.document_id = dm.id) AS face_time",
            "(SELECT COUNT(DISTINCT dlvl.visit_id) FROM document_link_visit_logs dlvl WHERE dlvl.document_id = dm.id) AS total_visits"
        ];

        // Fetch from tables
        $tables = "FROM document_master dm 
        LEFT JOIN account_folders af ON dm.account_folder_id = af.id 
        INNER JOIN user_master um ON dm.user_id = um.id 
        LEFT JOIN account_contacts ac ON dm.last_opened_by = ac.id";

        // Fetching conditions and order
        $condition = "WHERE dm.account_id = " . $payload["account_id"] . " AND dm.status <> " . $payload["deleted"];

        // Add filters
        if (isset($query_params["user"])) {
            $condition .= " AND dm.user_id = " . ((int) $query_params["user"]);
        }
        if (isset($query_params["status"])) {
            $condition .= " AND dm.status = " . ((int) $query_params["status"]);
        }
        if (isset($query_params["folder"])) {
            //$tables = "FROM document_master dm INNER JOIN document_folders df ON dm.id = df.document_id INNER JOIN user_master um ON dm.user_id = um.id";
            $condition .= " AND dm.account_folder_id = " . ((int) $query_params["folder"]) . " AND dm.status <> " . $payload["deleted"];
        }
        if (isset($query_params["shared"])) {
            $condition .= " AND (EXISTS (SELECT dt.document_id 
            FROM account_team_members atm 
            INNER JOIN document_teams dt ON dt.account_team_id = atm.account_team_id 
            WHERE atm.user_id = " . $payload["user_id"] . " 
            AND atm.status <> " . $payload["deleted"] . " 
            AND dt.status <> " . $payload["deleted"] . " 
            AND dt.document_id = dm.id)
                 OR EXISTS (SELECT dm.id 
            FROM account_team_members atm 
            INNER JOIN account_folder_teams aft ON aft.account_team_id = atm.account_team_id 
            INNER JOIN document_master dm1 ON dm1.account_folder_id = aft.account_folder_id
            WHERE atm.user_id = " . $payload["user_id"] . " 
            AND atm.status <> " . $payload["deleted"] . " 
            AND aft.status <> " . $payload["deleted"] . " 
            AND dm.status <> " . $payload["deleted"] . " 
            AND dm1.id = dm.id)
                OR dm.public = 1
            )
            AND dm.user_id <> " . $payload["user_id"] . "
            ";
        } else {
            $condition .= " AND (dm.user_id = " . $payload["user_id"] . ") ";
        }

        if (isset($query_params["query"])) {
            $search_condition = [
                //"um.first_name LIKE '%" . $query_params["query"] . "%'",
                //"um.last_name LIKE '%" . $query_params["query"] . "%'",
                "dm.file_name LIKE '%" . addslashes($query_params["query"]) . "%'"
            ];

            $condition .= " AND (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "created_by":
                $order_by = " ORDER BY um.first_name " . $query_params["order"] . ", um.last_name " . $query_params["order"];
                break;

            case "name":
                $order_by = " ORDER BY dm.file_name " . $query_params["order"];
                break;

            case "status":
                $order_by = " ORDER BY dm.status " . $query_params["order"];
                break;

            default:
                $order_by = " ORDER BY dm.id DESC";
                break;
        }

        try {


            // Fetch total records
            $count_sql = "SELECT COUNT(dm.id) " . $tables . " " . $condition;
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

    /**
     * Get list of documents for given document link id
     *
     * @param $payload (array): Payload of other required information
     *
     * @return (array) Array of records
     */
    public function getAllDocuments($payload = []) {
        $data = [];

        if (empty($payload)) {
            return $data;
        }

        try {
            // Fetch from tables
            $tables = "FROM " . $this->_table . " " . $this->_table_alias . " INNER JOIN document_link_files dlf ON dlf.document_id = " . $this->_table_alias . ".id";

            // Fetching conditions and order
            $condition = "WHERE dlf.document_link_id = " . $payload["doc_link_id"] . " AND dlf.status <> " . $payload["deleted"] . " AND " . $this->_table_alias . ".status = " . $payload["active"];

            // Fetch total records
            $valid_row_sql = "SELECT " . $this->_table_alias . ".file_name, " . $this->_table_alias . ".file_path, " . $this->_table_alias . ".file_type, dlf.status, dlf.document_id " . $tables . " " . $condition;
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

}
