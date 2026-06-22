<?php
/**
 * GitHub Auto-Update Script for Shared Hosting
 * 
 * This script updates the project directory from a GitHub repository.
 * Supports custom target directory and displays commit details.
 */

// ============================
// CONFIGURATION
// ============================

// Base directory - use SCRIPT_DIR (git directory) for .env and logs
/** @var string BASE_DIR */
define('BASE_DIR', dirname(__DIR__));
/** @var string SCRIPT_DIR */
define('SCRIPT_DIR', __DIR__);

// Load configuration from SCRIPT_DIR/.env
$config = loadConfig();

// Define constants from config
/** @var string GITHUB_TOKEN */
define('GITHUB_TOKEN', (string)($config['GITHUB_TOKEN'] ?? ''));
/** @var string REPO_USER */
define('REPO_USER', (string)($config['REPO_USER'] ?? 'farhamaghdasi'));
/** @var string REPO_NAME */
define('REPO_NAME', (string)($config['REPO_NAME'] ?? 'arash-laravel-panel'));
/** @var string BRANCH */
define('BRANCH', (string)($config['BRANCH'] ?? 'main'));
/** @var string TELEGRAM_BOT_TOKEN */
define('TELEGRAM_BOT_TOKEN', (string)($config['TELEGRAM_BOT_TOKEN'] ?? ''));
/** @var string TELEGRAM_CHAT_ID */
define('TELEGRAM_CHAT_ID', (string)($config['TELEGRAM_CHAT_ID'] ?? ''));
/** @var bool BACKUP_BEFORE_UPDATE */
define('BACKUP_BEFORE_UPDATE', ($config['BACKUP_BEFORE_UPDATE'] ?? 'true') === 'true');
/** @var string LOG_FILE */
define('LOG_FILE', SCRIPT_DIR . '/' . ($config['LOG_FILE'] ?? 'update_log.txt'));
/** @var string VERSION_FILE */
define('VERSION_FILE', SCRIPT_DIR . '/' . ($config['VERSION_FILE'] ?? '.version'));
/** @var string[] EXCLUDE_FILES */
define('EXCLUDE_FILES', explode(',', (string)($config['EXCLUDE_FILES'] ?? 'git,.env,__backups,.git*,config*.php,database*,*.sql,*.log,update_log.txt')));
/** @var bool DELETE_EXTRACTED_FILES */
define('DELETE_EXTRACTED_FILES', ($config['DELETE_EXTRACTED_FILES'] ?? 'true') === 'true');

// ============================================
// NEW: TARGET DIRECTORY CONFIGURATION
// ============================================

/** @var string TARGET_DIR - Directory where files will be updated */
define('TARGET_DIR', (string)($config['TARGET_DIR'] ?? dirname(__DIR__)));

// BACKUP_DIR: if absolute path (starts with /), use as-is; otherwise resolve relative to TARGET_DIR
$backupDirRaw = (string)($config['BACKUP_DIR'] ?? '');
if (!empty($backupDirRaw) && ($backupDirRaw[0] === '/' || preg_match('/^[A-Z]:\\\\/i', $backupDirRaw))) {
    /** @var string BACKUP_DIR */
    define('BACKUP_DIR', rtrim($backupDirRaw, '/\\'));
} else {
    /** @var string BACKUP_DIR */
    define('BACKUP_DIR', TARGET_DIR . '/' . ($backupDirRaw ?: '__backups'));
}

// ============================================
// PROXY CONFIGURATION
// ============================================

/** @var bool USE_PROXY - Enable/disable Cloudflare Worker proxy */
define('USE_PROXY', ($config['USE_PROXY'] ?? 'false') === 'true');
/** @var string PROXY_URL - Cloudflare Worker URL */
define('PROXY_URL', (string)($config['PROXY_URL'] ?? ''));

// ============================================
// COMMIT DETAILS CACHE
// ============================================

/** @var array|null $cachedCommitDetails - Cache for commit details */
$cachedCommitDetails = null;

/**
 * Load configuration from .env file
 */
function loadConfig() {
    $configFile = SCRIPT_DIR . '/.env';
    
    $defaultConfig = [
        'GITHUB_TOKEN' => '',
        'REPO_USER' => 'farhamaghdasi',
        'REPO_NAME' => 'arash-laravel-panel',
        'BRANCH' => 'main',
        'TELEGRAM_BOT_TOKEN' => '',
        'TELEGRAM_CHAT_ID' => '',
        'BACKUP_BEFORE_UPDATE' => 'true',
        'BACKUP_DIR' => '__backups',
        'LOG_FILE' => 'update_log.txt',
        'VERSION_FILE' => '.version',
        'EXCLUDE_FILES' => 'git,.env,__backups,.git*,config*.php,database*,*.sql,*.log,update_log.txt',
        'DELETE_EXTRACTED_FILES' => 'true',
        // New settings
        'TARGET_DIR' => dirname(__DIR__),
        'USE_PROXY' => 'false',
        'PROXY_URL' => ''
    ];
    
    if (!file_exists($configFile)) {
        logDebug("⚠️ Config file not found: $configFile");
        return $defaultConfig;
    }
    
    logDebug("📄 Loading config from: $configFile");
    
    $content = file_get_contents($configFile);
    $lines = explode("\n", $content);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (empty($line) || $line[0] === '#') {
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            if (strlen($value) >= 2) {
                $firstChar = $value[0];
                $lastChar = $value[strlen($value) - 1];
                
                if (($firstChar === '"' && $lastChar === '"') ||
                    ($firstChar === "'" && $lastChar === "'")) {
                    $value = substr($value, 1, -1);
                }
            }
            
            $defaultConfig[$key] = $value;
            
            // Hide sensitive data in logs
            $displayValue = $value;
            if (in_array($key, ['GITHUB_TOKEN', 'TELEGRAM_BOT_TOKEN'])) {
                $displayValue = strlen($value) > 10 ? substr($value, 0, 6) . '...' . substr($value, -4) : '***';
            }
            logDebug("   $key = " . (strlen($displayValue) > 50 ? substr($displayValue, 0, 50) . '...' : $displayValue));
        }
    }
    
    return $defaultConfig;
}

/**
 * Save configuration to .env file
 */
function saveConfig($newConfig) {
    $configFile = SCRIPT_DIR . '/.env';
    
    $content = "";
    foreach ($newConfig as $key => $value) {
        $content .= "$key=$value\n";
    }
    
    return file_put_contents($configFile, $content) !== false;
}

/**
 * Get list of backups
 */
