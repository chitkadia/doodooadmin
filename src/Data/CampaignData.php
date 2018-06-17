<?php
namespace App\Data;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Interop\Container\ContainerInterface;
use \App\Components\SHAppComponent;
use \App\Components\StringComponent;
use \App\Components\DateTimeComponent;
use \App\Components\ErrorComponent;
use \App\Components\LoggerComponent;
use \App\Models\CampaignMaster;
use \App\Models\CampaignStages;
use \App\Models\CampaignSequences;
use \App\Models\UserAuthenticationTokens;

class CampaignData extends AppData {

	/**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }


    /**
     * Export stage performance details in Campaign
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function exportData(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameter
        $route                  = $request->getAttribute('route');
        $request_campaign_id    = $route->getArgument('id'); // Request campaign'id
        $request_stage_id       = $route->getArgument('stage_id'); // Request campaign's Stage id
        $request_auth_token     = $route->getArgument('auth_token'); // User auth token
        $campaign_id    		= StringComponent::decodeRowId($request_campaign_id);
        $stage_id       		= StringComponent::decodeRowId($request_stage_id);
        $current_time 			= \App\Components\DateTimeComponent::getDateTime();
        $track_reply    		= 0;
        $track_click    		= 0;
        $csv_payload   			= null;
        $timezone       		= \DEFAULT_TIMEZONE;
        $server_timezone		= \DEFAULT_TIMEZONE;

        // Validate request
        if (empty($campaign_id) || empty($stage_id) ) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $model_campaign_master      = new CampaignMaster();
        $model_campaign_sequences   = new CampaignSequences();

        // Get user id and account id from auth token
        try {
        	$model = new \App\Models\UserAuthenticationTokens();

            $condition = [
                "fields" => [
                    "uat.user_id",
                    "um.account_id",
                    "uat.expires_at"
                ],
                "where" => [
                    ["where" => ["uat.token", "=", $request_auth_token]],
                    ["where" => ["um.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ],
                "join" => [
                    "user_master"
                ]
            ];
            $row = $model->fetch($condition);

            if (!empty($row)) {
	            
	            if ($row["expires_at"] < $current_time) {
	                // Fetch error code & message and return the response
	               return \App\Components\ErrorComponent::outputError($response, "api_messages/AUTH_TOKEN_EXPIRED");
	            }

	            // Other values for condition
	            $other_values = [
	                "user_id"       => $row["user_id"],
	                "account_id"    => $row["account_id"],
	                "deleted"       => SHAppComponent::getValue("app_constant/STATUS_DELETE")
	            ];
	            $valid = $model_campaign_master->checkRowValidity($campaign_id, $other_values);

	            if (!$valid) {
	                // Fetch error code & message and return the response
	                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
	            }
	        } else {
	        	return \App\Components\ErrorComponent::outputError($response, "api_messages/AUTH_TOKEN_EXPIRED");
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
                    $final_title =  preg_replace('/[^A-Za-z0-9\-]/','', $campaign_master_data[0]['title']); 
                    $title = $final_title."_Stage".$campaign_master_data[0]['stage']."_export";
                }
            }
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        
        $file = fopen("php://output",'w') or die("Can't open php://output");
        foreach($data_array as $list) {
				fputcsv($file, $list);
		}
       
        return $response->withHeader('Content-Description', 'File Transfer')
				   ->withHeader('Content-Type', 'application/csv')
				   ->withHeader('Content-Disposition: attachment; filename='.$title.".csv")
				   ->withHeader('Expires', '0')
				   ->withHeader('Cache-Control', 'must-revalidate')
				   ->withHeader('Pragma', 'public')
				   ->withHeader('Content-Length', filesize($file));
    }
}