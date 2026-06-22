<?php
/**
 * GitHub API Module
 * Handles communication with the GitHub API.
 */

function testGitHubConnection() {
    logDebug("Testing GitHub connection...");

    $testHeaders = ['User-Agent: PHP-Update-Script'];
    if (!empty(GITHUB_TOKEN)) {
        $testHeaders[] = 'Authorization: token ' . GITHUB_TOKEN;
    }

    $response = proxyRequest('github', '/repos/' . REPO_USER . '/' . REPO_NAME, $testHeaders);
    logResponse($response['status_code'], $response['body']);

    if ($response['status_code'] === 401) {
        logError("GitHub token is invalid or expired");
    } elseif ($response['status_code'] === 403) {
        logError("Rate limit exceeded or no access to repository");
        if ($response['body']) {
            $data = json_decode($response['body'], true);
            if (isset($data['message'])) {
                logError("GitHub says: " . $data['message']);
            }
        }
    } elseif ($response['status_code'] === 404) {
        logError("Repository not found: " . REPO_USER . "/" . REPO_NAME);
    } elseif ($response['status_code'] === 200) {
        logSuccess("GitHub connection successful");
    } else {
        logError("Unexpected response: " . $response['status_code']);
    }
}

function getLatestCommitInfo() {
    $apiPath = "/repos/" . REPO_USER . "/" . REPO_NAME . "/commits/" . BRANCH;

    $headers = [
        'User-Agent: PHP-Update-Script',
        'Accept: application/vnd.github.v3+json'
    ];

    if (!empty(GITHUB_TOKEN)) {
        $headers[] = 'Authorization: token ' . GITHUB_TOKEN;
    }

    logRequest($apiPath, $headers);
    $response = proxyRequest('github', $apiPath, $headers);
    logResponse($response['status_code'], $response['body']);

    if (!$response) {
        logError("No response from GitHub API");
        return false;
    }

    if ($response['status_code'] !== 200) {
        logError("GitHub API returned HTTP " . $response['status_code']);
        if ($response['body']) {
            $error = json_decode($response['body'], true);
            if (isset($error['message'])) {
                logError("Error message: " . $error['message']);
            }
        }
        return false;
    }

    if (empty($response['body'])) {
        logError("Empty response body from GitHub");
        return false;
    }

    $data = json_decode($response['body'], true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        logError("JSON parse error: " . json_last_error_msg());
        return false;
    }

    if (!isset($data['sha'])) {
        logError("Invalid response structure - no SHA found");
        return false;
    }

    return [
        'sha' => $data['sha'],
        'message' => $data['commit']['message'] ?? 'No message',
        'author' => $data['commit']['author']['name'] ?? 'Unknown',
        'date' => isset($data['commit']['author']['date']) ? date('Y-m-d H:i:s', strtotime($data['commit']['author']['date'])) : ''
    ];
}

