#!/usr/bin/env python3
import os
import requests
import subprocess
import random
import json
from ftplib import FTP

# =========================================================
# 設定（あなたが指定した実データ）
# =========================================================
# =========================================================
# 設定読み込み（config.json）
# =========================================================
with open("config.json", "r", encoding="utf-8") as f:
    _cfg = json.load(f)

PEXELS_API_KEY = _cfg["PEXELS_API_KEY"]

FTP_HOST = _cfg["FTP_HOST"]
FTP_USER = _cfg["FTP_USER"]
FTP_PASS = _cfg["FTP_PASS"]
FTP_DIR  = _cfg["FTP_DIR"]

TARGET_SECONDS = _cfg["TARGET_SECONDS"]
OUTPUT_FILE    = _cfg["OUTPUT_FILE"]

HEADERS = {"Authorization": PEXELS_API_KEY}


# =========================================================
# 1) Vidnoz で安全に使える動画ジャンル（露出少なめ）
# =========================================================
SAFE_QUERIES = [
    # 宇宙・星・天体
    "starry sky timelapse",
    "night sky stars",
    "milky way galaxy",
    "galaxy space background",
    "cosmic nebula",
    "planet earth from space",
    "moon surface",
    "solar system animation",
    "universe abstract background",
    "cosmic light particles",

    # 神社・寺・信仰（海外ストック向け表現）
    "japanese shrine torii",
    "shinto shrine forest",
    "japanese temple nature",
    "ancient shrine atmosphere",
    "sacred place in forest",
    "spiritual place nature",
    "temple incense smoke",
    "shrine path sunlight",

    # ピラミッド・古代文明
    "egyptian pyramids",
    "ancient ruins",
    "ancient civilization ruins",
    "stone monument ancient",
    "mystical ancient temple",
    "lost civilization ruins",
    "ancient architecture details",

    # 歴史的建造物（世界）
    "historical architecture",
    "ancient stone building",
    "old castle ruins",
    "medieval architecture",
    "heritage site architecture",
    "historic monument",

    # 神秘・抽象・雰囲気
    "mystical light rays",
    "sacred geometry animation",
    "esoteric symbols animation",
    "mystic energy light",
    "spiritual abstract background",
    "cosmic consciousness concept",
    "time and space abstract",
#    "young woman working on laptop",
#    "young woman studying",
#    "woman reading book",
#    "female office worker",
#    "woman walking in city",
#    "woman using smartphone",
#    "woman sitting in cafe",
#    "student girl in library",

    # ★追加（安全&portrait大量系）
#    "woman working at home",
#    "female student studying",
#    "woman commuting",
#    "woman typing on keyboard",
#    "people studying",
#    "people reading",
#    "people working in cafe",
#    "person walking in city",
#    "office worker typing",
    "lifestyle daily routine",
    "smartphone user portrait",
#    "walking pov city",
    "study desk portrait",
    "cafe ambient portrait",
#    "young woman working on laptop",
#    "young woman studying",
#    "woman reading book",
#    "female office worker",
#    "woman walking in city",
#    "woman using smartphone",
#    "woman sitting in cafe",
#    "student girl in library",

    # ★追加（安全&portrait大量系）
#    "woman working at home",
#    "female student studying",
#    "woman commuting",
#    "woman typing on keyboard",
#    "people studying",
#    "people reading",
#    "people working in cafe",
#    "person walking in city",
#    "office worker typing",
#    "lifestyle daily routine",
#    "smartphone user portrait",
#    "walking pov city",
#    "study desk portrait",
    "cafe ambient portrait",
]


# =========================================================
# 2) Pexels API 検索（安全キーワード＋ランダムページ）
# =========================================================
def fetch_list():
    q = random.choice(SAFE_QUERIES)

    global LAST_QUERY
    LAST_QUERY = q

    print(f"🔍 縦動画探索: Query={q}")

    # page=1〜30を探索
    for page in range(1, 31):
        url = f"https://api.pexels.com/videos/search?query={q}&orientation=portrait&per_page=40&page={page}"
        r = requests.get(url, headers=HEADERS)
        if r.status_code != 200:
            continue

        data = r.json().get("videos", [])
        print(f"   → Page {page}: {len(data)} 本")

        if len(data) > 0:
            print(f"🎯 使用ページ: {page}")
            return data

    print("❌ 縦動画探索失敗（Pexels API 動画 データ枯渇か仕様変更）")
    return []


# =========================================================
# 3) 縦型動画だけ抽出（width < height）
# =========================================================
def pick_vertical(videos):
    out = []
    for v in videos:
        for f in v["video_files"]:
            if f["height"] > f["width"]:
                out.append(v)
                break
    print(f"📱 縦型動画：{len(out)} 本")
    return out


# =========================================================
# 4) 最小サイズURL（秒数計測用）
# =========================================================
def get_small(v):
    files = sorted(v["video_files"], key=lambda x: x["width"] * x["height"])
    return files[0]["link"]


# =========================================================
# 5) 秒数取得
# =========================================================
def get_dur(path):
    cmd = [
        "ffprobe", "-v", "error",
        "-show_entries", "format=duration",
        "-of", "default=noprint_wrappers=1:nokey=1", path
    ]
    sec = subprocess.check_output(cmd).decode().strip()
    return float(sec)


