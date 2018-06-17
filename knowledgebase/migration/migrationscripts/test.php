<?php



$dateTime = '2017-10-05 05:31:00';

if(is_date($dateTime)) {
	print "date111111111111111111";
}

print "<pre>dateTime :: ";
print_r($dateTime);
print "</pre>";

$date = new DateTime($dateTime, new DateTimeZone('Asia/Kolkata'));
$date->setTimeZone(new DateTimeZone('UTC'));
$convertedDate = $date->format('Y-m-d H:i:s');


print "<pre>convertedDate :: ";
print_r(strtotime($convertedDate));
print "</pre>";
// require_once('class/common.php');



// print date('Y-m-d H:i:s');


// ERRORLOGS::addLog();



// while(true) {
// 	echo "Are you sure you want to do this?  Type 'yes' to continue: ";
// 	echo "asdfasfasdf";

// 	$handle = fopen ("php://stdin","r");
// 	$line = fgets($handle);

// 	print $line;

// 	if(trim($line) != 'yes'){
// 	    echo "ABORTING!\n";
// 	    // exit;
// 	}
// 	else {
// 		echo "\n";
// 		echo "Thank you, continuing...\n";
// 	}

// }

?>