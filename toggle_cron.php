<?php
/*
 * toggle_cron.php
 * Updated July 2026 — uses www-data user crontab (no sudo crontab)
 * Modified by N5AD
 */
require_once __DIR__ . '/auth_check.inc.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed.";
    exit;
}

if (empty($_POST['raw_line']) || !isset($_POST['enable'])) {
    echo "Missing parameters.";
    exit;
}

$raw_line = trim($_POST['raw_line']);
$enable   = (bool)$_POST['enable'];  // true = uncomment (enable), false = comment (disable)

// Read current USER crontab
$output = [];
$retval = 0;
exec('crontab -l 2>/dev/null', $output, $retval);

// Empty crontab is OK (retval may be non-zero on some systems when empty)
$new_crontab = [];
$found = false;

foreach ($output as $line) {
    $trimmed = trim($line);
    if ($trimmed === $raw_line || $trimmed === "# $raw_line") {
        $found = true;
        if ($enable) {
            // Uncomment if commented
            if (strpos($trimmed, '#') === 0) {
                $new_line = ltrim($trimmed, '# ');
            } else {
                $new_line = $raw_line;
            }
        } else {
            // Comment if uncommented
            if (strpos($trimmed, '#') !== 0) {
                $new_line = "# $raw_line";
            } else {
                $new_line = $raw_line; // already commented
            }
        }
        $new_crontab[] = $new_line;
    } else {
        $new_crontab[] = $line;
    }
}

if (!$found) {
    echo "Cron line not found.";
    exit;
}

// Write new crontab to temp file
$tempfile = tempnam(sys_get_temp_dir(), 'cron');
file_put_contents($tempfile, implode("\n", $new_crontab) . "\n");

exec("crontab " . escapeshellarg($tempfile), $out, $ret);
unlink($tempfile);

if ($ret === 0) {
    echo $enable ? "Cron job enabled." : "Cron job disabled.";
} else {
    echo "Failed to update crontab.";
}
