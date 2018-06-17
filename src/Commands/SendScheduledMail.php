<?php
/**
 * Used to send scheduled emails
 */
namespace App\Commands;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Interop\Container\ContainerInterface;
use \App\Components\SHAppComponent;
use \App\Components\SHActivity;
use \App\Components\DateTimeComponent;
use \App\Components\LoggerComponent;
use \App\Models\EmailMaster;
use \App\Models\EmailRecipients;
use \App\Models\AccountContacts;
use \App\Models\AccountSendingMethods;
use \App\Models\AccountLinkMaster;
use \App\Models\EmailLinks;
use \App\Components\Mailer\GmailComponent;
use \App\Components\Mailer\SMTPComponent;
use \App\Components\StringComponent;
use \App\Components\Mailer\TransactionMailsComponent;

class SendScheduledMail extends AppCommand {

    const SEND_MAILS_TIME_LIMIT = 30;
    const LOG_FILE_SHCEDULE_MAIL_PROCESS = __DIR__ . "/../../logs/schedule_mail_process.log";

    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Generate application configuration file
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $args) {
    	
        $current_time = DateTimeComponent::getDateTime();
    	$end_time = $current_time + self::SEND_MAILS_TIME_LIMIT;
        $num_records_processed = 0;

    	$modulo = isset($_SERVER['argv'][3]) ? $_SERVER['argv'][3] : "";
    	$reminder = isset($_SERVER['argv'][2]) ? $_SERVER['argv'][2] : "";

    	$model_email_master = new EmailMaster();
    	$model_account_sending_method = new AccountSendingMethods();
    	$model_email_recipients = new EmailRecipients();

    	$other_values = [
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        LoggerComponent::log("Schedule mail process start", self::LOG_FILE_SHCEDULE_MAIL_PROCESS);

    	while( $current_time <= $end_time ) {

        	try {
        		$condition = [
	    			"fields" => [
	    				"em.id",
                        "em.account_id",
                        "em.user_id",
	                    "em.account_template_id",
	                    "em.subject",
	                    "em.content",
	                    "em.is_scheduled",
	                    "em.scheduled_at",
	                    "em.timezone",
	                    "em.sent_at",
	                    "em.track_reply",
	                    "em.track_click",
	                    "em.total_recipients",
	                    "em.snooze_notifications",
                        "em.sent_message_id",
                        "em.source_id",
	                    "em.progress",
	                    "em.status",
	                    "em.account_sending_method_id",
	                    "um.first_name",
	                    "um.email"
	    			],
	                "where" => [
	                    ["where" => ["em.is_scheduled", "=", SHAppComponent::getValue("app_constant/FLAG_YES")]],
	                    ["where" => ["em.scheduled_at", "<=", $current_time]],
	                    ["where" => ["em.progress", "=", SHAppComponent::getValue("app_constant/PROGRESS_SCHEDULED")]],
                        ["where" => ["em.status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
	                ],
	                "join" => [
	                	"user_master"
	                ],
	                "limit" => 1
	            ];

	            if (!empty($modulo) && !is_null($reminder)) {
	            	$condition["where"][] = ["where" => ["em.id % " . $modulo, "=", $reminder]];
	            }

	            $row_data = $model_email_master->fetch($condition);
	    		
                if(!empty($row_data["id"])) {

                    LoggerComponent::log("Start process of #" .$row_data["id"], self::LOG_FILE_SHCEDULE_MAIL_PROCESS);

                	$email_id = $row_data["id"];
                	$account_sending_method = $row_data["account_sending_method_id"];
                	$info["subject"] = $row_data["subject"];
                    $info["content"] = $row_data["content"];
                    $info["reply_to"] = "";

                    try {
                    	$condition = [
    		                "fields" => [
    		                    "ac.email",
                                "er.type"
    		                ],
    		                "where" => [
    		                    ["where" => ["er.email_id", "=", $email_id]],
    		                    ["where" => ["er.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
    		                ],
    		                "join" => [
    		                    "account_contacts"
    		                ]
    		            ];
    		            $recipients_data = $model_email_recipients->fetchAll($condition);

                        $to_recipients = [];
                        $cc_recipients = [];
                        $bcc_recipients =[];

                        if(!empty($recipients_data)) {
                            foreach($recipients_data as $recipient) {
                                switch($recipient["type"]) {
                                    case SHAppComponent::getValue("app_constant/TO_EMAIL_RECIPIENTS") :
                                        $to_recipients[] = $recipient["email"];
                                        break;
                                    case SHAppComponent::getValue("app_constant/CC_EMAIL_RECIPIENTS") :
                                        $cc_recipients[] = $recipient["email"];
                                        break;
                                    case SHAppComponent::getValue("app_constant/BCC_EMAIL_RECIPIENTS") :
                                        $bcc_recipients[] = $recipient["email"];
                                        break;
                                    default :
                                        break;
                                }
                            }
                        }
    		            $info["to"] = $to_recipients;
                        $info["cc"] = implode(",", $cc_recipients);
                        $info["bcc"] = implode(",", $bcc_recipients);
                        
                    } catch(\Exception $e) {
                    	 LoggerComponent::log("Could not fetch contacts #". $row_data["id"] . "Error:" . $e->getMessage(), self::LOG_FILE_SHCEDULE_MAIL_PROCESS);
                    }

                	$res_sending_method = $model_account_sending_method->getSendingMethodById($account_sending_method, $other_values);

                	if(!empty($res_sending_method)) {
                        // Generate tracking pixel image
                        $tracking_url = TRACKING_PIXEL[rand(0, TRACKING_PIXEL_COUNT)];

                        $encrypted_pixel_id = StringComponent::encodeRowId($email_id);
                        $encrypted_user_id = StringComponent::encodeRowId($row_data["user_id"]);
                        $encrypted_account_id = StringComponent::encodeRowId($row_data["account_id"]);

                        if ( $row_data["track_click"] == SHAppComponent::getValue("app_constant/FLAG_YES") ) {

                            $regex = FIND_LINK_PATTERN;
                            preg_match_all($regex, $info["content"], $attachment_urls);

                            $model_account_link_master = new AccountLinkMaster();
                            $model_email_links = new EmailLinks();

                            // If link exists in content
                            if (!empty($attachment_urls[2])) {
                                foreach ($attachment_urls[2] as $url) {
                                    $link_id = $model_account_link_master->getLinkIdByUrl($url, $row_data["account_id"]);

                                    $redirect_key = StringComponent::encodeRowId([$row_data["account_id"], $email_id, $link_id]);
                                    
                                    try {
                                        $save_data = [
                                            "account_id" => $row_data["account_id"],
                                            "email_id" => $email_id,
                                            "account_link_id" => $link_id,
                                            "redirect_key" => $redirect_key
                                        ];

                                        $model_email_links->save($save_data);

                                    } catch(\Exception $e) {}
                                        
                                    $new_url = $tracking_url . "/". TRACKING_UNIQUE_TEXT . "/r/e/" . $redirect_key."?r=".$url;
                                    $info["content"] = str_replace('"' . $url . '"', '"' . $new_url . '"', $info["content"]);
                                }
                            }
                        }

                        $tracking_pixel_image = '<img width="1" height="1" id="shtracking_1s2" src="' . $tracking_url . '/'. TRACKING_UNIQUE_TEXT . '/e/' . $encrypted_pixel_id . '/' . $encrypted_user_id . '/' . $encrypted_account_id . '" style="display:none">';


                        $info["content"] = $info["content"] . $tracking_pixel_image;
                		$info["from_email"] = $res_sending_method["from_email"];
                        $info["from_name"] = $res_sending_method["from_name"];
                		$email_sending_method_id = $res_sending_method["email_sending_method_id"];
                        $payload = json_decode($res_sending_method["payload"]);


                        // Send email with Gmail
                        if($email_sending_method_id == SHAppComponent::getValue("email_sending_method/GMAIL")) {
                            LoggerComponent::log("Send mail using gmail account #". $row_data["id"], self::LOG_FILE_SHCEDULE_MAIL_PROCESS);
                        	
                            if ($res_sending_method["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE") && $res_sending_method["connection_status"] == SHAppComponent::getValue("app_constant/FLAG_YES") ) {
                                $info["refresh_token"] = $payload->refresh_token;
                                $info["thread_id"] = "";

                                //send mail using draft_id for chrome_plugin
                                $source_chrome = SHAppComponent::getValue("source/CHROME_PLUGIN");
                                if($row_data["source_id"] == $source_chrome && !empty($row_data["sent_message_id"])) {
                                    $info["draft_id"] = $row_data["sent_message_id"];
                                }

                                $result = GmailComponent::sendMailGmail($info, false);
                          
                                if(isset($result["gmail_message_id"]) && $result["gmail_message_id"] != "") {
                                	//mail sent successfully
                                	$progress = SHAppComponent::getValue("app_constant/PROGRESS_SENT");
                                	$sent_message_id = $result["gmail_message_id"];
                                	$sent_response = "";
                                	$sent_at = DateTimeComponent::getDateTime();

                                    LoggerComponent::log("Mail sent successfully #". $row_data["id"], self::LOG_FILE_SHCEDULE_MAIL_PROCESS);
                                } else {
                                	//mail not sent
                                	$progress = SHAppComponent::getValue("app_constant/PROGRESS_FAILED");
                                	$sent_message_id = "0";
                                	$sent_response = $result["error_message"];
                                	$sent_at = DateTimeComponent::getDateTime();

                                    if(!$result["success"]) {
                                        if (json_decode($result["error_message"], true) === NULL) {
                                            $attempt_error_arr = array(
                                                "message"   => $result["error_message"],
                                                "code"      => null
                                            );
                                        } else {
                                            $attempt_error_arr = json_decode($result["error_message"], true);

                                            if (isset($attempt_error_arr["error"]["message"])) {
                                                $attempt_error_arr["message"] = $attempt_error_arr["error"]["message"];
                                            }
                                        }

                                        if (isset($attempt_error_arr["error"])) {
                                            if ($attempt_error_arr["error"]["code"] == 401) {
                                                // Update user Gmail account as disconnected
                                                $update_data = [
                                                    "id" => $res_sending_method["id"],
                                                    "error_message" => "401 - Error in connecting your Gmail account."
                                                ];
                                                $model_account_sending_method->disConnectMailAccount($update_data);
                                            }
                                        }
                                    }

                                    LoggerComponent::log("Mail could not set #". $row_data["id"] . "Error : " .$sent_response, self::LOG_FILE_SHCEDULE_MAIL_PROCESS);

                                    //Send email to user to draft not found
                                    if ($row_data["source_id"] == $source_chrome) {

                                	   $datetime = DateTimeComponent::convertDateTime($row_data["scheduled_at"], true, \DEFAULT_TIMEZONE, $row_data["timezone"], \APP_TIME_FORMAT);
                                	   $error = json_decode($sent_response, true);
                                	   $error_message = $error["error"]["message"];

                                	   $content = "The mail with subject <b>".$row_data["subject"]."</b> which was scheduled at <b>". $datetime . " " . $row_data["timezone"] ." </b> has failed. Please find the details of the failure below: <br/><br/><b> ". $error_message ."</b> <br/><br/><br/> Thanks, <br/> Team Saleshandy.";
                
                                        $info["smtp_details"]["host"] = HOST;
                                        $info["smtp_details"]["port"] = PORT;
                                        $info["smtp_details"]["encryption"] = ENCRYPTION;
                                        $info["smtp_details"]["username"] = USERNAME;
                                        $info["smtp_details"]["password"] = PASSWORD;

                                        $info["from_email"] = FROM_EMAIL;
                                        $info["from_name"] = FROM_NAME;

                                        $info["to"] = $row_data["email"];
                                        $info["cc"] = '';
                                        $info["bcc"] = '';
                                        $info["subject"] = "Scheduled mail process";
                                        $info["content"] = $content;

                                        $success = TransactionMailsComponent::mailSendSmtp($info);
                                    }
                                }
                            } else {
                                //mail not sent
                                $progress = SHAppComponent::getValue("app_constant/PROGRESS_FAILED");
                                $sent_message_id = "0";
                                $sent_response = "Your Gmail account has been disconnected";
                                $sent_at = DateTimeComponent::getDateTime();

                                LoggerComponent::log("Mail could not set #". $row_data["id"] . "Error : " .$sent_response, self::LOG_FILE_SHCEDULE_MAIL_PROCESS); 
                            }

                            try {
                                $condition = [
                                    "where" => [
                                        ["where" => ["email_id", "=", $email_id]]
                                    ]
                                ];
                                $save_data = [
                                    "progress" => $progress
                                ];
                                $updated = $model_email_recipients->update($save_data, $condition);
                                LoggerComponent::log("Email Receipent table updated #". $row_data["id"], self::LOG_FILE_SHCEDULE_MAIL_PROCESS);

                            } catch(\Exception $e) {
                                LoggerComponent::log("Email Receipents table not updated #". $row_data["id"] . "Error : " .$e->getMessage(), self::LOG_FILE_SHCEDULE_MAIL_PROCESS);
                            }
                            try {
                            	$save_data = [
    				                "id" => $email_id,
    				                "sent_at" => $sent_at,
                                    "sent_message_id" => $sent_message_id,
                                    "sent_response" => $sent_response,
    				                "progress" => $progress,
                                    "is_scheduled" => SHAppComponent::getValue("app_constant/FLAG_NO")
    				            ];
    				            $model_email_master->save($save_data);
                                LoggerComponent::log("Email Master table updated #". $row_data["id"], self::LOG_FILE_SHCEDULE_MAIL_PROCESS);

                            } catch(\Exception $e) {
                            	LoggerComponent::log("Email Master table not updated #". $row_data["id"] . "Error : " .$e->getMessage(), self::LOG_FILE_SHCEDULE_MAIL_PROCESS);
                            }
                        }

                        // Send email with user's SMTP
                        if($email_sending_method_id == SHAppComponent::getValue("email_sending_method/SMTP")) {

                            LoggerComponent::log("Send mail using smtp account #". $row_data["id"], self::LOG_FILE_SHCEDULE_MAIL_PROCESS);

                            if ($res_sending_method["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE") && $res_sending_method["connection_status"] == SHAppComponent::getValue("app_constant/FLAG_YES") ) {
                        	  	$info["smtp_details"]["host"] = $payload->host;
                                $info["smtp_details"]["username"] = $payload->username;
                               	$info["smtp_details"]["password"] = $payload->password;
                                $info["smtp_details"]["port"] = $payload->port;
                            	$info["smtp_details"]["encryption"] = $payload->encryption;

                            	$result = SMTPComponent::mailSendUserSmtp($info);

                                if(isset($result["message_id"]) && $result["message_id"] != "") {
                                    //mail sent successfully
                                    $progress = SHAppComponent::getValue("app_constant/PROGRESS_SENT");
                                    $sent_message_id = $result["message_id"];
                                    $sent_response = "";
                                    $sent_at = DateTimeComponent::getDateTime();

                                    LoggerComponent::log("Mail sent successfully #". $row_data["id"], self::LOG_FILE_SHCEDULE_MAIL_PROCESS);
                            	} else {
                            		//mail not sent
                                    $progress = SHAppComponent::getValue("app_constant/PROGRESS_FAILED");
                            		$sent_message_id = "0";
                            		$sent_response = $result["message"];
                            		$sent_at = DateTimeComponent::getDateTime();

                                    if(!$result["success"]) {

                                        if (isset($result["error_message"])) {
                                            if (json_decode($result["error_message"], true) === NULL) {
                                                $attempt_error_arr = array(
                                                    "message"   => $result["error_message"],
                                                    "code"      => null
                                                );
                                            } else {
                                                $attempt_error_arr = json_decode($result["error_message"], true);

                                                if (isset($attempt_error_arr["error"]["message"])) {
                                                    $attempt_error_arr["message"] = $attempt_error_arr["error"]["message"];
                                                }
                                            }

                                            if (stristr($attempt_error_arr["message"],CONNECTION_NOT_ESTABLISH) !== false || 
                                                stristr($attempt_error_arr["message"], EXPECTED_CODE_250_BUT_GONE) !== false || 
                                                stristr($attempt_error_arr["message"], EXPECTED_CODE_220_BUT_GONE) !== false) {
                                               
                                                //Update user SMTP account as disconnected
                                                $update_data = [
                                                    "id" => $res_sending_method["id"],                                      
                                                    "error_message" => "Error in connecting your SMTP account. Please reconnect your account.",
                                                ];
                                                $model_account_sending_method->disConnectMailAccount($update_data);
                                            }
                                        } else if (isset($result["message"])) {
                                            if (stristr($result["message"],SMTP_CONNECTION_FAILED) !== false || 
                                                stristr($result["message"], COULD_NOT_AUTHENTICATE) !== false ) {
                                                
                                                //Update user SMTP account as disconnected
                                                $update_data = [
                                                    "id" => $res_sending_method["id"],                                      
                                                    "error_message" => "Error in connecting your SMTP account. Please reconnect your account.",
                                                ];
                                                $model_account_sending_method->disConnectMailAccount($update_data);
                                            } 
                                        }
                                    }

                                    LoggerComponent::log("Mail could not set #". $row_data["id"] . "Error : " .$sent_response, self::LOG_FILE_SHCEDULE_MAIL_PROCESS);
                                }
                            } else {
                                //mail not sent
                                $progress = SHAppComponent::getValue("app_constant/PROGRESS_FAILED");
                                $sent_message_id = "0";
                                $sent_response = "Your Gmail account has been disconnected";
                                $sent_at = DateTimeComponent::getDateTime();
                                
                                LoggerComponent::log("Mail could not set #". $row_data["id"] . "Error : " .$sent_response, self::LOG_FILE_SHCEDULE_MAIL_PROCESS); 
                            }
                            try {
                                $condition = [
                                    "where" => [
                                        ["where" => ["email_id", "=", $email_id]]
                                    ]
                                ];
                                $save_data = [
                                    "progress" => $progress
                                ];
                                $updated = $model_email_recipients->update($save_data, $condition);
                                LoggerComponent::log("Email Receipent table updated #". $row_data["id"], self::LOG_FILE_SHCEDULE_MAIL_PROCESS);

                            } catch(\Exception $e) {
                               LoggerComponent::log("Email Receipents table not updated #". $row_data["id"] . "Error : " .$e->getMessage(), self::LOG_FILE_SHCEDULE_MAIL_PROCESS);
                            }
                            try {
                            	$save_data = [
    				                "id" => $email_id,
    				                "sent_at" => $sent_at,
    				                "progress" => $progress,
                                    "sent_message_id" => $sent_message_id,
                                    "sent_response" => $sent_response,
                                    "is_scheduled" => SHAppComponent::getValue("app_constant/FLAG_NO")
    				            ];
    				            $model_email_master->save($save_data);
                                LoggerComponent::log("Email Master table updated #". $row_data["id"], self::LOG_FILE_SHCEDULE_MAIL_PROCESS);
                                
                            } catch(\Exception $e) {
                            	LoggerComponent::log("Email Master table not updated #". $row_data["id"] . "Error : " .$e->getMessage(), self::LOG_FILE_SHCEDULE_MAIL_PROCESS);
                            }
                        }
                        
                        if($result["success"]) {
                            //Add activity
                            try {
                                $activity_params = [
                                    "user_id" => $row_data["user_id"],
                                    "account_id" => $row_data["account_id"],
                                    "action" => SHAppComponent::getValue("actions/EMAILS/SENT"),
                                    "record_id" => $row_data["id"]
                                ];
                                $sh_activity = new SHActivity();
                                $sh_activity->addActivity($activity_params);
                                LoggerComponent::log("Activity inserted sucessfully #". $row_data["id"], self::LOG_FILE_SHCEDULE_MAIL_PROCESS);

                            } catch(\Exception $e) {
                                LoggerComponent::log("Activity not inserted #". $row_data["id"] . "Error : " .$e->getMessage(), self::LOG_FILE_SHCEDULE_MAIL_PROCESS);
                            }
                        }
                	} else {
                        LoggerComponent::log("Could not fetch sending method details #". $row_data["id"], self::LOG_FILE_SHCEDULE_MAIL_PROCESS);
                    	$save_data = [
                          "id" => $row_data["id"],
                          "progress" => SHAppComponent::getValue("app_constant/PROGRESS_FAILED"),
                          "is_scheduled" => SHAppComponent::getValue("app_constant/FLAG_NO")
                        ];
                        $model_email_master->save($save_data);
		        	}
                    LoggerComponent::log("Process Finished for Send Mail #". $row_data["id"], self::LOG_FILE_SHCEDULE_MAIL_PROCESS);
                } else {
                	LoggerComponent::log("Record not found to be processing", self::LOG_FILE_SHCEDULE_MAIL_PROCESS); 
                	break;
                }
            } catch(\Exception $e) {
                LoggerComponent::log("Could not fetch scheduled mail record #". $row_data["id"] . "Error : ". $e->getMessage(), self::LOG_FILE_SHCEDULE_MAIL_PROCESS);
            }
            
            $current_time = DateTimeComponent::getDateTime();
            $num_records_processed++;
    	}
        LoggerComponent::log("Total ".$num_records_processed." records to be proceed", self::LOG_FILE_SHCEDULE_MAIL_PROCESS);
        LoggerComponent::log("===================================================", self::LOG_FILE_SHCEDULE_MAIL_PROCESS);
    }
}
