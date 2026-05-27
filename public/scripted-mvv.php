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
.badge{background:#007f96;color:#fff;border-radius:999px;padding:3px 9px;font-size:11px;font-weight:800}.right{margin-left:auto;display:flex;gap:8px}.link{border:1px solid #007f96;color:#007f96;border-radius:7px;padding:6px 10px;text-decoration:none;font-size:12px;font-weight:800}
.container{max-width:720px;margin:0 auto;padding:0 0 80px}.count{padding:12px 18px;color:#6b7a82;border-bottom:1px solid #f0f3f4}
.card{display:flex;gap:14px;padding:18px;border-bottom:1px solid #f0f3f4}.thumb{width:96px;height:170px;border-radius:10px;background:#000;overflow:hidden;flex-shrink:0}.thumb video{width:100%;height:100%;object-fit:cover}
.body{min-width:0;flex:1}.title{font-weight:900;font-size:16px;margin-bottom:6px}.meta{color:#7b8990;font-size:12px;margin-bottom:10px}.actions{display:flex;gap:7px;flex-wrap:wrap}.btn{display:inline-flex;align-items:center;justify-content:center;border:1px solid #d6e3e8;border-radius:8px;padding:7px 11px;text-decoration:none;color:#46545a;font-size:12px;font-weight:800}.btn.primary{background:#e0f5f8;border-color:#b7e0e8;color:#007f96}
.empty{text-align:center;color:#9aa6ab;padding:70px 20px}.detail{padding:18px}.video{max-width:390px;margin:0 auto 18px;background:#000;border-radius:14px;overflow:hidden}.video video{width:100%;display:block}.lyrics{white-space:pre-wrap;background:#f6fafb;border:1px solid #dce8ec;border-radius:10px;padding:14px;line-height:1.75;color:#34444b}
@media(max-width:560px){.card{padding:14px}.thumb{width:82px;height:146px}.right{display:none}}
</style>
</head>
<body>
<header class="header">
  <img src="assets/kurage-icon.png" alt="Kurage">
  <h1><a href="<?= h($THIS_FILE) ?>">AIRadio Scripted-MV</a></h1>
  <span class="badge">Lyrics MV</span>
  <div class="right"><a class="link" href="scripted-mv.php">生成</a></div>
</header>

<?php if ($detail): ?>
<main class="container">
  <div class="detail">
    <div class="video"><video src="<?= h($detail['video_url'] ?? '') ?>" controls playsinline preload="metadata"></video></div>
    <h2 class="title"><?= h($detail['title'] ?? '') ?></h2>
    <div class="meta"><?= h($detail['filename'] ?? '') ?> / <?= h($detail['updated_at'] ?? '') ?><?php if (!empty($detail['scene_count'])): ?> / 画像<?= h($detail['scene_count']) ?>枚<?php endif; ?></div>
    <div class="actions">
      <a class="btn primary" href="<?= h($detail['video_url'] ?? '') ?>" download>MP4</a>
      <a class="btn" href="<?= h($detail['lrc_url'] ?? '') ?>">LRC</a>
      <a class="btn" href="<?= h($THIS_FILE) ?>">一覧</a>
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
</body>
</html>
