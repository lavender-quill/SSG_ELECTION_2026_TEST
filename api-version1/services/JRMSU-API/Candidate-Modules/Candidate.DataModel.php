<?php
 
  class Candidate_DataModel{
/*==============================================================================================================================================================================================================================*/
	 
 	      Const Register_Candidate_JsonSchema = '{"Type": "object",
											        "Properties":{ "Student_ID" 		: {"Type": "string"},
																   "Position_ID"		: {"Type": "integer"},
													               "Candidate_Slate_ID" : {"Type": "integer"},
																   "Election_Year" 		: {"Type": "string"}
													              },
										            "Required": ["Student_ID", "Position_ID", "Candidate_Slate_ID", "Election_Year"]
										      }';		
	    
/*==============================================================================================================================================================================================================================*/
    
 	 
 	      Const Update_Candidate_Profile_Status_JsonSchema = '{"Type": "object",
																"Properties":{ "Student_ID" 		: {"Type": "string"},
																			   "Election_Year"		: {"Type": "string"},
																			   "Application_Status"	: {"Type": "string"}
																			},
																"Required": ["Student_ID", "Election_Year", "Application_Status"]
													}';		

		Const Get_Candidate_ID_Schema = '{"Type": "object",
													"Properties":{ 
																   "Election_Year"		: {"Type": "string"}
																  
																},
													"Required": [ "Election_Year"]
										}';													
	    
/*==============================================================================================================================================================================================================================*/
    
Const Generate_Ballot_JsonSchema = '{
	"type": "object",
	"properties": {
	  "Election_year": { "type": "string" },
	  "College_Code": { "type": "string" },
	  "Candidate_Info": {
		"type": "array",
		"items": {
		  "type": "object",
		  "properties": {
			"Student_ID": { "type": "string" },
			"Candidate_Name": { "type": "string" },
			"College_Code": { "type": "string" }
		  },
		  "required": ["Student_ID", "Candidate_Name", "College_Code"]
		}
	  }
	},
	"required": ["Election_year", "College_Code", "Candidate_Info"]
  }';
  


  Const Get_All_Candidates = '{"Type": "object",
  "Properties":{ 
				 "Election_Year"		: {"Type": "string"},
				  "Application_Status"		: {"Type": "string"}
				
			  },
  "Required": [ "Election_Year" , "Application_Status"]
}';			

/*==============================================================================================================================================================================================================================*/
Const Upload_Photo_jsonSchema = '{"Type": "object",
"Properties":{ 
			   "Photo"		: {"Type": "string"},
				"Candidate_ID"		: {"Type": "string"}
			  
			},
"Required": [ "Photo" , "Candidate_ID"]
}';	
		
		
	 }
 ?>