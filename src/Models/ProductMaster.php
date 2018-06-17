<?php
/**
 * Model file for operations on account_billing_master table
 */
namespace App\Models;

class ProductMaster extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "product_master";
        $this->_table_alias = "pm";
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
            "product_category_id" => "Product Category Id",
            "product_sub_category_id" => "Product Sub Category Id",
            "age_category_id" => "Age Category Id",
            "product_name" => "Product Name",
            "product_price" => "Product Price",
            "product_short_description" => "Product Short Description",
            "product_description" => "Product Description",
            "product_amazon_link" => "Product Amazon Link",
            "status" => "Status",
            "created_date" => "Created Date",
            "modified_date" => "Modified Date"
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
            "pm.id",
            "pm.product_category_id",
            "pm.product_sub_category_id",
            "pm.age_category_id",
            "pm.product_name",
            "pm.product_price",
            "pm.product_short_description",
            "pm.product_description",
            "pm.product_amazon_link",
            "pm.status",
            "pm.created_date",
            "pm.modified_date",
            "pc.category_name",
            "ag.age_category_name"
        ];

        // Fetch from tables
        $tables = "FROM " . $this->_table . " AS " . $this->_table_alias . " INNER JOIN product_category AS pc ON pm.product_category_id = pc.id INNER JOIN age_category AS ag ON ag.id = pm.age_category_id";

        // Fetching conditions and order
        $condition = "WHERE pm.status <> " . $payload["deleted"];

        // Add filters
        
        if (isset($query_params["query"])) {
            $search_condition = [
                "pm.product_name LIKE '%" . $query_params["query"] . "%'"
            ];

            $condition .= " HAVING (" . implode(" OR ", $search_condition) . ")";
        }

        if (isset($query_params["category"])) {
            $condition .= " AND pm.product_category_id = " . ($query_params["category"]);
        }

        if (isset($query_params["age_cat"])) {
            $condition .= " AND pm.age_category_id = " . ($query_params["age_cat"]);
        }
        if (isset($query_params["sub_cat"])) {
            $condition .= " AND pm.product_sub_category_id = " . ($query_params["sub_cat"]);
        }

        // Add Order by
        switch ($query_params["order_by"]) {
            case "pm.product_name":
                $order_by = " ORDER BY pm.product_name " . $query_params["order"];
                break;

            default:
                $order_by = " ORDER BY pm.id DESC";
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

    public function getSubCategoryList($conditions) {
        $data = [];

        // Fetch fields
        $fields = [
            "id",
            "sub_category_name"
        ];

        // Fetch from tables
        $tables = "FROM product_sub_category";

        // Fetching conditions and order
        $condition = "WHERE id IN (" . $conditions["ids"] . ")";

        $sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition;
        $this->_query_string = $sql;
        $data = [];
        $stmt = $this->_db->query($sql);
        foreach ($stmt as $row) {
            $data[] = $row;
        }

        $stmt = null;

        return $data;
    }

    public function getProdImageList($conditions) {
        $data = [];

        // Fetch fields
        $fields = [
            "id",
            "image_name",
            "image_path",
            "is_default"
        ];

        // Fetch from tables
        $tables = "FROM product_image";

        // Fetching conditions and order
        $condition = "WHERE product_id = " . $conditions["product_id"];

        $sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition;
        $this->_query_string = $sql;
        $data = [];
        $stmt = $this->_db->query($sql);
        foreach ($stmt as $row) {
            $row["full_path"] = DOCUMENT_UPLOADED_DIRECTORY . $row["image_path"];
            $data[] = $row;
        }

        $stmt = null;

        return $data;
    }

    public function getProdImageDefault($conditions) {
        $data = [];

        // Fetch fields
        $fields = [
            "id",
            "image_name",
            "image_path"
        ];

        // Fetch from tables
        $tables = "FROM product_image";

        // Fetching conditions and order
        $condition = "WHERE product_id = " . $conditions["product_id"] . " AND is_default = 1";

        $sql = "SELECT " . implode(", ", $fields) . " " . $tables . " " . $condition;
        $this->_query_string = $sql;
        $data = [];
        $stmt = $this->_db->query($sql);
        foreach ($stmt as $row) {
            $row["full_path"] = DOCUMENT_UPLOADED_DIRECTORY_SHOW . $row["image_path"];
            $data = $row;
        }

        $stmt = null;

        return $data;
    }
    
}