function getBackups() {
    $backups = [];
    
    if (is_dir(BACKUP_DIR)) {
        $files = glob(BACKUP_DIR . '/backup_*.zip');
        foreach ($files as $file) {
            $backups[] = [
                'name' => basename($file),
                'path' => $file,
                'size' => round(filesize($file) / 1024, 2),
                'date' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
    }
    
    usort($backups, function($a, $b) {
        return strcmp($b['name'], $a['name']);
    });
    
    return $backups;
}

// ============================
// LOGGING FUNCTIONS
// ============================

/**
 * Check if current request is an AJAX request
 */
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Mask sensitive data in logs
 */
function maskSensitiveData($message) {
    // Mask GitHub tokens
    $message = preg_replace('/ghp_[a-zA-Z0-9]{36}/', 'ghp_***MASKED***', $message);
    $message = preg_replace('/github_pat_[a-zA-Z0-9_]{40,}/', 'github_pat_***MASKED***', $message);
    
    // Mask Telegram bot tokens
    $message = preg_replace('/[0-9]{8,10}:[a-zA-Z0-9_-]{35}/', '***TELEGRAM_TOKEN_MASKED***', $message);
    
    // Mask any string that looks like a token (alphanumeric with hyphens/underscores, 30+ chars)
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
    $logEntry = "[$timestamp] [ERROR] ❌ " . maskSensitiveData($message) . "\n";
    if (!isAjaxRequest()) {
        echo $logEntry;
    }
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logSuccess($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [SUCCESS] ✅ " . maskSensitiveData($message) . "\n";
    if (!isAjaxRequest()) {
        echo $logEntry;
    }
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logWarning($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [WARNING] ⚠️ " . maskSensitiveData($message) . "\n";
    if (!isAjaxRequest()) {
        echo $logEntry;
    }
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logDebug($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [DEBUG] 🔍 " . maskSensitiveData($message) . "\n";
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logRequest($url, $headers, $method = 'GET') {
    // Mask sensitive headers
    $maskedHeaders = array_map(function($header) {
        if (strpos($header, 'Authorization:') !== false) {
            return 'Authorization: ***MASKED***';
        }
        if (strpos($header, 'token ') !== false) {
            return 'token ***MASKED***';
        }
        return $header;
    }, $headers);
    
    logDebug("🌐 REQUEST: $method $url");
    logDebug("   Headers: " . json_encode($maskedHeaders, JSON_UNESCAPED_SLASHES));
}

function logResponse($statusCode, $body = null, $error = null) {
    logDebug("📡 RESPONSE: HTTP $statusCode");
    
    if ($error) {
        logDebug("   Error: " . maskSensitiveData($error));
    }
    
    if ($body && strlen($body) < 1000) {
        logDebug("   Body preview: " . maskSensitiveData(substr($body, 0, 500) . (strlen($body) > 500 ? '...' : '')));
    } elseif ($body) {
        logDebug("   Body size: " . strlen($body) . " bytes");
    }
}

// ============================
// COMMIT DETAILS FUNCTIONS (NEW)
// ============================

/**
 * Get detailed commit information including changed files
 */
function getCommitDetails($commitSha) {
    global $cachedCommitDetails;
    
    // Return cached if available
    if ($cachedCommitDetails !== null) {
        return $cachedCommitDetails;
    }
    
    $apiPath = "/repos/" . REPO_USER . "/" . REPO_NAME . "/commits/" . $commitSha;
    
    $headers = [
        'User-Agent: PHP-Update-Script',
        'Accept: application/vnd.github.v3+json'
    ];
    
    if (!empty(GITHUB_TOKEN)) {
        $headers[] = 'Authorization: token ' . GITHUB_TOKEN;
    }
    
    logRequest($apiPath, $headers);
    
    $response = proxyRequest('github', $apiPath, $headers);
    
    logResponse($response['status_code'], $response['body']);
    
    if ($response['status_code'] !== 200) {
        logError("Failed to get commit details: HTTP " . $response['status_code']);
        return null;
    }
    
    if (empty($response['body'])) {
        logError("Empty response body");
        return null;
    }
    
    $data = json_decode($response['body'], true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logError("JSON parse error: " . json_last_error_msg());
        return null;
    }
    
    // Extract file changes
    $files = [];
    $totalAdditions = 0;
    $totalDeletions = 0;
    
    if (isset($data['files'])) {
        foreach ($data['files'] as $file) {
            $files[] = [
                'filename' => $file['filename'] ?? 'unknown',
                'status' => $file['status'] ?? 'modified',
                'additions' => $file['additions'] ?? 0,
                'deletions' => $file['deletions'] ?? 0,
                'changes' => $file['changes'] ?? 0,
                'patch' => $file['patch'] ?? null
            ];
            $totalAdditions += $file['additions'] ?? 0;
            $totalDeletions += $file['deletions'] ?? 0;
        }
    }
    
    $commitDetails = [
        'sha' => $data['sha'] ?? $commitSha,
        'message' => $data['commit']['message'] ?? 'No message',
        'author' => $data['commit']['author']['name'] ?? 'Unknown',
        'author_email' => $data['commit']['author']['email'] ?? '',
        'date' => isset($data['commit']['author']['date']) ? date('Y-m-d H:i:s', strtotime($data['commit']['author']['date'])) : '',
        'url' => $data['html_url'] ?? '',
        'files' => $files,
        'stats' => [
            'total_additions' => $totalAdditions,
            'total_deletions' => $totalDeletions,
            'total_changes' => $totalAdditions + $totalDeletions,
            'files_changed' => count($files)
        ]
    ];
    
    $cachedCommitDetails = $commitDetails;
    return $commitDetails;
}

/**
 * Get full commit message with all details
 */
function getFullCommitMessage($commitSha) {
    $details = getCommitDetails($commitSha);
    if (!$details) {
        return null;
    }
    
    $message = $details['message'];
    
    // Add stats to message
    $stats = $details['stats'];
    $message .= "\n\n📊 Changes: " . $stats['files_changed'] . " files changed";
    $message .= " (+" . $stats['total_additions'] . " / -" . $stats['total_deletions'] . ")";
    
    // Add file list
    if (!empty($details['files'])) {
        $message .= "\n\n📁 Changed Files:";
        foreach ($details['files'] as $file) {
            $statusIcon = $file['status'] === 'added' ? '➕' : 
                         ($file['status'] === 'deleted' ? '➖' : '📝');
            $message .= "\n  " . $statusIcon . " " . $file['filename'] . 
                       " (+" . $file['additions'] . "/-" . $file['deletions'] . ")";
        }
    }
    
    return $message;
}

// ============================
// PROXY HELPER FUNCTIONS
// ============================

/**
 * Make request through Cloudflare Worker proxy or directly
 */
function proxyRequest($target, $path, $headers = [], $method = 'GET', $postData = null, $queryParams = null) {
    if (!USE_PROXY || empty(PROXY_URL)) {
        // Direct request
        logDebug("🔀 Using DIRECT request");
        return fetchUrlDirect($target, $path, $headers, $method, $postData, $queryParams);
    }
    
    // Proxy request
    logDebug("🔀 Using PROXY request: " . PROXY_URL);
    
    $proxyUrl = PROXY_URL . '?target=' . urlencode($target) . '&path=' . urlencode($path);
    
    if ($queryParams) {
        $proxyUrl .= '&query=' . urlencode($queryParams);
    }
    
    // Add token for Telegram
    if ($target === 'telegram') {
        $token = '';
        foreach ($headers as $header) {
            if (stripos($header, 'token:') !== false) {
                $token = trim(substr($header, strpos($header, ':') + 1));
                break;
            }
        }
        if ($token) {
            $proxyUrl .= '&token=' . urlencode($token);
        }
    }
    
    $proxyHeaders = [];
    
    // Forward authorization header for GitHub
    if ($target === 'github') {
        $authHeader = '';
        foreach ($headers as $header) {
            if (stripos($header, 'Authorization:') !== false) {
                $authHeader = $header;
                break;
            }
        }
        if ($authHeader) {
            $proxyHeaders[] = $authHeader;
        }
        
        // Forward other headers
        foreach ($headers as $header) {
            if (stripos($header, 'User-Agent:') !== false || 
                stripos($header, 'Accept:') !== false) {
                $proxyHeaders[] = $header;
            }
        }
    }
    
    // Forward content-type
    foreach ($headers as $header) {
        if (stripos($header, 'Content-Type:') !== false) {
            $proxyHeaders[] = $header;
        }
    }
    
    // Always set User-Agent
    $hasUserAgent = false;
    foreach ($proxyHeaders as $header) {
        if (stripos($header, 'User-Agent:') !== false) {
            $hasUserAgent = true;
            break;
        }
    }
    if (!$hasUserAgent) {
        $proxyHeaders[] = 'User-Agent: PHP-Update-Script-Proxy';
    }
    
    logDebug("🔀 Proxy URL: " . PROXY_URL . '?target=' . $target . '&path=' . $path);
    
    $result = fetchUrl($proxyUrl, $proxyHeaders, $method, $postData);
    
    return $result;
}

/**
 * Direct request without proxy
 */
function fetchUrlDirect($target, $path, $headers = [], $method = 'GET', $postData = null, $queryParams = null) {
    $baseUrls = [
        'github' => 'https://api.github.com',
        'telegram' => 'https://api.telegram.org'
    ];
    
    $baseUrl = $baseUrls[$target] ?? '';
    if (empty($baseUrl)) {
        return ['body' => null, 'status_code' => 0, 'error' => 'Invalid target'];
    }
    
    $url = $baseUrl . $path;
    if ($queryParams) {
        $url .= '?' . $queryParams;
    }
    
    return fetchUrl($url, $headers, $method, $postData);
}

// ============================
// MAIN UPDATE FUNCTION
// ============================

function performUpdate() {
    $startTime = microtime(true);
    
    logInfo("📁 BASE_DIR: " . BASE_DIR);
    logInfo("📁 SCRIPT_DIR: " . SCRIPT_DIR);
    logInfo("🎯 TARGET_DIR: " . TARGET_DIR);
    logInfo("💾 BACKUP_DIR: " . BACKUP_DIR);
    logInfo("📄 LOG_FILE: " . LOG_FILE);
    logInfo("🔀 USE_PROXY: " . (USE_PROXY ? 'Yes' : 'No'));
    if (USE_PROXY) {
        logInfo("🔀 PROXY_URL: " . PROXY_URL);
    }
    
    // Check if target directory exists
    if (!is_dir(TARGET_DIR)) {
        logError("Target directory does not exist: " . TARGET_DIR);
        return ['success' => false, 'upgraded' => false, 'error' => 'Target directory not found'];
    }
    
    if (empty(REPO_USER) || empty(REPO_NAME)) {
        logError("Repository configuration missing");
        return ['success' => false, 'upgraded' => false, 'error' => 'Repository configuration missing'];
    }
    
    logInfo("🚀 Starting update process");
    logInfo("📦 Repository: " . REPO_USER . "/" . REPO_NAME);
    logInfo("🌿 Branch: " . BRANCH);
    logInfo("📂 Target: " . TARGET_DIR);
    
    if (!class_exists('ZipArchive')) {
        logError("ZipArchive class not available");
        return ['success' => false, 'upgraded' => false, 'error' => 'ZipArchive not available'];
    }
    
    logSuccess("✓ ZipArchive available");
    
    logInfo("📡 Fetching latest commit...");
    $latestCommit = getLatestCommitInfo();
    
    if (!$latestCommit) {
        logError("Could not fetch commit info");
        testGitHubConnection();
        return ['success' => false, 'upgraded' => false, 'error' => 'Could not fetch commit info'];
    }
    
    logSuccess("✓ Got commit info: " . substr($latestCommit['sha'], 0, 7));
    logInfo("📝 Message: " . $latestCommit['message']);
    logInfo("👤 Author: " . $latestCommit['author']);
    logInfo("📅 Date: " . $latestCommit['date']);
    
    // Get detailed commit info
    logInfo("📋 Fetching commit details...");
    $commitDetails = getCommitDetails($latestCommit['sha']);
    if ($commitDetails) {
        logInfo("📊 Files changed: " . $commitDetails['stats']['files_changed']);
        logInfo("📊 Additions: +" . $commitDetails['stats']['total_additions'] . " / Deletions: -" . $commitDetails['stats']['total_deletions']);
        
        // Log file changes
        foreach ($commitDetails['files'] as $file) {
            logDebug("   " . $file['status'] . ": " . $file['filename'] . " (+" . $file['additions'] . "/-" . $file['deletions'] . ")");
        }
    }
    
    logInfo("🔍 Checking if update needed...");
    $updateNeeded = isUpdateNeeded($latestCommit['sha']);
    
    if (!$updateNeeded) {
        $current = file_exists(VERSION_FILE) ? substr(file_get_contents(VERSION_FILE), 0, 7) : 'N/A';
        logSuccess("✓ Already up to date: " . $current);
        return [
            'success' => true, 
            'upgraded' => false, 
            'new_version' => substr($latestCommit['sha'], 0, 7),
            'commit_details' => $commitDetails
        ];
    }
    
    $current = file_exists(VERSION_FILE) ? substr(file_get_contents(VERSION_FILE), 0, 7) : 'First install';
    logInfo("⬆️ Update needed! Current: $current, New: " . substr($latestCommit['sha'], 0, 7));
    
    if (BACKUP_BEFORE_UPDATE) {
        logInfo("💾 Creating backup...");
        logInfo("💾 Backup destination: " . BACKUP_DIR);
        $backupResult = createBackup();
        if ($backupResult) {
            logSuccess("✓ Backup created: " . $backupResult);
        } else {
            logWarning("Backup creation failed");
        }
    }
    
    logInfo("⬇️ Downloading repository...");
    $zipFile = downloadRepositoryZip($latestCommit['sha']);
    
    if (!$zipFile) {
        logError("Download failed");
        return ['success' => false, 'upgraded' => false, 'error' => 'Download failed'];
    }
    
    $size = round(filesize($zipFile) / 1024);
    logSuccess("✓ Downloaded: " . $size . " KB");
    
    logInfo("📂 Extracting files to: " . TARGET_DIR);
    $extractResult = extractAndReplace($zipFile);
    
    if (file_exists($zipFile)) {
        unlink($zipFile);
        logInfo("🧹 Cleaned temp file");
    }
    
    if (!$extractResult['success']) {
        logError("Extraction failed: " . $extractResult['error']);
        return ['success' => false, 'upgraded' => false, 'error' => $extractResult['error']];
    }
    
    logSuccess("✓ Files updated successfully!");
    logInfo("📊 Stats - Updated: " . $extractResult['files_updated'] . ", Skipped: " . $extractResult['files_skipped'] . ", Failed: " . $extractResult['files_failed']);
    
    if (file_put_contents(VERSION_FILE, $latestCommit['sha']) !== false) {
        logSuccess("✓ Version file updated");
    } else {
        logWarning("Could not update version file");
    }
    
    if (!empty(TELEGRAM_BOT_TOKEN) && !empty(TELEGRAM_CHAT_ID)) {
        logInfo("📱 Sending Telegram...");
        $telegramSent = sendTelegramNotification($latestCommit, $extractResult, $commitDetails);
        if ($telegramSent) {
            logSuccess("✓ Telegram sent");
        } else {
            logWarning("Telegram failed");
        }
    }
    
    $executionTime = round(microtime(true) - $startTime, 2);
    logSuccess("✓ Completed in {$executionTime}s");
    
    return [
        'success' => true,
        'upgraded' => true,
        'new_version' => substr($latestCommit['sha'], 0, 7),
        'commit_message' => $latestCommit['message'],
        'commit_details' => $commitDetails,
        'execution_time' => $executionTime,
        'files_updated' => $extractResult['files_updated'],
        'files_skipped' => $extractResult['files_skipped']
    ];
}

// ============================
// HELPER FUNCTIONS
// ============================

function testGitHubConnection() {
    logDebug("🔧 Testing GitHub connection...");
    
    $testHeaders = ['User-Agent: PHP-Update-Script'];
    if (!empty(GITHUB_TOKEN)) {
        $testHeaders[] = 'Authorization: token ' . GITHUB_TOKEN;
    }
    
    $response = proxyRequest('github', '/repos/' . REPO_USER . '/' . REPO_NAME, $testHeaders);
    logResponse($response['status_code'], $response['body']);
    
    if ($response['status_code'] === 401) {
        logError("GitHub token is invalid or expired");
    } elseif ($response['status_code'] === 403) {
        logError("Rate limit exceeded or no access to repository");
        if ($response['body']) {
            $data = json_decode($response['body'], true);
            if (isset($data['message'])) {
                logError("GitHub says: " . $data['message']);
            }
        }
    } elseif ($response['status_code'] === 404) {
        logError("Repository not found: " . REPO_USER . "/" . REPO_NAME);
    } elseif ($response['status_code'] === 200) {
        logSuccess("✓ GitHub connection successful");
    } else {
        logError("Unexpected response: " . $response['status_code']);
    }
}

function getLatestCommitInfo() {
    $apiPath = "/repos/" . REPO_USER . "/" . REPO_NAME . "/commits/" . BRANCH;
    
    $headers = [
        'User-Agent: PHP-Update-Script',
        'Accept: application/vnd.github.v3+json'
    ];
    
    if (!empty(GITHUB_TOKEN)) {
        $headers[] = 'Authorization: token ' . GITHUB_TOKEN;
        logDebug("Using GitHub token for authentication");
    } else {
        logDebug("No GitHub token - using public access (rate limited)");
    }
    
    logRequest($apiPath, $headers);
    
    $response = proxyRequest('github', $apiPath, $headers);
    
    logResponse($response['status_code'], $response['body']);
    
    if (!$response) {
        logError("No response from GitHub API");
        return false;
    }
    
    if ($response['status_code'] !== 200) {
        logError("GitHub API returned HTTP " . $response['status_code']);
        
        if ($response['body']) {
            $error = json_decode($response['body'], true);
            if (isset($error['message'])) {
                logError("Error message: " . $error['message']);
            }
            
            if ($response['status_code'] === 403 && isset($error['message']) && strpos($error['message'], 'rate limit') !== false) {
                logError("GitHub rate limit exceeded");
            }
        }
        return false;
    }
    
    if (empty($response['body'])) {
        logError("Empty response body from GitHub");
        return false;
    }
    
    $data = json_decode($response['body'], true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logError("JSON parse error: " . json_last_error_msg());
        logDebug("Raw response: " . substr($response['body'], 0, 500));
        return false;
    }
    
    if (!isset($data['sha'])) {
        logError("Invalid response structure - no SHA found");
        logDebug("Response structure: " . print_r($data, true));
        return false;
    }
    
    return [
        'sha' => $data['sha'],
        'message' => $data['commit']['message'] ?? 'No message',
        'author' => $data['commit']['author']['name'] ?? 'Unknown',
        'date' => isset($data['commit']['author']['date']) ? date('Y-m-d H:i', strtotime($data['commit']['author']['date'])) : ''
    ];
}

function isUpdateNeeded($latestSha) {
    if (!file_exists(VERSION_FILE)) {
        logDebug("Version file not found - first update");
        return true;
    }
    
    $currentSha = trim(@file_get_contents(VERSION_FILE));
    
    if (empty($currentSha)) {
        logDebug("Version file is empty");
        return true;
    }
    
    $currentShort = substr($currentSha, 0, 7);
    $latestShort = substr($latestSha, 0, 7);
    
    logDebug("Version check: Current=$currentShort, Latest=$latestShort");
    
    if ($currentShort === $latestShort) {
        logDebug("Versions match - no update needed");
        return false;
    }
    
    logDebug("New version available");
    return true;
}

function downloadRepositoryZip($commitSha) {
    $apiPath = "/repos/" . REPO_USER . "/" . REPO_NAME . "/zipball/" . BRANCH;
    
    $headers = [
        'User-Agent: PHP-Update-Script',
        'Accept: application/vnd.github.v3+json'
    ];
    
    if (!empty(GITHUB_TOKEN)) {
        $headers[] = 'Authorization: token ' . GITHUB_TOKEN;
    }
    
    logRequest($apiPath, $headers);
    
    $response = proxyRequest('github', $apiPath, $headers);
    
    logResponse($response['status_code'], null, $response['error'] ?? null);
    
    if (!$response) {
        logError("No response when downloading ZIP");
        return false;
    }
    
    if ($response['status_code'] !== 200) {
        logError("Download failed with HTTP " . $response['status_code']);
        return false;
    }
    
    if (empty($response['body'])) {
        logError("Downloaded file is empty");
        return false;
    }
    
    $tempFile = tempnam(sys_get_temp_dir(), 'github_') . '.zip';
    logDebug("Saving to temp file: " . $tempFile);
    
    $bytesWritten = @file_put_contents($tempFile, $response['body']);
    
    if ($bytesWritten === false) {
        logError("Failed to save ZIP file to disk");
        return false;
    }
    
    logDebug("ZIP saved: " . $bytesWritten . " bytes");
    
    // Validate ZIP magic bytes (PK\x03\x04)
    $handle = @fopen($tempFile, 'rb');
    if ($handle) {
        $magic = fread($handle, 4);
        fclose($handle);
        if ($magic !== "PK\x03\x04") {
            $preview = @file_get_contents($tempFile, false, null, 0, 200);
            logError("Downloaded file is NOT a valid ZIP (magic bytes: " . bin2hex(substr($magic, 0, 4)) . ")");
            logDebug("File preview: " . substr($preview, 0, 200));
            @unlink($tempFile);
            return false;
        }
        logDebug("ZIP magic bytes valid (PK header confirmed)");
    }
    
    return $tempFile;
}

function extractAndReplace($zipFile) {
    $result = [
        'success' => false,
        'error' => '',
        'files_updated' => 0,
        'files_skipped' => 0,
        'files_failed' => 0
    ];
    
    $zip = new ZipArchive;
    
    logDebug("Opening ZIP file: " . $zipFile);
    logDebug("ZIP file size: " . (file_exists($zipFile) ? filesize($zipFile) : 'NOT FOUND') . " bytes");
    
    $openResult = $zip->open($zipFile);
    if ($openResult !== true) {
        $errorCode = is_int($openResult) ? $openResult : 'unknown';
        $result['error'] = 'Cannot open ZIP (code ' . $errorCode . '): ' . $zip->getStatusString();
        logError("Failed to open ZIP file - error code: $errorCode, message: " . $zip->getStatusString());
        return $result;
    }
    
    logDebug("ZIP opened successfully, entries: " . $zip->numFiles);
    
    $tempDir = sys_get_temp_dir() . '/github_update_' . time();
    logDebug("Creating temp dir: " . $tempDir);
    
    if (!mkdir($tempDir, 0755, true)) {
        $result['error'] = 'Cannot create temp directory';
        $zip->close();
        return $result;
    }
    
    logDebug("Extracting to temp dir...");
    if (!$zip->extractTo($tempDir)) {
        $result['error'] = 'Cannot extract ZIP file';
        logError("Extraction failed");
        $zip->close();
        deleteDirectory($tempDir);
        return $result;
    }
    
    $zip->close();
    logDebug("Extraction completed");
    
    $folders = glob($tempDir . '/*', GLOB_ONLYDIR);
    if (empty($folders)) {
        $result['error'] = 'No folders found after extraction';
        logError("No extracted folders found");
        deleteDirectory($tempDir);
        return $result;
    }
    
    $sourceDir = $folders[0];
    logDebug("Source directory: " . basename($sourceDir));
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $excludedPatterns = [];
    foreach (EXCLUDE_FILES as $pattern) {
        $pattern = trim($pattern);
        if (!empty($pattern)) {
            $excludedPatterns[] = $pattern;
        }
    }
    
    logDebug("Exclusion patterns: " . implode(', ', $excludedPatterns));
    
    foreach ($files as $file) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($sourceDir) + 1);
        
        if ($file->isDir()) {
            continue;
        }
        
        $skip = false;
        foreach ($excludedPatterns as $pattern) {
            if (fnmatch($pattern, $relativePath) ||
                fnmatch($pattern, basename($relativePath))) {
                $skip = true;
                $result['files_skipped']++;
                logDebug("Skipping (excluded): $relativePath (pattern: $pattern)");
                break;
            }
        }
        
        if ($skip) {
            continue;
        }
        
        $destPath = TARGET_DIR . '/' . $relativePath;
        $destDir = dirname($destPath);
        
        if (!is_dir($destDir)) {
            if (!mkdir($destDir, 0755, true)) {
                logError("Failed to create directory: $destDir");
                $result['files_failed']++;
                continue;
            }
            logDebug("Created directory: $destDir");
        }
        
        if (copy($filePath, $destPath)) {
            $result['files_updated']++;
            logDebug("Updated: $relativePath");
        } else {
            $result['files_failed']++;
            logError("Failed to copy: $relativePath");
        }
    }
    
    if (DELETE_EXTRACTED_FILES) {
        deleteDirectory($tempDir);
        logDebug("Cleaned temp directory");
    }
    
    $result['success'] = true;
    return $result;
}

function createBackup() {
    if (!is_dir(BACKUP_DIR)) {
        logDebug("Creating backup directory: " . BACKUP_DIR);
        if (!mkdir(BACKUP_DIR, 0755, true)) {
            logError("Failed to create backup directory");
            return false;
        }
        
        $htaccess = BACKUP_DIR . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Order Deny,Allow\nDeny from all\n");
        }
    }
    
    $backupName = BACKUP_DIR . '/backup_' . date('Y-m-d_His') . '.zip';
    logDebug("Creating backup: " . basename($backupName));
    
    $zip = new ZipArchive;
    if ($zip->open($backupName, ZipArchive::CREATE) !== true) {
        logError("Failed to create backup ZIP");
        return false;
    }
    
    $backupSource = TARGET_DIR;
    logDebug("Backup source: $backupSource");
    
    // Determine backup dir relative to target (if inside target)
    $backupDirRelative = null;
    $realTarget = realpath($backupSource);
    $realBackup = realpath(BACKUP_DIR);
    if ($realTarget && $realBackup && strpos($realBackup, $realTarget . DIRECTORY_SEPARATOR) === 0) {
        $backupDirRelative = substr($realBackup, strlen($realTarget) + 1) . DIRECTORY_SEPARATOR;
        logDebug("Backup dir is inside target, skipping: " . $backupDirRelative);
    }
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($backupSource, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $addedFiles = 0;
    foreach ($files as $file) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($backupSource) + 1);
        
        if ($backupDirRelative !== null && strpos($relativePath, $backupDirRelative) === 0) {
            continue;
        }
        
        // Skip git directory
        if (strpos($relativePath, 'git/') === 0) {
            continue;
        }
        
        $skip = false;
        foreach (EXCLUDE_FILES as $pattern) {
            $pattern = trim($pattern);
            if (empty($pattern)) continue;
            
            if (fnmatch($pattern, $relativePath) ||
                fnmatch($pattern, basename($relativePath))) {
                $skip = true;
                break;
            }
        }
        
        if ($skip) {
            continue;
        }
        
        if ($file->isDir()) {
            $zip->addEmptyDir($relativePath);
        } else {
            $zip->addFile($filePath, $relativePath);
            $addedFiles++;
        }
    }
    
    $zip->close();
    
    if ($addedFiles > 0) {
        $size = round(filesize($backupName) / 1024);
        logDebug("Backup created: " . basename($backupName) . " ($size KB, $addedFiles files)");
        return basename($backupName);
    }
    
    @unlink($backupName);
    logWarning("No files added to backup");
    return false;
}

function sendTelegramNotification($commitInfo, $extractResult, $commitDetails = null) {
    if (empty(TELEGRAM_BOT_TOKEN) || empty(TELEGRAM_CHAT_ID)) {
        logDebug("Telegram not configured");
        return false;
    }
    
    // Get the request URL
    $requestUrl = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
    
    // Build message with full commit details
    $message = "🚀 *Website Update Successful!*\n\n";
    $message .= "📦 *Repository:* " . REPO_USER . "/" . REPO_NAME . "\n";
    $message .= "🌿 *Branch:* " . BRANCH . "\n";
    $message .= "🔖 *Commit:* " . substr($commitInfo['sha'], 0, 7) . "\n";
    
    // Full commit message
    $fullMessage = $commitInfo['message'];
    $message .= "📝 *Message:* " . $fullMessage . "\n";
    
    $message .= "👤 *Author:* " . $commitInfo['author'] . "\n";
    $message .= "📅 *Time:* " . date('Y-m-d H:i:s') . "\n";
    
    // Add commit details if available
    if ($commitDetails) {
        $stats = $commitDetails['stats'];
        $message .= "\n📊 *Changes:* " . $stats['files_changed'] . " files changed";
        $message .= " (+" . $stats['total_additions'] . "/-" . $stats['total_deletions'] . ")\n";
        
        // List changed files (max 10)
        $filesList = "";
        $count = 0;
        foreach ($commitDetails['files'] as $file) {
            if ($count >= 10) {
                $filesList .= "\n  ... and " . (count($commitDetails['files']) - 10) . " more files";
                break;
            }
            $statusIcon = $file['status'] === 'added' ? '➕' : 
                         ($file['status'] === 'deleted' ? '➖' : '📝');
            $filesList .= "\n  " . $statusIcon . " `" . basename($file['filename']) . "`";
            $filesList .= " (+" . $file['additions'] . "/-" . $file['deletions'] . ")";
            $count++;
        }
        if (!empty($filesList)) {
            $message .= "📁 *Files:*" . $filesList . "\n";
        }
    }
    
    $message .= "\n📊 *Update Stats:* " . $extractResult['files_updated'] . " files updated\n";
    $message .= "🔗 *URL:* " . $requestUrl;
    
    // Prepare headers for proxy
    $headers = [
        'Content-Type' => 'application/json',
        'token' => TELEGRAM_BOT_TOKEN
    ];
    
    $postData = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];
    
    logDebug("Sending Telegram notification to " . TELEGRAM_CHAT_ID);
    
    // Use proxy for Telegram
    $response = proxyRequest('telegram', '/sendMessage', $headers, 'POST', $postData);
    
    if ($response && $response['status_code'] === 200) {
        logDebug("Telegram notification sent successfully");
        return true;
    } else {
        logError("Telegram notification failed: " . ($response['error'] ?? 'Unknown error'));
        if ($response && $response['body']) {
            logDebug("Telegram response: " . $response['body']);
        }
        return false;
    }
}

function fetchUrl($url, $headers = [], $method = 'GET', $postData = null) {
    $result = ['body' => null, 'status_code' => 0, 'error' => null, 'headers' => []];
    
    if (function_exists('curl_init')) {
        $ch = curl_init();
        if (!$ch) {
            $result['error'] = 'curl_init failed';
            return $result;
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-Update-Script');
        curl_setopt($ch, CURLOPT_HEADER, 1);
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        if ($method === 'POST' && $postData !== null) {
            curl_setopt($ch, CURLOPT_POST, 1);
            if (is_array($postData)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
                // Add content-type header if not already set
                $hasContentType = false;
                foreach ($headers as $header) {
                    if (stripos($header, 'Content-Type:') !== false) {
                        $hasContentType = true;
                        break;
                    }
                }
                if (!$hasContentType) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
                }
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            }
        }
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            $result['error'] = curl_error($ch);
        } else {
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headerStr = substr($response, 0, $headerSize);
            $result['body'] = substr($response, $headerSize);
            $result['status_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            $headersArray = explode("\r\n", $headerStr);
            foreach ($headersArray as $header) {
                if (strpos($header, ':') !== false) {
                    list($key, $value) = explode(':', $header, 2);
                    $result['headers'][trim($key)] = trim($value);
                }
            }
        }
        
        curl_close($ch);
        unset($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $contextOptions = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'timeout' => 60,
                'ignore_errors' => true
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ];
        
        if ($method === 'POST' && $postData !== null) {
            if (is_array($postData)) {
                $contextOptions['http']['content'] = json_encode($postData);
                $contextOptions['http']['header'] .= "\r\nContent-Type: application/json";
            } else {
                $contextOptions['http']['content'] = $postData;
            }
        }
        
        $context = stream_context_create($contextOptions);
        $result['body'] = @file_get_contents($url, false, $context);
        
        if ($result['body'] !== false && isset($http_response_header[0])) {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
            if (isset($matches[1])) {
                $result['status_code'] = (int)$matches[1];
            }
            
            foreach ($http_response_header as $header) {
                if (strpos($header, ':') !== false) {
                    list($key, $value) = explode(':', $header, 2);
                    $result['headers'][trim($key)] = trim($value);
                }
            }
        } else {
            $result['error'] = 'file_get_contents failed';
        }
    } else {
        $result['error'] = 'No HTTP method available';
    }
    
    return $result;
}

function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            @unlink($path);
        }
    }
    
    @rmdir($dir);
}

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

