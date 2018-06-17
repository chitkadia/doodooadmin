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

class DocumentViewer extends AppViewer {

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
    public function view(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = "";
        $output_template = __DIR__ . "/../../docs/type/single.html";
        $http_status = 200;

        // Get request parameters
        $route = $request->getAttribute("route");
        $code = $route->getArgument("code");
        $file_code = $route->getArgument("file_code");

        $code_info = StringComponent::decodeRowId($code, true);
        $row_id = isset($code_info[0]) ? (int) $code_info[0] : 0;
        $user_id = isset($code_info[1]) ? (int) $code_info[1] : 0;
        $account_id = isset($code_info[2]) ? (int) $code_info[2] : 0;

        $document_id = StringComponent::decodeRowId($file_code);
        $contact_id = null;
        $visit_id = null;

        $status_active = \App\Components\SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
        $flag_yes = \App\Components\SHAppComponent::getValue("app_constant/FLAG_YES");
        $document_viewer_view_slug = \App\Components\SHAppComponent::getValue("app_constant/DOC_VIEWER_VIEW");
        $document_viewer_preview_slug = \App\Components\SHAppComponent::getValue("app_constant/DOC_VIEWER_PREVIEW");

        $add_visit_entry = false;
        $is_multi_document = false;
        $is_preview = false;

        // Check if viewer is opened in preview mode
        $preview_mode = $request->getAttribute("preview");
        $mode = $document_viewer_view_slug;
        if (!empty($preview_mode)) {
            $is_preview = true;
            $mode = $document_viewer_preview_slug;
        }

        try {
            // Get link data
            $model_document_links = new DocumentLinks();

            $condition = [
                "fields" => [
                    "dl.name",
                    "dl.account_contact_id",
                    "dl.is_set_expiration_date",
                    "dl.expires_at",
                    "dl.forward_tracking",
                    "dl.email_me_when_viewed",
                    "dl.allow_download",
                    "dl.password_protected",
                    "dl.ask_visitor_info",
                    "dl.visitor_info_payload",
                    "dl.visitor_slug",
                    "um.first_name",
                    "um.last_name",
                    "um.email"
                ],
                "where" => [
                    ["where" => ["dl.id", "=", $row_id]],
                    ["where" => ["dl.account_id", "=", $account_id]],
                    ["where" => ["dl.user_id", "=", $user_id]],
                    ["where" => ["dl.status", "=", $status_active]]
                ],
                "join" => [
                    "user_master"
                ]
            ];
            $record_data = $model_document_links->fetch($condition);

            if (empty($record_data)) {
                return self::showPageNotFound($response);
            }

            // Get link documents
            $model_document_link_files = new DocumentLinkFiles();

            $condition = [
                "fields" => [
                    "dm.id",
                    "dm.file_name",
                    "dm.file_path",
                    "dm.bucket_path",
                    "dm.file_type",
                    "dm.file_pages"
                ],
                "where" => [
                    ["where" => ["dlf.document_link_id", "=", $row_id]],
                    ["where" => ["dm.status", "=", $status_active]],
                    ["where" => ["dlf.status", "=", $status_active]]
                ],
                "join" => [
                    "document_master"
                ]
            ];
            $link_documents = $model_document_link_files->fetchAll($condition);

            $document_list_ids = [];
            foreach ($link_documents as $dockey => $doc) {
                $document_list_ids[$doc["id"]] = \DOC_CLOUDFRONT_URL . $doc["bucket_path"];

                $link_documents[$dockey]["preview_image"] = "/docimg.png";
                $link_documents[$dockey]["document_link"] = "/" . $mode . "/" . $code . "/d/" . StringComponent::encodeRowId($doc["id"]);
            }

            // Check if valid document is being accessed
            if (!empty($document_id) && !isset($document_list_ids[$document_id])) {
                return self::showPageNotFound($response);
            }

            // CHeck if there is any document available or not
            if (empty($link_documents)) {
                return self::showPageNotFound($response);
            }

            // Check if link has been expired
            if ($record_data["is_set_expiration_date"] == $flag_yes) {
                $expiry_datetime = (int) $record_data["expires_at"];
                $current_time = DateTimeComponent::getDateTime();

                if ($current_time > $expiry_datetime) {
                    return self::showPageNotFound($response);
                }
            }

            // If we've no documents or it's a multi document link, then render multi page
            if ((empty($link_documents) || count($link_documents) > 1) && empty($document_id)) {
                $output_template = __DIR__ . "/../../docs/type/multi.html";

            } else if (!empty($link_documents) && count($link_documents) == 1 && empty($document_id)) {
                $document_id = $link_documents[0]["id"];
            }

        } catch (\Exception $e) {
            // Send 500 internal server status code
            return self::showErrorPage($response);
        }

        // Check if it's an multi document link or not?
        if (count($link_documents) > 1) {
            $is_multi_document = true;
        }

        // Check if it has been visited by the user or not
        $visitor_slug_present = $request->getCookieParam($record_data["visitor_slug"]);
        $fields_to_display = json_decode($record_data["visitor_info_payload"], true);

        // Check if ask visitor information is enabled
        if (($record_data["ask_visitor_info"] == $flag_yes || $record_data["password_protected"] == $flag_yes) && !empty($fields_to_display)) {
            if (empty($visitor_slug_present)) {
                $show_email = false;
                $show_name = false;
                $show_company = false;
                $show_phone = false;
                $show_password = false;

                if ($record_data["ask_visitor_info"] == $flag_yes) {
                    $fields_to_display = json_decode($record_data["visitor_info_payload"], true);
                    
                    $show_email = (in_array("email", $fields_to_display)) ? true : false;
                    $show_name = (in_array("name", $fields_to_display)) ? true : false;
                    $show_company = (in_array("company", $fields_to_display)) ? true : false;
                    $show_phone = (in_array("contact", $fields_to_display)) ? true : false;
                }
                if ($record_data["password_protected"] == $flag_yes) {
                    $show_password = true;
                }

                $output_template = __DIR__ . "/../../docs/type/ask_info.html";

            } else {
                // Add visit entry
                $add_visit_entry = true;

                $visitor_contact_id = StringComponent::decodeRowId($visitor_slug_present);
                if (!empty($visitor_contact_id)) {
                    $contact_id = (int) $visitor_contact_id;
                }
            }

        } else {
            // Set contact id to the prospect with whome it was shared
            $contact_id = $record_data["account_contact_id"];

            // Add visit entry
            $add_visit_entry = true;
        }

        // Check if previously visited
        $visitor_visit_id = $request->getCookieParam($code);
        if (!empty($visitor_visit_id)) {
            $visit_id = StringComponent::decodeRowId($visitor_visit_id);

            if (!empty($visit_id)) {
                $add_visit_entry = false;

            } else {
                $visit_id = null;
                $add_visit_entry = true;
            }
        }

        if ($add_visit_entry && !$is_preview) {
            // Add visit entry
            try {
                // Get location data
                $location_data = BrowserInformationComponent::getClientInformation();

                $model_document_link_visit = new DocumentLinkVisits();

                $save_data = [
                    "document_link_id" => $row_id,
                    "account_contact_id" => $contact_id,
                    "location" => json_encode($location_data),
                    "modified" => DateTimeComponent::getDateTime()
                ];
                if ($model_document_link_visit->save($save_data) !== false) {
                    $visit_id = $model_document_link_visit->getLastInsertId();

                    // Update who opened the link last
                    $update_data = [
                        "id" => $row_id,
                        "last_opened_by" => $contact_id,
                        "last_opened_at" => DateTimeComponent::getDateTime()
                    ];
                    $update = $model_document_links->save($update_data);

                    // add activity for document visit
                    $sh_activity = new SHActivity();
                    
                    $cname = \SOMEONE_TEXT;
                    if (!empty($contact_id)) {
                        $model_account_contacts = new AccountContacts();

                        $other_params = [
                            "deleted" => \App\Components\SHAppComponent::getValue("app_constant/STATUS_DELETE")
                        ];
                        $contact_data = $model_account_contacts->getConatctDataById($contact_id, $other_params);

                        if (!empty($contact_data)) {
                            $cname = trim($contact_data["first_name"]);
                            $cname = empty($cname) ? $contact_data["email"] : $cname;
                        }
                    }

                    $activity_params = [
                        "user_id" => $user_id,
                        "account_id" => $account_id,
                        "account_contact_id" => $contact_id,
                        "action" => \App\Components\SHAppComponent::getValue("actions/DOCUMENT_LINKS/VIEWED"),
                        "record_id" => $row_id,
                        "location_data" => $location_data,
                        "cname" => $cname,
                        "document_name" => $record_data["name"]
                    ];
                    $sh_activity->addActivity($activity_params);

                } else {
                    // Send 500 internal server status code
                    return self::showErrorPage($response);
                }

            } catch (\Exception $e) {
                // Send 500 internal server status code
                return self::showErrorPage($response);
            }
        }

        $page_title = $record_data["name"];
        $document_url = "";
        if (!empty($document_list_ids[$document_id])) {
            $document_url = $document_list_ids[$document_id];
        }
        $a = StringComponent::encodeRowId($row_id); // Link Id
        $b = StringComponent::encodeRowId($document_id); // Document Id
        $c = $record_data["visitor_slug"]; // Cookie token
        $d = StringComponent::encodeRowId($visit_id); // Visit Id
        $e = StringComponent::encodeRowId($contact_id); // Contact Id
        $f = $code; // URL Slug
        $m = (int) $is_preview; // Preview or not
        $t = \DOC_VIEWER_VISIT_TIMEOUT;

        $header_template = __DIR__ . "/../../docs/type/viewer-header.html";
        $viewer_header_content = file_get_contents($header_template);
        $viewer_header_content = str_replace("{{Name}}", $page_title, $viewer_header_content);

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
