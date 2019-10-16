<?php	

	/****************************************************************
		File:  WNHIS_services.php 
		Author: Phusit Roongroj <phusit@nectec.or.th> 
		Internet Innovation Lab (INO)
		National Electronics and Computer Technology Center (NECTEC)
		Web services API for BANGKOK NHIS @V1.0
		03-10-2019
	*****************************************************************/

	require_once dirname(__FILE__) . "/lib/nhis_services.class.php" ;

	$serv = new NHIS_Webservices ($_REQUEST['t'], $_REQUEST['c'] );
	$a =  $serv->callServiceType ();
	header("Content-Type: application/json;"); 	

	echo $a;
	
?>
