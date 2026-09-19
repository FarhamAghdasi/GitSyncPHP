<!-- Backups -->
<div class="panel section-gap" id="backups-section">
    <div class="panel-header">
        <div class="panel-title-wrap">
            <h2><?php echo icon('database', 17); ?> Backups</h2>
            <p>Manage your application backups.</p>
        </div>
        <div class="panel-actions" style="align-items: center;">
            <span class="badge"><?php echo count($backups); ?> items</span>
            <?php if (!empty($backups)): ?>
            <button class="btn btn-danger btn-icon" onclick="deleteAllBackups()"><?php echo icon('trash', 13); ?> Delete All</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="panel-body">
        <?php if (empty($backups)): ?>
        <div class="empty-state">
            <div class="icon-wrap"><?php echo icon('archive', 24); ?></div>
            <p>No backups available</p>
            <span>Backups will appear here once created.</span>
        </div>
        <?php else: ?>
        <ul class="backup-list">
            <?php foreach ($backups as $backup):
                $backupSizeMB = round(filesize($backup['path']) / (1024 * 1024), 2);
                $isLarge = $backupSizeMB > 200;
            ?>
            <li class="backup-item">
                <div class="backup-info">
                    <div class="backup-icon">
                        <?php echo icon('package', 17); ?>
                        <?php if ($isLarge): ?><span class="warning-badge">!</span><?php endif; ?>
                    </div>
                    <div class="backup-details">
                        <div class="name"><?php echo htmlspecialchars($backup['name']); ?><?php if ($isLarge): ?> <span class="text-danger" style="font-size: 11px;">(<?php echo $backupSizeMB; ?> MB)</span><?php endif; ?></div>
                        <div class="meta"><?php echo $backup['size']; ?> KB &middot; <?php echo htmlspecialchars($backup['date']); ?></div>
                    </div>
                </div>
                <div class="backup-actions">
                    <a href="?action=download_backup&key=<?php echo htmlspecialchars($key); ?>&file=<?php echo urlencode($backup['name']); ?>" class="btn btn-primary btn-icon">
                        <?php echo icon('download', 13); ?> Download
                    </a>
                    <button class="btn btn-danger btn-icon" onclick="deleteBackup('<?php echo htmlspecialchars($backup['name']); ?>')">
                        <?php echo icon('trash', 13); ?> Delete
                    </button>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
