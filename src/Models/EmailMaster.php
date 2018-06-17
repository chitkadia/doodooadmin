<?php
/**
 * Model file for operations on email_master table
 */
namespace App\Models;

class EmailMaster extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "email_master";
        $this->_table_alias = "em";
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
            "user_id" => "Create By",
            "account_template_id" => "Template",
            "account_sending_method_id" => "Sent Via",
            "source_id" => "Source",
            "subject" => "Subject",
            "content" => "Content",
            "open_count" => "Open Count",
            "last_opened" => "Last Opened At",
            "reply_count" => "Reply Count",
            "last_replied" => "Last Replied At",
            "reply_last_checked" => "Reply Last Checked At",
            "reply_check_count" => "Reply Check Count",
            "click_count" => "Total Clicks",
            "last_clicked" => "Last Clicked At",
            "is_scheduled" => "Is Scheduled",
            "is_bounce" => "Is Bounce?",
            "scheduled_at" => "Scheduled At",
            "timezone" => "Timezone",
            "sent_at" => "Sent At",
            "sent_message_id" => "Message Id",
            "sent_response" => "Sent Response",
            "track_reply" => "Track Reply?",
            "track_click" => "Track Clicks?",
            "total_recipients" => "Total Recipients",
            "snooze_notifications" => "Snooze Notifications?",
            "progress" => "Progress",
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
            "account_templates" => ["account_templates AS at", $this->_table_alias . ".account_template_id", "=", "at.id", "INNER"],
            "account_sending_methods" => ["account_sending_methods AS asm", $this->_table_alias . ".account_sending_method_id", "=", "asm.id", "INNER"],
            "account_sending_methods_left" => ["account_sending_methods AS asm", $this->_table_alias . ".account_sending_method_id", "=", "asm.id", "LEFT"],
            "source_master" => ["source_master AS sm", $this->_table_alias . ".source_id", "=", "sm.id", "INNER"]
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
            $tables = "FROM email_master em INNER JOIN user_master um ON em.user_id = um.id";

            // Fetching conditions and order
            $condition = "WHERE em.id = " . $id . " AND em.account_id = " . $payload["account_id"] . " AND em.status <> " . $payload["deleted"];

            //if (!$payload["is_owner"]) {
                $condition .= " AND em.user_id = " . $payload["user_id"];
            //}

            // Fetch total records
            $valid_row_sql = "SELECT em.id " . $tables . " " . $condition;
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

        // Fetch fields
        $fields = [
            "em.id",
            "em.user_id",
            "um.first_name",
            "um.last_name",
            "em.account_sending_method_id",
            "asm.name AS email_sending_account",
            "em.subject",
            "em.open_count",
            "em.reply_count",
            "em.click_count",
            "em.last_opened",
            "em.last_replied",
            "em.last_clicked",
            "em.scheduled_at",
            "em.timezone",
            "em.sent_at",
            "em.total_recipients",
            "em.snooze_notifications",
            "em.progress",
            "em.status",
            "em.source_id",
            "esmm.code",
            "(SELECT eth.acted_at FROM email_track_history eth WHERE eth.email_id = em.id ORDER BY eth.id DESC LIMIT 1) AS last_activity_date",
            //"(SELECT COUNT(ero.id) FROM email_recipients ero WHERE ero.email_id = em.id AND ero.open_count > 0 AND ero.status <> " . $payload["deleted"] . ") AS open_count",
            //"(SELECT COUNT(err.id) FROM email_recipients err WHERE err.email_id = em.id AND err.replied = 1 AND err.status <> " . $payload["deleted"] . ") AS reply_count",
            "(SELECT COUNT(erb.id) FROM email_recipients erb WHERE erb.email_id = em.id AND erb.is_bounce = 1 AND erb.status <> " . $payload["deleted"] . ") AS bounce_count"
            //"(SELECT COUNT(erc.id) FROM email_recipients erc WHERE erc.email_id = em.id AND erc.click_count > 0 AND erc.status <> " . $payload["deleted"] . ") AS click_count",
            //"( if(em.status = 0, 'Draft', if(em.progress = 0, 'Schedule', if(em.progress = 1, 'Sent', 'Fail'))) ) AS sort_status"
        ];

        // Fetch from tables
        $tables = "FROM email_master em 
        INNER JOIN user_master um ON em.user_id = um.id 
        LEFT JOIN account_sending_methods asm ON em.account_sending_method_id = asm.id 
        LEFT JOIN email_sending_method_master esmm ON asm.email_sending_method_id = esmm.id";

        // Fetching conditions and order
        $condition = "WHERE em.account_id = " . $payload["account_id"] . " AND em.status <> " . $payload["deleted"];

        //if (!$payload["is_owner"]) {
            $condition .= " AND em.user_id = " . $payload["user_id"];
        //}

        // Add filters
        if (isset($query_params["user"])) {
            $condition .= " AND em.user_id = " . ((int) $query_params["user"]);
        }
        if (isset($query_params["status"])) {
            $condition .= " AND em.status = " . ((int) $query_params["status"]);
        }
        if (isset($query_params["progress"])) {
            $condition .= " AND em.progress = " . ((int) $query_params["progress"]) . " AND em.status <> " . $payload["draft"];
        }

        if (isset($query_params["query"])) {
            $search_condition = [
                "um.first_name LIKE '%" . $query_params["query"] . "%'",
                "um.last_name LIKE '%" . $query_params["query"] . "%'",
                "em.subject LIKE '%" . $query_params["query"] . "%'"
            ];

            $condition .= " AND (" . implode(" OR ", $search_condition) . ")";
        }

        $order_by_last_activity = "";
        // Add Order by
	    switch ($query_params["order_by"]) {
            case "subject":
                $condition .= " ORDER BY em.subject " . $query_params["order"];
                break;

            case "created_by":
                $condition .= " ORDER BY um.first_name " . $query_params["order"] . ", um.last_name " . $query_params["order"];
                break;

            case "total_recipients":
                $condition .= " ORDER BY em.total_recipients " . $query_params["order"];
                break;

            case "scheduled_at":
                $condition .= " ORDER BY em.scheduled_at " . $query_params["order"] . ",sent_at " . $query_params["order"];
                break;

	        case "progress":
                $condition .= " ORDER BY em.status " . $query_params["order"] . ",progress " . $query_params["order"];
                break;

            case "open_count":
                $condition .= " ORDER BY em.open_count " . $query_params["order"];
                break;

            case "reply_count":
                $condition .= " ORDER BY em.reply_count " . $query_params["order"];
                break;

            case "click_count":
                $condition .= " ORDER BY em.click_count " . $query_params["order"];
                break;

            case "last_activity":
                $order_by_last_activity = " ORDER BY last_activity_date " . $query_params["order"];
                break;

            default:
                $condition .= " ORDER BY em.id DESC";
                break;
        }

        try {
            // Fetch total records
            $count_sql = "SELECT COUNT(em.id) " . $tables . " " . $condition;
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

            $condition .= $order_by_last_activity;

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
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $data;
    }
}
