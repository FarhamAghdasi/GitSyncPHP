<!-- Operations Card -->
<div class="card glass-card">
    <div class="card-header">
        <h2>Operations</h2>
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-primary" onclick="showSettingsModal()">Settings</button>
            <button class="btn btn-ghost" onclick="refreshStatus()">Refresh Status</button>
        </div>
    </div>
    <div class="card-body">
        <div id="updateProgress" style="display: none;">
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <p id="progressText" style="text-align: center; opacity: 0.7;">Updating...</p>
        </div>
    </div>
</div>
