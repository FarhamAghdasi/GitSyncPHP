<?php if ($commitDetails && !empty($commitDetails['files'])): ?>
<!-- Commit Details -->
<div class="panel section-gap">
    <div class="panel-header">
        <div class="panel-title-wrap">
            <h2><?php echo icon('commit', 17); ?> Commit Details</h2>
            <p>Files changed in the latest commit.</p>
        </div>
        <span class="badge"><?php echo $commitDetails['stats']['files_changed']; ?> files changed</span>
    </div>
    <div class="panel-body">
        <div class="commit-message-box">
            <strong>Full Message</strong>
            <p><?php echo htmlspecialchars($commitDetails['message']); ?></p>
        </div>

        <div class="commit-meta">
            <span><?php echo icon('user', 14); ?> <strong>Author:</strong> <?php echo htmlspecialchars($commitDetails['author']); ?></span>
            <span><?php echo icon('calendar', 14); ?> <strong>Date:</strong> <?php echo htmlspecialchars($commitDetails['date']); ?></span>
            <span><strong>Additions:</strong> <span class="text-success">+<?php echo $commitDetails['stats']['total_additions']; ?></span></span>
            <span><strong>Deletions:</strong> <span class="text-danger">-<?php echo $commitDetails['stats']['total_deletions']; ?></span></span>
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
                        $statusColor = $file['status'] === 'added' ? 'var(--green)' :
                                      ($file['status'] === 'deleted' ? 'var(--red)' : 'var(--amber)');
                    ?>
                    <tr>
                        <td style="color: <?php echo $statusColor; ?>;">
                            <?php echo htmlspecialchars($file['status']); ?>
                        </td>
                        <td style="font-family: var(--font-mono); font-size: 12px;">
                            <?php echo htmlspecialchars(basename($file['filename'])); ?>
                            <span style="opacity: 0.5; font-size: 11px;">(<?php echo htmlspecialchars(dirname($file['filename'])); ?>)</span>
                        </td>
                        <td style="text-align: center; color: var(--green);"><?php echo $file['additions']; ?></td>
                        <td style="text-align: center; color: var(--red);"><?php echo $file['deletions']; ?></td>
                        <td style="text-align: center;"><?php echo $file['changes']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
