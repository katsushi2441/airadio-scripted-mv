from __future__ import annotations

import base64
import time
from pathlib import Path

import requests

from config import ERNIE_URL


def generate_image(prompt: str, output_path: Path, width: int = 576, height: int = 1024) -> Path:
    payload = {
        "prompt": prompt,
        "negative_prompt": (
            "text, watermark, logo, lyrics, subtitles, bad anatomy, blurry, low quality, "
            "horror, gore, grotesque, uncanny"
        ),
        "width": width,
        "height": height,
        "num_inference_steps": 4,
        "guidance_scale": 1.0,
        "use_pe": False,
        "output_format": "png",
    }
    resp = requests.post(
        ERNIE_URL,
        json=payload,
        timeout=300,
        headers={"Accept": "application/json", "Content-Type": "application/json"},
    )
    resp.raise_for_status()
    data = resp.json()
    image_b64 = data.get("image_base64") or ""
    if not image_b64:
        raise RuntimeError(f"No image_base64 in ERNIE response: {data}")
    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_bytes(base64.b64decode(image_b64))
    return output_path


def generate_scene_images(scenes: list[dict], job_dir: Path) -> list[Path]:
    assets_dir = job_dir / "assets"
    assets_dir.mkdir(parents=True, exist_ok=True)
    paths: list[Path] = []
    for scene in scenes:
        idx = int(scene.get("index", len(paths)))
        out = assets_dir / f"scene_{idx:02d}.png"
        if out.exists() and out.stat().st_size > 0:
            paths.append(out)
            continue
        prompt = scene.get("image_prompt") or "cinematic vertical 9:16 music video background"
        if idx > 0:
            time.sleep(3)
        paths.append(generate_image(prompt, out))
    return paths
