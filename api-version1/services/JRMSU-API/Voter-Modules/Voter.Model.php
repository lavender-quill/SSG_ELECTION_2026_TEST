<?php

use Configuration\Application as Application;
use Configuration\MsgPrompt as MsgPrompt;

use Extension\JSON as JSON;
use Extension\Error_Handler as Error_Handler;

use PhpDataObject\PdoMySql as PdoMySql;

class Voter
{
        /*==============================================================================================================================================================================================================================*/

        static function Search_Record($MyRecord)
        {
                //Request Token
                $EndPoint = "https://jrmsu-arms.online/api/version-2/services/credential/token/request";
                $Response = JSON::Convert(Voter_ExtModel::Dispatch($EndPoint, Voter_ExtModel::GetSecurity(), ""), "ARRAY");

                if (stripos($Response["Status"], "Error:") === false) {
                        $MyHeader = array(
                                "Secret-Key" => $Response["Secret_Key"],
                                "User-Agent" => "Coderstation-Protocol",
                                "Authorization" => "Bearer " . $Response["JWToken"]
                        );

                        $EndPoint = "https://jrmsu-arms.online/api/version-2/services/student/enrollment/search";
                        echo JSON::Convert(Voter_ExtModel::Dispatch($EndPoint, $MyHeader, JSON::Convert($MyRecord)));
                }
        }

        /*==============================================================================================================================================================================================================================*/

        // static function Account_Login($MyRecord)
        // {
        //      //Request Token
        //      $EndPoint = "https://jrmsu-arms.online/api/version-2/services/credential/token/request";
        //      $Response = JSON::Convert(Voter_ExtModel::Dispatch($EndPoint, Voter_ExtModel::$Security, ""), "ARRAY");

        //      if (stripos($Response["Status"], "Error:") === false) {
        //              $MyHeader = array(
        //                      "Secret-Key" => $Response["Secret_Key"],
        //                      "User-Agent" => "Coderstation-Protocol",
        //                      "Authorization" => "Bearer " . $Response["JWToken"]
        //              );

        //              //Login Student Account
        //              $EndPoint = "https://jrmsu-arms.online/api/version-2/services/student/account/login";
        //              $Response = JSON::Convert(JSON::Convert(Voter_ExtModel::Dispatch($EndPoint, $MyHeader, JSON::Convert($MyRecord))), "ARRAY");


        //              if (stripos($Response["Status"], "Error:") === false) {
        //                      $MyRecord["Student_ID"] = $MyRecord["Username"];
        //                      //Search Student Record SSG Database

        //                      $MyQuery = array("Voter_Search", JSON::Convert($MyRecord));
        //                      $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY")[0]["Result"];

        //                      if (stripos($Response["Status"], "Error:") === false) {
        //                              //Search Student Record ARM-System Database
        //                              $EndPoint = "https://jrmsu-arms.online/api/version-2/services/student/enrollment/search";
        //                              $Response = JSON::Convert(Voter_ExtModel::Dispatch($EndPoint, $MyHeader, JSON::Convert($MyRecord)));


        //                              if (stripos(JSON::Convert($Response, "ARRAY")["Status"], "Error:") === false) {

        //                                      $MyQuery = array("Voter_Update", JSON::Convert(JSON::Convert($Response, "ARRAY")["Record"]));
        //                                      $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY");
        //                                      echo JSON::Convert($Result[0]);
        //                              } else {
        //                                      echo JSON::Convert($Response);
        //                              }

        //                      } else {
        //                              echo JSON::Convert($Result);
        //                      }

        //              } else {
        //                      echo JSON::Convert($Response);
        //              }

        //      }

        // }



        //      static function Account_Login($MyRecord)
// {
//     // Request Token
//     $EndPoint = "https://jrmsu-arms.online/api/version-2/services/credential/token/request";
//     $Response = JSON::Convert(Voter_ExtModel::Dispatch($EndPoint, Voter_ExtModel::$Security, ""), "ARRAY");

