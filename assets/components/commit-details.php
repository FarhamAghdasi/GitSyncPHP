<?php if ($commitDetails && !empty($commitDetails['files'])): ?>
<!-- Commit Details Card -->
<div class="card glass-card">
    <div class="card-header">
        <h2>Commit Details</h2>
        <span class="badge"><?php echo $commitDetails['stats']['files_changed']; ?> files changed</span>
    </div>
    <div class="card-body">
        <div class="commit-message-box glass-inset">
            <strong>Full Message:</strong>
            <p style="margin-top: 8px; white-space: pre-wrap; word-break: break-word;">
                <?php echo htmlspecialchars($commitDetails['message']); ?>
            </p>
        </div>

        <div class="commit-meta">
            <span>👤 <strong>Author:</strong> <?php echo htmlspecialchars($commitDetails['author']); ?></span>
            <span>📅 <strong>Date:</strong> <?php echo htmlspecialchars($commitDetails['date']); ?></span>
            <span>➕ <strong>Additions:</strong> <span class="text-success"><?php echo $commitDetails['stats']['total_additions']; ?></span></span>
            <span>➖ <strong>Deletions:</strong> <span class="text-danger"><?php echo $commitDetails['stats']['total_deletions']; ?></span></span>
        </div>

        <div class="file-changes-table">
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>File</th>
                        <th>Additions</th>
                        <th>Deletions</th>
                        <th>Changes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commitDetails['files'] as $file):
                        $statusIcon = $file['status'] === 'added' ? '➕' :
                                     ($file['status'] === 'deleted' ? '➖' : '📝');
                        $statusColor = $file['status'] === 'added' ? 'var(--color-success)' :
                                      ($file['status'] === 'deleted' ? 'var(--color-danger)' : 'var(--color-warning)');
                    ?>
                    <tr>
                        <td style="color: <?php echo $statusColor; ?>;">
                            <?php echo $statusIcon; ?> <?php echo htmlspecialchars($file['status']); ?>
                        </td>
                        <td style="font-family: monospace; font-size: 12px;">
                            <?php echo htmlspecialchars(basename($file['filename'])); ?>
                            <span style="opacity: 0.5; font-size: 11px;">(<?php echo htmlspecialchars(dirname($file['filename'])); ?>)</span>
                        </td>
                        <td style="text-align: center; color: var(--color-success);"><?php echo $file['additions']; ?></td>
                        <td style="text-align: center; color: var(--color-danger);"><?php echo $file['deletions']; ?></td>
                        <td style="text-align: center;"><?php echo $file['changes']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
