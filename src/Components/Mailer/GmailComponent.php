<?php
/**
 * Library for sending emails using GMAIL accounts
 */
namespace App\Components\Mailer;

use \App\Components\DateTimeComponent;
use \App\Components\LoggerComponent;

class GmailComponent {

	/**
	 * Get access token
	 *
	 * @param $refresh_token (string): refresh token used to get access token
	 *
	 * @return (string) access token
	 */
	public static function getAccessTokenFromRefreshToken($refresh_token="") {
		//create and initialize Google Client object
        $client = new \Google_Client();
        $client->addScope("email");
        $client->addScope("profile");
        $client->addScope("https://mail.google.com");
        $client->setAuthConfig(\CLIENT_SECRET_PATH);
        $client->setAccessType('offline');
		$client->setIncludeGrantedScopes(true);
		$client->setApprovalPrompt('force');
		
		$client->refreshToken($refresh_token);
		$res = $client->getAccessToken();
		return $res['access_token'];
	}

	/**
	 * Send email
	 *
	 * @param $info: array of misc data
	 * ---- $info["subject"]: mail subject
	 * ---- $info["content"]: mail content
	 * ---- $info["to"]: mail address to where send message
	 * ---- $info["cc"]: cc mail
	 * ---- $info["bcc"]: bcc mail
	 * ---- $info["from_email"]: from email address of user
	 * ---- $info["from_name"]: from name of user
	 * ---- $info["reply_to"]: where reply should be sent
	 * ---- $info["refresh_token"]: refresh token of gmail
	 * ---- $info["thread_id"]: thread id of gmail mail
	 * @param $thread: true if want to send message in thread, false if simple send with gmail
	 *
	 * @return $return_data: array of misc data
	 */
	public static function sendMailGmail($info, $thread=false) {
        $return_data = [
			"sent" => false,
			"bounce" => 0,
			"error_message" => "",
			"success" => false
		];
		
		//create and initialize Google Client object        
        $client = new \Google_Client();
        $client->addScope("email");
        $client->addScope("profile");
        $client->addScope("https://mail.google.com");
        $client->setAuthConfig(\CLIENT_SECRET_PATH);
        $client->setAccessType('offline');
		$client->setIncludeGrantedScopes(true);
		$client->setApprovalPrompt('force');

        //create Google Service object
        $service = new \Google_Service_Gmail($client);

        //retrive user's information
        //$info["to"] = trim($info["to"]);
		$info["cc"] = trim($info["cc"]);
		$info["bcc"] = trim($info["bcc"]);
		$info["subject"] = trim($info["subject"]);
		$info["content"] = trim($info["content"]);
		$info["from_email"] = trim($info["from_email"]);
		$info["from_name"] = trim($info["from_name"]);
		$info["reply_to"] = trim($info["reply_to"]);

		//create and initialize Mailer object
		$mail = new \PHPMailer(true);
		$mail->CharSet = "UTF-8";
		$subject = $info["subject"];
		$msg = $info["content"];
		$femail = $info["from_email"];
		$fname = $info["from_name"];
		$mail->setFrom($femail,$fname);
		$mail->addReplyTo($femail,$fname);
		if (is_array($info["to"])) {
			foreach ($info["to"] as $to) {
				$mail->addAddress($to);
			}
		} else {
			$mail->addAddress($info["to"]);
		}
		if(!empty($info["cc"])) {
			$cc_arr = explode(",", $info["cc"]);
			foreach ($cc_arr as $cc) {
				$mail->addCC($cc);
			}
		}
		if(!empty($info["bcc"])) {
			$bcc_arr = explode(",", $info["bcc"]);
			foreach ($bcc_arr as $bcc) {
				$mail->addBCC($bcc);
			}
		}
		$mail->Subject = $subject;
		$mail->msgHTML($msg);
		$mail->preSend();
		$mime = $mail->getSentMIMEMessage();
		$data = base64_encode($mime);
		$data = str_replace(array('+','/','='),array('-','_',''),$data); // url safe
		
		//create Google message object
		$msg = new \Google_Service_Gmail_Message($client);
		//if message needs to send in same thread
		if($thread) {
			$msg->setThreadId($info["thread_id"]);
		}		
		$msg->setRaw($data);

		//check if access_token is expired
		if ($client->isAccessTokenExpired()) {
			$client->refreshToken($info["refresh_token"]);
		}

		if (!empty($info["draft_id"])) {
			if ($client->isAccessTokenExpired()) {
				$client->refreshToken($info["refresh_token"]);
			}
			//sent mail usign draft_id with gmail draft service
			$draft_body = new \Google_Service_Gmail_Draft($client);
			//$draft_body->setMessage($msg);
			$draft_body->setId($info["draft_id"]);

			try {
				$draft = $service->users_drafts->send('me', $draft_body);
				
				if(!empty($draft->getId())) {
					$return_data["sent"] = $draft;
					$return_data["gmail_message_id"] = $draft->getId();
					$return_data["gmail_message_thread_id"] = $draft->getThreadId();
					$return_data["success"] = true;
				} else {
					$return_data["error_message"] = "Mail cannot be sent";
					$return_data["success"] = false;
				}
			} catch(\Exception $e) {
				$return_data["error_message"] = $e->getMessage();
				$return_data["success"] = false;
			}
		} else {
			try {
	        	//send email
				$user_id = "me";
				$message = $service->users_messages->send($user_id, $msg);
				
				if(!empty($message->getId())) {
					$return_data["sent"] = $message;
					$return_data["gmail_message_id"] = $message->getId();
					$return_data["gmail_message_thread_id"] = $message->getThreadId();
					$return_data["success"] = true;
				} else {
					$return_data["error_message"] = "Mail cannot be sent";
					$return_data["success"] = false;
				}

			} catch(\Exception $e) {
				$return_data["error_message"] = $e->getMessage();
				$return_data["success"] = false;
			}
		}
		return $return_data;
    }

