<?php
/**
 * Minimal inline SVG icon set (Lucide-style, stroke-based).
 * Self-contained so the panel has no external icon-font/CDN dependency.
 */

function icon($name, $size = 18, $class = '') {
    $paths = [
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'github' => '<path d="M9 19c-4.3 1.4-4.3-2.5-6-3m12 5v-3.5c0-1 .1-1.4-.5-2 2.8-.3 5.5-1.4 5.5-6a4.6 4.6 0 0 0-1.3-3.2 4.2 4.2 0 0 0-.1-3.2s-1.1-.3-3.5 1.3a12.3 12.3 0 0 0-6.2 0C6.5 2.8 5.4 3.1 5.4 3.1a4.2 4.2 0 0 0-.1 3.2A4.6 4.6 0 0 0 4 9.5c0 4.6 2.7 5.7 5.5 6-.6.6-.6 1.2-.5 2V21"/>',
        'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'terminal' => '<polyline points="4 6 9 12 4 18"/><line x1="12" y1="18" x2="20" y2="18"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.9 2.9l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.9-2.9l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.9-2.9l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.6V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.9 2.9l-.1.1a1.7 1.7 0 0 0-.3 1.9V9c.1.7.6 1.3 1.6 1.4h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.6 1z"/>',
        'bell' => '<path d="M6 8a6 6 0 1 1 12 0c0 6 2 8 2 8H4s2-2 2-8"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
        'user' => '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20.5c1.4-3.6 4.2-5.5 7.5-5.5s6.1 1.9 7.5 5.5"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="M8.3 12.4l2.4 2.4 5-5.2"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="16" rx="2"/><line x1="16" y1="3" x2="16" y2="7"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="3.5" y1="10" x2="20.5" y2="10"/>',
        'sync' => '<path d="M20 11A8 8 0 0 0 5.3 7"/><polyline points="5 4 5.3 7 8.3 6.6"/><path d="M4 13a8 8 0 0 0 14.7 4"/><polyline points="19 20 18.7 17 15.7 17.4"/>',
        'branch' => '<circle cx="6" cy="5" r="2.2"/><circle cx="6" cy="19" r="2.2"/><circle cx="18" cy="12" r="2.2"/><path d="M6 7.2V16.8"/><path d="M6 8c0 4.5 3.5 4 8.5 4.9 1.6.3 3.5.9 3.5-.9"/>',
        'commit' => '<circle cx="12" cy="12" r="3"/><line x1="1" y1="12" x2="9" y2="12"/><line x1="15" y1="12" x2="23" y2="12"/>',
        'external-link' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/>',
        'x' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'trash' => '<polyline points="3.5 6 5.5 6 21 6"/><path d="M8.5 6V4a1.5 1.5 0 0 1 1.5-1.5h4A1.5 1.5 0 0 1 15.5 4v2m2 0-.7 13.1a2 2 0 0 1-2 1.9H9.2a2 2 0 0 1-2-1.9L6.5 6"/><line x1="10" y1="10.5" x2="10" y2="16"/><line x1="14" y1="10.5" x2="14" y2="16"/>',
        'download' => '<path d="M12 3v12"/><polyline points="7 10.5 12 15.5 17 10.5"/><path d="M4.5 19h15"/>',
        'refresh' => '<polyline points="20 4 20 10 14 10"/><path d="M20.5 14a8.5 8.5 0 1 1-2.2-8.2L20 10"/>',
        'lock' => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/>',
        'package' => '<path d="M21 8.5v7a1.8 1.8 0 0 1-.9 1.6l-7 4a1.8 1.8 0 0 1-1.8 0l-7-4A1.8 1.8 0 0 1 3 15.5v-7a1.8 1.8 0 0 1 .9-1.6l7-4a1.8 1.8 0 0 1 1.8 0l7 4a1.8 1.8 0 0 1 .9 1.6z"/><polyline points="3.3 7 12 12 20.7 7"/><line x1="12" y1="22" x2="12" y2="12"/>',
        'archive' => '<rect x="3" y="4" width="18" height="4.5" rx="1"/><path d="M4.5 8.5V19a1.5 1.5 0 0 0 1.5 1.5h12a1.5 1.5 0 0 0 1.5-1.5V8.5"/><line x1="10" y1="12.5" x2="14" y2="12.5"/>',
        'alert-triangle' => '<path d="M12 4 2.5 20.5h19L12 4z"/><line x1="12" y1="10.5" x2="12" y2="14.5"/><circle cx="12" cy="17.3" r="0.4" fill="currentColor" stroke="none"/>',
        'chevron-right' => '<polyline points="9 6 15 12 9 18"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M3 3l18 18"/><path d="M10.6 5.2A9.9 9.9 0 0 1 12 5c6.5 0 10 7 10 7a17.9 17.9 0 0 1-3.2 4.2M6.6 6.6A17.5 17.5 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 4.4-1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
    ];

    $inner = $paths[$name] ?? $paths['shield'];

    return '<svg class="icon icon-' . htmlspecialchars($name) . ($class ? ' ' . htmlspecialchars($class) : '') . '" '
        . 'width="' . (int)$size . '" height="' . (int)$size . '" viewBox="0 0 24 24" '
        . 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
        . $inner . '</svg>';
}
