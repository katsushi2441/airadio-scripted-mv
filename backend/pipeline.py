from __future__ import annotations

import json
import shutil
import subprocess
import sys
import time
import traceback
import math
from pathlib import Path

from config import BASE_DIR, JOBS_DIR, OUTPUTS_DIR, WORK_DIR
from image_gen import generate_scene_images
from publish_web import publish_job
from script_gen import generate_mv_script
from video_gen import audio_duration, generate_mv_video


def job_path(job_id: str) -> Path:
    return JOBS_DIR / f"{job_id}.json"


def load_job(job_id: str) -> dict | None:
    path = job_path(job_id)
    if not path.exists():
        return None
    return json.loads(path.read_text(encoding="utf-8"))


def update_job(job_id: str, **kwargs) -> None:
    JOBS_DIR.mkdir(parents=True, exist_ok=True)
    data = {}
    path = job_path(job_id)
    if path.exists():
        data = json.loads(path.read_text(encoding="utf-8"))
    data.update(kwargs)
    data["updated_at"] = time.strftime("%Y-%m-%d %H:%M:%S")
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")


def lrc_to_text(lrc: str) -> str:
    lines = []
    for line in lrc.splitlines():
        text = line.strip()
        while text.startswith("[") and "]" in text:
            text = text.split("]", 1)[1].strip()
        if text:
            lines.append(text)
    return "\n".join(lines) + ("\n" if lines else "")


def title_from_filename(job: dict, audio: Path) -> str:
    return Path(job.get("filename") or audio.name).stem


def normalize_title(title: str, fallback: str) -> str:
    title = title.strip()
    return title if title else fallback


def run_lyrics_pipeline(job_id: str) -> None:
    job = load_job(job_id)
    if not job:
        return

    audio = Path(job["input_file"])
    out_dir = OUTPUTS_DIR / job_id
    script = BASE_DIR / "tools" / "lyrics-extractor" / "extract_lyrics.py"
    model = job.get("model") or "small"
    language = job.get("language") or "ja"

    log_lines: list[str] = []

    try:
        update_job(job_id, status="separating", progress=10, message="Demucsでボーカルを分離中")
        out_dir.mkdir(parents=True, exist_ok=True)

        cmd = [
            sys.executable,
            str(script),
            str(audio),
            "--output-dir",
            str(out_dir),
            "--work-dir",
            str(WORK_DIR),
            "--model",
            model,
        ]
        if language:
            cmd += ["--language", language]

        proc = subprocess.Popen(
            cmd,
            cwd=str(BASE_DIR),
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            bufsize=1,
        )

        assert proc.stdout is not None
        for line in proc.stdout:
            line = line.rstrip()
            log_lines.append(line)
            joined = "\n".join(log_lines[-80:])
            if "WhisperModel" in line or "transcribe" in line:
                update_job(job_id, status="transcribing", progress=25, message="faster-whisperで歌詞を解析中", log=joined)
            else:
                update_job(job_id, log=joined)

        code = proc.wait()
        if code != 0:
            raise RuntimeError(f"lyrics extraction failed: exit={code}")

        meta_path = out_dir / "metadata.json"
        meta = {}
        if meta_path.exists():
            meta = json.loads(meta_path.read_text(encoding="utf-8"))

        lyrics_text_path = out_dir / "lyrics.txt"
        lrc_path = out_dir / "lyrics.lrc"
        lyrics_text = lyrics_text_path.read_text(encoding="utf-8") if lyrics_text_path.exists() else ""
        if not lyrics_text.strip():
            raise RuntimeError("歌詞テキストが空です。歌声認識に失敗した可能性があります。")

        update_job(
            job_id,
            status="scripting",
            progress=40,
            message="歌詞からMV脚本と画像プロンプトを生成中",
            output_dir=str(out_dir),
            segments=meta.get("segments"),
            detected_language=meta.get("language"),
            log="\n".join(log_lines[-120:]),
        )
        duration_sec = audio_duration(audio)
        scene_count = max(1, math.ceil(duration_sec / 10.0))
        mv_script = generate_mv_script(lyrics_text, job.get("filename") or audio.name, scene_count=scene_count)
        mv_script["title"] = title_from_filename(job, audio)
        update_job(job_id, script=mv_script, title=mv_script.get("title"), duration_sec=duration_sec, scene_count=scene_count)

        job_dir = JOBS_DIR / job_id
        update_job(job_id, status="imaging", progress=55, message=f"MV用画像を{scene_count}枚生成中")
        image_paths = generate_scene_images(mv_script.get("scenes") or [], job_dir)
        update_job(job_id, image_count=len(image_paths), progress=78)

        update_job(job_id, status="rendering", progress=85, message="HyperFramesで歌詞字幕付きMVを生成中")
        video_path = generate_mv_video(mv_script, image_paths, audio, lrc_path, job_dir)

        update_job(
            job_id,
            status="done",
            progress=100,
            message="歌詞字幕付きMV生成完了",
            video_file=str(video_path),
            output_dir=str(out_dir),
            segments=meta.get("segments"),
            detected_language=meta.get("language"),
            log="\n".join(log_lines[-120:]),
        )
        try:
            publish_job(job_id)
        except Exception as pub_exc:
            update_job(job_id, publish_error=str(pub_exc))
    except Exception as exc:
        update_job(
            job_id,
            status="error",
            progress=100,
            message="解析に失敗しました",
            error=str(exc),
            traceback=traceback.format_exc(),
            log="\n".join(log_lines[-120:]),
        )


