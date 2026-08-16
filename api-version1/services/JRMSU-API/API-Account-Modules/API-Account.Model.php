<?php
  
    use Configuration\Application as Application;
    use Configuration\MsgPrompt   as MsgPrompt;
      
    use Extension\JSON 			  as JSON;
	use Extension\Error_Handler   as Error_Handler;
	use Security\CryptoHashing	  as CryptoHashing;
	
    use PhpDataObject\PdoMySql    as PdoMySql;
	
     
	class API_Account{
	  
/*==============================================================================================================================================================================================================================*/
  static function Register_Record($MyRecord) { 
    
	$ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), API_Account_DataModel::Register_Record_JsonSchema); 
		   
	if($ValidateSchema["Valid"]==true) 
		   {  date_default_timezone_set(Application::$Server["TimeZone"]);  
	          $MyRecord["Date_Register"] = time();
			  $MyRecord["Password"] = CryptoHashing::HashData(Application::$Cipher["HashDifficulty"], $MyRecord["Password"],  Application::$Cipher["Key"])["Value"];
			  $MyRecord["Status"] = "Inactive" ;
	           $MyQuery = Array("API_Account_Update", JSON::Convert($MyRecord));
		       
			   $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_API_Manage_DBase, $MyQuery), "ARRAY")[0]["Result"];
              	 echo JSON::Convert($Result);	
             
		   }else{
			    echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
		   }
		}	
/*==============================================================================================================================================================================================================================*/
  static function Search_Record($MyRecord) { 
	       
		   $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), API_Account_DataModel::Search_Record_JsonSchema); 
		   
		   if($ValidateSchema["Valid"]==true) 
		   {  $MyQuery = Array("API_Account_Search", JSON::Convert($MyRecord));
		      $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_API_Manage_DBase, $MyQuery), "ARRAY")[0]["Result"];
              	 		  
              echo 	JSON::Convert($Result);		  
			  
		   }else{
			   echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
		   }
		 
	   }		
		 
 
/*==============================================================================================================================================================================================================================*/
 
 static function Validate_Record($MyRecord) { 
	       
		   $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), API_Account_DataModel::Validate_Record_JsonSchema); 
		   
		   if($ValidateSchema["Valid"]==true) 
		   {  $MyQuery = Array("API_Account_Validate", JSON::Convert($MyRecord));
		       $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_API_Manage_DBase, $MyQuery), "ARRAY")[0]["Result"];
               
			    echo 	JSON::Convert($Result);		  
			  
		   }else{
			   echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
		   }
		 
	   }		
		 
 /*==============================================================================================================================================================================================================================*/
 
 static function Update_Status_Record($MyRecord) { 
	       
		   $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), API_Account_DataModel::Update_Status_Record_JsonSchema); 
		   
		   if($ValidateSchema["Valid"]==true) 
		   {  $MyQuery = Array("API_Account_Update_Status", JSON::Convert($MyRecord));
		       $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_API_Manage_DBase, $MyQuery), "ARRAY")[0]["Result"];
               
			    echo 	JSON::Convert($Result);		  
			  
		   }else{
			   echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
		   }
		 
	   }
	   
/*==============================================================================================================================================================================================================================*/
/*==============================================================================================================================================================================================================================*/

   }
	
 ?>