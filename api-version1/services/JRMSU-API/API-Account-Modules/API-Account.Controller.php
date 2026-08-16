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
					case "PROFILE/REGISTER"			:	API_Account::Register_Record($MyData["Record"]);   		break;
					case "PROFILE/SEARCH"			:	API_Account::Search_Record($MyData["Record"]);     		break;
                    case "PROFILE/VALIDATE"			:	API_Account::Validate_Record($MyData["Record"]);   		break;
					case "PROFILE/STATUS/UPDATE"	:	API_Account::Update_Status_Record($MyData["Record"]);   break;					
				    
   				    default						:	echo JSON::Convert(Array("Status"=> MsgPrompt::$Error["Request_Error"]), "STRING");	 break;
				}
		 	 } else{ echo JSON::Convert(Array("Status"=> MsgPrompt::$Error["Required_PostMethod"]), "STRING");}
		  } else { echo JSON::Convert(Array("Status"=> MsgPrompt::$Error["Parsing_Error"]), "STRING");}
	   }else { echo JSON::Convert(Array("Status"=> $ValidateSchema["Status"]), "STRING");}
