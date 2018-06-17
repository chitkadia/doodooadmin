<?php
/**
 * Used to generate application configuration variables file
 */
namespace App\Commands;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Interop\Container\ContainerInterface;
use \App\Components\DateTimeComponent;
use \App\Components\LoggerComponent;
use \App\Components\SHAppComponent;
use \App\Components\StringComponent;
use \App\Components\Mailer\GmailComponent;
use \App\Components\Mailer\SMTPComponent;
use \App\Components\Mailer\TransactionMailsComponent;
use \App\Models\CampaignMaster;
use \App\Models\CampaignStages;
use \App\Models\CampaignDomainBlocklist;
use \App\Models\CampaignSequences;
use \App\Models\CampaignLinks;
use \App\Models\CampaignLogs;
use \App\Models\AccountContacts;
use \App\Models\AccountCompanies;
use \App\Models\AccountLinkMaster;
use \App\Models\AccountSendingMethods;
use \App\Controllers\LinksController;
use \App\Models\UserMaster;

class CampaignCommand extends AppCommand {

    //define constant for queue process cron execute limt 
    const QUEUE_PROCESS_LIMIT_TIME              = 35; // no of seconds
    const QUEUE_PROCESS_LIMIT_RECORDS           = 100;
    const REPLY_CHECK_TIME_LIMIT                = 180; // no of seconds
    const FINAL_REPLY_CHECK_TIME_LIMIT          = 180; // no of seconds
    const RESET_INTERVAL_SECONDS                = 60 * 60;

    //define constant for queue prepare log file 
    const LOG_FILE_DIRECTORY                    = __DIR__ . "/../../logs";
    const LOG_FILE_QPREPARE                     = self::LOG_FILE_DIRECTORY . "/af_prepare_queue.log";
    const LOG_FILE_QPREPARE_BULK                = self::LOG_FILE_DIRECTORY . "/af_bulk_prepare_queue.log";
    const LOG_FILE_QPROCESS                     = self::LOG_FILE_DIRECTORY . "/af_process_queue.log";
    const LOG_FILE_QPROCESS_DYNA                = self::LOG_FILE_DIRECTORY . "/af_process_queue";
    const LOG_FILE_CHECK_REPLY                  = self::LOG_FILE_DIRECTORY . "/af_reply_check_queue.log";
    const LOG_FILE_CHECK_REPLY_DYNA             = self::LOG_FILE_DIRECTORY . "/af_reply_check_queue";
    const LOG_FILE_FINAL_CHECK_REPLY            = self::LOG_FILE_DIRECTORY . "/af_final_reply_check_queue.log";
    const LOG_FILE_PERFORMANCE_REPORT           = self::LOG_FILE_DIRECTORY . "/af_performance_report.log";
    const LOG_FILE_DOMAIN_BLOCK                 = self::LOG_FILE_DIRECTORY . "/af_domain_block.log";

    const VALID_EDIT_DURATION                   = 900;
    const VALID_EDIT_DURATION_TEXT              = "15 minutes";
    const CSV_EMAIL_FIELD                       = "Email";
    const CSV_FIRST_NAME_FIELD                  = "First Name";
    const CSV_LAST_NAME_FIELD                   = "Last Name";
    const CSV_COMPANY_FIELD                     = "Company";

    const SYSTEM_PAUSE_REASON_GMAIL             = 1;
    const SYSTEM_PAUSE_REASON_SMTP              = 2;
    const SYSTEM_PAUSE_REASON_FAIL              = 3;
    const SYSTEM_PAUSE_REASON_BOUNCE            = 4;
    const SYSTEM_PAUSE_REASON_GMAIL_AUTH        = 5;
    const SYSTEM_PAUSE_REASON_SMTP_AUTH         = 6;

    private $campaign_mail_info                 = [];
    private $is_bulk_campaign                   = 0;

