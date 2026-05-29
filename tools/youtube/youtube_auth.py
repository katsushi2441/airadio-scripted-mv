#!/usr/bin/env python3
from __future__ import annotations

import argparse
from pathlib import Path

from google_auth_oauthlib.flow import InstalledAppFlow


ROOT = Path(__file__).resolve().parents[2]
SCOPES = ["https://www.googleapis.com/auth/youtube.upload"]
DEFAULT_CLIENT_SECRET = ROOT / "storage" / "youtube" / "client_secret.json"
DEFAULT_TOKEN = ROOT / "storage" / "youtube" / "token.json"


def main() -> None:
    parser = argparse.ArgumentParser(description="Create YouTube OAuth token.json for uploads.")
    parser.add_argument("--client-secret", default=str(DEFAULT_CLIENT_SECRET))
    parser.add_argument("--token", default=str(DEFAULT_TOKEN))
    parser.add_argument("--port", type=int, default=0)
    args = parser.parse_args()

    client_secret = Path(args.client_secret)
    token_path = Path(args.token)
    if not client_secret.is_file():
        raise SystemExit(f"client secret not found: {client_secret}")

    flow = InstalledAppFlow.from_client_secrets_file(str(client_secret), SCOPES)
    creds = flow.run_local_server(port=args.port, prompt="consent", access_type="offline")

    token_path.parent.mkdir(parents=True, exist_ok=True)
    token_path.write_text(creds.to_json(), encoding="utf-8")
    print(f"saved: {token_path}")


if __name__ == "__main__":
    main()
