<?php
/**
 * Library for sending push notification to the user's connected with difference sources Via NODE PubSub Server
 */
namespace App\Components;

use \App\Components\ModelValidationsComponent as Validator;

class PushNotificationComponent {

 	/**
 	 * Function to set message
 	 */
	public static function sendPushNotification($arrParams = array()) {
 		
 		/* Array to retrun response to this function*/
		$arrReturn = array();
		$arrReturn['flagError'] = 0;
		$arrReturn['arrErrors'] = array();

		// Set validations and valid sending account
		$request_validations["message"] = [
			["type" => Validator::FIELD_REQ_NOTEMPTY]
		];

		$request_validations["enc_user_id"] = [
			["type" => Validator::FIELD_REQ_NOTEMPTY]
		];

		$request_validations["title"] = [
			["type" => Validator::FIELD_REQ_NOTEMPTY]
		];
 		
 		/* There is no validation for meta_data as the metadata will have no fixed structure. It may have string, array or Object*/
		$meta_data = "";
		if(isset($arrParams['meta_data']) && !empty($arrParams['meta_data'])) {
			$meta_data = $arrParams['meta_data'];
		}

		$validation_errors = Validator::validate($request_validations, $arrParams);
 		
 		// if any validation error found
		if(!empty($validation_errors)) {
			$arrReturn['flagError'] = 1;
			$arrReturn['arrErrors'] = $validation_errors;
		}
		else {

			/* Creating Payload to send via push notification */
 			$arrParamsToSendToNodeServer = array();
 			$arrParamsToSendToNodeServer['message'] = base64_encode(strip_tags($arrParams['message']));
 			$arrParamsToSendToNodeServer['title'] = base64_encode($arrParams['title']);
 			$arrParamsToSendToNodeServer['meta_data'] = $meta_data;
 			$arrParamsToSendToNodeServer['enc_user_id'] = $arrParams['enc_user_id'];
 			
			$strJsonUrlEncodedData = urlencode(json_encode($arrParamsToSendToNodeServer));
 			
 			/* Preparing command for push notification */
 			$commandToExecute = "php " . ROOT_PATH . "cron/command.php /send-push-notification/" . $strJsonUrlEncodedData . " > /dev/null &";

			/* Executing command that will run in background. */
			exec($commandToExecute, $arrOutPut);
		}

		return $arrReturn;
	}



}