        //     if (stripos($Response["Status"], "Error:") === false) {
//         $MyHeader = array(
//             "Secret-Key" => $Response["Secret_Key"],
//             "User-Agent" => "Coderstation-Protocol",
//             "Authorization" => "Bearer " . $Response["JWToken"]
//         );

        //         // Login Student Account
//         $EndPoint = "https://jrmsu-arms.online/api/version-2/services/student/account/login";
//         $Response = JSON::Convert(JSON::Convert(Voter_ExtModel::Dispatch($EndPoint, $MyHeader, JSON::Convert($MyRecord))), "ARRAY");

        //         if (stripos($Response["Status"], "Error:") === false) {
//             $MyRecord["Student_ID"] = $MyRecord["Username"];

        //             // Search Student Record SSG Database
//             $MyQuery = array("Voter_Search", JSON::Convert($MyRecord));
//             $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY")[0]["Result"];

        //             if (stripos($Response["Status"], "Error:") === false) {
//                 // Search Student Record ARM-System Database
//                 $EndPoint = "https://jrmsu-arms.online/api/version-2/services/student/enrollment/search";
//                 $EnrollmentResponseRaw = Voter_ExtModel::Dispatch($EndPoint, $MyHeader, JSON::Convert($MyRecord));
//                 $EnrollmentResponse = JSON::Convert($EnrollmentResponseRaw, "ARRAY");

        //                 if (stripos($EnrollmentResponse["Status"], "Error:") === false) {

        //                     // Check if Year Level is 4
//                     if (isset($EnrollmentResponse["Record"]["Year_Level"]) && $EnrollmentResponse["Record"]["Year_Level"] == 4) {
//                         // Update Voter Record
//                         $MyQuery = array("Voter_Update", JSON::Convert($EnrollmentResponse["Record"]));
//                         $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY");
//                         echo JSON::Convert($Result[0]);
//                     } else {
//                         // Not Year Level 4
//                         echo JSON::Convert(array(
//                             "Status" => "Error: Only 4th year students are allowed to login.",
//                             "Year_Level" => $EnrollmentResponse["Record"]["Year_Level"]
//                         ));
//                     }

        //                 } else {
//                     echo JSON::Convert($EnrollmentResponse);
//                 }

        //             } else {
//                 echo JSON::Convert($Result);
//             }

        //         } else {
//             echo JSON::Convert($Response);
//         }
//     }
// }

        // working online ARMS API
// static function Account_Login($MyRecord)
// {
//     // Request Token
//     $EndPoint = "https://jrmsu-arms.online/api/version-2/services/credential/token/request";
//     $Response = JSON::Convert(Voter_ExtModel::Dispatch($EndPoint, Voter_ExtModel::$Security, ""), "ARRAY");

        //     if (stripos($Response["Status"], "Error:") === false) {
//         $MyHeader = array(
//             "Secret-Key" => $Response["Secret_Key"],
//             "User-Agent" => "Coderstation-Protocol",
//             "Authorization" => "Bearer " . $Response["JWToken"]
//         );

        //         // Login Student Account
//         $EndPoint = "https://jrmsu-arms.online/api/version-2/services/student/account/login";
//         $Response = JSON::Convert(JSON::Convert(Voter_ExtModel::Dispatch($EndPoint, $MyHeader, JSON::Convert($MyRecord))), "ARRAY");

        //         if (stripos($Response["Status"], "Error:") === false) {
//             $MyRecord["Student_ID"] = $MyRecord["Username"];

        //             // Search Student Record SSG Database
//             $MyQuery = array("Voter_Search", JSON::Convert($MyRecord));
//             $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY")[0]["Result"];

        //             if (stripos($Response["Status"], "Error:") === false) {
//                 // Search Student Record ARM-System Database
//                 $EndPoint = "https://jrmsu-arms.online/api/version-2/services/student/enrollment/search";
//                 $EnrollmentResponseRaw = Voter_ExtModel::Dispatch($EndPoint, $MyHeader, JSON::Convert($MyRecord));
//                 $EnrollmentResponse = JSON::Convert($EnrollmentResponseRaw, "ARRAY");

