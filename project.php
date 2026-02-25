<?php
header("Content-Type: application/json; charset=utf-8");

define("PROJECT_TOKEN", "YOUR_SECRET_TOKEN");

$token = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["token"])) {
        $token = $_POST["token"];
    }
} else {
    if (isset($_GET["token"])) {
        $token = $_GET["token"];
    }
}

if ($token !== PROJECT_TOKEN) {
    echo json_encode(array("status"=>"fail","reason"=>"invalid token"));
    exit;
}

$dir = __DIR__ . "/projects";

if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

/* =========================
   MP4保存処理（POST）
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!isset($_POST["name"]) || !isset($_FILES["mp4"])) {
        echo json_encode(array("status"=>"fail","reason"=>"missing params"));
        exit;
    }

    $name = trim($_POST["name"]);
    $name = preg_replace("/[\/\\\\]/", "_", $name);

    $target = $dir . "/" . $name . ".mp4";

    if (move_uploaded_file($_FILES["mp4"]["tmp_name"], $target)) {
        echo json_encode(array("status"=>"ok"));
    } else {
        echo json_encode(array("status"=>"fail","reason"=>"save error"));
    }

    exit;
}

/* =========================
   一覧取得（GET）
========================= */

$projects = array();

$files = scandir($dir);

foreach ($files as $file) {

    if (preg_match('/\.json$/i', $file)) {

        $base = preg_replace('/\.json$/i', '', $file);

        $json = $dir . "/" . $base . ".json";
        $mp3  = $dir . "/" . $base . ".mp3";
        $mp4  = $dir . "/" . $base . ".mp4";

        $projects[] = array(
            "name" => $base,
            "json" => file_exists($json),
            "mp3"  => file_exists($mp3),
            "mp4"  => file_exists($mp4)
        );
    }
}

echo json_encode(
    array("projects"=>$projects),
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);

exit;

