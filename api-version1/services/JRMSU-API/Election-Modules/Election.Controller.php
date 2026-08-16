<?php
    
    require_once ("../../autoloader.php");	
	

     use Configuration\MsgPrompt as MsgPrompt;
	 use Extension\JSON 		 as JSON;
     use Controller\JSON_Schema  as Controller_JSON_Schema;
	 
	 //Recieve Data from Client  
	  $MyData   = JSON::Convert(file_get_contents('php://input'), "ARRAY"); 
      $MyMethod = strtoupper($_SERVER['REQUEST_METHOD']);
	   
	  /* Verify the required format of the JSON Schema for the Controller. */  
	 $ValidateSchema = Controller_JSON_Schema::Validate(JSON::Convert($MyData));
	    
		if($ValidateSchema["Valid"]!==false){
			if(isset($MyData["Record"]) && ($MyData["Record"] !== null || json_last_error() === JSON_ERROR_NONE)) {
			   if($MyMethod=="POST"){
					switch (strtoupper($MyData["Request"])){
					 
					  case "VOTES/CAST"            : Election_ExtModel:: vote_cast($MyData["Record"]); break;
					  case "VOTES/RESULT"            : Election:: election_generate_result($MyData["Record"]); break;
					  case "SCHEDULE/CHECK"            : Election:: Check_Voting_Availability($MyData["Record"]); break;
					  case "USER/STATUS"            : Election:: Check_User_Vote_Status($MyData["Record"]); break;
					  case "ACCOUNT/UPDATE_STATUS"            : Election:: User_Account_Update_Status($MyData["Record"]); break;
					  case "ACCOUNT/CRUD"            : Election:: User_Account_CRUD($MyData["Record"]); break;
					  case "APP/CRUD"            : Election:: App_Service_CRUD($MyData["Record"]); break;
					  case "LOG/VIEW"            : Election:: Get_User_Log($MyData["Record"]); break;
					  case "LOG/INSERT"            : Election:: Insert_User_Log($MyData["Record"]); break;
					  case "CREATE/SCHEDULE"            : Election:: Create_Schedule($MyData["Record"]); break;
					   case "VOTE/COUNT/COLLEGE"            : Election:: Get_votes_count_per_College($MyData["Record"]); break;
					  
					 
					 
					  default						:	echo JSON::Convert(Array("Status"=> MsgPrompt::$Error["Request_Error"]), "STRING");	 break;
				 }
			   } else{ echo JSON::Convert(Array("Status"=> MsgPrompt::$Error["Required_PostMethod"]), "STRING");}
		   } else { echo JSON::Convert(Array("Status"=> MsgPrompt::$Error["Parsing_Error"]), "STRING");}
		}else { echo JSON::Convert(Array("Status"=> $ValidateSchema["Status"]), "STRING");}
