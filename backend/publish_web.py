from __future__ import annotations

import ftplib
import json
import os
from pathlib import Path

from config import JOBS_DIR, OUTPUTS_DIR

ROOT = Path(__file__).resolve().parent.parent
ENV_FILE = Path("/home/kojima/exdirect/aixec/.env")
REMOTE_ROOT = "/web/airadio-scripted-mv_exbridge_jp"
PUBLIC_BASE_URL = "https://airadio-scripted-mv.exbridge.jp"
LOCAL_PUBLIC_DB = ROOT / "storage" / "lyrics" / "public_videos.json"


def load_env() -> dict[str, str]:
    env = dict(os.environ)
    if ENV_FILE.exists():
        for line in ENV_FILE.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            env[key.strip()] = value.strip().strip('"').strip("'")
    return env


def mkdir_p(ftp: ftplib.FTP, path: str) -> None:
    cur = ""
    for part in path.strip("/").split("/"):
        cur += "/" + part
        try:
            ftp.mkd(cur)
        except Exception:
            pass


def upload(ftp: ftplib.FTP, local: Path, remote: str) -> None:
    mkdir_p(ftp, str(Path(remote).parent))
    with local.open("rb") as fh:
        ftp.storbinary("STOR " + remote, fh)


def load_public_db() -> list[dict]:
    if not LOCAL_PUBLIC_DB.exists():
        return []
    try:
        data = json.loads(LOCAL_PUBLIC_DB.read_text(encoding="utf-8"))
        return data if isinstance(data, list) else []
    except Exception:
        return []


def save_public_db(items: list[dict]) -> None:
    LOCAL_PUBLIC_DB.parent.mkdir(parents=True, exist_ok=True)
    LOCAL_PUBLIC_DB.write_text(json.dumps(items, ensure_ascii=False, indent=2), encoding="utf-8")


def publish_job(job_id: str) -> None:
    job_path = JOBS_DIR / f"{job_id}.json"
    if not job_path.exists():
        raise FileNotFoundError(job_path)
    job = json.loads(job_path.read_text(encoding="utf-8"))
    video_file = Path(job.get("video_file") or "")
    out_dir = OUTPUTS_DIR / job_id
    if not video_file.is_file():
        raise FileNotFoundError(video_file)

    files = {
        "lyrics_mv.mp4": video_file,
        "lyrics.lrc": out_dir / "lyrics.lrc",
        "lyrics.txt": out_dir / "lyrics.txt",
        "metadata.json": out_dir / "metadata.json",
    }
    remote_dir = f"{REMOTE_ROOT}/videos/{job_id}"
    env = load_env()
    with ftplib.FTP(env["FTP_HOST"], timeout=90) as ftp:
        ftp.login(env["FTP_USER"], env["FTP_PASS"])
        for name, path in files.items():
            if path.is_file():
                upload(ftp, path, f"{remote_dir}/{name}")

        item = {
            "job_id": job_id,
            "title": Path(job.get("filename") or job_id).stem,
            "filename": job.get("filename") or "",
            "created_at": job.get("created_at") or "",
            "updated_at": job.get("updated_at") or "",
            "duration_sec": job.get("duration_sec"),
            "scene_count": job.get("scene_count"),
            "video_url": f"{PUBLIC_BASE_URL}/videos/{job_id}/lyrics_mv.mp4",
            "lrc_url": f"{PUBLIC_BASE_URL}/videos/{job_id}/lyrics.lrc",
            "txt_url": f"{PUBLIC_BASE_URL}/videos/{job_id}/lyrics.txt",
            "detail_url": f"{PUBLIC_BASE_URL}/scripted-mvv.php?id={job_id}",
        }
        items = [x for x in load_public_db() if x.get("job_id") != job_id]
        items.insert(0, item)
        items.sort(key=lambda x: x.get("updated_at") or x.get("created_at") or "", reverse=True)
        save_public_db(items)
        upload(ftp, LOCAL_PUBLIC_DB, f"{REMOTE_ROOT}/data/scripted_mv_videos.json")
