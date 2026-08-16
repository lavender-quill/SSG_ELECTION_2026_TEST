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
					case "POSITION/REGISTER"		:	Candidate::Register_Position($MyData["Record"]); 	 										break;
					case "PROFILE/STATUS/UPDATE"	:	Candidate::Profile_Status_Update($MyData["Record"]); 	 										break;
				    case "ACCOUNT/ID/GET"					:	Candidate::get_Candidate_StudentID($MyData["Record"]); 	 										break;
				    case "BALLOT/GENERATE"					:	Candidate::Generate_Candidates_List($MyData["Record"]); 	 										break;
					case "ACCOUNT/GET/ALL"					:	Candidate::Get_All_Candidates($MyData["Record"]); 	 										break;
					case "ACCOUNT/UPDATE/PHOTO"					:	Candidate::Upload_Photo($MyData["Record"]); 	 										break;


   				    default							:	echo JSON::Convert(Array("Status"=> MsgPrompt::$Error["Request_Error"]), "STRING");	 break;
				}
		 	 } else{ echo JSON::Convert(Array("Status"=> MsgPrompt::$Error["Required_PostMethod"]), "STRING");}
		  } else { echo JSON::Convert(Array("Status"=> MsgPrompt::$Error["Parsing_Error"]), "STRING");}
	   }else { echo JSON::Convert(Array("Status"=> $ValidateSchema["Status"]), "STRING");}
