
---

## Relationship to AIRadio

This repository provides AIRadio-related web tools.

The current main product is **Kurageプロジェクト AIRadio Scripted-MV**:

- Upload a music file from the web UI
- Queue the analysis on the RTX/FastAPI server
- Separate vocals with Demucs
- Generate SRT/LRC/TXT with faster-whisper
- Generate a visual script from lyrics
- Generate images at roughly one image per 10 seconds
- Render a lyric-subtitled music video with HyperFrames
- Publish finished videos, lyrics, and metadata to the web server

Each component can be used independently, but together they form a complete
AI media pipeline.

---

## Use Cases

- Narrated lyric videos
- Shareable AI audio content for social platforms
- Lyric extraction from music files using Demucs + faster-whisper

---

## Philosophy

This project is part of the AIRadio ecosystem.  It is intentionally split
into a small web front end, a Python backend, and reusable processing tools.

## Directory Layout

```text
public/          Web公開PHP
backend/         FastAPI queue and job runner
tools/           CLI processing tools
storage/         Local jobs, uploads, and generated files
docs/            Documentation
deploy/          Deployment helpers
```

---

## Lyrics MV Generator

`public/scripted-mv.php` provides a browser upload flow for music files.
The web page posts the audio to the FastAPI backend on the RTX server,
queues the job, and then shows progress until `vocals.wav`, `lyrics.srt`,
`lyrics.lrc`, `lyrics.txt`, and `lyrics_mv.mp4` are ready.

`public/scripted-mvv.php` is the public viewer. It reads only web-server files:

- `videos/{job_id}/lyrics_mv.mp4`
- `videos/{job_id}/lyrics.lrc`
- `videos/{job_id}/lyrics.txt`
- `data/scripted_mv_videos.json`

CLI usage is documented in:

- [tools/lyrics-extractor/README.md](tools/lyrics-extractor/README.md)

FastAPI backend:

```bash
cd /home/kojima/exdirect/airadio-scripted-mv/backend
uvicorn main:app --host 0.0.0.0 --port 18201
```

Web deployment uploads the contents of `public/` to:

```text
/web/airadio-scripted-mv_exbridge_jp
```

---

## Demo

This project is currently available at:
https://airadio-scripted-mv.exbridge.jp/

---

## License

MIT License
