<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \libphonenumber\PhoneNumberUtil\PhoneNumberFormat as PhoneNumberFormat;

require_once "../config/db_config.php";

// Check if a string is of a minimum length.
function isStrLenGreaterEqual($str, $minLen) {
  if (strlen($str) >= $minLen) {
    return true;
  } else {
    return false;
  }
}

// Check if a string is of a maximum length.
function isStrLenLessThanEqual($str, $maxLen) {
  if (strlen($str) <= $maxLen) {
    return true;
  } else {
    return false;
  }
}

// Send a JSON response with JSON header.
function sendResponseJson($success, $result, $debugInfo) {
  $response = array(
      "success" => ($success == true ? "true" : "false"),
      "result" => $result
  );
  if ($debugInfo != null) {
    $response['debugInfo'] = $debugInfo;
  }
  header('Content-type: application/json');
  echo json_encode($response);
}

function validateRecaptcha($aReCaptchaResponse, $aUserIpAddr) {
  // Start by sending the reCaptcha token to Google for verification.
	// If the recaptcha fails the client might be a bot and we should exit immediately.
	$data = array(
			'secret' => RECAPTCHA_PRIVATE_KEY,
			'response' => $aReCaptchaResponse,
			'remoteip' => $aUserIpAddr
	);
	
	// Formulate the POST request to check the recaptcha token.
	// Note: use key 'http' even if you send the request to https://...
	$options = array(
		'http' => array(
			'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
			'method'  => 'POST',
			'content' => http_build_query($data)
		)
	);
  
  $context  = stream_context_create($options);
	$result = file_get_contents("https://www.google.com/recaptcha/api/siteverify", false, $context);
	if ($result === false) {
		// Handle error.
		$res = array(
			'success' => false,
			'result' => "reCaptcha error: No response! result = " . $result
		);
		header('Content-type: application/json');
		echo json_encode($res);
		exit;
	} else {
		$resultAr = json_decode($result, true);
		if ($resultAr['success'] == false) {
			$res = array(
				'success' => false,
				'result' => "reCaptcha failed with errors: " . $resultAr['error-codes']
			);
			header('Content-type: application/json');
			echo json_encode($res);
			exit;
		}
	}
}

function getAllMatchingRecruitersInTable($emailStr, $phoneStr) {
  // Get a list of recruiters entries that match a phone number, email or (first & last name).
	$sqlCheckIfRecruiterExists = "SELECT * FROM recruiters WHERE email = :email OR phone1 = :phone1 OR phone2 = :phone2 OR phone3 = :phone3 or phone4 = :phone4";
	
	try {
    // Get the database object.
    $db = new recruiter_block_list_db();

    // Connect to the database.
    $db = $db->connect();

    $stmt = $db->prepare($sqlCheckIfRecruiterExists);
		$stmt->bindParam(':email', $emailStr);
		$stmt->bindParam(':phone1', $phoneStr);
    $stmt->bindParam(':phone2', $phoneStr);
    $stmt->bindParam(':phone3', $phoneStr);
    $stmt->bindParam(':phone4', $phoneStr);
    $stmt->execute();
		
		$all_matching_recruiters = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
    
    return $all_matching_recruiters;
  } catch (PDOException $e) {
    sendResponseJson(false, "Error: " . $e, null);
    exit;
  }
}

function insertRecruiterIntoRecruiterSuggestionsTable(
    $firstName,
    $lastName,
    $email,
    $phone,
    $company,
    $address,
    $city,
    $stateCode,
    $countryCode,
    $zip) {
  
  $sqlInsertIntoRecSug = "INSERT INTO recruiter_suggestions (firstName,lastName,email,phone,company,website,address,city,stateCode,countryCode,zip) VALUES (:firstName,:lastName,:email,:phone,:company,:website,:address,:city,:stateCode,:countryCode,:zip)";

  // Get website from email address.
  $emailPartsAr = explode('@', $email);
  $website = "www." . $emailPartsAr[1];
  
  try {
    // Get the database object.
    $db = new recruiter_block_list_db();

    // Connect to the database.
    $db = $db->connect();

    $stmt = $db->prepare($sqlInsertIntoRecSug);
    $stmt->bindParam(':firstName', $firstName);
    $stmt->bindParam(':lastName', $lastName);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':company', $company);
    $stmt->bindParam(':website', $website);
    $stmt->bindParam(':address', $address);
    $stmt->bindParam(':city', $city);
    $stmt->bindParam(':stateCode', $stateCode);
    $stmt->bindParam(':countryCode', $countryCode);
    $stmt->bindParam(':zip', $zip);
    $stmt->execute();
    $db = null;

    return true;
  } catch (PDOException $e) {
    sendResponseJson(false, "Error: Could not add the recruiter! " . $e, null);
    exit;
  }
  return false;
}

