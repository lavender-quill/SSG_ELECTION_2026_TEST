<?php
  
  ////////////////////////////////////////////////////////////////
  //	Coded by	: Armando T. Saguin Jr.						//
  //				  Coderstation Information System Innovator //
  //	Email		: saguin.armando.jr@gmail.com				//
  //	Mobile No.	: +639306694943								//
  //	Version		: 1.0.01									//
  ////////////////////////////////////////////////////////////////
   
  namespace Extension;
  
  use ErrorException;
  class Error_Handler{
	 public static function Intercept(){     
     set_error_handler(function($errno, $errstr, $errfile, $errline ){ 
                  throw new  ErrorException($errstr, $errno, 0, $errfile, $errline); 
	              });
    }
   }
