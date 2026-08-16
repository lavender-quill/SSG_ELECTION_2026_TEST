<?php
 
  ////////////////////////////////////////////////////////////////
  //	Coded by	: Armando T. Saguin Jr.						//
  //				  Coderstation Information System Innovator //
  //	Email		: saguin.armando.jr@gmail.com				//
  //	Mobile No.	: +639306694943								//
  //	Version		: 1.0.01									//
  ////////////////////////////////////////////////////////////////
   
 namespace Extension;
  
 class Thread{
	  static function RunPhp($Php_Script, $Php_Dir) {
			    if(substr(php_uname(), 0, 7) == "Windows"){
			    	pclose(popen("start /B " . $Php_Dir. "php -q ". $Php_Script, "r")); 
				}else {exec( $Php_Dir . "php -q " . $Php_Script . " > /dev/null &"); }
             }

 }
