
---

## Relationship to AIRadio

This repository is an **optional plugin for AIRadio Core**.

AIRadio handles:
- Script generation
- Text-to-speech
- Audio creation

This plugin handles:
- Video footage preparation
- Script-based music video generation

Each component can be used independently,  
but together they form a complete AI media pipeline.

---

## Use Cases

- AI-generated music videos
- Narrated lyric videos
- Message-driven short videos
- Shareable AI audio content for social platforms
- Lyric extraction from music files using Demucs + faster-whisper

---

## Philosophy

This project is part of the AIRadio ecosystem.

Instead of a single monolithic tool,  
AIRadio is built as a set of small, composable plugins.

Each plugin:
- Has a clear responsibility
- Can evolve independently
- Gains more value when combined with others

Future updates will focus on expanding subtitle customization,
including font size, positioning, and visual layout templates.

---

## Lyrics Extractor

`lyrics-extractor.php` provides a browser upload flow for music files.
The web page posts the audio to the FastAPI backend on the RTX server,
queues the job, and then shows progress until `vocals.wav`, `lyrics.srt`,
`lyrics.lrc`, and `lyrics.txt` are ready.

CLI usage is documented in:

- [tools/lyrics-extractor/README.md](tools/lyrics-extractor/README.md)

FastAPI backend:

```bash
cd /home/kojima/exdirect/airadio-scripted-mv/backend
uvicorn main:app --host 0.0.0.0 --port 18201
```

---

## Demo

This project is currently demonstrated at:
https://airadio-scripted-mv.exbridge.jp/

A demo video showing the full pipeline is also available on YouTube.

---

## Whitepaper

- [AIRadio Scripted MV Whitepaper](docs/whitepaper.md)



---

## License

MIT License
