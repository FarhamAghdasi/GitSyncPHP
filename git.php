<?php
/**
 * GitHub Auto-Update Script for Shared Hosting
 *
 * Main controller — includes modules from lib/ and renders the UI.
 */

define('BASE_DIR', dirname(__DIR__));
define('SCRIPT_DIR', __DIR__);

// Load modules (order matters — logger first, config uses logDebug)
require_once __DIR__ . '/lib/logger.php';
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/github.php';
require_once __DIR__ . '/lib/telegram.php';
require_once __DIR__ . '/lib/updater.php';

// ============================
// HANDLE AJAX ACTIONS
// ============================

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$key = $_GET['key'] ?? '';

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

if ($action === 'get_log_size' && securityCheck()) {
    $size = 0;
    if (file_exists(LOG_FILE)) {
        $size = round(filesize(LOG_FILE) / (1024 * 1024), 2);
    }
    echo json_encode(['size' => $size, 'path' => LOG_FILE]);
    exit;
}

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

if ($action === 'run_update' && securityCheck()) {
    @unlink(LOG_FILE);
    logInfo("GITHUB AUTO-UPDATE STARTED");

    $result = performUpdate();

    logInfo("UPDATE PROCESS FINISHED");
    echo json_encode($result);
    exit;
}

if ($action === 'delete_all_backups' && securityCheck()) {
    $backups = getBackups();
    $deleted = 0;
    $errors = [];

    foreach ($backups as $backup) {
        if (file_exists($backup['path'])) {
            if (unlink($backup['path'])) {
                $deleted++;
            } else {
                $errors[] = basename($backup['path']);
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

// ============================
// WEB INTERFACE
// ============================

if (!securityCheck() && php_sapi_name() !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    echo "Access Denied\n";
    echo "Use ?key=YOUR_SECRET_KEY or add your IP to .ip_whitelist\n";
    exit;
}

$currentVersion = file_exists(VERSION_FILE) ? substr(file_get_contents(VERSION_FILE), 0, 7) : 'N/A';

$latestCommit = null;
$updateAvailable = false;
$commitDetails = null;
$commitInfo = @getLatestCommitInfo();
if ($commitInfo) {
    $latestCommit = $commitInfo;
    $updateAvailable = isUpdateNeeded($commitInfo['sha']);
    $commitDetails = getCommitDetails($commitInfo['sha']);
}

$backups = getBackups();
$logContent = @file_get_contents(LOG_FILE) ?: '';

include __DIR__ . '/assets/header.php';
include __DIR__ . '/assets/components/header-bar.php';
include __DIR__ . '/assets/components/status-cards.php';
include __DIR__ . '/assets/components/commit-details.php';
include __DIR__ . '/assets/components/update-banner.php';
include __DIR__ . '/assets/components/operations-card.php';
include __DIR__ . '/assets/components/backups-card.php';
include __DIR__ . '/assets/components/log-card.php';
include __DIR__ . '/assets/footer.php';
