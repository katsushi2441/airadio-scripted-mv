#!/usr/bin/env python3

import sys
import json
import subprocess
import base64
from pathlib import Path

def get_audio_duration(audio_path):
    result = subprocess.run(
        [
            "ffprobe",
            "-v", "error",
            "-show_entries", "format=duration",
            "-of", "default=noprint_wrappers=1:nokey=1",
            str(audio_path)
        ],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    return float(result.stdout.strip())

def save_base64_image(data_uri, index, work_dir):

    header, encoded = data_uri.split(",", 1)

    ext = ".png"
    if "jpeg" in header or "jpg" in header:
        ext = ".jpg"

    img_path = work_dir / f"_frame_{index}{ext}"

    with img_path.open("wb") as f:
        f.write(base64.b64decode(encoded))

    return img_path

def main():

    if len(sys.argv) != 2:
        print("Usage: python3 genkamishibai.py <base_path_without_ext>")
        sys.exit(1)

    base = Path(sys.argv[1])

    json_path = base.with_suffix(".json")
    mp3_path  = base.with_suffix(".mp3")
    mp4_path  = base.with_suffix(".mp4")

    with json_path.open("r", encoding="utf-8") as f:
        data = json.load(f)

    slides = data.get("slides", [])
    bgm_start = float(data.get("bgm_start", 0))

    total_duration = get_audio_duration(mp3_path) - bgm_start
    per_duration = total_duration / len(slides)

    work_dir = json_path.parent

    input_parts = []
    filter_parts = []
    concat_parts = ""

    for i, slide in enumerate(slides):

        img_path = save_base64_image(slide["image"], i, work_dir)

        input_parts += [
            "-loop", "1",
            "-t", str(per_duration),
            "-i", str(img_path)
        ]

        text = slide.get("text", "").replace("'", r"\'")

        filter_parts.append(
            f"[{i}:v]"
            f"scale=1280:720:force_original_aspect_ratio=decrease,"
            f"pad=1280:720:(ow-iw)/2:(oh-ih)/2,"
            f"drawtext=text='{text}':"
            f"fontsize=36:"
            f"fontcolor=white:"
            f"borderw=2:"
            f"bordercolor=black:"
            f"x=(w-text_w)/2:"
            f"y=h*0.9-(t/{total_duration})*(h*0.9+text_h)"
            f"[v{i}]"
        )

        concat_parts += f"[v{i}]"

    filter_complex = ";".join(filter_parts)
    filter_complex += f";{concat_parts}concat=n={len(slides)}:v=1:a=0[vout]"

    cmd = [
        "ffmpeg",
        "-y",
        *input_parts,
        "-ss", str(bgm_start),
        "-i", str(mp3_path),
        "-filter_complex", filter_complex,
        "-map", "[vout]",
        "-map", f"{len(slides)}:a",
        "-shortest",
        "-preset", "veryfast",
        str(mp4_path)
    ]

    subprocess.run(cmd)

if __name__ == "__main__":
    main()

