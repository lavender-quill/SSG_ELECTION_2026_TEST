<?php

  ////////////////////////////////////////////////////////////////
  //	Coded by	: Armando T. Saguin Jr.						//
  //				  Coderstation Information System Innovator //
  //	Email		: saguin.armando.jr@gmail.com				//
  //	Mobile No.	: +639306694943								//
  //	Version		: 1.0.01									//
  ////////////////////////////////////////////////////////////////
  
  namespace Configuration;
    
  class Page_Header{
	   
//==============================================================================================================================================================================
 	 public static function Config($Header){
		  
			 header("Expires: Sat, 13 Jan 1979 05:00:00 GMT");
             header("Cache-Control: no-cache");
             header("Pragma: no-cache");
			 
			 header("Content-Type: application/json");
			 
			 header("Developer: " . $Header["Developer"]);
             header("Provider: " . $Header["Provider"]);
			 header("Web-Application: " . $Header["AppName"]);
			 
			 header("Client-Identity: " . Self::MachineBAID());
			 header("Authorization: Bearer ". $Header["JWToken"]);
			 
		  }
		  
//==============================================================================================================================================================================
     public static function MachineBAID() {
		// Generate a random Machine Browser Application Unique Identity
			$timestamp = time() * 1000;
			return preg_replace_callback('/[xy]/', function($matches) {
				$r = mt_rand(0, 15);
				$v = $matches[0] === 'x' ? $r : ($r & 0x3 | 0x8);
				return dechex($v);
			}, 'xxxz10xxSxx-xAxx-yyyz12xxGxx-yxxUxy-xxIz20yyyzxNxxxz13yyyy') . '-' . $timestamp;
		}

//==============================================================================================================================================================================

	 
	 }
 ?>