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
define('BASE_DIR', dirname(__DIR__));
define('SCRIPT_DIR', __DIR__);

// Load configuration from SCRIPT_DIR/.env
$config = loadConfig();

// Define constants from config
define('GITHUB_TOKEN', (string)($config['GITHUB_TOKEN'] ?? ''));
define('REPO_USER', (string)($config['REPO_USER'] ?? 'farhamaghdasi'));
define('REPO_NAME', (string)($config['REPO_NAME'] ?? 'trust-wallet-balance-checker'));
define('BRANCH', (string)($config['BRANCH'] ?? 'main'));
define('TELEGRAM_BOT_TOKEN', (string)($config['TELEGRAM_BOT_TOKEN'] ?? ''));
define('TELEGRAM_CHAT_ID', (string)($config['TELEGRAM_CHAT_ID'] ?? ''));
define('BACKUP_BEFORE_UPDATE', (bool)($config['BACKUP_BEFORE_UPDATE'] ?? true));
define('BACKUP_DIR', BASE_DIR . '/' . ($config['BACKUP_DIR'] ?? '__backups'));
define('LOG_FILE', SCRIPT_DIR . '/' . ($config['LOG_FILE'] ?? 'update_log.txt'));
define('VERSION_FILE', SCRIPT_DIR . '/' . ($config['VERSION_FILE'] ?? '.version'));
define('EXCLUDE_FILES', explode(',', (string)($config['EXCLUDE_FILES'] ?? 'git,.env,__backups,.git*,config*.php,database*,*.sql,*.log,update_log.txt')));
define('DELETE_EXTRACTED_FILES', (bool)($config['DELETE_EXTRACTED_FILES'] ?? true));
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

