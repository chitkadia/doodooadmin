<?php
/**
 * Used to reset Quota of mail account as per user plan
 */
namespace App\Commands;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Interop\Container\ContainerInterface;
use \App\Components\DateTimeComponent;
use \App\Components\SHAppComponent;
use \App\Components\LoggerComponent;
use \App\Models\AccountSendingMethods;

class ResetEmailLimitCommand extends AppCommand {

    //define constant for reset quota cron execute limt 
    const RESET_QUOTA_PROCESS_LIMIT_TIME = 10;

    //define constant for document conversion and upload log file 
    const LOG_FILE_QUOTA_RESET_PROCESS =  __DIR__ . "/../../logs/reset_quota_process.log";

    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Reset Quota of mail account
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $args) {        
        $current_time = DateTimeComponent::getDateTime();
    
        $end_time = $current_time + self::RESET_QUOTA_PROCESS_LIMIT_TIME;
        $num_records_processed = 0;

        $reminder = (int) $_SERVER["argv"][2];
        $modulo = (int) $_SERVER["argv"][3];

        LoggerComponent::log("Reset quota process start", self::LOG_FILE_QUOTA_RESET_PROCESS);

        $model_account_sending_methods = new AccountSendingMethods();

        while ($current_time <= $end_time) {

            $condition = [
                "fields" => [
                    "asm.id",
                    "asm.total_limit",
                    "abm.plan_id"
                ],
                "where" => [
                    ["where" => ["asm.next_reset", "<=", $current_time ]],
                    ["where" => ["asm.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE") ]]
                ],
                "join" => [
                    "account_billing_master"
                ],
                "limit" => 1 
            ];
          
            if ( !empty($modulo) && !is_null($reminder) ) {
                $condition["where"][] = ["where" => ["asm.id%" . $modulo, "=", $reminder ]]; 
            }

            try {
                $row_data = $model_account_sending_methods->fetch($condition);

            } catch(\Exception $e) {
                LoggerComponent::log("Database error: " . $e->getMessage(), self::LOG_FILE_QUOTA_RESET_PROCESS);
            }

            if (!empty($row_data["id"])) {

                LoggerComponent::log("Start process of #" .$row_data["id"], self::LOG_FILE_QUOTA_RESET_PROCESS);
               
                try {
                    
                    $save = $model_account_sending_methods->setQuota($row_data["plan_id"], $row_data["id"]);

                    if ($save) {
                        LoggerComponent::log("Quota reset of #" . $row_data["id"] . " is successfully", self::LOG_FILE_QUOTA_RESET_PROCESS);
                    } else {
                        LoggerComponent::log("Quota not reset #" . $row_data["id"] . " Error :" . $model_account_sending_methods->getQueryError(), self::LOG_FILE_QUOTA_RESET_PROCESS);
                    }

                } catch(\Exception $e) {
                    LoggerComponent::log("Database error of #" . $row_data["id"] . " Error :" . $e->getMessage(), self::LOG_FILE_QUOTA_RESET_PROCESS);
                }

                LoggerComponent::log("Process Finished for Reset Quota of #". $row_data["id"], self::LOG_FILE_QUOTA_RESET_PROCESS);
         
            } else {
                LoggerComponent::log("Record not found to be processing", self::LOG_FILE_QUOTA_RESET_PROCESS);
                break;
            }

            $current_time = DateTimeComponent::getDateTime();
            $num_records_processed++;
        }

        LoggerComponent::log("Total ".$num_records_processed." records to be proceed", self::LOG_FILE_QUOTA_RESET_PROCESS);
        LoggerComponent::log("===================================================", self::LOG_FILE_QUOTA_RESET_PROCESS);
       
    }

}