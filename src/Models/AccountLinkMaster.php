<?php
/**
 * Model file for operations on account_link_master table
 */
namespace App\Models;

use \App\Components\DateTimeComponent;

class AccountLinkMaster extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "account_link_master";
        $this->_table_alias = "alm";
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
            "url" => "URL",
            "total_clicked" => "Total Clicks",
            "last_clicked" => "Last Clicked At",
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
            "account_master" => ["account_master AS am", $this->_table_alias . ".account_id", "=", "am.id", "INNER"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }

    /**
     * Get link id by url
     *
     * @param $url (string): URL
     * @param $account_id (integer): Account Id
     *
     * @return (integer|null) Link id
     */
    public function getLinkIdByUrl($url, $account_id) {
        $link_id = null;

        $condition = [
            "fields" => [
                "alm.id"
            ],
            "where" => [
                ["where" => ["alm.account_id", "=", $account_id]],
                ["where" => ["alm.url", "=", $url]]
            ]
        ];
        $row = $this->fetch($condition);

        if (!empty($row["id"])) {
            $link_id = $row["id"];
        } else {
            try {
                $save_data = [
                    "url" => $url,
                    "account_id" => $account_id,
                    "created" => DateTimeComponent::getDateTime(),
                    "modified" => DateTimeComponent::getDateTime()
                ];
                if ($this->save($save_data)) {
                    $link_id = $this->getLastInsertId();
                }

            } catch(\Exeption $e) { }
        }

        return $link_id;
    }

}