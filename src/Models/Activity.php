<?php
/**
 * Model file for operations on activity table
 */
namespace App\Models;

use \App\Components\SHAppComponent;

class Activity extends AppModel {

    // Initialize model
    use ModelInitialize;

    /**
     * @override
     */
    protected function _setTable() {
        $this->_table = "activity";
        $this->_table_alias = "a";
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
            "user_id" => "User",
            "account_contact_id" => "Contact",
            "record_id" => "Row",
            "sub_record_id" => "Sub Record Id",
            "action_id" => "Action",
            "action_group_id" => "Action Group",
            "other_data" => "Other Information",
            "created" => "Created At"
        ];
    }

    /**
     * @override
     */
    protected function _setJoins() {
        $this->_joins = [
            "account_master" => ["account_master AS am", $this->_table_alias . ".account_id", "=", "am.id", "INNER"],
            "user_master" => ["user_master AS um", $this->_table_alias . ".user_id", "=", "um.id", "INNER"],
            "source_master" => ["source_master AS sm", $this->_table_alias . ".source_id", "=", "sm.id", "INNER"],
            "action_master" => ["action_master AS ams", $this->_table_alias . ".action_id", "=", "ams.id", "INNER"]
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

        $fields = [
            "a.id",
            "a.user_id",
            "a.account_contact_id",
            "a.record_id",
            "a.sub_record_id",
            "a.action_id",
            "a.action_group_id",
            "a.other_data",
            "a.created",
            "um.first_name as user_firstname",
            "um.last_name as user_lastname",
            "um.email as user_email",
            "ac.first_name as contact_firstname",
            "ac.last_name as contact_lastname",
            "ac.email as contact_email"
        ];

        $action_group = 0;

        if (!empty($query_params["action_group"])) {
            $action_group = (int) $query_params["action_group"];
        }

        $condition = " WHERE a.account_id = " . (int) $payload["account_id"];
                
        $condition_user = "";
        if(!empty($query_params["user"])) {
            $condition_user .= " AND um.id = " . (int) $query_params["user"];
        } else {
            $condition_user .= " AND um.id = " . (int) $payload["user_id"];
        }

        $condition_action = "";
        if(!empty($query_params["action"])) {
            $condition_action .= " AND a.action_id = " . (int) $query_params["action"]; 
        }

        $email_search = "";
        $campaign_search = "";
        $document_link_search = "";

        if (!empty($query_params["query"])) {
            $search_condition = [
                "um.first_name LIKE '%" . $query_params["query"] . "%'",
                "um.last_name LIKE '%" . $query_params["query"] . "%'",
                "ac.email LIKE '%" . $query_params["query"] . "%'"
            ];

            if ($action_group == SHAppComponent::getValue("actions/EMAILS/ACTION_GROUP_ID")) {
                $search_condition[] = "em.subject LIKE '%" . $query_params["query"] . "%'";
                $email_search .= " AND (" . implode(" OR ", $search_condition) . ")";
            }
            else if ($action_group == SHAppComponent::getValue("actions/CAMPAIGNS/ACTION_GROUP_ID")) {
                $search_condition[] = "cm.title LIKE '%" . $query_params["query"] . "%'";
                $campaign_search .= " AND (" . implode(" OR ", $search_condition) . ")";
            }
            else if ($action_group == SHAppComponent::getValue("actions/DOCUMENT_LINKS/ACTION_GROUP_ID")) {
                $search_condition[] = "dl.name LIKE '%" . $query_params["query"] . "%'";
                $document_link_search .= " AND (" . implode(" OR ", $search_condition) . ")";
            }

            else if ($action_group == 0) {
                $email_condition = [
                    "em.subject LIKE '%" . $query_params["query"] . "%'"
                ];
                $email_search .= " AND (" . implode(" OR ", array_merge($search_condition, $email_condition)) . ")";

                $campaign_condition = [
                    "cm.title LIKE '%" . $query_params["query"] . "%'"
                ];
                $campaign_search .= " AND (" . implode(" OR ", array_merge($search_condition, $campaign_condition)) . ")";

                $document_link_condition = [
                    "dl.name LIKE '%" . $query_params["query"] . "%'"
                ];
                $document_link_search .= " AND (" . implode(" OR ", array_merge($search_condition, $document_link_condition)) . ")";
            }
        }
                     
        switch($action_group) {

            case SHAppComponent::getValue("actions/EMAILS/ACTION_GROUP_ID") :
                $count_activity_sql = "select COUNT(a.id) from activity a
                INNER JOIN user_master um ON a.user_id = um.id" . $condition_user ." 
                LEFT JOIN account_contacts ac on a.account_contact_id = ac.id
                INNER JOIN email_master em on a.record_id = em.id
                AND em.progress <> " . SHAppComponent::getValue("app_constant/PROGRESS_FAILED") . "
                AND em.status <> " . SHAppComponent::getValue("app_constant/STATUS_DELETE") . $email_search;

                $condition .= " AND a.action_group_id = " . $action_group;
                $condition .= " " .$condition_action;
                                
                $get_activity_sql = "select " . implode(", ", $fields) . ", em.subject as entity from activity a
                INNER JOIN user_master um ON a.user_id = um.id ". $condition_user ." 
                LEFT JOIN account_contacts ac on a.account_contact_id = ac.id
                INNER JOIN email_master em on a.record_id = em.id
                AND em.progress <> " . SHAppComponent::getValue("app_constant/PROGRESS_FAILED") . "
                AND em.status <> " . SHAppComponent::getValue("app_constant/STATUS_DELETE") . $email_search; 

                $count_activity_sql .= " " . $condition;
                $get_activity_sql .= " " . $condition;

                break;

            case SHAppComponent::getValue("actions/CAMPAIGNS/ACTION_GROUP_ID") :

                $count_activity_sql = "select COUNT(a.id) from activity a
                INNER JOIN user_master um ON a.user_id = um.id" . $condition_user ." 
                INNER JOIN account_contacts ac on a.account_contact_id = ac.id
                INNER JOIN campaign_stages cs on a.record_id = cs.id
                INNER JOIN campaign_master cm on cs.campaign_id = cm.id
                AND cm.status <> " . SHAppComponent::getValue("app_constant/STATUS_DELETE") . $campaign_search;

                $condition .= " AND a.action_group_id = " . $action_group;
                $condition .= " " .$condition_action;
                                
                $get_activity_sql = "select " . implode(", ", $fields) . ", cm.title as entity, cs.stage as stage from activity a
                INNER JOIN user_master um ON a.user_id = um.id ". $condition_user ." 
                INNER JOIN account_contacts ac on a.account_contact_id = ac.id
                INNER JOIN campaign_stages cs on a.record_id = cs.id
                INNER JOIN campaign_master cm on cs.campaign_id = cm.id
                AND cm.status <> " . SHAppComponent::getValue("app_constant/STATUS_DELETE") . $campaign_search; 

                $count_activity_sql .= " " . $condition;
                $get_activity_sql .= " " . $condition;
                
                break;
            case SHAppComponent::getValue("actions/DOCUMENT_LINKS/ACTION_GROUP_ID") :
                
                $count_activity_sql = "select COUNT(a.id) from activity a
                INNER JOIN user_master um ON a.user_id = um.id" . $condition_user ." 
                LEFT JOIN account_contacts ac on a.account_contact_id = ac.id
                INNER JOIN document_links dl on a.record_id = dl.id
                AND dl.status <> " . SHAppComponent::getValue("app_constant/STATUS_DELETE") . $document_link_search;

                $condition .= " AND a.action_group_id = " . $action_group;
                $condition .= " " .$condition_action;
                                
                $get_activity_sql = "select " . implode(", ", $fields) . ", dl.name as entity from activity a
                INNER JOIN user_master um ON a.user_id = um.id ". $condition_user ." 
                LEFT JOIN account_contacts ac on a.account_contact_id = ac.id
                INNER JOIN document_links dl on a.record_id = dl.id
                AND dl.status <> " . SHAppComponent::getValue("app_constant/STATUS_DELETE") . $document_link_search; 

                $count_activity_sql .= " " . $condition;
                $get_activity_sql .= " " . $condition;

                break;
                
            default :
                $condition .= " " .$condition_action;
               
                $count_activity_sql = "
                select SUM(cnt) from (select COUNT(a.id) as cnt from activity a 
                    INNER JOIN user_master um ON a.user_id = um.id ". $condition_user ."
                    LEFT JOIN account_contacts ac on a.account_contact_id = ac.id
                    INNER JOIN email_master em on a.record_id = em.id
                    AND em.progress <> " . SHAppComponent::getValue("app_constant/PROGRESS_FAILED") . "
                    AND em.status <> " . SHAppComponent::getValue("app_constant/STATUS_DELETE") . $email_search . $condition . "
                    AND a.action_group_id = " . SHAppComponent::getValue("actions/EMAILS/ACTION_GROUP_ID") ."
                    UNION 
                    select COUNT(a.id) as cnt from activity a 
                    INNER JOIN user_master um ON a.user_id = um.id ". $condition_user ."
                    INNER JOIN account_contacts ac on a.account_contact_id = ac.id
                    INNER JOIN campaign_stages cs on a.record_id = cs.id
                    INNER JOIN campaign_master cm on cs.campaign_id = cm.id
                    AND cm.status <> " . SHAppComponent::getValue("app_constant/STATUS_DELETE") . $campaign_search . $condition . " 
                    AND a.action_group_id = " . SHAppComponent::getValue("actions/CAMPAIGNS/ACTION_GROUP_ID") ."
                    UNION
                    select COUNT(a.id) as cnt from activity a 
                    INNER JOIN user_master um ON a.user_id = um.id ". $condition_user ."
                    LEFT JOIN account_contacts ac on a.account_contact_id = ac.id
                    INNER JOIN document_links dl on a.record_id = dl.id
                    AND dl.status <> " . SHAppComponent::getValue("app_constant/STATUS_DELETE") . $document_link_search . $condition . "
                    AND a.action_group_id = " . SHAppComponent::getValue("actions/DOCUMENT_LINKS/ACTION_GROUP_ID") ."
                ) count_activity";
                
                $get_activity_sql = "
                select " . implode(", ", $fields) . ", em.subject as entity, '' as stage from activity a
                INNER JOIN user_master um ON a.user_id = um.id" . $condition_user ." 
                LEFT JOIN account_contacts ac on a.account_contact_id = ac.id
                INNER JOIN email_master em on a.record_id = em.id
                AND em.progress <> " . SHAppComponent::getValue("app_constant/PROGRESS_FAILED") . "
                AND em.status <> " . SHAppComponent::getValue("app_constant/STATUS_DELETE") . $email_search . $condition . "
                AND a.action_group_id = " . SHAppComponent::getValue("actions/EMAILS/ACTION_GROUP_ID") ."
                UNION 
                select " . implode(", ", $fields) . ", cm.title as entity, cs.stage as stage from activity a 
                INNER JOIN user_master um ON a.user_id = um.id ". $condition_user ." 
                INNER JOIN account_contacts ac on a.account_contact_id = ac.id
                INNER JOIN campaign_stages cs on a.record_id = cs.id
                INNER JOIN campaign_master cm on cs.campaign_id = cm.id
                AND cm.status <> " . SHAppComponent::getValue("app_constant/STATUS_DELETE") . $campaign_search . $condition . "
                AND a.action_group_id = " . SHAppComponent::getValue("actions/CAMPAIGNS/ACTION_GROUP_ID") . "
                UNION
                select " . implode(", ", $fields) . ", dl.name as entity, '' as stage from activity a
                INNER JOIN user_master um ON a.user_id = um.id" . $condition_user ." 
                LEFT JOIN account_contacts ac on a.account_contact_id = ac.id
                INNER JOIN document_links dl on a.record_id = dl.id
                AND dl.status <> " . SHAppComponent::getValue("app_constant/STATUS_DELETE") . $document_link_search . $condition . "
                AND a.action_group_id = " . SHAppComponent::getValue("actions/DOCUMENT_LINKS/ACTION_GROUP_ID");
                break;
        }
        $get_activity_sql .= " ORDER BY created DESC";
        
        try {
            //Fetch total records
            $this->_query_string = $count_activity_sql;

            $number_of_rows = $this->_db->query($count_activity_sql)->fetchColumn();
            
            $total_pages = ceil($number_of_rows / $query_params["per_page"]);
            
            // If page doesn't exists, then get first page data
            if ($query_params["page"] > $total_pages) {
                $query_params["page"] = 1;
            }
            $offset = ($query_params["page"] - 1) * $query_params["per_page"];

            // Add limit
            $limit = "LIMIT " . $offset . ", " . $query_params["per_page"];

            $get_activity_sql .= " " .$limit;
            $this->_query_string = $get_activity_sql;

            $stmt = $this->_db->query($get_activity_sql);
            $data["rows"] = [];

            foreach ($stmt as $row) {
                $data["rows"][] = $row;
            }

            $data["total_records"] = $number_of_rows;
            $data["total_pages"] = $total_pages;
            $data["current_page"] = $query_params["page"];
            $data["per_page"] = $query_params["per_page"];

            $stmt = null;

        } catch (\Exception $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;
            throw new \Exception($this->_failed_query_message);
        }
        return $data;
    }

}