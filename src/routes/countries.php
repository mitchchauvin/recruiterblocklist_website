<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once "../config/db_config.php";

// Get all countries (as JSON)
$app->get('/countries/get_all_by_name', function(Request $request, Response $response) {
    $sql = "SELECT name,code FROM countries ORDER BY name";
    
    try {
      // Get the database object.
      $db = new recruiter_block_list_db();

      // Connect to the database.
      $db = $db->connect();

      $stmt = $db->prepare($sql);
      $stmt->execute();
      
      // Turn on output buffering with the gzhandler
      ob_start('ob_gzhandler');
      header('Content-type: application/json');
      echo '[';
      if ($country = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // First entry. No comma.
        echo "{\"name\":\"" . $country['name'] . "\",\"code\":\"" . $country['code'] . "\"}";
        
        // Subsequent entries, prefix with comma.
        while ($country = $stmt->fetch(PDO::FETCH_ASSOC)) {
          echo ",{\"name\":\"" . $country['name'] . "\",\"code\":\"" . $country['code'] . "\"}";
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