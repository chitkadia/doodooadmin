<?php
/**
 * Campaign tracking related functionality
 */
namespace App\Track;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Interop\Container\ContainerInterface;
use \App\Components\SHAppComponent;
use \App\Components\DateTimeComponent;
use \App\Components\LoggerComponent;
use \App\Components\SHActivity;
use \App\Components\StringComponent;
use \App\Components\DisplayPixelComponent;
use \App\Models\CampaignSequences;
use \App\Models\CampaignTrackHistory;

class CampaignTracker extends AppTracker {

    const LOG_FILE_DIRECTORY                    = __DIR__ . "/../../logs";
    const LOG_FILE_CAMPAIGN_OPEN_COUNT          = self::LOG_FILE_DIRECTORY . "/af_campaign_open_track.log";
    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $args) {
     
        // Get request parameters
        $route = $request->getAttribute("route");    
        $request_params = $route->getArguments();        
        $model_campaign_sequences = new CampaignSequences();
        
        $campaign_sequence_id = StringComponent::decodeRowId($request_params["campaign_sequence_id"]);
        $account_id = StringComponent::decodeRowId($request_params["account_id"]);
        $user_id = StringComponent::decodeRowId($request_params["user_id"]);

        $modified_time = DateTimeComponent::getDateTime();

        $open_count_log_file = self::LOG_FILE_CAMPAIGN_OPEN_COUNT;
        
        //Create log directory if not exist
        if (!is_dir(self::LOG_FILE_DIRECTORY)) {
            mkdir(self::LOG_FILE_DIRECTORY, 0777, true);
        }

        try {
            LoggerComponent::log("Tracking Open Count : Account # ".$account_id." --  Sequence # ".$campaign_sequence_id, $open_count_log_file);
            
            $condition = [
                "fields" => [
                    "csq.id",
                    "csq.open_count",
                    "csq.last_opened",
                    "csq.campaign_stage_id",
                    "csq.account_contact_id",
                    "csq.is_bounce",
                    "cm.title",
                    "cst.stage",
                    "ac.first_name",
                    "ac.email"
                ],
                "where" => [
                    ["where" => ["csq.id", "=", $campaign_sequence_id]],
                    ["where" => ["cm.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ],
                "join" => [
                    "campaign_master",
                    "campaign_stages",
                    "account_contacts"
                ]
            ];
            
            $row_campaign_sequence = $model_campaign_sequences->fetch($condition);
            
            if ( empty($row_campaign_sequence["id"]) ) {                
                LoggerComponent::log("CampaignSequences record not found for sequence # ". $campaign_sequence_id, $open_count_log_file);
                LoggerComponent::log("===========================================", $open_count_log_file);
                return DisplayPixelComponent::displayPixel($response);
            }
            $now = strtotime("now");
            $datediff = DIFFERENCE_BETWEEN_LAST_MAIL_OPEN_SECOND;

            if ($row_campaign_sequence["last_opened"] != 0 ) {
                $datediff = $now - $row_campaign_sequence["last_opened"];
            }

            // set open count to 0 when it is bounce.
            if($row_campaign_sequence["is_bounce"] && $row_campaign_sequence["open_count"]>0){
                
                 //Save record to campaign sequence 
                 try {
                    $save_data = [
                        "id" => $row_campaign_sequence["id"],
                        "click_count" => 0,
                        "open_count"  => 0,  
                        "last_clicked" => "",
                        "last_opened"  => "",
                        "modified" => $modified_time
                    ];
                    
                    $saved = $model_campaign_sequences->save($save_data);
                    
                } catch(\Exception $e) {
                    LoggerComponent::log("Error occured to update open count to 0 when bounce for campaign sequence # ". $row_campaign_sequence["id"]." -- ".$e->getMessage(), $open_count_log_file);
                    LoggerComponent::log("===========================================", $open_count_log_file);
                    return DisplayPixelComponent::displayPixel($response);
                }

                //Set campaign link to click count 0 when it is bounce.
                try {
                    $model_campaign_links = new CampaignLinks();
                    $condition = [
                        "where" => [
                            ["where" => ["campaign_sequence_id", "=", $row_campaign_sequence["id"]]]
                        ]
                    ];
                    $save_data = [
                        "total_clicked" => 0,
                        "last_clicked" => "",
                        "modified" => $last_clicked
                    ];
                    $updated = $model_campaign_links->update($save_data, $condition);                           
               
                } catch(\Exception $e) {
                    LoggerComponent::log("Error occured to update open count to 0 when bounce for campaign link # ". $row_campaign_sequence["id"]." -- ".$e->getMessage(), $open_count_log_file);
                    LoggerComponent::log("===========================================", $open_count_log_file);
                    return DisplayPixelComponent::displayPixel($response);                            
                }
            }
            
            if($datediff >= DIFFERENCE_BETWEEN_LAST_MAIL_OPEN_SECOND && !$row_campaign_sequence["is_bounce"]) {   
            
                //Save record to campaign sequence
                try {
                    $save_data = [
                        "id" => $row_campaign_sequence["id"],
                        "open_count" => $row_campaign_sequence["open_count"] + 1,
                        "last_opened" => DateTimeComponent::getDateTime()
                    ];
                    
                    $saved = $model_campaign_sequences->save($save_data);

                } catch(\Exception $e) {
                    LoggerComponent::log("Error occured to update open count for campaign sequence # ". $row_campaign_sequence["id"]." -- ".$e->getMessage(), $open_count_log_file);
                    LoggerComponent::log("===========================================", $open_count_log_file);
                    return DisplayPixelComponent::displayPixel($response);
                }

                // Save record to campaign track history
                try {
                
                    $model_campaign_track_history = new CampaignTrackHistory();
                    
                    $save_data = [
                        "campaign_sequence_id" => $campaign_sequence_id,
                        "type" => SHAppComponent::getValue("app_constant/EMAIL_TRACK_OPEN"),
                        "acted_at" => DateTimeComponent::getDateTime()
                    ];
                    
                    $saved = $model_campaign_track_history->save($save_data);
                    
                } catch(\Exception $e) {
                    LoggerComponent::log("Error occured to add campaign track history for campaign sequence # ". $campaign_sequence_id." -- ".$e->getMessage(), $open_count_log_file);
                    LoggerComponent::log("===========================================", $open_count_log_file);
                    return DisplayPixelComponent::displayPixel($response);
                }

                //Add Activity 
                try {
                    
                    $activity_params = [
                        "user_id" => $user_id,
                        "account_id" => $account_id,
                        "action" => SHAppComponent::getValue("actions/CAMPAIGNS/OPEN"),
                        "record_id" => $row_campaign_sequence["campaign_stage_id"],
                        "account_contact_id" => $row_campaign_sequence["account_contact_id"],
                        "stage" => $row_campaign_sequence["stage"],
                        "campaign_title" => $row_campaign_sequence["title"]
                    ];
                    $name = trim($row_campaign_sequence["first_name"]);
                    $name = empty($name) ? $row_campaign_sequence["email"] : $name;
                    $activity_params["cname"] = $name;
                    
                    $sh_activity = new SHActivity();
                    $sh_activity->addActivity($activity_params);
                                        
                } catch(\Exception $e) {
                    return DisplayPixelComponent::displayPixel($response); 
                }

            }

        } catch(\Exception $e) {
            LoggerComponent::log("Error occured for campaign sequence # ". $campaign_sequence_id." -- ".$e->getMessage(), $open_count_log_file);
            LoggerComponent::log("===========================================", $open_count_log_file);
            return DisplayPixelComponent::displayPixel($response);
        }
        LoggerComponent::log("===========================================", $open_count_log_file);
        // return tracking pixel image
        return DisplayPixelComponent::displayPixel($response);
    }

  public function v1CampaignTrack(ServerRequestInterface $request, ResponseInterface $response, $args) {
    
        // Get request parameters
        $route = $request->getAttribute("route");    
        $request_params = $route->getArguments();        
        
        $model_campaign_sequences = new CampaignSequences();
        $campaign_sequence_id = StringComponent::decryptID($request_params["campaign_sequence_id"]);
        
        
         try {
            $condition = [
                "fields" => [
                    "csq.id",
                    "cst.account_id",
                    "cst.user_id"
                ],
                "where" => [
                    ["where" => ["csq.id", "=", $campaign_sequence_id]]                    
                ],
                "join" => ["campaign_stages"]
            ];

            $row_seq = $model_campaign_sequences->fetch($condition);
           
            $account_id = 0;
            $user_id = 0;
            
            if(!empty($row_seq)){
                $account_id = $row_seq["account_id"];
                $user_id = $row_seq["user_id"];
            }

            $params = [
                "campaign_sequence_id" => $campaign_sequence_id,
                "account_id" => $account_id,
                "user_id" => $user_id
            ];
           
  
            return $this->campaignTrack($request,$response, $args, $params);
            
        } catch(\Exception $e) {
            return DisplayPixelComponent::displayPixel($response);
        }
        
    }

     public function campaignTrack(ServerRequestInterface $request, ResponseInterface $response, $args, $params){


        $campaign_sequence_id = $params["campaign_sequence_id"];
        $account_id = $params["account_id"];
        $user_id = $params["user_id"];

        
        $model_campaign_sequences = new CampaignSequences();
        $modified_time = DateTimeComponent::getDateTime();

        $open_count_log_file = self::LOG_FILE_CAMPAIGN_OPEN_COUNT;
        
        //Create log directory if not exist
        if (!is_dir(self::LOG_FILE_DIRECTORY)) {
            mkdir(self::LOG_FILE_DIRECTORY, 0777, true);
        }

        try {
            LoggerComponent::log("Tracking Open Count : Account # ".$account_id." --  Sequence # ".$campaign_sequence_id, $open_count_log_file);
            
            $condition = [
                "fields" => [
                    "csq.id",
                    "csq.open_count",
                    "csq.last_opened",
                    "csq.campaign_stage_id",
                    "csq.account_contact_id",
                    "csq.is_bounce",
                    "cm.title",
                    "cst.stage",
                    "ac.first_name",
                    "ac.email"
                ],
                "where" => [
                    ["where" => ["csq.id", "=", $campaign_sequence_id]]
                ],
                "join" => [
                    "campaign_master",
                    "campaign_stages",
                    "account_contacts"
                ]
            ];
            
            $row_campaign_sequence = $model_campaign_sequences->fetch($condition);
        
            if ( empty($row_campaign_sequence["id"]) ) {                
                LoggerComponent::log("CampaignSequences record not found for sequence # ". $campaign_sequence_id, $open_count_log_file);
                LoggerComponent::log("===========================================", $open_count_log_file);
                return DisplayPixelComponent::displayPixel($response);
            }
            $now = strtotime("now");
            $datediff = DIFFERENCE_BETWEEN_LAST_MAIL_OPEN_SECOND;

            if ($row_campaign_sequence["last_opened"] != 0 ) {
                $datediff = $now - $row_campaign_sequence["last_opened"];
            }

            // set open count to 0 when it is bounce.
            if($row_campaign_sequence["is_bounce"] && $row_campaign_sequence["open_count"]>0){
                
                 //Save record to campaign sequence 
                 try {
                    $save_data = [
                        "id" => $row_campaign_sequence["id"],
                        "click_count" => 0,
                        "open_count"  => 0,  
                        "last_clicked" => "",
                        "last_opened"  => "",
                        "modified" => $modified_time
                    ];
                    
                    $saved = $model_campaign_sequences->save($save_data);
                    
                } catch(\Exception $e) {
                    LoggerComponent::log("Error occured to update open count to 0 when bounce for campaign sequence # ". $row_campaign_sequence["id"]." -- ".$e->getMessage(), $open_count_log_file);
                    LoggerComponent::log("===========================================", $open_count_log_file);
                    return DisplayPixelComponent::displayPixel($response);
                }

                //Set campaign link to click count 0 when it is bounce.
                try {
                    $model_campaign_links = new CampaignLinks();
                    $condition = [
                        "where" => [
                            ["where" => ["campaign_sequence_id", "=", $row_campaign_sequence["id"]]]
                        ]
                    ];
                    $save_data = [
                        "total_clicked" => 0,
                        "last_clicked" => "",
                        "modified" => $last_clicked
                    ];
                    $updated = $model_campaign_links->update($save_data, $condition);                           
               
                } catch(\Exception $e) {
                    LoggerComponent::log("Error occured to update open count to 0 when bounce for campaign link # ". $row_campaign_sequence["id"]." -- ".$e->getMessage(), $open_count_log_file);
                    LoggerComponent::log("===========================================", $open_count_log_file);
                    return DisplayPixelComponent::displayPixel($response);                            
                }
            }
            
            if($datediff >= DIFFERENCE_BETWEEN_LAST_MAIL_OPEN_SECOND && !$row_campaign_sequence["is_bounce"]) {   
            
                //Save record to campaign sequence
                try {
                    $save_data = [
                        "id" => $row_campaign_sequence["id"],
                        "open_count" => $row_campaign_sequence["open_count"] + 1,
                        "last_opened" => DateTimeComponent::getDateTime()
                    ];
                    
                    $saved = $model_campaign_sequences->save($save_data);

                } catch(\Exception $e) {
                    LoggerComponent::log("Error occured to update open count for campaign sequence # ". $row_campaign_sequence["id"]." -- ".$e->getMessage(), $open_count_log_file);
                    LoggerComponent::log("===========================================", $open_count_log_file);
                    return DisplayPixelComponent::displayPixel($response);
                }

                // Save record to campaign track history
                try {
                
                    $model_campaign_track_history = new CampaignTrackHistory();
                    
                    $save_data = [
                        "campaign_sequence_id" => $campaign_sequence_id,
                        "type" => SHAppComponent::getValue("app_constant/EMAIL_TRACK_OPEN"),
                        "acted_at" => DateTimeComponent::getDateTime()
                    ];
                    
                    $saved = $model_campaign_track_history->save($save_data);
                    
                } catch(\Exception $e) {
                    LoggerComponent::log("Error occured to add campaign track history for campaign sequence # ". $campaign_sequence_id." -- ".$e->getMessage(), $open_count_log_file);
                    LoggerComponent::log("===========================================", $open_count_log_file);
                    return DisplayPixelComponent::displayPixel($response);
                }

                //Add Activity 
                try {
                    
                    $activity_params = [
                        "user_id" => $user_id,
                        "account_id" => $account_id,
                        "action" => SHAppComponent::getValue("actions/CAMPAIGNS/OPEN"),
                        "record_id" => $row_campaign_sequence["campaign_stage_id"],
                        "account_contact_id" => $row_campaign_sequence["account_contact_id"],
                        "stage" => $row_campaign_sequence["stage"],
                        "campaign_title" => $row_campaign_sequence["title"]
                    ];
                    $name = trim($row_campaign_sequence["first_name"]);
                    $name = empty($name) ? $row_campaign_sequence["email"] : $name;
                    $activity_params["cname"] = $name;
                    
                    $sh_activity = new SHActivity();
                    $sh_activity->addActivity($activity_params);
                                        
                } catch(\Exception $e) {
                    return DisplayPixelComponent::displayPixel($response); 
                }

            }

        } catch(\Exception $e) {
            LoggerComponent::log("Error occured for campaign sequence # ". $campaign_sequence_id." -- ".$e->getMessage(), $open_count_log_file);
            LoggerComponent::log("===========================================", $open_count_log_file);
            return DisplayPixelComponent::displayPixel($response);
        }
        LoggerComponent::log("===========================================", $open_count_log_file);
        // return tracking pixel image
        return DisplayPixelComponent::displayPixel($response);
    }

}