# =========================================================
# 6) 必要最低限の動画を選ぶ
# =========================================================
def select_minimum(videos):
    os.makedirs("tmp_dur", exist_ok=True)

    chosen = []
    total = 0

    print("⏱ 必要秒数を満たすまで動画追加…")

    for v in videos:
        url = get_small(v)
        tmp = f"tmp_dur/{v['id']}.mp4"

        if not os.path.exists(tmp):
            with open(tmp, "wb") as f:
                f.write(requests.get(url, headers=HEADERS).content)

        dur = get_dur(tmp)
        total += dur
        chosen.append(v)

        print(f"  ✔ {v['id']} : {int(dur)} sec → 合計 {int(total)} sec")

        if total >= TARGET_SECONDS:
            break

    print(f"🎯 必要本数：{len(chosen)}")
    return chosen


# =========================================================
# 7) 高品質動画ダウンロード
# =========================================================
def download_hd(videos):
    os.makedirs("mv_hd", exist_ok=True)
    out = []

    for v in videos:
        big = sorted(
            v["video_files"],
            key=lambda x: x["width"] * x["height"],
            reverse=True
        )[0]

        url = big["link"]
        path = f"mv_hd/{v['id']}.mp4"

        if not os.path.exists(path):
            print(f"⬇ DL: {path}")
            with open(path, "wb") as f:
                f.write(requests.get(url, headers=HEADERS).content)

        out.append(path)

    return out




# =========================================================
# 8) ffmpeg 1080×1920 統一 + concat（fps固定で無限ループ防止）
# =========================================================
def concat(files):

    # ★追加（連結順も毎回変える）
    random.shuffle(files)

    # ★追加：動画0件なら ffmpeg 実行しない
    if len(files) == 0:
        print("❌ 結合する動画が 0 件のため ffmpeg をスキップします。")
        return None

    inputs = []
    work = []
    concat_inputs = []

    for i, f in enumerate(files):
        inputs += ["-i", f]
        work.append(f"[{i}:v]scale=360:640,setsar=1[v{i}]")
        concat_inputs.append(f"[v{i}]")

    flt = "; ".join(work)
    flt += f"; {''.join(concat_inputs)}concat=n={len(files)}:v=1:a=0[tempv]; [tempv]fps=12[outv]"

    cmd = [
        "ffmpeg",
        *inputs,
        "-filter_complex", flt,
        "-map", "[outv]",
        "-c:v", "libx264",
        "-profile:v", "baseline",
        "-level", "3.0",
        "-pix_fmt", "yuv420p",
        "-crf", "35",
        "-preset", "veryfast",
        "-movflags", "+faststart",
        "-an",
        OUTPUT_FILE,
        "-y"
    ]

    print("🎬 ffmpeg 実行中…（軽量版 concat）")
    subprocess.run(cmd)
    print("生成:", OUTPUT_FILE)
    return OUTPUT_FILE

def concat_big(files):

    # ★追加（連結順も毎回変える）
    random.shuffle(files)

    # ★追加：動画0件なら ffmpeg 実行しない
    if len(files) == 0:
        print("❌ 結合する動画が 0 件のため ffmpeg をスキップします。")
        return None

    inputs = []
    work = []
    concat_inputs = []

    for i, f in enumerate(files):
        inputs += ["-i", f]
        work.append(f"[{i}:v]scale=1080:1920,setsar=1[v{i}]")
        concat_inputs.append(f"[v{i}]")

    flt = "; ".join(work)
    flt += f"; {''.join(concat_inputs)}concat=n={len(files)}:v=1:a=0[tempv]; [tempv]fps=30[outv]"

    cmd = [
        "ffmpeg",
        *inputs,
        "-filter_complex", flt,
        "-map", "[outv]",
        "-c:v", "libx264",
        "-profile:v", "main",
        "-level", "4.0",
        "-pix_fmt", "yuv420p",
        "-crf", "28",
        "-preset", "veryfast",
        "-movflags", "+faststart",
        "-an",
        OUTPUT_FILE,
        "-y"
    ]


    cmd1 = [
        "ffmpeg",
        *inputs,
        "-filter_complex", flt,
        "-map", "[outv]",
        "-preset", "fast",
        "-an",
        OUTPUT_FILE,
        "-y"
    ]

    print("🎬 ffmpeg 実行中…（安全版 concat）")
    subprocess.run(cmd)
    print("生成:", OUTPUT_FILE)
    return OUTPUT_FILE


# =========================================================
# 9) FTP アップロード
# =========================================================
def upload(path):
    ftp = FTP()
    ftp.encoding = "utf-8"
    ftp.connect(FTP_HOST, 21)
    ftp.login(FTP_USER, FTP_PASS)
    ftp.cwd(FTP_DIR)

    print("📤 FTP:", path)
    with open(path, "rb") as f:
        ftp.storbinary("STOR " + os.path.basename(path), f)

    ftp.quit()
    print("🎉 FTP アップロード完了")


# =========================================================
# MAIN
# =========================================================
if __name__ == "__main__":

    print("📥 Pexels 取得開始…")
    raw = fetch_list()

    vertical = pick_vertical(raw)

    # ★追加：縦動画リストの順番をランダム化（先頭固定を防ぐ）
    random.shuffle(vertical)

    # ★追加：縦型動画が0件なら停止
    if len(vertical) == 0:
        print("❌ 縦型動画が 0 件のため処理を停止します。")
        exit()

    selected = select_minimum(vertical)

    hd_files = download_hd(selected)

    out = concat(hd_files)

    if out:
        upload(out)

    if LAST_QUERY:
        print("🔎 使用した検索キーワード:", LAST_QUERY)

    print("🎉 全処理 完了！")
