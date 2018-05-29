<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once "../config/db_config.php";

// Get all states (as JSON)
$app->post('/states/get_all_by_name', function(Request $request, Response $response) {
    $countryCode = strtoupper(htmlspecialchars($request->getParam('countryCode')));
    $sql = "SELECT name,code FROM states WHERE countryCode = :countryCode ORDER BY name";
    
    try {
      // Get the database object.
      $db = new recruiter_block_list_db();

      // Connect to the database.
      $db = $db->connect();

      $stmt = $db->prepare($sql);
      $stmt->execute(array(":countryCode" => $countryCode));
      
      // Turn on output buffering with the gzhandler
      ob_start('ob_gzhandler');
      header('Content-type: application/json');
      echo '[';
      if ($state = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // First entry. No comma.
        echo "{\"name\":\"" . $state['name'] . "\",\"code\":\"" . $state['code'] . "\"}";
        
        // Subsequent entries, prefix with comma.
        while ($state = $stmt->fetch(PDO::FETCH_ASSOC)) {
          echo ",{\"name\":\"" . $state['name'] . "\",\"code\":\"" . $state['code'] . "\"}";
        }
      }
      
      // Closing square bracket.
      echo ']';
      
      $db = null;
    } catch (PDOException $e) {
      header('Content-type: application/json');
      echo '{"error": {"text": '.$e->getMessage().'}';
    }
});

?>