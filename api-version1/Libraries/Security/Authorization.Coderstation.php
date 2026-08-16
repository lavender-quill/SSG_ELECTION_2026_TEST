<?php
 
  /*=============================================================*/
  /*	Coded by	: Armando T. Saguin Jr.						 */
  /*				  Coderstation Information System Innovator  */
  /*	Email		: saguin.armando.jr@gmail.com				 */
  /*	Mobile No.	: +639306694943								 */
  /*	Version		: 1.0.01									 */
  /*=============================================================*/

  
 namespace Security; 
 
 use Extension\JSON 			as JSON;
 use Extension\Error_Handler    as Error_Handler;
 
 use Configuration\MsgPrompt 	as MsgPrompt;
 use Configuration\Application 	as Application;
 use Configuration\Route_Page   as RoutePage;
 
 use Security\CryptoHashing	    as CryptoHashing;
 use Security\JWToken	        as JWToken;
 use PhpDataObject\PdoMySQL		as PdoMySql;
   
class Authorization{

//   public static function Validate($Request_Headers){
	  
// 	 $MyHeader= JSON::Convert($Request_Headers,"ARRAY");
// 	  if (isset($MyHeader["AUTHORIZATION"])){
// 	   $JWToken = $MyHeader["AUTHORIZATION"];	 
// 	  }else{$JWToken ="";}
	 	 
// 	 $Response =  JSON::Convert(Array("Status"=> MsgPrompt::$Error["Authentication_Error"], "JWToken"=> $JWToken), "STRING"); 
//      $MyService = "{$MyHeader["MODULE"]}/{$MyHeader["PROCESS-REQUEST"]}";	
// 	$API_Account= " ";
	 
// 	/* Intercept Errors in a Try-Catch Statement */
//     Error_Handler::Intercept();
//     try{  
// 	      $CheckHeader = Self::Check_Header_Properties($MyHeader);
// 	 	  if($CheckHeader==="Validated"){
// 			         /*Validate API Account */
// 					if (Self::isProcess_Authorized($MyService)===false){
// 						/*Validate Secret Key*/
// 						 if(isset($MyHeader["SECRET-KEY"])){
// 						   $DeCrypt_SpecialKey = CryptoHashing::DecryptData($MyHeader["SECRET-KEY"], Application::$Cipher["ApiKey"]);
// 							 if(trim($DeCrypt_SpecialKey)=="Error"){
// 							   return JSON::Convert(Array("Status"=>  MsgPrompt::$Error["Secret_Key_Error"] ,"JWToken"=> $JWToken), "STRING"); 
// 						     }
						 
// 						   $Secret_Key = explode("<-+->", CryptoHashing::DecryptData($MyHeader["SECRET-KEY"], Application::$Cipher["ApiKey"])); 
// 							if($Secret_Key[2] == date("mdY")){
// 								$API_Account = Self::Validate_API($Secret_Key[0], $Secret_Key[1], $MyService);	 
// 							}else{return JSON::Convert(Array("Status"=>  MsgPrompt::$Error["Secret_Key_Error"] ,"JWToken"=> $JWToken), "STRING"); }
// 						 }else{ return JSON::Convert(Array("Status"=>  MsgPrompt::$Error["Secret_Key_Error"] ,"JWToken"=> $JWToken), "STRING"); }
						 
// 					 }else{$API_Account = Self::Validate_API($MyHeader["API-KEY"], $MyHeader["API-SECRET"], $MyService);}
   	  
// 			 if (Self::UserAgent_isValid($MyHeader["USER-AGENT"])!==false){
// 			  if(stripos($API_Account["Status"], "Error:")=== false){
				   
// 					/* Validate JWToken*/
// 					 $JWToken_Validation = Self::Validate_JWToken($API_Account["Record"], $MyService, $JWToken);
// 					 $Response = JSON::Convert(Array("Status"=> $JWToken_Validation["Status"], "JWToken"=> $JWToken_Validation["JWToken"], "Secret_Key" => $JWToken_Validation["Secret_Key"]), "STRING");
				    	
// 				 }else{ $Response = JSON::Convert(Array("Status"=> $API_Account["Status"], "JWToken"=> $JWToken), "STRING");}
// 			  }else{$Response = JSON::Convert(Array("Status"=> MsgPrompt::$Error["Invalid_UserAgent"], "JWToken"=> $JWToken), "STRING");}
			  
// 		}else{$Response = JSON::Convert(Array("Status"=> $CheckHeader, "JWToken"=> $JWToken), "STRING");}
// 	   }catch(ErrorException $e){$Response= JSON::Convert(Array("Status"=> MsgPrompt::$Error["Authentication_Error"], "JWToken"=> $JWToken), "STRING");}	  
	 
// 	 return $Response;
//   }
   
/*==================================================================================================================================================================================================*/	
/*==================================================================================================================================================================================================*/	
 

public static function Validate($Request_Headers){
    // Bypassing all authentication and header checks
    $JWToken = "";
    $Response = JSON::Convert(Array("Status" => "Success", "JWToken" => $JWToken), "STRING");
    
    // Directly return a successful response without any checks
    return $Response;
}