// Insert a recruiter's domain into the recruiter domain suggestions table
// if the recruiter's domain doesn't already exist.
function insertRecDomainIntoRecDomainSuggestionsTable($userId, $userIpAddr, $domain) {
  // Try to parse the domain URL and get the host name.
	if (substr($domain, 0, 4) !== "http") {
		$domain = "http://" . $domain;
	}
	$parsed_domain = parse_url($domain);
	if (array_key_exists('host', $parsed_domain) == false) {
		// Handle error.
    sendResponseJson(false, "Error: The format of the domain is incorrect! The format should be domain.com. The domain you entered was " . $domain, null);
		exit;
	}
	$hostName = $parsed_domain['host'];
	
	if (substr($hostName, 0, 4) === "www.") {
		$hostName = substr($hostName, 4);
	}

	$sqlQuery = "SELECT * FROM recruiter_domains WHERE domain = :domain";

  try {
    // Get the database object and connect to the DB.
    $db = new recruiter_block_list_db();
    $db = $db->connect();

    $stmt = $db->prepare($sqlQuery);
    $stmt->bindParam(':domain', $hostName);
    $stmt->execute();
		
		$row = $stmt->fetch(PDO::FETCH_OBJ);
		if ($row != false) {
      // The recruiter domain is alreasdy in the recruiter domain table.
			return true;
		}
  } catch (PDOException $e) {
    sendResponseJson(false, "Error: " . $e->getMessage(), null);
    exit;
  }
	
	$sqlInsert = "INSERT INTO recruiter_domains_suggestions (user_id,user_ip,domain) VALUES (:user_id,:user_ip,:domain)";
	
	try {
		$db = new recruiter_block_list_db();
		$db = $db->connect();
		
		$stmt = $db->prepare($sqlInsert);
		$stmt->bindParam(':user_id', $userId);
		$stmt->bindParam(':user_ip', $userIpAddr);
		$stmt->bindParam(':domain', $hostName);
    $stmt->execute();
		
		return true;
	} catch (PDOException $e) {
		sendResponseJson(false, "Error: " . $e->getMessage(), null);
    exit;
  }
  return false;
}

