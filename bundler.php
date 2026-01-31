<?php
/**
 * Bundler Script - Generates a single combined file
 * 
 * Usage: php bundler.php [output_file]
 * Default output: bundle.php
 */

$outputFile = $argv[1] ?? 'bundle.php';

// Asset paths
$cssPath = __DIR__ . '/assets/style.css';
$jsPath = __DIR__ . '/assets/script.js';
$headerPath = __DIR__ . '/assets/header.php';
$footerPath = __DIR__ . '/assets/footer.php';
$gitPhpPath = __DIR__ . '/git.php';

// Check if assets exist
if (!file_exists($cssPath)) {
    die("Error: style.css not found in assets/ folder\n");
}
if (!file_exists($jsPath)) {
    die("Error: script.js not found in assets/ folder\n");
}
if (!file_exists($headerPath)) {
    die("Error: header.php not found in assets/ folder\n");
}
if (!file_exists($footerPath)) {
    die("Error: footer.php not found in assets/ folder\n");
}

echo "Building bundle...\n";

// Read assets
$cssContent = file_get_contents($cssPath);
$jsContent = file_get_contents($jsPath);
$headerContent = file_get_contents($headerPath);
$footerContent = file_get_contents($footerPath);

// Read git.php to extract PHP functions
$gitPhpContent = file_get_contents($gitPhpPath);

// Extract PHP code (only functions and configuration, NOT the HTML output)
$phpCode = '';
$lines = explode("\n", $gitPhpContent);
$skipLine = false;
foreach ($lines as $line) {
    // Skip the opening <?php tag
    if (trim($line) === '<?php') {
        continue;
    }
    // Skip until we hit the web interface section
    if (strpos($line, '<!DOCTYPE html>') !== false || 
        strpos($line, 'include __DIR__') !== false ||
        strpos($line, '// Include header') !== false) {
        $skipLine = true;
        continue;
    }
    // Skip closing tags at the end of PHP blocks in the HTML section
    if (trim($line) === '?>') {
        continue;
    }
    if (!$skipLine) {
        $phpCode .= $line . "\n";
    }
}

// Build the bundle content
$parts = [];

// Part 1: PHP opening and code
$parts[] = "<?php\n";
$parts[] = $phpCode;
$parts[] = "";

