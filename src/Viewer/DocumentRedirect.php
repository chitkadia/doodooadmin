<?php
/**
 * Document viewer related functionality
 */
namespace App\Viewer;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Interop\Container\ContainerInterface;
use \App\Components\StringComponent;
use \App\Components\DateTimeComponent;
use \App\Components\BrowserInformationComponent;
use \App\Components\SHActivity;
use \App\Models\DocumentLinks;
use \App\Models\DocumentLinkFiles;
use \App\Models\DocumentLinkVisits;
use \App\Models\AccountContacts;

class DocumentRedirect extends AppViewer {

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Landing page for document viewer
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response, $args) {
        return self::showPageNotFound($response);
    }

    /**
     * Render the link page (Can be single document or multiple document listing)
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function viewDocument(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = "";
        $output_template = __DIR__ . "/../../docs/type/single.html";
        $http_status = 200;
        $status_active = \App\Components\SHAppComponent::getValue("app_constant/STATUS_ACTIVE");        
        // Get request parameters
        $route = $request->getAttribute("route");
        $link = $route->getArgument("link");
 
        try {
            // Get link data
            $model_document_links = new DocumentLinks();

            $condition = [
                "fields" => [
                    "dl.link_code"
                ],
                "where" => [
                    ["where" => ["dl.old_link", "=", $link]],
                    ["where" => ["dl.status", "=", $status_active]]
                ],
                "join" => [
                    "user_master"
                ]
            ];
            $record_data = $model_document_links->fetch($condition);
           
            if (empty($record_data) && empty($record_data["link_code"])) {
                return self::showPageNotFound($response);
            }
 
            header("Location: http://shdocs.cultofpassion.com/view/".$record_data["link_code"]);       
      

        } catch (\Exception $e) {
            // Send 500 internal server status code
            return self::showErrorPage($response);
        }
    }

    /**
     * Render the link page (Can be single document or multiple document listing)
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function viewFilePerformance(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = "";
        $output_template = __DIR__ . "/../../docs/type/single.html";
        $http_status = 200;
        $status_active = \App\Components\SHAppComponent::getValue("app_constant/STATUS_ACTIVE");        
        // Get request parameters
        $route = $request->getAttribute("route");
        $link = $route->getArgument("link");
       
        try {
            // Get link data
            $model_document_links = new DocumentLinks();

            $condition = [
                "fields" => [
                    "dl.id",
                    "dlf.document_id"
                ],
                "where" => [
                    ["where" => ["dl.old_link", "=", $link]],
                    ["where" => ["dl.status", "=", $status_active]]
                ],
                "join" => [
                    "document_link_files"
                ]
            ];
            $record_data = $model_document_links->fetch($condition);
            
            $document_id = StringComponent::encodeRowId($record_data["document_id"]);
            
            if (empty($record_data) && empty($record_data["link_code"])) {
                $redirect_url = \DEFAULT_API_ENDPOINT."/documents/".$link."/space/performance";
                header("Location: ".$redirect_url); 
            }
            $redirect_url = \DEFAULT_API_ENDPOINT."/documents/".$document_id."/space/performance";
 
            header("Location: ".$redirect_url);       
      

        } catch (\Exception $e) {
            // Send 500 internal server status code
            return self::showErrorPage($response);
        }
    }


     /**
     * Render the link page (Can be single document or multiple document listing)
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function viewFileLinkPerformance(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = "";
        $output_template = __DIR__ . "/../../docs/type/single.html";
        $http_status = 200;
        $status_active = \App\Components\SHAppComponent::getValue("app_constant/STATUS_ACTIVE");        
        // Get request parameters
        $route = $request->getAttribute("route");
        $link = $route->getArgument("link");
       
        try {
            // Get link data
            $model_document_links = new DocumentLinks();

            $condition = [
                "fields" => [
                    "dl.id"
                ],
                "where" => [
                    ["where" => ["dl.old_link", "=", $link]],
                    ["where" => ["dl.status", "=", $status_active]]
                ]
            ];
            $record_data = $model_document_links->fetch($condition);
 
            $document_id = StringComponent::encodeRowId($record_data["id"]);
            
  
            if (empty($record_data) && empty($record_data["link_code"])) {
                $redirect_url = \DEFAULT_API_ENDPOINT."/documents/".$link."/space/performance";
                header("Location: ".$redirect_url); 
            }
            $redirect_url = \DEFAULT_API_ENDPOINT."/documents_space/".$document_id."/performance";
            header("Location: ".$redirect_url);       
      

        } catch (\Exception $e) {
            // Send 500 internal server status code
            return self::showErrorPage($response);
        }
    }

    /**
     * Output error response
     *
     * @param $response (object): Response object
     *
     * @return (object) Response object
     */
    public static function showErrorPage(ResponseInterface &$response) {
        $output = "";
        $output_template = __DIR__ . "/../../docs/type/500.html";
        $http_status = 500;

        $page_title = "Oops! Something went wrong";
        $page_heading = "Err!";
        $page_description = "Oops! Something went wrong. Please try again later.";
        $home_page_url = \SH_WEBSITE_URL;
        $viewer_header_content = "";

        try {
            // Start output buffer
            ob_start();

            // Insert template
            include $output_template;

            // Flush buffer and store output
            $output = ob_get_clean();

        } catch (\Exception $e) {
            // Send 500 internal server status code
            $http_status = 500;
        }

        // Send output
        $response->getBody()->write($output);
        return $response->withStatus($http_status);
    }

    /**
     * Output page not found response
     *
     * @param $response (object): Response object
     *
     * @return (object) Response object
     */
    public static function showPageNotFound(ResponseInterface &$response) {
        $output = "";
        $output_template = __DIR__ . "/../../docs/type/404.html";
        $http_status = 404;

        $page_title = "Page not found";
        $page_heading = "404";
        $page_description = "Page you're trying to access was not found.";
        $home_page_url = \SH_WEBSITE_URL;
        $viewer_header_content = "";

        try {
            // Start output buffer
            ob_start();

            // Insert template
            include $output_template;

            // Flush buffer and store output
            $output = ob_get_clean();

        } catch (\Exception $e) {
            // Send 500 internal server status code
            return self::showErrorPage($response);
        }

        // Send output
        $response->getBody()->write($output);
        return $response->withStatus($http_status);
    }

}