$app->post('/recruiters/add', function(Request $request, Response $response) {
  
  // Get the client's IP address (don't trust this but save anyway).
	$userIpAddr = htmlspecialchars(get_client_ip());
  $userId = htmlspecialchars($request->getParam('userId'));
  
  // Validate the recaptcha or send JSON response and exit!
  if (defined('DEBUG') && DEBUG == true) {
  } else {
    // Get the reCaptcha response.
    $gReCaptchaResponse = htmlspecialchars($request->getParam('g-recaptcha-response'));
    
    validateRecaptcha($gReCaptchaResponse, $userIpAddr);
  }
  
  $firstName = trim(htmlspecialchars($request->getParam('firstName')));
	$lastName = trim(htmlspecialchars($request->getParam('lastName')));
  $email = strtolower(trim(htmlspecialchars($request->getParam('email'))));
	$phone = trim(htmlspecialchars($request->getParam('phone')));
  $company = trim(htmlspecialchars($request->getParam('company')));
  
	$address = trim(htmlspecialchars($request->getParam('address')));
	$city = trim(htmlspecialchars($request->getParam('city')));
	$stateCode = strtoupper(trim(htmlspecialchars($request->getParam('stateCode'))));
	$countryCode = strtoupper(trim(htmlspecialchars($request->getParam('countryCode'))));
	$zip = trim(htmlspecialchars($request->getParam('zip')));
  
  // Inputs for debugging.
  $inputs = array(
      "firstName" => $firstName,
      "lastName" => $lastName,
      "email" => $email,
      "phone" => $phone,
      "company" => $company,
      "address" => $address,
      "city" => $city,
      "stateCode" => $stateCode,
      "countryCode" => $countryCode,
      "zip" => $zip,
  );
  
  // First name validation (required).
  if (isStrLenGreaterEqual($firstName, 1) == false) {
    sendResponseJson(false, "Error: Please enter a first name!", $inputs);
    exit;
  } else if (isStrLenLessThanEqual($firstName, 63) == false) {
    sendResponseJson(false, "Error: The first name must be 63 characters or less!", $inputs);
    exit;
  }
  
  // Last name validation (required).
  if (isStrLenGreaterEqual($lastName, 1) == false) {
    sendResponseJson(false, "Error: Please enter a last name!", $inputs);
    exit;
  } else if (isStrLenLessThanEqual($lastName, 63) == false) {
    sendResponseJson(false, "Error: The last name must be 63 characters or less!", $inputs);
    exit;
  }
  
  // Email validation (required).
  if (isStrLenGreaterEqual($email, 3) == false) {
    sendResponseJson(false, "Error: Please enter a valid email address!", $inputs);
    exit;
  } else if (isStrLenLessThanEqual($email, 63) == false) {
    sendResponseJson(false, "Error: The email address must be 63 characters or less!", $inputs);
    exit;
  }
  $emailSplitAr = explode('@', $email);
  if (count($emailSplitAr) != 2) {
    sendResponseJson(false, "Error: The email address is invalid (it must contain an @ symbol)!", $inputs);
    exit;
  }
  
  // Phone number (required).
  if (isStrLenGreaterEqual($phone, 1) == false) {
    sendResponseJson(false, "Error: Please enter a valid phone number!", $inputs);
    exit;
  }
  $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
  try {
    $phoneNumberObject = $phoneNumberUtil->parse($phone, null);
  } catch (\libphonenumber\NumberParseException $npe) {
    sendResponseJson(false, "Error: The phone number is not valid! NumberParseException: " . $npe, $inputs);
    exit;
  }
  // Check if this is a valid phone number.
  if ($phoneNumberUtil->isValidNumber($phoneNumberObject) == false) {
    sendResponseJson(false, "Error: The phone number supplied is invalid!", $inputs);
    exit;
  }
  // Replace phone with the E164 format.
  $phone = $phoneNumberUtil->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::E164);
  // Make sure the string length doesn't exceed the field in the DB.
  if (isStrLenLessThanEqual($phone, 15) == false) {
    $inputs['phone'] = $phone;
    sendResponseJson(false, "Error: The last name must be 15 characters or less!", $inputs);
    exit;
  }
  
  // Company validation (required).
  if (isStrLenGreaterEqual($company, 1) == false) {
    sendResponseJson(false, "Error: Please enter a company name!", $inputs);
    exit;
  } else if (isStrLenLessThanEqual($company, 31) == false) {
    sendResponseJson(false, "Error: The company name must be 31 characters or less!", $inputs);
    exit;
  }
  
  // Address (optional).
  if (isStrLenLessThanEqual($address, 63) == false) {
    sendResponseJson(false, "Error: The address must be 63 characters or less!", $inputs);
    exit;
  }
  
  // City (optional).
  if (isStrLenLessThanEqual($city, 63) == false) {
    sendResponseJson(false, "Error: The city must be 63 characters or less!", $inputs);
    exit;
  }
  
  // stateCode (optional).
  if (isStrLenLessThanEqual($stateCode, 7) == false) {
    sendResponseJson(false, "Error: The state code must be 7 characters or less!", $inputs);
    exit;
  }
  
  // Country code (required).
  if (isStrLenGreaterEqual($countryCode, 1) == false) {
    sendResponseJson(false, "Error: Please enter a valid country code!", $inputs);
    exit;
  } else if (isStrLenLessThanEqual($countryCode, 7) == false) {
    sendResponseJson(false, "Error: The country code must be 7 characters or less!", $inputs);
    exit;
  }
  
  // Zip (optional).
  if (isStrLenLessThanEqual($zip, 11) == false) {
    sendResponseJson(false, "Error: The zip must be 11 characters or less!", $inputs);
    exit;
  }
  
  /*$matchingRecruitersInTable = getAllMatchingRecruitersInTable($email, $phoneValidStr);
  if ($matchingRecruitersInTable) {
    // Calculate a percentage match to existing recruiters, 
    // then decide if the recruiter should be added.
  }*/
  
  // Insert into recruiter suggestions table.
  $resultRecSuggInsertBool = insertRecruiterIntoRecruiterSuggestionsTable(
      $firstName,
      $lastName,
      $email,
      $phone,
      $company,
      $address,
      $city,
      $stateCode,
      $countryCode,
      $zip);
  
  if ($resultRecSuggInsertBool != true) {
    sendResponseJson(false, "Error: Could not add the recruiter! Please check your inputs!", $inputs);
    exit;
  }
  
  // Insert the recruiter's domain into the recruiter domain suggestions table
  // if the domain doesn't already exist in the official recruiter domain table.
  $resultRecDomSuggInsertBool = insertRecDomainIntoRecDomainSuggestionsTable(
      $userId,
      $userIpAddr,
      $emailSplitAr[1]);
  
  if ($resultRecDomSuggInsertBool != true) {
    sendResponseJson(false, "Error: Could not add the recruiter! Please check your inputs!", $inputs);
    exit;
  }
  
  sendResponseJson(
      true,
      "Thank you for your contribution! Note: It may take some time for your suggestion to be added to the block list!",
      null);
  exit;

});