        //                 if (stripos($EnrollmentResponse["Status"], "Error:") === false) {
//                     $Record = $EnrollmentResponse["Record"];

        //                     //  Check if Year Level is 4 or 5
//                     if (isset($Record["Year_Level"]) && in_array($Record["Year_Level"], [4, 5])) {

        //                         //  Check if entered password matches LAST NAME
//                         if (isset($Record["Student_Name"])) {
//                             $StudentName = $Record["Student_Name"];
//                             $LastName = strtoupper(trim(explode(',', $StudentName)[0])); // Extract LASTNAME
//                             $EnteredPassword = strtoupper(trim($MyRecord["Password"])); // Normalize input

        //                             if ($EnteredPassword === $LastName) {
//                                 // Update Voter Record
//                                 $MyQuery = array("Voter_Update", JSON::Convert($Record));
//                                 $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY");
//                                 echo JSON::Convert($Result[0]);
//                             } else {
//                                 echo JSON::Convert(array(
//                                     "Status" => "Error: Incorrect password.",
//                                     "Hint" => "Expected Last Name: " . $LastName
//                                 ));
//                             }
//                         } else {
//                             echo JSON::Convert(array("Status" => "Error: Missing student name in record."));
//                         }
//                     } else {

        //                         echo JSON::Convert(array(
//                             "Status" => "Error: Only 4th year and 5th year students are allowed to login.",
//                             "Year_Level" => $Record["Year_Level"]
//                         ));
//                     }

        //                 } else {
//                     echo JSON::Convert($EnrollmentResponse);
//                 }

        //             } else {
//                 echo JSON::Convert($Result);
//             }

        //         } else {
//             echo JSON::Convert($Response);
//         }
//     }
// }


        /*==============================================================================================================================================================================================================================*/
        /*==============================================================================================================================================================================================================================*/
        // static function Account_Login($MyRecord)
// {
//     //  Validate input using the same schema as Search_Profile
//     $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Voter_DataModel::Login_JsonSchema);

        //     if ($ValidateSchema["Valid"] == true) {
//         // Search student profile from internal SSG database
//         $MyQuery = array("Voter_Search", JSON::Convert($MyRecord));
//         $SearchResult = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY");
//              echo JSON::Convert($SearchResult[0]);
//         $Record = $SearchResult[0]["Result"] ?? null;

        //         if ($Record) {
//             // Allow only 4th and 5th year students
//             if (isset($Record["Year_Level"]) && in_array($Record["Year_Level"], [4, 5])) {

        //                 //  Match password with LAST NAME
//                 if (isset($Record["Student_Name"])) {
//                     $LastName = strtoupper(trim(explode(',', $Record["Student_Name"])[0]));
//                     $EnteredPassword = strtoupper(trim($MyRecord["Password"]));

        //                     if ($EnteredPassword === $LastName) {
//                         //  Update voter record in SSG DB
//                         $MyQuery = ["Voter_Update", JSON::Convert($Record)];
//                         $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY");
//                         echo JSON::Convert($Result[0]);
//                     } else {
//                         echo JSON::Convert([
//                             "Status" => "Error: Incorrect password.",
//                             "Hint" => "Expected Last Name: " . $LastName
//                         ]);
//                     }
//                 } else {
//                     echo JSON::Convert(["Status" => "Error: Missing student name."]);
//                 }

        //             } else {
//                 echo JSON::Convert([
//                     "Status" => "Error: Only 4th year and 5th year students are allowed.",
//                     "Year_Level" => $Record["Year_Level"] ?? "Unknown"
//                 ]);
//             }

        //         } else {
//             echo JSON::Convert(["Status" => "Error: No matching student record found."]);
//         }

