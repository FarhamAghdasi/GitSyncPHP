    <!-- Settings Modal -->
    <div class="modal" id="settingsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Settings</h3>
                <button class="modal-close" onclick="closeSettingsModal()">&times;</button>
            </div>
            <form id="settingsForm">
                <div class="settings-grid">
                    <div class="form-group">
                        <label>GITHUB_TOKEN</label>
                        <input type="text" name="GITHUB_TOKEN" value="<?php echo htmlspecialchars(GITHUB_TOKEN); ?>" placeholder="ghp_...">
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
                        <input type="text" name="TELEGRAM_BOT_TOKEN" value="<?php echo htmlspecialchars(TELEGRAM_BOT_TOKEN); ?>" placeholder="123456:ABC...">
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
                        <label class="checkbox-group">
                            <input type="checkbox" name="BACKUP_BEFORE_UPDATE" <?php echo BACKUP_BEFORE_UPDATE ? 'checked' : ''; ?>>
                            <span>BACKUP_BEFORE_UPDATE</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-group">
                            <input type="checkbox" name="DELETE_EXTRACTED_FILES" <?php echo DELETE_EXTRACTED_FILES ? 'checked' : ''; ?>>
                            <span>DELETE_EXTRACTED_FILES</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeSettingsModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div class="modal" id="confirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Update</h3>
                <button class="modal-close" onclick="closeConfirmModal()">&times;</button>
            </div>
            <p style="margin-bottom: 20px; color: #666;">
                Are you sure you want to perform the update?<br><br>
                <strong>Note:</strong> An automatic backup will be created before the update.
            </p>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeConfirmModal()">Cancel</button>
                <button class="btn btn-success" onclick="confirmUpdate()">Yes, Update</button>
            </div>
        </div>
    </div>
    
    <!-- Delete All Backups Confirmation Modal -->
    <div class="modal" id="deleteAllModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Delete All Backups</h3>
                <button class="modal-close" onclick="closeDeleteAllModal()">&times;</button>
            </div>
            <p style="margin-bottom: 20px; color: #666;">
                Are you sure you want to delete all backups?<br><br>
                <strong>Warning:</strong> This action cannot be undone!
            </p>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDeleteAllModal()">Cancel</button>
                <button class="btn btn-danger" onclick="confirmDeleteAllBackups()">Delete All</button>
            </div>
        </div>
    </div>
    
    <script src="assets/script.js"></script>
</body>
</html>