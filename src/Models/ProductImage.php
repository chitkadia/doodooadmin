<?php
/**
 * Model file for operations on account_billing_master table
 */
namespace App\Models;

class ProductImage extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "product_image";
        $this->_table_alias = "pi";
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
            "product_id" => "Product Id",
            "image_name" => "Image Name",
            "image_path" => "Image Path",
            "image_extension" => "Image Extension",
            "is_default" => "Is Default",
            "status" => "Status",
            "created_date" => "Created Date",
            "modified_date" => "Modified Date"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "product_category" => ["product_category AS pc", $this->_table_alias . ".product_category_id", "=", "pc.id", "INNER"]
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
            "id",
            "product_id",
            "image_name",
            "image_path",
            "image_extension",
            "is_default",
            "status",
            "created_date",
            "modified_date"
        ];

        // Fetch from tables
        $tables = "FROM " . $this->_table . " AS " . $this->_table_alias;

        // Fetching conditions and order
        $condition = "WHERE status <> " . $payload["deleted"];

        // Add filters
        
        if (isset($query_params["query"])) {
            $search_condition = [
                "image_name LIKE '%" . $query_params["query"] . "%'"
            ];

            $condition .= " HAVING (" . implode(" OR ", $search_condition) . ")";
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "image_name":
                $order_by = " ORDER BY image_name " . $query_params["order"];
                break;

            default:
                $order_by = " ORDER BY id DESC";
                break;
        }

        try {
            // Fetch total records
            $count_sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition;
            $this->_query_string = $count_sql;

            $number_of_rows = $this->_db->query($count_sql)->rowCount();
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
            $data["rows"] = [];
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
            $tables = "FROM " . $this->_table;

            // Fetching conditions and order
            $condition = "WHERE id = " . $id . " AND status <> " . $payload["deleted"];

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
     * Check row validity (has user access to this row or not)
     *
     * @param $id (integer): Id of the record
     * @param $payload (array): Payload of other required information
     *
     * @return (boolean) Row valid or not
     */
    public function checkRowValidityImage($id, $payload = []) {
        $valid = false;

        if (empty($id) || empty($payload)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM " . $this->_table;

            // Fetching conditions and order
            $condition = "WHERE id = " . $id . " AND product_id = " . $payload["product_id"] . " AND status <> " . $payload["deleted"];

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
    
}