<?php
/**
 * Setup application wide Dependency Injection (Services)
 */
$app_container = $sh_app->getContainer();

// If request comes from API - register common handlers
if (defined("SH_API_ENDPOINT")) {
    // Handle 404 requests
    $app_container["notFoundHandler"] = function($c) {
        return function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response) use ($c) {

            // Fetch error code & message and return the response
            return \App\Components\ErrorComponent::outputError($response, "api_messages/REQUEST_NOT_FOUND");
        };
    };

    // Handle HTTP method invalid
    $app_container["notAllowedHandler"] = function($c) {
        return function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, $methods) use ($c) {

            // Fetch error code & message and return the response
            return \App\Components\ErrorComponent::outputError($response, "api_messages/INVALID_HTTP_METHOD")
                        ->withHeader("Allow", implode(", ", $methods));
        };
    };

    // Handle fatal errors
    /*
    $app_container["errorHandler"] = function($c) {
        return function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, \Exception $exception) use ($c) {

            // Fetch error code & message and return the response
            return \App\Components\ErrorComponent::outputError($response, "api_messages/SERVER_EXCEPTION");
        };
    };
    $app_container["phpErrorHandler"] = function($c) {
        return function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, \Throwable $exception) use ($c) {

            // Fetch error code & message and return the response
            return \App\Components\ErrorComponent::outputError($response, "api_messages/SERVER_EXCEPTION");
        };
    };
    */
}

// If request comes from Commands - register common handlers
/*
if (defined("SH_COMMANDS")) {
    // Handle 404 requests
    $app_container["notFoundHandler"] = function($c) {
        return function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response) use ($c) { };
    };

    // Handle HTTP method invalid
    $app_container["notAllowedHandler"] = function($c) {
        return function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, $methods) use ($c) { };
    };

    // Handle fatal errors
    $app_container["errorHandler"] = function($c) {
        return function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, \Exception $exception) use ($c) { };
    };
    $app_container["phpErrorHandler"] = function($c) {
        return function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, \Throwable $exception) use ($c) { };
    };
}
*/

// If request comes from Track - register common handlers
if (defined("SH_TRACK")) {
    // Handle 404 requests
    $app_container["notFoundHandler"] = function($c) {
        return function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response) use ($c) { 
               // Fetch error code & message and return the response
            return \App\Components\DisplayPixelComponent::displayPixel($response);
        };
    };

    // Handle HTTP method invalid
    $app_container["notAllowedHandler"] = function($c) {
        return function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, $methods) use ($c) {

            return \App\Components\DisplayPixelComponent::displayPixel($response);
        };
    };

    // Handle fatal errors
    $app_container["errorHandler"] = function($c) {
        return function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, \Exception $exception) use ($c) {

            return \App\Components\DisplayPixelComponent::displayPixel($response);
        };
    };
    $app_container["phpErrorHandler"] = function($c) {
        return function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, \Throwable $exception) use ($c) { 
            
            return \App\Components\DisplayPixelComponent::displayPixel($response);
        };
    };
}

// If request comes from document viewer - register common handlers
if (defined("SH_DOCS")) {
    // Handle 404 requests
    $app_container["notFoundHandler"] = function($c) {
        return function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response) use ($c) {

            // Show page not found message
            return \App\Viewer\DocumentViewer::showPageNotFound($response);
        };
    };

    // Handle HTTP method invalid
    $app_container["notAllowedHandler"] = function($c) {
        return function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, $methods) use ($c) {

            // Show error message
            return \App\Viewer\DocumentViewer::showErrorPage($response)->withHeader("Allow", implode(", ", $methods));
        };
    };

    // Handle fatal errors
    $app_container["errorHandler"] = function($c) {
        return function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, \Exception $exception) use ($c) {

            // Show error message
            return \App\Viewer\DocumentViewer::showErrorPage($response);
        };
    };
    $app_container["phpErrorHandler"] = function($c) {
        return function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, \Throwable $exception) use ($c) {

            // Show error message
            return \App\Viewer\DocumentViewer::showErrorPage($response);
        };
    };

    $app_container["cookie"] = function($c) {
        $request = $c->get("request");
        return new \Slim\Http\Cookies($request->getCookieParams());
    };
}