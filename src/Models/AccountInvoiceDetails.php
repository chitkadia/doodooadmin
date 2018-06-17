<?php
/**
 * Model file for operations on account_invoice_details table
 */
namespace App\Models;

class AccountInvoiceDetails extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "account_invoice_details";
        $this->_table_alias = "aid";
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
            "invoice_number" => "Number",
            "account_id" => "Account",
            "account_subscription_id" => "Subscription",
            "account_payment_id" => "Paid Through",
            "currency" => "Currency",
            "amount" => "Amount",
            "discount_amount" => "Discount Amount",
            "credit_amount" => "Credit Amount",
            "total_amount" => "Total Amount",
            "file_copy" => "File Path",
            "created" => "Created At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "account_master" => ["account_master AS am", $this->_table_alias . ".account_id", "=", "am.id", "INNER"],
            "account_subscription_details" => ["account_subscription_details AS asd", $this->_table_alias . ".account_subscription_id", "=", "asd.id", "INNER"],
            "payment_method_master" => ["payment_method_master AS pmm", $this->_table_alias . ".payment_method_id", "=", "pmm.id", "INNER"],
            "coupon_master" => ["coupon_master AS cm", "asd.coupon_id", "=", "cm.id", "LEFT"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }

    public function checkRowValidity($id, $payload = []) {
        $valid = false;

        if (empty($id) || empty($payload)) {
            return $valid;
        }

        try {
            // Fetch from tables
            $tables = "FROM account_invoice_details aid";

            // Fetching conditions and order
            $condition = "WHERE aid.id = " . $id . " AND aid.account_id = " . $payload["account_id"];

            // Fetch total records
            $valid_row_sql = "SELECT aid.id " . $tables . " " . $condition;
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