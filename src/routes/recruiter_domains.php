<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once "../config/db_config.php";

function get_client_ip() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

// Insert a new recruiter.
$app->post('/recruiter_domains/add', function(Request $request, Response $response) {
    
	// Get the reCaptcha response.
	$gReCaptchaResponse = htmlspecialchars($request->getParam('g-recaptcha-response'));
	
	// Get the client's IP address (don't trust this but save anyway).
	$userIpAddr = htmlspecialchars(get_client_ip());
	
	// Get the user ID.
	$userId = htmlspecialchars($request->getParam('userId'));
	
	// Get the recruiter's domain to add.
	$domain = $request->getParam('domain');
	$domain = trim($domain);
	$domain = strtolower($domain);
	$domain = htmlspecialchars($domain);
	
	// Start by sending the reCaptcha token to Google for verification.
	// If the recaptcha fails the client might be a bot and we should exit immediately.
	$url = 'https://www.google.com/recaptcha/api/siteverify';
	$data = array(
			'secret' => RECAPTCHA_PRIVATE_KEY,
			'response' => $gReCaptchaResponse,
			'remoteip' => $userIpAddr
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
	$result = file_get_contents($url, false, $context);
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
	
	// Try to parse the domain URL and get the host name.
	if (substr($domain, 0, 4) !== "http") {
		$domain = "http://" . $domain;
	}
	$parsed_domain = parse_url($domain);
	if (array_key_exists('host', $parsed_domain) == false) {
		// Handle error.
		$result = array(
			'success' => false,
			'result' => "Error: The format of the domain is incorrect! The format should be domain.com. The domain you entered was " . $domain
		);
		header('Content-type: application/json');
		echo json_encode($result);
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
			$result = array(
				'success' => false,
				'result' => "Error: The recruiter's domain already exists in the database!"
			);
			header('Content-type: application/json');
			echo json_encode($result);
			exit;
		}
    } catch (PDOException $e) {
		header('Content-type: application/json');
		$result = array(
			'success' => false,
			'result' => 'Error: '.$e->getMessage()
		);
		echo json_encode($result);
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
		
		$result = array(
			'success' => true,
			'result' => "Your suggestion " . $hostName . "has been received! Note: it may take up to 24 hrs for your suggestion to be added to the block list!",
		);
		header('Content-type: application/json');
		echo json_encode($result);
		exit;
	} catch (PDOException $e) {
		header('Content-type: application/json');
		$result = array(
			'success' => false,
			'result' => 'Error: '.$e->getMessage()
		);
		echo json_encode($result);
		exit;
    }
});

// Get all recruiter domains (as JSON)
$app->get('/recruiter_domains/get_all_as_json', function(Request $request, Response $response) {
    $sql = "SELECT domain FROM recruiter_domains";

    try {
        // Get the database object.
        $db = new recruiter_block_list_db();

        // Connect to the database.
        $db = $db->connect();

        $stmt = $db->query($sql);
        $all_recruiter_domains = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
        echo json_encode($all_recruiter_domains);
    } catch (PDOException $e) {
        echo '{"error": {"text": '.$e->getMessage().'}';
    }
});

// Get all recruiter domains (as TEXT)
$app->get('/recruiter_domains/get_all_as_text', function(Request $request, Response $response) {
    $sql = "SELECT domain FROM recruiter_domains ORDER BY domain";

    try {
        // Get the database object.
        $db = new recruiter_block_list_db();

        // Connect to the database.
        $db = $db->connect();
        $stmt = $db->query($sql);
		
		header('Content-type: application/text');
		header('Content-Disposition: attachment; filename="recruiterBlockList.txt"');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			echo $row['domain']."\r\n";
		}
		$db = null;
    } catch (PDOException $e) {
        echo '{"error": {"text": '.$e->getMessage().'}';
    }
});

// Get all recruiter domains (as CSV)
$app->get('/recruiter_domains/get_all_as_csv', function(Request $request, Response $response) {
    $sql = "SELECT domain FROM recruiter_domains";

    try {
        // Get the database object.
        $db = new recruiter_block_list_db();

        // Connect to the database.
        $db = $db->connect();
        $stmt = $db->query($sql);
		
		header('Content-type: application/text');
		header('Content-Disposition: attachment; filename="recruiterBlockList.csv"');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			echo $row['domain'].",\r\n";
		}
		$db = null;
    } catch (PDOException $e) {
        echo '{"error": {"text": '.$e->getMessage().'}';
    }
});

// Get all recruiter domains (as Gmail XML Mail Filter)
$app->get('/recruiter_domains/get_all_as_xml', function(Request $request, Response $response) {
    $sql = "SELECT * FROM recruiter_domains";

    try {
        // Get the database object.
        $db = new recruiter_block_list_db();

        // Connect to the database.
        $db = $db->connect();
        $stmt = $db->query($sql);
		
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
			echo "\t\t<apps:property name='from' value='@".$row['domain']."'/>\r\n";
			echo "\t\t<apps:property name='shouldTrash' value='true'/>\r\n";
			echo "\t\t<apps:property name='sizeOperator' value='s_sl'/>\r\n";
			echo "\t\t<apps:property name='sizeUnit' value='s_smb'/>\r\n";
			echo "\t</entry>\r\n";
		}
		echo "</feed>";
		$db = null;
    } catch (PDOException $e) {
        echo '{"error": {"text": '.$e->getMessage().'}';
    }
});

// Get by ID
$app->post('/recruiter_domains/get_by_id', function(Request $request, Response $response) {
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
			$db = new recruiter_block_list_db();

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
			$db = new recruiter_block_list_db();

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

?>