<?php
  
  
 const Config_Folder = "/";
 const Services_Folder = "/JRMSU-API/";
 const Libraries_Folder = "/Libraries/";
 
 spl_autoload_register(function($ClassName){
       
	 $ClassName= str_replace("\\","/",$ClassName);
	 
	 $ExtModel= str_replace("_ExtModel","",$ClassName);
	 $DataModel= str_replace("_DataModel","",$ClassName);
	  
     $ClassLibrary 	= dirname(dirname(__FILE__)) . Libraries_Folder . str_replace("_",".", $ClassName) .  ".Coderstation.php";
     $ConfigLibrary = dirname(dirname(__FILE__)) . Config_Folder . str_replace("_",".", $ClassName) .  ".Config.php";
	 
     $Rest_ApiModel 	= dirname(__FILE__) . Services_Folder .  str_replace("_","-", $ClassName) . "-Modules/" . str_replace("_","-", $ClassName).".Model.php";
	 $Rest_Api_ExtModel = dirname(__FILE__) . Services_Folder .  str_replace("_","-", $ExtModel) . "-Modules/" . str_replace("_","-", $ExtModel).".ExtModel.php";
	 $Rest_ApiDataModel = dirname(__FILE__) . Services_Folder .  str_replace("_","-", $DataModel) . "-Modules/" . str_replace("_","-", $DataModel).".DataModel.php";
      
	 if(file_exists($ClassLibrary))				{include_once($ClassLibrary); } 
	 if(file_exists($ConfigLibrary))			{include_once($ConfigLibrary);} 
	 if(file_exists($Rest_ApiModel))			{include_once($Rest_ApiModel);}
	 if(file_exists($Rest_Api_ExtModel))		{include_once($Rest_Api_ExtModel);}  
	 if(file_exists($Rest_ApiDataModel))		{include_once($Rest_ApiDataModel);}  
	  
  });
