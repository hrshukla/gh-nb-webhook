<?php
	// Variables
	//---------------------
	$DEBUG = true;
	// The file to store debug logs, if $DEBUG is true
	$ERROR_FILE = '/path/to/error.log';
	
	$homeloc = 'home';	// What is your Home region called in Geofence?
	
	// Replace array values with whatever GeoHopper knows you as. 
	// Replace array keys with GeoHopper configured email address
	// Below assumes 2 devices exist, one used by wife, one by the husband
	// If you have more family members using GeoHopper, add them as key-value pairs
	$family_members = array('wife@wife.com' => 'wife',
							'husband@husband.com' => 'husband');
	
	
	// $statfile - File to store our last status; 
	// ###### This file needs to exist & be populated before first use #####
	// You don't need to update this file if you add new members; 
	// That happens automatically.
	
	// Format & content of the file is as follows:
	// {"wife":"home","husband":"away"}
	// i.e., 1-line, json-encoded
	// Replace 'wife' & 'husband' with whatever GeoHopper knows you as
	// Should match the $family_members values as below	
	$statfile = '/path/to/.status';
		
	
	// Replace with your Ninja Blocks WebHooks to trigger when every one is home, and everyone is away respectively
	$home_wh = "https://api.ninja.is/rest/v0/device/WEBHOOK_when_home";
	$away_wh = "https://api.ninja.is/rest/v0/device/WEBHOOK_when_away";	


	// Debug Logging
     	function __log($message, $level = "NOTICE") {
        	global $DEBUG;
			global $ERROR_FILE;
         	if ($DEBUG) {
             		error_log("$level: $message", 3, "$ERROR_FILE");
               }
     	}
	error_reporting(-1);
	ini_set('display_errors', 1);

	// OK, Extract GeoHoppers web post & decode

	$body = @file_get_contents('php://input');
	$obj = json_decode($body);

	// If script called directly, nothing to do
	if (!$obj) die("Oops! No payload!");
	
	// Log the results obtained from GeoHopper
	$results = print_r($obj, true);
	__log("Event logged with data: $results");

	// Grab the values passed from GeoHopper

	$sender = $obj->{'sender'};
	$location = $obj->{'location'};
	$event = $obj->{'event'};

	// If location obtained is other than home, do nothing

	if($location != "$homeloc"){
		__log("Location is not home; Exiting");
		exit;
	}
	
	// If sender is other than configured in $family_members, do nothing
	// You perhaps forgot to add this person to $family_members
	// Might be worth calling another webhook to NB to alert the houseowner.
	if(!array_key_exists($sender, $family_members)){
		__log("Sender not in my family_members list; Doing nothing");
		exit;
	}		

	// Read last known status
	$last_status_json = file_get_contents($statfile);
	$last_status = json_decode($last_status_json, true);
	$state_array = array_count_values($last_status);
	
	// If every family member is home, set away count to 0
	if(!isset($state_array['away'])){
		$state_array['away'] = 0;
	}
	// And if everyone is away, set home count to 0
	if(!isset($state_array['home'])){
		$state_array['home'] = 0;
	}	

	// But wait, what if we don't have the GeoHopper sender in the .stat file?
	// So you added a new family member in family_members file
	// If you didn't add it to the status file, lets accommodate that
	if(!array_key_exists($family_members[$sender], $last_status)){
		// Lets adjust the stat count manually
		if($event == 'LocationEnter'){
			__log("Assuming missing person from status file was originally away");
			$state_array['away']++;
		} else {
			__log("Assuming missing person from status file was originally home");
			$state_array['home']++;
		}
	}		
	
	
	// Interpret the last known cumulative status - 
	
	if( $state_array['away'] == count($family_members)){
		$home_status = 'away';	
	// If all but one family member are away, the status of house is 'lasthome'	
	} elseif ( $state_array['home'] == 1 ){
		$home_status = 'lasthome';
	// Any other combination of home and away sets the status of the house to 'home'	
	} else {
		$home_status ='home';
	}	
	__log("Interpreting last known status to be $home_status");
			
	// Act on our events
	switch ($event) {
		case "LocationEnter":
			// Someone has just come home. Lets update our status file
			
			// If we are seeing someone entering, whose last known status was already 'home'
			// something failed somewhere.. perhaps a missing interim notification from GeoHopper. 
			// Lets not do anything rather than trigger a false arm / disarm
			
			if(!isset($last_status[$family_members[$sender]]) || $last_status[$family_members[$sender]] == 'away'){	
				$new_status = array($family_members[$sender] => 'home');
				$a = array_replace($last_status, $new_status);
			} else {
				__log("New status same as last known.. Not trigerring anything");
				// You could optionally call NB webhook here to alert the householder
				exit;
			}
			// Next, lets determine whether we need to disarm the alarm.
			// We need to do that only if $home_status was 'away'
			if($home_status == 'away'){
				// Call Ninja Blocks WebHook Here
				__log("Calling NB webhook to disable alarm");
				//$result = file_get_contents($home_wh);
			}	
			break;
	
		case "LocationExit":
			// Someone has just exited home. Lets update our status file
			
			// As above, if we are seeing someone exiting, whose last known status was already away
			// something failed somewhere.. perhaps a missing notification from GeoHopper. 
			// Lets not do anything rather than a false arm / disarm
			if(!isset($last_status[$family_members[$sender]]) || $last_status[$family_members[$sender]] == 'home'){
				$new_status = array("$family_members[$sender]" => 'away');
				$a = array_replace($last_status, $new_status);
			} else {
				__log("New status same as last known.. Not trigerring anything");
				// You could optionally call NB webhook here to alert the householder
				exit;
			}	
		
			// Next, lets determine whether we need to arm the alarm
			// We need to do that if this was the last person who just exited
			if ($home_status == 'lasthome'){
				// Call NB WebHook here
				__log("Calling NB Webhook to enable alarm");
				//$result = file_get_contents($away_wh);
			}	
			break;

		default:
			__log("Neither entry nor exit happening");
			break;
	}
	
	// Update the last status
	if (isset($a)){
		$encoded = json_encode($a);
		__log("Updating status file with contents: $encoded");	
		file_put_contents($statfile, $encoded);
	}

?>