// ============================
// HANDLE AJAX ACTIONS
// ============================

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$key = $_GET['key'] ?? '';

// Save settings
if ($action === 'save_settings' && securityCheck()) {
    $newConfig = [
        'GITHUB_TOKEN' => $_POST['GITHUB_TOKEN'] ?? '',
        'REPO_USER' => $_POST['REPO_USER'] ?? 'farhamaghdasi',
        'REPO_NAME' => $_POST['REPO_NAME'] ?? 'arash-laravel-panel',
        'BRANCH' => $_POST['BRANCH'] ?? 'main',
        'TELEGRAM_BOT_TOKEN' => $_POST['TELEGRAM_BOT_TOKEN'] ?? '',
        'TELEGRAM_CHAT_ID' => $_POST['TELEGRAM_CHAT_ID'] ?? '',
        'BACKUP_BEFORE_UPDATE' => ($_POST['BACKUP_BEFORE_UPDATE'] ?? 'true') === 'true' ? 'true' : 'false',
        'BACKUP_DIR' => $_POST['BACKUP_DIR'] ?? '__backups',
        'LOG_FILE' => $_POST['LOG_FILE'] ?? 'update_log.txt',
        'VERSION_FILE' => $_POST['VERSION_FILE'] ?? '.version',
        'EXCLUDE_FILES' => $_POST['EXCLUDE_FILES'] ?? 'git,.env,__backups,.git*,config*.php,database*,*.sql,*.log,update_log.txt',
        'DELETE_EXTRACTED_FILES' => ($_POST['DELETE_EXTRACTED_FILES'] ?? 'true') === 'true' ? 'true' : 'false',
        'TARGET_DIR' => $_POST['TARGET_DIR'] ?? dirname(__DIR__),
        'USE_PROXY' => ($_POST['USE_PROXY'] ?? 'false') === 'true' ? 'true' : 'false',
        'PROXY_URL' => $_POST['PROXY_URL'] ?? ''
    ];
    
    if (saveConfig($newConfig)) {
        echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save settings']);
    }
    exit;
}

