#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import os
from pathlib import Path
from urllib.parse import parse_qs, urlparse

from google_auth_oauthlib.flow import InstalledAppFlow


ROOT = Path(__file__).resolve().parents[2]
SCOPES = [
    "https://www.googleapis.com/auth/youtube.upload",
    "https://www.googleapis.com/auth/youtube.force-ssl",
]
DEFAULT_CLIENT = ROOT / "storage" / "youtube" / "oauth_client.json"
DEFAULT_TOKEN = ROOT / "storage" / "youtube" / "token.json"
DEFAULT_PENDING = ROOT / "storage" / "youtube" / "oauth_pending.json"


def _state_from_url(url: str) -> str:
    parsed = urlparse(url)
    values = parse_qs(parsed.query)
    return (values.get("state") or [""])[0]


def _write_pending(path: Path, *, client_config: dict, redirect_uri: str, state: str, code_verifier: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(
        json.dumps(
            {
                "client_config": client_config,
                "redirect_uri": redirect_uri,
                "state": state,
                "code_verifier": code_verifier,
            },
            ensure_ascii=False,
            indent=2,
        ),
        encoding="utf-8",
    )
    path.chmod(0o600)


def _load_pending(path: Path, redirected: str) -> tuple[dict, str, str]:
    if not path.is_file():
        raise SystemExit(f"pending OAuth state not found: {path}")
    pending = json.loads(path.read_text(encoding="utf-8"))
    expected_state = str(pending.get("state") or "")
    actual_state = _state_from_url(redirected)
    if not actual_state or actual_state != expected_state:
        raise SystemExit("redirect URL state does not match pending OAuth state")
    client_config = pending.get("client_config")
    if not isinstance(client_config, dict):
        raise SystemExit("pending OAuth client_config is invalid")
    return client_config, str(pending.get("redirect_uri") or ""), str(pending.get("code_verifier") or "")


def main() -> None:
    os.environ.setdefault("OAUTHLIB_INSECURE_TRANSPORT", "1")
    parser = argparse.ArgumentParser(description="Create YouTube token.json by pasting the final localhost redirect URL.")
    parser.add_argument("--client", default=str(DEFAULT_CLIENT))
    parser.add_argument("--token", default=str(DEFAULT_TOKEN))
    parser.add_argument("--redirect-uri", default="http://localhost:8089/")
    parser.add_argument("--pending", default=str(DEFAULT_PENDING))
    parser.add_argument("--resume-url", default="", help="finish OAuth with a pasted localhost redirect URL from a saved pending state")
    args = parser.parse_args()

    token_path = Path(args.token)
    pending_path = Path(args.pending)

    if args.resume_url:
        data, redirect_uri, code_verifier = _load_pending(pending_path, args.resume_url)
        flow = InstalledAppFlow.from_client_config(
            data,
            SCOPES,
            redirect_uri=redirect_uri,
            code_verifier=code_verifier,
            autogenerate_code_verifier=False,
        )
        flow.fetch_token(authorization_response=args.resume_url)
        token_path.parent.mkdir(parents=True, exist_ok=True)
        token_path.write_text(flow.credentials.to_json(), encoding="utf-8")
        token_path.chmod(0o600)
        pending_path.unlink(missing_ok=True)
        print(f"saved: {token_path}")
        return

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
    _write_pending(
        pending_path,
        client_config=data,
        redirect_uri=args.redirect_uri,
        state=_state,
        code_verifier=flow.code_verifier or "",
    )
    print("Open this URL:")
    print(auth_url)
    print("")
    print("After approval, paste the final localhost URL here.")
    redirected = input("redirect URL> ").strip()
    flow.fetch_token(authorization_response=redirected)

    token_path.parent.mkdir(parents=True, exist_ok=True)
    token_path.write_text(flow.credentials.to_json(), encoding="utf-8")
    token_path.chmod(0o600)
    pending_path.unlink(missing_ok=True)
    print(f"saved: {token_path}")


if __name__ == "__main__":
    main()
