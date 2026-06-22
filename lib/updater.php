<?php
/**
 * Updater Module
 * Handles update execution, file extraction, and backups.
 */

function performUpdate() {
    $startTime = microtime(true);

    logInfo("BASE_DIR: " . BASE_DIR);
    logInfo("SCRIPT_DIR: " . SCRIPT_DIR);
    logInfo("TARGET_DIR: " . TARGET_DIR);
    logInfo("BACKUP_DIR: " . BACKUP_DIR);
    logInfo("LOG_FILE: " . LOG_FILE);
    logInfo("USE_PROXY: " . (USE_PROXY ? 'Yes' : 'No'));
    if (USE_PROXY) {
        logInfo("PROXY_URL: " . PROXY_URL);
    }

    if (!is_dir(TARGET_DIR)) {
        logError("Target directory does not exist: " . TARGET_DIR);
        return ['success' => false, 'upgraded' => false, 'error' => 'Target directory not found'];
    }

    if (empty(REPO_USER) || empty(REPO_NAME)) {
        logError("Repository configuration missing");
        return ['success' => false, 'upgraded' => false, 'error' => 'Repository configuration missing'];
    }

    logInfo("Starting update process");
    logInfo("Repository: " . REPO_USER . "/" . REPO_NAME);
    logInfo("Branch: " . BRANCH);
    logInfo("Target: " . TARGET_DIR);

    if (!class_exists('ZipArchive')) {
        logError("ZipArchive class not available");
        return ['success' => false, 'upgraded' => false, 'error' => 'ZipArchive not available'];
    }

    logSuccess("ZipArchive available");

    logInfo("Fetching latest commit...");
    $latestCommit = getLatestCommitInfo();

    if (!$latestCommit) {
        logError("Could not fetch commit info");
        testGitHubConnection();
        return ['success' => false, 'upgraded' => false, 'error' => 'Could not fetch commit info'];
    }

    logSuccess("Got commit info: " . substr($latestCommit['sha'], 0, 7));
    logInfo("Message: " . $latestCommit['message']);
    logInfo("Author: " . $latestCommit['author']);
    logInfo("Date: " . $latestCommit['date']);

    logInfo("Fetching commit details...");
    $commitDetails = getCommitDetails($latestCommit['sha']);
    if ($commitDetails) {
        logInfo("Files changed: " . $commitDetails['stats']['files_changed']);
        logInfo("Additions: +" . $commitDetails['stats']['total_additions'] . " / Deletions: -" . $commitDetails['stats']['total_deletions']);

        foreach ($commitDetails['files'] as $file) {
            logDebug("   " . $file['status'] . ": " . $file['filename'] . " (+" . $file['additions'] . "/-" . $file['deletions'] . ")");
        }
    }

    logInfo("Checking if update needed...");
    $updateNeeded = isUpdateNeeded($latestCommit['sha']);

    if (!$updateNeeded) {
        $current = file_exists(VERSION_FILE) ? substr(file_get_contents(VERSION_FILE), 0, 7) : 'N/A';
        logSuccess("Already up to date: " . $current);
        return [
            'success' => true,
            'upgraded' => false,
            'new_version' => substr($latestCommit['sha'], 0, 7),
            'commit_details' => $commitDetails
        ];
    }

    $current = file_exists(VERSION_FILE) ? substr(file_get_contents(VERSION_FILE), 0, 7) : 'First install';
    logInfo("Update needed! Current: $current, New: " . substr($latestCommit['sha'], 0, 7));

    if (BACKUP_BEFORE_UPDATE) {
        logInfo("Creating backup...");
        logInfo("Backup destination: " . BACKUP_DIR);
        $backupResult = createBackup();
        if ($backupResult) {
            logSuccess("Backup created: " . $backupResult);
        } else {
            logWarning("Backup creation failed");
        }
    }

    logInfo("Downloading repository...");
    $zipFile = downloadRepositoryZip($latestCommit['sha']);

    if (!$zipFile) {
        logError("Download failed");
        return ['success' => false, 'upgraded' => false, 'error' => 'Download failed'];
    }

    $size = round(filesize($zipFile) / 1024);
    logSuccess("Downloaded: " . $size . " KB");

    logInfo("Extracting files to: " . TARGET_DIR);
    $extractResult = extractAndReplace($zipFile);

    if (file_exists($zipFile)) {
        unlink($zipFile);
        logInfo("Cleaned temp file");
    }

    if (!$extractResult['success']) {
        logError("Extraction failed: " . $extractResult['error']);
        return ['success' => false, 'upgraded' => false, 'error' => $extractResult['error']];
    }

    logSuccess("Files updated successfully!");
    logInfo("Stats - Updated: " . $extractResult['files_updated'] . ", Skipped: " . $extractResult['files_skipped'] . ", Failed: " . $extractResult['files_failed']);

    if (file_put_contents(VERSION_FILE, $latestCommit['sha']) !== false) {
        logSuccess("Version file updated");
    } else {
        logWarning("Could not update version file");
    }

    if (!empty(TELEGRAM_BOT_TOKEN) && !empty(TELEGRAM_CHAT_ID)) {
        logInfo("Sending Telegram...");
        $telegramSent = sendTelegramNotification($latestCommit, $extractResult, $commitDetails);
        if ($telegramSent) {
            logSuccess("Telegram sent");
        } else {
            logWarning("Telegram failed");
        }
    }

    $executionTime = round(microtime(true) - $startTime, 2);
    logSuccess("Completed in {$executionTime}s");

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
