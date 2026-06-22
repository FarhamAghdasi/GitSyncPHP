<?php
/**
 * Logging Module
 */

function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function maskSensitiveData($message) {
    $message = preg_replace('/ghp_[a-zA-Z0-9]{36}/', 'ghp_***MASKED***', $message);
    $message = preg_replace('/github_pat_[a-zA-Z0-9_]{40,}/', 'github_pat_***MASKED***', $message);
    $message = preg_replace('/[0-9]{8,10}:[a-zA-Z0-9_-]{35}/', '***TELEGRAM_TOKEN_MASKED***', $message);
    $message = preg_replace('/[a-zA-Z0-9_-]{30,}/', '***TOKEN_MASKED***', $message);
    return $message;
}

function logInfo($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [INFO] " . maskSensitiveData($message) . "\n";
    if (!isAjaxRequest()) {
        echo $logEntry;
    }
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logError($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [ERROR] " . maskSensitiveData($message) . "\n";
    if (!isAjaxRequest()) {
        echo $logEntry;
    }
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logSuccess($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [SUCCESS] " . maskSensitiveData($message) . "\n";
    if (!isAjaxRequest()) {
        echo $logEntry;
    }
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logWarning($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [WARNING] " . maskSensitiveData($message) . "\n";
    if (!isAjaxRequest()) {
        echo $logEntry;
    }
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logDebug($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [DEBUG] " . maskSensitiveData($message) . "\n";
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logRequest($url, $headers, $method = 'GET') {
    $maskedHeaders = array_map(function($header) {
        if (strpos($header, 'Authorization:') !== false) {
            return 'Authorization: ***MASKED***';
        }
        if (strpos($header, 'token ') !== false) {
            return 'token ***MASKED***';
        }
        return $header;
    }, $headers);

    logDebug("REQUEST: $method $url");
    logDebug("   Headers: " . json_encode($maskedHeaders, JSON_UNESCAPED_SLASHES));
}

function logResponse($statusCode, $body = null, $error = null) {
    logDebug("RESPONSE: HTTP $statusCode");

    if ($error) {
        logDebug("   Error: " . maskSensitiveData($error));
    }

    if ($body && strlen($body) < 1000) {
        logDebug("   Body preview: " . maskSensitiveData(substr($body, 0, 500) . (strlen($body) > 500 ? '...' : '')));
    } elseif ($body) {
        logDebug("   Body size: " . strlen($body) . " bytes");
    }
}
