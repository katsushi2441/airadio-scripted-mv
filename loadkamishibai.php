<?php
header("Content-Type: application/json; charset=utf-8");

if(!isset($_GET["project"])){
    echo json_encode(array("status"=>"fail"));
    exit;
}

$title = preg_replace("/[\/\\\\]/", "_", $_GET["project"]);
$file = __DIR__ . "/projects/" . $title . ".json";

if(!file_exists($file)){
    echo json_encode(array("status"=>"fail"));
    exit;
}

echo file_get_contents($file);

