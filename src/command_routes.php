<?php
/**
 * Register application wide routes (Cron Jobs)
 */
$sh_app->any("/generate-app-vars", \App\Commands\AppVarsConfigCommand::class);

$sh_app->any("/document-convert-upload", \App\Commands\DocumentsUploaderCommand::class . ":convertUpload");

$sh_app->any("/send-scheduled-mails", \App\Commands\SendScheduledMail::class);

$sh_app->any("/reset-email-limit", \App\Commands\ResetEmailLimitCommand::class);

$sh_app->any("/action-prepare-queue", \App\Commands\CampaignCommand::class . ":actionPrepareQueue");

$sh_app->any("/action-prepare-queue-bulk", \App\Commands\CampaignCommand::class . ":actionPrepareQueueBulk");

$sh_app->any("/action-process-queue", \App\Commands\CampaignCommand::class . ":actionProcessQueue");

$sh_app->any("/action-process-queue-bulk", \App\Commands\CampaignCommand::class . ":actionProcessQueueBulk");

$sh_app->any("/action-check-reply", \App\Commands\CampaignCommand::class . ":actionCheckReply");

$sh_app->any("/action-check-reply-bulk", \App\Commands\CampaignCommand::class . ":actionCheckReplyBulk");

$sh_app->any("/action-final-reply-check", \App\Commands\CampaignCommand::class . ":actionFinalReplyCheck");

$sh_app->any("/action-final-reply-check-bulk", \App\Commands\CampaignCommand::class . ":actionFinalReplyCheckBulk");

$sh_app->any("/action-performance-email", \App\Commands\CampaignCommand::class . ":actionCampaignPerformanceEmail");

$sh_app->any("/check-trial-expire", \App\Commands\BillingPlanCommand::class . ":actionCheckTrialExpire");

$sh_app->any("/check-plan-cancelled", \App\Commands\BillingPlanCommand::class . ":actionCheckPlanCancelled");

$sh_app->any("/remove-deleted-members", \App\Commands\BillingPlanCommand::class . ":actionCheckDeleteMemberList");

$sh_app->any("/send-push-notification/{stringData}", \App\Commands\sendPushNotificationCommand::class . ":sendPushNotification");

$sh_app->any("/testpush", \App\Commands\sendPushNotificationCommand::class . ":sendtestpush");

$sh_app->any("/check-email-reply", \App\Commands\EmailReplyCommand::class . ":actionCheckEmailReply");

//$sh_app->any("/check-document-links", \App\Commands\DocumentLinksCommand::class);
 $sh_app->any("/check-document-links", \App\Commands\DocumentLinksCommand::class . ":actionCheckDocLinkNotViewed");

$sh_app->any("/check-document-visited-links", \App\Commands\DocumentLinksCommand::class . ":actionCheckDocLinkViewed");