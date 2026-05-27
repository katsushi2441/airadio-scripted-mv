<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_common.php';
date_default_timezone_set('Asia/Tokyo');

$THIS_FILE = 'lyrics-extractor.php';
$API = rtrim(AIRADIO_LYRICS_API, '/');
$auth = url2ai_auth_bootstrap();
$logged_in = $auth['logged_in'];
$session_user = $auth['session_user'];
$is_admin = $auth['is_admin'];

if (isset($_GET['login'])) {
    header('Location: ' . url2ai_auth_login_url('https://airadio-scripted-mv.exbridge.jp/' . $THIS_FILE));
    exit;
}
if (isset($_GET['logout'])) {
    header('Location: ' . url2ai_auth_logout_url('https://airadio-scripted-mv.exbridge.jp/' . $THIS_FILE));
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function api_json(string $method, string $path, $payload = null, int $timeout = 20): array {
    global $API;
    $ch = curl_init($API . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false || $err) { return array('ok'=>false, 'error'=>$err ?: 'API request failed', 'status'=>0); }
    $json = json_decode($body, true);
    if (!is_array($json)) { $json = array('raw'=>$body); }
    $json['_http_status'] = $status;
    return $json;
}

function api_upload(array $file, string $model, string $language): array {
    global $API;
    $ch = curl_init($API . '/extract');
    $post = array(
        'audio' => curl_file_create($file['tmp_name'], $file['type'] ?: 'application/octet-stream', $file['name']),
        'model' => $model,
        'language' => $language,
    );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false || $err) { return array('ok'=>false, 'error'=>$err ?: 'upload failed', 'status'=>0); }
    $json = json_decode($body, true);
    if (!is_array($json)) { $json = array('raw'=>$body); }
    $json['_http_status'] = $status;
    return $json;
}

