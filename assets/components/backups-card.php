<!-- Backups Card -->
<div class="card glass-card">
    <div class="card-header">
        <h2>Backups</h2>
        <div style="display: flex; gap: 10px; align-items: center;">
            <span class="badge"><?php echo count($backups); ?> items</span>
            <?php if (!empty($backups)): ?>
            <button class="btn btn-danger btn-icon" onclick="deleteAllBackups()">Delete All</button>
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
                    <div class="backup-icon">📦<?php if ($isLarge): ?><span class="warning-badge">!</span><?php endif; ?></div>
                    <div class="backup-details">
                        <div class="name"><?php echo htmlspecialchars($backup['name']); ?><?php if ($isLarge): ?> <span class="text-danger" style="font-size: 12px;">(<?php echo $backupSizeMB; ?> MB)</span><?php endif; ?></div>
                        <div class="meta"><?php echo $backup['size']; ?> KB | <?php echo htmlspecialchars($backup['date']); ?></div>
                    </div>
                </div>
                <div class="backup-actions">
                    <a href="?action=download_backup&key=<?php echo htmlspecialchars($key); ?>&file=<?php echo urlencode($backup['name']); ?>" class="btn btn-primary btn-icon">
                        Download
                    </a>
                    <button class="btn btn-danger btn-icon" onclick="deleteBackup('<?php echo htmlspecialchars($backup['name']); ?>')">
                        Delete
                    </button>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
