<?php

class Election_DataModel
{
	/*==============================================================================================================================================================================================================================*/

	const Search_Record_JsonSchema = '{"Type": "object",
											        "Properties":{ "Student_ID" : {"Type": "string"},
																   "Semester"	: {"Type": "string"},
													               "School_Year": {"Type": "string"}
													              },
										            "Required": ["Student_ID", "Semester", "School_Year"]
										      }';

	/*======================================================================================================================================================================================================================================*/

	const Vote_Check_Availability = '{"Type": "object",
											  "Properties":{ "School_Year" : {"Type": "string"}},
											  "Required": ["School_Year"]
										}';





	/*==============================================================================================================================================================================================================================*/

	// tomsss
	const vote_record_JsonSchema = '{
	"Type": "object",
	"properties": {
	  "Voter_ID": { "Type": "string" },
	  "School_Year": { "Type": "string" },
	  "votes_list": {
		"Type": "array",
		"items": {
		  "Type": "object",
		  "properties": {
			"position_name": { "type": "string" },
			"candidates": {
			  "Type": "array",
			  "items": {
				"Type": "object",
				"properties": {
				  "candidate_id": { "Type": "string" }
				},
				"required": ["candidate_id"]
			  }
			}
		  },
		  "required": ["position_name", "candidates"]
		}
	  }
	},
	"required": ["Voter_ID", "School_Year", "votes_list"]
  }';


	/*==============================================================================================================================================================================================================================*/
	const election_generate_result_JsonSchema = '{
    "Type": "object",
    "Properties": {
        "School_Year": { "Type": "string" },
        "College_Code": { "Type": "string" },
        "Candidates": {
            "Type": "array",
            "items": {
                "Type": "object",
                "properties": {
                    "Candidate_ID": { "Type": "string" },
                    "Position": { "Type": "string" },
                    "Candidate_Slate": { "Type": "string" },
                    "Candidate_Name": { "Type": "string" }
                },
                "required": ["Candidate_ID", "Position", "Candidate_Slate", "Candidate_Name"]
            }
        }
    },
    "required": ["School_Year", "College_Code", "Candidates"]
}';


	const User_Vote_Status = '{"Type": "object",
							"Properties":{ "Voter_ID" : {"Type": "string"},
							
							"School_Year": {"Type": "string"}
							 },
							"Required": ["Voter_ID", "School_Year"]
							 }';
	const User_Log_JsonSchema = '{"Type": "object",
							 "Properties":{ "User_ID" : {"Type": "string"}
							 
							
							  },
							 "Required": ["User_ID"]
							  }';

	const Insert_User_Log_JsonSchema = '{"Type": "object",
							  "Properties":{ "User_ID" : {"Type": "string"},
							  				 "Service_ID" : {"Type": "string"}
							 
							   },
							  "Required": ["User_ID"]
							   }';
	const User_Account_CRUD_JsonSchema = '{
											"Type": "object",
											"Properties": {
												"Action": {
												"Type": "string",
												"enum": ["UPSERT", "DELETE", "VIEW_ALL"]
												},
												"_Username": { "Type": "string" },
												"_Student_ID": { "Type": "string" },
												"_Userlevel": { "Type": "string" }
												
											},
											"required": ["Action"],
											"allOf": [
												{
												"if": {
													"properties": { "Action": { "const": "UPSERT" } }
												},
												"then": {
													"required": ["_Username", "_Student_ID", "_Userlevel"]
												}
												},
												{
												"if": {
													"properties": { "Action": { "const": "DELETE" } }
												},
												"then": {
													"required": ["_Username", "_Student_ID"]
												}
												}
											],
											"additionalProperties": false
											}
											';

	const User_Account_Update_Status = '{"Type": "object",
											"Properties":{ "UserName" : {"Type": "string"},
															"User_Status" : {"Type": "string"}
										   
											 },
											"Required": ["UserName" , "User_Status" ]
											 }';
	const App_Service_CRUD = '{
								"Type": "object",
								"Properties": {
									"Action": {
									"Type": "string",
									"enum": ["INSERT", "DELETE", "VIEW_ALL"]
									},
									"Service_Name": {
									"Type": "string"
									}
								},
								"required": ["Action"],
								"if": {
									"properties": { "Action": { "const": "VIEW_ALL" } }
								},
								"else": {
									"required": ["Service_Name"]
								},
								"additionalProperties": false
								}
								';

}

	const Create_ScheduleJsonSchema = '{"Type": "object",
											        "Properties":{ "Time_Start" : {"Type": "string"},
																   "Time_End"	: {"Type": "string"},
													               "School_Year": {"Type": "string"}
													              },
										            "Required": ["Time_Start", "Time_End", "School_Year"]
										      }';

	const election_count_per_college_JsonSchema = '{
													"Type": "object",
													"Properties": {
														"School_Year": { "Type": "string" },
														"Students": {
															"Type": "array",
															"items": {
																"Type": "object",
																"properties": {
																	"Student_ID": { "Type": "string" },
																	"Student_Name": { "Type": "string" },
																	"College_Description": { "Type": "string" }
																	
																},
																"required": ["Student_ID", "Student_Name", "College_Description"]
															}
														}
													},
													"required": ["School_Year", "Students"]
												}';
