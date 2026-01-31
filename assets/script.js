// GitSyncPHP JavaScript

// Global variables
var UPDATE_KEY = '<?php echo htmlspecialchars($key); ?>';

// Toast Notification System
function showToast(message, type, duration) {
    type = type || 'info';
    duration = duration || 4000;
    
    var container = document.getElementById('toastContainer');
    var toast = document.createElement('div');
    toast.className = 'toast ' + type;
    
    var icons = {
        success: '✓',
        error: '✗',
        warning: '⚠',
        info: 'ℹ'
    };
    
    toast.innerHTML = 
        '<span class="toast-icon">' + (icons[type] || icons.info) + '</span>' +
        '<span class="toast-message">' + message + '</span>' +
        '<button class="toast-close" onclick="this.parentElement.remove()">×</button>';
    
    container.appendChild(toast);
    
    // Auto remove after duration
    setTimeout(function() {
        if (toast.parentElement) {
            toast.remove();
        }
    }, duration);
}

// Auto-scroll log to bottom
function scrollLogToBottom() {
    var log = document.getElementById('logContainer');
    if (log) {
        log.scrollTop = log.scrollHeight;
    }
}

scrollLogToBottom();

// Modal functions
function showSettingsModal() {
    document.getElementById('settingsModal').classList.add('active');
}

function closeSettingsModal() {
    document.getElementById('settingsModal').classList.remove('active');
}

function showConfirmModal() {
    document.getElementById('confirmModal').classList.add('active');
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('active');
}

function showDeleteAllModal() {
    document.getElementById('deleteAllModal').classList.add('active');
}

function closeDeleteAllModal() {
    document.getElementById('deleteAllModal').classList.remove('active');
}

// Settings form submission
document.getElementById('settingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var formData = new FormData();
    formData.append('action', 'save_settings');
    formData.append('key', UPDATE_KEY);
    
    // Handle checkboxes
    var backupCheckbox = document.querySelector('input[name="BACKUP_BEFORE_UPDATE"]');
    var deleteCheckbox = document.querySelector('input[name="DELETE_EXTRACTED_FILES"]');
    
    if (backupCheckbox) {
        formData.set('BACKUP_BEFORE_UPDATE', backupCheckbox.checked ? 'true' : 'false');
    }
    if (deleteCheckbox) {
        formData.set('DELETE_EXTRACTED_FILES', deleteCheckbox.checked ? 'true' : 'false');
    }
    
    // Add form fields
    var inputs = document.querySelectorAll('#settingsForm input, #settingsForm select');
    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].name && inputs[i].name !== 'BACKUP_BEFORE_UPDATE' && inputs[i].name !== 'DELETE_EXTRACTED_FILES') {
            formData.append(inputs[i].name, inputs[i].value);
        }
    }
    
    fetch('', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(result) {
        if (result.success) {
            closeSettingsModal();
            showToast('Settings saved successfully', 'success');
            setTimeout(function() { location.reload(); }, 1500);
        } else {
            showToast('Error saving settings: ' + result.message, 'error');
        }
    })
    .catch(function(error) {
        showToast('Server connection error', 'error');
    });
});

// Update functions
function startUpdate() {
    showConfirmModal();
}

function confirmUpdate() {
    closeConfirmModal();
    runUpdate();
}

function forceUpdate() {
    runUpdate();
}

function runUpdate() {
    var progressDiv = document.getElementById('updateProgress');
    var progressFill = document.getElementById('progressFill');
    var progressText = document.getElementById('progressText');
    
    progressDiv.style.display = 'block';
    progressFill.style.width = '30%';
    progressText.textContent = 'Connecting to GitHub...';
    
    var formData = new FormData();
    formData.append('action', 'run_update');
    formData.append('key', UPDATE_KEY);
    
    fetch('', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(result) {
        progressFill.style.width = '70%';
        progressText.textContent = 'Downloading and extracting files...';
        
        progressFill.style.width = '100%';
        
        if (result.success) {
            if (result.upgraded) {
                progressText.textContent = 'Update completed successfully!';
                showToast('Update completed successfully! Version: ' + result.new_version, 'success');
            } else {
                progressText.textContent = 'System was already up to date';
                showToast('System is already up to date', 'info');
            }
            
            setTimeout(function() { location.reload(); }, 2000);
        } else {
            progressText.textContent = 'Update failed';
            showToast('Update failed', 'error');
            setTimeout(function() { progressDiv.style.display = 'none'; }, 3000);
        }
        
        // Refresh log
        location.reload();
    })
    .catch(function(error) {
        progressText.textContent = 'Server connection error';
        showToast('Server connection error', 'error');
        setTimeout(function() { progressDiv.style.display = 'none'; }, 3000);
    });
}

// Clear log
function clearLog() {
    fetch('?action=get_log_size&key=' + UPDATE_KEY)
    .then(function(response) { return response.json(); })
    .then(function(sizeData) {
        var confirmMessage = 'Do you want to clear the log?';
        if (sizeData.size > 200) {
            confirmMessage = 'Warning! Log file size is ' + sizeData.size + ' MB.\n\nAre you sure you want to clear it?';
        }
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        fetch('?action=clear_log&key=' + UPDATE_KEY)
        .then(function(response) { return response.json(); })
        .then(function(result) {
            if (result.success) {
                showToast('Log cleared successfully', 'success');
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                showToast('Error clearing log: ' + result.message, 'error');
            }
        })
        .catch(function(error) {
            showToast('Server connection error', 'error');
        });
    })
    .catch(function(error) {
        showToast('Server connection error', 'error');
    });
}

// Delete backup
function deleteBackup(filename) {
    var formData = new FormData();
    formData.append('action', 'get_backup_size');
    formData.append('key', UPDATE_KEY);
    formData.append('file', filename);
    
    fetch('', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(sizeData) {
        var confirmMessage = 'Are you sure you want to delete this backup?';
        if (sizeData.size > 200) {
            confirmMessage = 'Warning! This backup size is ' + sizeData.size + ' MB.\n\nAre you sure you want to delete it?';
        }
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        var delFormData = new FormData();
        delFormData.append('action', 'delete_backup');
        delFormData.append('key', UPDATE_KEY);
        delFormData.append('file', filename);
        
        fetch('', {
            method: 'POST',
            body: delFormData
        })
        .then(function(response) { return response.json(); })
        .then(function(result) {
            if (result.success) {
                showToast('Backup deleted successfully', 'success');
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                showToast('Error deleting backup: ' + result.message, 'error');
            }
        })
        .catch(function(error) {
            showToast('Server connection error', 'error');
        });
    })
    .catch(function(error) {
        showToast('Server connection error', 'error');
    });
}

// Delete all backups
function deleteAllBackups() {
    showDeleteAllModal();
}

function confirmDeleteAllBackups() {
    closeDeleteAllModal();
    
    var formData = new FormData();
    formData.append('action', 'delete_all_backups');
    formData.append('key', UPDATE_KEY);
    
    fetch('', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(result) {
        if (result.success) {
            showToast(result.message || 'All backups deleted successfully', 'success');
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showToast('Error deleting backups: ' + result.message, 'error');
        }
    })
    .catch(function(error) {
        showToast('Server connection error', 'error');
    });
}

// Refresh status
function refreshStatus() {
    location.reload();
}

// Close modals on outside click
document.querySelectorAll('.modal').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});