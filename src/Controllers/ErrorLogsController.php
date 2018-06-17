<?php
/**
 * Error logs
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
use \App\Models\ErrorLogs;

class ErrorLogsController extends AppController {

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Store Errors
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function storeLogs(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $logged_in_source = SHAppComponent::getRequestSource();
       
        // Get request parameters
        $request_params = $request->getParsedBody();

        $model_error_logs = new ErrorLogs();           

        if (isset($request_params["error_payload"])) {
            $request_data["error_object"] = $request_params["error_payload"];      
        }
        
        // Store Error Logs in db
        try {
            
            $error_detail = "";
            if (!empty($request_data["error_object"])) {
                $error_detail = json_encode($request_data["error_object"]);
            }

            $save_data = [
                "account_id"   => $logged_in_account,
                "user_id"      => $logged_in_user,
                "source_id"    => $logged_in_source,
                "error"        => $error_detail,
                "occurred_at"  => DateTimeComponent::getDateTime()
            ];
            
            $saved = $model_error_logs->save($save_data);
            
            $output["message"] = "Data saved successfully.";

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 201);
    }
    
}