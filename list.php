<?php
header("Content-Type: application/json; charset=utf-8");

$dir = __DIR__ . "/musics";
$files = array();

if (is_dir($dir)) {
    foreach (scandir($dir) as $file) {
        if (preg_match('/\.mp4$/i', $file)) {
            $files[] = $file;
        }
    }
}

sort($files);

echo json_encode(array(
    "files" => $files
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

