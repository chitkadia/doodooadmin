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
use \App\Components\PaytmComponent;
use \App\Models\TblProductCategory;
use \App\Models\ProductSubCategory;
use \App\Models\AgeCategory;
use \App\Models\ProductMaster;
use \App\Models\ProductImage;
use \App\Models\ShoppingCart;
use \App\Models\PlanMaster;
use \App\Models\ActiveplanDetails;
use \App\Models\PurchaseDetails;
use \App\Models\PaymentDetails;
use \App\Models\ProductRating;
use \App\Models\OrderShippingDetail;

class ProductController extends AppController {

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
    public function getProductCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Fetch All record
        $model_product_category = new TblProductCategory();

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
            $data = $model_product_category->getListData($query_params, $other_values);

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
            if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_INACTIVE")) {
                $status = "STATUS_INACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE")) {
                $status = "STATUS_ACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_REMOVED")) {
                $status = "STATUS_REMOVED";
            } else {
                $status = "NA";
            }

            $created_date = DateTimeComponent::convertDateTime($row["created_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);
            $modified_date = DateTimeComponent::convertDateTime($row["modified_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "category_name" => $row["category_name"],
                "status" => $status,
                "created_date" => $created_date,
                "modified_date" => $modified_date
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
    public function addProductCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_source = SHAppComponent::getRequestSource();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["product_category"])) {
            $request_data["product_category"] = $request_params["product_category"];
        }

        // Validate request
        $request_validations = [
            "product_category" => [
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
        $model_product_category = new TblProductCategory();

        try {
            $save_data = [
                "category_name" => trim($request_data["product_category"]),
                "created_date" => DateTimeComponent::getDateTime(),
                "modified_date" => DateTimeComponent::getDateTime()
            ];

            $model_product_category->save($save_data);

            $output["message"] = "Product category successfully added.";

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 201);
    }

    /**
     * Update existing record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function updateProductCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_category = new TblProductCategory();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_product_category->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["product_category"])) {
            $request_data["product_category"] = $request_params["product_category"];
        }

        // Validate request
        $request_validations = [
            "product_category" => [
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

        try {
            $update_data_pro_cat = [
                "category_name" => trim($request_data["product_category"]),
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_cat_update = [
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];
            if ($model_product_category->update($update_data_pro_cat, $conditions_pro_cat_update) !== false) {
                
                $output["message"] = "Product category successfully updated.";

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
     * View single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function viewProductCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();
        // $user_timezone = SHAppComponent::getUserTimeZone();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_category = new TblProductCategory();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_product_category->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get user information
        try {
            $condition = [
                "fields" => [
                    "category_name"
                ],
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];
            $product_category_data = $model_product_category->fetch($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        
        $output = [
            "category_name" => $product_category_data["category_name"]
        ];

        return $response->withJson($output, 200);
    }

    /**
     * Delete single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function deleteProductCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_category = new TblProductCategory();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_product_category->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Update status
        try {
            $update_data_pro_cat = [
                "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_cat_update = [
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];

            if ($model_product_category->update($update_data_pro_cat, $conditions_pro_cat_update) !== false) {

                $output["message"] = "Product category deleted successfully.";
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
     * Update existing record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function updateProductCategoryListStatus(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_category = new TblProductCategory();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_product_category->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["status"])) {
            $request_data["status"] = $request_params["status"];
        }

        try {
            $update_data_pro_cat = [
                "status" => SHAppComponent::getValue("app_constant/".$request_data["status"]),
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_cat_update = [
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];
            if ($model_product_category->update($update_data_pro_cat, $conditions_pro_cat_update) !== false) {
                
                $output["message"] = "Product category status successfully updated.";

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
     * List all data
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getProductSubCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Fetch All record
        $model_product_category = new ProductSubCategory();

        // $condition = [
        //     "fields" => [
        //         "psc.id",
        //         "psc.product_category_id",
        //         "psc.sub_category_name",
        //         "psc.status",
        //         "psc.created_date",
        //         "pc.category_name"
        //     ],
        //     "where" => [
        //         ["where" => ["psc.status", "<>", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
        //     ],
        //     "join" => [
        //         "product_category"
        //     ]
        // ];
        // $data = $model_product_category->fetchAll($condition);

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
            $data = $model_product_category->getListData($query_params, $other_values);

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
            if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_INACTIVE")) {
                $status = "STATUS_INACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE")) {
                $status = "STATUS_ACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_REMOVED")) {
                $status = "STATUS_REMOVED";
            } else {
                $status = "NA";
            }

            $created_date = DateTimeComponent::convertDateTime($row["created_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);
            $modified_date = DateTimeComponent::convertDateTime($row["modified_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "product_category_id" => StringComponent::encodeRowId($row["product_category_id"]),
                "category_name" => $row["category_name"],
                "sub_category_name" => $row["sub_category_name"],
                "status" => $status,
                "created_date" => $created_date,
                "modified_date" => $modified_date
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
    public function addProductSubCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_source = SHAppComponent::getRequestSource();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["sub_category_name"])) {
            $request_data["sub_category_name"] = $request_params["sub_category_name"];
        }
        if (isset($request_params["product_category_id"])) {
            $request_data["product_category_id"] = $request_params["product_category_id"];
        }

        // Validate request
        $request_validations = [
            "sub_category_name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "product_category_id" => [
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
        $model_product_category = new ProductSubCategory();

        try {
            $save_data = [
                "product_category_id" => StringComponent::decodeRowId($request_data["product_category_id"]),
                "sub_category_name" => trim($request_data["sub_category_name"]),
                "created_date" => DateTimeComponent::getDateTime(),
                "modified_date" => DateTimeComponent::getDateTime()
            ];

            $model_product_category->save($save_data);

            $output["message"] = "Product sub category successfully added.";

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 201);
    }

    /**
     * Update existing record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function updateProductSubCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_category = new ProductSubCategory();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_product_category->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["sub_category_name"])) {
            $request_data["sub_category_name"] = $request_params["sub_category_name"];
        }
        if (isset($request_params["product_category_id"])) {
            $request_data["product_category_id"] = $request_params["product_category_id"];
        }

        // Validate request
        $request_validations = [
            "sub_category_name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "product_category_id" => [
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

        try {
            $update_data_pro_cat = [
                "product_category_id" => StringComponent::decodeRowId($request_data["product_category_id"]),
                "sub_category_name" => trim($request_data["sub_category_name"]),
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_cat_update = [
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];
            if ($model_product_category->update($update_data_pro_cat, $conditions_pro_cat_update) !== false) {
                
                $output["message"] = "Product sub category successfully updated.";

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
     * View single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function viewProductSubCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();
        // $user_timezone = SHAppComponent::getUserTimeZone();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_category = new ProductSubCategory();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_product_category->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get user information
        try {
            $condition = [
                "fields" => [
                    "product_category_id",
                    "sub_category_name"
                ],
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];
            $product_category_data = $model_product_category->fetch($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $model_main_product_category = new TblProductCategory();
        // Get product category information
        try {
            $condition_main_prod = [
                "fields" => [
                    "id",
                    "category_name"
                ],
                "where" => [
                    ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $product_category_details = $model_main_product_category->fetchAll($condition_main_prod);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $output = [
            "product_category_id" => StringComponent::encodeRowId($product_category_data["product_category_id"]),
            "product_sub_category_name" => $product_category_data["sub_category_name"]
        ];

        $output["category_data"] = [];

        foreach($product_category_details as $category_data) {
            $data_list = [
                "category_id" => StringComponent::encodeRowId($category_data["id"]),
                "main_category_name" => $category_data["category_name"]
            ];

            $output["category_data"][] = $data_list;
        }

        return $response->withJson($output, 200);
    }

    /**
     * Delete single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function deleteProductSubCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_category = new ProductSubCategory();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_product_category->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Update status
        try {
            $update_data_pro_cat = [
                "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_cat_update = [
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];

            if ($model_product_category->update($update_data_pro_cat, $conditions_pro_cat_update) !== false) {

                $output["message"] = "Product sub category deleted successfully.";
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
     * Update existing record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function updateProductSubCategoryListStatus(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_category = new ProductSubCategory();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_product_category->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["status"])) {
            $request_data["status"] = $request_params["status"];
        }

        try {
            $update_data_pro_cat = [
                "status" => SHAppComponent::getValue("app_constant/".$request_data["status"]),
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_cat_update = [
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];
            if ($model_product_category->update($update_data_pro_cat, $conditions_pro_cat_update) !== false) {
                
                $output["message"] = "Product sub category status successfully updated.";

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

    public function viewMainCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        $model_main_product_category = new TblProductCategory();
        // Get product category information
        try {
            $condition_main_prod = [
                "fields" => [
                    "id",
                    "category_name"
                ],
                "where" => [
                    ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                ]
            ];
            $product_category_details = $model_main_product_category->fetchAll($condition_main_prod);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        foreach($product_category_details as $category_data) {
            $data_list = [
                "category_id" => StringComponent::encodeRowId($category_data["id"]),
                "main_category_name" => $category_data["category_name"]
            ];

            $output[] = $data_list;
        }

        return $response->withJson($output, 200);
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
    public function getAgeCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Fetch All record
        $model_product_category = new AgeCategory();

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
            $data = $model_product_category->getListData($query_params, $other_values);

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
            if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_INACTIVE")) {
                $status = "STATUS_INACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE")) {
                $status = "STATUS_ACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_REMOVED")) {
                $status = "STATUS_REMOVED";
            } else {
                $status = "NA";
            }

            $created_date = DateTimeComponent::convertDateTime($row["created_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);
            $modified_date = DateTimeComponent::convertDateTime($row["modified_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "age_category_name" => $row["age_category_name"],
                "status" => $status,
                "created_date" => $created_date,
                "modified_date" => $modified_date
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
    public function addAgeCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_source = SHAppComponent::getRequestSource();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["age_category_name"])) {
            $request_data["age_category_name"] = $request_params["age_category_name"];
        }

        // Validate request
        $request_validations = [
            "age_category_name" => [
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
        $model_product_category = new AgeCategory();

        try {
            $save_data = [
                "age_category_name" => trim($request_data["age_category_name"]),
                "created_date" => DateTimeComponent::getDateTime(),
                "modified_date" => DateTimeComponent::getDateTime()
            ];

            $model_product_category->save($save_data);

            $output["message"] = "Age category successfully added.";

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 201);
    }

    /**
     * Update existing record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function updateAgeCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_category = new AgeCategory();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_product_category->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["age_category_name"])) {
            $request_data["age_category_name"] = $request_params["age_category_name"];
        }

        // Validate request
        $request_validations = [
            "age_category_name" => [
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

        try {
            $update_data_pro_cat = [
                "age_category_name" => trim($request_data["age_category_name"]),
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_cat_update = [
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];
            if ($model_product_category->update($update_data_pro_cat, $conditions_pro_cat_update) !== false) {
                
                $output["message"] = "Age category successfully updated.";

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
     * View single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function viewAgeCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();
        // $user_timezone = SHAppComponent::getUserTimeZone();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_category = new AgeCategory();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_product_category->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get user information
        try {
            $condition = [
                "fields" => [
                    "age_category_name"
                ],
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];
            $product_category_data = $model_product_category->fetch($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        
        $output = [
            "age_category_name" => $product_category_data["age_category_name"]
        ];

        return $response->withJson($output, 200);
    }

    /**
     * Delete single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function deleteAgeCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_category = new AgeCategory();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_product_category->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Update status
        try {
            $update_data_pro_cat = [
                "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_cat_update = [
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];

            if ($model_product_category->update($update_data_pro_cat, $conditions_pro_cat_update) !== false) {

                $output["message"] = "Age category deleted successfully.";
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
     * Update existing record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function updateAgeCategoryListStatus(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_category = new AgeCategory();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_product_category->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["status"])) {
            $request_data["status"] = $request_params["status"];
        }

        try {
            $update_data_pro_cat = [
                "status" => SHAppComponent::getValue("app_constant/".$request_data["status"]),
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_cat_update = [
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];
            if ($model_product_category->update($update_data_pro_cat, $conditions_pro_cat_update) !== false) {
                
                $output["message"] = "Age category status successfully updated.";

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

    public function getAllCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Fetch All record
        $model_product_category = new TblProductCategory();

        $condition = [
            "fields" => [
                "id",
                "category_name",
                "status",
                "created_date",
                "modified_date"
            ],
            "where" => [
                ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
            ]
        ];
        $data = $model_product_category->fetchAll($condition);

        foreach($data as $row) {

            if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_INACTIVE")) {
                $status = "STATUS_INACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE")) {
                $status = "STATUS_ACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_REMOVED")) {
                $status = "STATUS_REMOVED";
            } else {
                $status = "NA";
            }

            $created_date = DateTimeComponent::convertDateTime($row["created_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);
            $modified_date = DateTimeComponent::convertDateTime($row["modified_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);

            $data_show = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "category_name" => $row["category_name"],
                "status" => $status,
                "created_date" => $created_date,
                "modified_date" => $modified_date
            ];

            $output[] = $data_show;
        }

        return $response->withJson($output, 200);
    }

    public function getAllSubCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Fetch All record
        $model_product_category = new ProductSubCategory();

        $condition = [
            "fields" => [
                "psc.id",
                "psc.product_category_id",
                "psc.sub_category_name",
                "psc.status",
                "psc.created_date",
                "psc.modified_date",
                "pc.category_name"
            ],
            "where" => [
                ["where" => ["psc.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
            ],
            "join" => [
                "product_category"
            ]
        ];
        $data = $model_product_category->fetchAll($condition);

        foreach($data as $row) {

            if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_INACTIVE")) {
                $status = "STATUS_INACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE")) {
                $status = "STATUS_ACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_REMOVED")) {
                $status = "STATUS_REMOVED";
            } else {
                $status = "NA";
            }

            $created_date = DateTimeComponent::convertDateTime($row["created_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);
            $modified_date = DateTimeComponent::convertDateTime($row["modified_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);

            $data_show = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "category_name" => $row["category_name"],
                "status" => $status,
                "created_date" => $created_date,
                "modified_date" => $modified_date,
                "sub_category_name" => $row["sub_category_name"],
                "product_category_id" => $row["product_category_id"]
            ];

            $output[] = $data_show;
        }

        return $response->withJson($output, 200);
    }

    public function getAllAgeGroupList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Fetch All record
        $model_product_category = new AgeCategory();

        $condition = [
            "fields" => [
                "id",
                "age_category_name",
                "status",
                "created_date",
                "modified_date"
            ],
            "where" => [
                ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
            ]
        ];
        $data = $model_product_category->fetchAll($condition);

        foreach($data as $row) {

            if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_INACTIVE")) {
                $status = "STATUS_INACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE")) {
                $status = "STATUS_ACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_REMOVED")) {
                $status = "STATUS_REMOVED";
            } else {
                $status = "NA";
            }

            $created_date = DateTimeComponent::convertDateTime($row["created_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);
            $modified_date = DateTimeComponent::convertDateTime($row["modified_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);

            $data_show = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "age_category_name" => $row["age_category_name"],
                "status" => $status,
                "created_date" => $created_date,
                "modified_date" => $modified_date
            ];

            $output[] = $data_show;
        }

        return $response->withJson($output, 200);
    }

    public function getSelectedSubCategoryList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();
        // $user_timezone = SHAppComponent::getUserTimeZone();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_category = new TblProductCategory();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_product_category->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get all sub category of particular category
        $model_product_sub_category = new ProductSubCategory();

        $condition = [
            "fields" => [
                "psc.id",
                "psc.product_category_id",
                "psc.sub_category_name",
                "psc.status",
                "psc.created_date",
                "psc.modified_date",
                "pc.category_name"
            ],
            "where" => [
                ["where" => ["psc.status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]],
                ["where" => ["psc.product_category_id", "=", $row_id]]
            ],
            "join" => [
                "product_category"
            ]
        ];
        $data = $model_product_sub_category->fetchAll($condition);

        foreach($data as $row) {

            if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_INACTIVE")) {
                $status = "STATUS_INACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE")) {
                $status = "STATUS_ACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_REMOVED")) {
                $status = "STATUS_REMOVED";
            } else {
                $status = "NA";
            }

            $created_date = DateTimeComponent::convertDateTime($row["created_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);
            $modified_date = DateTimeComponent::convertDateTime($row["modified_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);

            $data_show = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "category_name" => $row["category_name"],
                "status" => $status,
                "created_date" => $created_date,
                "modified_date" => $modified_date,
                "sub_category_name" => $row["sub_category_name"],
                "product_category_id" => $row["product_category_id"]
            ];

            $output[] = $data_show;
        }

        return $response->withJson($output, 200);
    }

    public function createProduct(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["product_category_id"])) {
            $request_data["product_category_id"] = $request_params["product_category_id"];
        }
        if (isset($request_params["product_sub_category_id"])) {
            $request_data["product_sub_category_id"] = $request_params["product_sub_category_id"];
        }
        if (isset($request_params["age_group"])) {
            $request_data["age_group"] = $request_params["age_group"];
        }
        if (isset($request_params["product_name"])) {
            $request_data["product_name"] = $request_params["product_name"];
        }
        if (isset($request_params["product_price"])) {
            $request_data["product_price"] = $request_params["product_price"];
        }
        if (isset($request_params["product_amazon_link"])) {
            $request_data["product_amazon_link"] = $request_params["product_amazon_link"];
        }
        if (isset($request_params["product_short_description"])) {
            $request_data["product_short_description"] = $request_params["product_short_description"];
        }
        if (isset($request_params["product_description"])) {
            $request_data["product_description"] = $request_params["product_description"];
        }

        // Validate request
        $request_validations = [
            "product_category_id" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "age_group" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "product_name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "product_price" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "product_amazon_link" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "product_short_description" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "product_description" => [
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
        $model_product_master = new ProductMaster();

        try {

            $sub_cat_array = [];
            for($i = 0; $i < count($request_data["product_sub_category_id"]); $i++) {
                array_push($sub_cat_array, StringComponent::decodeRowId($request_data["product_sub_category_id"][$i]));
            }
            $sub_cat_save = implode(",", $sub_cat_array);

            $save_data = [
                "product_category_id" => StringComponent::decodeRowId($request_data["product_category_id"]),
                "product_sub_category_id" => $sub_cat_save,
                "age_category_id" => StringComponent::decodeRowId($request_data["age_group"]),
                "product_name" => trim($request_data["product_name"]),
                "product_price" => trim($request_data["product_price"]),
                "product_short_description" => trim($request_data["product_short_description"]),
                "product_description" => trim($request_data["product_description"]),
                "product_amazon_link" => trim($request_data["product_amazon_link"]),
                "created_date" => DateTimeComponent::getDateTime(),
                "modified_date" => DateTimeComponent::getDateTime()
            ];

            if ($model_product_master->save($save_data) !== false) {
                $inserted_role_id = $model_product_master->getLastInsertId();

                $output["id"] = StringComponent::encodeRowId($inserted_role_id);
                $output["message"] = "Product added successfully.";

            } else {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 201);
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
    public function getProductList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Fetch All record
        $model_product_master = new ProductMaster();

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
        if (!empty($params["category"])) {
            $query_params["category"] = $params["category"];
        }
        if (!empty($params["age_cat"])) {
            $query_params["age_cat"] = StringComponent::decodeRowId($params["age_cat"]);
        }
        if (!empty($params["sub_cat"])) {
            $query_params["sub_cat"] = StringComponent::decodeRowId($params["sub_cat"]);
        }
        
        if (!empty($params["query"])) {
            $query_params["query"] = SHAppComponent::prepareSearchText($params["query"]);
        }

        // Other values for condition
        $other_values = [
            "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
        ];

        try {
            $data = $model_product_master->getListData($query_params, $other_values);

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
            if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_INACTIVE")) {
                $status = "STATUS_INACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE")) {
                $status = "STATUS_ACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_REMOVED")) {
                $status = "STATUS_REMOVED";
            } else {
                $status = "NA";
            }

            $created_date = DateTimeComponent::convertDateTime($row["created_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);
            $modified_date = DateTimeComponent::convertDateTime($row["modified_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);

            $condition_sub_cat = [
                "ids" => $row["product_sub_category_id"]
            ];
            $get_sub_cat_list = $model_product_master->getSubCategoryList($condition_sub_cat);

            $condition_prod_image = [
                "product_id" => $row["id"]
            ];
            $get_prod_image_list = $model_product_master->getProdImageList($condition_prod_image);
            $get_prod_image_default = $model_product_master->getProdImageDefault($condition_prod_image);

            $array_sub_cat = [];
            $array_sub_cat_id = [];
            foreach($get_sub_cat_list as $data_sub_cat) {
                array_push($array_sub_cat, $data_sub_cat["sub_category_name"]);
                array_push($array_sub_cat_id, $data_sub_cat["id"]);
            }

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "product_category_id" => StringComponent::encodeRowId($row["product_category_id"]),
                "product_sub_category_id" => json_decode(json_encode($array_sub_cat_id), true),
                "age_category_id" => StringComponent::encodeRowId($row["age_category_id"]),
                "sub_category_name" => json_decode(json_encode($array_sub_cat), true),
                "category_name" => $row["category_name"],
                "product_name" => $row["product_name"],
                "product_price" => $row["product_price"],
                "product_short_description" => $row["product_short_description"],
                "product_description" => $row["product_description"],
                "product_amazon_link" => $row["product_amazon_link"],
                "age_category_name" => $row["age_category_name"],
                "status" => $status,
                "created_date" => $created_date,
                "modified_date" => $modified_date,
                "product_images" => $get_prod_image_list,
                "product_default_image" => $get_prod_image_default
            ];

            $output["rows"][] = $row_data;
        }

        return $response->withJson($output, 200);
    }

    /**
     * Update existing record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function updateProductStatus(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_master = new ProductMaster();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_product_master->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["status"])) {
            $request_data["status"] = $request_params["status"];
        }

        try {
            $update_data_pro = [
                "status" => SHAppComponent::getValue("app_constant/".$request_data["status"]),
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_update = [
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];
            if ($model_product_master->update($update_data_pro, $conditions_pro_update) !== false) {
                
                $output["message"] = "Product status successfully updated.";

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
     * Delete single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function deleteProduct(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_master = new ProductMaster();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_product_master->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Update status
        try {
            $update_data_pro = [
                "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_update = [
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];

            if ($model_product_master->update($update_data_pro, $conditions_pro_update) !== false) {

                $output["message"] = "Product deleted successfully.";
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
     * Update existing record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function updateProductList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_master = new ProductMaster();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_product_master->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["product_category_id"])) {
            $request_data["product_category_id"] = $request_params["product_category_id"];
        }
        if (isset($request_params["product_sub_category_id"])) {
            $request_data["product_sub_category_id"] = $request_params["product_sub_category_id"];
        }
        if (isset($request_params["age_group"])) {
            $request_data["age_group"] = $request_params["age_group"];
        }
        if (isset($request_params["product_name"])) {
            $request_data["product_name"] = $request_params["product_name"];
        }
        if (isset($request_params["product_price"])) {
            $request_data["product_price"] = $request_params["product_price"];
        }
        if (isset($request_params["product_amazon_link"])) {
            $request_data["product_amazon_link"] = $request_params["product_amazon_link"];
        }
        if (isset($request_params["product_short_description"])) {
            $request_data["product_short_description"] = $request_params["product_short_description"];
        }
        if (isset($request_params["product_description"])) {
            $request_data["product_description"] = $request_params["product_description"];
        }

        // Validate request
        $request_validations = [
            "product_category_id" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "age_group" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "product_name" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "product_price" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "product_amazon_link" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "product_short_description" => [
                ["type" => Validator::FIELD_REQ_NOTEMPTY]
            ],
            "product_description" => [
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

        try {
            $sub_cat_array = [];
            for($i = 0; $i < count($request_data["product_sub_category_id"]); $i++) {
                array_push($sub_cat_array, StringComponent::decodeRowId($request_data["product_sub_category_id"][$i]));
            }
            $sub_cat_save = implode(",", $sub_cat_array);

            $update_data_pro = [
                "product_category_id" => StringComponent::decodeRowId($request_data["product_category_id"]),
                "product_sub_category_id" => $sub_cat_save,
                "age_category_id" => StringComponent::decodeRowId($request_data["age_group"]),
                "product_name" => trim($request_data["product_name"]),
                "product_price" => trim($request_data["product_price"]),
                "product_short_description" => trim($request_data["product_short_description"]),
                "product_description" => trim($request_data["product_description"]),
                "product_amazon_link" => trim($request_data["product_amazon_link"]),
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_update = [
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];
            if ($model_product_master->update($update_data_pro, $conditions_pro_update) !== false) {
                
                $output["message"] = "Product successfully updated.";

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
     * View single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function viewProductList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();
        // $user_timezone = SHAppComponent::getUserTimeZone();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_master = new ProductMaster();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE")
            ];

            $valid = $model_product_master->checkRowValidity($row_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get user information
        try {
            $condition = [
                "fields" => [
                    "pm.id",
                    "pm.product_category_id",
                    "pm.product_sub_category_id",
                    "pm.age_category_id",
                    "pm.product_name",
                    "pm.product_price",
                    "pm.product_short_description",
                    "pm.product_description",
                    "pm.product_amazon_link",
                    "(SELECT COUNT(pr.id) FROM product_rating AS pr WHERE pr.product_id = pm.id) AS review_count",
                    "(SELECT SUM(pr.rating) FROM product_rating AS pr WHERE pr.product_id = pm.id) AS review_sum"
                ],
                "where" => [
                    ["where" => ["pm.id", "=", $row_id]]
                ]
            ];
            $product_category_data = $model_product_master->fetch($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $condition_sub_cat = [
            "ids" => $product_category_data["product_sub_category_id"]
        ];
        $get_sub_cat_list = $model_product_master->getSubCategoryList($condition_sub_cat);

        $condition_prod_image = [
            "product_id" => $product_category_data["id"]
        ];
        $get_prod_image_list = $model_product_master->getProdImageList($condition_prod_image);

        $array_sub_cat_id = [];
        foreach($get_sub_cat_list as $data_sub_cat) {
            array_push($array_sub_cat_id, StringComponent::encodeRowId($data_sub_cat["id"]));
        }

        $average_review = 0;
        if ($product_category_data["review_count"] > 0) {
            $average_review = round($product_category_data["review_sum"]/$product_category_data["review_count"], 0)/10;
        }
        
        $output = [
            "id" => StringComponent::encodeRowId($product_category_data["id"]),
            "product_category_id" => StringComponent::encodeRowId($product_category_data["product_category_id"]),
            "product_sub_category_id" => json_decode(json_encode($array_sub_cat_id), true),
            "age_category_id" => StringComponent::encodeRowId($product_category_data["age_category_id"]),
            "product_name" => $product_category_data["product_name"],
            "product_price" => $product_category_data["product_price"],
            "product_short_description" => $product_category_data["product_short_description"],
            "product_description" => $product_category_data["product_description"],
            "product_amazon_link" => $product_category_data["product_amazon_link"],
            "product_images" => $get_prod_image_list,
            "average_review" => $average_review
        ];

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
    public function uploadImage(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_source = SHAppComponent::getRequestSource();
        $request_params = $request->getParsedBody();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);
        
        
        $file_type_array = [
            "image/gif",
            "image/x-icon",
            "image/jpeg",
            "image/png"
        ];
        if (!in_array($_FILES["file"]["type"], $file_type_array)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/INVALID_FILE_TYPE", "Please upload .png,.jpeg,.jpg, .ico or .gif extension image");
        }

        if(!empty($_FILES["file"])) {
            //get the name of file
            $file_name = $_FILES['file']['name'];

            //get the extension of file
            $file_extension = "." .strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            //generate unique name of file
            $generated_file_name = strtotime("now") . rand(11111, 99999) . $file_extension;

            //destination path of file
            $destination_file_path = DOCUMENT_UPLOADED_DIRECTORY . $row_id. "/" .$generated_file_name;

            $directory_path = DOCUMENT_UPLOADED_DIRECTORY . $row_id . "/";

            //Make directory if does not exists
            if (!is_dir($directory_path)) {
                mkdir($directory_path, 0777, true);
            }

            //Move file in uploaded directory
            if(move_uploaded_file($_FILES['file']['tmp_name'], $destination_file_path)) {
                   try { 

                        $image_data = [
                            "product_id" => $row_id,
                            "image_name" => $file_name,
                            "image_path" => $row_id . "/" . $generated_file_name,
                            "image_extension" => $file_extension,
                            "created_date" => DateTimeComponent::getDateTime(),
                            "modified_date" => DateTimeComponent::getDateTime()
                        ];
                        
                        $model_product_image = new ProductImage();
                       
                        if ($model_product_image->save($image_data) !== false) {
                            $image_id = $model_product_image->getLastInsertId();

                            $output = [
                                "id" => StringComponent::encodeRowId($image_id),
                                "status" => true,
                                "message" => "Image Uploaded successfully"
                            ];
                        } else {
                            // Fetch error code & message and return the response
                            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                        }
                } catch(\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }
            } else {
                $output = [ "status" => false, "message" => "Image Upload Fail"];
            }
        }
        return $response->withJson($output, 201);

    }

    /**
     * List All Images
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function viewProductImageList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Fetch All record
        $model_product_image = new ProductImage();

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
            $data = $model_product_image->getListData($query_params, $other_values);

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
            if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_INACTIVE")) {
                $status = "STATUS_INACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE")) {
                $status = "STATUS_ACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_REMOVED")) {
                $status = "STATUS_REMOVED";
            } else {
                $status = "NA";
            }

            $created_date = DateTimeComponent::convertDateTime($row["created_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);
            $modified_date = DateTimeComponent::convertDateTime($row["modified_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "product_id" => StringComponent::encodeRowId($row["product_id"]),
                "image_name" => $row["image_name"],
                "image_path" => DOCUMENT_UPLOADED_DIRECTORY_SHOW . $row["image_path"],
                "image_extension" => $row["image_extension"],
                "is_default" => $row["is_default"],
                "status" => $status,
                "created_date" => $created_date,
                "modified_date" => $modified_date
            ];

            $output["rows"][] = $row_data;
        }

        return $response->withJson($output, 200);
    }

    /**
     * Update existing image record status
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function updateImageStatus(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");
        $img_id = $route->getArgument("img_id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $product_id = StringComponent::decodeRowId($id);
        $image_id = StringComponent::decodeRowId($img_id);

        // Check if record is valid
        $model_product_image = new ProductImage();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "product_id" => $product_id
            ];

            $valid = $model_product_image->checkRowValidityImage($image_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["status"])) {
            $request_data["status"] = $request_params["status"];
        }

        try {
            $update_data_pro = [
                "status" => SHAppComponent::getValue("app_constant/".$request_data["status"]),
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_update = [
                "where" => [
                    ["where" => ["id", "=", $image_id]],
                    ["where" => ["product_id", "=", $product_id]]
                ]
            ];
            if ($model_product_image->update($update_data_pro, $conditions_pro_update) !== false) {
                
                $output["message"] = "Product image status successfully updated.";

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
     * Update existing image record default
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function updateImageDefault(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");
        $img_id = $route->getArgument("img_id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $product_id = StringComponent::decodeRowId($id);
        $image_id = StringComponent::decodeRowId($img_id);

        // Check if record is valid
        $model_product_image = new ProductImage();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "product_id" => $product_id
            ];

            $valid = $model_product_image->checkRowValidityImage($image_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        try {
            $update_data_pro_def = [
                "is_default" => SHAppComponent::getValue("app_constant/FLAG_NO"),
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_update_def = [
                "where" => [
                    ["where" => ["product_id", "=", $product_id]]
                ]
            ];

            if ($model_product_image->update($update_data_pro_def, $conditions_pro_update_def) !== false) {

                $update_data_pro = [
                    "is_default" => SHAppComponent::getValue("app_constant/FLAG_YES"),
                    "modified_date" => DateTimeComponent::getDateTime()
                ];
                $conditions_pro_update = [
                    "where" => [
                        ["where" => ["id", "=", $image_id]],
                        ["where" => ["product_id", "=", $product_id]]
                    ]
                ];
                if ($model_product_image->update($update_data_pro, $conditions_pro_update) !== false) {
                    
                    $output["message"] = "Product image default successfully updated.";

                } else {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 200);
    }

    /**
     * Delete single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function deleteImage(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");
        $img_id = $route->getArgument("img_id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $product_id = StringComponent::decodeRowId($id);
        $image_id = StringComponent::decodeRowId($img_id);

        // Check if record is valid
        $model_product_image = new ProductImage();

        try {
            // Other values for condition
            $other_values = [
                "deleted" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "product_id" => $product_id
            ];

            $valid = $model_product_image->checkRowValidityImage($image_id, $other_values);

            if (!$valid) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/RECORD_NOT_FOUND");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        // Update status
        try {
            $update_data_pro_cat = [
                "status" => SHAppComponent::getValue("app_constant/STATUS_DELETE"),
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_cat_update = [
                "where" => [
                    ["where" => ["id", "=", $image_id]],
                    ["where" => ["product_id", "=", $product_id]]
                ]
            ];

            if ($model_product_image->update($update_data_pro_cat, $conditions_pro_cat_update) !== false) {

                $output["message"] = "Product image deleted successfully.";
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
     * Update image record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function UpdateImage(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_source = SHAppComponent::getRequestSource();
        $request_params = $request->getParsedBody();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("id");
        $img_id = $route->getArgument("img_id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $product_id = StringComponent::decodeRowId($id);
        $image_id = StringComponent::decodeRowId($img_id);
        
        
        $file_type_array = [
            "image/gif",
            "image/x-icon",
            "image/jpeg",
            "image/png"
        ];
        if (!in_array($_FILES["file"]["type"], $file_type_array)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/INVALID_FILE_TYPE", "Please upload .png,.jpeg,.jpg, .ico or .gif extension image");
        }

        if(!empty($_FILES["file"])) {
            //get the name of file
            $file_name = $_FILES['file']['name'];

            //get the extension of file
            $file_extension = "." .strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            //generate unique name of file
            $generated_file_name = strtotime("now") . rand(11111, 99999) . $file_extension;

            //destination path of file
            $destination_file_path = DOCUMENT_UPLOADED_DIRECTORY . $product_id. "/" .$generated_file_name;

            $directory_path = DOCUMENT_UPLOADED_DIRECTORY . $product_id . "/";

            //Make directory if does not exists
            if (!is_dir($directory_path)) {
                mkdir($directory_path, 0777, true);
            }

            //Move file in uploaded directory
            if(move_uploaded_file($_FILES['file']['tmp_name'], $destination_file_path)) {
                   try { 

                        $image_data = [
                            "image_name" => $file_name,
                            "image_path" => $product_id . "/" . $generated_file_name,
                            "image_extension" => $file_extension,
                            "modified_date" => DateTimeComponent::getDateTime()
                        ];
                        $conditions_image_data = [
                            "where" => [
                                ["where" => ["id", "=", $image_id]],
                                ["where" => ["product_id", "=", $product_id]]
                            ]
                        ];
                        
                        $model_product_image = new ProductImage();
                       
                        if ($model_product_image->update($image_data, $conditions_image_data) !== false) {

                            $output = [
                                "status" => true,
                                "message" => "Image updated successfully"
                            ];
                        } else {
                            // Fetch error code & message and return the response
                            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                        }
                } catch(\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }
            } else {
                $output = [ "status" => false, "message" => "Image Upload Fail"];
            }
        }
        return $response->withJson($output, 201);

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
    public function addCart(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Get request parameters
        $route = $request->getAttribute("route");
        $prod_id = $route->getArgument("prod_id");

        // Validate request
        if (empty($prod_id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($prod_id);

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["quantity"])) {
            $request_data["quantity"] = $request_params["quantity"];
        }

        // Validate request
        $request_validations = [
            "quantity" => [
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
        $model_shopping_cart = new ShoppingCart();

        try {
            $condition = [
                "fields" => [
                    "id",
                    "quantity"
                ],
                "where" => [
                    ["where" => ["user_id", "=", $logged_in_user]],
                    ["where" => ["product_id", "=", $row_id]],
                    ["where" => ["status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                ]
            ];
            $cart_data = $model_shopping_cart->fetch($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if (empty($cart_data)) {
            try {
                $save_data = [
                    "user_id" => $logged_in_user,
                    "product_id" => $row_id,
                    "quantity" => $request_data["quantity"],
                    "created_date" => DateTimeComponent::getDateTime(),
                    "modified_date" => DateTimeComponent::getDateTime()
                ];

                $model_shopping_cart->save($save_data);

                $output["message"] = "Product successfully added to cart.";

            } catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
        } else {
            try {
            $update_data_pro_cat = [
                "quantity" => $request_data["quantity"] + $cart_data["quantity"],
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_cat_update = [
                "where" => [
                    ["where" => ["id", "=", $cart_data["id"]]]
                ]
            ];
            $model_shopping_cart->update($update_data_pro_cat, $conditions_pro_cat_update);

            $output["message"] = "Product successfully added to cart.";            

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }
        }

        return $response->withJson($output, 201);
    }

    /**
     * view cart record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function viewCart(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Save record
        $model_shopping_cart = new ShoppingCart();

        try {
            $condition = [
                "fields" => [
                    "sc.id",
                    "sc.quantity",
                    "sc.product_id",
                    "pm.product_name",
                    "pm.product_price",
                    "pm.product_category_id"
                ],
                "where" => [
                    ["where" => ["sc.user_id", "=", $logged_in_user]],
                    ["where" => ["sc.status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                ],
                "join" => [
                    "product_master"
                ]
            ];
            $cart_data = $model_shopping_cart->fetchAll($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $total_cart_price = 0;
        foreach($cart_data as $data) {
            $total_price = $data["quantity"] * $data["product_price"];
            $total_cart_price += $data["quantity"] * $data["product_price"];
            $output["rows"][] = [
                "cart_id" => StringComponent::encodeRowId($data["id"]),
                "quantity" => $data["quantity"],
                "product_id" => StringComponent::encodeRowId($data["product_id"]),
                "product_name" => $data["product_name"],
                "product_price" => $data["product_price"],
                "total_price" => $total_price,
                "product_category" => $data["product_category_id"]
            ];
        }
        $output["total_cart_price"] = $total_cart_price;

        return $response->withJson($output, 201);
    }

    /**
     * Update cart record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function updateCart(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["quantity"])) {
            $request_data["quantity"] = $request_params["quantity"];
        }

        // Save record
        $model_shopping_cart = new ShoppingCart();

        foreach($request_data["quantity"] as $key => $value) {
            try {
                $update_data_pro_cat = [
                    "quantity" => $value,
                    "modified_date" => DateTimeComponent::getDateTime()
                ];
                $conditions_pro_cat_update = [
                    "where" => [
                        ["where" => ["product_id", "=", StringComponent::decodeRowId($key)]],
                        ["where" => ["user_id", "=", $logged_in_user]],
                        ["where" => ["status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                    ]
                ];
                $model_shopping_cart->update($update_data_pro_cat, $conditions_pro_cat_update);

                $output["message"] = "Cart Successfully updated.";            

            } catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
        }

        return $response->withJson($output, 201);
    }

    /**
     * Make payment process
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function makePayment(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["amount"])) {
            $request_data["amount"] = $request_params["amount"];
        }
        if (isset($request_params["plan_id"])) {
            $request_data["plan_id"] = $request_params["plan_id"];
        }

        $order_id = "ORD_" . $logged_in_user . "_" . rand(10000,99999999);
        $customer_id = "CUST_" . StringComponent::encodeRowId($logged_in_user);
        $industry_type_id = "Retail";
        $channel_id = "WEB";
        $txn_amount = $request_data["amount"];

        // Save record
        $model_shopping_cart = new ShoppingCart();
        $plan_master = new PlanMaster();
        $active_plan_detail = new ActiveplanDetails();
        $purchase_detail = new PurchaseDetails();
        $payment_detail = new PaymentDetails();

        try {
            $condition_plan = [
                "fields" => [
                    "plan_price",
                    "plan_deposite",
                    "configuration"
                ],
                "where" => [
                    ["where" => ["id", "=", $request_data["plan_id"]]]
                ]
            ];
            $plan_data = $plan_master->fetch($condition_plan);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        try {
            $condition_plan = [
                "fields" => [
                    "id"
                ],
                "where" => [
                    ["where" => ["user_id", "=", $logged_in_user]]
                ]
            ];
            $active_plan_data = $active_plan_detail->fetch($condition_plan);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if (empty($active_plan_data)) {
            try {
                $save_data = [
                    "user_id" => $logged_in_user,
                    "plan_id" => $request_data["plan_id"],
                    "quantity_configuration" => $plan_data["configuration"],
                    "deposite" => $plan_data["plan_deposite"],
                    "created_date" => DateTimeComponent::getDateTime(),
                    "modified_date" => DateTimeComponent::getDateTime()
                ];

                $active_plan_detail->save($save_data);

            } catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
        } else {
            try {
                $update_data_pro_cat = [
                    "plan_id" => $request_data["plan_id"],
                    "quantity_configuration" => $plan_data["configuration"],
                    "modified_date" => DateTimeComponent::getDateTime()
                ];
                $conditions_pro_cat_update = [
                    "where" => [
                        ["where" => ["user_id", "=", $logged_in_user]]
                    ]
                ];
                $active_plan_detail->update($update_data_pro_cat, $conditions_pro_cat_update);

            } catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
        }

        try {
            $save_data_pd = [
                "user_id" => $logged_in_user,
                "plan_id" => $request_data["plan_id"],
                "type" => 1,
                "created_date" => DateTimeComponent::getDateTime(),
                "modified_date" => DateTimeComponent::getDateTime()
            ];

            $last_insert_id_pd = $purchase_detail->save($save_data_pd);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        try {
            $update_data_pro_cat_pd = [
                "active_current_purchase_detail" => $last_insert_id_pd,
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_cat_update_pd = [
                "where" => [
                    ["where" => ["user_id", "=", $logged_in_user]]
                ]
            ];
            $active_plan_detail->update($update_data_pro_cat_pd, $conditions_pro_cat_update_pd);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        try {
            header("Pragma: no-cache");
            header("Cache-Control: no-cache");
            header("Expires: 0");
            // following files need to be included
            
            $checkSum = "";
            $paramList = array();

            $ORDER_ID = $order_id;
            $CUST_ID = $customer_id;
            $INDUSTRY_TYPE_ID = $industry_type_id;
            $CHANNEL_ID = $channel_id;
            $TXN_AMOUNT = $txn_amount;

            try {
                $save_data_payd = [
                    "purchase_detail_id" => $last_insert_id_pd,
                    "user_id" => $logged_in_user,
                    "order_id" => $ORDER_ID,
                    "customer_id" => $CUST_ID,
                    "transaction_amount" => $TXN_AMOUNT,
                    "created_date" => DateTimeComponent::getDateTime()
                ];

                $last_insert_id_payd = $payment_detail->save($save_data_payd);
                
            } catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

            // Create an array having all required parameters for creating checksum.
            $paramList["MID"] = \PAYTM_MERCHANT_MID;
            $paramList["ORDER_ID"] = $ORDER_ID;
            $paramList["CUST_ID"] = $CUST_ID;
            $paramList["INDUSTRY_TYPE_ID"] = $INDUSTRY_TYPE_ID;
            $paramList["CHANNEL_ID"] = $CHANNEL_ID;
            $paramList["TXN_AMOUNT"] = $TXN_AMOUNT;
            $paramList["WEBSITE"] = \PAYTM_MERCHANT_WEBSITE;
            $paramList["CALLBACK_URL"] = 'http://api.toystore.com/payment-response';
            $paramList["PAYMENT_MODE_ONLY"] = 'Yes';
            $paramList["AUTH_MODE"] = '3D';
            $paramList["PAYMENT_TYPE_ID"] = 'DC';
            $paramList["CARD_TYPE"] = 'VISA';

            //Here checksum string will return by getChecksumFromArray() function.
            $checkSum = PaytmComponent::getChecksumFromArray($paramList,PAYTM_MERCHANT_KEY);

            $output["checksum"] = $checkSum;
            $output["paramList"] = $paramList;
            $output["CALL_URL"] = \PAYTM_TXN_URL;

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 201);
    }

    /**
     * Payment Process
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function paymentResponse(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];

        if (isset($request_params["ORDERID"])) {
            $request_data["ORDERID"] = $request_params["ORDERID"];
        }
        if (isset($request_params["TXNID"])) {
            $request_data["TXNID"] = $request_params["TXNID"];
        }
        if (isset($request_params["TXNAMOUNT"])) {
            $request_data["TXNAMOUNT"] = $request_params["TXNAMOUNT"];
        }
        if (isset($request_params["PAYMENTMODE"])) {
            $request_data["PAYMENTMODE"] = $request_params["PAYMENTMODE"];
        }
        if (isset($request_params["TXNDATE"])) {
            $request_data["TXNDATE"] = $request_params["TXNDATE"];
        }
        if (isset($request_params["STATUS"])) {
            $request_data["STATUS"] = $request_params["STATUS"];
        }
        if (isset($request_params["BANKTXNID"])) {
            $request_data["BANKTXNID"] = $request_params["BANKTXNID"];
        }
        if (isset($request_params["BANKNAME"])) {
            $request_data["BANKNAME"] = $request_params["BANKNAME"];
        }
        if (isset($request_params["CHECKSUMHASH"])) {
            $request_data["CHECKSUMHASH"] = $request_params["CHECKSUMHASH"];
        }

        $user_id_val = explode("_", $request_data["ORDERID"]);
        $logged_in_user = $user_id_val[1];

        $model_shopping_cart = new ShoppingCart();
        $plan_master = new PlanMaster();
        $active_plan_detail = new ActiveplanDetails();
        $purchase_detail = new PurchaseDetails();
        $payment_detail = new PaymentDetails();

        try {
            $condition_plan = [
                "fields" => [
                    "apd.id",
                    "apd.active_current_purchase_detail"
                ],
                "where" => [
                    ["where" => ["apd.user_id", "=", $logged_in_user]]
                ]
            ];
            $active_plan_data = $active_plan_detail->fetch($condition_plan);
            
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        if (empty($active_plan_data)) {
            
            return ErrorComponent::outputError($response, "No plan details found");

        } else {
            try {
                $update_data_pro_cat = [
                    "status" => 1,
                    "modified_date" => DateTimeComponent::getDateTime()
                ];
                $conditions_pro_cat_update = [
                    "where" => [
                        ["where" => ["user_id", "=", $logged_in_user]]
                    ]
                ];
                $active_plan_detail->update($update_data_pro_cat, $conditions_pro_cat_update);

            } catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
        }

        try {
            $end_date = strtotime('+30 days', DateTimeComponent::getDateTime());
            if ($request_data["STATUS"] == "TXN_SUCCESS") {
                $status = 1;
            } else {
                $status = 0;
            }

            $save_data_pd = [
                "payment_mode" => 1,
                "type" => 1,
                "start_date" => DateTimeComponent::getDateTime(),
                "end_date" => $end_date,
                "status" => $status,
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_cat_update_pd = [
                "where" => [
                    ["where" => ["user_id", "=", $logged_in_user]],
                    ["where" => ["id", "=", $active_plan_data["active_current_purchase_detail"]]]
                ]
            ];

            $purchase_detail->update($save_data_pd, $conditions_pro_cat_update_pd);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        try {
            if ($request_data["STATUS"] == "TXN_SUCCESS") {
                $status_pd = 1;
            } else {
                $status_pd = 0;
            }
            $payment_date = DateTimeComponent::convertDateTime($request_data["TXNDATE"], false, \USER_TIMEZONE, \DEFAULT_TIMEZONE, "");
            $save_data_payd = [
                "transaction_id" => $request_data["TXNID"],
                "bank_transaction_id" => $request_data["BANKTXNID"],
                "bank_name" => $request_data["BANKNAME"],
                "payment_mode" => $request_data["PAYMENTMODE"],
                "checksumhash" => $request_data["CHECKSUMHASH"],
                "transaction_date" => $payment_date,
                "status" => $status_pd,
                "created_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_cat_update_pd = [
                "where" => [
                    ["where" => ["user_id", "=", $logged_in_user]],
                    ["where" => ["purchase_detail_id", "=", $active_plan_data["active_current_purchase_detail"]]]
                ]
            ];

            $payment_detail->update($save_data_payd, $conditions_pro_cat_update_pd);
            
        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        header('Location: http://localhost:4200/');
    }

    /**
     * Delete Cart
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function deleteCart(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Get request parameters
        $route = $request->getAttribute("route");
        $cart_id = $route->getArgument("cart_id");

        // Validate request
        if (empty($cart_id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($cart_id);

        // Fetch request data
        $request_data = [];
        
        // Save record
        $model_shopping_cart = new ShoppingCart();

        try {
            $condition = [
                "where" => [
                    ["where" => ["id", "=", $row_id]]
                ]
            ];
            if ($model_shopping_cart->delete($condition) !== false) {
                $output["message"] = "Item successfully deleted from cart";
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 201);
    }

    /**
     * Add Review
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function addReview(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_source = SHAppComponent::getRequestSource();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["review"])) {
            $request_data["review"] = $request_params["review"];
        }
        if (isset($request_params["rating"])) {
            $request_data["rating"] = $request_params["rating"];
        }
        if (isset($request_params["product_id"])) {
            $request_data["product_id"] = $request_params["product_id"];
        }

        // Validate request
        $request_validations = [
            "review" => [
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
        $model_product_rating = new ProductRating();

        try {
            $save_data = [
                "user_id" => $logged_in_user,
                "product_id" => StringComponent::decodeRowId($request_data["product_id"]),
                "rating" => $request_data["rating"],
                "review" => $request_data["review"],
                "created_date" => DateTimeComponent::getDateTime(),
                "modified_date" => DateTimeComponent::getDateTime()
            ];

            $model_product_rating->save($save_data);

            $output["message"] = "Your review successfully added.";

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 201);
    }

    public function viewReview(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get request parameters
        $route = $request->getAttribute("route");
        $prod_id = $route->getArgument("prod_id");

        // Validate request
        if (empty($prod_id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($prod_id);

        // Fetch All record
        $model_product_review = new ProductRating();

        $condition = [
            "fields" => [
                "pr.id",
                "pr.rating",
                "pr.review",
                "um.first_name",
                "um.last_name"
            ],
            "where" => [
                ["where" => ["pr.status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]],
                ["where" => ["pr.product_id", "=", $row_id]]
            ],
            "join" => [
                "user_master"
            ]
        ];
        $data = $model_product_review->fetchAll($condition);

        foreach($data as $row) {

            $data_show = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "reviewer_name" => $row["first_name"] . " " . $row["last_name"],
                "rating" => $row["rating"]/10,
                "review" => $row["review"]
            ];

            $output[] = $data_show;
        }

        return $response->withJson($output, 200);
    }

    public function getShippingAddress(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        $logged_in_user = SHAppComponent::getUserId();

        // Fetch All record
        $model_shipping_address = new OrderShippingDetail();

        $condition = [
            "fields" => [
                "id",
                "address",
                "land_mark",
                "city",
                "state",
                "country",
                "zip_code"
            ],
            "where" => [
                ["where" => ["user_id", "=", $logged_in_user]],
                ["where" => ["status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
            ]
        ];
        $data = $model_shipping_address->fetchAll($condition);

        foreach($data as $row) {

            $data_show = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "address" => $row["address"],
                "land_mark" => $row["land_mark"],
                "city" => $row["city"],
                "state" => $row["state"],
                "country" => $row["country"],
                "zipcode" => $row["zip_code"]
            ];

            $output[] = $data_show;
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
    public function addShippingAddress(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_source = SHAppComponent::getRequestSource();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        if (isset($request_params["address"])) {
            $request_data["address"] = $request_params["address"];
        }
        if (isset($request_params["land_mark"])) {
            $request_data["land_mark"] = $request_params["land_mark"];
        }
        if (isset($request_params["zipcode"])) {
            $request_data["zipcode"] = $request_params["zipcode"];
        }
        if (isset($request_params["city"])) {
            $request_data["city"] = $request_params["city"];
        }
        if (isset($request_params["state"])) {
            $request_data["state"] = $request_params["state"];
        }
        if (isset($request_params["country"])) {
            $request_data["country"] = $request_params["country"];
        }

        // Validate request
        $request_validations = [
            "address" => [
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
        $model_order_shippind_detail = new OrderShippingDetail();

        try {
            $save_data = [
                "user_id" => $logged_in_user,
                "address" => trim($request_data["address"]),
                "land_mark" => trim($request_data["land_mark"]),
                "city" => trim($request_data["city"]),
                "state" => trim($request_data["state"]),
                "country" => trim($request_data["country"]),
                "zip_code" => trim($request_data["zipcode"]),
                "created_date" => DateTimeComponent::getDateTime(),
                "modified_date" => DateTimeComponent::getDateTime()
            ];

            $model_order_shippind_detail->save($save_data);

            $output["message"] = "Shipping Address Successfully Added.";

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 201);
    }

    /**
     * Checkout Process
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function checkoutProducts(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        $logged_in_user = SHAppComponent::getUserId();

        // Get request parameters
        $request_params = $request->getParsedBody();

        // Fetch request data
        $request_data = [];
        
        // Save record
        $model_shopping_cart = new ShoppingCart();
        $plan_master = new PlanMaster();
        $product_master = new ProductMaster();
        $active_plan_detail = new ActiveplanDetails();
        $purchase_detail = new PurchaseDetails();
        $payment_detail = new PaymentDetails();

        try {
            $condition_plan = [
                "fields" => [
                    "apd.id",
                    "apd.plan_id",
                    "apd.total_quantity",
                    "apd.toy_quantity",
                    "apd.book_quantity",
                    "pd.start_date",
                    "pd.end_date"
                ],
                "where" => [
                    ["where" => ["apd.user_id", "=", $logged_in_user]],
                    ["where" => ["apd.status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                ],
                "join" => [
                    "purchase_details"
                ]
            ];
            $active_plan_data = $active_plan_detail->fetch($condition_plan);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        $final_book_quantity_add;
        $final_toy_quantity_add;
        $total_book_quantity_add;
        $total_toy_quantity_add;

        if (empty($active_plan_data)) {
           return ErrorComponent::outputError($response, "No Active Plan Found");

        } else {

            try {
                $condition_osm = [
                    "fields" => [
                        "id",
                        "product_id",
                        "quantity"
                    ],
                    "where" => [
                        ["where" => ["user_id", "=", $logged_in_user]],
                        ["where" => ["status", "=", SHAppComponent::getValue("app_constant/STATUS_ACTIVE")]]
                    ]
                ];
                $active_cart_data = $model_shopping_cart->fetchAll($condition_osm);

            } catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

            $array_book = [];
            $array_toy = [];
            foreach ($active_cart_data as $cart_data) {
                try {
                    $condition_pm = [
                        "fields" => [
                            "product_category_id"
                        ],
                        "where" => [
                            ["where" => ["id", "=", $cart_data["product_id"]]]
                        ]
                    ];
                    $active_pm_data = $product_master->fetch($condition_pm);

                } catch(\Exception $e) {
                    // Fetch error code & message and return the response
                    return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
                }
                if ($active_pm_data["product_category_id"] == 1) {
                    array_push($array_book, $cart_data["quantity"]);
                } else {
                    array_push($array_toy, $cart_data["quantity"]);
                }
            }

            $total_book_quant = array_sum($array_book);
            $total_toy_quant = array_sum($array_toy);

            $final_book_quant = $active_plan_data["book_quantity"]+$total_book_quant;
            $final_toy_quant = $active_plan_data["toy_quantity"]+$total_toy_quant;

            $total_quant_final = $active_plan_data["total_quantity"]+$total_book_quant+$total_toy_quant;

            $final_book_quantity_add = $final_book_quant;
            $final_toy_quantity_add = $final_toy_quant;
            $total_book_quantity_add = $total_book_quant;
            $total_toy_quantity_add = $total_toy_quant;

            try {
                $update_data_pro_cat = [
                    "total_quantity" => $total_quant_final,
                    "toy_quantity" => $final_toy_quant,
                    "book_quantity" => $final_book_quant,
                    "modified_date" => DateTimeComponent::getDateTime()
                ];
                $conditions_pro_cat_update = [
                    "where" => [
                        ["where" => ["user_id", "=", $logged_in_user]]
                    ]
                ];
                $active_plan_detail->update($update_data_pro_cat, $conditions_pro_cat_update);

            } catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }
        }

        try {
            $save_data_pd = [
                "user_id" => $logged_in_user,
                "plan_id" => $active_plan_data["plan_id"],
                "quantity" => $total_book_quantity_add+$total_toy_quantity_add,
                "book_quantity" => $total_book_quantity_add,
                "toy_quantity" => $total_toy_quantity_add,
                "type" => 2,
                "start_date" => $active_plan_data["start_date"],
                "end_date" => $active_plan_data["end_date"],
                "next_purchase_details" => $active_plan_data["id"],
                "created_date" => DateTimeComponent::getDateTime(),
                "modified_date" => DateTimeComponent::getDateTime()
            ];

            $last_insert_id_pd = $purchase_detail->save($save_data_pd);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        try {
            $update_data_pro_cat_pd = [
                "active_current_purchase_detail" => $last_insert_id_pd,
                "modified_date" => DateTimeComponent::getDateTime()
            ];
            $conditions_pro_cat_update_pd = [
                "where" => [
                    ["where" => ["user_id", "=", $logged_in_user]]
                ]
            ];
            $active_plan_detail->update($update_data_pro_cat_pd, $conditions_pro_cat_update_pd);

            $output["message"] = "Successfully added product(s)";

            try {
                $condition_delete = [
                    "where" => [
                        ["where" => ["user_id", "=", $logged_in_user]]
                    ]
                ];

                $model_shopping_cart->delete($condition_delete);

            } catch(\Exception $e) {
                // Fetch error code & message and return the response
                return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
            }

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        return $response->withJson($output, 201);
    }

    /**
     * List all order
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function getOrderList(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Fetch All record
        $model_payment_details = new PaymentDetails();

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
            $data = $model_payment_details->getListData($query_params, $other_values);

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
            if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_INACTIVE")) {
                $status = "STATUS_INACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE")) {
                $status = "STATUS_ACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_REMOVED")) {
                $status = "STATUS_REMOVED";
            } else {
                $status = "NA";
            }

            $transaction_date = DateTimeComponent::convertDateTime($row["transaction_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);

            $row_data = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "user_id" => $row["user_id"],
                "order_id" => $row["order_id"],
                "customer_id" => $row["customer_id"],
                "transaction_id" => $row["transaction_id"],
                "transaction_amount" => $row["transaction_amount"],
                "transaction_date" => $transaction_date,
                "bank_transaction_id" => $row["bank_transaction_id"],
                "bank_name" => $row["bank_name"],
                "payment_mode" => $row["payment_mode"],
                "status" => $status
            ];

            $output["rows"][] = $row_data;
        }

        return $response->withJson($output, 200);
    }

    /**
     * View single record
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     *
     * @return (object) Response object
     */
    public function viewProductImage(ServerRequestInterface $request, ResponseInterface $response, $args) {
        $output = [];

        // Get logged in user details
        // $logged_in_user = SHAppComponent::getUserId();
        // $logged_in_account = SHAppComponent::getAccountId();
        // $user_timezone = SHAppComponent::getUserTimeZone();

        // Get request parameters
        $route = $request->getAttribute("route");
        $id = $route->getArgument("prod_id");

        // Validate request
        if (empty($id)) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/BAD_REQUEST");
        }

        $row_id = StringComponent::decodeRowId($id);

        // Check if record is valid
        $model_product_image = new ProductImage();

        // Get user information
        try {
            $condition = [
                "fields" => [
                    "id",
                    "product_id",
                    "image_name",
                    "image_path",
                    "image_extension",
                    "is_default",
                    "status",
                    "created_date",
                    "modified_date",
                ],
                "where" => [
                    ["where" => ["product_id", "=", $row_id]]
                ]
            ];
            $product_image_data = $model_product_image->fetchAll($condition);

        } catch(\Exception $e) {
            // Fetch error code & message and return the response
            return ErrorComponent::outputError($response, "api_messages/DB_OPERATION_FAIL");
        }

        foreach($product_image_data as $row) {
            if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_INACTIVE")) {
                $status = "STATUS_INACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_ACTIVE")) {
                $status = "STATUS_ACTIVE";
            } else if ($row["status"] == SHAppComponent::getValue("app_constant/STATUS_REMOVED")) {
                $status = "STATUS_REMOVED";
            } else {
                $status = "NA";
            }

            $created_date = DateTimeComponent::convertDateTime($row["created_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);
            $modified_date = DateTimeComponent::convertDateTime($row["modified_date"], true, \DEFAULT_TIMEZONE, \USER_TIMEZONE, \APP_TIME_FORMAT);
            
            $data_row = [
                "id" => StringComponent::encodeRowId($row["id"]),
                "product_id" => StringComponent::encodeRowId($row["product_id"]),
                "image_name" => $row["image_name"],
                "image_path" => DOCUMENT_UPLOADED_DIRECTORY_SHOW . $row["image_path"],
                "image_extension" => $row["image_extension"],
                "is_default" => $row["is_default"],
                "status" => $status,
                "created_date" => $created_date,
                "modified_date" => $modified_date
            ];

            $output["rows"][] = $data_row;
        }

        return $response->withJson($output, 200);
    }

}
