<?php
/**
 * Used to send notification to user whose document not viewed
 */
namespace App\Commands;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Interop\Container\ContainerInterface;
use \App\Components\SHAppComponent;
use \App\Components\SHActivity;
use \App\Components\DateTimeComponent;
use \App\Components\LoggerComponent;
use \App\Models\AccountContacts;
use \App\Models\AccountSendingMethods;
use \App\Models\DocumentLinks;
use \App\Models\DocumentLinkVisits;
use \App\Components\Mailer\GmailComponent;
use \App\Components\Mailer\SMTPComponent;
use \App\Components\StringComponent;
use \App\Components\Mailer\TransactionMailsComponent;

class DocumentLinksCommand extends AppCommand {

    const DOCUMENT_LINKS_RECORD_LIMIT = 5;
    
    const LOG_FILE_DIRECTORY                    = __DIR__ . "/../../logs";
    const LOG_FILE_DOCUMENT_LINKS               = self::LOG_FILE_DIRECTORY . "/dl_check_process.log";
    const LOG_FILE_DOCUMENT_LINKS_DYNA          = self::LOG_FILE_DIRECTORY . "/dl_check_process";

    const LOG_FILE_DOCUMENT_LINKS_VISITED       = self::LOG_FILE_DIRECTORY . "/dl_visited_process.log";
    const LOG_FILE_DOCUMENT_LINKS_DYNA_VISITED  = self::LOG_FILE_DIRECTORY . "/dl_visited_process";

    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Check document links which is not viewed
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     */
    public function actionCheckDocLinkNotViewed(ServerRequestInterface $request, ResponseInterface $response, $args) {
    	
        $current_time = DateTimeComponent::getDateTime();
        $flag_yes  = SHAppComponent::getValue("app_constant/FLAG_YES");
        $flag_no = SHAppComponent::getValue("app_constant/FLAG_NO");
    	
        // Get records for which to check document links now viewed
        $row_document_link_records    = [];
        $num_records_processed = 0;
        
    	$modulo = isset($_SERVER['argv'][3]) ? $_SERVER['argv'][3] : "";
        $reminder = isset($_SERVER['argv'][2]) ? $_SERVER['argv'][2] : "";
        
        // Decide which log file to use
        if(!is_null($reminder) && !empty($modulo)){
            $log_file_name = self::LOG_FILE_DOCUMENT_LINKS_DYNA.'_'.$reminder.'_'.$modulo.'.log';
        } else {
            $log_file_name = self::LOG_FILE_DOCUMENT_LINKS;
        }
        
        //Create log directory if not exist
        if (!is_dir(self::LOG_FILE_DIRECTORY)) {
            mkdir(self::LOG_FILE_DIRECTORY, 0777, true);
        }
        // Log  document link check process start
        LoggerComponent::log("Document links check process start", $log_file_name);

    	$model_document_links = new DocumentLinks();
    
        	try {
                    $condition = [
                        "fields" => [
                        "dl.id",
                        "dl.name",
                        "um.first_name",
                        "um.email"
                    ],
                    "where" => [
                        ["where" => ["dl.remind_not_viewed", "=", $flag_yes]],
                        ["where" => ["dl.not_viewed_mail_sent", "=", $flag_no]],
                        ["where" => ["dl.remind_at", "<", $current_time]],
                        ["where" => ["dl.last_opened_at", "=", $flag_no]],
                        ["where" => ["dl.status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                    ],
                    "join" => [
                        "user_master"
                    ],
                    "limit" => self::DOCUMENT_LINKS_RECORD_LIMIT
                    ];

	            if (!empty($modulo) && !is_null($reminder)) {
	            	$condition["where"][] = ["where" => ["dl.id % " . $modulo, "=", $reminder]];
	            }

                $row_document_link_records = $model_document_links->fetchAll($condition);
                $number_of_records         = count($row_document_link_records);
                
               } catch(\Exception $e) {
                LoggerComponent::log("Could not fetch records to check document links ---".$e->getMessage() , $log_file_name);
            }
            
            LoggerComponent::log($number_of_records . " records found.", $log_file_name);

            for ($c = 0; $c < $number_of_records; $c++) {
                $num_records_processed++;

                $document_link_id = $row_document_link_records[$c]["id"];
                $doc_link_name    = $row_document_link_records[$c]["name"];
                $user_email       = $row_document_link_records[$c]["email"];
                $space_link       = "documents_space/";
                $space_link_part  = "/performance";
                $linkUrl          = \BASE_URL.$space_link.StringComponent::encodeRowId($document_link_id).$space_link_part;
                
                LoggerComponent::log("Processing email id # ".$user_email, $log_file_name);

                try {
                    $email_subject = "Document link has not been viewed";
                    
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
                    $info["subject"] = $email_subject;
                    $info["content"] = file_get_contents(\EMAIL_TEPLATES_FOLDER . "/document_link_not_viewed.html");
                    $info["content"] = str_replace("{FirstName}", $row_document_link_records[$c]["first_name"], $info["content"]);
                    
                    $info["content"] = str_replace("{LinkURLApp}", $linkUrl, $info["content"]);
                    $info["content"] = str_replace("{LinkName}", $doc_link_name, $info["content"]);

                    $email_send = TransactionMailsComponent::mailSendSmtp($info);
                    
                    //if successfull email sent then update record email is sent
                    if ($email_send["success"]) {
                        try {
                            $update_data = [
                                "id" => $document_link_id,
                                "not_viewed_mail_sent" => $flag_yes,
                                "modified" => $current_time
                            ];

                            $model_document_links->save($update_data);   
                        } catch (\Exception $e) {
                            LoggerComponent::log("Error in updating record #". $document_link_id, $log_file_name);
                        }
                    }
                    LoggerComponent::log("send email to user #". $user_email, $log_file_name);
                } catch(\Exception $e) {
                    LoggerComponent::log("Could not send email #". $user_email . "Error : ". $e->getMessage(), $log_file_name);
                }
            }
            
            // Log complete
            LoggerComponent::log("Total ".$num_records_processed." records found to check", $log_file_name);
            LoggerComponent::log("Completed document links check", $log_file_name);
            LoggerComponent::log("====================================", $log_file_name);
    }


    /**
     * Check document links which is visited
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     */
    public function actionCheckDocLinkViewed(ServerRequestInterface $request, ResponseInterface $response, $args) {
        
        $current_time = DateTimeComponent::getDateTime();
        $flag_yes  = SHAppComponent::getValue("app_constant/FLAG_YES");
        $flag_no = SHAppComponent::getValue("app_constant/FLAG_NO");
        
        // Get records for which to check document links now viewed
        $row_document_link_visited_records = [];
        $num_records_processed = 0;

        $modulo = isset($_SERVER['argv'][3]) ? $_SERVER['argv'][3] : "";
        $reminder = isset($_SERVER['argv'][2]) ? $_SERVER['argv'][2] : "";
        
        // Decide which log file to use
        if(!is_null($reminder) && !empty($modulo)){
            $log_file_name = self::LOG_FILE_DOCUMENT_LINKS_DYNA_VISITED.'_'.$reminder.'_'.$modulo.'.log';
        } else {
            $log_file_name = self::LOG_FILE_DOCUMENT_LINKS_VISITED;
        }
        
        //Create log directory if not exist
        if (!is_dir(self::LOG_FILE_DIRECTORY)) {
            mkdir(self::LOG_FILE_DIRECTORY, 0777, true);
        }
        // Log  document link check process start
        LoggerComponent::log("Document links Visited process start", $log_file_name);

        $model_document_link_visits = new DocumentLinkVisits();
        
            try {
                    $condition = [
                        "fields" => [
                        "dv.id",
                        "dl.id as link_id",
                        "dl.name",
                        "dl.last_opened_at",
                        "um.first_name as user_fname",
                        "um.email as user_email",
                        "dv.location",
                        "dv.modified",
                        "ac.email as visitor_email"
                    ],
                    "where" => [
                        ["where" => ["dl.email_me_when_viewed", "=", $flag_yes]],
                        ["where" => ["dv.viewed_mail_sent", "=", $flag_no]],
                        ["where" => ["dl.status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                    ],
                    "join" => [
                        "document_links",
                        "user_master",
                        "account_contacts"
                    ],
                    "limit" => self::DOCUMENT_LINKS_RECORD_LIMIT
                    ];

                if (!empty($modulo) && !is_null($reminder)) {
                    $condition["where"][] = ["where" => ["dv.id % " . $modulo, "=", $reminder]];
                }

                $row_document_link_visited_records = $model_document_link_visits->fetchAll($condition);
                $number_of_records   = count($row_document_link_visited_records);
               
               } catch(\Exception $e) {
                LoggerComponent::log("Could not fetch records to check document links visits---".$e->getMessage() , $log_file_name);
            }
            
            LoggerComponent::log($number_of_records . " records found.", $log_file_name);

            for ($v = 0; $v < $number_of_records; $v++) {
                $num_records_processed++;

                $document_link_visit_id   = $row_document_link_visited_records[$v]['id'];
                $document_link_id         = $row_document_link_visited_records[$v]['link_id'];
                $document_link_name       = $row_document_link_visited_records[$v]["name"];
                $creator_first_name       = $row_document_link_visited_records[$v]["user_fname"];
                $creator_email            = $row_document_link_visited_records[$v]["user_email"];
                $visitor_email            = $row_document_link_visited_records[$v]["visitor_email"];
                $visited_at               = DateTimeComponent::convertDateTime($row_document_link_visited_records[$v]["modified"], true, \DEFAULT_TIMEZONE, \DEFAULT_TIMEZONE, \APP_TIME_FORMAT). " (".\DEFAULT_TIMEZONE.") ";
                $visitor_location_payload = json_decode($row_document_link_visited_records[$v]["location"]);
                $visitor_browser_name     = $visitor_location_payload->bn;
                $visitor_os_name          = $visitor_location_payload->os;
                $visitor_location         = $visitor_location_payload->c.", ".$visitor_location_payload->s.", ".$visitor_location_payload->co;
                $visitor_ip               = $visitor_location_payload->ip;

                $space_link       = "documents_space/";
                $space_link_part  = "/performance";
                $linkUrl          = \BASE_URL.$space_link.StringComponent::encodeRowId($document_link_id).$space_link_part;
                
                LoggerComponent::log("Processing visit id # ".$document_link_visit_id, $log_file_name);
                
                try {
                    $email_subject = $visitor_email." just visited your document";
                    
                    $info["smtp_details"]["host"] = HOST;
                    $info["smtp_details"]["port"] = PORT;
                    $info["smtp_details"]["encryption"] = ENCRYPTION;
                    $info["smtp_details"]["username"] = USERNAME;
                    $info["smtp_details"]["password"] = PASSWORD;

                    $info["from_email"] = FROM_EMAIL;
                    $info["from_name"] = FROM_NAME;

                    $info["to"] = $creator_email;
                    $info["cc"] = '';
                    $info["bcc"] = '';
                    $info["subject"] = $email_subject;
                    
                    $info["content"] = file_get_contents(\EMAIL_TEPLATES_FOLDER . "/document_link_visited_email.html");
                    $info["content"] = str_replace("{FirstName}", $creator_first_name, $info["content"]);
                    $info["content"] = str_replace("{VisitorEmail}", $visitor_email, $info["content"]); 
                    $info["content"] = str_replace("{LinkName}", $document_link_name, $info["content"]);
                    $info["content"] = str_replace("{VisitedOn}", $visited_at, $info["content"]);
                    $info["content"] = str_replace("{location}", $visitor_location, $info["content"]);
                    $info["content"] = str_replace("{ip}", $visitor_ip, $info["content"]);
                    $info["content"] = str_replace("{osName}", $visitor_os_name, $info["content"]);
                    $info["content"] = str_replace("{bsName}", $visitor_browser_name, $info["content"]);
                    $info["content"] = str_replace("{LinkURLApp}", $linkUrl, $info["content"]);
                    $email_send = TransactionMailsComponent::mailSendSmtp($info);
                    
                    //if successfull email sent then update record as email sent
                    if ($email_send["success"]) {
                        try {
                            $update_data = [
                                "id" => $document_link_visit_id,
                                "viewed_mail_sent" => $flag_yes,
                                "modified" => $current_time
                            ];

                            $model_document_link_visits->save($update_data);   
                        } catch (\Exception $e) {
                            LoggerComponent::log("Error in updating record #". $document_link_visit_id, $log_file_name);
                        }
                    }
                    LoggerComponent::log("send email to user #". $creator_email, $log_file_name);
                } catch(\Exception $e) {
                    LoggerComponent::log("Could not send email #". $creator_email . "Error : ". $e->getMessage(), $log_file_name);
                }
            }
            
            // Log complete
            LoggerComponent::log("Total ".$num_records_processed." records found to check", $log_file_name);
            LoggerComponent::log("Completed document link visits check", $log_file_name);
            LoggerComponent::log("====================================", $log_file_name);
    }
}
