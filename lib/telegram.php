<?php
/**
 * Telegram Notification Module
 */

function sendTelegramNotification($commitInfo, $extractResult, $commitDetails = null) {
    if (empty(TELEGRAM_BOT_TOKEN) || empty(TELEGRAM_CHAT_ID)) {
        logDebug("Telegram not configured");
        return false;
    }

    $requestUrl = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');

    $message = "*Website Update Successful!*\n\n";
    $message .= "*Repository:* " . REPO_USER . "/" . REPO_NAME . "\n";
    $message .= "*Branch:* " . BRANCH . "\n";
    $message .= "*Commit:* " . substr($commitInfo['sha'], 0, 7) . "\n";
    $message .= "*Message:* " . $commitInfo['message'] . "\n";
    $message .= "*Author:* " . $commitInfo['author'] . "\n";
    $message .= "*Time:* " . date('Y-m-d H:i:s') . "\n";

    if ($commitDetails) {
        $stats = $commitDetails['stats'];
        $message .= "\n*Changes:* " . $stats['files_changed'] . " files changed";
        $message .= " (+" . $stats['total_additions'] . "/-" . $stats['total_deletions'] . ")\n";

        $filesList = "";
        $count = 0;
        foreach ($commitDetails['files'] as $file) {
            if ($count >= 10) {
                $filesList .= "\n  ... and " . (count($commitDetails['files']) - 10) . " more files";
                break;
            }
            $statusIcon = $file['status'] === 'added' ? '+' :
                         ($file['status'] === 'deleted' ? '-' : '~');
            $filesList .= "\n  " . $statusIcon . " `" . basename($file['filename']) . "`";
            $filesList .= " (+" . $file['additions'] . "/-" . $file['deletions'] . ")";
            $count++;
        }
        if (!empty($filesList)) {
            $message .= "*Files:*" . $filesList . "\n";
        }
    }

    $message .= "\n*Update Stats:* " . $extractResult['files_updated'] . " files updated\n";
    $message .= "*URL:* " . $requestUrl;

    $headers = [
        'Content-Type' => 'application/json',
        'token' => TELEGRAM_BOT_TOKEN
    ];

    $postData = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];

    logDebug("Sending Telegram notification to " . TELEGRAM_CHAT_ID);

    $response = proxyRequest('telegram', '/sendMessage', $headers, 'POST', $postData);

    if ($response && $response['status_code'] === 200) {
        logDebug("Telegram notification sent successfully");
        return true;
    } else {
        logError("Telegram notification failed: " . ($response['error'] ?? 'Unknown error'));
        if ($response && $response['body']) {
            logDebug("Telegram response: " . $response['body']);
        }
        return false;
    }
}
