#!/usr/bin/env python3

import requests
import subprocess
from pathlib import Path
from ftplib import FTP

WEB_BASE = "https://airadio-scripted-mv.exbridge.jp"
PROJECT_API = WEB_BASE + "/project.php"
TOKEN = "YOUR_SECRET_TOKEN"

FTP_HOST = "ftp-exbridge.heteml.net"
FTP_USER = "exbridge"
FTP_PASS = "Xbrg20042025"
FTP_DIR  = "/web/airadio-scripted-mv_exbridge_jp/projects/"

WORK_DIR = Path("work")
WORK_DIR.mkdir(exist_ok=True)

def download(project_name):

    json_url = f"{WEB_BASE}/projects/{project_name}.json"
    mp3_url  = f"{WEB_BASE}/projects/{project_name}.mp3"

    json_path = WORK_DIR / f"{project_name}.json"
    mp3_path  = WORK_DIR / f"{project_name}.mp3"

    print("Downloading:", project_name)

    json_path.write_bytes(requests.get(json_url).content)
    mp3_path.write_bytes(requests.get(mp3_url).content)

    return WORK_DIR / project_name

def upload(project_name):

    mp4_path = WORK_DIR / f"{project_name}.mp4"

    print("Uploading via FTP:", project_name)

    ftp = FTP(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)
    ftp.set_pasv(True)

    ftp.cwd(FTP_DIR)

    with open(mp4_path, "rb") as f:
        ftp.storbinary(f"STOR {project_name}.mp4", f)

    ftp.quit()

    print("FTP upload complete")

def main():

    r = requests.get(PROJECT_API, params={"token": TOKEN})
    data = r.json()

    for p in data.get("projects", []):

        name = p["name"]

        if p["json"] and p["mp3"] and not p["mp4"]:

            base = download(name)

            subprocess.run([
                 "python3",
                 "genkamishibai.py",
                str(base)
            ])

            upload(name)

        else:
            print("Skip:", name)

if __name__ == "__main__":
    main()

