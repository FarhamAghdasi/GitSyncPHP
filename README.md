# GitSyncPHP - GitHub Auto-Update Script for Shared Hosting

## Table of Contents

- [Description](#description)
- [Features](#features)
- [Installation](#installation)
  - [Step 1: Copy Files to /git Folder](#step-1-copy-files-to-git-folder)
  - [Step 2: Configure .env File](#step-2-configure-env-file)
  - [Step 3: Security Setup](#step-3-security-setup)
- [Usage Scenarios](#usage-scenarios)
  - [Scenario 1: Subdirectory of Main Project](#scenario-1-subdirectory-of-main-project)
  - [Scenario 2: Root of Subdomain](#scenario-2-root-of-subdomain)
  - [Scenario 3: Subfolder of Public HTML](#scenario-3-subfolder-of-public-html)
- [Configuration](#configuration)
  - [.env Settings](#env-settings)
  - [GitHub Token](#github-token)
  - [Telegram Setup](#telegram-setup)
- [Security](#security)
  - [Secret Key Method](#secret-key-method)
  - [IP Whitelist Method](#ip-whitelist-method)
- [Usage](#usage)
  - [Web Browser](#web-browser)
  - [CLI Execution](#cli-execution)
  - [Cron Job Automation](#cron-job-automation)
- [Backup Management](#backup-management)
- [Logs](#logs)
- [Troubleshooting](#troubleshooting)
- [License](#license)
- [Contributing](#contributing)
- [Contact](#contact)

## Description

This PHP script automatically updates your project directory from a GitHub repository. Designed for shared hosting environments without SSH access. The script is placed in a `/git` folder within your main project and keeps your application synchronized with your GitHub repository.

## Features

- 🚀 **Automatic updates** from GitHub
- 💾 **Automatic backup** before updating
- 📱 **Telegram notifications** after successful updates
- 🔒 **Security** with secret key and IP whitelist
- 🌐 **Web UI** for easy management
- 📜 **Complete logging** of all operations
- ⚡ **Lightweight** - minimal dependencies

## Installation

### Step 1: Copy Files to /git Folder

Copy the entire GitSyncPHP folder contents to your project's `/git` directory:

```
your-project/
├── /git/
│   ├── git.php
│   ├── .env.example
│   └── .gitignore
├── /your-app-files/
└── index.php
```

### Step 2: Configure .env File

```bash
# Navigate to git directory
cd your-project/git/

# Copy template file
cp .env.example .env

# Edit the configuration
nano .env
```

### Step 3: Security Setup

Create a secret key file for secure access:

```bash
# Generate a secure random key
echo "your-secure-random-key-here" > .update_key
```

## Usage Scenarios

### Scenario 1: Subdirectory of Main Project

Your main application is at the root, and GitSyncPHP is in `/git`:

```
/var/www/html/your-project/
├── /git/
│   ├── git.php
│   ├── .env
│   ├── .update_key
│   └── /__backups/
├── /src/
├── /public/
├── index.php
└── .env
```

**Access URL:**
```
https://yourdomain.com/your-project/git/git.php?key=your-secure-key
```

### Scenario 2: Root of Subdomain

The entire subdomain is dedicated to auto-updates:

```
/var/www/html/updates.yourdomain.com/
├── /git/
│   ├── git.php
│   ├── .env
│   ├── .update_key
│   └── /__backups/
├── index.php (optional redirect)
└── .htaccess
```

**Access URL:**
```
https://updates.yourdomain.com/git/git.php?key=your-secure-key
```

**Recommended .env configuration:**
```env
TARGET_DIR=/var/www/html/updates.yourdomain.com
SCRIPT_DIR=/var/www/html/updates.yourdomain.com/git
```

### Scenario 3: Subfolder of Public HTML

Shared hosting with public_html folder:

```
/home/username/public_html/
├── /your-app/
│   ├── /git/
│   │   ├── git.php
│   │   ├── .env
│   │   ├── .update_key
│   │   └── /__backups/
│   ├── /application/
│   └── index.php
```

**Access URL:**
```
https://yourdomain.com/your-app/git/git.php?key=your-secure-key
```

## Configuration

### .env Settings

```env
# ==========================================
# GitHub Repository Configuration
# ==========================================
GITHUB_TOKEN=ghp_your_github_token_here
REPO_USER=your-github-username
REPO_NAME=your-repository-name
BRANCH=main

# ==========================================
# Telegram Notifications (Optional)
# ==========================================
TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
TELEGRAM_CHAT_ID=-1001234567890

# ==========================================
# Backup Settings
# ==========================================
BACKUP_BEFORE_UPDATE=true
BACKUP_DIR=__backups
DELETE_EXTRACTED_FILES=true

# ==========================================
# Files & Paths
# ==========================================
LOG_FILE=update_log.txt
VERSION_FILE=.version
EXCLUDE_FILES=git,.env,.update_key,.ip_whitelist,__backups,*.log,update_log.txt
```

### GitHub Token

1. Go to [GitHub Settings → Personal Access Tokens](https://github.com/settings/tokens)
2. Click **Generate new token (classic)**
3. Set expiration date
4. Select `repo` scope for private repositories or `public_repo` for public repositories
5. Copy the generated token

### Telegram Setup

**Get Bot Token:**
1. Open Telegram and search for @BotFather
2. Send `/newbot` command
3. Follow instructions to create a new bot
4. Copy the bot token

**Get Chat ID:**
1. Search for @userinfobot on Telegram
2. Start a conversation
3. Your Chat ID will be displayed

## Security

### Secret Key Method

Create a `.update_key` file with a random secure string:

```bash
# Generate a random key
openssl rand -base64 32 > .update_key

# Or manually create
echo "complex-random-string-at-least-32-chars" > .update_key
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
# Navigate to git directory
cd /path/to/your-project/git/

# Run update
php git.php

# Check logs
cat update_log.txt
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

**Weekly on Sundays at midnight:**
```bash
0 0 * * 0 /usr/bin/php /path/to/your-project/git/git.php > /dev/null 2>&1
```

## Backup Management

Backups are automatically created in the `__backups/` directory before each update.

**Backup Features:**
- Automatic creation before updates
- ZIP format compression
- Timestamped filenames
- Secure with .htaccess protection

**Backup Structure:**
```
git/
├── /__backups/
│   ├── .htaccess
│   ├── backup_2024-01-15_120000.zip
│   ├── backup_2024-01-16_120000.zip
│   └── backup_2024-01-17_120000.zip
```

**Download/Restore:**
1. Access web UI
2. Go to Backups section
3. Click download to save locally
4. Extract ZIP to restore files

## Logs

All operations are logged in `update_log.txt`:

```bash
# View complete log
cat update_log.txt

# View last 50 lines
tail -50 update_log.txt

# View in real-time
tail -f update_log.txt

# Filter for errors only
grep "\[ERROR\]" update_log.txt

# Filter for success only
grep "\[SUCCESS\]" update_log.txt

# Filter for specific date
grep "2024-01-15" update_log.txt
```

**Log Levels:**
- `[INFO]` - General information
- `[SUCCESS]` - Successful operations
- `[WARNING]` - Warnings (non-critical)
- `[ERROR]` - Errors (action may have failed)
- `[DEBUG]` - Debug information

## Troubleshooting

### 401 Unauthorized

**Cause:** Invalid or expired GitHub token

**Solution:**
```bash
# Check token validity
curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/user
```

### 403 Forbidden

**Cause:** Rate limit exceeded or insufficient permissions

**Solution:**
```bash
# Check rate limit
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

### Update Not Working

**Checklist:**
1. Verify .env file exists and has correct settings
2. Check file permissions (git directory should be writable)
3. Review update_log.txt for errors
4. Test GitHub API access manually
5. Ensure PHP has write permissions to target directory

## License

This project is released under the MIT License. See the [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## Contact

- **Author:** farhamaghdasi
- **GitHub:** [@farhamaghdasi](https://github.com/farhamaghdasi)
- **Repository:** [GitSyncPHP](https://github.com/farhamaghdasi/GitSyncPHP)

---

**Made with ❤️ for the PHP Community**
