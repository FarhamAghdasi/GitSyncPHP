<!-- Status Cards -->
<div class="status-cards">
    <div class="status-card glass-card info">
        <div class="icon">📌</div>
        <div class="label">Current Version</div>
        <div class="value"><?php echo htmlspecialchars($currentVersion); ?></div>
    </div>
    <?php if ($latestCommit): ?>
    <div class="status-card glass-card success">
        <div class="icon">🔖</div>
        <div class="label">Latest Commit</div>
        <div class="value"><?php echo htmlspecialchars(substr($latestCommit['sha'], 0, 7)); ?></div>
    </div>
    <div class="status-card glass-card info">
        <div class="icon">📝</div>
        <div class="label">Commit Message</div>
        <div class="value" style="font-size: 13px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
            <?php
            $msg = $latestCommit['message'];
            echo htmlspecialchars(strlen($msg) > 50 ? substr($msg, 0, 50) . '...' : $msg);
            ?>
        </div>
    </div>
    <div class="status-card glass-card warning">
        <div class="icon">📅</div>
        <div class="label">Date</div>
        <div class="value"><?php echo htmlspecialchars($latestCommit['date']); ?></div>
    </div>
    <?php endif; ?>
    <div class="status-card glass-card <?php echo $updateAvailable ? 'success' : 'info'; ?>">
        <div class="icon">🔄</div>
        <div class="label">Status</div>
        <div class="value"><?php echo $updateAvailable ? 'Update Available' : 'Up to Date'; ?></div>
    </div>
</div>
