<?php
/**
 * Security Module
 */

function securityCheck() {
    if (php_sapi_name() === 'cli') {
        return true;
    }

    if (isset($_GET['key'])) {
        $secretFile = SCRIPT_DIR . '/.update_key';
        if (file_exists($secretFile)) {
            $expectedKey = trim(file_get_contents($secretFile));
            if (hash_equals($expectedKey, $_GET['key'])) {
                return true;
            }
        }
    }

    $ipFile = SCRIPT_DIR . '/.ip_whitelist';
    if (file_exists($ipFile)) {
        $allowedIPs = file($ipFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

        foreach ($allowedIPs as $ip) {
            if (trim($ip) === $clientIP) {
                return true;
            }
        }
    }

    return false;
}
