<?php
/**
 * announcement.php - ASL3 Final Version (fixed)
 * Created by N5AD
 * Converts MP3/WAV → u-law (.ul), installs to Asterisk sounds,
 * and sets up cron job with support for:
 *   - Local vs Global playback
 *   - Polite vs Priority mode
 *   - Standard + Nth week of month scheduling
 *   - Leading pause
 *
 * Updated July 2026
 * - Writes .ul directly to /usr/local/share/asterisk/sounds/announcements/
 * - Runs convert script with sudo (NOPASSWD already configured)
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

// Run convert script as root (sudoers already has NOPASSWD for this exact path)
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

// ---------- Set permissions ----------
exec("sudo chmod 644 " . escapeshellarg($ul_file));
exec("sudo chown root:root " . escapeshellarg($ul_file));

// ---------- Select playback script ----------
$play_script = $PLAY_SCRIPTS[$scope][$mode] ?? $PLAY_SCRIPTS['local']['polite'];

$scope_note = strtoupper($scope);
$mode_note  = strtoupper($mode);
$desc_clean = "# Announcement: $desc [$mode_note] [$scope_note]";

// ---------- Install cron if schedule was given ----------
if ($min !== '' && $hour !== '') {

    $play_target = "$SOUNDS_DIR/$base_name";

    if ($use_nth && in_array($week, ['1','2','3','4','5'])) {
        // Nth week of the month
        $low  = ((int)$week - 1) * 7 + 1;
        $high = ($week == 5) ? 31 : $low + 6;
        $cond = "[ \$(date +\\%d) -ge $low ] && [ \$(date +\\%d) -le $high ]";

        $cron_line = "$min $hour * * $dow /bin/bash -c '$cond && $play_script $play_target'";

        $nth_suffix = ['','st','nd','rd','th','th'][(int)$week];
        $desc_clean .= " ({$week}{$nth_suffix} week - $dow)";
    } else {
        // Standard cron
        $cron_line = "$min $hour $dom $month $dow $play_script $play_target";
    }

    // Safe cron installation
    $tmp_cron = tempnam(sys_get_temp_dir(), 'ann_cron_');
    exec("sudo crontab -l > " . escapeshellarg($tmp_cron) . " 2>/dev/null || true");

    file_put_contents($tmp_cron, $desc_clean . "\n", FILE_APPEND);
    file_put_contents($tmp_cron, $cron_line . "\n", FILE_APPEND);

    exec("sudo crontab " . escapeshellarg($tmp_cron), $cron_out, $cron_ret);
    unlink($tmp_cron);

    if ($cron_ret !== 0) {
        die("Error: Failed to install cron job.");
    }

    echo "Announcement installed successfully!\n";
    if ($pause_seconds > 0) {
        echo "Pause  : {$pause_seconds} seconds at start\n";
    }
    echo "File   : $base_name.ul\n";
    echo "Mode   : $mode_note\n";
    echo "Scope  : $scope_note\n";
    echo "Schedule: $cron_line\n";
} else {
    echo "File converted successfully (no schedule set).\n";
}

echo "UL saved to: $ul_file\n";
?>
