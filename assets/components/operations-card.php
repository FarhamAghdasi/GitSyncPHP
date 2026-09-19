<!-- Operations -->
<div class="panel section-gap">
    <div class="panel-header">
        <div class="panel-title-wrap">
            <h2><?php echo icon('terminal', 17); ?> Operations</h2>
            <p>View and manage update operations, logs and status.</p>
        </div>
        <div class="panel-actions">
            <button class="btn btn-ghost" onclick="showSettingsModal()"><?php echo icon('settings', 14); ?> Settings</button>
            <button class="btn btn-ghost" onclick="refreshStatus()"><?php echo icon('refresh', 14); ?> Refresh Status</button>
        </div>
    </div>
    <div class="panel-body">
        <div class="info-grid" style="margin-bottom: 16px;">
            <div class="info-block">
                <div class="info-block-label"><?php echo icon('github', 12); ?> Repository</div>
                <div class="info-block-value">
                    <a href="https://github.com/<?php echo urlencode(REPO_USER); ?>/<?php echo urlencode(REPO_NAME); ?>" target="_blank" rel="noopener">
                        <?php echo htmlspecialchars(REPO_USER . '/' . REPO_NAME); ?> <?php echo icon('external-link', 12); ?>
                    </a>
                </div>
            </div>
            <div class="info-block">
                <div class="info-block-label"><?php echo icon('branch', 12); ?> Branch</div>
                <div class="info-block-value"><?php echo htmlspecialchars(BRANCH); ?></div>
            </div>
            <div class="info-block">
                <div class="info-block-label"><?php echo icon('clock', 12); ?> Latest Commit Date</div>
                <div class="info-block-value"><?php echo $latestCommit && !empty($latestCommit['date']) ? htmlspecialchars($latestCommit['date']) : 'No commits yet'; ?></div>
            </div>
            <div class="info-block">
                <div class="info-block-label"><?php echo icon('commit', 12); ?> Latest Commit</div>
                <div class="info-block-value"><?php echo $latestCommit ? htmlspecialchars(substr($latestCommit['sha'], 0, 7)) : 'No commits yet'; ?></div>
            </div>
        </div>

        <div id="updateProgress" style="display: none;">
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <p id="progressText">Updating...</p>
        </div>
    </div>
</div>
