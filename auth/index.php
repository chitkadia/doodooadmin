<?php
// Get required variables
$email_address = "";
$method = "GMAIL";
$source = "CHROME_PLUGIN";
$code = "";
$scope = "";

if (isset($_GET["email"])) {
    $email_address = trim($_GET["email"]);
}
if (isset($_GET["method"])) {
    $method = trim($_GET["method"]);
}
if (isset($_GET["source"])) {
    $source = trim($_GET["source"]);
}
if (isset($_GET["code"])) {
    $code = trim($_GET["code"]);
}
if (isset($_GET["scope"])) {
    $scope = trim($_GET["scope"]);
}

$api_url = "https://api.cultofpassion.com/user/loginwith/".$method;
$error = true;
$user_auth_token = null;

// If code present, do get auth token from API
if (!empty($code) && !empty($scope) && !empty($source)) {
    try {
        $data = ["code"=>$code, "scope"=>$scope];
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

    } catch (Exception $e) {
        // Do nothing
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, user-scalable=no" />
<title>SalesHandy - Authorization</title>
<link href="https://fonts.googleapis.com/css?family=Roboto:400,700" rel="stylesheet" />
<style>
* { -moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; }
body { font-family: 'Roboto', sans-serif; font-size: 14px; line-height: 22px; padding: 50px 0px; }
a, a:visited { text-decoration: underline; color: #000000; }
.container { max-width: 80%; margin: 0px auto; padding: 10px; text-align: center; }
.error { border: 1px solid red; color: red; }
.success { border: 1px solid green; color: green; }
</style>
</head>

<body>
<?php
if ($error) {
    ?>
    <div class="container error">
        <h1>Something Went Wrong</h1>
    </div>
    <?php
} else {
    ?>
    <div class="container success">
        <h1>Authorized Successfully</h1>
    </div>
    <script>
        localStorage.setItem("authToken", "<?php echo $user_auth_token; ?>");
        let authToken = "<?php echo $user_auth_token; ?>";
        let targetWindow = window.opener;
        targetWindow.postMessage(authToken, "*");
    </script>
    <?php
}
?>
</body>
</html>