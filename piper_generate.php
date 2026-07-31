<?php
/*
 * Updated for native ASL3 asl3-tts (piper)
 * Uses /usr/bin/piper and voices in /var/lib/piper-tts
 * Modified by N5AD / updated July 2026
 */
require_once __DIR__ . '/auth_check.inc.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed.";
    exit;
}

$text = trim($_POST['text'] ?? '');
$filename = basename(trim($_POST['filename'] ?? ''));
$voice = trim($_POST['voice'] ?? ''); // voice model path from dropdown

if (!$text || !$filename) {
    echo "Missing text or filename.";
    exit;
}

// Default voice if none selected (ASL3 native Amy low)
if (empty($voice)) {
    $voice = "/var/lib/piper-tts/en_US-amy-low.onnx";
}

$script = "/usr/local/bin/piper_prompt_tts.sh";

// Build safe command with voice as third argument
$cmd = escapeshellcmd(
    "sudo $script " .
    escapeshellarg($text) . " " .
    escapeshellarg($filename) . " " .
    escapeshellarg($voice)
);

exec($cmd . " 2>&1", $output, $retval);

if ($retval === 0) {
    echo "Success! Generated in /mp3/$filename.wav\n"
       . "You can now select it in the dropdown and convert to .ul.";
} else {
    echo "Failed: " . implode("\n", $output);
}
?>
