<?php
/**
 * cron_validate.inc.php
 * Allow-list validation for cron schedule fields (ANNOUNCEMENT-MGR-003).
 * Only digits, comma, hyphen, slash, and asterisk - no shell metacharacters.
 */

/**
 * Validate a single cron field.
 * Allows star, star/step, n, n-m, n-m/step, and comma-separated lists of those.
 * $min and $max are numeric bounds for any pure numbers that appear.
 */
function announcement_mgr_valid_cron_field(string $value, int $min, int $max): bool {
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    // Reject anything outside the safe character set
    if (!preg_match('/^[0-9*,\\-\/]+$/', $value)) {
        return false;
    }
    foreach (explode(',', $value) as $part) {
        $part = trim($part);
        if ($part === '') {
            return false;
        }
        // star/step
        if (preg_match('/^\*\/(\d+)$/', $part, $m)) {
            $step = (int)$m[1];
            if ($step < 1 || $step > $max) {
                return false;
            }
            continue;
        }
        // star
        if ($part === '*') {
            continue;
        }
        // range or range/step
        if (preg_match('/^(\d+)-(\d+)(?:\/(\d+))?$/', $part, $m)) {
            $lo = (int)$m[1];
            $hi = (int)$m[2];
            $step = isset($m[3]) ? (int)$m[3] : 1;
            if ($lo < $min || $hi > $max || $lo > $hi || $step < 1) {
                return false;
            }
            continue;
        }
        // single number
        if (preg_match('/^\d+$/', $part)) {
            $n = (int)$part;
            if ($n < $min || $n > $max) {
                return false;
            }
            continue;
        }
        return false;
    }
    return true;
}

/**
 * Validate standard five cron fields + optional week for nth-week mode.
 * Returns null on success, or an error string.
 */
function announcement_mgr_validate_schedule(
    string $min,
    string $hour,
    string $dom,
    string $month,
    string $dow,
    string $week = '*',
    bool $use_nth = false
): ?string {
    if (!announcement_mgr_valid_cron_field($min, 0, 59)) {
        return "Invalid minute field: $min (allowed 0-59, *, lists/ranges/steps)";
    }
    if (!announcement_mgr_valid_cron_field($hour, 0, 23)) {
        return "Invalid hour field: $hour (allowed 0-23, *, lists/ranges/steps)";
    }
    if (!announcement_mgr_valid_cron_field($dom, 1, 31)) {
        return "Invalid day-of-month field: $dom (allowed 1-31, *, lists/ranges/steps)";
    }
    if (!announcement_mgr_valid_cron_field($month, 1, 12)) {
        return "Invalid month field: $month (allowed 1-12, *, lists/ranges/steps)";
    }
    if (!announcement_mgr_valid_cron_field($dow, 0, 7)) {
        return "Invalid day-of-week field: $dow (allowed 0-7, *, lists/ranges/steps)";
    }
    if ($use_nth) {
        $week = trim($week);
        if ($week !== '*' && !preg_match('/^[1-5]$/', $week)) {
            return "Invalid week-of-month: $week (allowed * or 1-5)";
        }
        if (!preg_match('/^[0-7]$/', trim($dow))) {
            return "Nth-week scheduling requires a single day-of-week (0-7), got: $dow";
        }
    }
    return null;
}

/**
 * Sanitize description used in cron comment lines.
 */
function announcement_mgr_safe_desc(string $desc): string {
    $desc = str_replace(["\r", "\n", "\0"], ' ', $desc);
    $desc = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $desc) ?? $desc;
    $desc = trim($desc);
    if ($desc === '') {
        $desc = 'Announcement';
    }
    return substr($desc, 0, 120);
}
