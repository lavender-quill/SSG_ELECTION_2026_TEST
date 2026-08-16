<?php
    
    require_once("autoloader.php");
	
	use Controller\Route 		  as Route;
    use Extension\JSON 			  as JSON;
	use Extension\Error_Handler   as Error_Handler;
	
	use Configuration\Application as Application;
	use Configuration\Page_Header as PageHeader;
	use Security\Authorization 	  as Authorization;
	 

    Error_Handler::Intercept();	 
	  $MyToken  = ""; 
	  $Response = "";
	  
  	  /* Recieve Data from Client */  	
	  $MyData   = JSON::Convert(file_get_contents('php://input'), "ARRAY");
	 
	 
	 /* Convert All Header Keys to UpperCase */ 
	  $Request_Headers =  array_change_key_case(apache_request_headers(), CASE_UPPER);  
	  $Request_Headers["PROCESS-REQUEST"] = Route::Info()["Process"];
	  $Request_Headers["MODULE"] = Route::Info()["Module"];
	   
	  try{
			/*Check the Client's Authorization.*/
			 $Authorization = Authorization::Validate($Request_Headers);
		     $JWToken = JSON::Convert($Authorization,"ARRAY")["JWToken"];
		       
           /* Create a Page Header for the client application. */
					$Header["Developer"]	= Application::$System["Developer"]; $Header["Provider"] = Application::$System["Issuer"];
					$Header["AppName"]	= Application::$System["AppName"]; 	 $Header["JWToken"]  = $JWToken;
					PageHeader::Config($Header);
					
	     if(stripos(JSON::Convert($Authorization,"ARRAY")["Status"], "Error:") === false ){
			 
           	 if (strtoupper(Route::Info()["Process"]) != "TOKEN/REQUEST"){
                /*Redirecting to the microservices process.*/				 
				$Response = Route::Controller(JSON::Convert(Array("Request"=> Route::Info()["Process"], "Record"=> $MyData),"STRING")); 
			 }else{ echo JSON::Convert($Authorization,"STRING");}
		 	 
		 }else{  /*Return an Authorization Error Message.*/
		        $Response = JSON::Convert(Array("Status" => JSON::Convert($Authorization,"ARRAY")["Status"]));
		 }echo  $Response; 
		 
		  
	 }catch(ErrorException $e){/*echo "<BR/>$e<BR/>";*/}
	  
?>