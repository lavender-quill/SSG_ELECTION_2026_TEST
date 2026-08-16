<?php
 
  ////////////////////////////////////////////////////////////////
  //	Coded by	: Armando T. Saguin Jr.						//
  //				  Coderstation Information System Innovator //
  //	Email		: saguin.armando.jr@gmail.com				//
  //	Mobile No.	: +639306694943								//
  //	Version		: 1.0.01									//
  ////////////////////////////////////////////////////////////////

  
namespace Security; 

use \DateTime;
use Extension\JSON 			   as JSON;
use Extension\Error_Handler    as Error_Handler;
  
class JWToken{
	 
 public static function Create($Payload, $CipherKey, $JWTExpiration, $Timezone)
 {  
     date_default_timezone_set($Timezone);

     $Header = json_encode(["typ" => "JWT", "alg" => "HS256"], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
     $Payload = json_encode([
        "tkn" => $Payload,	 
        "nbf" => time() + 1,
        "iat" => time(),
        "exp" => time() + (int)$JWTExpiration 
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    
    $Encode_Header = rtrim(strtr(base64_encode($Header), '+/', '-_'), '=');
    $Encode_Payload = rtrim(strtr(base64_encode($Payload), '+/', '-_'), '=');
  
    $Signature = hash_hmac("sha256", $Encode_Header . "." . $Encode_Payload, $CipherKey, true);
    $Encode_Signature = rtrim(strtr(base64_encode($Signature), '+/', '-_'), '=');
    $JWToken = $Encode_Header . "." . $Encode_Payload . "." . $Encode_Signature;

    return $JWToken;
	  }
	
//=========================================================================================================================================
//=========================================================================================================================================

  public static function Validate($CipherKey, $JWToken)
   { 
    if(stripos($JWToken, "Bearer")!==false){ 
        $JWToken =  explode(" ", $JWToken);
	   if(sizeof($JWToken)>=2){
		$JWToken = $JWToken[1]; /* Remove Bearer Word */   
	   }else{
		//Return Error Message
		    $Status = "Error: The JSON Web Token is invalid, prompting users to log in again to resolve the issue.";
		    return Array("Status" => $Status, "Record" => "{}");
	   }        		   
    }  
	
	//Intercept Errors in a Try-Catch Statement
    Error_Handler::Intercept(); 
	try{    
		$MyRecord="";   
		$Token  = explode(".", $JWToken);
		$Header = CryptoHashing::Decode_Base64url($Token[0]);
		$Payload = CryptoHashing::Decode_Base64url($Token[1]);
		$ProvidedSignature = $Token[2];
	 
	     $Encode_Header = CryptoHashing::Encode_Base64url($Header);
	     $Encode_Payload = CryptoHashing::Encode_Base64url($Payload);
		 
		 $Signature = hash_hmac("sha256", $Encode_Header . "." . $Encode_Payload, $CipherKey, true);
	     $Encode_Signature = CryptoHashing::Encode_Base64url($Signature);
         
		 $MyRecord = JSON::Convert($Payload, "OBJECT");
			if(isset($MyRecord->tkn)){
				$MyRecord = $MyRecord->tkn;
			    $isValidSignature = (($ProvidedSignature === $Encode_Signature)? 1:0);
				$Status="JSON Web Token has been Verified and Confirmed as Authentic.";
	   
				if($isValidSignature==0) {$Status = "Error: JSON Web Token has an Invalid Signature.";}
				if(Self::TimeToSeconds(Self::TimeStampDiff(time(), JSON::Convert($Payload, "OBJECT")->exp))<=0){
					$Status  = "Error: JSON Web Token was Already Expired.";
				}	 
			}else{$Status  = "Error: JSON Web Token is not Recognized.";
			      return Array("Status" => $Status, "Record" => $MyRecord);
			}	 
		  
	  }catch(ErrorException $e){ $Status = "Error: JSON Web Token is not Recognized.";}
	   
	   return Array("Status" => $Status, "Record" => $MyRecord);
	  	    
     }
   
//=========================================================================================================================================
 
 private static function TimeStampDiff($Time1,$Time2)
  {
    $datetime1 = new DateTime("@$Time1");
    $datetime2 = new DateTime("@$Time2");
    $interval = $datetime1->diff($datetime2);
	 
    return $interval;
  }
//=========================================================================================================================================
 
 private static function TimeToSeconds($DateInterval)
  {
    $HSec=  $DateInterval->format('%H') * 3600 ;
    $MSec=  $DateInterval->format('%I') * 60  ;
    $Sec=   $DateInterval->format('%s');
    $Symbol=1;
	
	if($DateInterval->format('%r')=="-")
	{$Symbol=-1;}

    return  ($HSec +  $MSec +  $Sec) * $Symbol;
  }
   
 
//=========================================================================================================================================
//=========================================================================================================================================

}
 
?>