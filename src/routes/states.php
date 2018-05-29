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
        $all_states = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        header('Content-type: application/json');
        echo json_encode($all_states);
        $db = null;
    } catch (PDOException $e) {
        echo '{"error": {"text": '.$e->getMessage().'}';
    }
});

?>