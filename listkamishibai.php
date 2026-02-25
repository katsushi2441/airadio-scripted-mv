<?php
$dir = __DIR__ . "/projects";

/* ==========================
   削除処理
========================== */
if(isset($_POST["delete_project"])){

    $name = trim($_POST["delete_name"]);
    $safe = preg_replace('/[^\p{L}\p{N}_\-]/u','_', $name);

    $jsonFile = $dir . "/" . $safe . ".json";
    $mp3File  = $dir . "/" . $safe . ".mp3";
    $mp4File  = $dir . "/" . $safe . ".mp4";

    if(file_exists($jsonFile)) unlink($jsonFile);
    if(file_exists($mp3File)) unlink($mp3File);
    if(file_exists($mp4File)) unlink($mp4File);

    header("Location: listkamishibai.php");
    exit;
}

/* ==========================
   新規作成処理
========================== */
if(isset($_POST["new_project"])){

    $name = trim($_POST["project_name"]);

    if($name !== ""){

        $safe = preg_replace('/[^\p{L}\p{N}_\-]/u','_', $name);

        $jsonFile = $dir . "/" . $safe . ".json";

        if(!file_exists($jsonFile)){

            $init = array(
                "title" => $safe,
                "slides" => array(),
                "bgm_file" => ""
            );

            file_put_contents(
                $jsonFile,
                json_encode($init, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );

            header("Location: kamishibai.html?project=" . urlencode($safe));
            exit;
        }
    }
}

$files = glob($dir . "/*.json");
rsort($files);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Kamishib.AI – Projects</title>
<link rel="stylesheet" href="style.css">
</head>
<script>
(function(){
    var s = document.createElement('script');
    s.src = 'https://aiknowledgecms.exbridge.jp/simpletrack.php'
          + '?url=' + encodeURIComponent(location.href)
          + '&ref=' + encodeURIComponent(document.referrer);
    document.head.appendChild(s);
})();
</script>
<body>

<div class="header">
    <a href="./">
        <img src="./images/kamishibai_logo.png" class="logo">
    </a>
    <h2>Kamishib.AI Maker –紙芝居エディタ - Project List</h2>
</div>

<!-- =======================
     新規プロジェクト作成UI
======================== -->
<div style="width:92%;margin:10px 0 30px 4%;">

    <form method="POST" style="display:flex;gap:10px;align-items:center;">
        <input
            type="text"
            name="project_name"
            placeholder="新規プロジェクト名"
            required
            style="padding:8px 10px;width:260px;"
        >
        <button type="submit" name="new_project">
            ＋ 新規作成
        </button>
    </form>

</div>

<?php
if(!$files){
    echo '<div style="margin-left:4%;margin-top:40px;">プロジェクトがありません。</div>';
}

foreach($files as $file){

    $json = json_decode(file_get_contents($file), true);
    if(!$json) continue;

    $title = isset($json["title"]) ? htmlspecialchars($json["title"]) : "No Title";
    $slides = isset($json["slides"]) ? $json["slides"] : array();
    $bgm = isset($json["bgm_file"]) ? $json["bgm_file"] : "";
?>

<div style="width:92%;margin:30px 0 30px 4%;">

    <h2><?php echo $title; ?></h2>

    <div style="margin-left:4%;margin-top:14px;display:flex;align-items:center;gap:18px;flex-wrap:wrap;">

        <a href="kamishibai.html?project=<?php echo urlencode($title); ?>">
            <button class="actionBtn">✏ 編集</button>
        </a>

        <?php
        if(file_exists(__DIR__ . "/projects/" . $title . ".mp3")){
            $mp3Path = "projects/" . urlencode($title) . ".mp3";
        ?>
            <div style="color:#94a3b8;">
                🎵 <?php echo htmlspecialchars($bgm); ?>
            </div>
            <audio controls preload="none" style="width:220px;">
                <source src="<?php echo $mp3Path; ?>" type="audio/mpeg">
            </audio>
        <?php } ?>

        <!-- 削除ボタン -->
        <form method="POST" style="display:inline;">
            <input type="hidden" name="delete_name" value="<?php echo htmlspecialchars($title); ?>">
            <button
                type="submit"
                name="delete_project"
                class="actionBtn"
                onclick="return confirm('本当に削除しますか？');"
                style="background:#ef4444;color:white;"
            >
                🗑 削除
            </button>
        </form>

    </div>

    <div id="editor">
        <?php
        $count = 0;
        foreach($slides as $s){
            if(isset($s["image"]) && strpos($s["image"], "data:image") === 0){
                echo '<div class="slideBox">';
                echo '<img src="'.$s["image"].'">';
                echo '</div>';
                $count++;
                if($count >= 5) break;
            }
        }
        ?>
    </div>

</div>

<?php } ?>

</body>
</html>

