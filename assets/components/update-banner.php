<?php if ($updateAvailable): ?>
<!-- Update Banner -->
<div class="update-banner glass-card">
    <div class="info">
        <h3>New Update Available!</h3>
        <p>Version <?php echo htmlspecialchars(substr($latestCommit['sha'], 0, 7)); ?> is ready to install</p>
        <?php if ($commitDetails): ?>
        <p style="font-size: 13px; opacity: 0.8; margin-top: 5px;">
            <?php echo $commitDetails['stats']['files_changed']; ?> files changed
            (+<?php echo $commitDetails['stats']['total_additions']; ?>/<?php echo $commitDetails['stats']['total_deletions']; ?>)
        </p>
        <?php endif; ?>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button class="btn btn-success" onclick="startUpdate()">Start Update</button>
        <button class="btn btn-ghost" onclick="showSettingsModal()">Settings</button>
    </div>
</div>
<?php else: ?>
<div class="update-banner glass-card" style="--banner-accent: var(--color-info);">
    <div class="info">
        <h3>System is Up to Date</h3>
        <p>You are using the latest version</p>
    </div>
    <button class="btn btn-primary" onclick="forceUpdate()">Check Again</button>
</div>
<?php endif; ?>
