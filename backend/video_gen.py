from __future__ import annotations

import html
import json
import os
import shutil
import subprocess
from pathlib import Path

from config import HYPERFRAMES_VERSION, NVM_NODE


def audio_duration(path: Path) -> float:
    result = subprocess.run(
        [
            "ffprobe",
            "-v",
            "error",
            "-show_entries",
            "format=duration",
            "-of",
            "default=noprint_wrappers=1:nokey=1",
            str(path),
        ],
        capture_output=True,
        text=True,
        check=True,
    )
    return max(float(result.stdout.strip() or 0), 1.0)


def parse_lrc(path: Path) -> list[dict]:
    items = []
    for line in path.read_text(encoding="utf-8").splitlines():
        if not line.strip():
            continue
        if not line.startswith("[") or "]" not in line:
            continue
        stamp, text = line.split("]", 1)
        stamp = stamp.strip("[")
        try:
            minute, rest = stamp.split(":", 1)
            sec = float(rest)
            start = int(minute) * 60 + sec
        except Exception:
            continue
        text = " ".join(text.strip().split())
        if text:
            items.append({"start": start, "text": text})
    return items


def lyric_timeline(lrc_path: Path, total_duration: float) -> list[dict]:
    raw = parse_lrc(lrc_path)
    lines = []
    for i, item in enumerate(raw):
        end = raw[i + 1]["start"] if i + 1 < len(raw) else total_duration
        dur = max(1.2, min(8.0, end - item["start"]))
        lines.append({"start": item["start"], "duration": dur, "text": item["text"]})
    return lines


def build_html(script: dict, image_count: int, lyrics: list[dict], total_duration: float, audio_ext: str) -> str:
    title = html.escape(script.get("title") or "Lyrics MV")
    scenes = script.get("scenes") or []
    scene_duration = total_duration / max(image_count, 1)

    scene_blocks = []
    scene_anims = []
    for i in range(image_count):
        start = i * scene_duration
        duration = scene_duration + 0.8
        scene_blocks.append(f"""
    <div class="scene clip" id="scene-{i}" data-start="{start:.2f}" data-duration="{duration:.2f}">
      <img class="scene-bg" src="assets/scene_{i:02d}.png" alt="scene {i}">
    </div>""")
        scene_anims.append(f"""
  tl.to("#scene-{i}", {{opacity:1, scale:1.04, duration:0.6}}, {start:.2f})
    .to("#scene-{i} .scene-bg", {{scale:1.12, duration:{duration:.2f}, ease:"none"}}, {start:.2f})
    .to("#scene-{i}", {{opacity:0, duration:0.6}}, {max(start + duration - 0.6, start):.2f});""")

    lyric_blocks = []
    lyric_anims = []
    for i, line in enumerate(lyrics):
        text = html.escape(line["text"])
        start = float(line["start"])
        dur = float(line["duration"])
        lyric_blocks.append(f'<div class="lyric-line" id="lyric-{i}">{text}</div>')
        lyric_anims.append(f"""
  tl.to("#lyric-{i}", {{opacity:1, y:0, duration:0.18}}, {start:.2f})
    .to("#lyric-{i}", {{opacity:0, y:-10, duration:0.18}}, {max(start + dur - 0.18, start):.2f});""")

    return f"""<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>{title}</title>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.14.2/dist/gsap.min.js"></script>
  <style>
    * {{ box-sizing:border-box; margin:0; padding:0; }}
    html,body {{ width:576px; height:1024px; overflow:hidden; background:#000; font-family:"Noto Sans JP",sans-serif; }}
    #composition {{ position:relative; width:576px; height:1024px; overflow:hidden; background:#000; }}
    .scene {{ position:absolute; inset:0; opacity:0; overflow:hidden; background:#000; }}
    .scene-bg {{ width:100%; height:100%; object-fit:cover; display:block; transform:scale(1.04); }}
    #lyrics {{ position:absolute; left:24px; right:24px; bottom:86px; z-index:20; min-height:150px; display:flex; align-items:center; justify-content:center; }}
    .lyric-line {{ position:absolute; opacity:0; transform:translateY(12px); color:#fff; font-size:34px; font-weight:900; line-height:1.42; text-align:center; letter-spacing:0; text-shadow:0 3px 12px rgba(0,0,0,.95),0 0 28px rgba(0,0,0,.8); }}
    #title {{ position:absolute; inset:0; z-index:30; display:flex; align-items:center; justify-content:center; color:#fff; font-size:34px; font-weight:900; text-align:center; padding:40px; background:rgba(0,0,0,.48); opacity:0; text-shadow:0 3px 12px rgba(0,0,0,.9); }}
  </style>
</head>
<body>
  <div id="composition" data-composition-id="main" data-width="576" data-height="1024">
    <div id="title">{title}</div>
    {"".join(scene_blocks)}
    <div id="lyrics">{"".join(lyric_blocks)}</div>
    <audio id="music" src="music.{audio_ext}" data-start="0" data-duration="{total_duration:.2f}" data-track-index="1" data-volume="1"></audio>
  </div>
  <script>
  (function(){{
    const tl = gsap.timeline({{paused:true}});
    tl.to("#title", {{opacity:1, duration:0.4}}, 0).to("#title", {{opacity:0, duration:0.5}}, 1.4);
    {"".join(scene_anims)}
    {"".join(lyric_anims)}
    window.__timelines = window.__timelines || {{}};
    window.__timelines.main = tl;
  }})();
  </script>
</body>
</html>"""


