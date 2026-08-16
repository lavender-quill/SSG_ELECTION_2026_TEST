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
					case "ENROLLMENT/SEARCH"	:	Voter::Search_Record($MyData["Record"]); 	 										break;
					case "PROFILE/SEARCH"		:	Voter::Search_Profile($MyData["Record"]); 	 										break;
					case "ACCOUNT/LOGIN"		:	Voter::Account_Login($MyData["Record"]); 	 										break;
				    case "ACCOUNT/LOGIN2"		:	Voter::Account_Login2($MyData["Record"]); 	 										break;
					case "PROFILE/GET"		:	Voter::Get_Candidate_Info_($MyData["Record"]); 	 										break;
					case "PROFILE/UPDATE"		:	Voter::Account_Update($MyData["Record"]); 	 										break;
					case "PROFILE/SEARCH/DUMMY"		:	Voter::Student_SearchDummy($MyData["Record"]); 	 										break;
					 case "ACCOUNT/LOGIN3"		:	Voter::Login($MyData["Record"]); 	 										break;
					case "ACCOUNT/UPDATE/PASSWORD"		:	Voter::UpdatePassword($MyData["Record"]); 	 										break;
					case "ACCOUNT/COUNT/CASTED"		:	Voter::Get_Casted_Voters($MyData["Record"]); 	 										break;
					case "GET/ALL"		:	Voter::Get_All_Students($MyData["Record"]); 	 										break;
   				    default						:	echo JSON::Convert(Array("Status"=> MsgPrompt::$Error["Request_Error"]), "STRING");	 break;
				}
		 	 } else{ echo JSON::Convert(Array("Status"=> MsgPrompt::$Error["Required_PostMethod"]), "STRING");}
		  } else { echo JSON::Convert(Array("Status"=> MsgPrompt::$Error["Parsing_Error"]), "STRING");}
	   }else { echo JSON::Convert(Array("Status"=> $ValidateSchema["Status"]), "STRING");}
