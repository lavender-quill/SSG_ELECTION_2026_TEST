<?php
   
  Namespace Configuration;
   
    class MsgPrompt{
		 
		public static $Error = array(
		
						 "AccessCode_Expired"			=> "Error: Your Access Code was overwrite with the new user.",
		                 "Access_OTP_Error"				=> "Error: Your OTP access token is not valid for this service request.",

						 "API_Service_Error"			=> "Error: The Client's API-Key was not authorized to access the service.",
						 "API_Not_Recognized"			=> "Error: The API-Key provided by the client was not recognized.",
						 "API_Invalid_Client"           => "Error: The Client's IP address is not authorized to use the API-Key.",
						 "Authentication_Error"			=> "Error: Client Authentication failed due to missing properties.",
						 
						 "Controller_NotFound"			=> "Error: The API endpoint is not valid in the service...",
						 
						 "Email_Error"					=> "Error: Server station is unable to connect to the mail server.",
						  
						 "Invalid_AccessCode"		    => "Error: The AccessCode is Invalid, Indicating that alterations may have been made to it.",
						 "Invalid_Data"		    		=> "Error: Unable to process the incorrect data.",
						 "Invalid_UserAgent"    		=> "Error: The server's security does not recognize the client's User Agent.",
						 
						 "JSON_Schema_Array_Error"      => "Error: An 'ARRAY' of records must be submitted according to the required JSON schema.",
						 "JWToken_Error"			    => "Error: The JSON Web Token is either invalid or not found.",
						 
						 "Parsing_Error"		        => "Error: The parsing error occurred due to invalid JSON schema input.",
					     "Request_Error"				=> "Error: The requested Process is not Valid.", 
						  
						 "Required_PostMethod"			=> "Error: This request requires the use of a POST method.", 
						 "Restricted_Module"			=> "Error: The current User is unable to access this module.",
						 "Record_NotFound"				=> "Error: The requested record is not found in the database server.",
						 
						 "Secret_Key_Error"				=> "Error: The Secret Key has already Expired or Invalid.",
						 "Tampered_Error"				=> "Error: The Request is pending because the record has been found tampered with."
                        
						 );
       
 	   }

	  
 ?>
							 