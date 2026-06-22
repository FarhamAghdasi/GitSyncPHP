<?php
$logSizeMB = file_exists(LOG_FILE) ? round(filesize(LOG_FILE) / (1024 * 1024), 2) : 0;
$logIsLarge = $logSizeMB > 200;
?>
<!-- Log Card -->
<div class="card glass-card">
    <div class="card-header">
        <h2>Operation Log<?php if ($logIsLarge): ?><span class="warning-badge"><?php echo $logSizeMB; ?> MB</span><?php endif; ?></h2>
        <button class="btn btn-ghost btn-icon" onclick="clearLog()">Clear</button>
    </div>
    <div class="card-body">
        <div class="log-container" id="logContainer">
            <?php
            $lines = explode("\n", $logContent);
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;

                $class = 'info';
                if (strpos($line, '[ERROR]') !== false) $class = 'error';
                elseif (strpos($line, '[WARNING]') !== false) $class = 'warning';
                elseif (strpos($line, '[SUCCESS]') !== false) $class = 'success';
                elseif (strpos($line, '[DEBUG]') !== false) $class = 'debug';

                $line = maskSensitiveData($line);
                echo '<div class="log-line ' . $class . '">' . htmlspecialchars($line) . '</div>';
            }
            ?>
        </div>
    </div>
</div>
