<?php
// Get required variables
$email_address = null;
$name = null;
$first_name = null;
$last_name = null;
$password = null;
$source = "WEB_APP";
$error_message = '';
$timezone = null;
$redirect_url = null;

if (isset($_POST["email"])) {
  $email_address = $_POST["email"];
}

if (isset($_POST["first_name"])) {
  $name = explode(" ",$_POST["first_name"]);
  if (!empty($name[0]) && $name[0] != null) {
    $first_name = trim($name[0]);
  }
  if (!empty($name[1]) && $name[1] != null) {
    $last_name = trim($name[1]);   
  }
}

if (isset($_POST["password"])) {
  $password = $_POST["password"];
}

if (isset($_POST["timezone"])) {
  $timezone = $_POST["timezone"];
}



$api_url = "https://api.cultofpassion.com/user/signup";
$error = true;
$user_auth_token = null;

// If code present, do get auth token from API
 if (!empty($email_address) && !empty($first_name) && !empty($password)) {
    try {
        $data = ["email"=>$email_address, "first_name"=>$first_name,"password"=>$password,"timezone" => $timezone];
        $post_data = json_encode($data);

        $headers = [
            "Content-Type: application/json;charset=UTF-8",
            "Content-Length: " . strlen($post_data),
            "Accept: application/json",
            "X-SH-Source: " . $source
        ];

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);

        $output_json = json_decode($result, true);
        $json_error = (bool) json_last_error();

        if (!$json_error) {
            if (!empty($output_json["auth_token"])) {
                $error = false;
                $user_auth_token = $output_json["auth_token"];
            }
        }

        if (isset($output_json["error_message"])) {
          $error_message = $output_json["error_message"];
        } else if (isset($user_auth_token) && !empty($user_auth_token)) {
          $redirect_url = "https://qaapi2.cultofpassion.com/login?access_token=".$user_auth_token."&admin_access_token=".$user_auth_token."&auth_signup=true";; ?>
	     <script>
    			var redirect_url = "<?php echo $redirect_url;?>";
    			if (redirect_url != '') {
    			 window.top.location.href = redirect_url;
    			}
    		</script>	
       <?php }
    } catch (Exception $e) {
        // Do nothing
    }
 }
