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
        "index.html",
        "scripted-mv.php",
        "scripted-mvv.php",
        "auth_common.php",
        "config.php",
        "assets/kurage-icon.png",
        "assets/kurage.png",
        "assets/scripted-mv-ogp.png",
        "assets/scripted-mv-icon.png",
        "assets/scripted-mv-icon-512.png",
        "assets/scripted-mv-icon-256.png",
    ]
    with ftplib.FTP(host, timeout=60) as ftp:
        ftp.login(user, password)
        for name in files:
            if "/" in name:
                parts = name.split("/")[:-1]
                path = REMOTE_ROOT
                for part in parts:
                    path += "/" + part
                    try:
                        ftp.mkd(path)
                    except Exception:
                        pass
            upload_file(ftp, PUBLIC / name, REMOTE_ROOT + "/" + name)


if __name__ == "__main__":
    main()
