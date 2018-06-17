<?php
/**
 * Model file for operations on account_templates table
 */
namespace App\Models;

class AccountTemplates extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "account_templates";
        $this->_table_alias = "at";
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
            "source_id" => "Source",
            "title" => "Title",
            "subject" => "Subject",
            "content" => "Content",
            "total_mail_usage" => "Total Usage in Emails",
            "total_mail_open" => "Total Email Opens (Unique)",
            "total_campaign_usage" => "Total Usage in Campaigns",
            "total_campaign_mails" => "Total Recipients of Campaigns",
            "total_campaign_open" => "Total Recipients Open (Unique)",
            "last_used_at" => "Last Used At",
            "public" => "Is Public",
            "share_access" => "Shared Access",
            "status" => "Status",
            "account_folder_id" => "Folder",
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
            $tables = "FROM account_templates at INNER JOIN user_master um ON at.user_id = um.id";

            // Fetching conditions and order
            $condition = "WHERE at.id = " . $id . " AND at.account_id = " . $payload["account_id"] . " AND at.status <> " . $payload["deleted"];

            // Fetch total records
            $valid_row_sql = "SELECT at.id " . $tables . " " . $condition;
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
            "at.id",
            "at.user_id",
            "um.first_name",
            "um.last_name",
            "at.title",
            "at.total_mail_usage",
            "at.total_mail_open",
            "round((at.total_mail_open / at.total_mail_usage * 100), 2) AS email_performance",
            "at.total_campaign_mails",
            "at.total_campaign_open",
            "round((at.total_campaign_open / at.total_campaign_mails * 100), 2) AS campaign_performance",
            "at.last_used_at",
            "at.status",
            "at.share_access",
            "at.public",
            "af.name AS folder_name",
            "af.share_access AS folder_share_access",
            "(SELECT COUNT(att.id) FROM account_template_teams att WHERE att.account_template_id = at.id AND att.status <> " . $payload["deleted"] . ") AS total_teams"
        ];
   
        // Fetch from tables
        $tables = "FROM account_templates as at
        LEFT JOIN account_folders af ON at.account_folder_id = af.id
        INNER JOIN user_master um ON at.user_id = um.id";

        // Fetching conditions and order
        $condition = "WHERE at.account_id = " . $payload["account_id"] . " AND at.status <> " . $payload["deleted"];

        // Add filters
        if (isset($query_params["user"])) {
            $condition .= " AND at.user_id = " . ((int) $query_params["user"]);
        }
        if (isset($query_params["status"])) {
            $condition .= " AND at.status = " . ((int) $query_params["status"]);
        }
        if (isset($query_params["folder"])) {
            //$tables = "FROM account_templates at INNER JOIN account_template_folders atf ON at.id = atf.account_template_id INNER JOIN user_master um ON at.user_id = um.id";
            $condition .= " AND at.account_folder_id = " . ((int) $query_params["folder"]) . " AND at.status <> " . $payload["deleted"];
        }
        if (isset($query_params["shared"])) {
            
            $condition .= " AND (EXISTS (SELECT att.account_template_id 
            FROM account_team_members atm 
            INNER JOIN account_template_teams att ON att.account_team_id = atm.account_team_id 
            WHERE atm.user_id = " . $payload["user_id"] . " 
            AND atm.status <> " . $payload["deleted"] . " 
            AND att.status <> " . $payload["deleted"] . " 
            AND att.account_template_id = at.id)
                 OR EXISTS (SELECT at.id 
            FROM account_team_members atm 
            INNER JOIN account_folder_teams aft ON aft.account_team_id = atm.account_team_id 
            INNER JOIN account_templates at1 ON at1.account_folder_id = aft.account_folder_id
            WHERE atm.user_id = " . $payload["user_id"] . " 
            AND atm.status <> " . $payload["deleted"] . " 
            AND aft.status <> " . $payload["deleted"] . " 
            AND at.status <> " . $payload["deleted"] . " 
            AND at.id = at1.id)
                OR at.public = 1
                OR af.public = 1
            )
            ";
        } else {
            $condition .= " AND (at.user_id = " . $payload["user_id"] . " OR af.user_id = " . $payload["user_id"] . ") ";
        }

        if (isset($query_params["query"])) {
            $search_condition = [
                "um.first_name LIKE '%" . addslashes($query_params["query"]) . "%'",
                "um.last_name LIKE '%" . addslashes($query_params["query"]) . "%'",
                "at.title LIKE '%" . addslashes($query_params["query"]) . "%'"
            ];

            $condition .= " AND (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "title":
                $order_by = " ORDER BY at.title " . $query_params["order"];
                break;

            case "created_by":
                $order_by = " ORDER BY um.first_name " . $query_params["order"] . ", um.last_name " . $query_params["order"];
                break;

            case "total_mail_usage":
                $order_by = " ORDER BY at.total_mail_usage " . $query_params["order"];
                break;

            case "total_mail_open":
                $order_by = " ORDER BY at.total_mail_open " . $query_params["order"];
                break;

            case "total_campaign_mails":
                $order_by = " ORDER BY at.total_campaign_mails " . $query_params["order"];
                break;

            case "total_campaign_open":
                $order_by = " ORDER BY at.total_campaign_open " . $query_params["order"];
                break;

            case "total_mail_usage":
                $condition .= " ORDER BY at.total_mail_usage " . $query_params["order"];
                break;

            case "total_campaign_mails":
                $condition .= " ORDER BY at.total_campaign_mails " . $query_params["order"];
                break;

            case "email_performance":
                $order_by = " ORDER BY email_performance " . $query_params["order"];
                break;

            case "campaign_performance":
                $order_by = " ORDER BY campaign_performance " . $query_params["order"];
                break;

            case "last_used_at":
                $order_by = " ORDER BY at.last_used_at " . $query_params["order"];
                break;

            case "status":
                $order_by = " ORDER BY at.status " . $query_params["order"];
                break;

            case "last_used_at":
                $condition .= " ORDER BY at.last_used_at " . $query_params["order"];
                break;

            default:
                $order_by = " ORDER BY at.id DESC";
                break;
        }

        try {
            // Fetch total records
            $count_sql = "SELECT COUNT(at.id) " . $tables . " " . $condition;
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