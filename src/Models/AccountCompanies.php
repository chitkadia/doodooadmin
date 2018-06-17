<?php
/**
 * Model file for operations on account_companies table
 */
namespace App\Models;

class AccountCompanies extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "account_companies";
        $this->_table_alias = "ac";
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
            "source_id" => "Source",
            "name" => "Name",
            "address" => "Address",
            "city" => "City",
            "state" => "State",
            "country" => "Country",
            "zipcode" => "Zipcode",
            "logo" => "Logo",
            "website" => "Website",
            "contact_phone" => "Phone",
            "contact_fax" => "Fax",
            "short_info" => "Short Description",
            "total_mail_sent" => "Total Mails Sent",
            "total_mail_failed" => "Total Mails Failed",
            "total_mail_replied" => "Total Mails Replied",
            "total_mail_bounced" => "Total Mails Bounced",
            "total_link_clicks" => "Total Link Clicks",
            "total_document_viewed" => "Total Documents Viewed",
            "total_document_facetime" => "Document View Time",
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
     * Get company id by name
     *
     * @param $name (string): Name
     * @param $account_id (integer): Account Id
     *
     * @return (integer|null) Company id
     */
    public function getCompanyIdByName($name, $account_id, $payload = []) {
        $company_id = null;

        $condition = [
            "fields" => [
                "ac.id"
            ],
            "where" => [
                ["where" => ["ac.account_id", "=", $account_id]],
                ["where" => ["ac.name", "=", trim($name)]]
            ]
        ];
        $row = $this->fetch($condition);

        if (!empty($row["id"])) {
            $company_id = $row["id"];
            
        } else {
            try {
                $save_data = [
                    "name" => trim($name),
                    "account_id" => $account_id,
                    "address" => "",
                    "city" => "",
                    "state" => "",
                    "country" => "",
                    "zipcode" => "",
                    "logo" => "",
                    "website" => "",
                    "contact_phone" => "",
                    "contact_fax" => "",
                    "short_info" => ""
                ];
                if ($this->save($save_data)) {
                    return $this->getLastInsertId();
                }

            } catch(\Exeption $e) { }
        }

        return $company_id;
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
            $tables = "FROM account_companies ac";

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
            "ac.name",
            "ac.contact_phone",
            "ac.website",
            "ac.city",
            "ac.total_mail_sent",
            "ac.total_mail_failed",
            "ac.total_mail_replied",
            "ac.total_mail_bounced",
            "ac.total_link_clicks",
            "ac.total_document_viewed",
            "ac.total_document_facetime",
            "ac.status",
            "(SELECT COUNT(acn.id) FROM account_contacts acn WHERE ac.id = acn.account_company_id and acn.status <> " . $payload["deleted"] . ") as contacts"
        ];

        // Fetch from tables
        $tables = "FROM account_companies ac";

        // Fetching conditions and order
        $condition = "WHERE ac.account_id = " . $payload["account_id"] . " AND ac.status <> " . $payload["deleted"];

        if (isset($query_params["query"])) {
            $search_condition = [
                "ac.name LIKE '%" . $query_params["query"] . "%'",
                "ac.address LIKE '%" . $query_params["query"] . "%'",
                "ac.website LIKE '%" . $query_params["query"] . "%'",
                "ac.city LIKE '%" . $query_params["query"] . "%'",
            ];

            $condition .= " AND (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "name":
                $condition_orderby = " ORDER BY ac.name " . $query_params["order"];
                break;

            case "website":
                $condition_orderby = " ORDER BY ac.website " . $query_params["order"];
                break;

            case "phone":
                $condition_orderby = " ORDER BY ac.contact_phone " . $query_params["order"];
                break;

            case "city":
                $condition_orderby = " ORDER BY ac.city " . $query_params["order"];
                break;

            default: 
                $condition_orderby = " ORDER BY ac.id DESC";
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

            $sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition . " " . $condition_orderby . " " . $limit;
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

}