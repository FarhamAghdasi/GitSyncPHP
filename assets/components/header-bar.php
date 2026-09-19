<!-- Hero -->
<div class="hero-card section-gap">
    <div class="hero-left">
        <div class="hero-icon"><?php echo icon('github', 24); ?></div>
        <div class="hero-text">
            <h1>GitHub Auto-Update</h1>
            <p>Automatically check for updates from your GitHub repository and keep your system up to date.</p>
            <div class="repo-pill">
                <?php echo icon('github', 14); ?>
                <span class="repo-name"><?php echo htmlspecialchars(REPO_USER . '/' . REPO_NAME); ?></span>
                <span class="pill-sep"></span>
                <span class="branch-tag"><?php echo icon('branch', 12); ?> <?php echo htmlspecialchars(BRANCH); ?></span>
            </div>
        </div>
    </div>
    <div class="hero-visual" aria-hidden="true">
        <svg viewBox="0 0 220 100" width="100%" height="100%" fill="none">
            <defs>
                <linearGradient id="heroGridGrad" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.5"/>
                    <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0.25"/>
                </linearGradient>
            </defs>
            <circle cx="110" cy="50" r="46" stroke="#3b82f61a" stroke-width="1"/>
            <circle cx="110" cy="50" r="32" stroke="#3b82f629" stroke-width="1"/>
            <ellipse cx="110" cy="50" rx="46" ry="18" stroke="#8b5cf633" stroke-width="1"/>
            <rect x="86" y="30" width="26" height="26" rx="5" fill="url(#heroGridGrad)" opacity="0.7" transform="rotate(-8 99 43)"/>
            <rect x="108" y="42" width="20" height="20" rx="4" fill="#3b82f6" opacity="0.35" transform="rotate(10 118 52)"/>
            <circle cx="156" cy="30" r="3" fill="#22d3ee"/>
            <circle cx="60" cy="70" r="2.4" fill="#8b5cf6"/>
            <circle cx="168" cy="66" r="2" fill="#3b82f6"/>
            <path d="M110 50 L156 30 M110 50 L60 70 M110 50 L168 66" stroke="#3b82f61f" stroke-width="1"/>
        </svg>
    </div>
</div>
