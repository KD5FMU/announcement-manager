<?php
/**
 * announcement.php - ASL3 Final Version (hardened)
 * Created by N5AD
 * Converts MP3/WAV → u-law (.ul), installs to Asterisk sounds,
 * and sets up cron job with support for:
 *   - Local vs Global playback
 *   - Polite vs Priority mode
 *   - Standard + Nth week of month scheduling
 *   - Leading pause
 *
 * Updated July 2026 (security hardening)
 * - Writes .ul via audio_convert.sh (which sets its own permissions)
 * - Cron jobs live in www-data's USER crontab (not root)
 * - Cron lines call play scripts via sudo (narrow NOPASSWD only)
 */

$TMP_DIR        = '/mp3';
$CONVERT_SCRIPT = '/etc/asterisk/local/audio_convert.sh';
$SOUNDS_DIR     = '/usr/local/share/asterisk/sounds/announcements';

$PLAY_SCRIPTS = [
    'local' => [
        'polite'   => '/etc/asterisk/local/polite_play.sh',
        'priority' => '/etc/asterisk/local/playaudio.sh'
    ],
    'global' => [
        'polite'   => '/etc/asterisk/local/polite_global.sh',
        'priority' => '/etc/asterisk/local/playglobal.sh'
    ]
];

// ---------- Get POST data ----------
$mp3     = isset($_POST['file']) ? basename($_POST['file']) : '';
$min     = $_POST['min']    ?? '';
$hour    = $_POST['hour']   ?? '';
$dom     = $_POST['dom']    ?? '*';
$month   = $_POST['month']  ?? '*';
$dow     = $_POST['dow']    ?? '*';
$week    = $_POST['week']   ?? '*';
$use_nth = !empty($_POST['use_nth']) && $_POST['use_nth'] == 1;
$desc    = $_POST['desc']   ?? 'Announcement';
$scope   = $_POST['scope']  ?? 'local';
$mode    = $_POST['mode']   ?? 'polite';
$pause_seconds = isset($_POST['pause']) ? floatval($_POST['pause']) : 0;

if (!$mp3) {
    die("Error: No audio file specified.");
}

// ---------- Validate source file ----------
$src_file = "$TMP_DIR/$mp3";
if (!file_exists($src_file)) {
    die("Error: Source file not found: $src_file");
}

// ---------- Validate converter ----------
if (!is_executable($CONVERT_SCRIPT)) {
    die("Error: Conversion script not found or not executable: $CONVERT_SCRIPT");
}

// ---------- Convert directly into the Asterisk sounds directory ----------
$base_name = pathinfo($mp3, PATHINFO_FILENAME);
$ul_file   = "$SOUNDS_DIR/$base_name.ul";          // final location

// Run convert script as root (sudoers has NOPASSWD for this exact path).
// audio_convert.sh itself sets chmod 644 — no separate chmod/chown needed.
$cmd_convert = "sudo " . escapeshellarg($CONVERT_SCRIPT) . " "
             . escapeshellarg($src_file) . " "
             . escapeshellarg($ul_file);

if ($pause_seconds > 0) {
    $cmd_convert .= " " . escapeshellarg($pause_seconds);
}

exec($cmd_convert . " 2>&1", $output, $ret);

if ($ret !== 0 || !file_exists($ul_file)) {
    echo "Error: Audio conversion failed.\n";
    echo "Command: $cmd_convert\n";
    echo "Return code: $ret\n";
    echo "Output:\n" . implode("\n", $output) . "\n";
    echo "Expected UL file: $ul_file\n";
    echo "File exists? " . (file_exists($ul_file) ? "YES" : "NO") . "\n";
    die();
}

// ---------- Select playback script ----------
$play_script = $PLAY_SCRIPTS[$scope][$mode] ?? $PLAY_SCRIPTS['local']['polite'];

$scope_note = strtoupper($scope);
$mode_note  = strtoupper($mode);
$desc_clean = "# Announcement: $desc [$mode_note] [$scope_note]";

// ---------- Install cron if schedule was given ----------
// IMPORTANT: We now write to www-data's USER crontab (no sudo crontab).
// The cron line itself uses "sudo" to invoke the play helper (narrow NOPASSWD).
if ($min !== '' && $hour !== '') {

    $play_target = "$SOUNDS_DIR/$base_name";

    if ($use_nth && in_array($week, ['1','2','3','4','5'])) {
        // Nth week of the month
        $low  = ((int)$week - 1) * 7 + 1;
        $high = ($week == 5) ? 31 : $low + 6;
        $cond = "[ \$(date +\\%d) -ge $low ] && [ \$(date +\\%d) -le $high ]";

        $cron_line = "$min $hour * * $dow /bin/bash -c '$cond && sudo $play_script $play_target'";

        $nth_suffix = ['','st','nd','rd','th','th'][(int)$week];
        $desc_clean .= " ({$week}{$nth_suffix} week - $dow)";
    } else {
        // Standard cron — note the leading "sudo" so the play script runs as root
        $cron_line = "$min $hour $dom $month $dow sudo $play_script $play_target";
    }

    // Install into www-data's own crontab (no sudo)
    $tmp_cron = tempnam(sys_get_temp_dir(), 'ann_cron_');
    @chmod($tmp_cron, 0600);
    exec("crontab -l 2>/dev/null > " . escapeshellarg($tmp_cron) . " || true");

    $existing = @file_get_contents($tmp_cron);
    if ($existing === false) { $existing = ""; }
    if ($existing !== "" && substr($existing, -1) !== "\n") {
        $existing .= "\n";
    }
    $existing .= $desc_clean . "\n" . $cron_line . "\n";
    file_put_contents($tmp_cron, $existing);

    exec("crontab " . escapeshellarg($tmp_cron) . " 2>&1", $cron_out, $cron_ret);
    @unlink($tmp_cron);

    if ($cron_ret !== 0) {
        die("Error: Failed to install cron job into user crontab.\n" . implode("\n", $cron_out));
    }

    echo "Announcement installed successfully!\n";
    if ($pause_seconds > 0) {
        echo "Pause  : {$pause_seconds} seconds at start\n";
    }
    echo "File   : $base_name.ul\n";
    echo "Mode   : $mode_note\n";
    echo "Scope  : $scope_note\n";
    echo "Schedule: $cron_line\n";
    echo "(Cron entry is in www-data user crontab)\n";
} else {
    echo "File converted successfully (no schedule set).\n";
}

echo "UL saved to: $ul_file\n";
?>
