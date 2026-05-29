#!/usr/bin/env python3
from __future__ import annotations

import argparse
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
        raise SystemExit("YouTube credentials are invalid. Run youtube_auth_paste.py again.")
    return creds


def main() -> None:
    parser = argparse.ArgumentParser(description="Set a YouTube video thumbnail.")
    parser.add_argument("--video-id", required=True)
    parser.add_argument("--image", required=True)
    parser.add_argument("--token", default=str(DEFAULT_TOKEN))
    args = parser.parse_args()

    image_path = Path(args.image)
    if not image_path.is_file():
        raise SystemExit(f"image not found: {image_path}")

    creds = load_credentials(Path(args.token))
    youtube = build("youtube", "v3", credentials=creds)
    media = MediaFileUpload(str(image_path), mimetype="image/jpeg" if image_path.suffix.lower() in (".jpg", ".jpeg") else "image/png")
    response = youtube.thumbnails().set(videoId=args.video_id, media_body=media).execute()
    print(response)


if __name__ == "__main__":
    main()
