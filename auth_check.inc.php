<?php
/**
 * auth_check.inc.php
 * Shared authentication gate for Announcement Manager action endpoints.
 * Accepts either:
 *   1) Supermon session (sm61loggedin / similar), or
 *   2) Allmon3 session cookie validated via local auth/check
 * Fail closed: 403 JSON/text if neither is valid.
 *
 * Include at the top of every action PHP script:
 *   require_once __DIR__ . '/auth_check.inc.php';
 */

if (defined('ANNOUNCEMENT_MGR_AUTH_CHECKED')) {
    return;
}
define('ANNOUNCEMENT_MGR_AUTH_CHECKED', true);

/**
 * Supermon login check (session-based)
 */
function announcement_mgr_supermon_logged_in(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        // Must use the same session name Supermon uses
        @session_start(['name' => 'supermon61']);
    }
    $flags = [
        'sm61loggedin',
        'smloggedin',
        'loggedin',
        'supermon_loggedin',
    ];
    foreach ($flags as $key) {
        if (!empty($_SESSION[$key]) && $_SESSION[$key] === true) {
            return true;
        }
        if (isset($_SESSION[$key]) && ($_SESSION[$key] === 'yes' || $_SESSION[$key] === 1 || $_SESSION[$key] === '1')) {
            return true;
        }
    }
    return false;
}
    // Known Supermon session flags across versions
    $flags = [
        'sm61loggedin',
        'smloggedin',
        'loggedin',
        'supermon_loggedin',
    ];
    foreach ($flags as $key) {
        if (!empty($_SESSION[$key]) && $_SESSION[$key] === true) {
            return true;
        }
        // some installs store "yes" / 1
        if (isset($_SESSION[$key]) && ($_SESSION[$key] === 'yes' || $_SESSION[$key] === 1 || $_SESSION[$key] === '1')) {
            return true;
        }
    }
    return false;
}

/**
 * Allmon3 login check (cookie forwarded to local auth endpoint)
 */
function announcement_mgr_allmon3_logged_in(): bool {
    if (!function_exists('curl_init')) {
        return false;
    }

    $checkUrl = 'https://127.0.0.1/allmon3/master/auth/check';
    // Fallback HTTP if TLS to localhost is broken on some installs
    $urls = [
        'https://127.0.0.1/allmon3/master/auth/check',
        'http://127.0.0.1/allmon3/master/auth/check',
    ];

    $cookieHeader = $_SERVER['HTTP_COOKIE'] ?? '';
    if ($cookieHeader === '') {
        return false;
    }

    foreach ($urls as $checkUrl) {
        $ch = curl_init($checkUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Cookie: $cookieHeader"],
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $response = curl_exec($ch);
        $code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $code >= 500) {
            continue;
        }
        $data = json_decode($response, true);
        if (is_array($data) && isset($data['SUCCESS']) && $data['SUCCESS'] === 'Logged In') {
            return true;
        }
    }
    return false;
}

/**
 * Require authentication or exit 403
 */
function announcement_mgr_require_auth(): void {
    $sm = announcement_mgr_supermon_logged_in();
    $am = announcement_mgr_allmon3_logged_in();

    if ($sm || $am) {
        return;
    }

    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Access Denied\n";
    echo "Supermon check: " . ($sm ? "YES" : "NO") . "\n";
    echo "Allmon3 check: " . ($am ? "YES" : "NO") . "\n";
    echo "Session name: " . session_name() . "\n";
    echo "Session id: " . session_id() . "\n";
    echo "sm61loggedin: ";
    var_export($_SESSION['sm61loggedin'] ?? 'NOT SET');
    echo "\nCookies received: " . ($_SERVER['HTTP_COOKIE'] ?? '(none)') . "\n";
    exit;
}
announcement_mgr_require_auth();
