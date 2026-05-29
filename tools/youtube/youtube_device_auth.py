#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import time
from pathlib import Path
from urllib.parse import urlencode
from urllib.request import Request, urlopen
from urllib.error import HTTPError


ROOT = Path(__file__).resolve().parents[2]
SCOPES = ["https://www.googleapis.com/auth/youtube.upload"]
DEFAULT_TOKEN = ROOT / "storage" / "youtube" / "token.json"
DEFAULT_CLIENT = ROOT / "storage" / "youtube" / "oauth_client.json"


def post_form(url: str, payload: dict) -> dict:
    data = urlencode(payload).encode("utf-8")
    req = Request(url, data=data, headers={"Content-Type": "application/x-www-form-urlencoded"})
    try:
        with urlopen(req, timeout=30) as res:
            return json.loads(res.read().decode("utf-8"))
    except HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        try:
            return json.loads(body)
        except Exception:
            raise RuntimeError(body) from exc


def load_client(path: Path) -> dict:
    if not path.is_file():
        raise SystemExit(f"client file not found: {path}")
    data = json.loads(path.read_text(encoding="utf-8"))
    if "installed" in data:
        data = data["installed"]
    if "web" in data:
        data = data["web"]
    client_id = data.get("client_id")
    if not client_id:
        raise SystemExit("client_id not found")
    return {"client_id": client_id, "client_secret": data.get("client_secret", "")}


def main() -> None:
    parser = argparse.ArgumentParser(description="Create YouTube token.json using Google device auth.")
    parser.add_argument("--client", default=str(DEFAULT_CLIENT))
    parser.add_argument("--token", default=str(DEFAULT_TOKEN))
    args = parser.parse_args()

    client = load_client(Path(args.client))
    device = post_form("https://oauth2.googleapis.com/device/code", {
        "client_id": client["client_id"],
        "scope": " ".join(SCOPES),
    })
    if "error" in device:
        raise SystemExit(json.dumps(device, ensure_ascii=False))

    print("Open:", device.get("verification_url") or "https://www.google.com/device")
    print("Code:", device["user_code"])
    print("Waiting for approval...")

    token_path = Path(args.token)
    deadline = time.time() + int(device.get("expires_in") or 600)
    interval = int(device.get("interval") or 5)
    while time.time() < deadline:
        time.sleep(interval)
        payload = {
            "client_id": client["client_id"],
            "device_code": device["device_code"],
            "grant_type": "urn:ietf:params:oauth:grant-type:device_code",
        }
        if client.get("client_secret"):
            payload["client_secret"] = client["client_secret"]
        token = post_form("https://oauth2.googleapis.com/token", payload)
        if "access_token" in token:
            token["client_id"] = client["client_id"]
            if client.get("client_secret"):
                token["client_secret"] = client["client_secret"]
            token["token_uri"] = "https://oauth2.googleapis.com/token"
            token["scopes"] = SCOPES
            token["token"] = token.pop("access_token")
            token_path.parent.mkdir(parents=True, exist_ok=True)
            token_path.write_text(json.dumps(token, ensure_ascii=False, indent=2), encoding="utf-8")
            print(f"saved: {token_path}")
            return
        err = token.get("error")
        if err == "authorization_pending":
            continue
        if err == "slow_down":
            interval += 5
            continue
        raise SystemExit(json.dumps(token, ensure_ascii=False))
    raise SystemExit("device auth expired")


if __name__ == "__main__":
    main()
