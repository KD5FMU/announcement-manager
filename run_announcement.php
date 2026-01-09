
<?php

// run_announcement.php

// Plays a .ul file immediately on the AllStar node

// Created by N5AD


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


// Sanitize input

$ul_file = basename($_POST['file']); // prevents path traversal

$sounds_dir = "/usr/local/share/asterisk/sounds";

$full_path = $sounds_dir . "/" . $ul_file;


// Check if file exists

if (!file_exists($full_path)) {

    echo "UL file not found: $ul_file";

    exit;

}


// Path to play script

$play_script = "/etc/asterisk/local/playaudio.sh";


// Verify play script exists and is executable

if (!is_executable($play_script)) {

    echo "playaudio.sh not found or not executable.";

    exit;

}


// Command to run: playaudio.sh expects filename **without extension**

$base_name = pathinfo($ul_file, PATHINFO_FILENAME);

$cmd = escapeshellcmd("sudo $play_script $base_name");


// Run the command

exec($cmd . " 2>&1", $output, $retval);


if ($retval === 0) {

    echo "Playing $base_name now.";

} else {

    echo "Failed to play $base_name. Output: " . implode("\n", $output);

}

?>

