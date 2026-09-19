<?php require_once __DIR__ . '/icons.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitHub Auto-Update — GitSyncPHP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="toast-container" id="toastContainer"></div>

    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <a class="nav-item active" href="git.php<?php echo $key ? '?key=' . htmlspecialchars($key) : ''; ?>">
                    <?php echo icon('github', 17); ?> GitHub Auto-Update
                </a>
                <a class="nav-item" href="#backups-section">
                    <?php echo icon('database', 17); ?> Backups
                </a>
                <a class="nav-item" href="#log-section">
                    <?php echo icon('terminal', 17); ?> Logs
                </a>
                <button type="button" class="nav-item" onclick="showSettingsModal()">
                    <?php echo icon('settings', 17); ?> Settings
                </button>
            </nav>
        </aside>

        <div class="main-wrap">
            <header class="topbar">
                <div class="topbar-left">
                    <?php echo icon('github', 19); ?>
                    <span class="topbar-title">GitHub Auto-Update</span>
                </div>
                <div class="topbar-right">
                    <div class="status-pill">
                        <span class="status-dot"></span>
                        System Online
                    </div>
                </div>
            </header>

            <main class="main-content">
