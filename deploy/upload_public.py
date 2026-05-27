#!/usr/bin/env python3
import ftplib
import os
from pathlib import Path


ROOT = Path(__file__).resolve().parent.parent
PUBLIC = ROOT / "public"
REMOTE_ROOT = "/web/airadio-scripted-mv_exbridge_jp"


def upload_file(ftp: ftplib.FTP, local: Path, remote: str) -> None:
    with local.open("rb") as fh:
        ftp.storbinary("STOR " + remote, fh)
    print("uploaded", remote)


def main() -> None:
    host = os.environ["FTP_HOST"]
    user = os.environ["FTP_USER"]
    password = os.environ["FTP_PASS"]
    files = [
        ".htaccess",
        "index.php",
        "lyrics-extractor.php",
        "auth_common.php",
        "config.php",
    ]
    with ftplib.FTP(host, timeout=60) as ftp:
        ftp.login(user, password)
        for name in files:
            upload_file(ftp, PUBLIC / name, REMOTE_ROOT + "/" + name)


if __name__ == "__main__":
    main()
