<?php
/**
 * GitHub Auto-Update Script for Shared Hosting
 * 
 * This script updates the wallet directory from a GitHub repository.
 * Script is placed in /wallet/git/ and updates /wallet/
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
define('REPO_NAME', (string)($config['REPO_NAME'] ?? 'trust-wallet-balance-checker'));
/** @var string BRANCH */
define('BRANCH', (string)($config['BRANCH'] ?? 'main'));
/** @var string TELEGRAM_BOT_TOKEN */
define('TELEGRAM_BOT_TOKEN', (string)($config['TELEGRAM_BOT_TOKEN'] ?? ''));
/** @var string TELEGRAM_CHAT_ID */
define('TELEGRAM_CHAT_ID', (string)($config['TELEGRAM_CHAT_ID'] ?? ''));
/** @var bool BACKUP_BEFORE_UPDATE */
define('BACKUP_BEFORE_UPDATE', (bool)($config['BACKUP_BEFORE_UPDATE'] ?? true));
/** @var string BACKUP_DIR */
define('BACKUP_DIR', BASE_DIR . '/' . ($config['BACKUP_DIR'] ?? '__backups'));
/** @var string LOG_FILE */
define('LOG_FILE', SCRIPT_DIR . '/' . ($config['LOG_FILE'] ?? 'update_log.txt'));
/** @var string VERSION_FILE */
define('VERSION_FILE', SCRIPT_DIR . '/' . ($config['VERSION_FILE'] ?? '.version'));
/** @var string[] EXCLUDE_FILES */
define('EXCLUDE_FILES', explode(',', (string)($config['EXCLUDE_FILES'] ?? 'git,.env,__backups,.git*,config*.php,database*,*.sql,*.log,update_log.txt')));
/** @var bool DELETE_EXTRACTED_FILES */
define('DELETE_EXTRACTED_FILES', (bool)($config['DELETE_EXTRACTED_FILES'] ?? true));
/** @var string TARGET_DIR */
define('TARGET_DIR', BASE_DIR);

/**
 * Load configuration from .env file
 */
