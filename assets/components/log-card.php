<?php
$logSizeMB = file_exists(LOG_FILE) ? round(filesize(LOG_FILE) / (1024 * 1024), 2) : 0;
$logIsLarge = $logSizeMB > 200;
?>
<!-- Operation Log -->
<div class="panel section-gap" id="log-section">
    <div class="panel-header">
        <div class="panel-title-wrap">
            <h2>
                <?php echo icon('terminal', 17); ?> Operation Log
                <?php if ($logIsLarge): ?><span class="warning-badge text"><?php echo $logSizeMB; ?> MB</span><?php endif; ?>
            </h2>
            <p>Real-time logs of GitHub update operations.</p>
        </div>
        <div class="panel-actions" style="align-items: center;">
            <button class="btn btn-ghost btn-icon" onclick="clearLog()"><?php echo icon('trash', 13); ?> Clear</button>
            <span class="badge live">Live</span>
        </div>
    </div>
    <div class="panel-body">
        <div class="log-container" id="logContainer">
            <?php
            $lines = explode("\n", $logContent);
            $hasLines = false;
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $hasLines = true;

                $class = 'info';
                if (strpos($line, '[ERROR]') !== false) $class = 'error';
                elseif (strpos($line, '[WARNING]') !== false) $class = 'warning';
                elseif (strpos($line, '[SUCCESS]') !== false) $class = 'success';
                elseif (strpos($line, '[DEBUG]') !== false) $class = 'debug';

                $line = maskSensitiveData($line);
                echo '<div class="log-line ' . $class . '">' . htmlspecialchars($line) . '</div>';
            }
            if (!$hasLines) {
                echo '<div class="log-empty">No log entries yet. Run an update or check to see activity here.</div>';
            }
            ?>
        </div>
    </div>
</div>
