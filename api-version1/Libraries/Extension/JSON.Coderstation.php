<?php
  
  /*=============================================================*/
  /*	Coded by	: Armando T. Saguin Jr.						 */
  /*				  Coderstation Information System Innovator  */
  /*	Email		: saguin.armando.jr@gmail.com				 */
  /*	Mobile No.	: +639306694943								 */
  /*	Version		: 1.0.01									 */
  /*=============================================================*/
   
  namespace Extension;
  
  class JSON{
      
/*==============================================================================================================================================================================================================================*/
      
    public static function Convert($Source, $Target="STRING"){
		
		$Result = $Source;
      	if(is_array($Source)){
			if(strtoupper($Target)=="STRING"){
				 $Result = json_encode($Source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				 $Result =  str_replace("\\","", $Result );
				 $Result =  str_replace("\"{","{", $Result );
				 $Result =  str_replace("}\"","}", $Result );
				 //JSON_PRETTY_PRINT
			}
			else if(strtoupper($Target)=="OBJECT"){
				  $Result = json_encode($Source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			}
		  }
		else if(is_object($Source)){
			if(strtoupper($Target)=="STRING"){
				 $Result =  str_replace("\\","",json_encode($Source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
				 $Result =  str_replace("\\","", $Result );
				 $Result =  str_replace("\"{","{", $Result );
				 $Result =  str_replace("}\"","}", $Result );
			}
			else if(strtoupper($Target)=="ARRAY"){
				 $Result = json_decode(json_encode($Source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);
			}
		  }
		 else if(is_string($Source)){
			if(strtoupper($Target)=="OBJECT"){
				 $Result = json_decode($Source);
			}
			else if(strtoupper($Target)=="ARRAY"){
				  $Result = json_decode($Source, true);
             }
		  }
		 return $Result;
		 
		 
	 }

/*==============================================================================================================================================================================================================================*/
/*==============================================================================================================================================================================================================================*/
  public static function ValidateSchema($JSON_Data, $JSON_Schema){
	 
	  $JSON_Data = json_decode($JSON_Data, true);
      $JSON_Schema = json_decode($JSON_Schema, true);
	 
	  $Result ="";
	  
		  if ($JSON_Data === null && json_last_error() !== JSON_ERROR_NONE) {
			  $Result = Array("Valid" => false, 
	 	                      "Status" => "The JSON data format is invalid"); 
          }else{
			    if ($JSON_Schema === null && json_last_error() !== JSON_ERROR_NONE) {
			      $Result = Array("Valid" => false, 
	 	                          "Status" => "Invalid JSON Schema Structure"); 
                }else{ $Result = Self::Check_JsonSchema($JSON_Data, $JSON_Schema);}
		  }
	 
	 return $Result ;
  }
  
/*==============================================================================================================================================================================================================================*/

 private static function Check_JsonSchema($JSON_Data, $JSON_Schema){
	  
	  foreach ($JSON_Schema['Properties'] as $key => $property) {
        if (isset($JSON_Data[$key])) {
            /* Check data type */
            $dataType = gettype($JSON_Data[$key]);
             
            if ($dataType !== $property['Type']) {
			   /* If type is 'object' or 'array', skip direct type comparison */
			   if($property['Type'] != "object" && $dataType != "array") {
				   if (!(($property['Type'] === "double" && $dataType=="integer") || ($property['Type'] === "integer" && $dataType=="double"))){
					 return Array("Valid" => false, 
	 	                         "Status" => "Error: The '{$key}' property must have a value of type '{$property['Type']}' as specified by the Schema.");  
	                     } 
			      }
            }
		     
			/* Check nested arrays data */
			if ($property['Type'] == "array" && $dataType == "array") {
				for ($Cnt = 0; $Cnt < count($JSON_Data[$key]); $Cnt++) {
					$itemType = gettype($JSON_Data[$key][$Cnt]);
					
					if ($property['Properties']['Type'] !== $itemType) {
						if (!(($property['Properties']['Type'] === "double" && $itemType=="integer") || ($property['Properties']['Type'] === "integer" && $itemType=="double"))){
						   return Array("Valid" => false, 
	 	                            "Status" => "Error: The '{$key}' property must have a value of type '{$property['Properties']['Type']}' as specified by the Schema.");  
	                     }  
                    }
					
					    
					/*  Check maximum and minimum values for array elements */
					if (isset($property['Properties']['Maximum']) && $JSON_Data[$key][$Cnt] > $property['Properties']['Maximum']) {
						return Array("Valid" => false, 
	 	                            "Status" => "Error: The '{$key}' property value must be less than {$property['Properties']['Maximum']} as defined in the schema.");
					}

					if (isset($property['Properties']['Minimum']) && $JSON_Data[$key][$Cnt] < $property['Properties']['Minimum']) {
						return Array("Valid" => false, 
	 	                            "Status" => "Error: The value of the '{$key}' attribute must be greater than {$property['Properties']['Minimum']} as defined in the schema.");
					}								   
				}
			}
			
			/* Check minimum and maximum values for individual properties */
            if (isset($property['Minimum']) && $JSON_Data[$key] < $property['Minimum']) {
                 return Array("Valid" => false, 
	 	                      "Status" => "Error: The '{$key}' attribute must be assigned a value greater than {$property['Minimum']} as defined in the schema.");
            }

            if (isset($property['Maximum']) && $JSON_Data[$key] > $property['Maximum']) {
               return Array("Valid" => false, 
	 	                    "Status" => "Error: The '{$key}' property must have a value less than {$property['Maximum']} as defined in the schema.");
            }
			
			/* Check for specific format (e.g., Email) */
			if (isset($property['Format']) && $property['Format'] == 'email' && !filter_var($JSON_Data[$key], FILTER_VALIDATE_EMAIL)) {
				return Array("Valid" => false, 
	 	                      "Status" => "Error: The '{$key}' property's value must conform to the Email format as defined by the schema.");
            }
			 
            /* Check nested objects */
            if ($property['Type'] === 'object' && isset($property['Properties'])) {
				$nestedValidation = Self::Check_JsonSchema($JSON_Data[$key], $property);
				if (!$nestedValidation['Valid']) {
					return $nestedValidation;
				}
				/* Check required fields in nested objects */
				foreach ($property['Required'] as $requiredKey) {
					if (!isset($JSON_Data[$key][$requiredKey])) {
						return Array("Valid" => false, 
	 	                            "Status" => "Error: The value for the '{$requiredKey}' property should be set in accordance with the schema.");
					}
				}
			}
			 
        } elseif (isset($JSON_Schema['Required'])) {
			/* Check if the property is required and missing */
			if (in_array($key, $JSON_Schema['Required'])) {
				if (!isset($JSON_Data[$key])) {
					return Array("Valid" => false, 
	 	                      "Status" => "Error: The value for the '{$key}' property should be set in accordance with the schema.");
				}
			}
        }
    }
	 
    return Array("Valid" => true, 
	 	         "Status" => "The data is valid based on the required format of the JSON Schema.");

    }
/*==============================================================================================================================================================================================================================*/   

  }