function logInfo($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [INFO] $message\n";
    echo $logEntry;
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logError($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [ERROR] ❌ $message\n";
    echo $logEntry;
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logSuccess($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [SUCCESS] ✅ $message\n";
    echo $logEntry;
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logWarning($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [WARNING] ⚠️ $message\n";
    echo $logEntry;
    $logFile = defined('LOG_FILE') ? LOG_FILE : SCRIPT_DIR . '/update_log.txt';
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logDebug($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [DEBUG] 🔍 $message\n";
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

// Get log content
$logContent = @file_get_contents(LOG_FILE) ?: '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔄 GitHub Auto-Update</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header .repo-info {
            color: #666;
            font-size: 14px;
        }
        
        .status-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .status-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .status-card:hover {
            transform: translateY(-5px);
        }
        
        .status-card .icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .status-card .label {
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .status-card .value {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .status-card.success .value { color: #27ae60; }
        .status-card.warning .value { color: #f39c12; }
        .status-card.info .value { color: #3498db; }
        
        .update-banner {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }
        
        .update-banner .info h3 {
            font-size: 20px;
            margin-bottom: 5px;
        }
        
        .update-banner .info p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .btn {
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
        }
        
        .btn-success:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(39, 174, 96, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }
        
        .btn-danger:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(231, 76, 60, 0.4);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .card-header h2 {
            color: #2c3e50;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .log-container {
            background: #1e1e1e;
            border-radius: 12px;
            padding: 20px;
            max-height: 500px;
            overflow-y: auto;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 13px;
            direction: ltr;
            text-align: left;
        }
        
        .log-line {
            padding: 4px 0;
            border-bottom: 1px solid #333;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .log-line.success { color: #4CAF50; }
        .log-line.error { color: #f44336; }
        .log-line.warning { color: #ff9800; }
        .log-line.info { color: #2196F3; }
        .log-line.debug { color: #9C27B0; }
        
        .backup-list {
            list-style: none;
        }
        
        .backup-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s ease;
        }
        
        .backup-item:hover {
            background: #f8f9fa;
        }
        
        .backup-item:last-child {
            border-bottom: none;
        }
        
        .backup-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .backup-icon {
            font-size: 24px;
        }
        
        .backup-details .name {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .backup-details .meta {
            font-size: 12px;
            color: #666;
        }
        
        .backup-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-icon {
            padding: 8px 12px;
            font-size: 12px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            color: #2c3e50;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .modal-footer {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #fff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin: 15px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-state .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .warning-badge {
            background: #e74c3c;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            margin-right: 5px;
        }
        
        @media (max-width: 768px) {
            .update-banner {
                flex-direction: column;
                text-align: center;
            }
            
            .header h1 {
                font-size: 22px;
            }
            
            .card-header {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🔄 آپدیت خودکار از GitHub</h1>
            <p class="repo-info">📦 <?php echo htmlspecialchars(REPO_USER . '/' . REPO_NAME); ?> | 🌿 <?php echo htmlspecialchars(BRANCH); ?></p>
        </div>
        
        <!-- Status Cards -->
        <div class="status-cards">
            <div class="status-card info">
                <div class="icon">📌</div>
                <div class="label">نسخه فعلی</div>
                <div class="value"><?php echo htmlspecialchars($currentVersion); ?></div>
            </div>
            <?php if ($latestCommit): ?>
            <div class="status-card success">
                <div class="icon">🔖</div>
                <div class="label">آخرین کامیت</div>
                <div class="value"><?php echo htmlspecialchars(substr($latestCommit['sha'], 0, 7)); ?></div>
            </div>
            <div class="status-card info">
                <div class="icon">📝</div>
                <div class="label">پیام کامیت</div>
                <div class="value" style="font-size: 14px;"><?php echo htmlspecialchars(substr($latestCommit['message'], 0, 30)) . '...'; ?></div>
            </div>
            <div class="status-card warning">
                <div class="icon">📅</div>
                <div class="label">تاریخ</div>
                <div class="value"><?php echo htmlspecialchars($latestCommit['date']); ?></div>
            </div>
            <?php endif; ?>
            <div class="status-card <?php echo $updateAvailable ? 'success' : 'info'; ?>">
                <div class="icon">🔄</div>
                <div class="label">وضعیت</div>
                <div class="value"><?php echo $updateAvailable ? 'آپدیت موجود' : 'به‌روز'; ?></div>
            </div>
        </div>
        
        <!-- Update Banner -->
        <?php if ($updateAvailable): ?>
        <div class="update-banner">
            <div class="info">
                <h3>🚀 آپدیت جدید موجود است!</h3>
                <p>نسخه <?php echo htmlspecialchars(substr($latestCommit['sha'], 0, 7)); ?> آماده نصب است</p>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn btn-success" onclick="startUpdate()">
                    ▶️ شروع آپدیت
                </button>
                <button class="btn btn-secondary" onclick="showSettingsModal()">
                    ⚙️ تنظیمات
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="update-banner" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
            <div class="info">
                <h3>✅ سیستم به‌روز است</h3>
                <p>شما از آخرین نسخه استفاده می‌کنید</p>
            </div>
            <button class="btn btn-primary" onclick="forceUpdate()">
                🔄 بررسی مجدد
            </button>
        </div>
        <?php endif; ?>
        
        <!-- Main Actions Card -->
        <div class="card">
            <div class="card-header">
                <h2>📋 عملیات</h2>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-primary" onclick="showSettingsModal()">
                        ⚙️ تنظیمات
                    </button>
                    <button class="btn btn-secondary" onclick="refreshStatus()">
                        🔄 بروزرسانی وضعیت
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="updateProgress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <p id="progressText" style="text-align: center; color: #666;">در حال آپدیت...</p>
                </div>
            </div>
        </div>
        
        <!-- Backups Card -->
        <div class="card">
            <div class="card-header">
                <h2>💾 بک‌آپ‌ها</h2>
                <span style="background: #667eea; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px;">
                    <?php echo count($backups); ?> عدد
                </span>
            </div>
            <div class="card-body">
                <?php if (empty($backups)): ?>
                <div class="empty-state">
                    <div class="icon">📦</div>
                    <p>هیچ بک‌آپی موجود نیست</p>
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
                                ⬇️ دانلود
                            </a>
                            <button class="btn btn-danger btn-icon" onclick="deleteBackup('<?php echo htmlspecialchars($backup['name']); ?>')">
                                🗑️ حذف
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
                <h2>📜 لاگ عملیات<?php if ($logIsLarge): ?><span class="warning-badge">⚠️ <?php echo $logSizeMB; ?> MB</span><?php endif; ?></h2>
                <button class="btn btn-secondary btn-icon" onclick="clearLog()">
                    🧹 پاک کردن
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
    
    <!-- Settings Modal -->
    <div class="modal" id="settingsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>⚙️ تنظیمات</h3>
                <button class="modal-close" onclick="closeSettingsModal()">&times;</button>
            </div>
            <form id="settingsForm">
                <div class="settings-grid">
                    <div class="form-group">
                        <label>GITHUB_TOKEN</label>
                        <input type="text" name="GITHUB_TOKEN" value="<?php echo htmlspecialchars(GITHUB_TOKEN); ?>" placeholder="ghp_...">
                    </div>
                    <div class="form-group">
                        <label>REPO_USER</label>
                        <input type="text" name="REPO_USER" value="<?php echo htmlspecialchars(REPO_USER); ?>">
                    </div>
                    <div class="form-group">
                        <label>REPO_NAME</label>
                        <input type="text" name="REPO_NAME" value="<?php echo htmlspecialchars(REPO_NAME); ?>">
                    </div>
                    <div class="form-group">
                        <label>BRANCH</label>
                        <input type="text" name="BRANCH" value="<?php echo htmlspecialchars(BRANCH); ?>">
                    </div>
                    <div class="form-group">
                        <label>TELEGRAM_BOT_TOKEN</label>
                        <input type="text" name="TELEGRAM_BOT_TOKEN" value="<?php echo htmlspecialchars(TELEGRAM_BOT_TOKEN); ?>" placeholder="123456:ABC...">
                    </div>
                    <div class="form-group">
                        <label>TELEGRAM_CHAT_ID</label>
                        <input type="text" name="TELEGRAM_CHAT_ID" value="<?php echo htmlspecialchars(TELEGRAM_CHAT_ID); ?>">
                    </div>
                    <div class="form-group">
                        <label>BACKUP_DIR</label>
                        <input type="text" name="BACKUP_DIR" value="<?php echo htmlspecialchars(BACKUP_DIR); ?>">
                    </div>
                    <div class="form-group">
                        <label>EXCLUDE_FILES</label>
                        <input type="text" name="EXCLUDE_FILES" value="<?php echo htmlspecialchars(implode(',', EXCLUDE_FILES)); ?>">
                    </div>
                    <div class="form-group">
                        <label class="checkbox-group">
                            <input type="checkbox" name="BACKUP_BEFORE_UPDATE" <?php echo BACKUP_BEFORE_UPDATE ? 'checked' : ''; ?>>
                            <span>BACKUP_BEFORE_UPDATE</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-group">
                            <input type="checkbox" name="DELETE_EXTRACTED_FILES" <?php echo DELETE_EXTRACTED_FILES ? 'checked' : ''; ?>>
                            <span>DELETE_EXTRACTED_FILES</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeSettingsModal()">لغو</button>
                    <button type="submit" class="btn btn-primary">💾 ذخیره</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div class="modal" id="confirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>⚠️ تأیید آپدیت</h3>
                <button class="modal-close" onclick="closeConfirmModal()">&times;</button>
            </div>
            <p style="margin-bottom: 20px; color: #666;">
                آیا از انجام آپدیت اطمینان دارید؟<br><br>
                <strong>توجه:</strong> قبل از آپدیت یک بک‌آپ خودکار ایجاد می‌شود.
            </p>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeConfirmModal()">لغو</button>
                <button class="btn btn-success" onclick="confirmUpdate()">✅ بله، آپدیت کن</button>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-scroll log to bottom
        function scrollLogToBottom() {
            const log = document.getElementById('logContainer');
            if (log) {
                log.scrollTop = log.scrollHeight;
            }
        }
        
        scrollLogToBottom();
        
        // Modal functions
        function showSettingsModal() {
            document.getElementById('settingsModal').classList.add('active');
        }
        
        function closeSettingsModal() {
            document.getElementById('settingsModal').classList.remove('active');
        }
        
        function showConfirmModal() {
            document.getElementById('confirmModal').classList.add('active');
        }
        
        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('active');
        }
        
        // Settings form submission
        document.getElementById('settingsForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'save_settings');
            formData.append('key', '<?php echo htmlspecialchars($key); ?>');
            
            // Handle checkboxes
            formData.set('BACKUP_BEFORE_UPDATE', document.querySelector('input[name="BACKUP_BEFORE_UPDATE"]').checked ? 'true' : 'false');
            formData.set('DELETE_EXTRACTED_FILES', document.querySelector('input[name="DELETE_EXTRACTED_FILES"]').checked ? 'true' : 'false');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    closeSettingsModal();
                    location.reload();
                } else {
                    alert('خطا در ذخیره تنظیمات: ' + result.message);
                }
            } catch (error) {
                alert('خطا در ارتباط با سرور');
            }
        });
        
        // Update functions
        function startUpdate() {
            showConfirmModal();
        }
        
        function confirmUpdate() {
            closeConfirmModal();
            runUpdate();
        }
        
        function forceUpdate() {
            runUpdate();
        }
        
        async function runUpdate() {
            const progressDiv = document.getElementById('updateProgress');
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');
            
            progressDiv.style.display = 'block';
            progressFill.style.width = '30%';
            progressText.textContent = 'در حال اتصال به GitHub...';
            
            try {
                const formData = new FormData();
                formData.append('action', 'run_update');
                formData.append('key', '<?php echo htmlspecialchars($key); ?>');
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                progressFill.style.width = '70%';
                progressText.textContent = 'در حال دانلود و استخراج فایل‌ها...';
                
                const result = await response.json();
                
                progressFill.style.width = '100%';
                
                if (result.success) {
                    if (result.upgraded) {
                        progressText.textContent = '✅ آپدیت با موفقیت انجام شد!';
                    } else {
                        progressText.textContent = 'ℹ️ سیستم از قبل به‌روز بود';
                    }
                    
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    progressText.textContent = '❌ آپدیت ناموفق بود';
                    setTimeout(() => {
                        progressDiv.style.display = 'none';
                    }, 3000);
                }
                
                // Refresh log
                location.reload();
            } catch (error) {
                progressText.textContent = '❌ خطا در ارتباط با سرور';
                setTimeout(() => {
                    progressDiv.style.display = 'none';
                }, 3000);
            }
        }
        
        // Clear log
        async function clearLog() {
            try {
                // Get log size first
                const sizeResponse = await fetch('?action=get_log_size&key=<?php echo htmlspecialchars($key); ?>');
                const sizeData = await sizeResponse.json();
                
                let confirmMessage = 'آیا میخواهید لاگ را پاک کنید؟';
                if (sizeData.size > 200) {
                    confirmMessage = '⚠️ هشدار! حجم فایل لاگ ' + sizeData.size + ' مگابایت است.\n\nآیا از پاک کردن آن اطمینان دارید؟';
                }
                
                if (!confirm(confirmMessage)) {
                    return;
                }
                
                const response = await fetch('?action=clear_log&key=<?php echo htmlspecialchars($key); ?>');
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert('خطا در پاک کردن لاگ: ' + result.message);
                }
            } catch (error) {
                alert('خطا در ارتباط با سرور');
            }
        }
        
        // Delete backup
        async function deleteBackup(filename) {
            try {
                // Get backup size first
                const formData = new FormData();
                formData.append('action', 'get_backup_size');
                formData.append('key', '<?php echo htmlspecialchars($key); ?>');
                formData.append('file', filename);
                
                const sizeResponse = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const sizeData = await sizeResponse.json();
                
                let confirmMessage = 'آیا از حذف این بکآپ مطمئن هستید؟';
                if (sizeData.size > 200) {
                    confirmMessage = '⚠️ هشدار! حجم این بکآپ ' + sizeData.size + ' مگابایت است.\n\nآیا از حذف آن اطمینان دارید؟';
                }
                
                if (!confirm(confirmMessage)) {
                    return;
                }
                
                const delFormData = new FormData();
                delFormData.append('action', 'delete_backup');
                delFormData.append('key', '<?php echo htmlspecialchars($key); ?>');
                delFormData.append('file', filename);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: delFormData
                });
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert('خطا در حذف بکآپ: ' + result.message);
                }
            } catch (error) {
                alert('خطا در ارتباط با سرور');
            }
        }
        
        // Refresh status
        function refreshStatus() {
            location.reload();
        }
        
        // Close modals on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
