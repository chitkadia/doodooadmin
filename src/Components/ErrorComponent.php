<?php
/**
 * Library for outputing API error responses
 */
namespace App\Components;

class ErrorComponent {

    /**
     * Output error response
     *
     * @param $response (object): Response object
     * @param $error_path (string): Path to error details
     * @param $additional_message (string): Additional error message to be printed along with api_messages table message
     *
     * @return (object) Response object
     */
    public static function outputError(\Psr\Http\Message\ResponseInterface &$response, $error_path, $additional_message = null, $append_addition_message = true) {
        $error_details = SHAppComponent::getValue($error_path);

        $output = [];
        $output["error"] = true;
        $output["error_code"] = $error_details["error_code"];
        $output["error_message"] = $error_details["error_message"];

        if (!empty($additional_message)) {
            if($append_addition_message){
                $output["error_message"] .=" ". $additional_message;
            }else{
                $output["error_message"] = $additional_message;
            }
        }

        return $response->withJson($output, $error_details["http_code"])
                    ->withHeader("Access-Control-Allow-Origin", \CORS_ALLOW_ORIGIN)
                    ->withHeader("Access-Control-Allow-Headers", \CORS_ALLOW_HEADER)
                    ->withHeader("Access-Control-Allow-Methods", \CORS_ALLOW_METHOD);
    }

}