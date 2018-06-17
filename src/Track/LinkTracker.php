<?php
/**
 * Link tracking related functionality
 */
namespace App\Track;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Interop\Container\ContainerInterface;
use \App\Components\StringComponent;
use \App\Components\DateTimeComponent;
use \App\Components\SHAppComponent;
use \App\Components\SHActivity;
use \App\Models\AccountLinkMaster;
use \App\Models\EmailLinks;
use \App\Models\EmailTrackHistory;
use \App\Models\EmailMaster;

class LinkTracker extends AppTracker {

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $args) {

        // Get request parameters
        $route = $request->getAttribute("route");
        $request_params = $route->getArguments();

        $redirect_key = $request_params["redirect_key"];
        $request_params = StringComponent::decodeRowId($redirect_key, true);
    	$redirect_url = "";
    	
    	if (!empty($request_params)) {

    		$account_id = $request_params[0];
	    	$email_id = $request_params[1];
	    	$account_link_id = $request_params[2];

	    	$last_clicked = DateTimeComponent::getDateTime();

		    try {
		    	// Fetch row from account link master
		    	$model_account_link_master = new AccountLinkMaster();
		        $condition = [
		            "fields" => [
		                "url",
		                "total_clicked"
		            ],
		            "where" => [
		                ["where" => ["id", "=", $account_link_id]],
                        ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
		            ]
		        ];
		        $account_link_row = $model_account_link_master->fetch($condition);

                if (!empty($account_link_row)) {
                    $redirect_url = $account_link_row["url"];

                    //Save record to account_link_master
                    try {
                        $save_data = [
                            "id" => $account_link_id,
                            "total_clicked" => $account_link_row["total_clicked"] + 1,
                            "last_clicked" => $last_clicked,
                            "modified" => $last_clicked
                        ];

                        $model_account_link_master->save($save_data);

                    } catch(\Exception $e) { }

                    //fetch row from email links
                    $model_email_links = new EmailLinks();
                    $condition = [
                        "fields" => [
                            "id",
                            "total_clicked"
                        ],
                        "where" => [
                            ["where" => ["account_id", "=", $account_id]],
                            ["where" => ["email_id", "=", $email_id]],
                            ["where" => ["account_link_id", "=", $account_link_id]],
                            ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                        ]
                    ];
                    $email_link_row = $model_email_links->fetch($condition);

                    if (!empty($email_link_row)) {
                        //save record to email_links
                        try {
                            $save_data = [
                                "id" => $email_link_row["id"],
                                "total_clicked" => $email_link_row["total_clicked"] + 1,
                                "last_clicked" => $last_clicked,
                                "modified" => $last_clicked
                            ];

                            $model_email_links->save($save_data);

                        } catch(\Exception $e) { }
                    }

                    //fetch row from email master
                    $model_email_master = new EmailMaster();
                    $condition = [
                        "fields" => [
                            "click_count",
                            "user_id",
                            "subject",
                            "snooze_notifications"
                        ],
                        "where" => [
                            ["where" => ["account_id", "=", $account_id]],
                            ["where" => ["id", "=", $email_id]],
                            ["where" => ["status", "<>", SHAppComponent::getValue("app_constant/STATUS_DELETE")]]
                        ]
                    ];
                    $email_master_row = $model_email_master->fetch($condition);

                    if (!empty($email_master_row)) {
                        //save record to email table
                        try {
                            $save_data = [
                                "id" => $email_id,
                                "click_count" => $email_master_row["click_count"] + 1,
                                "last_clicked" => $last_clicked,
                                "modified" => $last_clicked
                            ];

                            $model_email_master->save($save_data);

                            // Save record to email track history
                            $model_email_track_history = new EmailTrackHistory();
                            $save_data = [
                                "email_id" => $email_id,
                                "account_link_id" => $account_link_id,
                                "type" => SHAppComponent::getValue("app_constant/EMAIL_TRACK_CLICK"),
                                "acted_at" => DateTimeComponent::getDateTime()
                            ];
                            $saved = $model_email_track_history->save($save_data);

                            $activity_params = [
                                "user_id" => $email_master_row["user_id"],
                                "account_id" => $account_id,
                                "action" => SHAppComponent::getValue("actions/EMAILS/CLICKED"),
                                "record_id" => $email_id,
                                "subject" => $email_master_row["subject"],
                                "url" => $account_link_row["url"],
                                "snooze" => $email_master_row["snooze_notifications"],
                                "sub_record_id" => $account_link_id
                            ];
                            
                            $sh_activity = new SHActivity();
                            $sh_activity->addActivity($activity_params);
                            
                        } catch(\Exception $e) {}
                    }
                }
            } catch(\Exception $e) {}
    	}

    	if ($redirect_url != "") {
                $parsed = parse_url($redirect_url);
                if (empty($parsed["scheme"])) {
                    $redirect_url = "http://" . ltrim($redirect_url, "/");
                }

    		return $response->withRedirect($redirect_url);
    	} else {
    		
    		$output_template = __DIR__ . "/../../track/404.html";
            $http_status = 404;

            try {
	            // Start output buffer
	            ob_start();

	            // Insert template
	            include $output_template;

	            // Flush buffer and store output
	            $output = ob_get_clean();

	        } catch (\Exception $e) {
	            $output_template = __DIR__ . "/../../track/500.html";
	            $http_status = 500;
	        }

	        // Send output
	        $response->getBody()->write($output);
	        return $response->withStatus($http_status);
    	}	
    }
}
