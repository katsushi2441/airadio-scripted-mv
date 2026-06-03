# YouTube Upload Workflow

Kurage / AIRadio Scripted-MVで生成した動画を、Codex/ClaudeからYouTubeへ投稿する手順。

## 目的

- YouTube Data APIを使い、Pythonから動画を投稿する。
- 初回だけOAuth認証し、以後は `storage/youtube/token.json` のrefresh tokenで自動更新する。
- 生成動画をYouTubeに投稿し、必要に応じてAIxSNSにも告知する。

## 重要な保存場所

```text
/home/kojima/exdirect/airadio-scripted-mv/
├── tools/youtube/youtube_auth.py          # ローカルブラウザ認証用
├── tools/youtube/youtube_auth_paste.py    # localhost URL貼り付け認証用
├── tools/youtube/upload_youtube.py        # YouTube投稿用
├── tools/youtube/set_thumbnail.py         # YouTubeサムネ設定用
├── tools/youtube/requirements.txt
└── storage/youtube/
    ├── oauth_client.json                  # OAuth client_id/client_secret
    ├── token.json                         # refresh token入り。Git管理禁止
    └── *_response.json                    # 投稿結果
```

`storage/youtube/`、`client_secret*.json`、`token.json` は `.gitignore` 済み。Gitに入れない。

## 初回セットアップ

```bash
cd /home/kojima/exdirect/airadio-scripted-mv
python3 -m pip install --user -r tools/youtube/requirements.txt
mkdir -p storage/youtube
```

Google Cloud ConsoleでOAuthクライアントを用意し、以下のどちらかを置く。

```text
storage/youtube/client_secret.json
storage/youtube/oauth_client.json
```

`oauth_client.json` の最小形式:

```json
{
  "installed": {
    "client_id": "xxxxx.apps.googleusercontent.com",
    "client_secret": "GOCSPX-xxxxx"
  }
}
```

## Google側で必要な設定

OAuthアプリがテスト中の場合、YouTube投稿に使うGoogleアカウントをテストユーザーに追加する。

```text
Google Cloud Console
→ Google Auth Platform
→ 対象 / Audience
→ テストユーザー / Test users
→ メールアドレスを追加
```

OAuthアプリを本番運用するなら、後で公開状態にする。テスト状態だとrefresh tokenが短命になる場合がある。

## token.jsonを作る

サーバ上のCodex/Claudeではブラウザを直接開けないことが多いので、通常は貼り付け方式を使う。

```bash
cd /home/kojima/exdirect/airadio-scripted-mv
python3 tools/youtube/youtube_auth_paste.py
```

表示されたGoogle認証URLをユーザーに開いてもらう。

認証後、ブラウザは以下のようなURLに遷移する。

```text
http://localhost:8089/?state=...&code=...&scope=https://www.googleapis.com/auth/youtube.upload
```

このURL全体を `redirect URL>` に貼り付ける。

成功すると:

```text
storage/youtube/token.json
```

が作られる。

## 投稿コマンド

基本形:

```bash
python3 tools/youtube/upload_youtube.py /path/to/video.mp4 \
  --title "動画タイトル" \
  --description "説明文" \
  --tags "AI,Kurage,Horizon" \
  --privacy public \
  --json-out storage/youtube/upload_response.json
```

`--privacy` は `private` / `unlisted` / `public`。

テスト投稿は `unlisted` 推奨。本番告知する動画は `public`。

`upload_youtube.py` は、投稿タイトルへ自動で以下のsuffixを付与する。

```text
 - Ernie Kurage Wan Hyperframes Ollama Claude
```

手動で `--title` を指定するときは、ベースタイトルだけ入れればよい。YouTubeのタイトル上限100文字に収まるよう、suffix込みで自動調整される。

## Horizon/Kurage動画の投稿例

対象ページ:

```text
https://aiknowledgecms.exbridge.jp/horizonv.php?id=699591664b8c43f8
```

ローカル動画:

```text
/home/kojima/exdirect/kurage/storage/jobs/699591664b8c43f8/output.mp4
```

ジョブ情報:

