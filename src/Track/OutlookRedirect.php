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
use App\Components\Mailer\TransactionMailsComponent;
use \App\Models\UserMaster;

class OutlookRedirect extends AppTracker {

    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $args) {
      
        $route = $request->getAttribute("route");
        $request_params = $route->getArguments();
        $uri_path = $request->getUri()->getPath();
           
        $id = 0;
        if(isset($request_params["id"])){
            $id = $request_params["id"];
        }

        if($id !=0){
            $this->sendEmail($id);
        }
       
        header('HTTP/1.1 500 Internal Server Error');
        exit;
    } 
    
    public function sendEmail($id) {

        try{

            $model_user_master = new UserMaster();
            $condition = [
                "fields" => [
                    "email"
                ],
                "where" => [
                    ["where" => ["id", "=", $id]]
                ]
            ];
          
            $row_user = $model_user_master->fetch($condition);
            if(isset($row_user["email"])){

                $subject = "Update SalesHandy 2.0";
                $body = "Please check our new version";

                $info["smtp_details"]["host"] = HOST;
                $info["smtp_details"]["port"] = PORT;
                $info["smtp_details"]["encryption"] = ENCRYPTION;
                $info["smtp_details"]["username"] = USERNAME;
                $info["smtp_details"]["password"] = PASSWORD;

                $info["from_email"] = FROM_EMAIL;
                $info["from_name"] = FROM_NAME;

                $info["to"] = $row_user["email"];
                $info["cc"] = '';
                $info["bcc"] = '';
                $info["subject"] = $subject;
                $info["content"] = $body;
                
                $result = TransactionMailsComponent::mailSendSmtp($info); 
            }
            
        } catch(Exception $e) {
        }
        
    }
        
}