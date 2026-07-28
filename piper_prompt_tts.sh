#!/bin/bash
# Piper TTS wrapper for AllStar
# Uses native ASL3 asl3-tts piper (/usr/bin/piper + /var/lib/piper-tts)
# Generates ONLY .wav in /mp3 (8 kHz mono)

TEXT="$1"
OUTPUT_NAME="$2"
VOICE="$3"   # third argument = voice model path or filename

if [ -z "$TEXT" ] || [ -z "$OUTPUT_NAME" ]; then
    echo "Usage: $0 \"Text to speak\" output_filename [voice_model]"
    exit 1
fi

# Default voice if none provided (ASL3 default Amy low)
if [ -z "$VOICE" ]; then
    VOICE="/var/lib/piper-tts/en_US-amy-low.onnx"
fi

# If only a filename was given (no leading /), prepend the standard voices dir
if [[ "$VOICE" != /* ]]; then
    VOICE="/var/lib/piper-tts/$VOICE"
fi

PIPER_BIN="/usr/bin/piper"
OUT_DIR="/mp3"

# Ensure output directory exists
if [ ! -d "$OUT_DIR" ]; then
    echo "ERROR: $OUT_DIR does not exist"
    exit 1
fi

# Ensure the voice model exists
if [ ! -f "$VOICE" ]; then
    echo "ERROR: Voice model not found: $VOICE"
    exit 1
fi

TMP_WAV="/tmp/${OUTPUT_NAME}_tmp.wav"
FINAL_WAV="${OUT_DIR}/${OUTPUT_NAME}.wav"

# Generate speech with selected voice
echo "$TEXT" | "$PIPER_BIN" --model "$VOICE" --output_file "$TMP_WAV"

# Convert to 8 kHz mono WAV for AllStar
sox "$TMP_WAV" -r 8000 -c 1 "$FINAL_WAV"

# Cleanup
rm -f "$TMP_WAV"

echo "Generated WAV: $FINAL_WAV (using voice: $VOICE)"
