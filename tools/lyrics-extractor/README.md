# Lyrics Extractor

MP3などの曲ファイルから、Demucsでボーカルを分離し、faster-whisperで歌詞のSRT/LRCを生成します。

## Install

```bash
cd /home/kojima/exdirect/airadio-scripted-mv
python3 -m pip install -r tools/lyrics-extractor/requirements.txt
```

## CLI

```bash
python3 tools/lyrics-extractor/extract_lyrics.py input.mp3 \
  --output-dir storage/lyrics/outputs/test01 \
  --model small \
  --language ja
```

生成物:

- `vocals.wav`
- `lyrics.srt`
- `lyrics.lrc`
- `lyrics.txt`
- `metadata.json`

## Web

`lyrics-extractor.php` からMP3をアップロードして実行できます。

歌は通常の会話音声より認識が難しいため、生成後の歌詞は人間の確認・修正を前提にします。