def create_project(job_dir: Path, script: dict, image_paths: list[Path], audio_file: Path, lrc_path: Path) -> Path:
    project_dir = job_dir / "hf_project"
    assets_dir = project_dir / "assets"
    assets_dir.mkdir(parents=True, exist_ok=True)
    for i, path in enumerate(image_paths):
        shutil.copy(path, assets_dir / f"scene_{i:02d}.png")
    audio_ext = audio_file.suffix.lower().lstrip(".") or "mp3"
    shutil.copy(audio_file, project_dir / f"music.{audio_ext}")
    total = audio_duration(audio_file)
    lyrics = lyric_timeline(lrc_path, total)
    (project_dir / "index.html").write_text(build_html(script, len(image_paths), lyrics, total, audio_ext), encoding="utf-8")
    (project_dir / "hyperframes.json").write_text(json.dumps({
        "$schema": "https://hyperframes.heygen.com/schema/hyperframes.json",
        "registry": "https://raw.githubusercontent.com/heygen-com/hyperframes/main/registry",
        "paths": {"blocks": "compositions", "components": "compositions/components", "assets": "assets"},
    }, indent=2), encoding="utf-8")
    (project_dir / "package.json").write_text(json.dumps({
        "name": f"airadio-lyrics-mv-{job_dir.name}",
        "private": True,
        "type": "module",
        "scripts": {"render": f"npx --yes hyperframes@{HYPERFRAMES_VERSION} render"},
    }, indent=2), encoding="utf-8")
    (project_dir / "meta.json").write_text(json.dumps({"id": job_dir.name, "name": script.get("title") or "Lyrics MV"}, ensure_ascii=False, indent=2), encoding="utf-8")
    return project_dir


def render_video(project_dir: Path, output_path: Path) -> Path:
    env = dict(os.environ)
    env["PATH"] = NVM_NODE + ":" + env.get("PATH", "")
    cmd = f'source ~/.nvm/nvm.sh 2>/dev/null; cd "{project_dir}" && npx --yes hyperframes@{HYPERFRAMES_VERSION} render --output "{output_path}"'
    result = subprocess.run(["bash", "-c", cmd], cwd=str(project_dir), env=env, capture_output=True, text=True, timeout=900)
    if result.returncode != 0:
        raise RuntimeError(f"hyperframes render failed rc={result.returncode}\nstdout:{result.stdout[-2000:]}\nstderr:{result.stderr[-2000:]}")
    if not output_path.exists():
        raise RuntimeError("HyperFrames finished but output.mp4 was not created")
    return output_path


def generate_mv_video(script: dict, image_paths: list[Path], audio_file: Path, lrc_path: Path, job_dir: Path) -> Path:
    project_dir = create_project(job_dir, script, image_paths, audio_file, lrc_path)
    return render_video(project_dir, job_dir / "lyrics_mv.mp4")
