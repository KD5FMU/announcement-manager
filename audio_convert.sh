#!/bin/bash
#
# audio_convert.sh - Convert audio file to ulaw .ul with optional leading pause
#
# Usage: audio_convert.sh input_file output_file.ul [pause_seconds]
#
# - output_file is required (announcement.php always supplies the full path)
# - pause_seconds: optional number of seconds of silence to add at the start (default 0)
#
# Requires sox (apt install sox libsox-fmt-mp3)
# Written to be robust under both bash and when called via sudo from PHP
# Sets final permissions itself so www-data does not need chmod/chown sudo

INPUT_FILE="$1"
OUTPUT_FILE="$2"
PAUSE_SECONDS="${3:-0}"

if [ -z "$INPUT_FILE" ] || [ -z "$OUTPUT_FILE" ]; then
    echo "Usage: $0 input_file output_file.ul [pause_seconds]"
    echo "Example: $0 /mp3/announcement.wav /usr/local/share/asterisk/sounds/announcements/announcement.ul 1.5"
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

# Validate pause is a number (POSIX-friendly)
case "$PAUSE_SECONDS" in
    ''|*[!0-9.]* )
        echo "ERROR: pause_seconds must be a number (e.g. 0, 1, 1.5)"
        exit 1
        ;;
esac

if [ "$(echo "$PAUSE_SECONDS > 0" | bc -l 2>/dev/null || echo 0)" = "1" ]; then
    TEMP_SILENCE=$(mktemp --suffix=.wav)
    sox -n -r 8000 -c 1 -e u-law "$TEMP_SILENCE" trim 0 "$PAUSE_SECONDS"
    sox "$TEMP_SILENCE" "$INPUT_FILE" -t raw -r 8000 -c 1 -e u-law "$OUTPUT_FILE"
    RC=$?
    rm -f "$TEMP_SILENCE"
else
    sox "$INPUT_FILE" -t raw -r 8000 -c 1 -e u-law "$OUTPUT_FILE"
    RC=$?
fi

if [ $RC -eq 0 ] && [ -f "$OUTPUT_FILE" ]; then
    # Set safe ownership + permissions so www-data can later delete the file
    # (no need for www-data to have chmod/chown/rm sudo rights)
    chown www-data:www-data "$OUTPUT_FILE" 2>/dev/null || true
    chmod 644 "$OUTPUT_FILE"
    # Leave ownership as root (or the user that ran sudo). Asterisk can read world-readable files.
    echo "Conversion successful: $OUTPUT_FILE"
    if [ "$(echo "$PAUSE_SECONDS > 0" | bc -l 2>/dev/null || echo 0)" = "1" ]; then
        echo "Added ${PAUSE_SECONDS} second pause at the beginning."
    fi
    exit 0
else
    echo "Conversion failed (exit $RC)"
    exit 1
fi
