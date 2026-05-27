<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Tokyo');

$THIS_FILE = 'scripted-mvv.php';
$SITE_NAME = 'Kurageプロジェクト AIRadio Scripted-MV';
$BASE_URL = 'https://airadio-scripted-mv.exbridge.jp';
$DATA_FILE = __DIR__ . '/data/scripted_mv_videos.json';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function load_videos(string $path): array {
    if (!is_file($path)) { return array(); }
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : array();
}

$videos = load_videos($DATA_FILE);
$detail_id = isset($_GET['id']) ? preg_replace('/[^a-zA-Z0-9]/', '', (string)$_GET['id']) : '';
$detail = null;
foreach ($videos as $v) {
    if (($v['job_id'] ?? '') === $detail_id) { $detail = $v; break; }
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
.container{max-width:720px;margin:0 auto;padding:0 0 80px}.count{padding:12px 18px;color:#6b7a82;border-bottom:1px solid #f0f3f4}
.card{display:flex;gap:14px;padding:18px;border-bottom:1px solid #f0f3f4}.thumb{width:96px;height:170px;border-radius:10px;background:#000;overflow:hidden;flex-shrink:0}.thumb video{width:100%;height:100%;object-fit:cover}
.body{min-width:0;flex:1}.title{font-weight:900;font-size:16px;margin-bottom:6px}.meta{color:#7b8990;font-size:12px;margin-bottom:10px}.actions{display:flex;gap:7px;flex-wrap:wrap}.btn{display:inline-flex;align-items:center;justify-content:center;border:1px solid #d6e3e8;border-radius:8px;padding:7px 11px;text-decoration:none;color:#46545a;font-size:12px;font-weight:800}.btn.primary{background:#e0f5f8;border-color:#b7e0e8;color:#007f96}
.empty{text-align:center;color:#9aa6ab;padding:70px 20px}.detail{padding:18px}.video{max-width:390px;margin:0 auto 18px;background:#000;border-radius:14px;overflow:hidden}.video video{width:100%;display:block}.lyrics{white-space:pre-wrap;background:#f6fafb;border:1px solid #dce8ec;border-radius:10px;padding:14px;line-height:1.75;color:#34444b}
.reel-overlay{display:none;position:fixed;inset:0;z-index:500;background:#000}.reel-overlay.open{display:flex;align-items:stretch;justify-content:center}.reel-close{position:fixed;top:14px;right:14px;z-index:600;background:rgba(0,0,0,.6);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:20px;padding:7px 16px;font-size:13px;cursor:pointer}.reel-feed{width:100%;max-width:420px;height:100dvh;overflow-y:scroll;scroll-snap-type:y mandatory;-webkit-overflow-scrolling:touch;scrollbar-width:none}.reel-feed::-webkit-scrollbar{display:none}.reel-slide{position:relative;height:100dvh;scroll-snap-align:start;background:#000;display:flex;align-items:center;justify-content:center;overflow:hidden}.reel-slide video{width:100%;height:100%;object-fit:contain;display:block}.reel-grad{position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,.2) 0%,transparent 30%,transparent 55%,rgba(0,0,0,.75) 100%);pointer-events:none}.reel-info{position:absolute;bottom:0;left:0;right:60px;padding:12px 14px calc(env(safe-area-inset-bottom,0px) + 44px)}.reel-title{font-size:14px;font-weight:800;color:#fff;margin-bottom:4px;line-height:1.4}.reel-meta{font-size:12px;color:rgba(255,255,255,.65)}.reel-side{position:absolute;right:8px;bottom:calc(env(safe-area-inset-bottom,0px) + 48px);display:flex;flex-direction:column;gap:12px;align-items:center}.reel-side-btn{background:rgba(0,0,0,.5);border:none;border-radius:50%;width:44px;height:44px;color:#fff;font-size:17px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:1px;text-decoration:none}.reel-side-btn span{font-size:9px;color:rgba(255,255,255,.65)}
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
  </div>
</header>

<?php if ($detail): ?>
<main class="container">
  <div class="detail">
    <div class="video"><video src="<?= h($detail['video_url'] ?? '') ?>" controls playsinline preload="metadata"></video></div>
    <h2 class="title"><?= h($detail['title'] ?? '') ?></h2>
    <div class="meta"><?= h($detail['filename'] ?? '') ?> / <?= h($detail['updated_at'] ?? '') ?><?php if (!empty($detail['scene_count'])): ?> / 画像<?= h($detail['scene_count']) ?>枚<?php endif; ?></div>
    <div class="actions">
      <a class="btn primary" href="<?= h($THIS_FILE) ?>">一覧</a>
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
  <div class="count"><?= count($videos) ?> videos</div>
  <?php if (!$videos): ?>
    <div class="empty">公開済み動画はまだありません。</div>
  <?php endif; ?>
  <?php foreach ($videos as $v): ?>
    <article class="card">
      <a class="thumb" href="<?= h($THIS_FILE . '?id=' . urlencode($v['job_id'] ?? '')) ?>">
        <video src="<?= h($v['video_url'] ?? '') ?>" muted preload="metadata" playsinline></video>
      </a>
      <div class="body">
        <div class="title"><?= h($v['title'] ?? '') ?></div>
        <div class="meta"><?= h($v['filename'] ?? '') ?><br><?= h($v['updated_at'] ?? $v['created_at'] ?? '') ?><?php if (!empty($v['duration_sec'])): ?> / <?= h((string)round((float)$v['duration_sec'])) ?>秒<?php endif; ?><?php if (!empty($v['scene_count'])): ?> / 画像<?= h($v['scene_count']) ?>枚<?php endif; ?></div>
        <div class="actions">
          <a class="btn primary" href="<?= h($THIS_FILE . '?id=' . urlencode($v['job_id'] ?? '')) ?>">再生</a>
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
        <button class="reel-side-btn reel-mute-btn" type="button" onclick="reelMuteToggle()">音<span>音声</span></button>
        <a class="reel-side-btn" href="<?= h($THIS_FILE . '?id=' . urlencode($v['job_id'] ?? '')) ?>">詳<span>詳細</span></a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<script>
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
