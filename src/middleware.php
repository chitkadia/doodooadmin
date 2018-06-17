<?php

/**
 * Register application wide layers
 */
// Register middleware if request comes from API
if (defined("SH_API_ENDPOINT")) {
    $sh_app->add(function (\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, $next) {
        // If request is not CORS
        if (!$request->isOptions()) {

            // Check if database connection failed
            $db = new \App\Models\AppVarsModel();
            if (!$db->dbConnected()) {
                // Fetch error code & message and return the response
                return \App\Components\ErrorComponent::outputError($response, "api_messages/DB_CONNECTION_FAIL");
            }

            // Get route
            $route_pattern = $request->getAttribute("route");
            $route = null;
            if (!is_null($route_pattern)) {
                $route = $route_pattern->getPattern();
            }

            // Get headers
            $header_content_type = $request->getHeaderLine("Content-Type");
            $header_accept = $request->getHeaderLine("Accept");
            $header_source = $request->getHeaderLine("X-SH-Source");
            $header_auth_token = $request->getHeaderLine("X-Authorization-Token");

            if ($route == "/product/{id}/image-upload" || $route == "/product/{id}/{img_id}/image-upload") {
                // Check if source header is present and valid
                if (!$request->hasHeader("X-SH-Source")) {
                    // Fetch error code & message and return the response
                    return \App\Components\ErrorComponent::outputError($response, "api_messages/SOURCE_MISSING");
                }

                $valid_sources = \App\Components\SHAppComponent::getValue("source");
                if (!isset($valid_sources[$header_source])) {
                    // Fetch error code & message and return the response
                    return \App\Components\ErrorComponent::outputError($response, "api_messages/INVALID_SOURCE");
                }

                \App\Components\SHAppComponent::setRequestSource($valid_sources[$header_source]);
            } else if ($route == "/payment-response") {

            } else {
                // Check required headers
                if (!$request->hasHeader("Content-Type") || (!$request->hasHeader("Accept") || $header_accept == "*/*")) {
                    $additional_message = "";
                    if (!$request->hasHeader("Content-Type")) {
                        $additional_message .= "Content-Type, ";
                    }
                    if (!$request->hasHeader("Accept") || $header_accept == "*/*") {
                        $additional_message .= "Accept, ";
                    }
                    $additional_message = rtrim($additional_message, ", ");

                    // Fetch error code & message and return the response
                    return \App\Components\ErrorComponent::outputError($response, "api_messages/REQUIRED_HEADER_MISSING", $additional_message);
                }

                if ($header_content_type != "application/json;charset=UTF-8" || $header_accept != "application/json") {

                    $additional_message = "";
                    if ($header_content_type != "application/json;charset=UTF-8") {
                        $additional_message .= "Content-Type, ";
                    }
                    if ($header_accept != "application/json") {
                        $additional_message .= "Accept, ";
                    }
                    $additional_message = rtrim($additional_message, ", ");

                    // Fetch error code & message and return the response
                    return \App\Components\ErrorComponent::outputError($response, "api_messages/INVALID_HEADER_VALUE", $additional_message);
                }

                // Check if source header is present and valid
                if (!$request->hasHeader("X-SH-Source")) {
                    // Fetch error code & message and return the response
                    return \App\Components\ErrorComponent::outputError($response, "api_messages/SOURCE_MISSING");
                }

                $valid_sources = \App\Components\SHAppComponent::getValue("source");
                if (!isset($valid_sources[$header_source])) {
                    // Fetch error code & message and return the response
                    return \App\Components\ErrorComponent::outputError($response, "api_messages/INVALID_SOURCE");
                }

                \App\Components\SHAppComponent::setRequestSource($valid_sources[$header_source]);
            }

            // If route found - then only check remaining checks
            if (!empty($route)) {
                // Get public resources
                $public_resources = \App\Components\SHAppComponent::getValue("public_resources");
                if (!isset($public_resources[$route])) {
                    // Check authentication
                    if (!$request->hasHeader("X-Authorization-Token")) {
                        // Fetch error code & message and return the response
                        return \App\Components\ErrorComponent::outputError($response, "api_messages/LOGIN_REQUIRED");
                    }
                }

                // Get default resources
                $default_resources = \App\Components\SHAppComponent::getValue("default_resources");

                $token_info = \App\Components\StringComponent::decodeRowId($header_auth_token, true);
                $current_time = \App\Components\DateTimeComponent::getDateTime();
                $status_active = \App\Components\SHAppComponent::getValue("app_constant/STATUS_ACTIVE");
                $status_delete = \App\Components\SHAppComponent::getValue("app_constant/STATUS_DELETE");
                $status_removed = \App\Components\SHAppComponent::getValue("app_constant/STATUS_REMOVED");
                $user_timezone = \App\Components\SHAppComponent::getValue("app_constant/U_TIMEZONE");

                if (!empty($token_info)) {
                    // Check if token is valid
                    $model = new \App\Models\UserAuthenticationTokens();

                    $condition = [
                        "fields" => [
                            "uat.user_id",
                            "uat.user_resources",
                            "uat.expires_at",
                            "um.role_id",
                            "um.user_type_id",
                            "um.status AS user_status",
                            "apd.plan_id"
                        ],
                        "where" => [
                            ["where" => ["uat.user_id", "=", $token_info[0]]],
                            ["where" => ["uat.source_id", "=", $token_info[1]]],
                            ["where" => ["uat.generated_at", "=", $token_info[2]]]
                        ],
                        "join" => [
                            "user_master",
                            "active_plan_details"
                        ]
                    ];
                    $row = $model->fetch($condition);

                    if (!isset($public_resources[$route])) {
                        if (empty($row["user_id"])) {
                            // Fetch error code & message and return the response
                            return \App\Components\ErrorComponent::outputError($response, "api_messages/INVALID_AUTH_TOKEN");
                        }

                        if ($row["expires_at"] < $current_time) {
                            // Fetch error code & message and return the response
                            return \App\Components\ErrorComponent::outputError($response, "api_messages/AUTH_TOKEN_EXPIRED");
                        }

                        if ($row["user_status"] == $status_delete) {
                            // Fetch error code & message and return the response
                            return \App\Components\ErrorComponent::outputError($response, "api_messages/USER_DELETED");
                        }

                        if ($row["user_status"] != $status_active && $row["user_status"] != $status_removed) {
                            // Fetch error code & message and return the response
                            return \App\Components\ErrorComponent::outputError($response, "api_messages/USER_ACCOUNT_NOT_ACTIVE");
                        }

                        // if (!isset($default_resources[$route])) {
                        //     $flag_yes = \App\Components\SHAppComponent::getValue("app_constant/FLAG_YES");
                        //     if ($row["verified"] != $flag_yes) {
                        //         // Fetch error code & message and return the response
                        //         return \App\Components\ErrorComponent::outputError($response, "api_messages/USER_NOT_VERIFIED");
                        //     }
                        // }
                    }

                    $user_resources = json_decode($row["user_resources"], true);

                    \App\Components\SHAppComponent::setUserId($row["user_id"]);
                    // \App\Components\SHAppComponent::setAccountId($row["account_id"]);
                    \App\Components\SHAppComponent::setPlanId($row["plan_id"]);
                    \App\Components\SHAppComponent::setUserRole($row["role_id"]);
                    \App\Components\SHAppComponent::setUserType($row["user_type_id"]);
                    \App\Components\SHAppComponent::setUserResources($user_resources);

                    // Get timezone and set it
                    $time_zone_set = \DEFAULT_TIMEZONE;
                    \App\Components\SHAppComponent::setUserTimeZone($time_zone_set);

                    // Get current user plan information and set it
                    // $model_abm = new \App\Models\AccountBillingMaster();

                    // $condition_abm = [
                    //     "fields" => [
                    //         "configuration",
                    //         "email_acc_seats"
                    //     ],
                    //     "where" => [
                    //         ["where" => ["account_id", "=", \App\Components\SHAppComponent::getAccountId()]]
                    //     ]
                    // ];
                    // $row_plan_configuration = $model_abm->fetch($condition_abm);

                    // if (!empty($row_plan_configuration)) {
                    //     $configuration = json_decode($row_plan_configuration["configuration"], true);
                    // } else {
                    //     $configuration = [];
                    // }
                    // \App\Components\SHAppComponent::setPlanConfiguration($configuration);
                    // \App\Components\SHAppComponent::setEmailaccountSeat($row_plan_configuration["email_acc_seats"]);
                }

                if (!isset($default_resources[$route])) {
                    // Check if user has access or not
                    $get_user_resources = \App\Components\SHAppComponent::getUserResources();

                    $get_plan_configuration = \App\Components\SHAppComponent::getPlanConfiguration();

                    /*if (strpos($route, "campaign") !== false) {
                        if (!isset($get_plan_configuration["ea_plan"])) {
                            // Fetch error code & message and return the response
                            return \App\Components\ErrorComponent::outputError($response, "api_messages/UPGRADE_ACCOUNT");
                        }
                    }*/

                    if (!isset($get_user_resources[$route])) {
                        // Fetch error code & message and return the response
                        return \App\Components\ErrorComponent::outputError($response, "api_messages/NOT_AUTHORIZED");
                    }
                }
            }

            // Call next middleware
            $response = $next($request, $response);
        }

        return $response->withHeader("Access-Control-Allow-Origin", \CORS_ALLOW_ORIGIN)
                ->withHeader("Access-Control-Allow-Headers", \CORS_ALLOW_HEADER)
                ->withHeader("Access-Control-Allow-Methods", \CORS_ALLOW_METHOD);
    });
}