def run_mv_from_existing_lyrics(job_id: str) -> None:
    job = load_job(job_id)
    if not job:
        return
    try:
        audio = Path(job["input_file"])
        out_dir = OUTPUTS_DIR / job_id
        lyrics_text_path = out_dir / "lyrics.txt"
        lrc_path = out_dir / "lyrics.lrc"
        lyrics_text = lyrics_text_path.read_text(encoding="utf-8") if lyrics_text_path.exists() else ""
        if not lyrics_text.strip():
            raise RuntimeError("歌詞テキストが空です。最初から生成してください。")

        update_job(job_id, status="scripting", progress=40, message="既存歌詞からMV脚本を再生成中", error=None, traceback=None)
        duration_sec = audio_duration(audio)
        scene_count = max(1, math.ceil(duration_sec / 10.0))
        mv_script = generate_mv_script(lyrics_text, job.get("filename") or audio.name, scene_count=scene_count)
        mv_script["title"] = normalize_title(job.get("title") or "", title_from_filename(job, audio))
        update_job(job_id, script=mv_script, title=mv_script.get("title"), duration_sec=duration_sec, scene_count=scene_count)

        job_dir = JOBS_DIR / job_id
        update_job(job_id, status="imaging", progress=55, message=f"MV用画像を{scene_count}枚生成中")
        image_paths = generate_scene_images(mv_script.get("scenes") or [], job_dir)
        update_job(job_id, image_count=len(image_paths), progress=78)

        update_job(job_id, status="rendering", progress=85, message="HyperFramesで歌詞字幕付きMVを生成中")
        video_path = generate_mv_video(mv_script, image_paths, audio, lrc_path, job_dir)
        update_job(job_id, status="done", progress=100, message="歌詞字幕付きMV生成完了", video_file=str(video_path), output_dir=str(out_dir))
        try:
            publish_job(job_id)
        except Exception as pub_exc:
            update_job(job_id, publish_error=str(pub_exc))
    except Exception as exc:
        update_job(job_id, status="error", progress=100, message="MV生成に失敗しました", error=str(exc), traceback=traceback.format_exc())


def delete_job_files(job_id: str) -> None:
    job = load_job(job_id)
    if job and job.get("input_file"):
        Path(job["input_file"]).unlink(missing_ok=True)
    shutil.rmtree(OUTPUTS_DIR / job_id, ignore_errors=True)
    job_path(job_id).unlink(missing_ok=True)


def run_rerender_pipeline(job_id: str, corrected_lrc: str, corrected_title: str = "") -> None:
    job = load_job(job_id)
    if not job:
        return
    try:
        audio = Path(job["input_file"])
        out_dir = OUTPUTS_DIR / job_id
        lrc_path = out_dir / "lyrics.lrc"
        lrc_path.write_text(corrected_lrc.strip() + "\n", encoding="utf-8")
        (out_dir / "lyrics.txt").write_text(lrc_to_text(corrected_lrc), encoding="utf-8")

        script = job.get("script")
        if not script:
            raise RuntimeError("MV脚本がありません。最初から生成してください。")
        title = normalize_title(corrected_title, normalize_title(job.get("title") or "", title_from_filename(job, audio)))
        script["title"] = title

        job_dir = JOBS_DIR / job_id
        image_paths = []
        for i in range(len(script.get("scenes") or [])):
            path = job_dir / "assets" / f"scene_{i:02d}.png"
            if not path.is_file():
                raise RuntimeError(f"画像が見つかりません: {path.name}")
            image_paths.append(path)

        update_job(job_id, status="rendering", progress=85, message="修正した歌詞でMP4を再生成中")
        video_path = generate_mv_video(script, image_paths, audio, lrc_path, job_dir)
        update_job(
            job_id,
            status="done",
            progress=100,
            message="修正歌詞でMP4再生成完了",
            video_file=str(video_path),
            title=title,
            script=script,
        )
        try:
            publish_job(job_id)
        except Exception as pub_exc:
            update_job(job_id, publish_error=str(pub_exc))
    except Exception as exc:
        update_job(
            job_id,
            status="error",
            progress=100,
            message="MP4再生成に失敗しました",
            error=str(exc),
            traceback=traceback.format_exc(),
        )
