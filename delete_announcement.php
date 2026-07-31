<?php
/*
 * delete_announcement.php
 * Updated July 2026 — uses www-data user crontab (no sudo crontab)
 * Modified by N5AD
 */
if (!isset($_POST['raw_line'])) {
    echo "Error: Missing cron line";
    exit;
}

$raw = trim($_POST['raw_line']);

// Get current USER crontab (www-data)
$output = [];
exec('crontab -l 2>/dev/null', $output);

$lines = $output;

// Remove the requested line
$new_lines = [];
$removed = false;

foreach ($lines as $idx => $line) {
    if (trim($line) === $raw) {
        $removed = true;
        continue;
    }
    $new_lines[] = $line;
}

if (!$removed) {
    echo "Cron line not found";
    exit;
}

// Now remove orphaned # Announcement lines
$final_lines = [];
$i = 0;
while ($i < count($new_lines)) {
    $current = $new_lines[$i];
    if (strpos(trim($current), '# Announcement') === 0) {
        // Look ahead
        if ($i + 1 >= count($new_lines) || trim($new_lines[$i + 1]) === '' || strpos(trim($new_lines[$i + 1]), '#') === 0) {
            // orphaned → skip it
            $i++;
            continue;
        }
    }
    $final_lines[] = $current;
    $i++;
}

// Write back to temp file and install into user crontab
$tempfile = tempnam(sys_get_temp_dir(), 'cron_clean_');
file_put_contents($tempfile, implode(PHP_EOL, $final_lines) . PHP_EOL);

exec("crontab " . escapeshellarg($tempfile));
unlink($tempfile);

echo "Cron Entry deleted Successfully.";
