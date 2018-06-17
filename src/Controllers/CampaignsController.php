<?php
/**
 * Campaigns related functionality
 */
namespace App\Controllers;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Interop\Container\ContainerInterface;
use \App\Components\SHAppComponent;
use \App\Components\ModelValidationsComponent as Validator;
use \App\Components\StringComponent;
use \App\Components\DateTimeComponent;
use \App\Components\ErrorComponent;
use \App\Components\LoggerComponent;
use \App\Models\CampaignMaster;
use \App\Models\CampaignStages;
use \App\Models\CampaignSequences;
use \App\Models\CampaignLinks;
use \App\Models\CampaignLogs;
use \App\Models\CampaignTrackHistory;
use \App\Models\AccountSendingMethods;
use \App\Models\EmailSendingMethodMaster;
use \App\Models\CampaignDomainBlocklist;
use \App\Components\Mailer\GmailComponent;
use \App\Components\Mailer\SMTPComponent;
use \Slim\Http\UploadedFile;

class CampaignsController extends AppController {

    // Campaign Constants
    const CONTACT_FOLDER = "campaignjson";
    const CONTACT_TEMP_FILE = "followup_";
    const CSV_MAX_ROWS = 200;
    const CSV_MAX_ROWS_PREMIUM_CUSTOMER = 2000;
    const CSV_MAX_COLS = 20;
    const CSV_EMAIL_FIELD = "Email";
    const CSV_DOMAIN_FIELD = "Domain";
    const CSV_MAX_FILE_SIZE_IN_MB = 5;
    const CSV_MAX_ROWS_UPLOAD_LIMIT = 500;

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * List all data
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function lists(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();
        $plan_configuration = SHAppComponent::getPlanConfiguration();
        $allowed_campaign_limit = $plan_configuration["total_camp_create_count"];
        
        // Get request parameters
        $params = $request->getQueryParams();

        // Set parameters
        $query_params = [
            "page" => 1,
            "per_page" => SHAppComponent::getValue("app_constant/DEFAULT_LIST_PER_PAGE"),
            "order_by" => "modified",
            "order" => "DESC"
        ];

        if (!empty($params["page"])) {
            if (is_numeric($params["page"])) {
                $query_params["page"] = (int) $params["page"];
            }
        }
        if (!empty($params["per_page"])) {
            if (is_numeric($params["per_page"])) {
                $query_params["per_page"] = (int) $params["per_page"];
            }
        }
        if (!empty($params["order_by"])) {
            $query_params["order_by"] = trim($params["order_by"]);
        }
        if (!empty($params["order"])) {
            $query_params["order"] = (trim($params["order"]) == "DESC") ? "DESC" : "ASC";
        }
        if (!empty($params["user"])) {
            if ( strtolower($params["user"]) != "all" && strtolower($params["user"]) != "any" ) {
                $user_id = StringComponent::decodeRowId(trim($params["user"]));

                if (!empty($user_id)) {
                    $query_params["user"] = $user_id;
                }
            }
        }
        if (!empty($params["status"])) {
            $status = trim($params["status"]);
            
            $query_params["status"] = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");

            switch ($status) {
                case "STATUS_DRAFT":
                    $query_params["status"] = SHAppComponent::getValue("app_constant/STATUS_DRAFT");
                    $query_params["progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED");
                    break;

                case "CAMP_PROGRESS_SCHEDULED":
                    $query_params["progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED");
                    break;

                case "CAMP_PROGRESS_QUEUED":
                    $query_params["progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_QUEUED");
                    break;

                case "CAMP_PROGRESS_IN_PROGRESS":
                    $query_params["progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_IN_PROGRESS");
                    break;

                case "CAMP_PROGRESS_PAUSED":
                    $query_params["progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED");
                    break;

                case "CAMP_PROGRESS_WAITING":
                    $query_params["progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_WAITING");
                    break;

                case "CAMP_PROGRESS_HALT":
                    $query_params["progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_HALT");
                    break;

                case "CAMP_PROGRESS_FINISH":
                    $query_params["progress"] = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH");
                    break;

                default:
                    unset($query_params["status"]);
                    break;
            }
        }
        if (!empty($params["query"])) {
            $query_params["query"] = SHAppComponent::prepareSearchText($params["query"]);
        }
      
        // Other values for condition
        $other_values = [
            "user_id" => $logged_in_user,
            "account_id" => $logged_in_account,
            "is_owner" => $is_owner,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
            "progress" => SHAppComponent::getValue("app_constant/SEQ_PROGRESS_SENT")
        ];

        // Get data
        $model_campaign_master = new CampaignMaster();        
        // Get stage data
        $model_campaign_stage = new CampaignStages();
        $output["is_allowed_to_create"] = false;
        
        try {
            $data = $model_campaign_master->getListData($query_params, $other_values);
           
            
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        // Added seperate try catch because if we want to ignore exception in this case we can ignore it.
        try {
            $output["is_allowed_to_create"] = $model_campaign_master->isAllowedToCreateCampaign($logged_in_account, $allowed_campaign_limit);            
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Process data and prepare for output
        $output["total_records"] = $data["total_records"];
        $output["total_pages"] = $data["total_pages"];
        $output["current_page"] = $data["current_page"];
        $output["per_page"] = (int) $data["per_page"];
        $output["rows"] = [];
      

        if (!empty($data["rows"])) {

            foreach ($data["rows"] as $row) {
                $campaign_stage = [];                
                $created_by = (($row["user_id"] == $logged_in_user) ? "Me" : trim($row["first_name"] . " " . $row["last_name"]));
                $created_by_name = (($row["user_id"] == $logged_in_user) ? "Me" : trim($row["first_name"]));
                $created_by_id = StringComponent::encodeRowId($row["user_id"]);                
                $progress = $row["overall_progress"];
                $isSetStage = true;
                $isSetStageScheduleTime = true;
                $status_text = "";
            
                switch ($progress) {
                    case '0':
                        $progress = 'CAMP_PROGRESS_SCHEDULED';
                        $status_text = "Scheduled";
                        break;

                    case '1':
                        $progress = 'CAMP_PROGRESS_QUEUED';
                        $status_text = "Queued";
                        break;

                    case '2':
                        $progress = 'CAMP_PROGRESS_IN_PROGRESS';
                        $isSetStageScheduleTime = false; 
                        $status_text = "In-progress";
                        break;

                    case '3':
                        $progress = 'CAMP_PROGRESS_PAUSED';
                        $status_text = "Paused";
                        break;

                    case '4':
                        $progress = 'CAMP_PROGRESS_WAITING'; 
                        $status_text = "Waiting";
                        break;

                    case '5':
                        $progress = 'CAMP_PROGRESS_HALT';
                        $isSetStageScheduleTime = false; 
                        $status_text = "Halted";
                        break;

                    case '6':
                        $progress = 'CAMP_PROGRESS_FINISH';
                        $status_text = "Finished";
                        //$current_stage_schedule_on = DateTimeComponent::convertDateTimeCampaign($campaign_stage['scheduled_on'],true,\DEFAULT_TIMEZONE,$row["timezone"],'d-M-Y h:i A')." ".$row["timezone"];
                        if($row["status"] != 1){
                            $isSetStage = false;
                        }
                        break;
                    default:
                        $progress = 'CAMP_PROGRESS_SCHEDULED'; 
                        $status_text = "Scheduled";                      
                        break;
                }
                if($row["status"] != 1){
                    $isSetStage = false;
                    $isSetStageScheduleTime = false;  
                    $status_text = "Draft";             
                }

                $condition = [
                    "fields" => [
                        "id",
                        "stage",
                        "subject",
                        "scheduled_on",
                        "finished_on",
                        "stage_defination",
                        "status",
                        "(total_contacts-total_deleted) as stage_contact",
                        "progress as stage_progress"
                    ],
                    "where" => [
                        ["where" => ["campaign_id", "=" , $row["id"]]],                        
                        ["where" => ["cst.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                    ]
                ];
                
                try {
                    $campaign_stages = $model_campaign_stage->fetchAll($condition);              
                    $count = 0;
                    $current_stage = null;
                    $current_stage_schedule_on = null;                    
                    $current_stage_subject = null;
                    $current_stage_finish = null;
                    $setNextStageToyetTosent = false;
                    foreach($campaign_stages as $campaign_stage)
                    {
                        $campaign_stages[$count]["id"] = StringComponent::encodeRowId($campaign_stage["id"]);
                        $current_stage_finish = $campaign_stages[$count]["finished_on"];                        
                        

                        unset($campaign_stages[$count]["finished_on"]);
                        $progress_text_class = "";
                        switch ($campaign_stage["stage_progress"]) {
                            case '0':
                                $progress_text_class = 'campaign_stage_schedule';
                                $current_stage_defination = json_encode($campaign_stages[$count]["stage_defination"], true);
                                $ignore_count = empty($current_stage_defination["ignore"]) ? 0 : count($current_stage_defination["ignore"]);
                                $campaign_stages[$count]["stage_contact"] = $campaign_stages[$count]["stage_contact"] - $ignore_count;                                
                                if($row["status"] == 0){
                                    $progress_text_class = 'campaign_stage_yet_to_sent'; 
                                }
                                break;
        
                            case '1':
                                $progress_text_class = 'campaign_stage_schedule';
                                break;
        
                            case '2':
                                $progress_text_class = 'campaign_stage_in_progress';
                                break;
        
                            case '3':
                                $progress_text_class = 'campaign_stage_pause';
                                break;
        
                            case '4':
                                $progress_text_class = 'campaign_stage_pause'; 
                                break;
        
                            case '5':
                                $progress_text_class = 'campaign_stage_pause';
                                break;
        
                            case '6':
                                $progress_text_class = 'campaign_stage_sent';
                                break;
                            default:
                                $progress_text_class = 'campaign_stage_schedule'; 
                                break;
                        }
                     
                        if($campaign_stage["stage_progress"] == 6){
                            
                            $campaign_stages[$count]["progress_text"]  = 'campaign_stage_sent';

                        }else if($campaign_stage["stage_progress"] <> SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH") && $current_stage == 0){
                            
                            $current_stage = $campaign_stage["stage"];               
                            $current_stage_schedule_on = DateTimeComponent::convertDateTimeCampaign($campaign_stage['scheduled_on'],true,\DEFAULT_TIMEZONE,$row["timezone"], \APP_TIME_FORMAT)." ".$row["timezone"];                            
                            $current_stage_subject = $campaign_stage["subject"];
                            
                        }else if($row["total_stages"]>$count+1 && $current_stage == 0){
                          
                            if($campaign_stages[$count+1]["stage_progress"] == SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH")){
                                continue;
                            }                     
                            $current_stage = $campaign_stages[$count+1]["stage"];
                            $current_stage_schedule_on = DateTimeComponent::convertDateTimeCampaign($campaign_stages[$count+1]['scheduled_on'],true,\DEFAULT_TIMEZONE,$row["timezone"], \APP_TIME_FORMAT)." ".$row["timezone"];                            
                            $current_stage_subject = $campaign_stages[$count+1]["subject"];
                        }else{
                            $progress_text_class = 'campaign_stage_yet_to_sent'; 
                        }
                    
                        if(!$setNextStageToyetTosent && 
                                ($progress == "CAMP_PROGRESS_PAUSED" || 
                                $progress == "CAMP_PROGRESS_WAITING" || 
                                $progress == "CAMP_PROGRESS_HALT") && 
                            $campaign_stage["stage_progress"] <> SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH"))
                        {
                                $progress_text_class = 'campaign_stage_pause';
                                $setNextStageToyetTosent = true;                            
                        }
                    
                        
                        if($campaign_stage["status"] == 0){
                            $campaign_stages[$count]["progress_text"]  = 'campaign_stage_yet_to_sent';
                        }else{
                            $campaign_stages[$count]["progress_text"]  = $progress_text_class;
                        }
                       
                        $count++;
                    }

                 
                    
                    if($progress == 'CAMP_PROGRESS_FINISH'){
                        if(empty($current_stage_finish)){
                            $current_stage_schedule_on = "";
                        }else{
                            $current_stage_schedule_on = DateTimeComponent::convertDateTimeCampaign($current_stage_finish,true,\DEFAULT_TIMEZONE,$row["timezone"], \APP_TIME_FORMAT)." ".$row["timezone"]; 
                        }
                    }

                    if(!$isSetStage){
                        $current_stage = null;
                    }
                    
                    if(!$isSetStageScheduleTime){
                        $current_stage_schedule_on = null;
                    }
                    
                    
                } catch(\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }
               
                $row_data = [
                    "id" => StringComponent::encodeRowId($row["id"]),
                    "title" => $row["title"],
                    "created_by" => $created_by,
                    "created_by_id" => $created_by_id,      
                    "created_by_name" => $created_by_name,    
                    "created_email" => $row["created_email"],
                    "total_stages" => $row["total_stages"],                    
                    "stage_data" => $campaign_stages,
                    "timezone" => $row["timezone"],
                    "last_activity" => $row["status_message"],
                    "priority" => $row["priority"],
                    "progress" => $progress,
                    "campaign_status" => $status_text,
                    "status" => $row["status"],
                    "current_stage" => $current_stage,
                    "current_stage_schedule_on" => $current_stage_schedule_on,
                    "current_stage_subjet" => $current_stage_subject,
                    "snooze" => (bool) $row["snooze_notifications"],
                    "track_reply" => (bool) $row["track_reply"],
                    "track_click" => (bool) $row["track_click"],                  
                    "is_bulk_campaign" => $row["is_bulk_campaign"],
                    "sending_account_id" => StringComponent::encodeRowId($row["account_sending_method_id"]),
                    "sending_method_name" => $row["sending_method_name"],
                    "sending_account_email" => $row["sending_account_email"],
                    "sending_account" => $row["sending_account"],
                    "total_contacts" => $row["total_contacts"],
                    "total_success" => $row["total_success"],
                    "total_open" => $row["total_open"],
                    "total_reply" => $row["total_reply"],
                    "total_clicks" => $row["total_clicks"],
                    "total_bounce" => $row["total_bounce"],
                    "success_ratio" => SHAppComponent::getRatioValue($row["total_success"], $row["total_contacts"]),
                    "open_ratio" => SHAppComponent::getRatioValue($row["total_open"], $row["total_contacts"]),
                    "reply_ratio" => SHAppComponent::getRatioValue($row["total_reply"], $row["total_contacts"]),
                    "clicks_ratio" => SHAppComponent::getRatioValue($row["total_clicks"], $row["total_contacts"]),
                    "bounce_ratio" => SHAppComponent::getRatioValue($row["total_bounce"], $row["total_contacts"])
                ];
                
                $output["rows"][] = $row_data;
            }
     }   

        return $response->withJson($output, 200);
    }

    /**
     * Create new record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
      
        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();
        $plan_configuration = SHAppComponent::getPlanConfiguration();
        $allowed_campaign_limit = $plan_configuration["total_camp_create_count"];
        $allowed_csv_limit = $plan_configuration["camp_csv_contact_limit"];
        $camp_multi_stage = $plan_configuration["camp_multi_stage"];
        
        // Get request parameters
        $request_params = $request->getParsedBody();

        // Set minimum and maximum defaul time interval between two mails
        $min_interval = \DEFAULT_CAMPAIGN_MINIMUM_INTERVAL_BETWEEN_MAILS;
        $max_interval = \DEFAULT_CAMPAIGN_MAXIMUM_INTERVAL_BETWEEN_MAILS;
        
        // Fetch request data
        $request_data = [
            "total_stages" => 0,
           // "timezone" => \DEFAULT_TIMEZONE,
            "other_data" => [],
            "status_message" => "",
            "csv_file"=> "",
            "sending_account" => NULL,
            "is_scheduled" => SHAppComponent::getValue("app_constant/FLAG_NO"),
            "track_reply" => SHAppComponent::getValue("app_constant/FLAG_NO"),
            "track_click" => SHAppComponent::getValue("app_constant/FLAG_YES"),
            "send_as_reply" => SHAppComponent::getValue("app_constant/FLAG_YES"),
            "min_interval" => 45,
            "max_interval" => 60,
            "cc" => "",
            "bcc" => "",
            "is_bulk_campaign" => 0,
            //"time" => (DateTimeComponent::getDateTime(NULL,\DEFAULT_TIMEZONE) + \DEFAULT_CAMPAIGN_SCHEDULE_INTERVAL),
            "stages" => []
        ];
        
        if (isset($request_params["title"])) {
            $request_data["title"] = $request_params["title"];
        }
        if (isset($request_params["csv_file"])) {
            $request_data["csv_file"] = $request_params["csv_file"];
        }
        if (isset($request_params["is_bulk_campaign"])) {
            $request_data["is_bulk_campaign"] = $request_params["is_bulk_campaign"];
        }
        if (isset($request_params["sending_account"])) {
            $sending_account = StringComponent::decodeRowId(trim($request_params["sending_account"]));
            
            if (!empty($sending_account)) {
                $request_data["sending_account"] = $sending_account;

                // set track reply field based on sending method type
                $email_type = null;
                $sending_list = null;
                $sending_methods = new AccountSendingMethods();
                $email_master = new EmailSendingMethodMaster();
                $sending_list = $sending_methods->fetchById($sending_account,["id,email_sending_method_id"]);

                if (!empty($sending_list)) {
                    $email_type = $email_master->fetchById($sending_list["email_sending_method_id"],["id,code"]);
                    if ($email_type["code"] == "GMAIL" && !empty($email_type)) {
                        $request_data["track_reply"] = SHAppComponent::getValue("app_constant/FLAG_YES");
                    }
                }
            }    
        }
        if (isset($request_params["track_links"])) {
            $request_data["track_click"] = (int) $request_params["track_links"];
        }
        if (isset($request_params["send_as_thread"])) {
            $request_data["send_as_reply"] = (int) $request_params["send_as_thread"];
        }
        if (isset($request_params["min_interval"])) {
            $request_data["min_interval"] = (int) $request_params["min_interval"];
        }
        if (isset($request_params["max_interval"])) {
            $request_data["max_interval"] = (int) $request_params["max_interval"];
        }
        if (isset($request_params["timezone"])) {
            $request_data["timezone"] = $request_params["timezone"];
        }
        if (isset($request_params["cc"])) {
            $request_data["cc"] = $request_params["cc"];
        }
        if (isset($request_params["bcc"])) {
            $request_data["bcc"] = $request_params["bcc"];
        }
        if (isset($request_params["time"])) {         
            $request_data["time"] = $request_params["time"];
        }
        // else{  
        //     $request_data["time"] = DateTimeComponent::convertDateTimeCampaign($request_data["time"],true, \DEFAULT_TIMEZONE,$request_data["timezone"],\APP_TIME_FORMAT);
        // }
        if (isset($request_params["stages"])) {
            $request_data["stages"] = $request_params["stages"];
        }
        if (isset($request_params["is_draft"])) {
            $request_data["is_draft"] = $request_params["is_draft"];
        }
        if (isset($request_params["import_id"])) {
            $request_data["import_id"] =  StringComponent::decodeRowId(trim($request_params["import_id"]));
        } else {
            $request_data["import_id"] = 0;
        }
    
        if (isset($request_data["stages"][0]["subject"])) {
            $first_stage_subject = trim($request_data["stages"][0]["subject"]);
        }

        $model_campaign_master = new CampaignMaster();
        // Added seperate try catch because if we want to ignore exception in this case we can ignore it.
        try {
            $is_allowed_to_create = $model_campaign_master->isAllowedToCreateCampaign($logged_in_account, $allowed_campaign_limit);            
            if(!$is_allowed_to_create){
                return ErrorComponent::outputError($response, "api_messages/CAMPAIGN_QUOTA_OVER");
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

         // Added seperate try catch because if we want to ignore exception in this case we can ignore it.
         try {                    
             
            if($request_data["is_bulk_campaign"] && $allowed_csv_limit != BULK_CAMPAIGN_CSV_LIMIT){
                return ErrorComponent::outputError($response, "api_messages/BULK_CAMPAIGN_NOT_ALLOWED");
            }
            
            if($camp_multi_stage == 0 && count($request_data["stages"])>1){
                return ErrorComponent::outputError($response, "api_messages/MULTI_STAGE_NOT_ALLOWED");
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        
        // To check if campaign save as draft or not
        if ($request_data["is_draft"] != true) {
            // Validate request
            $request_validations = [
                "title" => [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ],
                "csv_file" => [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ],
                "sending_account" => [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ],
                "time" => [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ],
                "timezone" => [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ]
            ];
            $validation_errors = Validator::validate($request_validations, $request_data);
         
            $min_schedule_time = DateTimeComponent::convertDateTimeCampaign((DateTimeComponent::getDateTime() + \DEFAULT_CAMPAIGN_SCHEDULE_INTERVAL),true);
           
            $schedule_date = "";
            
            if(!empty($request_data["time"]) && !empty($request_data["timezone"])){
                $schedule_date = DateTimeComponent::convertDateTimeCampaign($request_data["time"],false, $request_data["timezone"], \DEFAULT_TIMEZONE, null, \CAMPAIGN_DATE_TIME_FORMAT);
            }
            // Campaign schedule time should be greater then 15 min.
            if(!empty($schedule_date) && $min_schedule_time>$schedule_date){
                
                $validation_errors[] = "Campaign schedule time should be great then ".\DEFAULT_CAMPAIGN_SCHEDULE_INTERVAL_MINUTE." min from now.";          
            }
            
            // In bulk campaign set min interval as per DEFAULT_CAMPAIGN_MINIMUM_INTERVAL_BETWEEN_BULK_MAILS. 
            if($request_data["is_bulk_campaign"]){
                $min_interval = \DEFAULT_CAMPAIGN_MINIMUM_INTERVAL_BETWEEN_BULK_MAILS;
            }

            // Check Minimum and maximum interval between two mail is proper or not
            if($min_interval>$request_data["min_interval"] || $max_interval<$min_interval || $max_interval<$request_data["max_interval"]){
                $validation_errors[] = "Campaign minimum and maximum interval between two mail must be ".$min_interval." to ".$max_interval." seconds.";          
            }
                    
            // It will give error if first stage's subject is empty or not exist.             
            if (isset($first_stage_subject)) {                                
                if ($first_stage_subject == "") {
                    $validation_errors[] = "Parameter Required: first stage's subject is required.";                
                }
            } else {
                $validation_errors[] =  "Parameter Missing: first stage's subject is required.";                
            }
            // If request is invalid
            if (!empty($validation_errors)) {
                // Fetch error code & message and return the response
                $additional_message = implode("\n", $validation_errors);
                return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
            }
        }  
     
        // Save record
        try {
            $other_data = [
                "cc" => $request_data["cc"],
                "bcc" => $request_data["bcc"],
                "days" => [1,2,3,4,5,6,7],
                "min_seconds" => $request_data["min_interval"],
                "max_seconds" => $request_data["max_interval"],
                "contacts_file" => $request_data["csv_file"],
                "import_id" => $request_data["import_id"]
            ];
           
            $save_data = [
                "account_id" => $logged_in_account,
                "user_id" => $logged_in_user,
                "title" => trim($request_data["title"]),
                "account_sending_method_id" => $request_data["sending_account"],
                "source_id" => $logged_in_source,
                "status_message" => '',
                "total_stages" => $request_data["total_stages"],
                "timezone" => trim($request_data["timezone"]),
                "other_data" => json_encode($other_data),
                "track_reply" => $request_data["track_reply"],
                "track_click" => $request_data["track_click"],
                "send_as_reply" => $request_data["send_as_reply"],
                "is_bulk_campaign" => $request_data["is_bulk_campaign"],
                "created" => DateTimeComponent::getDateTime(),
                "modified" => DateTimeComponent::getDateTime()
            ];
      
            if ($request_data["is_draft"] == SHAppComponent::getValue("app_constant/FLAG_YES")) {
                $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_DRAFT");
                $save_data["progress"] = SHAppComponent::getValue("app_constant/PROGRESS_SCHEDULED");
            } else {
                $request_data["is_scheduled"] = SHAppComponent::getValue("app_constant/FLAG_YES");
            }

            // On Successfully save campaign add all stages.
             if ($model_campaign_master->save($save_data) !== false) {
                $inserted_campaign_id = $model_campaign_master->getLastInsertId();
                
                // Save stage data
                $model_campaign_stage = new CampaignStages();
                
                // Convert request date to GMT+00:00 Timezone
                $schedule_date = DateTimeComponent::convertDateTimeCampaign($request_data["time"],false,$request_data["timezone"]);
                
                $stage_number = 1;

                $previous_subject = '';

                $stageSubject = '';

                $stage_days_count = 0;

                $max_reply_check_date = 0;

                $last_insert_stage_id = 0;

                foreach ($request_data["stages"] as $stage_info) {
                    
                    if($request_data["send_as_reply"] && $stage_number>1) {
                        // Forcefully set subject as reply
                        $stageSubject = "Re: ".$first_stage_subject;

                    }else if (!empty($stage_info["subject"])) {
                        $stageSubject = trim($stage_info["subject"]);
                        $previous_subject = $stageSubject;
                    }
                    else {
                        // keep it same as previous stage subject
                        $stageSubject = $previous_subject;
                    }                    

                    if(empty($stage_info["stage_defination"]["days"]) && $stage_number==1) {                        
                        $temp_convert_date = $schedule_date;
                        $max_reply_check_date = $temp_convert_date + \CAMPAIGN_MAX_REPLY_CHECK_TIME;
                    } else {

                        if(empty($stage_info["stage_defination"]["days"])){
                            $stage_info["stage_defination"]["days"] = \DEFAULT_REPLY_TRACK_DAYS;
                        }
                        else if($stage_info["stage_defination"]["days"] < \DEFAULT_REPLY_TRACK_DAYS) {
                            $stage_info["stage_defination"]["days"] = \DEFAULT_REPLY_TRACK_DAYS;
                        }
                        
                        $stage_days_count = $stage_days_count + $stage_info["stage_defination"]["days"];  
                        $temp_convert_date = ($schedule_date + ($stage_days_count * 24 * 60 * 60));
                        $max_reply_check_date = $temp_convert_date + \CAMPAIGN_MAX_REPLY_CHECK_TIME;

                        if($last_insert_stage_id!=0){
                            
                            $condition = [
                                "where" => [
                                    ["where" => ["id","=", $last_insert_stage_id]]
                                ]
                            ];
                            $save_data = [
                                "track_reply_max_date"  => $temp_convert_date,
                                "modified"              => DateTimeComponent::getDateTime()
                            ];
                           
                            $model_campaign_stage->update($save_data, $condition);                           
                        }
                       
                    }
                    
                    $save_data = [
                        "account_id" => $logged_in_account,
                        "user_id" => $logged_in_user,
                        "campaign_id" => $inserted_campaign_id,
                        "account_template_id" => StringComponent::decodeRowId($stage_info["template_id"]),
                        "subject" => $stageSubject,
                        "content" => trim($stage_info["content"]),
                        "stage_defination" => json_encode($stage_info["stage_defination"]),
                        "stage" => $stage_number,
                        "scheduled_on" => $temp_convert_date,
                        "track_reply_max_date" => $max_reply_check_date,
                        "created" => DateTimeComponent::getDateTime(),
                        "modified" => DateTimeComponent::getDateTime()
                    ];

                    try {
                       $saved = $model_campaign_stage->save($save_data);

                       $last_insert_stage_id = $model_campaign_stage->getLastInsertId();
                       
                       $stage_number++;

                    } catch(\Exception $e) { }

                }

                // Update total stages
                try {
                    $save_data = [
                        "id" => $inserted_campaign_id,
                        "total_stages" => $stage_number - 1
                    ];
                    $saved = $model_campaign_master->save($save_data);

                } catch(\Exception $e) { }

                $output["id"] = StringComponent::encodeRowId($inserted_campaign_id);
                if ($request_data["is_draft"]) {
                    $output["message"] = "Campaign saved successfully.";

                } else if ($request_data["is_scheduled"]) {
                    $output["message"] = "Campaign scheduled successfully.";

                } else {
                    $output["message"] = "Campaign sent successfully.";
                }

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 201);
    }

    /**
     * Update existing record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();
        $plan_configuration = SHAppComponent::getPlanConfiguration();
        $allowed_csv_limit = $plan_configuration["camp_csv_contact_limit"];
        $camp_multi_stage = $plan_configuration["camp_multi_stage"];

        // Set minimum and maximum defaul time interval between two mails
        $min_interval = \DEFAULT_CAMPAIGN_MINIMUM_INTERVAL_BETWEEN_MAILS;
        $max_interval = \DEFAULT_CAMPAIGN_MAXIMUM_INTERVAL_BETWEEN_MAILS;
        
        // Get request parameters
        $request_params = $request->getParsedBody();
        $route = $request->getAttribute('route');
        $request_campaign_id = $route->getArgument('id');
        $campaign_id = StringComponent::decodeRowId($request_campaign_id);

        // Validate request
        if (empty($campaign_id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        
    
        $model_campaign_master = new CampaignMaster();
        $model_campaign_stage = new CampaignStages();
        // Fetch stage data
        try {
            $condition = [
                "fields" => [
                    "cm.id",
                    "cm.timezone",                 
                    "cm.total_stages",
                    "cm.send_as_reply",
                    "cm.overall_progress"                    
                ],
                "where" => [
                    ["where" => ["cm.id", "=", $campaign_id]]
                ]
            ];
            $row = $model_campaign_master->fetch($condition);
           
            if (empty($row)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }
        }
        catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if($row["overall_progress"] == SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH")){
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", "You can not update finished campaign.", false);
        }
        
        try {
            $condition = [
                "fields" => [
                    "cst.id",
                    "cst.stage",                    
                    "cst.scheduled_on",
                    "cst.progress"                    
                ],
                "where" => [
                    ["where" => ["cm.id", "=", $campaign_id]],
                    ["where" => ["cst.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]],
                ],
                "order_by"  => [
                    "cst.id  ASC"
                ],
                "join" => [
                    "campaign_master"
                ]
            ];
            $row_seq = $model_campaign_stage->fetchAll($condition);
            
            if (empty($row_seq)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }
        }
        catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $campaignSchedule = true;
        if($row_seq[0]["progress"] <> SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED")){
            $campaignSchedule = false;
        }

        // Fetch request data
        $request_data = [
            "total_stages" => 0,
            // "timezone" => \DEFAULT_TIMEZONE,
            "other_data" => [],
            "status_message" => "",
            "csv_file"=> "",
            "sending_account" => NULL,
            "is_scheduled" => SHAppComponent::getValue("app_constant/FLAG_NO"),
            "track_reply" => SHAppComponent::getValue("app_constant/FLAG_NO"),
            "track_click" => SHAppComponent::getValue("app_constant/FLAG_YES"),
            "send_as_reply" => SHAppComponent::getValue("app_constant/FLAG_YES"),
            "min_interval" => 45,
            "max_interval" => 60,
            "cc" => "",
            "bcc" => "",
            "is_bulk_campaign" => 0,
            // "time" => (DateTimeComponent::getDateTime(NULL,\DEFAULT_TIMEZONE) + \DEFAULT_CAMPAIGN_SCHEDULE_INTERVAL),
            "stages" => [],
            "deleted_stage_ids" => []
        ];

        if (isset($request_params["title"])) {
            $request_data["title"] = $request_params["title"];
        }
        if (isset($request_params["csv_file"])) {
            $request_data["csv_file"] = $request_params["csv_file"];
        }
        if (isset($request_params["is_bulk_campaign"])) {
            $request_data["is_bulk_campaign"] = $request_params["is_bulk_campaign"];
        }
        if (isset($request_params["sending_account"])) {
            $sending_account = StringComponent::decodeRowId(trim($request_params["sending_account"]));

            if (!empty($sending_account)) {
                $request_data["sending_account"] = $sending_account;

                // set track reply field based on sending method type
                $email_type = null;
                $sending_list = null;
                $sending_methods = new AccountSendingMethods();
                $email_master = new EmailSendingMethodMaster();
                $sending_list = $sending_methods->fetchById($sending_account,["id,email_sending_method_id"]);

                if (!empty($sending_list)) {
                    $email_type = $email_master->fetchById($sending_list["email_sending_method_id"],["id,code"]);
                    if ($email_type["code"] == "GMAIL" && !empty($email_type)) {
                        $request_data["track_reply"] = SHAppComponent::getValue("app_constant/FLAG_YES");
                    }
                }  
            }    
        }
        if (isset($request_params["track_reply"])) {
            $request_data["track_reply"] = (int) $request_params["track_reply"];
        }
        if (isset($request_params["track_links"])) {
            $request_data["track_click"] = (int) $request_params["track_links"];
        }
        if (isset($request_params["send_as_thread"])) {
            $request_data["send_as_reply"] = (int) $request_params["send_as_thread"];
        }
        if (isset($request_params["min_interval"])) {
            $request_data["min_interval"] = (int) $request_params["min_interval"];
        }
        if (isset($request_params["max_interval"])) {
            $request_data["max_interval"] = (int) $request_params["max_interval"];
        }
        if (isset($request_params["timezone"])) {
            $request_data["timezone"] = $request_params["timezone"];
        }
        if (isset($request_params["cc"])) {
            $request_data["cc"] = $request_params["cc"];
        }
        if (isset($request_params["bcc"])) {
            $request_data["bcc"] = $request_params["bcc"];
        }
        if (isset($request_params["time"])) {
            $request_data["time"] = $request_params["time"];            
        }
        // else{  
        //     $request_data["time"] = DateTimeComponent::convertDateTimeCampaign($request_data["time"],true, \DEFAULT_TIMEZONE,$request_data["timezone"],\APP_TIME_FORMAT);
        // }
        if (isset($request_params["stages"])) {
            $request_data["stages"] = $request_params["stages"];
        }
        if (isset($request_params["deleted_stage_ids"])) {
            $request_data["deleted_stage_ids"] = $request_params["deleted_stage_ids"];
        }
        if (isset($request_params["is_draft"])) {
            $request_data["is_draft"] = $request_params["is_draft"];
        }
        if (isset($request_params["import_id"])) {
            $request_data["import_id"] =  StringComponent::decodeRowId(trim($request_params["import_id"]));
        } else {
            $request_data["import_id"] = 0;
        }
        if (isset($request_data["stages"][0]["subject"])) {
            $first_stage_subject = trim($request_data["stages"][0]["subject"]);
        }

        // Added seperate try catch because if we want to ignore exception in this case we can ignore it.
        try {                    
             
            if($request_data["is_bulk_campaign"] && $allowed_csv_limit != BULK_CAMPAIGN_CSV_LIMIT){
                return ErrorComponent::outputError($response, "api_messages/BULK_CAMPAIGN_NOT_ALLOWED");
            }
            
            if($camp_multi_stage == 0 && count($request_data["stages"])>1){
                return ErrorComponent::outputError($response, "api_messages/MULTI_STAGE_NOT_ALLOWED");
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        // To check if campaign save as draft or not
        if ($request_data["is_draft"] != true) {
            // Validate request
            $request_validations = [
                "title" => [
                    ["type" => Validator::FIELD_REQ_NOTEMPTY]
                ]
            ];

            if($campaignSchedule){
                $request_validations["csv_file"][] = ["type" => Validator::FIELD_REQ_NOTEMPTY];                
                $request_validations["sending_account"][] = ["type" => Validator::FIELD_REQ_NOTEMPTY];
                $request_validations["time"][] = ["type" => Validator::FIELD_REQ_NOTEMPTY];
                $request_validations["timezone"][] = ["type" => Validator::FIELD_REQ_NOTEMPTY];
            }
           
            $validation_errors = Validator::validate($request_validations, $request_data);
           
            if($campaignSchedule){
                
                $min_schedule_time = DateTimeComponent::convertDateTimeCampaign((DateTimeComponent::getDateTime() + \DEFAULT_CAMPAIGN_SCHEDULE_INTERVAL),true);                
                $schedule_date = DateTimeComponent::convertDateTimeCampaign($request_data["time"],false, $request_data["timezone"], \DEFAULT_TIMEZONE, null, \CAMPAIGN_DATE_TIME_FORMAT);
                
                if(!empty($request_data["time"]) && $min_schedule_time>$schedule_date){
                    // Add error message  
                       $validation_errors[] = "Campaign schedule time should be great then ".\DEFAULT_CAMPAIGN_SCHEDULE_INTERVAL_MINUTE." min from now.";          
                }

                // In bulk campaign set min interval as per DEFAULT_CAMPAIGN_MINIMUM_INTERVAL_BETWEEN_BULK_MAILS. 
                if($request_data["is_bulk_campaign"]){
                    $min_interval = \DEFAULT_CAMPAIGN_MINIMUM_INTERVAL_BETWEEN_BULK_MAILS;
                }
                // Check Minimum and maximum interval between two mail is proper or not
                if($min_interval>$request_data["min_interval"] || $max_interval<$min_interval || $max_interval<$request_data["max_interval"]){
                    $validation_errors[] = "Campaign minimum and maximum interval between two mail must be ".$min_interval." to ".$max_interval." seconds.";          
                }
                
                // It will give error if first stage's subject is empty or not exist.             
                if (isset($first_stage_subject)) {                                
                    if ($first_stage_subject == "") {
                        $validation_errors[] = "Parameter Required: first stage's subject is required.";                
                    }
                } else {
                    $validation_errors[] =  "Parameter Missing: first stage's subject is required.";                
                }
            }

            // If request is invalid
            if (!empty($validation_errors)) {
                // Fetch error code & message and return the response
                $additional_message = implode("\n", $validation_errors);
                return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
            }
        }    

        // Save record
        $model_campaign_master = new CampaignMaster();

        try {           
         
            if($campaignSchedule){
                
                $other_data = [
                    "cc" => $request_data["cc"],
                    "bcc" => $request_data["bcc"],
                    "days" => [1,2,3,4,5,6,7],
                    "min_seconds" => $request_data["min_interval"],
                    "max_seconds" => $request_data["max_interval"],
                    "contacts_file" => $request_data["csv_file"],
                    "import_id" => $request_data["import_id"]
                ];

                $save_data = [
                    "id" => $campaign_id,
                    "account_id" => $logged_in_account,
                    "user_id" => $logged_in_user,
                    "title" => trim($request_data["title"]),
                    "account_sending_method_id" => $request_data["sending_account"],
                    "source_id" => $logged_in_source,
                    "status_message" => '',
                    "total_stages" => $request_data["total_stages"],
                    "timezone" => trim($request_data["timezone"]),
                    "other_data" => json_encode($other_data),
                    "track_reply" => $request_data["track_reply"],
                    "track_click" => $request_data["track_click"],
                    "send_as_reply" => $request_data["send_as_reply"],
                    "is_bulk_campaign" => $request_data["is_bulk_campaign"],
                    "created" => DateTimeComponent::getDateTime(),
                    "modified" => DateTimeComponent::getDateTime()
                ];
                                 
                if ($request_data["is_draft"] == SHAppComponent::getValue("app_constant/FLAG_YES")) {
                    $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_DRAFT");
                    $save_data["progress"] = SHAppComponent::getValue("app_constant/PROGRESS_SCHEDULED");
                } else {
                    $save_data["status"] = SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                    $save_data["progress"] = SHAppComponent::getValue("app_constant/PROGRESS_SCHEDULED"); 
                    $request_data["is_scheduled"] = SHAppComponent::getValue("app_constant/FLAG_YES");
                } 

            }else { 
                   
                $request_data["time"]           = $row_seq[0]["scheduled_on"];
                $request_data["timezone"]       = $row["timezone"];
                $request_data["send_as_reply"]  = $row["send_as_reply"];
               
                $save_data = [
                    "id" => $campaign_id,
                    "title" => trim($request_data["title"]),
                    "source_id" => $logged_in_source,
                    "modified" => DateTimeComponent::getDateTime()
                ];
            }
          

            // On Successfully save campaign add all stages.
            if ($model_campaign_master->save($save_data) !== false) {
            
               
                // Save stage data
                $model_campaign_stage = new CampaignStages();
                
                // Convert request date to GMT+00:00 Timezone
                if($campaignSchedule){
                    $schedule_date = DateTimeComponent::convertDateTimeCampaign($request_data["time"],false,$request_data["timezone"]);
                }else{
                    $schedule_date = $request_data["time"];
                }
                
                $stage_number = 1;
                $previous_subject = '';
                $stageSubject = '';
                $stage_days_count = 0;
                $max_reply_check_date = 0;
                $last_insert_stage_id = 0;
                $row_seq_data = []; 
                foreach ($row_seq as $data) {
                    $row_seq_data[$data["id"]] = $data;
                }
             
                foreach ($request_data["stages"] as $stage_info) {

                 
                    if($request_data["send_as_reply"] && $stage_number>1) {
                        // Forcefully set subject as reply
                        $stageSubject = "Re: ".$first_stage_subject;

                    }else if (!empty($stage_info["subject"])) {
                        $stageSubject = trim($stage_info["subject"]);
                        $previous_subject = $stageSubject;
                    }
                    else {
                        // keep it same as previous stage subject
                        $stageSubject = $previous_subject;
                    }   

                    if(empty($stage_info["stage_defination"]["days"]) && $stage_number==1) {                        
                        $temp_convert_date = $schedule_date;
                        $max_reply_check_date = $temp_convert_date;
                    } else {

                        if(empty($stage_info["stage_defination"]["days"])){
                            $stage_info["stage_defination"]["days"] = \DEFAULT_REPLY_TRACK_DAYS;
                        }
                        else if($stage_info["stage_defination"]["days"] < \DEFAULT_REPLY_TRACK_DAYS) {
                            $stage_info["stage_defination"]["days"] = \DEFAULT_REPLY_TRACK_DAYS;
                        }

                        $stage_days_count = $stage_days_count + $stage_info["stage_defination"]["days"];
                        $temp_convert_date = ($schedule_date + ($stage_days_count * 24 * 60 * 60)); 
                        
                        $max_reply_check_date = $temp_convert_date + \CAMPAIGN_MAX_REPLY_CHECK_TIME;

                        if($last_insert_stage_id!=0){
                            
                            $condition = [
                                "where" => [
                                    ["where" => ["id","=", $last_insert_stage_id]]
                                ]
                            ];
                            $save_data = [
                                "track_reply_max_date"  => $temp_convert_date,
                                "modified"              => DateTimeComponent::getDateTime()
                            ];
                           
                            $model_campaign_stage->update($save_data, $condition);
                           
                        }
                        
                    }
                    
                    // Update/Create stage as per request data
                    if (!empty($stage_info["stage_id"])) {

                        $stage_id = StringComponent::decodeRowId($stage_info["stage_id"]);
                        
                        if($row_seq_data[$stage_id]["progress"] <> SHAppComponent::getValue("app_constant/PROGRESS_SCHEDULED")){                            
                            $stage_number++;
                            continue;
                        }
                     
                        if(!$campaignSchedule && $row_seq_data[$stage_id]["stage"] == 1 ){
                            $temp_convert_date = $row_seq_data[$stage_id]["scheduled_on"];                           
                        }
                        
                        $save_data = [
                            "id" =>  $stage_id,
                            "account_id" => $logged_in_account,
                            "user_id" => $logged_in_user,
                            "campaign_id" => $campaign_id,
                            "account_template_id" => StringComponent::decodeRowId($stage_info["template_id"]),
                            "subject" => $stageSubject,
                            "content" => trim($stage_info["content"]),
                            "stage_defination" => json_encode($stage_info["stage_defination"]),
                            "stage" => $stage_number,
                            "scheduled_on" => $temp_convert_date,
                            "track_reply_max_date" => $max_reply_check_date,
                            "created" => DateTimeComponent::getDateTime(),
                            "modified" => DateTimeComponent::getDateTime()
                        ];
                       
                      
                    } else {
                        $save_data = [
                            "account_id" => $logged_in_account,
                            "user_id" => $logged_in_user,
                            "campaign_id" => $campaign_id,
                            "account_template_id" => StringComponent::decodeRowId($stage_info["template_id"]),
                            "subject" => $stageSubject,
                            "content" => trim($stage_info["content"]),
                            "stage_defination" => json_encode($stage_info["stage_defination"]),
                            "stage" => $stage_number,
                            "scheduled_on" => $temp_convert_date,
                            "track_reply_max_date" => $max_reply_check_date,
                            "created" => DateTimeComponent::getDateTime(),
                            "modified" => DateTimeComponent::getDateTime()
                        ];
                    } 
           
                    try {
                       
                        if(isset($save_data["id"])){
                            $stage_schedule_condition = [
                                "fields" => [
                                    "cst.id",
                                    "cst.status",
                                    "cst.scheduled_on"
                                ],
                                "where" => [
                                    ["where" => ["cst.id", "=", $save_data["id"]]],
                                    ["where" => ["cst.progress", "=", SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED")]],
                                ]
                            ];
                           
                            $row = $model_campaign_stage->fetch($stage_schedule_condition);
                        
                            // Ignore not found record
                            if(empty($row)){
                                continue;
                            }
                        }
                      
                        $saved = $model_campaign_stage->save($save_data);
                       
                        if(isset($save_data["id"])){
                            $last_insert_stage_id =$save_data["id"];
                        }else{
                            $last_insert_stage_id = $model_campaign_stage->getLastInsertId();
                        }
                        
                        $stage_number++;

                    }catch(\Exception $e) {
                        // TODO : Ignore or error
                    }

                    if($camp_multi_stage == 0){
                        break;
                    }
                }

                // Delete request stages 
                if (!empty( $request_data["deleted_stage_ids"])) {
                    foreach ($request_data["deleted_stage_ids"] as $stage_delete_ids) {
                      
                        $stage_delete_id = StringComponent::decodeRowId($stage_delete_ids);
                       
                        if($row_seq_data[$stage_delete_id]["progress"] <> SHAppComponent::getValue("app_constant/PROGRESS_SCHEDULED") || $row_seq_data[$stage_delete_id]["stage"] == 1){
                            continue;
                        }
                        $save_data = [
                            "id" =>  $stage_delete_id,
                            "account_id" => $logged_in_account,
                            "user_id" => $logged_in_user,
                            "campaign_id" => $campaign_id,
                            "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                            "created" => DateTimeComponent::getDateTime(),
                            "modified" => DateTimeComponent::getDateTime()
                        ];
                     
                        try {
                            if(isset($save_data["id"])){

                                $stage_schedule_condition = [
                                    "fields" => [
                                        "cst.id",
                                        "cst.status",
                                        "cst.scheduled_on"
                                    ],
                                    "where" => [
                                        ["where" => ["cst.id", "=", $save_data["id"]]],
                                        ["where" => ["cst.progress", "=", SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED")]],
                                    ]
                                ];
                             
                                $row = $model_campaign_stage->fetch($stage_schedule_condition);
                                // Ignore not found recound
                                if(empty($row)){
                                    continue;
                                }
                            }
                            $saved = $model_campaign_stage->save($save_data);
                        } catch(\Exception $e) {
                            // TODO : Ignore or error
                        }
                    }
                }

                // Update total stages
                try {
                    $save_data = [
                        "id" => $campaign_id,
                        "total_stages" => $stage_number - 1
                    ];
                    $saved = $model_campaign_master->save($save_data);

                   
                    if (isset($save_data["total_stages"]) && $save_data["total_stages"] != null) {
                        $camp_first_stage = $row_seq[$save_data["total_stages"] - 1]["progress"];
                        if ($camp_first_stage != null && !empty($camp_first_stage) && $camp_first_stage == 6) {
                            $save_data = [
                                "id" => $campaign_id,
                                "overall_progress" => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH")
                            ];
                            $saved = $model_campaign_master->save($save_data);
                        }
                    }   
                } catch(\Exception $e) { }


                $output["id"] = StringComponent::encodeRowId($campaign_id);
                if ($request_data["is_draft"]) {
                    $output["message"] = "Campaign saved successfully.";

                } else if ($request_data["is_scheduled"]) {
                    $output["message"] = "Campaign scheduled successfully.";

                } else {
                    $output["message"] = "Campaign updated successfully.";
                }

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * View single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function view(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
      
        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();

        // Get request paramaters
        $route = $request->getAttribute('route');
        $request_campaign_id = $route->getArgument('id');

        // Decode request id
        $campaign_id = StringComponent::decodeRowId($request_campaign_id);
        
        // Validate request
        if (empty($campaign_id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }
        
        $model_campaign_master              = new CampaignMaster();
        $model_campaign_stage               = new CampaignStages();
        $model_email_sending_method_master  = new EmailSendingMethodMaster();

        // Campaign model
        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_campaign_master->checkRowValidity($campaign_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $show_pause = false;
        $show_resume = false;
       
        // Fetch data
        try {
            $condition = [
                "fields" => [
                    "cm.id",
                    "cm.title",
                    "cm.account_sending_method_id",
                    "cm.total_stages",
                    "cm.timezone",
                    "cm.status_message",
                    "cm.track_reply",
                    "cm.track_click",
                    "cm.overall_progress",
                    "cm.other_data",
                    "cm.is_bulk_campaign",
                    "cm.send_as_reply",
                    "cm.status",
                    "cm.user_id",
                    "um.first_name",
                    "um.email",
                    "um.last_name",
                    "asm.id as sending_method_id",
                    "asm.from_email",
                    "asm.email_sending_method_id",
                    "(SELECT SUM(cst.total_contacts - cst.total_deleted) FROM campaign_stages cst WHERE cst.campaign_id = cm.id) as total_recipients",
                    "(SELECT SUM(cst.total_success) FROM campaign_stages cst WHERE cst.campaign_id = cm.id AND cst.status <> ".$other_values["deleted"].") as email_success_count",
                    "(SELECT COUNT(csq.open_count) FROM campaign_sequences csq WHERE csq.campaign_id = cm.id AND csq.open_count > 0 AND csq.status <> ".$other_values["deleted"]." AND csq.is_bounce <> 1) as total_email_opened",
                    "(SELECT COUNT(csq.replied) FROM campaign_sequences csq WHERE csq.campaign_id = cm.id AND csq.status <> ".$other_values["deleted"]." AND csq.open_count > 0 AND csq.is_bounce <> 1 AND csq.replied <> 0) as reply_count",
                    "(SELECT COUNT(csq.click_count) FROM campaign_sequences csq WHERE csq.campaign_id = cm.id AND csq.status <> " .$other_values["deleted"]. " AND csq.click_count > 0 AND csq.is_bounce <> 1) as total_link_clicked_count",
                    "(SELECT COUNT(csq.is_bounce) FROM campaign_sequences csq WHERE csq.campaign_id = cm.id AND csq.is_bounce = 1 AND csq.status <> " .$other_values["deleted"].") as total_bounce_count"
                ],
                "where" => [
                    ["where" => ["cm.id", "=", $campaign_id]],
                ],
                "join" => [
                    "account_sending_methods",
                    "user_master"
                ]
            ];
            $row = $model_campaign_master->fetch($condition);
           
            // Get CSV file data
            $other_data_temp = json_decode($row["other_data"]);
            $csv_file_data = [];
            $file_path = __DIR__."/../../upload/". $other_data_temp->contacts_file;      
            if (!empty($file_path)) {
                $data = null;
                try {
                    $contacts_data = @file_get_contents($file_path);

                } catch(Exception $e) {
                    // To DO
                }
                
                if (!empty($contacts_data)) {
                    $csv_file_data = $contacts_data;                  
                } 
            }

            
            // Get campaign stages'Id
            if (!empty($row["id"])) {

                // Whether to show pause / resume buttons
                if ($row["overall_progress"] == SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED")) {
                    // If already pased, then show resume button
                    $show_resume = true;

                } else if ($row["overall_progress"] != SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH") && $row["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE")) {
                    // If campaign hasn't been finished yet, then show pause button
                    $show_pause = true;
                }

                // Campaign stage model
                $condition = [
                    "fields" => [
                        "cst.id",
                        "cst.campaign_id",
                        "cst.subject",
                        "cst.content",
                        "cst.stage_defination",
                        "cst.account_template_id",
                        "cst.stage",
                        "cst.progress",
                        "cst.scheduled_on"
                    ],
                    "where" => [
                        ["where" => ["cst.campaign_id", "=", $row["id"]]],
                        ["where" => ["cst.status", "<>",SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                    ],
                    "order_by" => [
                        "cst.stage ASC"
                    ]
                ];
                $campaign_stage_data = $model_campaign_stage->fetchAll($condition);
                $current_stage = 0;
                $stage_array = [];
                $removed_contact = 0;
                $scheduled_days_from_start = 0;
                $days_delay = 0;
                $first_stage_schedule_time = 0;
                if (!empty($campaign_stage_data)) {
                    // Check if any queue is prepared for the campaign, then only allow to pause the campaign, otherwise don't
                    if ($campaign_stage_data[0]['progress'] < SHAppComponent::getValue("app_constant/CAMP_PROGRESS_QUEUED")) {
                        $show_pause = false;
                    }
                    
                    for ($i = 0; $i < count($campaign_stage_data); $i++) {

                        $current_stage_defination = json_decode($campaign_stage_data[$i]['stage_defination'],true);
                        if ($i == 0) {    
                            $first_stage_schedule_time = $campaign_stage_data[$i]['scheduled_on'];                        
                            $scheduled_date_format =  DateTimeComponent::convertDateTimeCampaign($campaign_stage_data[$i]['scheduled_on'],true,\DEFAULT_TIMEZONE,$row["timezone"], \APP_TIME_FORMAT);                            
                        } else {
                            $scheduled_date_format =DateTimeComponent::convertDateTimeCampaign($campaign_stage_data[$i]['scheduled_on'],true,\DEFAULT_TIMEZONE,$row["timezone"],\APP_TIME_FORMAT)." ".$row["timezone"];
                            $scheduled_days_from_start =  $scheduled_days_from_start + $current_stage_defination["days"];
                        }
                        
                        $ignore_count = empty($current_stage_defination["ignore"]) ? 0 : count($current_stage_defination["ignore"]);
                        $removed_contact = $removed_contact + $ignore_count;
                        $delay_stage_info = "";

                        $stage_array[$i] = array(
                            "id" => StringComponent::encodeRowId($campaign_stage_data[$i]['id']),
                            "campaign_id" => StringComponent::encodeRowId($campaign_stage_data[$i]['campaign_id']),
                            "subject" => $campaign_stage_data[$i]['subject'],
                            "content" => $campaign_stage_data[$i]['content'],
                            "stage" => $campaign_stage_data[$i]['stage'],
                            "progress" => $campaign_stage_data[$i]['progress'],
                            "scheduled_on" => $scheduled_date_format,
                            "stage_defination" => $campaign_stage_data[$i]['stage_defination'],
                            "account_template_id" => StringComponent::encodeRowId($campaign_stage_data[$i]['account_template_id']),
                            "delay_stage_info" =>""
                        );
                        
                        $current_time = DateTimeComponent::getDateTime();
                        
                        $is_delay = false;
                        
                        if($days_delay == 0 && $campaign_stage_data[$i]['progress'] != SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH")
                                            && $campaign_stage_data[$i]['progress'] == SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED"))
                        {                           
                            $diff_new_old = $current_time - $campaign_stage_data[$i]['scheduled_on']; 
                            $days_delay = floor($diff_new_old / (24 * 60 * 60));
                        }

                        $reschedule_time = $first_stage_schedule_time + ( $scheduled_days_from_start * 24 * 60 * 60);
                        $diffrence_between_schedule_and_days = $campaign_stage_data[$i]['scheduled_on'] - $reschedule_time;
                        
                        if($days_delay > 0){
                            $stage_array[$i]["delay_stage_info"] = "Delayed due to campaign pause.";    
                        }

                        if($diffrence_between_schedule_and_days > 60){   
                            $stage_array[$i]["delay_stage_info"] = "Delayed due to campaign pause.";
                            if($show_pause){
                                $original = DateTimeComponent::convertDateTimeCampaign($campaign_stage_data[$i]['scheduled_on'], true, \DEFAULT_TIMEZONE, $row["timezone"], \APP_TIME_FORMAT);
                                $reschedule_text = DateTimeComponent::convertDateTimeCampaign($reschedule_time, true, \DEFAULT_TIMEZONE, $row["timezone"], \APP_TIME_FORMAT);
                                $stage_array[$i]["delay_stage_info"] =  $stage_array[$i]["delay_stage_info"] ." (re-scheduled @ ".$reschedule_text.")";
                            }                               
                        }

                        // check if campaign is draft then change msg for delay_stage_info
                        if (isset($row["status"]) && $row["status"] == 0) {
                            $stage_array[$i]["delay_stage_info"] = "";
                        }

                        if($campaign_stage_data[$i]["progress"] <> SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH") && $current_stage == 0){                    
                            $current_stage = $campaign_stage_data[$i]['stage'];                            
                        }else if($row["total_stages"]>$i+1 && $current_stage == 0){                            
                            if($campaign_stage_data[$i+1]["progress"] == SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH")){                                
                                continue;
                            }                                                 
                            $current_stage = $campaign_stage_data[$i+1]['stage'];
                        }else if($stage_array[$i]["progress"]==SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH")) {
                            $current_stage = $campaign_stage_data[$i]['stage']; 
                        }
                    }
                } else {
                    $stage_array = null;
                }
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get email sending method name
        try {
            $email_sending_mothods = $model_email_sending_method_master->fetchList("id", "code");
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        $email_sending_method_name = array_key_exists($row["email_sending_method_id"], $email_sending_mothods) ? $email_sending_mothods[$row["email_sending_method_id"]] : "";
    
        // Get status lable and date
        try {
            $condition = [
                "fields"    => [
                    "cst.stage",
                    "cst.scheduled_on"
                ],
                "where"     => [
                    ["where" => ["cst.campaign_id", "=", $campaign_id]],
                    ["where" => ["cst.progress", "=", 0]]
                ],
                "order_by"  => [
                    "cst.id  ASC"
                ],
                "limit"     => 1
            ];
            $campaign_status_data = $model_campaign_stage->fetch($condition);
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        $total_recipients = $row["total_recipients"];
     
        if ( !empty($campaign_status_data) ) {
            if ( $campaign_status_data["stage"] == 1 ) {
                $status_label = "Scheduled";
            } else {
                $status_label = "Next run";
            }
            $status_date = $campaign_status_data["scheduled_on"]; 
            $campaign_status_data = $model_campaign_stage->fetch($condition);
            $contacts_data_array = [];
            if(!empty($csv_file_data)){
                $contacts_data_array = json_decode($csv_file_data, true);
                $total_contacts      = count($contacts_data_array);
                $total_contacts      = $total_contacts - $removed_contact;
                $total_recipients    = $total_contacts ? --$total_contacts : 0 ; 
            }
        } else {
           
            try {
                $condition = [
                    "fields"    => [
                        "cst.progress",
                        "cst.scheduled_on",
                        "cst.finished_on",
                        "cst.stage"
                    ],
                    "where"     => [
                        ["where" => ["cst.campaign_id", "=", $campaign_id]]
                    ],
                    "order_by"  => [
                        "cst.id  DESC"
                    ],
                    "limit"     => 1
                ];
                $campaign_status_data = $model_campaign_stage->fetch($condition);
            } catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

            if ( $campaign_status_data["progress"] == SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH") ) {
                $status_label   = "Finished";
                $status_date    = $campaign_status_data["finished_on"];
            } else {
                $status_label   = "Scheduled";
                $status_date    = $campaign_status_data["scheduled_on"]; 
            }
        }
             
        $status_date = DateTimeComponent::convertDateTimeCampaign($status_date,true,\DEFAULT_TIMEZONE,$row["timezone"],\CAMPAIGN_DATE_FORMAT);
        if($row["overall_progress"] == SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED")){
            $status_label   = "Paused";
            $status_date    = ""; 
        }
        $success_ratio  = $this->getPerformanceText($row["email_success_count"], $total_recipients);
        $open_ratio     = $this->getPerformanceText($row["total_email_opened"], $total_recipients);
        $bounce_ratio   = $this->getPerformanceText($row["total_bounce_count"], $total_recipients);
        
        if ( $row["track_reply"] == 1) {
            $reply_ratio    = $this->getPerformanceText($row["reply_count"], $total_recipients);
        } else {
            $reply_ratio    = "";
        }
        if ( $row["track_click"] == 1) {
            $click_ratio    = $this->getPerformanceText($row["total_link_clicked_count"], $total_recipients);
        } else {
            $click_ratio    = "";
        }

        $created_by = (($row["user_id"] == $logged_in_user) ? "Me" : trim($row["first_name"] . " " . $row["last_name"]));
        $created_by_email = $row["email"];

        $output = [
            "id"                            => StringComponent::encodeRowId($row["id"]),
            "title"                         => $row["title"],
            "account_sending_method_id"     => StringComponent::encodeRowId($row["account_sending_method_id"]),
            "account_sending_method_name"   => $row["from_email"],
            "email_sending_method_name"     => $email_sending_method_name,
            "created_by"                    => $created_by,
            "created_by_email"              => $created_by_email,
            "status_label"                  => $status_label,
            "current_stage"                 => $current_stage,
            "status_date"                   => $status_date,
            "total_stages"                  => $row["total_stages"],
            "timezone"                      => $row["timezone"],
            "status_message"                => $row["status_message"],
            "send_as_reply"                 => $row["send_as_reply"],
            "track_reply"                   => $row["track_reply"],
            "track_click"                   => $row["track_click"],
            "check_progress"                => $row["overall_progress"],
            "overall_progress"              => $this->getProgressText($row["overall_progress"], $row["status"]),
            "other_data"                    => $row["other_data"],
            "csv_file_data"                 => $csv_file_data,
            "is_bulk_campaign"              => $row["is_bulk_campaign"],
            "status"                        => $row["status"],
            "stages"                        => $stage_array,
            "total_recipients"              => $total_recipients,
            "email_success_count"           => $row["email_success_count"],
            "total_email_opened"            => $row["total_email_opened"],
            "reply_count"                   => $row["reply_count"],
            "total_link_clicked_count"      => $row["total_link_clicked_count"],
            "total_bounce_count"            => $row["total_bounce_count"],
            "success_ratio"                 => $success_ratio,
            "open_ratio"                    => $open_ratio,            
            "reply_ratio"                   => $reply_ratio,
            "click_ratio"                   => $click_ratio,
            "bounce_ratio"                  => $bounce_ratio,
            "show_pause"                    => $show_pause,
            "show_resume"                   => $show_resume
        ];

        if (!empty($row["last_error"])) {
            $output["last_error"] = $row["last_error"];
        }

        return $response->withJson($output, 200);
    }

    /**
     * View single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function viewRecipient(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();

        // Get request paramaters
        $route = $request->getAttribute('route');
        $request_campaign_id = $route->getArgument('id');

        // Decode request id
        $campaign_id = StringComponent::decodeRowId($request_campaign_id);
    
        // Validate request
        if (empty($campaign_id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        // Campaign model
        $model_campaign_master = new CampaignMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_campaign_master->checkRowValidity($campaign_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
       
        // Fetch data
        try {
            // Campaign seqeunce model
            $model_campaign_sequence = new CampaignSequences();
            
            $condition = [
                "fields" => [
                    "csq.id",
                    "ac.email",
                    "csq.progress",
                    "csq.is_bounce",
                    "csq.scheduled_at",
                    "csq.sent_at",
                    "csq.open_count",
                    "csq.last_opened",
                    "csq.replied",
                    "csq.click_count",
                    "csq.last_clicked",
                    "csq.status" ],
                "where" => [
                    ["where" => ["csq.campaign_id", "=", $campaign_id]],
                    ["where" => ["cst.stage", "=", 1]],
                ],
                "join" => [
                    "campaign_stages",
                    "account_contacts"
                ]
            ];
            $row_seq = $model_campaign_sequence->fetchAll($condition);


        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $output = [
            "id" => StringComponent::encodeRowId($row["id"])
            
        ];

        return $response->withJson($output, 200);
    }

    /**
     * View records 
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function viewRecipientClickCount(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
        
        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();
     
        // Get request paramaters
        $route = $request->getAttribute('route');
        $campaign_stage_id       = $route->getArgument('stage_id'); // Request campaign' stage id
        $campaign_sequence_id    = $route->getArgument('seq_id'); // Request campaign's Stage sequence id
      
        // Decode request id
        $campaign_stage_id = StringComponent::decodeRowId($campaign_stage_id);
        $campaign_sequence_id = StringComponent::decodeRowId($campaign_sequence_id);

        // Validate request
        if (empty($campaign_stage_id) || empty($campaign_sequence_id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }
        
        // Campaign sequences model
        $model_campaign_sequence = new CampaignSequences();
        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_campaign_sequence->checkRowValidity($campaign_sequence_id, $other_values);
           
            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
       
        // Fetch data
        try {
            // Campaign seqeunce model
            $model_campaign_links = new CampaignLinks();
            
            $condition = [
                "fields" => [
                    "cl.id",
                    "cl.total_clicked",
                    "cl.last_clicked",
                    "alm.url",
                    "cm.timezone"
                     ],
                "where" => [
                    ["where" => ["cl.campaign_sequence_id", "=", $campaign_sequence_id]]
                ],
                "join" => [
                    "campaign_master",
                    "account_link_master"
                ]
            ];
            $row_seq = $model_campaign_links->fetchAll($condition);
           
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        $output = [];
        for($i=0;$i<count($row_seq);$i++){
            $last_clicked = "";
            if($row_seq[$i]["total_clicked"] > 0 && !empty($row_seq[$i]["last_clicked"])){
                $last_clicked = DateTimeComponent::convertDateTimeCampaign($row_seq[$i]["last_clicked"], true, \DEFAULT_TIMEZONE, $row_seq[$i]["timezone"], \CAMPAIGN_DATE_FORMAT);
            } 
            $output[$i] = [
                "url" => $row_seq[$i]["url"],            
                "last_clicked" => $last_clicked,
                "total_clicked" => $row_seq[$i]["total_clicked"]
            ];
        }
        return $response->withJson($output, 200);
    }

    /**
     * Copy single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function copy(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();
        $plan_configuration = SHAppComponent::getPlanConfiguration();
        $allowed_campaign_limit = $plan_configuration["total_camp_create_count"];
        $allowed_csv_limit = $plan_configuration["camp_csv_contact_limit"];
        $camp_multi_stage = $plan_configuration["camp_multi_stage"];
   
        // Get request paramaters
        $route = $request->getAttribute('route');
        $request_campaign_id = $route->getArgument('id');
        $campaign_id = StringComponent::decodeRowId($request_campaign_id);

        // Validate request
        if (empty($campaign_id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        // Campaign model
        $model_campaign_master = new CampaignMaster();

          // Added seperate try catch because if we want to ignore exception in this case we can ignore it.
        try {
            $is_allowed_to_create = $model_campaign_master->isAllowedToCreateCampaign($logged_in_account, $allowed_campaign_limit);            
            if(!$is_allowed_to_create){
                return ErrorComponent::outputError($response, "api_messages/CAMPAIGN_QUOTA_OVER");
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        // Campaign  Stage model
        $model_campaign_stage = new CampaignStages(); 

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_campaign_master->checkRowValidity($campaign_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        try {
            $condition = [
                "fields" => [
                    "cm.id",
                    "cm.user_id",
                    "cm.title",
                    "cm.account_sending_method_id",
                    "cm.from_date",
                    "cm.to_date",
                    "cm.total_stages",
                    "cm.timezone",
                    "cm.status_message",
                    "cm.track_reply",
                    "cm.track_click",
                    "cm.send_as_reply",
                    "cm.is_bulk_campaign",
                    "cm.overall_progress",
                    "cm.priority",
                    "cm.snooze_notifications",
                    "cm.other_data",
                    "cm.status",
                    "asm.id as sending_method_id",
                    "asm.status as sending_method_status"
                ],
                "where" => [
                    ["where" => ["cm.id", "=", $campaign_id]]
                ],
                "join" => [
                    "account_sending_methods"
                ]
            ];
            $row = $model_campaign_master->fetch($condition);
           
            // Save new campaing and save new stages
            if (!empty($row)) {
                $account_sending_method_id = null;
                if ($logged_in_user == $row["user_id"] && $row["sending_method_status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE")) {
                    $account_sending_method_id = $row["account_sending_method_id"];
                }

                // Added seperate try catch because if we want to ignore exception in this case we can ignore it.
                try {                    
                    if($row["is_bulk_campaign"] && $allowed_csv_limit != BULK_CAMPAIGN_CSV_LIMIT){
                        return ErrorComponent::outputError($response, "api_messages/BULK_CAMPAIGN_NOT_ALLOWED");
                    }
                } catch(\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }
                
                $save_data = [
                    "account_id" => $logged_in_account,
                    "user_id" => $logged_in_user,
                    "source_id" => $logged_in_source,
                    "title" => "Copy of " . trim($row["title"]),
                    "account_sending_method_id" => $account_sending_method_id,
                    "from_date" => $row["from_date"],
                    "to_date" => $row["to_date"],
                    "total_stages" => $row["total_stages"],
                    "timezone" => trim($row["timezone"]),
                    "status_message" => $row["status_message"],
                    "other_data" => $row["other_data"],
                    "track_reply" => $row["track_reply"],
                    "track_click" => $row["track_click"],
                    "send_as_reply" => $row["send_as_reply"],
                    "is_bulk_campaign" => $row["is_bulk_campaign"],
                    "overall_progress" => SHAppComponent::getValue("app_constant/PROGRESS_SCHEDULED"),
                    "priority" => $row["priority"],
                    "snooze_notifications" => $row["snooze_notifications"],
                    "status" => SHAppComponent::getValue("app_constant/STATUS_DRAFT"),
                    "created" => DateTimeComponent::getDateTime(),
                    "modified" => DateTimeComponent::getDateTime()
                ];
               
                if ($model_campaign_master->save($save_data) !== false) {
                  
                    $inserted_campaign_id = $model_campaign_master->getLastInsertId();
                    
                    // Get all stages data
                    $condition = [
                        "fields" => [
                            "cst.id",
                            "cst.subject",
                            "cst.content",
                            "cst.account_template_id",
                            "cst.stage",
                            "cst.stage_defination",
                            "cst.scheduled_on",
                            "cst.track_reply_max_date",
                            "cst.status" ],
                        "where" => [
                            ["where" => ["cst.campaign_id", "=", $campaign_id]],
                            ["where" => ["cst.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                        ]
                    ];       
                    $row_stages = $model_campaign_stage->fetchAll($condition);
    
                    // Save new stages data using new campaing Id
                    if (!empty($row_stages)) {
                        foreach ($row_stages as $stage_info) {

                            $stage_defination   = json_decode($stage_info['stage_defination'], true);
                  
                            // check if any contacts to be ignored
                            $stage_defination['ignore'] = [];
                            $stage_defination = json_encode($stage_defination);                            
                            $save_data = [
                                "account_id" => $logged_in_account,
                                "user_id" => $logged_in_user,
                                "campaign_id" => $inserted_campaign_id,
                                "subject" => trim($stage_info["subject"]),
                                "content" => trim($stage_info["content"]),
                                "stage_defination" => $stage_defination,
                                "scheduled_on" => $stage_info["scheduled_on"],
                                "track_reply_max_date" => $stage_info["track_reply_max_date"],
                                "stage" => $stage_info["stage"],
                                "account_template_id" => $stage_info["account_template_id"],
                                "created" => DateTimeComponent::getDateTime(),
                                "modified" => DateTimeComponent::getDateTime()
                            ];

                            try {
                                $saved = $model_campaign_stage->save($save_data);

                            } catch(\Exception $e) { }
                            
                            if($camp_multi_stage == 0 ){
                                break;
                            }
                        }   
                    }
                    $output["message"] = "Campaign copied successfully";
                    
                } else {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }   
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 201);
    }

    /**
     * Delete single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();
        $in_progress = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_IN_PROGRESS");

        // Get request paramaters
        $route = $request->getAttribute('route');
        $request_campaign_id = $route->getArgument('id');
        $campaign_id = StringComponent::decodeRowId($request_campaign_id);

        // Validate request
        if (empty($campaign_id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        // Campaign model
        $model_campaign_master = new CampaignMaster(); 

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_campaign_master->checkRowValidity($campaign_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
       
        // Fetch data
        try {
            $condition = [
                "fields" => [
                    "cm.id",
                ],
                "where" => [
                    ["where" => ["cm.id", "=", $campaign_id]],
                    ["where" => ["cm.overall_progress", "!=", $in_progress]]
                ],
            ];
            $row = $model_campaign_master->fetch($condition); 

            if (!empty($row['id'])) {
                $save_data = [
                    "id" => $row['id'],
                    "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                    "modified" => DateTimeComponent::getDateTime()
                ];
                if ($model_campaign_master->save($save_data) !== false) {
                    $output["message"] = "Campaign deleted successfully.";

                } else {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }
            } else {
               // Fetch error code & message and return the response
               return ErrorComponent::outputError($response, "api_messages/INVALID_DELETE_REQUEST");  
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }      

        return $response->withJson($output, 200);
    }

    /**
     * Update status for single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function statusUpdate(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();

        return $response->withJson($output, 200);
    }


    /**
     * Snooze notifications (toggle)
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function snooze(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();

        return $response->withJson($output, 200);
    }

    /**
     * View single stage information
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function viewStage(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output             = [];
        $server_timezone    = \DEFAULT_TIMEZONE;
        $total_records_to_render = 0;
        // Get logged in user details
        $logged_in_user     = SHAppComponent::getUserId();
        $logged_in_account  = SHAppComponent::getAccountId();
        $is_owner           = SHAppComponent::isAccountOwner();
        $logged_in_source   = SHAppComponent::getRequestSource();

        // Get request parameter
        $route                  = $request->getAttribute('route');
        $request_campaign_id    = $route->getArgument('id'); // Request campaign'id
        $request_stage_id       = $route->getArgument('stage_id'); // Request campaign's Stage id

        $campaign_id    = StringComponent::decodeRowId($request_campaign_id);
        $stage_id       = StringComponent::decodeRowId($request_stage_id);

        // Validate request
        if (empty($campaign_id) || empty($stage_id) ) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }      

        $model_campaign_stage   = new CampaignStages();

        // Campaign stage model
        try {
            // Other values for condition
            $other_values = [
                "user_id"       => $logged_in_user,
                "account_id"    => $logged_in_account,
                "deleted"       => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];
            $valid = $model_campaign_stage->checkRowValidity($stage_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        
        try {
            // Fetch data
            $condition = [
                "fields" => [
                    "cm.timezone",
                    "cm.other_data",
                    "cm.track_reply",
                    "cm.track_click",
                    "cm.status",
                    "cm.overall_progress",
                    "cst.id",
                    "cst.subject",
                    "cst.content",
                    "cst.stage",
                    "cst.stage_defination",
                    "cst.scheduled_on",
                    "cst.track_reply_max_date",
                    "cst.progress",
                    "cst.total_contacts",
                    "cst.total_success",
                    "cst.total_fail",
                    "cst.total_deleted",
                    "(SELECT COUNT(csqt.id) FROM campaign_sequences csqt WHERE cst.id = csqt.campaign_stage_id AND csqt.status <> " . SHAppComponent::getValue("app_constant/STATUS_DELETE") . ") as total_contact_records",
                    "(SELECT COUNT(csqb.id) FROM campaign_sequences csqb WHERE cst.id = csqb.campaign_stage_id AND csqb.status <> " . SHAppComponent::getValue("app_constant/STATUS_DELETE") . " AND csqb.is_bounce = 1 ) as total_bounce_count",
                    "(SELECT COUNT(csqo.id) FROM campaign_sequences csqo WHERE cst.id = csqo.campaign_stage_id AND csqo.status <> " . SHAppComponent::getValue("app_constant/STATUS_DELETE") . " AND csqo.open_count > 0 AND csqo.is_bounce <> 1 ) as total_email_opened",
                    "(SELECT COUNT(csqr.id) FROM campaign_sequences csqr WHERE cst.id = csqr.campaign_stage_id AND csqr.status <> " . SHAppComponent::getValue("app_constant/STATUS_DELETE") . " AND csqr.replied = 1 AND csqr.is_bounce <> 1) as reply_count",
                    "(SELECT COUNT(csqc.id) FROM campaign_sequences csqc WHERE cst.id = csqc.campaign_stage_id AND csqc.status <> " . SHAppComponent::getValue("app_constant/STATUS_DELETE") . " AND csqc.click_count > 0 AND csqc.is_bounce <> 1) as total_link_clicked_count"
                ],
                "where" => [
                    ["where" => ["cst.id", "=", $stage_id]]
                ],
                "join" => [
                    "campaign_master"
                ]
            ];
            $row = $model_campaign_stage->fetch($condition);
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
             
        // Get Sequence Data             
        if (!empty($row['id'])) {
            $scheduled_on               = DateTimeComponent::convertDateTimeCampaign($row["scheduled_on"], true, $server_timezone, $row["timezone"], \CAMPAIGN_DATE_FORMAT);
            $output["scheduled_on"]     = $scheduled_on;
            $output["timezone_text"]    = $row["timezone"];
            $stage_defination           = json_decode($row["stage_defination"], true);            
            $ignore_count = empty($stage_defination["ignore"]) ? 0 : count($stage_defination["ignore"]); 
            $removed_contact = $ignore_count;
            $text = "<p><b>Condition:&nbsp;</b>Stage is scheduled to be processed @ <b>".$output["scheduled_on"]." ".$output["timezone_text"]."</b></p>";
            if ( $row["stage"] > 1 ) {
                $text = "<p><b>Condition:&nbsp;</b> ";
                if($stage_defination["condition"] == SHAppComponent::getValue("app_constant/CONDITION_NOT_OPEN")) {
                    $text .= "<span class=".CAMPAIGN_CONDITION_CSS.">Not Opened</span>";

                } else if($stage_defination["condition"] == SHAppComponent::getValue("app_constant/CONDITION_NOT_REPLY")) {
                    $text .= "<span class=".CAMPAIGN_CONDITION_CSS.">Not Replied</span>";

                } else if($stage_defination["condition"] == SHAppComponent::getValue("app_constant/CONDITION_SENT")) {
                    $text .= "<span class=".CAMPAIGN_CONDITION_CSS.">Been Sent</span>";
                }
                $text .= " to the message from <span class=".CAMPAIGN_CONDITION_CSS.">Stage ".($row["stage"]-1)."</span>";
                $text .= " after <b>".$stage_defination["days"]." day(s)</b> that is";
                $text .= " @ <b>".$output["scheduled_on"]." ".$output["timezone_text"]."</b></p>";
            }
            
            if (!empty($row["total_contact_records"])) {
                $total_records_to_render = $row["total_contact_records"];
            } else if ($row["stage"] == 1) {
                // if sequence is not created for stage and it is first stage, then read from csv file
                $record_other_data  = json_decode($row["other_data"], true);
                $stage_defination   = json_decode($row['stage_defination'], true);
                $ignore_count = empty($stage_defination["ignore"]) ? 0 : count($stage_defination["ignore"]);
                $removed_contact = $ignore_count;
                $file_path = __DIR__."/../../upload/". $record_other_data["contacts_file"];   
                $contacts_data_array = [];
                if (!empty($file_path)) {
                    $contacts_data              = @file_get_contents($file_path);
                    $contacts_data_array        = json_decode($contacts_data, true);
                }
                $total_contacts             = count($contacts_data_array);
                $total_records_to_render    = $total_contacts - $removed_contact - 1; // -1 for csv header.
                // If total contact is not 0 then it will reduce contact by 1 else set it 0.
                $total_records_to_render    = $total_records_to_render>0 ? $total_records_to_render : 0 ;
            }else{
              
                 // if sequence is not created for stage and it is higher from first stage, then read from previous stage
                 try {
                    $condition = [
                        "fields" => [
                            "id"
                        ],
                        "where" => [
                            ["where" => ["campaign_id", "=", $campaign_id ]],
                            ["where" => ["stage", "=", $row["stage"] - 1 ]]
                        ]
                    ];

                    $row_previous_stage = $model_campaign_stage->fetch($condition);
                   
                } catch(\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

                if ( !empty($row_previous_stage["id"]) ) {
                    $previous_stage_id  = $row_previous_stage["id"];
                    $stage_defination   = json_decode($row['stage_defination'], true);
                  
                    // check if any contacts to be ignored
                    if(empty($stage_defination['ignore'])) {
                        $stage_defination['ignore'] = [];
                    }

                    // Get sequence of previous stage
                    $fetch_contact_condition = [
                        ["where" => ["csq.status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE") ]],
                        ["where" => ["csq.progress", "=", SHAppComponent::getValue("app_constant/SEQ_PROGRESS_SENT") ]],
                        ["where" => ["csq.campaign_id", "=", $campaign_id ]],
                        ["where" => ["csq.campaign_stage_id", "=", $previous_stage_id ]]
                    ];
                    if(!empty($stage_defination["condition"])) {
                        switch($stage_defination["condition"]) {
                            case SHAppComponent::getValue("app_constant/CONDITION_NOT_OPEN"):
                                $fetch_contact_condition[] = ["where" => ["csq.open_count", "=", 0 ]];
                                break;

                            case SHAppComponent::getValue("app_constant/CONDITION_NOT_REPLY"):
                                $fetch_contact_condition[] = ["where" => ["csq.replied", "=", 0 ]];
                                break;

                            case SHAppComponent::getValue("app_constant/CONDITION_SENT"):
                                break;
                                
                            default:
                                break;
                        }
                    }
                    $model_campaign_sequence    = new CampaignSequences();
                    // Total contacts
                    try {
                        $condition = [
                            "fields"    => [
                                "COUNT(id) AS cnt" 
                            ],
                            "where"     => $fetch_contact_condition
                        ];
                       
                        $rows_sequence_contacts_total   = $model_campaign_sequence->fetch($condition);
                        $total_contacts = (empty($rows_sequence_contacts_total["cnt"])) ? 0 : (int) $rows_sequence_contacts_total["cnt"];
                        $total_contacts  = $total_contacts - $removed_contact; 
                        
                    } catch(\Exception $e) {
                        // Fetch error code & message and return the response
                        return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                    }
                        
                    $total_records_to_render    = $total_contacts < 0 ? 0 : $total_contacts;
                }
              
            }
            
            $stage_progress = $row["progress"];
            if($stage_progress <> SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH") &&
             $row["overall_progress"] == SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED")){
                $stage_progress = $row["overall_progress"];
            }

            $delay_stage_info = "";
            $delay_response = $this->getCampaignDelayTime($campaign_id, $row['stage']);
            
            if(isset($delay_response) && $delay_response["is_delay"] && isset($row['status']) && $row['status'] != 0) {
                $delay_stage_info = "Campaign delayed due to pause.";
            }
            
            $output["text"]                     = $text;
            $output['id']                       = StringComponent::encodeRowId($row["id"]);
            $output['subject']                  = $row['subject'];
            $output['content']                  = $row['content'];
            $output['stage']                    = $row['stage'];
            $output['stage_defination']         = $row['stage_defination'];
            $output['stage_days']               = $stage_defination['days'];
            $output['stage_condition']          = $stage_defination['condition'];
            $output['allow_reply']              = $row['track_reply'];
            $output["delay_stage_info"]         = $delay_stage_info;
            $output["track_reply"]              = $row['track_reply'];
            $output["track_click"]              = $row["track_click"];
            $output["total_recipients"]         = $total_records_to_render;
            $output["email_success_count"]      = $row["total_success"];
            $output["fail_count"]               = $row["total_fail"];
            $output["delete_count"]             = $row["total_deleted"];
            $output["total_email_opened"]       = $row["total_email_opened"];
            $output["reply_count"]              = $row["reply_count"];
            $output["total_bounce_count"]       = $row["total_bounce_count"];
            $output["total_link_clicked_count"] = $row["total_link_clicked_count"];
            $output["export_csv"]               = StringComponent::encodeRowId($row["id"]);
            $output["check_progress"]           = $row["progress"];
            $output['read_only_contacts']       = 1;

            $output["overall_progress"]         = $this->getProgressText($stage_progress, $row["status"]);

            // TODO : if not handle by client then enabled this line.
            // if($output["overall_progress"] ==  "Paused"){
            //     $output["scheduled_on"] = "";
            //     $output["timezone_text"] = "";                
            // }

            $output["success_ratio"]            = $this->getPerformanceText($output["email_success_count"], $output["total_recipients"]);
            $output["open_ratio"]               = $this->getPerformanceText($output["total_email_opened"], $output["total_recipients"]);
            $output["bounce_ratio"]             = $this->getPerformanceText($output["total_bounce_count"], $output["total_recipients"]);
            $output["manual_track_reply"]       = 0;

            if ($output["track_reply"] == 1) {
                $output["reply_ratio"]  = $this->getPerformanceText($output["reply_count"], $output["total_recipients"]);
                $reply_tracking_date    = $row["track_reply_max_date"];
                
                if(strtotime("now") <= $reply_tracking_date) {
                    $output["manual_track_reply"]   = 1;
                }
            } else {
                $output["reply_ratio"]  = "";
            }

            if ($output["track_click"] == 1) {
                $output["click_ratio"]  = $this->getPerformanceText($output["total_link_clicked_count"], $output["total_recipients"]);
            } else {
                $output["click_ratio"]  = "";
            }  
        } else {
            $output["no_records"] = "No records found for this stage.";
        }
        
        return $response->withJson($output, 200);
    }

    /**
     * View single stage information
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function viewStageContacts(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output                     = [];
        $total_records_to_render    = 0;
        $total_pages_to_render      = 0;
        $server_timezone            = \DEFAULT_TIMEZONE;
        
        // Get logged in user details
        $logged_in_user     = SHAppComponent::getUserId();
        $logged_in_account  = SHAppComponent::getAccountId();
        $is_owner           = SHAppComponent::isAccountOwner();
        $logged_in_source   = SHAppComponent::getRequestSource();

        // Get request parameter
        $route                  = $request->getAttribute('route');
        $request_campaign_id    = $route->getArgument('id'); // Request campaign'id
        $request_stage_id       = $route->getArgument('stage_id'); // Request campaign's Stage id

        $campaign_id            = StringComponent::decodeRowId($request_campaign_id);
        $stage_id               = StringComponent::decodeRowId($request_stage_id);

        // Validate request
        if (empty($campaign_id) || empty($stage_id) ) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        // Get request parameters
        $params = $request->getQueryParams();
        
        // Set parameters
        $query_params = [
            "page"      => 1,
            "per_page"  => SHAppComponent::getValue("app_constant/DEFAULT_CAMP_LIST_PPAGE"),
            "order_by"  => "id",
            "order"     => "ASC"
        ];
        

        if (!empty($params["page"])) {
            if (is_numeric($params["page"])) {
                $query_params["page"] = (int) $params["page"];
            }
        }
        if (!empty($params["per_page"])) {
            if (is_numeric($params["per_page"])) {
                $query_params["per_page"] = (int) $params["per_page"];
            }
        }
        if (!empty($params["order_by"])) {
            $query_params["order_by"] = trim($params["order_by"]);
        }
        if (!empty($params["order"])) {
            $query_params["order"] = (trim($params["order"]) == "DESC") ? "DESC" : "ASC";
        }

        $model_campaign_stage       = new CampaignStages();
        $model_campaign_sequence    = new CampaignSequences();
        $model_campaign_logs        = new CampaignLogs();

        // Campaign stage model
        try {
            // Other values for condition
            $other_values = [
                "user_id"       => $logged_in_user,
                "account_id"    => $logged_in_account,
                "deleted"       => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];
            $valid = $model_campaign_stage->checkRowValidity($stage_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        try {
            // Fetch data
            $condition = [
                "fields" => [
                    "cm.id as campaign_id",
                    "cm.timezone",
                    "cm.other_data",
                    "cm.track_reply",
                    "cm.track_click",
                    "cm.is_bulk_campaign",
                    "cst.id as campaign_stage_id",
                    "cst.content",
                    "cst.stage",
                    "cst.stage_defination",
                    "cst.progress",
                    "(SELECT COUNT(csqt.id) FROM campaign_sequences csqt WHERE cst.id = csqt.campaign_stage_id ) as total_contact_records" 
                ],
                "where" => [
                    ["where" => ["cst.id", "=", $stage_id]]
                ],
                "join" => [
                    "campaign_master"
                ]
            ];
            $row = $model_campaign_stage->fetch($condition);
          
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        
        // Get Sequence Data             
        if (!empty($row['campaign_stage_id'])) {
         
            if($row["is_bulk_campaign"]){
                $query_params["per_page"] =  SHAppComponent::getValue("app_constant/DEFAULT_BCAMP_LIST_PPAGE");
            }
            // if sequence is created for the stage
            if ( !empty($row["total_contact_records"]) ) {
                $total_records_to_render    = $row["total_contact_records"];
                $total_pages_to_render      = ceil($row["total_contact_records"] / $query_params["per_page"]);
                $offset                     = ($query_params["page"] - 1) * $query_params["per_page"];
                $stage_defination           = json_decode($row['stage_defination'], true);

               

                $order_by = "";
                switch ($query_params["order_by"]) {
                    case "email":
                        $order_by = "email";
                        break;
        
                    case "status":
                        $order_by = "status";
                        break;
        
                    case "open":
                        $order_by = "open_count";
                        break;
        
                    case "reply":
                        $order_by = "replied";
                        break;
        
                    case "click":
                        $order_by = "click_count";
                        break;
        
                    case "bounce":
                        $order_by = "is_bounce";
                        break;
        
                    default:
                        $order_by = "id";
                        break;
                }
                // Campaign seqeunce model
                try {
                    $condition = [
                        "fields" => [
                            "csq.id",
                            "ac.email",
                            "ac.first_name",
                            "ac.last_name",
                            // "csq.csv_payload",
                            "csq.progress",
                            "csq.is_bounce",
                            "csq.scheduled_at",
                            "csq.sent_at",
                            "csq.locked",
                            "csq.locked_date",
                            "csq.open_count",
                            "csq.last_opened",
                            "csq.replied",
                            "csq.last_replied",
                            "csq.click_count",
                            "csq.last_clicked",
                            "csq.status" 
                        ],
                        "where"     => [
                            ["where" => ["csq.campaign_id", "=", $campaign_id]],
                            ["where" => ["csq.campaign_stage_id", "=", $stage_id]],
                        ],
                        "join"      => [
                            "account_contacts"
                        ],
                        "order_by"  => [
                            "csq.status ASC ",
                            "csq.scheduled_at ASC ",
                            $order_by . " " . $query_params["order"]
                        ],                        
                        "limit"     => $query_params["per_page"],
                        "offset"    => $offset
                    ];
                    $row_seq = $model_campaign_sequence->fetchAll($condition);
                   
                } catch(\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }
                    
                // Get Campaing's stage vise repiled,click,open and bounce count
                if (!empty($row_seq)) {
                  
                    // Campaign logs model
                    for ($i = 0; $i < count($row_seq); $i++) {

                        $scheduled_at    = $row_seq[$i]["scheduled_at"];
                        $sent_at         = $row_seq[$i]["sent_at"];
                        // $csv_payload     = json_decode($row_seq[$i]["csv_payload"], true);
                                                    
                        // $is_scheduled               = 0;
                        $is_sequence_status         = 0;
                        $status                     = "";                        
                        $allow_delete               = 1;
                        $is_removed                 = 0;
                        try {
                            $condition = [
                                "fields" => [
                                    "log",
                                    "log_type" 
                                ],
                                "where" => [
                                    ["where" => ["campaign_sequence_id", "=", $row_seq[$i]["id"]]]
                                ],
                                "order_by" => [
                                    "id DESC"
                                ],
                                "limit" => 1
                            ];
                            $row_log = $model_campaign_logs->fetch($condition);
                        } catch(\Exception $e) {
                            // Fetch error code & message and return the response
                            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                        }

                        // if ( !empty($row_log) ) {
                        //     if ( $row_log["log_type"] == SHAppComponent::getValue("app_constant/LOG_TYPE_INFO") && $row_seq[$i]["progress"] == SHAppComponent::getValue("app_constant/SEQ_PROGRESS_SCHEDULED") ) {
                        //         $is_scheduled = 1;
                        //     }
                        // }

                        switch($row_seq[$i]["progress"]) {
                            case SHAppComponent::getValue("app_constant/SEQ_PROGRESS_SCHEDULED"):
                                $get_datetime_timezone = DateTimeComponent::convertDateTimeCampaign($scheduled_at, true, $server_timezone, $row["timezone"], \CAMPAIGN_DATE_FORMAT);
                          
                                $status = "Scheduled @ ".$get_datetime_timezone;
                                break;

                            case SHAppComponent::getValue("app_constant/SEQ_PROGRESS_SENT"):
                                $get_datetime_timezone = DateTimeComponent::convertDateTimeCampaign($sent_at, true, $server_timezone, $row["timezone"], \CAMPAIGN_DATE_FORMAT);
                                
                                $status                 = "Sent @ ".$get_datetime_timezone;
                                $allow_delete           = 0;
                                $is_sequence_status     = 1;
                                break;

                            case SHAppComponent::getValue("app_constant/SEQ_PROGRESS_FAILED"):
                                if($row_seq[$i]["is_bounce"] == 1) {
                                    $get_datetime_timezone = DateTimeComponent::convertDateTimeCampaign($row_seq[$i]["last_replied"], true, $server_timezone, $row["timezone"], \CAMPAIGN_DATE_FORMAT);
                                    
                                    $status                 = "Bounced @ ".$get_datetime_timezone;
                                    $is_sequence_status     = 2;

                                } else {
                                  
                                    $get_datetime_timezone = DateTimeComponent::convertDateTimeCampaign($sent_at, true, $server_timezone, $row["timezone"], \CAMPAIGN_DATE_FORMAT);
                                    
                                    $status                 = "Failed @ ".$get_datetime_timezone;
                                    $is_sequence_status     = 2;
                                }
                                
                                $allow_delete   = 0;
                                break;

                            default:
                                break;
                        }
                      
                        if($row_seq[$i]["status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE")) {
                            $allow_delete   = 0;
                            $is_removed     = 1;
                            $status         = "Removed from Queue";
                            $is_sequence_status         = 3;
                            if ($row_seq[$i]["locked"] == 1 && empty($row_seq[$i]["locked_date"])) {
                                $status = "Duplicate, Auto Removed";
                            }
                        }
                      
                        $last_opened = "";
                        $last_replied = "";
                     
                        if($row_seq[$i]["is_bounce"]){
                            $row_seq[$i]["open_count"] = 0;
                            $row_seq[$i]["click_count"] = 0;
                            $row_seq[$i]["replied"] = 0;
                        }else{
                            if(!empty($row_seq[$i]["last_opened"]))
                            {   
                                $last_opened = "Last Opened at ".DateTimeComponent::convertDateTimeCampaign($row_seq[$i]["last_opened"], true, $server_timezone, $row["timezone"], \CAMPAIGN_DATE_FORMAT);
                            }
                            if(!empty($row_seq[$i]["last_replied"]))
                            {
                                $last_replied = "Replied at ".DateTimeComponent::convertDateTimeCampaign($row_seq[$i]["last_replied"], true, $server_timezone, $row["timezone"], \CAMPAIGN_DATE_FORMAT);
                            }
                        }
                      
                        $output["rows"][] = [
                            "id"                    => StringComponent::encodeRowId($row_seq[$i]["id"]),
                            "email"                 => $row_seq[$i]["email"],
                            "status"                => $status,
                            "open"                  => $row_seq[$i]["open_count"],
                            "last_opened"           => $last_opened,
                            "reply"                 => $row_seq[$i]["replied"],
                            "last_replied"          => $last_replied,
                            "bounce"                => $row_seq[$i]["is_bounce"],
                            "click"                 => $row_seq[$i]["click_count"],                        
                            "allow_delete"          => $allow_delete,
                            "is_removed"            => $is_removed,
                            "is_sequence_status"    => $is_sequence_status,
                            "read_from_csv"         => 0
                        ];
                        
                    }
                } else {
                    $output["no_records"] = "No records found for this stage.";
                }

            } else if ( $row["progress"] == SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH") ) {
                $output["no_records"] = "No records found for this stage.";
            } else if ( $row["stage"] == 1 ) {
                // if sequence is not created for stage and it is first stage, then read from csv file
                $record_other_data  = json_decode($row["other_data"], true);
                $file_path = __DIR__."/../../upload/". $record_other_data["contacts_file"];
                $stage_defination   = json_decode($row['stage_defination'], true);

                // check if any contacts to be ignored
                if(empty($stage_defination['ignore'])) {
                    $stage_defination['ignore'] = [];
                }
                $contacts_data_array = [];
                $columns_array       = [];
                if (!empty($file_path)) {
                    $contacts_data              = @file_get_contents($file_path);                
                    $contacts_data_array        = json_decode($contacts_data, true);
                    $columns_array              = $contacts_data_array[0];  
                }
                $total_contacts             = count($contacts_data_array);
                // If total contact is not 0 then it will reduce contact by 1 else set it 0.
                $total_records_to_render    = $total_contacts ? --$total_contacts : 0 ;                                           
                $total_columns              = count($columns_array);
                
                $total_pages_to_render  = ceil($total_contacts / $query_params["per_page"]);
                $offset                 = ($query_params["page"] - 1) * $query_params["per_page"];
                $loop_limit             = ($query_params["page"] < $total_pages_to_render) ? $query_params["per_page"] : (int) (($total_contacts % $query_params["per_page"]));
                $loop_limit             = ($loop_limit < 0) ? 0 : $loop_limit;
                for($r = $offset+1; $r <= ($loop_limit + $offset); $r++) {
                    $payload = [];
                    $is_sequence_status     = 0;
                    for($j = 0; $j < $total_columns; $j++) {
                        if(isset($columns_array[$j]) && isset($contacts_data_array[$r][$j])) {
                            $payload["{{".$columns_array[$j]."}}"] = $contacts_data_array[$r][$j];
                        }
                    }

                    $allow_delete   = 1;
                    $is_removed     = 0;
                    $status         = "Scheduled";
                    if(in_array($r, $stage_defination['ignore'])) {
                        $is_removed         = 1;
                        $allow_delete       = 0;
                        $is_sequence_status = 3;
                        $status             = "Removed from Queue";
                    }
                   
                    $output["rows"][] = [
                        "id"                    => StringComponent::encodeRowId($r),
                        "email"                 => $payload["{{".self::CSV_EMAIL_FIELD."}}"],
                        "status"                => $status,
                        "open"                  => 0,
                        "reply"                 => 0,
                        "bounce"                => 0,
                        "click"                 => 0,
                        "allow_delete"          => $allow_delete,
                        "is_removed"            => $is_removed,
                        "is_sequence_status"    => $is_sequence_status,
                        "read_from_csv"         => 1
                    ];
                }                
            } else {
                // if sequence is not created for stage and it is higher from first stage, then read from previous stage
                try {
                    $condition = [
                        "fields" => [
                            "id" 
                        ],
                        "where" => [
                            ["where" => ["campaign_id", "=", $campaign_id ]],
                            ["where" => ["stage", "=", $row["stage"] - 1 ]]
                        ]
                    ];
                    $row_previous_stage = $model_campaign_stage->fetch($condition);
                } catch(\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

                if ( !empty($row_previous_stage["id"]) ) {
                    $previous_stage_id  = $row_previous_stage["id"];
                    $stage_defination   = json_decode($row['stage_defination'], true);
                  
                    // check if any contacts to be ignored
                    if(empty($stage_defination['ignore'])) {
                        $stage_defination['ignore'] = [];
                    }

                    // Get sequence of previous stage
                    $fetch_contact_condition = [
                        ["where" => ["csq.status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE") ]],
                        ["where" => ["csq.progress", "=", SHAppComponent::getValue("app_constant/SEQ_PROGRESS_SENT") ]],
                        ["where" => ["csq.campaign_id", "=", $campaign_id ]],
                        ["where" => ["csq.campaign_stage_id", "=", $previous_stage_id ]]
                    ];
                    if(!empty($stage_defination["condition"])) {
                        switch($stage_defination["condition"]) {
                            case SHAppComponent::getValue("app_constant/CONDITION_NOT_OPEN"):
                                $fetch_contact_condition[] = ["where" => ["csq.open_count", "=", 0 ]];
                                break;

                            case SHAppComponent::getValue("app_constant/CONDITION_NOT_REPLY"):
                                $fetch_contact_condition[] = ["where" => ["csq.replied", "=", 0 ]];
                                break;

                            case SHAppComponent::getValue("app_constant/CONDITION_SENT"):
                                break;
                                
                            default:
                                break;
                        }
                    }
                   
                    // Total contacts
                    try {
                        $condition = [
                            "fields"    => [
                                "COUNT(id) AS cnt" 
                            ],
                            "where"     => $fetch_contact_condition
                        ];
                        $rows_sequence_contacts_total   = $model_campaign_sequence->fetch($condition);
                    } catch(\Exception $e) {
                        // Fetch error code & message and return the response
                        return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                    }
                        
                    $total_records_to_render    = (empty($rows_sequence_contacts_total["cnt"])) ? 0 : (int) $rows_sequence_contacts_total["cnt"];

                    $total_pages_to_render      = ceil($total_records_to_render / $query_params["per_page"]);
                    $offset                     = ($query_params["page"] - 1) * $query_params["per_page"];

                    // Get sequence of previous stage
                    try {
                        $condition = [
                            "fields"    => [
                                "csq.id",
                                "ac.email",
                                "ac.first_name",
                                "ac.last_name"
                            ],
                            "where"     => $fetch_contact_condition,
                            "join"      => [
                                "account_contacts"
                            ],
                            "order_by"  => [
                                $query_params["order_by"] . " " . $query_params["order"]
                            ],
                            "limit"     => $query_params["per_page"],
                            "offset"    => $offset
                        ];
                        $rows_sequence_contacts = $model_campaign_sequence->fetchAll($condition);                      
                    } catch(\Exception $e) {
                        // Fetch error code & message and return the response
                        return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                    }

                    if ( !empty($rows_sequence_contacts) ) {                      
                        for($r = 0; $r < count($rows_sequence_contacts); $r++) {
                            $is_sequence_status = 0;
                            $is_removed     = 0;
                            $allow_delete   = 1;
                            $status         = "Scheduled";
                            if(in_array($rows_sequence_contacts[$r]['id'], $stage_defination['ignore'])) {
                                $is_removed         = 1;
                                $allow_delete       = 0;
                                $is_sequence_status = 3;
                                $status             = "Removed from Queue";
                            }
                            
                            $output["rows"][] = [
                                "id"                    => StringComponent::encodeRowId($rows_sequence_contacts[$r]['id']),
                                "email"                 => $rows_sequence_contacts[$r]['email'],
                                "status"                => $status,
                                "open"                  => 0,
                                "last_opened"           => 0,
                                "reply"                 => 0,
                                "last_replied"          => 0,
                                "bounce"                => "",
                                "click"                 => "",
                                "allow_delete"          => $allow_delete,   
                                "is_removed"            => $is_removed,                            
                                "is_sequence_status"    => $is_sequence_status,
                                "read_from_csv"         => 0
                            ];
                        }
                    } else {
                        $output["no_records"] = "No records found for this stage.";
                    }
                } else {
                    $output["no_records"] = "No records found for this stage.";
                }
            }
        } else {
            $output["no_records"] = "No records found for this stage.";
        } 

        // Process data and prepare for output
              
        $output["track_reply"]      = $row["track_reply"];
        $output["track_click"]      = $row["track_click"];
        $output["total_recipients"] = $total_records_to_render;
        $output["total_pages"]      = $total_pages_to_render;
        $output["current_page"]     = $query_params["page"];
        $output["per_page"]         = $query_params["per_page"];
        
        if (!empty($row["last_error"])) {
            $output["last_error"] = $row["last_error"];
        }

        return $response->withJson($output, 200);
    }

    /**
    * Display - wherver performance text is not applicable (like reply tracking false)
    */
    private function getEmptyPerformanceText() {
        return "<span class=\"grey\"><i class=\"fa fa-minus\"></i></span>";
    }

    /**
    * Get performance with class details
    * $input ratio compared to $compare
    *
    * return string with performance and class
    */
    private function getPerformanceText($input=0, $compare=0, $negative=false) {
        $return_text = "0";

        if(!empty($compare)) {
            $ratio = round(($input * 100) / $compare);
            $ratio = ($ratio > 100) ? 100 : $ratio;
            $return_text = $ratio;
        }

        return $return_text;
    }

    /**
    * Get progress text with icons and class
    * $progress value of progress
    * return string with progress text, icons and class
    */
    private function getProgressText($progress=null, $status=null) {
        $return_text = "<span class=\"grey\"><i class=\"fa fa-save\"></i> Draft</span>";
        $return_status = "Draft";

        if($status == SHAppComponent::getValue("app_constant/STATUS_DRAFT") || $status == null) {
            //return $return_text;
            return $return_status;
        }

        switch($progress) {
            case SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED"):
                $return_text = "<span class=\"orange\"><i class=\"fa fa-clock-o\"></i> Scheduled</span>";
                $return_status = "Scheduled";
                break;

            case SHAppComponent::getValue("app_constant/CAMP_PROGRESS_QUEUED"):
                $return_text = "<span class=\"orange\"><i class=\"fa fa-users\"></i> Queued</span>";
                $return_status = "Queued";
                break;

            case SHAppComponent::getValue("app_constant/CAMP_PROGRESS_IN_PROGRESS"):
                $return_text = "<span class=\"green\"><i class=\"fa fa-circle-o-notch fa-spin\"></i> In Progress</span>";
                $return_status = "In Progress";
                break;

            case SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED"):
                $return_text = "<span class=\"orange\"><i class=\"fa fa-pause\"></i> Paused</span>";
                $return_status = "Paused";
                break;

            case SHAppComponent::getValue("app_constant/CAMP_PROGRESS_WAITING"):
                $return_text = "<span class=\"red\"><i class=\"fa fa-warning\"></i> Waiting</span>";
                $return_status = "Waiting";
                break;

            case SHAppComponent::getValue("app_constant/CAMP_PROGRESS_HALT"):
                $return_text = "<span class=\"red\"><i class=\"fa fa-warning\"></i> Halted</span>";
                $return_status = "Halted";
                break;

            case SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH"):
                $return_text = "<span class=\"green\"><i class=\"fa fa-check\"></i> Finished</span>";
                $return_status = "Finished";
                break;

            default:
                $return_text = "<span class=\"grey\"><i class=\"fa fa-question\"></i> Unknown</span>";
                $return_status = "Unknown";
                break;
        }

        //return $return_text;
        return $return_status;
    }

    /**
     * Remove sequence from queue
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function sequenceDelete(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
        $return_val = false;

        // Get logged in user details
        $logged_in_user     = SHAppComponent::getUserId();
        $logged_in_account  = SHAppComponent::getAccountId();
        $is_owner           = SHAppComponent::isAccountOwner();
        $logged_in_source   = SHAppComponent::getRequestSource();

        // Get request parameter
        $route                  = $request->getAttribute('route');
        $request_stage_id       = $route->getArgument('id'); // Request campaign' stage id
        $request_sequence_id    = $route->getArgument('seq_id'); // Request campaign's Stage sequence id

        $stage_id       = StringComponent::decodeRowId($request_stage_id);
        $sequence_id    = StringComponent::decodeRowId($request_sequence_id);

        // Validate request
        if (empty($stage_id) || empty($sequence_id) ) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $model_campaign_master      = new CampaignMaster();
        $model_campaign_stage       = new CampaignStages();
        $model_campaign_sequences   = new CampaignSequences();

        // Campaign stage model
        try {
            // Other values for condition
            $other_values = [
                "user_id"       => $logged_in_user,
                "account_id"    => $logged_in_account,
                "deleted"       => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];
            $valid = $model_campaign_stage->checkRowValidity($stage_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get campaign record
        try {
            $condition = [
                "fields" => [
                    "cst.id",
                    "cst.stage_defination",
                    "cst.progress"
                ],
                "where" => [
                    ["where" => ["cst.id", "=", $stage_id ]]
                ]
            ];
            $rows_stage_rec = $model_campaign_stage->fetch($condition);
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if ( !empty($rows_stage_rec) ) {
            if ( $rows_stage_rec["progress"] == SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED") ) {
                $stage_defination = json_decode($rows_stage_rec['stage_defination'], true);

                if(empty($stage_defination['ignore'])) {
                    $stage_defination['ignore'] = [];
                }
                if(!in_array($sequence_id, $stage_defination["ignore"])) {
                    $stage_defination['ignore'][] = $sequence_id;
                } else {                   
                    return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST", "Email receipient is already deleted.",false);
                }

                $stage_defination = json_encode($stage_defination);

                try {
                    $save_data = [
                        "id"                => $rows_stage_rec["id"],
                        "stage_defination"  => $stage_defination,
                        "modified"          => DateTimeComponent::getDateTime()
                    ];
                    $model_campaign_stage->save($save_data);
                } catch(\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

                $return_val = true;
            } else {
                try {
                    $condition = [
                        "fields" => [
                            "cst.id",
                            "cst.total_deleted",
                            "csq.status"
                        ],
                        "where" => [
                            ["where" => ["csq.id", "=", $sequence_id ]],
                            ["where" => ["csq.progress", "=", SHAppComponent::getValue("app_constant/SEQ_PROGRESS_SCHEDULED") ]]
                        ],
                        "join" => [
                            "campaign_stages"
                        ]
                    ]; 
                    $rows = $model_campaign_sequences->fetch($condition);                                        
                } catch(\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

                if ( !empty($rows) ) {  

                    try {                       
                        if($rows["status"] == SHAppComponent::getValue("app_constant/STATUS_DELETE")){
                            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST", "Email receipient is already deleted.",false);
                        }

                        $save_data = [
                            "id"        => $sequence_id,
                            "status"    => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                            "modified"  => DateTimeComponent::getDateTime()
                        ];
                        $res = $model_campaign_sequences->save($save_data);
                        
                        $save_data = [
                            "id"            => $rows_stage_rec["id"],
                            "total_deleted" => $rows["total_deleted"] + 1,
                            "modified"      => DateTimeComponent::getDateTime()
                        ];
                        $model_campaign_stage->save($save_data);
    
                        $return_val = true;
                    } catch(\Exception $e) {
                        // Fetch error code & message and return the response
                        return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                    }
                  
                } else {
                    return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
                }
            }
        } else {
            return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
        }

        $output[] = $return_val;            

        return $response->withJson($output, 200);
    }

    /**
     * View mail sent to sequence
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function sequenceMail(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user     = SHAppComponent::getUserId();
        $logged_in_account  = SHAppComponent::getAccountId();
        $is_owner           = SHAppComponent::isAccountOwner();
        $logged_in_source   = SHAppComponent::getRequestSource();
 
        // Get request parameter
        $route                  = $request->getAttribute('route');
        $request_stage_id       = $route->getArgument('id'); // Request campaign'id
        $request_sequence_id    = $route->getArgument('seq_id'); // Request campaign's Stage id

        $stage_id       = StringComponent::decodeRowId($request_stage_id);
        $sequence_id    = StringComponent::decodeRowId($request_sequence_id);

        // Validate request
        if (empty($stage_id) || empty($sequence_id) ) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $model_campaign_master      = new CampaignMaster();
        $model_campaign_stage       = new CampaignStages();
        $model_campaign_sequences   = new CampaignSequences();

        // Campaign stage model
        try {
            // Other values for condition
            $other_values = [
                "user_id"       => $logged_in_user,
                "account_id"    => $logged_in_account,
                "deleted"       => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];
            $valid = $model_campaign_stage->checkRowValidity($stage_id, $other_values);
            
            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
       
        // Get campaign stage record
        try {

            $condition = [
                "fields" => [
                    "cst.id",
                    "cst.campaign_id",
                    "cst.subject",                    
                    "cst.content",
                    "cst.stage_defination",
                    "cst.progress",
                    "cst.stage",                    
                    "cm.other_data",
                    "(SELECT COUNT(csqt.id) FROM campaign_sequences csqt WHERE cst.id = csqt.campaign_stage_id ) as total_contact_records"                     
                ],
                "where" => [
                    ["where" => ["cst.id", "=", $stage_id ]]
                ],
                "join" => [
                    "campaign_master"
                ]
            ];
           
            $rows_stage_rec = $model_campaign_stage->fetch($condition);
           
            //Check if record is not empty
            if(!empty($rows_stage_rec)){
                // Check total contact record found then queue is prepared.
                // or sequence is not created for stage and it is higher from first stage, 
                // then read from sequence id. It assign previous sequence id to it.
                if(!empty($rows_stage_rec["total_contact_records"]) || $rows_stage_rec["stage"]>1){
                   
                    try{
                        $condition = [
                            "fields" => [
                                "csq.csv_payload",                          
                            ],
                            "where"     => [                            
                                ["where" => ["csq.id", "=", $sequence_id]]
                            ]
                        ];
                        $row_seq = $model_campaign_sequences->fetch($condition);
                        
                    } catch(\Exception $e) {
                        // Fetch error code & message and return the response
                        return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                    }
                    if(!empty($row_seq)){

                        $payload = json_decode($row_seq["csv_payload"], true);
                      
                        if(!empty($rows_stage_rec)){                
                            $rows_stage_rec["subject"]        = strtr($rows_stage_rec["subject"], $payload);
                            $rows_stage_rec["content"]        = strtr(html_entity_decode($rows_stage_rec["content"]), $payload);               
                        }
                   
                        $output = [
                            "subject"   =>  $rows_stage_rec["subject"],
                            "content"   =>  $rows_stage_rec["content"] 
                        ];
                    }
                    else{
                        $output["no_records"] = "No content found for this receipient.";
                    }
                }else if ( $rows_stage_rec["progress"] == SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH") ) {
                    $output["no_records"] = "No records found for this sequence.";
                }// When queue is not prepared read sequence data from csv file. (This occured when stage is 1.) 
                else if($rows_stage_rec["stage"] == 1){
                    $record_other_data  = json_decode($rows_stage_rec["other_data"], true);
                    $file_path = __DIR__."/../../upload/". $record_other_data["contacts_file"];
                    $stage_defination   = json_decode($rows_stage_rec['stage_defination'], true);
                
                    $contacts_data_array = [];
                    $columns_array       = [];
                    if (!empty($file_path)) {
                        $contacts_data              = @file_get_contents($file_path);                
                        $contacts_data_array        = json_decode($contacts_data, true);
                        $columns_array              = $contacts_data_array[0];  
                    }
                    $total_contacts             = count($contacts_data_array);
                    // If total contact is not 0 then it will reduce contact by 1 else set it 0.
                    $total_records_to_render    = $total_contacts ? --$total_contacts : 0 ;                                           
                    $total_columns              = count($columns_array);
                    

                    
                    $payload = [];
                    for($j = 0; $j < $total_columns; $j++) {
                        if(isset($columns_array[$j]) && isset($contacts_data_array[$sequence_id][$j])) {
                            $payload["{{".$columns_array[$j]."}}"] = $contacts_data_array[$sequence_id][$j];
                        }
                    }
                   

                    if(!empty($rows_stage_rec)){                
                        $rows_stage_rec["subject"]        = strtr($rows_stage_rec["subject"], $payload);
                        $rows_stage_rec["content"]        = strtr(html_entity_decode($rows_stage_rec["content"]), $payload);               
                    }
                    
                    $output = [
                        "subject"   =>  $rows_stage_rec["subject"],
                        "content"   =>  $rows_stage_rec["content"] 
                    ];  
                }else{
                    $output["no_records"] = "No records found for this sequence.";                   
                }
            }    
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

     /**
     * Check reply for particular campaignStage
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function replyCheck(ServerRequestInterface $request, ResponseInterface $response, $args) {
        
        // Default values
        
        $output = array(
            "success"=> 0, 
            "message"=>"Invalid request."
        );
       
        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();
        
        $log_file_name = __DIR__ . "/../../logs/af_reply_check_queue.log";
        // Get request paramaters
        $route = $request->getAttribute('route');
        $campaign_id = $route->getArgument('id');
        $campaign_stage_id = $route->getArgument('stage_id');

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Decode request id
        $campaign_id = StringComponent::decodeRowId($campaign_id);
        $campaign_stage_id = StringComponent::decodeRowId($campaign_stage_id);
        
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

        $number_of_records          = 0;
        // Get records for which to check reply
        $row_reply_check_records    = [];
        try {
            $query_params = [
                "status_active"     => SHAppComponent::getValue("app_constant/STATUS_ACTIVE"),
                "flag_yes"          => SHAppComponent::getValue("app_constant/FLAG_YES"),
                "current_date"      => $current_date,
                "seq_progress_sent" => SHAppComponent::getValue("app_constant/SEQ_PROGRESS_SENT")                
            ];

            $row_reply_check_records    = $model_campaign_sequences->getRecordsToCheckReply($campaign_stage_id, $query_params);
            $number_of_records          = count($row_reply_check_records);
        
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        for ($c=0; $c<$number_of_records; $c++) {

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
            // Ignore user id which are in ignore list
            if(!in_array($user_id, $ignore_users)){
                $res_sending_method         = $model_account_sending_method->getSendingMethodById($account_sending_method_id, $other_values);
               
                if(!$res_sending_method){
                    $output["message"] = "Could not find sending account method.";
                    return $output;
                }

                $email_sending_method_id    = $res_sending_method["email_sending_method_id"];
                $payload                    = json_decode($res_sending_method["payload"]);
                $refresh_token              = isset($payload->refresh_token)?$payload->refresh_token:"";
                $thread_id                  = "";
                            
                
                if ($email_sending_method_id == SHAppComponent::getValue("email_sending_method/GMAIL")) {
                    try {
                        
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
                            
                           if ($replied_array["replied"]) {
                                $update_sequence_record["replied"]          = 1;
                                $update_sequence_record["last_replied"]     = DateTimeComponent::convertDateTime($replied_array["replied_at"],false);
                                                                    
                                // If email count is 0, then update to at least 1
                                if($open_count == 0) {
                                    $update_sequence_record["open_count"]   = 1;
                                    $update_sequence_record["last_opened"]  = DateTimeComponent::convertDateTime($replied_array["replied_at"],false);
                                }
                            }
                        }
                    } catch(\Exception $e) {
                        // TODO : Set error proper
                        $output["message"] = "Failed to check reply.";
                        return $output;
                    }
                }
            }

            // update sequence record
            try {
                $condition = [
                    "where" => [
                        ["where" => ["id", "=", $sequence_id]]
                    ]
                ];
                $model_campaign_sequences->update($update_sequence_record, $condition);
               
                $output["success"] = 1; 
                $output["message"] = "Successfully Checked";

            } catch(Exception $e) {
                //TODO : Ignore or throw error
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
            
        }
        return $response->withJson($output, 200);    
    }

    /**
     * Export stage performance details
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function exportData(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();

        // Get request parameter
        $route                  = $request->getAttribute('route');
        $request_campaign_id    = $route->getArgument('id'); // Request campaign'id
        $request_stage_id       = $route->getArgument('stage_id'); // Request campaign's Stage id

        $campaign_id    = StringComponent::decodeRowId($request_campaign_id);
        $stage_id       = StringComponent::decodeRowId($request_stage_id);
        $track_reply    = 0;
        $track_click    = 0;
        $csv_payload    = null;
        $timezone       = \DEFAULT_TIMEZONE;
        $server_timezone= \DEFAULT_TIMEZONE;

        // Validate request
        if (empty($campaign_id) || empty($stage_id) ) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $model_campaign_master      = new CampaignMaster();
        $model_campaign_sequences   = new CampaignSequences();

        // Campaign stage model
        try {
            // Other values for condition
            $other_values = [
                "user_id"       => $logged_in_user,
                "account_id"    => $logged_in_account,
                "deleted"       => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];
            $valid = $model_campaign_master->checkRowValidity($campaign_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        
        try {
            // Get data from campaign master table
            $condition = [
                "fields" => [
                    "cm.id",
                    "cm.title",
                    "cm.timezone",
                    "cm.track_reply",
                    "cm.track_click",
                    "cst.stage",  
                ],
                "where" => [
                    ["where" => ["cm.id","=",$campaign_id]],
                    ["where" => ["cst.id","=",$stage_id]],
                    ["where" => ["cm.status","<>",SHAppComponent::getValue("app_constant/STATUS_DELETE")]],
                    ["where" => ["cst.progress","=",SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH")]],
                ],
                "join" => [
                    "campaign_stages"
                ]
            ];    

            $campaign_master_data = $model_campaign_master->fetchAll($condition);

            if (!empty($campaign_master_data)) {
                $track_reply = $campaign_master_data[0]["track_reply"];
                $track_click = $campaign_master_data[0]["track_click"];
                $timezone    = $campaign_master_data[0]["timezone"];
            }

            // Get data from campaign sequence table
            $condition = [
                "fields" => [
                    "csv_payload",
                    "is_bounce",
                    "sent_at",
                    "progress",
                    "open_count",
                    "last_opened",
                    "replied",
                    "last_replied",
                    "click_count",
                    "last_clicked" ,
                    "status" 
                ],
                "where" => [
                    ["where" => ["campaign_id","=",$campaign_id]],
                    ["where" => ["campaign_stage_id","=",$stage_id]],
                    ["where" => ["status","<>",SHAppComponent::getValue("app_constant/STATUS_DELETE")]],
                ],
            ];

            $sequence_data = $model_campaign_sequences->fetchAll($condition);

            if (!empty($sequence_data)) {
                // Prepare CSV data
                $data_array = [];

                for ($i = 0; $i < count($sequence_data); $i++) {
                    $csv_payload = json_decode($sequence_data[$i]["csv_payload"], true);
                    $temp_arr = [];

                    if ($i == 0) {
                        foreach ($csv_payload as $key => $val) {
                            $temp_arr[] = preg_replace( '/^\W*(.*?)\W*$/','$1',($key));
                        }
                        $temp_arr[] = "Sent Date";
                        $temp_arr[] = "Falied";
                        $temp_arr[] = "Bounce";
                        $temp_arr[] = "Open Count";
                        $temp_arr[] = "Last Open Date";
                        if ($track_reply == 1) {
                            $temp_arr[] = "Replied";    
                            $temp_arr[] = "Replied Date";   
                        }
                        if ($track_click == 1) {
                            $temp_arr[] = "Click Count";    
                            $temp_arr[] = "Last Click Date";   
                        }
                        $temp_arr[] = "Remove From Stage";
                        $data_array[] = $temp_arr;
                        $temp_arr = [];
                    }

                    foreach ($csv_payload as $key => $val) {
                        $temp_arr[] = $val;
                    }
                    $temp_arr[] = DateTimeComponent::convertDateTimeCampaign($sequence_data[$i]["sent_at"], true, $server_timezone, $timezone, \CAMPAIGN_DATE_FORMAT)."(".$timezone.")";

                    if ($sequence_data[$i]["progress"] == 1) {
                        $temp_arr[] = 0;
                    } else if ($sequence_data[$i]["progress"] == 2) {
                        $temp_arr[] = 1; 
                    } 

                    $temp_arr[] = $sequence_data[$i]["is_bounce"];

                    $temp_arr[] = $sequence_data[$i]["open_count"];

                    if ($sequence_data[$i]["last_opened"] == 0) {
                        $temp_arr[] = "-";
                    } else {
                        $temp_arr[] = DateTimeComponent::convertDateTimeCampaign($sequence_data[$i]["last_opened"], true, $server_timezone, $timezone, \CAMPAIGN_DATE_FORMAT)."(".$timezone.")";
                    }

                    if ($track_reply == 1) {
                       $temp_arr[] = $sequence_data[$i]["replied"];

                       if ($sequence_data[$i]["last_replied"] == 0) {
                            $temp_arr[] = "-";
                       } else {
                           $temp_arr[] = DateTimeComponent::convertDateTimeCampaign($sequence_data[$i]["last_replied"], true, $server_timezone, $timezone, \CAMPAIGN_DATE_FORMAT)."(".$timezone.")";
                       }
                    }

                    if ($track_click == 1) {
                       $temp_arr[] = $sequence_data[$i]["click_count"];

                       if ($sequence_data[$i]["last_clicked"] == 0) {
                            $temp_arr[] = "-";
                       } else {
                            $temp_arr[] = DateTimeComponent::convertDateTimeCampaign($sequence_data[$i]["last_clicked"], true, $server_timezone, $timezone, \CAMPAIGN_DATE_FORMAT)."(".$timezone.")";
                       }
                    }

                    if ($sequence_data[$i]["status"] == 1) {
                        $temp_arr[] = 0;
                    } else {
                        $temp_arr[] = 1;
                    }

                    $data_array[] = $temp_arr;
                }

                //This code is used to create csv file name using title and stage
                if(!empty($campaign_master_data[0]['title']) && !empty($campaign_master_data[0]['stage'])){
                    $final_title =  preg_replace('/[^A-Za-z0-9\-]/', '', $campaign_master_data[0]['title']); 
                    $title = $final_title."_Stage".$campaign_master_data[0]['stage']."_export";
                }
                
                $output_csv = fopen("php://output",'w') or die("Can't open php://output");
                header("Content-Type:application/csv"); 
                header("Content-Disposition:attachment;filename=".$title.".csv"); 
                foreach($data_array as $fields_csv_value) {
                    fputcsv($output_csv, $fields_csv_value);
                }
                fclose($output_csv) or die("Can't close php://output");
            }
            
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        return $response->withJson($output, 200);
    }

    /**
     * Send a test email
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function sendTestMail(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();

        // Get request parameters
        $request_params = $request->getParsedBody();

        if (isset($request_params["sending_method"])) {
            $sending_method = StringComponent::decodeRowId(trim($request_params["sending_method"]));

            if (!empty($sending_method)) {
                $request_data["sending_method"] = $sending_method;
            } else {
                $request_data["sending_method"] = "";
            }
        }
        if (isset($request_params["subject"])) {
            $request_data["subject"] = $request_params["subject"];
        }
        if (isset($request_params["content"])) {
            $request_data["content"] = $request_params["content"];
        }
        if (isset($request_params["to_email"])) {
            $request_data["to_email"] = $request_params["to_email"];
        }
        if (isset($request_params["sample_csv_data"])) {
            $request_data["sample_csv_data"] = $request_params["sample_csv_data"];
        }

        $field_key = $request_data['sample_csv_data'];
    
        // Prepare mail data
        $key = array_map(function($val){
            return "{{" . $val . "}}";
        }, array_keys($field_key)); //add {{in field name}} by array map
        $field_val = array_map(function($val){
            return  $val;
        }, ($field_key));
      
            
        $subject =  $request_data["subject"];
        $content = $request_data["content"];
    

        $combined = array_combine($key, $field_val);
    
        if (!empty($combined)) {
            $content = html_entity_decode($content);
            
            $search = array_keys($combined);
            $replace = array_values($combined);
            $content = str_replace($search, $replace, $content);
            $subject = str_replace($search, $replace, $subject);
        }
  
        // Validate request
        $request_validations = [
            "sending_method" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "subject" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "content" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "to_email" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY],
                ["type" => Validator::FIELD_EMAIL]
            ]
        ];

        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        //send email
        $model_account_sending_method = new AccountSendingMethods();

        $other_values = [
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        try {
            $row = $model_account_sending_method->getSendingMethodById($request_data["sending_method"], $other_values);
            if(!empty($row)) {
                $email_sending_method_id = $row["email_sending_method_id"];
                $payload = json_decode($row["payload"]);

                $info["subject"] = $subject;
                $info["content"] = $content;
                $info["to"] = $request_data["to_email"];
                $info["cc"] = "";
                $info["bcc"] = "";
                $info["from_email"] = $row["from_email"];
                $info["from_name"] = $row["from_name"];
                $info["reply_to"] = "";

                // Send email with Gmail
                if ($email_sending_method_id == 1) {
                    $info["refresh_token"] = $payload->refresh_token;
                    $info["thread_id"] = "";

                    $result = GmailComponent::sendMailGmail($info, false);
                    if (isset($result["gmail_message_id"]) && $result["gmail_message_id"] != "") {
                        //mail sent successfully
                        $output["message"] = "Email sent successfully.";
                    } else {
                        //mail not sent
                        $output["message"] = "Email not sent.";
                    }
                }

                // Send email with user's SMTP
                if ($email_sending_method_id == 2) {
                    $info["smtp_details"]["host"] = $payload->host;
                    $info["smtp_details"]["username"] = $payload->username;
                    $info["smtp_details"]["password"] = $payload->password;
                    $info["smtp_details"]["port"] = $payload->port;
                    $info["smtp_details"]["encryption"] = $payload->encryption;

                    $result = SMTPComponent::mailSendUserSmtp($info);
                    if (isset($result["message_id"]) && $result["message_id"] != "") {
                        //mail sent successfully
                        $output["message"] = "Email sent successfully.";
                    } else {
                        //mail not sent
                        $output["message"] = "Email not sent.";
                    }
                }
            }
        } catch(\Exception $e) { }
        
        return $response->withJson($output, 200);
    }

    /**
     * Upload and process csv file
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function previewCsvData(ServerRequestInterface $request, ResponseInterface $response, $args) {
        ini_set("auto_detect_line_endings", true);
        $output = [];
    
        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();

        // Get request paramaters
        $route = $request->getAttribute('route');
      
        // Get request parameters
        $request_params = $request->getParsedBody();

        // Get request file
        $uploaded_files = $request->getUploadedFiles();

        // Get request file length if file not upload successfully
        $get_content_length = $request->getContentLength();

        $result = array("valid"=>true, "message"=>"", "rows"=>0, "srows"=>0, "erows"=>0, "frows"=>0, "skrows"=>0, "dprows"=>0);

        // convert request length to megabytes number
        $bytes_size = round(number_format($get_content_length / 1048576, 2));

        if (!empty($uploaded_files['fileObj'])) {
            // handle single input with single file upload
            $uploaded_file = $uploaded_files['fileObj'];
        
            // requested file type
            $file_type = $uploaded_file->getClientMediaType();

            // requested file error
            $file_error = $uploaded_file->getError();
            
            // requested file size
            $file_size = (int) $uploaded_file->getSize();

            $bytes = ceil(number_format($file_size / 1048576, 2));

            // Allowed file type
            $valid_type = array(
                'text/csv',
                'text/plain',
                'application/csv',
                'text/comma-separated-values',
                'application/excel',
                'application/vnd.ms-excel',
                'application/vnd.msexcel',
                'text/anytext',
                'application/octet-stream',
                'application/txt',
            );

            // Check if uploaded file is of valid type or not
            if (in_array($file_type, $valid_type) && $file_error == 0 && $file_type != "application/x-rar" && $bytes <= self::CSV_MAX_FILE_SIZE_IN_MB) {

                // requested file name
                $source_file = $uploaded_file->file;
             
                // path for upload file
                $destination_path = CAMPAIGN_CSV_FILE_PATH;
               
                if (!is_dir($destination_path)) {
                    mkdir($destination_path, 0777, true);
                }
                // To check if bulk email is true or false
                $request_bulk_email = 0;
                if (!empty($request_params["bulk_emails"]) && $request_params["bulk_emails"] == 1) {
                    $request_bulk_email = 1;
                }

                $MAX_ROWS = self::CSV_MAX_ROWS;
                if ($request_bulk_email == 1) {
                    $MAX_ROWS = self::CSV_MAX_ROWS_PREMIUM_CUSTOMER;
                }

                // Name of file to be saved
                $filename = self::CONTACT_FOLDER."_".self::CONTACT_TEMP_FILE."_".strtotime("now").".json";

                $rows = $srows = $erows = $frows = $skrows = $dprows = 0;;

                // Possible value for email column
                $possible_email_fields = array("Email", "Mail", "E-mail", "Emailaddress", "Email-address", "Email Address", "Email-id", "Emailid", "Email Id"); // all in word case
                $duplicate_emails = array();

                // Convert CSV file to Contacts JSON
                try {
                    $json_data = array();
                    $i = 0;
                    $c = 0;
                    $num_cols = 0;
                    $skip_cols = array();
                    $key_of_email_column = 0;
                    $last_row_empty = 0;

                    $delimiter = $this->detectDelimiter($source_file);

                    $file = fopen($source_file, "r");
                   
                    while (!feof($file)) {
                        $row = fgetcsv($file, 0, $delimiter);
                        $num_cols = ($i == 0) ? count($row) : $num_cols;
                        $c = $num_cols-1;

                        $new_row = array_fill(0, $num_cols, "");

                        while ($c >= 0) {
                            if (!in_array($c, $skip_cols)) {
                                if(isset($row[$c])) {
                                    $new_row[$c] = trim($row[$c]);
                                    $new_row[$c] = ($i == 0) ? preg_replace("/\xef\xbb\xbf/", "", $new_row[$c]) : $new_row[$c];
                                    $new_row[$c] = mb_convert_encoding($new_row[$c], "UTF-8");
                                    $new_row[$c] = ($i == 0) ? ucwords(strtolower($new_row[$c])) : $new_row[$c];
                                }
                            }
                            $c--;
                        }

                        if ($i == 0) {
                            if(trim(implode("", $new_row)) == "") {
                             // Fetch error code & message and return the response
                             return ErrorComponent::outputError($response,"api_messages/CSV_HEADER_ROW_MISSING");
                             break; 
                            }

                            if (!in_array(self::CSV_EMAIL_FIELD, $new_row)) {
                                $found_email = false;
                                foreach ($possible_email_fields as $p) {
                                    $find_key = array_search($p, $new_row);

                                    if ($find_key !== false) {
                                        $new_row[$find_key] = self::CSV_EMAIL_FIELD;
                                        $found_email = true;
                                        break;
                                    }
                                }

                                if (!$found_email) {
                                    // Fetch error code & message and return the response
                                    return ErrorComponent::outputError($response, "api_messages/CSV_EMAIL_COLUMN_MISSING");
                                    break;
                                }
                            }

                            if ($num_cols > self::CSV_MAX_COLS) {
                             // Fetch error code & message and return the response
                             return ErrorComponent::outputError($response, "api_messages/CSV_COULMN_LIMIT_EXCEED");
                             break;
                            }

                            $temp_new_row = array();
                            for ($j=0; $j<$num_cols; $j++) {
                                if(empty($new_row[$j]))
                                    $skip_cols[] = $j;
                                else
                                    $temp_new_row[] = $new_row[$j];
                            }
                            $new_row = $temp_new_row;
                            $num_cols = count($new_row);

                            $key_of_email_column = array_search(self::CSV_EMAIL_FIELD, $new_row);
                            if ($key_of_email_column === false) {
                                // Fetch error code & message and return the response
                                return ErrorComponent::outputError($response, "api_messages/CSV_EMAIL_COLUMN_MISSING");
                                break;
                            }
                        }

                        if (trim(implode("", $new_row)) != "") {
                            $last_row_empty = 0;
                            $i++;

                            if (($i - 1) > $MAX_ROWS) {
                                // If CSV has more than rows greater than max limit
                                $result["error_message"] = "Rows after " . $MAX_ROWS . " in CSV are ignored.";
                                $skrows++;

                            } else if($i > 1) {
                                // If row is data row (except header row), then check if email is valid
                                if(!empty($new_row[$key_of_email_column]) && filter_var($new_row[$key_of_email_column], FILTER_VALIDATE_EMAIL)) {
                                    if (!isset($duplicate_emails[$new_row[$key_of_email_column]])) {
                                        $json_data[] = $new_row;
                                        $srows++;

                                        $duplicate_emails[$new_row[$key_of_email_column]] = 1;

                                    } else {
                                        $dprows++;
                                    }

                                } else {
                                    $frows++;
                                }

                            } else {
                                $json_data[] = $new_row;
                                $srows++;
                            }

                        } else {
                            $last_row_empty++;
                            $erows++;
                        }

                        $rows++;
                    }
                    fclose($file);

                    if ($result["valid"]) {
                        if (count($json_data) <= 1) {
                            // Fetch error code & message and return the response
                            return ErrorComponent::outputError($response, "api_messages/CSV_NO_CONTACTS_FOUND");

                        } else if (!file_put_contents($destination_path.'/'.$filename, json_encode($json_data))) {
                            // else if (!file_put_contents($destination_path.'/'.$filename, json_encode($this->utf8ize($json_data)))) {
                            // Fetch error code & message and return the response
                            return ErrorComponent::outputError($response, "api_messages/CSV_FILE_UPLOAD_FAIL");

                        } else {
                            if (!empty($last_row_empty)) {
                                // Ignore continues empty rows at the end of CSV
                                $erows -= $last_row_empty;
                                $rows -= $last_row_empty;
                            }

                            $result["file_path"] = $destination_path.'/'.$filename;
                            $output["rows"] = $rows - 1;
                            $output["srows"] = $srows - 1;
                            $output["erows"] = $erows;
                            $output["frows"] = $frows;
                            $output["skrows"] = $skrows;
                            $output["dprows"] = $dprows;

                            $file_path = trim($result["file_path"]);
                        
                            $data = null;
                            try {
                                $data = @file_get_contents($file_path);

                            } catch(Exception $e) {
                                // To DO
                            }
                            if (!empty($data)) {
                                $output['csv_data'] = $data;
                                $output['file_path'] = CAMPAIGN_CSV_DB_FILE_PATH.'/'.$filename;
                            } 
                        }
                    }

                } catch(Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }

            } else if ($bytes > self::CSV_MAX_FILE_SIZE_IN_MB) { 

                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/CSV_FILE_SIZE_EXCEED", self::CSV_MAX_FILE_SIZE_IN_MB . " MB");
            } else {  

                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/CSV_INVALID_FILE_TYPE");
            }
        } else if ($bytes_size > self::CSV_MAX_FILE_SIZE_IN_MB) { 

            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/CSV_FILE_SIZE_EXCEED", self::CSV_MAX_FILE_SIZE_IN_MB . " MB");

        } else {  
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
        }    
        return $response->withJson($output, 200);
    }
    // public function utf8ize($d) {
    //     try{
    //         if (is_array($d)) {
    //             foreach ($d as $k => $v) {
    //                 $d[$k] = $this->utf8ize($v);
    //             }
    //         } else if (is_string ($d)) {
    //             return utf8_encode($d);
    //         }
    //         return $d;
    //     } catch(Exception $e) {
    //     }
    // }
    // public function utf8remove($d) {
    //     try{
    //         if (is_array($d)) {
    //             foreach ($d as $k => $v) {
    //                 $d[$k] = $this->utf8remove($v);
    //             }
    //         } else if (is_string ($d)) {
    //             return utf8_decode($d);
    //         }
    //         return $d;
    //     } catch(Exception $e) {
    //     }
    // }
    /**
     * Import CSV data from other campaign
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function importCsvData(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
    
        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();

        // Get request parameters
        $request_params = $request->getParsedBody();
        $route = $request->getAttribute('route');
        $request_campaign_id = $route->getArgument('id');
        $campaign_id = StringComponent::decodeRowId($request_campaign_id);

        // Validate request
        if (empty($campaign_id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        // Campaign model
        $model_campaign_master = new CampaignMaster();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_campaign_master->checkRowValidity($campaign_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
       
        // Fetch data
        try {
            $condition = [
                "fields" => [
                    "cm.id",
                    "cm.other_data",
                ],
                "where" => [
                    ["where" => ["cm.id", "=", $campaign_id]],
                ],
            ];
            $row = $model_campaign_master->fetch($condition);

            // Get CSV file data
            $other_data_temp = json_decode($row["other_data"]);
            $file_path = __DIR__."/../../upload/". $other_data_temp->contacts_file; 
            $csv_file_data = [];
   
            if (!empty($file_path)) {
                $data = null;
                try {
                    $data = @file_get_contents($file_path);

                } catch(Exception $e) {
                    // To DO
                }
                if (!empty($data)) {
                    $csv_file_data = $data;
                } 
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $output = [
            "id" => StringComponent::encodeRowId($row["id"]),
            "other_data" => $row["other_data"],
            "csv_file_data" => $csv_file_data,
        ];

        if (!empty($row["last_error"])) {
            $output["last_error"] = $row["last_error"];
        }

        return $response->withJson($output, 200);
    }

    /**
     * Detect delimiter of CSV file
     * 
     * @param $csvFile: Path of file
     *
     * @return string of delimiter
     */
    private function detectDelimiter($csvFile=null) {
        $delimiters = array(
            ";" => 0,
            "," => 0,
            "\t" => 0,
            "|" => 0
        );

        try {
            $handle = fopen($csvFile, "r");
            $firstLine = fgets($handle);
            fclose($handle);

            foreach ($delimiters as $delimiter => &$count) {
                $count = count(str_getcsv($firstLine, $delimiter));
            }

            return array_search(max($delimiters), $delimiters);

        } catch(Exception $e) {
            return ",";
        }
    }

    /**
     * Change campaign from pause state to resume and vice-versa
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function statusPauseResume(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();

        // Get request paramaters
        $route = $request->getAttribute('route');
        $request_campaign_id = $route->getArgument('id');

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Decode request id
        $campaign_id = StringComponent::decodeRowId($request_campaign_id);
        
        // Validate request
        if (empty($campaign_id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        // Get request parameters (POST)
        $request_params = $request->getParsedBody();

        $resume_campaign = false;
        if (isset($request_params["resume"])) {
            $resume_campaign = (bool) $request_params["resume"];
        }

        // Campaign model
        $model_campaign_master = new CampaignMaster();
        $model_campaign_stages = new CampaignStages();

        try {
            // Other values for condition
            $other_values = [
                "user_id" => $logged_in_user,
                "account_id" => $logged_in_account,
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_campaign_master->checkRowValidity($campaign_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Fetch data
        try {
            $condition = [
                "fields" => [
                    "overall_progress",
                    "other_data",
                    "timezone"
                ],
                "where" => [
                    ["where" => ["id", "=", $campaign_id]],
                    ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $row = $model_campaign_master->fetch($condition);

            if (!empty($row)) {
                $current_progress = $row["overall_progress"];
               
                if ($current_progress == SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED")) {
                    $campaign_other_data = json_decode($row["other_data"], true);

                    $params = [
                        "campaign_id" => $campaign_id,
                        "timezone" => $row["timezone"],
                        "max_seconds" => $campaign_other_data["max_seconds"],
                        "min_seconds" => $campaign_other_data["min_seconds"]
                    ];

                    // Get pause campaign details
                    $get_paused_campaign_details = $this->getPausedCampaignDetails($params);
                  
                    if ($get_paused_campaign_details["reschedule_queue"] || $get_paused_campaign_details["reschedule_stage"]) {
                        // If queue or upcoming campaing is effected with delay, then
                        if ($resume_campaign) {
                            // If user has approved the resume, then resume the campaign
                            $output = $this->getPausedCampaignDetails($params, true);

                            if ($output["success"]) {
                                // Get last running campaign progress
                                $condition = [
                                    "fields" => [
                                        "progress"
                                    ],
                                    "where" => [
                                        ["where" => ["campaign_id", "=", $campaign_id]],
                                        ["where" => ["progress", "<>", SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH")]],
                                        ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                                    ],
                                    "order_by" => [
                                        "stage ASC"
                                    ]
                                ];
                                $processed_campaigns = $model_campaign_stages->fetch($condition);

                                if (!empty($processed_campaigns["progress"])) {
                                    $progress_to_set = $processed_campaigns["progress"];

                                } else {
                                    $progress_to_set = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED");
                                }

                                // Set progress of campaign to the last known progress before paused
                                $save_data = [
                                    "id" => $campaign_id,
                                    "overall_progress" => $progress_to_set,
                                    "modified" => DateTimeComponent::getDateTime()
                                ];
                                $model_campaign_master->save($save_data);
                            }

                        } else {
                            // Promot user the information of the campaing effect
                            $output = $get_paused_campaign_details;
                        }

                    } else {
                        // If no queue or upcoming campaign is effected with delay, then resume the campaing right away
                        // Get last running campaign progress
                        $condition = [
                            "fields" => [
                                "progress"
                            ],
                            "where" => [
                                ["where" => ["campaign_id", "=", $campaign_id]],
                                ["where" => ["progress", "<>", SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH")]],
                                ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                            ],
                            "order_by" => [
                                "stage ASC"
                            ]
                        ];
                        $processed_campaigns = $model_campaign_stages->fetch($condition);

                        if (!empty($processed_campaigns["progress"])) {
                            $progress_to_set = $processed_campaigns["progress"];

                        } else {
                            $progress_to_set = SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED");
                        }

                        // Set progress of campaign to the last known progress before paused
                        $save_data = [
                            "id" => $campaign_id,
                            "overall_progress" => $progress_to_set,
                            "modified" => DateTimeComponent::getDateTime()
                        ];
                        if ($model_campaign_master->save($save_data) !== false) {
                            $output["success"] = true;

                        } else {
                            // Fetch error code & message and return the response
                            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                        }
                    }

                } else {
                    // Pause campaign
                    $condition = [
                        "fields" => [
                            "id"
                        ],
                        "where" => [
                            ["where" => ["campaign_id", "=", $campaign_id]],
                            ["where" => ["progress", ">", SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED")]],
                            ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                        ]
                    ];
                    $processed_campaigns = $model_campaign_stages->fetchAll($condition);

                    if (count($processed_campaigns) > 0) {
                        // Allow to pause the campaign only when at lease campaign has been queued once
                        $save_data = [
                            "id" => $campaign_id,
                            "overall_progress" => SHAppComponent::getValue("app_constant/CAMP_PROGRESS_PAUSED"),
                            "modified" => DateTimeComponent::getDateTime()
                        ];
                        if ($model_campaign_master->save($save_data) !== false) {
                            $output["success"] = true;

                        } else {
                            // Fetch error code & message and return the response
                            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                        }

                    } else {
                        // Don't allow to pause campaign whole campaign is yet to be sent
                        return ErrorComponent::outputError($response, "api_messages/CAMP_CANT_PAUSE");
                    }
                }

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        
        return $response->withJson($output, 200);
    }

    /**
     * Get details of campaign if need to pause or already paused
     *
     * @param $params (array): Campaign information
     * @param $update_data (bool): Whether campaign and queue needs to be updated as well (re-scheduling of queue and campaigns)
     *
     * @return (array) Payload of campaign information
     */
    private function getPausedCampaignDetails($params, $update_data = false) {
        $return_data = [
            "reschedule_queue" => false,
            "old_queue_time" => null,
            "reschedule_stage" => false,
            "stage_details" => []
        ];

        $campaign_id = isset($params["campaign_id"]) ? (int) $params["campaign_id"] : 0;
        $timezone = isset($params["timezone"]) ? $params["timezone"] : \DEFAULT_TIMEZONE;
        $min_seconds = isset($params["min_seconds"]) ? (int) $params["min_seconds"] : 0;
        $max_seconds = isset($params["max_seconds"]) ? (int) $params["max_seconds"] : 0;
        $current_stage_days_delay = 0;

        try {
            // Get campaign stage details
            $model_campaign_stages = new CampaignStages();

            $condition = [
                "fields" => [
                    "id",
                    "progress",
                    "stage",
                    "scheduled_on",
                    "stage_defination"
                ],
                "where" => [
                    ["where" => ["campaign_id", "=", $campaign_id]],
                    ["where" => ["progress", "<>", SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH")]],
                    ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ],
                "order_by" => [
                    "stage ASC"
                ]
            ];
            $stage_details = $model_campaign_stages->fetch($condition);
         
            if (!empty($stage_details)) {
                $campaign_stage_id = $stage_details["id"];
                $stage_scheduled_time = $stage_details["scheduled_on"];
                $stage_number = $stage_details["stage"];
                $stage_defination = $stage_details["stage_defination"];

                $current_time = DateTimeComponent::getDateTime();
          
                // Get campaign stage pending sequences
                $model_campaign_sequences = new CampaignSequences();

                $condition = [
                    "fields" => [
                        "MIN(scheduled_at) AS old_scheduled_at",
                        "COUNT(id) AS total_queue"
                    ],
                    "where" => [
                        ["where" => ["campaign_stage_id", "=", $campaign_stage_id]],
                        ["where" => ["progress", "=", SHAppComponent::getValue("app_constant/PROGRESS_SCHEDULED")]]
                    ]
                ];
                $get_pending_queue = $model_campaign_sequences->fetch($condition);
              
           if (!empty($get_pending_queue["old_scheduled_at"])) {
                    // When campaign queue was already prepared
                    $return_data["reschedule_queue"] = true;

                    $return_data["old_queue_time"] = DateTimeComponent::convertDateTime($get_pending_queue["old_scheduled_at"], true, \DEFAULT_TIMEZONE, $timezone, \APP_TIME_FORMAT);

                    $max_queue_reschedule = $current_time + ($max_seconds * $get_pending_queue["total_queue"]);
                    $max_queue_reschedule += 12 * 60 * 60;
                    
                    // If update is called
                    if ($update_data) {                      
                        $condition = [
                            "fields" => [
                                "id",
                                "scheduled_at"
                            ],
                            "where" => [
                                ["where" => ["campaign_stage_id", "=", $campaign_stage_id]],
                                ["where" => ["progress", "=", SHAppComponent::getValue("app_constant/PROGRESS_SCHEDULED")]]
                            ]
                        ];
                        $pending_queue_data = $model_campaign_sequences->fetchAll($condition);
                       
                        $queue_schedule_time = $current_time + (2 * 60);                     
                        foreach ($pending_queue_data as $queue) {
                            try {
                                $random_seconds = (int) rand($min_seconds, $max_seconds);
                                $queue_schedule_time = $queue_schedule_time + $random_seconds;
                                
                                if($queue["scheduled_at"] > $queue_schedule_time){
                                    continue;
                                }

                                $save_data = [
                                    "id" => $queue["id"],
                                    "scheduled_at" => $queue_schedule_time,
                                    "locked" => SHAppComponent::getValue("app_constant/FLAG_NO"),
                                    "modified" => DateTimeComponent::getDateTime()
                                ];
                                $save = $model_campaign_sequences->save($save_data);

                            } catch (\Exception $e) { }
                        }

                        $max_queue_reschedule = $queue_schedule_time + (12 * 60 * 60);
                    }

                } else if ($stage_scheduled_time < $current_time) {
                    // When campaign was not queued, check if time has passed for the stage
                    $max_queue_reschedule = $current_time + (24 * 60 * 60);
                    // It is set to 5 min later because queue is not prepared and queue prepare is done at every 5 minute.
                    $new_run_time = $current_time + ( 5 * 60);
                    $return_data["stage_details"][] = [
                        "stage" => $stage_number,
                        "old_run_date" => DateTimeComponent::convertDateTime($stage_scheduled_time, true, \DEFAULT_TIMEZONE, $timezone, \APP_TIME_FORMAT),
                        "new_run_date" => DateTimeComponent::convertDateTime($new_run_time, true, \DEFAULT_TIMEZONE, $timezone, \APP_TIME_FORMAT)
                    ];
                    $prev_stage_id = 0;
                    // If update is called
               
                    if ($update_data) {
                        try {
                          
                            $max_reply_check_date = $current_time + \CAMPAIGN_MAX_REPLY_CHECK_TIME;
                            $save_data = [
                                "id" => $campaign_stage_id,
                                "scheduled_on" => $new_run_time,
                                "track_reply_max_date" => $max_reply_check_date,
                                "modified" => DateTimeComponent::getDateTime()
                            ];

                            $prev_stage_id = $campaign_stage_id;
                          
                            if ($stage_number > 1) {
                                $diff_new_old = $current_time - $stage_scheduled_time;
                                $current_stage_days_delay = floor($diff_new_old / (24 * 60 * 60));

                                $stage_def_array = json_decode($stage_defination, true);
                                if (isset($stage_def_array["days"])) {
                                    $stage_def_array["days"] = (int) $stage_def_array["days"];
                                    $stage_def_array["days"] += $current_stage_days_delay;
                                }
                                $new_stage_defination = json_encode($stage_def_array);

                                $save_data["stage_defination"] = $new_stage_defination;
                            }
                            $saved = $model_campaign_stages->save($save_data);

                        } catch (\Exception $e) { }
                    }
                }
       
                // Check if stages needs to be re-scheduled or not
                $condition = [
                    "fields" => [
                        "id",
                        "stage",
                        "scheduled_on",
                        "stage_defination"
                    ],
                    "where" => [
                        ["where" => ["campaign_id", "=", $campaign_id]],
                        ["where" => ["progress", "<>", SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH")]],
                        ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                    ],
                    "order_by" => [
                        "stage ASC"
                    ],
                    "limit" => 10,
                    "offset" => 1
                ];
                $get_upcoming_stages = $model_campaign_stages->fetchAll($condition);
             
                if (!empty($get_upcoming_stages)) {
                    $immediate_stage_time = $get_upcoming_stages[0]["scheduled_on"];
                 
                    if ($max_queue_reschedule > $immediate_stage_time) {
                        $days_delay = ceil(($max_queue_reschedule - $immediate_stage_time) / (24 * 60 * 60));
                        if (empty($days_delay)) {
                            $days_delay = 1;
                        }
                    
                        $return_data["reschedule_stage"] = true;
        
                        $max_reply_check_date = 0;
                       
                        for($i=0; $i<count($get_upcoming_stages); $i++) {
                            
                            $stage_time_old = (int) $get_upcoming_stages[$i]["scheduled_on"];
                            $stage_time_new = $stage_time_old + ($days_delay * 24 * 60 * 60);
                              
                            $return_data["stage_details"][] = [
                                "stage" => $get_upcoming_stages[$i]["stage"],
                                "old_run_date" => DateTimeComponent::convertDateTime($stage_time_old, true, \DEFAULT_TIMEZONE, $timezone, \APP_TIME_FORMAT),
                                "new_run_date" => DateTimeComponent::convertDateTime($stage_time_new, true, \DEFAULT_TIMEZONE, $timezone, \APP_TIME_FORMAT)
                            ];
                           
                            // If update is called
                            if ($update_data) {
                                try {

                                    if($prev_stage_id!=0){
                                       
                                        $condition = [
                                            "where" => [
                                                ["where" => ["id","=", $prev_stage_id]]
                                            ]
                                        ];
                                        $save_data = [
                                            "track_reply_max_date"  => $stage_time_new,
                                            "modified"              => DateTimeComponent::getDateTime()
                                        ];   
                                        $model_campaign_stages->update($save_data, $condition); 
                                                         
                                    }
                                    
                                    $max_reply_check_date = $stage_time_new + \CAMPAIGN_MAX_REPLY_CHECK_TIME;
                                    $save_data = [
                                        "id" => $get_upcoming_stages[$i]["id"],
                                        "scheduled_on" => $stage_time_new,
                                        "track_reply_max_date" => $max_reply_check_date,
                                        "modified" => DateTimeComponent::getDateTime()
                                    ];
                                 
                                    if ($i == 0) {
                                        $stage_def_array = json_decode($get_upcoming_stages[$i]["stage_defination"], true);
                                        if (isset($stage_def_array["days"])) {
                                            $stage_def_array["days"] = (int) $stage_def_array["days"];
                                            $stage_def_array["days"] += $days_delay - $current_stage_days_delay;
                                        }
                                        $new_stage_defination = json_encode($stage_def_array);

                                        $save_data["stage_defination"] = $new_stage_defination;
                                    }

                                    $saved = $model_campaign_stages->save($save_data);
                                    $prev_stage_id = $save_data["id"];

                                } catch (\Exception $e) {  }
                            }
                        }
                    }                 
                }
            }

        } catch(\Exception $e) { }

        // If called alng with update, then return only success info rather than pause info
        if ($update_data) {
            $return_data = [];
            $return_data["success"] = true;
        }

        return $return_data;
    }

       
    /**
     * List all data
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function listsDomainBlock(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];
        
        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $get_user_timezone = SHAppComponent::getUserTimeZone();
        
        // Get request parameters
        $params = $request->getQueryParams();

        // Set parameters
        $query_params = [
            "page" => 1,
            "per_page" => SHAppComponent::getValue("app_constant/DEFAULT_50_LIST_PER_PAGE"),
            "order_by" => "id",
            "order" => "DESC"
        ];

        if (!empty($params["page"])) {
            if (is_numeric($params["page"])) {
                $query_params["page"] = (int) $params["page"];
            }
        }
        if (!empty($params["per_page"])) {
            if (is_numeric($params["per_page"])) {
                $query_params["per_page"] = (int) $params["per_page"];
            }
        }
        if (!empty($params["order_by"])) {
            $query_params["order_by"] = trim($params["order_by"]);
        }
        if (!empty($params["order"])) {
            $query_params["order"] = (trim($params["order"]) == "DESC") ? "DESC" : "ASC";
        }
        if (!empty($params["user"])) {
            if ( strtolower($params["user"]) != "all" && strtolower($params["user"]) != "any" ) {
                $user_id = StringComponent::decodeRowId(trim($params["user"]));

                if (!empty($user_id)) {
                    $query_params["user"] = $user_id;
                }
            }
        }
        
        if (!empty($params["query"])) {
            $query_params["query"] = SHAppComponent::prepareSearchText($params["query"]);
        }

        // Other values for condition
        $other_values = [
            "account_id" => $logged_in_account,
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        // Get data
        $model_domain_blocklist = new CampaignDomainBlocklist();

        try {
            $data = $model_domain_blocklist->getListData($query_params, $other_values);
          
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        // Process data and prepare for output
        $output["total_records"] = $data["total_records"];
        $output["total_pages"] = $data["total_pages"];
        $output["current_page"] = $data["current_page"];
        $output["per_page"] = (int) $data["per_page"];
        $output["rows"] = [];
       

        if (!empty($data["rows"])) {

            foreach ($data["rows"] as $row) {
                // Set timezones
                $default_timezone = \DEFAULT_TIMEZONE;
                
                $last_modified = DateTimeComponent::convertDateTime($row["modified"], true, $default_timezone, $get_user_timezone, \APP_TIME_FORMAT);
                
                $row_data = [
                    "id" => StringComponent::encodeRowId($row["id"]),
                    "user" => StringComponent::encodeRowId($logged_in_user),
                    "Domain" => $row["domain"],
                    "status" => $row["status"],
                    "last_modified" => $last_modified
                ];

                $output["rows"][] = $row_data;
            }
        }   

        return $response->withJson($output, 200);
    }

    /**
     * Delete single record from domain block list
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function deleteDomain(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        
        // Get request paramaters
        $route = $request->getAttribute('route');
        $request_domain_id = $route->getArgument('id');
        $domain_id = StringComponent::decodeRowId($request_domain_id);

        // Validate request
        if (empty($domain_id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $model_domain_block = new CampaignDomainBlocklist();
       
        // Fetch data
        try {
            
            $save_data = [
                "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "modified" => DateTimeComponent::getDateTime()
            ];
            $condition = [
                "where" => [
                    ["where" => ["id", "=", $domain_id]]
                ] 
            ];
            if ($model_domain_block->update($save_data, $condition) !== false) {
                $output["message"] = "Domain deleted successfully.";

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
            

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }      

        return $response->withJson($output, 200);
    }

    /**
     * Create a domain block list with csv data
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function createDomainBlocklist(ServerRequestInterface $request, ResponseInterface $response, $args) {
        ini_set("auto_detect_line_endings", true);
        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        
        // Get request parameters
        $request_params = $request->getParsedBody();
        $model_domain_block = new CampaignDomainBlocklist();
        $replace = 0;
        
        if (!empty($request_params["replace_file"]) && $request_params["replace_file"] == 1) {
            $replace = 1;
            
        }
        // Get request file
        $uploaded_files = $request->getUploadedFiles();

        // Get request file length if file not upload successfully
        $get_content_length = $request->getContentLength();

        $result = array("valid"=>true, "message"=>"", "rows"=>0, "srows"=>0, "erows"=>0, "frows"=>0, "skrows"=>0, "dprows"=>0);
        
        // handle single input with single file upload
        $uploaded_file = $uploaded_files['fileObj'];

        // convert request length to megabytes number
        $bytes_size = round(number_format($get_content_length / 1048576, 2));
        
        if (!empty($uploaded_file)) {
        
            // requested file type
            $file_type = $uploaded_file->getClientMediaType();
            
            // requested file error
            $file_error = $uploaded_file->getError();

            // requested file size
            $file_size = (int) $uploaded_file->getSize();
            
            $file_in_bytes = round(number_format($file_size / 1048576, 2));
            
            // Allowed file type
            $valid_type = array(
                'text/csv',
                'text/plain',
                'application/csv',
                'text/comma-separated-values',
                'application/excel',
                'application/vnd.ms-excel',
                'application/vnd.msexcel',
                'text/anytext',
                'application/octet-stream',
                'application/txt'
            );

            // Check if uploaded file is of valid type or not
            if (in_array($file_type, $valid_type) && $file_error == 0 && $file_type != "application/x-rar" && $file_in_bytes <= self::CSV_MAX_FILE_SIZE_IN_MB) {
               
                // requested file name
                $source_file = $uploaded_file->file;
                $rows = $srows = $erows = $frows = $skrows = $dprows = 0;;
                // Possible value for domain column
                $possible_domain_fields = array("Domain", "domain", "Domain Name", "domain name", "Domain name", "Domain_Name", "Domain_name"); // all in word case
                $duplicate_domains = []; 
                try {
                    $csv_file_records = [];
                    $i = 0;
                    $c = 0;
                    $num_cols = 0;
                    $skip_cols = [];
                    $key_of_domain_column = 0;
                    $last_row_empty = 0;

                    $delimiter = $this->detectDelimiter($source_file);

                    $file = fopen($source_file, "r");
                    while(!feof($file)) {
                        $row = fgetcsv($file, 0, $delimiter);
                        $num_cols = ($i == 0) ? count($row) : $num_cols;
                        $c = $num_cols-1;

                        $new_row = array_fill(0, $num_cols, "");

                        while($c >= 0) {
                            if (!in_array($c, $skip_cols)) {
                                if(isset($row[$c])) {
                                    $new_row[$c] = trim($row[$c]);
                                    $new_row[$c] = ($i == 0) ? preg_replace("/\xef\xbb\xbf/", "", $new_row[$c]) : $new_row[$c];
                                    $new_row[$c] = mb_convert_encoding($new_row[$c], "UTF-8");
                                    $new_row[$c] = ($i == 0) ? ucwords(strtolower($new_row[$c])) : $new_row[$c];
                                }
                            }
                            $c--;
                        }

                        if ($i == 0) {
                            if(trim(implode("", $new_row)) == "") {
                             // Fetch error code & message and return the response
                             return ErrorComponent::outputError($response,"api_messages/CSV_HEADER_ROW_MISSING");
                             break; 
                            }

                            if (!in_array(self::CSV_DOMAIN_FIELD, $new_row)) {
                                $found_domain = false;
                                foreach ($possible_domain_fields as $pd) {
                                    $find_key = array_search($pd, $new_row);

                                    if ($find_key !== false) {
                                        $new_row[$find_key] = self::CSV_DOMAIN_FIELD;
                                        $found_domain = true;
                                        break;
                                    }
                                }

                                if (!$found_domain) {
                                    // Fetch error code & message and return the response
                                    return ErrorComponent::outputError($response, "api_messages/CSV_DOMAIN_COLUMN_MISSING");
                                    break;
                                }
                            }

                            if ($num_cols > self::CSV_MAX_COLS) {
                             // Fetch error code & message and return the response
                             return ErrorComponent::outputError($response, "api_messages/CSV_COULMN_LIMIT_EXCEED");
                             break;
                            }

                            $temp_new_row = [];
                            for ($j=0; $j<$num_cols; $j++) {
                                if(empty($new_row[$j]))
                                    $skip_cols[] = $j;
                                else
                                    $temp_new_row[] = $new_row[$j];
                            }
                            $new_row = $temp_new_row;
                            $num_cols = count($new_row);
                            $key_of_domain_column = array_search(self::CSV_DOMAIN_FIELD, $new_row);
                            if ($key_of_domain_column === false) {
                                // Fetch error code & message and return the response
                                return ErrorComponent::outputError($response, "api_messages/CSV_DOMAIN_COLUMN_MISSING");
                                break;
                            }
                        }

                        if (trim(implode("", $new_row)) != "") {
                            $last_row_empty = 0;
                            $i++;

                            if($i > 1) {
                                // If row is data row (except header row), then check if email is valid
                                if(!empty($new_row[$key_of_domain_column])) {
                                    if (!isset($duplicate_domains[$new_row[$key_of_domain_column]])) {
                                        $csv_file_records[] = $new_row[$key_of_domain_column];
                                        $srows++;

                                        $duplicate_domains[$new_row[$key_of_domain_column]] = 1;

                                    } else {
                                        $dprows++;
                                    }

                                } else {
                                    $frows++;
                                }

                            }

                        } else {
                            $last_row_empty++;
                            $erows++;
                        }

                        $rows++;
                    }
                        
                    fclose($file);

                } catch(Exception $e) { }
                
            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/CSV_INVALID_FILE_TYPE");
            }

            // If contacts needs to be replaced
            if ($replace) {
                
                try {
                    
                    $save_data = [
                        "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                        "modified" => DateTimeComponent::getDateTime()
                    ];
                    
                    $condition = [
                        "where" => [
                            ["where" => ["cdb.account_id", "=", $logged_in_account]]
                        ]
                    ];
                    
                   $model_domain_block->update($save_data);

                } catch(Exception $e) { }
            }
            
            if (!empty($last_row_empty)) {
                // Ignore continues empty rows at the end of CSV
                $erows -= $last_row_empty;
                $rows -= $last_row_empty;
            }

            // Insert records
            $loop_counter = 1;
            $affected_rows = 0;
            
            try {
                
                $condition_domains_get = [
                    "fields" => [
                        "cdb.domain"
                    ],
                    "where" => [
                        ["where" => ["cdb.account_id", "=", $logged_in_account]],
                        ["where" => ["cdb.status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                    ]
                ];

                $get_previous_domain_rows = $model_domain_block->fetchAll($condition_domains_get);
                
            } catch(Exception $e) { }
            //convert one dimensional and find only domain values from array 
            $matched_prev_domains = array_column($get_previous_domain_rows, 'domain');
            
            foreach ($csv_file_records as $csv_row) {
                
                if (!empty($csv_row) && $loop_counter <= self::CSV_MAX_ROWS_UPLOAD_LIMIT) {
                    
                    if (!in_array($csv_row, $matched_prev_domains)) {
                    
                        try {
                            //$model_domain_block = new CampaignDomainBlocklist();
                            $save_data = [
                                "account_id" => $logged_in_account,
                                "user_id" => $logged_in_user,
                                "domain" => isset($csv_row) ? trim($csv_row) : "",
                                "modified" => DateTimeComponent::getDateTime()
                            ];
                            
                            if ($model_domain_block->save($save_data)) {
                                $affected_rows++;
                            }

                        } catch (\Exception $e) { }
                    }
                    
                } else {
                    break;
                }
                $loop_counter++;
            }
            
            if ($affected_rows > 0){
                $output["message"] = $affected_rows." Records Imported Successfully";
            } else {
                // Fetch error code & message and return the response
                //return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", "No records found to import file", false); 
                $output["message"] = "No records found to import file";
            }
            
        } else if ($bytes_size > self::CSV_MAX_FILE_SIZE_IN_MB) { 
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/CSV_FILE_SIZE_EXCEED", self::CSV_MAX_FILE_SIZE_IN_MB . " MB");
        }
        return $response->withJson($output, 200);        
    }
    
      /**
     * It will return campaign is delay or not with delay days.
     *
     * @param $campaignId (int): Campaign Id
     * @param $output (array): Response     
     *
     * @return (array) Response array
     */
    public function getCampaignDelayTime($campaignId, $stage){
        $output = [
            "is_delay" => false
        ];
       
        try{

            $model_campaign_stage = new CampaignStages();
            // Campaign stage model
            $condition = [
                "fields" => [                    
                    "cst.scheduled_on",
                    "cst.stage_defination",
                    "cst.progress"
                ],
                "where" => [
                    ["where" => ["cst.campaign_id", "=", $campaignId]],
                    ["where" => ["cst.stage","<=", $stage]],
                    ["where" => ["cst.status", "<>",SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $campaign_stage_data = $model_campaign_stage->fetchAll($condition);
            $first_stage_schedule_time = 0;
            $scheduled_days_from_start = 0;
            $days_delay = 0;
            $diffrence_between_schedule_and_days = 0;
            if(!empty($campaign_stage_data)){
                for ($i = 0; $i < count($campaign_stage_data); $i++) {
                    $current_time = DateTimeComponent::getDateTime();
                    $current_stage_defination = json_decode($campaign_stage_data[$i]["stage_defination"], true);
                    
                    if ($i == 0) {    
                        $first_stage_schedule_time = $campaign_stage_data[$i]['scheduled_on'];
                    } else {
                        $scheduled_days_from_start =  $scheduled_days_from_start + $current_stage_defination["days"];
                    }

                    if($days_delay == 0 && $campaign_stage_data[$i]['progress'] != SHAppComponent::getValue("app_constant/CAMP_PROGRESS_FINISH") && $campaign_stage_data[$i]['progress'] == SHAppComponent::getValue("app_constant/CAMP_PROGRESS_SCHEDULED"))
                    {                           
                        $diff_new_old = $current_time - $campaign_stage_data[$i]['scheduled_on']; 
                        $days_delay = floor($diff_new_old / (24 * 60 * 60));
                    }

                    $reschedule_time = $first_stage_schedule_time + ( $scheduled_days_from_start * 24 * 60 * 60);
                    $diffrence_between_schedule_and_days = $campaign_stage_data[$i]['scheduled_on'] - $reschedule_time;
                }

                if($days_delay > 0){
                    $output["is_delay"] = true;
                }

                if($diffrence_between_schedule_and_days > 60){                       
                    $output["is_delay"] = true;
                }
            }
        } catch(\Exception $e) {
        }  

        return $output;
    }
}
