<?php
  
    use Configuration\Application as Application;
    use Configuration\MsgPrompt   as MsgPrompt;
      
    use Extension\JSON 			  as JSON;
	use Extension\Error_Handler   as Error_Handler;
	
    use PhpDataObject\PdoMySql    as PdoMySql;
     
	class Election{
/*==============================================================================================================================================================================================================================*/
  //  debug toms
//   static function vote_cast($MyRecord) { 
//     // Convert the input to JSON string (if not already)
//     $ConvertedRecord = JSON::Convert($MyRecord); 
    
//     // Validate the converted record using the updated schema
//     $ValidateSchema = JSON::ValidateSchema($ConvertedRecord, Election_DataModel::vote_record_JsonSchema); 
    
//     if ($ValidateSchema["Valid"] == true) {
//         // Call stored procedure 'vote_record' with the JSON string as parameter
//         $MyQuery = Array("vote_record", $ConvertedRecord);

//         // Execute the query using your PDO helper and get the result
//         $QueryResult = PdoMySQL::ExecQuery(Application::$SSG_Election_DBase, $MyQuery);

//         // Convert and extract the result
//         $Result = JSON::Convert($QueryResult, "ARRAY")[0]["Result"];

//         // Output the result in JSON
//         echo JSON::Convert($Result);		  
//     } else {
//         // If validation failed, show validation status
//         echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
//     }
// }

 
 
/*==============================================================================================================================================================================================================================*/

static function election_generate_result($MyRecord) { 
    $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Election_DataModel:: election_generate_result_JsonSchema); 
    
    if($ValidateSchema["Valid"]==true) 
    {  $MyQuery = Array("vote_get_result", JSON::Convert($MyRecord));
        $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Election_DBase, $MyQuery), "ARRAY")[0]["Result"];

       
         echo 	JSON::Convert($Result);		  
       
    }else{
        echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
    }
}



static function  Check_Voting_Availability($MyRecord) { 
	       
    $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Election_DataModel:: Vote_Check_Availability); 
    
    if($ValidateSchema["Valid"]==true) 
    {  $MyQuery = Array("election_schedule_check", JSON::Convert($MyRecord));
        $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Election_DBase, $MyQuery), "ARRAY")[0]["Result"];

       
         echo 	JSON::Convert($Result);		  
       
    }else{
        echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
    }
  
}


static function  Check_User_Vote_Status($MyRecord) { 
	       
    $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Election_DataModel:: User_Vote_Status); 
    
    if($ValidateSchema["Valid"]==true) 
    {  $MyQuery = Array("user_vote_status", JSON::Convert($MyRecord));
        $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Election_DBase, $MyQuery), "ARRAY")[0]["Result"];

       
         echo 	JSON::Convert($Result);		  
       
    }else{
        echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
    }
  
}


static function  Get_User_Log($MyRecord) { 
	       
    $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Election_DataModel:: User_Log_JsonSchema); 
    
    if($ValidateSchema["Valid"]==true) 
    {  $MyQuery = Array("user_app_service_get_log", JSON::Convert($MyRecord));
        $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Election_DBase, $MyQuery), "ARRAY")[0]["Result"];

       
         echo 	JSON::Convert($Result);		  
       
    }else{
        echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
    }
  
}

static function  Insert_User_Log($MyRecord) { 
	       
    $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Election_DataModel:: Insert_User_Log_JsonSchema); 
    
    if($ValidateSchema["Valid"]==true) 
    {  $MyQuery = Array("user_app_service_insert_log", JSON::Convert($MyRecord));
        $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Election_DBase, $MyQuery), "ARRAY")[0]["Result"];

       
         echo 	JSON::Convert($Result);		  
       
    }else{
        echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
    }
  
}

static function  User_Account_CRUD($MyRecord) { 
	       
    $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Election_DataModel:: User_Account_CRUD_JsonSchema); 
    
    if($ValidateSchema["Valid"]==true) 
    {  $MyQuery = Array("user_account_CRUD", JSON::Convert($MyRecord));
        $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Election_DBase, $MyQuery), "ARRAY")[0]["Result"];

       
         echo 	JSON::Convert($Result);		  
       
    }else{
        echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
    }
  
}


static function  User_Account_Update_Status($MyRecord) { 
	       
    $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Election_DataModel:: User_Account_Update_Status); 
    
    if($ValidateSchema["Valid"]==true) 
    {  $MyQuery = Array("user_account_updateStat", JSON::Convert($MyRecord));
        $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Election_DBase, $MyQuery), "ARRAY")[0]["Result"];

       
         echo 	JSON::Convert($Result);		  
       
    }else{
        echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
    }
  
}

static function  App_Service_CRUD($MyRecord) { 
	       
    $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Election_DataModel:: App_Service_CRUD); 
    
    if($ValidateSchema["Valid"]==true) 
    {  $MyQuery = Array("app_services_CRUD", JSON::Convert($MyRecord));
        $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Election_DBase, $MyQuery), "ARRAY")[0]["Result"];

       
         echo 	JSON::Convert($Result);		  
       
    }else{
        echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
    }
  
}

static function  Create_Schedule($MyRecord) { 
	       
    $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Election_DataModel:: Create_ScheduleJsonSchema); 
    
    if($ValidateSchema["Valid"]==true) 
    {  $MyQuery = Array("Election_schedule_create", JSON::Convert($MyRecord));
        $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Election_DBase, $MyQuery), "ARRAY")[0]["Result"];

       
         echo 	JSON::Convert($Result);		  
       
    }else{
        echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
    }
  
}

static function  Get_votes_count_per_College($MyRecord) { 
	       
    $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Election_DataModel:: election_generate_result_JsonSchema  ); 
    
    if($ValidateSchema["Valid"]==true) 
    {  $MyQuery = Array("votes_count_who_already_cast", JSON::Convert($MyRecord));
        $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Election_DBase, $MyQuery), "ARRAY")[0]["Result"];

       
         echo 	JSON::Convert($Result);		  
       
    }else{
        echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
    }
  
}





   }
	
 ?>