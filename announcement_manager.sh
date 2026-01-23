#!/usr/bin/env bash
#
# setup-supermon-announcements.sh
# Fully automates Supermon Announcements Manager setup:
# - Installs required packages: sox + libsox-fmt-mp3 (for MP3 support) + git (for repo cloning)
# - Copies files from GitHub to /var/www/html/supermon/custom/
# - Installs prerequisite scripts: playaudio.sh & audio_convert.sh in /etc/asterisk/local/
#   (embedded exact contents from KD5FMU's GitHub repos with proper credit)
# - Prompts user for their AllStar node number and auto-configures playaudio.sh
# - Creates /mp3 directory with correct permissions (2775, setgid)
# - Automatically grants access to the invoking user
# - Sets ownership & permissions on files
# - Modifies /var/www/html/supermon/link.php: replaces everything after the last </div>
#   with the new announcement + footer includes (idempotent)
# - Safe & idempotent (can run multiple times)
#
# Run as root: sudo bash setup-supermon-announcements.sh
# Author: N5AD - January 2026 (updated)

set -euo pipefail

# ────────────────────────────────────────────────
# CONFIG
# ────────────────────────────────────────────────

REPO_URL="https://github.com/n5ad/Supermon-7.4-announcement-creation-and-management-of-cron.git"
TEMP_CLONE="/tmp/supermon-announcements"
TARGET_DIR="/var/www/html/supermon/custom"
LINK_PHP="/var/www/html/supermon/link.php"
MP3_DIR="/mp3"
LOCAL_DIR="/etc/asterisk/local"

# ────────────────────────────────────────────────
# Helpers
# ────────────────────────────────────────────────

echo_step() { echo -e "\n\033[1;34m==>\033[0m $1"; }
warn() { echo -e "\033[1;33mWARNING:\033[0m $1" >&2; }
error() { echo -e "\033[1;31mERROR:\033[0m $1" >&2; exit 1; }

check_root() { [[ $EUID -eq 0 ]] || error "Run as root (sudo)."; }

# ────────────────────────────────────────────────
# Main
# ────────────────────────────────────────────────

check_root

echo ""
echo "Supermon Announcements Manager - Full Setup"
echo "──────────────────────────────────────────────"
echo "GitHub Repo: $REPO_URL"
echo "Target dir: $TARGET_DIR"
echo "MP3 dir: $MP3_DIR"
echo "Local scripts dir: $LOCAL_DIR"
echo "link.php location: $LINK_PHP"
echo ""

echo -n "Continue setup? (y/N) "
read -r answer
[[ "$answer" =~ ^[Yy]$ ]] || { echo "Aborted."; exit 0; }

# Prompt for AllStar node number
echo ""
echo_step "Enter your AllStar node number"
echo -n "Node number (e.g., 12345): "
read -r NODE_NUMBER

# Basic validation: must be digits only
if [[ ! "$NODE_NUMBER" =~ ^[0-9]+$ ]]; then
    error "Invalid node number! Please enter digits only (e.g., 12345)."
fi

echo "Using node number: $NODE_NUMBER"
echo ""

# 0. Install required packages (sox + MP3 support + git)
echo_step "0. Installing required packages"
PACKAGES_TO_INSTALL=""
if ! command -v sox >/dev/null 2>&1; then
    PACKAGES_TO_INSTALL="$PACKAGES_TO_INSTALL sox"
fi
if ! dpkg -l | grep -q libsox-fmt-mp3; then
    PACKAGES_TO_INSTALL="$PACKAGES_TO_INSTALL libsox-fmt-mp3"
fi
if ! command -v git >/dev/null 2>&1; then
    PACKAGES_TO_INSTALL="$PACKAGES_TO_INSTALL git"
fi

if [[ -n "$PACKAGES_TO_INSTALL" ]]; then
    echo "Installing missing packages: $PACKAGES_TO_INSTALL"
    apt update && apt install -y $PACKAGES_TO_INSTALL || error "Failed to install packages. Check your internet/apt sources."
    echo "Packages installed successfully."
else
    echo "sox, libsox-fmt-mp3, and git are already installed – skipping."