// Get all recruiter domains (as JSON)
$app->get('/recruiters/get_all_as_json', function(Request $request, Response $response) {
    $sqlGetAllRecruiters = "SELECT * FROM recruiters";

    try {
        // Get the database object.
        $db = new recruiter_block_list_db();

        // Connect to the database.
        $db = $db->connect();
        $stmt = $db->prepare($sqlGetAllRecruiters);
        $stmt->execute();
        
        // Turn on output buffering with the gzhandler.
        ob_start('ob_gzhandler');
        
        header('Content-type: application/json');
        echo '[';
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
          // First entry. No comma.
          echo json_encode($row);
          
          // Subsequent entries, prefix with comma.
          while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "," . json_encode($row);
          }
        }
        echo ']';
        
        $db = null;
    } catch (PDOException $e) {
        sendResponseJson(false, "Error: " . $e->getMessage(), null);
        exit;
    } 
});


// Get all recruiter domains (as TEXT)
$app->get('/recruiters/get_all_as_text', function(Request $request, Response $response) {
    $sql = "SELECT * FROM recruiters";

    try {
        // Get the database object.
        $db = new recruiter_block_list_db();
        $db = $db->connect();
        $stmt = $db->prepare($sql);
        $stmt->execute();
        
        // Turn on output buffering with the gzhandler.
        ob_start('ob_gzhandler');
        
        header('Content-type: application/text');
        header('Content-Disposition: attachment; filename="recruiterBlockList.txt"');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach ($row as $key => $value) {
                echo $key."=".$value.",";
            }
            echo "\r\n";
        }
        $db = null;
    } catch (PDOException $e) {
        sendResponseJson(false, "Error: " . $e->getMessage(), null);
        exit;
    }
});


// Get all recruiter domains (as CSV)
$app->get('/recruiters/get_all_as_csv', function(Request $request, Response $response) {
    $sql = "SELECT * FROM recruiters";

    try {
        // Get the database object.
        $db = new recruiter_block_list_db();
        $db = $db->connect();
        $stmt = $db->prepare($sql);
        $stmt->execute();
        
        // Turn on output buffering with the gzhandler.
        ob_start('ob_gzhandler');
        
        header('Content-type: application/csv');
        header('Content-Disposition: attachment; filename="recruiterBlockList.csv"');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach ($row as $key => $value) {
                echo "\"" . $value . "\",";
            }
            echo "\r\n";
        }
        $db = null;
    } catch (PDOException $e) {
        sendResponseJson(false, "Error: " . $e->getMessage(), null);
        exit;
    }
});

