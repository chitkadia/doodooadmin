<?php
/**
 * Teams related functionality
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
use \App\Models\ContactUsLeads;

class ContactusLeadsController extends AppController {

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * List all data
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getContactusLeads(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Fetch All record
        $model_contactus_leads = new ContactUsLeads();

        // Get request parameters
        $params = $request->getQueryParams();
        
        // Set parameters
        $query_params = [
            "page" => 1,
            "per_page" => SHAppComponent::getValue("app_constant/DEFAULT_LIST_PER_PAGE"),
            "order_by" => "id",
            "order" => "DESC"
        ];

        if (!empty($params["page"])) {
            $query_params["page"] = (int) $params["page"];
        }
        if (!empty($params["per_page"])) {
            $query_params["per_page"] = (int) $params["per_page"];
        }
        if (!empty($params["order_by"])) {
            $query_params["order_by"] = trim($params["order_by"]);
        }
        if (!empty($params["order"])) {
            $query_params["order"] = trim($params["order"]);
        }
        
        if (!empty($params["query"])) {
            $query_params["query"] = SHAppComponent::prepareSearchText($params["query"]);
        }

        // Other values for condition
        $other_values = [
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        try {
            $data = $model_contactus_leads->getListData($query_params, $other_values);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Process data and prepare for output
        $output["total_records"] = $data["total_records"];
        $output["total_pages"] = $data["total_pages"];
        $output["current_page"] = $data["current_page"];
        $output["per_page"] = $data["per_page"];
        $output["rows"] = [];

        foreach ($data["rows"] as $row) {
            
            $created_date = DateTimeComponent::convertDateTime($row["created_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "name" => $row["name"],
                "email" => $row["email"],
                "address" => $row["address"],
                "city" => $row["city"],
                "contact_no" => $row["contact_no"],
                "message" => $row["message"],
                "created_date" => $created_date
            ];

            $output["rows"][] = $row_data;
        }

        return $response->withJson($output, 200);
    }

    /**
     * Create new record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function addContactus(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["name"])) {
            $request_data["name"] = $request_params["name"];
        }
        if (isset($request_params["email"])) {
            $request_data["email"] = $request_params["email"];
        }
        if (isset($request_params["address"])) {
            $request_data["address"] = $request_params["address"];
        }
        if (isset($request_params["city"])) {
            $request_data["city"] = $request_params["city"];
        }
        if (isset($request_params["contact_no"])) {
            $request_data["contact_no"] = $request_params["contact_no"];
        }
        if (isset($request_params["message"])) {
            $request_data["message"] = $request_params["message"];
        }

        // Validate request
        $request_validations = [
            "name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "email" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "address" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "city" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "contact_no" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "message" => [
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

        // Save record
        $model_contact_us = new ContactUsLeads();

        try {
            $save_data = [
                "name" => trim($request_data["name"]),
                "email" => trim($request_data["email"]),
                "address" => trim($request_data["address"]),
                "city" => trim($request_data["city"]),
                "contact_no" => trim($request_data["contact_no"]),
                "message" => trim($request_data["message"]),
                "created_date" => DateTimeComponent::getDateTime()
            ];

            $model_contact_us->save($save_data);

            $output["message"] = "Thank you for contacting us. We will get back to you shortly.";

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 201);
    }

}
