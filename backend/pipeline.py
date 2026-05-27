from __future__ import annotations

import json
import shutil
import subprocess
import sys
import time
import traceback
from pathlib import Path

from config import BASE_DIR, JOBS_DIR, OUTPUTS_DIR, WORK_DIR


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
        update_job(job_id, status="separating", progress=20, message="Demucsでボーカルを分離中")
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
                update_job(job_id, status="transcribing", progress=70, message="faster-whisperで歌詞を解析中", log=joined)
            else:
                update_job(job_id, log=joined)

        code = proc.wait()
        if code != 0:
            raise RuntimeError(f"lyrics extraction failed: exit={code}")

        meta_path = out_dir / "metadata.json"
        meta = {}
        if meta_path.exists():
            meta = json.loads(meta_path.read_text(encoding="utf-8"))

        update_job(
            job_id,
            status="done",
            progress=100,
            message="歌詞データ生成完了",
            output_dir=str(out_dir),
            segments=meta.get("segments"),
            detected_language=meta.get("language"),
            log="\n".join(log_lines[-120:]),
        )
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


def delete_job_files(job_id: str) -> None:
    job = load_job(job_id)
    if job and job.get("input_file"):
        Path(job["input_file"]).unlink(missing_ok=True)
    shutil.rmtree(OUTPUTS_DIR / job_id, ignore_errors=True)
    job_path(job_id).unlink(missing_ok=True)

