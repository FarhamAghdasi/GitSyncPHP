            </main>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal" id="settingsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php echo icon('settings', 17); ?> Settings</h3>
                <button class="modal-close" onclick="closeSettingsModal()">x</button>
            </div>
            <form id="settingsForm">
                <div class="settings-grid">
                    <div class="form-group">
                        <label>GITHUB_TOKEN</label>
                        <div class="input-with-action">
                            <input type="password" name="GITHUB_TOKEN" value="<?php echo htmlspecialchars(GITHUB_TOKEN); ?>" placeholder="ghp_...">
                            <button type="button" class="field-toggle" onclick="toggleFieldVisibility(this)" aria-label="Show value">
                                <?php echo icon('eye', 15); ?>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>REPO_USER</label>
                        <input type="text" name="REPO_USER" value="<?php echo htmlspecialchars(REPO_USER); ?>">
                    </div>
                    <div class="form-group">
                        <label>REPO_NAME</label>
                        <input type="text" name="REPO_NAME" value="<?php echo htmlspecialchars(REPO_NAME); ?>">
                    </div>
                    <div class="form-group">
                        <label>BRANCH</label>
                        <input type="text" name="BRANCH" value="<?php echo htmlspecialchars(BRANCH); ?>">
                    </div>
                    <div class="form-group">
                        <label>TELEGRAM_BOT_TOKEN</label>
                        <div class="input-with-action">
                            <input type="password" name="TELEGRAM_BOT_TOKEN" value="<?php echo htmlspecialchars(TELEGRAM_BOT_TOKEN); ?>" placeholder="123456:ABC...">
                            <button type="button" class="field-toggle" onclick="toggleFieldVisibility(this)" aria-label="Show value">
                                <?php echo icon('eye', 15); ?>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>TELEGRAM_CHAT_ID</label>
                        <input type="text" name="TELEGRAM_CHAT_ID" value="<?php echo htmlspecialchars(TELEGRAM_CHAT_ID); ?>">
                    </div>
                    <div class="form-group">
                        <label>BACKUP_DIR</label>
                        <input type="text" name="BACKUP_DIR" value="<?php echo htmlspecialchars(BACKUP_DIR); ?>">
                    </div>
                    <div class="form-group">
                        <label>EXCLUDE_FILES</label>
                        <input type="text" name="EXCLUDE_FILES" value="<?php echo htmlspecialchars(implode(',', EXCLUDE_FILES)); ?>">
                    </div>
                    <div class="form-group">
                        <label class="switch-row">
                            <span class="switch-row-text">BACKUP_BEFORE_UPDATE</span>
                            <span class="switch">
                                <input type="checkbox" name="BACKUP_BEFORE_UPDATE" <?php echo BACKUP_BEFORE_UPDATE ? 'checked' : ''; ?>>
                                <span class="switch-track"><span class="switch-thumb"></span></span>
                            </span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="switch-row">
                            <span class="switch-row-text">DELETE_EXTRACTED_FILES</span>
                            <span class="switch">
                                <input type="checkbox" name="DELETE_EXTRACTED_FILES" <?php echo DELETE_EXTRACTED_FILES ? 'checked' : ''; ?>>
                                <span class="switch-track"><span class="switch-thumb"></span></span>
                            </span>
                        </label>
                    </div>

                    <div class="form-group form-divider">
                        <h4>Target Directory</h4>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>TARGET_DIR</label>
                        <input type="text" name="TARGET_DIR" value="<?php echo htmlspecialchars(TARGET_DIR); ?>" placeholder="/home/user/public_html/project">
                        <small class="field-hint">
                            Directory where files will be updated (e.g., <?php echo htmlspecialchars(dirname(__DIR__)); ?>)
                        </small>
                    </div>

                    <div class="form-group form-divider">
                        <h4>Proxy Settings</h4>
                    </div>
                    <div class="form-group">
                        <label class="switch-row">
                            <span class="switch-row-text">Enable Cloudflare Worker Proxy</span>
                            <span class="switch">
                                <input type="checkbox" name="USE_PROXY" <?php echo USE_PROXY ? 'checked' : ''; ?>>
                                <span class="switch-track"><span class="switch-thumb"></span></span>
                            </span>
                        </label>
                        <small class="field-hint">
                            Enable if you cannot access GitHub API directly
                        </small>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>PROXY_URL</label>
                        <input type="text" name="PROXY_URL" value="<?php echo htmlspecialchars(PROXY_URL); ?>" placeholder="https://github-proxy.your-subdomain.workers.dev">
                        <small class="field-hint">
                            Your Cloudflare Worker URL
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeSettingsModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><?php echo icon('check-circle', 15); ?> Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal" id="confirmModal">
        <div class="modal-content" style="max-width: 440px;">
            <div class="modal-header">
                <h3><?php echo icon('sync', 16); ?> Confirm Update</h3>
                <button class="modal-close" onclick="closeConfirmModal()">x</button>
            </div>
            <p style="color: var(--text-secondary); font-size: 13.5px; line-height: 1.6;">
                Are you sure you want to perform the update?<br><br>
                <strong style="color: var(--text-primary);">Note:</strong> An automatic backup will be created before the update.
            </p>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeConfirmModal()">Cancel</button>
                <button class="btn btn-success" onclick="confirmUpdate()"><?php echo icon('check-circle', 15); ?> Yes, Update</button>
            </div>
        </div>
    </div>

    <!-- Delete All Backups Confirmation Modal -->
    <div class="modal" id="deleteAllModal">
        <div class="modal-content" style="max-width: 440px;">
            <div class="modal-header">
                <h3><?php echo icon('alert-triangle', 16); ?> Confirm Delete All Backups</h3>
                <button class="modal-close" onclick="closeDeleteAllModal()">x</button>
            </div>
            <p style="color: var(--text-secondary); font-size: 13.5px; line-height: 1.6;">
                Are you sure you want to delete all backups?<br><br>
                <strong style="color: var(--text-primary);">Warning:</strong> This action cannot be undone!
            </p>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeDeleteAllModal()">Cancel</button>
                <button class="btn btn-danger" onclick="confirmDeleteAllBackups()"><?php echo icon('trash', 15); ?> Delete All</button>
            </div>
        </div>
    </div>

    <!-- Confirm Clear Log Modal -->
    <div class="modal" id="confirmClearLogModal">
        <div class="modal-content" style="max-width: 440px;">
            <div class="modal-header">
                <h3><?php echo icon('terminal', 16); ?> Confirm Clear Log</h3>
                <button class="modal-close" onclick="closeConfirmClearLogModal()">x</button>
            </div>
            <p id="confirmClearLogMessage" style="color: var(--text-secondary); font-size: 13.5px; line-height: 1.6;">
                Are you sure you want to clear the log?
            </p>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeConfirmClearLogModal()">Cancel</button>
                <button class="btn btn-danger" onclick="confirmClearLog()"><?php echo icon('trash', 15); ?> Clear Log</button>
            </div>
        </div>
    </div>

    <script>
        var UPDATE_KEY = '<?php echo htmlspecialchars($key); ?>';
    </script>
    <script src="assets/script.js"></script>
</body>
</html>
