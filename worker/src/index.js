// ============================================
// CLOUDFLARE WORKER - API PROXY (FIXED)
// ============================================

// ============================================
// CONFIGURATION
// ============================================

const ALLOWED_ENDPOINTS = {
    github: 'https://api.github.com',
    telegram: 'https://api.telegram.org'
};

// ============================================
// MAIN HANDLER
// ============================================

export default {
    async fetch(request, env, ctx) {
        try {
            const url = new URL(request.url);
            const target = url.searchParams.get('target') || '';
            const path = url.searchParams.get('path') || '';
            const method = request.method;
            
            // Get headers from original request
            const requestHeaders = {};
            for (const [key, value] of request.headers) {
                requestHeaders[key.toLowerCase()] = value;
            }
            
            // ============================================
            // ROUTE: GitHub API
            // ============================================
            if (target === 'github') {
                // Build GitHub API URL
                const githubUrl = ALLOWED_ENDPOINTS.github + path;
                
                // Prepare headers
                const headers = new Headers();
                headers.set('Accept', 'application/vnd.github.v3+json');
                headers.set('User-Agent', 'Cloudflare-Worker-Proxy');
                
                // Forward authorization header if present
                if (requestHeaders['authorization']) {
                    headers.set('Authorization', requestHeaders['authorization']);
                }
                
                // Forward query parameters
                const queryString = url.searchParams.get('query') || '';
                const fullUrl = githubUrl + (queryString ? '?' + queryString : '');
                
                console.log('🔀 Proxy GitHub Request:', {
                    url: fullUrl,
                    method: method,
                    headers: Object.fromEntries(headers)
                });
                
                // Make request to GitHub
                const response = await fetch(fullUrl, {
                    method: method,
                    headers: headers,
                    body: method !== 'GET' ? request.body : undefined
                });
                
                // Get response
                const contentType = response.headers.get('content-type') || '';
                const isBinary = contentType.includes('zip') || contentType.includes('octet-stream') || contentType.includes('gzip') || contentType.includes('binary');

                let responseData;
                let returnHeaders = {
                    'Access-Control-Allow-Origin': '*'
                };

                if (isBinary) {
                    responseData = await response.arrayBuffer();
                    returnHeaders['Content-Type'] = contentType || 'application/octet-stream';
                    returnHeaders['Content-Length'] = response.headers.get('content-length') || String(responseData.byteLength);
                } else {
                    responseData = await response.text();
                    returnHeaders['Content-Type'] = contentType || 'application/json';
                }

                console.log('📡 GitHub Response:', {
                    status: response.status,
                    statusText: response.statusText,
                    contentType: contentType,
                    isBinary: isBinary,
                    bodyType: isBinary ? 'arrayBuffer' : 'text'
                });
                
                // Return response
                return new Response(responseData, {
                    status: response.status,
                    headers: returnHeaders
                });
            }
            
            // ============================================
            // ROUTE: Telegram API
            // ============================================
            if (target === 'telegram') {
                const token = url.searchParams.get('token') || '';
                const pathParam = url.searchParams.get('path') || '';
                
                if (!token || !pathParam) {
                    return new Response(
                        JSON.stringify({
                            error: 'Missing token or path parameters'
                        }),
                        {
                            status: 400,
                            headers: { 'Content-Type': 'application/json' }
                        }
                    );
                }
                
                const telegramUrl = `${ALLOWED_ENDPOINTS.telegram}/bot${token}${pathParam}`;
                
                console.log('🔀 Proxy Telegram Request:', {
                    url: telegramUrl,
                    method: method
                });
                
                // Get body for POST
                let body = null;
                if (method === 'POST') {
                    body = await request.text();
                }
                
                // Make request to Telegram
                const response = await fetch(telegramUrl, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: body
                });
                
                const responseData = await response.text();
                
                return new Response(responseData, {
                    status: response.status,
                    headers: {
                        'Content-Type': 'application/json',
                        'Access-Control-Allow-Origin': '*'
                    }
                });
            }
            
            // ============================================
            // ROUTE: Health Check
            // ============================================
            if (target === 'health') {
                return new Response(
                    JSON.stringify({
                        status: 'ok',
                        timestamp: new Date().toISOString(),
                        version: '1.1.0',
                        endpoints: Object.keys(ALLOWED_ENDPOINTS)
                    }),
                    {
                        status: 200,
                        headers: { 'Content-Type': 'application/json' }
                    }
                );
            }
            
            // ============================================
            // DEFAULT: Invalid target
            // ============================================
            return new Response(
                JSON.stringify({
                    error: 'Invalid target parameter',
                    usage: {
                        github: '?target=github&path=/repos/user/repo/commits/main',
                        telegram: '?target=telegram&token=BOT_TOKEN&path=/sendMessage',
                        health: '?target=health'
                    }
                }),
                {
                    status: 400,
                    headers: { 'Content-Type': 'application/json' }
                }
            );
            
        } catch (error) {
            console.error('❌ Worker Error:', error);
            return new Response(
                JSON.stringify({
                    error: 'Internal Server Error',
                    message: error.message
                }),
                {
                    status: 500,
                    headers: { 'Content-Type': 'application/json' }
                }
            );
        }
    }
};