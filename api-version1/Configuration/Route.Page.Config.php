<?php

  ////////////////////////////////////////////////////////////////
  //	Coded by	: Armando T. Saguin Jr.						//
  //				  Coderstation Information System Innovator //
  //	Email		: saguin.armando.jr@gmail.com				//
  //	Mobile No.	: +639306694943								//
  //	Version		: 1.0.01									//
  ////////////////////////////////////////////////////////////////
  
   Namespace Configuration;
 
    class Route_Page{
		 
//=================================================================================================================================		 
																	      // Page    => "Folder/Filename"
		public static $Services = array(  
										 array("Module"	=> "Voter",	        "Page"	=> "Voter-Modules"),
										 array("Module"	=> "Candidate",	    "Page"	=> "Candidate-Modules"),
										 array("Module"	=> "College",	    "Page"	=> "College-Modules"),  										 
										 array("Module"	=> "Election",	    "Page"	=> "Election-Modules"),
										 array("Module"	=> "API-Account",	"Page"	=> "API-Account-Modules") 
									  );
//=================================================================================================================================	
//=================================================================================================================================		
	    
		 public static $Authorized = Array("Token/Request", 
		                                   "API-Account/Profile/Register",
										   "API-Account/Profile/Search",
										   "API-Account/Profile/Validate",
										   "API-Account/Profile/Status/Update",
										   "Election/Votes/Cast",
                                           "Voter/Account/Login",
										   "Voter/Enrollment/Search",
										   "Voter/Profile/Search",
										   "College/Program/Search",
										   "College/Program/Record/Update",
										   "College/Record/Update",
										   "College/Search",
										   "College/Program/Major/Update",
										   "College/Program/Major/Search",
										   "Candidate/Position/Register",
										   "Candidate/Profile/Status/Update",
										   "Election/Votes/Cast",
										   "Election/Schedule/Check",
										   "Election/Votes/Result",
										   "Election/User/Status",
										   "Election/Account/CRUD",
										   "Election/App/CRUD",
										   "Election/Account/Update_Status",
										   "Election/Log/View",
										   "Election/Log/Insert",
										   "Election/Create/Schedule",
										   "Election/Vote/Count/College",								
										   "Voter/Student/Info",
										   "Voter/Profile/Get",
										   "Voter/Get/All",
										   "Candidate/Account/Register",
										   "Candidate/Ballot/Get",
										   "Candidate/Account/ID/Get",
										    "Candidate/Ballot/Generate",
											"Voter/Profile/Update",
											"Candidate/Account/Get/All",
											"Candidate/Account/Update/Photo",
											"Voter/Profile/Search/Dummy",
											 "Voter/Account/Login3",
											 "Voter/Account/Update/Password",
											 "Voter/Account/Count/Casted"


										   
 										    
										   );									   
	        
//=================================================================================================================================		
//=================================================================================================================================		
	    
		
//=================================================================================================================================		
}


	
 ?>
		