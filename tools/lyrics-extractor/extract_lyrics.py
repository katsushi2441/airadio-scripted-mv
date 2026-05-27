#!/usr/bin/env python3
import argparse
import json
import shutil
import subprocess
import sys
from datetime import datetime
from pathlib import Path


def run(cmd):
    print("+ " + " ".join(str(c) for c in cmd), flush=True)
    subprocess.run(cmd, check=True)


def srt_time(seconds):
    ms = int(round(seconds * 1000))
    h, ms = divmod(ms, 3600_000)
    m, ms = divmod(ms, 60_000)
    s, ms = divmod(ms, 1000)
    return f"{h:02}:{m:02}:{s:02},{ms:03}"


def lrc_time(seconds):
    cs = int(round(seconds * 100))
    m, cs = divmod(cs, 6000)
    s, cs = divmod(cs, 100)
    return f"[{m:02}:{s:02}.{cs:02}]"


def write_outputs(segments, out_dir):
    srt_lines = []
    lrc_lines = []
    txt_lines = []

    for i, seg in enumerate(segments, 1):
        text = " ".join((seg.text or "").strip().split())
        if not text:
            continue
        srt_lines += [str(i), f"{srt_time(seg.start)} --> {srt_time(seg.end)}", text, ""]
        lrc_lines.append(f"{lrc_time(seg.start)}{text}")
        txt_lines.append(text)

    (out_dir / "lyrics.srt").write_text("\n".join(srt_lines), encoding="utf-8")
    (out_dir / "lyrics.lrc").write_text("\n".join(lrc_lines) + "\n", encoding="utf-8")
    (out_dir / "lyrics.txt").write_text("\n".join(txt_lines) + "\n", encoding="utf-8")


def pick_device(device):
    if device != "auto":
        return device
    try:
        import torch

        return "cuda" if torch.cuda.is_available() else "cpu"
    except Exception:
        return "cpu"


def main():
    parser = argparse.ArgumentParser(description="Extract vocals with Demucs and create SRT/LRC lyrics with faster-whisper.")
    parser.add_argument("audio", help="Input audio file: mp3, wav, m4a, flac, ogg")
    parser.add_argument("--output-dir", default="storage/lyrics/outputs/manual", help="Output directory")
    parser.add_argument("--work-dir", default="storage/lyrics/work", help="Temporary Demucs work directory")
    parser.add_argument("--model", default="small", help="faster-whisper model size: tiny/base/small/medium/large-v3")
    parser.add_argument("--language", default=None, help="Language code, e.g. ja or en. Auto detect if omitted.")
    parser.add_argument("--device", default="auto", choices=["auto", "cpu", "cuda"], help="Whisper device")
    parser.add_argument("--compute-type", default="auto", help="faster-whisper compute type")
    parser.add_argument("--keep-work", action="store_true", help="Keep Demucs intermediate files")
    args = parser.parse_args()

    audio = Path(args.audio).expanduser().resolve()
    if not audio.exists():
        raise SystemExit(f"input not found: {audio}")

    out_dir = Path(args.output_dir).expanduser().resolve()
    work_dir = Path(args.work_dir).expanduser().resolve()
    out_dir.mkdir(parents=True, exist_ok=True)
    work_dir.mkdir(parents=True, exist_ok=True)

    demucs_out = work_dir / out_dir.name
    if demucs_out.exists():
        shutil.rmtree(demucs_out)

    run([sys.executable, "-m", "demucs", "--two-stems=vocals", "-n", "htdemucs", "-o", str(demucs_out), str(audio)])

    vocals_candidates = list(demucs_out.glob(f"htdemucs/{audio.stem}/vocals.wav"))
    if not vocals_candidates:
        vocals_candidates = list(demucs_out.glob("**/vocals.wav"))
    if not vocals_candidates:
        raise SystemExit("Demucs completed, but vocals.wav was not found.")

    vocals = vocals_candidates[0]
    final_vocals = out_dir / "vocals.wav"
    shutil.copy2(vocals, final_vocals)

    try:
        from faster_whisper import WhisperModel
    except Exception as exc:
        raise SystemExit(
            "faster-whisper is not installed. Run: python3 -m pip install -r tools/lyrics-extractor/requirements.txt"
        ) from exc

    device = pick_device(args.device)
    compute_type = args.compute_type
    if compute_type == "auto":
        compute_type = "float16" if device == "cuda" else "int8"

    model = WhisperModel(args.model, device=device, compute_type=compute_type)
    segments_iter, info = model.transcribe(
        str(final_vocals),
        language=args.language,
        vad_filter=False,
        beam_size=5,
    )
    segments = list(segments_iter)
    write_outputs(segments, out_dir)

    meta = {
        "input": str(audio),
        "output_dir": str(out_dir),
        "vocals": str(final_vocals),
        "model": args.model,
        "device": device,
        "compute_type": compute_type,
        "language": getattr(info, "language", None),
        "language_probability": getattr(info, "language_probability", None),
        "segments": len(segments),
        "created_at": datetime.now().isoformat(timespec="seconds"),
    }
    (out_dir / "metadata.json").write_text(json.dumps(meta, ensure_ascii=False, indent=2), encoding="utf-8")

    if not args.keep_work:
        shutil.rmtree(demucs_out, ignore_errors=True)

    print(json.dumps(meta, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
