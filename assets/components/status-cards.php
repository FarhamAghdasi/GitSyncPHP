<!-- Status Cards -->
<div class="status-grid section-gap">
    <div class="stat-card">
        <div class="stat-icon c-green"><?php echo icon('check-circle', 18); ?></div>
        <div class="stat-body">
            <div class="stat-label">Current Version</div>
            <div class="stat-value"><?php echo htmlspecialchars($currentVersion); ?></div>
            <div class="stat-desc"><?php echo $currentVersion === 'N/A' ? 'No version installed yet' : 'Installed on this system'; ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon c-blue"><?php echo icon('calendar', 18); ?></div>
        <div class="stat-body">
            <div class="stat-label">Update Status</div>
            <div class="stat-value"><?php echo $updateAvailable ? 'Update Available' : 'Up to Date'; ?></div>
            <div class="stat-desc">
                <?php
                if ($latestCommit && !empty($latestCommit['date'])) {
                    echo 'Latest commit: ' . htmlspecialchars($latestCommit['date']);
                } else {
                    echo 'No commit data yet';
                }
                ?>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon c-purple"><?php echo icon('archive', 18); ?></div>
        <div class="stat-body">
            <div class="stat-label">Backup Before Update</div>
            <div class="stat-value"><?php echo BACKUP_BEFORE_UPDATE ? 'Enabled' : 'Disabled'; ?></div>
            <div class="stat-desc"><?php echo count($backups); ?> backup<?php echo count($backups) === 1 ? '' : 's'; ?> stored</div>
        </div>
        <span class="stat-badge <?php echo BACKUP_BEFORE_UPDATE ? 'on' : 'off'; ?>"><?php echo BACKUP_BEFORE_UPDATE ? 'ON' : 'OFF'; ?></span>
    </div>
</div>
