<?php
 
 Namespace Configuration;

    class Application{
                public static $SSG_API_Manage_DBase = array(
                                                                         "Host"         => "153.92.15.54", 
                                                                         "Port"         => "3306",
                                                                         "Username"     => "u619479353_CCS_Creative", 
                                                                         "Password"     => null,
                                                                         "DBName"       => "u619479353_SSG_API_Manage");
                                                                         
                public static $SSG_Voter_DBase = array(
                                                                         "Host"         => "153.92.15.54", 
                                                                         "Port"         => "3306",
                                                                         "Username"     => "u619479353_CC5_Creative", 
                                                                         "Password"     => null,
                                                                         "DBName"       => "u619479353_SSG_Voter");                                                      

        public static $SSG_Candidate_DBase = array(
                                                                         "Host"         => "153.92.15.54", 
                                                                         "Port"         => "3306",
                                                                         "Username"     => "u619479353_CCS_Cr3ative", 
                                                                         "Password"     => null,
                                                                         "DBName"       => "u619479353_SSG_Candidate"); 
                                                                         
                public static $SSG_Election_DBase = array(
                                                                         "Host"         => "153.92.15.54", 
                                                                         "Port"         => "3306",
                                                                         "Username"     => "u619479353_CCS_Creativ3", 
                                                                         "Password"     => null,
                                                                         "DBName"       => "u619479353_SSG_Election");                                                           

                 
            public static $Server = array("TimeZone" => "Asia/Manila",
                                              "Account"  =>     "asaguin.jr@gmail.com",
                                                                          "support"      =>     "support@coderstation.net");
                
                public static $Cipher = array("ApiKey"                   => "!@#$&*-#I#AM#INVINCIBLE#-*&$#@!",
                                              "Key"                      => "$#@-CODER-##-STA-##-TION-@#$",
                                                                          "SudoKey"              => "????dm??Hi????",                  /* Wildcard: f,q,k,x */
                                                                          "Initiator"            => "??dm??Hi??",                               
                                              "JwtExpiration"    => 1800,                              /* 30 Minutes */
                                                                          "OtpExpiration"        => 120,                               /* 02 Minutes */
                                              "HashDifficulty"   => 3,
                                              "AccessCodeLength" => 6,
                                              "RandomPassLength" => 10
                                                                          );
                 
                public static $System = array("Issuer"                  => "Coderstation Information System Innovator",
                                              "Developer"               => "DaemonChain",
                                              "AppName"                 => "ARM-System API",
                                              );        
                
                 
                                                                                
        public static $UserAgent = array("Coderstation-Protocol",
                                                                                 "PostmanRuntime" 
                                                                                 ); 

        public static function init(): void {
            // Read all DB connection details from environment variables.
            // Non-sensitive values are stored as shared env vars in .replit [userenv.shared].
            // DB_PASSWORD is a Replit Secret (never stored in code or .replit).
            $host     = getenv('DB_HOST')     ?: '153.92.15.54';
            $port     = getenv('DB_PORT')     ?: '3306';
            $password = getenv('DB_PASSWORD') ?: '';

            self::$SSG_API_Manage_DBase['Host']     = $host;
            self::$SSG_API_Manage_DBase['Port']     = $port;
            self::$SSG_API_Manage_DBase['Username'] = getenv('DB_MANAGE_USER') ?: self::$SSG_API_Manage_DBase['Username'];
            self::$SSG_API_Manage_DBase['DBName']   = getenv('DB_MANAGE_NAME') ?: self::$SSG_API_Manage_DBase['DBName'];
            self::$SSG_API_Manage_DBase['Password'] = $password;

            self::$SSG_Voter_DBase['Host']     = $host;
            self::$SSG_Voter_DBase['Port']     = $port;
            self::$SSG_Voter_DBase['Username'] = getenv('DB_VOTER_USER') ?: self::$SSG_Voter_DBase['Username'];
            self::$SSG_Voter_DBase['DBName']   = getenv('DB_VOTER_NAME') ?: self::$SSG_Voter_DBase['DBName'];
            self::$SSG_Voter_DBase['Password'] = $password;

            self::$SSG_Candidate_DBase['Host']     = $host;
            self::$SSG_Candidate_DBase['Port']     = $port;
            self::$SSG_Candidate_DBase['Username'] = getenv('DB_CANDIDATE_USER') ?: self::$SSG_Candidate_DBase['Username'];
            self::$SSG_Candidate_DBase['DBName']   = getenv('DB_CANDIDATE_NAME') ?: self::$SSG_Candidate_DBase['DBName'];
            self::$SSG_Candidate_DBase['Password'] = $password;

            self::$SSG_Election_DBase['Host']     = $host;
            self::$SSG_Election_DBase['Port']     = $port;
            self::$SSG_Election_DBase['Username'] = getenv('DB_ELECTION_USER') ?: self::$SSG_Election_DBase['Username'];
            self::$SSG_Election_DBase['DBName']   = getenv('DB_ELECTION_NAME') ?: self::$SSG_Election_DBase['DBName'];
            self::$SSG_Election_DBase['Password'] = $password;
        }
        }
