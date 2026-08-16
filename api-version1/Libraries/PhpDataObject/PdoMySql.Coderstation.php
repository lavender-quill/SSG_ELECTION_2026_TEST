<?php
        
  ////////////////////////////////////////////////////////////////
  //    Coded by        : Armando T. Saguin Jr.                                         //
  //                              Coderstation Information System Innovator //
  //    Email           : saguin.armando.jr@gmail.com                           //
  //    Mobile No.      : +639306694943                                                         //
  //    Version         : 1.0.01                                                                        //
  ////////////////////////////////////////////////////////////////
 
  
 Namespace PhpDataObject;
  
 use \PDO;
 use Extension\JSON                     as JSON;
 use Extension\Error_Handler    as Error_Handler;
 
 use Configuration\MsgPrompt    as MsgPrompt;
 
 class PdoMySql
         {       
          
//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- 
//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- 
  
   public static function ExecQuery($DBase, $Parameter)
        {    $Result="";
         
                      //Intercept Errors in a Try-Catch Statement
               Error_Handler::Intercept();
                                  
                 try {   
                               $Options = [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                                                           PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                                                           PDO::ATTR_EMULATE_PREPARES   => false];
                                                                
                                  $Connection = new PDO("mysql:host=".  $DBase["Host"] . 
                                                        ";port=" .  $DBase["Port"] . 
                                                                                ";dbname=" .  $DBase["DBName"] . 
                                                                                ";charset=utf8mb4;", $DBase["Username"], $DBase["Password"], $Options);
 
                               $Statement=$Connection->prepare("Call CODERSTATION_DisPatcher(?,?)");
                                   $Statement->execute($Parameter); 
                                   
                               $Result= JSON::Convert($Statement->fetchall(PDO::FETCH_ASSOC), "STRING"); 
                                                   
                                 } catch(PDOException $e) { error_log('PdoMySql::ExecQuery failed: ' . $e->getMessage()); }
                               
                             $Result= str_replace("\\\"", "\"", $Result);
                                 $Result= str_replace("\"{", "{", $Result);
                             $Result= str_replace("}\"", "}", $Result);
                                 $Result= str_replace("\"[", "[", $Result);
                             $Result= str_replace("]\"", "]", $Result);
                             $Result= str_replace("\\", "|_|", $Result);        //For Base64 Data
                         
                         return $Result;
                 }
                 
                 
//------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- 
//----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- 
            
 }
        
        ?>