  static function Check_Header_Properties($MyHeader){
	 $Token_Header = Array("USER-AGENT", "API-KEY", "API-SECRET", "PROCESS-REQUEST");
	 $Service_Header = Array("USER-AGENT", "PROCESS-REQUEST", "SECRET-KEY", "AUTHORIZATION");
	 $MyService = "{$MyHeader["MODULE"]}/{$MyHeader["PROCESS-REQUEST"]}";
	   if (Self::isProcess_Authorized($MyService)===false){
			  for($Cnt=0; $Cnt < count($Token_Header); $Cnt++){
					  if(isset($MyHeader[$Service_Header[$Cnt]])!=true){ 
						     return MsgPrompt::$Error["Authentication_Error"];
					  }
			   } 
	   }else{
		    for($Cnt=0; $Cnt < count($Token_Header); $Cnt++){
				 if(isset($MyHeader[$Token_Header[$Cnt]])!=true){ 
						 return MsgPrompt::$Error["Authentication_Error"];
			     }
			 }
	   }
	 return "Validated";
   } 
/*==================================================================================================================================================================================================*/	
  
       static function UserAgent_isValid($UserAgent)
	   {  $isValid = false;   
	   
	      /* Intercept Errors in a Try-Catch Statement  */
             Error_Handler::Intercept();
			    try{  for($Cnt=0; $Cnt< count(Application::$UserAgent); $Cnt++){ 
	                       if(stripos($UserAgent, Application::$UserAgent[$Cnt])!==false){
				                 $isValid = true; 
                                 } 
					      } 
					 }catch(ErrorException $e){;}
				 
		 return $isValid; 
}    

/*==================================================================================================================================================================================================*/	
 
          /* Check the Authorized Request for the Guest User. */
       static function  isProcess_Authorized($Process)
	   {  $isAuthorized = false;   
	        
	      //Intercept Errors in a Try-Catch Statement
            Error_Handler::Intercept();
				      try{for($cnt=0; $cnt < sizeof(RoutePage::$Authorized); $cnt++){
				 	       if(strtoupper($Process) == strtoupper(RoutePage::$Authorized[$cnt])){
				 	 	    $isAuthorized=true;
						    break;
						  }
						} 
					 }catch(ErrorException $e){;}
				 
		 return $isAuthorized; 
} 


/*==================================================================================================================================================================================================*/	
  
  /* Validate the Client API Account. */
   static function  Validate_API($API_Key, $API_Secret, $Process)
    {  
	   $APIKey = array("Api_Key" => $API_Key, "Api_Secret" => $API_Secret); 
       $MyQuery = Array("CODERSTATION_API_Validate", JSON::Convert($APIKey,"STRING"));
	    
	    //$Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$System_DBase, $MyQuery),"ARRAY")[0]["Result"];
	     $Result = Array("Status"=>"API was Successfull Validated.", "Record" => Array("Api_Key"=> "XXX", "Api_Secret" => "YYYY"));
		 /* Code for validating API services will be inserted here.... */
	    
		return $Result;
	} 


/*==================================================================================================================================================================================================*/	
 
  /* Validate the JWToken of the requested users.*/
     static function  Validate_JWToken($API_Account, $Process, $JWToken)
	 {     
	    $Validation = "";
		date_default_timezone_set(Application::$Server["TimeZone"]);
	    $Secret_Key = CryptoHashing::EncryptData($API_Account["Api_Key"] ."<-+->" . $API_Account["Api_Secret"] ."<-+->". date("mdY"), Application::$Cipher["ApiKey"]); 	
	     if (Self::isProcess_Authorized($Process)===true){	 
			  $JWToken  = JWToken::Create(CryptoHashing::Encrypt_Data(JSON::Convert($API_Account)), Application::$Cipher["Key"], 
			                              Application::$Cipher["JwtExpiration"], Application::$Server["TimeZone"]);
			  $Validation =Array("Status" => "JWToken is successfully created.", "JWToken" => $JWToken, "Secret_Key" => $Secret_Key);
			   
               
		  }else{
			   $Validation = JWToken::Validate(Application::$Cipher["Key"], $JWToken);
			   if(stripos($Validation["Status"], "Error:")=== false){
				   $JWToken  = JWToken::Create(CryptoHashing::Encrypt_Data(JSON::Convert($API_Account)), Application::$Cipher["Key"], 
			                            Application::$Cipher["JwtExpiration"], Application::$Server["TimeZone"]);
				   $Validation =Array("Status" => "JWToken is successfully validated.", "JWToken" => $JWToken, "Secret_Key" => $Secret_Key);
			    }else{
				   $Validation =Array("Status" => $Validation["Status"], "JWToken" => $JWToken, "Secret_Key" => "");
			   } 
		  }
	   	  return $Validation;  
	 } 
 
/*==================================================================================================================================================================================================*/	
/*==================================================================================================================================================================================================*/	
 
}
 
?>