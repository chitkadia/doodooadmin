<?php
/**
 * Model file for operations on user_authentication_tokens table
 */
namespace App\Models;

class UserAuthenticationTokens extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "user_authentication_tokens";
        $this->_table_alias = "uat";
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
            "user_id" => "User",
            "source_id" => "Source",
            "token" => "Token",
            "generated_at" => "Generated At",
            "expires_at" => "Expired At",
            "user_resources" => "Resources"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "user_master" => ["user_master AS um", $this->_table_alias . ".user_id", "=", "um.id", "INNER"],
            "active_plan_details" => ["active_plan_details AS apd", "apd.user_id", "=", "um.id", "LEFT"]
        ];
    }

    /**
     * @override
     */
    protected function _setValidations() {
        $this->_validations = [];
    }


    /**
     * Expire currently acitve member's auth token
     *
     * @param $ids (int): array(ID)
     *
     * @param $account_id (int): ID
     *
     * @return Boolean : True or False
     */
    public function memberTokenExpire($ids = array(),$account_id,$current_time) {
        $data = array();

        if (!empty($ids) && !empty($account_id)) {

            foreach ($ids as $value) {
                
                $fields = [
                    "id",
                    "expires_at"
                ];

                $table = "FROM user_authentication_tokens WHERE user_id = ".$value["id"];

                try {
                    $sql = "SELECT " . implode(", ", $fields) . " " . $table;
                    
                    $this->_query_string = $sql;
                        
                    $stm = $this->_db->query($sql);

                    foreach ($stm as $row) {
                        $data = $row;
                    }

                    if (!empty($data)) {

                        if ($current_time <= $data["expires_at"]) {
                            
                             $condition = [
                                "where" => [
                                    ["where" => ["user_id", "=", $value['id']]],
                                ]
                            ];
                            $save_data = [
                                "expires_at" => ($current_time - 2),
                                "modified" => $current_time
                            ];
                            $affect_rows = $this->update($save_data, $condition);
                        }
                    }

                } catch(\PDOException $e) {
                        $this->_failed_query_message = $e->getMessage();
                        $this->_query_error = true;
                    throw new \Exception($this->_failed_query_message);
                }
            }
        } 

        return true;
    }


    /**
     * Expire single member's auth token
     *
     * @param $id (int): ID
     *
     * @param $account_id (int): ID
     *
     * @return Boolean : True or False
     */
    public function singleMemberTokenExpire($id,$account_id,$current_time) {
        $data = array();

        if (!empty($id) && !empty($account_id)) {

            $fields = [
                "id",
                "expires_at"
            ];
            
            $table = "FROM user_authentication_tokens WHERE user_id = ".$id;

            try {
                $sql = "SELECT " . implode(", ", $fields) . " " . $table;
                
                $this->_query_string = $sql;
                    
                $stm = $this->_db->query($sql);

                foreach ($stm as $row) {
                    $data = $row;
                }

                if (!empty($data)) {

                    if ($current_time <= $data["expires_at"]) {
                        
                         $condition = [
                            "where" => [
                                ["where" => ["user_id", "=", $id]],
                                ["where" => ["id", "=", $data['id']]]
                            ]
                        ];
                        $save_data = [
                            "expires_at" => ($current_time - 2),
                            "modified" => $current_time
                        ];
                        $affect_rows = $this->update($save_data, $condition);
                        
                        if ($affect_rows) {
                            return true;
                        } else {
                            return false;
                        }
                    }
                }

            } catch(\PDOException $e) {
                    $this->_failed_query_message = $e->getMessage();
                    $this->_query_error = true;
                throw new \Exception($this->_failed_query_message);
            }
        }
    } 
}