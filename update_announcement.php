<?php
/*
 * update_announcement.php
 * Updated July 2026 — uses www-data user crontab (no sudo crontab)
 * Cron lines include "sudo" so the play helper runs as root
 * Modified by N5AD
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed.";
    exit;
}

$raw_line = trim($_POST['raw_line'] ?? '');
$min      = trim($_POST['min']      ?? '');
$hour     = trim($_POST['hour']     ?? '');
$dom      = trim($_POST['dom']      ?? '');
$month    = trim($_POST['month']    ?? '');
$dow      = trim($_POST['dow']      ?? '');
$week     = trim($_POST['week']     ?? '*');
$use_nth  = !empty($_POST['use_nth']) && $_POST['use_nth'] == 1;

if (!$raw_line || $min === '' || $hour === '' || $dom === '' || $month === '' || $dow === '') {
    echo "Missing required fields.";
    exit;
}

// Read current USER crontab
$output = [];
$retval = 0;
exec('crontab -l 2>/dev/null', $output, $retval);

$new_crontab = [];
$found = false;
$comment_line = null;

foreach ($output as $line) {
    $trimmed = trim($line);

    // Preserve comment line
    if (strpos($trimmed, '# Announcement:') === 0) {
        $comment_line = $trimmed;
        continue;
    }

    // Match the job line
    if ($trimmed === $raw_line || strpos($trimmed, $raw_line) !== false) {
        $found = true;

        // Extract the full command part after time fields
        if (preg_match('/^\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+(.+)$/', $line, $matches)) {
            $full_command = trim($matches[1]);
        } else {
            $full_command = 'sudo /etc/asterisk/local/playaudio.sh /unknown/path';
        }

        // Normalize: extract the play script + target (strip any existing sudo / bash -c wrapper)
        $play_cmd = $full_command;

        // If it is an nth-week job wrapped in bash -c, pull the play command out
        if (strpos($full_command, '/bin/bash -c') !== false) {
            if (preg_match('/(?:sudo\s+)?(\/(?:etc\/asterisk\/local\/(?:playaudio|playglobal|polite_play|polite_global)\.sh)\s+[^\s\'"]+)/', $full_command, $play_matches)) {
                $play_cmd = 'sudo ' . trim($play_matches[1]);
            }
        } else {
            // Ensure the command starts with sudo
            if (strpos($play_cmd, 'sudo ') !== 0) {
                // Strip a leading path if present and re-add sudo
                if (preg_match('/(\/(?:etc\/asterisk\/local\/(?:playaudio|playglobal|polite_play|polite_global)\.sh)\s+.+)$/', $play_cmd, $m)) {
                    $play_cmd = 'sudo ' . $m[1];
                } else {
                    $play_cmd = 'sudo ' . $play_cmd;
                }
            }
        }

        // Clean trailing junk
        $play_cmd = trim($play_cmd, " ')\t");

        // Build new line
        if ($use_nth && in_array($week, ['1','2','3','4','5']) && preg_match('/^[1-7]$/', trim($dow))) {
            // Rebuild nth-week
            $low  = ((int)$week - 1) * 7 + 1;
            $high = ((int)$week === 5) ? 31 : $low + 6;

            $cond = "[ \$(date +\\%d) -ge $low ] && [ \$(date +\\%d) -le $high ]";

            $new_line = "$min $hour $dom $month $dow /bin/bash -c '$cond && $play_cmd'";
        } else {
            // Classic style
            $new_line = "$min $hour $dom $month $dow $play_cmd";
        }

        // Add comment if present
        if ($comment_line) {
            $new_crontab[] = $comment_line;
        }
        $new_crontab[] = $new_line;
        $comment_line = null; // consumed
    } else {
        // If we previously saw a comment but this line is not the matching job, keep the comment
        if ($comment_line !== null) {
            $new_crontab[] = $comment_line;
            $comment_line = null;
        }
        $new_crontab[] = $line;
    }
}

// Flush any leftover comment
if ($comment_line !== null) {
    $new_crontab[] = $comment_line;
}

if (!$found) {
    echo "Original cron line not found in crontab.";
    exit;
}

// Write updated USER crontab
$tempfile = tempnam(sys_get_temp_dir(), 'cron_update_');
file_put_contents($tempfile, implode("\n", $new_crontab) . "\n");

exec("crontab " . escapeshellarg($tempfile), $out, $ret);
unlink($tempfile);

if ($ret === 0) {
    echo "Cron job updated successfully.";
} else {
    echo "Failed to update crontab.";
}