$proxy = $_GET['proxy'] ?? '';
if ($proxy !== '') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$is_admin) {
        http_response_code(403);
        echo json_encode(array('ok'=>false, 'error'=>'admin login required'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($proxy === 'extract' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(array('ok'=>false, 'error'=>'audio upload required'), JSON_UNESCAPED_UNICODE);
            exit;
        }
        $model = $_POST['model'] ?? 'small';
        $language = trim((string)($_POST['language'] ?? 'ja'));
        echo json_encode(api_upload($_FILES['audio'], $model, $language), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($proxy === 'status' && isset($_GET['job_id'])) {
        $jid = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['job_id']);
        echo json_encode(api_json('GET', '/status/' . $jid, null, 15), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($proxy === 'jobs') {
        echo json_encode(api_json('GET', '/jobs?limit=20', null, 15), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($proxy === 'health') {
        echo json_encode(api_json('GET', '/health', null, 8), JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(array('ok'=>false, 'error'=>'unknown proxy'), JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['file'], $_GET['job_id'])) {
    if (!$is_admin) { http_response_code(403); echo 'admin login required'; exit; }
    $jid = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['job_id']);
    $file = basename((string)$_GET['file']);
    $allowed = array('lyrics_mv.mp4', 'vocals.wav', 'lyrics.srt', 'lyrics.lrc', 'lyrics.txt', 'metadata.json');
    if (!in_array($file, $allowed, true)) { http_response_code(404); exit; }
    $url = $API . '/file/' . rawurlencode($jid) . '/' . rawurlencode($file);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    $data = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream';
    curl_close($ch);
    if ($code !== 200 || $data === false) { http_response_code(404); echo 'file not found'; exit; }
    header('Content-Type: ' . $ctype);
    header('Content-Disposition: attachment; filename="' . $file . '"');
    echo $data;
    exit;
}

$health = api_json('GET', '/health', null, 5);
$api_ok = !empty($health['ok']);
$recent_jobs = array();
if ($is_admin) {
    $jobs = api_json('GET', '/jobs?limit=20', null, 10);
    $recent_jobs = $jobs['jobs'] ?? array();
}
?><!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AIRadio Lyrics Extractor</title>
<style>
:root{--bg:#f4f7f7;--surface:#fff;--border:#dbe5e8;--border2:#c4d4d8;--accent:#007f96;--accent2:#35b99b;--text:#132329;--muted:#5a6a72;--red:#b2473f;--green:#2f8f46}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Noto Sans JP",sans-serif;font-size:14px}
header{position:sticky;top:0;z-index:10;background:rgba(255,255,255,.96);border-bottom:1px solid var(--border);padding:.85rem 1.4rem;display:flex;justify-content:space-between;align-items:center;box-shadow:0 1px 4px rgba(19,35,41,.06)}
.logo{font-weight:900;font-size:1.1rem;text-decoration:none;color:var(--text)}.logo span{color:var(--accent)}.sub{font-size:.72rem;color:var(--muted);margin-left:.4rem}
.userbar{display:flex;gap:.7rem;align-items:center;color:var(--muted);font-size:.82rem}.userbar strong{color:var(--green)}
.btn-sm{border:1px solid var(--border2);color:var(--muted);padding:.25rem .7rem;border-radius:6px;text-decoration:none;background:#fff}
.api{font-size:.72rem;border-radius:5px;padding:.22rem .55rem;font-weight:800}.api-ok{background:#e6f4e0;color:var(--green)}.api-ng{background:#fbeaea;color:var(--red)}
.container{max-width:980px;margin:0 auto;padding:1.4rem}.hero{text-align:center;padding:2rem 1rem 1.2rem}.hero h1{font-size:1.7rem;margin:.4rem 0;font-weight:900}.hero p{color:var(--muted);line-height:1.8}
.card{background:var(--surface);border:1px solid var(--border);border-radius:12px;margin-bottom:1rem;box-shadow:0 2px 8px rgba(19,35,41,.05);overflow:hidden}
.card-head{padding:.72rem 1.2rem;border-bottom:1px solid var(--border);background:#f8fbfb;color:var(--muted);font-size:.78rem;font-weight:900;letter-spacing:.04em;text-transform:uppercase;display:flex;gap:.5rem;align-items:center}.dot{width:7px;height:7px;border-radius:50%;background:var(--accent)}
.card-body{padding:1.2rem}.grid{display:grid;grid-template-columns:1fr 130px 110px auto;gap:.65rem;align-items:end}
label{display:block;font-size:.75rem;color:var(--muted);font-weight:800;margin-bottom:.35rem}
input,select,button{font:inherit}input[type=file],input[type=text],select{width:100%;border:1px solid var(--border2);border-radius:8px;padding:.62rem .8rem;background:#fff;color:var(--text)}
.btn{border:0;background:var(--accent);color:#fff;font-weight:900;border-radius:8px;padding:.68rem 1.2rem;cursor:pointer;white-space:nowrap}.btn:hover{background:#006578}.btn:disabled{opacity:.5;cursor:not-allowed}
.hint{font-size:.78rem;color:var(--muted);line-height:1.7;margin-top:.65rem}.progress{height:7px;background:var(--border);border-radius:99px;overflow:hidden;margin:.8rem 0}.fill{height:100%;background:linear-gradient(90deg,var(--accent),var(--accent2));width:0%;transition:.3s}
.badge{display:inline-flex;border-radius:5px;padding:.22rem .6rem;font-size:.72rem;font-weight:900}.badge-done{background:#e6f4e0;color:var(--green)}.badge-error{background:#fbeaea;color:var(--red)}.badge-queued,.badge-separating,.badge-transcribing{background:#e0f2f7;color:var(--accent)}
#status-box{display:none}.links a{display:inline-block;margin:.25rem .35rem .25rem 0;background:var(--accent);color:#fff;text-decoration:none;border-radius:8px;padding:.55rem .75rem;font-weight:900;font-size:.82rem}
pre{white-space:pre-wrap;max-height:260px;overflow:auto;background:#0b1018;color:#cfe1ff;border-radius:8px;padding:.9rem;font-size:.78rem}
.jobs{display:grid;gap:.5rem}.job{display:flex;align-items:center;gap:.7rem;padding:.65rem .8rem;border:1px solid var(--border);border-radius:8px;background:#f8fbfb;cursor:pointer}.job:hover{border-color:var(--accent)}.job-title{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.meta{color:var(--muted);font-size:.75rem}
@media(max-width:760px){.grid{grid-template-columns:1fr}.container{padding:1rem}header{padding:.8rem 1rem;align-items:flex-start;gap:.6rem;flex-direction:column}}
</style>
</head>
<body>
<header>
    <div><a class="logo" href="<?= h($THIS_FILE) ?>"><span>AIRadio</span> Lyrics MV</a><span class="sub">Demucs + Whisper + HyperFrames</span></div>
  <div class="userbar">
    <span class="api <?= $api_ok ? 'api-ok' : 'api-ng' ?>"><?= $api_ok ? 'API ●' : 'API ×' ?></span>
    <?php if ($logged_in): ?><span>@<strong><?= h($session_user) ?></strong></span><a class="btn-sm" href="?logout=1">logout</a><?php else: ?><a class="btn-sm" href="?login=1">Xでログイン</a><?php endif; ?>
  </div>
</header>
<main class="container">
<?php if (!$logged_in): ?>
  <section class="hero"><h1>曲ファイルから歌詞字幕付きMVを生成</h1><p>管理者ログイン後、MP3をアップロードして生成できます。</p></section>
  <section class="card"><div class="card-body" style="text-align:center"><a class="btn" href="?login=1">Xでログイン</a></div></section>
<?php elseif (!$is_admin): ?>
  <section class="card"><div class="card-body">管理者アカウント <strong>xb_bittensor</strong> でログインしてください。</div></section>
<?php else: ?>
  <section class="hero"><h1>歌詞字幕付きミュージックビデオ生成</h1><p>MP3をアップロードすると、歌詞抽出、脚本生成、画像12枚生成、HyperFrames動画生成まで非同期で実行します。</p></section>
  <section class="card">
    <div class="card-head"><span class="dot"></span> Upload</div>
    <div class="card-body">
      <form id="upload-form">
        <div class="grid">
          <div><label>音声ファイル</label><input type="file" name="audio" accept=".mp3,.wav,.m4a,.flac,.ogg,audio/*" required></div>
          <div><label>モデル</label><select name="model"><option value="small">small</option><option value="base">base</option><option value="medium">medium</option><option value="large-v3">large-v3</option><option value="tiny">tiny</option></select></div>
          <div><label>言語</label><input type="text" name="language" value="ja"></div>
          <button id="btn-submit" class="btn" type="submit">解析開始</button>
        </div>
        <div class="hint">生成物: lyrics_mv.mp4 / vocals.wav / lyrics.srt / lyrics.lrc / lyrics.txt</div>
      </form>
    </div>
  </section>

  <section class="card" id="status-box">
    <div class="card-head"><span class="dot"></span> Status</div>
    <div class="card-body">
      <div style="display:flex;align-items:center;gap:.6rem"><span id="badge" class="badge badge-queued">queued</span><strong id="filename"></strong></div>
      <div class="progress"><div id="fill" class="fill"></div></div>
      <div id="message" class="hint">待機中...</div>
      <div id="result-links" class="links" style="display:none"></div>
      <div id="error" style="display:none;color:var(--red);margin-top:.7rem"></div>
      <pre id="log" style="display:none"></pre>
    </div>
  </section>

  <?php if (count($recent_jobs) > 0): ?>
  <section class="card">
    <div class="card-head"><span class="dot"></span> Recent Jobs</div>
    <div class="card-body"><div class="jobs">
      <?php foreach ($recent_jobs as $job): ?>
      <div class="job" onclick="loadJob('<?= h($job['job_id']) ?>')">
        <span class="badge badge-<?= h($job['status'] ?: 'queued') ?>"><?= h($job['status'] ?: '?') ?></span>
        <span class="job-title"><?= h($job['filename'] ?: $job['job_id']) ?></span>
        <span class="meta"><?= h(substr($job['created_at'] ?: '', 5, 11)) ?></span>
      </div>
      <?php endforeach; ?>
    </div></div>
  </section>
  <?php endif; ?>
<?php endif; ?>
</main>
<script>
var PROXY = '<?= h($THIS_FILE) ?>';
var timer = null;
function startPoll(jobId){ if(timer) clearInterval(timer); poll(jobId); timer=setInterval(function(){poll(jobId)},2500); }
function stopPoll(){ if(timer){clearInterval(timer); timer=null;} }
function loadJob(jobId){ document.getElementById('status-box').style.display='block'; startPoll(jobId); }
var form=document.getElementById('upload-form');
if(form){
  form.addEventListener('submit',function(e){
    e.preventDefault();
    var btn=document.getElementById('btn-submit'); btn.disabled=true;
    fetch(PROXY+'?proxy=extract',{method:'POST',body:new FormData(form)})
      .then(r=>r.json()).then(function(d){
        btn.disabled=false;
        if(d.ok&&d.job_id){ document.getElementById('status-box').style.display='block'; startPoll(d.job_id); }
        else alert('登録失敗: '+(d.error||JSON.stringify(d)));
      }).catch(function(err){btn.disabled=false;alert(err.message)});
  });
}
function poll(jobId){
  fetch(PROXY+'?proxy=status&job_id='+encodeURIComponent(jobId)).then(r=>r.json()).then(updateUI).catch(console.error);
}
function updateUI(d){
  if(!d.ok){return}
  var status=d.status||'queued';
  document.getElementById('badge').textContent=status;
  document.getElementById('badge').className='badge badge-'+status;
  document.getElementById('filename').textContent=d.filename||d.job_id;
  document.getElementById('fill').style.width=(d.progress||0)+'%';
  document.getElementById('message').textContent=d.message||status;
  var log=document.getElementById('log');
  if(d.log){log.style.display='block';log.textContent=d.log}else{log.style.display='none'}
  var err=document.getElementById('error');
  if(status==='error'){stopPoll();err.style.display='block';err.textContent=d.error||'error';}
  var links=document.getElementById('result-links');
  if(status==='done'){
    stopPoll(); err.style.display='none'; links.style.display='block';
    var files=['lyrics_mv.mp4','vocals.wav','lyrics.srt','lyrics.lrc','lyrics.txt','metadata.json'];
    links.innerHTML=files.map(function(f){return '<a href="'+PROXY+'?job_id='+encodeURIComponent(d.job_id)+'&file='+encodeURIComponent(f)+'">'+f+'</a>'}).join('');
  } else { links.style.display='none'; }
}
</script>
</body>
</html>
