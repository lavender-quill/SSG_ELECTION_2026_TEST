<?php
 
  class Voter_DataModel{
/*==============================================================================================================================================================================================================================*/
	 
 	      Const Search_Record_JsonSchema = '{"Type": "object",
											        "Properties":{ "Student_ID" : {"Type": "string"},
																   "Semester"	: {"Type": "string"},
													               "School_Year": {"Type": "string"}
													              },
										            "Required": ["Student_ID", "Semester", "School_Year"]
										      }';		
	    
/*==============================================================================================================================================================================================================================*/
    
 	 
 	      Const Login_JsonSchema = '{"Type": "object",
											        "Properties":{ "Student_ID" : {"Type": "string"},
																   "Password"	: {"Type": "string"},
																   "Semester"	: {"Type": "string"},
													               "School_Year": {"Type": "string"}
													              },
										            "Required": ["Student_ID", "Password", "Semester", "School_Year"]
										      }';		
	    
/*==============================================================================================================================================================================================================================*/
    
/*==============================================================================================================================================================================================================================*/
Const Student_Get_Info_JsonSchema = '{
	"Type": "array",
	"Items": {
	  "Type": "object",
	  "Properties": {
		"Student_ID": { "Type": "string" }
	  },
	  "Required": ["Student_ID"]
	}
  }';
  

  Const Update_Account_JsonSchema = '{"Type": "object",
  "Properties":{ "Student_ID" : {"Type": "string"},
				 "Student_Name"	: {"Type": "string"},
				 "Sex"	: {"Type": "string"},
				 "Program_Enrolled": {"Type": "string"},
				 "Major"	: {"Type": "string"},
				  "Year_Level"	: {"Type": "string"},
				 "Semester"	: {"Type": "string"},
				 "School_Year"	: {"Type": "string"},
				 "Admission_Status"	: {"Type": "string"},	
				 "Enrollment_Status"	: {"Type": "string"}
				 
				 
				},
  "Required": ["Student_ID", "Student_Name", "Sex", "Program_Enrolled", "Major", "Year_Level" , "Semester" , "School_Year" , "Admission_Status" , "Enrollment_Status"]
}';		
		    Const Search_Student_JsonSchema = '{"Type": "object",
											        "Properties":{ "Student_ID" : {"Type": "string"},
																   "Semester"	: {"Type": "string"},
													               "School_Year": {"Type": "string"}
													              },
										            "Required": ["Student_ID", "Semester", "School_Year"]
										      }';	
		


	   Const UpdatePassword_JsonSchema = '{"Type": "object",
											        "Properties":{ "Student_ID" : {"Type": "string"},
																   "Password"	: {"Type": "string"}
																   
													              },
										            "Required": ["Student_ID", "Password"]
										      }';	


	  Const Get_Casted_Voter = '{"Type": "object",
											        "Properties":{ 
													               "School_Year": {"Type": "string"}
													              },
										            "Required": ["School_Year"]
										      }';	

	  Const Get_All_Voter = '{"Type": "object",
											        "Properties":{ 
													               "School_Year": {"Type": "string"}
													              },
										            "Required": ["School_Year"]
										      }';	
				

	 }
 ?>