<?php
/**
 * Model file for operations on valid_plan_coupons table
 */
namespace App\Models;

class ValidPlanCoupons extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "valid_plan_coupons";
        $this->_table_alias = "vpc";
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
            "plan_id" => "Plan",
            "coupon_id" => "Coupon",
            "recurring_validity" => "Recurring Validity",
            "status" => "Status",
            "modified" => "Modified At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "plan_master" => ["plan_master AS pm", $this->_table_alias . ".plan_id", "=", "pm.id", "INNER"],
            "coupon_master" => ["coupon_master AS cm", $this->_table_alias . ".coupon_id", "=", "cm.id", "INNER"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }

}