        //     } else {
//         echo JSON::Convert(["Status" => $ValidateSchema["Status"]]);
//     }
// }

        // 2


        // working with year level filter -- current
// static function Account_Login($MyRecord)
// {
//     $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Voter_DataModel::Login_JsonSchema);

//          if ($ValidateSchema["Valid"] == true) {
//         $MyQuery = array("Student_Search", JSON::Convert($MyRecord));
//         $SearchResult = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY");

//             // echo JSON::Convert($SearchResult[0]); // for debugging

//              //  Fix: Get inner "Record"
//         $Record = $SearchResult[0]["Result"]["Record"] ?? null;

//              if ($Record) {
//             if (isset($Record["Year_Level"]) && in_array($Record["Year_Level"], [1,2,3,4, 5])) {
//                 if (isset($Record["Student_Name"])) {
//                     $LastName = strtoupper(trim(explode(',', $Record["Student_Name"])[0]));
//                     $EnteredPassword = strtoupper(trim($MyRecord["Password"]));

//                          if ($EnteredPassword === $LastName) {
//                         $MyQuery = ["Voter_Update", JSON::Convert($Record)];
//                         $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY");
//                         echo JSON::Convert($Result[0]);
//                     } else {
//                         echo JSON::Convert([
//                             "Status" => "Error: Incorrect password.",
//                             "Hint" => "Expected Last Name: " . $LastName
//                         ]);
//                     }
//                 } else {
//                     echo JSON::Convert(["Status" => "Error: Missing student name."]);
//                 }
//             } else {
//                 echo JSON::Convert([
//                     "Status" => "Error: Only 4th year and 5th year students are allowed.",
//                     "Year_Level" => $Record["Year_Level"] ?? "Unknown"
//                 ]);
//             }
//         } else {
//             echo JSON::Convert(["Status" => "Error: No matching student record found."]);
//         }

//          } else {
//         echo JSON::Convert(["Status" => $ValidateSchema["Status"]]);
//     }
// }



        // with year and college filter
        // static function Account_Login($MyRecord)
        // {
        //      $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Voter_DataModel::Login_JsonSchema);

        //      if ($ValidateSchema["Valid"] == true) {
        //              $MyQuery = array("Student_Search", JSON::Convert($MyRecord));
        //              $SearchResult = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY");

        //              //  Extract inner "Record"
        //              $Record = $SearchResult[0]["Result"]["Record"] ?? null;

        //              if ($Record) {
        //                      //  List of blocked colleges
                                
        //                      $blockedColleges = ['CCJE', 'CON', 'COT', 'CCS', 'SOM', 'CNAHS', 'CME']; // Add other College_Codes to block

        //                      // Extract actual college code from combined field
        //                      $collegeRaw = $Record["College"] ?? '';
        //                      $studentCollege = strtoupper(trim(explode('-', $collegeRaw)[0] ?? ''));

        //                      if (in_array($studentCollege, $blockedColleges)) {
        //                              echo JSON::Convert([
        //                                      "Status" => "Error: Your college is not allowed to log in.",
        //                                      "College_Code" => $studentCollege
        //                              ]);
        //                              return;
        //                      }


        //                      // Check Year Level
        //                      if (isset($Record["Year_Level"]) && in_array($Record["Year_Level"], [1, 2, 3, 4, 5])) {

        //                              if (isset($Record["Student_Name"])) {
        //                                      //  Extract last name
        //                                      $LastName = strtoupper(trim(explode(',', $Record["Student_Name"])[0]));
        //                                      $EnteredPassword = strtoupper(trim($MyRecord["Password"]));

        //                                      //  Validate last name as password
        //                                      if ($EnteredPassword === $LastName) {
        //                                              $MyQuery = ["Voter_Update", JSON::Convert($Record)];
        //                                              $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY");
        //                                              echo JSON::Convert($Result[0]);
        //                                      } else {
        //                                              echo JSON::Convert([
        //                                                      "Status" => "Error: Incorrect password.",
        //                                                      "Hint" => "Expected Last Name: " . $LastName
        //                                              ]);
        //                                      }
        //                              } else {
        //                                      echo JSON::Convert(["Status" => "Error: Missing student name."]);
        //                              }