// Part 2: Bundle mode PHP code
$parts[] = "// ============================";
$parts[] = "// BUNDLE MODE - All Assets Inlined";
$parts[] = "// ============================";
$parts[] = "";
$parts[] = "\$action = \$_POST['action'] ?? \$_GET['action'] ?? '';";
$parts[] = "\$key = \$_GET['key'] ?? '';";
$parts[] = "";
$parts[] = "// Security check";
$parts[] = "if (!securityCheck() && php_sapi_name() !== 'cli') {";
$parts[] = "    header('HTTP/1.0 403 Forbidden');";
$parts[] = "    echo \"Access Denied\\n\";";
$parts[] = "    echo \"Use ?key=YOUR_SECRET_KEY or add your IP to .ip_whitelist\\n\";";
$parts[] = "    exit;";
$parts[] = "}";
$parts[] = "";
$parts[] = "// Handle AJAX actions";
$parts[] = "if (\$action === 'save_settings' && securityCheck()) {";
$parts[] = "    \$newConfig = [";
$parts[] = "        'GITHUB_TOKEN' => \$_POST['GITHUB_TOKEN'] ?? '',";
$parts[] = "        'REPO_USER' => \$_POST['REPO_USER'] ?? 'farhamaghdasi',";
$parts[] = "        'REPO_NAME' => \$_POST['REPO_NAME'] ?? 'trust-wallet-balance-checker',";
$parts[] = "        'BRANCH' => \$_POST['BRANCH'] ?? 'main',";
$parts[] = "        'TELEGRAM_BOT_TOKEN' => \$_POST['TELEGRAM_BOT_TOKEN'] ?? '',";
$parts[] = "        'TELEGRAM_CHAT_ID' => \$_POST['TELEGRAM_CHAT_ID'] ?? '',";
$parts[] = "        'BACKUP_BEFORE_UPDATE' => (\$_POST['BACKUP_BEFORE_UPDATE'] ?? 'true') === 'true' ? 'true' : 'false',";
$parts[] = "        'BACKUP_DIR' => \$_POST['BACKUP_DIR'] ?? '__backups',";
$parts[] = "        'LOG_FILE' => \$_POST['LOG_FILE'] ?? 'update_log.txt',";
$parts[] = "        'VERSION_FILE' => \$_POST['VERSION_FILE'] ?? '.version',";
$parts[] = "        'EXCLUDE_FILES' => \$_POST['EXCLUDE_FILES'] ?? 'git,.env,__backups,.git*,config*.php,database*,*.sql,*.log,update_log.txt',";
$parts[] = "        'DELETE_EXTRACTED_FILES' => (\$_POST['DELETE_EXTRACTED_FILES'] ?? 'true') === 'true' ? 'true' : 'false'";
$parts[] = "    ];";
$parts[] = "    ";
$parts[] = "    if (saveConfig(\$newConfig)) {";
$parts[] = "        echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);";
$parts[] = "    } else {";
$parts[] = "        echo json_encode(['success' => false, 'message' => 'Failed to save settings']);";
$parts[] = "    }";
$parts[] = "    exit;";
$parts[] = "}";
$parts[] = "";
$parts[] = "if (\$action === 'download_backup' && securityCheck()) {";
$parts[] = "    \$backupFile = \$_GET['file'] ?? '';";
$parts[] = "    \$backupPath = BACKUP_DIR . '/' . basename(\$backupFile);";
$parts[] = "    ";
$parts[] = "    if (file_exists(\$backupPath)) {";
$parts[] = "        header('Content-Type: application/zip');";
$parts[] = "        header('Content-Disposition: attachment; filename=\"' . basename(\$backupPath) . '\"');";
$parts[] = "        header('Content-Length: ' . filesize(\$backupPath));";
$parts[] = "        readfile(\$backupPath);";
$parts[] = "        exit;";
$parts[] = "    }";
$parts[] = "}";
$parts[] = "";
$parts[] = "if (\$action === 'delete_backup' && securityCheck()) {";
$parts[] = "    \$backupFile = \$_POST['file'] ?? '';";
$parts[] = "    \$backupPath = BACKUP_DIR . '/' . basename(\$backupFile);";
$parts[] = "    ";
$parts[] = "    if (file_exists(\$backupPath)) {";
$parts[] = "        if (unlink(\$backupPath)) {";
$parts[] = "            echo json_encode(['success' => true, 'message' => 'Backup deleted successfully']);";
$parts[] = "        } else {";
$parts[] = "            echo json_encode(['success' => false, 'message' => 'Failed to delete backup']);";
$parts[] = "        }";
$parts[] = "    } else {";
$parts[] = "        echo json_encode(['success' => false, 'message' => 'Backup file not found']);";
$parts[] = "    }";
$parts[] = "    exit;";
$parts[] = "}";
$parts[] = "";
$parts[] = "if (\$action === 'clear_log' && securityCheck()) {";
$parts[] = "    if (file_exists(LOG_FILE)) {";
$parts[] = "        if (unlink(LOG_FILE)) {";
$parts[] = "            echo json_encode(['success' => true, 'message' => 'Log cleared successfully']);";
$parts[] = "        } else {";
$parts[] = "            echo json_encode(['success' => false, 'message' => 'Failed to clear log']);";
$parts[] = "        }";
$parts[] = "    } else {";
$parts[] = "        echo json_encode(['success' => true, 'message' => 'Log file does not exist']);";
$parts[] = "    }";
$parts[] = "    exit;";
$parts[] = "}";
$parts[] = "";
$parts[] = "if (\$action === 'get_log_size' && securityCheck()) {";
$parts[] = "    \$size = 0;";
$parts[] = "    if (file_exists(LOG_FILE)) {";
$parts[] = "        \$size = round(filesize(LOG_FILE) / (1024 * 1024), 2);";
$parts[] = "    }";
$parts[] = "    echo json_encode(['size' => \$size, 'path' => LOG_FILE]);";
$parts[] = "    exit;";
$parts[] = "}";
$parts[] = "";
$parts[] = "if (\$action === 'get_backup_size' && securityCheck()) {";
$parts[] = "    \$backupFile = \$_POST['file'] ?? '';";
$parts[] = "    \$backupPath = BACKUP_DIR . '/' . basename(\$backupFile);";
$parts[] = "    \$size = 0;";
$parts[] = "    if (file_exists(\$backupPath)) {";
$parts[] = "        \$size = round(filesize(\$backupPath) / (1024 * 1024), 2);";
$parts[] = "    }";
$parts[] = "    echo json_encode(['size' => \$size, 'path' => \$backupPath]);";
$parts[] = "    exit;";
$parts[] = "}";
$parts[] = "";
$parts[] = "if (\$action === 'run_update' && securityCheck()) {";
$parts[] = "    @unlink(LOG_FILE);";
$parts[] = "    logInfo(\"=\" . str_repeat(\"=\", 60));";
$parts[] = "    logInfo(\"GITHUB AUTO-UPDATE STARTED\");";
$parts[] = "    ";
$parts[] = "    \$result = performUpdate();";
$parts[] = "    ";
$parts[] = "    logInfo(\"=\" . str_repeat(\"=\", 60));";
$parts[] = "    if (\$result['success']) {";
$parts[] = "        if (\$result['upgraded']) {";
$parts[] = "            logSuccess(\"UPDATE SUCCESSFUL - Version: \" . \$result['new_version']);";
$parts[] = "        } else {";
$parts[] = "            logInfo(\"ALREADY UP TO DATE - Version: \" . \$result['new_version']);";
$parts[] = "        }";
$parts[] = "    } else {";
$parts[] = "        logError(\"UPDATE FAILED\");";
$parts[] = "    }";
$parts[] = "    logInfo(\"=\" . str_repeat(\"=\", 60));";
$parts[] = "    ";
$parts[] = "    echo json_encode(\$result);";
$parts[] = "    exit;";
$parts[] = "}";
$parts[] = "";
$parts[] = "if (\$action === 'delete_all_backups' && securityCheck()) {";
$parts[] = "    \$backups = getBackups();";
$parts[] = "    \$deleted = 0;";
$parts[] = "    \$errors = [];";
$parts[] = "    ";
$parts[] = "    foreach (\$backups as \$backup) {";
$parts[] = "        \$backupPath = \$backup['path'];";
$parts[] = "        if (file_exists(\$backupPath)) {";
$parts[] = "            if (unlink(\$backupPath)) {";
$parts[] = "                \$deleted++;";
$parts[] = "            } else {";
$parts[] = "                \$errors[] = basename(\$backupPath);";
$parts[] = "            }";
$parts[] = "        }";
$parts[] = "    }";
$parts[] = "    ";
$parts[] = "    if (empty(\$errors)) {";
$parts[] = "        echo json_encode(['success' => true, 'message' => \"\$deleted بک‌آپ با موفقیت حذف شد\"]);";
$parts[] = "    } else {";
$parts[] = "        echo json_encode(['success' => false, 'message' => 'خطا در حذف برخی بک‌آپ‌ها: ' . implode(', ', \$errors)]);";
$parts[] = "    }";
$parts[] = "    exit;";
$parts[] = "}";
$parts[] = "";
$parts[] = "// Get current version";
$parts[] = "\$currentVersion = file_exists(VERSION_FILE) ? substr(file_get_contents(VERSION_FILE), 0, 7) : 'N/A';";
$parts[] = "";
$parts[] = "// Get latest commit info";
$parts[] = "\$latestCommit = null;";
$parts[] = "\$updateAvailable = false;";
$parts[] = "\$commitInfo = @getLatestCommitInfo();";
$parts[] = "if (\$commitInfo) {";
$parts[] = "    \$latestCommit = \$commitInfo;";
$parts[] = "    \$updateAvailable = isUpdateNeeded(\$commitInfo['sha']);";
$parts[] = "}";
$parts[] = "";
$parts[] = "// Get backups";
$parts[] = "\$backups = getBackups();";
$parts[] = "";
$parts[] = "// Get log content";
$parts[] = "\$logContent = @file_get_contents(LOG_FILE) ?: '';";
$parts[] = "?>";
$parts[] = "";

