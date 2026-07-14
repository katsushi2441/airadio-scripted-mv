#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import os
from pathlib import Path

from google_auth_oauthlib.flow import InstalledAppFlow


ROOT = Path(__file__).resolve().parents[2]
SCOPES = [
    "https://www.googleapis.com/auth/youtube.upload",
    "https://www.googleapis.com/auth/youtube.force-ssl",
]
DEFAULT_CLIENT = ROOT / "storage" / "youtube" / "oauth_client.json"
DEFAULT_TOKEN = ROOT / "storage" / "youtube" / "token.json"


def main() -> None:
    os.environ.setdefault("OAUTHLIB_INSECURE_TRANSPORT", "1")
    parser = argparse.ArgumentParser(description="Create YouTube token.json by pasting the final localhost redirect URL.")
    parser.add_argument("--client", default=str(DEFAULT_CLIENT))
    parser.add_argument("--token", default=str(DEFAULT_TOKEN))
    parser.add_argument("--redirect-uri", default="http://localhost:8089/")
    args = parser.parse_args()

    client_path = Path(args.client)
    if not client_path.is_file():
        raise SystemExit(f"client file not found: {client_path}")
    data = json.loads(client_path.read_text(encoding="utf-8"))
    if "installed" not in data and "web" not in data:
        data = {"installed": data}
    key = "installed" if "installed" in data else "web"
    data[key].setdefault("auth_uri", "https://accounts.google.com/o/oauth2/auth")
    data[key].setdefault("token_uri", "https://oauth2.googleapis.com/token")
    data[key].setdefault("redirect_uris", [args.redirect_uri])

    flow = InstalledAppFlow.from_client_config(data, SCOPES, redirect_uri=args.redirect_uri)
    auth_url, _state = flow.authorization_url(access_type="offline", prompt="consent", include_granted_scopes="true")
    print("Open this URL:")
    print(auth_url)
    print("")
    print("After approval, paste the final localhost URL here.")
    redirected = input("redirect URL> ").strip()
    flow.fetch_token(authorization_response=redirected)

    token_path = Path(args.token)
    token_path.parent.mkdir(parents=True, exist_ok=True)
    token_path.write_text(flow.credentials.to_json(), encoding="utf-8")
    print(f"saved: {token_path}")


if __name__ == "__main__":
    main()
