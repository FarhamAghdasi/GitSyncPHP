    <!-- Settings Modal -->
    <div class="modal" id="settingsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>تنظیمات</h3>
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
                    <button type="button" class="btn btn-secondary" onclick="closeSettingsModal()">لغو</button>
                    <button type="submit" class="btn btn-primary">ذخیره</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div class="modal" id="confirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>تأیید آپدیت</h3>
                <button class="modal-close" onclick="closeConfirmModal()">&times;</button>
            </div>
            <p style="margin-bottom: 20px; color: #666;">
                آیا از انجام آپدیت اطمینان دارید؟<br><br>
                <strong>توجه:</strong> قبل از آپدیت یک بک‌آپ خودکار ایجاد می‌شود.
            </p>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeConfirmModal()">لغو</button>
                <button class="btn btn-success" onclick="confirmUpdate()">بله، آپدیت کن</button>
            </div>
        </div>
    </div>
    
    <!-- Delete All Backups Confirmation Modal -->
    <div class="modal" id="deleteAllModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>تأیید حذف همه بک‌آپ‌ها</h3>
                <button class="modal-close" onclick="closeDeleteAllModal()">&times;</button>
            </div>
            <p style="margin-bottom: 20px; color: #666;">
                آیا از حذف تمام بک‌آپ‌ها اطمینان دارید؟<br><br>
                <strong>هشدار:</strong> این عملیات برگشت‌پذیر نیست!
            </p>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDeleteAllModal()">لغو</button>
                <button class="btn btn-danger" onclick="confirmDeleteAllBackups()">حذف همه</button>
            </div>
        </div>
    </div>
    
    <script src="assets/script.js"></script>
</body>
</html>
