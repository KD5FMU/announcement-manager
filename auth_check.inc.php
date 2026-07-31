<?php
/**
 * auth_check.inc.php
 * Accepts Supermon session OR Allmon3 session. Fail closed with 403.
 */
if (defined('ANNOUNCEMENT_MGR_AUTH_CHECKED')) {
    return;
}
define('ANNOUNCEMENT_MGR_AUTH_CHECKED', true);

function announcement_mgr_supermon_logged_in(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start(['name' => 'supermon61']);
    }
    if (!empty($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true) {
        return true;
    }
    if (isset($_SESSION['sm61loggedin']) &&
        ($_SESSION['sm61loggedin'] === 'yes' || $_SESSION['sm61loggedin'] === 1 || $_SESSION['sm61loggedin'] === '1')) {
        return true;
    }
    return false;
}

function announcement_mgr_allmon3_logged_in(): bool {
    if (!function_exists('curl_init')) {
        return false;
    }
    $cookieHeader = $_SERVER['HTTP_COOKIE'] ?? '';
    if ($cookieHeader === '') {
        return false;
    }
    $urls = [
        'https://127.0.0.1/allmon3/master/auth/check',
        'http://127.0.0.1/allmon3/master/auth/check',
    ];
    foreach ($urls as $checkUrl) {
        $ch = curl_init($checkUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Cookie: $cookieHeader"],
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false || $code >= 500) {
            continue;
        }
        $data = json_decode($response, true);
        if (is_array($data) && isset($data['SUCCESS']) && $data['SUCCESS'] === 'Logged In') {
            return true;
        }
        if (is_array($data) && isset($data['SECURITY']) && $data['SECURITY'] === 'Logged In') {
            return true;
        }
        if (is_array($data) && !empty($data['logged_in'])) {
            return true;
        }
    }
    return false;
}

function announcement_mgr_require_auth(): void {
    if (announcement_mgr_supermon_logged_in() || announcement_mgr_allmon3_logged_in()) {
        return;
    }
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Access Denied: please log into Supermon or Allmon3 first.";
    exit;
}

announcement_mgr_require_auth();
