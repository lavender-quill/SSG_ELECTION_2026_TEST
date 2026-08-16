<?php
  
    use Configuration\Application as Application;
    use Configuration\MsgPrompt   as MsgPrompt;
      
    use Extension\JSON 			  as JSON;
	use Extension\Error_Handler   as Error_Handler;
	
    use PhpDataObject\PdoMySql    as PdoMySql;
     
	class Election_ExtModel{
/*==============================================================================================================================================================================================================================*/
 
static function vote_cast($MyRecord) { 
    // Convert the input to JSON string (if not already)
    $ConvertedRecord = JSON::Convert($MyRecord); 
    
    // Validate the converted record using the updated schema
    $ValidateSchema = JSON::ValidateSchema($ConvertedRecord, Election_DataModel::vote_record_JsonSchema); 
    
    if ($ValidateSchema["Valid"] == true) {
        // Call stored procedure 'vote_record' with the JSON string as parameter
        $MyQuery = Array("vote_record", $ConvertedRecord);

        // Execute the query using your PDO helper and get the result
        $QueryResult = PdoMySQL::ExecQuery(Application::$SSG_Election_DBase, $MyQuery);

        // Convert and extract the result
        $Result = JSON::Convert($QueryResult, "ARRAY")[0]["Result"];

        // Output the result in JSON
        echo JSON::Convert($Result);		  
    } else {
        // If validation failed, show validation status
        echo JSON::Convert(Array("Status" => $ValidateSchema["Status"]));
    }
}

 
/*==============================================================================================================================================================================================================================*/
	 
/*==============================================================================================================================================================================================================================*/
	 	  

 }
	
 ?>