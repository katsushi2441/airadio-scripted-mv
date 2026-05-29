#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
from pathlib import Path

from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials
from googleapiclient.discovery import build
from googleapiclient.http import MediaFileUpload


ROOT = Path(__file__).resolve().parents[2]
SCOPES = ["https://www.googleapis.com/auth/youtube.upload"]
DEFAULT_TOKEN = ROOT / "storage" / "youtube" / "token.json"


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


def upload_video(args: argparse.Namespace) -> dict:
    video_path = Path(args.video)
    if not video_path.is_file():
        raise SystemExit(f"video not found: {video_path}")

    creds = load_credentials(Path(args.token))
    youtube = build("youtube", "v3", credentials=creds)
    body = {
        "snippet": {
            "title": args.title,
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
    args = parser.parse_args()

    response = upload_video(args)
    if args.json_out:
        Path(args.json_out).write_text(json.dumps(response, ensure_ascii=False, indent=2), encoding="utf-8")


if __name__ == "__main__":
    main()
