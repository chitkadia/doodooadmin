<?php
/**
 * Link tracking related functionality
 */
namespace App\Track;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Interop\Container\ContainerInterface;
use \App\Components\StringComponent;
use \App\Components\SHAppComponent;
use \App\Components\LoggerComponent;
use \App\Components\DateTimeComponent;
use \App\Components\SHActivity;
use \App\Models\AccountLinkMaster;
use \App\Models\CampaignLinks;
use \App\Models\CampaignSequences;

class CampaignLinkTracker extends AppTracker {

    const LOG_FILE_DIRECTORY                    = __DIR__ . "/../../logs";
    const LOG_FILE_CAMPAIGN_LINK_TRACK          = self::LOG_FILE_DIRECTORY . "/af_campaign_link_track.log";

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
        $link_track_log_file = self::LOG_FILE_CAMPAIGN_LINK_TRACK;
       
        $redirect_key = $request_params["redirect_key"];
        $request_params = StringComponent::decodeRowId($redirect_key, true);
    

        //Create log directory if not exist
        if (!is_dir(self::LOG_FILE_DIRECTORY)) {
            mkdir(self::LOG_FILE_DIRECTORY, 0777, true);
        }

        return $this->LinkTrack($request, $response, $args, $request_params);
    	
    }

    public function v1LinkTrack(ServerRequestInterface $request, ResponseInterface $response, $args)   {

        // Get request parameters
        $route = $request->getAttribute("route");
        $request_params = $route->getArguments();
        $params = $request->getQueryParams();
        $link_track_log_file = self::LOG_FILE_CAMPAIGN_LINK_TRACK;
        $utm = $request_params["utm"];
       
        $is_found = false;
         //Create log directory if not exist
        if (!is_dir(self::LOG_FILE_DIRECTORY)) {
            mkdir(self::LOG_FILE_DIRECTORY, 0777, true);
        }
        
        if (!empty($utm)) {           
            
            try {
                // Fetch row from campaign link 
                $model_campaign_links = new CampaignLinks();
                $condition = [
                    "fields" => [
                        "cl.account_id",
                        "cl.campaign_stage_id",
                        "cl.campaign_sequence_id",
                        "cl.account_link_id",
                        "cst.user_id",
                    ],
                    "where" => [
                        ["where" => ["cl.utm", "=", $utm]]
                    ],
                    "join" => ["campaign_stages"]
                ];
                $campaign_link_row = $model_campaign_links->fetch($condition);
           
                if (!empty($campaign_link_row)) {
                    
                    $request_params[0] = $campaign_link_row["account_id"];
                    $request_params[1] = $campaign_link_row["campaign_stage_id"];
                    $request_params[2] = $campaign_link_row["campaign_sequence_id"];
                    $request_params[3] = $campaign_link_row["account_link_id"];
                    $request_params[4] = $campaign_link_row["user_id"];
                    $is_found = true;
                    return $this->LinkTrack($request, $response, $args, $request_params);

                }
            } catch(\Exception $e) {
                LoggerComponent::log("Error occured Campaign Stage # ".$campaign_stage_id." Sequence # ". $sequence_id." -- ".$e->getMessage(), $link_track_log_file);
            }            
          
        }

        if (!$is_found) {
            
            $output_template = __DIR__ . "/../../track/404.html";
            $http_status = 404;

            try {
                // Start output buffer
                ob_start();

                // Insert template
                include $output_template;

                // Flush buffer and store output
                $output = ob_get_clean();

            } catch (\Exception $e) {
                $output_template = __DIR__ . "/../../track/500.html";
                $http_status = 500;
            }
            
            // Send output
            $response->getBody()->write($output);
            return $response->withStatus($http_status);
        }

     }

     public function LinkTrack(ServerRequestInterface $request, ResponseInterface $response, $args, $request_params) {
          
        $redirect_url = "";
        if (!empty($request_params)) {
            
            $account_id = $request_params[0];
            $campaign_stage_id = $request_params[1];
            $sequence_id = $request_params[2];
            $account_link_id = $request_params[3];
            $user_id = $request_params[4];
            
            $last_clicked = DateTimeComponent::getDateTime();
 
            try {
                
                LoggerComponent::log("Tracking Campaign Link : Campaign Stage # ".$campaign_stage_id." Sequence # ".$sequence_id, $link_track_log_file);
                
                // Fetch row from account link master               
                $model_account_link_master = new AccountLinkMaster();
                $model_campaign_sequences = new CampaignSequences();
                $model_campaign_links = new CampaignLinks();
                $condition = [
                    "fields" => [
                        "url",
                        "total_clicked"
                    ],
                    "where" => [
                        ["where" => ["id", "=", $account_link_id]],
                        ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                    ]
                ];
                $account_link_row = $model_account_link_master->fetch($condition);
                       
                if (!empty($account_link_row)) {          
                    $redirect_url = $account_link_row["url"];   
                    //fetch row from campaign links
                    
                    $condition = [
                        "fields" => [
                            "cl.id",
                            "cl.total_clicked",
                            "csq.is_bounce"
                        ],
                        "where" => [
                            ["where" => ["cl.account_id", "=", $account_id]],
                            ["where" => ["cl.campaign_sequence_id", "=", $sequence_id]],
                            ["where" => ["cl.account_link_id", "=", $account_link_id]],
                            ["where" => ["cm.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                        ],
                        "join" => [
                            "campaign_sequences",
                            "campaign_master"
                        ]
                    ];
                        
                    $campaign_link_row = $model_campaign_links->fetch($condition);
         
                     // set open count to 0 when it is bounce.
                    if($campaign_link_row["is_bounce"]){
                    
                        //Save record to campaign sequence 
                        try {
                         
                            $save_data = [
                                "id" => $sequence_id,
                                "click_count" => 0,
                                "open_count"  => 0,  
                                "last_clicked" => "",
                                "last_opened"  => 0,
                                "modified" => $last_clicked
                            ];
                        
                            $model_campaign_sequences->save($save_data);

                        } catch(\Exception $e) {
                                                        
                        }

                        //Save record to campaign link 
                        try {
                            $condition = [
                                "where" => [
                                    ["where" => ["campaign_sequence_id", "=", $sequence_id]]
                                ]
                            ];
                            $save_data = [
                                "total_clicked" => 0,
                                "last_clicked" => "",
                                "modified" => $last_clicked
                            ];
                            $updated = $model_campaign_links->update($save_data, $condition);

                        } catch(\Exception $e) {

                        }
                    }
                    else if (!empty($campaign_link_row)) {
                        //Save record to account_link_master
                        try {
                            $save_data = [
                                "id" => $account_link_id,
                                "total_clicked" => $account_link_row["total_clicked"] + 1,
                                "last_clicked" => $last_clicked,
                                "modified" => $last_clicked
                            ];

                            $model_account_link_master->save($save_data);

                        } catch(\Exception $e) { 
                            LoggerComponent::log("Error updating link count in account link master table for # ".$account_link_id." -- ".$e->getMessage(), $link_track_log_file);
                        }
                        
                        //save record to campaign links
                        try {
                            $save_data = [
                                "id" => $campaign_link_row["id"],
                                "total_clicked" => $campaign_link_row["total_clicked"] + 1,
                                "last_clicked" => $last_clicked,
                                "modified" => $last_clicked
                            ];

                            $model_campaign_links->save($save_data);
                            
                        } catch(\Exception $e) {
                            LoggerComponent::log("Error updating link count in campaign link table for # ".$campaign_link_row["id"]." Sequence # ". $sequence_id." -- ".$e->getMessage(), $link_track_log_file);
                        }
                    

                        //fetch row from campaign sequence
                        try {
                            
                            $condition = [
                                "fields" => [
                                    "csq.click_count",
                                    "csq.account_contact_id",
                                    "cm.title",
                                    "cst.stage",
                                    "ac.first_name",
                                    "ac.email"
                                ],
                                "where" => [
                                    ["where" => ["csq.id", "=", $sequence_id]],
                                    ["where" => ["csq.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                                ],
                                "join" => [
                                    "campaign_master",
                                    "campaign_stages",
                                    "account_contacts"
                                ]
                            ];
                            $campaign_sequences_row = $model_campaign_sequences->fetch($condition);
                        } catch(\Exception $e) {
                            LoggerComponent::log("Error fetching details of campaing sequence table for sequence id # ".$sequence_id." -- ".$e->getMessage(), $link_track_log_file);
                        }
                    
                        if (!empty($campaign_sequences_row)) {
                            //save record to campaign sequence
                            try {
                                $save_data = [
                                    "id" => $sequence_id,
                                    "click_count" => $campaign_sequences_row["click_count"] + 1,
                                    "last_clicked" => $last_clicked,
                                    "modified" => $last_clicked
                                ];
                            
                                $model_campaign_sequences->save($save_data);

                                //Add Activity 
                                $activity_params = [
                                    "user_id" => $user_id,
                                    "account_id" => $account_id,
                                    "action" => SHAppComponent::getValue("actions/CAMPAIGNS/CLICKED"),
                                    "record_id" => $campaign_stage_id,
                                    "account_contact_id" => $campaign_sequences_row["account_contact_id"],
                                    "stage" => $campaign_sequences_row["stage"],
                                    "campaign_title" => $campaign_sequences_row["title"],
                                    "url" => $redirect_url,
                                    "sub_record_id" => $account_link_id
                                ];
                                $name = trim($campaign_sequences_row["first_name"]);
                                $name = empty($name) ? $campaign_sequences_row["email"] : $name;
                                $activity_params["cname"] = $name;
                                                
                                $sh_activity = new SHActivity();
                                $sh_activity->addActivity($activity_params);
                                            
                            } catch(\Exception $e) {
                                LoggerComponent::log("Error updating link count in campaign sequence table for Sequence # ". $sequence_id." -- ".$e->getMessage(), $link_track_log_file);
                            }                      
                        }else{                        
                            LoggerComponent::log("CampaignSequences record not found for sequence # ". $sequence_id, $link_track_log_file);
                        }
                    }else{                        
                        LoggerComponent::log("CampaignLinks record not found for sequence # ". $sequence_id, $link_track_log_file);
                    }               
                }else{                        
                    LoggerComponent::log("AccountLinkMaster record not found for account_link_id # ". $account_link_id, $link_track_log_file);
                }
            } catch(\Exception $e) {
                LoggerComponent::log("Error occured Campaign Stage # ".$campaign_stage_id." Sequence # ". $sequence_id." -- ".$e->getMessage(), $link_track_log_file);
            }
        }
    
        if ($redirect_url != "") {
            $parsed = parse_url($redirect_url);
            if (empty($parsed["scheme"])) {
                $redirect_url = "http://" . ltrim($redirect_url, "/");
            }
            LoggerComponent::log("Redirect to : ".$redirect_url, $link_track_log_file);
            LoggerComponent::log("===========================================", $link_track_log_file);
            return $response->withRedirect($redirect_url);
        } else {
            
            $output_template = __DIR__ . "/../../track/404.html";
            $http_status = 404;

            try {
                // Start output buffer
                ob_start();

                // Insert template
                include $output_template;

                // Flush buffer and store output
                $output = ob_get_clean();

            } catch (\Exception $e) {
                $output_template = __DIR__ . "/../../track/500.html";
                $http_status = 500;
            }
            
            LoggerComponent::log("Error http status : ".$http_status, $link_track_log_file);
            LoggerComponent::log("===========================================", $link_track_log_file);
            
            // Send output
            $response->getBody()->write($output);
            return $response->withStatus($http_status);
        }
     }

}
