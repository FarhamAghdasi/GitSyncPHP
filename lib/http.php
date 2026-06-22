<?php
/**
 * HTTP Client Module
 * Handles direct and proxied HTTP requests via cURL or fopen.
 */

function proxyRequest($target, $path, $headers = [], $method = 'GET', $postData = null, $queryParams = null) {
    if (!USE_PROXY || empty(PROXY_URL)) {
        logDebug("Using DIRECT request");
        return fetchUrlDirect($target, $path, $headers, $method, $postData, $queryParams);
    }

    logDebug("Using PROXY request: " . PROXY_URL);

    $proxyUrl = PROXY_URL . '?target=' . urlencode($target) . '&path=' . urlencode($path);

    if ($queryParams) {
        $proxyUrl .= '&query=' . urlencode($queryParams);
    }

    if ($target === 'telegram') {
        $token = '';
        foreach ($headers as $header) {
            if (stripos($header, 'token:') !== false) {
                $token = trim(substr($header, strpos($header, ':') + 1));
                break;
            }
        }
        if ($token) {
            $proxyUrl .= '&token=' . urlencode($token);
        }
    }

    $proxyHeaders = [];

    if ($target === 'github') {
        $authHeader = '';
        foreach ($headers as $header) {
            if (stripos($header, 'Authorization:') !== false) {
                $authHeader = $header;
                break;
            }
        }
        if ($authHeader) {
            $proxyHeaders[] = $authHeader;
        }

        foreach ($headers as $header) {
            if (stripos($header, 'User-Agent:') !== false ||
                stripos($header, 'Accept:') !== false) {
                $proxyHeaders[] = $header;
            }
        }
    }

    foreach ($headers as $header) {
        if (stripos($header, 'Content-Type:') !== false) {
            $proxyHeaders[] = $header;
        }
    }

    $hasUserAgent = false;
    foreach ($proxyHeaders as $header) {
        if (stripos($header, 'User-Agent:') !== false) {
            $hasUserAgent = true;
            break;
        }
    }
    if (!$hasUserAgent) {
        $proxyHeaders[] = 'User-Agent: PHP-Update-Script-Proxy';
    }

    logDebug("Proxy URL: " . PROXY_URL . '?target=' . $target . '&path=' . $path);

    return fetchUrl($proxyUrl, $proxyHeaders, $method, $postData);
}

function fetchUrlDirect($target, $path, $headers = [], $method = 'GET', $postData = null, $queryParams = null) {
    $baseUrls = [
        'github' => 'https://api.github.com',
        'telegram' => 'https://api.telegram.org'
    ];

    $baseUrl = $baseUrls[$target] ?? '';
    if (empty($baseUrl)) {
        return ['body' => null, 'status_code' => 0, 'error' => 'Invalid target'];
    }

    $url = $baseUrl . $path;
    if ($queryParams) {
        $url .= '?' . $queryParams;
    }

    return fetchUrl($url, $headers, $method, $postData);
}

function fetchUrl($url, $headers = [], $method = 'GET', $postData = null) {
    $result = ['body' => null, 'status_code' => 0, 'error' => null, 'headers' => []];

    if (function_exists('curl_init')) {
        $ch = curl_init();
        if (!$ch) {
            $result['error'] = 'curl_init failed';
            return $result;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-Update-Script');
        curl_setopt($ch, CURLOPT_HEADER, 1);

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if ($method === 'POST' && $postData !== null) {
            curl_setopt($ch, CURLOPT_POST, 1);
            if (is_array($postData)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
                $hasContentType = false;
                foreach ($headers as $header) {
                    if (stripos($header, 'Content-Type:') !== false) {
                        $hasContentType = true;
                        break;
                    }
                }
                if (!$hasContentType) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
                }
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            }
        }

        $response = curl_exec($ch);

        if ($response === false) {
            $result['error'] = curl_error($ch);
        } else {
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headerStr = substr($response, 0, $headerSize);
            $result['body'] = substr($response, $headerSize);
            $result['status_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $headersArray = explode("\r\n", $headerStr);
            foreach ($headersArray as $header) {
                if (strpos($header, ':') !== false) {
                    list($key, $value) = explode(':', $header, 2);
                    $result['headers'][trim($key)] = trim($value);
                }
            }
        }

        curl_close($ch);
        unset($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $contextOptions = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'timeout' => 60,
                'ignore_errors' => true
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ];

        if ($method === 'POST' && $postData !== null) {
            if (is_array($postData)) {
                $contextOptions['http']['content'] = json_encode($postData);
                $contextOptions['http']['header'] .= "\r\nContent-Type: application/json";
            } else {
                $contextOptions['http']['content'] = $postData;
            }
        }

        $context = stream_context_create($contextOptions);
        $result['body'] = @file_get_contents($url, false, $context);

        if ($result['body'] !== false && isset($http_response_header[0])) {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
            if (isset($matches[1])) {
                $result['status_code'] = (int)$matches[1];
            }

            foreach ($http_response_header as $header) {
                if (strpos($header, ':') !== false) {
                    list($key, $value) = explode(':', $header, 2);
                    $result['headers'][trim($key)] = trim($value);
                }
            }
        } else {
            $result['error'] = 'file_get_contents failed';
        }
    } else {
        $result['error'] = 'No HTTP method available';
    }

    return $result;
}
