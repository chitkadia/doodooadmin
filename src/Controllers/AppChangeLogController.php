<?php
/**
 * Login related functionality
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
use \App\Models\AppChangeLog;

class AppChangeLogController extends AppController {

/**
 * Constructor
 */
public function __construct(ContainerInterface $container) {
    parent::__construct($container);
}


    /**
     * Get Outlook Latest Package information
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getLatestPackage(ServerRequestInterface $request, ResponseInterface $response, $args) {
        try{
            
            $request_params = $request->getParsedBody();
            $output = [
                "is_update_available" => false,
            ];
            // Fetch request data
            $request_data = [
                "package_version" => ""
            ];

            if (isset($request_params["package_version"])) {
                $request_data["package_version"] = $request_params["package_version"];
            }
          
            $model_app_change_log = new AppChangeLog();
            $package_id = 0;
            try {
                $package_id = $model_app_change_log->getPackageIdFromVersion($request_data["package_version"]);               
              
            } catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
           
            $payload = [
                "source" => SHAppComponent::getValue("source/OUTLOOK_PLUGIN"),
                "status" => SHAppComponent::getValue("app_constant/STATUS_ACTIVE")  
            ];            
        
            $row_details = $model_app_change_log->getPackageUpdateDetails($package_id, $payload);
            
            if(!empty($row_details)){
                $output = ["is_update_available"    => true,
                           "is_critical"            => $row_details["critical"]? true : false,
                           "is_logout_required"     => $row_details["logout_required"]? true : false,
                           "version"                => $row_details["release_name"],
                           "download_path"          => $row_details["package_path"]
                          ];
            }
         
            return $response->withJson($output, 200);
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
    } 


}
