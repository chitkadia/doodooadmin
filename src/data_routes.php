<?php
/**
 * Register application wide routes (Data)
 */
$sh_app->get("/{auth_token}/{id}/export-data/{stage_id}", \App\Data\CampaignData::class . ":exportData");