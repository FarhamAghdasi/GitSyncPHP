<?php
/**
 * Configuration Module
 * Loads .env configuration and defines constants.
 */

$configFile = SCRIPT_DIR . '/.env';
$config = loadConfig();

define('GITHUB_TOKEN', (string)($config['GITHUB_TOKEN'] ?? ''));
define('REPO_USER', (string)($config['REPO_USER'] ?? 'farhamaghdasi'));
define('REPO_NAME', (string)($config['REPO_NAME'] ?? 'arash-laravel-panel'));
define('BRANCH', (string)($config['BRANCH'] ?? 'main'));
define('TELEGRAM_BOT_TOKEN', (string)($config['TELEGRAM_BOT_TOKEN'] ?? ''));
define('TELEGRAM_CHAT_ID', (string)($config['TELEGRAM_CHAT_ID'] ?? ''));
define('BACKUP_BEFORE_UPDATE', ($config['BACKUP_BEFORE_UPDATE'] ?? 'true') === 'true');
define('LOG_FILE', SCRIPT_DIR . '/' . ($config['LOG_FILE'] ?? 'update_log.txt'));
define('VERSION_FILE', SCRIPT_DIR . '/' . ($config['VERSION_FILE'] ?? '.version'));
define('EXCLUDE_FILES', explode(',', (string)($config['EXCLUDE_FILES'] ?? 'git,.env,__backups,.git*,config*.php,database*,*.sql,*.log,update_log.txt')));
define('DELETE_EXTRACTED_FILES', ($config['DELETE_EXTRACTED_FILES'] ?? 'true') === 'true');
define('TARGET_DIR', (string)($config['TARGET_DIR'] ?? dirname(__DIR__)));

$backupDirRaw = (string)($config['BACKUP_DIR'] ?? '');
if (!empty($backupDirRaw) && ($backupDirRaw[0] === '/' || preg_match('/^[A-Z]:\\\\/i', $backupDirRaw))) {
    define('BACKUP_DIR', rtrim($backupDirRaw, '/\\'));
} else {
    define('BACKUP_DIR', TARGET_DIR . '/' . ($backupDirRaw ?: '__backups'));
}

define('USE_PROXY', ($config['USE_PROXY'] ?? 'false') === 'true');
define('PROXY_URL', (string)($config['PROXY_URL'] ?? ''));

$cachedCommitDetails = null;

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
        'TARGET_DIR' => dirname(__DIR__),
        'USE_PROXY' => 'false',
        'PROXY_URL' => ''
    ];

    if (!file_exists($configFile)) {
        logDebug("Config file not found: $configFile");
        return $defaultConfig;
    }

    logDebug("Loading config from: $configFile");

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

            $displayValue = $value;
            if (in_array($key, ['GITHUB_TOKEN', 'TELEGRAM_BOT_TOKEN'])) {
                $displayValue = strlen($value) > 10 ? substr($value, 0, 6) . '...' . substr($value, -4) : '***';
            }
            logDebug("   $key = " . (strlen($displayValue) > 50 ? substr($displayValue, 0, 50) . '...' : $displayValue));
        }
    }

    return $defaultConfig;
}

function saveConfig($newConfig) {
    $configFile = SCRIPT_DIR . '/.env';

    $content = "";
    foreach ($newConfig as $key => $value) {
        $content .= "$key=$value\n";
    }

    return file_put_contents($configFile, $content) !== false;
}