// Download backup
if ($action === 'download_backup' && securityCheck()) {
    $backupFile = $_GET['file'] ?? '';
    $backupPath = BACKUP_DIR . '/' . basename($backupFile);
    
    if (file_exists($backupPath)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($backupPath) . '"');
        header('Content-Length: ' . filesize($backupPath));
        readfile($backupPath);
        exit;
    } else {
        echo 'Backup file not found';
        exit;
    }
}

// Delete backup
if ($action === 'delete_backup' && securityCheck()) {
    $backupFile = $_POST['file'] ?? '';
    $backupPath = BACKUP_DIR . '/' . basename($backupFile);
    
    if (file_exists($backupPath)) {
        if (unlink($backupPath)) {
            echo json_encode(['success' => true, 'message' => 'Backup deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete backup']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Backup file not found']);
    }
    exit;
}

// Clear log
if ($action === 'clear_log' && securityCheck()) {
    if (file_exists(LOG_FILE)) {
        if (unlink(LOG_FILE)) {
            echo json_encode(['success' => true, 'message' => 'Log cleared successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to clear log']);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'Log file does not exist']);
    }
    exit;
}

// Get log file size (in MB)
if ($action === 'get_log_size' && securityCheck()) {
    $size = 0;
    if (file_exists(LOG_FILE)) {
        $size = round(filesize(LOG_FILE) / (1024 * 1024), 2);
    }
    echo json_encode(['size' => $size, 'path' => LOG_FILE]);
    exit;
}

// Get backup size (in MB)
if ($action === 'get_backup_size' && securityCheck()) {
    $backupFile = $_POST['file'] ?? '';
    $backupPath = BACKUP_DIR . '/' . basename($backupFile);
    $size = 0;
    if (file_exists($backupPath)) {
        $size = round(filesize($backupPath) / (1024 * 1024), 2);
    }
    echo json_encode(['size' => $size, 'path' => $backupPath]);
    exit;
}

// Run update
if ($action === 'run_update' && securityCheck()) {
    // Start fresh log
    @unlink(LOG_FILE);
    logInfo("=" . str_repeat("=", 60));
    logInfo("GITHUB AUTO-UPDATE STARTED");
    logInfo("📂 Target Directory: " . TARGET_DIR);
    
    $result = performUpdate();
    
    logInfo("=" . str_repeat("=", 60));
    if ($result['success']) {
        if ($result['upgraded']) {
            logSuccess("UPDATE SUCCESSFUL - Version: " . $result['new_version']);
        } else {
            logInfo("ALREADY UP TO DATE - Version: " . $result['new_version']);
        }
    } else {
        logError("UPDATE FAILED: " . ($result['error'] ?? 'Unknown error'));
    }
    logInfo("=" . str_repeat("=", 60));
    
    echo json_encode($result);
    exit;
}

// ============================
// WEB INTERFACE
// ============================

if (!securityCheck() && php_sapi_name() !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    echo "Access Denied\n";
    echo "Use ?key=YOUR_SECRET_KEY or add your IP to .ip_whitelist\n";
    exit;
}

// Get current version
$currentVersion = file_exists(VERSION_FILE) ? substr(file_get_contents(VERSION_FILE), 0, 7) : 'N/A';

// Get latest commit info
$latestCommit = null;
$updateAvailable = false;
$commitDetails = null;
$commitInfo = @getLatestCommitInfo();
if ($commitInfo) {
    $latestCommit = $commitInfo;
    $updateAvailable = isUpdateNeeded($commitInfo['sha']);
    // Get commit details
    $commitDetails = getCommitDetails($commitInfo['sha']);
}

// Get backups
$backups = getBackups();

// Delete all backups
if ($action === 'delete_all_backups' && securityCheck()) {
    $backups = getBackups();
    $deleted = 0;
    $errors = [];
    
    foreach ($backups as $backup) {
        $backupPath = $backup['path'];
        if (file_exists($backupPath)) {
            if (unlink($backupPath)) {
                $deleted++;
            } else {
                $errors[] = basename($backupPath);
            }
        }
    }
    
    if (empty($errors)) {
        echo json_encode(['success' => true, 'message' => "$deleted backup(s) deleted successfully"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting some backups: ' . implode(', ', $errors)]);
    }
    exit;
}

// Get log content
$logContent = @file_get_contents(LOG_FILE) ?: '';

// Include header component
include __DIR__ . '/assets/header.php';
?>
        <!-- Header -->
        <div class="header">
            <h1>🔄 GitHub Auto-Update</h1>
            <p class="repo-info">📦 <?php echo htmlspecialchars(REPO_USER . '/' . REPO_NAME); ?> | 🌿 <?php echo htmlspecialchars(BRANCH); ?></p>
            <p class="repo-info" style="font-size: 12px; color: #888;">📂 Target: <?php echo htmlspecialchars(TARGET_DIR); ?></p>
        </div>
        
        <!-- Status Cards -->
        <div class="status-cards">
            <div class="status-card info">
                <div class="icon">📌</div>
                <div class="label">Current Version</div>
                <div class="value"><?php echo htmlspecialchars($currentVersion); ?></div>
            </div>
            <?php if ($latestCommit): ?>
            <div class="status-card success">
                <div class="icon">🔖</div>
                <div class="label">Latest Commit</div>
                <div class="value"><?php echo htmlspecialchars(substr($latestCommit['sha'], 0, 7)); ?></div>
            </div>
            <div class="status-card info">
                <div class="icon">📝</div>
                <div class="label">Commit Message</div>
                <div class="value" style="font-size: 13px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <?php 
                    $msg = $latestCommit['message'];
                    echo htmlspecialchars(strlen($msg) > 50 ? substr($msg, 0, 50) . '...' : $msg);
                    ?>
                </div>
            </div>
            <div class="status-card warning">
                <div class="icon">📅</div>
                <div class="label">Date</div>
                <div class="value"><?php echo htmlspecialchars($latestCommit['date']); ?></div>
            </div>
            <?php endif; ?>
            <div class="status-card <?php echo $updateAvailable ? 'success' : 'info'; ?>">
                <div class="icon">🔄</div>
                <div class="label">Status</div>
                <div class="value"><?php echo $updateAvailable ? 'Update Available' : 'Up to Date'; ?></div>
            </div>
        </div>
        
        <!-- Commit Details Card (NEW) -->
        <?php if ($commitDetails && !empty($commitDetails['files'])): ?>
        <div class="card">
            <div class="card-header">
                <h2>📋 Commit Details</h2>
                <span style="background: #667eea; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px;">
                    <?php echo $commitDetails['stats']['files_changed']; ?> files changed
                </span>
            </div>
            <div class="card-body">
                <div style="margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <strong>📝 Full Message:</strong>
                    <p style="margin-top: 8px; color: #333; white-space: pre-wrap; word-break: break-word;">
                        <?php echo htmlspecialchars($commitDetails['message']); ?>
                    </p>
                </div>
                
                <div style="margin-bottom: 10px; display: flex; gap: 20px; flex-wrap: wrap;">
                    <span>👤 <strong>Author:</strong> <?php echo htmlspecialchars($commitDetails['author']); ?></span>
                    <span>📅 <strong>Date:</strong> <?php echo htmlspecialchars($commitDetails['date']); ?></span>
                    <span>➕ <strong>Additions:</strong> <span style="color: #27ae60;"><?php echo $commitDetails['stats']['total_additions']; ?></span></span>
                    <span>➖ <strong>Deletions:</strong> <span style="color: #e74c3c;"><?php echo $commitDetails['stats']['total_deletions']; ?></span></span>
                </div>
                
                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #eee; border-radius: 8px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead style="background: #f8f9fa; position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th style="padding: 10px; text-align: left;">Status</th>
                                <th style="padding: 10px; text-align: left;">File</th>
                                <th style="padding: 10px; text-align: center;">Additions</th>
                                <th style="padding: 10px; text-align: center;">Deletions</th>
                                <th style="padding: 10px; text-align: center;">Changes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commitDetails['files'] as $file): 
                                $statusIcon = $file['status'] === 'added' ? '➕' : 
                                             ($file['status'] === 'deleted' ? '➖' : '📝');
                                $statusColor = $file['status'] === 'added' ? '#27ae60' : 
                                              ($file['status'] === 'deleted' ? '#e74c3c' : '#f39c12');
                            ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 8px 10px; color: <?php echo $statusColor; ?>;">
                                    <?php echo $statusIcon; ?> <?php echo htmlspecialchars($file['status']); ?>
                                </td>
                                <td style="padding: 8px 10px; font-family: monospace; font-size: 12px;">
                                    <?php echo htmlspecialchars(basename($file['filename'])); ?>
                                    <span style="color: #999; font-size: 11px;">(<?php echo htmlspecialchars(dirname($file['filename'])); ?>)</span>
                                </td>
                                <td style="padding: 8px 10px; text-align: center; color: #27ae60;">
                                    <?php echo $file['additions']; ?>
                                </td>
                                <td style="padding: 8px 10px; text-align: center; color: #e74c3c;">
                                    <?php echo $file['deletions']; ?>
                                </td>
                                <td style="padding: 8px 10px; text-align: center;">
                                    <?php echo $file['changes']; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Update Banner -->
        <?php if ($updateAvailable): ?>
        <div class="update-banner">
            <div class="info">
                <h3>🚀 New Update Available!</h3>
                <p>Version <?php echo htmlspecialchars(substr($latestCommit['sha'], 0, 7)); ?> is ready to install</p>
                <?php if ($commitDetails): ?>
                <p style="font-size: 13px; opacity: 0.9; margin-top: 5px;">
                    📊 <?php echo $commitDetails['stats']['files_changed']; ?> files changed 
                    (+<?php echo $commitDetails['stats']['total_additions']; ?>/<?php echo $commitDetails['stats']['total_deletions']; ?>)
                </p>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn btn-success" onclick="startUpdate()">
                    ▶️ Start Update
                </button>
                <button class="btn btn-secondary" onclick="showSettingsModal()">
                    ⚙️ Settings
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="update-banner" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
            <div class="info">
                <h3>✅ System is Up to Date</h3>
                <p>You are using the latest version</p>
            </div>
            <button class="btn btn-primary" onclick="forceUpdate()">
                🔄 Check Again
            </button>
        </div>
        <?php endif; ?>
        
        <!-- Main Actions Card -->
        <div class="card">
            <div class="card-header">
                <h2>📋 Operations</h2>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-primary" onclick="showSettingsModal()">
                        ⚙️ Settings
                    </button>
                    <button class="btn btn-secondary" onclick="refreshStatus()">
                        🔄 Refresh Status
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="updateProgress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <p id="progressText" style="text-align: center; color: #666;">Updating...</p>
                </div>
            </div>
        </div>
        
        <!-- Backups Card -->
        <div class="card">
            <div class="card-header">
                <h2>💾 Backups</h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <span style="background: #667eea; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px;">
                        <?php echo count($backups); ?> items
                    </span>
                    <?php if (!empty($backups)): ?>
                    <button class="btn btn-warning btn-icon" onclick="deleteAllBackups()">
                        🗑️ Delete All
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($backups)): ?>
                <div class="empty-state">
                    <div class="icon">📦</div>
                    <p>No backups available</p>
                </div>
                <?php else: ?>
                <ul class="backup-list">
                    <?php foreach ($backups as $backup): 
                        $backupSizeMB = round(filesize($backup['path']) / (1024 * 1024), 2);
                        $isLarge = $backupSizeMB > 200;
                    ?>
                    <li class="backup-item">
                        <div class="backup-info">
                            <div class="backup-icon">📦<?php if ($isLarge): ?><span class="warning-badge">⚠️</span><?php endif; ?></div>
                            <div class="backup-details">
                                <div class="name"><?php echo htmlspecialchars($backup['name']); ?><?php if ($isLarge): ?> <span style="color: #e74c3c; font-size: 12px;">(<?php echo $backupSizeMB; ?> MB)</span><?php endif; ?></div>
                                <div class="meta"><?php echo $backup['size']; ?> KB | <?php echo htmlspecialchars($backup['date']); ?></div>
                            </div>
                        </div>
                        <div class="backup-actions">
                            <a href="?action=download_backup&key=<?php echo htmlspecialchars($key); ?>&file=<?php echo urlencode($backup['name']); ?>" class="btn btn-primary btn-icon">
                                ⬇️ Download
                            </a>
                            <button class="btn btn-danger btn-icon" onclick="deleteBackup('<?php echo htmlspecialchars($backup['name']); ?>')">
                                🗑️ Delete
                            </button>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Log Card -->
        <?php
        $logSizeMB = file_exists(LOG_FILE) ? round(filesize(LOG_FILE) / (1024 * 1024), 2) : 0;
        $logIsLarge = $logSizeMB > 200;
        ?>
        <div class="card">
            <div class="card-header">
                <h2>📜 Operation Log<?php if ($logIsLarge): ?><span class="warning-badge">⚠️ <?php echo $logSizeMB; ?> MB</span><?php endif; ?></h2>
                <button class="btn btn-secondary btn-icon" onclick="clearLog()">
                    🧹 Clear
                </button>
            </div>
            <div class="card-body">
                <div class="log-container" id="logContainer">
                    <?php
                    $lines = explode("\n", $logContent);
                    foreach ($lines as $line) {
                        if (empty(trim($line))) continue;
                        
                        $class = 'info';
                        if (strpos($line, '[ERROR]') !== false) $class = 'error';
                        elseif (strpos($line, '[WARNING]') !== false) $class = 'warning';
                        elseif (strpos($line, '[SUCCESS]') !== false) $class = 'success';
                        elseif (strpos($line, '[DEBUG]') !== false) $class = 'debug';
                        
                        // Mask sensitive data in log display
                        $line = maskSensitiveData($line);
                        echo '<div class="log-line ' . $class . '">' . htmlspecialchars($line) . '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include modals and JavaScript from footer.php -->
    <?php include __DIR__ . '/assets/footer.php'; ?>