fi

# 1. Clone repo
echo_step "1. Cloning GitHub repo"
rm -rf "$TEMP_CLONE"
git clone --depth 1 "$REPO_URL" "$TEMP_CLONE" || error "Git clone failed"

# 2. Copy PHP & inc files
echo_step "2. Copying files to $TARGET_DIR"
mkdir -p "$TARGET_DIR"
cp -v "$TEMP_CLONE"/*.{php,inc} "$TARGET_DIR"/ 2>/dev/null || warn "No .php/.inc files found"
rm -rf "$TEMP_CLONE"

# 3. Create /mp3 dir + permissions
echo_step "3. Creating /mp3 directory"
mkdir -p "$MP3_DIR"

# Automatically detect the user who invoked sudo
MP3_USER="${SUDO_USER:-$(whoami)}"
echo "Granting /mp3 access to user: $MP3_USER"

# Add user to www-data group if not already a member
if id -nG "$MP3_USER" | grep -qw "www-data"; then
    echo "$MP3_USER is already in www-data group"
else
    echo "Adding $MP3_USER to www-data group"
    usermod -aG www-data "$MP3_USER"
fi

# Set ownership & permissions on /mp3 (setgid so new files inherit group)
chown -R www-data:www-data "$MP3_DIR"
chmod -R 2775 "$MP3_DIR"

echo "MP3 directory permissions set with setgid. $MP3_USER can now access /mp3."

# 4. Set ownership & permissions on custom files
echo_step "4. Setting ownership & permissions"
chown -R www-data:www-data "$TARGET_DIR"
find "$TARGET_DIR" -type f -name "*.php" -exec chmod 644 {} \;
find "$TARGET_DIR" -type f -name "*.inc" -exec chmod 644 {} \;

# 4.5. Install prerequisite scripts in /etc/asterisk/local/ (if missing)
echo_step "4.5. Installing prerequisite scripts in $LOCAL_DIR"

mkdir -p "$LOCAL_DIR"
chown asterisk:asterisk "$LOCAL_DIR" 2>/dev/null || chown root:root "$LOCAL_DIR"
chmod 755 "$LOCAL_DIR"

# ----- playaudio.sh -----
# Credit: Original from https://github.com/KD5FMU/Play-Audio-ASL3-Node
# Author: KD5FMU - Embedded here with permission/credit
PLAY_SCRIPT="$LOCAL_DIR/playaudio.sh"

if [[ ! -f "$PLAY_SCRIPT" ]]; then
    echo "Creating $PLAY_SCRIPT (missing)"
    cat > "$PLAY_SCRIPT" << EOF
#!/bin/bash
#
# playaudio.sh – Play an audio file over an AllStarLink v3 node (Debian 12)
# Original Author: KD5FMU
# Source: https://github.com/KD5FMU/Play-Audio-ASL3-Node/blob/main/playaudio.sh
# Embedded in setup-supermon-announcements.sh by N5AD - Jan 2026

NODE="$NODE_NUMBER"

# Require root to talk to asterisk.ctl
if [ "\$EUID" -ne 0 ]; then
    echo "This script must be run with sudo or as root."
    exit 1
fi

if [ -z "\$1" ]; then
    echo "Usage: \$0 <audio_file_without_extension>"
    exit 1
fi

/usr/sbin/asterisk -rx "rpt localplay \${NODE} \$1"
EOF

    chmod +x "$PLAY_SCRIPT"
    chown asterisk:asterisk "$PLAY_SCRIPT" 2>/dev/null || chown root:root "$PLAY_SCRIPT"
    chmod 755 "$PLAY_SCRIPT"
    echo "Created $PLAY_SCRIPT with your node number: $NODE_NUMBER"
else
    echo "$PLAY_SCRIPT already exists – skipping creation (node number not updated automatically)"
    echo "If you need to change the node number, edit the NODE= line manually."
fi

# ----- audio_convert.sh -----
# Credit: Original from https://github.com/KD5FMU/Convert-Audio-File-to-ulaw
# Author: KD5FMU
# Source: https://github.com/KD5FMU/Convert-Audio-File-to-ulaw/blob/main/audio_convert.sh
# Embedded in setup-supermon-announcements.sh by N5AD - Jan 2026
CONVERT_SCRIPT="$LOCAL_DIR/audio_convert.sh"

if [[ ! -f "$CONVERT_SCRIPT" ]]; then
    echo "Creating $CONVERT_SCRIPT (missing)"
    cat > "$CONVERT_SCRIPT" << 'EOF'
#!/bin/bash
#
# audio_convert.sh - Convert audio file to ulaw .ul
# Original Author: KD5FMU
# Source: https://github.com/KD5FMU/Convert-Audio-File-to-ulaw/blob/main/audio_convert.sh
# Embedded in setup-supermon-announcements.sh by N5AD - Jan 2026
#
# Usage: audio_convert.sh input_file [output_file.ul]
#
# If output_file is not specified, it will be named the same as input_file but with .ul extension
# Requires sox (install with apt install sox libsox-fmt-mp3)

if [ $# -lt 1 ]; then
    echo "Usage: $0 [input_file] [output_file.ul]"
    exit 1
fi

# Input file
INPUT_FILE="$1"

# Output file (optional second argument, defaults to input filename with .ul extension)
OUTPUT_FILE="${2:-${INPUT_FILE%.*}.ul}"

# Convert the audio file to 8000Hz, 16-bit, mono, raw u-law format
sox "$INPUT_FILE" -t raw -r 8000 -c 1 -e u-law "$OUTPUT_FILE"

# Check if conversion was successful
if [ $? -eq 0 ]; then
    echo "Conversion successful!"
    echo "Output file: $OUTPUT_FILE"
else
    echo "Error: Conversion failed."
fi
EOF

    chmod +x "$CONVERT_SCRIPT"
    chown asterisk:asterisk "$CONVERT_SCRIPT" 2>/dev/null || chown root:root "$CONVERT_SCRIPT"
    chmod 755 "$CONVERT_SCRIPT"
    echo "Created $CONVERT_SCRIPT"
else
    echo "$CONVERT_SCRIPT already exists – skipping"
fi

# Ensure both scripts are executable (safe even if files already existed)
chmod +x "$PLAY_SCRIPT" "$CONVERT_SCRIPT" 2>/dev/null || true
echo "Verified: Both scripts are executable."

# 5. Modify link.php: replace everything after the last </div> with new includes (idempotent)
echo_step "5. Modifying $LINK_PHP – replacing footer include section"

if [[ ! -f "$LINK_PHP" ]]; then
    warn "$LINK_PHP not found – skipping modification"
else
    # Check if our desired block is already present
    if grep -q 'include_once "custom/announcement.inc";' "$LINK_PHP" && \
       grep -q 'include_once "footer.inc";' "$LINK_PHP"; then
        echo "Announcement include section already present in $LINK_PHP – no changes needed"
    else
        echo "Replacing everything after the last </div> with the new announcement + footer includes..."

        # Use perl for reliable multi-line replacement
        perl -i -0777 -pe '
            s/(<\/div>\s*).*?$/$1\n\n<?php\ninclude_once "custom\/announcement.inc";\ninclude_once "footer.inc";\n?>\n/s
        ' "$LINK_PHP" || error "Perl replacement failed – check if perl is installed or edit $LINK_PHP manually"

        echo "Successfully replaced the bottom section in $LINK_PHP"
    fi

    # Ensure correct ownership & permissions
    chown www-data:www-data "$LINK_PHP"
    chmod 644 "$LINK_PHP"
fi

# 6. Final verification
echo_step "6. Setup complete – verification"
echo "Run these to test:"
echo " sudo -u www-data sudo $LOCAL_DIR/playaudio.sh netreminder"
echo " sudo -u www-data sudo $LOCAL_DIR/audio_convert.sh /mp3/test.mp3"
echo " ls -ld $TARGET_DIR $MP3_DIR $LOCAL_DIR"
echo " grep announcement.inc $LINK_PHP   # to verify the include was added"
echo ""
echo "Log into Supermon → Announcements Manager should now appear at the bottom."
echo "73 — N5AD"
