<?php
/**
 * Register application wide routes (Incoming Webhooks)
 */
$sh_app->map(["POST", "OPTIONS"], "/web-hooks/stripe", \App\Controllers\IncommingWebhooksController::class . ":stripeWebhooks");