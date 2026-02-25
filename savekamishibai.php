<?php
header("Content-Type: application/json; charset=utf-8");

if(!isset($_POST["json"])){
    echo json_encode(array("status"=>"fail"));
    exit;
}

$data = json_decode($_POST["json"], true);
if(!$data || !isset($data["title"])){
    echo json_encode(array("status"=>"fail"));
    exit;
}

$title = trim($data["title"]);
$title = preg_replace("/[\/\\\\]/", "_", $title);

$dir = __DIR__ . "/projects";
if(!is_dir($dir)){
    mkdir($dir, 0777, true);
}

$jsonFile = $dir . "/" . $title . ".json";
file_put_contents($jsonFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

if(isset($_FILES["bgm"]) && $_FILES["bgm"]["error"] === 0){

    $ext = pathinfo($_FILES["bgm"]["name"], PATHINFO_EXTENSION);
    if(strtolower($ext) === "mp3"){
        $bgmPath = $dir . "/" . $title . ".mp3";
        move_uploaded_file($_FILES["bgm"]["tmp_name"], $bgmPath);
    }
}

echo json_encode(array("status"=>"ok"));

