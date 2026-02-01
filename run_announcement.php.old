<?php
// run_announcement.php
// Plays a .ul (or other format) file immediately on the AllStar node
// Updated by Grok to support files in /usr/local/share/asterisk/sounds/announcements/
// and to strip any extension from the filename for consistency
// Original by N5AD

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed.";
    exit;
}

if (empty($_POST['file'])) {
    echo "No file specified.";
    exit;
}

// Sanitize input - just get the base filename, no path traversal
$base = basename($_POST['file']);

// Strip any extension (e.g., if UL dropdown sends "myannounce.ul", make it "myannounce")
$base_name = pathinfo($base, PATHINFO_FILENAME);

// We will pass this relative path to playaudio.sh
// Asterisk will look in /usr/local/share/asterisk/sounds/announcements/
$play_path = "announcements/" . $base_name;  // no extension needed

// Path to play script
$play_script = "/etc/asterisk/local/playaudio.sh";

// Verify play script exists and is executable
if (!is_executable($play_script)) {
    echo "playaudio.sh not found or not executable at $play_script.";
    exit;
}

// Command to run: playaudio.sh expects filename (or subdir/filename) without extension
$cmd = escapeshellcmd("sudo $play_script $play_path");

// Run the command and capture output
exec($cmd . " 2>&1", $output, $retval);

if ($retval === 0) {
    echo "Playing '$base_name' now.";
} else {
    $error_msg = implode("\n", $output);
    echo "Failed to play '$base_name'. Return code: $retval\nOutput: $error_msg";
}
?>
