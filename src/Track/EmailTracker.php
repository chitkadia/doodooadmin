<?php
/**
 * Email tracking related functionality
 */
namespace App\Track;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Interop\Container\ContainerInterface;
use \App\Components\SHAppComponent;
use \App\Components\DateTimeComponent;
use \App\Components\StringComponent;
use \App\Components\SHActivity;
use \App\Components\DisplayPixelComponent;
use \App\Models\EmailMaster;
use \App\Models\EmailTrackHistory;

class EmailTracker extends AppTracker {

    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $args) {
        
        $route = $request->getAttribute("route");
        $request_params = $route->getArguments();

        $params = [
            "email_id" => StringComponent::decodeRowId($request_params["email_id"]),
            "account_id" => StringComponent::decodeRowId($request_params["account_id"]),
            "user_id" => StringComponent::decodeRowId($request_params["user_id"])
        ];

        return $this->emailTrack($request,$response, $args, $params);
    } 
    
    public function v1EmailTrack(ServerRequestInterface $request, ResponseInterface $response, $args) {
     
        $route = $request->getAttribute("route");
        $request_params = $route->getArguments();
        
        $model_email_master = new EmailMaster();
        $email_id = $request_params["email_id"];

        try {
            $condition = [
                "fields" => [
                    "id",
                    "account_id"
                ],
                "where" => [
                    ["where" => ["id", "=", $email_id]]                    
                ]
            ];

            $row_email = $model_email_master->fetch($condition);
          
            $account_id = 0;
            
            if(!empty($row_email) && isset($row_email["account_id"])){
                $account_id = $row_email["account_id"];
            }

            $params = [
                "email_id" => $email_id,
                "account_id" => $account_id,
                "user_id" => $request_params["user_id"]
            ];
           
  
            return $this->emailTrack($request,$response, $args, $params);
            
        } catch(\Exception $e) {
            return DisplayPixelComponent::displayPixel($response);
        }
        
    }
     public function emailTrack(ServerRequestInterface $request, ResponseInterface $response, $args, $params){

        $email_id = $params["email_id"];
        $account_id = $params["account_id"];
        $user_id = $params["user_id"];

        $model_email_master = new EmailMaster();
        try {
            $condition = [
                "fields" => [
                    "id",
                    "open_count",
                    "last_opened",
                    "user_id",
                    "account_id",
                    "subject",
                    "source_id",
                    "sent_at",
                    "snooze_notifications"
                ],
                "where" => [
                    ["where" => ["id", "=", $email_id]],
                    ["where" => ["progress", "=", SHAppComponent::getValue("app_constant/PROGRESS_SENT")]],
                    ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];

            $row_email = $model_email_master->fetch($condition);

            if ( empty($row_email["id"]) ) {
                return DisplayPixelComponent::displayPixel($response);
            }

            $now = DateTimeComponent::getDateTime();
            $datediff = DIFFERENCE_BETWEEN_LAST_MAIL_OPEN_SECOND;

            $source_outlook = SHAppComponent::getValue("source/OUTLOOK_PLUGIN");
            $source_chrome = SHAppComponent::getValue("source/CHROME_PLUGIN");
            $isFirstTime = false;
           
            if ($row_email["last_opened"] != 0 ) {
                $datediff = $now - $row_email["last_opened"];
            } else if ($row_email["source_id"] == $source_outlook || $row_email["source_id"] == $source_chrome ) {
                $datediff = $now - $row_email["sent_at"];
                $isFirstTime = true;
            }

            $max_duration = 0;
            if($isFirstTime){
                if ($row_email["source_id"] == $source_outlook) {
                    $max_duration = OUTLOOK_MAIL_OPEN_MARGIN_FIRST_TIME;
                } else {
                    $max_duration = MAIL_OPEN_MARGIN_FIRST_TIME;
                }
            }else{
                $max_duration = DIFFERENCE_BETWEEN_LAST_MAIL_OPEN_SECOND;
            }
         
            if ($datediff >= $max_duration) {
                try {
                    $save_data = [
                        "id" => $email_id,
                        "open_count" => $row_email["open_count"] + 1,
                        "last_opened" => DateTimeComponent::getDateTime()
                    ];
                    $saved = $model_email_master->save($save_data);

                } catch(\Exception $e) {
                    return DisplayPixelComponent::displayPixel($response);
                }

                // Save record to email track history
                try {
                    $model_email_track_history = new EmailTrackHistory();
                    $save_data = [
                        "email_id" => $email_id,
                        "type" => SHAppComponent::getValue("app_constant/EMAIL_TRACK_OPEN"),
                        "acted_at" => DateTimeComponent::getDateTime()
                    ];
                    $saved = $model_email_track_history->save($save_data);

                } catch(\Exception $e) {
                    return DisplayPixelComponent::displayPixel($response);
                }

                //Add Activity 
                try {
                    $activity_params = [
                        "user_id" => $user_id,
                        "account_id" => $account_id,
                        "action" => SHAppComponent::getValue("actions/EMAILS/OPEN"),
                        "record_id" => $email_id,
                        "subject" => $row_email["subject"],
                        "snooze" => $row_email["snooze_notifications"]
                    ];
                    
                    $sh_activity = new SHActivity();
                    $sh_activity->addActivity($activity_params);
                                        
                } catch(\Exception $e) {
                    return DisplayPixelComponent::displayPixel($response); 
                }
            }
        } catch(\Exception $e) {
            return DisplayPixelComponent::displayPixel($response);
        }
        return DisplayPixelComponent::displayPixel($response);
    }
     
}