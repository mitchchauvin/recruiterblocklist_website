<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';

$app = new \Slim\App;

require '../src/config/recruiter_block_list_db.php';

include '../src/routes/recruiter_domains.php';
include '../src/routes/recruiters.php';

$app->run();
?>
