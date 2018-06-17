<?php
/**
 * Model file for operations on campaign_links table
 */
namespace App\Models;

class CampaignLinks extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "campaign_links";
        $this->_table_alias = "cl";
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
            "campaign_id" => "Campaign",
            "campaign_stage_id" => "Campaign Stage",
            "campaign_sequence_id" => "Campaign Sequence",
            "account_link_id" => "Link",
            "redirect_key" => "Redirect Key",
            "total_clicked" => "Total Clicked",
            "last_clicked" => "Last Clicked At",
            "modified" => "Modified At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "account_master" => ["account_master AS am", $this->_table_alias . ".account_id", "=", "am.id", "INNER"],
            "campaign_master" => ["campaign_master AS cm", $this->_table_alias . ".campaign_id", "=", "cm.id", "INNER"],
            "campaign_stages" => ["campaign_stages AS cst", $this->_table_alias . ".campaign_stage_id", "=", "cst.id", "INNER"],
            "campaign_sequences" => ["campaign_sequences AS csq", $this->_table_alias . ".campaign_sequence_id", "=", "csq.id", "INNER"],
            "account_link_master" => ["account_link_master AS alm", $this->_table_alias . ".account_link_id", "=", "alm.id", "INNER"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }


    /**
     * Check if link is already exist or not based on that perform action
     *
     * To create link or just return redirect key
     *
     * @param $data_array (array): params array
     * 
     * @return (string) key of campaign link
     */
    public function checkRedirectKey ($data_array = []) {
        $check_link_record = null;
        if (isset($data_array) && !empty($data_array)) {
            
            $condition = [
                "fields" => [
                    "id",
                    "redirect_key"
                ],
                "where" => [
                    ["where" => ["account_link_id","=",$data_array["account_link_id"]]],
                    ["where" => ["campaign_sequence_id","=",$data_array["campaign_sequence_id"]]]
                ]
            ];

            $check_link_record = $this->fetchAll($condition);

            if (!empty($check_link_record[0]["redirect_key"])) {
                return $check_link_record[0]["redirect_key"];

            } else {
                // Save campaign link to map redirect key and url.
                $save_data = [
                    "account_id"            => $data_array["account_id"],
                    "campaign_id"           => $data_array["campaign_id"],
                    "campaign_stage_id"     => $data_array["campaign_stage_id"],
                    "campaign_sequence_id"  => $data_array["campaign_sequence_id"],
                    "account_link_id"       => $data_array["account_link_id"],
                    "redirect_key"          => $data_array["redirect_key"]
                ];
                $inserted_id = $this->save($save_data);  

                if (!empty($inserted_id))  {
                    return $data_array["redirect_key"];
                } 
            }
        }
    }
}