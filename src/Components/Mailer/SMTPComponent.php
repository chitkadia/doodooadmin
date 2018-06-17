<?php
/**
 * Library for sending emails using SMTP accounts
 */
namespace App\Components\Mailer;

class SMTPComponent {
    /**
	 * Send Email with User's SMTP
	 * 
	 * @param $info (array): Array of SMTP details and mail sending variables
	 *
	 * @return (array) Array with result message
	 */
	public static function mailSendUserSmtp($info) {

		$return_data = array(
			"message" => "",
			"success" => false
		);

		// check if smtp data passed or not
		if(empty($info["smtp_details"])) {
			$return_data["message"] = "No smtp details provided while sending mail";
			$return_data["success"] = false;

			return $return_data;
		}

		$smtp_debug = 0;
		$debug_output = 'echo';
		$smtp_auth = true;

		// smtp details
		$info["smtp_details"]["host"] = trim($info["smtp_details"]["host"]);
		$info["smtp_details"]["username"] = trim($info["smtp_details"]["username"]);
		$info["smtp_details"]["password"] = trim($info["smtp_details"]["password"]);
		$info["smtp_details"]["port"] = trim($info["smtp_details"]["port"]);
		$info["smtp_details"]["encryption"] = trim($info["smtp_details"]["encryption"]);
        // varibles required for sending email
		//$info["to"] = trim($info["to"]);
		$info["cc"] = trim($info["cc"]);
		$info["bcc"] = trim($info["bcc"]);
		$info["subject"] = trim($info["subject"]);
		$info["content"] = trim($info["content"]);

		$info["from_email"] = trim($info["from_email"]);
		$info["from_name"] = trim($info["from_name"]);
		//PHPMailer code
		try {
			$mail = new \PHPMailer(true);
			$mail->isSMTP();
			$mail->SMTPDebug = $smtp_debug;
			$mail->Debugoutput = $debug_output;
			$mail->Host = $info["smtp_details"]["host"];
			$mail->Port = $info["smtp_details"]["port"];
			$mail->SMTPAuth = $smtp_auth;
			$mail->CharSet = "UTF-8";
			$mail->SMTPSecure = $info["smtp_details"]["encryption"];
			$mail->Username = $info["smtp_details"]["username"];
			$mail->Password = $info["smtp_details"]["password"];
			
			$mail->setFrom($info["from_email"], $info["from_name"]);
			$mail->addReplyTo($info["from_email"]);
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
			$mail->Subject = $info["subject"];
			$mail->msgHTML($info["content"]);
			
			if ($mail->send()) {
				$return_data["message_id"] = $mail->getLastMessageID();
			    $return_data["message"] = "Message sent successfully!";
			    $return_data["success"] = true;
			} else {
			    $return_data["message"] = $mail->ErrorInfo;
			    $return_data["success"] = false;
			}
		} catch (\phpmailerException $e) {
		    $return_data["message"] = $e->errorMessage(); //Pretty error messages from PHPMailer
		    $return_data["success"] = false;
		} catch (\Exception $e) {
			$return_data["message"] = $e->getMessage();
			$return_data["success"] = false;
		}

		return $return_data;
    }
}