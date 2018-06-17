<?php
/**
 * Model file for operations on account_contacts table
 */
namespace App\Models;

use \App\Components\SHAppComponent;

class AccountContacts extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "account_contacts";
        $this->_table_alias = "aco";
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
            "account_company_id" => "Company",
            "source_id" => "Source",
            "email" => "Email",
            "first_name" => "First Name",
            "last_name" => "Last Name",
            "phone" => "Phone",
            "city" => "City",
            "country" => "Country",
            "notes" => "Notes",
            "total_mail_sent" => "Total Mails Sent",
            "total_mail_failed" => "Total Mails Failed",
            "total_mail_replied" => "Total Mails Replied",
            "total_mail_bounced" => "Total Mails Bounced",
            "total_link_clicks" => "Total Links Clicked",
            "total_document_viewed" => "Total Documents Viewed",
            "total_document_facetime" => "Document View Time",
            "status" => "Status",
            "is_blocked" => "Blocked",
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
            "account_companies" => ["account_companies AS ac", $this->_table_alias . ".account_company_id", "=", "ac.id", "INNER"],
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
     * Get contact id by email address
     *
     * @param $email (string): Email address
     * @param $account_id (integer): Account Id
     * @param $payload (array): Other data payload
     *
     * @return (integer|null) Contact id
     */
    public function getContactIdByEmail($email, $account_id, $payload = []) {
        $contact_id = null;

        $condition = [
            "fields" => [
                "aco.id",
                "aco.first_name",
                "aco.last_name",
                "aco.account_company_id",
                "aco.status",
            ],
            "where" => [
                ["where" => ["aco.account_id", "=", $account_id]],
                ["where" => ["aco.email", "=", $email]]
            ]
        ];
        $row = $this->fetch($condition);

        if (!empty($row["id"])) {
            $contact_id = $row["id"];
            $saved_name = trim($row["first_name"] . " " . $row["last_name"]);
            $saved_company = $row["account_company_id"];
            $save_data = [];

            // If contact exists but without name and now name is available then store it
            if ((isset($payload["first_name"]) || isset($payload["last_name"])) && empty($saved_name)) {
                $save_data["first_name"] = isset($payload["first_name"]) ? $payload["first_name"] : "";
                $save_data["last_name"] = isset($payload["last_name"]) ? $payload["last_name"] : "";
            }
            // If company is not assigned and now company is provided then store it
            if (!empty($payload["company_id"]) && empty($saved_company)) {
                $save_data["account_company_id"] = (int) $payload["company_id"];
            }

            if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
                $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
            }
            // Save data if any change required
            if (!empty($save_data)) {
                $save_data["id"] = $contact_id;

                try {
                    $this->save($save_data);

                } catch(\Exeption $e) { }
            }

        } else {
            try {
                $save_data = [
                    "email" => $email,
                    "account_id" => $account_id,
                    "first_name" => isset($payload["first_name"]) ? $payload["first_name"] : "",
                    "last_name" => isset($payload["last_name"]) ? $payload["last_name"] : "",
                    "phone" => isset($payload["phone"]) ? $payload["phone"] : "",
                    "city" => "",
                    "country" => "",
                    "notes" => ""
                ];
                if (!empty($payload["company_id"])) {
                    $save_data["account_company_id"] = (int) $payload["company_id"];
                }
                if ($this->save($save_data)) {
                    return $this->getLastInsertId();
                }

            } catch(\Exeption $e) { }
        }

        return $contact_id;
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
            $tables = "FROM account_contacts ac";

            // Fetching conditions and order
            $condition = "WHERE ac.id = " . $id . " AND ac.account_id = " . $payload["account_id"] . " AND ac.status <> " . $payload["deleted"];

            // Fetch total records
            $valid_row_sql = "SELECT ac.id " . $tables . " " . $condition;
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
            "ac.id",
            "ac.first_name",
            "ac.last_name",
            "ac.email",
            "ac.city",
            "ac.country",
            "ac.phone",
            "ac.notes",
            "ac.total_mail_sent",
            "ac.total_mail_failed",
            "ac.total_mail_replied",
            "ac.total_mail_bounced",
            "ac.total_link_clicks",
            "ac.total_document_viewed",
            "ac.total_document_facetime",
            "ac.status",
            "ac.is_blocked",
            "acm.name",
            "(ac.total_mail_sent + ac.total_mail_failed) AS opend_mail"
        ];

        // Fetch from tables
        $tables = "FROM account_contacts ac LEFT JOIN account_companies acm ON ac.account_company_id = acm.id";

        // Fetching conditions and order
        $condition = "WHERE ac.account_id = " . $payload["account_id"] . " AND ac.status <> " . $payload["deleted"];

        if (isset($query_params["query"])) {
            $search_condition = [
                "ac.first_name LIKE '%" . $query_params["query"] . "%'",
                "ac.last_name LIKE '%" . $query_params["query"] . "%'",
                "ac.email LIKE '%" . $query_params["query"] . "%'",
                "acm.name LIKE '%" . $query_params["query"] . "%'",
            ];

            $condition .= " AND (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        $sort_condition = "";
        switch ($query_params["order_by"]) {
            case "name":
                $sort_condition .= " ORDER BY ac.first_name " . $query_params["order"];
                break;

            case "email":
                $sort_condition .= " ORDER BY ac.email " . $query_params["order"];
                break;

            case "company":
                $sort_condition .= " ORDER BY acm.name " . $query_params["order"];
                break;

            case "mail_sent":
                $sort_condition .= " ORDER BY ac.total_mail_sent " . $query_params["order"];
                break;

            case "mail_opened":
                $sort_condition .= " ORDER BY opend_mail " . $query_params["order"];
                break;

            case "link_clicked":
                $sort_condition .= " ORDER BY ac.total_link_clicks " . $query_params["order"];
                break;

            case "reply":
                $sort_condition .= " ORDER BY ac.total_mail_replied " . $query_params["order"];
                break;

            case "document_view":
                $sort_condition .= " ORDER BY ac.total_document_facetime " . $query_params["order"];
                break;

            default: 
                $sort_condition .= " ORDER BY ac.id DESC";
                break;
        }

        try {
            // Fetch total records
            $count_sql = "SELECT COUNT(ac.id) " . $tables . " " . $condition;
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

            $sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition . " " . $sort_condition . " " . $limit;
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

    /**
     * Get list of records by company
     * @param $query_params (array): Query parameters
     * @param $payload (array): Payload of other required information
     *
     * @return (array) Array of records
     */
    public function  getListDataByCompany($query_params, $payload = []) {
        $data = [];

        // Fetch fields
        $fields = [
            "ac.id",
            "ac.email",
            "ac.first_name",
            "ac.last_name",
            "ac.total_mail_sent",
            "ac.total_mail_failed",
            "ac.total_mail_replied",
            "ac.total_mail_bounced",
            "ac.total_link_clicks",
            "ac.total_document_viewed",
            "ac.total_document_facetime",
            "ac.status"
        ];

        // Fetch from tables
        $tables = "FROM account_contacts ac";

        // Fetching conditions and order
        $condition = "WHERE ac.account_company_id = " . $payload["account_company_id"] . " AND ac.status <> " . $payload["deleted"];

        // Add Order by
        switch ($query_params["order_by"]) {
            case "name":
                $condition .= " ORDER BY ac.first_name " . $query_params["order"];
                break;

            default: 
                $condition .= " ORDER BY ac.id DESC";
                break;
        }

        try {
            $sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition;
            $this->_query_string = $sql;

            $stmt = $this->_db->query($sql);
            foreach ($stmt as $row) {
                $data["rows"][] = $row;
            }

            $stmt = null;

        } catch(\PDOException $e) {
            $this->_failes_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $data;
    }

    /**
     * Get data of single record of conatct details
     * @param $query_params (array): Query parameters
     * @param $payload (array): Payload of other required information
     *
     * @return (array) Array of records
     */
    public function  getConatctDataById($id, $payload = []) {
        $data = [];

        $condition = [
            "fields" => [
                "aco.id",
                "aco.first_name",
                "aco.last_name",
                "aco.email"
            ],
            "where" => [
                ["where" => ["aco.id", "=", $id]],
                ["where" => ["aco.status", "<>", $payload["deleted"]]]
            ]
        ];
        
        try {
            $row = $this->fetch($condition);
            if(count($row) > 0){
                $data = $row;
            }
        
        } catch(\PDOException $e) {
            $this->_failes_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }
        return $data;
    }

}
