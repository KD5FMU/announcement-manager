#!/bin/bash
#
# audio_convert.sh - clean version
# Usage: audio_convert.sh input_file output_file.ul [pause_seconds]

INPUT_FILE="$1"
OUTPUT_FILE="$2"
PAUSE_SECONDS="${3:-0}"

if [ -z "$INPUT_FILE" ] || [ -z "$OUTPUT_FILE" ]; then
    echo "Usage: $0 input_file output_file.ul [pause_seconds]"
    exit 1
fi

if [ ! -f "$INPUT_FILE" ]; then
    echo "ERROR: Input file not found: $INPUT_FILE"
    exit 1
fi

if ! command -v sox >/dev/null 2>&1; then
    echo "ERROR: sox not found"
    exit 1
fi

# Validate pause is a number
case "$PAUSE_SECONDS" in
    ''|*[!0-9.]* ) echo "ERROR: pause_seconds must be a number"; exit 1 ;;
esac

if [ "$(echo "$PAUSE_SECONDS > 0" | bc -l 2>/dev/null || echo 0)" = "1" ]; then
    TEMP=$(mktemp --suffix=.wav)
    sox -n -r 8000 -c 1 -e u-law "$TEMP" trim 0 "$PAUSE_SECONDS"
    sox "$TEMP" "$INPUT_FILE" -t raw -r 8000 -c 1 -e u-law "$OUTPUT_FILE"
    RC=$?
    rm -f "$TEMP"
else
    sox "$INPUT_FILE" -t raw -r 8000 -c 1 -e u-law "$OUTPUT_FILE"
    RC=$?
fi

if [ $RC -eq 0 ] && [ -f "$OUTPUT_FILE" ]; then
    echo "Conversion successful: $OUTPUT_FILE"
    exit 0
else
    echo "Conversion failed (exit $RC)"
    exit 1
fi
EOF
