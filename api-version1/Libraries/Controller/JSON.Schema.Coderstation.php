<?php

  ////////////////////////////////////////////////////////////////
  //	Coded by	: Armando T. Saguin Jr.						//
  //				  Coderstation Information System Innovator //
  //	Email		: saguin.armando.jr@gmail.com				//
  //	Mobile No.	: +639306694943								//
  //	Version		: 1.0.01									//
  ////////////////////////////////////////////////////////////////
  
  
  namespace Controller;
     
	 
  use Extension\JSON as JSON;
   
  class JSON_Schema{
	   
//==============================================================================================================================================================================
  	    
		public static function Validate($JSON_Data){
		    
			$JSON_Schema = '{"Type": "object",
									 "Properties": { "Request": {"Type": "string" },
													 "Record": {"Type": "object"} 
												   },
									 "Required": ["Request", "Record"]
							}';
							
		   return JSON::ValidateSchema($JSON_Data, $JSON_Schema); 	
	  }
		  
 
//================================================================================================================================================================================
 		  
	 }
 ?>