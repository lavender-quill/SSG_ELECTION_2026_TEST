<?php
  
    use Configuration\Application as Application;
    use Configuration\MsgPrompt   as MsgPrompt;
      
    use Extension\JSON 			  as JSON;
	use Extension\Error_Handler   as Error_Handler;
	
    use PhpDataObject\PdoMySql    as PdoMySql;
     
	class Candidate{
/*==============================================================================================================================================================================================================================*/
  static function Register_Position($MyRecord) { 
	       
		   $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Candidate_DataModel::Register_Candidate_JsonSchema); 
		   
		   if($ValidateSchema["Valid"]==true) 
		   {  $MyQuery = Array("Candidate_Position_Register", JSON::Convert($MyRecord));
		      $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Candidate_DBase, $MyQuery), "ARRAY")[0]["Result"];
              			  
              echo 	JSON::Convert($Result);		  
			  
		   }else{
			   echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
		   }
		 
	   }		
		 
 
/*==============================================================================================================================================================================================================================*/
 
 static function Profile_Status_Update($MyRecord) { 
	       
		   $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Candidate_DataModel::Update_Candidate_Profile_Status_JsonSchema); 
		   
		   if($ValidateSchema["Valid"]==true) 
		   {  $MyQuery = Array("Candidate_Position_Status_Update", JSON::Convert($MyRecord));
		      $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Candidate_DBase, $MyQuery), "ARRAY")[0]["Result"];
              			  
              echo 	JSON::Convert($Result);		  
			  
		   }else{
			   echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
		   }
		 
	   }		
		 
 
/*==============================================================================================================================================================================================================================*/
/*==============================================================================================================================================================================================================================*/


static function get_Candidate_StudentID($MyRecord) { 
      
	$ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Candidate_DataModel:: Get_Candidate_ID_Schema); 
		 
		 if($ValidateSchema["Valid"]==true) 
		 {  $MyQuery = Array("candidate_position_get_id", JSON::Convert($MyRecord));
			$Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Candidate_DBase, $MyQuery), "ARRAY")[0]["Result"];
						   
			echo 	JSON::Convert($Result);		  
			
		 }else{
			 echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
		 }
		
  }

  static function Generate_Candidates_List($MyRecord) { 
      
	$ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Candidate_DataModel:: Generate_Ballot_JsonSchema); 
		 
		 if($ValidateSchema["Valid"]==true) 
		 {  $MyQuery = Array("candidate_position_generate_ballot_final", JSON::Convert($MyRecord));
			$Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Candidate_DBase, $MyQuery), "ARRAY")[0]["Result"];
						   
			echo 	JSON::Convert($Result);		  
			
		 }else{
			 echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
		 }
		
  }

  static function Get_All_Candidates($MyRecord) { 
      
	$ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Candidate_DataModel:: Get_All_Candidates); 
		 
		 if($ValidateSchema["Valid"]==true) 
		 {  $MyQuery = Array("candidate_position_get_all", JSON::Convert($MyRecord));
			$Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Candidate_DBase, $MyQuery), "ARRAY")[0]["Result"];
						   
			echo 	JSON::Convert($Result);		  
			
		 }else{
			 echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
		 }
		
  }

  static function Upload_Photo($MyRecord) { 
      
	$ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Candidate_DataModel:: Upload_Photo_jsonSchema); 
		 
		 if($ValidateSchema["Valid"]==true) 
		 {  $MyQuery = Array("candidate_photo_Add", JSON::Convert($MyRecord));
			$Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Candidate_DBase, $MyQuery), "ARRAY")[0]["Result"];
						   
			echo 	JSON::Convert($Result);		  
			
		 }else{
			 echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
		 }
		
  }
   


   }


	
 ?>