<?php
header("Content-Type: application/json; charset=utf-8");

$input = file_get_contents("php://input");
$data  = json_decode($input, true);

if (!is_array($data) || !isset($data["script"]) || trim($data["script"]) === "") {
    echo json_encode(array("error" => "script required"));
    exit;
}

$userScript = $data["script"];  // ユーザーが入力した原稿（歌詞など）

// プロンプトを生成
$prompt = "あなたは漫画脚本家です。
以下の原稿をもとに、1～5コマの短い漫画の物語を作成してください。
内容をそのまま分割するのではなく、
状況・人物・行動がわかるストーリーに再構成してください。

【重要】

原稿の文章をそのまま分割して使うのは禁止
必ず登場人物の状況がわかる流れにする
原稿の言葉を引用しすぎない（再解釈する）
物語として「起→葛藤→決断→余韻」が見える構成にする
セリフのみ
有効なJSONのみ出力

マークダウン禁止
【出力形式】
{
  \"frames\": [
    { \"frame\": 1, \"text\": \"セリフ1\" },
    { \"frame\": 2, \"text\": \"セリフ2\" }
  ]
}

【原稿】
" . $userScript . "

上記の形式で、JSONのみを出力してください。";

// 外部APIに送信
$ch = curl_init("http://exbridge.ddns.net:8002/generate_story");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(
    array("prompt" => $prompt),
    JSON_UNESCAPED_UNICODE
));
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Content-Type: application/json"
));
curl_setopt($ch, CURLOPT_TIMEOUT, 180);

$response = curl_exec($ch);

if ($response === false) {
    echo json_encode(array("error" => "api connection failed"));
    exit;
}

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    echo json_encode(array("error" => "api http error", "status" => $http_code));
    exit;
}

$cleanResponse = preg_replace('/```json\s*/', '', $response);
$cleanResponse = preg_replace('/```\s*/', '', $cleanResponse);
$cleanResponse = trim($cleanResponse);

$decoded = json_decode($cleanResponse, true);

if (!is_array($decoded) || !isset($decoded["frames"])) {
    echo json_encode(array("error" => "invalid api response"));
    exit;
}

echo json_encode($decoded, JSON_UNESCAPED_UNICODE);
exit;