// Get all recruiter domains (as Gmail XML Mail Filter)
$app->get('/recruiters/get_all_as_xml', function(Request $request, Response $response) {
    $sql = "SELECT * FROM recruiters";

    try {
        // Get the database object.
        $db = new recruiter_block_list_db();
        $db = $db->connect();
        $stmt = $db->prepare($sql);
        $stmt->execute();
        
        // Turn on output buffering with the gzhandler.
        ob_start('ob_gzhandler');
        
        header('Content-type: application/xml');
        header('Content-Disposition: attachment; filename="recruiterBlockListGmailMailFilter.xml"');
        
        echo "<?xml version='1.0' encoding='UTF-8'?><feed xmlns='http://www.w3.org/2005/Atom' xmlns:apps='http://schemas.google.com/apps/2006'>\r\n";
        echo "\t<title>Mail Filters</title>\r\n";
        
        // Date
        $dateTimeStr = "<updated>".date('Y-m-d')."T".date('G:i:s')."Z</updated>";
        echo "\t".$dateTimeStr."\r\n";
        
        echo "\t<author>\r\n";
        echo "\t\t<name>Recruiter Blocker</name>\r\n";
        echo "\t\t<email>support@recruiterblocker.com</email>\r\n";
        echo "\t</author>\r\n";
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "\t<entry>\r\n";
            echo "\t\t<category term='filter'></category>\r\n";
            echo "\t\t<title>Mail Filter</title>\r\n";
            echo "\t\t<id>tag:mail.google.com,2008:filter:".$row['id']."</id>\r\n";
            echo "\t\t".$dateTimeStr."\r\n";
            echo "\t\t<content></content>\r\n";
            echo "\t\t<apps:property name='from' value='".$row['email']."'/>\r\n";
            echo "\t\t<apps:property name='shouldTrash' value='true'/>\r\n";
            echo "\t\t<apps:property name='sizeOperator' value='s_sl'/>\r\n";
            echo "\t\t<apps:property name='sizeUnit' value='s_smb'/>\r\n";
            echo "\t</entry>\r\n";
        }
        echo "</feed>";
        $db = null;
    } catch (PDOException $e) {
        sendResponseJson(false, "Error: " . $e->getMessage(), null);
    }
});

/*
// Get by ID
$app->post('/recruiters/get_by_id', function(Request $request, Response $response) {
	$id = $request->getParam('id');
	
	$id = trim($id);
	$id = strtolower($id);
	
	// changes characters used in html to their equivalents, for example: < to &gt;
	$id = htmlspecialchars($id);
	//echo '{"notice": {"text": "id='.$id.'"}';
	
	if (strlen($id) >= 1) {
		$sql = 'SELECT * FROM recruiter_domains WHERE id = :id_to_find';
		
		try {
			// Get the database object.
			$db = new recruiter_domains_db();

			// Connect to the database.
			$db = $db->connect();
			$stmt = $db->prepare($sql);
			$result = $stmt->execute(array(":id_to_find" => $id));
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if ($row) {
				$response_array['success'] = true;
				$response_array['result'] = $row;
			} else {
				$response_array['success'] = false;  
			}
			
			$db = null;
			header('Content-type: application/json');
			echo json_encode($response_array);
		} catch (PDOException $e) {
			echo '{"error": {"text": '.$e->getMessage().'}';
		}
	} else {
		echo '{"error": {"text": The ID cannot be empty!}';
	}
});

// Check if a recruiter domain is in the list.
$app->post('/recruiter_domains/is_in_list', function(Request $request, Response $response) {
    $domain = $request->getParam('domain');
	
	$domain = trim($domain);
	$domain = strtolower($domain);
	
	// changes characters used in html to their equivalents, for example: < to &gt;
	$domain = htmlspecialchars($domain);
	//echo '{"notice": {"text": "domain='.$domain.'"}';
	
	if (strpos($domain, '.') !== false && strlen($domain) >= 3) {
		$sql = 'SELECT * FROM recruiter_domains WHERE domain = :domain_to_find';
		
		try {
			// Get the database object.
			$db = new recruiter_domains_db();

			// Connect to the database.
			$db = $db->connect();
			$stmt = $db->prepare($sql);
			$result = $stmt->execute(array(":domain_to_find" => $domain));
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if ($row) {
				$response_array['success'] = true;
			} else {
				$response_array['success'] = false;
			}
			
			$db = null;
			header('Content-type: application/json');
			echo json_encode($response_array);
		} catch (PDOException $e) {
			echo '{"error": {"text": '.$e->getMessage().'}';
		}
	} else {
		$response_array['success'] = false;
		header('Content-type: application/json');
		echo json_encode($response_array);
	}
});
*/
?>