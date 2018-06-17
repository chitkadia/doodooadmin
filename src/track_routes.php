<?php
/**
 * Register application wide routes (Tracking URLs)
 */

$sh_app->map(["GET", "OPTIONS"], "/" .TRACKING_UNIQUE_TEXT. "/e/{email_id}/{user_id}/{account_id}", \App\Track\EmailTracker::class);

$sh_app->map(["GET", "OPTIONS"], "/" .TRACKING_UNIQUE_TEXT. "/oe/{email_id}/{user_id}", \App\Track\EmailTracker::class . ":v1EmailTrack");

$sh_app->map(["GET", "OPTIONS"], "/" .TRACKING_UNIQUE_TEXT. "/r/e/{redirect_key}", \App\Track\LinkTracker::class);

$sh_app->map(["GET", "OPTIONS"], "/" .TRACKING_UNIQUE_TEXT. "/c/{campaign_sequence_id}/{user_id}/{account_id}", \App\Track\CampaignTracker::class);

$sh_app->map(["GET", "OPTIONS"], "/" .TRACKING_UNIQUE_TEXT. "/oc/{campaign_sequence_id}", \App\Track\CampaignTracker::class. ":v1CampaignTrack");

$sh_app->map(["GET", "OPTIONS"], "/" .TRACKING_UNIQUE_TEXT. "/r/c/{redirect_key}", \App\Track\CampaignLinkTracker::class);

$sh_app->map(["GET", "OPTIONS"], "/" .TRACKING_UNIQUE_TEXT. "/or/c/{utm}", \App\Track\CampaignLinkTracker::class. ":v1LinkTrack");

$sh_app->map(["GET", "OPTIONS"], "/outlook/{id}", \App\Track\OutlookRedirect::class);

$sh_app->map(["GET", "OPTIONS"], "/outlook", \App\Track\OutlookRedirect::class);
