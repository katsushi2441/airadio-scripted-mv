#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import subprocess
import tempfile
from pathlib import Path

from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials
from googleapiclient.discovery import build
from googleapiclient.http import MediaFileUpload


ROOT = Path(__file__).resolve().parents[2]
SCOPES = ["https://www.googleapis.com/auth/youtube.upload"]
DEFAULT_TOKEN = ROOT / "storage" / "youtube" / "token.json"
TITLE_SUFFIX = " - Ernie Kurage Wan Hyperframes Ollama Claude"
MAX_YOUTUBE_TITLE = 100


def normalize_title(title: str) -> str:
    title = (title or "").strip()
    if TITLE_SUFFIX not in title:
        base_limit = MAX_YOUTUBE_TITLE - len(TITLE_SUFFIX)
        title = title[:base_limit].rstrip() + TITLE_SUFFIX
    return title[:MAX_YOUTUBE_TITLE].rstrip()


def load_credentials(token_path: Path) -> Credentials:
    if not token_path.is_file():
        raise SystemExit(f"token not found: {token_path}")
    creds = Credentials.from_authorized_user_file(str(token_path), SCOPES)
    if creds.expired and creds.refresh_token:
        creds.refresh(Request())
        token_path.write_text(creds.to_json(), encoding="utf-8")
    if not creds.valid:
        raise SystemExit("YouTube credentials are invalid. Run youtube_auth.py again.")
    return creds


def _probe_video_size(video_path: Path) -> tuple[int, int]:
    cmd = [
        "ffprobe", "-v", "error",
        "-select_streams", "v:0",
        "-show_entries", "stream=width,height",
        "-of", "csv=s=x:p=0",
        str(video_path),
    ]
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
    if result.returncode != 0:
        raise RuntimeError(result.stderr[-500:] or "ffprobe failed")
    width, height = result.stdout.strip().split("x", 1)
    return int(width), int(height)


def make_thumbnail_intro_video(video_path: Path, thumbnail_path: Path, seconds: float = 1.2) -> Path:
    """Create an upload-only MP4 with thumbnail image inserted as the first frame segment.

    This helps YouTube Shorts pick the intended thumbnail, because Shorts often ignore
    external custom thumbnails set via API.
    """
    if not thumbnail_path.is_file():
        return video_path
    width, height = _probe_video_size(video_path)
    tmpdir = Path(tempfile.mkdtemp(prefix="youtube-thumb-intro-"))
    intro = tmpdir / "intro.mp4"
    output = tmpdir / "upload_with_thumbnail_intro.mp4"
    vf = (
        f"scale={width}:{height}:force_original_aspect_ratio=increase,"
        f"crop={width}:{height},setsar=1"
    )
    intro_cmd = [
        "ffmpeg", "-y",
        "-loop", "1", "-t", f"{seconds:.2f}",
        "-i", str(thumbnail_path),
        "-f", "lavfi", "-t", f"{seconds:.2f}", "-i", "anullsrc=channel_layout=stereo:sample_rate=44100",
        "-vf", vf,
        "-r", "30",
        "-c:v", "libx264", "-pix_fmt", "yuv420p",
        "-c:a", "aac", "-shortest",
        str(intro),
    ]
    result = subprocess.run(intro_cmd, capture_output=True, text=True, timeout=120)
    if result.returncode != 0:
        raise RuntimeError("thumbnail intro failed: " + result.stderr[-1000:])
    # Use filter concat, not concat demuxer. Some generated MP4s have timestamp
    # metadata that makes demuxer concat produce a much longer upload duration.
    concat_cmd = [
        "ffmpeg", "-y",
        "-i", str(intro),
        "-i", str(video_path),
        "-filter_complex", "[0:v:0][0:a:0][1:v:0][1:a:0]concat=n=2:v=1:a=1[v][a]",
        "-map", "[v]",
        "-map", "[a]",
        "-c:v", "libx264", "-pix_fmt", "yuv420p",
        "-c:a", "aac",
        "-movflags", "+faststart",
        str(output),
    ]
    result = subprocess.run(concat_cmd, capture_output=True, text=True, timeout=300)
    if result.returncode != 0 or not output.exists():
        raise RuntimeError("thumbnail intro concat failed: " + result.stderr[-1000:])
    print(f"thumbnail intro video: {output}", flush=True)
    return output


def upload_video(args: argparse.Namespace) -> dict:
    video_path = Path(args.video)
    if not video_path.is_file():
        raise SystemExit(f"video not found: {video_path}")
    if args.thumbnail_intro:
        video_path = make_thumbnail_intro_video(video_path, Path(args.thumbnail_intro), args.thumbnail_intro_seconds)

    creds = load_credentials(Path(args.token))
    youtube = build("youtube", "v3", credentials=creds)
    body = {
        "snippet": {
            "title": normalize_title(args.title),
            "description": args.description,
            "categoryId": args.category_id,
            "tags": [tag.strip() for tag in args.tags.split(",") if tag.strip()],
        },
        "status": {
            "privacyStatus": args.privacy,
            "selfDeclaredMadeForKids": False,
        },
    }
    media = MediaFileUpload(str(video_path), chunksize=-1, resumable=True, mimetype="video/mp4")
    request = youtube.videos().insert(part="snippet,status", body=body, media_body=media)

    response = None
    print("uploading...")
    while response is None:
        status, response = request.next_chunk()
        if status:
            print(f"{int(status.progress() * 100)}%")

    print(f"https://youtu.be/{response['id']}")
    return response


def main() -> None:
    parser = argparse.ArgumentParser(description="Upload a video to YouTube using saved OAuth token.json.")
    parser.add_argument("video")
    parser.add_argument("--title", required=True)
    parser.add_argument("--description", default="")
    parser.add_argument("--tags", default="")
    parser.add_argument("--category-id", default="28")
    parser.add_argument("--privacy", choices=("private", "unlisted", "public"), default="unlisted")
    parser.add_argument("--token", default=str(DEFAULT_TOKEN))
    parser.add_argument("--json-out", default="")
    parser.add_argument("--thumbnail-intro", default="", help="prepend this image as a short intro frame for Shorts thumbnails")
    parser.add_argument("--thumbnail-intro-seconds", type=float, default=1.2)
    args = parser.parse_args()

    response = upload_video(args)
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(response, ensure_ascii=False, indent=2), encoding="utf-8")


if __name__ == "__main__":
    main()