        //                      } else {
        //                              echo JSON::Convert([
        //                                      "Status" => "Error: Only 1st to 5th year students are allowed.",
        //                                      "Year_Level" => $Record["Year_Level"] ?? "Unknown"
        //                              ]);
        //                      }
        //              } else {
        //                      echo JSON::Convert(["Status" => "Error: No matching student record found."]);
        //              }
        //      } else {
        //              echo JSON::Convert(["Status" => $ValidateSchema["Status"]]);
        //      }
        // }


        // for hs

static function Account_Login($MyRecord)
{
    $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Voter_DataModel::Login_JsonSchema);

    if ($ValidateSchema["Valid"] == true) {

        // Step 1: Initial search using provided semester
        $MyQuery = array("Student_Search", JSON::Convert($MyRecord));
        $SearchResult = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY");

        $Record = $SearchResult[0]["Result"]["Record"] ?? null;

        // Step 2: Retry with 1st sem if no record AND trying to login for 2nd sem
        if (!$Record && isset($MyRecord["Semester"]) && $MyRecord["Semester"] === "2nd") {
            $RetryRecordData = $MyRecord;
            $RetryRecordData["Semester"] = "1st";

            $RetryQuery = array("Student_Search", JSON::Convert($RetryRecordData));
            $RetryResult = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $RetryQuery), "ARRAY");

            $RetryRecord = $RetryResult[0]["Result"]["Record"] ?? null;

            // Extract College Code from either 'College_Code' or parsed from 'College'
            $CollegeCode = $RetryRecord["College_Code"] ?? explode(' ', $RetryRecord["College"] ?? '')[0] ?? null;

