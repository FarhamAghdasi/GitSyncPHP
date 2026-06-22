# GitSyncPHP - GitHub Auto-Update Script for Shared Hosting

## Table of Contents

- [Description](#description)
- [Features](#features)
- [Project Structure](#project-structure)
- [Installation](#installation)
- [Usage](#usage)
- [Configuration](#configuration)
- [Security](#security)
- [Backup Management](#backup-management)
- [Toast Notifications](#toast-notifications)
- [Troubleshooting](#troubleshooting)
- [License](#license)

## Description

This PHP script automatically updates your project directory from a GitHub repository. Designed for shared hosting environments without SSH access. The script is placed in a `/git` folder within your main project and keeps your application synchronized with your GitHub repository.

## Features

- Automatic updates from GitHub
- Automatic backup before updating
- Telegram notifications after successful updates
- Security with secret key and IP whitelist
- Web UI for easy management
- Complete logging of all operations
- Lightweight - minimal dependencies
- Component-based architecture with modular PHP backend
- Dark glassmorphism UI
- Toast notification system

## Project Structure

```
GitSyncPHP/
├── git.php                          (main controller)
├── lib/
│   ├── config.php                   (configuration + constants)
│   ├── logger.php                   (logging functions)
│   ├── github.php                   (GitHub API interaction)
│   ├── updater.php                  (update + backup + extract)
│   ├── telegram.php                 (Telegram notifications)
│   ├── http.php                     (HTTP client)
│   └── security.php                 (security checks)
├── assets/
│   ├── header.php                   (HTML head section)
│   ├── footer.php                   (modal dialogs + script include)
│   ├── style.css                    (dark glass CSS)
│   ├── script.js                    (JavaScript)
│   └── components/
│       ├── header-bar.php           (header section)
│       ├── status-cards.php         (status cards grid)
│       ├── commit-details.php       (commit details table)
│       ├── update-banner.php        (update notification banner)
│       ├── operations-card.php      (operations panel)
│       ├── backups-card.php         (backups list)
│       └── log-card.php             (log viewer)
├── .env
├── .env.example
└── README.md
```

## Installation

### Step 1: Copy Files to /git Folder

Copy the entire GitSyncPHP folder contents to your project's `/git` directory:

```
your-project/
├── /git/
│   ├── git.php
│   ├── lib/
│   │   ├── config.php
│   │   ├── logger.php
│   │   ├── github.php
│   │   ├── updater.php
│   │   ├── telegram.php
│   │   ├── http.php
│   │   └── security.php
│   ├── assets/
│   │   ├── header.php
│   │   ├── footer.php
│   │   ├── style.css
│   │   ├── script.js
│   │   └── components/
│   └── .env.example
├── /your-app-files/
└── index.php
```

### Step 2: Configure .env File

```bash
cd your-project/git/
cp .env.example .env
nano .env
```

### Step 3: Security Setup

Create a secret key file for secure access:

```bash
echo "your-secure-random-key-here" > .update_key
```

## Usage

### Web Browser

Access the update panel:

```
https://yourdomain.com/your-project/git/git.php?key=your-secure-key
```

From the web interface you can:
- Check for updates
- View current version
- View commit history
- Run updates manually
- Manage backups
- View logs
- Change settings

### CLI Execution

```bash
cd /path/to/your-project/git/
php git.php
```

### Cron Job Automation

**Every hour:**
```bash
0 * * * * /usr/bin/php /path/to/your-project/git/git.php > /dev/null 2>&1
```

**Daily at 3 AM:**
```bash
0 3 * * * /usr/bin/php /path/to/your-project/git/git.php > /dev/null 2>&1
```

## Configuration

### .env Settings

```env
# GitHub Repository Configuration
GITHUB_TOKEN=ghp_your_github_token_here
REPO_USER=your-github-username
REPO_NAME=your-repository-name
BRANCH=main

# Telegram Notifications (Optional)
TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
TELEGRAM_CHAT_ID=-1001234567890

# Backup Settings
BACKUP_BEFORE_UPDATE=true
BACKUP_DIR=__backups
DELETE_EXTRACTED_FILES=true

# Files & Paths
LOG_FILE=update_log.txt
VERSION_FILE=.version
EXCLUDE_FILES=git,.env,.update_key,.ip_whitelist,__backups,*.log,update_log.txt
```

## Security

### Secret Key Method

Create a `.update_key` file with a random secure string:

```bash
openssl rand -base64 32 > .update_key
```

**Access with key:**
```
https://yourdomain.com/path/to/git/git.php?key=your-secure-key
```

### IP Whitelist Method

Create an `.ip_whitelist` file:

```bash
nano .ip_whitelist
```

Add IP addresses (one per line):
```
192.168.1.100
10.0.0.5
203.0.113.50
```

**Note:** You can use both methods together for maximum security.

## Backup Management

Backups are automatically created in the `__backups/` directory before each update.

**Backup Features:**
- Automatic creation before updates
- ZIP format compression
- Timestamped filenames
- Secure with .htaccess protection
- Delete All button to remove all backups at once

## Toast Notifications

All browser alerts have been replaced with modern toast notifications:

- Success notifications (green)
- Error notifications (red)
- Warning notifications (orange)
- Info notifications (blue)

Toast features:
- Animated slide-in and fade-out
- Auto-dismiss after 4 seconds
- Manual close button
- Click to dismiss

## Troubleshooting

### 401 Unauthorized

**Cause:** Invalid or expired GitHub token

**Solution:**
```bash
curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/user
```

### 403 Forbidden

**Cause:** Rate limit exceeded or insufficient permissions

**Solution:**
```bash
curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/rate_limit
```

### 404 Not Found

**Cause:** Repository doesn't exist or wrong name

**Solution:** Verify `REPO_USER` and `REPO_NAME` in .env file

### ZipArchive Not Available

**Solution:**
```bash
# Ubuntu/Debian
sudo apt-get install php-zip
sudo service apache2 restart

# CentOS/RHEL
sudo yum install php-pecl-zip
sudo service httpd restart
```

### cURL Not Available

**Solution:**
```bash
# Ubuntu/Debian
sudo apt-get install php-curl
sudo service apache2 restart

# CentOS/RHEL
sudo yum install php-curl
sudo service httpd restart
```

## License

This project is released under the MIT License. See the [LICENSE](LICENSE) file for details.

---

**Made with for the PHP Community**
