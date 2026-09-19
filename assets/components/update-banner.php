<?php if ($updateAvailable): ?>
<!-- Health Banner: update available -->
<div class="health-banner pending section-gap">
    <div class="health-left">
        <div class="health-icon"><?php echo icon('refresh', 19); ?></div>
        <div class="health-text">
            <h3>New Update Available</h3>
            <p>
                Version <?php echo htmlspecialchars(substr($latestCommit['sha'], 0, 7)); ?> is ready to install
                <?php if ($commitDetails): ?>
                    &nbsp;·&nbsp;<?php echo $commitDetails['stats']['files_changed']; ?> files changed
                    (+<?php echo $commitDetails['stats']['total_additions']; ?>/-<?php echo $commitDetails['stats']['total_deletions']; ?>)
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="health-actions">
        <button class="btn btn-success" onclick="startUpdate()"><?php echo icon('download', 15); ?> Start Update</button>
        <button class="btn btn-ghost" onclick="showSettingsModal()"><?php echo icon('settings', 15); ?> Settings</button>
    </div>
</div>
<?php else: ?>
<!-- Health Banner: up to date -->
<div class="health-banner ok section-gap">
    <div class="health-left">
        <div class="health-icon"><?php echo icon('shield', 19); ?></div>
        <div class="health-text">
            <h3>System is Up to Date</h3>
            <p>Your application is running the latest version from GitHub.</p>
        </div>
    </div>
    <div class="health-actions">
        <button class="btn btn-primary" onclick="forceUpdate()"><?php echo icon('refresh', 15); ?> Check Again</button>
    </div>
</div>
<?php endif; ?>
