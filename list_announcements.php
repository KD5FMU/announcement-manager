<?php
/*
 * list_announcements.php
 * Updated July 2026 — reads www-data user crontab (no sudo crontab)
 * Modified by N5AD
 */
require_once __DIR__ . '/auth_check.inc.php';

header('Content-Type: application/json');

$cron = [];
$output = [];
$return_var = 0;

/*
 * Read USER crontab safely
 */
exec('crontab -l 2>/dev/null', $output, $return_var);

// Empty is fine
$current = null;

foreach ($output as $line) {

    $line = trim($line);

    // Detect announcement comment
    if (strpos($line, '# Announcement:') === 0) {

        $current = [
            'description' => trim(substr($line, strlen('# Announcement:'))),
            'schedule'    => '',
            'command'     => ''
        ];
        continue;
    }

    // If we just saw an announcement comment, next line is the cron entry
    if ($current && preg_match('/^\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+/', $line)) {

        $parts = preg_split('/\s+/', $line, 6);

        $current['schedule'] = implode(' ', array_slice($parts, 0, 5));
        $current['command']  = $parts[5] ?? '';

        $cron[] = $current;
        $current = null;
    }
}

echo json_encode($cron, JSON_PRETTY_PRINT);
