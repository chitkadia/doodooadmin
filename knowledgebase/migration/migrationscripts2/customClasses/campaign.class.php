<?php


CLASS CAMPAIGN EXTENDS GENERALFUNCTIONS {

	protected $objLogger;

	function __construct ($objLogger) {
		if(DEBUG) {
			print "__construct ()";
		}

		$this->objDBConNew = new COMMONDBFUNC();

		$this->objDBConOld = new COMMONDBFUNC(HOST_OLD, PASSWORD_OLD, USERNAME_OLD, DBNAME_OLD);

		$this->objLogger = $objLogger;

		$this->perPageRecord = 500;
		$this->currentPage = 0;

		$this->totalCampaignProcessed = 0;

		$this->arrUserIdNotHavingAccount = array();

		$this->intLastInsertCampaignId = 0;
	}


	function migrateCampaignsIncrementalScript () {
		if(DEBUG) {
			print "migrateCampaignsIncrementalScript ()";
		}

		$this->setLastInsertedCampaignMasterId();

		if(is_numeric($this->intLastInsertCampaignId) && $this->intLastInsertCampaignId > 0) {
			$this->delteAllCampaignRelatedDataReferenceToMaster($this->intLastInsertCampaignId);
		}

		$this->migrateCampaigns();
	}


	function delteAllCampaignRelatedDataReferenceToMaster ($intLastInsertCampaignId = NULL) {
		if(DEBUG) {
			print "delteAllCampaignRelatedDataReferenceToMaster ()";
		}

		if(empty($intLastInsertCampaignId) || !is_numeric($intLastInsertCampaignId) || $intLastInsertCampaignId <= 0) {
			return false;
		}

		// $qrySel = "	SET FOREIGN_KEY_CHECKS = 0 ";

		// if(DEBUG) {
		// 	print nl2br($qrySel);
		// }
		
		// $objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		// if(!$objDBResult) {
		// 	print "Error occur.";
		// 	return false;
		// }


		$qrySel = "	DELETE FROM campaign_sequences WHERE campaign_id = '" . addslashes($intLastInsertCampaignId) . "' ";

		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}




		$qrySel = "	DELETE FROM campaign_stages WHERE campaign_id = '" . addslashes($intLastInsertCampaignId) . "' ";

		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}



		$qrySel = "	DELETE FROM campaign_master WHERE id = '" . addslashes($intLastInsertCampaignId) . "' ";

		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}




		// $qrySel = "	SET FOREIGN_KEY_CHECKS = 1 ";
		
		// if(DEBUG) {
		// 	print nl2br($qrySel);
		// }
		
		// $objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		// if(!$objDBResult) {
		// 	print "Error occur.";
		// 	return false;
		// }
	}

	function setLastInsertedCampaignMasterId () {
		if(DEBUG) {
			print "setLastInsertedCampaignMasterId ()";
		}

		$this->intLastInsertCampaignId = 0;

		$qrySel = "	SELECT cm.id campaignMasterId
					FROM campaign_master cm
					ORDER BY cm.id DESC
					LIMIT 1";
		
		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConNew->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}
		else {
			if($objDBResult->getNumRows() > 0) {
				$rowGetInfo = $objDBResult->fetchAssoc();
				$this->intLastInsertCampaignId = $rowGetInfo['campaignMasterId'];
			}
		}
	}


	function migrateCampaigns () {
		if(DEBUG) {
			print "migrateCampaigns ()";
		}

		$this->objLogger->setMessage('Fetching parent campaign records');

		while(true) {
			$arrCampaigns = $this->getParentAndChildCampaignsList();


			if(!empty($arrCampaigns)) {

	 			// foreach ($arrCampaigns as $key => $arrOldCampaignValues) {
				for($intI = 0; $intI < COUNT($arrCampaigns); $intI++) {

	print "this->totalCampaignProcessed :: " . $this->totalCampaignProcessed . "\n";
	$this->totalCampaignProcessed++;

	$this->objLogger->setMessage('#################CAMPAIGN MASTER MIGRATION START############################');

	 				$id 					= $arrCampaigns[$intI]['id'];
	 				$user_id 				= $arrCampaigns[$intI]['user_id'];
	 				$parent_id 				= $arrCampaigns[$intI]['parent_id'];
	 				$title 					= $arrCampaigns[$intI]['title'];
	 				$subject 				= $arrCampaigns[$intI]['subject'];
	 				$content 				= $arrCampaigns[$intI]['content'];
	 				$template_id 			= $arrCampaigns[$intI]['template_id'];
	 				$sending_method 		= $arrCampaigns[$intI]['sending_method'];
	 				$stage 					= $arrCampaigns[$intI]['stage'];
	 				$total_stages 			= $arrCampaigns[$intI]['total_stages'];
	 				$stage_defination 		= $arrCampaigns[$intI]['stage_defination'];
	 				$scheduled_on 			= $arrCampaigns[$intI]['scheduled_on'];
	 				$timezone 				= $arrCampaigns[$intI]['timezone'];
	 				$next_run_at 			= $arrCampaigns[$intI]['next_run_at'];
	 				$other_data 			= $arrCampaigns[$intI]['other_data'];
	 				$track_email 			= $arrCampaigns[$intI]['track_email'];
	 				$track_reply 			= $arrCampaigns[$intI]['track_reply'];
	 				$track_link_click 		= $arrCampaigns[$intI]['track_link_click'];
	 				$send_as_thread 		= $arrCampaigns[$intI]['send_as_thread'];
	 				$track_reply_max_date 	= $arrCampaigns[$intI]['track_reply_max_date'];
	 				$progress 				= $arrCampaigns[$intI]['progress'];
	 				$overall_progress 		= $arrCampaigns[$intI]['overall_progress'];
	 				$total_contacts 		= $arrCampaigns[$intI]['total_contacts'];
	 				$total_success 			= $arrCampaigns[$intI]['total_success'];
	 				$total_fail 			= $arrCampaigns[$intI]['total_fail'];
	 				$total_deleted 			= $arrCampaigns[$intI]['total_deleted'];
	 				$started_on 			= $arrCampaigns[$intI]['started_on'];
	 				$finish_on 				= $arrCampaigns[$intI]['finish_on'];
	 				$report_sent 			= $arrCampaigns[$intI]['report_sent'];
	 				$priority 				= $arrCampaigns[$intI]['priority'];
	 				
	 				$reply_last_checked 	= 0;
	 				if(isset($arrCampaigns[$intI]['reply_last_checked'])) {
	 					$reply_last_checked = $arrCampaigns[$intI]['reply_last_checked'];
	 				}

	 				$is_bulk_campaign 	= 0;
	 				if(isset($arrCampaigns[$intI]['is_bulk_campaign'])) {
	 					$is_bulk_campaign = $arrCampaigns[$intI]['is_bulk_campaign'];
	 				}
	 				
	 				$system_error 	= '';
	 				if(isset($arrCampaigns[$intI]['system_error'])) {
	 					$system_error = $arrCampaigns[$intI]['system_error'];
	 				}

	 				$locked 				= $arrCampaigns[$intI]['locked'];
	 				$status 				= $arrCampaigns[$intI]['status'];
	 				$created 				= $arrCampaigns[$intI]['created'];
	 				$modified 				= $arrCampaigns[$intI]['modified'];

	 				// if(!isset($this->arrUserIdNotHavingAccount[$user_id])) {
	 				// 	$arrParamUserAccountNotFound = array();
	 				// 	$arrParamUserAccountNotFound['campaignId'] = $id;
	 				// 	$arrParamUserAccountNotFound['userId'] = $user_id;
	 				// 	$arrParamUserAccountNotFound['checkInArray'] = 1;
	 				// 	$this->userAcccountNotFoundLog($arrParamUserAccountNotFound);
	 				// 	continue;
	 				// }

	 				$accountId = $this->getAccountNumberForUser($user_id);

	 				if(is_numeric($accountId) && $accountId > 0) {
	 					
	 					$convertedScheduledOn = 0;
						if(strtotime($scheduled_on)) {
							$convertedScheduledOn = self::convertDateTimeIntoTimeStamp($scheduled_on);
						}

						$convertedNextRunAt = 0;
						if(strtotime($next_run_at)) {
							$convertedNextRunAt = self::convertDateTimeIntoTimeStamp($next_run_at);
						}

						$convertedTrackReplyMaxDate = 0;
						if(strtotime($track_reply_max_date)) {
							$convertedTrackReplyMaxDate = self::convertDateTimeIntoTimeStamp($track_reply_max_date);
						}

						$convertedStartedOn = 0;
						if(strtotime($started_on)) {
							$convertedStartedOn = self::convertDateTimeIntoTimeStamp($started_on);
						}

						$convertedFinishedOn = 0;
						if(strtotime($finish_on)) {
							$convertedFinishedOn = self::convertDateTimeIntoTimeStamp($finish_on);
						}

						$convertedCreated = 0;
						if(strtotime($created)) {
							$convertedCreated = self::convertDateTimeIntoTimeStamp($created);
						}

						$convertedModified = 0;
						if(strtotime($modified)) {
							$convertedModified = self::convertDateTimeIntoTimeStamp($modified);
						}

	 					// $convertedScheduledOn 			= self::convertDateTimeIntoTimeStamp($scheduled_on);
	 					// $convertedNextRunAt 			= self::convertDateTimeIntoTimeStamp($next_run_at);
	 					// $convertedTrackReplyMaxDate 	= self::convertDateTimeIntoTimeStamp($track_reply_max_date);
	 					// $convertedStartedOn 			= self::convertDateTimeIntoTimeStamp($started_on);
	 					// $convertedFinishedOn 			= self::convertDateTimeIntoTimeStamp($finish_on);
	 					// $convertedCreated 				= self::convertDateTimeIntoTimeStamp($created);
	 					// $convertedModified 				= self::convertDateTimeIntoTimeStamp($modified);

	 					
	 					$arrParamsForGettingCampaignStages = array();
	 					$arrParamsForGettingCampaignStages['campaignId'] = $id;
	 					$arrCampaignStates = $this->getParentAndChildCampaignsList($arrParamsForGettingCampaignStages);

	 					$totalStagesAsPerQuery = (COUNT($arrCampaignStates) + 1);

	 					$this->objLogger->setMessage('Total number of stages for campaign id (' . $id . ') is (' . $totalStagesAsPerQuery . ')');
	 					if($totalStagesAsPerQuery != $total_stages) {
	 						$this->objLogger->setMessage('Total stages with the DB query is not matching with value from the old database');
	 						$this->objLogger->setMessage('Total stages as per query :: ' . $totalStagesAsPerQuery);
	 						$this->objLogger->setMessage('Total stages as per old DB value :: ' . $total_stages);
	 					}

	 					$strTimeZone = '';
	 					if(!empty($timezone)) {
	 						// $strTimeZone = 'GMT' . $timezone;
	 						$strTimeZone = $timezone;
						}


						$fromDate = $convertedScheduledOn;
						$toDate = $convertedScheduledOn;
						if(!empty($arrCampaignStates)) {
							// foreach ($arrCampaignStates as $key => $arrChildCampaignInfo) {
							for($intJ = 0; $intJ < COUNT($arrCampaignStates); $intJ++) {
 								
 								$convertedChildScheduledOn = 0;
 								if(strtotime($arrCampaignStates[$intJ]['scheduled_on'])) {
 									$convertedChildScheduledOn = self::convertDateTimeIntoTimeStamp($arrCampaignStates[$intJ]['scheduled_on']);
 								}
 								
								if($convertedChildScheduledOn > $toDate) {
									$toDate = $convertedChildScheduledOn;
								}
							}
						}


	 					$arrAllCombinedStages = [$arrCampaigns[$intI]] + $arrCampaignStates;

	 					$arrParamsToInsertIntoCampaignMaster = array();
	 					$arrParamsToInsertIntoCampaignMaster['id'] 							= $id;
						$arrParamsToInsertIntoCampaignMaster['account_id'] 					= NULL;
						$arrParamsToInsertIntoCampaignMaster['user_id'] 					= $user_id;
						$arrParamsToInsertIntoCampaignMaster['title'] 						= $title;
						$arrParamsToInsertIntoCampaignMaster['account_sending_method_id'] 	= '1'; //// need to check either GMAIL OR SMTP OR WHICH EMAIL ACCOUNT ID.
						$arrParamsToInsertIntoCampaignMaster['source_id'] 					= '1';
						$arrParamsToInsertIntoCampaignMaster['total_stages'] 				= $totalStagesAsPerQuery;
						$arrParamsToInsertIntoCampaignMaster['from_date'] 					= $fromDate;
						$arrParamsToInsertIntoCampaignMaster['to_date'] 					= $toDate;
						$arrParamsToInsertIntoCampaignMaster['timezone'] 					= $strTimeZone;
						$arrParamsToInsertIntoCampaignMaster['other_data'] 					= $other_data;
						$arrParamsToInsertIntoCampaignMaster['status_message'] 				= $system_error;
						$arrParamsToInsertIntoCampaignMaster['track_reply'] 				= $track_reply;
						$arrParamsToInsertIntoCampaignMaster['track_click'] 				= $track_link_click;
						$arrParamsToInsertIntoCampaignMaster['send_as_reply'] 				= $send_as_thread;
						$arrParamsToInsertIntoCampaignMaster['overall_progress'] 			= $overall_progress;
						$arrParamsToInsertIntoCampaignMaster['priority'] 					= $priority;
						$arrParamsToInsertIntoCampaignMaster['replyLastCheck'] 				= $reply_last_checked;
						$arrParamsToInsertIntoCampaignMaster['isBulkCampaign'] 				= $is_bulk_campaign;
						$arrParamsToInsertIntoCampaignMaster['snooze_notifications'] 		= 0;
						$arrParamsToInsertIntoCampaignMaster['status'] 						= $status;
						$arrParamsToInsertIntoCampaignMaster['created'] 					= $convertedCreated;
						$arrParamsToInsertIntoCampaignMaster['modified'] 					= $convertedModified;

						$campaignMasterId = $this->insertIntoCampaignMasterTable($arrParamsToInsertIntoCampaignMaster);

						$this->objLogger->setMessage('Campaign master entry made (' . $campaignMasterId . ')');

						if(is_numeric($campaignMasterId) && $campaignMasterId > 0) {
	 						
	 						$this->objLogger->setMessage('Starting inserting campaign stages into the database for campaign id (' . $campaignMasterId . ')');

							$arrParamsToInsertChildStages = array();
							$arrParamsToInsertChildStages['campaignMasterId'] = $campaignMasterId;
							$arrParamsToInsertChildStages['arrChildCampaign'] = $arrAllCombinedStages;
							$arrParamsToInsertChildStages['accountId'] = $accountId;

							$this->insertIntoCampaignStagesFunction($arrParamsToInsertChildStages);
						}
						else {
							$this->objLogger->setMessage('Problem creating campaign master entry for campaign id (' . $id . ')');
						}
	 				}
	 				// No account found this user.
	 				else {

	 					$this->objLogger->setMessage('Account Id not found for the campaign id :: ' . $id . ', user id :: ' . $user_id);

	 					// $arrParamUserAccountNotFound = array();
	 					// $arrParamUserAccountNotFound['campaignId'] = $id;
	 					// $arrParamUserAccountNotFound['userId'] = $user_id;
	 					// $arrParamUserAccountNotFound['checkInArray'] = 1;
	 					// $this->userAcccountNotFoundLog($arrParamUserAccountNotFound);
	 				}
$this->objLogger->setMessage('#################CAMPAIGN MASTER MIGRATION END############################');
	 			}

	 			unset($arrCampaigns);

	 			$this->objLogger->addLog();
	 			// $this->migrateCampaigns();
			}
			else {
				$this->objLogger->setMessage('All parent campaigns fetched complete.. campaign migration finished.');
				$this->objLogger->addLog();
				break;
			}
		}

		return;
	}


	function insertIntoCampaignStagesFunction ($arrParams = array()) {
		if(DEBUG) {
			print "insertIntoCampaignStagesFunction ()";
		}

		$campaignMasterId = 0;
		if(isset($arrParams['campaignMasterId']) && is_numeric($arrParams['campaignMasterId']) && $arrParams['campaignMasterId'] > 0) {
			$campaignMasterId = $arrParams['campaignMasterId'];
		}

		$arrChildCampaign = array();
		if(isset($arrParams['arrChildCampaign']) && !empty($arrParams['arrChildCampaign'])) {
			$arrChildCampaign = $arrParams['arrChildCampaign'];
		}

		$accountId = 0;
		if(isset($arrParams['accountId']) && is_numeric($arrParams['accountId']) && $arrParams['accountId'] > 0) {
			$accountId = $arrParams['accountId'];
		}

		if($campaignMasterId <= 0 || empty($arrChildCampaign)) {
			return false;
		}


		// foreach ($arrChildCampaign as $key => $arrChildCampaignInfo) {
		for($intI = 0; $intI < COUNT($arrChildCampaign); $intI++) {
 			
$this->objLogger->setMessage('#################CAMPAIGN STAGE MIGRATION START############################');
 			$stageIndex 			= ($intI + 1);

			$id 					= $arrChildCampaign[$intI]['id'];
			$user_id 				= $arrChildCampaign[$intI]['user_id'];
			$parent_id 				= $arrChildCampaign[$intI]['parent_id'];
			$title 					= $arrChildCampaign[$intI]['title'];
			$subject 				= $arrChildCampaign[$intI]['subject'];
			$content 				= $arrChildCampaign[$intI]['content'];
			$template_id 			= $arrChildCampaign[$intI]['template_id'];
			$sending_method 		= $arrChildCampaign[$intI]['sending_method'];
			$stage 					= $arrChildCampaign[$intI]['stage'];
			$total_stages 			= $arrChildCampaign[$intI]['total_stages'];
			$stage_defination 		= $arrChildCampaign[$intI]['stage_defination'];
			$scheduled_on 			= $arrChildCampaign[$intI]['scheduled_on'];
			$timezone 				= $arrChildCampaign[$intI]['timezone'];
			$next_run_at 			= $arrChildCampaign[$intI]['next_run_at'];
			$other_data 			= $arrChildCampaign[$intI]['other_data'];
			$track_email 			= $arrChildCampaign[$intI]['track_email'];
			$track_reply 			= $arrChildCampaign[$intI]['track_reply'];
			$track_link_click 		= $arrChildCampaign[$intI]['track_link_click'];
			$send_as_thread 		= $arrChildCampaign[$intI]['send_as_thread'];
			$track_reply_max_date 	= $arrChildCampaign[$intI]['track_reply_max_date'];
			$progress 				= $arrChildCampaign[$intI]['progress'];
			$overall_progress 		= $arrChildCampaign[$intI]['overall_progress'];
			$total_contacts 		= $arrChildCampaign[$intI]['total_contacts'];
			$total_success 			= $arrChildCampaign[$intI]['total_success'];
			$total_fail 			= $arrChildCampaign[$intI]['total_fail'];
			$total_deleted 			= $arrChildCampaign[$intI]['total_deleted'];
			$started_on 			= $arrChildCampaign[$intI]['started_on'];
			$finish_on 				= $arrChildCampaign[$intI]['finish_on'];
			$report_sent 			= $arrChildCampaign[$intI]['report_sent'];
			$priority 				= $arrChildCampaign[$intI]['priority'];
			
			$reply_last_checked 	= 0;
			if(isset($arrChildCampaign[$intI]['reply_last_checked'])) {
				$reply_last_checked = $arrChildCampaign[$intI]['reply_last_checked'];
			}

			$is_bulk_campaign 	= 0;
			if(isset($arrChildCampaign[$intI]['is_bulk_campaign'])) {
				$is_bulk_campaign = $arrChildCampaign[$intI]['is_bulk_campaign'];
			}
			
			$system_error 	= '';
			if(isset($arrChildCampaign[$intI]['system_error'])) {
				$system_error = $arrChildCampaign[$intI]['system_error'];
			}

			$locked 				= $arrChildCampaign[$intI]['locked'];
			$status 				= $arrChildCampaign[$intI]['status'];
			$created 				= $arrChildCampaign[$intI]['created'];
			$modified 				= $arrChildCampaign[$intI]['modified'];



 			/**
 			 *
 			 * Converting all the datetime values into the timestamp values.
 			 *
 			 */
			// $convertedScheduledOn 			= self::convertDateTimeIntoTimeStamp($scheduled_on);
			// $convertedNextRunAt 			= self::convertDateTimeIntoTimeStamp($next_run_at);
			// $convertedTrackReplyMaxDate 	= self::convertDateTimeIntoTimeStamp($track_reply_max_date);
			// $convertedStartedOn 			= self::convertDateTimeIntoTimeStamp($started_on);
			// $convertedFinishedOn 			= self::convertDateTimeIntoTimeStamp($finish_on);
			// $convertedCreated 				= self::convertDateTimeIntoTimeStamp($created);
			// $convertedModified 				= self::convertDateTimeIntoTimeStamp($modified);


			$convertedScheduledOn = 0;
			if(strtotime($scheduled_on)) {
				$convertedScheduledOn = self::convertDateTimeIntoTimeStamp($scheduled_on);
			}

			$convertedNextRunAt = 0;
			if(strtotime($next_run_at)) {
				$convertedNextRunAt = self::convertDateTimeIntoTimeStamp($next_run_at);
			}

			$convertedTrackReplyMaxDate = 0;
			if(strtotime($track_reply_max_date)) {
				$convertedTrackReplyMaxDate = self::convertDateTimeIntoTimeStamp($track_reply_max_date);
			}

			$convertedStartedOn = 0;
			if(strtotime($started_on)) {
				$convertedStartedOn = self::convertDateTimeIntoTimeStamp($started_on);
			}

			$convertedFinishedOn = 0;
			if(strtotime($finish_on)) {
				$convertedFinishedOn = self::convertDateTimeIntoTimeStamp($finish_on);
			}

			$convertedCreated = 0;
			if(strtotime($created)) {
				$convertedCreated = self::convertDateTimeIntoTimeStamp($created);
			}

			$convertedModified = 0;
			if(strtotime($modified)) {
				$convertedModified = self::convertDateTimeIntoTimeStamp($modified);
			}



 			$arrParamsToGetCampaignSequence = array();
 			$arrParamsToGetCampaignSequence['campaignMasterId'] = $campaignMasterId;
 			$arrParamsToGetCampaignSequence['campaignStageId'] = $id;

			$arrCampaignStageSequence = $this->getCampaignStageSequence($arrParamsToGetCampaignSequence);

			$totalCampaignSequence = COUNT($arrCampaignStageSequence);

			$this->objLogger->setMessage('Total Number of sequence found for campaign id (' . $campaignMasterId . ') and stage id (' . $id . ') are (' . $totalCampaignSequence . ')');

			if(is_numeric($template_id) && $template_id > 0) {
			}
			else {
				$template_id = NULL;
			}

			$arrParamsToInsertIntoCampaignStages = array();
			$arrParamsToInsertIntoCampaignStages['id']						=	$id;
			$arrParamsToInsertIntoCampaignStages['account_id']				=	$accountId;
			$arrParamsToInsertIntoCampaignStages['user_id']					=	$user_id;
			$arrParamsToInsertIntoCampaignStages['campaign_id']				=	$campaignMasterId;
			$arrParamsToInsertIntoCampaignStages['subject']					=	$subject;
			$arrParamsToInsertIntoCampaignStages['content']					=	$content;
			$arrParamsToInsertIntoCampaignStages['account_template_id']		=	$template_id;
			$arrParamsToInsertIntoCampaignStages['stage']					=	$stageIndex;
			$arrParamsToInsertIntoCampaignStages['stage_defination']		=	$stage_defination;
			$arrParamsToInsertIntoCampaignStages['scheduled_on']			=	$convertedScheduledOn;
			$arrParamsToInsertIntoCampaignStages['track_reply_max_date']	=	$convertedTrackReplyMaxDate;
			$arrParamsToInsertIntoCampaignStages['progress']				=	$progress;
			$arrParamsToInsertIntoCampaignStages['locked']					=	$locked;
			$arrParamsToInsertIntoCampaignStages['total_contacts']			=	$totalCampaignSequence;
			$arrParamsToInsertIntoCampaignStages['total_success']			=	$total_success;
			$arrParamsToInsertIntoCampaignStages['total_fail']				=	$total_fail;
			$arrParamsToInsertIntoCampaignStages['total_deleted']			=	$total_deleted;
			$arrParamsToInsertIntoCampaignStages['started_on']				=	$convertedStartedOn;
			$arrParamsToInsertIntoCampaignStages['finished_on']				=	$convertedFinishedOn;
			$arrParamsToInsertIntoCampaignStages['report_sent']				=	$report_sent;
			$arrParamsToInsertIntoCampaignStages['status']					=	$status;
			$arrParamsToInsertIntoCampaignStages['created']					=	$convertedCreated;
			$arrParamsToInsertIntoCampaignStages['modified']				=	$convertedModified;


			$campaignStageId = $this->insertIntoCampaignStageDB($arrParamsToInsertIntoCampaignStages);

			$this->objLogger->setMessage('Campaign stage inserted with campaign stage id (' . $campaignStageId . ')');

			if(is_numeric($campaignStageId) && $campaignStageId > 0) {

				$this->objLogger->setMessage('Starting to import campaign sequences for the campaign master (' . $campaignMasterId . ') and campaign stage (' . $campaignStageId . ')');

				$arrParamsForMigratingSequence = array();
				$arrParamsForMigratingSequence['campaignStageId'] = $campaignStageId;
				$arrParamsForMigratingSequence['accountId'] = $accountId;
				$arrParamsForMigratingSequence['campaignMasterId'] = $campaignMasterId;
				$arrParamsForMigratingSequence['arrCampaignStageSequence'] = $arrCampaignStageSequence;

				$this->migrateCampaignSequence($arrParamsForMigratingSequence);
			}
			else {
				$this->objLogger->setMessage('Error occured while inserting campaign stage with id (' . $id . ')');
			}
$this->objLogger->setMessage('#################CAMPAIGN STAGE MIGRATION END############################');
		}
	}


	function migrateCampaignSequence ($arrParams = array()) {
		if(DEBUG) {
			print "migrateCampaignSequence ()";
		}

		$campaignStageId = 0;
		if(isset($arrParams['campaignStageId']) && is_numeric($arrParams['campaignStageId']) && $arrParams['campaignStageId'] > 0) {
			$campaignStageId = $arrParams['campaignStageId'];
		}

		$accountId = 0;
		if(isset($arrParams['accountId']) && is_numeric($arrParams['accountId']) && $arrParams['accountId'] > 0) {
			$accountId = $arrParams['accountId'];
		}

		$campaignMasterId = 0;
		if(isset($arrParams['campaignMasterId']) && is_numeric($arrParams['campaignMasterId']) && $arrParams['campaignMasterId'] > 0) {
			$campaignMasterId = $arrParams['campaignMasterId'];
		}

		$arrCampaignStageSequence = array();
		if(isset($arrParams['arrCampaignStageSequence']) && !empty($arrParams['arrCampaignStageSequence'])) {
			$arrCampaignStageSequence = $arrParams['arrCampaignStageSequence'];
		}

		if($campaignStageId == 0 || $accountId == 0 || $campaignMasterId == 0 || empty($arrCampaignStageSequence)) {
			$this->objLogger->setMessage('Some error occured while importing sequence for campaign stage');
			$this->objLogger->setMessage('campaignStageId :: ' . $campaignStageId);
			$this->objLogger->setMessage('accountId :: ' . $accountId);
			$this->objLogger->setMessage('campaignMasterId :: ' . $campaignMasterId);
			$this->objLogger->setMessage('campaignStageId :: ' . json_encode($arrCampaignStageSequence));
			return false;
		}

$this->objLogger->setMessage('#################CAMPAIGN SEQUENCE MIGRATION START############################');

 		$strInsertQuery = "";

		// foreach ($arrCampaignStageSequence as $key => $arrCampaignStageSequenceInfo) {
 		for($intI = 0; $intI < COUNT($arrCampaignStageSequence); $intI++) {
 			
			$id						=	$arrCampaignStageSequence[$intI]['id'];
			$campaign_id			=	$arrCampaignStageSequence[$intI]['campaign_id'];
			$campaign_stage_id		=	$arrCampaignStageSequence[$intI]['campaign_stage_id'];
			$email					=	$arrCampaignStageSequence[$intI]['email'];
			$fields_payload			=	$arrCampaignStageSequence[$intI]['fields_payload'];
			$progress				=	$arrCampaignStageSequence[$intI]['progress'];
			$is_bounce				=	$arrCampaignStageSequence[$intI]['is_bounce'];
			$scheduled_at			=	$arrCampaignStageSequence[$intI]['scheduled_at'];
			$sent_at				=	$arrCampaignStageSequence[$intI]['sent_at'];
			$message_send_id		=	$arrCampaignStageSequence[$intI]['message_send_id'];
			$sent_response			=	$arrCampaignStageSequence[$intI]['sent_response'];
			$locked					=	$arrCampaignStageSequence[$intI]['locked'];
			$locked_date			=	$arrCampaignStageSequence[$intI]['locked_date'];
			$open_count				=	$arrCampaignStageSequence[$intI]['open_count'];
			$last_opened			=	$arrCampaignStageSequence[$intI]['last_opened'];
			$replied_count			=	$arrCampaignStageSequence[$intI]['replied_count'];
			$last_replied			=	$arrCampaignStageSequence[$intI]['last_replied'];
			$reply_check_count		=	$arrCampaignStageSequence[$intI]['reply_check_count'];
			$reply_last_checked		=	$arrCampaignStageSequence[$intI]['reply_last_checked'];
			$click_count			=	$arrCampaignStageSequence[$intI]['click_count'];
			$last_visited			=	$arrCampaignStageSequence[$intI]['last_visited'];
			$status					=	$arrCampaignStageSequence[$intI]['status'];
			$created				=	$arrCampaignStageSequence[$intI]['created'];
			$modified				=	$arrCampaignStageSequence[$intI]['modified'];

 			
			$convertedScheduledAt = 0;
			if(strtotime($scheduled_at)) {
				$convertedScheduledAt = self::convertDateTimeIntoTimeStamp($scheduled_at);
			}

			$convertedSentAt = 0;
			if(strtotime($sent_at)) {
				$convertedSentAt = self::convertDateTimeIntoTimeStamp($sent_at);
			}

			$convertedLockeDate = 0;
			if(strtotime($locked_date)) {
				$convertedLockeDate = self::convertDateTimeIntoTimeStamp($locked_date);
			}

			$convertedLastOpened = 0;
			if(strtotime($last_opened)) {
				$convertedLastOpened = self::convertDateTimeIntoTimeStamp($last_opened);
			}

			$convertedLastReplied = 0;
			if(strtotime($last_replied)) {
				$convertedLastReplied = self::convertDateTimeIntoTimeStamp($last_replied);
			}

			$convertedReplyLastChecked = 0;
			if(strtotime($reply_last_checked)) {
				$convertedReplyLastChecked = self::convertDateTimeIntoTimeStamp($reply_last_checked);
			}

			$convertedLastVisited = 0;
			if(strtotime($last_visited)) {
				$convertedLastVisited = self::convertDateTimeIntoTimeStamp($last_visited);
			}

			$convertedCreated = 0;
			if(strtotime($created)) {
				$convertedCreated = self::convertDateTimeIntoTimeStamp($created);
			}

			$convertedModified = 0;
			if(strtotime($modified)) {
				$convertedModified = self::convertDateTimeIntoTimeStamp($modified);
			}


			// $convertedScheduledAt = self::convertDateTimeIntoTimeStamp($scheduled_at);
			// $convertedSentAt = self::convertDateTimeIntoTimeStamp($sent_at);
			// $convertedLockeDate = self::convertDateTimeIntoTimeStamp($locked_date);
			// $convertedLastOpened = self::convertDateTimeIntoTimeStamp($last_opened);
			// $convertedLastReplied = self::convertDateTimeIntoTimeStamp($last_replied);
			// $convertedReplyLastChecked = self::convertDateTimeIntoTimeStamp($reply_last_checked);
			// $convertedLastVisited = self::convertDateTimeIntoTimeStamp($last_visited);
			// $convertedCreated = self::convertDateTimeIntoTimeStamp($created);
			// $convertedModified = self::convertDateTimeIntoTimeStamp($modified);

 			
 			$arrParamsForAccountContactIdFromEmailAndAccountId = array();
 			$arrParamsForAccountContactIdFromEmailAndAccountId['email'] = $email;
 			$arrParamsForAccountContactIdFromEmailAndAccountId['accountId'] = $accountId;
			$accountContactIdToBeUsed = $this->getAccountContactIdFromEmailAndAccountId($arrParamsForAccountContactIdFromEmailAndAccountId);
 			

 			$strInsertQuery .= "	('" . addslashes($id) . "',
									'" . addslashes($campaignMasterId) . "',
									'" . addslashes($campaignStageId) . "',
									'" . addslashes($accountContactIdToBeUsed) . "',
									'" . addslashes($fields_payload) . "',
									'" . addslashes($progress) . "',
									'" . addslashes($is_bounce) . "',
									'" . addslashes($convertedScheduledAt) . "',
									'" . addslashes($convertedSentAt) . "',
									'" . addslashes($message_send_id) . "',
									'" . addslashes($sent_response) . "',
									'" . addslashes($locked) . "',
									'" . addslashes($convertedLockeDate) . "',
									'" . addslashes($open_count) . "',
									'" . addslashes($convertedLastOpened) . "',
									'" . addslashes($convertedLastReplied > 0 ? 1 : 0) . "',
									'" . addslashes($convertedLastReplied) . "',
									'" . addslashes($reply_check_count) . "',
									'" . addslashes($convertedReplyLastChecked) . "',
									'" . addslashes($click_count) . "',
									'" . addslashes($convertedLastVisited) . "',
									'" . addslashes($status) . "',
									'" . addslashes($convertedCreated) . "',
									'" . addslashes($convertedModified) . "'
									), ";
		}

		if(!empty($strInsertQuery)) {

			$strInsertQuery = rtrim($strInsertQuery, ', ');

			$qryIns = "	INSERT INTO campaign_sequences (id, campaign_id, campaign_stage_id, account_contact_id, csv_payload, progress, is_bounce, scheduled_at, sent_at, message_send_id, sent_response, locked, locked_date, open_count, last_opened, replied, last_replied, reply_check_count, reply_last_checked, click_count, last_clicked, status, created, modified)
						VALUES " . $strInsertQuery;
					
			if(DEBUG) {
				print nl2br($qryIns);
			}

			$campaignSequenceId = $this->objDBConNew->insertAndGetId($qryIns);

			if(!$campaignSequenceId) {
				print "Cant insert into 'campaign_sequences', error occured.";
				// return false;
			}
			else {	
			}
		}

		$this->objLogger->setMessage('#################CAMPAIGN SEQUENCE MIGRATION END############################');
	}


	function insertIntoCampaignStageDB ($arrParams = array()) {
		if(DEBUG) {
			print "insertIntoCampaignStageDB ()";
		}
		
 		$intReturn = 0;

		// $arrMandatoryValue = array(
		// 							'id', 
		// 							'account_id', 
		// 							'user_id', 
		// 							'campaign_id', 
		// 							'subject', 
		// 							'content', 
		// 							'account_template_id', 
		// 							'stage', 
		// 							'stage_defination', 
		// 							'scheduled_on', 
		// 							'track_reply_max_date', 
		// 							'progress', 
		// 							'locked', 
		// 							'total_contacts', 
		// 							'total_success', 
		// 							'total_fail', 
		// 							'total_deleted', 
		// 							'started_on', 
		// 							'finished_on', 
		// 							'report_sent', 
		// 							'status', 
		// 							'created', 
		// 							'modified'
		// 						);

		// if(!empty($arrParams)) {
		// 	foreach ($arrMandatoryValue as $key => $value) {
		// 		if(!isset($arrParams[$value])) {
		// 			return $intReturn;
		// 		}
		// 	}
		// }
		// else {
		// 	return $intReturn;
		// }


		$arrParamsToVerifyAccountTemplate = array();
 		$arrParamsToVerifyAccountTemplate['accountTemplateId'] = $arrParams['account_template_id'];
		$accountTemplateIdToBeUsed = $this->verifyAccountTemplateIdPresentOrNot($arrParamsToVerifyAccountTemplate);


		$qryIns = "	INSERT INTO campaign_stages (id, account_id, user_id, campaign_id, subject, content, account_template_id, stage, stage_defination, scheduled_on, track_reply_max_date, progress, locked, total_contacts, total_success, total_fail, total_deleted, started_on, finished_on, report_sent, status, created, modified)
					VALUES ('" . addslashes($arrParams['id']) . "',
							NULL,
							'" . addslashes($arrParams['user_id']) . "',
							'" . addslashes($arrParams['campaign_id']) . "',
							'" . addslashes($arrParams['subject']) . "',
							'" . addslashes($arrParams['content']) . "',
							" . addslashes($accountTemplateIdToBeUsed) . ",
							'" . addslashes($arrParams['stage']) . "',
							'" . addslashes($arrParams['stage_defination']) . "',
							'" . addslashes($arrParams['scheduled_on']) . "',
							'" . addslashes($arrParams['track_reply_max_date']) . "',
							'" . addslashes($arrParams['progress']) . "',
							'" . addslashes($arrParams['locked']) . "',
							'" . addslashes($arrParams['total_contacts']) . "',
							'" . addslashes($arrParams['total_success']) . "',
							'" . addslashes($arrParams['total_fail']) . "',
							'" . addslashes($arrParams['total_deleted']) . "',
							'" . addslashes($arrParams['started_on']) . "',
							'" . addslashes($arrParams['finished_on']) . "',
							'" . addslashes($arrParams['report_sent']) . "',
							'" . addslashes($arrParams['status']) . "',
							'" . addslashes($arrParams['created']) . "',
							'" . addslashes($arrParams['modified']) . "'
							)";
				
		if(DEBUG) {
			print nl2br($qryIns);
		}
		
		$campaignStageId = $this->objDBConNew->insertAndGetId($qryIns);
		
		if(!$campaignStageId) {
			print "Cant insert into 'campaign_stages', error occured.";
			return false;
		}
		else {
			$intReturn = $campaignStageId;
		}
		return $intReturn;
	}



	function getCampaignStageSequence ($arrParams = NULL) {
		if(DEBUG) {
			print "getCampaignStageSequence ()";
		}

		$arrReturn = array();


 		$campaignMasterId = 0;
 		if(isset($arrParams['campaignMasterId']) && is_numeric($arrParams['campaignMasterId']) && $arrParams['campaignMasterId'] > 0) {
 			$campaignMasterId = $arrParams['campaignMasterId'];
 		}

 		$campaignStageId = 0;
 		if(isset($arrParams['campaignStageId']) && is_numeric($arrParams['campaignStageId']) && $arrParams['campaignStageId'] > 0) {
 			$campaignStageId = $arrParams['campaignStageId'];
 		}

 		if($campaignMasterId == 0 || $campaignStageId == 0) {
 			return $arrReturn;
 		}

		$qrySel = "	SELECT *
					FROM new_campaign_sequence ncs
					WHERE ncs.campaign_id = '" . addslashes($campaignMasterId) . "'
					AND ncs.campaign_stage_id = '" . addslashes($campaignStageId) . "'";

		if(DEBUG) {
			print nl2br($qrySel);
		}
		
		$objDBResult = $this->objDBConOld->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}
		else {
			if($objDBResult->getNumRows() > 0) {
				while($rowGetInfo = $objDBResult->fetchAssoc()) {
					array_push($arrReturn, $rowGetInfo);
				}
			}
		}

		return $arrReturn;
	}



	function insertIntoCampaignMasterTable ($arrParams = array()) {
		if(DEBUG) {
			print "insertIntoCampaignMasterTable ()";
		}

		$intReturn = 0;

		// $arrMandatoryValue = array(
		// 							'id', 
		// 							'account_id', 
		// 							'user_id', 
		// 							'title', 
		// 							'account_sending_method_id', 
		// 							'source_id', 
		// 							'total_stages', 
		// 							'from_date', 
		// 							'to_date', 
		// 							'timezone', 
		// 							'other_data', 
		// 							'status_message', 
		// 							'track_reply', 
		// 							'track_click', 
		// 							'send_as_reply', 
		// 							'overall_progress', 
		// 							'priority', 
		// 							'replyLastCheck', 
		// 							'isBulkCampaign', 
		// 							'snooze_notifications', 
		// 							'status', 
		// 							'created', 
		// 							'modified'
		//  						);
		// if(!empty($arrParams)) {
		// 	foreach ($arrMandatoryValue as $key => $value) {
		// 		if(!isset($arrParams[$value])) {
		// 			return $intReturn;
		// 		}
		// 	}
		// }
		// else {
		// 	return $intReturn;
		// }
// print "12123123";
// die();

		$qryIns = "	INSERT INTO campaign_master (id, account_id, user_id, title, account_sending_method_id, source_id, total_stages, from_date, to_date, timezone, other_data, status_message, track_reply, track_click, send_as_reply, overall_progress, priority, is_bulk_campaign, snooze_notifications, status, created, modified)
					VALUES ('" . addslashes($arrParams['id']) . "',
							NULL,
							'" . addslashes($arrParams['user_id']) . "',
							'" . addslashes($arrParams['title']) . "',
							'" . addslashes($arrParams['account_sending_method_id']) . "',
							'" . addslashes($arrParams['source_id']) . "',
							'" . addslashes($arrParams['total_stages']) . "',
							'" . addslashes($arrParams['from_date']) . "',
							'" . addslashes($arrParams['to_date']) . "',
							'" . addslashes($arrParams['timezone']) . "',
							'" . addslashes($arrParams['other_data']) . "',
							'" . addslashes($arrParams['status_message']) . "',
							'" . addslashes($arrParams['track_reply']) . "',
							'" . addslashes($arrParams['track_click']) . "',
							'" . addslashes($arrParams['send_as_reply']) . "',
							'" . addslashes($arrParams['overall_progress']) . "',
							'" . addslashes($arrParams['priority']) . "',
							'" . addslashes($arrParams['isBulkCampaign']) . "',
							'" . addslashes($arrParams['snooze_notifications']) . "',
							'" . addslashes($arrParams['status']) . "',
							'" . addslashes($arrParams['created']) . "',
							'" . addslashes($arrParams['modified']) . "'
						)";
				
		if(DEBUG) {
			print nl2br($qryIns);
		}
		
		$campaignMasterId = $this->objDBConNew->insertAndGetId($qryIns);
		
		if(!$campaignMasterId) {
			print "Cant insert into 'campaign_master', error occured.";
			// return false;
		}
		else {
			$intReturn = $campaignMasterId;
		}
		return $intReturn;
	}


	function userAcccountNotFoundLog ($arrParams = array()) {
		if(DEBUG) {
			print "userAcccountNotFoundLog ()";
		}

		if(isset($arrParams['checkInArray']) && $arrParams['checkInArray'] == "1") {
			$this->arrUserIdNotHavingAccount[$arrParams['userId']] = "1";
		}
 		$this->objLogger->setMessage('Account Id not found for campaign id (' . $arrParams['campaignId'] . '), user id (' . $arrParams['userId'] . ')');
	}


	function getParentAndChildCampaignsList ($arrParams = array()) {
		if(DEBUG) {
			print "getParentAndChildCampaignsList ()";
		}

		$arrReturn = array();


		$campaignId = 0;
		if(isset($arrParams['campaignId']) && is_numeric($arrParams['campaignId']) && $arrParams['campaignId'] > 0) {
			$campaignId = $arrParams['campaignId'];
		}

		$qrySel = "	SELECT *
					FROM new_campaign_master ncm
					WHERE 1 ";
 		
 		if($campaignId > 0) {
 			$qrySel .= "	AND ncm.parent_id = '" . addslashes($campaignId) . "'
							ORDER BY ncm.scheduled_on ASC ";
 		}
 		else {
 			$qrySel .= "	AND ncm.parent_id = '" . addslashes($campaignId) . "' " ;

 			if(is_numeric($this->intLastInsertCampaignId) && $this->intLastInsertCampaignId > 0) {
 				$qrySel .= " AND ncm.id >= '" . addslashes($this->intLastInsertCampaignId) . "' ";
 			}
			
			$qrySel .= " ORDER BY ncm.id ASC ";
 		}
 		
 		if($campaignId == 0) {
 			$limit = " LIMIT " . ($this->currentPage * $this->perPageRecord) . ", " . $this->perPageRecord;
			$qrySel .= $limit;
 		}

		if(DEBUG) {
			print nl2br($qrySel);
		}
print "qrySel :: " . $qrySel . "\n";
		$objDBResult = $this->objDBConOld->executeQuery($qrySel);
		
		if(!$objDBResult) {
			print "Error occur.";
			return false;
		}
		else {
			if($objDBResult->getNumRows() > 0) {
				while($rowGetInfo = $objDBResult->fetchAssoc()) {
					array_push($arrReturn, $rowGetInfo);
				}
			}
		}

		$this->currentPage++;
		return $arrReturn;
	}


	
}