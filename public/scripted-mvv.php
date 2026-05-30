<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_common.php';
date_default_timezone_set('Asia/Tokyo');

$THIS_FILE = 'scripted-mvv.php';
$SITE_NAME = 'Kurageプロジェクト AIRadio Scripted-MV';
$BASE_URL = 'https://airadio-scripted-mv.exbridge.jp';
$DATA_FILE = __DIR__ . '/data/scripted_mv_videos.json';
$ADMIN = 'xb_bittensor';

$auth = url2ai_auth_bootstrap();
$logged_in   = $auth['logged_in'];
$session_user = $auth['session_user'];
$is_admin    = $auth['is_admin'];

if (isset($_GET['login'])) {
    header('Location: ' . url2ai_auth_login_url($BASE_URL . '/' . $THIS_FILE)); exit;
}
if (isset($_GET['logout'])) {
    header('Location: ' . url2ai_auth_logout_url($BASE_URL . '/' . $THIS_FILE)); exit;
}
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function copy_text(array $v): string {
    $title = trim((string)($v['title'] ?? ''));
    $url = video_url($v);
    return $title !== '' ? $title . "\n\n" . $url : $url;
}

function load_videos(string $path): array {
    if (!is_file($path)) { return array(); }
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : array();
}

function save_videos(string $path, array $videos): void {
    $dir = dirname($path);
    if (!is_dir($dir)) { mkdir($dir, 0775, true); }
    $tmp = $path . '.tmp';
    file_put_contents($tmp, json_encode($videos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    rename($tmp, $path);
}

function video_url(array $v): string {
    global $BASE_URL, $THIS_FILE;
    return $BASE_URL . '/' . $THIS_FILE . '?id=' . urlencode((string)($v['job_id'] ?? ''));
}

// 削除処理（管理者のみ）
if (isset($_POST['delete_job']) && $is_admin) {
    $del_id = preg_replace('/[^a-zA-Z0-9]/', '', (string)$_POST['delete_job']);
    $all = load_videos($DATA_FILE);
    $all = array_values(array_filter($all, fn($v) => ($v['job_id'] ?? '') !== $del_id));
    save_videos($DATA_FILE, $all);
    header('Location: ' . $THIS_FILE); exit;
}

$videos = load_videos($DATA_FILE);
$changed = false;
foreach ($videos as &$v) {
    if (!isset($v['views']) || !is_numeric($v['views'])) {
        $v['views'] = 9999;
        $changed = true;
    }
}
unset($v);
$detail_id = isset($_GET['id']) ? preg_replace('/[^a-zA-Z0-9]/', '', (string)$_GET['id']) : '';
$detail = null;
foreach ($videos as $i => $v) {
    if (($v['job_id'] ?? '') === $detail_id) {
        $videos[$i]['views'] = (int)($videos[$i]['views'] ?? 9999) + 1;
        $detail = $videos[$i];
        $changed = true;
        break;
    }
}
if ($changed) {
    save_videos($DATA_FILE, $videos);
}

$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'created';
if (!in_array($sort, array('created', 'views'), true)) { $sort = 'created'; }
if (!$detail) {
    usort($videos, function($a, $b) use ($sort) {
        if ($sort === 'views') {
            $av = (int)($a['views'] ?? 9999);
            $bv = (int)($b['views'] ?? 9999);
            if ($bv !== $av) { return $bv <=> $av; }
        }
        $ad = (string)($a['created_at'] ?? $a['updated_at'] ?? '');
        $bd = (string)($b['created_at'] ?? $b['updated_at'] ?? '');
        return strcmp($bd, $ad);
    });
}

if ($detail) {
    $page_title = ($detail['title'] ?? 'Scripted-MV') . ' | ' . $SITE_NAME;
    $page_desc = '歌詞字幕付きミュージックビデオ: ' . ($detail['filename'] ?? '');
    $page_url = $BASE_URL . '/' . $THIS_FILE . '?id=' . urlencode($detail_id);
    $og_video = $detail['video_url'] ?? '';
} else {
    $page_title = $SITE_NAME . ' | 歌詞字幕付きミュージックビデオ一覧';
    $page_desc = 'MP3から歌詞を抽出し、AIで映像脚本と画像を生成した歌詞字幕付きミュージックビデオ一覧。';
    $page_url = $BASE_URL . '/' . $THIS_FILE;
    $og_video = '';
}
?><!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($page_title) ?></title>
<meta name="description" content="<?= h($page_desc) ?>">
<link rel="canonical" href="<?= h($page_url) ?>">
<meta property="og:type" content="<?= $detail ? 'video.other' : 'website' ?>">
<meta property="og:title" content="<?= h($page_title) ?>">
<meta property="og:description" content="<?= h($page_desc) ?>">
<meta property="og:url" content="<?= h($page_url) ?>">
<?php if ($og_video): ?><meta property="og:video" content="<?= h($og_video) ?>"><?php endif; ?>
<style>
*{box-sizing:border-box}body{margin:0;background:#fff;color:#132329;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Noto Sans JP",sans-serif;font-size:14px}
.header{position:sticky;top:0;background:#fff;border-bottom:1px solid #e5edf0;z-index:10;padding:14px 18px;display:flex;align-items:center;gap:10px}
.header img{width:38px;height:38px;border-radius:50%;object-fit:cover}.header h1{font-size:16px;margin:0;font-weight:900}.header a{text-decoration:none;color:inherit}
.badge{background:#007f96;color:#fff;border-radius:999px;padding:3px 9px;font-size:11px;font-weight:800}.right{margin-left:auto;display:flex;gap:8px;align-items:center}.link,.reel-btn{border:1px solid #007f96;color:#007f96;border-radius:7px;padding:6px 10px;text-decoration:none;font-size:12px;font-weight:800;background:#fff;cursor:pointer;font-family:inherit}.reel-btn{background:#007f96;color:#fff}.link:hover{background:#e0f5f8}.reel-btn:hover{background:#006880}
.container{max-width:720px;margin:0 auto;padding:0 0 80px}.count{padding:12px 18px;color:#6b7a82;border-bottom:1px solid #f0f3f4;display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap}.sorts{display:flex;gap:6px}.sort{border:1px solid #d6e3e8;border-radius:999px;padding:5px 10px;color:#53636b;text-decoration:none;font-size:12px;font-weight:800}.sort.active{background:#007f96;border-color:#007f96;color:#fff}
.card{display:flex;gap:14px;padding:18px;border-bottom:1px solid #f0f3f4}.thumb{width:96px;height:170px;border-radius:10px;background:#000;overflow:hidden;flex-shrink:0}.thumb video{width:100%;height:100%;object-fit:cover}
.body{min-width:0;flex:1}.title{font-weight:900;font-size:16px;margin-bottom:6px}.meta{color:#7b8990;font-size:12px;margin-bottom:10px}.views{display:inline-flex;align-items:center;gap:4px;color:#007f96;font-weight:900}.actions{display:flex;gap:7px;flex-wrap:wrap}.btn{display:inline-flex;align-items:center;justify-content:center;border:1px solid #d6e3e8;border-radius:8px;padding:7px 11px;text-decoration:none;color:#46545a;font-size:12px;font-weight:800;background:#fff;cursor:pointer;font-family:inherit}.btn.primary{background:#e0f5f8;border-color:#b7e0e8;color:#007f96}.btn.x{background:#111827;border-color:#111827;color:#fff}
.empty{text-align:center;color:#9aa6ab;padding:70px 20px}.detail{padding:18px}.video{max-width:390px;margin:0 auto 18px;background:#000;border-radius:14px;overflow:hidden}.video video{width:100%;display:block}.lyrics{white-space:pre-wrap;background:#f6fafb;border:1px solid #dce8ec;border-radius:10px;padding:14px;line-height:1.75;color:#34444b}
.reel-overlay{display:none;position:fixed;inset:0;z-index:500;background:#000}.reel-overlay.open{display:flex;align-items:stretch;justify-content:center}.reel-close{position:fixed;top:14px;right:14px;z-index:600;background:rgba(0,0,0,.6);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:20px;padding:7px 16px;font-size:13px;cursor:pointer}.reel-feed{width:100%;max-width:420px;height:100dvh;overflow-y:scroll;scroll-snap-type:y mandatory;-webkit-overflow-scrolling:touch;scrollbar-width:none}.reel-feed::-webkit-scrollbar{display:none}.reel-slide{position:relative;height:100dvh;scroll-snap-align:start;background:#000;display:flex;align-items:center;justify-content:center;overflow:hidden}.reel-slide video{width:100%;height:100%;object-fit:contain;display:block}.reel-grad{position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,.2) 0%,transparent 30%,transparent 55%,rgba(0,0,0,.75) 100%);pointer-events:none}.reel-info{position:absolute;bottom:0;left:0;right:60px;padding:12px 14px calc(env(safe-area-inset-bottom,0px) + 44px)}.reel-title{font-size:14px;font-weight:800;color:#fff;margin-bottom:4px;line-height:1.4}.reel-meta{font-size:12px;color:rgba(255,255,255,.65)}.reel-side{position:absolute;right:8px;bottom:calc(env(safe-area-inset-bottom,0px) + 48px);display:flex;flex-direction:column;gap:12px;align-items:center}.reel-side-btn{background:rgba(0,0,0,.5);border:none;border-radius:50%;width:44px;height:44px;color:#fff;font-size:17px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:1px;text-decoration:none}.reel-side-btn span{font-size:9px;color:rgba(255,255,255,.65)}
.btn.danger{background:#fff0f0;border-color:#fca5a5;color:#dc2626}.btn.danger:hover{background:#fee2e2}
.userbar{display:flex;gap:.7rem;align-items:center;color:#6b7a82;font-size:.82rem}
@media(max-width:560px){.card{padding:14px}.thumb{width:82px;height:146px}.header{padding:12px}.header h1{font-size:14px}.badge{display:none}.link,.reel-btn{padding:5px 8px}}
</style>
</head>
<body>
<header class="header">
  <img src="assets/kurage-icon.png" alt="Kurage">
  <h1><a href="<?= h($THIS_FILE) ?>">AIRadio Scripted-MV</a></h1>
  <span class="badge">Lyrics MV</span>
  <div class="right">
    <?php if ($detail): ?><a class="link" href="<?= h($THIS_FILE) ?>">一覧</a><?php endif; ?>
    <?php if (!$detail && $videos): ?><button class="reel-btn" type="button" onclick="openReel(0)">リール表示</button><?php endif; ?>
    <a class="link" href="scripted-mv.php">生成</a>
    <?php if ($logged_in): ?>
      <span class="userbar">@<?= h($session_user) ?> <a class="link" href="?logout=1">logout</a></span>
    <?php else: ?>
      <a class="link" href="?login=1">Xでログイン</a>
    <?php endif; ?>
  </div>
</header>

<?php if ($detail): ?>
<main class="container">
  <div class="detail">
    <div class="video"><video src="<?= h($detail['video_url'] ?? '') ?>" controls playsinline preload="metadata"></video></div>
    <h2 class="title"><?= h($detail['title'] ?? '') ?></h2>
    <div class="meta"><?= h($detail['filename'] ?? '') ?> / <?= h($detail['updated_at'] ?? '') ?> / <span class="views">表示<?= h((string)($detail['views'] ?? 9999)) ?></span><?php if (!empty($detail['scene_count'])): ?> / 画像<?= h($detail['scene_count']) ?>枚<?php endif; ?></div>
    <div class="actions">
      <a class="btn primary" href="<?= h($THIS_FILE) ?>">一覧</a>
      <button class="btn copy-btn" type="button" data-copy-text="<?= h(copy_text($detail)) ?>">📋 コピー</button>
      <a class="btn x" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=<?= rawurlencode(($detail['title'] ?? 'AIRadio Scripted-MV') . "\n\n" . video_url($detail)) ?>">𝕏 投稿</a>
      <?php if ($is_admin): ?>
      <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
        <input type="hidden" name="delete_job" value="<?= h($detail_id) ?>">
        <button class="btn danger" type="submit">🗑 削除</button>
      </form>
      <?php endif; ?>
    </div>
    <?php
      $lyrics = '';
      if (!empty($detail['txt_url'])) {
          $local_txt = __DIR__ . '/videos/' . $detail_id . '/lyrics.txt';
          if (is_file($local_txt)) { $lyrics = (string)file_get_contents($local_txt); }
      }
    ?>
    <?php if ($lyrics): ?><h3>歌詞</h3><div class="lyrics"><?= h($lyrics) ?></div><?php endif; ?>
  </div>
</main>
<?php else: ?>
<main class="container">
  <div class="count">
    <span><?= count($videos) ?> videos</span>
    <span class="sorts">
      <a class="sort <?= $sort === 'created' ? 'active' : '' ?>" href="<?= h($THIS_FILE . '?sort=created') ?>">作成日順</a>
      <a class="sort <?= $sort === 'views' ? 'active' : '' ?>" href="<?= h($THIS_FILE . '?sort=views') ?>">表示回数順</a>
    </span>
  </div>
  <?php if (!$videos): ?>
    <div class="empty">公開済み動画はまだありません。</div>
  <?php endif; ?>
  <?php foreach ($videos as $ri => $v): ?>
    <article class="card">
      <a class="thumb" href="<?= h($THIS_FILE . '?id=' . urlencode($v['job_id'] ?? '')) ?>">
        <video class="thumb-video" src="<?= h($v['video_url'] ?? '') ?>" muted preload="metadata" playsinline></video>
      </a>
      <div class="body">
        <div class="title"><?= h($v['title'] ?? '') ?></div>
        <div class="meta"><?= h($v['filename'] ?? '') ?><br><?= h($v['updated_at'] ?? $v['created_at'] ?? '') ?> / <span class="views">表示<?= h((string)($v['views'] ?? 9999)) ?></span><?php if (!empty($v['duration_sec'])): ?> / <?= h((string)round((float)$v['duration_sec'])) ?>秒<?php endif; ?><?php if (!empty($v['scene_count'])): ?> / 画像<?= h($v['scene_count']) ?>枚<?php endif; ?></div>
        <div class="actions">
          <a class="btn primary" href="<?= h($THIS_FILE . '?id=' . urlencode($v['job_id'] ?? '')) ?>">📄 詳細</a>
          <button class="btn copy-btn" type="button" data-copy-text="<?= h(copy_text($v)) ?>">📋 コピー</button>
          <a class="btn x" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=<?= rawurlencode(($v['title'] ?? 'AIRadio Scripted-MV') . "\n\n" . video_url($v)) ?>">𝕏 投稿</a>
          <button class="btn reel-open-btn" type="button" data-idx="<?= $ri ?>">🎬 リール</button>
          <?php if ($is_admin): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
            <input type="hidden" name="delete_job" value="<?= h($v['job_id'] ?? '') ?>">
            <button class="btn danger" type="submit">🗑</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </article>
  <?php endforeach; ?>
</main>
<?php endif; ?>

<?php if (!$detail && $videos): ?>
<div class="reel-overlay" id="reel-overlay">
  <button class="reel-close" type="button" onclick="closeReel()">一覧に戻る</button>
  <div class="reel-feed" id="reel-feed">
    <?php foreach ($videos as $ri => $v): ?>
    <div class="reel-slide" data-job="<?= h($v['job_id'] ?? '') ?>">
      <video src="<?= h($v['video_url'] ?? '') ?>" playsinline muted loop preload="<?= $ri === 0 ? 'metadata' : 'none' ?>"></video>
      <div class="reel-grad"></div>
      <div class="reel-info">
        <div class="reel-title"><?= h($v['title'] ?? '') ?></div>
        <div class="reel-meta"><?= h($v['filename'] ?? '') ?></div>
      </div>
      <div class="reel-side">
        <button class="reel-side-btn reel-mute-btn" type="button" onclick="reelMuteToggle()">🔇<span>音声</span></button>
        <button class="reel-side-btn reel-copy-btn" type="button" data-copy-text="<?= h(copy_text($v)) ?>">📋<span>コピー</span></button>
        <a class="reel-side-btn" href="https://twitter.com/intent/tweet?text=<?= rawurlencode(($v['title'] ?? '') . "\n\n" . video_url($v)) ?>" target="_blank" rel="noopener">𝕏<span>投稿</span></a>
        <a class="reel-side-btn" href="<?= h($THIS_FILE . '?id=' . urlencode($v['job_id'] ?? '')) ?>">📄<span>詳細</span></a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<script>
function primeThumbVideos(root){
  (root||document).querySelectorAll('video.thumb-video').forEach(function(v){
    if(v.dataset.thumbReady)return;
    v.dataset.thumbReady='1';
    v.muted=true;
    v.addEventListener('loadedmetadata',function(){
      try{
        var t=Math.min(1, Math.max(0, (v.duration||2)-0.1));
        if(isFinite(t)) v.currentTime=t;
      }catch(e){}
    },{once:true});
    v.addEventListener('seeked',function(){ v.pause(); },{once:true});
  });
}
primeThumbVideos(document);
function copyText(text, onDone){
  if(navigator.clipboard&&window.isSecureContext){
    navigator.clipboard.writeText(text).then(function(){
      showCopyDone();
      if(typeof onDone === 'function') onDone();
    }).catch(function(){ fallbackCopy(text); });
  }else{ fallbackCopy(text); }
}
function showCopyDone(){
  var el=document.createElement('div');
  el.textContent='✓ コピーしました';
  el.style='position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#222;color:#fff;padding:10px 20px;border-radius:8px;z-index:9999;font-size:13px;pointer-events:none';
  document.body.appendChild(el);
  setTimeout(function(){ el.remove(); }, 2000);
}
function fallbackCopy(text){
  var ta=document.createElement('textarea');
  ta.value=text;ta.style.position='fixed';ta.style.left='-9999px';
  document.body.appendChild(ta);ta.focus();ta.select();
  try{document.execCommand('copy');showCopyDone();}catch(e){prompt('コピーしてください',text);}
  document.body.removeChild(ta);
}
// リールのコピー・リールオープンボタン委任
document.addEventListener('click', function(e){
  var btn = e.target.closest('.copy-btn');
  if(btn){ copyText(btn.dataset.copyText || ''); return; }
  var copyBtn = e.target.closest('.reel-copy-btn');
  if(copyBtn){
    copyText(copyBtn.dataset.copyText || '', function(){
      copyBtn.innerHTML = '✓<span>コピー済</span>';
      setTimeout(function(){ copyBtn.innerHTML = '📋<span>コピー</span>'; }, 2000);
    });
    return;
  }
  var reelBtn = e.target.closest('.reel-open-btn');
  if(reelBtn){ openReel(parseInt(reelBtn.dataset.idx||'0',10)); }
});
var reelMuted=true,reelCurrent=0,reelSlides=[],reelObs=null,reelReady=false;
function openReel(idx){
  var overlay=document.getElementById('reel-overlay');
  if(!overlay)return;
  overlay.classList.add('open');
  document.body.style.overflow='hidden';
  if(!reelReady){
    reelReady=true;
    reelSlides=Array.from(overlay.querySelectorAll('.reel-slide'));
    var feed=document.getElementById('reel-feed');
    reelObs=new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        var vid=entry.target.querySelector('video');
        if(!vid)return;
        if(entry.isIntersecting){
          reelCurrent=reelSlides.indexOf(entry.target);
          vid.muted=reelMuted;
          vid.play().catch(function(){vid.muted=true;reelMuted=true;vid.play().catch(function(){});});
        }else{
          vid.pause();
          vid.currentTime=0;
        }
      });
    },{root:feed,threshold:.75});
    reelSlides.forEach(function(s){reelObs.observe(s);});
  }
  if(idx>=0&&idx<reelSlides.length){
    setTimeout(function(){reelSlides[idx].scrollIntoView({behavior:'instant',block:'start'});},50);
  }
}
function closeReel(){
  var overlay=document.getElementById('reel-overlay');
  if(!overlay)return;
  overlay.classList.remove('open');
  document.body.style.overflow='';
  overlay.querySelectorAll('video').forEach(function(v){v.pause();v.currentTime=0;});
}
function reelMuteToggle(){
  reelMuted=!reelMuted;
  document.querySelectorAll('#reel-overlay video').forEach(function(v){v.muted=reelMuted;});
  document.querySelectorAll('.reel-mute-btn').forEach(function(btn){btn.innerHTML=(reelMuted?'音':'音')+'<span>音声</span>';});
}
document.addEventListener('keydown',function(e){
  var overlay=document.getElementById('reel-overlay');
  if(!overlay||!overlay.classList.contains('open'))return;
  if(e.key==='Escape'){closeReel();return;}
  if(e.key==='ArrowDown'&&reelSlides[reelCurrent+1])reelSlides[reelCurrent+1].scrollIntoView({behavior:'smooth',block:'start'});
  if(e.key==='ArrowUp'&&reelSlides[reelCurrent-1])reelSlides[reelCurrent-1].scrollIntoView({behavior:'smooth',block:'start'});
});
</script>
</body>
</html>