    /**
     * Get thread id
     *
     * @param $message_id (string): message id used to get thread id
     * @param $refresh_token (string): refresh token used to get access token
     *
     * @return (string) thread id
     */
    public static function getThreadIdFromMessageId($message_id="", $refresh_token="", $access_token="") {
    	//create and initialize Google Client object
    	$client = new \Google_Client();
        $client->addScope("email");
        $client->addScope("profile");
        $client->addScope("https://mail.google.com");
        $client->setAuthConfig(\CLIENT_SECRET_PATH);
        $client->setAccessType('offline');
		$client->setIncludeGrantedScopes(true);
		$client->setApprovalPrompt('force');
		$client->setAccessToken($access_token);
        //create Google Service object
        $service = new \Google_Service_Gmail($client);

        //check if access_token is expired
		if ($client->isAccessTokenExpired()) {
			$client->refreshToken($refresh_token);
		}

        try {
        	//get message
        	$user_id = "me";
        	$message = $service->users_messages->get($user_id, $message_id);
        	$return_data = $message->getThreadId();
        } catch (\Exception $e) {
		    $return_data["error_message"] = 'An error occurred: ' . $e->getMessage();
		}
        
        return $return_data;
    }

	  /**
     * Check reply of message by thread id
     *
     * @param $refresh_token (string): refresh token used to get access token
	 * @param $message_id (string): message id of sent message
	 * @param $thread_id (string): thread id of sent message
	 *
	 * @return $return_data: array of misc data
     */
    public static function checkAndRefreshAccessToken($refresh_token=null, $access_token=null) {
		$return_data = array("expired"=>true);
		
    	if(!empty($refresh_token)) {
    		//create and initialize Google Client object
	    	$client = new \Google_Client();
	        $client->addScope("email");
	        $client->addScope("profile");
	        $client->addScope("https://mail.google.com");
	        $client->setAuthConfig(\CLIENT_SECRET_PATH);
	        $client->setAccessType('offline');
			$client->setIncludeGrantedScopes(true);
			$client->setApprovalPrompt('force');
			$client->setAccessToken($access_token);
	        //create Google Service object
	        $service = new \Google_Service_Gmail($client);

	        //check if access_token is expired
			if ($client->isAccessTokenExpired()) {			
				$output = $client->refreshToken($refresh_token);
				$return_data = $output;
				$return_data["expired"] = true;
				
			}else{
				
				$return_data = array("expired"=>false);
			}
		}
		return $return_data;
	}
    /**
     * Check reply of message by thread id
     *
     * @param $refresh_token (string): refresh token used to get access token
	 * @param $message_id (string): message id of sent message
	 * @param $thread_id (string): thread id of sent message
	 *
	 * @return $return_data: array of misc data
     */
    public static function checkGmailReply($refresh_token=null, $message_id=null, $thread_id=null, $log_file_name=null, $access_token=null) {
    	$return_data = array("replied"=>false, "replied_at"=>null, "bounce"=>false, "snippet"=>"", "reply_from"=>"");
    	if(!empty($refresh_token) && !empty($message_id) && !empty($thread_id)) {
    		//create and initialize Google Client object
	    	$client = new \Google_Client();
	        $client->addScope("email");
	        $client->addScope("profile");
	        $client->addScope("https://mail.google.com");
	        $client->setAuthConfig(\CLIENT_SECRET_PATH);
	        $client->setAccessType('offline');
			$client->setIncludeGrantedScopes(true);
			$client->setApprovalPrompt('force');
			$client->setAccessToken($access_token);
	        //create Google Service object
	        $service = new \Google_Service_Gmail($client);

	        //check if access_token is expired
			if ($client->isAccessTokenExpired()) {
				$client->refreshToken($refresh_token);
			}

		    try {
		        $user_id = "me";
		        $thread = $service->users_threads->get($user_id, $thread_id);
		        $messages = $thread->getMessages();
				
	        	if(!empty($messages)) {
					if(is_array($messages) && count($messages) > 1) {
						$message_thread_id = $messages[0]["threadId"];
						
						// TO DO: Check below code and output (currently copied from old working code)
						// Implementation logic can be better
						$own_mail = 0;
						foreach($messages as $message) {
							if($message->getId() == $message_id) {
								$own_mail = 1;
							}
							// $message->getId() != $message_id  and own_mail = 1 both are required
							// When 1st mail send in that same thread 2nd mail sent. you are checking reply for 2nd mail so in message list first is not your mail
							// so 1st condition id not match, so you have to continue for check in loop 2nd mail is your so own mail flag become true.
							// now if there is another message in that thread then id is not match it is reply.
							if($own_mail == 1 && $message->getThreadId() == $message_thread_id && $message->getId() != $message_id && !empty($message->getLabelIds())) {
								if(($key = array_search("INBOX", $message->getLabelIds())) !== false || 
									($key = array_search("CATEGORY_PERSONAL", $message->getLabelIds())) !== false) {
									
									if(!empty($message->getInternalDate())) {
										$message_epoch = ($message->getInternalDate() / 1000);
										// $dt_obj = new \DateTime("@$message_epoch");
										// $gmt_message_time = $dt_obj->format('Y-m-d H:i:s');										
										$return_data["replied_at"] = DateTimeComponent::convertDateTime($message_epoch, true, \DEFAULT_TIMEZONE, \DEFAULT_TIMEZONE, \DEFAULT_TIME_FORMAT);
										
									} else {
										$gmt_message_time = gmdate('Y-m-d H:i:s');
										$return_data["replied_at"] = DateTimeComponent::convertDateTime($gmt_message_time, false, \DEFAULT_TIMEZONE, \DEFAULT_TIMEZONE, \DEFAULT_TIME_FORMAT);	
									}

									$format = "Y-m-d H:i:s";
									$return_data["replied"] = true;								
									// $return_data["replied_at"] = DateTimeComponent::convertDateTime($gmt_message_time, false, "GMT", "Asia/Calcutta", $format);	
								
									if(!empty($message->getSnippet())) {
										$return_data["snippet"] = $message->getSnippet();
									}

									// Check if reply is bounce
									$failed_to_email = "";
									$failed_from_email = "";
									$mail_subject_payload = "";
									if(!empty($message->getPayload()->getHeaders())) {
										foreach($message->getPayload()->getHeaders() as $hk => $hv) {
											if(strtolower($hv["name"]) == "x-failed-recipients") {
												$failed_to_email = trim($hv["value"]);

											} else if(strtolower($hv["name"]) == "from") {
												$failed_from_email = trim($hv["value"]);
												$return_data["reply_from"] = trim($hv["value"]);

											} else if(strtolower($hv["name"]) == "subject") {
												$mail_subject_payload = trim($hv["value"]);
											}
										}

										$failed_from_email = strtolower($failed_from_email);
										$failed_to_email = strtolower($failed_to_email);
										$mail_subject_payload = strtolower($mail_subject_payload);

										// Check if reply has come from mailer-daemon
										if(preg_match("/mailer-daemon/", $failed_from_email) || 
											preg_match("/mailerdaemon/", $failed_from_email)) {

											$return_data["replied"] = false;
											$return_data["bounce"] = true;
											if(empty($return_data["snippet"])){
												$return_data["snippet"] = $failed_from_email;
											}
											if(!empty($log_file_name)){
												LoggerComponent::log("Mail bounce due to ".$failed_from_email, $log_file_name);
											}
										}

										if(!$return_data["bounce"] && !empty($mail_subject_payload)) {
											// Check if subject contain bounce related keywords
											if(preg_match("/undelivered/", $mail_subject_payload) || 
												preg_match("/undeliverable/", $mail_subject_payload) || 
												preg_match("/delivery status notification/", $mail_subject_payload)) {

												$return_data["replied"] = false;
												$return_data["bounce"] = true;
												if(empty($return_data["snippet"])){
													$return_data["snippet"] = $mail_subject_payload;
												}
												if(!empty($log_file_name)){
													LoggerComponent::log("Mail bounce due to ".$mail_subject_payload, $log_file_name);
												}
											}
										}

										if(!$return_data["bounce"] && !empty($return_data["snippet"])) {
											$snippet = html_entity_decode(strtolower($return_data["snippet"]), \ENT_QUOTES);

											// Check if mail message contain bounce related keywords
											if(preg_match("/address not found/", $snippet) || 
												preg_match("/could not be delivered/", $snippet) || 
												preg_match("/couldn't be delivered/", $snippet) || 
												preg_match("/could not be found/", $snippet) || 
												preg_match("/couldn't be found/", $snippet) || 
												preg_match("/message not delivered/", $snippet) || 
												preg_match("/delivery incomplete/", $snippet) || 
												preg_match("/message blocked/", $snippet) || 
												preg_match("/message was not sent/", $snippet) || 
												(
													preg_match("/delivery/", $snippet) && 
													preg_match("/failed/", $snippet)
												)) {

												$return_data["replied"] = false;
												$return_data["bounce"] = true;

												if(!empty($log_file_name)){
													LoggerComponent::log("Mail bounce due to ".$snippet, $log_file_name);
												}
											}
										}
									}
									break;
								}
							}
						}
					}
				}
		    } catch (\Exception $e){
				if(!empty($log_file_name)){
					LoggerComponent::log('An error occurred: ' . $e->getMessage(). " Return data ".$return_data, $log_file_name);
				}
				$return_data["error_message"] = 'An error occurred: ' . $e->getMessage();
		    }
    	}
    	return $return_data;
    }

