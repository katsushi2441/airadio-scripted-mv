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
        "options": {"temperature": 0.65, "num_predict": 3072},
    }
    resp = requests.post(f"{OLLAMA_URL}/api/generate", json=payload, timeout=240)
    resp.raise_for_status()
    response_text = resp.json().get("response") or ""
    script = parse_json_from_response(response_text)
    scenes = script.get("scenes") or []
    if len(scenes) != scene_count:
        raise ValueError(f"Script must have {scene_count} scenes, got {len(scenes)}")
    for i, scene in enumerate(scenes):
        scene["index"] = i
        scene.setdefault("duration", 5)
        scene.setdefault("image_prompt", "vertical 9:16 cinematic music video background, no text")
    return script
