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
					case "SEARCH"					:	College::Search_Record($MyData["Record"]); 			     	break;
					case "RECORD/UPDATE"			:	College::Update_Record(); 			     					break;
				    case "PROGRAM/SEARCH"			:	College::Program_Search_Record($MyData["Record"]); 	 	 	break;
					case "PROGRAM/RECORD/UPDATE"	:	College::Program_Update_Record();				 	 	 	break;
					case "PROGRAM/MAJOR/UPDATE"		:	College::Major_Update_Record();					 	 	 	break;
					case "PROGRAM/MAJOR/SEARCH"		:	College::Major_Search_Record($MyData["Record"]);					 	 	 	break;
					
   				 
					default							:	echo JSON::Convert(Array("Status"=> MsgPrompt::$Error["Request_Error"]), "STRING");	 break;
				}
		 	 } else{ echo JSON::Convert(Array("Status"=> MsgPrompt::$Error["Required_PostMethod"]), "STRING");}
		   } else { echo JSON::Convert(Array("Status"=> MsgPrompt::$Error["Parsing_Error"]), "STRING");}
	  }else { echo JSON::Convert(Array("Status"=> $ValidateSchema["Status"]), "STRING");}
