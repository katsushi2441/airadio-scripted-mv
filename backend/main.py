from __future__ import annotations

import shutil
import threading
import time
import uuid
from pathlib import Path

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse

from config import DEFAULT_LANGUAGE, DEFAULT_MODEL, JOBS_DIR, OUTPUTS_DIR, PORT, UPLOADS_DIR
from pipeline import load_job, run_lyrics_pipeline, update_job

app = FastAPI(title="AIRadio Lyrics Extractor API", version="1.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

ALLOWED_EXT = {"mp3", "wav", "m4a", "flac", "ogg"}


@app.get("/health")
def health():
    return {"ok": True, "service": "airadio-lyrics", "time": time.strftime("%Y-%m-%d %H:%M:%S")}


@app.post("/extract")
def extract(
    audio: UploadFile = File(...),
    model: str = Form(DEFAULT_MODEL),
    language: str = Form(DEFAULT_LANGUAGE),
):
    ext = Path(audio.filename or "audio.mp3").suffix.lower().lstrip(".")
    if ext not in ALLOWED_EXT:
        raise HTTPException(status_code=400, detail="unsupported audio type")

    job_id = uuid.uuid4().hex[:16]
    UPLOADS_DIR.mkdir(parents=True, exist_ok=True)
    JOBS_DIR.mkdir(parents=True, exist_ok=True)
    input_file = UPLOADS_DIR / f"{job_id}.{ext}"

    with input_file.open("wb") as f:
        shutil.copyfileobj(audio.file, f)

    update_job(
        job_id,
        status="queued",
        progress=0,
        message="キューに登録しました",
        filename=audio.filename,
        input_file=str(input_file),
        model=model,
        language=language,
        created_at=time.strftime("%Y-%m-%d %H:%M:%S"),
    )

    thread = threading.Thread(target=run_lyrics_pipeline, args=(job_id,), daemon=True)
    thread.start()
    return {"ok": True, "job_id": job_id}


@app.get("/status/{job_id}")
def status(job_id: str):
    job = load_job(job_id)
    if job is None:
        raise HTTPException(status_code=404, detail="job not found")
    return {
        "ok": True,
        "job_id": job_id,
        "status": job.get("status", "unknown"),
        "progress": job.get("progress", 0),
        "message": job.get("message", ""),
        "filename": job.get("filename", ""),
        "model": job.get("model", ""),
        "language": job.get("language", ""),
        "detected_language": job.get("detected_language"),
        "segments": job.get("segments"),
        "created_at": job.get("created_at"),
        "updated_at": job.get("updated_at"),
        "error": job.get("error"),
        "log": job.get("log", ""),
        "files": {
            "vocals": f"/file/{job_id}/vocals.wav",
            "srt": f"/file/{job_id}/lyrics.srt",
            "lrc": f"/file/{job_id}/lyrics.lrc",
            "txt": f"/file/{job_id}/lyrics.txt",
            "metadata": f"/file/{job_id}/metadata.json",
        } if job.get("status") == "done" else {},
    }


@app.get("/jobs")
def jobs(limit: int = 20):
    JOBS_DIR.mkdir(parents=True, exist_ok=True)
    items = []
    for path in JOBS_DIR.glob("*.json"):
        job = load_job(path.stem)
        if not job:
            continue
        items.append({
            "job_id": path.stem,
            "status": job.get("status"),
            "progress": job.get("progress", 0),
            "filename": job.get("filename"),
            "created_at": job.get("created_at"),
            "updated_at": job.get("updated_at"),
        })
    items.sort(key=lambda x: x.get("created_at") or "", reverse=True)
    return {"ok": True, "jobs": items[:limit]}


@app.get("/file/{job_id}/{name}")
def file(job_id: str, name: str):
    allowed = {"vocals.wav", "lyrics.srt", "lyrics.lrc", "lyrics.txt", "metadata.json"}
    if name not in allowed:
        raise HTTPException(status_code=404, detail="file not found")
    path = OUTPUTS_DIR / job_id / name
    if not path.exists():
        raise HTTPException(status_code=404, detail="file not found")
    media = "application/octet-stream"
    if name.endswith(".txt") or name.endswith(".srt") or name.endswith(".lrc") or name.endswith(".json"):
        media = "text/plain; charset=utf-8"
    if name.endswith(".wav"):
        media = "audio/wav"
    return FileResponse(path=str(path), media_type=media, filename=name)


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=PORT)

