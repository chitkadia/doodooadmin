<?php
/**
 * Branding related functionality
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
use \App\Models\Branding;
use \App\Models\AccountOrganization;

class BrandingController extends AppController {

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Get branding details
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getDetails(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();

        // Check if branding is already set or not
        $model_branding = new Branding();

        try {
            $condition = [
                "fields" => [
                    "b.id",
                    "b.subdomain",
                    "b.custom_url",
                    "b.branding_payload",
                    "b.enabled"
                ],
                "where" => [
                    ["where" => ["b.account_id", "=", $logged_in_account]]
                ]
            ];
            $row = $model_branding->fetch($condition);

            // If branding configuration is not set yet
            if (empty($row)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/BRANDING_NOT_SET");
            }

            // Get branding information
            $output["subdomain"] = $row["subdomain"];
            $output["custom_url"] = $row["custom_url"];
            $output["enabled"] = (bool) $row["enabled"];

            $branding_payload = json_decode($row["branding_payload"], true);
            $output["bgcolor"] = !empty($branding_payload["bgcolor"]) ? $branding_payload["bgcolor"] : null;
            $output["fgcolor"] = !empty($branding_payload["fgcolor"]) ? $branding_payload["fgcolor"] : null;
            $output["use_logo_from_org"] = !empty($branding_payload["use_logo_from_org"]) ? $branding_payload["use_logo_from_org"] : null;
            $output["logo"] = !empty($branding_payload["logo"]) ? $branding_payload["logo"] : null;
            $output["use_address_from_org"] = !empty($branding_payload["use_address_from_org"]) ? $branding_payload["use_address_from_org"] : null;
            $output["address"] = !empty($branding_payload["address"]) ? $branding_payload["address"] : null;
            $output["use_website_from_org"] = !empty($branding_payload["use_website_from_org"]) ? $branding_payload["use_website_from_org"] : null;
            $output["website"] = !empty($branding_payload["website"]) ? $branding_payload["website"] : null;

            if ($output["use_logo_from_org"] || $output["use_address_from_org"] || $output["use_website_from_org"]) {
                // Get inherited settings from organization
                $model_account_organization = new AccountOrganization();

                $condition = [
                    "fields" => [
                        "ao.address",
                        "ao.city",
                        "ao.state",
                        "ao.country",
                        "ao.zipcode",
                        "ao.logo",
                        "ao.website",
                        "ao.contact_phone",
                        "ao.contact_fax"
                    ],
                    "where" => [
                        ["where" => ["ao.account_id", "=", $logged_in_account]]
                    ]
                ];
                $data = $model_account_organization->fetch($condition);

                if ($output["use_logo_from_org"] && !empty($data["logo"])) {
                    $output["logo"] = $data["logo"];
                }
                if ($output["use_website_from_org"] && !empty($data["website"])) {
                    $output["website"] = $data["website"];
                }
                if ($output["use_address_from_org"]) {
                    $address = "";
                    $address .= !empty($data["address"]) ? $data["address"]."\n" : "";

                    if (!empty($data["city"]) && !empty($data["state"])) {
                        $address .= $data["city"] . "," . $data["state"] . "\n";
                    } else if (!empty($data["city"])) {
                        $address .= $data["city"] . "\n";
                    } else if (!empty($data["state"])) {
                        $address .= $data["state"] . "\n";
                    }

                    if (!empty($data["country"]) && !empty($data["zipcode"])) {
                        $address .= $data["country"] . " " . $data["zipcode"] . "\n";
                    } else if (!empty($data["country"])) {
                        $address .= $data["country"] . "\n";
                    } else if (!empty($data["zipcode"])) {
                        $address .= $data["zipcode"] . "\n";
                    }

                    $address .= !empty($data["contact_phone"]) ? "Phone: ". $data["contact_phone"]."\n" : "";
                    $address .= !empty($data["contact_fax"]) ? "Fax: " . $data["contact_fax"]."\n" : "";
                    $output["address"] = $address;
                }
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * Update brand details
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [
            "subdomain" => "",
            "bgcolor" => "",
            "fgcolor" => "",
            "use_logo_from_org" => false,
            "logo" => "",
            "use_address_from_org" => false,
            "address" => "",
            "use_website_from_org" => false,
            "website" => ""
        ];
        
        if (isset($request_params["subdomain"])) {
            $request_data["subdomain"] = $request_params["subdomain"];
        }
        if (isset($request_params["bgcolor"])) {
            $request_data["bgcolor"] = $request_params["bgcolor"];
        }
        if (isset($request_params["fgcolor"])) {
            $request_data["fgcolor"] = $request_params["fgcolor"];
        }
        if (isset($request_params["use_logo_from_org"])) {
            $request_data["use_logo_from_org"] = (int) $request_params["use_logo_from_org"];
        }
        if (isset($request_params["use_address_from_org"])) {
            $request_data["use_address_from_org"] = (int) $request_params["use_address_from_org"];
        }
        if (isset($request_params["use_website_from_org"])) {
            $request_data["use_website_from_org"] = (int) $request_params["use_website_from_org"];
        }

        if (empty($request_data["use_logo_from_org"]) && isset($request_params["logo"])) {
            $request_data["logo"] = $request_params["logo"];
        }
        if (empty($request_data["use_address_from_org"]) && isset($request_params["address"])) {
            $request_data["address"] = $request_params["address"];
        }
        if (empty($request_data["use_website_from_org"]) && isset($request_params["website"])) {
            $request_data["website"] = $request_params["website"];
        }

        $branding_payload = [
            "bgcolor" => trim($request_data["bgcolor"]),
            "fgcolor" => trim($request_data["fgcolor"]),
            "use_logo_from_org" => $request_data["use_logo_from_org"],
            "logo" => trim($request_data["logo"]),
            "use_address_from_org" => $request_data["use_address_from_org"],
            "address" => trim($request_data["address"]),
            "use_website_from_org" => $request_data["use_website_from_org"],
            "website" => trim($request_data["website"])
        ];

        // Save record
        $model_branding = new Branding();

        try {
            // Check if branding is already created or not
            $condition = [
                "fields" => [
                    "b.id"
                ],
                "where" => [
                    ["where" => ["b.account_id", "=", $logged_in_account]]
                ]
            ];
            $row = $model_branding->fetch($condition);

            $save_data = [
                "account_id" => $logged_in_account,
                "subdomain" => trim($request_data["subdomain"]),
                "custom_url" => "",
                "branding_payload" => json_encode($branding_payload),
                "modified" => DateTimeComponent::getDateTime()
            ];
            if (!empty($row["id"])) {
                $save_data["id"] = $row["id"];
            }

            if ($model_branding->save($save_data) !== false) {
                $output["message"] = "Branding updated successfully.";

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * Enable / Disable branding
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function statusUpdate(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        $logged_in_account = SHAppComponent::getAccountId();
        $is_owner = SHAppComponent::isAccountOwner();
        $logged_in_source = SHAppComponent::getRequestSource();

        // Check if branding is already set or not
        $model_branding = new Branding();

        try {
            $condition = [
                "fields" => [
                    "b.id",
                    "b.enabled"
                ],
                "where" => [
                    ["where" => ["b.account_id", "=", $logged_in_account]]
                ]
            ];
            $row = $model_branding->fetch($condition);

            // If branding configuration is not set yet
            if (empty($row)) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/BRANDING_NOT_SET");
            }

            // Check which flag to set
            $flag_yes = SHAppComponent::getValue("app_constant/FLAG_YES");
            $flag_no = SHAppComponent::getValue("app_constant/FLAG_NO");

            if ($row["enabled"] == $flag_yes) {
                $flag_enabled = $flag_no;
                $output["message"] = "Branding disabled successfully.";

            } else {
                $flag_enabled = $flag_yes;
                $output["message"] = "Branding enabled successfully.";
            }

            // Update branding status
            $save_data = [
                "id" => $row["id"],
                "enabled" => $flag_enabled,
                "modified" => DateTimeComponent::getDateTime()
            ];
            if ($model_branding->save($save_data) === false) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

}