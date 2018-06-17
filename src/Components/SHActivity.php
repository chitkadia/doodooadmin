<?php
/**
 * Library for add activity based on action
 */
namespace App\Components;

use \App\Components\SHAppComponent;
use \App\Components\BrowserInformationComponent;
use \App\Components\DateTimeComponent;
use \App\Components\LoggerComponent;
use \App\Components\PushNotificationComponent;
use \App\Components\StringComponent;

use \App\Models\Activity;
use \App\Models\EmailRecipients;
use \App\Models\EmailMaster;

class SHActivity {

    const LOG_FILE_PATH =  __DIR__ . "/../../logs/sh_activity.log";
    const NOTIFICATION_TITLE = "SalesHandy";
    /**
     * Function is used to add activity based on action
     * 
     * @param $activity_params (array) : Parameters to identify activity record
     *
     * @return null;
     */
    public function addActivity($activity_params=[]) {
        
        $user_id = !empty($activity_params["user_id"]) ? (int) $activity_params["user_id"] : 0;
        $account_id = !empty($activity_params["account_id"]) ? (int) $activity_params["account_id"] : 0;
        $action = !empty($activity_params["action"]) ? (int) $activity_params["action"] : 0;
        $record_id = !empty($activity_params["record_id"]) ? (int) $activity_params["record_id"] : 0;

        if ($user_id == 0 || $account_id == 0 || $action == 0 || $record_id == 0) {
            return;
        }
     
        switch ($action) {
            case SHAppComponent::getValue("actions/EMAILS/OPEN") :
                $this->addEmailOpen($activity_params);
                break;
            case SHAPPComponent::getValue("actions/EMAILS/SENT") :
                $this->addEmailSent($activity_params);
                break;
            case SHAPPComponent::getValue("actions/EMAILS/SCHEDULED") :
                $this->addEmailScheduled($activity_params);
                break;
            case SHAPPComponent::getValue("actions/EMAILS/CLICKED") :
                $this->addEmailClick($activity_params);
                break;
            case SHAppComponent::getValue("actions/CAMPAIGNS/OPEN") :
                if(!empty($activity_params["account_contact_id"])) {
                    $this->addCampaignOpen($activity_params);
                }
                break;
            case SHAppComponent::getValue("actions/CAMPAIGNS/CLICKED") :
                if(!empty($activity_params["account_contact_id"])) {
                    $this->addCampaignLinkTrack($activity_params);
                }
                break;
            case SHAppComponent::getValue("actions/DOCUMENT_LINKS/VIEWED") :
                $this->addDocumentVisit($activity_params);
                break;    
            default:
                break;
        }
        return;
    }
    /**
     * Function is used to insert email open activity
     *
     * @param $activity_params (array) : Parameters to identify activity record
     */
    private function addEmailOpen(&$activity_params) {
        $email_recipient_model = new EmailRecipients();

        $condition = [
            "fields" => [
                "er.account_contact_id",
                "ac.first_name",
                "ac.email"
            ],
            "where" => [
                ["where" => ["er.email_id", "=", $activity_params["record_id"] ]],
                ["where" => ["er.type", "=", SHAppComponent::getValue("app_constant/TO_EMAIL_RECIPIENTS")]],
		        ["where" => ["er.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]           
            ],
            "join" => [
                "account_contacts"
            ]
        ];

        try {
            //fetch all recipients
            $recipients_data = $email_recipient_model->fetchAll($condition);
            
            if (!empty($recipients_data)) {
                //get Location and other information
                $other_data = BrowserInformationComponent::getClientInformation();

                //save_data to activity
                $insert_data = [
                    "account_id" => $activity_params["account_id"],
                    "user_id"    => $activity_params["user_id"],
                    "record_id"  => $activity_params["record_id"],
                    "action_id"  => SHAppComponent::getValue("actions/EMAILS/OPEN"),
                    "action_group_id" => SHAPPComponent::getValue("actions/EMAILS/ACTION_GROUP_ID"),
                    "created"    => DateTimeComponent::getDateTime(),
                    "other_data" => json_encode($other_data)
                ];

                if(count($recipients_data) == 1) {
                    $insert_data["account_contact_id"] = $recipients_data[0]["account_contact_id"];
                }

                $this->insertActivity($insert_data); 

                $contacts = [];
                foreach($recipients_data as $recipient) {
                    $name = trim($recipient["first_name"]);
                    $name = empty($name) ? $recipient["email"] : $name;
                    $contacts[] = $name;
                }
                //Prepare text for display push notification
                $data = [
                    "action_id" => $activity_params["action"],
                    "entity" => empty($activity_params["subject"]) ? "(no subject)" : $activity_params["subject"],
                    "cname" => $contacts
                ];
                
                $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");

                if($activity_params["snooze"] == $flag_yes) {
                    $notification_data = [
                        "title" => self::NOTIFICATION_TITLE,
                        "message" => $this->prepareDisplayText($data),
                        "enc_user_id" => StringComponent::encodeRowId($activity_params["user_id"])
                    ];
                    PushNotificationComponent::sendPushNotification($notification_data);
                }
            }
        } catch (\Exception $e) {
            $this->prepareLog("Email Open Activity", $activity_params, $e->getMessage());
        }
    }
    /**
     * Function is used to insert email sent activity
     * 
     * @param $activity_params (array) : Parameters to identify activity record
     */
    private function addEmailSent(&$activity_params) {
        try {
            //save_data to activity
            $insert_data = [
                "account_id" => $activity_params["account_id"],
                "user_id"    => $activity_params["user_id"],
                "record_id"  => $activity_params["record_id"],
                "action_id"  => SHAppComponent::getValue("actions/EMAILS/SENT"),
                "action_group_id" => SHAPPComponent::getValue("actions/EMAILS/ACTION_GROUP_ID"),
                "created"    => DateTimeComponent::getDateTime()
            ];

            $this->insertActivity($insert_data);

        } catch (\Exception $e) {
            $this->prepareLog("Email Sent Activity", $activity_params, $e->getMessage());
        }
    }
    /**
     * Function is used to insert email scheduled activity
     * 
     * @param $activity_params (array) : Parameters to identify activity record
     */
    private function addEmailScheduled(&$activity_params) {
        try {
           //save_data to activity
            $insert_data = [
                "account_id" => $activity_params["account_id"],
                "user_id"    => $activity_params["user_id"],
                "record_id"  => $activity_params["record_id"],
                "action_id"  => SHAppComponent::getValue("actions/EMAILS/SCHEDULED"),
                "action_group_id" => SHAPPComponent::getValue("actions/EMAILS/ACTION_GROUP_ID"),
                "created"    => DateTimeComponent::getDateTime()
            ];

            $this->insertActivity($insert_data);

        } catch (\Exception $e) {
            $this->prepareLog("Email Scheduled Activity", $activity_params, $e->getMessage());
        }
    }
    /**
     * Function is used to insert email Link click activity
     * 
     * @param $activity_params (array) : Parameters to identify activity record
     */
    private function addEmailClick(&$activity_params) {
        $email_recipient_model = new EmailRecipients();

        $condition = [
            "fields" => [
                "er.account_contact_id",
                "ac.first_name",
                "ac.email"
            ],
            "where" => [
                ["where" => ["er.email_id", "=", $activity_params["record_id"] ]],
                ["where" => ["er.type", "=", SHAppComponent::getValue("app_constant/TO_EMAIL_RECIPIENTS")]],
                ["where" => ["er.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]           
            ],
            "join" => [
                "account_contacts"
            ]
        ];

        try {
            //fetch all recipients
            $recipients_data = $email_recipient_model->fetchAll($condition);
            
            if (!empty($recipients_data)) {
                //get Location and other information
                $other_data = BrowserInformationComponent::getClientInformation();

                //save_data to activity
                $insert_data = [
                    "account_id" => $activity_params["account_id"],
                    "user_id"    => $activity_params["user_id"],
                    "record_id"  => $activity_params["record_id"],
                    "sub_record_id" => $activity_params["sub_record_id"],
                    "action_id"  => SHAppComponent::getValue("actions/EMAILS/CLICKED"),
                    "action_group_id" => SHAPPComponent::getValue("actions/EMAILS/ACTION_GROUP_ID"),
                    "created"    => DateTimeComponent::getDateTime(),
                    "other_data" => json_encode($other_data)
                ];

                if(count($recipients_data) == 1) {
                    $insert_data["account_contact_id"] = $recipients_data[0]["account_contact_id"];
                }

                $this->insertActivity($insert_data); 

                $contacts = [];
                foreach($recipients_data as $recipient) {
                    $name = trim($recipient["first_name"]);
                    $name = empty($name) ? $recipient["email"] : $name;
                    $contacts[] = $name;
                }

                //Prepare text for display push notification
                $data = [
                    "action_id" => $activity_params["action"],
                    "entity" => empty($activity_params["subject"]) ? "(no subject)" : $activity_params["subject"],
                    "cname" => $contacts,
                    "link" => $activity_params["url"]
                ];

                $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");

                if($activity_params["snooze"] == $flag_yes) {
                    $notification_data = [
                        "title" => self::NOTIFICATION_TITLE,
                        "message" => $this->prepareDisplayText($data),
                        "enc_user_id" => StringComponent::encodeRowId($activity_params["user_id"])
                    ];
                    PushNotificationComponent::sendPushNotification($notification_data);
                }
            }
        } catch (\Exception $e) {
            $this->prepareLog("Email Link Track Activity", $activity_params, $e->getMessage()); 
        }
    }
    /**
     * Function is used to insert campaign email open activity
     * 
     * @param $activity_params (array) : Parameters to identify activity record
     */
    private function addCampaignOpen(&$activity_params) {
        try {
            //get Location and other information
            $other_data = BrowserInformationComponent::getClientInformation();

            //save_data to activity
            $insert_data = [
                "account_id" => $activity_params["account_id"],
                "user_id"    => $activity_params["user_id"],
                "record_id"  => $activity_params["record_id"],
                "account_contact_id" => $activity_params["account_contact_id"],
                "action_id"  => SHAppComponent::getValue("actions/CAMPAIGNS/OPEN"),
                "action_group_id" => SHAPPComponent::getValue("actions/CAMPAIGNS/ACTION_GROUP_ID"),
                "created"    => DateTimeComponent::getDateTime(),
                "other_data" => json_encode($other_data)
            ];

            $this->insertActivity($insert_data);

            //Prepare text for display push notification
            $data = [
                "action_id" => $activity_params["action"],
                "stage" => $activity_params["stage"],
                "entity" => $activity_params["campaign_title"],
                "cname" => $activity_params["cname"]
            ];

            $notification_data = [
                "title" => self::NOTIFICATION_TITLE,
                "message" => $this->prepareDisplayText($data),
                "enc_user_id" => StringComponent::encodeRowId($activity_params["user_id"])
            ];

            PushNotificationComponent::sendPushNotification($notification_data);
            
        } catch (\Exception $e) {
            $this->prepareLog("Campaign email open activity", $activity_params, $e->getMessage()); 
        }
    }
    /**
     * Function is used to insert campaign email link tracking activity
     * 
     * @param $activity_params (array) : Parameters to identify activity record
     */
    private function addCampaignLinkTrack(&$activity_params) {
        try {
            //get Location and other information
            $other_data = BrowserInformationComponent::getClientInformation();

            //save_data to activity
            $insert_data = [
                "account_id" => $activity_params["account_id"],
                "user_id"    => $activity_params["user_id"],
                "record_id"  => $activity_params["record_id"],
                "sub_record_id" => $activity_params["sub_record_id"],
                "account_contact_id" => $activity_params["account_contact_id"],
                "action_id"  => SHAppComponent::getValue("actions/CAMPAIGNS/CLICKED"),
                "action_group_id" => SHAPPComponent::getValue("actions/CAMPAIGNS/ACTION_GROUP_ID"),
                "created"    => DateTimeComponent::getDateTime(),
                "other_data" => json_encode($other_data)
            ];

            $this->insertActivity($insert_data);

            //Prepare text for display push notification
            $data = [
                "action_id" => $activity_params["action"],
                "stage" => $activity_params["stage"],
                "entity" => $activity_params["campaign_title"],
                "cname" => $activity_params["cname"],
                "link" => $activity_params["url"]
            ];

            $notification_data = [
                "title" => self::NOTIFICATION_TITLE,
                "message" => $this->prepareDisplayText($data),
                "enc_user_id" => StringComponent::encodeRowId($activity_params["user_id"])
            ];
            
            PushNotificationComponent::sendPushNotification($notification_data);

        } catch (\Exception $e) {
            $this->prepareLog("Campaign email link tracking activity", $activity_params, $e->getMessage()); 
        }
    }
    /**
     * Function is used to insert Document visit activity
     * 
     * @param $activity_params (array) : Parameters to identify activity record
     */
    private function addDocumentVisit(&$activity_params) {
        try {
            
            //save_data to activity
            $insert_data = [
                "account_id" => $activity_params["account_id"],
                "user_id"    => $activity_params["user_id"],
                "record_id"  => $activity_params["record_id"],
                "account_contact_id" => $activity_params["account_contact_id"],
                "action_id"  => SHAppComponent::getValue("actions/DOCUMENT_LINKS/VIEWED"),
                "action_group_id" => SHAPPComponent::getValue("actions/DOCUMENT_LINKS/ACTION_GROUP_ID"),
                "created"    => DateTimeComponent::getDateTime(),
                "other_data" => json_encode($activity_params["location_data"])
            ];

            $this->insertActivity($insert_data);
            
            //Prepare text for display push notification for document visit
            $data = [
                "action_id" => $activity_params["action"],
                "entity" => $activity_params["document_name"],
                "cname" => $activity_params["cname"]
            ];
            
            $notification_data = [
                "title" => self::NOTIFICATION_TITLE,
                "message" => $this->prepareDisplayText($data),
                "enc_user_id" => StringComponent::encodeRowId($activity_params["user_id"])
            ];
            
            PushNotificationComponent::sendPushNotification($notification_data);

        } catch (\Exception $e) {
            $this->prepareLog("Document visit activity", $activity_params, $e->getMessage());
        }
    }
    /**
     * Function is used to insert new activity
     * 
     * @param $inser_data (array) : data of add activity
     */
    private function insertActivity($insert_data) {

        $activity_model = new Activity();
        
        $params = [
            "account_id" => $insert_data["account_id"],
            "user_id"    => $insert_data["user_id"],
            "record_id"  => $insert_data["record_id"],
            "action_id"  => $insert_data["action_id"]
        ];

        try {
            $activity_model->save($insert_data);

        } catch(\Exception $e) {
            $this->prepareLog("Inside insert activity function", $params, $e->getMessage());
        }
    }
    /**
     * Function is used to prepare log
     */
    private function prepareLog($action_text, $params, $error) {
        $error_message  = $action_text;
        $error_message .= "\nActivity params : ". json_encode($params);
        $error_message .= "\nError : " . $error;
        $error_message .= "\n=================================================";

        LoggerComponent::log($error_message, self::LOG_FILE_PATH); 
    }
    /**
     * Function is used to prepare display text of activity.
     *
     * @param $data (array) : data of add activity
     *
     * @return $display_text : activity display text
     */
    public function prepareDisplayText($data) {
        $text_to_display = "";

        $action_id = !empty($data["action_id"]) ? (int) $data["action_id"] : "";
        $entity = !empty($data["entity"]) ? trim($data["entity"]) : "";
        $cname = !empty($data["cname"]) ? $data["cname"] : "";
        
        if ($action_id == "" || $entity == "" || $cname == "") {
            return;
        }
        
        $activity_template = SHAppComponent::getValue("activity_template/" . $action_id);
        if (!empty($activity_template)) {

            if (is_array($data["cname"])) {
                $total_contacts = count($data["cname"]);
                if ($total_contacts <= 2) {
                    $cname = $total_contacts == 1 ? "" : "Someone from ";
                    $cname .= implode(", ", $data["cname"]);
                } else {
                    $cname = "Someone from " . $data["cname"][0] . ", " . $data["cname"][1] . " and ";
                    $cname .= ($total_contacts - 2) == 1 ? $total_contacts - 2 . " Other" : ($total_contacts - 2) . " Others";
                }
            } else {
                $cname = $data["cname"];
            }
            $activity_template = str_replace("{contact}", $cname, $activity_template);
            $activity_template = str_replace("{entity}", $data["entity"], $activity_template);
            
            if (!empty($data["stage"])) {
                $activity_template = str_replace("{stage}", $data["stage"], $activity_template); 
            }
            if (!empty($data["link"])) {
                $url = preg_replace("(^https?://)", "", $data["link"]);
                $activity_template = str_replace("{f_link}", $data["link"], $activity_template);
                $activity_template = str_replace("{link}", $url, $activity_template);   
            }
            if ($action_id == SHAPPComponent::getValue("actions/EMAILS/SENT") || $action_id == SHAPPComponent::getValue("actions/EMAILS/SCHEDULED")) {
                $activity_template = str_replace("Someone from ", "", $activity_template);
            }
            $text_to_display = $activity_template;
        }  
        return $text_to_display;
    }
}
