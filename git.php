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
    // Use fallback if LOG_FILE not defined yet
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
    
    // Log paths
    logInfo("📁 BASE_DIR: " . BASE_DIR);
    logInfo("📁 SCRIPT_DIR: " . SCRIPT_DIR);
    logInfo("🎯 TARGET_DIR: " . TARGET_DIR);
    logInfo("📄 LOG_FILE: " . LOG_FILE);
    
    // Validate
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
    
    // Check ZipArchive
    if (!class_exists('ZipArchive')) {
        logError("ZipArchive class not available");
        return ['success' => false, 'upgraded' => false];
    }
    
    logSuccess("✓ ZipArchive available");
    
    // Get latest commit
    logInfo("📡 Fetching latest commit...");
    $latestCommit = getLatestCommitInfo();
    
    if (!$latestCommit) {
        logError("Could not fetch commit info");
        // Test GitHub connectivity
        testGitHubConnection();
        return ['success' => false, 'upgraded' => false];
    }
    
    logSuccess("✓ Got commit info: " . substr($latestCommit['sha'], 0, 7));
    logInfo("📝 Message: " . $latestCommit['message']);
    logInfo("👤 Author: " . $latestCommit['author']);
    logInfo("📅 Date: " . $latestCommit['date']);
    
    // Check if update needed
    logInfo("🔍 Checking if update needed...");
    $updateNeeded = isUpdateNeeded($latestCommit['sha']);
    
    if (!$updateNeeded) {
        $current = file_exists(VERSION_FILE) ? substr(file_get_contents(VERSION_FILE), 0, 7) : 'N/A';
        logSuccess("✓ Already up to date: " . $current);
        return ['success' => true, 'upgraded' => false, 'new_version' => substr($latestCommit['sha'], 0, 7)];
    }
    
    $current = file_exists(VERSION_FILE) ? substr(file_get_contents(VERSION_FILE), 0, 7) : 'First install';
    logInfo("⬆️ Update needed! Current: $current, New: " . substr($latestCommit['sha'], 0, 7));
    
    // Create backup
    if (BACKUP_BEFORE_UPDATE) {
        logInfo("💾 Creating backup...");
        $backupResult = createBackup();
        if ($backupResult) {
            logSuccess("✓ Backup created: " . $backupResult);
        } else {
            logWarning("Backup creation failed");
        }
    }
    
    // Download ZIP
    logInfo("⬇️ Downloading repository...");
    $zipFile = downloadRepositoryZip($latestCommit['sha']);
    
    if (!$zipFile) {
        logError("Download failed");
        return ['success' => false, 'upgraded' => false];
    }
    
    $size = round(filesize($zipFile) / 1024);
    logSuccess("✓ Downloaded: " . $size . " KB");
    
    // Extract and update
    logInfo("📂 Extracting files...");
    $extractResult = extractAndReplace($zipFile);
    
    // Cleanup
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
    
    // Update version file
    if (file_put_contents(VERSION_FILE, $latestCommit['sha']) !== false) {
        logSuccess("✓ Version file updated");
    } else {
        logWarning("Could not update version file");
    }
    
    // Telegram notification
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
    
    // Test 1: Basic connectivity
    $testUrl = "https://api.github.com";
    logDebug("   Testing: $testUrl");
    $response = fetchUrl($testUrl, ['User-Agent: PHP-Update-Script']);
    logResponse($response['status_code'], $response['body']);
    
    // Test 2: Repository access
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
        
        // Parse error message
        if ($response['body']) {
            $error = json_decode($response['body'], true);
            if (isset($error['message'])) {
                logError("Error message: " . $error['message']);
            }
            
            // Check for rate limit info
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
    
    // Extract to temp dir
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
    
    // Find extracted folder
    $folders = glob($tempDir . '/*', GLOB_ONLYDIR);
    if (empty($folders)) {
        $result['error'] = 'No folders found after extraction';
        logError("No extracted folders found");
        deleteDirectory($tempDir);
        return $result;
    }
    
    $sourceDir = $folders[0];
    logDebug("Source directory: " . basename($sourceDir));
    
    // Get all files
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
        
        // Check if excluded
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
        
        // Copy to target
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
    
    // Cleanup temp
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
        
        // Add .htaccess protection
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
    
    // Backup only from TARGET_DIR (wallet directory)
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
        
        // Skip backup directory itself
        if (strpos($relativePath, basename(BACKUP_DIR) . '/') === 0) {
            continue;
        }
        
        // Skip excluded files
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
    
    $message = "🚀 *Update Successful!*\n\n";
    $message .= "📦 *Repo:* " . REPO_USER . "/" . REPO_NAME . "\n";
    $message .= "🔖 *Commit:* `" . substr($commitInfo['sha'], 0, 7) . "`\n";
    $message .= "📝 *Message:* " . $commitInfo['message'] . "\n";
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
    
    // Try cURL first
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
        curl_setopt($ch, CURLOPT_HEADER, 1); // Get headers too
        
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
            // Split headers and body
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headerStr = substr($response, 0, $headerSize);
            $result['body'] = substr($response, $headerSize);
            $result['status_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Parse headers
            $headersArray = explode("\r\n", $headerStr);
            foreach ($headersArray as $header) {
                if (strpos($header, ':') !== false) {
                    list($key, $value) = explode(':', $header, 2);
                    $result['headers'][trim($key)] = trim($value);
                }
            }
        }
        
        curl_close($ch);
    }
    // Fallback to file_get_contents
    elseif (ini_get('allow_url_fopen')) {
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
            
            // Parse headers
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
    // Allow CLI
    if (php_sapi_name() === 'cli') {
        return true;
    }
    
    // Check secret key
    if (isset($_GET['key'])) {
        $secretFile = SCRIPT_DIR . '/.update_key';
        if (file_exists($secretFile)) {
            $expectedKey = trim(file_get_contents($secretFile));
            if (hash_equals($expectedKey, $_GET['key'])) {
                return true;
            }
        }
    }
    
    // Check IP whitelist
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
// EXECUTION
// ============================

// Security check
if (!securityCheck() && php_sapi_name() !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    echo "Access Denied\n";
    echo "Use ?key=YOUR_SECRET_KEY or add your IP to .ip_whitelist\n";
    exit;
}

// Start log
logInfo("=" . str_repeat("=", 60));
logInfo("GITHUB AUTO-UPDATE STARTED");

// Run update
$result = performUpdate();

// Final log
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

// Web output
if (php_sapi_name() !== 'cli') {
    $logContent = @file_get_contents(LOG_FILE) ?: 'No log found';
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>آپدیت خودکار از GitHub</title>
        <style>
            body {
                font-family: Tahoma, sans-serif;
                background: #f5f5f5;
                margin: 0;
                padding: 20px;
            }
            
            .container {
                max-width: 1200px;
                margin: 0 auto;
                background: white;
                border-radius: 10px;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            
            .header {
                background: #2c3e50;
                color: white;
                padding: 20px;
                text-align: center;
            }
            
            .log {
                padding: 20px;
                background: #1e1e1e;
                color: #f8f8f2;
                font-family: 'Courier New', monospace;
                font-size: 12px;
                max-height: 600px;
                overflow-y: auto;
                white-space: pre-wrap;
                direction: ltr;
                text-align: left;
                line-height: 1.4;
            }
            
            .log-line {
                padding: 2px 0;
                border-bottom: 1px solid #333;
            }
            
            .success { color: #4CAF50; }
            .error { color: #f44336; }
            .warning { color: #ff9800; }
            .info { color: #2196F3; }
            .debug { color: #9C27B0; }
            
            .status {
                padding: 30px;
                text-align: center;
                font-size: 18px;
                font-weight: bold;
            }
            
            .status.success { background: #d4edda; color: #155724; }
            .status.error { background: #f8d7da; color: #721c24; }
            .status.info { background: #d1ecf1; color: #0c5460; }
            
            .footer {
                text-align: center;
                padding: 15px;
                color: #666;
                font-size: 12px;
                border-top: 1px solid #eee;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔄 آپدیت خودکار از GitHub</h1>
                <p><?php echo htmlspecialchars(REPO_USER . '/' . REPO_NAME . ' (' . BRANCH . ')'); ?></p>
            </div>
            
            <div class="log">
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
            
            <div class="status <?php echo $result['success'] ? ($result['upgraded'] ? 'success' : 'info') : 'error'; ?>">
                <?php if ($result['success']): ?>
                    <?php if ($result['upgraded']): ?>
                        ✅ آپدیت موفقیت‌آمیز بود!<br>
                        <small>نسخه جدید: <?php echo htmlspecialchars($result['new_version']); ?></small>
                    <?php else: ?>
                        ℹ️ قبلاً آپدیت هستید<br>
                        <small>نسخه: <?php echo htmlspecialchars($result['new_version']); ?></small>
                    <?php endif; ?>
                <?php else: ?>
                    ❌ آپدیت ناموفق بود<br>
                    <small>لاگ بالا را بررسی کنید</small>
                <?php endif; ?>
            </div>
            
            <div class="footer">
                اجرا شده در <?php echo date('Y-m-d H:i:s'); ?> | 
                <a href="?key=<?php echo htmlspecialchars($_GET['key'] ?? ''); ?>&force=1" style="color: #3498db;">🔄 اجرای مجدد</a>
            </div>
        </div>
        
        <script>
            // Auto-scroll to bottom
            document.addEventListener('DOMContentLoaded', function() {
                const log = document.querySelector('.log');
                if (log) {
                    log.scrollTop = log.scrollHeight;
                }
            });
        </script>
    </body>
    </html>
    <?php
}