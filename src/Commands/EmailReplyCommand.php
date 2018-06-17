<?php
/**
 * Used to check reply for emails
 */
namespace App\Commands;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Interop\Container\ContainerInterface;
use \App\Components\SHAppComponent;
use \App\Components\DateTimeComponent;
use \App\Components\LoggerComponent;
use \App\Components\Mailer\GmailComponent;
use \App\Components\Mailer\SMTPComponent;
use \App\Components\Mailer\TransactionMailsComponent;
use \App\Models\AccountSendingMethods;
use \App\Models\EmailMaster;
use \App\Models\EmailTrackHistory;

class EmailReplyCommand extends AppCommand {
    
    //define constant for process cron execute limt 
    const REPLY_CHECK_TIME_LIMIT                = 180; // no of seconds
    const REPLY_CHECK_RECORDS_LIMIT             = 200; // no of records
    
    //define constant for process prepare log file 
    const LOG_FILE_DIRECTORY                    = __DIR__ . "/../../logs";
    const LOG_FILE_CHECK_REPLY                  = self::LOG_FILE_DIRECTORY . "/em_reply_check_process.log";
    const LOG_FILE_CHECK_REPLY_DYNA             = self::LOG_FILE_DIRECTORY . "/em_reply_check_process";
        
    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Check reply of email
     */
    public function actionCheckEmailReply(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $current_time           = time();
        $end_time               = $current_time + self::REPLY_CHECK_TIME_LIMIT;
        $num_records_processed  = 0;
        $flag_yes               = SHAppComponent::getValue("app_constant/FLAG_YES");
        $flag_no                = SHAppComponent::getValue("app_constant/FLAG_NO");

        $modulo             = isset($_SERVER['argv'][3]) ? $_SERVER['argv'][3] : "";
        $reminder           = isset($_SERVER['argv'][2]) ? $_SERVER['argv'][2] : "";
        
     
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

        $model_email_master          = new EmailMaster();
        $model_email_track_history   = new EmailTrackHistory();
        
        $model_account_sending_method   = new AccountSendingMethods();

        // store ids of user whose gmail account is not connected, and skip it from re-checking
        $ignore_users   = [];

        // Get current date
        $current_date   = DateTimeComponent::getDateTime();

        $other_values   = [
            "deleted"      => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];
        
        // Process queue with limited resources
        while ($end_time >= $current_time) {
            // Get the module wise query parameter
          
            // Get records for which to check reply
            $row_reply_check_records    = [];
            $number_of_records          = 0;
            
            try{
                $condition = [
                    "fields" => [
                        "em.id",
                        "em.account_id",
                        "em.user_id",
                        "em.track_reply",
                        "em.sent_message_id",
                        "em.sent_response",
                        "em.open_count",
                        "em.reply_check_count",
                        "em.progress",
                        "em.status",
                        "em.account_sending_method_id"
                    ],
                    "where" => [
                        ["where" => ["em.track_reply", "=", $flag_yes]],
                        ["where" => ["em.progress", "=", SHAppComponent::getValue("app_constant/PROGRESS_SENT")]],
                        ["where" => ["em.status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]],
                        ["where" => ["em.is_bounce", "=", $flag_no]],
                        ["where" => ["em.reply_count", "=", $flag_no]]
                    ],
                    "order_by" =>[
                        "em.reply_last_checked"
                    ],
                    "limit" => self::REPLY_CHECK_RECORDS_LIMIT
                ];

                if (!empty($modulo) && !is_null($reminder)) {
                    $condition["where"][] = ["where" => ["em.id % " . $modulo, "=", $reminder]];
                }
                
                $row_reply_check_records = $model_email_master->fetchAll($condition);

                $number_of_records       = count($row_reply_check_records);
                
            }catch(\Exception $e) {
                LoggerComponent::log("Could not fetch emails for check reply ---".$e->getMessage() , $log_file_name);
            }
          
            LoggerComponent::log($number_of_records . " records found.", $log_file_name);
            
            for ($c = 0; $c < $number_of_records; $c++) {
                $num_records_processed++;

                // email ids of which bounce is found
                $email_ids_stats_update  = [];

                $email_id                   = $row_reply_check_records[$c]["id"];
                $account_id                 = $row_reply_check_records[$c]["account_id"];
                $sent_message_id            = $row_reply_check_records[$c]["sent_message_id"];
                $sent_response              = $row_reply_check_records[$c]["sent_response"];
                $open_count                 = (int) $row_reply_check_records[$c]["open_count"];
                $reply_check_count          = (int) $row_reply_check_records[$c]["reply_check_count"];
                $user_id                    = $row_reply_check_records[$c]["user_id"];
                $account_sending_method_id  = $row_reply_check_records[$c]["account_sending_method_id"];
                
                $update_email_record = [
                    "reply_last_checked"    => $current_date,
                    "reply_check_count"     => $reply_check_count + $flag_yes,
                    "modified"              => $current_date,
                ];

                LoggerComponent::log("Processing email id # ".$email_id, $log_file_name);
                // Ignore user id which are in ignore list
               
                if(!in_array($user_id, $ignore_users)){
                    $res_sending_method         = $model_account_sending_method->getSendingMethodById($account_sending_method_id, $other_values);
                    
                    $email_sending_method_id    = $res_sending_method["email_sending_method_id"];
                    $payload                    = json_decode($res_sending_method["payload"]);
                   
                    $refresh_token              = isset($payload->refresh_token)?$payload->refresh_token:"";
                    
                    $thread_id                  = "";
                   
                    if ($email_sending_method_id == SHAppComponent::getValue("email_sending_method/GMAIL")) {
                        try {
                            // Set access token values from sending method
                            $access_token["access_token"]    = isset($payload->access_token)?$payload->access_token: "";
                            $access_token["created"]         = isset($payload->created)?$payload->created: 0;
                            $access_token["expires_in"]      = isset($payload->expires_in)?$payload->expires_in: 0;
                            
                            // Check token is valid or not if expired then get new accesstoken
                            $access_token_check_value = GmailComponent::checkAndRefreshAccessToken($refresh_token, $access_token);
                            if($access_token_check_value["expired"]){
                                $payload->access_token = $access_token_check_value["access_token"];
                                $payload->created = $access_token_check_value["created"];
                                $payload->expires_in = $access_token_check_value["expires_in"];  
                                                
                                $res_sending_method["payload"]  = json_encode($payload);
                                // Save new access token value to database.
                                $model_account_sending_method->save($res_sending_method);
            
                                // Set new access token value
                                $access_token["access_token"]   =  $payload->access_token;
                                $access_token["created"]        =  $payload->created;
                                $access_token["expires_in"]     =  $payload->expires_in;                 
                            } 
                            
                            if (!empty($sent_response)) {
                                $sent_response_array = json_decode($sent_response, true);
                                if (!empty($sent_response_array["thread_id"])) {
                                    $thread_id = $sent_response_array["thread_id"];
                                }
                            }
                            // when thread is not available but message id is available
                            if(empty($thread_id) && !empty($sent_message_id)) {
                                $thread_id = GmailComponent::getThreadIdFromMessageId($sent_message_id, $refresh_token, $access_token);
                            }

                            if (!empty($thread_id)) {
                                $replied_array = GmailComponent::checkGmailReply($refresh_token, $sent_message_id, $thread_id, $log_file_name, $access_token);
                               
                                if ($replied_array["bounce"]) {
                                    $update_email_record["is_bounce"]    = $flag_yes;
                                    $update_email_record["last_replied"] = DateTimeComponent::convertDateTime($replied_array["replied_at"],false);
                                    $update_email_record["progress"]     = SHAppComponent::getValue("app_constant/PROGRESS_FAILED");
                                    $update_email_record["open_count"]   = $flag_no;
                                    $update_email_record["reply_count"]  = $flag_no;

                                    $email_ids_stats_update[]            = $email_id;
                                    LoggerComponent::log("Mail Bounce for email id # ".$email_id. " -- replied array ".json_encode($replied_array), $log_file_name);

                                } elseif ($replied_array["replied"]) {
                                    $update_email_record["reply_count"]      = $flag_yes;
                                    $update_email_record["last_replied"]     = DateTimeComponent::convertDateTime($replied_array["replied_at"],false);
                                    
                                    //if reply found store in email track history
                                    try {
                                        $update_email_history = [
                                            "email_id" => $email_id,
                                            "type"     => SHAppComponent::getValue("app_constant/EMAIL_TRACK_REPLY"),
                                            "acted_at" => DateTimeComponent::convertDateTime($replied_array["replied_at"],false),
                                        ];

                                        $reply_history = $model_email_track_history->save($update_email_history);
                                    } catch(\Exception $e) {
                                        LoggerComponent::log("Error while updating reply track history for email # ".$email_id." -- ".$e->getMessage(), $log_file_name);
                                    }
                                    // If email open count is 0, then update to at least 1
                                    if($open_count == $flag_no) {
                                        $update_email_record["open_count"]   = $flag_yes;
                                        $update_email_record["last_opened"]  = DateTimeComponent::convertDateTime($replied_array["replied_at"],false);
                                    }
                                }
                            } else {
                                LoggerComponent::log("Message thread not found for email # ".$email_id, $log_file_name);
                            }

                        } catch(\Exception $e) {
                            LoggerComponent::log("Error while fetching thread Id for email # ".$email_id." -- ".$e->getMessage(), $log_file_name);
                        }
                    } else {

                        LoggerComponent::log("User # ".$user_id." GMAIL account is not logged in", $log_file_name);
                        $ignore_users[] = $user_id;
                    }
                } else {
                    LoggerComponent::log("Skipping email id # ".$email_id." [User GMAIL account not connected]", $log_file_name);
                }

                // Process record
                try {
                    $condition = [
                        "where" => [
                            ["where" => ["id", "=", $email_id]]
                        ]
                    ];

                   $response =  $model_email_master->update($update_email_record, $condition);
                } catch(\Exception $e) {
                    LoggerComponent::log("Error while updating reply count for email # ".$email_id." -- ".$e->getMessage(), $log_file_name);
                }
                
            }           
            if ($number_of_records == $flag_no) {
                LoggerComponent::log("No record found in reply check process", $log_file_name);
                break; // break from loop
            }

            $current_time = time();

            // If any single campaign reply/bounce tracking is asked, then only process 200 records
            if ($num_records_processed >= self::REPLY_CHECK_RECORDS_LIMIT) {
                $current_time = $end_time + 10;              
            }
        }

        // Log complete
        LoggerComponent::log("Total ".$num_records_processed." records found to check", $log_file_name);
        LoggerComponent::log("Completed reply check", $log_file_name);
        LoggerComponent::log("====================================", $log_file_name);
    }
}
