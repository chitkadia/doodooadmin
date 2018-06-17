<?php
/**
 * Used to send push notification to the node server.
 */
namespace App\Commands;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Interop\Container\ContainerInterface;
use \App\Models\AppVarsModel;
use \App\Components\PushNotificationComponent;


class sendPushNotificationCommand extends AppCommand {
    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    public function sendPushNotification(ServerRequestInterface $request, ResponseInterface $response, $args) {

    	$stringData = $args['stringData'];
    	
		$dataToSend = "data=" . $stringData;

    	$curlRespource = curl_init(NODE_SERVER_PUSH_ENDPOINT);

    	curl_setopt($curlRespource, CURLOPT_POST, true);
		curl_setopt($curlRespource, CURLOPT_POSTFIELDS, $dataToSend);

		$arrResponse = curl_exec($curlRespource);
		curl_close($curlRespource);
    }

    public function sendtestpush () {

        $arr = array();
        $arr['message'] = "This is a test message ";
        $arr['title'] = "this is title ";
        $arr['enc_user_id'] = "123123";

        PushNotificationComponent::sendPushNotification($arr);
    }

}