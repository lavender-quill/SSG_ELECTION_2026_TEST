<?php

  ////////////////////////////////////////////////////////////////
  //	Coded by	: Armando T. Saguin Jr.						//
  //				  Coderstation Information System Innovator //
  //	Email		: saguin.armando.jr@gmail.com				//
  //	Mobile No.	: +639306694943								//
  //	Version		: 1.0.01									//
  ////////////////////////////////////////////////////////////////
  
  namespace Notifier;
   
  use Configuration\MsgPrompt 		as MsgPrompt;
  use Configuration\Application 	as Application;
  
  use Extension\JSON 				as JSON;
   
  class Email{
	   
 //================================================================================================================================================================================
 public static function Send_Code($Record){
			            $Result = JSON::Convert(Array("Status"=> $Record["Success_Message"]), "STRING"); 
				         
						 ob_start();   
					       include("Email-Templates/Email-Secret.html");
					  	  $EmailMessage =  ob_get_contents();
						 ob_clean();
						  
						$EmailMessage = str_replace("[MYTITLE]", $Record["EmailTitle"], $EmailMessage);
						$EmailMessage = str_replace("[SECRET]", $Record["Label"], $EmailMessage);   
						$EmailMessage = str_replace("[MYCODES]", $Record["SecretCode"], $EmailMessage); 
						$EmailMessage = str_replace("[MYCOMPANY]", Application::$System["Issuer"], $EmailMessage); 
						$EmailMessage = str_replace("[AUTHOR]", Application::$System["Developer"], $EmailMessage); 
					   
		                 $MailRecipient =  $Record["Email_Address"];
		                 $MailSubject = $Record["Mail_Subject"];
		                  
		                $MailHeaders = "From:" . Application::$System["Issuer"] . "<" . Application::$Server["support"] . ">\r\n";
                        $MailHeaders.= "Reply-To: ". Application::$System["Issuer"] . "<" . Application::$Server["support"] . ">\r\n";
                        //$MailHeaders .= "CC: support@coderstation.net\r\n";
                        $MailHeaders .= "MIME-Version: 1.0\r\n";
                        $MailHeaders .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
           
                           if(mail($MailRecipient, $MailSubject, $EmailMessage, $MailHeaders)){ 
						         $Result = JSON::Convert(Array("Status"=> $Record["Success_Message"]), "STRING"); 
						   }else{ 
						         $Result = JSON::Convert(Array("Status"=> $Record["Error_Message"]), "STRING");
						   }
		              
		      return $Result;
		  }
		  
//==============================================================================================================================================================================
//================================================================================================================================================================================
		  
	 }
 ?>