    private $unique_email_sending_customer_id   = [29832]; // brendan@treatme.com
    private $customer_user_ids                  = [];

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Prepares a queue of pending campaigns
     */
    public function actionPrepareQueue(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $prepare_queue_log_file = self::LOG_FILE_QPREPARE;
        $domain_block_log_file = self::LOG_FILE_DOMAIN_BLOCK;
        if ($this->is_bulk_campaign == 1) {
            $prepare_queue_log_file = self::LOG_FILE_QPREPARE_BULK;
        }

        //Create log directory if not exist
        if (!is_dir(self::LOG_FILE_DIRECTORY)) {
            mkdir(self::LOG_FILE_DIRECTORY, 0777, true);
        }
        // Log queue prepare start
        LoggerComponent::log("Starting queue prepare", $prepare_queue_log_file);

        // Prepare duration window to fetch campaigns which are scheduled in next XX duration
        $duration_window = strtotime("now") + self::VALID_EDIT_DURATION;

        // Limit campaign processing
        $campaigns_limit = 8;
        if ($this->is_bulk_campaign == 1) {
            $campaigns_limit = 2;
        }
        
        // Set parameters
        $query_params = [
            "bulk_campaign"     => $this->is_bulk_campaign,
            "duration_window"   => $duration_window,
            "campaigns_limit"   => $campaigns_limit
        ];

        // Other values for condition
        $other_values = [
            "active"                => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
            "campaign_scheduled"    => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED"),
            "campaign_paused"       => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED"),
            "campaign_finished"       => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH")
        ];

        // Get campaigns which are ACTIVE, in QUEUE, are within DURATION
        $model_campaign_master      = new CampaignMaster();
        $model_campaign_stages      = new CampaignStages();
        $model_campaign_sequences   = new CampaignSequences();
        $model_account_contacts     = new AccountContacts();
        $model_account_company      = new AccountCompanies();
        $model_account_link_master  = new AccountLinkMaster();

        $rows_pending_campaigns = $model_campaign_master->getPendingCampaigns($query_params, $other_values);
        $number_of_records = count($rows_pending_campaigns);
        
        LoggerComponent::log("Total ".$number_of_records." campaigns found to process", $prepare_queue_log_file);

        // If campaign finished due to empty queue - send mails of finish
        $finished_campaigns = [];
        // Get array of id from pending campaigns
        $campaign_ids_for_lock = array_column($rows_pending_campaigns, 'id');
        // Get array of campaign_stage_id from pending campaigns
        $campaign_stage_ids_for_lock = array_column($rows_pending_campaigns, 'campaign_stage_id');        
        
        // Lock campaigns
        try {
            if ($number_of_records > 0) { 
                LoggerComponent::log("Locking campaign id # ".json_encode($campaign_ids_for_lock)." and campaign stage id ".json_encode($campaign_stage_ids_for_lock), $prepare_queue_log_file);
                
                $condition = [
                    "where" => [
                        ["whereIn" => ["id", $campaign_stage_ids_for_lock]]
                    ]
                ];
                $save_data = [
                    "locked" => 1
                ];  
                         
                $model_campaign_stages->update($save_data, $condition);
            }

        } catch(Exception $e) {            
            LoggerComponent::log("Could not lock campaign id # ".json_encode($campaign_ids_for_lock)." -- ".$e->getMessage(), $prepare_queue_log_file);
        }

        // Process campaigns
        for($i=0; $i<$number_of_records; $i++) {
          
            $campaign_id            = $rows_pending_campaigns[$i]["id"];
            $campaign_stage_id      = $rows_pending_campaigns[$i]["campaign_stage_id"];
            $account_id             = $rows_pending_campaigns[$i]["account_id"];
            $user_id                = $rows_pending_campaigns[$i]["user_id"];
            $other_data             = json_decode($rows_pending_campaigns[$i]["other_data"], true);
            $next_run_at            = $rows_pending_campaigns[$i]["scheduled_on"];
            $current_stage          = $rows_pending_campaigns[$i]["stage"];
            $stage_defination       = json_decode($rows_pending_campaigns[$i]["stage_defination"], true);
            $campaign_content       = $rows_pending_campaigns[$i]["content"];
            $campaign_user_id       = $rows_pending_campaigns[$i]["user_id"];
            $campaign_title         = $rows_pending_campaigns[$i]["title"];
            $track_link_click       = $rows_pending_campaigns[$i]["track_click"];
            $num_contacts_prepared  = 0;
            $num_contacts_deleted   = 0;
            $filelink_url_arry      = "";

            //Fetch external link from mail content for link track if any
            $regex = '/(href=")([^{#].*?)"/i';
            preg_match_all($regex,$campaign_content, $email_urls);

            // check if any contacts to be ignored
            if(empty($stage_defination['ignore'])) {
                $stage_defination['ignore'] = [];
            }

            // ------ Create file links for embeded documents [END]
            $update_campaign_progress = array(
                "content"           => $campaign_content,
                "progress"          => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_QUEUED"),
                "overall_progress"  => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_QUEUED"),
                "total_contacts"    => 0,
                "started_on"        => $rows_pending_campaigns[$i]["scheduled_on"],
                "modified"          => DateTimeComponent::getDateTime()
            );

            $log_activity = array(
                "campaign_id"       => $campaign_id,
                "campaign_stage_id" => $campaign_stage_id,
                "log"               => "",
                "log_type"          => SHAppComponent::getValue("app_constant/LOG_TYPE_INFO")
            );

            LoggerComponent::log("Processing campaign_id # ".$campaign_id, $prepare_queue_log_file);
            
            if($current_stage == 1) {
                // Patch 02 Nov 2017 (don't allow duplicate emails in prepare queue)
                $restrict_duplicate = $this->checkIfDuplicateRestrict($campaign_user_id);
                 
				/* -------------- if campaign is FIRST stage --------------- */
                $log_activity["log"] = "Campaign queue prepared";
                $log_activity["log_type"] = SHAppComponent::getValue("app_constant/LOG_TYPE_INFO");

                // log about deleting campaign sequence if already exists
                LoggerComponent::log("Skiping campaign sequence of campaign id # ".$campaign_id." stage id # ".$campaign_stage_id." [if exists]", $prepare_queue_log_file);

                try {
                    // skip campaign if already sequence prepared
                    $condition = [
                        "fields" => [
                            "id"
                        ],
                        "where" => [
                            ["where" => ["campaign_stage_id", "=", $campaign_stage_id ]]
                        ]
                    ];
                    $row_data = $model_campaign_sequences->fetchAll($condition);
                    if (!empty($row_data["id"])) {
                        LoggerComponent::log("Campaign sequence is already created for campaign id # ".$campaign_id." stage id # ".$campaign_stage_id, $prepare_queue_log_file);
                        $num_contacts_prepared++;
                        continue;
                    }

                } catch(Exception $e) {
                    LoggerComponent::log("Could not check campaign sequence of campaign id # ".$campaign_id." stage id # ".$campaign_stage_id, $prepare_queue_log_file);
                }
                
                // Read contacts from contact_file path.
                if(!empty($other_data["contacts_file"])) {
                    $contact_file = __DIR__ . "/../../upload/". $other_data["contacts_file"];
                    $total_contacts = 0;

                    if (file_exists($contact_file)) {
                        try {
                            $data_contacts          = file_get_contents($contact_file);
                            $data_contacts_array    = json_decode($data_contacts, true);
                            $columns_array          = $data_contacts_array[0];
                            $total_contacts         = count($data_contacts_array);
                            $total_columns          = count($columns_array);

                        } catch(Exception $e) {
                            LoggerComponent::log("Error while reading contact json file for campaign id ".$campaign_id, $prepare_queue_log_file);
                            LoggerComponent::log("Contact File: ".$other_data["contacts_file"], $prepare_queue_log_file);
                        }
                    }else{
                        LoggerComponent::log("Could not find contacts file of campaign id ".$campaign_id." file path ". $contact_file, $prepare_queue_log_file);
                    }

                    LoggerComponent::log("Preparing queue for campaign id # ".$campaign_id. " campaign stage id # ".$campaign_stage_id." for records # ".($total_contacts - 1), $prepare_queue_log_file);
                    // Process to available contacts in csv file.
                    for($r=1; $r<$total_contacts; $r++) {
                        $random_seconds     = (int) rand($other_data["min_seconds"], $other_data["max_seconds"]);
                        $scheduled_at       = $next_run_at + $random_seconds;
                        
                        // set first seqeunce same as schedule time
                        if ($r == 1) {
                          $scheduled_at       = $next_run_at;  
                        }
                       
                        $sequence_locked    = 0;
                        $contact_payload    = [];

                        try {
                            $payload = [];
                            for($j=0; $j<$total_columns; $j++) {
                                if(isset($columns_array[$j]) && isset($data_contacts_array[$r][$j])) {
                                    $payload["{{".$columns_array[$j]."}}"] = $data_contacts_array[$r][$j];
                                }
                            }

                            $email_address = $payload["{{".self::CSV_EMAIL_FIELD."}}"];

                            $company_name = "";
                            if (isset($payload["{{".self::CSV_COMPANY_FIELD."}}"])) {
                                $company_name = $payload["{{".self::CSV_COMPANY_FIELD."}}"];

                                $company_id = $model_account_company->getCompanyIdByName($company_name, $account_id);
                                $contact_payload["company_id"] = $company_id;
                            }

                            if (isset($payload["{{".self::CSV_FIRST_NAME_FIELD."}}"])) {
                                $contact_payload["first_name"] = $payload["{{".self::CSV_FIRST_NAME_FIELD."}}"];
                            }
                            if (isset($payload["{{".self::CSV_LAST_NAME_FIELD."}}"])) {
                                $contact_payload["last_name"] = $payload["{{".self::CSV_LAST_NAME_FIELD."}}"];
                            }

                            $domain_check_for_restrict = $this->checkForDomainRestrict($email_address, $account_id);
                            $is_contact_unsubscribed  = $this->checkForContactUnsubscribe($email_address, $account_id);
                            
                            $sequence_status = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                            // If deleted from sequence, then set status to delete
                            if(in_array($r, $stage_defination['ignore'])) {
                                $sequence_status = SHAppComponent::getValue("app_constant/STATUS_DELETE");
                            }

                            if ($sequence_status != SHAppComponent::getValue("app_constant/STATUS_DELETE") && $restrict_duplicate) {
                                $email_already_exists = $this->checkIfEmailAlreadyExists($email_address);

                                if ($email_already_exists) {
                                    $sequence_status = SHAppComponent::getValue("app_constant/STATUS_DELETE");
                                    $sequence_locked = 1;
                                }
                            }

                            if ($sequence_status != SHAppComponent::getValue("app_constant/STATUS_DELETE") && ($domain_check_for_restrict || $is_contact_unsubscribed)) {
                                $sequence_status = SHAppComponent::getValue("app_constant/STATUS_DELETE");
                                $sequence_locked = 1;
                            }

                            
                            // Fetch account id is available. (if not add new record and return account id.)
                            $account_contact_id = $model_account_contacts->getContactIdByEmail($email_address, $account_id, $contact_payload);
                            // Store sequence data
                            $save_data = [];
                            $save_data = [
                                "campaign_id"           => $campaign_id,
                                "campaign_stage_id"     => $campaign_stage_id,
                                "account_contact_id"    => $account_contact_id,
                                "csv_payload"           => json_encode($payload),
                                "scheduled_at"          => $scheduled_at,
                                "message_send_id"       => "",
                                "sent_response"         => "",
                                "locked"                => $sequence_locked,
                                "created"               => DateTimeComponent::getDateTime(),
                                "modified"              => DateTimeComponent::getDateTime(),
                                "status"                => $sequence_status,
                            ];                            
                            $model_campaign_sequences->save($save_data);
                            $campaign_sequence_ids = $model_campaign_sequences->getLastInsertId();
                            
                            // log about deleting campaign sequence if recipents domain is block
                            if($sequence_locked ==1 && $sequence_status == SHAppComponent::getValue("app_constant/STATUS_DELETE")){
                                $log_activity_blocked = array(
                                    "campaign_id"           => $campaign_id,
                                    "campaign_stage_id"     => $campaign_stage_id,
                                    "campaign_sequence_id"  => $campaign_sequence_ids,   
                                    "log"                  => "Recipient is blocked",
                                    "log_type"              => SHAppComponent::getValue("app_constant/LOG_TYPE_INFO")
                                );

                                $log_messge ="Recipient ".$email_address." is blocked for Campaign id # ".$campaign_id. " campaign stage id # ".$campaign_stage_id;
                                
                                if($domain_check_for_restrict){
                                    $log_activity_blocked["log"] = "Recipient domain is blocked";
                                    $log_messge = "Recipient ".$email_address." is blocked due to domain block for Campaign id # ".$campaign_id. " campaign stage id # ".$campaign_stage_id;
                                }else if($is_contact_unsubscribed){
                                    $log_activity_blocked["log"]= "Recipient email is unsubscribed";
                                    $log_messge = "Recipient ".$email_address." is Blocked due to unsubscribed from Campaign id # ".$campaign_id. " campaign stage id # ".$campaign_stage_id;
                                }

                                // Update campaign logs activity
                                $this->logDB($log_activity_blocked, $domain_block_log_file);
                                LoggerComponent::log($log_messge, $domain_block_log_file);
                            }
                            
                            $sequence_log_message = "Added Sequence id # ".$campaign_sequence_ids." for campaign id # ".$campaign_id." campaign stage id # ".$campaign_stage_id;

                            if($domain_check_for_restrict){
                                $sequence_log_message = "Added Sequence id # ".$campaign_sequence_ids." for campaign id # ".$campaign_id." campaign stage id # ".$campaign_stage_id . " with blocked status due to domain block.";
                            }else if($is_contact_unsubscribed){
                                $sequence_log_message = "Added Sequence id # ".$campaign_sequence_ids." for campaign id # ".$campaign_id." campaign stage id # ".$campaign_stage_id. " with blocked status due to unsubscribed from email.";
                            }

                            LoggerComponent::log($sequence_log_message , $prepare_queue_log_file);
                            /** Add Link into campaign_links table if any track link is checked **/
                            if($track_link_click == 1) {
                                if(!empty($campaign_sequence_ids)){
                                    if(!empty($email_urls[2])){
                                        foreach ($email_urls[2] as $url) {
                                            /** It will generate link id if not exists. No need of link id to fetch and assign into variable. **/
                                            $model_account_link_master->getLinkIdByUrl($url, $account_id);
                                        }
                                    }
                                }
                            }

                            $next_run_at = $scheduled_at;
                            $num_contacts_prepared++;

                            if($sequence_status == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
                                $num_contacts_deleted++;
                            }
                        } catch(\Exception $e) {
                            LoggerComponent::log("Error while preparing sequence for contact id ".$account_id." in campaign id ".$campaign_id." -- ".$e->getMessage(), $prepare_queue_log_file);
                        }
                    }

                    /** If prepared contact and deleted contact same or not available any contact then 
                     *   finish campaign. 
                     **/
                    if(($num_contacts_prepared - $num_contacts_deleted) == 0) {
                        $update_campaign_progress["progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH");
                        $update_campaign_progress["overall_progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH");
                        $update_campaign_progress["finished_on"] = $update_campaign_progress["started_on"];

                        $finished_campaigns[] = $campaign_id;

                        $log_activity["log"] = "Could not find contacts for campaign";
                        $log_activity["log_type"] = SHAppComponent::getValue("app_constant/LOG_TYPE_WARNING");
                        LoggerComponent::log("Could not find contacts : Contact Prepared --> ".$num_contacts_prepared ." Contact Deleted --> ".$num_contacts_deleted , $prepare_queue_log_file);
                    }

                    $update_campaign_progress["total_contacts"] = $num_contacts_prepared;
                    $update_campaign_progress["total_deleted"] = $num_contacts_deleted;

                    LoggerComponent::log("Total ".$num_contacts_prepared." records queued for campaign id # ".$campaign_id, $prepare_queue_log_file);
                } else {
                    /** If not able to read contact json file then finish campaign. **/
                    LoggerComponent::log("Could not find contacts json of campaign id ".$campaign_id, $prepare_queue_log_file);
                    
                    $update_campaign_progress["progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH");
                    $update_campaign_progress["overall_progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH");
                    $update_campaign_progress["finished_on"] = $update_campaign_progress["started_on"];

                    $finished_campaigns[] = $campaign_id;

                    $log_activity["log"] = "Could not find contacts for campaign";
                    $log_activity["log_type"] = SHAppComponent::getValue("app_constant/LOG_TYPE_WARNING");
                }
            } else {
                /* -------------- if campaign is POST FIRST stage --------------- */
                $log_activity["log"] = "Campaign queue prepared for stage ".$current_stage;
                $log_activity["log_type"] = SHAppComponent::getValue("app_constant/LOG_TYPE_INFO");

                // log about deleting campaign sequence if already exists
                LoggerComponent::log("Deleting campaign sequence of campaign id # ".$campaign_id." stage id # ".$campaign_stage_id." [stage ".$current_stage."] [if exists]", $prepare_queue_log_file);

                try {
                    // skip if already sequence prepared
                    $condition = [
                        "fields" => [
                            "id"
                        ],
                        "where" => [
                            ["where" => ["campaign_stage_id", "=", $campaign_stage_id ]]
                        ]
                    ];
                    $row_data = $model_campaign_sequences->fetchAll($condition);
                    if (!empty($row_data["id"])) {
                        LoggerComponent::log("Campaign sequence is already created for campaign id # ".$campaign_id." stage id # ".$campaign_stage_id, $prepare_queue_log_file);
                        continue;
                    }

                } catch(Exception $e) {
                    LoggerComponent::log("Could not check campaign sequence of campaign id # ".$campaign_id." stage id # ".$campaign_stage_id, $prepare_queue_log_file);
                }

                // Get previous stage id
                $previous_stage_id = 0;
                $condition = [
                    "fields" => [
                        "id"
                    ],
                    "where" => [
                        ["where" => ["campaign_id", "=", $campaign_id]],
                        ["where" => ["stage", "=", $current_stage - 1]],
                        ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                    ]
                ];
                $row_data = $model_campaign_stages->fetch($condition);
                if (!empty($row_data["id"])) {
                    $previous_stage_id = $row_data["id"];
                }
                
                // Mark previous stage reply tracking max date to current date
                try {
                    LoggerComponent::log("Update track reply max date for campaign id # ".$previous_stage_id, $prepare_queue_log_file);
                    $update_data = [
                        "id" => $previous_stage_id,
                        "track_reply_max_date" => DateTimeComponent::getDateTime()
                    ];
                    $model_campaign_stages->save($update_data);
                } catch(\Exception $e) {
                    LoggerComponent::log("Error while updating reply max date for campaign # ".$previous_stage_id." ".$e->getMessage(), $prepare_queue_log_file);
                }

                
                
                // set condition as per the set at campaign create to process next stage.
                $fetch_contact_condition = [];
                if(!empty($stage_defination["condition"])) {
                    switch($stage_defination["condition"]) {
                        case SHAppComponent::getValue("app_constant/CONDITION_NOT_OPEN"):
                            $fetch_contact_condition = ["where" => ["open_count", "=", 0]];
                            break;

                        case SHAppComponent::getValue("app_constant/CONDITION_NOT_REPLY"):
                            $fetch_contact_condition = ["where" => ["replied", "=", 0]];
                            break;

                        case SHAppComponent::getValue("app_constant/CONDITION_SENT"):
                            break;
                            
                        default:
                            break;
                    }
                }

                // Get sequence of previous stage based on codition set to execute stage.
                $condition = [
                    "fields" => [
                        "id",
                        "account_contact_id",
                        "csv_payload",
                        "sent_response",
                        "message_send_id"
                    ],
                    "where" => [
                        ["where" => ["status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]],
                        ["where" => ["progress", "=", SHAppComponent::getValue("app_constant/SEQ_PROGRESS_SENT")]],
                        ["where" => ["campaign_stage_id", "=", $previous_stage_id]]
                    ]
                ];
                if (!empty($fetch_contact_condition)) {
                    $condition["where"][] = $fetch_contact_condition;
                }
                $rows_sequence_contacts = $model_campaign_sequences->fetchAll($condition);
                $number_of_sequence_records = count($rows_sequence_contacts);
                
                LoggerComponent::log("Preparing queue for stage id #". $campaign_stage_id." from campaign stage id # ".$previous_stage_id." campaign id # ".$campaign_id. " for records # ".$number_of_sequence_records, $prepare_queue_log_file);
                // Process to available contacts in previous stage based on codition set to execute stage.
                for($r=0; $r<$number_of_sequence_records; $r++) {
                    $random_seconds = (int) rand($other_data["min_seconds"], $other_data["max_seconds"]);
                    $scheduled_at   = $next_run_at + $random_seconds;
                    $sent_response  = $rows_sequence_contacts[$r]["sent_response"];
                    $csv_payload_array = json_decode($rows_sequence_contacts[$r]["csv_payload"], true);
                    $email_address = $csv_payload_array["{{Email}}"];
                    $sent_response_array = json_decode($sent_response, true);
                    $sent_response_array["sent"] = 0;
                    $sent_response_array["previous_message_id"] = $rows_sequence_contacts[$r]["message_send_id"];
                    $sent_response = json_encode($sent_response_array);
                    $sequence_locked = 0;
                    $sequence_status = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                    // If deleted from sequence, then set status to delete
                    if(in_array($rows_sequence_contacts[$r]['id'], $stage_defination['ignore'])) {
                        $sequence_status = SHAppComponent::getValue("app_constant/STATUS_DELETE");
                    }
                    
                    $check_for_domain_block_other_stages = $this->checkForDomainRestrict($email_address, $account_id);
                    $is_contact_unsubscirbed_other_stages  = $this->checkForContactUnsubscribe($email_address, $account_id);
                    
                    if ($sequence_status != SHAppComponent::getValue("app_constant/STATUS_DELETE") && ($check_for_domain_block_other_stages || $is_contact_unsubscirbed_other_stages)) {
                        $sequence_status = SHAppComponent::getValue("app_constant/STATUS_DELETE");
                        $sequence_locked = 1;
                    }
                    // Store sequence data
                    try {
                        $save_data = [];
                        $save_data = [
                            "campaign_id"           => $campaign_id,
                            "campaign_stage_id"     => $campaign_stage_id,
                            "account_contact_id"    => $rows_sequence_contacts[$r]["account_contact_id"],
                            "csv_payload"           => $rows_sequence_contacts[$r]["csv_payload"],
                            "scheduled_at"          => $scheduled_at,
                            "locked"                => $sequence_locked,
                            "message_send_id"       => "",
                            "sent_response"         => $sent_response,
                            "created"               => DateTimeComponent::getDateTime(),
                            "modified"              => DateTimeComponent::getDateTime(),
                            "status"                => $sequence_status,
                        ];
                        $model_campaign_sequences->save($save_data);
                        $campaign_sequence_ids = $model_campaign_sequences->getLastInsertId();
                    
                        // log about deleting campaign sequence if recipents domain is block
                        if($sequence_locked ==1 && $sequence_status == SHAppComponent::getValue("app_constant/STATUS_DELETE")){
                            $log_activity_blocked = array(
                                "campaign_id"           => $campaign_id,
                                "campaign_stage_id"     => $campaign_stage_id,
                                "campaign_sequence_id"  => $campaign_sequence_ids,
                                "log"                   => "Recipient campaign blocked",
                                "log_type"              => SHAppComponent::getValue("app_constant/LOG_TYPE_INFO")
                            );

                            $log_messge ="Recipient ".$email_address." is blocked for Campaign id # ".$campaign_id . " campaign stage id # ".$campaign_stage_id;
                                
                            if($check_for_domain_block_other_stages){
                                $log_activity_blocked["log"] = "Recipient domain is blocked";
                                $log_messge = "Recipient ".$email_address." is blocked due to domain block for Campaign id # ".$campaign_id . " campaign stage id # ".$campaign_stage_id;
                            }else if($is_contact_unsubscirbed_other_stages){
                                $log_activity_blocked["log"]= "Recipient email is unsubscribed";
                                $log_messge = "Recipient ".$email_address." is Blocked due to unsubscribed for Campaign id # ".$campaign_id. " campaign stage id # ".$campaign_stage_id;
                            }
                            
                            // Update campaign logs activity
                            $this->logDB($log_activity_blocked, $domain_block_log_file);
                            LoggerComponent::log($log_messge, $domain_block_log_file);                            
                        }

                        $sequence_log_message = "Added Sequence id # ".$campaign_sequence_ids." for campaign id # ".$campaign_id." campaign stage id # ".$campaign_stage_id;
                        
                        if($check_for_domain_block_other_stages){
                            $sequence_log_message = "Added Sequence id # ".$campaign_sequence_ids." for campaign id # ".$campaign_id." campaign stage id # ".$campaign_stage_id . " with blocked status due to domain block.";
                        }else if($is_contact_unsubscirbed_other_stages){
                            $sequence_log_message = "Added Sequence id # ".$campaign_sequence_ids." for campaign id # ".$campaign_id." campaign stage id # ".$campaign_stage_id. " with blocked status due to unsubscribed from email.";
                        }
                        
                        LoggerComponent::log($sequence_log_message, $prepare_queue_log_file);
                        /** Add Link into campaign_links table if any track link is checked **/
                        if($track_link_click == 1) {
                            if(!empty($campaign_sequence_ids)){
                                if(!empty($email_urls[2])){
                                    foreach ($email_urls[2] as $url) {
                                        /** It will generate link id if not exists. No need of link id to fetch and assign into variable. **/
                                        $model_account_link_master->getLinkIdByUrl($url, $account_id);
                                    }
                                }
                            }
                        }

                        $next_run_at = $scheduled_at;
                        $num_contacts_prepared++;

                        if($sequence_status == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
                            $num_contacts_deleted++;
                        }
                    } catch(\Exception $e) {
                        LoggerComponent::log("Error while preparing sequence for contact id ".$rows_sequence_contacts[$r]["account_contact_id"]." in campaign id ".$campaign_id." [stage: ".$current_stage."] -- ".$e->getMessage(), $prepare_queue_log_file);
                    }
                }
                /** If prepared contact and deleted contact same or not available any contact then 
                 *  finish campaign. 
                 **/
                if(($num_contacts_prepared - $num_contacts_deleted) == 0) {
                    
                    $update_campaign_progress["progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH");
                    $update_campaign_progress["overall_progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH");
                    $update_campaign_progress["finished_on"] = $update_campaign_progress["started_on"];

                    $finished_campaigns[] = $campaign_id;

                    $log_activity["log"] = "No contact found for stage ".$current_stage."";
                    $log_activity["log_type"] = SHAppComponent::getValue("app_constant/LOG_TYPE_WARNING");
                    LoggerComponent::log("Could not find contacts : Contact Prepared --> ".$num_contacts_prepared ." Contact Deleted --> ".$num_contacts_deleted , $prepare_queue_log_file);
                }

                $update_campaign_progress["total_contacts"] = $num_contacts_prepared;
                $update_campaign_progress["total_deleted"] = $num_contacts_deleted;

                LoggerComponent::log("Total ".$num_contacts_prepared." records queued for campaign id # ".$campaign_id, $prepare_queue_log_file);
            }

            // Update status of campaign
            try {
                $save_data = [];
                $save_data = [
                    "id"                => $campaign_id,
                    "overall_progress"  => $update_campaign_progress["overall_progress"],
                    "modified"          => DateTimeComponent::getDateTime()
                ];
                $model_campaign_master->save($save_data);
                // Update status of campaign stage
                $save_data = [
                    "id"                => $campaign_stage_id,
                    "content"           => $update_campaign_progress["content"],
                    "progress"          => $update_campaign_progress["progress"],
                    "total_contacts"    => $update_campaign_progress["total_contacts"],
                    "total_deleted"     => $update_campaign_progress["total_deleted"],
                    "started_on"        => $update_campaign_progress["started_on"],
                    "modified"          => DateTimeComponent::getDateTime()
                ];
                    
                if ( isset($update_campaign_progress["finished_on"]) ) {
                    $save_data["finished_on"] = $update_campaign_progress["finished_on"];
                }                  

                $model_campaign_stages->save($save_data);
            } catch(\Exception $e) {
                LoggerComponent::log("Error while updating progress for campaign id # ".$campaign_id." -- ".$e->getMessage(), $prepare_queue_log_file);
            }

            // Log campaign sequence count
            LoggerComponent::log("Total ".$num_contacts_prepared." contact sequences created for campaign id # ".$campaign_id, $prepare_queue_log_file);

            // Update campaign logs activity
            $this->logDB($log_activity, $prepare_queue_log_file);
        }

        /** ---- Remove queue from locked state where campaign faced server error ---- **/
        try {
            $condition = [
                "where" => [
                    ["where" => ["status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]],
                    ["where" => ["progress", "=", SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED")]],
                    ["where" => ["locked", "=", 1]]
                ]
            ];
            $save_data = [
                "locked" => 0
            ];
            $total_queue_unlocked = $model_campaign_sequences->update($save_data, $condition);
            LoggerComponent::log($total_queue_unlocked. " records removed from locked queue", $prepare_queue_log_file);
        } catch(\Exception $e) {
            LoggerComponent::log("Error while unlocking queue -- ".$e->getMessage(), $prepare_queue_log_file);
        }


        /** ---- Set campaigns to finish stages whose queue is processed but status hasn't changed ---- **/
        // Set parameters
        $query_params = [
            "bulk_campaign" => $this->is_bulk_campaign
        ];

        // Other values for condition
        $other_values = [
            "active"                => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
            "campaign_scheduled"    => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED"),
            "campaign_finished"     => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH")       
        ];        
        $rows_finished_campaigns_data   = $model_campaign_master->getFinishedCampaigns($query_params, $other_values);
        $number_of_campaigns_to_process = count($rows_finished_campaigns_data);
        
        /** Process to campaign whose status is not finish, which are in progress state.
         *  set them to finish.
         **/
        for($i=0; $i<$number_of_campaigns_to_process; $i++) {
            $campaign_id        = $rows_finished_campaigns_data[$i]["id"];
            $campaign_stage_id  = $rows_finished_campaigns_data[$i]["campaign_stage_id"];

            LoggerComponent::log("Zombie campaign id # ".$campaign_id, $prepare_queue_log_file);

            try {
                $save_data = [
                    "id"                => $campaign_id,
                    "overall_progress"  => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH"),
                    "modified"          => DateTimeComponent::getDateTime()
                ];
                $model_campaign_master->save($save_data);

                $save_data = [
                    "id"            => $campaign_stage_id,
                    "progress"      => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH"),                    
                    "finished_on"   => DateTimeComponent::getDateTime(),
                    "modified"      => DateTimeComponent::getDateTime()
                ];
                $model_campaign_stages->save($save_data);
            } catch(\Exception $e) {
                LoggerComponent::log("Zombie campaign id # ".$campaign_id." update fail -- ".$e->getMessage(), $prepare_queue_log_file);
            }
        }
        
        // Send emails to users whose campaign finished due to empty queue
        if(!empty($finished_campaigns)) {
            $this->sendCampaignReportMail($finished_campaigns);
        }
        
        // Log queue prepare complete
        LoggerComponent::log("Completed queue prepare", $prepare_queue_log_file);
        LoggerComponent::log("====================================", $prepare_queue_log_file);
        LoggerComponent::log("====================================", $domain_block_log_file);
    }

    /**
    * Get upcoming campaigns and prepare queue execution [BULK]
    */
    public function actionPrepareQueueBulk(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $this->is_bulk_campaign = 1;

        $this->actionPrepareQueue($request, $response, $args);
    }

    /**
     * Process campaigns in queue
     */
    public function actionProcessQueue(ServerRequestInterface $request, ResponseInterface $response, $args) {
        // Prepare log unique string for logs identification (process id)
        $PROCESS_ID             = "[".strtotime("now").rand(111111,999999)."] ";

        $current_time           = time();
        $end_time               = $current_time + self::QUEUE_PROCESS_LIMIT_TIME;
        $num_records_processed  = 0;
        
        $modulo                 = isset($_SERVER['argv'][3]) ? $_SERVER['argv'][3] : "";
        $reminder               = isset($_SERVER['argv'][2]) ? $_SERVER['argv'][2] : "";

        if(!is_null($reminder) && !empty($modulo)){
            $log_file_name = self::LOG_FILE_QPROCESS_DYNA.'_'.$reminder.'_'.$modulo.'.log';
        } else {
            $log_file_name = self::LOG_FILE_QPROCESS;
        }

        $base_url                   = BASE_URL;
        $connect_gmail_account_link = $base_url . "user/updateprofile";
        $connect_smtp_account_link  = $base_url . "user-smtp";
        $fail_bounce_blog_link      = "https://www.saleshandy.com/blog/email-delivery/";

        $system_paused_campaigns    = [];
        $campaigns_quota_exceed     = [];

        //Create log directory if not exist
        if (!is_dir(self::LOG_FILE_DIRECTORY)) {
            mkdir(self::LOG_FILE_DIRECTORY, 0777, true);
        }

        LoggerComponent::log($PROCESS_ID."Starting queue process", $log_file_name);

        $model_campaign_master          = new CampaignMaster();
        $model_campaign_stages          = new CampaignStages();
        $model_campaign_sequences       = new CampaignSequences();
        $model_campaign_links           = new CampaignLinks();
        $model_account_sending_method   = new AccountSendingMethods();
        $model_account_contacts         = new AccountContacts();
        $model_account_link_master      = new AccountLinkMaster();
        $model_user_master              = new UserMaster();
        
        $other_values = [
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];
        /** Loop execute for 10 seconds(QUEUE_PROCESS_LIMIT_TIME) 
         *  with max records 100 (QUEUE_PROCESS_LIMIT_RECORDS) 
         **/ 
        while($num_records_processed < self::QUEUE_PROCESS_LIMIT_RECORDS && $end_time >= $current_time) {
            $rows_pending_queue = [];
            try {
                $condition = [
                    "fields" => [
                        "csq.id",
                        "csq.campaign_id",
                        "csq.campaign_stage_id",
                        "csq.account_contact_id",
                        "csq.csv_payload",
                        "csq.sent_response",
                        "cst.subject",
                        "cst.content",
                        "cst.stage",
                        "cm.account_id",
                        "cm.user_id",
                        "cm.title",
                        "cm.send_as_reply",
                        "cm.track_click",
                        "cm.account_sending_method_id",
                        "cm.other_data",
                        "cm.total_stages"
                    ],
                    "where" => [
                        ["where" => ["cm.status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]],
                        ["where" => ["cm.overall_progress", "<>", SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED")]],
                        ["where" => ["cm.overall_progress", "<>", SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH")]],
                        ["where" => ["cm.is_bulk_campaign", "=", $this->is_bulk_campaign]],
                        ["where" => ["csq.status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]],
                        ["where" => ["csq.progress", "=", SHAppComponent::getValue("app_constant/SEQ_PROGRESS_SCHEDULED")]],
                        ["where" => ["csq.locked", "=", 0]],                        
                        ["where" => ["csq.scheduled_at", "<=", strtotime("now")]]
                    ],
                    "join" => [
                        "campaign_stages",
                        "campaign_master"
                    ],
                    "order_by" => [
                        "cm.priority DESC",
                        "csq.scheduled_at ASC"
                    ],
                    "limit" => 5,
                    "offset" => 0
                ];

                if (!empty($modulo) && !is_null($reminder)) {
                    $condition["where"][] = ["where" => ["csq.id % " . $modulo, "=", $reminder]];
                }

                $rows_pending_queue = $model_campaign_sequences->fetchAll($condition);
                $num_records_found  = count($rows_pending_queue);

            } catch(\Exception $e) {
                LoggerComponent::log($PROCESS_ID."Could not fetch campaign sequences -- ".$e->getMessage(), $log_file_name);
            }
            
            // Get array of id from pending campaigns
            $campaign_sequence_ids_for_lock = array_column($rows_pending_queue, 'id');

            // Set queue records to locked
            try {
                if ($num_records_found > 0) { 
                    LoggerComponent::log($PROCESS_ID."Locking sequence id # ".json_encode($campaign_sequence_ids_for_lock), $log_file_name);
                
                    $condition = [
                        "where" => [
                            ["whereIn" => ["id", $campaign_sequence_ids_for_lock]]
                        ]
                    ];
                    $save_data = [
                        "locked"        => 1,
                        "locked_date"   => DateTimeComponent::getDateTime()
                    ];
                    
                    $model_campaign_sequences->update($save_data, $condition);
                }

            } catch(Exception $e) {
                LoggerComponent::log($PROCESS_ID."Could not lock sequence id # ".json_encode($campaign_sequence_ids_for_lock)." -- ".$e->getMessage(), $log_file_name);
            }
            
            // Process queue
            for ($c=0; $c<$num_records_found; $c++) {
                $num_records_processed++;

                $account_id                 = $rows_pending_queue[$c]["account_id"];
                $user_id                    = $rows_pending_queue[$c]["user_id"];
                $sequence_id                = $rows_pending_queue[$c]["id"];
                $campaign_id                = $rows_pending_queue[$c]["campaign_id"];
                $campaign_stage_id          = $rows_pending_queue[$c]["campaign_stage_id"];
                $account_contact_id         = $rows_pending_queue[$c]["account_contact_id"];
                $csv_payload                = json_decode($rows_pending_queue[$c]["csv_payload"], true);
                $send_as_reply              = $rows_pending_queue[$c]["send_as_reply"];
                $sent_response              = $rows_pending_queue[$c]["sent_response"];
                $track_link_click           = $rows_pending_queue[$c]["track_click"];
                $sequence_stage             = $rows_pending_queue[$c]["stage"];

                $account_sending_method_id  = $rows_pending_queue[$c]["account_sending_method_id"];
                $other_data                 = json_decode($rows_pending_queue[$c]["other_data"], true);
                $total_stages               = $rows_pending_queue[$c]["total_stages"];
                $subject                    = $rows_pending_queue[$c]["subject"];
                $content                    = $rows_pending_queue[$c]["content"];
                $title                      = $rows_pending_queue[$c]["title"];
                $campaign_information = 
                [
                    "stage" => "Stage ".$sequence_stage,
                    "title" => $title,
                ]; 
                $add_delay_to_queue = false;
                $push_at_the_end_of_queue = false;
                $attempt_details = [];
                
                $update_sequence_record = array(
                    "progress"  => SHAppComponent::getValue("app_constant/SEQ_PROGRESS_SENT"),
                    "is_bounce" => 0,
                    "sent_at"   => DateTimeComponent::getDateTime(),
                    "modified"  => DateTimeComponent::getDateTime()
                );

                $update_stage_record = array(
                    "progress"  => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_IN_PROGRESS"),
                    "modified"  => DateTimeComponent::getDateTime()
                );

                $update_campaign_record = array(
                    "overall_progress"  => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_IN_PROGRESS"),
                    "modified"          => DateTimeComponent::getDateTime()
                );

                $log_activity = array(
                    "campaign_id"           => $campaign_id,
                    "campaign_stage_id"     => $campaign_stage_id,
                    "campaign_sequence_id"  => $sequence_id,
                    "log"                   => "",
                    "log_type"              => SHAppComponent::getValue("app_constant/LOG_TYPE_INFO")
                );

                // To pause campaign and notify user
                $campaign_system_paused = false;

                LoggerComponent::log($PROCESS_ID."Processing sequence id # ".$sequence_id, $log_file_name);

                // Set campaign data (if not set)
                $this->campaign_mail_info[] = $campaign_stage_id;
               
                // Get user details
                $user_email = "";
                $user = $model_user_master->getUserById($user_id, $account_id);
                if (!empty($user)) {
                    $user_email = $user["email"];
                }
                
                // Get account sending method
                $res_sending_method = $model_account_sending_method->getSendingMethodById($account_sending_method_id, $other_values);
                
                if(!empty($res_sending_method) && !empty($res_sending_method["credit_limit"])) {
                    
                    try {
                        $email = "";
                        $condition = [
                            "fields" => [
                                "email"
                            ],
                            "where" => [
                                ["where" => ["id", "=", $account_contact_id]]
                            ]
                        ];
                        $res_contacts = $model_account_contacts->fetch($condition);
                        if ( !empty($res_contacts["email"]) ) {
                            $email = $res_contacts["email"];
                        }
                    } catch(\Exception $e) {
                        LoggerComponent::log($PROCESS_ID."Error in getting email from contact id -- ".$e->getMessage(), $log_file_name);
                    }
                        
                    $info = [];
                    $info["subject"]        = $subject;
                    $info["content"]        = $content;
                    $info["to"]             = $email;
                    $info["cc"]             = (!empty($other_data["cc"])) ? $other_data["cc"] : "";
                    $info["bcc"]            = (!empty($other_data["bcc"])) ? $other_data["bcc"] : "";
                    $info["reply_to"]       = "";
                    $info["from_email"]     = $res_sending_method["from_email"];
                    $info["from_name"]      = $res_sending_method["from_name"];
                    
                    $info["subject"]        = strtr($info["subject"], $csv_payload);
                    $info["content"]        = strtr(html_entity_decode($info["content"]), $csv_payload);

                    $tracking_url           = TRACKING_PIXEL[rand(0, TRACKING_PIXEL_COUNT)];
                    $tracking_url           = $tracking_url . '/'.TRACKING_UNIQUE_TEXT
                                                .'/c/'. StringComponent::encodeRowId($sequence_id) 
                                                .'/'. StringComponent::encodeRowId($user_id) 
                                                .'/'. StringComponent::encodeRowId($account_id);
                                                
                    $tracking_pixel_image   = '<img width="0" height="0" id="shtracking_1s2" src="' . $tracking_url. '" style="display:none">';

                    $info["content"]        = $info["content"] . $tracking_pixel_image;
                    
                    // Link click track code
                    if ($track_link_click == 1) {
                        $regex = '/(href=")([^{#].*?)"/i';
                        preg_match_all($regex, $info["content"], $attachment_urls);
                            
                        if (!empty($attachment_urls[2])) {
                            $redirecturl_mapping = array();
                            foreach ($attachment_urls[2] as $url) {
                                $link_domain = \DOC_VIEWER_BASE_URL;
                                $link_domain = $link_domain."/view/";
                                $findDocSpace = strpos($url, $link_domain);   
                                
                                if($findDocSpace===false){    
                                    $link_id        = $model_account_link_master->getLinkIdByUrl($url, $account_id);
                                    $redirect_key   = StringComponent::encodeRowId([$account_id, $campaign_stage_id, $sequence_id, $link_id, $user_id]);

                                    $data = [
                                            "account_id"            => $account_id,
                                            "campaign_id"           => $campaign_id,
                                            "campaign_stage_id"     => $campaign_stage_id,
                                            "campaign_sequence_id"  => $sequence_id,
                                            "account_link_id"       => $link_id,
                                            "redirect_key"          => $redirect_key
                                    ];
                                    
                                    try {
                                        $model_campaign_links->checkRedirectKey($data);                                    
                                    } catch(\Exception $e) { 
                                        LoggerComponent::log($PROCESS_ID."Error in saving campaign link -- ".$e->getMessage(), $log_file_name);
                                    }
                                       
                                    $new_url = TRACKING_PIXEL[rand(0, TRACKING_PIXEL_COUNT)] ."/". TRACKING_UNIQUE_TEXT . "/r/c/" . $redirect_key."?r=".$url;
                                   
                                    $info["content"] = str_replace('"' . $url . '"', '"' . $new_url . '"', $info["content"]);
                                }
                            }
                        }
                    }
                   
                    $regex = '/(href=")([{#].*?)"/i';
                    preg_match_all($regex, $info["content"], $document_urls);                    
                    if (!empty($document_urls[2])) {
                        $documentUrls = array_unique($document_urls[2]);
                        
                        foreach ($documentUrls as $url) {
                            $docUrl = str_replace("{","",$url);
                            $docUrl = str_replace("}","",$docUrl);
                            $docUrl = str_replace("file:","",$docUrl);
                            $document_payload = [
                                "from_campaign" => "1",
                                "account_id" => $account_id,
                                "user_id" => $user_id,
                                "source_id" =>  SHAppComponent::getValue("source/WEB_APP"),
                                "contact_id" => $account_contact_id,
                                "document_id" =>StringComponent::decodeRowId($docUrl),
                                "name"  => "Link for " .trim($subject)
                            ];

                            $new_url = LinksController::generateDocumentLink($document_payload);
                            $info["content"] = str_replace('"' . $url . '"', '"' . $new_url . '"', $info["content"]);
                        }
                    }

                    $email_sending_method_id = $res_sending_method["email_sending_method_id"];
                    $payload = json_decode($res_sending_method["payload"]);
                    
                    // Send email with Gmail
                    if($email_sending_method_id == SHAppComponent::getValue("email_sending_method/GMAIL")) {
                       
                        if ($res_sending_method["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE")
                            && $res_sending_method["connection_status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE")) 
                        {
                            $info["refresh_token"]  = $payload->refresh_token;
                            $info["thread_id"]      = "";

                            // Check how to send email

                            if($sequence_stage > 1 && $send_as_reply && !empty($sent_response)) {
                                $sent_response_array    = json_decode($sent_response, true);
                                $info["thread_id"]      = $sent_response_array["thread_id"];
                                $previous_message_id    = $sent_response_array["previous_message_id"];

                                // when thread is not available but message id is available
                                if(empty($info["thread_id"]) && !empty($previous_message_id)) {
                                    $info["thread_id"] = GmailComponent::getThreadIdFromMessageId($previous_message_id, $info["refresh_token"]);
                                }
                            }
                         
                            $attempt_details = GmailComponent::sendMailGmail($info, false);
                            $attempt_details_log = $attempt_details;
                            unset($attempt_details_log["sent"]);
                            LoggerComponent::log($PROCESS_ID."Gmail Mail send response  ".json_encode($attempt_details_log), $log_file_name);
                            
                            if(isset($attempt_details["gmail_message_id"]) && $attempt_details["gmail_message_id"] != "") {
                                //mail sent successfully
                                $sent_message_id        = $attempt_details["gmail_message_id"];
                                $sent_response_err_msg  = "";

                                $update_sequence_record["message_send_id"] = $attempt_details["gmail_message_id"];
                                if (!empty($attempt_details["gmail_message_thread_id"])) {
                                    $update_sequence_record["sent_response"] = json_encode(array("thread_id"=>$attempt_details["gmail_message_thread_id"]));
                                }
                            } else {
                                //mail not sent
                                $sent_message_id        = "0";
                                $sent_response_err_msg  = $attempt_details["error_message"];
                            }
                        } else {
                            // Pause campaign and notify user
                            LoggerComponent::log($PROCESS_ID."Gmail data are invalid (user not connected)", $log_file_name);

                            // Campaign pause stage
                            $update_campaign_record["overall_progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED");
                            $update_campaign_record["status_message"]   = "Your Gmail account has been disconnected. Please re-connect your google account from email account page";
                            
                            $campaign_system_paused = true;
                            $system_paused_campaigns[$campaign_id] = [
                                "email" => $user_email,
                                "case"  => self::SYSTEM_PAUSE_REASON_GMAIL,
                                "title" => $title,
                                "stage" => $sequence_stage
                            ];
                        }
                    }

                    // Send email with user's SMTP
                    if($email_sending_method_id == SHAppComponent::getValue("email_sending_method/SMTP")) {
                        if ( $res_sending_method["status"] == SHAppComponent::getValue("app_constant/STATUS_INACTIVE")
                            || $res_sending_method["connection_status"] == SHAppComponent::getValue("app_constant/STATUS_INACTIVE") 
                            || empty($payload->username)) 
                        {
                            // Pause campaign and notify user
                            LoggerComponent::log($PROCESS_ID."SMTP details not set (accont not connected)", $log_file_name);

                            // Campaign pause stage
                            $update_campaign_record["overall_progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED");
                            $update_campaign_record["status_message"] = "Your SMTP account is not connected. Please verify your SMTP account settings from email account page.";

                            $campaign_system_paused = true;
                            $system_paused_campaigns[$campaign_id] = [
                                "email" => $user_email,
                                "case"  => self::SYSTEM_PAUSE_REASON_SMTP,
                                "title" => $title,
                                "stage" => $sequence_stage
                            ];
                        } else {
                            $info["smtp_details"]["host"]       = $payload->host;
                            $info["smtp_details"]["username"]   = $payload->username;
                            $info["smtp_details"]["password"]   = $payload->password;
                            $info["smtp_details"]["port"]       = $payload->port;
                            $info["smtp_details"]["encryption"] = $payload->encryption;

                            $attempt_details = SMTPComponent::mailSendUserSmtp($info);
                            $attempt_details_log = $attempt_details;
                            unset($attempt_details_log["sent"]);
                            LoggerComponent::log($PROCESS_ID."SMTP Mail send response  ".json_encode($attempt_details_log), $log_file_name);
                            

                            if(isset($attempt_details["message_id"]) && $attempt_details["message_id"] != "") {
                                //mail sent successfully
                                $sent_message_id        = $attempt_details["message_id"];
                                $sent_response_err_msg  = "";
                            } else {
                                //mail not sent
                                $sent_message_id        = "0";
                                $sent_response_err_msg  = $attempt_details["message"];
                            }
                        }                            
                    }

                    // if sending mail is attempted
                    if(isset($attempt_details["success"])) {
                        // If sending failed
                        if(!$attempt_details["success"]) {
                            if(!isset($attempt_details["error_message"]))
                            {
                                $attempt_error_arr = array(
                                    "message"   => $attempt_details["message"],
                                    "code"      => null
                                );
                            }
                            else if (json_decode($attempt_details["error_message"], true) === NULL) {
                                $attempt_error_arr = array(
                                    "message"   => $attempt_details["error_message"],
                                    "code"      => null
                                );
                                

                            } else {
                                $attempt_error_arr = json_decode($attempt_details["error_message"], true);

                                if (isset($attempt_error_arr["error"]["message"])) {
                                    $attempt_error_arr["message"] = $attempt_error_arr["error"]["message"];
                                }
                            }

                            if (isset($attempt_error_arr["error"])) {
                                // When we recieve error from gmail
                                if ($attempt_error_arr["error"]["code"] == 401) {
                                    unset($attempt_details["success"]);

                                    // Campaign pause stage
                                    $update_campaign_record["overall_progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED");
                                    $update_campaign_record["status_message"]   = "Error in connecting your Gmail account. SalesHandy app is not able to connect to your Gmail account, please re-connect your Gmail account from email account page.";
                                    
                                    $campaign_system_paused = true;
                                    $system_paused_campaigns[$campaign_id] = [
                                        "email" => $user_email,
                                        "case"  => self::SYSTEM_PAUSE_REASON_GMAIL_AUTH,
                                        "title" => $title,
                                        "stage" => $sequence_stage
                                    ];

                                    // Update user Gmail account as disconnected
                                    try {
                                        $update_data = [
                                            "id"=> $account_sending_method_id,
                                            "error_message"=> "401 - Error in connecting your Gmail account." 
                                        ];
                                        $model_account_sending_method->disConnectMailAccount($update_data);

                                    } catch (Exception $e) {
                                        // Do nothing
                                    }

                                } else if ($attempt_error_arr["error"]["code"] == 429 || $attempt_error_arr["error"]["code"] == 500) {
                                    unset($attempt_details["success"]);

                                    $push_at_the_end_of_queue = true;

                                    $log_activity["log"]        = "Temporary issue with sending email: ".$attempt_error_arr["message"];
                                    $log_activity["log_type"]   = SHAppComponent::getValue("app_constant/LOG_TYPE_WARNING");
                                }

                            } else if (stristr($attempt_error_arr["message"],CONNECTION_NOT_ESTABLISH) !== false || 
                                stristr($attempt_error_arr["message"], EXPECTED_CODE_250_BUT_GONE) !== false || 
                                stristr($attempt_error_arr["message"], EXPECTED_CODE_220_BUT_GONE) !== false ||
                                stristr($attempt_error_arr["message"], SMTP_CONNECTION_FAILED ) !== false ||
                                stristr($attempt_error_arr["message"], COULD_NOT_AUTHENTICATE) !== false) {
                                // Check for other known errors
                                unset($attempt_details["success"]);

                                // Campaign pause stage
                                $update_campaign_record["overall_progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED");
                                $update_campaign_record["status_message"]   = "Error in connecting your SMTP account. SalesHandy app is not able to connect to your SMTP account, please re-connect your SMTP account settings from email account page.";
                                
                                $campaign_system_paused = true;
                                $system_paused_campaigns[$campaign_id] = [
                                    "email" => $user_email,
                                    "case"  => self::SYSTEM_PAUSE_REASON_SMTP_AUTH,
                                    "title" => $campaign_information["title"],
                                    "stage" => $campaign_information["stage"]
                                ];

                                // Update user SMTP account as disconnected
                                try {
                                    $update_data = [
                                        "id"=> $account_sending_method_id,
                                        "error_message"=> "Error in connecting your SMTP account. Please reconnect your account."
                                    ];
                                    $model_account_sending_method->disConnectMailAccount($update_data);

                                } catch (Exception $e) {
                                    // Do nothing
                                }
                            } else if (stristr($attempt_error_arr["message"], EXPECTED_CODE_220) !== false || 
                                stristr($attempt_error_arr["message"], EXPECTED_CODE_250) !== false || 
                                stristr($attempt_error_arr["message"], RATE_LIMIT_EXCEED) !== false) {
                                unset($attempt_details["success"]);

                                $push_at_the_end_of_queue = true;

                                $log_activity["log"]        = "Temporary issue with sending email: ".$attempt_error_arr["message"];
                                $log_activity["log_type"]   = SHAppComponent::getValue("app_constant/LOG_TYPE_WARNING");
                            } else {
                                $update_sequence_record["progress"] = SHAppComponent::getValue("app_constant/SEQ_PROGRESS_FAILED");

                                $log_activity["log"]        = $attempt_details["error_message"];
                                $log_activity["log_type"]   = SHAppComponent::getValue("app_constant/LOG_TYPE_ERROR");
                            }

                            /** Removed as of 23 Oct 2017 (not used)
                            if(!empty($attempt_details["bounce"])) {
                                $update_sequence_record["is_bounce"] = 1;
                            }
                            */

                            LoggerComponent::log($PROCESS_ID."Error while sending mail to sequence id # ".$sequence_id." -- ".$sent_response_err_msg, $log_file_name);
                        } else {
                            $log_activity["log"] = "Mail successfully sent";
                            $log_activity["log_type"] = SHAppComponent::getValue("app_constant/LOG_TYPE_INFO");
                            LoggerComponent::log($PROCESS_ID."Mail sent to sequence id # ".$sequence_id, $log_file_name);
                        }

                        if (!$add_delay_to_queue && !$push_at_the_end_of_queue && !$campaign_system_paused) {
                            try {
                                // Decrease user quota
                                $response = $model_account_sending_method->decreaseQuota($res_sending_method["id"]);

                                // update sequence result
                                $condition = [
                                    "where" => [
                                        ["where" => ["id", "=", $sequence_id]]
                                    ]
                                ];
                                $model_campaign_sequences->update($update_sequence_record, $condition);

                                // Update campaign stats                                
                                $condition = [
                                    "fields" => [
                                        "total_success",
                                        "total_fail"
                                    ],
                                    "where" => [
                                        ["where" => ["id", "=", $campaign_stage_id]]
                                    ]
                                ];
                                $row_data = $model_campaign_stages->fetch($condition);
                                LoggerComponent::log($PROCESS_ID."Get details Campaign Stage to update total_success/ total_fail Data ".json_encode($row_data). " Stage # ".$campaign_stage_id , $log_file_name);
                                $update_data = null;
                                if ($attempt_details["success"]) {
                                    $update_data = $model_campaign_stages->updateStageData($campaign_stage_id,'total_success',1);                               
                                } else {
                                    $update_data = $model_campaign_stages->updateStageData($campaign_stage_id,'total_fail',1);                                 
                                }
                            
                                // $model_campaign_stages->save($update_data);                                
                                LoggerComponent::log($PROCESS_ID."Update details Campaign Stage total_success/ total_fail Data ".json_encode($update_data), $log_file_name);
                            } catch(\Exception $e) {
                                LoggerComponent::log($PROCESS_ID."Error while updating progress for sequence id # ".$sequence_id." (after mail send attempt) -- ".$e->getMessage(), $log_file_name);
                            }
                        }
                    }else{
                        LoggerComponent::log($PROCESS_ID."Error while sending mail to sequence id # ".$sequence_id. " not get success key in response.", $log_file_name);
                    }
                } else {
                    // credit over
                    LoggerComponent::log($PROCESS_ID."Mail sending credit is over while processing sequence id # ".$sequence_id, $log_file_name);

                    // Campaign waiting stage
                    $update_stage_record["progress"]            = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_WAITING");
                    $update_campaign_record["overall_progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_WAITING");

                    // Log activity warning
                    $log_activity["log"]        = "Mail sending credit is over";
                    $log_activity["log_type"]   = SHAppComponent::getValue("app_constant/LOG_TYPE_WARNING");

                    $add_delay_to_queue = true;
                }

                // Add delay to queue (1 hour)
                if ($add_delay_to_queue) {
                    $campaigns_quota_exceed[$campaign_stage_id] = 1;
                    $update_campaign_record["status_message"]   = "Mail quota exhausted. Campaign will resume soon after quota reset.";
                }
                
                // Push queue at the end of the sequence
                if ($push_at_the_end_of_queue) {
                    try {
                        $condition = [
                            "fields" => [
                                "MAX(scheduled_at) AS max_queue_time"
                            ],
                            "where" => [
                                ["where" => ["campaign_stage_id", "=", $campaign_stage_id]],
                                ["where" => ["status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                            ]
                        ];
                        $rows_campaign_last_queue_time = $model_campaign_sequences->fetch($condition);

                        if (!empty($rows_campaign_last_queue_time["max_queue_time"])) {
                            $max_scheduled_time = $rows_campaign_last_queue_time["max_queue_time"];
                        } else {
                            $max_scheduled_time = DateTimeComponent::getDateTime();
                        }
                        $random_interval = rand(60, 90);

                        $update_sequence_record["locked"]       = 0;
                        $update_sequence_record["scheduled_at"] = $max_scheduled_time + $random_interval;
                        $update_sequence_record["progress"]     = SHAppComponent::getValue("app_constant/SEQ_PROGRESS_SCHEDULED");
                        unset($update_sequence_record["sent_at"]);

                        $condition = [
                            "where" => [
                                ["where" => ["id", "=", $sequence_id]]
                            ]
                        ];
                        $model_campaign_sequences->update($update_sequence_record, $condition);

                    } catch(\Exception $e) {
                        LoggerComponent::log($PROCESS_ID."Could not push sequence id # ".$sequence_id." at end of queue -- ".$e->getMessage(), $log_file_name);
                    }
                }

                // Update campaign status
                try {
                    
                    $condition = [
                        "fields" => [
                            "id",
                            "overall_progress"
                        ],
                        "where" => [
                            ["where" => ["id", "=", $campaign_id]]
                        ]
                    ]; 
                    $campaign_master_row = $model_campaign_master->fetch($condition);
                    
                    if($campaign_master_row["overall_progress"] != SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED")){
                        $condition = [
                            "where" => [
                                ["where" => ["id", "=", $campaign_id]]
                            ]
                        ];
                        $model_campaign_master->update($update_campaign_record, $condition);
                    }

                    $condition = [
                        "where" => [
                            ["where" => ["id", "=", $campaign_stage_id]]
                        ]
                    ];
                    $model_campaign_stages->update($update_stage_record, $condition);
                    
                    
                } catch(\Exception $e) {
                    LoggerComponent::log($PROCESS_ID."Error while updating progress for campaign for sequence # ".$sequence_id." -- ".$e->getMessage(), $log_file_name);
                }

                // Add database level log
                if (!$campaign_system_paused) {
                    $this->logDB($log_activity, $log_file_name);    
                }
            }

            if ($num_records_found == 0) {
                LoggerComponent::log($PROCESS_ID."No record found in queue to process", $log_file_name);                
                break; // break from loop
            }

            $current_time = time();
        }

        LoggerComponent::log($PROCESS_ID."Total ".$num_records_processed." records processed", $log_file_name);
        LoggerComponent::log($PROCESS_ID."Completed queue process and start updating campaign status", $log_file_name);

        // Get campaign ids for which sequence processed and check if any of finished
        try {
            $send_finish_report_ids = [];

            $finish_time    = DateTimeComponent::getDateTime();            
            $campaign_stage_ids   = implode($this->campaign_mail_info,",");            

            if (!empty($campaign_stage_ids)) {
                // Set parameters
                $query_params = [
                    "bulk_campaign" => $this->is_bulk_campaign
                ];
                // Other values for condition
                $other_values = [
                    "active"                => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                    "progress"    => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED"),
                    "campaign_finished"     => $campaign_stage_ids
                ];
                $rows_finished_campaigns_data   = $model_campaign_stages->getFinishedCampaigns($query_params, $other_values);
                $number_of_campaigns_to_process = count($rows_finished_campaigns_data);

                for($i=0; $i<$number_of_campaigns_to_process; $i++) {
                    $campaign_stage_id  = $rows_finished_campaigns_data[$i]["id"];
                    $campaign_id        = $rows_finished_campaigns_data[$i]["campaign_id"];
                    $current_stage      = $rows_finished_campaigns_data[$i]["stage"];
                    $total_stages       = $rows_finished_campaigns_data[$i]["total_stages"];

                    try {
                        $parent_status_to_set = ($total_stages > $current_stage) ? SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED") : SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH");
                        $save_data = [
                            "id"                => $campaign_id,
                            "overall_progress"  => $parent_status_to_set,
                            "modified"          => $finish_time
                        ];
                        $model_campaign_master->save($save_data);

                        $save_data = [];
                        $save_data = [
                            "id"            => $campaign_stage_id,
                            "finished_on"   => $finish_time,
                            "progress"      => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH"),
                            "modified"      => $finish_time
                        ];
                        $model_campaign_stages->save($save_data);

                        LoggerComponent::log($PROCESS_ID."Finishing campaign # ".$campaign_id, $log_file_name);

                        $send_finish_report_ids[] = $campaign_id;

                    } catch(\Exception $e) {
                        LoggerComponent::log($PROCESS_ID."Error while finishing campaign # ".$campaign_id." -- ".$e->getMessage(), $log_file_name);
                    }
                }
            }
        } catch(\Exception $e) {
            LoggerComponent::log($PROCESS_ID."Error while updating progress for campaign stage ids # ".$campaign_stage_ids." -- ".$e->getMessage(), $log_file_name);
        }

        // Add delay in campaigns where quota exceed
        if (empty($reminder) && !empty($campaigns_quota_exceed)) {
            foreach ($campaigns_quota_exceed as $cid => $val) {
                try {

                    $model_campaign_sequences->addDelayOnQuotaExceed($cid, SHAppComponent::getValue("app_constant/SEQ_PROGRESS_SCHEDULED"));

                } catch(Exception $e) {
                    LoggerComponent::log($PROCESS_ID."Could not re-schedule campaign stage id # ".$cid." -- ".$e->getMessage(), $log_file_name);
                }
            }
        }

        // Log queue process complete
        LoggerComponent::log("====================================", $log_file_name);

        // Check bounce of recent email sent
        if (empty($reminder)) {
            //$this->checkMailBounce();
        }
     
        // Send finish report of campaign to user
        if(!empty($send_finish_report_ids)) {            
            $this->sendCampaignReportMail($send_finish_report_ids, $PROCESS_ID);
        }

        // Send campaign paused emails to users
        if (!empty($system_paused_campaigns)) {
            $this->notifyCampaignUser($system_paused_campaigns, $log_file_name, $PROCESS_ID);
        }       
    }

    /**
    * Get pending queue and send emails [BULK]
    */
    public function actionProcessQueueBulk(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $this->is_bulk_campaign = 1;

        $this->actionProcessQueue($request, $response, $args);
    }

    /**
     * Check reply of email
     */
    public function actionCheckReply(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $current_time           = time();
        $end_time               = $current_time + self::REPLY_CHECK_TIME_LIMIT;
        $num_records_processed  = 0;

        $modulo             = isset($_SERVER['argv'][3]) ? $_SERVER['argv'][3] : "";
        $reminder           = isset($_SERVER['argv'][2]) ? $_SERVER['argv'][2] : "";
        $req_campaign_stage_id    = isset($_SERVER['argv'][4]) ? $_SERVER['argv'][4] : "";
     
        // Decide which log file to use
        if(!is_null($reminder) && !empty($modulo)){
            $log_file_name = self::LOG_FILE_CHECK_REPLY_DYNA.'_'.$reminder.'_'.$modulo.'.log';
        } else {
            $log_file_name = self::LOG_FILE_CHECK_REPLY;
        }
        
        //Create log directory if not exist
        if (!is_dir(self::LOG_FILE_DIRECTORY)) {
            mkdir(self::LOG_FILE_DIRECTORY, 0777, true);
        }
        // Log queue process start
        LoggerComponent::log("Starting reply check", $log_file_name);

        $model_campaign_stages          = new CampaignStages();
        $model_campaign_sequences       = new CampaignSequences();
        $model_account_sending_method   = new AccountSendingMethods();

        // store ids of user whose gmail account is not connected, and skipp it from re-checking
        $ignore_users   = [];

        // Get current date
        $current_date   = DateTimeComponent::getDateTime();

        $other_values   = [
            "deleted"                   => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
            "sequence_progress_sent"    => SHAppComponent::getValue("app_constant/SEQ_PROGRESS_SENT"),
            "sequence_progress_fail"    => SHAppComponent::getValue("app_constant/SEQ_PROGRESS_FAILED"),
        ];
        
        // Process queue with limited resources
        while ($end_time >= $current_time) {
            // Get the module wise query parameter
          
            // Get records for which to check reply
            $row_reply_check_records    = [];
            $number_of_records          = 0;
            try {
                $query_params = [
                    "status_active"     => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                    "flag_yes"          => SHAppComponent::getValue("app_constant/FLAG_YES"),
                    "is_bulk_campaign"  => $this->is_bulk_campaign,
                    "current_date"      => $current_date,
                    "seq_progress_sent" => SHAppComponent::getValue("app_constant/SEQ_PROGRESS_SENT"),
                    "modulo"            => $modulo,
                    "reminder"          => $reminder,
                ];

                $row_reply_check_records    = $model_campaign_sequences->getRecordsToCheckReply($req_campaign_stage_id, $query_params);
                $number_of_records          = count($row_reply_check_records);
                
            } catch(\Exception $e) {
                LoggerComponent::log("Could not fetch campaign sequence for check reply ---".$e->getMessage() , $log_file_name);
            }

            LoggerComponent::log($number_of_records . " records found.", $log_file_name);

            for ($c=0; $c<$number_of_records; $c++) {
                $num_records_processed++;

                // campaign ids of which bounce is found
                $campaign_ids_stats_update  = [];

                $sequence_id                = $row_reply_check_records[$c]["id"];
                $campaign_id                = $row_reply_check_records[$c]["campaign_id"];
                $campaign_stage_id          = $row_reply_check_records[$c]["campaign_stage_id"];
                $account_contact_id         = $row_reply_check_records[$c]["account_contact_id"];
                $message_send_id            = $row_reply_check_records[$c]["message_send_id"];
                $sent_response              = $row_reply_check_records[$c]["sent_response"];
                $open_count                 = (int) $row_reply_check_records[$c]["open_count"];
                $reply_check_count          = (int) $row_reply_check_records[$c]["reply_check_count"];
                $user_id                    = $row_reply_check_records[$c]["user_id"];
                $account_sending_method_id  = $row_reply_check_records[$c]["account_sending_method_id"];
               
                $update_sequence_record = [
                    "reply_last_checked"    => DateTimeComponent::getDateTime(),
                    "reply_check_count"     => $reply_check_count + 1,
                    "modified"              => DateTimeComponent::getDateTime(),
                ];

                LoggerComponent::log("Processing sequence id # ".$sequence_id. " campaign id # ".$campaign_id." stage id # ".$campaign_stage_id, $log_file_name);
                // Ignore user id which are in ignore list
                if(!in_array($user_id, $ignore_users)){
                    $res_sending_method         = $model_account_sending_method->getSendingMethodById($account_sending_method_id, $other_values);
                
                    if ($res_sending_method["status"] != SHAppComponent::getValue("app_constant/STATUS_ACTIVE")
                    || $res_sending_method["connection_status"] != SHAppComponent::getValue("app_constant/STATUS_ACTIVE")) 
                    {
                        LoggerComponent::log("User # ".$user_id." GMAIL account is not active.", $log_file_name);

                    }else{
                        $email_sending_method_id    = $res_sending_method["email_sending_method_id"];
                        $payload                    = json_decode($res_sending_method["payload"]);
                    
                        $refresh_token              = isset($payload->refresh_token)?$payload->refresh_token:"";
                        
                        $thread_id                  = "";
                    
                        if ($email_sending_method_id == SHAppComponent::getValue("email_sending_method/GMAIL")) {
                            try {
                                // Set access token values from sending method
                                $access_token["access_token"]    = isset($payload->access_token)?$payload->access_token: "";
                                $access_token["created"]    = isset($payload->created)?$payload->created: 0;
                                $access_token["expires_in"]    = isset($payload->expires_in)?$payload->expires_in: 0;
                                
                                // Check token is valid or not if expired then get new accesstoken
                                $access_token_check_value = GmailComponent::checkAndRefreshAccessToken($refresh_token, $access_token);
                                if($access_token_check_value["expired"]){
                                    $payload->access_token = $access_token_check_value["access_token"];
                                    $payload->created = $access_token_check_value["created"];
                                    $payload->expires_in = $access_token_check_value["expires_in"];  
                                                    
                                    $res_sending_method ["payload"]  = json_encode($payload);
                                    // Save new access token value to database.
                                    $model_account_sending_method->save($res_sending_method);
                
                                    // Set new access token value
                                    $access_token["access_token"]   =  $payload->access_token;
                                    $access_token["created"]        =  $payload->created;
                                    $access_token["expires_in"]     = $payload->expires_in;                 
                                } 
                                
                                if (!empty($sent_response)) {
                                    $sent_response_array = json_decode($sent_response, true);
                                    if (!empty($sent_response_array["thread_id"])) {
                                        $thread_id = $sent_response_array["thread_id"];
                                    }
                                }
                                // when thread is not available but message id is available
                                if(empty($thread_id) && !empty($message_send_id)) {
                                    $thread_id = GmailComponent::getThreadIdFromMessageId($message_send_id, $refresh_token, $access_token);
                                }

                                if (!empty($thread_id)) {
                                    $replied_array = GmailComponent::checkGmailReply($refresh_token, $message_send_id, $thread_id, $log_file_name, $access_token);
                                    
                                    if ($replied_array["bounce"]) {
                                        $update_sequence_record["is_bounce"]    = 1;
                                        $update_sequence_record["last_replied"] = DateTimeComponent::convertDateTime($replied_array["replied_at"],false);
                                        $update_sequence_record["progress"]     = SHAppComponent::getValue("app_constant/SEQ_PROGRESS_FAILED");
                                        $update_sequence_record["open_count"]   = 0;
                                        $update_sequence_record["replied"]      = 0;

                                        $campaign_ids_stats_update[]            = $campaign_stage_id;
                                        LoggerComponent::log("Mail Bounce for sequence id # ".$sequence_id. " -- replied array ".json_encode($replied_array), $log_file_name);

                                    } elseif ($replied_array["replied"]) {
                                        $update_sequence_record["replied"]          = 1;
                                        $update_sequence_record["last_replied"]     = DateTimeComponent::convertDateTime($replied_array["replied_at"],false);
                                                                        
                                        // If email count is 0, then update to at least 1
                                        if($open_count == 0) {
                                            $update_sequence_record["open_count"]   = 1;
                                            $update_sequence_record["last_opened"]  = DateTimeComponent::convertDateTime($replied_array["replied_at"],false);
                                        }
                                    }
                                } else {
                                    LoggerComponent::log("Message thread not found for sequence # ".$sequence_id, $log_file_name);
                                }

                            } catch(\Exception $e) {
                                LoggerComponent::log("Error while fetching thread Id for sequence # ".$sequence_id." -- ".$e->getMessage(), $log_file_name);
                            }
                        } else {
                            LoggerComponent::log("User # ".$user_id." GMAIL account is not logged in", $log_file_name);
                            $ignore_users[] = $user_id;
                        }
                    }
                } else {
                    LoggerComponent::log("Skipping sequence id # ".$sequence_id." [User GMAIL account not connected]", $log_file_name);
                }

                // Process record
                try {
                    $condition = [
                        "where" => [
                            ["where" => ["id", "=", $sequence_id]]
                        ]
                    ];
                    $model_campaign_sequences->update($update_sequence_record, $condition);

                    if(!empty($update_sequence_record["is_bounce"]) && !empty($replied_array["snippet"])) {
                        $log_activity = [
                            "campaign_id"           => $campaign_id,
                            "campaign_stage_id"     => $campaign_stage_id,
                            "campaign_sequence_id"  => $sequence_id,
                            "log"                   => $replied_array["snippet"],
                            "log_type"              => SHAppComponent::getValue("app_constant/LOG_TYPE_ERROR")
                        ];

                        // Add campaign logs activity
                        $this->logDB($log_activity, $log_file_name);
                    }else if(!empty($update_sequence_record["is_bounce"])){
                        $log_activity = [
                            "campaign_id"           => $campaign_id,
                            "campaign_stage_id"     => $campaign_stage_id,
                            "campaign_sequence_id"  => $sequence_id,
                            "log"                   => $replied_array["snippet"],
                            "log_type"              => SHAppComponent::getValue("app_constant/LOG_TYPE_ERROR")
                        ];

                        // Add campaign logs activity
                        $this->logDB($log_activity, $log_file_name);
                    }

                } catch(Exception $e) {
                    LoggerComponent::log("Error while updating reply count for sequence # ".$sequence_id." -- ".$e->getMessage(), $log_file_name);
                }

                // Check if campaign statistics to be updated
                if (!empty($campaign_ids_stats_update)) {
                    foreach ($campaign_ids_stats_update as $cid) {
                        $row_campaign_data = $model_campaign_stages->getSuccessCountById($cid, $other_values);

                        if (!empty($row_campaign_data)) {
                            LoggerComponent::log("Updating campaign # ".$cid." stats", $log_file_name);

                            try {
                                $update_campaign_data = [
                                    "id"            => $cid,
                                    "total_success" => $row_campaign_data["cnt_success"],
                                    "total_fail"    => $row_campaign_data["cnt_fail"],
                                ];
                                $model_campaign_stages->save($update_campaign_data);
                            } catch(\Exception $e) {
                                LoggerComponent::log("Error while updating campaign # ".$cid." stats -- ".$e->getMessage(), $log_file_name);
                            }
                        }
                    }
                }
            }           
            if ($number_of_records == 0) {
                LoggerComponent::log("No record found in queue to process", $log_file_name);
                break; // break from loop
            }

            $current_time = time();

            // If any single campaign reply/bounce tracking is asked, then only process 200 records
            if (!empty($req_campaign_stage_id) && $num_records_processed >= 200) {
                $current_time = $end_time + 10;              
            }
        }

        // Log complete
        LoggerComponent::log("Total ".$num_records_processed." records found to check", $log_file_name);
        LoggerComponent::log("Completed reply check", $log_file_name);
        LoggerComponent::log("====================================", $log_file_name);
    }

    /**
    * Check reply of email [BULK]
    */
    public function actionCheckReplyBulk(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $this->is_bulk_campaign = 1;

        $this->actionCheckReply($request, $response, $args);
    }

    /**
     * Check campaign replies final time before next stage is sent
     */
    public function actionFinalReplyCheck(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $current_time = time();
        $end_time = $current_time + self::FINAL_REPLY_CHECK_TIME_LIMIT;
        $num_records_processed = 0;
        $log_file_name = self::LOG_FILE_FINAL_CHECK_REPLY;
        $current_date = DateTimeComponent::getDateTime();

        // Fetch campaigns which are in queue in next 60 mins
        $duration_window = strtotime("now") + (60 * 60);

        //Create log directory if not exist
        if (!is_dir(self::LOG_FILE_DIRECTORY)) {
            mkdir(self::LOG_FILE_DIRECTORY, 0777, true);
        }
        
        // Log queue process start
        LoggerComponent::log("Start final reply check job", $log_file_name);

        $model_campaign_master = new CampaignMaster();
        $model_campaign_stages = new CampaignStages();
        $model_campaign_sequences = new CampaignSequences();

        $query_params = [
            "is_bulk_campaign" => $this->is_bulk_campaign,
            "duration_window" => $duration_window,
            "current_date" => $current_date
        ];

        // Other values for condition
        $other_values = [
            "active" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
            "campaign_scheduled" => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED"),
            "campaign_paused" => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED")
        ];

        while($end_time >= $current_time) {
            $rows_upcoming_followups = $model_campaign_master->getAboutToStartCampaigns($query_params, $other_values);

            if (empty($rows_upcoming_followups)) {
                break;
            }

            $campaign_id = $rows_upcoming_followups["id"];
            $next_run_at = $rows_upcoming_followups["scheduled_on"];
            $user_id = $rows_upcoming_followups["user_id"];
            $current_stage = $rows_upcoming_followups["stage"];
            $account_sending_method_id = $rows_upcoming_followups["account_sending_method_id"];
            $previous_stage_id = 0;

            // Get previous stage id
            $condition = [
                "fields" => [
                    "cst.id"
                ],
                "where" => [
                    ["where" => ["cst.campaign_id", "=", $campaign_id]],
                    ["where" => ["cst.stage", "=", $current_stage - 1]],
                    ["where" => ["cm.status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                ]
            ];
            $previous_stage_campaign = $model_campaign_stages->fetch($condition);

            if(!empty($previous_stage_campaign["id"])) {
                $previous_stage_id = $previous_stage_campaign["id"];
            }

            // Track reply
            $this->actionCampaignCheckReply($previous_stage_id, $user_id, $account_sending_method_id, 1, $log_file_name);

            $num_records_processed++;
            $current_time = time();
        }

        // Log complete
        LoggerComponent::log("====================================", $log_file_name);
        LoggerComponent::log("Total ".$num_records_processed." campaigns checked", $log_file_name);
        LoggerComponent::log("====================================", $log_file_name);
    }

    /**
     * Check campaign replies final time before next stage is sent [BULK]
     */
    public function actionFinalReplyCheckBulk(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $this->is_bulk_campaign = 1;

        $this->actionFinalReplyCheck($request, $response, $args);
    }

    /**
     * Campaign reply check
     */
    public function actionCampaignCheckReply(ServerRequestInterface $request, ResponseInterface $response, $args) {
        
        $req_campaign_stage_id      = isset($_SERVER['argv'][2]) ? $_SERVER['argv'][2] : "";
        $user_id                    = isset($_SERVER['argv'][3]) ? $_SERVER['argv'][3] : "";
        $account_sending_method_id  = isset($_SERVER['argv'][4]) ? $_SERVER['argv'][4] : "";
        $set_reply_off              = isset($_SERVER['argv'][5]) ? $_SERVER['argv'][5] : "";
        $log_file                   = isset($_SERVER['argv'][6]) ? $_SERVER['argv'][6] : "";

        $num_records_processed = 0;
        $log_file_name = $log_file;
        $req_campaign_stage_id = (int) $req_campaign_stage_id;
        $user_id = (int) $user_id;
        $has_bounce = false;
        $continue_reply_track = true;

        // Log queue process start
        LoggerComponent::log("Starting reply check for campaign stage # " . $req_campaign_stage_id, $log_file_name);

        if (empty($req_campaign_stage_id) || empty($user_id)) {
            LoggerComponent::log("Invalid campaign stage id: ". $req_campaign_stage_id . " OR user id: ". $user_id, $log_file_name);
            return;
        }

        $model_campaign_master = new CampaignMaster();
        $model_campaign_stages = new CampaignStages();
        $model_campaign_sequences = new CampaignSequences();
        $model_account_sending_method = new AccountSendingMethods();

        $other_values = [
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        // Get records for which to check reply
        $condition = [
            "fields" => [
                "csq.id",
                "csq.campaign_id",
                "csq.campaign_stage_id",
                "csq.account_contact_id",
                "csq.message_send_id",
                "csq.sent_response",
                "csq.open_count",
                "csq.reply_check_count"
            ],
            "where" => [
                ["where" => ["csq.campaign_stage_id", "=", $req_campaign_stage_id]],
                ["where" => ["csq.is_bounce", "=", 0]],
                ["where" => ["csq.replied", "=", 0]],
                ["where" => ["csq.progress", "=", SHAppComponent::getValue("app_constant/SEQ_PROGRESS_SENT")]]
            ]
        ];
        $row_reply_check_records = $model_campaign_sequences->fetchAll($condition);
        $number_of_records = count($row_reply_check_records);

        if ( $number_of_records > 0 ) {
            $res_sending_method = $model_account_sending_method->getSendingMethodById($account_sending_method_id, $other_values);
            
            $email_sending_method_id = $res_sending_method["email_sending_method_id"];
            $payload = json_decode($res_sending_method["payload"]);
            $refresh_token = isset($payload->refresh_token)?$payload->refresh_token:"";

            if ( $email_sending_method_id == SHAppComponent::getValue("email_sending_method/GMAIL") ) {

                 // Set access token values from sending method
                 $access_token["access_token"]    = isset($payload->access_token)?$payload->access_token: "";
                 $access_token["created"]    = isset($payload->created)?$payload->created: 0;
                 $access_token["expires_in"]    = 30;// isset($payload->expires_in)?$payload->expires_in: 0;
                 
                 // Check token is valid or not if expired then get new accesstoken
                 $access_token_check_value = GmailComponent::checkAndRefreshAccessToken($refresh_token, $access_token);
                 if($access_token_check_value["expired"]){
                     $payload->access_token = $access_token_check_value["access_token"];
                     $payload->created = $access_token_check_value["created"];
                     $payload->expires_in = $access_token_check_value["expires_in"];  
                                     
                     $res_sending_method ["payload"]  = json_encode($payload);
                     // Save new access token value to database.
                     $model_account_sending_method->save($res_sending_method);
 
                     // Set new access token value
                     $access_token["access_token"]   =  $payload->access_token;
                     $access_token["created"]        =  $payload->created;
                     $access_token["expires_in"]     = $payload->expires_in;                 
                 } 

                for ($i=0; $i<$number_of_records; $i++) {
                    $num_records_processed++;

                    $sequence_id        = $row_reply_check_records[$i]["id"];
                    $campaign_id        = $row_reply_check_records[$i]["campaign_id"];
                    $campaign_stage_id  = $row_reply_check_records[$i]["campaign_stage_id"];
                    $message_send_id    = $row_reply_check_records[$i]["message_send_id"];
                    $sent_response      = $row_reply_check_records[$i]["sent_response"];
                    $reply_check_count  = $row_reply_check_records[$i]["reply_check_count"];
                    $thread_id          = "";

                    $update_sequence_record = array(
                        "reply_last_checked" => DateTimeComponent::getDateTime(),
                        "reply_check_count" => $reply_check_count + 1,
                        "modified" => DateTimeComponent::getDateTime()
                    );

                    LoggerComponent::log("Processing sequence id # ".$sequence_id, $log_file_name);

                    try {
                        if (!empty($sent_response)) {
                            $sent_response_array = json_decode($sent_response, true);
                            if (!empty($sent_response_array["thread_id"])) {
                                $thread_id = $sent_response_array["thread_id"];
                            }
                        }
                        // when thread is not available but message id is available
                        if(empty($thread_id) && !empty($message_send_id)) {
                            $thread_id = GmailComponent::getThreadIdFromMessageId($message_send_id, $refresh_token);
                        }

                        if (!empty($thread_id)) {
                            $replied_array = GmailComponent::checkGmailReply($refresh_token, $message_send_id, $thread_id, $log_file);

                            if ($replied_array["bounce"]) {
                                $update_sequence_record["is_bounce"] = 1;
                                $update_sequence_record["last_replied"] = $replied_array["replied_at"];
                                $update_sequence_record["progress"] = SHAppComponent::getValue("app_constant/SEQ_PROGRESS_FAILED");
                                $update_sequence_record["open_count"] = 0;
                                $update_sequence_record["replied"] = 0;

                                $campaign_ids_stats_update[] = $campaign_stage_id;
                                $has_bounce = true;
                                LoggerComponent::log("Mail Bounce for sequence id # ".$sequence_id. " -- replied array ".json_encode($replied_array), $log_file_name);
                            } elseif ($replied_array["replied"]) {
                                $update_sequence_record["replied"] = 1;
                                $update_sequence_record["last_replied"] = $replied_array["replied_at"];

                                // If email count is 0, then update to at least 1
                                if($open_count == 0) {
                                    $update_sequence_record["open_count"] = 1;
                                    $update_sequence_record["last_opened"] = $replied_array["replied_at"];
                                }
                            }
                        } else {
                            LoggerComponent::log("Message thread not found sequence # ".$sequence_id, $log_file_name);
                        }

                        // Process record
                        try {
                            $condition = [
                                "where" => [
                                    ["where" => ["id", "=", $sequence_id]]
                                ]
                            ];
                            $model_campaign_sequences->update($update_sequence_record, $condition);

                            if (!empty($update_sequence_record["is_bounce"]) && !empty($replied_array["snippet"]) ) {
                                $log_activity = array(
                                    "campaign_id" => $campaign_id,
                                    "campaign_stage_id" => $campaign_stage_id,
                                    "campaign_sequence_id" => $sequence_id,
                                    "log" => $replied_array["snippet"],
                                    "log_type" => SHAppComponent::getValue("app_constant/LOG_TYPE_ERROR")
                                );

                                // Update campaign logs activity
                                $this->logDB($log_activity);
                            }

                        } catch(\Exception $e) {
                            LoggerComponent::log("Error while updating reply count for sequence # ".$sequence_id." -- ".$e->getMessage(), $log_file_name);
                        }

                    } catch(\Exception $e) {
                        LoggerComponent::log("Error while fetching thread Id for sequence # ".$sequence_id." -- ".$e->getMessage(), $log_file_name);
                    }
                }
            } else {
                LoggerComponent::log("User # ".$user_id." GMAIL account is not logged in", $log_file_name);
            }
        } else {
            $set_reply_off = 1;
        }

        // Update campaign performance
        $update_campaign_data = [];
        /*$update_campaign_data = array(
            "reply_last_checked" => DateTimeComponent::getDateTime()
        );*/
        if ($has_bounce) {
            // If bounce, then update statistics
            $row_campaign_data = $model_campaign_stages->getSuccessCountById($req_campaign_stage_id, $other_values);

            if(!empty($row_campaign_data)) {
                $update_campaign_data["total_success"] = $row_campaign_data["cnt_success"];
                $update_campaign_data["total_fail"] = $row_campaign_data["cnt_fail"];
            }
        }

        if ($set_reply_off) {
            // Set reply tracking to off
            $update_campaign_data["track_reply_max_date"] = DateTimeComponent::getDateTime();
        }

        LoggerComponent::log("Updating campaign # ".$req_campaign_stage_id." stats", $log_file_name);

        try {
            $condition = [
                "where" => [
                    ["where" => ["id", "=", $req_campaign_stage_id]]
                ]
            ];
            $model_campaign_stages->update($update_campaign_data, $condition);
        } catch(\Exception $e) {
            LoggerComponent::log("Error while updating campaign # ".$req_campaign_stage_id." stats -- ".$e->getMessage(), $log_file_name);
        }

        // Log complete
        LoggerComponent::log("Total ".$num_records_processed." records checked", $log_file_name);
        LoggerComponent::log("====================================", $log_file_name);
    }

    /**
     * Send report to user when campaign finished
     *
     * @param $campaign_ids: campaign ids
     * @param $PROCESS_ID: Logger variable
     */
    private function sendCampaignReportMail($campaign_ids, $PROCESS_ID=null) {
        
        $log_file_name = self::LOG_FILE_QPREPARE;
        if(!empty($PROCESS_ID)) {
            $log_file_name = self::LOG_FILE_QPROCESS;
        }

        // Validate request
        if(empty($campaign_ids)) {
            LoggerComponent::log($PROCESS_ID."No campaign ids passed to send report", $log_file_name);
            return;
        }

        $model_campaign_master = new CampaignMaster();
        $model_campaign_stages = new CampaignStages();
        $model_campaign_sequences = new CampaignSequences();
        
        try {
            // Get finished campaigns
            $condition = [
                "fields" => [
                    "cm.id as campaign_id",
                    "cm.account_id",
                    "cm.user_id",
                    "cm.title",
                    "cm.total_stages",
                    "cm.timezone",
                    "cm.track_reply",
                    "cst.id as campaign_stage_id",
                    "cst.subject",
                    "cst.stage",
                    "cst.stage_defination",
                    "cst.total_contacts",
                    "cst.total_success",
                    "cst.total_fail",
                    "cst.total_deleted",
                    "cst.started_on",
                    "cst.finished_on"
                ],
                "where" => [
                    ["where" => ["cst.progress", "=", SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH")]],
                    ["where" => ["cst.report_sent", "=", 0]],                    
                    ["whereIn" => ["cm.id", $campaign_ids]],
                    ["whereNotIn" => ["cm.user_id", array(15168, 24573)]]
                 
                ],
                "join" => [
                    "campaign_stages"
                ]
            ];
            $report_data = $model_campaign_master->fetchAll($condition);
          
            if ($report_data != NULL) {
                $base_url = BASE_URL;
                $create_campaign_link = $base_url . "campaigns/list";
                

                foreach ($report_data as $campaign_data) {

                    LoggerComponent::log($PROCESS_ID."Sending mail report of campaign id # ".$campaign_data["campaign_id"]." stage # ".$campaign_data["campaign_stage_id"], $log_file_name);

                    $campaign_title = $campaign_data["title"];
                    $campaign_subject = $campaign_data["subject"];

                    $stage_details = "";
                    if(empty($campaign_data["total_stages"])) {
                        $stage_details = " [Stage " . $campaign_data["stage"] . "]";
                    }else if($campaign_data["total_stages"] != $campaign_data['stage']){
                        $stage_details = " [Stage " . $campaign_data["stage"] . "]";
                    }

                    $started_at = DateTimeComponent::convertDateTimeCampaign($campaign_data["started_on"], true, \DEFAULT_TIMEZONE, $campaign_data["timezone"], \CAMPAIGN_DATE_FORMAT);
                    $finished_on = DateTimeComponent::convertDateTimeCampaign($campaign_data["finished_on"], true, \DEFAULT_TIMEZONE, $campaign_data["timezone"], \CAMPAIGN_DATE_FORMAT);
                        
                    $total_contacts = $campaign_data["total_contacts"];
                    $total_contacts = $total_contacts - $campaign_data["total_deleted"];                    
                    $total_success = $campaign_data["total_success"];
                    $total_fail = $campaign_data["total_fail"];
                    $total_bounced = 0;

                    // Get bounce emails
                    $condition = [
                        "fields" => [
                            "count(id) as cnt"
                        ],
                        "where" => [
                            ["where" => ["campaign_stage_id", "=", $campaign_data["campaign_stage_id"]]],
                            ["where" => ["is_bounce", "=", "1"]]
                        ]
                    ];
                    $row_bounce_count = $model_campaign_sequences->fetch($condition);
                    if(!empty($row_bounce_count["cnt"])) {
                        $total_bounced = (int) $row_bounce_count["cnt"];
                    }

                    $user_view_link = $base_url . "campaigns/". StringComponent::encodeRowId($campaign_data['campaign_id'])."/view"; 

                    $mail_subject = "Campaign Report - " . $campaign_title . $stage_details;
        
                    // Prepare message body
                    $mail_body = "Hello,<br /><br />";

                    if ($total_contacts > 0) {
                        $mail_body .= "Find the details of campaign <b>" . $campaign_title . $stage_details . "</b>.<br /><br />";
                        $mail_body .= "<b>Started @</b> " . $started_at . "<br />";
                        $mail_body .= "<b>Finished @</b> " . $finished_on . "<br />";
                        $mail_body .= "<b>Total Contacts:</b> " . $total_contacts . "<br />";
                        $mail_body .= "<b>Total Mail Sent:</b> " . $total_success . "<br />";

                        if($total_bounced == 0) {
                            $mail_body .= "<b>Total Mail Failed:</b> " . $total_fail . "<br /><br />";

                        } else if($total_bounced == 1) {
                            $mail_body .= "<b>Total Mail Failed:</b> " . $total_fail . " (".$total_bounced." mail bounced)<br /><br />";

                        } else {
                            $mail_body .= "<b>Total Mail Failed:</b> " . $total_fail . " (".$total_bounced." mails bounced)<br /><br />";
                        }

                        if($campaign_data["track_reply"] == 1) {
                            $mail_body .= "<b>Note</b> : expect some delay in bounce emails count, it is updated as and when gmail send the bounce notification to your mailbox.<br /><br />";
                        }

                    } else {
                        $mail_body .= "Saleshandy couldn't find any contacts for <b>" . $campaign_title . $stage_details . "</b>, hence the campaign is marked as <b>Finished</b>.<br /><br />";

                        if ( $campaign_data["stage"] == 1 ) {
                            $mail_body .= "Note : CSV file of recipient doesn't contain any record.<br />";
                        } else {
                            $stage_defination_array = json_decode($campaign_data["stage_defination"], true);
                            $stage_text = "Send email to recipients who have ###CONDITION### to the message from Stage 1 after ###DAYS### days";

                            if ( $stage_defination_array["condition"] == SHAppComponent::getValue("app_constant/CONDITION_NOT_OPEN") ) {
                                $stage_text = str_replace("###CONDITION###", "Not Open", $stage_text);
                            } elseif ( $stage_defination_array["condition"] == SHAppComponent::getValue("app_constant/CONDITION_NOT_REPLY") ) {
                                $stage_text = str_replace("###CONDITION###", "Not Replied", $stage_text);
                            } elseif ( $stage_defination_array["condition"] == SHAppComponent::getValue("app_constant/CONDITION_SENT") ) {
                                $stage_text = str_replace("###CONDITION###", "Sent", $stage_text);
                            } else {
                                $stage_text = str_replace("###CONDITION###", "Unknown", $stage_text);
                            }

                            $stage_text = str_replace("###DAYS###", $stage_defination_array["days"], $stage_text);

                            $mail_body .= "Note : the condition you specified for stage ".$campaign_data["stage"]." was <b>".$stage_text."</b>.<br />";
                        }

                        $mail_body .= "<br />";
                    }

                    $mail_body .= "Click <a href=\"" . $user_view_link . "\">here</a> to view report of this campaign.<br /><br />";
                    $mail_body .= "Create new auto followup campaign <a href=\"" . $create_campaign_link . "\">here</a>.<br /><br />";
                    $mail_body .= "Spare a moment and send us your <a href=\"http://bit.ly/feedbackaf\">valuable feedback</a> to improve the auto followup!<br /><br />";
                    $mail_body .= "-<br />";
                    $mail_body .= "Cheers,<br />";
                    $mail_body .= "Team <a href=\"https://www.saleshandy.com/\">Saleshandy</a><br />";
                    $mail_body .= "Find answers to your query from our <a href=\"https://help.saleshandy.com/help_center\">Help Center</a>";

                    try {
                        $model_user_master = new UserMaster();
                        $user_email = "";
                        $user = $model_user_master->getUserById($campaign_data["user_id"], $campaign_data["account_id"]);
                        if (!empty($user)) {
                            $user_email = $user["email"];
                        }

                        //Send email to user to 
                        $info["smtp_details"]["host"] = HOST;
                        $info["smtp_details"]["port"] = PORT;
                        $info["smtp_details"]["encryption"] = ENCRYPTION;
                        $info["smtp_details"]["username"] = USERNAME;
                        $info["smtp_details"]["password"] = PASSWORD;

                        $info["from_email"] = FROM_EMAIL;
                        $info["from_name"] = FROM_NAME;

                        $info["to"] = $user_email;
                        $info["cc"] = '';
                        $info["bcc"] = '';
                        $info["subject"] = $mail_subject;
                        $info["content"] = $mail_body;
                        
                        $result = TransactionMailsComponent::mailSendSmtp($info);
                      
                        if ($result["success"]) {
                            $save_data = [
                                "id" => $campaign_data["campaign_stage_id"],
                                "report_sent" => 1
                            ];
                            $model_campaign_stages->save($save_data);
                        }

                    } catch(\Exception $e) {
                        LoggerComponent::log($PROCESS_ID."Error while sending mail report of campaign id # ".$campaign_data["campaign_id"]." -- ".$e->getMessage(), $log_file_name);
                    }
                }
            }
        } catch(\Exception $e) {
            LoggerComponent::log($PROCESS_ID."Error while sending campaign report -- ".$e->getMessage(), $log_file_name);
        }
    }

 
    /**
     * Send campaign summary email after 24 hours of campaign finish
	 * for learn about performance of campaign
     */
    public function actionCampaignPerformanceEmail() {
        $log_file_name = self::LOG_FILE_PERFORMANCE_REPORT;
        $date = "2017-06-14 12:18:27";
        $compare_date = date("Y-m-d H:i:s", strtotime("-24 hours"));        
        $compare_date = DateTimeComponent::convertDateTime($compare_date, false);
        
        $model_campaign_stages = new CampaignStages();
        $model_user_master = new UserMaster();
        
        //Create log directory if not exist
        if (!is_dir(self::LOG_FILE_DIRECTORY)) {
            mkdir(self::LOG_FILE_DIRECTORY, 0777, true);
        }

        LoggerComponent::log("Starting for getting data for performance report.", $log_file_name);
        try{
            $condition = [
                "fields" => [
                    "cst.campaign_id",
                    "cm.timezone",
                    "cst.id as campaign_stage_id",
                    "cst.stage",
                    "cst.subject",                    
                    "cst.scheduled_on",
                    "cst.total_contacts",
                    "cst.total_success",
                    "cst.total_fail",
                    "cst.total_deleted",
                    "cst.status",
                    "cst.user_id",
                    "cst.account_id",
                    "cm.title",
                    "cm.track_reply",
                    "cm.track_click", 
                    "(SELECT COUNT(csqo.id) FROM campaign_sequences csqo 
                                    WHERE csqo.campaign_stage_id = cst.id 
                                    AND csqo.status <> ".SHAppComponent::getValue("app_constant/STATUS_DELETE")." 
                                    AND csqo.open_count > 0 AND csqo.is_bounce = 0) AS open_cnt", 
                    "(SELECT COUNT(csqr.id) FROM campaign_sequences csqr 
                                    WHERE csqr.campaign_stage_id = cst.id 
                                    AND csqr.status <> ".SHAppComponent::getValue("app_constant/STATUS_DELETE")." 
                                    AND csqr.replied > 0 AND csqr.is_bounce = 0) AS reply_cnt",
                    "(SELECT count(DISTINCT(cln.campaign_sequence_id)) from campaign_links cln INNER JOIN campaign_sequences csql 
                                    where cln.campaign_stage_id = cst.id 
                                    AND csql.status <> ".SHAppComponent::getValue("app_constant/STATUS_DELETE")."
                                    AND csql.is_bounce = 0 
                                    AND cln.total_clicked > 0) AS total_clicked" 
                ],
                "where" => [
                    ["where" => ["cst.report_sent", "=", 1]],
                    ["where" => ["cst.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]],
                    ["where" => ["cst.started_on", ">=", $date]],
                    ["where" => ["cst.finished_on", "<=", $compare_date]],                                    
                    ["whereNotIn" => ["cm.user_id", array (15168, 24573, 36639, 27633, 25969, 38852, 38853)]]                   
                ],
                "join" => "campaign_master"
            ];
          
            $campaign_records = $model_campaign_stages->fetchAll($condition);                
        }catch(\Exception $e){     
          LoggerComponent::log("Error while fetching campaign Data  -- ".$e->getMessage(), $log_file_name);
        }
        if(!empty($campaign_records)){
            $base_url = BASE_URL;
            $create_campaign_link = $base_url . "campaigns/list";
          

            foreach ($campaign_records as $campaign_record) {

                LoggerComponent::log("Sending performance report of campaign id # ".$campaign_record["campaign_id"]. " stage id # ".$campaign_record["campaign_stage_id"] , $log_file_name);
                $campaign_data = array();
                $user_id = $campaign_record['user_id'];
                try{
                  
                    $user_email = "";
                    $user = $model_user_master->getUserById($campaign_record["user_id"], $campaign_record["account_id"]);
                    if (!empty($user)) {
                        $user_email = $user["email"];
                    }
                    
                    $userName = $user["first_name"] ." ". $user["last_name"];
                }catch(Exception $e){
                    LoggerComponent::log("Error while getUserById for campaign id # ".$campaign_record["campaign_id"]. " stage id # ".$campaign_record["campaign_stage_id"]." -- ".$e->getMessage(), $log_file_name);
                }
                $total_contacts = ($campaign_record['total_contacts'] - $campaign_record['total_deleted']);
                $open_count_percentage = $this->getPerformanceText($campaign_record['open_cnt'],$total_contacts);
    
                $reply_count_percentage = "";
                if( !empty($campaign_record['track_reply']) && $campaign_record['track_reply'] == 1 ) {
                    $reply_count_percentage = $this->getPerformanceText($campaign_record['reply_cnt'],$total_contacts);	
                }
                $click_count = "";
                if( !empty($campaign_record['track_click']) && $campaign_record['track_click'] == 1 ) {
                    $click_count = $this->getClickcount($campaign_record['total_clicked'],$total_contacts);
                }

                $sent_percentage = $this->getPerformanceText($campaign_record['total_success'],$total_contacts);

             
                $view_campaign_link = $base_url . "campaigns/".StringComponent::encodeRowId($campaign_record['campaign_id'])."/view"; 
                

                $campaign_data['open_count_percentage'] = $open_count_percentage;
                $campaign_data['reply_count_percentage'] =$reply_count_percentage;
                $campaign_data['click_count'] = $click_count;
                $campaign_data['sent_percentage'] = $sent_percentage;
                $campaign_data['report_url'] = $view_campaign_link;				

                $campaign_record = array_merge($campaign_record,$campaign_data);
                
                $campaign_record["scheduled_on"]  = DateTimeComponent::convertDateTimeCampaign($campaign_record['scheduled_on'],true,\DEFAULT_TIMEZONE,$campaign_record["timezone"],\APP_TIME_FORMAT)." ".$campaign_record["timezone"];
                
                $campaign_subject = "".$campaign_record['title']." (Stage ".$campaign_record['stage'].") - Performance Report";
                
                $body =  file_get_contents(\EMAIL_TEPLATES_FOLDER . "/campaign_performance_report_email.html");
               
                $body = str_replace("{FirstName}", $userName, $body);
                $body = str_replace("{title}", $campaign_record["title"],$body);
                $body = str_replace("{scheduled_on}", $campaign_record["scheduled_on"],$body);
            
                $body = str_replace("{sent_percentage}", $campaign_record["sent_percentage"],$body);
                $body = str_replace("{open_count_percentage}", $campaign_record["open_count_percentage"],$body);
                
                // Regex to replace click count if track count is true else remove that <th> tag
                $regex = '^\<th [a-zA-Z0-9= ;>":-]+{click_count}[% ]+<\/th>+^';
                preg_match_all($regex, $body, $click_count_replace_regex);                
                if( !empty($campaign_record['track_click']) && $campaign_record['track_click'] == 1 ) {
                    $body = str_replace("{click_count}", $campaign_record["click_count"],$body);
                }else if(!empty($click_count_replace_regex)) {                    
                    $body = str_replace($click_count_replace_regex[0], "",$body);
                }
                
                // Regex to replace reply count percentage if track reply is true else remove that <th> tag
                $regex = '^\<th [a-zA-Z0-9= ;>":-]+{reply_count_percentage}[% ]+<\/th>+^';
                preg_match_all($regex, $body, $reply_count_percentage_replace_regex);        
                if( !empty($campaign_record['track_reply']) && $campaign_record['track_reply'] == 1 ) {
                    $body = str_replace("{reply_count_percentage}", $campaign_record["reply_count_percentage"],$body);                
                }else if(!empty($reply_count_percentage_replace_regex)) {
                    $body = str_replace($reply_count_percentage_replace_regex[0], "",$body);
                }

                // Regex to remove table header <th> which contains CTR text if track click is false
                $regex = '^\<th [a-zA-Z0-9= ;>":-]+#[a-zA-Z0-9:; ">]+CTR<\/th>+^';
                preg_match_all($regex, $body, $ctr_replace_regex);   
                
                if(empty($campaign_record['track_click']) && !empty($ctr_replace_regex)) {
                    $body = str_replace($ctr_replace_regex[0], "",$body);
                }
               
                // Regex to remove table header <th> which contains Replied text if track reply is false
                $regex = '^\<th [a-zA-Z0-9= ;>":-]+#[a-zA-Z0-9:; ">]+Replied<\/th>+^';
                preg_match_all($regex, $body, $replied_replace_regex);   
                if(empty($campaign_record['track_reply']) && !empty($replied_replace_regex)) {
                    $body = str_replace($replied_replace_regex[0], "",$body);
                }  
              
                $body = str_replace("{total_success}", $campaign_record["total_success"],$body);
                $body = str_replace("{open_cnt}", $campaign_record["open_cnt"],$body);

                // Regex to replace total clicked count if track click is true else remove that <td> tag
                $regex = '^\<td [a-zA-Z0-9= ;>":-]+#[a-zA-Z0-9:; ">]+{total_clicked}+[a-zA-Z ]+<\/td>+^';
                preg_match_all($regex, $body, $total_clicked_replace_regex);   
              
                if( !empty($campaign_record['track_click']) && $campaign_record['track_click'] == 1 ) {
                    $body = str_replace("{total_clicked}", $campaign_record["total_clicked"],$body);
                }else if(!empty($total_clicked_replace_regex)) {
                    $body = str_replace($total_clicked_replace_regex[0], "",$body);
                }
               
                // Regex to replace reply count if track reply is true else remove that <td> tag
                $regex = '^\<td [a-zA-Z0-9= ;>":-]+#[a-zA-Z0-9:; ">]+{reply_cnt}+<\/td>+^';
                preg_match_all($regex, $body, $reply_cnt_replace_regex); 
              
                if( !empty($campaign_record['track_reply']) && $campaign_record['track_reply'] == 1 ) {
                    $body = str_replace("{reply_cnt}", $campaign_record["reply_cnt"],$body);
                }else if(!empty($reply_cnt_replace_regex)) {
                    $body = str_replace($reply_cnt_replace_regex[0], "",$body);
                }                

                $body = str_replace("{report_url}", $campaign_record["report_url"],$body);
                
                try {
                    
                    //Send email to user to 
                    $info["smtp_details"]["host"] = HOST;
                    $info["smtp_details"]["port"] = PORT;
                    $info["smtp_details"]["encryption"] = ENCRYPTION;
                    $info["smtp_details"]["username"] = USERNAME;
                    $info["smtp_details"]["password"] = PASSWORD;

                    $info["from_email"] = FROM_EMAIL;
                    $info["from_name"] = FROM_NAME;

                    $info["to"] = $user_email;
                    $info["cc"] = '';
                    $info["bcc"] = '';
                    $info["subject"] = $campaign_subject;
                    $info["content"] = $body;
                 
                    $result = TransactionMailsComponent::mailSendSmtp($info);
               
                    if ($result["success"]) {
                        $save_data = [
                            "id" => $campaign_record["campaign_stage_id"],
                            "report_sent" => 2
                        ];
                        $model_campaign_stages->save($save_data);
                       
                        LoggerComponent::log("Performance report send successfully for campaign id # ".$campaign_record["campaign_id"]. " stage id # ".$campaign_record["campaign_stage_id"], $log_file_name);    
                    }else{
                        LoggerComponent::log("Sending performance report Failed for campaign id # ".$campaign_record["campaign_id"]. " stage id # ".$campaign_record["campaign_stage_id"], $log_file_name);    
                    }
                    
                } catch(\Exception $e) {
                    LoggerComponent::log("Error while sending mail report of campaign id # ".$campaign_record["campaign_id"]. " stage id # ".$campaign_record["campaign_stage_id"]." -- ".$e->getMessage(), $log_file_name);
                }
               
            }
        }else{
            LoggerComponent::log("No records found for send performance report. " , $log_file_name);
        }

        LoggerComponent::log("-------------------------------------------------------------------------" , $log_file_name);
    }
    
  

    private function getPerformanceText($input=0, $compare=0) {
		$ratio = 0;

		if(!empty($compare)) {
			$ratio = round(($input * 100) / $compare);
		}

		return $ratio;
	}

	private function getClickcount($input=0, $compare=0) {
		$ratio = 0;

		if(!empty($compare)) {
			$ratio = round(($input * 100) / $compare);
			$temp_data = ($input / $compare) * 100;	
			$ratio = number_format($temp_data,0);
		}

		return $ratio;
	}
    /**
     * Log data to database
     */
    private function logDB($log, $log_file_name) {
        try {
            $model_campaign_logs = new CampaignLogs();

            $campaign_sequence_id = (isset($log["campaign_sequence_id"]) && $log["campaign_sequence_id"] != "") ? $log["campaign_sequence_id"] : NULL;
            $save_data = [
                "campaign_id" => $log["campaign_id"],
                "campaign_stage_id" => $log["campaign_stage_id"],
                "campaign_sequence_id" => $campaign_sequence_id,
                "log" => $log["log"],
                "log_type" => $log["log_type"],
                "created" => DateTimeComponent::getDateTime()
            ];
            $model_campaign_logs->save($save_data);
        } catch(\Exception $e) {
            LoggerComponent::log("Error while saving campaign log -- ".$e->getMessage(), $log_file_name);    
        }
    }


    /** ---- Patch 06 Nov 2017 ---- **/

    /**
     * Check if duplicate email sending is restricted for user
     */
    private function checkIfDuplicateRestrict($user_id) {
        $restricted = false;
        $this->customer_user_ids = [];

        $model_user_master = new UserMaster();

        $condition = [
            "fields" => [
                "account_id"
            ],
            "where" => [
                ["where" => ["id", "=", $user_id]]
            ]
        ];
        $details = $model_user_master->fetch($condition);
        if (!empty($details["account_id"])) {
            if ( in_array($details["account_id"], $this->unique_email_sending_customer_id) ) {
                $restricted = true;

                $condition = [
                    "fields" => [
                        "id"
                    ],
                    "where" => [
                        ["where" => ["account_id", "=", $details["account_id"]]]
                    ]
                ];
                $get_all_users = $model_user_master->fetchAll($condition);
                foreach($get_all_users as $user) {
                    $this->customer_user_ids[] = $user["id"];
                }
            }
        }

        return $restricted;
    }

    /**
     * Check if email already in queue
     */
    private function checkIfEmailAlreadyExists($email) {
        $already_in_queue = false;

        $model_campaign_sequences = new CampaignSequences();

        if(!empty($this->customer_user_ids)) {
            $users = implode(",", $this->customer_user_ids);

            try {
                $record_exists = $model_campaign_sequences->checkEmailExists($users, $email);

                if(!empty($record_exists["id"])) {
                    $already_in_queue = true;
                }

            } catch(\Exception $e) {
                // Do nothing
            }
        }

        return $already_in_queue;
    }

    /**
    * Notify user about campaign status change
    * 
    * @param $campaign_payload: campaign information
    * @param $log_file_path: log file
    * @param $PROCESS_ID: Logger variable
    */
    private function notifyCampaignUser($campaign_payload, $log_file_path, $PROCESS_ID) {
        foreach ($campaign_payload as $campaign_id => $payload) {
            $valid          = false;
            $case           = $payload["case"];
            $email          = $payload["email"];
            $campaign_title = $payload["title"];
            $campaign_stage = $payload["stage"];

            if ($campaign_stage > 1) {
                $campaign_title .= " [Stage ".$campaign_stage."]";
            }

            $base_url                   = BASE_URL;
            $view_campaign_link         = $base_url . "campaigns/".StringComponent::encodeRowId($campaign_id)."/view";
            $connect_gmail_account_link = $base_url . "mail-accounts";
            $connect_smtp_account_link  = $base_url . "mail-accounts";
            $fail_bounce_blog_link      = "https://www.saleshandy.com/blog/email-delivery/";

            $mail_subject   = "Campaign Paused, Action Required - ".$campaign_title;
            $mail_body      = "Hello,<br /><br />";
            $mail_body      .= "Your campaign <a href=\"".$view_campaign_link."\"><b>" . $campaign_title . "</b></a> has been Paused due to some reason.<br /><br />";

            switch ($case) {
                case self::SYSTEM_PAUSE_REASON_GMAIL:
                    $mail_body .= "Reason: <b>Your Gmail account has been disconnected.</b> ";
                    $mail_body .= "Please re-connect your google account from SalesHandy <a href=\"".$connect_gmail_account_link."\">profile page</a>.";
                    $valid = true;
                    break;

                case self::SYSTEM_PAUSE_REASON_SMTP:
                    $mail_body .= "Reason: <b>Your SMTP account is not connected.</b> ";
                    $mail_body .= "Please verify your <a href=\"".$connect_smtp_account_link."\">SMTP account settings</a>.";
                    $valid = true;
                    break;

                case self::SYSTEM_PAUSE_REASON_FAIL:
                case self::SYSTEM_PAUSE_REASON_BOUNCE:
                    $mail_body .= "Reason: <b>We have noticed frequent email failure / bounces for this campaign.</b> ";
                    $mail_body .= "Please resume campaign after removing the invalid emails. <a href=\"".$fail_bounce_blog_link."\">Know More</a>";
                    $valid = true;
                    break;

                case self::SYSTEM_PAUSE_REASON_GMAIL_AUTH:
                    $mail_body .= "Reason: <b>Error in connecting your Gmail account.</b> ";
                    $mail_body .= "SalesHandy app is not able to connect to your Gmail account, please re-connect your Gmail account from SalesHandy <a href=\"".$connect_gmail_account_link."\">profile page</a>.";
                    $valid = true;
                    break;

                case self::SYSTEM_PAUSE_REASON_SMTP_AUTH:
                    $mail_body .= "Reason: <b>Error in connecting your SMTP account.</b> ";
                    $mail_body .= "SalesHandy app is not able to connect to your SMTP account, please re-connect your <a href=\"".$connect_smtp_account_link."\">SMTP account settings</a>.";
                    $valid = true;
                    break;

                default:
                    break;
            }

            if ($valid) {
                $mail_body .= "<br /><br />";
                $mail_body .= "Spare a moment and send us your <a href=\"http://bit.ly/feedbackaf\">valuable feedback</a> to improve the auto followup!<br /><br />";
                $mail_body .= "-<br />";
                $mail_body .= "Cheers,<br />";
                $mail_body .= "Team <a href=\"https://www.saleshandy.com/\">Saleshandy</a><br />";
                $mail_body .= "Find answers to your query from our <a href=\"https://help.saleshandy.com/help_center\">Help Center</a>";

                try {
                    $info["smtp_details"]["host"]       = HOST;
                    $info["smtp_details"]["port"]       = PORT;
                    $info["smtp_details"]["encryption"] = ENCRYPTION;
                    $info["smtp_details"]["username"]   = USERNAME;
                    $info["smtp_details"]["password"]   = PASSWORD;

                    $info["from_email"]                 = FROM_EMAIL;
                    $info["from_name"]                  = FROM_NAME;

                    $info["to"]                         = $email;
                    $info["cc"]                         = "";
                    $info["bcc"]                        = "webmaster@saleshandy.com";
                    $info["subject"]                    = $mail_subject;
                    $info["content"]                    = $mail_body;
                    
                    $status = TransactionMailsComponent::mailSendSmtp($info);
                    if(isset($status["success"]) && !$status["success"]){
                        $errorMessage = isset($status["message"]) ? $status["message"] : "";
                        LoggerComponent::log($PROCESS_ID."Fail to send email to user for campaign id # ".$errorMessage, $log_file_path);
                    }
                } catch(Exception $e) {
                    LoggerComponent::log($PROCESS_ID."Could not send email to user for campaign id # ".$campaign_id." -- ".$e->getMessage(), $log_file_path);
                }
            }
        }
    }

    /**
     * Check if duplicate email sending is restricted for blocklist domain
     */
    private function checkForDomainRestrict($email, $account_id) {
        $domain_is_in_queue = false;
        
        $model_domain_blocklist = new CampaignDomainBlocklist();
        
        if(!empty($email) && !empty($account_id)) {
          
            try {
                $email_array = explode("@", $email);
                $domain = end($email_array);
                $condition = [];
                
                    try {
                        $condition = [
                            "fields" => [
                                "id"
                            ],
                            "where" => [
                                ["where" => ["account_id", "=", (int) $account_id]],
                                ["where" => ["domain", "=", str_replace("'", "\'", $domain)]],
                                ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                            ]
                        ];
                        $record_exists = $model_domain_blocklist->fetch($condition);
                        if(!empty($record_exists['id'])) {
                            $domain_is_in_queue = true;
                        }

                    } catch(Exception $e) { }
                

            } catch(Exception $e) { }

            return $domain_is_in_queue;
        }
    }

    /**
     * Check if contact is unsubscribed or not
     */
    private function checkForContactUnsubscribe($email, $account_id) {
        $is_contact_blocked = false;
        
        $model_account_contacts = new AccountContacts();
        
        if(!empty($email) && !empty($account_id)) {
          
            try {
                
                $condition = [];
                
                    try {
                        $condition = [
                            "fields" => [
                                "id"
                            ],
                            "where" => [
                                ["where" => ["account_id", "=", (int) $account_id]],
                                ["where" => ["email", "=", $email]],
                                ["where" => ["is_blocked", "=", SHAppComponent::getValue("app_constant/FLAG_YES")]]
                            ]
                        ];
                        $record_exists = $model_account_contacts->fetch($condition);
                        
                        if(!empty($record_exists['id'])) {
                            $is_contact_blocked = true;
                        }

                    } catch(Exception $e) { }
                

            } catch(Exception $e) { }

            return $is_contact_blocked;
        }
    }
}