            if ($RetryRecord && strtoupper($CollegeCode) === "HS") {
                                $RetryRecord["Semester"] = "2nd";
                $Record = $RetryRecord;
            }
        }

        if ($Record) {
            if (isset($Record["Year_Level"]) && in_array($Record["Year_Level"], [ 4, 5])) {
                if (isset($Record["Student_Name"])) {
                    $LastName = strtoupper(trim(explode(',', $Record["Student_Name"])[0]));
                    $EnteredPassword = strtoupper(trim($MyRecord["Password"]));

                    if ($EnteredPassword === $LastName) {
                        $MyQuery = ["Voter_Update", JSON::Convert($Record)];
                        $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY");
                        echo JSON::Convert($Result[0]);
                    } else {
                        echo JSON::Convert([
                            "Status" => "Error: Incorrect password.",
                        ]);
                    }
                } else {
                    echo JSON::Convert(["Status" => "Error: Missing student name."]);
                }
            } else {
                echo JSON::Convert([
                    "Status" => "Error: Only 4th year and 5th year students are allowed.",
                    "Year_Level" => $Record["Year_Level"] ?? "Unknown"
                ]);
            }
        } else {
            echo JSON::Convert(["Status" => "Error: No matching student record found."]);
        }

    } else {
        echo JSON::Convert(["Status" => $ValidateSchema["Status"]]);
    }
}


        static function Search_Profile($MyRecord)
        {

                $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Voter_DataModel::Search_Record_JsonSchema);

                if ($ValidateSchema["Valid"] == true) {
                        $MyQuery = array("Voter_Search", JSON::Convert($MyRecord));
                        $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY")[0]["Result"];

                        echo JSON::Convert($Result);

                } else {
                        echo JSON::Convert(array("Status" => $ValidateSchema["Status"]));
                }

        }
        /*==============================================================================================================================================================================================================================*/
        /*==============================================================================================================================================================================================================================*/



        static function Get_Candidate_Info_($MyRecord)
        {

                $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Voter_DataModel::Student_Get_Info_JsonSchema);

                if ($ValidateSchema["Valid"] == true) {
                        $MyQuery = array("student_get_info", JSON::Convert($MyRecord));
                        $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY")[0]["Result"];

                        echo JSON::Convert($Result);

                } else {
                        echo JSON::Convert(array("Status" => $ValidateSchema["Status"]));
                }
                //Candidate_DataModel :: Get_Candidate_Info_($MyRecord);
        }

        static function Account_Update($MyRecord)
        {

                $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Voter_DataModel::Update_Account_JsonSchema);

                if ($ValidateSchema["Valid"] == true) {
                        $MyQuery = array("Voter_Update", JSON::Convert($MyRecord));
                        $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY")[0]["Result"];

                        echo JSON::Convert($Result);

                } else {
                        echo JSON::Convert(array("Status" => $ValidateSchema["Status"]));
                }
                //Candidate_DataModel :: Get_Candidate_Info_($MyRecord);
        }

        static function Student_SearchDummy($MyRecord)
        {
                // Validate against JSON Schema
                $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Voter_DataModel::Search_Student_JsonSchema);

                if ($ValidateSchema["Valid"] === true) {
                        // Call the stored procedure 'Student_Search'
                        $MyQuery = ["Student_Search", JSON::Convert($MyRecord)];
                        $SearchResultRaw = PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery);

                        $SearchResultArray = JSON::Convert($SearchResultRaw, "ARRAY");
                        $SearchResponse = $SearchResultArray[0]["Result"] ?? null;

                        $SearchDecoded = JSON::Convert($SearchResponse, "ARRAY");

                        // If search was successful
                        if (isset($SearchDecoded["Status"]) && stripos($SearchDecoded["Status"], "Error:") === false) {

                                // Update user info
                                $UpdatedRecord = JSON::Convert($SearchDecoded["Record"]);
                                $UpdateQuery = ["Voter_Update", $UpdatedRecord];
                                $UpdateResultRaw = PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $UpdateQuery);

                                $UpdateResult = JSON::Convert($UpdateResultRaw, "ARRAY");

                                // Return update response
                                echo JSON::Convert($UpdateResult[0]);
                        } else {
                                // Return search error
                                echo JSON::Convert($SearchDecoded);
                        }
                } else {
                        // Return validation error
                        echo JSON::Convert(["Status" => $ValidateSchema["Status"]]);
                }
        }


        static function Account_Login2($MyRecord)
        {
                // Request Token
                $EndPoint = "https://jrmsu-arms.online/api/version-2/services/credential/token/request";
                $Response = JSON::Convert(Voter_ExtModel::Dispatch($EndPoint, Voter_ExtModel::GetSecurity(), ""), "ARRAY");

                if (stripos($Response["Status"], "Error:") === false) {
                        $MyHeader = [
                                "Secret-Key" => $Response["Secret_Key"],
                                "User-Agent" => "Coderstation-Protocol",
                                "Authorization" => "Bearer " . $Response["JWToken"]
                        ];

                        // Login Student Account
                        $EndPoint = "https://jrmsu-arms.online/api/version-2/services/student/account/login";
                        $Response = JSON::Convert(JSON::Convert(Voter_ExtModel::Dispatch($EndPoint, $MyHeader, JSON::Convert($MyRecord))), "ARRAY");

                        if (stripos($Response["Status"], "Error:") === false) {
                                $MyRecord["Student_ID"] = $MyRecord["Username"];

                                // Search student in SSG voter database
                                $MyQuery = ["Voter_Search", JSON::Convert($MyRecord)];
                                $ResultRaw = PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery);
                                $Result = JSON::Convert($ResultRaw, "ARRAY")[0]["Result"];

                                $Decoded = JSON::Convert($Result, "ARRAY");
                                if (isset($Decoded["Status"]) && stripos($Decoded["Status"], "Error:") === false) {


                                        //  BEGIN INTEGRATED Student_SearchDummy logic
                                        $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Voter_DataModel::Search_Student_JsonSchema);

                                        if ($ValidateSchema["Valid"] === true) {
                                                $SearchQuery = ["Student_Search", JSON::Convert($MyRecord)];
                                                $SearchResultRaw = PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $SearchQuery);

                                                $SearchResultArray = JSON::Convert($SearchResultRaw, "ARRAY");
                                                $SearchResponse = $SearchResultArray[0]["Result"] ?? null;
                                                $SearchDecoded = JSON::Convert($SearchResponse, "ARRAY");

                                                if (isset($SearchDecoded["Status"]) && stripos($SearchDecoded["Status"], "Error:") === false) {
                                                        $UpdatedRecord = JSON::Convert($SearchDecoded["Record"]);
                                                        $UpdateQuery = ["Voter_Update", $UpdatedRecord];
                                                        $UpdateResultRaw = PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $UpdateQuery);
                                                        $UpdateResult = JSON::Convert($UpdateResultRaw, "ARRAY");

                                                        echo JSON::Convert($UpdateResult[0]); // final echo
                                                } else {
                                                        echo JSON::Convert($SearchDecoded);
                                                }
                                        } else {
                                                echo JSON::Convert(["Status" => $ValidateSchema["Status"]]);
                                        }
                                        //  END INTEGRATED Student_SearchDummy logic

                                } else {
                                        echo JSON::Convert($Result);
                                }

                        } else {
                                echo JSON::Convert($Response);
                        }

                } else {
                        echo JSON::Convert($Response);
                }
        }


        static function Login($MyRecord)
        {

                $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Voter_DataModel::Login_JsonSchema);

                if ($ValidateSchema["Valid"] == true) {
                        $MyQuery = array("Student_Login", JSON::Convert($MyRecord));
                        $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY")[0]["Result"];

                        echo JSON::Convert($Result);

                } else {
                        echo JSON::Convert(array("Status" => $ValidateSchema["Status"]));
                }

        }

        static function UpdatePassword($MyRecord)
        {

                $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Voter_DataModel::UpdatePassword_JsonSchema);

                if ($ValidateSchema["Valid"] === true) {
                        // Call the stored procedure 'Student_Search'
                        $MyQuery = ["Student_Update_Password", JSON::Convert($MyRecord)];
                        $SearchResultRaw = PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery);

                        $SearchResultArray = JSON::Convert($SearchResultRaw, "ARRAY");
                        $SearchResponse = $SearchResultArray[0]["Result"] ?? null;

                        $SearchDecoded = JSON::Convert($SearchResponse, "ARRAY");
                        echo JSON::Convert($SearchDecoded);

                } else {
                        echo JSON::Convert(array("Status" => $ValidateSchema["Status"]));
                }

        }



        static function Get_Casted_Voters($MyRecord)
        {

                $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Voter_DataModel::Get_Casted_Voter);

                if ($ValidateSchema["Valid"] == true) {
                        $MyQuery = array("votes_count_who_already_cast", JSON::Convert($MyRecord));
                        $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Election_DBase, $MyQuery), "ARRAY")[0]["Result"];

                        echo JSON::Convert($Result);

                } else {
                        echo JSON::Convert(array("Status" => $ValidateSchema["Status"]));
                }

        }

        static function Get_All_Students($MyRecord)
        {

                $ValidateSchema = JSON::ValidateSchema(JSON::Convert($MyRecord), Voter_DataModel::Get_All_Voter);

                if ($ValidateSchema["Valid"] == true) {
                        $MyQuery = array("Students_get_all", JSON::Convert($MyRecord));
                        $Result = JSON::Convert(PdoMySQL::ExecQuery(Application::$SSG_Voter_DBase, $MyQuery), "ARRAY")[0]["Result"];

                        echo JSON::Convert($Result);

                } else {
                        echo JSON::Convert(array("Status" => $ValidateSchema["Status"]));
                }
                
        }

}