    /**
     * Read Message from gmail using message ID
     * 
     * @param $message_id (string) Id of message
     * @param $refresh_token (string) refresh token for gmail client
  	 *
  	 * @return (array) return info of message 
     */
    public static function getMessage($message_id = null, $refresh_token = null) {
    	$content = "";

    	if (!empty($message_id) && !empty($refresh_token)) {
    		//Initialize google client library object
	    	$client = new \Google_Client();
	        $client->addScope("email");
	        $client->addScope("profile");
	        $client->addScope("https://mail.google.com");
	        $client->setAuthConfig(\CLIENT_SECRET_PATH);
	        $client->setAccessType('offline');
			$client->setIncludeGrantedScopes(true);
			$client->setApprovalPrompt('force');
			//$client->setAccessToken($access_token);

	        //Intialize google service object
	        $service = new \Google_Service_Gmail($client);
	       
	        //check if access_token is expired
			if ($client->isAccessTokenExpired()) {
				$client->refreshToken($refresh_token);
			}

			try {
				$user_id = "me";
				$message = $service->users_messages->get($user_id, $message_id);
				$content = $message->getPayload()->getParts()[1]->getBody()->getData();
				$sanitizedData = strtr($content,'-_', '+/');
				$content = base64_decode($sanitizedData);
				
			} catch (\Exception $e) {
				//do something
			}
    	}

    	return $content;
    }

}