// Clean up header content - remove duplicate HTML structure since we build our own
$headerClean = preg_replace('/<!DOCTYPE html>.*?<body>/s', '', $headerContent);
$headerClean = preg_replace('/<\/body>.*?<\/html>/s', '', $headerClean);
$headerClean = preg_replace('/<link rel="stylesheet" href="assets\/style\.css">/', '', $headerClean);

// Clean up footer content - remove script src since we inline it
$footerClean = preg_replace('/<script src="assets\/script\.js"><\/script>/', '', $footerContent);
$footerClean = preg_replace('/<!DOCTYPE html>.*?<body>/s', '', $footerClean);
$footerClean = preg_replace('/<\/body>.*?<\/html>/s', '', $footerClean);

// Part 3: HTML with inline CSS
$htmlStart = '<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitHub Auto-Update</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
' . $cssContent . '
    </style>
</head>
<body>
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <div class="container">
' . $headerClean . '

        <!-- Main Content -->
        <div class="container">
            <!-- Header -->
            <div class="header">
                <h1>🔄 آپدیت خودکار از GitHub</h1>
                <p class="repo-info">📦 <?php echo htmlspecialchars(REPO_USER . "/" . REPO_NAME); ?> | 🌿 <?php echo htmlspecialchars(BRANCH); ?></p>
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
                    <div class="value"><?php echo htmlspecialchars(substr($latestCommit["sha"], 0, 7)); ?></div>
                </div>
                <div class="status-card info">
                    <div class="icon">📝</div>
                    <div class="label">پیام کامیت</div>
                    <div class="value" style="font-size: 14px;"><?php echo htmlspecialchars(substr($latestCommit["message"], 0, 30)) . "..."; ?></div>
                </div>
                <div class="status-card warning">
                    <div class="icon">📅</div>
                    <div class="label">تاریخ</div>
                    <div class="value"><?php echo htmlspecialchars($latestCommit["date"]); ?></div>
                </div>
                <?php endif; ?>
                <div class="status-card <?php echo $updateAvailable ? "success" : "info"; ?>">
                    <div class="icon">🔄</div>
                    <div class="label">وضعیت</div>
                    <div class="value"><?php echo $updateAvailable ? "آپدیت موجود" : "به‌روز"; ?></div>
                </div>
            </div>
            
            <!-- Update Banner -->
            <?php if ($updateAvailable): ?>
            <div class="update-banner">
                <div class="info">
                    <h3>🚀 آپدیت جدید موجود است!</h3>
                    <p>نسخه <?php echo htmlspecialchars(substr($latestCommit["sha"], 0, 7)); ?> آماده نصب است</p>
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
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <span style="background: #667eea; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px;">
                            <?php echo count($backups); ?> عدد
                        </span>
                        <?php if (!empty($backups)): ?>
                        <button class="btn btn-warning btn-icon" onclick="deleteAllBackups()">
                            🗑️ حذف همه
                        </button>
                        <?php endif; ?>
                    </div>
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
                            $backupSizeMB = round(filesize($backup["path"]) / (1024 * 1024), 2);
                            $isLarge = $backupSizeMB > 200;
                        ?>
                        <li class="backup-item">
                            <div class="backup-info">
                                <div class="backup-icon">📦<?php if ($isLarge): ?><span class="warning-badge">⚠️</span><?php endif; ?></div>
                                <div class="backup-details">
                                    <div class="name"><?php echo htmlspecialchars($backup["name"]); ?><?php if ($isLarge): ?> <span style="color: #e74c3c; font-size: 12px;">(<?php echo $backupSizeMB; ?> MB)</span><?php endif; ?></div>
                                    <div class="meta"><?php echo $backup["size"]; ?> KB | <?php echo htmlspecialchars($backup["date"]); ?></div>
                                </div>
                            </div>
                            <div class="backup-actions">
                                <a href="?action=download_backup&key=<?php echo htmlspecialchars($key); ?>&file=<?php echo urlencode($backup["name"]); ?>" class="btn btn-primary btn-icon">
                                    ⬇️ دانلود
                                </a>
                                <button class="btn btn-danger btn-icon" onclick="deleteBackup(\'<?php echo htmlspecialchars($backup["name"]); ?>\')">
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
                            
                            $class = "info";
                            if (strpos($line, "[ERROR]") !== false) $class = "error";
                            elseif (strpos($line, "[WARNING]") !== false) $class = "warning";
                            elseif (strpos($line, "[SUCCESS]") !== false) $class = "success";
                            elseif (strpos($line, "[DEBUG]") !== false) $class = "debug";
                            
                            echo "<div class=\"log-line " . $class . "\">" . htmlspecialchars($line) . "</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

' . $footerClean . '

        <script>
' . $jsContent . '
        </script>
    </body>
</html>';

$parts[] = $htmlStart;

// Combine all parts
$bundle = implode("\n", $parts);

// Write bundle
if (file_put_contents($outputFile, $bundle) !== false) {
    $size = round(strlen($bundle) / 1024, 2);
    echo "✅ Bundle created: $outputFile ($size KB)\n";
} else {
    die("Error: Failed to write bundle file\n");
}
