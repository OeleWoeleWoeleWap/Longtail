<?php 
	/*ini_set('display_startup_errors',1);
	ini_set('display_errors',1);
	error_reporting(-1);*/

	$client 	= $_GET['client'];
	$campaign 	= $_GET['campaign'];
	if(!file_exists()){
		echo mkdir('feeds/'.$client,0777,true);
	}
	$myfile = fopen("feeds/".$client."/".$campaign.".txt", "w");
	fwrite($myfile,$_POST['sFeed']);
?>