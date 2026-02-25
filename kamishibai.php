<?php
// =========================================
// audio2mp4.php
// 画像 + mp3/wav URL + ラジオ台本 → mp4 生成（スクロール対応）
// + 音声ファイル事前アップロード（専用ボタン）
// PHP5互換 / 機能削除なし
// =========================================

date_default_timezone_set("Asia/Tokyo");

define("API_ENDPOINT", "http://exbridge.ddns.net:8002/audio_to_mp4_multi");

$musicDir = __DIR__ . "/musics";
$musicUrlBase = "https://airadio-scripted-mv.exbridge.jp/musics";

if (!is_dir($musicDir)) {
    mkdir($musicDir, 0755, true);
}

$msg = "";
$result = null;
$audio_url = "";

/* =========================
   音声アップロード専用処理
========================= */
/* =========================
   MP4生成処理
========================= */
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && !isset($_POST["upload_audio"])
) {
    if (
        isset($_FILES["images"])
        && isset($_POST["audio_url"])
        && isset($_POST["script_text"])
        && isset($_FILES["images"]["tmp_name"])
        && (
            is_array($_FILES["images"]["tmp_name"])
                ? count($_FILES["images"]["tmp_name"]) > 0
                : $_FILES["images"]["tmp_name"] !== ""
        )
        && trim($_POST["audio_url"]) !== ""
    ) {

        $audio_url   = trim($_POST["audio_url"]);
        $script_text = trim($_POST["script_text"]);

        $ch = curl_init(API_ENDPOINT);

        // ★ 修正：配列の構造を修正
        $post = array(
            "audio_url"   => $audio_url,
            "script_text" => $script_text
        );

        // ★ 修正：images[]として複数ファイルを追加
        // ★ 複数画像追加（紙芝居用）
        foreach ($_FILES["images"]["tmp_name"] as $i => $tmp_path) {

            if ($tmp_path === "") continue;

            $post["images[]"] = new CURLFile(
                $tmp_path,
                mime_content_type($tmp_path),
                $_FILES["images"]["name"][$i]
            );
        }


        curl_setopt($ch, CURLOPT_POST, true);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);


        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($res === false) {
            $msg = "❌ API通信失敗: " . htmlspecialchars($err, ENT_QUOTES, "UTF-8");
        } else {
            $json = json_decode($res, true);
            if (is_array($json) && isset($json["ok"]) && $json["ok"]) {
                $result = $json;
                $msg = "✅ MP4生成完了";
            } else {
                $msg = "❌ 生成失敗<br><pre>" .
                    htmlspecialchars($res, ENT_QUOTES, "UTF-8") .
                    "</pre>";
            }
        }

    } else {
        $msg = "❌ 画像・音声URL・台本をすべて指定してください";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>音声＋台本 → MP4</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* ===============================
   Web3 Navy / Metallic UI
   audio2mp4.php
   構造・機能変更なし
=============================== */

body {
    font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
    background:
        radial-gradient(1200px 600px at 10% -10%, #1e3a8a33, transparent 40%),
        radial-gradient(800px 400px at 90% 10%, #0ea5e933, transparent 35%),
        linear-gradient(180deg, #020617 0%, #020617 100%);
    color: #e5e7eb;
    padding: 16px;
}

.wrap {
    max-width: 720px;
    margin: 0 auto;
}

/* ガラス＋メタリックカード */
.card {
    background:
        linear-gradient(
            135deg,
            rgba(30, 58, 138, 0.35),
            rgba(15, 23, 42, 0.65)
        );
    border: 1px solid rgba(148, 163, 184, 0.25);
    border-radius: 18px;
    padding: 16px;
    margin-bottom: 18px;
    backdrop-filter: blur(10px);
    box-shadow:
        0 10px 30px rgba(2, 6, 23, 0.6),
        inset 0 1px 0 rgba(255,255,255,0.04);
}

h1 {
    font-size: 18px;
    margin: 0 0 12px;
    font-weight: 700;
    color: #f8fafc;
    letter-spacing: 0.3px;
}

h2 {
    font-size: 15px;
    margin: 14px 0 8px;
    font-weight: 600;
    color: #c7d2fe;
}

/* フォーム */
label {
    font-size: 13px;
    display: block;
    margin-bottom: 4px;
    color: #94a3b8;
}

input[type="text"],
input[type="file"],
textarea {
    width: 100%;
    background: rgba(2, 6, 23, 0.7);
    color: #e5e7eb;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.35);
    padding: 8px;
    font-size: 14px;
}

textarea {
    min-height: 160px;
}

input::placeholder,
textarea::placeholder {
    color: #94a3b8;
}

/* ボタン */
button {
    width: 100%;
    margin-top: 12px;
    padding: 10px;
    border: 0;
    border-radius: 12px;
    background:
        linear-gradient(135deg, #2563eb, #0ea5e9);
    color: #ffffff;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.45);
}

button:hover {
    filter: brightness(1.08);
}

/* メッセージ */
.msg {
    margin-bottom: 10px;
    font-weight: 600;
    color: #38bdf8;
}

/* video */
video {
    width: 100%;
    margin-top: 8px;
    border-radius: 14px;
    background: rgba(2, 6, 23, 0.6);
}

/* note */
.note {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 10px;
    line-height: 1.6;
}

/* 結果URL */
a {
    color: #38bdf8;
    word-break: break-all;
    font-size: 13px;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}
/* ===============================
   Top Navigation
=============================== */
.top-nav {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.top-nav a {
    display: inline-block;
    padding: 8px 14px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    color: #e5e7eb;
    text-decoration: none;
    background: rgba(2, 6, 23, 0.55);
    border: 1px solid rgba(148, 163, 184, 0.35);
    backdrop-filter: blur(8px);
}

.top-nav a:hover {
    background: rgba(30, 58, 138, 0.45);
}

</style>
</head>
<body>

<div class="wrap">
<div class="top-nav">
    <a href="airadio.php">News2Audio</a>
    <a href="voicebox_ui.php">Voicebox UI</a>
    <a href="bgm_manager.php">BGM Manager</a>
    <a href="ttsfile.php">TTS Files</a>
    <a href="audio2mp4.php">Audio2MP4</a>
    <a href="video2mp4.php">Video2MP4</a>
</div>

<div class="card">
<h1>🎵 音声/楽曲ファイルアップロード</h1>

<form method="post" enctype="multipart/form-data">
<input type="hidden" name="upload_audio" value="1">

<label>音声ファイル（mp3 / wav）</label>
<input type="file" name="audio_file" accept=".mp3,.wav" required>

<button type="submit">音声/楽曲をアップロード</button>
</form>
</div>

<div class="card">
<h1>🎬静止画像＋ラジオ音声＋台本 → MP4</h1>

<?php if ($msg !== ""): ?>
<div><?php echo $msg; ?></div>
<?php endif; ?>

<form  method="post" enctype="multipart/form-data">
<label>① 背景画像</label>
<input type="file" name="images[]" id="imageInput" accept="image/*" multiple required>

<div id="preview" style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;"></div>

<label style="margin-top:12px;">② 音声/楽曲URL</label>
<input type="text" name="audio_url" value="<?php echo htmlspecialchars($audio_url, ENT_QUOTES, "UTF-8"); ?>" required>

<label style="margin-top:12px;">③ ラジオ台本（スクロール表示）</label>
<textarea name="script_text"></textarea>

<button type="submit">MP4を生成</button>
</form>

<div class="note">
・先に音声/楽曲をアップロードしてください<br>
・URLが確定してから MP4 を生成します
</div>
</div>

<?php if ($result): ?>
<div class="card">
<h2>生成結果</h2>

<video controls>
    <source src="<?php echo htmlspecialchars($result["mp4_url"], ENT_QUOTES, "UTF-8"); ?>">
</video>

<a href="<?php echo htmlspecialchars($result["mp4_url"], ENT_QUOTES, "UTF-8"); ?>" target="_blank">
<?php echo htmlspecialchars($result["mp4_url"], ENT_QUOTES, "UTF-8"); ?>
</a>
</div>
<?php endif; ?>

</div>

<script>
var API_ENDPOINT = "<?php echo API_ENDPOINT; ?>";
var imageInput = document.getElementById("imageInput");
var preview = document.getElementById("preview");
var form = document.getElementById("mp4Form");

var selectedFiles = [];

imageInput.addEventListener("change", function(e) {
    selectedFiles = Array.from(e.target.files);
    renderPreview();
});


function renderPreview() {
    preview.innerHTML = "";

    if (selectedFiles.length === 0) {
        return;
    }

    selectedFiles.forEach(function(file, index) {
        var reader = new FileReader();

        reader.onload = function(event) {
            var wrapper = document.createElement("div");
            wrapper.style.cssText = "display:flex; align-items:center; gap:8px; margin-bottom:6px; background:rgba(2,6,23,0.5); padding:8px; border-radius:8px;";

            var img = document.createElement("img");
            img.src = event.target.result;
            img.style.cssText = "width:80px; height:80px; object-fit:cover; border-radius:6px;";

            var info = document.createElement("div");
            info.style.cssText = "flex:1; font-size:12px; color:#94a3b8;";
            info.textContent = file.name + " - " + (index + 1) + "枚目";

            var up = document.createElement("button");
            up.type = "button";
            up.textContent = "▲";
            up.style.cssText = "width:32px; height:32px; padding:0; font-size:14px; margin:0;";
            up.onclick = function(e) {
                e.preventDefault();
                if (index > 0) {
                    var tmp = selectedFiles[index];
                    selectedFiles[index] = selectedFiles[index-1];
                    selectedFiles[index-1] = tmp;
                    renderPreview();
                }
            };

            var down = document.createElement("button");
            down.type = "button";
            down.textContent = "▼";
            down.style.cssText = "width:32px; height:32px; padding:0; font-size:14px; margin:0;";
            down.onclick = function(e) {
                e.preventDefault();
                if (index < selectedFiles.length - 1) {
                    var tmp = selectedFiles[index];
                    selectedFiles[index] = selectedFiles[index+1];
                    selectedFiles[index+1] = tmp;
                    renderPreview();
                }
            };

            var remove = document.createElement("button");
            remove.type = "button";
            remove.textContent = "✕";
            remove.style.cssText = "width:32px; height:32px; padding:0; font-size:14px; margin:0; background:linear-gradient(135deg, #dc2626, #b91c1c);";
            remove.onclick = function(e) {
                e.preventDefault();
                selectedFiles.splice(index, 1);
                renderPreview();
            };

            wrapper.appendChild(img);
            wrapper.appendChild(info);
            wrapper.appendChild(up);
            wrapper.appendChild(down);
            wrapper.appendChild(remove);
            preview.appendChild(wrapper);
        };

        reader.readAsDataURL(file);
    });
}
</script>




</body>
</html>

