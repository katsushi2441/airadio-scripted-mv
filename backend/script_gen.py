from __future__ import annotations

import json
import re

import requests

from config import OLLAMA_MODEL, OLLAMA_URL


SYSTEM_PROMPT = """You are a Japanese music video visual director.
Create exactly __SCENE_COUNT__ visual scenes for a vertical lyric music video.

Return ONLY JSON. No markdown.

Required format:
{"title":"MV title in Japanese under 30 chars","mood":"overall mood","scenes":[{"index":0,"visual":"日本語の映像意図","image_prompt":"English vertical 9:16 cinematic music video background, no text, no subtitles","duration":5}]}

Rules:
- Create exactly __SCENE_COUNT__ scenes.
- image_prompt must be English.
- Do not include text, lyrics, captions, subtitles, letters, logos, or watermarks in images.
- Use the lyrics only to infer mood, story, colors, locations, and emotional progression.
- Keep every image_prompt under 120 characters.
"""


def parse_json_from_response(text: str) -> dict:
    text = text.strip()
    text = re.sub(r"^```(?:json)?\\s*", "", text, flags=re.MULTILINE)
    text = re.sub(r"```\\s*$", "", text, flags=re.MULTILINE).strip()
    try:
        return json.loads(text)
    except Exception:
        pass
    start = text.find("{")
    if start >= 0:
        depth = 0
        for i, ch in enumerate(text[start:], start):
            if ch == "{":
                depth += 1
            elif ch == "}":
                depth -= 1
                if depth == 0:
                    return json.loads(text[start : i + 1])
    raise ValueError(f"Could not parse JSON: {text[:300]}")


def fallback_script(filename: str, scene_count: int, mood: str = "lyrical, cinematic") -> dict:
    title = re.sub(r"\.[^.]+$", "", filename).strip() or "Lyrics MV"
    palettes = [
        ("blue night city lights", "lonely neon street after rain"),
        ("soft dawn sky", "quiet room with morning light"),
        ("misty seaside", "wide ocean horizon with gentle fog"),
        ("empty train platform", "cinematic railway platform at dusk"),
        ("floating particles", "dreamlike light particles in dark space"),
        ("silhouette walking", "single silhouette walking through soft backlight"),
    ]
    scenes = []
    for i in range(scene_count):
        color, place = palettes[i % len(palettes)]
        scenes.append({
            "index": i,
            "visual": f"{title} の歌詞に合わせた感情的な縦型MV背景。シーン{i + 1}。",
            "image_prompt": f"vertical 9:16 cinematic music video background, {place}, {color}, no text",
            "duration": 5,
        })
    return {"title": title, "mood": mood, "scenes": scenes}


def generate_mv_script(lyrics_text: str, filename: str = "", scene_count: int = 1) -> dict:
    system_prompt = SYSTEM_PROMPT.replace("__SCENE_COUNT__", str(scene_count))
    prompt = f"""{system_prompt}

Song file: {filename}

Lyrics:
{lyrics_text[:6000]}

Create visual direction JSON only."""

    payload = {
        "model": OLLAMA_MODEL,
        "prompt": prompt,
        "stream": False,
        "format": "json",
        "options": {"temperature": 0.45, "num_predict": 8192},
    }
    resp = requests.post(f"{OLLAMA_URL}/api/generate", json=payload, timeout=240)
    resp.raise_for_status()
    response_text = resp.json().get("response") or ""
    try:
        script = parse_json_from_response(response_text)
    except Exception:
        return fallback_script(filename, scene_count)
    scenes = script.get("scenes") or []
    if len(scenes) < scene_count:
        scenes.extend(fallback_script(filename, scene_count - len(scenes)).get("scenes") or [])
    scenes = scenes[:scene_count]
    script["scenes"] = scenes
    for i, scene in enumerate(scenes):
        scene["index"] = i
        scene.setdefault("duration", 5)
        scene.setdefault("image_prompt", "vertical 9:16 cinematic music video background, no text")
    return script