```text
/home/kojima/exdirect/kurage/storage/jobs/699591664b8c43f8.json
```

元記事:

```text
https://katsushi2441.github.io/vwork/articles/2026-05-29-ai-news.html
```

投稿例:

```bash
cd /home/kojima/exdirect/airadio-scripted-mv
python3 tools/youtube/upload_youtube.py \
  /home/kojima/exdirect/kurage/storage/jobs/699591664b8c43f8/output.mp4 \
  --title "AIが変える未来：インフラから金融まで最前線速報" \
  --description $'Horizonで収集・要約したAI/Web3ニュースを、Kurageでショート動画化しました。\n\n元記事:\nhttps://katsushi2441.github.io/vwork/articles/2026-05-29-ai-news.html\n\nHorizon動画ページ:\nhttps://aiknowledgecms.exbridge.jp/horizonv.php?id=699591664b8c43f8' \
  --tags "AI,LLM,Web3,Horizon,Kurage,AIニュース,バイブコーディング" \
  --privacy public \
  --json-out storage/youtube/horizon_699591664b8c43f8_response.json
```

実績:

```text
https://youtu.be/o1hrS5r6DqI
```

## サムネイル画像を設定する

YouTubeのサムネイルは、投稿後に `tools/youtube/set_thumbnail.py` で設定できる。

画像は1280x720のJPG/PNGを推奨する。生成画像が大きい場合はPillowで変換する。

```bash
cd /home/kojima/exdirect/airadio-scripted-mv
python3 - <<'PY'
from PIL import Image
from pathlib import Path

src = Path("storage/youtube/thumbnails/source.png")
out = Path("storage/youtube/thumbnails/thumbnail_1280x720.jpg")
out.parent.mkdir(parents=True, exist_ok=True)
im = Image.open(src).convert("RGB")
im = im.resize((1280, 720), Image.LANCZOS)
im.save(out, quality=90, optimize=True)
print(out)
PY
```

設定コマンド:

```bash
python3 tools/youtube/set_thumbnail.py \
  --video-id o1hrS5r6DqI \
  --image storage/youtube/thumbnails/horizon_699591664b8c43f8_1280x720.jpg
```

成功すると `youtube#thumbnailSetResponse` が返り、`maxresdefault.jpg` のURLが表示される。

実績:

```text
https://i.ytimg.com/vi/o1hrS5r6DqI/maxresdefault.jpg
```

## AIxSNS告知

AIxSNS投稿APIはAIxEC APIサーバを使う。

```bash
cd /home/kojima/exdirect/aixec
python3 - <<'PY'
import json, urllib.request

content = """Horizon.AI生成ニュース動画をYouTubeに公開しました。

YouTube:
https://youtu.be/o1hrS5r6DqI

元記事:
https://katsushi2441.github.io/vwork/articles/2026-05-29-ai-news.html

Horizon動画ページ:
https://aiknowledgecms.exbridge.jp/horizonv.php?id=699591664b8c43f8"""

payload = json.dumps({"author": "kurage", "content": content}, ensure_ascii=False).encode("utf-8")
req = urllib.request.Request(
    "http://127.0.0.1:8081/posts",
    data=payload,
    headers={"Content-Type": "application/json"},
    method="POST",
)
print(urllib.request.urlopen(req, timeout=15).read().decode())
PY
```

Kurage生成動画のYouTube告知は、投稿者を `kurage` にする。

## トラブル対応

### invalid_grant

refresh tokenが失効している。`youtube_auth_paste.py` で再認証する。

### access_denied / テスト中でアクセスできない

Google Auth Platformのテストユーザーに、YouTube投稿に使うGoogleアカウントを追加する。

### localhost HTTPが弾かれる

`youtube_auth_paste.py` は `OAUTHLIB_INSECURE_TRANSPORT=1` を設定済み。
古いコードで失敗した場合、認証コードは一回限りなのでURLを再発行する。

### 投稿先チャンネルが違う

OAuth認証時に選んだGoogleアカウント/YouTubeチャンネルが投稿先になる。
違うチャンネルに投稿したい場合は `storage/youtube/token.json` を退避し、正しいアカウントで再認証する。