?>
<!DOCTYPE html>
<html>
   <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="csrf-param" content="_csrf">
      <title></title>
      <style>
         body {
          background: none;
          line-height: 0.5;
          overflow: hidden;
          font-style: normal;
          font-weight: 400;
          direction: ltr;
          color: #6f7b8a;
          font-family: arial;
          font-size: 13px;
         }
         .form-item.website { display: none; }
         .help-block-error { color: #a94442 !important; }   
         .field-user-phone { position: relative; }
         .field-user-phone .help-block-error { position: absolute; top: 40px; left: 0 }
         .field-user-phone.has-error input { margin-bottom: 32px !important; }
     
         input[type="text"] {
         background-color: #fff;
         float: none !important;
         width:100% !important;
         }
         .btn-success {
         padding: 12px !important;
         }
         .btn-cons {
         min-width: 35%;
         }
         .has-success .form-control {
         border-color: #eee !important;
         }
       
         .has-error .form-control{
         border-color:#a1a1a1 !important;
         }
         input[type="password"]:focus{
         background: none;
         }
         input[type="radio"] {
         position: absolute;
         opacity: 0;
         -moz-opacity: 0;
         -webkit-opacity: 0;
         -o-opacity: 0;
         }
         input[type="radio"] + label {
         position: relative;
         font-size: 16px;
         cursor: pointer;
         }
         input[type="radio"] + label:before {
         content: "";
         display: block;
         position: absolute;
         top: 10px;
         left: 25px !important;
         transition: all 0.3s ease;
         -webkit-transition: all 0.3s ease;
         -moz-transition: all 0.3s ease;
         height: 14px;
         width: 14px;
         background: white;
         border: 1px solid gray;
         box-shadow: inset 0px 0px 0px 2px white;
         -webkit-box-shadow: inset 0px 0px 0px 2px white;
         -moz-box-shadow: inset 0px 0px 0px 2px white;
         -o-box-shadow: inset 0px 0px 0px 2px white;
         -webkit-border-radius: 8px;
         -moz-border-radius: 8px;
         -o-border-radius: 8px;
         }
         input[type="radio"]:checked + label:before {
         background: #00a044;
         left:25px !important;
         }
         .radio-button-social{
         background: #eee;
         text-align: center;
         padding: 5px;
         border-radius: 3px;
         /* height: 127px; */
         width: 48%;
         margin-top: 1.5%;
         float: left;
         margin-bottom: 10px;
         border: 1px solid #d0d0d0;
         }
         .radio-button-social label img{
         padding-top: 5px;
         margin-left: 15px;
         }
         .btn-div{
         width: 100%;
         float: left;
         text-align: center;
         font-family: lato !important;
         }
         .btn{
         font-size: 16px !important;
         font-family: lato !important;
         }
         .help-block {
         line-height: 1em;
         }  
      </style>
    </head>  
   <body>
      <center>
        <div style="text-align: center;font-family:Lato;font-weight:300;font-style:normal;font-size: 26px;padding: 16px;" class="vc_custom_heading headertextclass">Signup for 14 days free trial</div>
        <p style="text-align: center; padding: 0 0 10px 0; margin: 0px !important;padding-bottom: 10px;">No Risk, No Obligations, No Credit-Card Required</p>
  
            <link href="https://fonts.googleapis.com/css?family=Lato&amp;subset=latin,latin-ext" rel="stylesheet" type="text/css">
            <form id="login-form" class="login-form" method="post" style="width: 50%">
               <input type="hidden" name="referrer" id="referrer" value="signup">
               <div class="form-item sv">
                  <div class="form-group field-user-fname has-success">
                     <input type="text" id="user-fname" class="input-item" name="first_name" placeholder="Full Name *" required="required" aria-invalid="false" style="float:left;width:48%;margin-right:1%; text-transform: capitalize; height:45px; font-size:16px; font-family: lato !important; margin-bottom:1.5%;"/>
                     <p class="help-block help-block-error"></p>
                  </div>
               </div>
               <div class="form-item ss">
                  <div class="form-group field-user-email has-success">
                     <input type="email" id="user-lname" class="input-item" name="email" placeholder="Email *"  aria-invalid="false" required="required" style="float:left;width:48%;margin-right:1%; text-transform: capitalize; height:45px; font-size:16px; font-family: lato !important; margin-bottom:3.5%;width: 100%;"/>
                      <!-- <p class="help-block help-block-error">Email cannot be blank.</p> -->
                  </div>
               </div>
               <div class="form-item ss">
                  <div class="form-group field-user-password required has-error">
                     <input type="password" id="user-email" class="input-item" name="password" placeholder="Password *" required="required" aria-invalid="false" style="float:left;width:48%;margin-right:1%; text-transform: capitalize; height:45px; font-size:16px; font-family: lato !important; margin-bottom:1.5%;width: 100%;" minlength="8"/>
       
                  </div>
               </div>
                <input type="hidden" id="timezoneVal" name="timezone" value="">
               <?php if (!empty($error_message)) { ?>
                 <p style="color:red;"><?php echo $error_message;?></p>
               <?php } ?>  
               <div class="form-item" style="font-family: lato;" style="padding-bottom: 10px;"> 
                  <input type="checkbox" name="newsletter_chk" value="chk_yes" checked=""> Subscribe to Newsletter.
               </div>
               <div class="btn-div">
                  <button class="btn btn-success btn-cons" style="font-weight:bold;padding:12px;">Signup</button>
                  <?php if (isset($msg) && !empty($msg)) { echo '<h3>'. $msg . '</h3>'; } ?>
                  <p style="font-size: 11px;line-height: normal;color: #949494;text-align: center;">By clicking the “Signup” button above, you agree to the Legal terms &amp; you consent to receiving email communications from SalesHandy. You may opt-out of receiving further email at any time.</p>
               </div>

            </form>
       </center>
   </body>
    <script type="text/javascript">
           var userLocalTimezone;
           // To get signup user local timezone
            var offset_diff = new Date().getTimezoneOffset();

            if(offset_diff < 0) offset_diff *= -1;

            var offset_hrs = Math.floor(offset_diff / 60);
            var offset_mins = (offset_diff - (offset_hrs * 60));

            if(offset_hrs < 0) offset_hrs *= -1;
            if(offset_mins < 0) offset_mins *= -1;

            offset_hrs = (offset_hrs < 10) ? "0"+offset_hrs : offset_hrs;
            offset_mins = (offset_mins >= 30) ? 30 : 0;
            offset_mins = (offset_mins < 10) ? "0"+offset_mins : offset_mins;

            offset_diff = new Date().getTimezoneOffset();
            if(offset_diff < 0) {
                userLocalTimezone = "GMT+"+offset_hrs+":"+offset_mins;

            } else if(offset_diff > 0) {
                userLocalTimezone = "GMT-"+offset_hrs+":"+offset_mins;

            } else {
                userLocalTimezone = "GMT";
            }
      
         document.getElementById("timezoneVal").value = userLocalTimezone; 
      </script>
</html>
