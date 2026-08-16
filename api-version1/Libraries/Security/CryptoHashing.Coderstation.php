<?php

  ////////////////////////////////////////////////////////////////
  //	Coded by	: Armando T. Saguin Jr.						//
  //				  Coderstation Information System Innovator //
  //	Email		: saguin.armando.jr@gmail.com				//
  //	Mobile No.	: +639306694943								//
  //	Version		: 1.0.01									//
  ////////////////////////////////////////////////////////////////

//==============================================================================================================================================================================
//==============================================================================================================================================================================
 
namespace Security;

class CryptoHashing{
  
//==============================================================================================================================================================================
//==============================================================================================================================================================================

public static function EncryptData($PlainText, $CipherKey="54GU1N") {
	    
	   $CipherKey = $CipherKey . "#546U1N-04271983-01131979-C4MP05#";
	   $CipherKey = substr(hash('sha256', $CipherKey, true), 0, 32);
       $Method = 'aes-256-cbc';
	   
     // IV must be exact 16 chars (128 bit)
       $InitVector = chr(0x0) . chr(0x1) . chr(0x1) . chr(0x3) . chr(0x1) . chr(0x9) . chr(0x7) . chr(0x9) . chr(0x0) . chr(0x4) . chr(0x2) . chr(0x7) . chr(0x1) . chr(0x9) . chr(0x8) . chr(0x4);
      
       $Encrypted = Self::Encode_Base64url(openssl_encrypt($PlainText, $Method, $CipherKey, OPENSSL_RAW_DATA, $InitVector));
	   $Encrypted= strtoupper(implode(unpack("H*", $Encrypted)));
	  
    return $Encrypted;
}

//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

public static function Encrypt_Data($PlainText, $CipherKey="54GU1N") {
	    
	$Encrypted = Self::EncryptData($PlainText, $CipherKey);    
 	$Encrypted= hex2bin($Encrypted);  
	
    return $Encrypted;
}

//==============================================================================================================================================================================

public static function DecryptData($CipherText, $CipherKey="54GU1N") {
	  
    $Decrypted="";
     try {   
			if (!preg_match('/^[0-9A-Fa-f]+$/', trim($CipherText))) { return "Error";}
            if (strlen(trim($CipherText)) % 2 !== 0) {return "Error"; }
	
           $CipherText = pack("H*", TRIM($CipherText));
	       $CipherKey = $CipherKey . "#546U1N-04271983-01131979-C4MP05#";
           $CipherKey = substr(hash('sha256', $CipherKey, true), 0, 32);
	       $Method = 'aes-256-cbc';
	    
         // IV must be exact 16 chars (128 bit)
       $InitVector = chr(0x0) . chr(0x1) . chr(0x1) . chr(0x3) . chr(0x1) . chr(0x9) . chr(0x7) . chr(0x9) . chr(0x0) . chr(0x4) . chr(0x2) . chr(0x7) . chr(0x1) . chr(0x9) . chr(0x8) . chr(0x4);
            
          $Decrypted = openssl_decrypt(Self::Decode_Base64url($CipherText), $Method, $CipherKey, OPENSSL_RAW_DATA, $InitVector);
           
	    }catch(ErrorException $e){return "Error";}
		   
		  
    
	return $Decrypted;
}

//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

public static function Decrypt_Data($CipherText, $CipherKey="54GU1N") {
	  
     $CipherText = bin2hex($CipherText);    
     $Decrypted = Self::DecryptData($CipherText, $CipherKey);
     
    return $Decrypted;
}

//==============================================================================================================================================================================
//==============================================================================================================================================================================

public static function HashData($Difficulty, $rawData,  $SecretKey="546U1N") {
      $Nonce=0;
	  $SecretKey = hash("sha256", $SecretKey); 
	   
	   $ExtraChar = "ATSJ-RACS-KJCS-BACS-JMCS-JBKS-A-B-C-D-E-F";
	   
	    if ($Difficulty >= 7) { $Difficulty = 6; }
        else if ($Difficulty <= 0) { $Difficulty = 1; }
	   
            $TempHashResult = hash("sha256", $SecretKey . $rawData . $SecretKey);
            $Zero_string = "";
			
			$PadStr = str_pad("", $Difficulty, "0", STR_PAD_BOTH);
		    $Nonce=0;
	 
	  while(substr($TempHashResult ,0, $Difficulty)!==$PadStr)
	    {  $TempHashResult = hash("sha256", $TempHashResult . $Nonce);
	       $Nonce++;
	     }
		 
		 $TempHashResult =  str_pad("",$Difficulty,"0",STR_PAD_RIGHT) . explode("-", $ExtraChar)[$Difficulty-1] . explode(str_pad("",$Difficulty,"0",STR_PAD_RIGHT), $TempHashResult)[1];
		     
			 $_Sha1 = sha1($TempHashResult . explode("-", $ExtraChar)[$Difficulty-1] . $Nonce);
		     $_Sha1_Part1 =  substr(substr($_Sha1, 0, 20),-10);
			 $_Sha1_Part2 =  substr($_Sha1, 0, 10);
			 $_Sha1_Part3 =  substr($_Sha1, -10);
			 $_Sha1_Part4 =  substr(substr($_Sha1, -20),0,10);
			 
			 $_Alpha=["HJM","HKJ","HBA","HRC","HAS", "H03S", "H13S", "H12S", "H84S", "H79S"];
			 $_Pointer = str_split(str_pad($Nonce, 4 , "0", STR_PAD_RIGHT));
			 
			 $Combi = explode("-", $ExtraChar)[$Difficulty-1+6] . str_pad("",$Difficulty,"0",STR_PAD_RIGHT) . $Nonce . $_Alpha[(int)$_Pointer[0]]  . $_Sha1_Part1 . $_Alpha[(int)$_Pointer[1]] . $_Sha1_Part2 . $_Alpha[(int)$_Pointer[2]] . $_Sha1_Part3 . $_Alpha[(int)$_Pointer[3]] . $_Sha1_Part4;
			 $TempHashResult  =  explode("-", $ExtraChar)[$Difficulty-1]  . $Combi ;  
			 
	         $TempHashResult =  strtoupper(str_pad($TempHashResult . $_Alpha[(int)$_Pointer[0]], 75, $_Sha1_Part1, STR_PAD_RIGHT));
	    return Array("Value" => $TempHashResult, "Nonce" => $Nonce);
	  
     }
	 
//==============================================================================================================================================================================
//==============================================================================================================================================================================
 public static function Is_HashCode_Valid($Difficulty, $rawData,  $SecretKey="546U1N", $HashCode="") {
    $isValid = false;   
       if(Self::HashData($Difficulty, $rawData,  $SecretKey)== $HashCode){
		   $isValid= true;
	   }else{$isValid = false;} 
	return $isValid;
 }

//==============================================================================================================================================================================
//==============================================================================================================================================================================
 
public static function Encode_Base64url($data)
{ 
  $b64 = base64_encode($data);
 
  if ($b64 === false) {
    return false;
  }
 
  $url = strtr($b64, '+/', '-_');
 
  return rtrim($url, '=');
}
//==============================================================================================================================================================================

 
public static function Decode_Base64url($data, $strict = false)
{  
  $b64 = strtr($data, '-_', '+/');
  return base64_decode($b64, $strict);
}
 
//==============================================================================================================================================================================
//==============================================================================================================================================================================

  
 public static function RandomPassword($Length)
  {
     $Comb = 'X23456789$ABCDEFGHIJKLMN$PQRSTUVWXYZ$23456789$';
	 $StrRandom = array(); 
	 $CombLen = strlen($Comb) - 1; 
           for ($iCnt = 0; $iCnt < $Length; $iCnt++) {
                 $Num = rand(0, $CombLen);
	             $Found=0;
	 
                $Temp = strtoupper($Comb[$Num]);
	            for($Cnt=0; $Cnt < $iCnt; $Cnt++) { 
				      if($Temp==$StrRandom[$Cnt]) 
					     {$Found=1; $iCnt--; break;}
		        }
	          if($Found==0){$StrRandom[] = $Temp;}
	         }
       return implode($StrRandom); 
  }
  
//==============================================================================================================================================================================
//==============================================================================================================================================================================

  
 public static function Random2FA($Length)
  {
     $Comb = '1234567890';
	 $StrRandom = array(); 
	 $CombLen = strlen($Comb) - 1; 
           for ($iCnt = 0; $iCnt < $Length; $iCnt++) {
                 $Num = rand(0, $CombLen);
	             $Found=0;
	 
                $Temp = strtoupper($Comb[$Num]);
	             $StrRandom[] = $Temp; 
	         }
       return implode($StrRandom); 
  }
    
   
//==============================================================================================================================================================================
//==============================================================================================================================================================================




 }
 
?>