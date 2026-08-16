<?php 
 
			
    $to =  "Armando Saguin<saguin.armando.jr@gmail.com>"; 
    $subject = "Test Mail\r\n";
    $headers = "From: CODERSTATION<asaguin.jr@gmail.com>\r\n";
    $headers .= "Reply-To: SERVICES<asaguin.jr@gmail.com>\r\n";
    //$headers .= "CC: theassassin.edu@gmail.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
	
	
     ob_start();   
  	     include("Email-API-Key.html");
        $message = ob_get_contents();
	 ob_clean();

    if(mail($to,$subject,$message,$headers))
    {
        echo "Mail Send Sucuceed";
    }
    else{
        echo "Mail Send Failed";    
    }
?>