function loadConfig() {
    $configFile = SCRIPT_DIR . '/.env';
    
    $defaultConfig = [
        'GITHUB_TOKEN' => '',
        'REPO_USER' => 'farhamaghdasi',
        'REPO_NAME' => 'trust-wallet-balance-checker',
        'BRANCH' => 'main',
        'TELEGRAM_BOT_TOKEN' => '',
        'TELEGRAM_CHAT_ID' => '',
        'BACKUP_BEFORE_UPDATE' => 'true',
        'BACKUP_DIR' => '__backups',
        'LOG_FILE' => 'update_log.txt',
        'VERSION_FILE' => '.version',
        'EXCLUDE_FILES' => 'git,.env,__backups,.git*,config*.php,database*,*.sql,*.log,update_log.txt',
        'DELETE_EXTRACTED_FILES' => 'true'
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
            logDebug("   $key = " . (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value));
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

function logInfo($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [INFO] $message\n";
    // Only echo if not an AJAX request
    if (!isAjaxRequest()) {
        echo $logEntry;
    }
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logError($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [ERROR] ❌ $message\n";
    // Only echo if not an AJAX request
    if (!isAjaxRequest()) {
        echo $logEntry;
    }
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logEntry, $logEntry, FILE_APPEND);
}

function logSuccess($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [SUCCESS] ✅ $message\n";
    // Only echo if not an AJAX request
    if (!isAjaxRequest()) {
        echo $logEntry;
    }
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logWarning($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [WARNING] ⚠️ $message\n";
    // Only echo if not an AJAX request
    if (!isAjaxRequest()) {
        echo $logEntry;
    }
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logDebug($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [DEBUG] 🔍 $message\n";
    // Never echo debug messages, only log to file
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logRequest($url, $headers, $method = 'GET') {
    logDebug("🌐 REQUEST: $method $url");
    logDebug("   Headers: " . json_encode($headers, JSON_UNESCAPED_SLASHES));
}

function logResponse($statusCode, $body = null, $error = null) {
    logDebug("📡 RESPONSE: HTTP $statusCode");
    
    if ($error) {
        logDebug("   Error: $error");
    }
    
    if ($body && strlen($body) < 1000) {
        logDebug("   Body preview: " . substr($body, 0, 500) . (strlen($body) > 500 ? '...' : ''));
    } elseif ($body) {
        logDebug("   Body size: " . strlen($body) . " bytes");
    }
}

// ============================
// MAIN UPDATE FUNCTION
// ============================

function performUpdate() {
    $startTime = microtime(true);
    
    logInfo("📁 BASE_DIR: " . BASE_DIR);
    logInfo("📁 SCRIPT_DIR: " . SCRIPT_DIR);
    logInfo("🎯 TARGET_DIR: " . TARGET_DIR);
    logInfo("📄 LOG_FILE: " . LOG_FILE);
    
    if (!is_dir(TARGET_DIR)) {
        logError("Target directory does not exist: " . TARGET_DIR);
        return ['success' => false, 'upgraded' => false];
    }
    
    if (empty(REPO_USER) || empty(REPO_NAME)) {
        logError("Repository configuration missing");
        return ['success' => false, 'upgraded' => false];
    }
    
    logInfo("🚀 Starting update process");
    logInfo("📦 Repository: " . REPO_USER . "/" . REPO_NAME);
    logInfo("🌿 Branch: " . BRANCH);
    logInfo("🔑 GitHub Token: " . (!empty(GITHUB_TOKEN) ? 'Yes (Length: ' . strlen(GITHUB_TOKEN) . ')' : 'No (public repo)'));
    
    if (!class_exists('ZipArchive')) {
        logError("ZipArchive class not available");
        return ['success' => false, 'upgraded' => false];
    }
    
    logSuccess("✓ ZipArchive available");
    
    logInfo("📡 Fetching latest commit...");
    $latestCommit = getLatestCommitInfo();
    
    if (!$latestCommit) {
        logError("Could not fetch commit info");
        testGitHubConnection();
        return ['success' => false, 'upgraded' => false];
    }
    
    logSuccess("✓ Got commit info: " . substr($latestCommit['sha'], 0, 7));
    logInfo("📝 Message: " . $latestCommit['message']);
    logInfo("👤 Author: " . $latestCommit['author']);
    logInfo("📅 Date: " . $latestCommit['date']);
    
    logInfo("🔍 Checking if update needed...");
    $updateNeeded = isUpdateNeeded($latestCommit['sha']);
    
    if (!$updateNeeded) {
        $current = file_exists(VERSION_FILE) ? substr(file_get_contents(VERSION_FILE), 0, 7) : 'N/A';
        logSuccess("✓ Already up to date: " . $current);
        return ['success' => true, 'upgraded' => false, 'new_version' => substr($latestCommit['sha'], 0, 7)];
    }
    
    $current = file_exists(VERSION_FILE) ? substr(file_get_contents(VERSION_FILE), 0, 7) : 'First install';
    logInfo("⬆️ Update needed! Current: $current, New: " . substr($latestCommit['sha'], 0, 7));
    
    if (BACKUP_BEFORE_UPDATE) {
        logInfo("💾 Creating backup...");
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
        return ['success' => false, 'upgraded' => false];
    }
    
    $size = round(filesize($zipFile) / 1024);
    logSuccess("✓ Downloaded: " . $size . " KB");
    
    logInfo("📂 Extracting files...");
    $extractResult = extractAndReplace($zipFile);
    
    if (file_exists($zipFile)) {
        unlink($zipFile);
        logInfo("🧹 Cleaned temp file");
    }
    
    if (!$extractResult['success']) {
        logError("Extraction failed: " . $extractResult['error']);
        return ['success' => false, 'upgraded' => false];
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
        $telegramSent = sendTelegramNotification($latestCommit, $extractResult);
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
    
    $testUrl = "https://api.github.com";
    logDebug("   Testing: $testUrl");
    $response = fetchUrl($testUrl, ['User-Agent: PHP-Update-Script']);
    logResponse($response['status_code'], $response['body']);
    
    $repoUrl = "https://api.github.com/repos/" . REPO_USER . "/" . REPO_NAME;
    logDebug("   Testing repository: $repoUrl");
    
    $headers = ['User-Agent: PHP-Update-Script'];
    if (!empty(GITHUB_TOKEN)) {
        $headers[] = 'Authorization: token ' . GITHUB_TOKEN;
    }
    
    $response = fetchUrl($repoUrl, $headers);
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
    }
}

function getLatestCommitInfo() {
    $apiUrl = "https://api.github.com/repos/" . REPO_USER . "/" . REPO_NAME . "/commits/" . BRANCH;
    
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
    
    logRequest($apiUrl, $headers);
    
    $response = fetchUrl($apiUrl, $headers);
    
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
                if (isset($response['headers']['X-RateLimit-Remaining'])) {
                    logError("Rate limit remaining: " . $response['headers']['X-RateLimit-Remaining']);
                }
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
    $zipUrl = "https://api.github.com/repos/" . REPO_USER . "/" . REPO_NAME . "/zipball/" . BRANCH;
    
    $headers = [
        'User-Agent: PHP-Update-Script',
        'Accept: application/vnd.github.v3+json'
    ];
    
    if (!empty(GITHUB_TOKEN)) {
        $headers[] = 'Authorization: token ' . GITHUB_TOKEN;
    }
    
    logRequest($zipUrl, $headers);
    
    $response = fetchUrl($zipUrl, $headers);
    
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
    logDebug("Saving to temp file: " . basename($tempFile));
    
    $bytesWritten = @file_put_contents($tempFile, $response['body']);
    
    if ($bytesWritten === false) {
        logError("Failed to save ZIP file to disk");
        return false;
    }
    
    logDebug("ZIP saved: " . $bytesWritten . " bytes");
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
    
    if ($zip->open($zipFile) !== true) {
        $result['error'] = 'Cannot open ZIP: ' . $zip->getStatusString();
        logError("Failed to open ZIP file");
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
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($backupSource, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $addedFiles = 0;
    foreach ($files as $file) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($backupSource) + 1);
        
        if (strpos($relativePath, basename(BACKUP_DIR) . '/') === 0) {
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

function sendTelegramNotification($commitInfo, $extractResult) {
    if (empty(TELEGRAM_BOT_TOKEN) || empty(TELEGRAM_CHAT_ID)) {
        logDebug("Telegram not configured");
        return false;
    }
    
    // Format as requested by user
    $message = "🚀 *Website Update Successful!*\n\n";
    $message .= "📦 *Repository:* " . REPO_USER . "/" . REPO_NAME . "\n";
    $message .= "🌿 *Branch:* " . BRANCH . "\n";
    $message .= "🔖 *Commit:* " . substr($commitInfo['sha'], 0, 7) . "\n";
    $message .= "📝 *Message:* " . $commitInfo['message'] . "\n";
    $message .= "👤 *Author:* " . $commitInfo['author'] . "\n";
    $message .= "📊 *Files:* " . $extractResult['files_updated'] . " updated\n";
    $message .= "🕐 *Time:* " . date('Y-m-d H:i:s');
    
    $telegramUrl = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    
    $data = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];
    
    $response = fetchUrl($telegramUrl, [], 'POST', $data);
    
    return $response && $response['status_code'] === 200;
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
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
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
            $contextOptions['http']['content'] = http_build_query($postData);
            $contextOptions['http']['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded";
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
        'REPO_NAME' => $_POST['REPO_NAME'] ?? 'trust-wallet-balance-checker',
        'BRANCH' => $_POST['BRANCH'] ?? 'main',
        'TELEGRAM_BOT_TOKEN' => $_POST['TELEGRAM_BOT_TOKEN'] ?? '',
        'TELEGRAM_CHAT_ID' => $_POST['TELEGRAM_CHAT_ID'] ?? '',
        'BACKUP_BEFORE_UPDATE' => ($_POST['BACKUP_BEFORE_UPDATE'] ?? 'true') === 'true' ? 'true' : 'false',
        'BACKUP_DIR' => $_POST['BACKUP_DIR'] ?? '__backups',
        'LOG_FILE' => $_POST['LOG_FILE'] ?? 'update_log.txt',
        'VERSION_FILE' => $_POST['VERSION_FILE'] ?? '.version',
        'EXCLUDE_FILES' => $_POST['EXCLUDE_FILES'] ?? 'git,.env,__backups,.git*,config*.php,database*,*.sql,*.log,update_log.txt',
        'DELETE_EXTRACTED_FILES' => ($_POST['DELETE_EXTRACTED_FILES'] ?? 'true') === 'true' ? 'true' : 'false'
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
    
    $result = performUpdate();
    
    logInfo("=" . str_repeat("=", 60));
    if ($result['success']) {
        if ($result['upgraded']) {
            logSuccess("UPDATE SUCCESSFUL - Version: " . $result['new_version']);
        } else {
            logInfo("ALREADY UP TO DATE - Version: " . $result['new_version']);
        }
    } else {
        logError("UPDATE FAILED");
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
$commitInfo = @getLatestCommitInfo();
if ($commitInfo) {
    $latestCommit = $commitInfo;
    $updateAvailable = isUpdateNeeded($commitInfo['sha']);
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
                <div class="value" style="font-size: 14px;"><?php echo htmlspecialchars(substr($latestCommit['message'], 0, 30)) . '...'; ?></div>
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
        
        <!-- Update Banner -->
        <?php if ($updateAvailable): ?>
        <div class="update-banner">
            <div class="info">
                <h3>🚀 New Update Available!</h3>
                <p>Version <?php echo htmlspecialchars(substr($latestCommit['sha'], 0, 7)); ?> is ready to install</p>
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
                        
                        echo '<div class="log-line ' . $class . '">' . htmlspecialchars($line) . '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include modals and JavaScript from footer.php -->
    <?php include __DIR__ . '/assets/footer.php'; ?>
