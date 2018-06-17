<?php
/**
 * Account organisation related functionality
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
use \App\Components\ImageUploaderComponent;
use \App\Models\AccountOrganization;

class AccountController extends AppController {

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * Get organisation details
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

        // Get data
        $model_account_organization = new AccountOrganization();

        try {
            $condition = [
                "fields" => [
                    "ao.name",
                    "ao.address",
                    "ao.city",
                    "ao.state",
                    "ao.country",
                    "ao.zipcode",
                    "ao.logo",
                    "ao.website",
                    "ao.contact_phone",
                    "ao.contact_fax",
                    "ao.short_info"
                ],
                "where" => [
                    ["where" => ["ao.account_id", "=", $logged_in_account]]
                ]
            ];
            $data = $model_account_organization->fetch($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if (!empty($data)) {
            $output["name"] = $data["name"];
            $output["address"] = $data["address"];
            $output["city"] = $data["city"];
            $output["state"] = $data["state"];
            $output["country"] = $data["country"];
            $output["zipcode"] = $data["zipcode"];
            $output["logo"] = $data["logo"];
            $output["website"] = $data["website"];
            $output["contact_phone"] = $data["contact_phone"];
            $output["contact_fax"] = $data["contact_fax"];
            $output["short_info"] = $data["short_info"];

            if (!empty($output["logo"])) {
                $output["logo"] = \URL_TO_MEDIA . \MEDIA_ORG_LOGOS . "/" . $output["logo"];
            }

        } else {
            $output["message"] = "Organisation profile is not setup yet.";
        }

        return $response->withJson($output, 200);
    }

    /**
     * Update organisation details
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

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [
            "address" => "",
            "city" => "",
            "state" => "",
            "country" => "",
            "zipcode" => "",
            "logo" => "",
            "website" => "",
            "contact_phone" => "",
            "contact_fax" => "",
            "short_info" => ""
        ];
        
        if (isset($request_params["name"])) {
            $request_data["name"] = trim($request_params["name"]);
        }
        if (isset($request_params["address"])) {
            $request_data["address"] = trim($request_params["address"]);
        }
        if (isset($request_params["city"])) {
            $request_data["city"] = trim($request_params["city"]);
        }
        if (isset($request_params["state"])) {
            $request_data["state"] = trim($request_params["state"]);
        }
        if (isset($request_params["country"])) {
            $request_data["country"] = trim($request_params["country"]);
        }
        if (isset($request_params["zipcode"])) {
            $request_data["zipcode"] = trim($request_params["zipcode"]);
        }
        if (isset($request_params["logo"])) {
            $request_data["logo"] = trim($request_params["logo"]);
        }
        if (isset($request_params["website"])) {
            $request_data["website"] = trim($request_params["website"]);
        }
        if (isset($request_params["contact_phone"])) {
            $request_data["contact_phone"] = trim($request_params["contact_phone"]);
        }
        if (isset($request_params["contact_fax"])) {
            $request_data["contact_fax"] = trim($request_params["contact_fax"]);
        }
        if (isset($request_params["short_info"])) {
            $request_data["short_info"] = trim($request_params["short_info"]);
        }

        // Validate request
        $request_validations = [
            "name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ]
        ];
        $validation_errors = Validator::validate($request_validations, $request_data);

        // If request is invalid
        if (!empty($validation_errors)) {
            // Fetch error code & message and return the response
            $additional_message = implode("\n", $validation_errors);
            return ErrorComponent::outputError($response, "api_messages/INVALID_REQUEST_BODY", $additional_message);
        }

        // Model account organization
        $model_account_organization = new AccountOrganization();

        try {
            // Fetch if data already exists
            $condition = [
                "fields" => [
                    "ao.id"
                ],
                "where" => [
                    ["where" => ["ao.account_id", "=", $logged_in_account]]
                ]
            ];
            $already_exists = $model_account_organization->fetch($condition);

            // Save data
            $save_data = [
                "name" => trim($request_data["name"]),
                "address" => trim($request_data["address"]),
                "city" => trim($request_data["city"]),
                "state" => trim($request_data["state"]),
                "country" => trim($request_data["country"]),
                "zipcode" => trim($request_data["zipcode"]),
                "website" => trim($request_data["website"]),
                "contact_phone" => trim($request_data["contact_phone"]),
                "contact_fax" => trim($request_data["contact_fax"]),
                "short_info" => trim($request_data["short_info"])
            ];
            if (!empty($already_exists["id"])) {
                $save_data["id"] = $already_exists["id"];
            }

            // Upload image
            if (!empty($request_data["logo"])) {
                // Upload image
                $upload_path = \PATH_TO_MEDIA . \MEDIA_ORG_LOGOS;
                $uploaded_image = ImageUploaderComponent::uploadImage($request_data["logo"], $upload_path);

                if (!empty($uploaded_image["error"])) {
                    // Fetch error code & message and return the response
                    $additional_message = "Could not upload user image.";
                    return ErrorComponent::outputError($response, "api_messages/ERROR_IMAGE_UPLOAD", $additional_message);
                }

                $save_data["logo"] = $uploaded_image["name"];

            } else if (isset($request_data["logo"])) {
                // If logo is passed empty string, then set it to null
                $save_data["logo"] = "";
            }

            if ($model_account_organization->save($save_data) !== false) {
                $output["message"] = "Data saved successfully.";

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

}