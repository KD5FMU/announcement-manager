<?php
/*
 * run_announcement.php
 * Updated July 2026 — auth gated; correct paths for .ul vs mp3; safe escaping
 * Modified by N5AD
 */
require_once __DIR__ . '/auth_check.inc.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed.";
    exit;
}

if (empty($_POST['file'])) {
    echo "No file specified.";
    exit;
}

$SOUNDS_DIR = '/usr/local/share/asterisk/sounds/announcements';

// Sanitize
$base = basename($_POST['file']);
$base_name = pathinfo($base, PATHINFO_FILENAME);

// Get scope (default to local for safety)
$scope  = strtolower(trim($_POST['scope'] ?? 'local'));
$source = strtolower(trim($_POST['source'] ?? 'ul'));

// Determine playback path
// Cron jobs use the full path under SOUNDS_DIR (no extension).
// Dropdown "Global Play" for raw mp3/wav uses /mp3/.
if ($source === 'mp3') {
    $play_path = '/mp3/' . $base_name;
} else {
    $full = $SOUNDS_DIR . '/' . $base_name;
    if (file_exists($full . '.ul') || file_exists($full)) {
        $play_path = $full;
    } else {
        $play_path = 'announcements/' . $base_name;
    }
}

if ($scope === 'global') {
    $play_script = '/etc/asterisk/local/playglobal.sh';
    $echo_msg = "Playing '$base_name' **GLOBALLY** now.";
} else {
    $play_script = '/etc/asterisk/local/playaudio.sh';
    $echo_msg = "Playing '$base_name' locally now.";
}

if (!is_executable($play_script)) {
    echo "Playback script not found or not executable: $play_script";
    exit;
}

// Per-argument escaping (do not wrap the whole command in escapeshellcmd)
$cmd = 'sudo ' . escapeshellarg($play_script) . ' ' . escapeshellarg($play_path);
exec($cmd . ' 2>&1', $output, $retval);

if ($retval === 0) {
    echo $echo_msg;
} else {
    $error = implode("\n", $output);
    echo "Failed to play '$base_name'.\nCode: $retval\nOutput: $error";
}
?>