function getCommitDetails($commitSha) {
    global $cachedCommitDetails;

    if ($cachedCommitDetails !== null) {
        return $cachedCommitDetails;
    }

    $apiPath = "/repos/" . REPO_USER . "/" . REPO_NAME . "/commits/" . $commitSha;

    $headers = [
        'User-Agent: PHP-Update-Script',
        'Accept: application/vnd.github.v3+json'
    ];

    if (!empty(GITHUB_TOKEN)) {
        $headers[] = 'Authorization: token ' . GITHUB_TOKEN;
    }

    logRequest($apiPath, $headers);
    $response = proxyRequest('github', $apiPath, $headers);
    logResponse($response['status_code'], $response['body']);

    if ($response['status_code'] !== 200) {
        logError("Failed to get commit details: HTTP " . $response['status_code']);
        return null;
    }

    if (empty($response['body'])) {
        logError("Empty response body");
        return null;
    }

    $data = json_decode($response['body'], true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        logError("JSON parse error: " . json_last_error_msg());
        return null;
    }

    $files = [];
    $totalAdditions = 0;
    $totalDeletions = 0;

    if (isset($data['files'])) {
        foreach ($data['files'] as $file) {
            $files[] = [
                'filename' => $file['filename'] ?? 'unknown',
                'status' => $file['status'] ?? 'modified',
                'additions' => $file['additions'] ?? 0,
                'deletions' => $file['deletions'] ?? 0,
                'changes' => $file['changes'] ?? 0,
                'patch' => $file['patch'] ?? null
            ];
            $totalAdditions += $file['additions'] ?? 0;
            $totalDeletions += $file['deletions'] ?? 0;
        }
    }

    $commitDetails = [
        'sha' => $data['sha'] ?? $commitSha,
        'message' => $data['commit']['message'] ?? 'No message',
        'author' => $data['commit']['author']['name'] ?? 'Unknown',
        'author_email' => $data['commit']['author']['email'] ?? '',
        'date' => isset($data['commit']['author']['date']) ? date('Y-m-d H:i:s', strtotime($data['commit']['author']['date'])) : '',
        'url' => $data['html_url'] ?? '',
        'files' => $files,
        'stats' => [
            'total_additions' => $totalAdditions,
            'total_deletions' => $totalDeletions,
            'total_changes' => $totalAdditions + $totalDeletions,
            'files_changed' => count($files)
        ]
    ];

    $cachedCommitDetails = $commitDetails;
    return $commitDetails;
}

function isUpdateNeeded($latestSha) {
    if (!file_exists(VERSION_FILE)) {
        logDebug("Version file not found - first update");
        return true;
    }

    $currentSha = trim(@file_get_contents(VERSION_FILE));

    if (empty($currentSha)) {
        logDebug("Version file is empty");
        return true;
    }

    $currentShort = substr($currentSha, 0, 7);
    $latestShort = substr($latestSha, 0, 7);

    logDebug("Version check: Current=$currentShort, Latest=$latestShort");

    if ($currentShort === $latestShort) {
        logDebug("Versions match - no update needed");
        return false;
    }

    logDebug("New version available");
    return true;
}

function downloadRepositoryZip($commitSha) {
    $apiPath = "/repos/" . REPO_USER . "/" . REPO_NAME . "/zipball/" . BRANCH;

    $headers = [
        'User-Agent: PHP-Update-Script',
        'Accept: application/vnd.github.v3+json'
    ];

    if (!empty(GITHUB_TOKEN)) {
        $headers[] = 'Authorization: token ' . GITHUB_TOKEN;
    }

    logRequest($apiPath, $headers);
    $response = proxyRequest('github', $apiPath, $headers);
    logResponse($response['status_code'], null, $response['error'] ?? null);

    if (!$response) {
        logError("No response when downloading ZIP");
        return false;
    }

    if ($response['status_code'] !== 200) {
        logError("Download failed with HTTP " . $response['status_code']);
        return false;
    }

    if (empty($response['body'])) {
        logError("Downloaded file is empty");
        return false;
    }

    $tempFile = tempnam(sys_get_temp_dir(), 'github_') . '.zip';
    logDebug("Saving to temp file: " . $tempFile);

    $bytesWritten = @file_put_contents($tempFile, $response['body']);

    if ($bytesWritten === false) {
        logError("Failed to save ZIP file to disk");
        return false;
    }

    logDebug("ZIP saved: " . $bytesWritten . " bytes");

    $handle = @fopen($tempFile, 'rb');
    if ($handle) {
        $magic = fread($handle, 4);
        fclose($handle);
        if ($magic !== "PK\x03\x04") {
            $preview = @file_get_contents($tempFile, false, null, 0, 200);
            logError("Downloaded file is NOT a valid ZIP (magic bytes: " . bin2hex(substr($magic, 0, 4)) . ")");
            logDebug("File preview: " . substr($preview, 0, 200));
            @unlink($tempFile);
            return false;
        }
        logDebug("ZIP magic bytes valid (PK header confirmed)");
    }

    return $tempFile;
}
