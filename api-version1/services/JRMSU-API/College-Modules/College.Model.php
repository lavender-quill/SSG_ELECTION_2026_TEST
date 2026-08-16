<?php
  
    use Configuration\Application as Application;
    use Configuration\MsgPrompt   as MsgPrompt;
      
    use Extension\JSON 			  as JSON;
	use Extension\Error_Handler   as Error_Handler;
	
    use PhpDataObject\PdoMySql    as PdoMySql;
     
	class College{
/*==============================================================================================================================================================================================================================*/
  static function Search_Record($MyRecord) { 
	      $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), College_DataModel::Search_Record_JsonSchema); 
		   
		   if($ValidateSchema["Valid"]==true) 
		   {  $MyQuery = Array("College_Search", JSON::Convert($MyRecord));
		      $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY")[0]["Result"];
              	 		  
              echo 	JSON::Convert($Result);		  
			  
		   }else{
			   echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
		   }
 	 
    }		
/*==============================================================================================================================================================================================================================*/
  static function Update_Record() { 
	       
		   //Request Token
	       $EndPoint="https://jrmsu-arms.online/api/version-2/services/credential/token/request";
		   $Response = JSON::Convert(Voter_ExtModel::Dispatch($EndPoint, Voter_ExtModel::$Security, ""), "ARRAY");
		   
		  if(stripos($Response["Status"], "Error:")=== false){
			  $MyHeader =  Array("Secret-Key" => $Response["Secret_Key"],
			                     "User-Agent" => "Coderstation-Protocol",
	                             "Authorization" => "Bearer " . $Response["JWToken"] 					   
	                            );  	
	 		   
			    $MyRecord["College"] ="ALL";
			    $EndPoint="https://jrmsu-arms.online/api/version-2/services/college/search";
				$Response = JSON::Convert(Voter_ExtModel::Dispatch($EndPoint, $MyHeader, JSON::Convert($MyRecord)));  
				 
				 $MyQuery = Array("College_Update", JSON::Convert(JSON::Convert($Response,"ARRAY")["Record"]));
		         $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY");
              	 		  
                 echo JSON::Convert($Result[0]);	
		  }
		 
	   }	
	   
/*==============================================================================================================================================================================================================================*/
   static function Program_Search_Record($MyRecord) { 
      
	  $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), College_DataModel::Program_Search_JsonSchema); 
		   
		   if($ValidateSchema["Valid"]==true) 
		   {  $MyQuery = Array("Program_Search", JSON::Convert($MyRecord));
		      $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY")[0]["Result"];
              	 		  
              echo 	JSON::Convert($Result);		  
			  
		   }else{
			   echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
		   }
	      
    }
/*==============================================================================================================================================================================================================================*/
 
 static function Program_Update_Record() { 
	       
		   //Request Token
	      $EndPoint="https://jrmsu-arms.online/api/version-2/services/credential/token/request";
		   $Response = JSON::Convert(Voter_ExtModel::Dispatch($EndPoint, Voter_ExtModel::$Security, ""), "ARRAY");
		   
		  if(stripos($Response["Status"], "Error:")=== false){
			  $MyHeader =  Array("Secret-Key" => $Response["Secret_Key"],
			                     "User-Agent" => "Coderstation-Protocol",
	                             "Authorization" => "Bearer " . $Response["JWToken"] 					   
	                            );  	
	 		    
				$MyRecord["Program"] ="ALL";
		   		$EndPoint="https://jrmsu-arms.online/api/version-2/services/college/program/search";
				$Response = JSON::Convert(Voter_ExtModel::Dispatch($EndPoint, $MyHeader, JSON::Convert($MyRecord)));  
				 
				 $MyQuery = Array("Program_Update", JSON::Convert(JSON::Convert($Response,"ARRAY")["Record"]));
		         $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY");
              	 		  
                 echo JSON::Convert($Result[0]);	
		  }
		 
	   }		  
/*==============================================================================================================================================================================================================================*/
/*==============================================================================================================================================================================================================================*/
   static function Major_Search_Record($MyRecord) { 
      
	  $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), College_DataModel::Major_Search_JsonSchema); 
		   
		   if($ValidateSchema["Valid"]==true) 
		   {  $MyQuery = Array("Major_Search", JSON::Convert($MyRecord));
		      $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY")[0]["Result"];
              	 		  
              echo 	JSON::Convert($Result);		  
			  
		   }else{
			   echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
		   }
	      
    }
/*==============================================================================================================================================================================================================================*/
 
 static function Major_Update_Record() { 
	       
		   //Request Token
	      $EndPoint="https://jrmsu-arms.online/api/version-2/services/credential/token/request";
		   $Response = JSON::Convert(Voter_ExtModel::Dispatch($EndPoint, Voter_ExtModel::$Security, ""), "ARRAY");
		   
		  if(stripos($Response["Status"], "Error:")=== false){
			  $MyHeader =  Array("Secret-Key" => $Response["Secret_Key"],
			                     "User-Agent" => "Coderstation-Protocol",
	                             "Authorization" => "Bearer " . $Response["JWToken"] 					   
	                            );  	
	 		    
				$MyRecord["Program"] ="ALL";
		   		$EndPoint="https://jrmsu-arms.online/api/version-2/services/college/program/search";
				$Response = JSON::Convert(Voter_ExtModel::Dispatch($EndPoint, $MyHeader, JSON::Convert($MyRecord)));  
				 
				 $MyQuery = Array("Major_Update", JSON::Convert(JSON::Convert($Response,"ARRAY")["Record"]));
		         $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY");
              	 		  
                 echo JSON::Convert($Result[0]);	
		  }
		 
	   }		  
/*==============================================================================================================================================================================================================================*/


   }
	
 ?>