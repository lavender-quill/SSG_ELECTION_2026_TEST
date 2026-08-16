<?php

  /*=============================================================*/
  /*	Coded by	: Armando T. Saguin Jr.						 */
  /*				  Coderstation Information System Innovator  */
  /*	Email		: saguin.armando.jr@gmail.com				 */
  /*	Mobile No.	: +639306694943								 */
  /*	Version		: 1.0.01									 */
  /*=============================================================*/
  
  namespace Controller;
    
  use Configuration\MsgPrompt 	as MsgPrompt;
  use Configuration\Application as Application;
  use Configuration\Route_Page  as Route_Page;
  
  use Extension\JSON 			as JSON;
  use Extension\Error_Handler   as Error_Handler;
   
  class Route{
	  
      static $Curl_Error = Array("301 Moved Permanently", "HTTP 404", "ErrorException");
/*==================================================================================================================================================================================================*/	
 	       
	  
		public static function Info(){
				
			$Module="";
			$Event="";
        
			$Method = strtoupper($_SERVER['REQUEST_METHOD']);
			 
			$Localhost = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .  "://localhost";  
		    $Host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .  "://$_SERVER[HTTP_HOST]";  
			
  
			$Extension = explode(dirname($_SERVER['PHP_SELF']), $Host . str_replace("%20"," ",$_SERVER['REQUEST_URI']));
				if(sizeof($Extension)>=2){
					if(strlen($Extension[1])<=1){$Module="";}
					else{ $Module=$Extension[1];
						$Request = explode("/", str_replace("//","/", $Module));
							if(sizeof($Request)>=2){
								$Module= $Request[1];
								$Event = explode($Module, $Extension[1])[1];
								if ($Event[0] === '/') { $Event = substr($Event, 1);}
							} 
					}
				}else{ $Module="";}

				$Controller ="";
				/* Get Controller Address */
						for($cnt=0; $cnt < sizeof(Route_Page::$Services); $cnt++){
							   if(strtoupper($Module) == strtoupper(Route_Page::$Services[$cnt]["Module"])){
									$Controller =   $Localhost . str_replace("//","/", dirname($_SERVER['PHP_SELF'])) .  Services_Folder .  Route_Page::$Services[$cnt]["Page"];
									 $Controller .=  "/". Route_Page::$Services[$cnt]["Module"];
									break;
								}
					    }
	 
			return array("URL" 			=> $Host .  str_replace("//","/", str_replace("%20"," ",$_SERVER['REQUEST_URI'])),
						 "Method" 		=> $Method, 
						 "Host" 		=> $Host, 
						 "DIR" 			=> str_replace("//","/", dirname($_SERVER['PHP_SELF'])), 
						 "Controller" 	=>  $Controller . ".Controller.php", 
						 "Module"		=> str_replace("//","/", $Module), 
						 "Process" 		=> str_replace("//","/", $Event)) ;
     }
						
			 
/*==================================================================================================================================================================================================*/	
    
	 public static function Controller($Data){
		          $Response="";
		                /* Intercept Errors in a Try-Catch Statement */
                             Error_Handler::Intercept();
							 
				               try{ //Request URL Page to open.
							       if(Self::Info()["Controller"]!=".Controller.php"){ 
								   
										$MyCurl = curl_init(Self::Info()["Controller"]);
								     
										curl_setopt($MyCurl, CURLOPT_RETURNTRANSFER, true);
										curl_setopt($MyCurl, CURLOPT_CUSTOMREQUEST, Self::Info()["Method"]);  
										curl_setopt($MyCurl, CURLOPT_POSTFIELDS, $Data);
										curl_setopt($MyCurl, CURLINFO_HEADER_OUT, true);
										curl_setopt($MyCurl, CURLOPT_HTTPHEADER, array(
												"Content-Type: application/json", 			/* Set content type to JSON   */
												"Content-Length: " . strlen($Data) 			/* Set content length         */
												));
										curl_setopt($MyCurl, CURLOPT_TIMEOUT, 20); 	   	    /* Set timeout to 20 seconds  */
										$Response = curl_exec($MyCurl);
										curl_close($MyCurl);
								    }
								   }catch(ErrorException $Err){
									 $Response= JSON::Convert(Array("Status"=>  MsgPrompt::$Error["Controller_NotFound"]), "STRING");
								  }
							   
							   if(trim($Response)==""){
								   $Response= JSON::Convert(Array("Status"=>  MsgPrompt::$Error["Controller_NotFound"]), "STRING"); 
							   }
                           							   
							  /* Check Error */
							  for($cnt=0; $cnt < sizeof(Self::$Curl_Error); $cnt++){
								   if(stripos($Response, Self::$Curl_Error[$cnt])!==false){
									   $Response = JSON::Convert(Array("Status"=> MsgPrompt::$Error["Controller_NotFound"]), "STRING");
									  break;
							     }
							  }
							  
			  return $Response;
	  }

/*==================================================================================================================================================================================================*/	
 		  
	 }
 ?>