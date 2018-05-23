<?php

	require_once "../config/db_config.php";
	
    class recruiter_block_list_db {
		// Properties
		
        // Connect to database.
        public function connect() {
			$mysql_connect_str = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';';
			$dbConnection = new PDO($mysql_connect_str, DB_USER, DB_PASS);
			$dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			return $dbConnection;            
        }
    }
?>