<?php
   
     
	class Voter_ExtModel{
/*==============================================================================================================================================================================================================================*/

    /**
     * Returns the API security headers, reading ARMS_API_KEY and ARMS_API_SECRET
     * from environment/Replit Secrets (never hardcoded in source).
     */
    static function GetSecurity(): array {
        return [
            "API-Key"    => getenv('ARMS_API_KEY')    ?: '',
            "API-Secret" => getenv('ARMS_API_SECRET') ?: '',
            "User-Agent" => "Coderstation-Protocol",
        ];
    }

    /** @deprecated Use Voter_ExtModel::GetSecurity() instead. */
    static $Security = Array("API-Key" => "",
	                         "API-Secret" => "",
                             "User-Agent" => "Coderstation-Protocol"  					   
	                    );  	
						
/*==============================================================================================================================================================================================================================*/
 
    static function Dispatch($EndPoint, $Security, $MyRecord) { 
	 
	 $MyCurl = curl_init($EndPoint);
     $Header = array("");
	
    if(isset($Security["Secret-Key"])) {
       $Header = array(
				"Secret-Key: {$Security['Secret-Key']}",
				"User-Agent: {$Security['User-Agent']}",
				"Authorization: {$Security['Authorization']}",
				"Content-Type: application/json",
				"Content-Length: " . strlen($MyRecord)
	    );
    } else {
		$Header =array(
				"Api-Key: {$Security['API-Key']}",
				"Api-Secret: {$Security['API-Secret']}",
				"User-Agent: {$Security['User-Agent']}",
				"Content-Type: application/json",
				"Content-Length: " . strlen($MyRecord)
	    );
    }
    
	curl_setopt($MyCurl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($MyCurl, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($MyCurl, CURLOPT_POSTFIELDS, $MyRecord);
	curl_setopt($MyCurl, CURLINFO_HEADER_OUT, true);
	//curl_setopt($MyCurl, CURLOPT_HEADER, true);  
	curl_setopt($MyCurl, CURLOPT_HTTPHEADER, $Header);
	curl_setopt($MyCurl, CURLOPT_TIMEOUT, 20);
	curl_setopt($MyCurl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($MyCurl, CURLOPT_SSL_VERIFYHOST, false);

	 $Response = curl_exec($MyCurl);
     curl_close($MyCurl);
	return $Response;
   }	
 
/*==============================================================================================================================================================================================================================*/
	  
/*==============================================================================================================================================================================================================================*/
	 	  

 }
	
 ?>