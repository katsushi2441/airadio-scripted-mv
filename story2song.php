<?php
// =========================================
// story2song.php
// エピソード → 歌詞生成 → ScriptedMVへ自動遷移
// PHP5互換 / 安定版
// =========================================

date_default_timezone_set("Asia/Tokyo");

$episode = "";
$msg = "";
$redirectLyrics = "";

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["generate"])
    && isset($_POST["episode"])
    && trim($_POST["episode"]) !== ""
) {

    $episode = trim($_POST["episode"]);

    $prompt =
"以下のエピソードを元に、
60秒程度のJ-POP風の歌詞を書いてください。

【重要】
・演出説明を書かない
・括弧を使わない
・秒数を書かない
・ポイント解説を書かない
・歌詞のみ出力する

エピソード：
".$episode;

    $ch = curl_init("https://exbridge.ddns.net/api/generate");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
        "model"  => "gemma3:12b",
        "prompt" => $prompt,
        "stream" => false
    )));
    $res = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($res === false) {
        $msg = "❌ cURL エラー: " . $curlErr;
    } else {
        $j = json_decode($res, true);
        if (isset($j["response"])) {
            $redirectLyrics = trim($j["response"]);
        } else {
            $msg = "❌ レスポンス異常";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>Story2Song | ScriptedMV拡張</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{
    font-family:system-ui,-apple-system,"Segoe UI",sans-serif;
    background:radial-gradient(1200px 600px at 50% -20%, #1e293b, #020617);
    color:#e5e7eb;
    padding:20px;
}
.wrap{max-width:720px;margin:0 auto}
.card{
    background:rgba(15,23,42,0.92);
    border:1px solid rgba(148,163,184,0.2);
    border-radius:18px;
    padding:20px;
    margin-bottom:20px;
}
h1{font-size:20px;margin:0 0 14px}
h2{font-size:16px;margin:0 0 12px}
textarea,input{
    width:100%;
    padding:10px;
    border-radius:12px;
    border:1px solid rgba(148,163,184,0.3);
    background:#020617;
    color:#fff;
    font-size:14px;
}
textarea{min-height:180px}
button{
    width:100%;
    margin-top:14px;
    padding:12px;
    border:0;
    border-radius:14px;
    background:linear-gradient(135deg,#6366f1,#22d3ee);
    color:#020617;
    font-weight:600;
    cursor:pointer;
}
.note{
    font-size:12px;
    color:#94a3b8;
    margin-top:8px;
}
.msg{margin-top:10px;font-size:14px}
#ai-thinking{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.85);
    display:none;
    align-items:center;
    justify-content:center;
    color:#fff;
    font-size:18px;
    z-index:9999;
}
</style>
</head>
<body>

<?php if($redirectLyrics!=""): ?>
<form id="autoRedirect" method="post" action="scripted-mv.php">
<input type="hidden" name="script_text" value="<?php echo htmlspecialchars($redirectLyrics, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="auto_story2song" value="1">
</form>
<script>
document.getElementById("autoRedirect").submit();
</script>
<?php exit; endif; ?>

<div class="wrap">

<div class="card">
<h1>🎼 Story2Song</h1>
<h2>エピソード → 歌詞生成</h2>

<form method="post" id="generateForm">
<label>エピソード（Xに投稿するような短文）</label>
<textarea name="episode" id="episodeArea" required><?php
echo htmlspecialchars($episode, ENT_QUOTES, "UTF-8");
?></textarea>

<button type="submit" name="generate">🎵 歌詞を生成</button>
</form>

<?php if($msg!=""): ?>
<div class="msg"><?php echo $msg; ?></div>
<?php endif; ?>

<div class="note">
物語の断片、感情、出来事を書いてください。<br>
AIが60秒の歌詞に変換します。
</div>

</div>
</div>

<div id="ai-thinking">
AIが歌詞を生成中です…<br>
少しお待ちください
</div>

<script>
var episodeArea = document.getElementById("episodeArea");

if(episodeArea){
    episodeArea.addEventListener("input", function(){
        localStorage.setItem("story2song_episode", episodeArea.value);
    });

    var saved = localStorage.getItem("story2song_episode");
    if(saved && episodeArea.value === ""){
        episodeArea.value = saved;
    }
}

var generateForm = document.getElementById("generateForm");
if(generateForm){
    generateForm.addEventListener("submit", function(){
        document.getElementById("ai-thinking").style.display = "flex";
        setTimeout(function(){
            var inputs = generateForm.querySelectorAll("textarea");
            for(var i=0;i<inputs.length;i++){
                inputs[i].disabled = true;
            }
        }, 100);
    });
}
</script>

</body>
</html>
