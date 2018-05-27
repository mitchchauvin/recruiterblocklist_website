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

        $stmt = $db->query($sql);
        $all_country_names = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
        header('Content-type: application/json');
        echo json_encode($all_country_names);
    } catch (PDOException $e) {
        echo '{"error": {"text": '.$e->getMessage().'}';
    }
});

?>